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
            $table->string('flag', 10)->nullable()->after('country');
        });

        // Populate flag from country code — most are just lowercased, except EN → gb-eng
        $flagMap = [
            'EN' => 'gb-eng',
        ];

        $countries = DB::table('competitions')->distinct()->pluck('country');

        foreach ($countries as $country) {
            $flag = $flagMap[$country] ?? strtolower($country);
            DB::table('competitions')->where('country', $country)->update(['flag' => $flag]);
        }
    }

    public function down(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->dropColumn('flag');
        });
    }
};
