<?php

namespace App\Actions\Feats\Counts;

use App\Models\Character;
use App\Support\FeatMeasure;
use App\Support\FeatRules;
use App\Support\InstanceType;
use App\Support\NameKind;
use App\Support\PlayerClass;
use App\Support\Role;
use App\Support\TalentTree;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The class, talent tree and role feats (docs/DESIGN.md "Art and game layer"). A feat applies by class only: a
 * character sees the feats of its class, of its class's trees and of the roles those trees play; every other key
 * is null, and `StoreCharacterFeats` deletes the rows a null leaves. Class and tree casts count in any spec; role
 * feats count only the sessions whose tree plays the role. Spells match by English name from the `names` table.
 */
class SpecCounts
{
    private const int HOUR_MS = 3_600_000;

    /**
     * One count per spec feat, keyed by the enum value; null when the feat does not apply to the character.
     *
     * @return array<string, int|float|null>
     */
    public function handle(Character $character): array
    {
        $rules = array_fill_keys(array_keys(FeatRules::RULES), null);
        $class = PlayerClass::tryFrom((string) $character->class);
        if ($class === null) {
            return $rules;
        }

        $sessions = $this->sessions($character, $class);
        $spellNames = $this->spellNames($sessions);

        foreach (FeatRules::RULES as $feat => [$scope, $measure, $patterns]) {
            if (! $this->applies($scope, $class)) {
                continue;
            }
            $in = $this->within($sessions, $scope);
            $rules[$feat] = match ($measure) {
                FeatMeasure::Casts => $this->casts($scope instanceof Role ? $in : $sessions, $spellNames, $patterns),
                FeatMeasure::Hours => intdiv($this->sum($in, fn (SpecSession $session): int => $session->playedMs), self::HOUR_MS),
                FeatMeasure::Kills => $this->sum($in, fn (SpecSession $session): int => $session->kills),
                FeatMeasure::Deaths => $this->sum($in, fn (SpecSession $session): int => $session->deaths),
                FeatMeasure::PlayerKills => $this->sum($in, fn (SpecSession $session): int => $session->playerKills),
                FeatMeasure::DungeonRuns => $this->sum($in, fn (SpecSession $session): int => $session->dungeonRuns),
                FeatMeasure::DungeonBosses => $this->sum($in, fn (SpecSession $session): int => $session->dungeonBosses),
                FeatMeasure::RaidBosses => $this->sum($in, fn (SpecSession $session): int => $session->raidBosses),
                FeatMeasure::DeathlessRuns => $this->sum($in, fn (SpecSession $session): int => $session->deathlessRuns),
                FeatMeasure::Damage => $this->sum($in, fn (SpecSession $session): int => $session->damage),
                FeatMeasure::Healing => $this->sum($in, fn (SpecSession $session): int => $session->healing),
                FeatMeasure::DamageTaken => $this->sum($in, fn (SpecSession $session): int => $session->damageTaken),
                FeatMeasure::BestDamage => $this->largest($in, fn (SpecSession $session): int => $session->bestDamage),
                FeatMeasure::BestHealing => $this->largest($in, fn (SpecSession $session): int => $session->bestHealing),
                FeatMeasure::BestDamageTaken => $this->largest($in, fn (SpecSession $session): int => $session->bestDamageTaken),
            };
        }

        return $rules;
    }

    /** True when `$scope` is the character's class, one of its trees, or a role one of its trees plays. */
    private function applies(PlayerClass|TalentTree|Role $scope, PlayerClass $class): bool
    {
        if ($scope instanceof PlayerClass) {
            return $scope === $class;
        }
        if ($scope instanceof TalentTree) {
            return $scope->class() === $class;
        }

        return in_array($scope, $this->roles($class), true);
    }

    /** @return list<Role> the roles the class's trees play */
    private function roles(PlayerClass $class): array
    {
        $roles = [];
        foreach (TalentTree::cases() as $tree) {
            if ($tree->class() === $class) {
                foreach ($tree->roles() as $role) {
                    $roles[$role->value] = $role;
                }
            }
        }

        return array_values($roles);
    }

    /** @return list<SpecSession> the counted sessions of the character, each with its tree and totals */
    private function sessions(Character $character, PlayerClass $class): array
    {
        $id = (int) $character->id;
        $rows = DB::table('sessions')->where('character_id', $id)->where('counted', true)
            ->selectRaw('session_start, played_ms, coalesce(kills, 0) as kills, coalesce(deaths, 0) as deaths')
            ->get()->keyBy('session_start');
        $trees = [];
        foreach (DB::table('talent_snapshots')->where('character_id', $id)->where('counted', true)
            ->selectRaw('distinct on (session_start) session_start, spec_tab_id')
            ->orderBy('session_start')->orderBy('at')->get() as $row) {
            $trees[(int) $row->session_start] = $row->spec_tab_id === null ? null : (int) $row->spec_tab_id;
        }
        $tabNames = $this->named(NameKind::TalentTab, array_values($trees));
        $playerKills = DB::table('kills')->where('character_id', $id)->where('counted', true)->where('player', true)
            ->groupBy('session_start')->selectRaw('session_start, count(*) as player_kills')
            ->pluck('player_kills', 'session_start');
        $runs = DB::table('dungeon_runs')->where('character_id', $id)->where('counted', true)
            ->groupBy('session_start')
            ->selectRaw('session_start')
            ->selectRaw('count(*) filter (where not continues_previous) as runs')
            ->selectRaw('coalesce(sum(bosses), 0) as bosses')
            ->selectRaw('coalesce(sum(bosses) filter (where instance_type = ?), 0) as raid_bosses', [InstanceType::Raid->value])
            ->selectRaw('count(*) filter (where not continues_previous and bosses > 0 and deaths = 0) as deathless')
            ->get()->keyBy('session_start');
        $meters = [];
        foreach (DB::table('meter_totals')->where('character_id', $id)->where('counted', true)
            ->groupBy('session_start', 'meter')
            ->selectRaw('session_start, meter, coalesce(sum(total), 0) as total, coalesce(max(best), 0) as best')->get() as $meter) {
            $meters[(int) $meter->session_start][$meter->meter] = ['total' => (int) $meter->total, 'best' => (int) $meter->best];
        }
        $casts = [];
        foreach (DB::table('spell_casts')->where('character_id', $id)->where('counted', true)
            ->selectRaw('session_start, spell_id, casts')->get() as $cast) {
            $casts[(int) $cast->session_start][(int) $cast->spell_id] = (int) $cast->casts;
        }

        $sessions = [];
        foreach ($rows as $sessionStart => $row) {
            $start = (int) $sessionStart;
            $tab = $trees[$start] ?? null;
            $meter = $meters[$start] ?? [];
            $run = $runs->get($start);
            $sessions[] = new SpecSession(
                tree: $tab === null ? null : TalentTree::of($class, $tabNames[$tab] ?? ''),
                playedMs: (int) $row->played_ms,
                kills: (int) $row->kills,
                deaths: (int) $row->deaths,
                playerKills: (int) ($playerKills[$start] ?? 0),
                dungeonRuns: $run === null ? 0 : (int) $run->runs,
                dungeonBosses: $run === null ? 0 : (int) $run->bosses,
                raidBosses: $run === null ? 0 : (int) $run->raid_bosses,
                deathlessRuns: $run === null ? 0 : (int) $run->deathless,
                damage: $meter['d']['total'] ?? 0,
                healing: $meter['h']['total'] ?? 0,
                damageTaken: $meter['t']['total'] ?? 0,
                bestDamage: $meter['d']['best'] ?? 0,
                bestHealing: $meter['h']['best'] ?? 0,
                bestDamageTaken: $meter['t']['best'] ?? 0,
                casts: $casts[$start] ?? [],
            );
        }

        return $sessions;
    }

    /**
     * @param  list<SpecSession>  $sessions
     * @return array<int, string> the most voted English name of each spell id cast
     */
    private function spellNames(array $sessions): array
    {
        $ids = [];
        foreach ($sessions as $session) {
            foreach (array_keys($session->casts) as $spellId) {
                $ids[$spellId] = $spellId;
            }
        }

        return $this->named(NameKind::Spell, array_values($ids));
    }

    /**
     * @param  list<int|null>  $ids
     * @return array<int, string>
     */
    private function named(NameKind $kind, array $ids): array
    {
        $ids = array_values(array_filter($ids, fn (?int $id): bool => $id !== null));
        if ($ids === []) {
            return [];
        }
        $names = [];
        foreach (DB::table('names')->where('kind', $kind->value)->whereIn('id', $ids)->orderBy('votes')->orderByDesc('name')->get(['id', 'name']) as $row) {
            $names[(int) $row->id] = (string) $row->name;
        }

        return $names;
    }

    /**
     * The sessions a scope counts: every session for a class, the tree's sessions for a tree and, for a role, the
     * sessions whose tree plays it.
     *
     * @param  list<SpecSession>  $sessions
     * @return list<SpecSession>
     */
    private function within(array $sessions, PlayerClass|TalentTree|Role $scope): array
    {
        return array_values(array_filter($sessions, fn (SpecSession $session): bool => match (true) {
            $scope instanceof TalentTree => $session->tree === $scope,
            $scope instanceof Role => $session->tree !== null && in_array($scope, $session->tree->roles(), true),
            default => true,
        }));
    }

    /**
     * The casts of the spells whose name matches a pattern, over `$sessions`.
     *
     * @param  list<SpecSession>  $sessions
     * @param  array<int, string>  $spellNames
     * @param  list<string>  $patterns
     */
    private function casts(array $sessions, array $spellNames, array $patterns): int
    {
        $count = 0;
        foreach ($sessions as $session) {
            foreach ($session->casts as $spellId => $spellCasts) {
                if ($this->matches($patterns, $spellNames[$spellId] ?? null)) {
                    $count += $spellCasts;
                }
            }
        }

        return $count;
    }

    /** @param  list<string>  $patterns */
    private function matches(array $patterns, ?string $name): bool
    {
        if ($name === null) {
            return false;
        }
        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $name, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<SpecSession>  $sessions
     * @param  callable(SpecSession): int  $read
     */
    private function sum(array $sessions, callable $read): int
    {
        return array_sum(array_map($read, $sessions));
    }

    /**
     * The largest reading of one session: a best is a fight, not a running total.
     *
     * @param  list<SpecSession>  $sessions
     * @param  callable(SpecSession): int  $read
     */
    private function largest(array $sessions, callable $read): int
    {
        return max([0, ...array_map($read, $sessions)]);
    }
}
