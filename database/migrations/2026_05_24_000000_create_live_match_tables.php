<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Live match sessions live on the control plane — see CLAUDE.md
        // "Control plane / tenant plane". A live duel doesn't belong to either
        // user's tenant Game; squads are snapshotted at team-pick time and
        // the match runs entirely on those snapshots.
        Schema::create('live_match_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('phase', 20)->default('lobby');
            $table->foreignId('host_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('guest_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->char('host_iso_code', 3)->nullable();
            $table->char('guest_iso_code', 3)->nullable();
            // source_game_id columns intentionally have no FK — games is a
            // tenant-plane table and a duel must survive deletion of either
            // user's save.
            $table->uuid('host_source_game_id')->nullable();
            $table->uuid('guest_source_game_id')->nullable();
            $table->jsonb('host_squad')->nullable();
            $table->jsonb('guest_squad')->nullable();
            $table->string('match_seed', 64);
            $table->smallInteger('current_minute')->default(0);
            $table->smallInteger('home_score')->default(0);
            $table->smallInteger('away_score')->default(0);
            $table->jsonb('context_state')->nullable();
            $table->jsonb('event_log')->default('[]');
            $table->boolean('host_bot')->default(false);
            $table->boolean('guest_bot')->default(false);
            $table->string('pause_reason', 30)->nullable();
            $table->boolean('pause_acked_by_host')->default(false);
            $table->boolean('pause_acked_by_guest')->default(false);
            $table->timestampTz('paused_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->integer('clock_version')->default(0);
            $table->timestampsTz();

            $table->index('phase');
            $table->index('host_user_id');
            $table->index('guest_user_id');
        });

        // DB-side default for UUID so bulk inserts also get one.
        DB::statement('ALTER TABLE live_match_sessions ALTER COLUMN id SET DEFAULT gen_random_uuid()');

        Schema::create('live_match_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('session_id')->constrained('live_match_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('side', 5);
            $table->string('action_type', 20);
            $table->jsonb('payload');
            $table->smallInteger('queued_at_minute');
            $table->smallInteger('applied_at_minute')->nullable();
            $table->string('status', 15)->default('queued');
            $table->string('reject_reason', 80)->nullable();
            $table->timestampsTz();

            $table->index(['session_id', 'status']);
        });

        DB::statement('ALTER TABLE live_match_actions ALTER COLUMN id SET DEFAULT gen_random_uuid()');
    }

    public function down(): void
    {
        Schema::dropIfExists('live_match_actions');
        Schema::dropIfExists('live_match_sessions');
    }
};
