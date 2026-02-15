<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('cup_round_templates');
    }

    public function down(): void
    {
        Schema::create('cup_round_templates', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
            $table->string('competition_id', 10);
            $table->string('season', 10);
            $table->unsignedTinyInteger('round_number');
            $table->string('round_name');
            $table->enum('type', ['one_leg', 'two_leg']);
            $table->date('first_leg_date');
            $table->date('second_leg_date')->nullable();
            $table->unsignedSmallInteger('teams_entering')->default(0);

            $table->foreign('competition_id')->references('id')->on('competitions');
            $table->unique(['competition_id', 'season', 'round_number']);
        });
    }
};
