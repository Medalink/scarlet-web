<?php

namespace App\Actions\Feats;

use App\Models\Character;
use App\Models\Install;
use App\Support\Feat;
use Illuminate\Support\Facades\DB;

/** The account crests of every character an install owns that has stored feats, with the ones its owner has not been shown yet: the `account` of `GET /installs/me/feats`. */
class ShowInstallAccountFeats
{
    public function __construct(private readonly ShowAccountFeats $account) {}

    /**
     * Gold feats are in, as they are for the owner's characters. `new` lists the account feats above the tier this install was last shown, highest tier first, then the order of `Feat`.
     *
     * @return array<string, mixed>|null null when the install owns no character with feats
     */
    public function handle(Install $install): ?array
    {
        $account = $this->account->handle($this->characterIds($install), true);
        if ($account === null) {
            return null;
        }
        $seen = DB::table('install_account_feats')->where('install_id', $install->id)->pluck('seen_tier', 'feat');
        $order = array_flip(array_map(fn (Feat $feat): string => $feat->value, Feat::cases()));
        $new = [];
        foreach ($account['cards'] as $card) {
            $from = (int) ($seen[$card['key']] ?? 0);
            if ($card['tier'] > $from) {
                $new[] = ['key' => $card['key'], 'tier' => $card['tier'], 'from' => $from];
            }
        }
        usort($new, fn (array $a, array $b): int => [$b['tier'], $order[$a['key']]] <=> [$a['tier'], $order[$b['key']]]);

        return [
            'held' => $account['held'], 'total' => $account['total'], 'tiers' => $account['tiers'],
            'cards' => $account['cards'], 'new' => $new,
        ];
    }

    /** @return list<int> */
    private function characterIds(Install $install): array
    {
        return array_values(Character::query()->ownedBy($install)
            ->whereExists(fn ($rows) => $rows->selectRaw('1')->from('character_feats')->whereColumn('character_feats.character_id', 'characters.id'))
            ->orderBy('characters.id')->pluck('characters.id')->map(fn ($id): int => (int) $id)->all());
    }
}
