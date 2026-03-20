<?php

namespace App\Modules\Editor\Services;

use App\Models\GamePlayerTemplate;
use App\Models\GamePlayerTemplateAudit;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlayerTemplateAdminService
{
    private const POSITION_GROUPS = [
        'Goalkeepers' => ['Goalkeeper'],
        'Defenders' => ['Centre-Back', 'Left-Back', 'Right-Back'],
        'Midfielders' => ['Defensive Midfield', 'Central Midfield', 'Attacking Midfield', 'Left Midfield', 'Right Midfield'],
        'Forwards' => ['Left Winger', 'Right Winger', 'Centre-Forward', 'Second Striker'],
    ];

    public function teamsWithTemplates(array $filters): Collection
    {
        $season = $filters['season'] ?? null;
        $search = $filters['search'] ?? null;

        $query = GamePlayerTemplate::query()
            ->select('team_id', DB::raw('COUNT(*) as players_count'))
            ->groupBy('team_id');

        if ($season) {
            $query->where('season', $season);
        }

        $teamIds = $query->pluck('players_count', 'team_id');

        $teamsQuery = Team::whereIn('id', $teamIds->keys());

        if ($search) {
            $driver = DB::getDriverName();
            if ($driver === 'pgsql') {
                $teamsQuery->whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($search) . '%']);
            } else {
                $teamsQuery->where('name', 'LIKE', '%' . $search . '%');
            }
        }

        return $teamsQuery->orderBy('name')
            ->get()
            ->map(function (Team $team) use ($teamIds) {
                $team->players_count = $teamIds[$team->id] ?? 0;
                return $team;
            });
    }

    public function squadForTeam(string $teamId, string $season): Collection
    {
        $templates = GamePlayerTemplate::with(['player', 'audits.user'])
            ->where('team_id', $teamId)
            ->where('season', $season)
            ->get();

        $grouped = collect();
        foreach (self::POSITION_GROUPS as $groupName => $positions) {
            $players = $templates->filter(fn ($t) => in_array($t->position, $positions))
                ->sortBy('number');
            if ($players->isNotEmpty()) {
                $grouped[$groupName] = $players->values();
            }
        }

        // Add any players with unknown positions
        $knownPositions = collect(self::POSITION_GROUPS)->flatten()->toArray();
        $unknown = $templates->filter(fn ($t) => !in_array($t->position, $knownPositions));
        if ($unknown->isNotEmpty()) {
            $existing = $grouped->get('Forwards', collect());
            $grouped['Forwards'] = $existing->merge($unknown)->values();
        }

        return $grouped;
    }

    public function find(int $id): GamePlayerTemplate
    {
        return GamePlayerTemplate::with(['player', 'team', 'audits.user'])->findOrFail($id);
    }

    public function create(array $data, User $user): GamePlayerTemplate
    {
        return DB::transaction(function () use ($data, $user) {
            $template = GamePlayerTemplate::create($data);

            GamePlayerTemplateAudit::create([
                'game_player_template_id' => $template->id,
                'user_id' => $user->id,
                'action' => 'created',
                'old_values' => null,
                'new_values' => $template->only($template->getFillable()),
                'created_at' => now(),
            ]);

            return $template;
        });
    }

    public function update(GamePlayerTemplate $template, array $data, User $user): GamePlayerTemplate
    {
        return DB::transaction(function () use ($template, $data, $user) {
            $oldValues = $template->only($template->getFillable());

            $template->update($data);
            $template->refresh();

            $newValues = $template->only($template->getFillable());

            // Only audit if something actually changed
            if ($oldValues !== $newValues) {
                GamePlayerTemplateAudit::create([
                    'game_player_template_id' => $template->id,
                    'user_id' => $user->id,
                    'action' => 'updated',
                    'old_values' => $oldValues,
                    'new_values' => $newValues,
                    'created_at' => now(),
                ]);
            }

            return $template;
        });
    }

    public function delete(GamePlayerTemplate $template, User $user): void
    {
        DB::transaction(function () use ($template, $user) {
            GamePlayerTemplateAudit::create([
                'game_player_template_id' => $template->id,
                'user_id' => $user->id,
                'action' => 'deleted',
                'old_values' => $template->only($template->getFillable()),
                'new_values' => [],
                'created_at' => now(),
            ]);

            $template->delete();
        });
    }

    public function restore(GamePlayerTemplate $template, GamePlayerTemplateAudit $audit, User $user): GamePlayerTemplate
    {
        return DB::transaction(function () use ($template, $audit, $user) {
            $oldValues = $template->only($template->getFillable());
            $restoreValues = $audit->new_values;

            // Only restore editable attributes (not season/player_id/team_id)
            $editableKeys = [
                'number', 'position', 'market_value', 'market_value_cents',
                'contract_until', 'annual_wage', 'fitness', 'morale', 'durability',
                'game_technical_ability', 'game_physical_ability',
                'potential', 'potential_low', 'potential_high', 'tier',
            ];

            $updateData = array_intersect_key($restoreValues, array_flip($editableKeys));
            $template->update($updateData);
            $template->refresh();

            GamePlayerTemplateAudit::create([
                'game_player_template_id' => $template->id,
                'user_id' => $user->id,
                'action' => 'restored',
                'old_values' => $oldValues,
                'new_values' => $template->only($template->getFillable()),
                'created_at' => now(),
            ]);

            return $template;
        });
    }

    public function recentAudits(int $limit = 50): Collection
    {
        return GamePlayerTemplateAudit::with(['template.player', 'template.team', 'user'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function availableSeasons(): array
    {
        return GamePlayerTemplate::distinct()
            ->orderByDesc('season')
            ->pluck('season')
            ->toArray();
    }

    public function availablePositions(): array
    {
        return GamePlayerTemplate::distinct()
            ->orderBy('position')
            ->pluck('position')
            ->toArray();
    }

    public function searchPlayers(string $query, int $limit = 20): Collection
    {
        $builder = Player::query();
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            $builder->whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($query) . '%']);
        } else {
            $builder->where('name', 'LIKE', '%' . $query . '%');
        }

        return $builder->limit($limit)->get(['id', 'name', 'date_of_birth']);
    }

    public function searchTeams(string $query, int $limit = 20): Collection
    {
        $builder = Team::query();
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            $builder->whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($query) . '%']);
        } else {
            $builder->where('name', 'LIKE', '%' . $query . '%');
        }

        return $builder->limit($limit)->get(['id', 'name']);
    }

    public static function positionGroups(): array
    {
        return self::POSITION_GROUPS;
    }

    public static function allPositions(): array
    {
        return collect(self::POSITION_GROUPS)->flatten()->toArray();
    }
}
