<?php

namespace App\Support\ClassPage\Mage;

use App\Support\ClassPage\Icon;
use App\Support\ClassPage\Module;
use App\Support\ClassPage\Pair;
use App\Support\ClassPage\PairRow;
use App\Support\ClassPage\Rank;
use App\Support\ClassPage\RankRow;
use App\Support\ClassPage\Span;
use App\Support\ClassRows\SpellBook;
use App\Support\ClassRows\SpellTotals;
use App\View\Format;

/** Portals and teleports by city: each city's Portal and Teleport spell as one row. */
final readonly class Portals
{
    private const int CITIES = 6;

    /** @param  array<string, array{portals: int, teleports: int, icon: SpellTotals|null}>  $cities */
    private function __construct(private array $cities, private Spells $spells) {}

    public static function of(SpellBook $book, Spells $spells): self
    {
        $cities = [];
        foreach ($book->named('Portal: *') as $portal) {
            $city = self::city($portal->name);
            $cities[$city] = ['portals' => ($cities[$city]['portals'] ?? 0) + $portal->casts, 'teleports' => $cities[$city]['teleports'] ?? 0, 'icon' => $cities[$city]['icon'] ?? $portal];
        }
        foreach ($book->named('Teleport: *') as $teleport) {
            $city = self::city($teleport->name);
            $cities[$city] = ['portals' => $cities[$city]['portals'] ?? 0, 'teleports' => ($cities[$city]['teleports'] ?? 0) + $teleport->casts, 'icon' => $cities[$city]['icon'] ?? $teleport];
        }
        uasort($cities, fn (array $a, array $b): int => [$b['portals'] + $b['teleports']] <=> [$a['portals'] + $a['teleports']]);

        return new self($cities, $spells);
    }

    /**
     * The city whose portals were opened most, with its share of every portal, or null when none was opened.
     *
     * @return array{city: string, share: float|int}|null
     */
    public function mostOpened(): ?array
    {
        $best = null;
        foreach ($this->cities as $city => $count) {
            if ($count['portals'] > 0 && ($best === null || $count['portals'] > $this->cities[$best]['portals'])) {
                $best = $city;
            }
        }

        return $best === null ? null : ['city' => $best, 'share' => $this->cities[$best]['portals'] / $this->spells->portals->casts * 100];
    }

    public function module(Span $span = Span::W5): ?Module
    {
        if ($this->cities === []) {
            return null;
        }
        $rows = [];
        foreach (array_slice($this->cities, 0, self::CITIES, true) as $city => $count) {
            $rows[] = new RankRow($city, $count['portals'] + $count['teleports'], $count['icon'] === null ? null : Icon::spell($count['icon']->iconId), aside: Format::count($count['portals'], 'portal').', '.Format::count($count['teleports'], 'teleport'));
        }
        $interrupted = $this->spells->portals->interrupts + $this->spells->teleports->interrupts;

        return new Module('Portals and teleports', [new Rank($rows), new Pair([
            new PairRow('Portals', $this->spells->portals->casts),
            new PairRow('Teleports', $this->spells->teleports->casts),
            new PairRow('Interrupted', $interrupted),
        ])], $span);
    }

    private static function city(string $spell): string
    {
        return trim(substr($spell, (int) strpos($spell, ':') + 1));
    }
}
