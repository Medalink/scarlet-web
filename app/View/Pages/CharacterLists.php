<?php

namespace App\View\Pages;

use App\Actions\Public\Character\DungeonRuns;
use App\Support\CreatureClass;
use App\Support\ItemQuality;
use App\View\Charts;
use App\View\Format;
use App\View\ItemTypes;
use Illuminate\Support\Carbon;

/** The lists of the character page that rank what a character did most or list what it did last: creatures, crafts and dungeon runs. */
final class CharacterLists
{
    /** Crosses drawn for the deaths of a run; more are written as a number. */
    private const int CROSSES = 5;

    /**
     * @param  list<array{id: int, name: string, kills: int}>  $killed
     * @return list<array<string, mixed>>
     */
    public static function killed(array $killed): array
    {
        $top = $killed === [] ? 1 : max(1, $killed[0]['kills']);

        return array_map(fn (array $row): array => [
            'label' => $row['name'], 'href' => '/creatures/'.$row['id'], 'value' => Format::count($row['kills'], 'kill'), 'share' => $row['kills'] / $top * 100,
        ], $killed);
    }

    /**
     * @param  list<array{item_id: int, name: string, quality: int, quantity: int}>  $crafted
     * @return list<array<string, mixed>>
     */
    public static function crafted(array $crafted): array
    {
        $top = $crafted === [] ? 1 : max(1, $crafted[0]['quantity']);

        return array_map(fn (array $row): array => [
            'label' => $row['name'], 'href' => '/items/'.$row['item_id'], 'quality' => ItemTypes::quality($row['quality']), 'class' => ItemQuality::from($row['quality'])->fill(),
            'value' => Format::number($row['quantity']).' crafted', 'share' => $row['quantity'] / $top * 100,
        ], $crafted);
    }

    /**
     * @param  array{total: int, zones: list<array{id: int, name: string, quests: int, linked: bool}>, others: int, levels: array<int, int>}  $done
     * @return array{total: string, zones: list<array<string, mixed>>, others: string|null, spec: array<string, mixed>|null}|null
     */
    public static function quests(array $done): ?array
    {
        if ($done['total'] === 0) {
            return null;
        }
        $top = max(1, $done['zones'][0]['quests'] ?? 1);
        $last = $done['levels'] === [] ? 0 : max(array_keys($done['levels']));
        $data = $say = [];
        for ($level = 1; $level <= $last; $level++) {
            $count = $done['levels'][$level] ?? 0;
            $data[] = [$level, $count];
            $say[] = Format::line('Level '.$level, ': '.Format::count($count, 'quest'));
        }
        $axis = Axis::nice((float) max([0, ...array_column($data, 1)]));

        return [
            'total' => Format::count($done['total'], 'quest').' turned in',
            'zones' => array_map(fn (array $zone): array => [
                'label' => $zone['name'], 'href' => $zone['linked'] ? '/zones/'.$zone['id'] : null, 'value' => Format::count($zone['quests'], 'quest'), 'share' => $zone['quests'] / $top * 100,
            ], $done['zones']),
            'others' => $done['others'] === 0 ? null : Format::count($done['others'], 'other zone'),
            'spec' => $data === [] ? null : Charts::bars(['w' => 560, 'h' => 220, 'x0' => 0, 'x1' => $last + 1, 'y1' => $axis['max'], 'yt' => Charts::ticks($axis['ticks']), 'xt' => Charts::ticks(Axis::spread(1, $last))], $data, '', $say),
        ];
    }

    /**
     * @param  array{counts: array<string, int>, rares: list<array{id: int, name: string, level: int, class: string}>}  $met
     * @return array{figures: list<array{label: string, value: string}>, rows: list<array<string, mixed>>}|null
     */
    public static function creatures(array $met): ?array
    {
        $figures = [];
        foreach ([CreatureClass::Rare, CreatureClass::RareElite, CreatureClass::Elite, CreatureClass::WorldBoss] as $rank) {
            $count = $met['counts'][$rank->value] ?? 0;
            if ($count > 0) {
                $figures[] = ['label' => ucfirst($rank->label()).($rank === CreatureClass::WorldBoss ? 'es met' : 's met'), 'value' => Format::number($count)];
            }
        }
        if ($figures === []) {
            return null;
        }
        $top = max([1, ...array_column($met['rares'], 'level')]);

        return [
            'figures' => $figures,
            'rows' => array_map(fn (array $row): array => [
                'label' => $row['name'], 'href' => '/creatures/'.$row['id'], 'value' => Format::creatureLevel($row['level'], null).', '.CreatureClass::from($row['class'])->label(), 'share' => max(0, $row['level']) / $top * 100,
            ], $met['rares']),
        ];
    }

    /**
     * One row for each run, newest first, with a bar for the time inside scaled to the longest run listed.
     *
     * @param  list<array{instance_id: int, name: string|null, at: string, seconds: float, bosses: int, deaths: int, group_size: int|null}>  $runs
     * @param  list<array<string, mixed>>  $instances  the answer of CharacterOverview for dungeons: one row per instance with its runs
     * @param  int  $addons  the runs other addons counted, which add to the count and are not listed
     * @return array{rows: list<array<string, mixed>>, total: string}|null
     */
    public static function runs(array $runs, array $instances, int $addons = 0): ?array
    {
        if ($runs === [] && $addons === 0) {
            return null;
        }
        $top = max([1.0, ...array_column($runs, 'seconds')]);
        $scarlet = (int) array_sum(array_column($instances, 'runs'));
        $all = $scarlet + $addons;

        return [
            'rows' => array_map(fn (array $run): array => [
                'label' => $run['name'] ?? 'Instance '.$run['instance_id'],
                'date' => Carbon::parse($run['at'])->utc()->format('j M'),
                'time' => Format::duration($run['seconds']),
                'share' => $run['seconds'] / $top * 100,
                'bosses' => $run['bosses'] === 0 ? 'No bosses' : Format::count($run['bosses'], 'boss', 'bosses'),
                'deaths' => $run['deaths'],
                'crosses' => min($run['deaths'], self::CROSSES),
                'deathsSay' => $run['deaths'] === 0 ? 'No deaths' : Format::count($run['deaths'], 'death'),
                'group' => $run['group_size'] === null ? null : ($run['group_size'] === 1 ? 'Solo' : $run['group_size'].' players'),
            ], $runs),
            'total' => Format::count($all, 'dungeon run').' in all.'.($addons > 0 ? ' From addons '.Format::number($addons).'.' : '').(count($runs) < $scarlet ? ' The latest '.DungeonRuns::SHOWN.' are listed.' : ''),
        ];
    }
}
