<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->string('scope', 20)->default('domestic')->after('role');
        });

        // Derive scope from existing role values
        DB::table('competitions')
            ->where('role', 'european')
            ->update(['scope' => 'continental']);
    }

    public function down(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->dropColumn('scope');
        });
    }
};
