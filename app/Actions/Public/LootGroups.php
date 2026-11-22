<?php

namespace App\Actions\Public;

use App\Actions\Rollups\GroupFigures;
use App\Actions\Rollups\GroupShare;
use App\Actions\Rollups\LooterKept;
use App\Actions\Rollups\LootRolls;
use App\Actions\Rollups\LootRuleEffects;
use App\Support\LootRule;
use App\Support\LootSourceRef;
use Illuminate\Support\Facades\DB;

class LootGroups
{
    public function __construct(
        private readonly GroupFigures $figures,
        private readonly LootRuleEffects $effects,
        private readonly LooterKept $kept,
        private readonly GroupShare $share,
        private readonly LootRolls $rolls,
    ) {}

    /**
     * Everything `/loot/groups` and `/public/loot/groups` show (docs/LOOT.md, Loot rules and group size), over every
     * ruleset and every loot rule but personal loot: the strip, what a corpse held under two rules (`$first` and `$second`,
     * else the two with the most corpses) and what the looter kept, narrowed to `$source` when one is given; and your share
     * of group loot and the rolls, which no source narrows (a group mate who does not upload has no source for what they
     * received, and a roll has none). `source_name` is the dictionary name of the narrowed source, null for none or for one
     * with no name.
     *
     * @return array<string, mixed>
     */
    public function handle(?LootSourceRef $source, ?LootRule $first, ?LootRule $second): array
    {
        return [
            'source' => $source,
            'source_name' => $source === null ? null : $this->name($source),
            'figures' => $this->figures->handle($source),
            'effects' => $this->effects->handle($source, $first, $second),
            'kept' => $this->kept->handle($source),
            'share' => $this->share->handle(),
            'rolls' => $this->rolls->handle(),
        ];
    }

    private function name(LootSourceRef $source): ?string
    {
        $name = DB::table('names')->where('kind', $source->nameKind()->value)->where('id', $source->id)->orderByDesc('votes')->orderBy('name')->value('name');

        return is_string($name) ? $name : null;
    }
}
