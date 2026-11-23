<?php

namespace App\Actions\Feats;

use App\Support\AccountRule;
use App\Support\Feat;
use Illuminate\Support\Facades\DB;

/** What /feats/{key} draws from: the feat as the public API gives it, the other feats of its collection and where the signed-in owner's character stands on it. */
class ShowFeatPage
{
    public function __construct(
        private readonly ShowFeat $feat,
        private readonly ShowFeatCatalog $catalog,
        private readonly ShowAccountFeats $account,
        private readonly ListAccountCharacterIds $owned,
    ) {}

    /**
     * @param  string|null  $as  the name of the owner's character to mark on the ladder, their most recently seen one with a count when null or not theirs
     * @return array{feat: array<string, mixed>, collection: list<array<string, mixed>>, mine: array{character: string, value: int|float, tier: int}|null, held: array<string, array{tier: int, progress: float}>, account: array<string, mixed>|null}|null null for a key that is not a feat
     */
    public function handle(string $key, ?int $accountId, ?string $as): ?array
    {
        $feat = $this->feat->handle($key);
        if ($feat === null) {
            return null;
        }
        $collection = array_values(array_filter($this->catalog->handle()['feats'], fn (array $other): bool => $other['collection'] === $feat['collection'] && $other['key'] !== $feat['key']));
        $mine = $accountId === null ? null : $this->mine(Feat::from($key), $accountId, $as);

        return [
            'feat' => $feat, 'collection' => $collection,
            'mine' => $mine,
            'held' => $mine === null ? [] : $this->held($accountId, $mine['character'], array_column($collection, 'key')),
            'account' => $accountId === null || $feat['account'] === AccountRule::None->value ? null : $this->ofAccount($key, $accountId),
        ];
    }

    /** @return array<string, mixed>|null the account's card of the feat, null when no character of the account has feats */
    private function ofAccount(string $key, int $accountId): ?array
    {
        $cards = array_column($this->account->handle($this->owned->handle($accountId))['cards'] ?? [], null, 'key');

        return $cards[$key] ?? null;
    }

    /**
     * The tier and the progress to the next tier of each of `$keys` that the account's character `$name` holds a count of.
     *
     * @param  list<string>  $keys
     * @return array<string, array{tier: int, progress: float}>
     */
    private function held(int $accountId, string $name, array $keys): array
    {
        $rows = DB::table('character_feats as cf')->join('characters as c', 'c.id', '=', 'cf.character_id')
            ->where('c.account_id', $accountId)->where('c.name', $name)->whereIn('cf.feat', $keys)->get(['cf.feat', 'cf.value']);
        $held = [];
        foreach ($rows as $row) {
            $feat = Feat::tryFrom((string) $row->feat);
            if ($feat === null) {
                continue;
            }
            $value = (float) $row->value;
            $tier = $feat->tier($value);
            $thresholds = $feat->thresholds();
            $from = $tier > 0 ? $thresholds[$tier - 1] : 0;
            $next = $thresholds[$tier] ?? null;
            $held[$feat->value] = ['tier' => $tier, 'progress' => $next === null ? 1.0 : round(max(0, min(1, ($value - $from) / ($next - $from))), 3)];
        }

        return $held;
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
