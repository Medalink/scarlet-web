<?php

namespace App\Http\Middleware;

use App\Models\Account;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets only an admin account through (docs/DESIGN.md, Admin) and keeps the answer out of every cache and index.
 * Anyone else gets 404, so the pages do not say they exist.
 */
class AdminOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $account = $request->user();
        abort_unless($account instanceof Account && $account->isAdmin(), 404);

        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }
}
