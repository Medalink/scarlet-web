<?php

namespace App\Support\ClassRows;

use Illuminate\Support\Str;

/**
 * Every spell a character cast, with the ranks of one name folded together. A builder names spells the way
 * `App\Support\FeatRules` does: by the client's names, `*` standing for any text.
 */
final class SpellBook
{
    /** @var array<string, SpellTotals> */
    private array $byName = [];

    /**
     * @param  list<\stdClass>  $rows  columns spell_id, level, casts, fight, cast_ms, channel_ms, interrupts, streak
     * @param  array<int, string>  $names  spell id to name
     */
    public function __construct(array $rows, private readonly array $names)
    {
        $grouped = [];
        foreach ($rows as $row) {
            $name = $names[(int) $row->spell_id] ?? null;
            if ($name === null) {
                continue;
            }
            $one = new SpellTotals(
                $name, (int) $row->casts, (int) $row->fight, (int) $row->cast_ms, (int) $row->channel_ms, (int) $row->interrupts, (int) $row->streak,
                $row->level === null ? [] : [(int) $row->level => ['casts' => (int) $row->casts, 'fight' => (int) $row->fight, 'channelMs' => (int) $row->channel_ms]],
                [(int) $row->spell_id => (int) $row->casts],
                $row->level === null ? [] : [(int) $row->spell_id => [(int) $row->level => (int) $row->casts]],
            );
            $grouped[$name] = isset($grouped[$name]) ? $grouped[$name]->merge($one) : $one;
        }
        uasort($grouped, fn (SpellTotals $a, SpellTotals $b): int => [$b->casts, $a->name] <=> [$a->casts, $b->name]);
        $this->byName = $grouped;
    }

    /** @return list<SpellTotals> the spells whose name matches a pattern, one per name, the most cast first */
    public function named(string ...$patterns): array
    {
        return array_values(array_filter($this->byName, fn (SpellTotals $spell): bool => self::matches($patterns, $spell->name)));
    }

    /** Every match as one spell named `$name`; a spell nobody cast is all zeros. */
    public function sum(string $name, string ...$patterns): SpellTotals
    {
        $total = SpellTotals::none($name);
        foreach ($this->named(...$patterns) as $spell) {
            $total = $total->merge($spell, $name);
        }

        return $total;
    }

    /** @return list<SpellTotals> every spell, the most cast first */
    public function all(): array
    {
        return array_values($this->byName);
    }

    public function nameOf(int $spellId): ?string
    {
        return $this->names[$spellId] ?? null;
    }

    /** The first level any spell was cast at, or null. */
    public function firstLevel(): ?int
    {
        $levels = [];
        foreach ($this->byName as $spell) {
            array_push($levels, ...array_keys($spell->byLevel));
        }

        return $levels === [] ? null : min($levels);
    }

    /** @param  array<array-key, string>  $patterns */
    public static function matches(array $patterns, string $name): bool
    {
        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $name, true)) {
                return true;
            }
        }

        return false;
    }
}
