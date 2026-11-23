<?php

namespace App\Actions\Feats;

use App\Actions\Public\ResolveNames;
use App\Models\Character;
use App\Support\ClassRows\ClassRows;
use App\Support\Feat;
use App\Support\FeatMeasure;
use App\Support\FeatRules;
use App\Support\FeatText;

/**
 * The fields a card carries on `GET /installs/me/feats` besides the ones the character page draws (docs/CONTRACTS.md, `FeatCard`):
 * where the feat sits, its count and thresholds, how far it has come toward the next tier, the words for both, the share of
 * characters at its tier and the spell its seal engraves.
 */
class ShowFeatCardFields
{
    public function __construct(
        private readonly ReadFeatShares $shares,
        private readonly ResolveNames $names,
    ) {}

    /**
     * @param  list<array{key: string, value: int|float, tier: int, next: int|null}>  $rows  the rows of `ShowCharacterFeats`
     * @return array<string, array{family: string, collection: string, value: int|float, thresholds: list<int>, next: int|null, left: int|float|null, progress: float, counted: string, to_next: string|null, share: float|null, spell: int|null, live: bool}> by feat key
     */
    public function handle(Character $character, array $rows): array
    {
        $shares = $this->shares->handle(array_column($rows, 'key'));
        $spells = $this->spells($character, $rows);
        $fields = [];
        foreach ($rows as $row) {
            $feat = Feat::from($row['key']);
            $thresholds = $feat->thresholds();
            $next = $row['next'];
            $left = $next === null ? null : max(0, $next - $row['value']);
            $from = $row['tier'] === 0 ? 0 : ($thresholds[$row['tier'] - 1] ?? 0);
            $live = $feat->live();
            $fields[$feat->value] = [
                'family' => $feat->family(),
                'collection' => $feat->collection(),
                'value' => $row['value'],
                'thresholds' => $thresholds,
                'next' => $next,
                'left' => $left,
                'progress' => $next === null ? 1.0 : round(max(0, min(1, ($row['value'] - $from) / ($next - $from))), 3),
                'counted' => FeatText::counted($feat, $row['value']),
                'to_next' => $left === null ? null : FeatText::toNext($feat, $left, $row['tier']),
                'share' => $row['tier'] === 0 ? null : ($shares[$feat->value][$row['tier'] - 1] ?? null),
                'spell' => $spells[$feat->value] ?? null,
                'live' => $live !== null && $live['kind'] !== 'pending',
            ];
        }

        return $fields;
    }

    /**
     * @param  list<array{key: string}>  $rows
     * @return array<string, int> by feat key, the spell the character cast most among those the cast feat counts
     */
    private function spells(Character $character, array $rows): array
    {
        $wanted = [];
        foreach ($rows as $row) {
            $rule = FeatRules::RULES[$row['key']] ?? null;
            if ($rule !== null && $rule[1] === FeatMeasure::Casts && Feat::from($row['key'])->engraves() === 'spell') {
                $wanted[$row['key']] = $rule[2];
            }
        }
        if ($wanted === []) {
            return [];
        }
        $book = ClassRows::of($character, $this->names)->spells();
        $spells = [];
        foreach ($wanted as $key => $patterns) {
            $top = $book->named(...$patterns)[0] ?? null;
            if ($top !== null && $top->iconId !== 0) {
                $spells[$key] = $top->iconId;
            }
        }

        return $spells;
    }
}
