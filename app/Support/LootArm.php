<?php

namespace App\Support;

use JsonSerializable;

/**
 * One arm of a chart: the windows of one source under one loot rule, or at one group size inside a rule. `windows` is
 * the `n` its rates rest on. A free-for-all arm that two or more characters contributed to counts its one reader, the
 * character with the most windows in it, since two players of a group may both open one corpse and neither upload sees
 * the other's read (docs/LOOT.md, Units of counting, rule 6); then `windows` is that character's windows, `characters`
 * is that character alone and the arm carries `one_reader`. Every other arm sums its characters.
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
     * @param  array<int, int>  $windowsByCharacter  windows keyed by character id
     */
    public static function of(string $key, string $label, array $windowsByCharacter, bool $freeForAll, bool $personal, bool $ruleUnknown, int $minimumCharacters): ?self
    {
        if (count($windowsByCharacter) < max(1, $minimumCharacters)) {
            return null;
        }
        $corpses = array_sum($windowsByCharacter);
        $reader = null;
        if ($freeForAll && count($windowsByCharacter) >= 2) {
            $busiest = $windowsByCharacter;
            ksort($busiest);
            arsort($busiest);
            $reader = (int) array_key_first($busiest);
        }
        $characters = $reader === null ? array_keys($windowsByCharacter) : [$reader];
        sort($characters);

        return new self($key, $label, $personal, $corpses, $reader === null ? $corpses : $windowsByCharacter[$reader], $characters, $reader, RateFlag::tags($reader === null ? count($characters) : 0, false, $reader !== null, $ruleUnknown));
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
