<?php

namespace App\Actions\Account;

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
    /**
     * Redeem a link code for the account: the install and the characters it first held now belong to the
     * account, and the sharing groups the install had refused become the account's refusals. A code works once.
     */
    public function handle(Account $account, string $code): LinkState
    {
        return DB::transaction(function () use ($account, $code): LinkState {
            $link = LinkCode::query()->where('code_hash', BinaryValue::bind(LinkCodeHash::of($code)))->lockForUpdate()->first();
            $link?->setRelation('install', Install::query()->whereKey($link->install_id)->lockForUpdate()->first());

            $state = LinkJudge::state($account, $link);
            if ($state !== LinkState::Ready || $link === null || $link->install === null) {
                return $state;
            }

            $this->own($account, $link->install);
            $link->used_at = now();
            $link->save();

            return LinkState::Linked;
        });
    }

    private function own(Account $account, Install $install): void
    {
        $install->account_id = $account->id;
        $install->linked_at = now();
        $install->save();

        Character::query()
            ->whereNull('account_id')
            ->whereIn('id', DB::table('install_character')->where('install_id', $install->id)->select('character_id'))
            ->update(['account_id' => $account->id]);

        $refused = RefusedGroup::query()->where('install_id', $install->id)->pluck('group_key')->all();
        foreach ($refused as $group) {
            RefusedGroup::query()->insertOrIgnore([['account_id' => $account->id, 'group_key' => $group, 'created_at' => now()]]);
        }
        RefusedGroup::query()->where('install_id', $install->id)->delete();
    }
}
