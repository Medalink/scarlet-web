<?php

namespace App\Actions\Admin;

use App\Http\Middleware\PublicApi;
use App\Models\ApiCall;
use App\Models\Install;
use App\Models\UploadUsage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The numbers of /admin/limits: the budgets in force, refusals and 429s per day, the callers that hit a limit, the
 * busiest callers today, uploads against the daily byte cap and installs against the character cap, from `api_calls`,
 * `upload_usage` and `install_character` (docs/DESIGN.md, Admin).
 */
class ApiLimits
{
    private const int TOP = 15;

    private const int SECONDS = 30;

    /** @return array<string, mixed> */
    public function handle(): array
    {
        /** @var array<string, mixed> */
        return Cache::remember(self::key(), self::SECONDS, fn (): array => [
            'days' => (int) config('scarlet.api_calls_days'),
            'budgets' => self::budgets(),
            'today' => $this->today(),
            'daily' => $this->daily(),
            'limited' => $this->limited(),
            'busiest' => $this->busiest(),
            'uploads' => $this->uploads(),
            'characters' => $this->characters(),
            'at' => now()->toIso8601String(),
        ]);
    }

    /** @return array<string, int> the limits config('scarlet') and PublicApi set */
    private static function budgets(): array
    {
        return [
            'public_token' => (int) config('scarlet.rate.public.token'),
            'bad_tokens' => PublicApi::BAD_TOKENS_PER_MINUTE,
            'upload_install' => (int) config('scarlet.rate.upload.per_install'),
            'upload_ip' => (int) config('scarlet.rate.upload.per_ip'),
            'upload_seconds' => (int) config('scarlet.rate.upload.seconds'),
            'registration' => (int) config('scarlet.rate.registration.per_ip'),
            'registration_seconds' => (int) config('scarlet.rate.registration.seconds'),
            'page' => (int) config('scarlet.page.per_address'),
            'page_seconds' => (int) config('scarlet.page.seconds'),
            'bytes_per_day' => (int) config('scarlet.upload.max_bytes_per_install_per_day'),
            'characters_per_install' => (int) config('scarlet.upload.max_characters_per_install'),
        ];
    }

    /** @return array{requests: int, refused: int, limited: int, addresses: int, limited_addresses: int} */
    private function today(): array
    {
        $row = self::callers()->where('day', now()->toDateString())->toBase()->selectRaw(
            'coalesce(sum(requests), 0) as requests, coalesce(sum(refused), 0) as refused, coalesce(sum(limited), 0) as limited,
            count(distinct address) as addresses, count(distinct address) filter (where limited > 0) as limited_addresses',
        )->first();

        return [
            'requests' => (int) ($row->requests ?? 0),
            'refused' => (int) ($row->refused ?? 0),
            'limited' => (int) ($row->limited ?? 0),
            'addresses' => (int) ($row->addresses ?? 0),
            'limited_addresses' => (int) ($row->limited_addresses ?? 0),
        ];
    }

    /** @return array<string, array{requests: int, refused: int, limited: int}> per UTC day that has a row */
    private function daily(): array
    {
        $daily = [];
        foreach (self::callers()->toBase()->groupBy('day')->selectRaw('day::text as day, sum(requests) as requests, sum(refused) as refused, sum(limited) as limited')->get() as $row) {
            $daily[(string) $row->day] = ['requests' => (int) $row->requests, 'refused' => (int) $row->refused, 'limited' => (int) $row->limited];
        }

        return $daily;
    }

    /** @return list<array<string, mixed>> address, limited, refused, requests, endpoints limited, accounts, installs and last_at of the addresses answered 429 most */
    private function limited(): array
    {
        return self::rows(self::callers()->groupBy('address')->havingRaw('sum(limited) > 0')->orderByDesc(DB::raw('sum(limited)'))->limit(self::TOP)->selectRaw(
            "address, sum(limited) as limited, sum(refused) as refused, sum(requests) as requests,
            count(distinct endpoint) filter (where limited > 0) as endpoints,
            coalesce(string_agg(distinct account_id::text, ',') filter (where account_id > 0), '') as accounts,
            count(distinct install_id) filter (where install_id <> '') as installs, max(last_at)::text as last_at",
        ), ['limited', 'refused', 'requests', 'endpoints', 'installs']);
    }

    /** @return list<array<string, mixed>> address, requests, limited, refused, first_at and last_at today of the addresses with the most requests today */
    private function busiest(): array
    {
        return self::rows(self::callers()->where('day', now()->toDateString())->groupBy('address')->orderByDesc(DB::raw('sum(requests)'))->limit(self::TOP)->selectRaw(
            "address, sum(requests) as requests, sum(limited) as limited, sum(refused) as refused,
            coalesce(string_agg(distinct account_id::text, ',') filter (where account_id > 0), '') as accounts,
            min(first_at)::text as first_at, max(last_at)::text as last_at",
        ), ['requests', 'limited', 'refused']);
    }

    /** @return list<array{install_id: string, name: string|null, account_id: int|null, bytes: int, requests: int}> today's upload bytes per install, largest first */
    private function uploads(): array
    {
        $usage = UploadUsage::query()->where('day', now()->toDateString())->orderByDesc('bytes')->limit(self::TOP)->get(['install_id', 'bytes', 'requests']);
        $installs = Install::query()->whereIn('id', $usage->pluck('install_id'))->get(['id', 'name', 'account_id'])->keyBy('id');

        return array_values($usage->map(fn (UploadUsage $row): array => [
            'install_id' => (string) $row->install_id,
            'name' => $installs->get($row->install_id)?->name,
            'account_id' => $installs->get($row->install_id)?->account_id,
            'bytes' => (int) $row->bytes,
            'requests' => (int) $row->requests,
        ])->all());
    }

    /** @return list<array{install_id: string, name: string|null, account_id: int|null, characters: int}> the installs that upload the most characters */
    private function characters(): array
    {
        return array_values(Install::query()->has('characters')->withCount('characters')->orderByDesc('characters_count')->limit(10)
            ->get(['id', 'name', 'account_id'])
            ->map(fn (Install $install): array => [
                'install_id' => $install->id,
                'name' => $install->name,
                'account_id' => $install->account_id,
                'characters' => (int) $install->getAttribute('characters_count'),
            ])->all());
    }

    /** @return Builder<ApiCall> the rows of callers, without the rows that count one character's lookups */
    private static function callers(): Builder
    {
        return ApiCall::query()->where('character_key', '');
    }

    /**
     * @param  Builder<ApiCall>  $query
     * @param  list<string>  $integers
     * @return list<array<string, mixed>>
     */
    private static function rows(Builder $query, array $integers): array
    {
        /** @var list<array<string, mixed>> */
        return $query->toBase()->get()->map(function (object $row) use ($integers): array {
            $values = (array) $row;
            foreach ($integers as $column) {
                $values[$column] = (int) $values[$column];
            }

            return $values;
        })->values()->all();
    }

    private static function key(): string
    {
        return 'admin-limits';
    }
}
