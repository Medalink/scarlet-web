<?php

namespace App\View\Pages;

use App\Models\Character;
use App\View\Format;

/** The data of the battlegrounds page from the answer of App\Actions\Public\CharacterBattlegrounds. */
final class BattlegroundsPage
{
    private const array FACTIONS = [0 => 'Horde', 1 => 'Alliance'];

    private const array RESULTS = ['w' => 'won', 'l' => 'lost', 'd' => 'drawn'];

    /** @var list<array{label: string, numeric?: bool}> */
    public const array COLUMNS = [
        ['label' => 'Player'], ['label' => 'Faction'], ['label' => 'Killing blows', 'numeric' => true], ['label' => 'Honorable kills', 'numeric' => true],
        ['label' => 'Deaths', 'numeric' => true], ['label' => 'Honor', 'numeric' => true], ['label' => 'Damage', 'numeric' => true], ['label' => 'Healing', 'numeric' => true],
    ];

    /**
     * @param  array{character: Character, owner: bool, matches: list<array<string, mixed>>}  $r
     * @return array{name: string, path: string, owner: bool, matches: list<array<string, mixed>>}
     */
    public static function data(array $r): array
    {
        $name = $r['character']->name;

        return [
            'name' => Format::characterName($name),
            'path' => '/c/'.rawurlencode($name),
            'owner' => $r['owner'],
            'matches' => array_map(self::match(...), $r['matches']),
        ];
    }

    /**
     * @param  array<string, mixed>  $match
     * @return array{heading: string, at: string, instant: string, rows: list<array{cells: list<mixed>}>}
     */
    private static function match(array $match): array
    {
        $result = self::RESULTS[(string) $match['result']] ?? null;
        $place = $match['zone'] ?? ($match['arena'] === true ? 'Arena' : 'Battleground');

        return [
            'heading' => $place.($result === null ? '' : ', '.$result),
            'at' => $match['at']->format('j M Y H:i').' UTC',
            'instant' => $match['at']->toIso8601String(),
            'rows' => array_values(array_map(self::row(...), $match['rows'])),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{cells: list<mixed>}
     */
    private static function row(array $row): array
    {
        $number = fn (mixed $value): string => $value === null ? '–' : Format::number((int) $value);
        $player = $row['name'] === null
            ? 'a player'
            : ['text' => Format::characterName((string) $row['name']), 'dot' => true, 'characterClass' => strtoupper((string) $row['class']), ...($row['public'] ? ['href' => '/c/'.rawurlencode((string) $row['name'])] : [])];

        return ['cells' => [
            $player,
            $row['faction'] === null ? '–' : (self::FACTIONS[(int) $row['faction']] ?? '–'),
            $number($row['killing_blows']), $number($row['honorable_kills']), $number($row['deaths']),
            $number($row['honor_gained']), $number($row['damage_done']), $number($row['healing_done']),
        ]];
    }
}
