<?php

namespace App\Support\Recap;

use Illuminate\Support\Facades\DB;

/** The `top` lists of a recap: killed, looted, killers and quests by zone, eight each at most. */
final class Top
{
    private const int LIMIT = 8;

    /**
     * @return array{killed: list<array{name: string, n: int}>, looted: list<array{id: int, name: string, quality: int, n: int}>, killers: list<array{name: string, n: int}>, quests_by_zone: list<array{zone: string, n: int}>}
     */
    public static function read(Scope $scope): array
    {
        return [
            'killed' => self::killed($scope),
            'looted' => self::looted($scope),
            'killers' => self::killers($scope),
            'quests_by_zone' => self::quests($scope),
        ];
    }

    /** @return list<array{name: string, n: int}> */
    private static function killed(Scope $scope): array
    {
        $counts = $scope->rows('kills')->whereNotNull('npc_id')->selectRaw('npc_id, count(*) as n')->groupBy('npc_id')->pluck('n', 'npc_id')->all();
        $names = self::names('npc', array_keys($counts));
        $rows = [];
        foreach ($counts as $id => $n) {
            $rows[] = ['name' => $names[$id] ?? 'Creature '.$id, 'n' => (int) $n];
        }

        return self::ranked($rows);
    }

    /** @return list<array{id: int, name: string, quality: int, n: int}> */
    private static function looted(Scope $scope): array
    {
        $counts = $scope->rows('loot_drops')->whereNotNull('item_id')->selectRaw('item_id, sum(greatest(quantity, 1)) as n')->groupBy('item_id')->pluck('n', 'item_id')->all();
        $names = self::names('item', array_keys($counts));
        $quality = DB::table('items')->whereIn('item_id', array_keys($counts))->pluck('quality', 'item_id');
        $rows = [];
        foreach ($counts as $id => $n) {
            $rows[] = ['id' => (int) $id, 'name' => $names[$id] ?? 'Item '.$id, 'quality' => (int) ($quality[$id] ?? 1), 'n' => (int) $n];
        }
        usort($rows, fn (array $a, array $b): int => [$b['n'], $a['name']] <=> [$a['n'], $b['name']]);

        return array_slice($rows, 0, self::LIMIT);
    }

    /** @return list<array{name: string, n: int}> */
    private static function killers(Scope $scope): array
    {
        $rows = [];
        $deaths = $scope->rows('deaths')->get(['killer', 'killer_kind', 'environmental_type']);
        foreach ($deaths as $death) {
            $name = is_string($death->environmental_type) && $death->environmental_type !== '' ? ucfirst(strtolower($death->environmental_type)) : ($death->killer_kind === 'p' ? '' : (string) $death->killer);
            if ($name !== '') {
                $rows[$name] = ($rows[$name] ?? 0) + 1;
            }
        }

        return self::ranked(array_map(fn (string $name, int $n): array => ['name' => $name, 'n' => $n], array_keys($rows), $rows));
    }

    /** @return list<array{zone: string, n: int}> */
    private static function quests(Scope $scope): array
    {
        $rows = $scope->rows('quest_events')->where('event', 'turned_in')->join('zones', 'zones.id', '=', 'quest_events.zone_id')->where('zones.name', '!=', '')
            ->selectRaw('zones.name as zone, count(*) as n')->groupBy('zones.name')->get()
            ->map(fn (object $row): array => ['zone' => (string) $row->zone, 'n' => (int) $row->n])->all();
        usort($rows, fn (array $a, array $b): int => [$b['n'], $a['zone']] <=> [$a['n'], $b['zone']]);

        return array_slice($rows, 0, self::LIMIT);
    }

    /**
     * @param  list<array{name: string, n: int}>  $rows
     * @return list<array{name: string, n: int}>
     */
    private static function ranked(array $rows): array
    {
        usort($rows, fn (array $a, array $b): int => [$b['n'], $a['name']] <=> [$a['n'], $b['name']]);

        return array_slice($rows, 0, self::LIMIT);
    }

    /**
     * @param  list<int|string>  $ids
     * @return array<int|string, string>
     */
    private static function names(string $kind, array $ids): array
    {
        return DB::table('names')->where('kind', $kind)->whereIn('id', $ids)->orderBy('votes')->pluck('name', 'id')->all();
    }
}
