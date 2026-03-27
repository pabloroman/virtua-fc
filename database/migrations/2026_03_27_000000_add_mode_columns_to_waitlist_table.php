<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('waitlist', function (Blueprint $table) {
            $table->boolean('wants_career')->default(true);
            $table->boolean('wants_tournament')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('waitlist', function (Blueprint $table) {
            $table->dropColumn(['wants_career', 'wants_tournament']);
        });
    }
};
