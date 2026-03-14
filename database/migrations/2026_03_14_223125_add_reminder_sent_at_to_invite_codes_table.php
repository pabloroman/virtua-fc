<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invite_codes', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable()->after('invite_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('invite_codes', function (Blueprint $table) {
            $table->dropColumn('reminder_sent_at');
        });
    }
};
