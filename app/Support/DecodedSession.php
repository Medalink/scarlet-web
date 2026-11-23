<?php

namespace App\Support;

use Generator;
use RuntimeException;
use Throwable;

/**
 * What `scarlet-part decode` wrote for one session (docs/ROWS.md): fact rows per table, the dictionaries, the recipes and spells,
 * the talent layouts, the pseudonyms and the session's own summary row.
 */
final class DecodedSession
{
    /** @var array<string, string> the spool file of each fact table that has rows, by FactTable value */
    private array $spooled = [];

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
     * Read the decoder's output file a line at a time. Fact rows, the bulk of it, go to one spool file per table
     * beside the output and are read back by `rows()` a row at a time, so the worker holds the dictionary rows and
     * the session row but never every fact row; call `dispose()` when the store is done. A session with more
     * dictionary rows than `scarlet.ingest.max_dictionary_rows` is refused.
     */
    public static function parseFile(string $file): self
    {
        $handle = fopen($file, 'rb');
        if ($handle === false) {
            throw new RuntimeException("cannot read {$file}");
        }
        $decoded = new self;
        $spools = [];
        $max = (int) config('scarlet.ingest.max_dictionary_rows');
        try {
            while (($line = fgets($handle)) !== false) {
                $line = rtrim($line, "\r\n");
                if ($line === '') {
                    continue;
                }
                $table = self::tableOf($line);
                if ($table !== null && FactTable::tryFrom($table) !== null) {
                    $spools[$table] ??= self::openSpool($decoded, $file, $table);
                    fwrite($spools[$table], $line."\n");

                    continue;
                }
                $decoded->add($line);
                if ($decoded->dictionaryRows() > $max) {
                    throw new RuntimeException("the decode wrote more than {$max} dictionary rows");
                }
            }
        } catch (Throwable $e) {
            $decoded->dispose();

            throw $e;
        } finally {
            fclose($handle);
            array_map(fclose(...), $spools);
        }

        return $decoded;
    }

    /** @return Generator<int, array<string, mixed>> the rows of one fact table, read back from its spool file */
    public function rows(FactTable $table): Generator
    {
        $file = $this->spooled[$table->value] ?? null;
        $handle = $file === null ? false : fopen($file, 'rb');
        if ($handle === false) {
            return;
        }
        try {
            while (($line = fgets($handle)) !== false) {
                /** @var array<string, mixed> $row */
                $row = json_decode($line, associative: true, flags: JSON_THROW_ON_ERROR);
                yield $row;
            }
        } finally {
            fclose($handle);
        }
    }

    public function hasRows(FactTable $table): bool
    {
        return isset($this->spooled[$table->value]);
    }

    public function dispose(): void
    {
        foreach ($this->spooled as $file) {
            is_file($file) && unlink($file);
        }
        $this->spooled = [];
    }

    /** @return resource */
    private static function openSpool(self $decoded, string $file, string $table)
    {
        $path = $file.'.'.$table;
        $decoded->spooled[$table] = $path;
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException("cannot write {$path}");
        }

        return $handle;
    }

    private static function tableOf(string $line): ?string
    {
        if (! str_starts_with($line, '{"t":"')) {
            return null;
        }
        $end = strpos($line, '"', 6);

        return $end === false ? null : substr($line, 6, $end - 6);
    }

    private function dictionaryRows(): int
    {
        return count($this->names) + count($this->items) + count($this->recipes) + count($this->spells) + count($this->zones)
            + count($this->pseudonyms) + count($this->talentTrees) + count($this->talentNodes);
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
            default => null,
        };
    }
}
