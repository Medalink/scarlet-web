<?php

namespace App\Actions\Public;

use App\Support\Rollup;
use App\Support\RollupRead;
use App\Support\SearchKind;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class Search
{
    public function __construct(private readonly RollupRead $read, private readonly ListRulesets $rulesets) {}

    /**
     * Public characters, creatures, items, zones and quests whose name contains the text, best match first (a name
     * that starts with it, then the shortest). With no kind, each kind answers its first `$limit` hits and its total;
     * with a kind, that kind answers `$limit + 1` hits from `$offset` so the caller can tell whether a next page
     * exists. A hit carries what it is and the numbers a result line shows; a number under the character threshold
     * is null. Characters are listed only when their owner made them public.
     *
     * @return array<string, array{count: int, hits: list<array<string, mixed>>}>
     */
    public function handle(string $term, ?SearchKind $kind, int $offset, int $limit): array
    {
        $pattern = '%'.addcslashes($term, '\\%_').'%';
        $answer = [];

        foreach ($kind === null ? SearchKind::cases() : [$kind] as $each) {
            $query = $this->matches($each, $pattern, $term);
            $answer[$each->value] = [
                'count' => (clone $query)->reorder()->getCountForPagination(),
                'hits' => $this->enrich($each, array_values($query->offset($offset)->limit($kind === null ? $limit : $limit + 1)->get()->map(fn (object $row): array => (array) $row)->all())),
            ];
        }

        return $answer;
    }

    private function matches(SearchKind $kind, string $pattern, string $term): Builder
    {
        $nameKind = $kind->nameKind();
        if ($nameKind !== null) {
            return DB::table('names')
                ->where('kind', $nameKind->value)
                ->where('name', 'ilike', $pattern)
                ->groupBy('id')
                ->selectRaw('id, (array_agg(name order by votes desc, name))[1] as name')
                ->orderByRaw('(lower(min(name)) like lower(?)) desc', [addcslashes($term, '\\%_').'%'])
                ->orderByRaw('length(min(name))')
                ->orderBy('id');
        }

        if ($kind === SearchKind::Zones) {
            return DB::table('zones')->where('name', 'ilike', $pattern)->select('id', 'name')
                ->orderByRaw('(lower(name) like lower(?)) desc', [addcslashes($term, '\\%_').'%'])->orderByRaw('length(name)')->orderBy('id');
        }

        return DB::table('characters')->where('is_public', true)->where('name_key', 'ilike', $pattern)
            ->select('id', 'name', 'race', 'class', 'level', 'played_seconds')
            ->selectRaw('(select s.ruleset_id from sessions s where s.character_id = characters.id order by s.end_at desc limit 1) as ruleset_id')
            ->orderByRaw('(name_key like lower(?)) desc', [addcslashes($term, '\\%_').'%'])->orderByRaw('length(name)')->orderBy('id');
    }

    /**
     * @param  list<array<string, mixed>>  $hits
     * @return list<array<string, mixed>>
     */
    private function enrich(SearchKind $kind, array $hits): array
    {
        if ($hits === []) {
            return [];
        }
        $ids = array_map(fn (array $hit): int => (int) $hit['id'], $hits);
        $more = match ($kind) {
            SearchKind::Creatures => $this->creatures($ids),
            SearchKind::Items => $this->items($ids),
            SearchKind::Zones => $this->zones($ids),
            SearchKind::Quests => $this->quests($ids),
            SearchKind::Characters => $this->characters($hits),
        };

        return array_map(fn (array $hit): array => [
            'kind' => $kind->value,
            'id' => (int) $hit['id'],
            'name' => (string) $hit['name'],
            'path' => $kind->path().($kind === SearchKind::Characters ? $hit['name'] : $hit['id']),
            ...($kind === SearchKind::Characters ? ['race' => $hit['race'], 'class' => $hit['class'], 'level' => $hit['level'] === null ? null : (int) $hit['level'], 'played_seconds' => (int) $hit['played_seconds']] : []),
            ...($more[(int) $hit['id']] ?? []),
        ], $hits);
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array<string, mixed>>
     */
    private function creatures(array $ids): array
    {
        $kills = $this->keyed($this->read->rows($this->read->shown(
            $this->read->from(Rollup::KillsDailyRuleset, null)->selectRaw('npc_id, sum(kills) as kills, distinct_count(rollup(characters)) as characters')->whereIn('npc_id', $ids)->groupBy('npc_id'),
        )), 'npc_id', ['kills', 'characters']);
        $facts = $this->keyed($this->read->rows($this->read->shown(
            $this->read->from(Rollup::CreatureFactsDaily, null)->selectRaw('npc_id, min(npc_level) as level_min, max(npc_level) as level_max, max(creature_type) as type')->whereIn('npc_id', $ids)->groupBy('npc_id'),
        )), 'npc_id', ['level_min', 'level_max', 'type']);

        $both = [];
        foreach ($ids as $id) {
            $both[$id] = [...($facts[$id] ?? ['level_min' => null, 'level_max' => null, 'type' => null]), ...($kills[$id] ?? ['kills' => null, 'characters' => null])];
        }

        return $both;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array<string, mixed>>
     */
    private function items(array $ids): array
    {
        $found = [];
        foreach (DB::table('items')->whereIn('item_id', $ids)->get(['item_id', 'item_level', 'quality', 'equip_loc', 'class_id', 'subclass_id']) as $item) {
            $found[(int) $item->item_id] = ['item_level' => $item->item_level, 'quality' => $item->quality, 'equip_loc' => $item->equip_loc, 'class_id' => $item->class_id, 'subclass_id' => $item->subclass_id];
        }
        $sources = DB::table(Rollup::DropsDaily->value)->whereIn('item_id', $ids)->groupBy('item_id')
            ->selectRaw('item_id, count(distinct (source_kind, source_id)) as sources')
            ->havingRaw('distinct_count(rollup(characters)) >= ?', [(int) config('scarlet.min_characters')])->pluck('sources', 'item_id');
        foreach ($ids as $id) {
            $found[$id] = [...($found[$id] ?? []), 'sources' => isset($sources[$id]) ? (int) $sources[$id] : null];
        }

        return $found;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array<string, mixed>>
     */
    private function zones(array $ids): array
    {
        $played = $this->keyed($this->read->rows($this->read->shown(
            $this->read->from(Rollup::PlayHourlyRuleset, null)->selectRaw('zone_id, (sum(ms) / 1000.0)::float as seconds, distinct_count(rollup(characters)) as characters')->whereIn('zone_id', $ids)->groupBy('zone_id'),
        )), 'zone_id', ['seconds']);
        $deaths = $this->keyed($this->read->rows(
            $this->read->from(Rollup::DeathsDailyRuleset, null)->selectRaw('zone_id, sum(deaths) as deaths')->whereIn('zone_id', $ids)->groupBy('zone_id'),
        ), 'zone_id', ['deaths']);

        $both = [];
        foreach ($ids as $id) {
            $both[$id] = ['seconds' => $played[$id]['seconds'] ?? null, 'deaths' => isset($played[$id]) ? ($deaths[$id]['deaths'] ?? 0) : null];
        }

        return $both;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array<string, mixed>>
     */
    private function quests(array $ids): array
    {
        return $this->keyed($this->read->rows($this->read->shown(
            $this->read->from(Rollup::QuestsDailyRuleset, null)->selectRaw('quest_id, sum(turn_ins) as turn_ins, distinct_count(rollup(characters)) as characters')->whereIn('quest_id', $ids)->groupBy('quest_id'),
        )), 'quest_id', ['turn_ins']);
    }

    /**
     * @param  list<array<string, mixed>>  $hits
     * @return array<int, array<string, mixed>>
     */
    private function characters(array $hits): array
    {
        $labels = collect($this->rulesets->handle())->keyBy('id');
        $found = [];
        foreach ($hits as $hit) {
            $ruleset = $labels->get($hit['ruleset_id'] ?? 0);
            $found[(int) $hit['id']] = ['ruleset' => $ruleset['slug'] ?? null, 'ruleset_label' => $ruleset['label'] ?? null, 'hardcore' => (bool) ($ruleset['hardcore'] ?? false)];
        }

        return $found;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $columns
     * @return array<int, array<string, mixed>>
     */
    private function keyed(array $rows, string $key, array $columns): array
    {
        $keyed = [];
        foreach ($rows as $row) {
            $keyed[(int) $row[$key]] = array_map(fn (mixed $value): mixed => is_numeric($value) ? $value + 0 : $value, array_intersect_key($row, array_flip($columns)));
        }

        return $keyed;
    }
}
