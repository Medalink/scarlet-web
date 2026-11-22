<?php

namespace App\Support\ClassRows;

/**
 * What a character cast of one spell name over every rank: totals, by level, and by rank. The icon of the spell is the
 * icon of the rank cast most. `ranks` are the spell ids with their casts, `rankLevels` the casts of each rank by level.
 */
final readonly class SpellTotals
{
    public int $iconId;

    /**
     * @param  array<int, array{casts: int, fight: int, channelMs: int}>  $byLevel
     * @param  array<int, int>  $ranks  casts by spell id
     * @param  array<int, array<int, int>>  $rankLevels  casts by spell id, then by level
     */
    public function __construct(
        public string $name,
        public int $casts,
        public int $fightCasts,
        public int $castMs,
        public int $channelMs,
        public int $interrupts,
        public int $streak,
        public array $byLevel,
        public array $ranks,
        public array $rankLevels,
    ) {
        $top = 0;
        $most = -1;
        foreach ($ranks as $id => $count) {
            if ($count > $most) {
                $top = $id;
                $most = $count;
            }
        }
        $this->iconId = $top;
    }

    /** @return list<int> */
    public function ids(): array
    {
        return array_keys($this->ranks);
    }

    public static function none(string $name): self
    {
        return new self($name, 0, 0, 0, 0, 0, 0, [], [], []);
    }

    public function merge(self $other, ?string $name = null): self
    {
        $levels = $this->byLevel;
        foreach ($other->byLevel as $level => $row) {
            $mine = $levels[$level] ?? ['casts' => 0, 'fight' => 0, 'channelMs' => 0];
            $levels[$level] = ['casts' => $mine['casts'] + $row['casts'], 'fight' => $mine['fight'] + $row['fight'], 'channelMs' => $mine['channelMs'] + $row['channelMs']];
        }
        $ranks = $this->ranks;
        $rankLevels = $this->rankLevels;
        foreach ($other->ranks as $id => $count) {
            $ranks[$id] = ($ranks[$id] ?? 0) + $count;
            foreach ($other->rankLevels[$id] ?? [] as $level => $casts) {
                $rankLevels[$id][$level] = ($rankLevels[$id][$level] ?? 0) + $casts;
            }
        }
        ksort($levels);

        return new self(
            $name ?? $this->name,
            $this->casts + $other->casts,
            $this->fightCasts + $other->fightCasts,
            $this->castMs + $other->castMs,
            $this->channelMs + $other->channelMs,
            $this->interrupts + $other->interrupts,
            max($this->streak, $other->streak),
            $levels,
            $ranks,
            $rankLevels,
        );
    }

    /** The casts at one level, 0 when none. */
    public function at(int $level): int
    {
        return $this->byLevel[$level]['casts'] ?? 0;
    }
}
