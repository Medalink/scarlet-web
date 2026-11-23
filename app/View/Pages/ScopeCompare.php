<?php

namespace App\View\Pages;

use App\View\Format;

/** The `You vs everyone` panel of a hub: the page's figures for everyone beside the same figures for one character. */
final class ScopeCompare
{
    /**
     * @param  list<array{label: string, value: string, icon?: string|null}>  $everyone
     * @param  list<array{label: string, value: string, icon?: string|null}>  $yours
     * @param  list<string>  $skip  figure labels that count players and mean nothing for one character
     * @return array{name: string, rows: list<array{0: string, 1: string, 2: string}>}
     */
    public static function figures(array $everyone, array $yours, string $name, array $skip = []): array
    {
        $mine = array_column($yours, 'value', 'label');
        $rows = [];
        foreach ($everyone as $figure) {
            if (! in_array($figure['label'], $skip, true) && isset($mine[$figure['label']])) {
                $rows[] = [$figure['label'], $figure['value'], $mine[$figure['label']]];
            }
        }

        return ['name' => Format::characterName($name), 'rows' => $rows];
    }
}
