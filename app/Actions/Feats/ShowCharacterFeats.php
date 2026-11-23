<?php

namespace App\Actions\Feats;

use App\Models\Character;
use App\Support\Feat;
use Illuminate\Support\Facades\DB;

/** The feats of a character as the page and the API list them, read from its stored `character_feats` rows. */
class ShowCharacterFeats
{
    public function __construct(private readonly CountHolders $holders) {}

    /**
     * Earned feats first, rarest first: the fewest characters holding the tier this one holds, then the higher tier, then
     * the order of `Feat`. The feats not yet earned follow, closest to copper first. Rarity orders the list and is not in
     * a row. The gold feats are left out unless `$economy`. `pinned` marks the feats the owner pinned; pinning does not
     * move a row.
     *
     * @return list<array{key: string, name: string, value: int|float, tier: int, tier_name: string|null, threshold: int|null, next: int|null, pinned: bool}>
     */
    public function handle(Character $character, bool $economy): array
    {
        /** @var array<string, list<int>> $holders */
        $holders = $this->holders->handle();
        $order = array_flip(array_map(fn (Feat $feat): string => $feat->value, Feat::cases()));
        $entries = [];
        foreach (DB::table('character_feats')->where('character_id', $character->id)->pluck('value', 'feat') as $key => $stored) {
            $feat = Feat::tryFrom((string) $key);
            if ($feat === null || ($feat->economy() && ! $economy)) {
                continue;
            }
            $value = $feat->decimals() === 0 ? (int) floor((float) $stored) : round((float) $stored, $feat->decimals());
            $tier = $feat->tier($value);
            $entries[] = [
                'row' => $this->row($feat, $value, $tier),
                'order' => $order[$feat->value],
                'rare' => $holders[$feat->value][max($tier, 1) - 1] ?? 0,
                'close' => $value / $feat->thresholds()[0],
            ];
        }
        usort($entries, fn (array $a, array $b): int => $this->compare($a, $b));

        $pinned = $character->pinned_feats ?? [];

        return array_map(fn (array $entry): array => $entry['row'] + ['pinned' => in_array($entry['row']['key'], $pinned, true)], $entries);
    }

    /**
     * @param  array{row: array{tier: int}, order: int, rare: int, close: float|int}  $a
     * @param  array{row: array{tier: int}, order: int, rare: int, close: float|int}  $b
     */
    private function compare(array $a, array $b): int
    {
        $earnedA = $a['row']['tier'] >= 1;
        if ($earnedA !== ($b['row']['tier'] >= 1)) {
            return $earnedA ? -1 : 1;
        }
        if ($earnedA) {
            return [$a['rare'], $b['row']['tier'], $a['order']] <=> [$b['rare'], $a['row']['tier'], $b['order']];
        }

        return [$b['close'], $a['rare'], $a['order']] <=> [$a['close'], $b['rare'], $b['order']];
    }

    /** @return array{key: string, name: string, value: int|float, tier: int, tier_name: string|null, threshold: int|null, next: int|null} */
    private function row(Feat $feat, int|float $value, int $tier): array
    {
        $thresholds = $feat->thresholds();

        return [
            'key' => $feat->value, 'name' => $feat->label(), 'value' => $value, 'tier' => $tier,
            'tier_name' => Feat::TIERS[$tier - 1] ?? null,
            'threshold' => $thresholds[$tier - 1] ?? null,
            'next' => $thresholds[$tier] ?? null,
        ];
    }
}
