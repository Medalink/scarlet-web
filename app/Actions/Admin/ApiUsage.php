<?php

namespace App\Actions\Admin;

use App\Models\Account;
use App\Models\ApiCall;
use App\Models\ApiSurface;
use App\Models\Character;
use App\Models\Install;
use App\Models\UploadUsage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * The numbers of /admin/api from `api_calls`: requests per day and surface, distinct callers, and the endpoints,
 * addresses, accounts, characters and installs behind them over the days the table keeps (docs/DESIGN.md, Admin).
 */
class ApiUsage
{
    private const int SECONDS = 60;

    private const int TOP = 15;

    /** @return array<string, mixed> */
    public function handle(): array
    {
        /** @var array<string, mixed> */
        return Cache::remember(self::key(), self::SECONDS, fn (): array => [
            'days' => (int) config('scarlet.api_calls_days'),
            'totals' => $this->totals(),
            'daily' => $this->daily(),
            'endpoints' => $this->endpoints(),
            'addresses' => $this->addresses(),
            'accounts' => $this->accounts(),
            'characters' => $this->characters(),
            'installs' => $this->installs(),
            'at' => now()->toIso8601String(),
        ]);
    }

    /** @return array<string, int> */
    private function totals(): array
    {
        $row = self::callers()->toBase()->selectRaw(
            "coalesce(sum(requests), 0) as requests, coalesce(sum(refused), 0) as refused,
            coalesce(sum(requests) filter (where day = ?), 0) as today,
            count(distinct address) as addresses, count(distinct account_id) filter (where account_id > 0) as accounts,
            count(distinct install_id) filter (where install_id <> '') as installs,
            coalesce(sum(requests) filter (where surface = ? and account_id > 0), 0) as keyed",
            [now()->toDateString(), ApiSurface::Public->value],
        )->first();

        return [
            'requests' => (int) ($row->requests ?? 0),
            'refused' => (int) ($row->refused ?? 0),
            'today' => (int) ($row->today ?? 0),
            'addresses' => (int) ($row->addresses ?? 0),
            'accounts' => (int) ($row->accounts ?? 0),
            'installs' => (int) ($row->installs ?? 0),
            'keyed' => (int) ($row->keyed ?? 0),
            'keys' => PersonalAccessToken::query()->where('tokenable_type', (new Account)->getMorphClass())->count(),
        ];
    }

    /** @return array<string, array{anonymous: int, keyed: int, upload: int, addresses: int, accounts: int, installs: int}> per UTC day that has a row */
    private function daily(): array
    {
        $rows = self::callers()->toBase()->groupBy('day')->orderBy('day')->selectRaw(
            "day::text as day,
            coalesce(sum(requests) filter (where surface = ? and account_id = 0), 0) as anonymous,
            coalesce(sum(requests) filter (where surface = ? and account_id > 0), 0) as keyed,
            coalesce(sum(requests) filter (where surface = ?), 0) as upload,
            count(distinct address) as addresses, count(distinct account_id) filter (where account_id > 0) as accounts,
            count(distinct install_id) filter (where install_id <> '') as installs",
            [ApiSurface::Public->value, ApiSurface::Public->value, ApiSurface::Upload->value],
        )->get();

        $daily = [];
        foreach ($rows as $row) {
            $daily[(string) $row->day] = [
                'anonymous' => (int) $row->anonymous, 'keyed' => (int) $row->keyed, 'upload' => (int) $row->upload,
                'addresses' => (int) $row->addresses, 'accounts' => (int) $row->accounts, 'installs' => (int) $row->installs,
            ];
        }

        return $daily;
    }

    /** @return list<array<string, mixed>> surface, endpoint, requests, refused and callers of the busiest endpoints */
    private function endpoints(): array
    {
        return self::top(self::callers()->groupBy('surface', 'endpoint')
            ->selectRaw('surface, endpoint, sum(requests) as requests, sum(refused) as refused, count(distinct address) as callers'), ['requests', 'refused', 'callers']);
    }

    /** @return list<array<string, mixed>> address, requests, refused, endpoints, accounts (comma-separated ids), installs, first_at and last_at of the busiest addresses */
    private function addresses(): array
    {
        return self::top(self::callers()->groupBy('address')->selectRaw(
            "address, sum(requests) as requests, sum(refused) as refused, count(distinct endpoint) as endpoints,
            coalesce(string_agg(distinct account_id::text, ',') filter (where account_id > 0), '') as accounts, count(distinct install_id) filter (where install_id <> '') as installs,
            min(first_at)::text as first_at, max(last_at)::text as last_at",
        ), ['requests', 'refused', 'endpoints', 'installs']);
    }

    /** @return list<array{account_id: int, keys: int, requests: int, addresses: int, last_at: string|null}> accounts with an API key, by the keyed requests they made */
    private function accounts(): array
    {
        $calls = self::callers()->where('surface', ApiSurface::Public->value)->where('account_id', '>', 0)->toBase()->groupBy('account_id')
            ->selectRaw('account_id, sum(requests) as requests, count(distinct address) as addresses')->get()->keyBy('account_id');
        $keys = PersonalAccessToken::query()->where('tokenable_type', (new Account)->getMorphClass())->toBase()->groupBy('tokenable_id')
            ->selectRaw('tokenable_id, count(*) as n, max(last_used_at)::text as last_at')->get();

        $accounts = [];
        foreach ($keys as $key) {
            $call = $calls->get($key->tokenable_id);
            $accounts[] = [
                'account_id' => (int) $key->tokenable_id, 'keys' => (int) $key->n,
                'requests' => (int) ($call->requests ?? 0), 'addresses' => (int) ($call->addresses ?? 0), 'last_at' => $key->last_at,
            ];
        }
        usort($accounts, fn (array $a, array $b): int => [$b['requests'], $b['keys']] <=> [$a['requests'], $a['keys']]);

        return array_slice($accounts, 0, self::TOP);
    }

    /** @return list<array{name: string, known: bool, requests: int}> characters asked for by name through the public API; one deleted since is not known */
    private function characters(): array
    {
        $rows = self::top(ApiCall::query()->where('character_key', '<>', '')->groupBy('character_key')
            ->selectRaw('character_key, sum(requests) as requests'), ['requests']);
        $known = Character::query()->whereIn('name_key', array_column($rows, 'character_key'))->get(['name', 'name_key'])->keyBy('name_key');

        return array_map(fn (array $row): array => [
            'name' => (string) ($known->get($row['character_key'])->name ?? $row['character_key']),
            'known' => $known->has($row['character_key']),
            'requests' => $row['requests'],
        ], $rows);
    }

    /** @return list<array{install_id: string, name: string|null, account_id: int|null, characters: list<string>, requests: int, bytes: int, last_at: string}> */
    private function installs(): array
    {
        $rows = self::top(self::callers()->where('install_id', '<>', '')->groupBy('install_id')
            ->selectRaw('install_id, sum(requests) as requests, max(last_at)::text as last_at'), ['requests']);
        $ids = array_column($rows, 'install_id');
        $installs = Install::query()->whereIn('id', $ids)->with(['characters' => fn (BelongsToMany $characters) => $characters->orderBy('name')->select(['characters.id', 'characters.name'])])
            ->get(['id', 'name', 'account_id'])->keyBy('id');
        $bytes = UploadUsage::query()->whereIn('install_id', $ids)->where('day', '>=', now()->subDays((int) config('scarlet.api_calls_days'))->toDateString())
            ->toBase()->groupBy('install_id')->selectRaw('install_id, sum(bytes) as bytes')->pluck('bytes', 'install_id');

        return array_map(function (array $row) use ($installs, $bytes): array {
            $install = $installs->get($row['install_id']);

            return [
                'install_id' => $row['install_id'], 'name' => $install?->name, 'account_id' => $install?->account_id,
                'characters' => $install === null ? [] : array_values($install->characters->pluck('name')->map(fn (mixed $name): string => (string) $name)->all()),
                'requests' => $row['requests'],
                'bytes' => (int) ($bytes[$row['install_id']] ?? 0), 'last_at' => $row['last_at'],
            ];
        }, $rows);
    }

    /**
     * The TOP rows of a grouped query by requests, with the listed columns as integers.
     *
     * @param  Builder<ApiCall>  $query
     * @param  list<string>  $integers
     * @return list<array<string, mixed>>
     */
    private static function top(Builder $query, array $integers): array
    {
        /** @var list<array<string, mixed>> */
        return $query->orderByDesc(DB::raw('sum(requests)'))->limit(self::TOP)->toBase()->get()
            ->map(function (object $row) use ($integers): array {
                $values = (array) $row;
                foreach ($integers as $column) {
                    $values[$column] = (int) $values[$column];
                }

                return $values;
            })->values()->all();
    }

    /** @return Builder<ApiCall> the rows of callers, without the rows that count one character's lookups */
    private static function callers(): Builder
    {
        return ApiCall::query()->where('character_key', '');
    }

    private static function key(): string
    {
        return 'admin-api';
    }
}
