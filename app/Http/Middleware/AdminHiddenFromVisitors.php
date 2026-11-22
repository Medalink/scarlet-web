<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes a refused /admin request look like a path that does not exist (docs/DESIGN.md, Admin): 404 with no cookie. A
 * visitor with no session gets it before StartSession runs, so no session is kept; any other 404 from AdminOnly loses
 * the session and XSRF cookies StartSession added. It must run before StartSession, which bootstrap/app.php arranges.
 */
class AdminHiddenFromVisitors
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(EdgeCacheable::anonymous($request), 404);

        $response = $next($request);
        if ($response->getStatusCode() === Response::HTTP_NOT_FOUND) {
            $domain = config('session.domain');
            foreach ([(string) config('session.cookie'), 'XSRF-TOKEN'] as $name) {
                $response->headers->removeCookie($name, (string) config('session.path'), is_string($domain) ? $domain : null);
            }
        }

        return $response;
    }
}
