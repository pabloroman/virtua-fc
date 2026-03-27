<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invite_codes', function (Blueprint $table) {
            $table->boolean('grants_career')->default(false);
            $table->boolean('grants_tournament')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('invite_codes', function (Blueprint $table) {
            $table->dropColumn(['grants_career', 'grants_tournament']);
        });
    }
};
