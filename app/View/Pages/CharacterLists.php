<?php

namespace App\View\Pages;

use App\Actions\Public\Character\DungeonRuns;
use App\Support\ItemQuality;
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
     * One row for each run, newest first, with a bar for the time inside scaled to the longest run listed.
     *
     * @param  list<array{instance_id: int, name: string|null, at: string, seconds: float, bosses: int, deaths: int, group_size: int|null}>  $runs
     * @param  list<array<string, mixed>>  $instances  the answer of CharacterOverview for dungeons: one row per instance with its runs
     * @return array{rows: list<array<string, mixed>>, total: string}|null
     */
    public static function runs(array $runs, array $instances): ?array
    {
        if ($runs === []) {
            return null;
        }
        $top = max(1.0, ...array_column($runs, 'seconds'));
        $all = (int) array_sum(array_column($instances, 'runs'));

        return [
            'rows' => array_map(fn (array $run): array => [
                'label' => $run['name'] ?? 'Instance '.$run['instance_id'],
                'date' => Carbon::parse($run['at'])->utc()->format('j M Y'),
                'time' => Format::duration($run['seconds']),
                'share' => $run['seconds'] / $top * 100,
                'bosses' => $run['bosses'] === 0 ? 'No bosses' : Format::count($run['bosses'], 'boss', 'bosses'),
                'deaths' => $run['deaths'],
                'crosses' => min($run['deaths'], self::CROSSES),
                'deathsSay' => $run['deaths'] === 0 ? 'No deaths' : Format::count($run['deaths'], 'death'),
                'group' => $run['group_size'] === null ? null : ($run['group_size'] === 1 ? 'Solo' : $run['group_size'].' players'),
            ], $runs),
            'total' => Format::count($all, 'dungeon run').' in all.'.(count($runs) < $all ? ' The latest '.DungeonRuns::SHOWN.' are listed.' : ''),
        ];
    }
}
