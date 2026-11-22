<?php

namespace App\Actions\Public;

use App\Support\LootCounted;
use App\Support\LootListing;
use App\Support\LootRead;
use App\Support\LootRule;
use App\Support\LootSourceKind;
use App\Support\NameKind;
use App\Support\Rollup;
use App\Support\RollupRead;
use App\Support\SearchKind;
use App\Support\SearchMatch;
use App\Support\SearchTier;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

class Search
{
    /** The fewest characters of text that look for a close spelling. */
    private const int FUZZY_MIN_LENGTH = 4;

    public function __construct(private readonly RollupRead $read, private readonly ListRulesets $rulesets) {}

    /**
     * Public characters, creatures, items, objects, zones and quests whose name holds every word of the text, best match first:
     * the tier of the match (`SearchMatch`), then what players did with the thing (`search_scores`; a character's
     * time played), then the shorter name. Close spellings are looked for only when nothing matched any other way in
     * any kind asked for, at pg_trgm's own word similarity threshold (0.6), closest first. With no kind, each kind
     * answers its first `$limit` hits and its total; with a kind, that kind answers `$limit + 1` hits from `$offset` so
     * the caller can tell whether a next page exists. A hit carries how it matched and the numbers a result line
     * shows; a number under the character threshold is null. Characters are listed only when their owner made them
     * public.
     *
     * @return array<string, array{count: int, hits: list<array<string, mixed>>}>
     */
    public function handle(string $term, ?SearchKind $kind, int $offset, int $limit, bool $withCounts = true): array
    {
        $take = $kind === null ? $limit : $limit + 1;
        $kinds = $kind === null ? SearchKind::cases() : [$kind];
        $answer = [];
        if (SearchMatch::words($term) === []) {
            foreach ($kinds as $each) {
                $answer[$each->value] = ['count' => 0, 'hits' => []];
            }

            return $answer;
        }

        foreach ($kinds as $each) {
            $answer[$each->value] = $this->found($each, $this->strict($each, $term), $offset, $take, withCounts: $withCounts);
        }
        if (array_sum(array_column($answer, 'count')) > 0 || mb_strlen(trim($term)) < self::FUZZY_MIN_LENGTH) {
            return $answer;
        }
        foreach ($kinds as $each) {
            $answer[$each->value] = $this->found($each, $this->close($each, $term), $offset, $take, SearchMatch::Fuzzy, withCounts: $withCounts);
        }

        return $answer;
    }

    /**
     * The count and the page of one query; `$as` names the tier of every hit when the query does not rank them.
     *
     * @return array{count: int, hits: list<array<string, mixed>>}
     */
    private function found(SearchKind $kind, Builder $query, int $offset, int $take, ?SearchMatch $as = null, bool $withCounts = true): array
    {
        $count = $withCounts ? $query->getCountForPagination() : null;
        $rows = $count === 0 ? [] : $this->fetch($query, $offset, $take);
        if ($as !== null) {
            $rows = array_map(fn (array $row): array => [...$row, 'tier' => $as->rank()], $rows);
        }

        return ['count' => $count ?? count($rows), 'hits' => $this->enrich($kind, $rows)];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetch(Builder $query, int $offset, int $take): array
    {
        return array_values($query->offset($offset)->limit($take)->get()->map(fn (object $row): array => (array) $row)->all());
    }

    private function strict(SearchKind $kind, string $term): Builder
    {
        $nameKind = $kind->nameKind();
        if ($nameKind !== null) {
            [$holds, $holdBindings] = SearchTier::filter('names.name', $term, 'names.id');
            [$rank, $rankBindings] = SearchTier::rank('names.name', $term, 'names.id', grouped: true);

            return $this->named($kind, $nameKind)
                ->whereRaw($holds, $holdBindings)
                ->selectRaw($rank, $rankBindings)
                ->orderBy('tier')->orderByRaw('s.score desc nulls last')->orderByRaw('min(length(names.name))')->orderBy('names.id');
        }

        if ($kind === SearchKind::Zones) {
            [$holds, $holdBindings] = SearchTier::filter('zones.name', $term, 'zones.id');
            [$rank, $rankBindings] = SearchTier::rank('zones.name', $term, 'zones.id');

            return $this->scored($kind, 'zones', 'id')->select('zones.id', 'zones.name')
                ->whereRaw($holds, $holdBindings)
                ->selectRaw($rank, $rankBindings)
                ->orderBy('tier')->orderByRaw('s.score desc nulls last')->orderByRaw('length(zones.name)')->orderBy('zones.id');
        }

        [$holds, $holdBindings] = SearchTier::filter('characters.name_key', $term);
        [$rank, $rankBindings] = SearchTier::rank('characters.name_key', $term);

        return $this->characterRows()
            ->whereRaw($holds, $holdBindings)
            ->selectRaw($rank, $rankBindings)
            ->orderBy('tier')->orderByRaw('case when played_known then played_seconds end desc nulls last')->orderByRaw('length(name)')->orderBy('id');
    }

    private function close(SearchKind $kind, string $term): Builder
    {
        $text = mb_strtolower(trim($term));
        $nameKind = $kind->nameKind();
        if ($nameKind !== null) {
            return $this->named($kind, $nameKind)->whereRaw('names.name %> ?', [$text])
                ->orderByRaw('max(word_similarity(?, names.name)) desc', [$text])->orderByRaw('s.score desc nulls last')->orderByRaw('min(length(names.name))')->orderBy('names.id');
        }

        if ($kind === SearchKind::Zones) {
            return $this->scored($kind, 'zones', 'id')->select('zones.id', 'zones.name')->whereRaw('zones.name %> ?', [$text])
                ->orderByRaw('word_similarity(?, zones.name) desc', [$text])->orderByRaw('s.score desc nulls last')->orderByRaw('length(zones.name)')->orderBy('zones.id');
        }

        return $this->characterRows()->whereRaw('characters.name_key %> ?', [$text])
            ->orderByRaw('word_similarity(?, characters.name_key) desc', [$text])->orderByRaw('case when played_known then played_seconds end desc nulls last')->orderByRaw('length(name)')->orderBy('id');
    }

    /** The names of one kind, one row per id with its most voted name. */
    private function named(SearchKind $kind, NameKind $nameKind): Builder
    {
        return $this->scored($kind, 'names', 'id')
            ->where('names.kind', $nameKind->value)
            ->when($kind === SearchKind::Objects, fn (Builder $query): Builder => $query->whereNotNull('s.score'))
            ->groupBy('names.id', 's.score')
            ->selectRaw('names.id, (array_agg(names.name order by names.votes desc, names.name))[1] as name');
    }

    private function scored(SearchKind $kind, string $table, string $id): Builder
    {
        return DB::table($table)->leftJoin('search_scores as s', fn (JoinClause $join) => $join->on('s.id', '=', $table.'.'.$id)->where('s.kind', $kind->value));
    }

    private function characterRows(): Builder
    {
        return DB::table('characters')->where('is_public', true)
            ->select('id', 'name', 'race', 'class', 'level', 'played_seconds', 'played_known', 'recorded_seconds')
            ->selectRaw('(select s.ruleset_id from sessions s where s.character_id = characters.id order by s.end_at desc limit 1) as ruleset_id');
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
            SearchKind::Objects => $this->objects($ids),
            SearchKind::Zones => $this->zones($ids),
            SearchKind::Quests => $this->quests($ids),
            SearchKind::Characters => $this->characters($hits),
        };

        return array_map(fn (array $hit): array => [
            'kind' => $kind->value,
            'id' => (int) $hit['id'],
            'name' => (string) $hit['name'],
            'match' => SearchMatch::fromRank((int) $hit['tier'])->value,
            'path' => $kind->path().($kind === SearchKind::Characters ? $hit['name'] : $hit['id']),
            ...($kind === SearchKind::Characters ? ['race' => $hit['race'], 'class' => $hit['class'], 'level' => $hit['level'] === null ? null : (int) $hit['level'], 'played_seconds' => $hit['played_known'] ? (int) $hit['played_seconds'] : null, 'recorded_seconds' => (int) $hit['recorded_seconds']] : []),
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
        $sources = LootRead::known(DB::table(LootCounted::Items->value))->whereIn('item_id', $ids)->groupBy('item_id')
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
    private function objects(array $ids): array
    {
        return $this->keyed($this->read->rows($this->read->shown(LootRule::within(LootListing::ofObjects($this->read->table(LootCounted::Windows->value, null)), 'loot_rule', null)
            ->selectRaw('source_id, sum(windows) as opened, distinct_count(rollup(characters)) as characters')
            ->where('source_kind', LootSourceKind::Object->value)->whereIn('source_id', $ids)->groupBy('source_id'))), 'source_id', ['opened']);
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
