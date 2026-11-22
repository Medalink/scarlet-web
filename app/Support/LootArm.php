<?php

namespace App\Support;

use JsonSerializable;

/**
 * One arm of a chart: the windows of one source under one loot rule, or at one group size inside a rule. `windows` is
 * the `n` its rates rest on. Windows with a corpse key count whole, each corpse once (docs/LOOT.md, Units of counting,
 * rule 7). Windows with no key of a free-for-all arm that two or more characters contributed to count their one reader,
 * the character with the most of them, since two players of a group may both open one corpse and neither upload sees
 * the other's read (rule 6); then `windows` is that character's unkeyed windows and the keyed ones, `characters` is that
 * character and those of the keyed windows, and the arm carries `one_reader`. An arm with no such unkeyed windows sums
 * its characters and carries no `one_reader`.
 */
final readonly class LootArm implements JsonSerializable
{
    /**
     * @param  list<int>  $characters  the characters whose windows `windows` counts
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
        $reader = null;
        if ($freeForAll && count($unkeyed) >= 2) {
            $busiest = $unkeyed;
            ksort($busiest);
            arsort($busiest);
            $reader = (int) array_key_first($busiest);
        }
        $characters = $reader === null ? $contributors : array_values(array_unique([$reader, ...array_keys($keyed)]));
        sort($characters);

        return new self(
            $key, $label, $personal, array_sum($unkeyed) + array_sum($keyed), ($reader === null ? array_sum($unkeyed) : $unkeyed[$reader]) + array_sum($keyed), $characters, $reader,
            RateFlag::tags($reader === null ? count($characters) : 0, false, $reader !== null, $ruleUnknown),
        );
    }

    /** Whether the arm counts this character's windows with no corpse key: all of them, or its one reader's. */
    public function countsUnkeyedOf(int $character): bool
    {
        return $this->reader === null || $this->reader === $character;
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
}
