<?php

namespace App\Http\Actions\LiveDuel;

use App\Modules\LiveMatch\Services\NationalSquadBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ShowLiveDuelEntry
{
    public function __construct(
        private readonly NationalSquadBuilder $squadBuilder,
    ) {}

    public function __invoke(Request $request)
    {
        $user = Auth::user();
        $nations = self::nationCatalog();

        $eligibility = [];
        foreach ($nations as $nation) {
            $eligibility[$nation['iso']] = $this->squadBuilder->eligibleCountFor($user, $nation['iso']);
        }

        return view('live.duel.entry', [
            'nations' => $nations,
            'eligibility' => $eligibility,
            'role' => 'host',
            'takenIso' => null,
        ]);
    }

    /**
     * Prototype catalog of pickable national teams. The `iso` value is the
     * exact string stored in game_players.nationality (full country names —
     * see PlayerNameGenerator::NATIONALITY_LOCALES).
     *
     * @return array<int, array{iso: string, name: string, flag: string}>
     */
    public static function nationCatalog(): array
    {
        return [
            ['iso' => 'Spain', 'name' => 'Spain', 'flag' => '🇪🇸'],
            ['iso' => 'Brazil', 'name' => 'Brazil', 'flag' => '🇧🇷'],
            ['iso' => 'Argentina', 'name' => 'Argentina', 'flag' => '🇦🇷'],
            ['iso' => 'France', 'name' => 'France', 'flag' => '🇫🇷'],
            ['iso' => 'Germany', 'name' => 'Germany', 'flag' => '🇩🇪'],
            ['iso' => 'Italy', 'name' => 'Italy', 'flag' => '🇮🇹'],
            ['iso' => 'Portugal', 'name' => 'Portugal', 'flag' => '🇵🇹'],
            ['iso' => 'England', 'name' => 'England', 'flag' => '🏴󠁧󠁢󠁥󠁮󠁧󠁿'],
            ['iso' => 'Netherlands', 'name' => 'Netherlands', 'flag' => '🇳🇱'],
            ['iso' => 'Belgium', 'name' => 'Belgium', 'flag' => '🇧🇪'],
            ['iso' => 'Croatia', 'name' => 'Croatia', 'flag' => '🇭🇷'],
            ['iso' => 'Uruguay', 'name' => 'Uruguay', 'flag' => '🇺🇾'],
            ['iso' => 'Colombia', 'name' => 'Colombia', 'flag' => '🇨🇴'],
            ['iso' => 'Mexico', 'name' => 'Mexico', 'flag' => '🇲🇽'],
            ['iso' => 'Morocco', 'name' => 'Morocco', 'flag' => '🇲🇦'],
            ['iso' => 'Senegal', 'name' => 'Senegal', 'flag' => '🇸🇳'],
        ];
    }
}
