<?php

namespace App\Actions\Rollups;

use App\Support\FactTable;
use App\Support\Rollup;
use Illuminate\Support\Facades\DB;

class ApplyStoragePolicies
{
    /** Rebuilt for a character on every ingest, so compressing it would decompress its old chunks on every upload. */
    public const string LEVELS = 'character_levels';

    /** The widest bucket of an aggregate is a day, and a refresh window must hold two buckets. */
    private const string MIN_REFRESH_OFFSET = '2 days';

    /**
     * Make the hypertables follow `config('scarlet.storage')`: each one's chunk interval, the compression settings
     * and compression policy of the fact tables, and the start of every aggregate's refresh policy. A second run
     * changes nothing. It never sets, reads or removes a retention policy: a retention setting, or a retention policy
     * found on a table, stops it before anything changes.
     *
     * @return array{refused: string|null, changes: list<string>}
     */
    public function handle(): array
    {
        $refused = $this->refusal();
        if ($refused !== null) {
            return ['refused' => $refused, 'changes' => []];
        }

        $changes = [];
        foreach ([...array_column(FactTable::cases(), 'value'), self::LEVELS] as $table) {
            $changes = [...$changes, ...$this->chunkInterval($table), ...($table === self::LEVELS ? [] : $this->compression($table))];
        }
        foreach (Rollup::cases() as $rollup) {
            $changes = [...$changes, ...$this->refreshPolicy($rollup)];
        }

        return ['refused' => null, 'changes' => $changes];
    }

    private function refusal(): ?string
    {
        $retention = config('scarlet.storage.retention');
        if ($retention !== null && $retention !== '') {
            return 'storage.retention is set. Dropping fact rows cannot be undone short of decoding every session again, so no retention policy is applied.';
        }

        foreach (['segmentby', 'orderby'] as $key) {
            if (preg_match('/^[a-z_][a-z0-9_]*(\s*,\s*[a-z_][a-z0-9_]*)*$/', (string) config("scarlet.storage.{$key}")) !== 1) {
                return "storage.{$key} must be a comma-separated list of column names.";
            }
        }

        $offset = (string) config('scarlet.storage.refresh_start_offset');
        $after = (string) config('scarlet.storage.compress_after');
        $row = DB::selectOne('select ?::interval >= ?::interval as wide, ?::interval < ?::interval as short', [$offset, self::MIN_REFRESH_OFFSET, $offset, $after]);
        if (! $row->wide || ! $row->short) {
            return "refresh_start_offset ({$offset}) must be at least ".self::MIN_REFRESH_OFFSET." and shorter than compress_after ({$after}), so a refresh policy never reaches a compressed chunk.";
        }

        $found = DB::table('timescaledb_information.jobs')->where('proc_name', 'policy_retention')->pluck('hypertable_name');
        if ($found->isNotEmpty()) {
            return 'A retention policy exists on '.$found->implode(', ').'. Remove it by hand if that is meant; this command never drops or removes anything.';
        }

        return null;
    }

    /** @return list<string> */
    private function chunkInterval(string $table): array
    {
        $dense = $table !== self::LEVELS && FactTable::from($table)->dense();
        $wanted = (string) config($dense ? 'scarlet.storage.chunk_dense' : 'scarlet.storage.chunk_sparse');
        $differs = DB::selectOne(
            'select count(*) as n from timescaledb_information.dimensions where hypertable_name = ? and dimension_number = 1 and time_interval is distinct from ?::interval',
            [$table, $wanted],
        );
        if ((int) $differs->n === 0) {
            return [];
        }
        DB::selectOne('select set_chunk_time_interval(?::regclass, ?::interval)', [$table, $wanted]);

        return ["{$table}: new chunks cover {$wanted}"];
    }

    /** @return list<string> */
    private function compression(string $table): array
    {
        $segmentby = (string) config('scarlet.storage.segmentby');
        $orderby = (string) config('scarlet.storage.orderby');
        $after = (string) config('scarlet.storage.compress_after');
        $changes = [];

        $current = DB::table('timescaledb_information.hypertable_compression_settings')->whereRaw('hypertable = ?::regclass', [$table])->first(['segmentby', 'orderby']);
        if ($current === null || $this->squash((string) $current->segmentby) !== $this->squash($segmentby) || $this->squash((string) $current->orderby) !== $this->squash($orderby)) {
            $statement = DB::selectOne(
                "select format('alter table %I set (timescaledb.compress, timescaledb.compress_segmentby = %L, timescaledb.compress_orderby = %L)', ?::text, ?::text, ?::text) as sql",
                [$table, $segmentby, $orderby],
            );
            DB::statement($statement->sql);
            $changes[] = "{$table}: compression by {$segmentby}, ordered by {$orderby}";
        }

        $job = DB::table('timescaledb_information.jobs')->where('proc_name', 'policy_compression')->where('hypertable_name', $table)->first(['job_id']);
        if ($job === null) {
            DB::selectOne('select add_compression_policy(?::regclass, compress_after => ?::interval)', [$table, $after]);
            $changes[] = "{$table}: compress chunks older than {$after}";

            return $changes;
        }
        if ($this->setConfig((int) $job->job_id, 'compress_after', $after)) {
            $changes[] = "{$table}: compress chunks older than {$after}";
        }

        return $changes;
    }

    /** @return list<string> */
    private function refreshPolicy(Rollup $rollup): array
    {
        $offset = (string) config('scarlet.storage.refresh_start_offset');
        $job = DB::table('timescaledb_information.jobs')->where('proc_name', 'policy_refresh_continuous_aggregate')->where('hypertable_name', $rollup->value)->first(['job_id']);
        if ($job === null) {
            DB::selectOne("select add_continuous_aggregate_policy(?::regclass, start_offset => ?::interval, end_offset => interval '1 hour', schedule_interval => interval '15 minutes')", [$rollup->value, $offset]);

            return ["{$rollup->value}: refresh policy added, from {$offset} back"];
        }

        return $this->setConfig((int) $job->job_id, 'start_offset', $offset) ? ["{$rollup->value}: refresh policy reaches {$offset} back"] : [];
    }

    /** Set one interval key of a job's config unless it already holds that interval. */
    private function setConfig(int $job, string $key, string $interval): bool
    {
        $changed = DB::selectOne(
            'select count(*) as n from timescaledb_information.jobs where job_id = ? and (config ->> ?)::interval is distinct from ?::interval',
            [$job, $key, $interval],
        );
        if ((int) $changed->n === 0) {
            return false;
        }
        DB::selectOne('select alter_job(?, config => jsonb_set(config, array[?::text], to_jsonb(?::text))) from timescaledb_information.jobs where job_id = ?', [$job, $key, $interval, $job]);

        return true;
    }

    private function squash(string $columns): string
    {
        return preg_replace('/\s+/', '', $columns) ?? $columns;
    }
}
