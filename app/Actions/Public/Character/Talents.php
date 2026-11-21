<?php

namespace App\Actions\Public\Character;

use App\Support\NameKind;
use App\Support\RespecKind;
use App\Support\TalentKind;
use App\Support\TalentRanks;
use Illuminate\Support\Facades\DB;

class Talents
{
    /**
     * What a character's talents look like on a page: the latest build of each talent group, the order the points were
     * taken in and the respecs. Null when no talent was recorded.
     *
     * The order comes from the builds the addon read, one per change. The points a build holds that the one before it
     * did not were taken between the two reads: a point the addon saw being picked has its exact level and its number
     * in the build, the others have the level of both reads. Points held at the first read have no order and are not
     * listed. A build with fewer points than the one before it, and no respec recorded between them, is a respec
     * between sessions.
     *
     * @return array{active: int, groups: list<array<string, mixed>>, order: list<array<string, mixed>>, respecs: list<array<string, mixed>>}|null
     */
    public function handle(int $characterId): ?array
    {
        $snapshots = $this->snapshots($characterId);
        if ($snapshots === []) {
            return null;
        }
        $picks = $this->picks($characterId);
        $stored = $this->respecs($characterId);

        $order = $respecs = $latest = $previous = [];
        foreach ($snapshots as $row) {
            $group = (int) $row->spec_group;
            $held = TalentRanks::parse((string) $row->ranks);
            if (isset($previous[$group])) {
                $diff = $this->diff($previous[$group]['held'], $held);
                $order = [...$order, ...$this->added($diff['added'], $held, $picks, $group, $previous[$group], $row)];
                if ($diff['removed'] > 0 && ! $this->recorded($stored, $group, $previous[$group]['at'], (string) $row->at)) {
                    $respecs[] = ['at' => (string) $row->at, 'level' => $row->level, 'group' => $group, 'kind' => RespecKind::Between->value, 'moved' => $diff['removed'], 'cost' => null];
                }
            }
            $previous[$group] = ['held' => $held, 'at' => (string) $row->at, 'level' => $row->level];
            $latest[$group] = ['row' => $row, 'held' => $held];
        }
        foreach ($stored as $row) {
            $respecs[] = ['at' => (string) $row->at, 'level' => $row->level, 'group' => (int) $row->spec_group, 'kind' => (string) $row->kind, 'moved' => (int) $row->points_moved, 'cost' => $row->cost_copper === null ? null : (int) $row->cost_copper];
        }
        usort($respecs, fn (array $a, array $b): int => strcmp($b['at'], $a['at']));
        usort($order, fn (array $a, array $b): int => strcmp($a['at'], $b['at']));
        ksort($latest);

        $names = $this->names($latest, $order);

        return [
            'active' => (int) $snapshots[count($snapshots) - 1]->spec_group,
            'groups' => array_values(array_map(fn (array $build): array => $this->group($build['row'], $build['held'], $names), $latest)),
            'order' => array_map(fn (array $row): array => [...$row, 'name' => $names['spell'][$row['spell']] ?? null], $order),
            'respecs' => $respecs,
        ];
    }

    /** @return list<\stdClass> the builds of every group, each differing from the one before it in its group, oldest first */
    private function snapshots(int $characterId): array
    {
        return array_values(DB::select(
            <<<'SQL'
            select at, level, spec_group, tree_id, tab_points, spec_tab_id, build_hash, ranks from (
                select *, lag(build_hash) over (partition by spec_group order by at) as before_hash
                from talent_snapshots where character_id = ?
            ) s where before_hash is distinct from build_hash order by at
            SQL,
            [$characterId],
        ));
    }

    /** @return list<\stdClass> the points the addon saw being picked outside a respec */
    private function picks(int $characterId): array
    {
        return array_values(DB::table('talent_changes')->where('character_id', $characterId)->where('kind', TalentKind::Pick->value)->whereNull('respec_index')
            ->orderBy('at')->get(['at', 'level', 'spec_group', 'node_id', 'spell_id', 'rank', 'point_no'])->all());
    }

    /** @return list<\stdClass> */
    private function respecs(int $characterId): array
    {
        return array_values(DB::table('talent_respecs')->where('character_id', $characterId)->orderBy('at')
            ->get(['at', 'level', 'spec_group', 'kind', 'cost_copper', 'points_moved'])->all());
    }

    /**
     * @param  array<int, array{rank: int}>  $before
     * @param  array<int, array{rank: int}>  $after
     * @return array{added: array<int, int>, removed: int} points gained by node, points lost in all
     */
    private function diff(array $before, array $after): array
    {
        $added = [];
        $removed = 0;
        foreach ($after as $node => $held) {
            $gain = $held['rank'] - ($before[$node]['rank'] ?? 0);
            if ($gain > 0) {
                $added[$node] = $gain;
            }
        }
        foreach ($before as $node => $held) {
            $removed += max(0, $held['rank'] - ($after[$node]['rank'] ?? 0));
        }

        return ['added' => $added, 'removed' => $removed];
    }

    /**
     * @param  array<int, int>  $added  points gained by node
     * @param  array<int, array{spell: int|null, rank: int}>  $held  the build that gained them
     * @param  list<\stdClass>  $picks
     * @param  array{at: string, level: mixed}  $before  the build before
     * @return list<array<string, mixed>>
     */
    private function added(array $added, array $held, array $picks, int $group, array $before, \stdClass $after): array
    {
        $rows = [];
        foreach ($picks as $pick) {
            $node = (int) $pick->node_id;
            if ((int) $pick->spec_group !== $group || (string) $pick->at <= $before['at'] || (string) $pick->at > (string) $after->at || ($added[$node] ?? 0) < 1) {
                continue;
            }
            $added[$node]--;
            $rows[] = ['at' => (string) $pick->at, 'group' => $group, 'level' => $pick->level, 'point' => $pick->point_no, 'node' => $node, 'spell' => $pick->spell_id, 'rank' => (int) $pick->rank, 'exact' => true];
        }
        foreach ($added as $node => $count) {
            for ($n = 0; $n < $count; $n++) {
                $rows[] = ['at' => (string) $after->at, 'group' => $group, 'level' => $after->level, 'point' => null, 'node' => $node, 'spell' => $held[$node]['spell'], 'rank' => $held[$node]['rank'] - $count + $n + 1, 'exact' => false, 'from' => $before['level']];
            }
        }

        return $rows;
    }

    /** @param  list<\stdClass>  $stored */
    private function recorded(array $stored, int $group, string $from, string $until): bool
    {
        foreach ($stored as $row) {
            if ((int) $row->spec_group === $group && (string) $row->at > $from && (string) $row->at <= $until) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array{row: \stdClass, held: array<int, array{spell: int|null}>}>  $latest
     * @param  list<array<string, mixed>>  $order
     * @return array{spell: array<int, string>, tab: array<int, string>}
     */
    private function names(array $latest, array $order): array
    {
        $spells = array_column($order, 'spell');
        $tabs = [];
        foreach ($latest as $build) {
            array_push($spells, ...array_column($build['held'], 'spell'));
            array_push($tabs, ...array_column(TalentRanks::tabs($build['row']->tab_points), 'id'), ...array_column($build['held'], 'tab'));
        }

        return ['spell' => $this->named(NameKind::Spell, $spells), 'tab' => $this->named(NameKind::TalentTab, $tabs)];
    }

    /**
     * @param  list<int|null>  $ids
     * @return array<int, string> the most voted name of each id
     */
    private function named(NameKind $kind, array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, fn (?int $id): bool => $id !== null)));
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
     * @param  array<int, array{node: int, entry: int, rank: int, spell: int|null, tab: int|null}>  $held
     * @param  array{spell: array<int, string>, tab: array<int, string>}  $names
     * @return array<string, mixed>
     */
    private function group(\stdClass $row, array $held, array $names): array
    {
        $tabs = array_map(fn (array $tab): array => ['id' => $tab['id'], 'name' => $names['tab'][$tab['id']] ?? null, 'spent' => $tab['spent']], TalentRanks::tabs($row->tab_points));

        return [
            'group' => (int) $row->spec_group,
            'at' => (string) $row->at,
            'level' => $row->level,
            'tree_id' => (int) $row->tree_id,
            'build_hash' => (string) $row->build_hash,
            'spec_tab_id' => $row->spec_tab_id === null ? null : (int) $row->spec_tab_id,
            'points' => TalentRanks::points($held),
            'tabs' => $tabs,
            'nodes' => array_values(array_map(fn (array $node): array => [...$node, 'name' => $node['spell'] === null ? null : ($names['spell'][$node['spell']] ?? null)], $held)),
        ];
    }
}
