<?php

namespace App\View\Pages;

use App\Support\GatherKind;

/** The words a gathering page changes with the kind: what an attempt and its result are called, and the labels built on them. */
final class GatheringWords
{
    /**
     * @return array<string, string>
     */
    public static function of(GatherKind $kind): array
    {
        $words = match ($kind) {
            GatherKind::Fish => [
                'description' => 'What players catch with Fishing in WoW Forever, how long a bite takes, where they fish and when, and the characters with the most catches.',
                'attempt' => 'cast', 'result' => 'catch', 'results' => 'catches', 'doing' => 'fish',
                'who' => 'Characters who fish', 'item' => 'Catch', 'lands' => 'Casts that catch', 'median' => 'Median wait for a bite', 'wait' => 'bite',
                'gave' => 'Something on the line', 'none' => 'No bite, or the fish got away', 'trip' => 'A fishing trip', 'at' => 'at the water',
            ],
            GatherKind::Herb => [
                'description' => 'What players pick with Herbalism in WoW Forever, how long a pick takes, where they pick and when, and the characters with the most picks.',
                'attempt' => 'pick', 'result' => 'pick', 'results' => 'picks', 'doing' => 'pick herbs',
                'who' => 'Characters who pick herbs', 'item' => 'Herb', 'lands' => 'Picks that give an herb', 'median' => 'Median pick', 'wait' => 'pick',
                'gave' => 'Herb taken', 'none' => 'Nothing taken', 'trip' => 'A picking trip', 'at' => '',
            ],
            GatherKind::Ore => [
                'description' => 'What players mine with Mining in WoW Forever, how many strikes a vein takes, where they mine and when, and the characters with the most strikes.',
                'attempt' => 'strike', 'result' => 'strike', 'results' => 'strikes', 'doing' => 'mine',
                'who' => 'Characters who mine', 'item' => 'Ore', 'lands' => 'Strikes that give ore', 'median' => 'Median strike', 'wait' => 'strike',
                'gave' => 'Ore taken', 'none' => 'Nothing taken', 'trip' => 'A mining trip', 'at' => '',
            ],
            GatherKind::Skin => [
                'description' => 'What players skin with Skinning in WoW Forever, which creatures give the most, where they skin and when, and the characters with the most skins.',
                'attempt' => 'skin', 'result' => 'skin', 'results' => 'skins', 'doing' => 'skin',
                'who' => 'Characters who skin', 'item' => 'Item', 'lands' => 'Skins that give leather', 'median' => 'Median skin', 'wait' => 'skin',
                'gave' => 'Leather taken', 'none' => 'Nothing taken', 'trip' => 'A skinning trip', 'at' => '',
            ],
        };

        return ['title' => $kind->label(), 'attempts' => $words['attempt'].'s', ...$words];
    }
}
