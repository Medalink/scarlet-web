<?php

namespace App\Support;

/**
 * What `scarlet-part decode` wrote for one session (docs/ROWS.md): fact rows per table, the dictionaries,
 * the pseudonyms and the session's own summary row.
 */
final class DecodedSession
{
    /** @var array<string, list<array<string, mixed>>> fact rows by FactTable value */
    public array $facts = [];

    /** @var list<array<string, mixed>> */
    public array $names = [];

    /** @var list<array<string, mixed>> */
    public array $items = [];

    /** @var list<array<string, mixed>> */
    public array $zones = [];

    /** @var list<array<string, mixed>> */
    public array $pseudonyms = [];

    /** @var array<string, mixed>|null */
    public ?array $summary = null;

    /** A session whose parts are all gone: nothing to store, everything of it to delete. */
    public static function empty(): self
    {
        return new self;
    }

    public static function parse(string $ndjson): self
    {
        $decoded = new self;
        foreach (explode("\n", $ndjson) as $line) {
            if ($line === '') {
                continue;
            }
            /** @var array<string, mixed> $row */
            $row = json_decode($line, associative: true, flags: JSON_THROW_ON_ERROR);
            $table = (string) ($row['t'] ?? '');
            match ($table) {
                'names' => $decoded->names[] = $row,
                'items' => $decoded->items[] = $row,
                'zones' => $decoded->zones[] = $row,
                'pn' => $decoded->pseudonyms[] = $row,
                'sessions' => $decoded->summary = $row,
                default => FactTable::tryFrom($table) !== null ? $decoded->facts[$table][] = $row : null,
            };
        }

        return $decoded;
    }
}
