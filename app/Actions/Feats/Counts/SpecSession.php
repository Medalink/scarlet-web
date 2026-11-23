<?php

namespace App\Actions\Feats\Counts;

use App\Support\TalentTree;

/** One counted session of a character: the tree it played, its play time and what it did, folded once for every spec feat. */
final readonly class SpecSession
{
    /**
     * @param  array<int, int>  $casts  spell id => casts in the session
     */
    public function __construct(
        public readonly ?TalentTree $tree,
        public readonly int $playedMs,
        public readonly int $dungeonRuns,
        public readonly int $dungeonBosses,
        public readonly int $raidBosses,
        public readonly int $deathlessRuns,
        public readonly int $damage,
        public readonly int $healing,
        public readonly int $damageTaken,
        public readonly int $bestDamage,
        public readonly int $bestHealing,
        public readonly int $bestDamageTaken,
        public readonly array $casts,
    ) {}
}
