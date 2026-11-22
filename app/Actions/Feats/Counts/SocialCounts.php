<?php

namespace App\Actions\Feats\Counts;

use App\Models\Character;
use App\Support\Emote;
use App\Support\Feat;
use Illuminate\Support\Facades\DB;

/** The social feats: emotes, other than Emote::READ, and talent respecs. */
class SocialCounts
{
    /**
     * One count per social feat, keyed by the enum value; null when the feat does not apply to the character.
     *
     * @return array<string, int|float|null>
     */
    public function handle(Character $character): array
    {
        $id = (int) $character->id;
        $emotes = DB::table('emotes')->where('character_id', $id)->where('counted', true)->whereNotNull('token')->where('token', '<>', Emote::READ)
            ->selectRaw('count(*) as uses, count(distinct token) as kinds')->first();
        $signature = DB::table('emotes')->where('character_id', $id)->where('counted', true)->whereNotNull('token')->where('token', '<>', Emote::READ)
            ->groupBy('token')->selectRaw('token, count(*) as uses')->pluck('uses', 'token');

        return [
            Feat::Expressive->value => (int) ($emotes->uses ?? 0),
            Feat::Mime->value => (int) ($emotes->kinds ?? 0),
            Feat::Signature->value => (int) $signature->max(),
            Feat::Indecisive->value => DB::table('talent_respecs')->where('character_id', $id)->where('counted', true)->count(),
        ];
    }
}
