<?php

namespace App\View\Pages;

use App\View\Format;

/**
 * The data of the spell page from the answer of App\Actions\Public\SpellRecord.
 */
final class SpellPage
{
    /** The client's power types (`Enum.PowerType`) as costs name them. */
    private const array POWERS = [0 => 'mana', 1 => 'rage', 2 => 'focus', 3 => 'energy'];

    /**
     * @param  array<string, mixed>  $r  the answer of SpellRecord
     * @return array<string, mixed>
     */
    public static function from(array $r): array
    {
        /** @var array<string, mixed>|null $facts */
        $facts = $r['facts'];
        /** @var array<string, mixed>|null $recipe */
        $recipe = $r['recipe'];
        $made = $recipe === null ? null : $recipe['output'];

        return [
            'name' => $r['name'],
            'sub' => $facts['description'] ?? '',
            'figures' => $facts === null ? [] : [
                ['label' => 'Cast time', 'value' => self::cast($facts['cast_ms'])],
                ['label' => 'Range', 'value' => self::range($facts['min_range'], $facts['max_range'])],
                ['label' => 'Cost', 'value' => self::costs((string) ($facts['costs'] ?? ''))],
                ['label' => 'Kind', 'value' => $facts['passive'] === null ? '–' : ($facts['passive'] ? 'Passive' : 'Active')],
            ],
            'makes' => $made === null ? null : CraftingRows::makes($made),
            'makesHref' => ($made['item_id'] ?? null) === null ? null : '/items/'.$made['item_id'],
            'reagents' => $recipe === null ? [] : CraftingRows::reagents($recipe['reagents']),
            'optional' => $recipe['optional_slots'] ?? null,
        ];
    }

    private static function cast(mixed $milliseconds): string
    {
        if ($milliseconds === null) {
            return '–';
        }

        return (int) $milliseconds === 0 ? 'Instant' : Format::number((int) $milliseconds / 1000, 1).' s';
    }

    private static function range(mixed $min, mixed $max): string
    {
        if ($max === null) {
            return '–';
        }
        if ((int) $max === 0) {
            return 'Self';
        }

        return ($min === null || (int) $min === 0 ? '' : Format::number((int) $min).' to ').Format::number((int) $max).' yd';
    }

    /** `powerType:cost` joined by commas, as "30 mana". */
    private static function costs(string $costs): string
    {
        $parts = [];
        foreach (explode(',', $costs) as $entry) {
            [$type, $cost] = [...explode(':', $entry, 2), ''];
            if (! ctype_digit($type) || ! ctype_digit($cost) || (int) $cost === 0) {
                continue;
            }
            $parts[] = Format::number((int) $cost).' '.(self::POWERS[(int) $type] ?? 'power '.$type);
        }

        return $parts === [] ? 'None' : implode(', ', $parts);
    }
}
