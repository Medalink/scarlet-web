<?php

namespace App\Actions\Feats;

use App\Support\Feat;
use Illuminate\Support\Facades\DB;

/** Sets what each stored feat row of the characters with ids above `$afterId` up to `$throughId` has been shown to the tier its value holds now, so a rule change announces nothing. */
class SettleSeenTiers
{
    /** @return int rows set */
    public function handle(int $afterId, int $throughId): int
    {
        $rows = [];
        $bindings = [];
        foreach (Feat::cases() as $feat) {
            $rows[] = '(?::text, ?::int, ?::int, ?::int, ?::int, ?::int, ?::int)';
            $bindings = [...$bindings, $feat->value, $feat->decimals(), ...$feat->thresholds()];
        }
        $bindings = [...$bindings, $afterId, $throughId];

        return DB::update(
            'update character_feats set seen_tier = (v.t1 <= held)::int + (v.t2 <= held)::int + (v.t3 <= held)::int + (v.t4 <= held)::int + (v.t5 <= held)::int '
            .'from (select f.*, case when f.d = 0 then floor(c.value) else round(c.value::numeric, f.d) end as held, c.character_id, c.feat as cf '
            .'from (values '.implode(', ', $rows).') as f(feat, d, t1, t2, t3, t4, t5) join character_feats c on c.feat = f.feat where c.character_id > ? and c.character_id <= ?) v '
            .'where character_feats.character_id = v.character_id and character_feats.feat = v.cf',
            $bindings,
        );
    }
}
