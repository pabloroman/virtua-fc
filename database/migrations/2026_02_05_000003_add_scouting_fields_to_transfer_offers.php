<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfer_offers', function (Blueprint $table) {
            $table->string('direction')->default('outgoing')->after('status'); // outgoing = user selling, incoming = user buying
            $table->uuid('selling_team_id')->nullable()->after('offering_team_id');
            $table->bigInteger('asking_price')->nullable()->after('transfer_fee'); // AI's asking price for reference
            $table->bigInteger('offered_wage')->nullable()->after('asking_price'); // Wage offered in incoming transfer

            $table->foreign('selling_team_id')
                ->references('id')
                ->on('teams');
        });
    }

    public function down(): void
    {
        Schema::table('transfer_offers', function (Blueprint $table) {
            $table->dropForeign(['selling_team_id']);
            $table->dropColumn(['direction', 'selling_team_id', 'asking_price', 'offered_wage']);
        });
    }
};
