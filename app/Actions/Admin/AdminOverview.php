<?php

namespace App\Actions\Admin;

use App\Models\Account;
use App\Models\Character;
use App\Models\Install;
use App\Models\Part;
use App\Models\PlaySession;
use App\Models\Ruleset;
use App\Models\UploadUsage;
use App\Support\Database\RowEstimate;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * The numbers of /admin: totals of accounts, installs, characters and play, and what each UTC day of the last
 * `DAYS` added (docs/DESIGN.md, Admin). Sessions and parts are counted by the planner's estimate, the rest exactly.
 */
class AdminOverview
{
    public const int DAYS = 90;

    private const int SECONDS = 60;

    /** @return array<string, mixed> */
    public function handle(): array
    {
        /** @var array<string, mixed> */
        return Cache::remember(self::key(), self::SECONDS, fn (): array => [
            'totals' => $this->totals(),
            'daily' => $this->daily(),
            'active' => $this->active(),
            'classes' => $this->characterSplit('class', DB::raw("coalesce(class, '') as v")),
            'factions' => $this->characterSplit('faction', DB::raw("coalesce(faction, '') as v")),
            'levels' => $this->levels(),
            'rulesets' => $this->rulesets(),
            'at' => now()->toIso8601String(),
        ]);
    }

    /** @return array<string, int> */
    private function totals(): array
    {
        $installs = Install::query()->toBase()
            ->selectRaw('count(*) as total, count(account_id) as linked, count(revoked_at) as revoked')->first();
        $characters = Character::query()->toBase()
            ->selectRaw('count(*) as total, count(*) filter (where is_public) as public, coalesce(sum(played_seconds), 0) as played')->first();

        return [
            'accounts' => Account::query()->count(),
            'accounts_with_keys' => PersonalAccessToken::query()->where('tokenable_type', (new Account)->getMorphClass())->distinct()->count('tokenable_id'),
            'installs' => (int) ($installs->total ?? 0),
            'installs_linked' => (int) ($installs->linked ?? 0),
            'installs_revoked' => (int) ($installs->revoked ?? 0),
            'characters' => (int) ($characters->total ?? 0),
            'characters_public' => (int) ($characters->public ?? 0),
            'played_seconds' => (int) ($characters->played ?? 0),
            'sessions' => RowEstimate::of('sessions'),
            'parts' => RowEstimate::of('parts'),
            'database_bytes' => (int) (DB::selectOne('select pg_database_size(current_database()) as bytes')->bytes ?? 0),
        ];
    }

    /** @return array<string, array<string, int>> per series, the value of each UTC day that has one */
    private function daily(): array
    {
        $since = now()->startOfDay()->subDays(self::DAYS);
        $usage = UploadUsage::query()->where('day', '>=', $since->toDateString())->toBase()
            ->groupBy('day')->selectRaw('day::text as day, sum(bytes) as bytes, count(*) as installs')->get();

        return [
            'accounts' => self::perDay(Account::query()->where('created_at', '>=', $since), DB::raw("(created_at at time zone 'UTC')::date::text as day")),
            'installs' => self::perDay(Install::query()->where('created_at', '>=', $since), DB::raw("(created_at at time zone 'UTC')::date::text as day")),
            'characters' => self::perDay(Character::query()->where('first_seen_at', '>=', $since), DB::raw("(first_seen_at at time zone 'UTC')::date::text as day")),
            'sessions' => self::perDay(PlaySession::query()->where('decoded_at', '>=', $since), DB::raw("(decoded_at at time zone 'UTC')::date::text as day")),
            'parts' => self::perDay(Part::query()->where('created_at', '>=', $since), DB::raw("(created_at at time zone 'UTC')::date::text as day")),
            'upload_bytes' => $usage->mapWithKeys(fn (object $row): array => [(string) $row->day => (int) $row->bytes])->all(),
            'active_installs' => $usage->mapWithKeys(fn (object $row): array => [(string) $row->day => (int) $row->installs])->all(),
        ];
    }

    /**
     * @param  Builder<covariant Model>  $query
     * @return array<string, int>
     */
    private static function perDay(Builder $query, Expression $day): array
    {
        return self::counts($query->toBase()->select($day)->selectRaw('count(*) as n')->groupByRaw('1'), 'day');
    }

    /** @return array{day: int, week: int, month: int} installs that reached the server within each span */
    private function active(): array
    {
        $row = Install::query()->toBase()->selectRaw(
            'count(*) filter (where last_seen_at >= ?) as day, count(*) filter (where last_seen_at >= ?) as week, count(*) filter (where last_seen_at >= ?) as month',
            [now()->subDay(), now()->subDays(7), now()->subDays(30)],
        )->first();

        return ['day' => (int) ($row->day ?? 0), 'week' => (int) ($row->week ?? 0), 'month' => (int) ($row->month ?? 0)];
    }

    /** @return array<string, int> characters per value of the selected `v`, unknown ones under '' */
    private function characterSplit(string $column, Expression $value): array
    {
        return self::counts(Character::query()->toBase()->groupBy($column)->select($value)->selectRaw('count(*) as n')->orderByDesc('n'), 'v');
    }

    /** @return array<string, int> */
    private static function counts(QueryBuilder $query, string $key): array
    {
        return $query->pluck('n', $key)->mapWithKeys(fn (mixed $n, mixed $k): array => [(string) $k => (int) $n])->all();
    }

    /** @return array<int, int> characters per ten levels (0 is 1 to 9, 6 is level 60 and up), unknown levels left out */
    private function levels(): array
    {
        return Character::query()->whereNotNull('level')->toBase()->groupByRaw('1')->orderByRaw('1')
            ->selectRaw('least(level / 10, 6) as bracket, count(*) as n')
            ->pluck('n', 'bracket')->mapWithKeys(fn (mixed $n, mixed $bracket): array => [(int) $bracket => (int) $n])->all();
    }

    /** @return array<string, int> characters with a session under each ruleset name */
    private function rulesets(): array
    {
        $perRuleset = PlaySession::query()->toBase()->groupBy('ruleset_id')
            ->selectRaw('ruleset_id, count(distinct character_id) as n')->pluck('n', 'ruleset_id');
        $byLabel = [];
        foreach (Ruleset::query()->whereIn('id', $perRuleset->keys())->get() as $ruleset) {
            $byLabel[$ruleset->label()] = ($byLabel[$ruleset->label()] ?? 0) + (int) $perRuleset[$ruleset->id];
        }
        arsort($byLabel);

        return $byLabel;
    }

    private static function key(): string
    {
        return 'admin-overview';
    }
}
