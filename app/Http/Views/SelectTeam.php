<?php

namespace App\Http\Views;

use App\Modules\Competition\Services\CountryConfig;
use App\Modules\Manager\Services\JobOfferService;
use App\Models\Competition;
use App\Models\Game;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

final class SelectTeam
{
    /**
     * Primera Federación runs as two parallel groups. They share one entry in
     * the league picker and one tab body below it, so the user picks a
     * division rather than a group.
     */
    private const PRIMERA_RFEF_GROUPS = ['ESP3A', 'ESP3B'];

    private const PRIMERA_RFEF_TAB = 'ESP3';

    public function __invoke(Request $request, CountryConfig $countryConfig, JobOfferService $jobOfferService)
    {
        if (Game::where('user_id', $request->user()->id)->whereNull('deleting_at')->count() >= 3) {
            return redirect()->route('dashboard')->withErrors(['limit' => __('messages.game_limit_reached')]);
        }

        // Build country → tier → competition structure for career mode (cached — static reference data).
        // Tiers may declare sibling competitions (e.g. Primera RFEF's ESP3A and
        // ESP3B both live at tier 3), so the tiers list is keyed by competition
        // ID rather than tier number to keep every league selectable.
        $countries = Cache::remember('career_mode_countries:v3', 3600, function () use ($countryConfig) {
            $countries = [];

            foreach ($countryConfig->playableCountryCodes() as $code) {
                $config = $countryConfig->get($code);
                $tiers = [];

                foreach ($config['tiers'] as $tier => $tierConfig) {
                    $entries = [$tierConfig];
                    foreach ($tierConfig['siblings'] ?? [] as $sibling) {
                        $entries[] = $sibling;
                    }

                    foreach ($entries as $entry) {
                        $competition = Competition::with('teams')
                            ->find($entry['competition']);

                        if ($competition) {
                            $tiers[$competition->id] = $competition;
                        }
                    }
                }

                if (!empty($tiers)) {
                    $countries[$code] = [
                        'name' => $config['name'],
                        'tiers' => $tiers,
                    ];
                }
            }

            return $countries;
        });

        // Load World Cup teams for tournament mode
        $wcTeams = collect();
        $wcFeaturedTeams = collect();
        $hasTournamentMode = config('game.tournament_mode_enabled')
            && $request->user()->canPlayTournamentMode()
            && Competition::where('id', 'WC2026')->exists();

        if ($hasTournamentMode) {
            $locale = app()->getLocale();
            $allWcTeams = Cache::remember("wc2026_selectable_teams:{$locale}", 600, function () {
                return Team::worldCupEligible()
                    ->where('is_placeholder', false)
                    ->get()
                    ->sortBy('name') // PHP sort: name accessor applies i18n translation
                    ->values();
            });

            // Featured national teams shown as larger cards
            $featuredCodes = ['ESP', 'ARG', 'BRA', 'ENG', 'FRA', 'GER', 'POR', 'NED'];
            $wcFeaturedTeams = $allWcTeams->filter(fn ($t) => in_array($t->fifa_code, $featuredCodes))->values();
            $wcTeams = $allWcTeams->reject(fn ($t) => in_array($t->fifa_code, $featuredCodes))->values();
        }

        // Sample 3 Local-tier Primera RFEF teams for the inline Pro Manager
        // picker. Resampled on every render — refreshing the page or being
        // bounced back here by a validation error produces a different three,
        // which is deliberate (mirrors a manager scanning the market).
        $hasCareerAccess = $request->user()->canPlayCareerMode();
        $proManagerTeams = $hasCareerAccess
            ? $jobOfferService->sampleInitialProManagerTeams()
            : collect();

        return view('select-team', [
            'countries' => $countries,
            'leagues' => $this->leagueOptions($countries),
            'wcTeams' => $wcTeams,
            'wcFeaturedTeams' => $wcFeaturedTeams,
            'hasTournamentMode' => $hasTournamentMode,
            'hasCareerAccess' => $hasCareerAccess,
            'proManagerTeams' => $proManagerTeams,
        ]);
    }

    /**
     * The league picker's options, in the order the countries were built.
     *
     * Shaped here rather than in the view because the labels need `__()`
     * applied in PHP: a competition's name is a plain database string that
     * passes straight through, but Primera Federación's combined entry is a
     * translation key, so the two only agree on the server. Flags resolve to
     * asset URLs for the same reason — the component renders whatever it is
     * handed.
     *
     * Not cached: `$countries` is, but these labels are locale-dependent.
     *
     * @param  array<string, array{name: string, tiers: array<string, Competition>}>  $countries
     * @return array<int, array{value: string, label: string, flag: string}>
     */
    private function leagueOptions(array $countries): array
    {
        $options = [];

        foreach ($countries as $country) {
            foreach ($country['tiers'] as $competition) {
                if (in_array($competition->id, self::PRIMERA_RFEF_GROUPS, true)) {
                    // Added when the first group is reached, which keeps the
                    // combined entry in the position that group held.
                    $options[self::PRIMERA_RFEF_TAB] ??= [
                        'value' => self::PRIMERA_RFEF_TAB,
                        'label' => __('game.primera_federacion'),
                        'flag' => $this->flagUrl($competition->flag),
                    ];

                    continue;
                }

                $options[$competition->id] = [
                    'value' => $competition->id,
                    'label' => __($competition->name),
                    'flag' => $this->flagUrl($competition->flag),
                ];
            }
        }

        return array_values($options);
    }

    private function flagUrl(?string $flag): string
    {
        return $flag ? Storage::disk('assets')->url("flags/{$flag}.svg") : '';
    }
}
