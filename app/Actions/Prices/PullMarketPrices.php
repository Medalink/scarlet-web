<?php

namespace App\Actions\Prices;

use App\Models\Market;
use App\Support\PriceTable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class PullMarketPrices
{
    private const int UPSERT_ROWS = 1000;

    /** Stop the run while this few calls are left in AHledger's rate window. */
    private const int RESERVE_CALLS = 5;

    private const int MARKET_LIST_TRIES = 3;

    private const int RETRY_PAUSE_MS = 3000;

    /**
     * Bring AHledger's prices up to date, gently: one call for the market list, then one conditional GET of each enabled
     * market's price table, a second apart. An unchanged table answers 304 and costs nothing; a new scan is folded
     * into that day's rows in one transaction with the market's scan time. A market the list no longer names is
     * disabled. The market list is tried three times, 3 seconds apart, when the host is unreachable or answers 429 or a
     * server error, and a list that still cannot be read is logged with its reason. A 429, a server error or an
     * unreachable host ends the run, and the next run tries again.
     *
     * @return array<string, string> the status of each market this run asked for, by market id
     */
    public function handle(): array
    {
        $markets = $this->syncMarkets();
        if ($markets === null) {
            return [];
        }

        $statuses = [];
        foreach ($markets as $i => $market) {
            if ($i > 0) {
                usleep((int) config('scarlet.ahledger.pause_ms') * 1000);
            }
            $response = $this->get('/v1/pricetable/'.$market->id, $market->etag);
            if ($response === null || $this->mustStop($response)) {
                $statuses[$market->id] = Market::STATUS_REFUSED;
                break;
            }
            $statuses[$market->id] = $this->store($market, $response);
            $left = $response->header('x-ratelimit-remaining');
            if ($left !== '' && (int) $left < self::RESERVE_CALLS) {
                break;
            }
        }

        return $statuses;
    }

    /** @return list<Market>|null the enabled markets, or null when the market list could not be read */
    private function syncMarkets(): ?array
    {
        $response = $this->get('/v1/markets', null, self::MARKET_LIST_TRIES);
        if ($response === null) {
            return null;
        }
        if (! $response->successful() || ! is_array($response->json('markets'))) {
            Log::warning('AHledger\'s market list was not usable.', ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 200)]);

            return null;
        }
        $regions = (array) config('scarlet.ahledger.regions');
        $rows = [];
        foreach ((array) $response->json('markets') as $m) {
            if (! is_array($m) || ($m['game'] ?? null) !== Market::GAME || ! is_string($m['id'] ?? null)) {
                continue;
            }
            $rows[] = [
                'id' => $m['id'],
                'ruleset' => (string) ($m['ruleset'] ?? ''),
                'faction' => (string) ($m['faction'] ?? ''),
                'region' => (string) ($m['region'] ?? ''),
                'label' => (string) ($m['label'] ?? $m['id']),
                'enabled' => in_array($m['region'] ?? null, $regions, true),
            ];
        }
        if ($rows === []) {
            Log::warning('AHledger\'s market list named no Forever market.');

            return null;
        }
        Market::query()->upsert($rows, ['id'], ['ruleset', 'faction', 'region', 'label', 'enabled']);
        Market::query()->whereNotIn('id', array_column($rows, 'id'))->update(['enabled' => false]);

        return array_values(Market::query()->where('enabled', true)->orderBy('id')->get()->all());
    }

    private function store(Market $market, Response $response): string
    {
        $market->polled_at = now();
        $market->status = $this->fold($market, $response);
        $market->save();

        return $market->status;
    }

    private function fold(Market $market, Response $response): string
    {
        if ($response->status() === 304) {
            return Market::STATUS_UNCHANGED;
        }
        if (! $response->successful()) {
            return 'http '.$response->status();
        }
        $table = PriceTable::parse($response->body(), $market->slug());
        if ($table === null) {
            return Market::STATUS_UNREADABLE;
        }
        $market->etag = $response->header('etag') ?: null;
        if ($market->scanned_at !== null && $table->scannedAt->lessThanOrEqualTo($market->scanned_at)) {
            return Market::STATUS_SAME_SCAN;
        }

        $day = $table->scannedAt->toDateString();
        $at = $table->scannedAt->toIso8601String();
        DB::transaction(function () use ($table, $market, $day, $at): void {
            foreach (array_chunk($table->rows, self::UPSERT_ROWS) as $chunk) {
                DB::table('market_price_days')->upsert(
                    array_map(fn (array $row): array => ['day' => $day, 'market_id' => $market->id, 'scanned_at' => $at, 'low' => $row['min_buyout'], ...$row], $chunk),
                    ['market_id', 'item_id', 'day'],
                    ['low' => DB::raw('least(market_price_days.low, excluded.low)'), 'scanned_at', 'min_buyout', 'median', 'median_7d', 'median_30d', 'quantity', 'sold_x10'],
                );
            }
            $market->scanned_at = $table->scannedAt;
            $market->save();
        });

        return Market::STATUS_STORED;
    }

    private function mustStop(Response $response): bool
    {
        return $response->status() === 429 || $response->serverError();
    }

    private function get(string $path, ?string $etag, int $tries = 1): ?Response
    {
        $client = $this->client()->withHeaders($etag === null ? [] : ['If-None-Match' => $etag]);
        if ($tries > 1) {
            $client = $client->retry(
                $tries,
                self::RETRY_PAUSE_MS,
                fn (Throwable $e): bool => $e instanceof ConnectionException || ($e instanceof RequestException && ($e->response->serverError() || $e->response->status() === 429)),
                throw: false,
            );
        }
        try {
            return $client->get($path);
        } catch (ConnectionException $e) {
            Log::warning('AHledger could not be reached.', ['path' => $path, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('scarlet.ahledger.base_url'))
            ->withUserAgent((string) config('scarlet.ahledger.user_agent'))
            ->timeout((int) config('scarlet.ahledger.timeout'));
    }
}
