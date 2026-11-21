<?php

namespace App\Http\Middleware;

use App\Models\Install;
use App\Support\Exceptions\ApiException;
use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel's rate limiter with the budgets of config('scarlet.rate'): `registration` and `ip` count per
 * client address, `install` per signed install (so it runs after VerifyInstallSignature).
 */
class RateLimitRequests
{
    public const string REGISTRATION = 'registration';

    public const string IP = 'ip';

    public const string INSTALL = 'install';

    public function __construct(private readonly RateLimiter $limiter) {}

    public function handle(Request $request, Closure $next, string $bucket): Response
    {
        [$key, $max, $seconds] = $this->budget($request, $bucket);

        if ($this->limiter->tooManyAttempts($key, $max)) {
            throw ApiException::rateLimited($this->limiter->availableIn($key));
        }
        $this->limiter->hit($key, $seconds);

        return $next($request);
    }

    /** @return array{0: string, 1: int, 2: int} the limiter key, the attempts allowed and the window in seconds */
    private function budget(Request $request, string $bucket): array
    {
        if ($bucket === self::REGISTRATION) {
            return ['api-registration:'.$request->ip(), (int) config('scarlet.rate.registration.per_ip'), (int) config('scarlet.rate.registration.seconds')];
        }

        $seconds = (int) config('scarlet.rate.upload.seconds');
        if ($bucket === self::INSTALL) {
            /** @var Install $install */
            $install = $request->attributes->get(VerifyInstallSignature::ATTRIBUTE);

            return ['api-upload-install:'.$install->id, (int) config('scarlet.rate.upload.per_install'), $seconds];
        }

        return ['api-upload-ip:'.$request->ip(), (int) config('scarlet.rate.upload.per_ip'), $seconds];
    }
}
