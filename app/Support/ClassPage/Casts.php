<?php

namespace App\Support\ClassPage;

use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\SpellTotals;

/** What the Rogue, Priest and Shaman pages do with spells the same way: fold ranks by name, rank them by casts, and stack them by level. */
final class Casts
{
    /** The icon of a spell total's most cast rank, or null when it was never cast. */
    public static function icon(SpellTotals $spell): ?Icon
    {
        return $spell->iconId === 0 ? null : Icon::spell($spell->iconId);
    }

    /**
     * One total for each spell name, in the order given, whether or not it was cast.
     *
     * @param  list<string>  $names
     * @return list<SpellTotals>
     */
    public static function named(ClassRows $rows, array $names): array
    {
        return array_map(fn (string $name): SpellTotals => $rows->spells()->sum($name, $name), $names);
    }

    /**
     * The spells that were cast, the most cast first.
     *
     * @param  list<SpellTotals>  $spells
     * @return list<SpellTotals>
     */
    public static function cast(array $spells): array
    {
        $cast = array_values(array_filter($spells, fn (SpellTotals $spell): bool => $spell->casts > 0));
        usort($cast, fn (SpellTotals $a, SpellTotals $b): int => [$b->casts, $a->name] <=> [$a->casts, $b->name]);

        return $cast;
    }

    /**
     * A rank of casts with the class colour, one row for each spell that was cast.
     *
     * @param  list<SpellTotals>  $spells
     */
    public static function rank(array $spells, int $most = 0): ?Rank
    {
        $cast = self::cast($spells);
        $cast = $most > 0 ? array_slice($cast, 0, $most) : $cast;
        if ($cast === []) {
            return null;
        }

        return new Rank(array_map(fn (SpellTotals $spell): RankRow => new RankRow($spell->name, $spell->casts, self::icon($spell), Tone::Own), $cast));
    }

    /**
     * The share of casts each spell had at every level that has a cast, as a stack. A spell nobody cast is not a series.
     *
     * @param  list<SpellTotals>  $spells
     * @param  list<int>  $levels
     * @param  list<string>  $hues  one colour for each spell, in the order of `$spells`
     */
    public static function stack(array $spells, array $levels, array $hues, string $what): ?Stack
    {
        $used = array_keys(array_filter($spells, fn (SpellTotals $spell): bool => $spell->casts > 0));
        $columns = [];
        foreach ($levels as $level) {
            $total = array_sum(array_map(fn (int $at): int => $spells[$at]->at($level), $used));
            if ($total > 0) {
                $columns[] = new StackColumn($level, array_map(fn (int $at): float => $spells[$at]->at($level) / $total, $used));
            }
        }
        if ($columns === []) {
            return null;
        }

        return new Stack(
            $columns,
            array_map(fn (int $at): Series => new Series($spells[$at]->name, new Hue($hues[$at])), $used),
            'Share of '.$what.' that was each spell at each level from '.$columns[0]->label.' to '.$columns[count($columns) - 1]->label,
        );
    }

    /**
     * The first level each spell was cast at, for a note on when a spell came in.
     *
     * @param  list<SpellTotals>  $spells
     * @return array<string, int> by name, only the spells that were cast
     */
    public static function firstLevels(array $spells): array
    {
        $first = [];
        foreach ($spells as $spell) {
            if ($spell->casts > 0 && $spell->byLevel !== []) {
                $first[$spell->name] = min(array_keys($spell->byLevel));
            }
        }
        asort($first);

        return $first;
    }

    /** The share of one number in another from 0 to 100, 0 when the other is 0. */
    public static function share(int|float $part, int|float $whole): float
    {
        return $whole > 0 ? min(100, $part / $whole * 100) : 0.0;
    }

    /** A number a played hour, 0 when no time was played. */
    public static function perHour(int|float $count, int $playedMs): float
    {
        return $playedMs > 0 ? $count / ($playedMs / 3_600_000) : 0.0;
    }
}
