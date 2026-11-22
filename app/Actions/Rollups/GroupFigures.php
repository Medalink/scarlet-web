<?php

namespace App\Actions\Rollups;

use App\Support\LootCounted;
use App\Support\LootGroupScope;
use App\Support\LootSourceRef;
use App\Support\RollupRead;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class GroupFigures
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * What the strip of `/loot/groups` counts: the corpses read for loot on creatures, or on the one source the page is
     * narrowed to, and never personal loot; the loot rules among them that a line has named; the largest group size; the
     * characters; the first and last day; the sources seen under two rules; and the uploaders' own rolls and the groups
     * they were in (a group is a run of spans that follow one another, of 2 or more), which no source narrows. All are 0
     * and `first_at` is null while fewer than `scarlet.min_characters` characters contributed.
     *
     * @return array{corpses: int, rules: int, largest: int, characters: int, first_at: string|null, last_at: string|null, several: int, rolls: int, groups: int}
     */
    public function handle(?LootSourceRef $source): array
    {
        $row = $this->read->rows(LootGroupScope::of(DB::table(LootCounted::WindowsCharacter->value), $source)
            ->selectRaw('sum(windows) as corpses, count(distinct loot_rule) filter (where loot_rule = any (?::text[])) as rules, max(group_size) as largest, count(distinct character_id) as characters, min(bucket) as first_at, max(bucket) as last_at', ['{'.implode(',', LootGroupScope::knownRules()).'}'])
            ->havingRaw('count(distinct character_id) >= ?', [$this->read->threshold(null)]))[0] ?? null;
        if ($row === null) {
            return ['corpses' => 0, 'rules' => 0, 'largest' => 0, 'characters' => 0, 'first_at' => null, 'last_at' => null, 'several' => 0, 'rolls' => $this->rolls(), 'groups' => $this->groups()];
        }

        return [
            'corpses' => (int) $row['corpses'], 'rules' => (int) $row['rules'], 'largest' => (int) $row['largest'], 'characters' => (int) $row['characters'],
            'first_at' => $row['first_at'], 'last_at' => $row['last_at'], 'several' => $this->several($source), 'rolls' => $this->rolls(), 'groups' => $this->groups(),
        ];
    }

    private function groups(): int
    {
        $row = $this->read->rows(DB::table('group_spans as s')->where('s.counted', true)->where('s.size', '>=', 2)
            ->whereNotExists(fn (Builder $before): Builder => $before->selectRaw('1')->from('group_spans as p')
                ->whereColumn('p.character_id', 's.character_id')->whereColumn('p.session_start', 's.session_start')->whereColumn('p.ended_at', 's.at'))
            ->selectRaw('count(*) as groups')->havingRaw('count(distinct s.character_id) >= ?', [$this->read->threshold(null)]))[0] ?? null;

        return (int) ($row['groups'] ?? 0);
    }

    private function rolls(): int
    {
        $row = $this->read->rows(DB::table('loot_rolls')->where('counted', true)->where('mine', true)
            ->selectRaw('count(*) as rolls')->havingRaw('count(distinct character_id) >= ?', [$this->read->threshold(null)]))[0] ?? null;

        return (int) ($row['rolls'] ?? 0);
    }

    private function several(?LootSourceRef $source): int
    {
        $per = LootGroupScope::of(DB::table(LootCounted::WindowsCharacter->value), $source)
            ->whereIn('loot_rule', LootGroupScope::knownRules())
            ->selectRaw('source_kind, source_id')->groupBy('source_kind', 'source_id')->havingRaw('count(distinct loot_rule) >= 2');

        return (int) $this->read->value(DB::query()->fromSub($per, 's')->selectRaw('count(*) as sources'), 'sources');
    }
}
