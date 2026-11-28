<?php

namespace App\Http\Middleware;

use App\Http\Requests\Api\PublicRequest;
use App\Models\Account;
use App\Models\Character;
use App\Models\Identity;
use App\Models\IdentityProvider;
use App\Support\ClientAddress;
use App\Support\Exceptions\ApiException;
use App\Support\PackedAnswer;
use App\Support\PublicApiCatalog;
use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * What every /api/v1/public answer shares: an API key (a personal access token with the `public:read` ability, sent as
 * a bearer token and never read from the query string), the rate limit per account, open CORS, and private HTTP caching
 * with an ETag, so no answer sits in a shared cache. A request with no key is `401 missing_key`. The origin keeps each
 * successful answer for `ANSWER_SECONDS`, keyed by the endpoint and the parameters PublicRequest validates, so keys
 * that ask the same thing (unknown query parameters, another spelling of a name) do not each run its queries.
 * Every answer says `X-Robots-Tag: noindex`, which keeps the JSON out of search results.
 */
class PublicApi
{
    /** Unknown, expired or under-powered bearer tokens an address may send a minute before it is refused unread. */
    public const int BAD_TOKENS_PER_MINUTE = 10;

    /** The request attribute that holds the id of the account whose API key was accepted. */
    public const string ACCOUNT_ATTRIBUTE = 'api-account';

    /** The request attribute that holds the requests the key's account had counted in the current rate-limit window. */
    public const string WINDOW_ATTRIBUTE = 'api-window-hits';

    public const int MAX_AGE = 300;

    /** The lifetime of an answer that names characters, so one made private or removed leaves it within a minute. */
    public const int NAMED_MAX_AGE = 30;

    public const int ANSWER_SECONDS = 30;

    private const int STATEMENT_TIMEOUT_MS = 10000;

    private const int LAST_USED_EVERY_SECONDS = 3600;

    public function __construct(private readonly RateLimiter $limiter) {}

    public function handle(Request $request, Closure $next): Response
    {
        $endpoint = self::endpointName($request);
        $address = ClientAddress::bucket($request);
        try {
            [$key, $max] = $this->budget($request, $address);
        } catch (ApiException $refusal) {
            $limited = $refusal->status === Response::HTTP_TOO_MANY_REQUESTS;

            return $this->headers($request, $endpoint, self::refusal($refusal), $limited ? (int) config('scarlet.rate.public.token') : null, 0);
        }

        if ($this->limiter->tooManyAttempts($key, $max)) {
            $request->attributes->set(self::WINDOW_ATTRIBUTE, $this->limiter->attempts($key));

            return $this->headers($request, $endpoint, self::refusal(ApiException::rateLimited($this->limiter->availableIn($key))), $max, 0);
        }
        $request->attributes->set(self::WINDOW_ATTRIBUTE, $this->limiter->hit($key, 60));

        $response = $this->answer($request, $endpoint, $next);

        return $this->headers($request, $endpoint, $response, $max, $this->limiter->remaining($key, $max));
    }

    /** The cache lifetime of an endpoint's answers. */
    public static function maxAge(?string $endpoint): int
    {
        return in_array($endpoint, PublicApiCatalog::NAMES_CHARACTERS, true) ? self::NAMED_MAX_AGE : self::MAX_AGE;
    }

    /**
     * @return array{0: string, 1: int} the limiter key and the requests allowed per minute
     *
     * @throws ApiException when there is no bearer token, it is unknown, expired or lacks the ability, or the address sent too many such tokens
     */
    private function budget(Request $request, string $address): array
    {
        $bearer = $request->bearerToken();
        if ($bearer === null) {
            throw ApiException::missingKey();
        }

        $refused = 'api-public-bad-token:'.$address;
        if ($this->limiter->tooManyAttempts($refused, self::BAD_TOKENS_PER_MINUTE)) {
            throw ApiException::rateLimited($this->limiter->availableIn($refused));
        }
        $token = PersonalAccessToken::findToken($bearer);
        if ($token === null || ! $token->can(Account::API_ABILITY) || ($token->expires_at !== null && $token->expires_at->isPast()) || ! self::battlenetAccount((int) $token->tokenable_id)) {
            $this->limiter->hit($refused, 60);
            throw ApiException::invalidToken();
        }
        if ($token->last_used_at === null || $token->last_used_at->diffInSeconds(now()) >= self::LAST_USED_EVERY_SECONDS) {
            $token->forceFill(['last_used_at' => now()])->save();
        }
        $request->attributes->set(self::ACCOUNT_ATTRIBUTE, (int) $token->tokenable_id);

        return ['api-public-account:'.$token->tokenable_id, (int) config('scarlet.rate.public.token')];
    }

    /** One lookup on the unique (account_id, provider) index: a key counts only while its account signs in with Battle.net. */
    private static function battlenetAccount(int $account): bool
    {
        return Identity::query()->where('account_id', $account)->where('provider', IdentityProvider::Battlenet)->exists();
    }

    private function answer(Request $request, ?string $endpoint, Closure $next): Response
    {
        $key = $endpoint === null ? null : self::answerKey($request, $endpoint);
        $body = $key === null ? null : PackedAnswer::unpack(Cache::get($key));
        if ($body !== null) {
            return response($body, Response::HTTP_OK, ['Content-Type' => 'application/json']);
        }

        DB::select('select set_config(?, ?, false)', ['statement_timeout', (string) self::STATEMENT_TIMEOUT_MS]);
        /** @var Response $response */
        $response = $next($request);
        $content = $response->getContent();
        if ($key === null || $response->getStatusCode() !== Response::HTTP_OK || ! is_string($content)) {
            return $response;
        }
        $packed = PackedAnswer::pack($content);
        if ($packed !== null) {
            Cache::put($key, $packed, self::ANSWER_SECONDS);
        }

        return $response;
    }

    /**
     * The answer's identity: the endpoint, its path parameters (a character name by its key, so every spelling of one
     * name is one answer) and the query parameters PublicRequest validates, so an invalid one is never served from
     * cache and any other parameter is left out.
     */
    private static function answerKey(Request $request, string $endpoint): string
    {
        $path = $request->route()?->parameters() ?? [];
        if (isset($path['name']) && is_string($path['name'])) {
            $path['name'] = Character::keyFor($path['name']);
        }
        $query = [];
        foreach (array_keys((new PublicRequest)->rules()) as $param) {
            $value = $request->query($param);
            if (is_string($value)) {
                $query[$param] = $value;
            }
        }
        ksort($query);

        return 'api-public-answer:'.sha1(serialize([$endpoint, $path, $query]));
    }

    private static function endpointName(Request $request): ?string
    {
        $route = $request->route()?->getName();

        return is_string($route) && str_starts_with($route, PublicApiCatalog::ROUTE_PREFIX) ? substr($route, strlen(PublicApiCatalog::ROUTE_PREFIX)) : null;
    }

    private static function refusal(ApiException $refusal): Response
    {
        $response = $refusal->render();
        $wait = $refusal->extra['retry_after'] ?? null;
        if (is_int($wait)) {
            $response->headers->set('Retry-After', (string) $wait);
        }

        return $response;
    }

    /** @param  int|null  $max  the limit of the key's account; null for an answer to a request with no accepted key, which has no limit to report */
    private function headers(Request $request, ?string $endpoint, Response $response, ?int $max, int $remaining): Response
    {
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Expose-Headers', 'ETag, Retry-After, X-RateLimit-Limit, X-RateLimit-Remaining');
        if ($max !== null) {
            $response->headers->set('X-RateLimit-Limit', (string) $max);
            $response->headers->set('X-RateLimit-Remaining', (string) max(0, $remaining));
        }
        $response->headers->set('Vary', 'Authorization');
        $response->headers->set('X-Robots-Tag', 'noindex');

        if (! $response->isSuccessful()) {
            $response->headers->set('Cache-Control', 'no-store');

            return $response;
        }

        $maxAge = self::maxAge($endpoint);
        $etag = '"'.md5((string) $response->getContent()).'"';
        $response->headers->set('ETag', $etag);
        $response->headers->set('Cache-Control', 'private, max-age='.$maxAge);
        if ($request->headers->get('If-None-Match') === $etag) {
            $response->setNotModified();
        }

        return $response;
    }
}
