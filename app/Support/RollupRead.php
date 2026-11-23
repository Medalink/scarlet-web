<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

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
    private const int DEFINITION_SECONDS = 300;

    /** @var array<string, array{definition: string, table: string}> */
    private array $definitions = [];

    /**
     * The aggregate over the scope. A `CharacterScope` reads the aggregate's own definition over only those characters'
     * rows (docs/DESIGN.md, Rollups, A player's own numbers).
     *
     * @param  int|list<int>|CharacterScope|null  $scope  one ruleset, several (an empty list matches none), null for all, or characters
     */
    public function from(Rollup $rollup, int|array|CharacterScope|null $scope): Builder
    {
        if (! $scope instanceof CharacterScope) {
            return $this->table($rollup->value, $scope);
        }

        $source = new Expression('('.$this->narrowed($rollup->value).') as '.$rollup->value); // @phpstan-ignore argument.type (the aggregate's own SQL from the catalog, with one binding)

        return $this->withRulesets(DB::query()->from($source)->addBinding($scope->characterArray(), 'from'), $scope->rulesetIds);
    }

    /**
     * A table, or an aggregate, narrowed to the scope's rulesets, and to its characters when it has them.
     *
     * @param  int|list<int>|CharacterScope|null  $scope
     */
    public function table(string $table, int|array|CharacterScope|null $scope): Builder
    {
        if ($scope instanceof CharacterScope) {
            return $this->withRulesets(DB::table($table)->whereIn('character_id', $scope->characterIds), $scope->rulesetIds);
        }

        return $this->withRulesets(DB::table($table), $scope);
    }

    /**
     * The fewest characters a group needs to be shown: `config('scarlet.min_characters')` in the public scopes, and one
     * for the characters of a player, whose own rows are shown whole.
     *
     * @param  int|list<int>|CharacterScope|null  $scope
     */
    public function threshold(int|array|CharacterScope|null $scope): int
    {
        return $scope instanceof CharacterScope ? 1 : (int) config('scarlet.min_characters');
    }

    /**
     * Hide the groups that fewer than the scope's threshold of characters contributed to. Every read of a scope that can
     * be a `CharacterScope` passes it.
     *
     * @param  int|list<int>|CharacterScope|null  $scope
     */
    public function shown(Builder $query, int|array|CharacterScope|null $scope = null): Builder
    {
        if ($scope instanceof CharacterScope) {
            return $query;
        }

        return $query->havingRaw('distinct_count(rollup(characters)) >= ?', [$this->threshold($scope)]);
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

    /**
     * An answer built from rows, kept like a read: for the fresh and then the stale seconds, under a key of what it was
     * built from. Keep plain arrays, which a later release of the code can still read.
     *
     * @param  array<array-key, mixed>  $inputs  what the answer was asked for
     * @param  Closure(): array<array-key, mixed>  $build
     * @return array<array-key, mixed>
     */
    public function kept(string $kind, array $inputs, Closure $build): array
    {
        $wrapped = Cache::flexible(
            self::builtKey($kind, $inputs),
            [(int) config('scarlet.rollups.read_fresh_seconds'), (int) config('scarlet.rollups.read_stale_seconds')],
            fn (): array => [$build()],
        );

        return $wrapped[0];
    }

    /** @param  int|list<int>|null  $rulesetId */
    private function withRulesets(Builder $query, int|array|null $rulesetId): Builder
    {
        return $query
            ->when(is_int($rulesetId), fn (Builder $query): Builder => $query->where('ruleset_id', $rulesetId))
            ->when(is_array($rulesetId), fn (Builder $query): Builder => $query->whereIn('ruleset_id', $rulesetId));
    }

    /**
     * The aggregate's stored definition with its fact table replaced by that table's rows of the characters in one
     * binding. The definition names the fact table exactly once, after `FROM`, and has no placeholder of its own.
     */
    private function narrowed(string $view): string
    {
        $stored = $this->definitions[$view] ??= $this->definition($view);
        $fact = $stored['table'];
        $narrowed = preg_replace('/\bfrom\s+'.preg_quote($fact, '/').'\b/i', 'FROM (SELECT * FROM '.$fact.' WHERE character_id = ANY (?::bigint[])) '.$fact, $stored['definition'], -1, $count);
        if ($count !== 1 || ! is_string($narrowed) || str_contains($stored['definition'], '?')) {
            throw new RuntimeException("The definition of {$view} does not name {$fact} exactly once and nothing else to bind.");
        }

        return $narrowed;
    }

    /**
     * Kept for `DEFINITION_SECONDS`, since a definition changes only with a migration and the catalog read costs about
     * 5 ms, a third of an own page's time when it is read for each aggregate.
     *
     * @return array{definition: string, table: string}
     */
    private function definition(string $view): array
    {
        /** @var array{definition: string, table: string} */
        return Cache::remember(self::definitionKey($view), self::DEFINITION_SECONDS, function () use ($view): array {
            $row = DB::table('timescaledb_information.continuous_aggregates')->where('view_name', $view)->first(['view_definition', 'hypertable_name']);
            if ($row === null) {
                throw new RuntimeException("{$view} is not a continuous aggregate.");
            }

            return ['definition' => rtrim(trim((string) $row->view_definition), ';'), 'table' => (string) $row->hypertable_name];
        });
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

    private static function definitionKey(string $view): string
    {
        return 'rollup-definition:'.$view;
    }

    /** @param  array<array-key, mixed>  $inputs */
    private static function builtKey(string $kind, array $inputs): string
    {
        return 'rollup-built:'.sha1($kind.'|'.json_encode($inputs, JSON_THROW_ON_ERROR));
    }

    private static function key(string $kind, Builder $query): string
    {
        return 'rollup-read:'.sha1($kind.'|'.$query->toSql().'|'.json_encode($query->getBindings(), JSON_THROW_ON_ERROR));
    }
}
