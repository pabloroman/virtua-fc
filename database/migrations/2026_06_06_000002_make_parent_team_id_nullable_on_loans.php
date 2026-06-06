<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A loan whose owning club is not part of the game has no parent team:
        // the player becomes a free agent when the loan ends. returnLoan()
        // already turns a null parent into team_id = null (free agent), so the
        // only thing missing was the column allowing null.
        Schema::table('loans', function (Blueprint $table) {
            $table->uuid('parent_team_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->uuid('parent_team_id')->nullable(false)->change();
        });
    }
};
