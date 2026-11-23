<?php

namespace App\Support;

use RuntimeException;

/**
 * What `scarlet-part decode` wrote for one session (docs/ROWS.md): fact rows per table, the dictionaries, the recipes and spells,
 * the talent layouts, the pseudonyms and the session's own summary row.
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
    public array $recipes = [];

    /** @var list<array<string, mixed>> */
    public array $spells = [];

    /** @var list<array<string, mixed>> */
    public array $zones = [];

    /** @var list<array<string, mixed>> */
    public array $pseudonyms = [];

    /** @var list<array<string, mixed>> */
    public array $talentTrees = [];

    /** @var list<array<string, mixed>> */
    public array $talentNodes = [];

    /** @var array<string, mixed>|null */
    public ?array $summary = null;

    /** A session whose parts are all gone: nothing to store, everything of it to delete. */
    public static function empty(): self
    {
        return new self;
    }

    /**
     * Read the decoder's output file a line at a time: the file, not a copy of it, is what the
     * worker holds.
     */
    public static function parseFile(string $file): self
    {
        $handle = fopen($file, 'rb');
        if ($handle === false) {
            throw new RuntimeException("cannot read {$file}");
        }
        try {
            $decoded = new self;
            while (($line = fgets($handle)) !== false) {
                $line = rtrim($line, "\r\n");
                if ($line === '') {
                    continue;
                }
                $decoded->add($line);
            }
        } finally {
            fclose($handle);
        }

        return $decoded;
    }

    private function add(string $line): void
    {
        /** @var array<string, mixed> $row */
        $row = json_decode($line, associative: true, flags: JSON_THROW_ON_ERROR);
        $table = (string) ($row['t'] ?? '');
        match ($table) {
            'names' => $this->names[] = $row,
            'items' => $this->items[] = $row,
            'recipes' => $this->recipes[] = $row,
            'spells' => $this->spells[] = $row,
            'zones' => $this->zones[] = $row,
            'pn' => $this->pseudonyms[] = $row,
            'talent_trees' => $this->talentTrees[] = $row,
            'talent_nodes' => $this->talentNodes[] = $row,
            'sessions' => $this->summary = $row,
            default => FactTable::tryFrom($table) !== null ? $this->facts[$table][] = $row : null,
        };
    }
}
