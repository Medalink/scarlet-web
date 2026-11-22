<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Caps every statement a page request runs at `scarlet.page.statement_seconds`, so a slow aggregate cannot hold a
 * php-fpm worker: the pages read the same tables as the public API, which caps its statements the same way
 * (docs/DESIGN.md, Security).
 */
class PageStatementTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        DB::statement('select set_config(?, ?, false)', [
            'statement_timeout',
            (string) ((int) config('scarlet.page.statement_seconds') * 1000),
        ]);

        return $next($request);
    }
}
