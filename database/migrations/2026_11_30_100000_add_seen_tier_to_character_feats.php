<?php

use App\Support\Feat;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The highest tier of a feat that its character's owner has been shown (docs/DESIGN.md "Art and game layer"). Rows that
 * exist now start at the tier their value holds, so nothing already earned is announced after the deploy; a row inserted
 * later starts at 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('character_feats', function (Blueprint $table): void {
            $table->smallInteger('seen_tier')->default(0);
        });

        $rows = [];
        $bindings = [];
        foreach (Feat::cases() as $feat) {
            $rows[] = '(?::text, ?::int, ?::int, ?::int, ?::int, ?::int, ?::int)';
            $bindings = [...$bindings, $feat->value, $feat->decimals(), ...$feat->thresholds()];
        }

        DB::update(
            'update character_feats set seen_tier = (v.t1 <= held)::int + (v.t2 <= held)::int + (v.t3 <= held)::int + (v.t4 <= held)::int + (v.t5 <= held)::int '
            .'from (select f.*, case when f.d = 0 then floor(c.value) else round(c.value::numeric, f.d) end as held, c.character_id, c.feat as cf '
            .'from (values '.implode(', ', $rows).') as f(feat, d, t1, t2, t3, t4, t5) join character_feats c on c.feat = f.feat) v '
            .'where character_feats.character_id = v.character_id and character_feats.feat = v.cf',
            $bindings,
        );
    }
};
