<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_summaries', function (Blueprint $table) {
            $table->unsignedSmallInteger('matches_played')->default(0)->after('your_record');
            $table->unsignedSmallInteger('matches_won')->default(0)->after('matches_played');
            $table->unsignedSmallInteger('matches_drawn')->default(0)->after('matches_won');
            $table->unsignedSmallInteger('matches_lost')->default(0)->after('matches_drawn');
            $table->unsignedSmallInteger('goals_scored')->default(0)->after('matches_lost');
            $table->unsignedSmallInteger('goals_conceded')->default(0)->after('goals_scored');
            $table->boolean('is_champion')->default(false)->after('goals_conceded');
            $table->unsignedSmallInteger('result_points')->default(0)->after('is_champion');

            $table->index(['user_id', 'is_champion']);
            $table->index('result_points');
        });

        $this->backfillExistingRows();
    }

    public function down(): void
    {
        Schema::table('tournament_summaries', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_champion']);
            $table->dropIndex(['result_points']);
            $table->dropColumn([
                'matches_played', 'matches_won', 'matches_drawn', 'matches_lost',
                'goals_scored', 'goals_conceded', 'is_champion', 'result_points',
            ]);
        });
    }

    private function backfillExistingRows(): void
    {
        DB::table('tournament_summaries')->orderBy('created_at')->each(function ($row) {
            $record = json_decode($row->your_record, true);

            $won = $record['won'] ?? 0;
            $drawn = $record['drawn'] ?? 0;
            $lost = $record['lost'] ?? 0;
            $goalsFor = $record['goalsFor'] ?? 0;
            $goalsAgainst = $record['goalsAgainst'] ?? 0;

            $resultPoints = match ($row->result_label) {
                'champion' => 6,
                'runner_up' => 5,
                'semi_finalist' => 4,
                'quarter_finalist' => 3,
                'round_of_16' => 2,
                default => 1,
            };

            DB::table('tournament_summaries')->where('id', $row->id)->update([
                'matches_played' => $won + $drawn + $lost,
                'matches_won' => $won,
                'matches_drawn' => $drawn,
                'matches_lost' => $lost,
                'goals_scored' => $goalsFor,
                'goals_conceded' => $goalsAgainst,
                'is_champion' => $row->result_label === 'champion',
                'result_points' => $resultPoints,
            ]);
        });
    }
};
