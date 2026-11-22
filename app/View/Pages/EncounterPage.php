<?php

namespace App\View\Pages;

use App\View\Format;

/**
 * The data of the encounter page from the answer of App\Actions\Public\EncounterRecord.
 */
final class EncounterPage
{
    /**
     * @param  array<string, mixed>  $r  the answer of EncounterRecord
     * @return array<string, mixed>
     */
    public static function from(array $r): array
    {
        /** @var list<array{encounter_id: int, item_id: int, dropped: int, characters: int, item: string}> $drops */
        $drops = $r['drops'];

        return [
            'id' => $r['id'],
            'sub' => Format::count(array_sum(array_column($drops, 'dropped')), 'item').' dropped, of '.Format::count(count($drops), 'kind').'.',
            'rows' => array_map(fn (array $drop): array => ['cells' => [
                ['text' => $drop['item'], 'href' => '/items/'.$drop['item_id']],
                Format::number($drop['dropped']),
                Format::number($drop['characters']),
            ]], $drops),
        ];
    }
}
