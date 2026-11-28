<?php

namespace App\View\Pages\Admin;

use App\View\Dates;
use App\View\Format;
use Illuminate\Support\HtmlString;

/** The view of /admin/database from the answer of DatabaseHealth (docs/comps/admin-database.html). */
final class DatabasePage
{
    private const int TOP_TABLES = 10;

    private const int TOP_ROWS = 8;

    private const int TOP_VACUUM = 10;

    private const int TOP_LISTED = 12;

    private const array JOB_KINDS = [
        'policy_refresh_continuous_aggregate' => 'Continuous aggregate',
        'policy_compression' => 'Compression',
        'policy_retention' => 'Retention',
        'policy_reorder' => 'Reorder',
        'policy_telemetry' => 'Telemetry',
        'policy_job_stat_history_retention' => 'Job history',
    ];

    private const array STATES = ['active' => 'Active', 'idle' => 'Idle', 'idle in transaction' => 'Idle in transaction', 'idle in transaction (aborted)' => 'Idle in an aborted transaction', 'background' => 'Background workers'];

    /**
     * @param  array<string, mixed>  $h
     * @return array<string, mixed>
     */
    public static function data(array $h): array
    {
        /** @var array<string, mixed> $server */
        $server = $h['server'];
        /** @var list<array<string, mixed>> $tables */
        $tables = $h['tables'];
        /** @var list<array<string, mixed>> $compression */
        $compression = $h['compression'];
        /** @var list<array<string, mixed>> $activity */
        $activity = $h['activity'];
        /** @var list<array<string, mixed>> $jobs */
        $jobs = $h['jobs'];
        $stored = array_values(array_filter($tables, fn (array $t): bool => $t['kind'] !== 'aggregate'));
        $hypertables = count(array_filter($tables, fn (array $t): bool => $t['kind'] === 'hypertable'));
        $packed = array_values(array_filter($compression, fn (array $c): bool => (int) $c['compressed_chunks'] > 0));
        $before = array_sum(array_map(fn (array $c): int => (int) $c['before_bytes'], $packed));
        $after = array_sum(array_map(fn (array $c): int => (int) $c['after_bytes'], $packed));
        $connections = array_sum(array_map(fn (array $a): int => (int) $a['n'], $activity));
        $failed = count(array_filter($jobs, fn (array $j): bool => $j['status'] === 'Failed'));

        return [
            'nav' => AdminCharts::nav('/admin/database'),
            'sub' => Dates::join(Format::bytes((int) $server['bytes']).' in '.count($stored).' tables, '.$hypertables.' of them hypertables. PostgreSQL '.$server['version'].', TimescaleDB '.$server['timescaledb'].', up since ', Dates::moment((string) $server['started_at']), '. Row counts are the planner\'s estimates. Read from the catalogs ', Dates::moment((string) $h['at']), '.'),
            'figures' => [
                ['label' => 'Size', 'value' => Format::bytes((int) $server['bytes']), 'icon' => null],
                ['label' => 'Tables', 'value' => Format::number(count($stored)), 'icon' => null],
                ['label' => 'Rows, estimated', 'value' => self::compact(array_sum(array_map(fn (array $t): int => (int) $t['rows'], $stored))), 'icon' => null],
                ['label' => 'Chunks', 'value' => Format::number(array_sum(array_map(fn (array $c): int => (int) $c['chunks'], $compression))), 'icon' => null],
                ['label' => 'Compression', 'value' => $after > 0 ? Format::number($before / $after, 1).'×' : 'None', 'icon' => null],
                ['label' => 'Connections', 'value' => Format::number($connections), 'icon' => null],
            ],
            'connections' => array_map(fn (array $a): array => [
                'label' => self::STATES[(string) $a['state']] ?? ucfirst((string) $a['state']),
                'value' => Format::number((int) $a['n']).($a['state'] === 'active' ? ' of '.$connections : ''),
                'share' => (int) $a['n'] / max(1, $connections) * 100,
            ], $activity),
            'longest' => max([0.0, ...array_map(fn (array $a): float => (float) $a['longest'], $activity)]),
            'cache' => [
                ['label' => 'Tables', 'value' => self::share($server['table_hits']), 'share' => (float) ($server['table_hits'] ?? 0) * 100],
                ['label' => 'Indexes', 'value' => self::share($server['index_hits']), 'share' => (float) ($server['index_hits'] ?? 0) * 100],
            ],
            'counters' => [
                ['Commits', Format::number((int) $server['commits'])],
                ['Rollbacks', Format::number((int) $server['rollbacks'])],
                ['Deadlocks', Format::number((int) $server['deadlocks'])],
                ['Temp files', Format::number((int) $server['temp_files']).' · '.Format::bytes((int) $server['temp_bytes'])],
                ['Rows inserted', self::compact((int) $server['inserted'])],
                ['Rows updated', self::compact((int) $server['updated'])],
                ['Rows deleted', self::compact((int) $server['deleted'])],
                ['Shared buffers', (string) $server['shared_buffers']],
                ['Work memory', (string) $server['work_mem']],
                ['Most connections', (string) $server['max_connections']],
            ],
            'packed' => $after > 0 ? [
                ['label' => 'Before', 'value' => Format::bytes($before), 'share' => 100],
                ['label' => 'After', 'value' => Format::bytes($after), 'share' => $after / $before * 100],
            ] : [],
            'sizes' => self::sizes($tables),
            'sizesNote' => self::rest($tables),
            'rows' => self::rows($stored),
            'hypertables' => self::hypertables(array_slice($compression, 0, self::TOP_LISTED)),
            'hypertablesNote' => count($compression) > self::TOP_LISTED ? (count($compression) - self::TOP_LISTED).' more hypertables hold less.' : null,
            'jobs' => self::jobs(array_slice($jobs, 0, self::TOP_LISTED)),
            'jobsNote' => $failed.' of '.count($jobs).' jobs failed their last run.'.(count($jobs) > self::TOP_LISTED ? ' Failed jobs come first, then the longest runs.' : ''),
            'scans' => self::indexes($h['busiest_indexes'], 'scans', ' scans'),
            'unused' => self::indexes($h['unused_indexes'], 'bytes', ''),
            'vacuum' => self::vacuum($h['vacuum']),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $tables
     * @return list<array<string, mixed>>
     */
    private static function sizes(array $tables): array
    {
        $top = array_slice($tables, 0, self::TOP_TABLES);
        $widest = max([1, ...array_map(fn (array $t): int => (int) $t['total'], $top)]);

        return array_map(fn (array $t): array => ['cells' => [
            ['text' => (string) $t['name'], 'code' => true, 'note' => $t['kind'] === 'table' ? null : ($t['kind'] === 'hypertable' ? 'hypertable' : 'aggregate')],
            ['text' => '', 'width' => round((int) $t['total'] / $widest * 12, 2).'rem', 'stack' => array_values(array_filter([
                ['class' => 'c-s1', 'count' => (int) $t['heap']],
                ['class' => 'c-s5', 'count' => (int) $t['indexes']],
                ['class' => 'c-s2', 'count' => (int) $t['toast']],
            ], fn (array $part): bool => $part['count'] > 0))],
            Format::bytes((int) $t['total']),
            Format::bytes((int) $t['heap']),
            Format::bytes((int) $t['indexes']),
            (int) $t['toast'] > 0 ? Format::bytes((int) $t['toast']) : ['text' => '', 'note' => '–'],
        ]], $top);
    }

    /** @param  list<array<string, mixed>>  $tables */
    private static function rest(array $tables): ?string
    {
        $rest = array_slice($tables, self::TOP_TABLES);
        if ($rest === []) {
            return null;
        }

        return count($rest).' more tables and aggregates hold '.Format::bytes(array_sum(array_map(fn (array $t): int => (int) $t['total'], $rest))).'.';
    }

    /**
     * @param  list<array<string, mixed>>  $tables
     * @return list<array<string, mixed>>
     */
    private static function rows(array $tables): array
    {
        usort($tables, fn (array $a, array $b): int => (int) $b['rows'] <=> (int) $a['rows']);
        $top = array_slice($tables, 0, self::TOP_ROWS);
        $most = max([1, ...array_map(fn (array $t): int => (int) $t['rows'], $top)]);

        return array_map(fn (int $i, array $t): array => [
            'label' => (string) $t['name'], 'who' => 'mono',
            'value' => self::compact((int) $t['rows']).($i === 0 ? ' rows' : ''),
            'share' => (int) $t['rows'] / $most * 100,
        ], array_keys($top), $top);
    }

    /**
     * @param  list<array<string, mixed>>  $compression
     * @return list<array<string, mixed>>
     */
    private static function hypertables(array $compression): array
    {
        $best = max([1.0, ...array_map(fn (array $c): float => (int) $c['after_bytes'] > 0 ? (int) $c['before_bytes'] / (int) $c['after_bytes'] : 0.0, $compression)]);

        return array_map(function (array $c) use ($best): array {
            $after = (int) $c['after_bytes'];
            $ratio = $after > 0 ? (int) $c['before_bytes'] / $after : 0.0;

            return ['cells' => [
                ['text' => (string) $c['name'], 'code' => true],
                Format::number((int) $c['chunks']),
                Format::number((int) $c['compressed_chunks']),
                $after > 0 ? Format::bytes((int) $c['before_bytes']) : ['text' => '', 'note' => '–'],
                $after > 0 ? Format::bytes($after) : ['text' => '', 'note' => '–'],
                $after > 0 ? ['text' => Format::number($ratio, 1).'×', 'bar' => round($ratio / $best * 9, 1).'rem'] : ['text' => '', 'note' => 'No chunk compressed yet'],
            ]];
        }, $compression);
    }

    /**
     * @param  list<array<string, mixed>>  $jobs
     * @return list<array<string, mixed>>
     */
    private static function jobs(array $jobs): array
    {
        return array_map(fn (array $j): array => ['cells' => [
            ['text' => $j['target'] === '' ? '#'.$j['id'] : (string) $j['target'], 'code' => true],
            self::JOB_KINDS[(string) $j['kind']] ?? (string) $j['kind'],
            self::every((string) $j['every']),
            $j['last_at'] === null ? ['text' => '', 'note' => 'Not run yet'] : self::when($j['last_at']),
            match ((string) $j['status']) {
                'Success' => 'Succeeded',
                'Failed' => ['text' => 'Failed', 'dot' => 'var(--accent)'],
                '' => ['text' => '', 'note' => '–'],
                default => (string) $j['status'],
            },
            $j['seconds'] === null ? ['text' => '', 'note' => '–'] : self::runTime((float) $j['seconds']),
            $j['next_at'] === null ? ['text' => '', 'note' => '–'] : self::when($j['next_at']),
        ]], $jobs);
    }

    /**
     * @param  list<array<string, mixed>>  $indexes
     * @return list<array<string, mixed>>
     */
    private static function indexes(array $indexes, string $by, string $unit): array
    {
        $most = max([1, ...array_map(fn (array $i): int => (int) $i[$by], $indexes)]);

        return array_map(fn (int $at, array $i): array => [
            'label' => (string) $i['name'], 'who' => 'mono',
            'value' => ($by === 'bytes' ? Format::bytes((int) $i['bytes']) : self::compact((int) $i['scans'])).($at === 0 ? $unit : ''),
            'share' => (int) $i[$by] / $most * 100,
        ], array_keys($indexes), $indexes);
    }

    /**
     * @param  list<array<string, mixed>>  $vacuum
     * @return list<array<string, mixed>>
     */
    private static function vacuum(array $vacuum): array
    {
        $rows = array_values(array_filter($vacuum, fn (array $v): bool => (int) $v['live'] + (int) $v['dead'] > 0));
        $rows = array_slice($rows, 0, self::TOP_VACUUM);
        $share = fn (array $v): float => (int) $v['dead'] / max(1, (int) $v['live'] + (int) $v['dead']);
        $most = max([0.0001, ...array_map($share, $rows)]);

        return array_map(fn (array $v): array => ['cells' => [
            ['text' => (string) $v['name'], 'code' => true],
            self::compact((int) $v['live']),
            Format::number((int) $v['dead']),
            ['text' => Format::percent($share($v), 1), 'bar' => round($share($v) / $most * 9, 1).'rem'],
            $v['vacuumed_at'] === null ? ['text' => '', 'note' => 'Never'] : self::when($v['vacuumed_at']),
            $v['analyzed_at'] === null ? ['text' => '', 'note' => 'Never'] : self::when($v['analyzed_at']),
        ]], $rows);
    }

    /** 912 → "912", 41,200 → "41.2K", 274,000,000 → "274M". */
    private static function compact(int $value): string
    {
        foreach ([[1_000_000_000, 'B'], [1_000_000, 'M'], [1_000, 'K']] as [$step, $unit]) {
            if ($value >= $step) {
                $scaled = $value / $step;

                return Format::number($scaled, $scaled < 100 ? 1 : 0).$unit;
            }
        }

        return Format::number($value);
    }

    private static function share(mixed $ratio): string
    {
        return $ratio === null ? '–' : Format::percent((float) $ratio, 1);
    }

    /** A PostgreSQL interval as words: "00:15:00" → "Every 15 minutes", "1 day" → "Every day". */
    private static function every(string $interval): string
    {
        $seconds = 0;
        if (preg_match('/(\d+) days?/', $interval, $days) === 1) {
            $seconds += (int) $days[1] * 86400;
        }
        if (preg_match('/(\d+):(\d+):(\d+)/', $interval, $clock) === 1) {
            $seconds += (int) $clock[1] * 3600 + (int) $clock[2] * 60 + (int) $clock[3];
        }
        foreach ([[86400, 'day'], [3600, 'hour'], [60, 'minute']] as [$step, $unit]) {
            if ($seconds >= $step && $seconds % $step === 0) {
                $count = intdiv($seconds, $step);

                return $count === 1 ? 'Every '.$unit : 'Every '.$count.' '.$unit.'s';
            }
        }

        return $seconds > 0 ? 'Every '.$seconds.' seconds' : $interval;
    }

    private static function runTime(float $seconds): string
    {
        return $seconds < 60 ? Format::number($seconds, 1).'s' : Format::duration($seconds);
    }

    /** @return array{date: HtmlString} */
    private static function when(mixed $instant): array
    {
        return ['date' => Dates::moment((string) $instant)];
    }
}
