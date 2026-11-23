<?php

namespace App\Actions\Admin;

use App\Actions\Release\ShowAppDownload;
use App\Models\Install;
use App\Models\PlaySession;
use Illuminate\Support\Facades\Cache;

/**
 * The numbers of /admin/clients: which app builds and systems the installs run, from the User-Agent each install last
 * signed with (`installs.client`), the addon versions the uploads came from, and the installs seen most recently
 * (docs/DESIGN.md, Admin).
 */
class ClientVersions
{
    public const int DAYS = 30;

    private const int WEEK = 7;

    private const int RECENT = 25;

    private const int AGENTS = 200;

    private const int SECONDS = 60;

    public function __construct(private readonly ShowAppDownload $download) {}

    /** @return array<string, mixed> */
    public function handle(): array
    {
        /** @var array<string, mixed> */
        return Cache::remember(self::key(), self::SECONDS, fn (): array => [
            'latest' => $this->download->handle()['version'],
            'totals' => $this->totals(),
            'clients' => $this->clients(),
            'addons' => $this->addons(),
            'recent' => $this->recent(),
            'at' => now()->toIso8601String(),
        ]);
    }

    /** @return array{installs: int, month: int, week: int, revoked: int, linked: int} */
    private function totals(): array
    {
        $row = Install::query()->toBase()->selectRaw(
            'count(*) as installs, count(*) filter (where revoked_at is null and last_seen_at >= ?) as month,
            count(*) filter (where revoked_at is null and last_seen_at >= ?) as week,
            count(*) filter (where revoked_at is not null) as revoked, count(*) filter (where account_id is not null) as linked',
            [now()->subDays(self::DAYS), now()->subDays(self::WEEK)],
        )->first();

        return [
            'installs' => (int) ($row->installs ?? 0),
            'month' => (int) ($row->month ?? 0),
            'week' => (int) ($row->week ?? 0),
            'revoked' => (int) ($row->revoked ?? 0),
            'linked' => (int) ($row->linked ?? 0),
        ];
    }

    /** @return list<array{client: string, month: int, week: int}> installs not revoked and seen within DAYS days, per User-Agent ('' when none is known yet), the AGENTS most common, since a client names its own */
    private function clients(): array
    {
        return array_values(Install::query()->whereNull('revoked_at')->where('last_seen_at', '>=', now()->subDays(self::DAYS))->toBase()
            ->groupBy('client')->orderByDesc('month')->limit(self::AGENTS)
            ->selectRaw("coalesce(client, '') as client, count(*) as month, count(*) filter (where last_seen_at >= ?) as week", [now()->subDays(self::WEEK)])
            ->get()->map(fn (object $row): array => ['client' => (string) $row->client, 'month' => (int) $row->month, 'week' => (int) $row->week])->all());
    }

    /** @return list<array{version: string, characters: int, sessions: int}> addon versions of the sessions decoded within DAYS days */
    private function addons(): array
    {
        return array_values(PlaySession::query()->where('decoded_at', '>=', now()->subDays(self::DAYS))->toBase()
            ->groupByRaw('1')->orderByDesc('characters')->limit(12)
            ->selectRaw("coalesce(extra->>'addon_version', '') as version, count(distinct character_id) as characters, count(*) as sessions")
            ->get()->map(fn (object $row): array => ['version' => (string) $row->version, 'characters' => (int) $row->characters, 'sessions' => (int) $row->sessions])->all());
    }

    /** @return list<array{id: string, name: string|null, account_id: int|null, client: string|null, characters: int, revoked: bool, created_at: string|null, last_seen_at: string|null}> */
    private function recent(): array
    {
        return array_values(Install::query()->withCount('characters')->orderByRaw('last_seen_at desc nulls last')->limit(self::RECENT)
            ->get(['id', 'name', 'account_id', 'client', 'revoked_at', 'created_at', 'last_seen_at'])
            ->map(fn (Install $install): array => [
                'id' => $install->id,
                'name' => $install->name,
                'account_id' => $install->account_id,
                'client' => $install->client,
                'characters' => (int) $install->getAttribute('characters_count'),
                'revoked' => $install->revoked_at !== null,
                'created_at' => $install->created_at->toIso8601String(),
                'last_seen_at' => $install->last_seen_at?->toIso8601String(),
            ])->all());
    }

    private static function key(): string
    {
        return 'admin-clients';
    }
}
