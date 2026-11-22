<?php

namespace App\Support;

/** One rule checked for one holder class: the spells it matches, the fastest hours to legend and the failure, if any. */
final class FeatProofCheck
{
    /**
     * @param  list<string>  $patterns  the spell name patterns of the rule, empty for a measure with no spells
     * @param  list<ClientSpell>  $spells  the class's spells whose name matches a pattern of the rule
     * @param  float|null  $hours  the fastest the legend tier could be earned, null for a measure with no ceiling
     * @param  string|null  $reason  why the rule fails for the class, null when it is in reach
     */
    public function __construct(
        public readonly Feat $feat,
        public readonly PlayerClass|TalentTree|Role $scope,
        public readonly PlayerClass $holder,
        public readonly FeatMeasure $measure,
        public readonly array $patterns,
        public readonly array $spells,
        public readonly ?float $hours,
        public readonly ?string $reason,
    ) {}
}
