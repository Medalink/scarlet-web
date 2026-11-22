<?php

namespace App\Http\Middleware;

use App\Actions\Admin\CountApiCall;
use App\Models\ApiSurface;
use App\Models\Character;
use App\Models\Install;
use App\Support\ClientAddress;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Counts each request to /api/v1 that matched a route into `api_calls` once the answer is sent (docs/DESIGN.md, Admin).
 * It runs first in its route group, so refusals by the limits after it are counted too. The account comes from the API
 * key PublicApi accepted or from the install VerifyInstallSignature accepted; a character is kept only for a 200 or 304
 * answer, so names that do not exist add no rows. A refused request is one answered 400 or above, except 404. A failed
 * count is reported and changes nothing else, since the answer has already gone.
 */
class RecordApiCall
{
    private const string SURFACE = 'api-surface';

    public function __construct(private readonly CountApiCall $count) {}

    public function handle(Request $request, Closure $next, string $surface): Response
    {
        $request->attributes->set(self::SURFACE, ApiSurface::from($surface));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $surface = $request->attributes->get(self::SURFACE);
        $route = $request->route();
        if (! $surface instanceof ApiSurface || $route === null) {
            return;
        }

        $install = $request->attributes->get(VerifyInstallSignature::ATTRIBUTE);
        $accountId = $install instanceof Install ? (int) $install->account_id : (int) $request->attributes->get(PublicApi::ACCOUNT_ATTRIBUTE, 0);
        $name = $route->parameter('name');
        $status = $response->getStatusCode();
        $found = in_array($status, [Response::HTTP_OK, Response::HTTP_NOT_MODIFIED], true);

        try {
            $this->count->handle(
                $surface,
                $request->method().' '.$route->uri(),
                ClientAddress::bucket($request),
                $accountId,
                $install instanceof Install ? $install->id : '',
                $found && is_string($name) ? Character::keyFor($name) : '',
                $status >= Response::HTTP_BAD_REQUEST && $status !== Response::HTTP_NOT_FOUND,
            );
        } catch (QueryException $e) {
            report($e);
        }
    }
}
