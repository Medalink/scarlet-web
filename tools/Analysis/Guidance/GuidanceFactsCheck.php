<?php

namespace Scarlet\Analysis\Guidance;

final class GuidanceFactsCheck
{
    /**
     * Prose that names a Larastan level must name the one in phpstan.neon.
     *
     * @param  list<string>  $paths
     * @return list<string>
     */
    public static function check(array $paths, int $level): array
    {
        $violations = [];
        foreach ($paths as $path) {
            foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $index => $line) {
                if (preg_match('/larastan/i', $line) === 1 && preg_match('/level (\d+)/i', $line, $matches) === 1 && (int) $matches[1] !== $level) {
                    $violations[] = $path.':'.($index + 1).': '.trim($line).' contradicts the PHPStan level '.$level.' in phpstan.neon';
                }
            }
        }

        return $violations;
    }
}
