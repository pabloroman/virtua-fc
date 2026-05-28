<?php

namespace App\Http\Actions\LiveDuel;

use App\Models\Team;
use App\Modules\LiveMatch\Services\NationalSquadBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ShowLiveDuelEntry
{
    public function __construct(
        private readonly NationalSquadBuilder $squadBuilder,
    ) {}

    public function __invoke(Request $request)
    {
        $teams = self::availableNationalTeams();
        $user = Auth::user();
        $eligibility = $teams->mapWithKeys(
            fn (Team $t) => [$t->id => $this->squadBuilder->eligibleCountFor($user, $t)],
        )->all();

        return view('live.duel.entry', [
            'teams' => $teams,
            'eligibility' => $eligibility,
            'role' => 'host',
            'takenTeamId' => null,
        ]);
    }

    /**
     * National teams eligible for tournament mode — same scope used by
     * SetupTournamentGame (Team::worldCupEligible).
     *
     * @return Collection<int, Team>
     */
    public static function availableNationalTeams(): Collection
    {
        return Team::query()
            ->worldCupEligible()
            ->orderBy('name')
            ->get();
    }
}
