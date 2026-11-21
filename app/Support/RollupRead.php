<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * What every read of a ruleset aggregate shares: the optional ruleset (none means every ruleset, summed)
 * and the rule that a bucket is shown only when enough characters contributed to it.
 *
 * An answer is kept for `scarlet.rollups.read_fresh_seconds`, then served stale for `read_stale_seconds` while one
 * request reads it again. The aggregates change only when a refresh runs, so this keeps a busy page to one read of
 * each aggregate per window however many visitors ask. The key is the query itself (SQL and bindings), so no viewer
 * can be handed what another asked for.
 */
class RollupRead
{
    /** @param  int|list<int>|null  $rulesetId  one ruleset, several (an empty list matches none) or null for all */
    public function from(Rollup $rollup, int|array|null $rulesetId): Builder
    {
        return DB::table($rollup->value)
            ->when(is_int($rulesetId), fn (Builder $query): Builder => $query->where('ruleset_id', $rulesetId))
            ->when(is_array($rulesetId), fn (Builder $query): Builder => $query->whereIn('ruleset_id', $rulesetId));
    }

    /** Hide the groups that fewer than `config('scarlet.min_characters')` characters contributed to. */
    public function shown(Builder $query): Builder
    {
        return $query->havingRaw('distinct_count(rollup(characters)) >= ?', [(int) config('scarlet.min_characters')]);
    }

    /** @return list<array<string, mixed>> */
    public function rows(Builder $query): array
    {
        /** @var list<array<string, mixed>> */
        return $this->remember('rows', $query, fn (): array => array_values($query->get()->map(fn (object $row): array => (array) $row)->all()));
    }

    /** The first row's `$column` of a query, null when it has no row. */
    public function value(Builder $query, string $column): mixed
    {
        return $this->remember('value:'.$column, $query, fn (): mixed => $query->value($column));
    }

    /** The sum of `$column` over a query. */
    public function sum(Builder $query, string $column): int|float
    {
        /** @var int|float */
        return $this->remember('sum:'.$column, $query, fn (): int|float => $query->sum($column) + 0);
    }

    /** @param  Closure(): mixed  $read */
    private function remember(string $kind, Builder $query, Closure $read): mixed
    {
        $wrapped = Cache::flexible(
            self::key($kind, $query),
            [(int) config('scarlet.rollups.read_fresh_seconds'), (int) config('scarlet.rollups.read_stale_seconds')],
            fn (): array => [$read()],
        );

        return $wrapped[0];
    }

    private static function key(string $kind, Builder $query): string
    {
        return 'rollup-read:'.sha1($kind.'|'.$query->toSql().'|'.json_encode($query->getBindings(), JSON_THROW_ON_ERROR));
    }
}
