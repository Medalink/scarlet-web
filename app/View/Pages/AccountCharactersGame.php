<?php

namespace App\View\Pages;

use App\View\Format;

/**
 * The art and game layer of /account/characters (docs/DESIGN.md, "Art and game layer"): the tally, the first places
 * among the account's characters and the next round numbers of its totals, from the rows of ShowCharacterRoster.
 *
 * @phpstan-import-type Tile from StatsGame
 * @phpstan-import-type Record from StatsGame
 * @phpstan-import-type Rule from RoundNumbers
 */
final class AccountCharactersGame
{
    private const int COPPER_PER_GOLD = 10000;

    private const int MIN_FOR_RECORDS = 2;

    /**
     * @param  list<array<string, mixed>>  $characters
     * @return array{tally: list<Tile>, records: list<Record>, rules: list<Rule>}
     */
    public static function data(array $characters): array
    {
        return [
            'tally' => self::tally($characters),
            'records' => count($characters) < self::MIN_FOR_RECORDS ? [] : self::records($characters),
            'rules' => self::rules($characters),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $characters
     * @return list<Tile>
     */
    private static function tally(array $characters): array
    {
        return [
            self::tile('characters', 'Characters', count($characters)),
            ['icon' => 'hours', 'label' => 'Time recorded', 'value' => Format::duration(self::sum($characters, 'recorded_seconds')), 'count' => null, 'unit' => null],
            self::tile('clock', 'Sessions', self::sum($characters, 'sessions')),
            self::tile('kills', 'Kills recorded', self::sum($characters, 'kills')),
            self::tile('deaths', 'Deaths recorded', self::sum($characters, 'deaths')),
            ['icon' => 'gold', 'label' => 'Gold', 'value' => Format::moneyText(self::sum($characters, 'balance')), 'count' => null, 'unit' => null],
        ];
    }

    /**
     * @return Tile
     */
    private static function tile(string $icon, string $label, int $value): array
    {
        return ['icon' => $icon, 'label' => $label, 'value' => Format::number($value), 'count' => $value, 'unit' => null];
    }

    /**
     * One tile per first place among the characters, in the order of the page; a tie goes to the character listed first.
     *
     * @param  list<array<string, mixed>>  $characters
     * @return list<Record>
     */
    private static function records(array $characters): array
    {
        $records = [];
        foreach ([
            ['level', 'Highest level', 'level', fn (int $level): string => 'Level '.$level],
            ['hours', 'Most time recorded', 'recorded_seconds', Format::duration(...)],
            ['kills', 'Most kills', 'kills', fn (int $kills): string => Format::number($kills)],
            ['deaths', 'Most deaths', 'deaths', fn (int $deaths): string => Format::number($deaths)],
            ['clock', 'Most sessions', 'sessions', fn (int $sessions): string => Format::number($sessions)],
            ['gold', 'Richest', 'balance', Format::moneyText(...)],
        ] as [$icon, $label, $key, $detail]) {
            $top = 0;
            $leader = null;
            foreach ($characters as $c) {
                $value = (int) ($c[$key] ?? 0);
                if ($value > $top) {
                    $top = $value;
                    $leader = $c;
                }
            }
            if ($leader === null) {
                continue;
            }
            $name = (string) $leader['name'];
            $records[] = ['icon' => $icon, 'value' => $detail($top), 'href' => '/c/'.rawurlencode($name), 'label' => $label, 'detail' => Format::characterName($name), 'class' => $records === [] ? 'rec-lead' : null];
        }

        return $records;
    }

    /**
     * @param  list<array<string, mixed>>  $characters
     * @return list<Rule>
     */
    private static function rules(array $characters): array
    {
        $known = array_filter(array_column($characters, 'balance'), fn (mixed $balance): bool => $balance !== null);

        return array_values(array_filter([
            RoundNumbers::rule('kills', 'Kills', self::sum($characters, 'kills')),
            RoundNumbers::rule('hours', 'Hours recorded', round(self::sum($characters, 'recorded_seconds') / 3600)),
            RoundNumbers::rule('clock', 'Sessions', self::sum($characters, 'sessions')),
            RoundNumbers::rule('deaths', 'Deaths', self::sum($characters, 'deaths')),
            RoundNumbers::rule('gold', 'Gold', $known === [] ? null : floor(self::sum($characters, 'balance') / self::COPPER_PER_GOLD), 'g'),
        ]));
    }

    /**
     * @param  list<array<string, mixed>>  $characters
     */
    private static function sum(array $characters, string $key): int
    {
        return (int) array_sum(array_column($characters, $key));
    }
}
