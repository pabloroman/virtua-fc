<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('fifa_code', 10)->nullable()->index()->after('type');
            $table->boolean('is_placeholder')->default(false)->after('fifa_code');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['fifa_code', 'is_placeholder']);
        });
    }
};
