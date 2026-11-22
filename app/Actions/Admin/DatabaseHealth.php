<?php

namespace App\Actions\Admin;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The numbers of /admin/database: sizes, row estimates, compression, background jobs, cache hits, connections, vacuum
 * and index use (docs/DESIGN.md, Admin). Every read is raw SQL over the PostgreSQL and TimescaleDB catalogs, which have
 * no models; none takes a value from outside, and none counts rows.
 */
class DatabaseHealth
{
    private const int SECONDS = 60;

    private const string TABLES = <<<'SQL'
        with hyper as (select hypertable_schema as s, hypertable_name as n from timescaledb_information.hypertables where hypertable_schema = current_schema())
        select c.relname as name, 'table' as kind, pg_relation_size(c.oid) as heap, pg_indexes_size(c.oid) as indexes,
            coalesce(pg_total_relation_size(nullif(c.reltoastrelid, 0)), 0) as toast, pg_total_relation_size(c.oid) as total, greatest(c.reltuples, 0)::bigint as rows
        from pg_class c join pg_namespace ns on ns.oid = c.relnamespace
        where ns.nspname = current_schema() and c.relkind in ('r', 'p') and not exists (select 1 from hyper where hyper.n = c.relname)
        union all
        select h.n, 'hypertable', d.table_bytes, d.index_bytes, d.toast_bytes, d.total_bytes, approximate_row_count(format('%I.%I', h.s, h.n)::regclass)
        from hyper h cross join lateral hypertable_detailed_size(format('%I.%I', h.s, h.n)::regclass) d
        union all
        select a.view_name, 'aggregate', d.table_bytes, d.index_bytes, d.toast_bytes, d.total_bytes,
            approximate_row_count(format('%I.%I', a.materialization_hypertable_schema, a.materialization_hypertable_name)::regclass)
        from timescaledb_information.continuous_aggregates a
        cross join lateral hypertable_detailed_size(format('%I.%I', a.materialization_hypertable_schema, a.materialization_hypertable_name)::regclass) d
        where a.view_schema = current_schema()
        order by total desc nulls last
        SQL;

    private const string COMPRESSION = <<<'SQL'
        select h.hypertable_name as name, h.num_chunks as chunks, coalesce(c.number_compressed_chunks, 0) as compressed_chunks,
            coalesce(c.before_compression_total_bytes, 0) as before_bytes, coalesce(c.after_compression_total_bytes, 0) as after_bytes
        from timescaledb_information.hypertables h
        left join lateral hypertable_compression_stats(format('%I.%I', h.hypertable_schema, h.hypertable_name)::regclass) c on true
        where h.hypertable_schema = current_schema()
        order by coalesce(c.before_compression_total_bytes, 0) desc, h.num_chunks desc
        SQL;

    private const string JOBS = <<<'SQL'
        select j.job_id as id, j.proc_name as kind, coalesce(j.hypertable_name, '') as target, j.schedule_interval::text as every,
            s.last_run_started_at as last_at, coalesce(s.last_run_status, '') as status,
            extract(epoch from s.last_run_duration)::float8 as seconds, s.next_start as next_at,
            coalesce(s.total_runs, 0) as runs, coalesce(s.total_failures, 0) as failures
        from timescaledb_information.jobs j left join timescaledb_information.job_stats s on s.job_id = j.job_id
        order by coalesce(s.total_failures, 0) desc, s.last_run_duration desc nulls last
        SQL;

    private const string ACTIVITY = <<<'SQL'
        select coalesce(state, 'background') as state, count(*) as n,
            coalesce(max(extract(epoch from now() - query_start)) filter (where state = 'active' and pid <> pg_backend_pid()), 0)::float8 as longest
        from pg_stat_activity where datname = current_database() group by 1 order by 2 desc
        SQL;

    private const string VACUUM = <<<'SQL'
        select coalesce(c.hypertable_name, t.relname) as name, sum(t.n_live_tup)::bigint as live, sum(t.n_dead_tup)::bigint as dead,
            max(t.last_autovacuum) as vacuumed_at, max(t.last_autoanalyze) as analyzed_at, sum(t.autovacuum_count)::bigint as vacuums
        from pg_stat_user_tables t
        left join timescaledb_information.chunks c on c.chunk_schema = t.schemaname and c.chunk_name = t.relname
        where t.schemaname in (current_schema(), '_timescaledb_internal')
        group by 1 order by 3 desc, 2 desc limit 20
        SQL;

    private const string INDEXES = <<<'SQL'
        select i.indexrelname as name, i.relname as tbl, i.idx_scan as scans, pg_relation_size(i.indexrelid) as bytes, x.indisunique as is_unique
        from pg_stat_user_indexes i join pg_index x on x.indexrelid = i.indexrelid
        where i.schemaname = current_schema()
            and not exists (select 1 from timescaledb_information.hypertables h where h.hypertable_schema = i.schemaname and h.hypertable_name = i.relname)
        order by i.idx_scan desc
        SQL;

    private const string SERVER = <<<'SQL'
        select current_setting('server_version') as version, (select extversion from pg_extension where extname = 'timescaledb') as timescaledb,
            pg_postmaster_start_time() as started_at, pg_database_size(current_database()) as bytes,
            current_setting('shared_buffers') as shared_buffers, current_setting('max_connections') as max_connections, current_setting('work_mem') as work_mem,
            d.xact_commit as commits, d.xact_rollback as rollbacks, d.deadlocks, d.temp_files, d.temp_bytes, d.conflicts,
            d.tup_returned as returned, d.tup_fetched as fetched, d.tup_inserted as inserted, d.tup_updated as updated, d.tup_deleted as deleted,
            d.blks_hit as blocks_hit, d.blks_read as blocks_read, d.stats_reset as reset_at,
            (select sum(heap_blks_hit)::float8 / nullif(sum(heap_blks_hit) + sum(heap_blks_read), 0) from pg_statio_user_tables) as table_hits,
            (select sum(idx_blks_hit)::float8 / nullif(sum(idx_blks_hit) + sum(idx_blks_read), 0) from pg_statio_user_indexes) as index_hits
        from pg_stat_database d where d.datname = current_database()
        SQL;

    /** @return array<string, mixed> */
    public function handle(): array
    {
        /** @var array<string, mixed> */
        return Cache::remember(self::key(), self::SECONDS, function (): array {
            $indexes = self::rows(self::INDEXES);

            return [
                'server' => (array) DB::selectOne(self::SERVER),
                'tables' => self::rows(self::TABLES),
                'compression' => self::rows(self::COMPRESSION),
                'jobs' => self::rows(self::JOBS),
                'activity' => self::rows(self::ACTIVITY),
                'vacuum' => self::rows(self::VACUUM),
                'busiest_indexes' => array_slice($indexes, 0, 12),
                'unused_indexes' => array_slice(self::unused($indexes), 0, 12),
                'at' => now()->toIso8601String(),
            ];
        });
    }

    /** @return list<array<string, mixed>> */
    private static function rows(string $sql): array
    {
        /** @var list<array<string, mixed>> */
        return array_values(array_map(fn (object $row): array => (array) $row, DB::select($sql)));
    }

    /**
     * @param  list<array<string, mixed>>  $indexes
     * @return list<array<string, mixed>> the indexes that enforce nothing and were never scanned since the statistics were reset, largest first
     */
    private static function unused(array $indexes): array
    {
        $unused = array_values(array_filter($indexes, fn (array $index): bool => (int) $index['scans'] === 0 && ! $index['is_unique']));
        usort($unused, fn (array $a, array $b): int => (int) $b['bytes'] <=> (int) $a['bytes']);

        return $unused;
    }

    private static function key(): string
    {
        return 'admin-database';
    }
}
