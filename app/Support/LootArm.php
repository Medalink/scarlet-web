<?php

namespace App\Support;

use JsonSerializable;

/**
 * One arm of a chart: the windows of one source under one loot rule, or at one group size inside a rule. `windows` is
 * the `n` its rates rest on. Windows with a corpse key count whole, each corpse once (docs/LOOT.md, Units of counting,
 * rule 7). Two characters of a free-for-all arm may both open one corpse, and a character with no keys and one with keys
 * cannot be told apart on it, so when the arm holds windows with no key from two characters, or a day on which the
 * character with the most of them has some and another character has windows with a key, the arm counts one reader: that
 * character's windows with no key, with the windows with a key. On a day where both exist it counts the larger of the
 * two sets, on every other day both (rule 6). Then `characters` is those of the sets counted and the arm carries
 * `one_reader`. An arm with none of that sums its characters and carries no `one_reader`.
 */
final readonly class LootArm implements JsonSerializable
{
    /**
     * @param  list<int>  $characters  the characters whose windows `windows` counts
     * @param  int|null  $reader  the character whose windows with no key are counted when only one character's are
     * @param  array<string, array{unkeyed: bool, keyed: bool}>  $days  which windows each day counts, by day (`''` for an arm read without days)
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
        public array $days,
        public array $flags,
    ) {}

    /**
     * Null while fewer than `$minimumCharacters` characters contributed to the arm. The one reader's larger-set rule
     * (rule 6) applies on the days where it can matter: the reader has windows with no key and another character has
     * windows with a key. Every other day counts all its windows with a key and the reader's windows with no key (or every
     * character's, where no other has windows with no key), so old windows with no key and new ones with a key that fall
     * on different days are all counted.
     *
     * @param  array<string, array{unkeyed: array<int, int>, keyed: array<int, int>}>  $days  windows by day, then by character id
     */
    public static function overDays(string $key, string $label, array $days, bool $freeForAll, bool $personal, bool $ruleUnknown, int $minimumCharacters): ?self
    {
        $unkeyed = $keyed = [];
        foreach ($days as $parts) {
            foreach ($parts['unkeyed'] as $character => $windows) {
                $unkeyed[$character] = ($unkeyed[$character] ?? 0) + $windows;
            }
            foreach ($parts['keyed'] as $character => $windows) {
                $keyed[$character] = ($keyed[$character] ?? 0) + $windows;
            }
        }
        $contributors = array_keys($unkeyed + $keyed);
        if (count($contributors) < max(1, $minimumCharacters)) {
            return null;
        }
        $reader = self::busiest($unkeyed);
        $contested = $freeForAll && $reader !== null
            ? array_keys(array_filter($days, fn (array $parts): bool => ($parts['unkeyed'][$reader] ?? 0) > 0 && array_sum(array_diff_key($parts['keyed'], [$reader => 0])) > 0))
            : [];
        $oneReader = $freeForAll && $reader !== null && (count($unkeyed) >= 2 || $contested !== []);

        $counted = [];
        $characters = [];
        $windows = 0;
        foreach ($days as $day => $parts) {
            $own = $oneReader ? ($parts['unkeyed'][$reader] ?? 0) : array_sum($parts['unkeyed']);
            $whole = array_sum($parts['keyed']);
            $keyedWins = ! in_array($day, $contested, true) || $whole >= $own;
            $counted[$day] = ['unkeyed' => ! in_array($day, $contested, true) || ! $keyedWins, 'keyed' => $keyedWins];
            $windows += ($counted[$day]['unkeyed'] ? $own : 0) + ($counted[$day]['keyed'] ? $whole : 0);
            if ($counted[$day]['unkeyed']) {
                array_push($characters, ...($oneReader ? ($own > 0 ? [$reader] : []) : array_keys($parts['unkeyed'])));
            }
            if ($counted[$day]['keyed']) {
                array_push($characters, ...array_keys($parts['keyed']));
            }
        }
        $characters = array_values(array_unique($characters));
        sort($characters);

        return new self(
            $key, $label, $personal, array_sum($unkeyed) + array_sum($keyed), $windows, $characters, $oneReader ? $reader : null, $counted,
            RateFlag::tags($oneReader ? 0 : count($characters), false, $oneReader, $ruleUnknown),
        );
    }

    /**
     * The windows of an arm made by `overDays` that listed one item, from the windows that listed it each day: the ones with
     * a key, the ones with no key of every character and the ones with no key of the one reader. Each day adds the sets
     * the arm counts that day.
     *
     * @param  array<string, array{keyed: int, unkeyed: int, reader: int}>  $days
     */
    public function dropsOver(array $days): int
    {
        $drops = 0;
        foreach ($days as $day => $listed) {
            $counts = $this->days[$day] ?? ['unkeyed' => true, 'keyed' => true];
            $drops += ($counts['keyed'] ? $listed['keyed'] : 0) + ($counts['unkeyed'] ? ($this->reader === null ? $listed['unkeyed'] : $listed['reader']) : 0);
        }

        return $drops;
    }

    /** Whether the arm counts this character's windows with no corpse key on the day: all of them, or its one reader's. */
    public function countsUnkeyedOn(string $day, int $character): bool
    {
        return ($this->days[$day]['unkeyed'] ?? true) && ($this->reader === null || $this->reader === $character);
    }

    /** Whether the arm counts the windows with a corpse key of the day. */
    public function countsKeyedOn(string $day): bool
    {
        return $this->days[$day]['keyed'] ?? true;
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
