<?php

namespace App\Actions\Feats;

use App\Models\Install;
use App\Support\Feat;
use Illuminate\Support\Facades\DB;

class MarkInstallAccountFeatsSeen
{
    public function __construct(private readonly ShowInstallAccountFeats $account) {}

    /**
     * Record the account tiers the app showed. Each feat's seen tier rises to the shown tier, never past the tier the account
     * holds now and never down; a key that is not an account feat is ignored. Safe to run twice and at once: each feat is one
     * guarded upsert (raw, because the query builder's upsert cannot take the greater of the two values).
     *
     * @param  array<array-key, int>  $shown  feat key => the tier that was shown
     */
    public function handle(Install $install, array $shown): void
    {
        $held = [];
        foreach ($this->account->handle($install)['cards'] ?? [] as $card) {
            $held[$card['key']] = $card['tier'];
        }
        foreach ($shown as $key => $tier) {
            $feat = Feat::tryFrom((string) $key);
            $target = $feat === null ? 0 : min($tier, $held[$feat->value] ?? 0);
            if ($feat === null || $target < 1) {
                continue;
            }
            DB::insert(
                'insert into install_account_feats (install_id, feat, seen_tier) values (?, ?, ?) on conflict (install_id, feat) do update set seen_tier = greatest(install_account_feats.seen_tier, excluded.seen_tier)',
                [$install->id, $feat->value, $target],
            );
        }
    }
}
