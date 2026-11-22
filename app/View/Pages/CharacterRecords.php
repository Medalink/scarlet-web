<?php

namespace App\View\Pages;

use App\View\Format;
use Illuminate\Support\Carbon;

/**
 * The Records strip of the character page: a lead tile and the tiles of the other facts the character holds a record in.
 * A fact the page shows in a list or a figure elsewhere is not a tile.
 */
final class CharacterRecords
{
    /** Tiles beside the lead. */
    public const int SHOWN = 6;

    /** Tiles that fill the two rows beside the lead. */
    private const int BESIDE = 4;

    /** Tiles, the lead included, a strip needs to be worth a section. */
    private const int LEAST = 3;

    /**
     * @param  array<string, mixed>  $records  the answer of App\Actions\Public\Character\Records
     * @param  array<string, mixed>  $totals  the answer of App\Actions\Public\Character\SessionTotals
     * @return array{lead: array<string, string|null>, tiles: list<array<string, string|null>>}|null
     */
    public static function tiles(array $records, array $totals, string $since = ''): ?array
    {
        $since = ltrim($since);
        $tiles = [];
        if ($records['blow'] !== null) {
            $blow = $records['blow'];
            $tiles[] = self::tile('deaths', Format::number($blow['damage']), 'Biggest killing blow from a creature', implode(', ', array_filter([
                'Killed by '.$blow['killer'], $blow['level'] === null ? null : 'level '.$blow['level'], $blow['zone'],
            ])));
        }
        if ($totals['longest'] !== null) {
            $tiles[] = self::tile('clock', Format::duration($totals['longest']['seconds']), 'Longest session', self::day($totals['longest']['at']));
        }
        if ($totals['busiest'] !== null) {
            $tiles[] = self::tile('kills', Format::number($totals['busiest']['kills']), 'Most kills in one session', self::day($totals['busiest']['at']));
        }
        if ($totals['questiest'] !== null) {
            $tiles[] = self::tile('quests', Format::number($totals['questiest']['quests']), 'Most quests in one session', self::day($totals['questiest']['at']));
        }
        if ($records['nodes'] > 0) {
            $tiles[] = self::tile('gather', Format::number($records['nodes']), 'Nodes gathered', $since, self::taken('Most gathered', $records['topNode']));
        }
        if ($records['fish'] > 0) {
            $tiles[] = self::tile('fish', Format::number($records['fish']), 'Fish caught', $since, self::taken('Most caught', $records['topFish']));
        }
        if (count($tiles) < self::LEAST) {
            return null;
        }
        $lead = array_shift($tiles);
        $tiles = array_slice($tiles, 0, self::SHOWN);

        return ['lead' => $lead, 'tiles' => self::spans($tiles)];
    }

    /**
     * Gives each tile its width on a 4-column strip whose lead takes two columns and two rows: the first four tiles fill
     * the rows beside it, the rest share a row of their own, and a short strip stretches its last tiles.
     *
     * @param  list<array<string, string|null>>  $tiles
     * @return list<array<string, string|null>>
     */
    private static function spans(array $tiles): array
    {
        $beside = min(count($tiles), self::BESIDE);
        $below = count($tiles) - $beside;
        foreach ($tiles as $i => $tile) {
            $tiles[$i]['span'] = match (true) {
                $i >= self::BESIDE => $below === 1 ? 'c4' : 'c2',
                $beside === 1 => 'c2 r2',
                $beside === 2 => 'c2',
                $beside === 3 && $i === 2 => 'c2',
                default => null,
            };
        }

        return $tiles;
    }

    /** @param  array{item_id: int, name: string, quantity: int}|null  $taken */
    private static function taken(string $label, ?array $taken): ?string
    {
        return $taken === null ? null : $label.': '.$taken['name'].', '.Format::number($taken['quantity']);
    }

    /** @return array<string, string|null> */
    private static function tile(string $icon, string $value, string $label, ?string $detail = null, ?string $extra = null): array
    {
        return ['icon' => $icon, 'value' => $value, 'label' => $label, 'detail' => $detail === '' ? null : $detail, 'extra' => $extra, 'span' => null];
    }

    private static function day(string $at): string
    {
        return Carbon::parse($at)->utc()->format('j M Y');
    }
}
