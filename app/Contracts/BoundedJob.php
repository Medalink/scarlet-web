<?php

namespace App\Contracts;

/**
 * A queued job that states its limits. Horizon kills a job at $timeout seconds, retries it
 * at most $tries times and the job stays under $memoryMb megabytes.
 */
interface BoundedJob
{
    // Implementations declare public int $timeout, public int $tries and public int $memoryMb.
}
