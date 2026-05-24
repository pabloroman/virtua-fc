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
     * Prototype catalog of pickable national teams. ISO codes match the
     * `nationality` array stored on GamePlayer.
     *
     * @return array<int, array{iso: string, name: string, flag: string}>
     */
    public static function nationCatalog(): array
    {
        return [
            ['iso' => 'ES', 'name' => 'Spain', 'flag' => 'es'],
            ['iso' => 'BR', 'name' => 'Brazil', 'flag' => 'br'],
            ['iso' => 'AR', 'name' => 'Argentina', 'flag' => 'ar'],
            ['iso' => 'FR', 'name' => 'France', 'flag' => 'fr'],
            ['iso' => 'DE', 'name' => 'Germany', 'flag' => 'de'],
            ['iso' => 'IT', 'name' => 'Italy', 'flag' => 'it'],
            ['iso' => 'PT', 'name' => 'Portugal', 'flag' => 'pt'],
            ['iso' => 'EN', 'name' => 'England', 'flag' => 'gb-eng'],
            ['iso' => 'NL', 'name' => 'Netherlands', 'flag' => 'nl'],
            ['iso' => 'BE', 'name' => 'Belgium', 'flag' => 'be'],
            ['iso' => 'CR', 'name' => 'Croatia', 'flag' => 'hr'],
            ['iso' => 'UY', 'name' => 'Uruguay', 'flag' => 'uy'],
        ];
    }
}
