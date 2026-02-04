<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['income', 'expense']);
            $table->string('category'); // transfer_in, transfer_out, wage, tv_rights, etc.
            $table->bigInteger('amount'); // In cents, always positive
            $table->string('description');
            $table->foreignUuid('related_player_id')->nullable()->constrained('game_players')->nullOnDelete();
            $table->date('transaction_date');
            $table->timestamps();

            $table->index(['game_id', 'transaction_date']);
            $table->index(['game_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_transactions');
    }
};
