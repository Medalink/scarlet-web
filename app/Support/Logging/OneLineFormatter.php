<?php

namespace App\Support\Logging;

use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;
use Throwable;

/**
 * One line per log record, so the flow engine's error watch matches and fingerprints a record without its stack trace
 * (docs/HOSTING.md, "Error watch"): level, time, exception class, file without its line, message, then the frames after
 * ` | trace ` and the context after ` | ctx `. The watch fingerprints only what comes before those two.
 */
final class OneLineFormatter implements FormatterInterface
{
    private const MESSAGE_LIMIT = 300;

    private const CONTEXT_LIMIT = 400;

    private const FRAMES = 6;

    public function format(LogRecord $record): string
    {
        $thrown = $record->context['exception'] ?? null;
        $thrown = $thrown instanceof Throwable ? $thrown : null;

        $line = sprintf(
            'SCARLET-LOG level=%s at=%s class=%s file=%s msg=%s',
            $record->level->getName(),
            $record->datetime->format('Y-m-d\TH:i:sP'),
            $thrown === null ? '-' : $thrown::class,
            $thrown === null ? '-' : $this->relative($thrown->getFile()),
            $this->oneLine($record->message, self::MESSAGE_LIMIT),
        );

        if ($thrown !== null) {
            $line .= ' | trace '.$this->frames($thrown);
        }

        $context = array_diff_key($record->context, ['exception' => true]);
        if ($context !== []) {
            $line .= ' | ctx '.$this->oneLine((string) json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR), self::CONTEXT_LIMIT);
        }

        return $line."\n";
    }

    /** @param  array<int, LogRecord>  $records */
    public function formatBatch(array $records): string
    {
        return implode('', array_map($this->format(...), $records));
    }

    private function frames(Throwable $thrown): string
    {
        $frames = [$this->relative($thrown->getFile()).':'.$thrown->getLine()];
        foreach ($thrown->getTrace() as $frame) {
            $file = $frame['file'] ?? null;
            if ($file === null || str_contains($file, '/vendor/')) {
                continue;
            }
            $frames[] = $this->relative($file).':'.($frame['line'] ?? 0);
            if (count($frames) > self::FRAMES) {
                break;
            }
        }

        return implode(' < ', $frames);
    }

    private function relative(string $file): string
    {
        $base = base_path().DIRECTORY_SEPARATOR;

        return str_starts_with($file, $base) ? substr($file, strlen($base)) : $file;
    }

    private function oneLine(string $text, int $limit): string
    {
        return mb_strimwidth(trim((string) preg_replace('/\s+/', ' ', $text)), 0, $limit, '...');
    }
}
