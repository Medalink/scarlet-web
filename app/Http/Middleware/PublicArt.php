<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The art answers PHP still serves (routes/maps.php; on the droplet nginx serves the same files from disk where it
 * can, deploy/nginx/nginx.conf): no session is kept in redis and no cookie is set, so the edge may cache them by the
 * Cache-Control the controller sends (docs/HOSTING.md, "Edge cache"). It must run before StartSession, which
 * bootstrap/app.php arranges, as EdgeCacheable does for the pages.
 */
class PublicArt
{
    public function handle(Request $request, Closure $next): Response
    {
        config(['session.driver' => 'array']);
        $response = $next($request);

        $path = (string) config('session.path');
        $domain = config('session.domain');
        $domain = is_string($domain) ? $domain : null;
        foreach ([(string) config('session.cookie'), 'XSRF-TOKEN'] as $name) {
            $response->headers->removeCookie($name, $path, $domain);
        }

        return $response;
    }
}
