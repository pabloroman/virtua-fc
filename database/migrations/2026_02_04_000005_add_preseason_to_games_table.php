<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->boolean('is_preseason')->default(false)->after('cup_eliminated');
            $table->unsignedTinyInteger('preseason_week')->default(0)->after('is_preseason');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['is_preseason', 'preseason_week']);
        });
    }
};
