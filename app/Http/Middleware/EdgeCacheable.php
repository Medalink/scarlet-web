<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * The public pages for a visitor who is not signed in: no session is kept, no cookie is set and the answer may be
 * cached at the edge for `scarlet.edge.page_seconds`. The same answer is also kept in the cache under a key that
 * keeps only the query parameters the pages read, so a junk parameter (`?x=1`) that bypasses the edge finds the
 * origin answer ready and runs no query. A request that carries a session or remember cookie, or an Authorization
 * header, goes the ordinary way and is never cached. Cloudflare bypasses its cache for the same cookies
 * (deploy/cloudflare.mjs). It must run before StartSession, which bootstrap/app.php arranges.
 */
class EdgeCacheable
{
    private const string REMEMBER_COOKIE_PREFIX = 'remember_web_';

    private const string CACHE_KEY = 'page:';

    /** The query parameters any public page reads; every other parameter is dropped from the cache key. */
    private const array QUERY = ['a', 'b', 'class', 'cursor', 'kind', 'layer', 'level', 'marks', 'max', 'min', 'only', 'q', 'quality', 'rule', 'ruleset', 'server', 'sessions', 'sort', 'source', 'v', 'view', 'zone'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! self::anonymous($request)) {
            return $next($request);
        }

        $key = self::CACHE_KEY.md5($request->getHost().' '.$request->getPathInfo().'?'.http_build_query(self::kept($request)));
        $kept = self::restored(Cache::get($key));
        if ($kept) {
            return $kept;
        }

        config(['session.driver' => 'array']);
        $response = $next($request);

        $path = (string) config('session.path');
        $domain = config('session.domain');
        $domain = is_string($domain) ? $domain : null;
        foreach ([(string) config('session.cookie'), 'XSRF-TOKEN'] as $name) {
            $response->headers->removeCookie($name, $path, $domain);
        }
        if ($request->isMethod(Request::METHOD_GET) && $response->getStatusCode() === Response::HTTP_OK && $response->headers->getCookies() === []) {
            $seconds = (int) config('scarlet.edge.page_seconds');
            $stale = (int) config('scarlet.rollups.read_stale_seconds');
            $response->headers->set('Cache-Control', 'public, max-age=0, s-maxage='.$seconds.', stale-while-revalidate='.$stale);
            self::keep($key, $response, $seconds);
        }

        return $response;
    }

    /** Keeps the body and headers of an answer, which serialise; the response object holds the view and its closures and does not. */
    private static function keep(string $key, Response $response, int $seconds): void
    {
        $content = $response->getContent();
        if (! is_string($content)) {
            return;
        }
        $headers = $response->headers->allPreserveCaseWithoutCookies();
        unset($headers['Date']);
        Cache::put($key, ['content' => $content, 'headers' => $headers], $seconds);
    }

    /** The answer `keep()` stored, or null when the cache holds none. */
    private static function restored(mixed $kept): ?Response
    {
        if (! is_array($kept) || ! is_string($kept['content'] ?? null) || ! is_array($kept['headers'] ?? null)) {
            return null;
        }

        return new HttpResponse($kept['content'], Response::HTTP_OK, $kept['headers']);
    }

    /**
     * The query parameters of the request that any public page reads, sorted so the key is stable.
     *
     * @return array<string, mixed>
     */
    private static function kept(Request $request): array
    {
        $query = array_intersect_key($request->query(), array_flip(self::QUERY));
        ksort($query);

        return $query;
    }

    /** A read from a visitor with no session, no remember cookie and no credentials. */
    private static function anonymous(Request $request): bool
    {
        if (! $request->isMethodSafe() || $request->headers->has('Authorization') || $request->cookies->has((string) config('session.cookie'))) {
            return false;
        }
        foreach (array_keys($request->cookies->all()) as $name) {
            if (str_starts_with((string) $name, self::REMEMBER_COOKIE_PREFIX)) {
                return false;
            }
        }

        return true;
    }
}
