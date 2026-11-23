<?php

namespace App\View\Pages;

use App\View\Format;

/** The `You vs everyone` panel of a hub: the page's figures for everyone beside the same figures for one character. */
final class ScopeCompare
{
    /**
     * @param  array<mixed>  $everyone  figures, each with a label and a value
     * @param  array<mixed>  $yours
     * @param  list<string>  $skip  figure labels that count players and mean nothing for one character
     * @return array{name: string, rows: list<array{0: string, 1: string, 2: string}>}
     */
    public static function figures(array $everyone, array $yours, string $name, array $skip = []): array
    {
        $mine = [];
        foreach ($yours as $figure) {
            $mine[(string) ($figure['label'] ?? '')] = (string) ($figure['value'] ?? '');
        }
        $rows = [];
        foreach ($everyone as $figure) {
            $label = (string) ($figure['label'] ?? '');
            if (! in_array($label, $skip, true) && isset($mine[$label])) {
                $rows[] = [$label, (string) ($figure['value'] ?? ''), $mine[$label]];
            }
        }

        return ['name' => Format::characterName($name), 'rows' => $rows];
    }
}
