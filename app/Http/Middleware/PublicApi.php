<?php

namespace App\Http\Middleware;

use App\Models\Account;
use App\Support\Exceptions\ApiException;
use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * What every /api/v1/public answer shares: the rate limit (per address, or per token when a personal access
 * token with the `public:read` ability is sent as a bearer token), open CORS, and HTTP caching with an ETag. An answer
 * to an anonymous caller may sit in a shared cache (Cloudflare) for `scarlet.edge.api_seconds`; one to a caller with
 * credentials is private.
 */
class PublicApi
{
    public const int ANONYMOUS_PER_MINUTE = 60;

    public const int TOKEN_PER_MINUTE = 600;

    public const int MAX_AGE = 300;

    public function __construct(private readonly RateLimiter $limiter) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            [$key, $max] = $this->budget($request);
        } catch (ApiException $refusal) {
            return $this->headers($request, $refusal->render(), self::ANONYMOUS_PER_MINUTE, 0);
        }

        if ($this->limiter->tooManyAttempts($key, $max)) {
            $wait = $this->limiter->availableIn($key);
            $response = ApiException::rateLimited($wait)->render();
            $response->headers->set('Retry-After', (string) $wait);

            return $this->headers($request, $response, $max, 0);
        }
        $this->limiter->hit($key, 60);

        $response = $next($request);

        return $this->headers($request, $response, $max, $this->limiter->remaining($key, $max));
    }

    /**
     * @return array{0: string, 1: int} the limiter key and the requests allowed per minute
     *
     * @throws ApiException when the bearer token is unknown, expired or lacks the ability
     */
    private function budget(Request $request): array
    {
        $bearer = $request->bearerToken();
        if ($bearer === null) {
            return ['api-public-ip:'.$request->ip(), self::ANONYMOUS_PER_MINUTE];
        }

        $token = PersonalAccessToken::findToken($bearer);
        if ($token === null || ! $token->can(Account::API_ABILITY) || ($token->expires_at !== null && $token->expires_at->isPast())) {
            throw ApiException::invalidToken();
        }

        return ['api-public-token:'.$token->getKey(), self::TOKEN_PER_MINUTE];
    }

    private function headers(Request $request, Response $response, int $max, int $remaining): Response
    {
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Expose-Headers', 'ETag, Retry-After, X-RateLimit-Limit, X-RateLimit-Remaining');
        $response->headers->set('X-RateLimit-Limit', (string) $max);
        $response->headers->set('X-RateLimit-Remaining', (string) max(0, $remaining));
        $response->headers->set('Vary', 'Authorization');

        if (! $response->isSuccessful()) {
            $response->headers->set('Cache-Control', 'no-store');

            return $response;
        }

        $etag = '"'.md5((string) $response->getContent()).'"';
        $response->headers->set('ETag', $etag);
        $response->headers->set('Cache-Control', $request->headers->has('Authorization')
            ? 'private, max-age='.self::MAX_AGE
            : 'public, max-age='.self::MAX_AGE.', s-maxage='.(int) config('scarlet.edge.api_seconds'));
        if ($request->headers->get('If-None-Match') === $etag) {
            $response->setNotModified();
        }

        return $response;
    }
}
