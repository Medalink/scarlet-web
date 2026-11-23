<?php

namespace App\Actions\Admin;

use App\Models\Install;
use App\Models\Part;
use App\Models\PlaySession;
use App\Models\UploadUsage;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MetricsRepository;

/**
 * The numbers of /admin/ingest: parts per day and sharing group, the decode backlog and how long a part waits for its
 * decode, the queues, and which decoder and addon versions the sessions came from (docs/DESIGN.md, Admin).
 */
class IngestPipeline
{
    public const int DAYS = 30;

    private const int HOURS_DAYS = 28;

    private const int SECONDS = 60;

    public function __construct(private readonly JobRepository $jobs, private readonly MetricsRepository $metrics) {}

    /** @return array<string, mixed> */
    public function handle(): array
    {
        /** @var array<string, mixed> */
        return Cache::remember(self::key(), self::SECONDS, fn (): array => [
            'groups' => $this->groups(),
            'undecoded' => Part::query()->whereNull('decoded_at')->count(),
            'failed_parts' => Part::query()->whereNull('decoded_at')
                ->where('failed_attempts', '>=', (int) config('scarlet.ingest.sweep_max_failures'))->count(),
            'sessions' => $this->sessions(),
            'latency' => $this->latency(),
            'queues' => $this->queues(),
            'decoders' => $this->versions(DB::raw("coalesce(decoder_version::text, '') as v")),
            'addons' => $this->versions(DB::raw("coalesce(extra->>'addon_version', '') as v")),
            'hours' => $this->hours(),
            'installs_today' => $this->installsToday(),
            'at' => now()->toIso8601String(),
        ]);
    }

    /** @return list<array{day: string, group: string, parts: int, bytes: int}> parts uploaded per UTC day and sharing group */
    private function groups(): array
    {
        return array_values(Part::query()->where('created_at', '>=', now()->startOfDay()->subDays(self::DAYS))->toBase()
            ->groupByRaw('1, 2')->orderByRaw('1, 2')
            ->selectRaw("(created_at at time zone 'UTC')::date::text as day, group_key as grp, count(*) as parts, sum(raw_bytes) as bytes")
            ->get()->map(fn (object $row): array => ['day' => (string) $row->day, 'group' => (string) $row->grp, 'parts' => (int) $row->parts, 'bytes' => (int) $row->bytes])->all());
    }

    /** @return array<string, int> sessions decoded per UTC day */
    private function sessions(): array
    {
        return PlaySession::query()->where('decoded_at', '>=', now()->startOfDay()->subDays(self::DAYS))->toBase()
            ->groupByRaw('1')->selectRaw("(decoded_at at time zone 'UTC')::date::text as day, count(*) as n")
            ->pluck('n', 'day')->map(fn (mixed $n): int => (int) $n)->all();
    }

    /** @return array<string, array{median: float, p90: float, parts: int}> seconds from upload to decode per UTC day of the upload */
    private function latency(): array
    {
        $rows = Part::query()->whereNotNull('decoded_at')->where('created_at', '>=', now()->startOfDay()->subDays(self::DAYS))->toBase()
            ->groupByRaw('1')->orderByRaw('1')->selectRaw(
                "(created_at at time zone 'UTC')::date::text as day,
                percentile_cont(0.5) within group (order by extract(epoch from decoded_at - created_at)) as median,
                percentile_cont(0.9) within group (order by extract(epoch from decoded_at - created_at)) as p90, count(*) as parts",
            )->get();

        $latency = [];
        foreach ($rows as $row) {
            $latency[(string) $row->day] = ['median' => (float) $row->median, 'p90' => (float) $row->p90, 'parts' => (int) $row->parts];
        }

        return $latency;
    }

    /** @return array{queues: list<array{name: string, length: int, throughput: int, runtime: float}>, failed: int, failed_recent: int, per_minute: float} */
    private function queues(): array
    {
        $queues = [];
        foreach (array_unique(array_values((array) config('scarlet.queues'))) as $name) {
            $name = (string) $name;
            $queues[] = [
                'name' => $name,
                'length' => Queue::size($name),
                'throughput' => (int) $this->metrics->throughputForQueue($name),
                'runtime' => (float) $this->metrics->runtimeForQueue($name),
            ];
        }

        return [
            'queues' => $queues,
            'failed' => (int) $this->jobs->countFailed(),
            'failed_recent' => (int) $this->jobs->countRecentlyFailed(),
            'per_minute' => (float) $this->metrics->jobsProcessedPerMinute(),
        ];
    }

    /** @return array<string, int> sessions decoded over the last DAYS days per version, by the expression that names the version `v` */
    private function versions(Expression $version): array
    {
        return PlaySession::query()->where('decoded_at', '>=', now()->startOfDay()->subDays(self::DAYS))->toBase()->groupByRaw('1')->orderByDesc('n')->limit(12)
            ->select($version)->selectRaw('count(*) as n')
            ->pluck('n', 'v')->mapWithKeys(fn (mixed $n, mixed $v): array => [(string) $v => (int) $n])->all();
    }

    /** @return array<int, array<int, int>> parts uploaded per ISO weekday (1 Monday) and UTC hour */
    private function hours(): array
    {
        $grid = [];
        $rows = Part::query()->where('created_at', '>=', now()->subDays(self::HOURS_DAYS))->toBase()->groupByRaw('1, 2')->selectRaw(
            "extract(isodow from created_at at time zone 'UTC')::int as dow, extract(hour from created_at at time zone 'UTC')::int as hour, count(*) as n",
        )->get();
        foreach ($rows as $row) {
            $grid[(int) $row->dow][(int) $row->hour] = (int) $row->n;
        }

        return $grid;
    }

    /** @return list<array{install_id: string, name: string|null, account_id: int|null, bytes: int, requests: int}> */
    private function installsToday(): array
    {
        $usage = UploadUsage::query()->where('day', now()->toDateString())->orderByDesc('bytes')->limit(10)->get(['install_id', 'bytes', 'requests']);
        $installs = Install::query()->whereIn('id', $usage->pluck('install_id'))->get(['id', 'name', 'account_id'])->keyBy('id');

        return array_values($usage->map(fn (UploadUsage $row): array => [
            'install_id' => (string) $row->getAttribute('install_id'),
            'name' => $installs->get($row->getAttribute('install_id'))?->name,
            'account_id' => $installs->get($row->getAttribute('install_id'))?->account_id,
            'bytes' => (int) $row->getAttribute('bytes'),
            'requests' => (int) $row->getAttribute('requests'),
        ])->all());
    }

    private static function key(): string
    {
        return 'admin-ingest';
    }
}
