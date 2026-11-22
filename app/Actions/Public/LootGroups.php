<?php

namespace App\Actions\Public;

use App\Actions\Rollups\GroupComposition;
use App\Actions\Rollups\GroupFigures;
use App\Actions\Rollups\GroupKillsBySize;
use App\Actions\Rollups\GroupShare;
use App\Actions\Rollups\LooterKept;
use App\Actions\Rollups\LootMarks;
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
        private readonly GroupKillsBySize $kills,
        private readonly GroupComposition $composition,
        private readonly LootMarks $marks,
    ) {}

    /**
     * Everything `/loot/groups` and `/public/loot/groups` show (docs/LOOT.md, Loot rules and group size), over every
     * ruleset and every loot rule but personal loot: the strip, what a corpse held under two rules (`$first` and `$second`,
     * else the two with the most corpses) and what the looter kept, narrowed to `$source` when one is given; and your share
     * of group loot, the rolls, the kills an hour by group size and the group composition, which no source narrows (a group
     * mate who does not upload has no source for what they received, and a roll and a stretch of time have none).
     * `source_name` is the dictionary name of the narrowed source, null for none or for one with no name. `marks` is the
     * developer list of every marked difference between loot rules or group sizes when `$marks` is true, else null.
     *
     * @return array<string, mixed>
     */
    public function handle(?LootSourceRef $source, ?LootRule $first, ?LootRule $second, bool $marks = false): array
    {
        return [
            'source' => $source,
            'source_name' => $source === null ? null : $this->name($source),
            'figures' => $this->figures->handle($source),
            'effects' => $this->effects->handle($source, $first, $second),
            'kept' => $this->kept->handle($source),
            'share' => $this->share->handle(),
            'rolls' => $this->rolls->handle(),
            'kills' => $this->kills->handle(),
            'composition' => $this->composition->handle(),
            'marks' => $marks ? $this->marks->handle() : null,
        ];
    }

    private function name(LootSourceRef $source): ?string
    {
        $name = DB::table('names')->where('kind', $source->nameKind()->value)->where('id', $source->id)->orderByDesc('votes')->orderBy('name')->value('name');

        return is_string($name) ? $name : null;
    }
}
