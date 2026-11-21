<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The public pages for a visitor who is not signed in: no session is kept, no cookie is set and the answer may be cached
 * at the edge for `scarlet.edge.page_seconds`. A request that carries a session or remember cookie, or an Authorization
 * header, goes the ordinary way and is never marked cacheable. Cloudflare bypasses its cache for the same cookies
 * (deploy/cloudflare.mjs). It must run before StartSession, which bootstrap/app.php arranges.
 */
class EdgeCacheable
{
    private const string REMEMBER_COOKIE_PREFIX = 'remember_web_';

    public function handle(Request $request, Closure $next): Response
    {
        if (! self::anonymous($request)) {
            return $next($request);
        }

        config(['session.driver' => 'array']);
        $response = $next($request);

        $path = (string) config('session.path');
        $domain = config('session.domain');
        $domain = is_string($domain) ? $domain : null;
        foreach ([(string) config('session.cookie'), 'XSRF-TOKEN'] as $name) {
            $response->headers->removeCookie($name, $path, $domain);
        }
        if ($response->getStatusCode() === Response::HTTP_OK && $response->headers->getCookies() === []) {
            $response->headers->set('Cache-Control', 'public, max-age=0, s-maxage='.(int) config('scarlet.edge.page_seconds'));
        }

        return $response;
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
