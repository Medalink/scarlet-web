<?php

namespace App\View\Pages;

use App\View\Format;
use Illuminate\Support\Carbon;

/** The Lifetime module of the character page, from the `lifetime` of App\Actions\Public\CharacterOverview. */
final class CharacterLifetime
{
    private const int WORLD_BOSS = 2;

    /**
     * @param  array{quests: int|null, titles: int|null, zones: int|null, locks: list<array<string, mixed>>}|null  $lifetime
     * @return array{figures: list<array<string, mixed>>, locks: array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>}|null}|null
     */
    public static function data(?array $lifetime): ?array
    {
        if ($lifetime === null) {
            return null;
        }
        $figures = array_values(array_filter([
            $lifetime['quests'] === null ? null : ['label' => 'Quests completed', 'value' => Format::number($lifetime['quests']), 'icon' => 'quests'],
            $lifetime['titles'] === null ? null : ['label' => 'Titles known', 'value' => Format::number($lifetime['titles']), 'icon' => 'crown'],
            $lifetime['zones'] === null ? null : ['label' => 'Zones explored', 'value' => Format::number($lifetime['zones']), 'icon' => 'zones'],
        ]));

        return ['figures' => $figures, 'locks' => $lifetime['locks'] === [] ? null : self::locks($lifetime['locks'])];
    }

    /**
     * @param  list<array<string, mixed>>  $locks
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>}
     */
    private static function locks(array $locks): array
    {
        return [
            'columns' => [['label' => 'Instance'], ['label' => 'Bosses', 'numeric' => true], ['label' => 'Resets']],
            'rows' => array_map(fn (array $lock): array => ['cells' => [
                ($lock['name'] ?? '') === '' ? 'Unnamed instance' : (string) $lock['name'].($lock['raid'] === self::WORLD_BOSS ? ', world boss' : '').($lock['extended'] ? ', extended' : ''),
                $lock['done'] === null || $lock['total'] === null ? '' : $lock['done'].' of '.$lock['total'],
                ['instant' => $lock['resets_at'], 'format' => 'D j M H:i', 'text' => Carbon::parse((string) $lock['resets_at'])->format('D j M H:i')],
            ]], $locks),
        ];
    }
}
