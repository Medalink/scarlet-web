<?php

namespace App\Support\Database;

use PDOException;
use Throwable;

final class Conflict
{
    /** SQLSTATE deadlock_detected and serialization_failure. */
    private const array STATES = ['40P01', '40001'];

    /**
     * Whether PostgreSQL aborted the work to break a deadlock or a serialization conflict with another transaction,
     * which says nothing about the rows and passes when the work runs again.
     */
    public static function lost(Throwable $e): bool
    {
        for ($cause = $e; $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof PDOException && in_array((string) $cause->getCode(), self::STATES, true)) {
                return true;
            }
        }

        return false;
    }
}
