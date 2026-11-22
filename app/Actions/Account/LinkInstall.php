<?php

namespace App\Actions\Account;

use App\Actions\Ingest\InvalidateReconciles;
use App\Jobs\ReconcileCharacterClaims;
use App\Models\Account;
use App\Models\Character;
use App\Models\Install;
use App\Models\LinkCode;
use App\Models\RefusedGroup;
use App\Support\Database\BinaryValue;
use App\Support\LinkCodeHash;
use App\Support\LinkJudge;
use Illuminate\Support\Facades\DB;

class LinkInstall
{
    public function __construct(private readonly InvalidateReconciles $reconciles) {}

    /**
     * Redeem a link code for the account: the install and the characters it first held now belong to the
     * account, and the sharing groups the install had refused become the account's refusals. A code works once.
     * Characters that now share an account can hold one record twice, so the addon days of those that hold claims
     * are marked as not reconciled in the same transaction and their claims are reconciled again.
     */
    public function handle(Account $account, string $code): LinkState
    {
        $linked = [];
        $state = DB::transaction(function () use ($account, $code, &$linked): LinkState {
            $link = LinkCode::query()->where('code_hash', BinaryValue::bind(LinkCodeHash::of($code)))->lockForUpdate()->first();
            $link?->setRelation('install', Install::query()->whereKey($link->install_id)->lockForUpdate()->first());

            $state = LinkJudge::state($account, $link);
            if ($state !== LinkState::Ready || $link === null || $link->install === null) {
                return $state;
            }

            $linked = $this->own($account, $link->install);
            $link->used_at = now();
            $link->save();

            return LinkState::Linked;
        });
        foreach ($linked as $characterId) {
            ReconcileCharacterClaims::dispatch($characterId);
        }

        return $state;
    }

    /** @return list<int> the characters that hold addon claims and whose verdicts the link can change */
    private function own(Account $account, Install $install): array
    {
        $install->account_id = $account->id;
        $install->linked_at = now();
        $install->save();

        $characterIds = array_values(DB::table('install_character')->where('install_id', $install->id)->pluck('character_id')->map(fn (mixed $id): int => (int) $id)->all());
        Character::query()->whereIn('id', $characterIds)->lockedInOrder()->pluck('id');
        Character::query()
            ->whereNull('account_id')
            ->whereIn('id', $characterIds)
            ->update(['account_id' => $account->id]);
        $linked = $this->reconciles->handle($characterIds);

        $refused = RefusedGroup::query()->where('install_id', $install->id)->pluck('group_key')->all();
        foreach ($refused as $group) {
            RefusedGroup::query()->insertOrIgnore([['account_id' => $account->id, 'group_key' => $group, 'created_at' => now()]]);
        }
        RefusedGroup::query()->where('install_id', $install->id)->delete();

        return $linked;
    }
}
