<?php

namespace App\Actions\Feats;

use App\Models\Character;
use App\Support\Feat;
use Illuminate\Support\Facades\DB;

/** What /feats/{key} draws from: the feat as the public API gives it, the other feats of its collection and where the signed-in owner's character stands on it. */
class ShowFeatPage
{
    public function __construct(
        private readonly ShowFeat $feat,
        private readonly ShowFeatCatalog $catalog,
    ) {}

    /**
     * @param  string|null  $as  the name of the owner's character to mark on the ladder, their most recently seen one with a count when null or not theirs
     * @return array{feat: array<string, mixed>, collection: list<array<string, mixed>>, mine: array{character: string, value: int|float, tier: int}|null}|null null for a key that is not a feat
     */
    public function handle(string $key, ?int $accountId, ?string $as): ?array
    {
        $feat = $this->feat->handle($key);
        if ($feat === null) {
            return null;
        }
        $collection = array_values(array_filter($this->catalog->handle()['feats'], fn (array $other): bool => $other['collection'] === $feat['collection'] && $other['key'] !== $feat['key']));

        return ['feat' => $feat, 'collection' => $collection, 'mine' => $accountId === null ? null : $this->mine(Feat::from($key), $accountId, $as)];
    }

    /** @return array{character: string, value: int|float, tier: int}|null */
    private function mine(Feat $feat, int $accountId, ?string $as): ?array
    {
        $rows = DB::table('character_feats as cf')->join('characters as c', 'c.id', '=', 'cf.character_id')
            ->where('c.account_id', $accountId)->where('cf.feat', $feat->value)
            ->orderByRaw('c.last_seen_at desc nulls last')->orderBy('c.id')->get(['c.name', 'cf.value'])->all();
        $row = $rows[0] ?? null;
        foreach ($rows as $candidate) {
            if ($candidate->name === $as) {
                $row = $candidate;
            }
        }
        if ($row === null) {
            return null;
        }
        $value = $feat->decimals() === 0 ? (int) floor((float) $row->value) : round((float) $row->value, $feat->decimals());

        return ['character' => (string) $row->name, 'value' => $value, 'tier' => $feat->tier($value)];
    }
}
