<?php

namespace App\Http\Middleware;

use App\Models\Install;
use App\Support\ClientAddress;
use App\Support\Exceptions\ApiException;
use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel's rate limiter with the budgets of config('scarlet.rate'): `registration` and `ip` count per
 * address bucket (App\Support\ClientAddress), `install` per signed install (so it runs after VerifyInstallSignature),
 * and `page` per address bucket too — the pages answer with a plain refusal instead of an API error.
 */
class RateLimitRequests
{
    public const string REGISTRATION = 'registration';

    public const string IP = 'ip';

    public const string INSTALL = 'install';

    public const string PAGE = 'page';

    public function __construct(private readonly RateLimiter $limiter) {}

    public function handle(Request $request, Closure $next, string $bucket): Response
    {
        [$key, $max, $seconds] = $this->budget($request, $bucket);

        if ($this->limiter->tooManyAttempts($key, $max)) {
            $wait = $this->limiter->availableIn($key);
            if ($bucket === self::PAGE) {
                return response("Too many requests from your address. Try again in {$wait} seconds.", Response::HTTP_TOO_MANY_REQUESTS, ['Retry-After' => (string) $wait]);
            }

            throw ApiException::rateLimited($wait);
        }
        $this->limiter->hit($key, $seconds);

        return $next($request);
    }

    /** @return array{0: string, 1: int, 2: int} the limiter key, the attempts allowed and the window in seconds */
    private function budget(Request $request, string $bucket): array
    {
        if ($bucket === self::REGISTRATION) {
            return ['api-registration:'.ClientAddress::bucket($request), (int) config('scarlet.rate.registration.per_ip'), (int) config('scarlet.rate.registration.seconds')];
        }

        if ($bucket === self::PAGE) {
            return ['page:'.ClientAddress::bucket($request), (int) config('scarlet.rate.page.per_address'), (int) config('scarlet.rate.page.seconds')];
        }

        $seconds = (int) config('scarlet.rate.upload.seconds');
        if ($bucket === self::INSTALL) {
            /** @var Install $install */
            $install = $request->attributes->get(VerifyInstallSignature::ATTRIBUTE);

            return ['api-upload-install:'.$install->id, (int) config('scarlet.rate.upload.per_install'), $seconds];
        }

        return ['api-upload-ip:'.ClientAddress::bucket($request), (int) config('scarlet.rate.upload.per_ip'), $seconds];
    }
}
