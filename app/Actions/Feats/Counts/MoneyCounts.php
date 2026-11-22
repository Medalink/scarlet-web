<?php

namespace App\Actions\Feats\Counts;

use App\Models\Character;
use App\Support\Feat;
use Illuminate\Support\Facades\DB;

/** The money feats, in whole gold rounded down. */
class MoneyCounts
{
    private const int COPPER_PER_GOLD = 10_000;

    /**
     * One count per money feat, keyed by the enum value; null when the feat does not apply to the character.
     *
     * @return array<string, int|float|null>
     */
    public function handle(Character $character): array
    {
        $sessions = DB::table('sessions')->where('character_id', (int) $character->id)->where('counted', true)
            ->selectRaw('coalesce(sum(copper_earned), 0) as earned, coalesce(sum(copper_spent), 0) as spent')
            ->selectRaw('coalesce(max(money_end), 0) as carried, coalesce(max(copper_earned), 0) as windfall')->first();

        return [
            Feat::Earner->value => intdiv((int) ($sessions->earned ?? 0), self::COPPER_PER_GOLD),
            Feat::Spender->value => intdiv((int) ($sessions->spent ?? 0), self::COPPER_PER_GOLD),
            Feat::Tycoon->value => intdiv((int) ($sessions->carried ?? 0), self::COPPER_PER_GOLD),
            Feat::Windfall->value => intdiv((int) ($sessions->windfall ?? 0), self::COPPER_PER_GOLD),
        ];
    }
}
