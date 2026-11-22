<?php

namespace App\Actions\Rollups;

use App\Support\LootGroupScope;
use App\Support\LootSourceRef;
use App\Support\Rollup;
use App\Support\RollupRead;
use Illuminate\Support\Facades\DB;

class GroupFigures
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * What the strip of `/loot/groups` counts: the corpses read for loot on creatures, or on the one source the page is
     * narrowed to, and never personal loot; the loot rules among them that a line has named; the largest group size; the
     * characters; the first and last day; the sources seen under two rules; and the uploaders' own rolls, which no source
     * narrows. All are 0 and `first_at` is null while fewer than `scarlet.min_characters` characters contributed.
     *
     * @return array{corpses: int, rules: int, largest: int, characters: int, first_at: string|null, last_at: string|null, several: int, rolls: int}
     */
    public function handle(?LootSourceRef $source): array
    {
        $row = $this->read->rows(LootGroupScope::of(DB::table(Rollup::LootWindowsCharacterDaily->value), $source)
            ->selectRaw('sum(windows) as corpses, count(distinct loot_rule) filter (where loot_rule = any (?::text[])) as rules, max(group_size) as largest, count(distinct character_id) as characters, min(bucket) as first_at, max(bucket) as last_at', ['{'.implode(',', LootGroupScope::knownRules()).'}'])
            ->havingRaw('count(distinct character_id) >= ?', [$this->read->threshold(null)]))[0] ?? null;
        if ($row === null) {
            return ['corpses' => 0, 'rules' => 0, 'largest' => 0, 'characters' => 0, 'first_at' => null, 'last_at' => null, 'several' => 0, 'rolls' => $this->rolls()];
        }

        return [
            'corpses' => (int) $row['corpses'], 'rules' => (int) $row['rules'], 'largest' => (int) $row['largest'], 'characters' => (int) $row['characters'],
            'first_at' => $row['first_at'], 'last_at' => $row['last_at'], 'several' => $this->several($source), 'rolls' => $this->rolls(),
        ];
    }

    private function rolls(): int
    {
        $row = $this->read->rows(DB::table('loot_rolls')->where('counted', true)->where('mine', true)
            ->selectRaw('count(*) as rolls')->havingRaw('count(distinct character_id) >= ?', [$this->read->threshold(null)]))[0] ?? null;

        return (int) ($row['rolls'] ?? 0);
    }

    private function several(?LootSourceRef $source): int
    {
        $per = LootGroupScope::of(DB::table(Rollup::LootWindowsCharacterDaily->value), $source)
            ->whereIn('loot_rule', LootGroupScope::knownRules())
            ->selectRaw('source_kind, source_id')->groupBy('source_kind', 'source_id')->havingRaw('count(distinct loot_rule) >= 2');

        return (int) $this->read->value(DB::query()->fromSub($per, 's')->selectRaw('count(*) as sources'), 'sources');
    }
}
