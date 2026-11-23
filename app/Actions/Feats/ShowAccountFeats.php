<?php

namespace App\Actions\Feats;

use App\Support\AccountRule;
use App\Support\Feat;
use App\Support\FeatText;
use App\View\Format;
use App\View\Pages\CharacterFeatWall;
use Illuminate\Support\Facades\DB;

/**
 * The account crests of a set of characters, computed from their stored `character_feats` on every read: a `sum` feat adds the
 * characters' counts, a `max` feat takes the highest and names the character that holds it (the lowest character id on a tie).
 * Each tier is read against the feat's account thresholds (`Feat::accountThresholds()`).
 */
class ShowAccountFeats
{
    /**
     * @param  list<int>  $characterIds  the characters of one account; those with no stored feats are left out
     * @param  bool|null  $economy  whether the gold feats are in; when null, whether any of the characters shows its economy
     * @return array{held: int, total: int, tiers: array<int, int>, characters: int, cards: list<array<string, mixed>>}|null null for no character with feats
     */
    public function handle(array $characterIds, ?bool $economy = null): ?array
    {
        $characterIds = array_values(array_map(fn ($id): int => (int) $id, DB::table('character_feats')->whereIn('character_id', $characterIds)->distinct()->pluck('character_id')->all()));
        if ($characterIds === []) {
            return null;
        }
        $economy ??= DB::table('characters')->whereIn('id', $characterIds)->where('show_economy', true)->exists();
        $feats = array_values(array_filter(Feat::cases(), fn (Feat $feat): bool => $feat->accountThresholds() !== null && ($economy || ! $feat->economy())));
        $values = $this->values($characterIds, $feats);
        $cards = [];
        $tiers = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        foreach ($feats as $feat) {
            $card = $this->card($feat, $values[$feat->value] ?? ['value' => 0, 'holder' => null], count($characterIds));
            $cards[] = $card;
            if ($card['tier'] > 0) {
                $tiers[$card['tier']]++;
            }
        }

        return ['held' => array_sum($tiers), 'total' => count($feats), 'tiers' => $tiers, 'characters' => count($characterIds), 'cards' => $cards];
    }

    /**
     * @param  list<int>  $characterIds
     * @param  list<Feat>  $feats
     * @return array<string, array{value: int|float, holder: string|null}> by feat key
     */
    private function values(array $characterIds, array $feats): array
    {
        $rows = DB::table('character_feats as cf')->join('characters as c', 'c.id', '=', 'cf.character_id')
            ->whereIn('cf.character_id', $characterIds)->whereIn('cf.feat', array_map(fn (Feat $feat): string => $feat->value, $feats))
            ->orderBy('c.id')->get(['c.name', 'cf.feat', 'cf.value']);
        $by = [];
        foreach ($rows as $row) {
            $feat = Feat::from((string) $row->feat);
            $value = (float) $row->value;
            $held = $by[$feat->value] ?? ['sum' => 0.0, 'max' => 0.0, 'holder' => null];
            $held['sum'] += $value;
            if ($value > $held['max']) {
                $held['max'] = $value;
                $held['holder'] = (string) $row->name;
            }
            $by[$feat->value] = $held;
        }
        $values = [];
        foreach ($by as $key => $held) {
            $feat = Feat::from($key);
            $sum = $feat->account() === AccountRule::Sum;
            $raw = $sum ? $held['sum'] : $held['max'];
            $values[$key] = ['value' => $feat->decimals() === 0 ? (int) floor($raw) : round($raw, $feat->decimals()), 'holder' => $sum ? null : $held['holder']];
        }

        return $values;
    }

    /**
     * @param  array{value: int|float, holder: string|null}  $account
     * @return array<string, mixed>
     */
    private function card(Feat $feat, array $account, int $characters): array
    {
        $thresholds = $feat->accountThresholds() ?? [];
        $value = $account['value'];
        $tier = 0;
        foreach ($thresholds as $i => $threshold) {
            if ($value >= $threshold) {
                $tier = $i + 1;
            }
        }
        $prev = $tier === 0 ? 0 : $thresholds[$tier - 1];
        $next = $thresholds[$tier] ?? null;
        $left = $next === null ? null : max(0, $next - $value);
        $counted = FeatText::counted($feat, $value);
        $toNext = $left === null ? null : FeatText::toNext($feat, $left, $tier);
        $row = ['key' => $feat->value, 'name' => $feat->label(), 'value' => $value, 'tier' => $tier, 'tier_name' => Feat::TIERS[$tier - 1] ?? null, 'threshold' => $thresholds[$tier - 1] ?? null, 'next' => $next, 'pinned' => false];
        $holder = $account['holder'];

        return CharacterFeatWall::card($row) + [
            'family' => $feat->family(), 'collection' => $feat->collection(), 'value' => $value, 'thresholds' => $thresholds,
            'next' => $next, 'prev' => $prev, 'left' => $left,
            'progress' => $next === null ? 1.0 : round(max(0, min(1, ($value - $prev) / ($next - $prev))), 3),
            'counted' => $feat->account() === AccountRule::Sum ? $counted.' on '.$characters.($characters === 1 ? ' character' : ' characters') : $counted,
            'to_next' => $holder === null ? $toNext : ($toNext === null ? '' : $toNext.' · ').'Held by '.Format::characterName($holder),
            'holder' => $holder, 'share' => null, 'spell' => null, 'live' => false,
        ];
    }
}
