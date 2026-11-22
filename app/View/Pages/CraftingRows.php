<?php

namespace App\View\Pages;

use App\View\Format;

/**
 * The table rows of recipes and boss drops that the item, spell and encounter pages share, from the answers of
 * App\Actions\Public\RecipeFacts and BossDrops.
 */
final class CraftingRows
{
    /**
     * @param  list<array{item_id: int, name: string, quantity: int}>  $reagents
     */
    public static function needs(array $reagents): string
    {
        return implode(', ', array_map(fn (array $reagent): string => $reagent['quantity'].' '.$reagent['name'], $reagents));
    }

    /**
     * @param  array{item_id: int|null, name: string|null, min: int|null, max: int|null}  $output
     */
    public static function makes(array $output): string
    {
        if ($output['name'] === null) {
            return '';
        }
        $most = $output['max'] ?? $output['min'];
        $least = $output['min'] ?? $most;
        $count = $most === null || $least === null ? '' : ($least === $most ? $most.' ' : $least.' to '.$most.' ');

        return $count.$output['name'];
    }

    /**
     * Recipes with the item they make and what they need, each linked to its spell page.
     *
     * @param  list<array<string, mixed>>  $recipes
     * @return list<array{cells: list<array<string, mixed>|string>}>
     */
    public static function recipes(array $recipes): array
    {
        return array_map(fn (array $recipe): array => ['cells' => [
            ['text' => $recipe['name'], 'href' => '/spells/'.$recipe['spell_id']],
            self::makes($recipe['output']),
            self::needs($recipe['reagents']),
        ]], $recipes);
    }

    /**
     * Reagents of one recipe, each linked to its item page.
     *
     * @param  list<array{item_id: int, name: string, quantity: int}>  $reagents
     * @return list<array{cells: list<array<string, mixed>|string>}>
     */
    public static function reagents(array $reagents): array
    {
        return array_map(fn (array $reagent): array => ['cells' => [
            ['text' => $reagent['name'], 'href' => '/items/'.$reagent['item_id']],
            Format::number($reagent['quantity']),
        ]], $reagents);
    }

    /**
     * @param  list<array{encounter_id: int, item_id: int, dropped: int, characters: int}>  $drops
     * @return list<array{cells: list<array<string, mixed>|string>}>
     */
    public static function encounters(array $drops): array
    {
        return array_map(fn (array $drop): array => ['cells' => [
            ['text' => 'Encounter '.$drop['encounter_id'], 'href' => '/encounters/'.$drop['encounter_id']],
            Format::number($drop['dropped']),
        ]], $drops);
    }
}
