<?php

namespace App\Support;

use JsonSerializable;

/**
 * One arm of a chart: the windows of one source under one loot rule, or at one group size inside a rule. `windows` is
 * the `n` its rates rest on. Windows with a corpse key count whole, each corpse once (docs/LOOT.md, Units of counting,
 * rule 7). Two characters of a free-for-all arm may both open one corpse, and a character with no keys and one with keys
 * cannot be told apart on it, so when the arm holds windows with no key and a second character contributed to it (it
 * has windows with no key from two characters, or windows with a key from another one) the arm counts one set and no more:
 * the windows with no key of the character with the most of them, together with the windows with a key when no other
 * character made those, else whichever is larger of that character's windows with no key and all the windows with a key
 * (rule 6). Then `characters` is those of the set counted and the arm carries `one_reader`. An arm with none of that sums
 * its characters and carries no `one_reader`.
 */
final readonly class LootArm implements JsonSerializable
{
    /**
     * @param  list<int>  $characters  the characters whose windows `windows` counts
     * @param  int|null  $reader  the character whose windows with no key are counted when only one character's are
     * @param  list<RateFlag>  $flags
     */
    private function __construct(
        public string $key,
        public string $label,
        public bool $personal,
        public int $corpses,
        public int $windows,
        public array $characters,
        public ?int $reader,
        public bool $unkeyedCounted,
        public bool $keyedCounted,
        public array $flags,
    ) {}

    /**
     * Null while fewer than `$minimumCharacters` characters contributed to the arm.
     *
     * @param  array<int, int>  $unkeyed  windows with no corpse key, by character id
     * @param  array<int, int>  $keyed  windows with a corpse key, each corpse once, by the character whose window was kept
     */
    public static function of(string $key, string $label, array $unkeyed, array $keyed, bool $freeForAll, bool $personal, bool $ruleUnknown, int $minimumCharacters): ?self
    {
        $contributors = array_keys($unkeyed + $keyed);
        if (count($contributors) < max(1, $minimumCharacters)) {
            return null;
        }
        $reader = self::busiest($unkeyed);
        $others = $reader === null ? [] : array_diff(array_keys($keyed), [$reader]);
        $oneReader = $freeForAll && $reader !== null && (count($unkeyed) >= 2 || $others !== []);
        $keyedCounted = ! $oneReader || $others === [] || array_sum($keyed) >= $unkeyed[$reader];
        $unkeyedCounted = ! $oneReader || $others === [] || ! $keyedCounted;
        $characters = $oneReader
            ? [...($unkeyedCounted ? [$reader] : []), ...($keyedCounted ? array_keys($keyed) : [])]
            : $contributors;
        $characters = array_values(array_unique($characters));
        sort($characters);
        $windows = ($unkeyedCounted ? ($oneReader ? $unkeyed[$reader] : array_sum($unkeyed)) : 0) + ($keyedCounted ? array_sum($keyed) : 0);

        return new self(
            $key, $label, $personal, array_sum($unkeyed) + array_sum($keyed), $windows, $characters, $oneReader && $unkeyedCounted ? $reader : null, $unkeyedCounted, $keyedCounted,
            RateFlag::tags($oneReader ? 0 : count($characters), false, $oneReader, $ruleUnknown),
        );
    }

    /**
     * The windows of the arm that listed one item, from the windows that listed it: the ones with a key, the ones with
     * no key of every character, and the ones with no key of the one reader. A caller with the counts of its own gives
     * the three and the arm takes the ones it counts.
     */
    public function drops(int $keyed, int $unkeyed, int $reader): int
    {
        $unkeyedDrops = $this->reader === null ? $unkeyed : $reader;

        return ($this->keyedCounted ? $keyed : 0) + ($this->unkeyedCounted ? $unkeyedDrops : 0);
    }

    /** Whether the arm counts this character's windows with no corpse key: all of them, or its one reader's. */
    public function countsUnkeyedOf(int $character): bool
    {
        return $this->unkeyedCounted && ($this->reader === null || $this->reader === $character);
    }

    /** From 10 windows an arm is drawn; under that it has counts only and takes no part in a chart. */
    public function drawn(): bool
    {
        return $this->windows >= RateSample::HOLLOW_FROM;
    }

    public function sharesCharacterWith(self $other): bool
    {
        return array_intersect($this->characters, $other->characters) !== [];
    }

    /**
     * @return array{key: string, label: string, windows: int, corpses: int, characters: int, flags: list<string>, drawn: bool}
     */
    public function jsonSerialize(): array
    {
        return [
            'key' => $this->key, 'label' => $this->label, 'windows' => $this->windows, 'corpses' => $this->corpses, 'characters' => count($this->characters),
            'flags' => array_map(fn (RateFlag $flag): string => $flag->value, $this->flags), 'drawn' => $this->drawn(),
        ];
    }

    /**
     * @param  array<int, int>  $windows
     */
    private static function busiest(array $windows): ?int
    {
        if ($windows === []) {
            return null;
        }
        ksort($windows);
        arsort($windows);

        return (int) array_key_first($windows);
    }
}
