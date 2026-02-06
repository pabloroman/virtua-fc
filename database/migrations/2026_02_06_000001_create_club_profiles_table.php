<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('team_id')->unique();
            $table->string('reputation_level'); // elite, continental, established, modest, local
            $table->bigInteger('commercial_revenue')->default(0); // cents per season
            $table->timestamps();

            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_profiles');
    }
};
