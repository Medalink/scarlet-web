<?php

namespace App\Support;

use Illuminate\Support\Str;

/** The proof map of the class, talent tree and role feats: each rule checked for every class that can hold it. */
final class FeatProof
{
    private const float SLOW_LIMIT = 300.0;

    private const float LONG_COOLDOWN_LIMIT = 100.0;

    private const float LONG_COOLDOWN = 600.0;

    private const float FLOOR_COOLDOWN = 1.5;

    public function __construct(private readonly ClientSpells $spells) {}

    /** @return list<FeatProofCheck> one check per rule and holder class, in rule and holder order */
    public function run(): array
    {
        $checks = [];
        foreach (FeatRules::RULES as $key => [$scope, $measure, $patterns]) {
            $feat = Feat::from($key);
            $holders = $this->holders($scope);
            if ($measure !== FeatMeasure::Casts) {
                foreach ($holders as $holder) {
                    $checks[] = new FeatProofCheck($feat, $scope, $holder, $measure, $patterns, [], null, null);
                }

                continue;
            }

            $matched = [];
            $used = [];
            foreach ($holders as $holder) {
                $matched[] = [$holder, $this->matching($holder, $patterns, $used)];
            }
            $dead = array_values(array_diff($patterns, array_keys($used)));
            $deadReason = null;
            if ($dead !== []) {
                $deadReason = 'no spell on this client matches '.implode(', no spell on this client matches ', $dead);
            }
            foreach ($matched as [$holder, $spells]) {
                $checks[] = $this->judge($feat, $scope, $holder, $patterns, $spells, $deadReason);
            }
        }

        return $checks;
    }

    /**
     * @param  list<string>  $patterns
     * @param  array<string, true>  $used  every pattern a spell of any holder class matched
     * @return array<string, ClientSpell> matched spells of the class keyed by name
     */
    private function matching(PlayerClass $holder, array $patterns, array &$used): array
    {
        $matched = [];
        foreach ($this->spells->of($holder) as $name => $spell) {
            foreach ($patterns as $pattern) {
                if (Str::is($pattern, $name, true)) {
                    $used[$pattern] = true;
                    $matched[$name] = $spell;
                }
            }
        }
        ksort($matched);

        return $matched;
    }

    /**
     * @param  list<string>  $patterns
     * @param  array<string, ClientSpell>  $matched
     */
    private function judge(Feat $feat, PlayerClass|TalentTree|Role $scope, PlayerClass $holder, array $patterns, array $matched, ?string $deadReason): FeatProofCheck
    {
        $reasons = [];
        if ($deadReason !== null) {
            $reasons[] = $deadReason;
        }
        if ($matched === []) {
            $reasons[] = 'no spell of '.$holder->name.' matches the rule';

            return new FeatProofCheck($feat, $scope, $holder, FeatMeasure::Casts, $patterns, [], null, implode('; ', $reasons));
        }

        $rate = 0.0;
        $shortest = INF;
        foreach ($matched as $spell) {
            $rate += 3600 / max($spell->cooldown, self::FLOOR_COOLDOWN);
            $shortest = min($shortest, $spell->cooldown);
        }
        $hours = $feat->thresholds()[4] / $rate;
        $limit = $shortest >= self::LONG_COOLDOWN ? self::LONG_COOLDOWN_LIMIT : self::SLOW_LIMIT;
        if ($hours > $limit) {
            $reasons[] = sprintf('fastest to legend is %s h, over the %d h limit', number_format($hours, 1), $limit);
        }

        return new FeatProofCheck($feat, $scope, $holder, FeatMeasure::Casts, $patterns, array_values($matched), $hours,
            $reasons === [] ? null : implode('; ', $reasons));
    }

    /** @return list<PlayerClass> the classes the scope's feats are stored on */
    private function holders(PlayerClass|TalentTree|Role $scope): array
    {
        if ($scope instanceof PlayerClass) {
            return [$scope];
        }
        if ($scope instanceof TalentTree) {
            return [$scope->class()];
        }
        $classes = [];
        foreach (TalentTree::cases() as $tree) {
            if (in_array($scope, $tree->roles(), true) && ! in_array($tree->class(), $classes, true)) {
                $classes[] = $tree->class();
            }
        }

        return $classes;
    }
}
