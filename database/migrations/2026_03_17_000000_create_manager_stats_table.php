<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manager_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('matches_played')->default(0);
            $table->unsignedInteger('matches_won')->default(0);
            $table->unsignedInteger('matches_drawn')->default(0);
            $table->unsignedInteger('matches_lost')->default(0);
            $table->decimal('win_percentage', 5, 2)->default(0);
            $table->unsignedInteger('current_unbeaten_streak')->default(0);
            $table->unsignedInteger('longest_unbeaten_streak')->default(0);
            $table->unsignedInteger('seasons_completed')->default(0);
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manager_stats');
    }
};
