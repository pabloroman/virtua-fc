<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_summaries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('team_id')->constrained();
            $table->string('competition_id', 10);
            $table->foreign('competition_id')->references('id')->on('competitions');
            $table->string('result_label');
            $table->json('your_record');
            $table->json('summary_data');
            $table->date('tournament_date');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_summaries');
    }
};
