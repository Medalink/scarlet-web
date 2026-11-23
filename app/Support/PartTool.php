<?php

namespace App\Support;

use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * The only class that runs the scarlet-part binary (docs/DESIGN.md, Decoding): it opens a request
 * batch, reads a zstd manifest, decodes a session's stored parts and lists the sharing groups.
 */
class PartTool
{
    private ?string $binary = null;

    /**
     * Open a batch (one zstd frame of length-prefixed parts) under the protocol limits: the parts
     * named by `wanted` go into $directory in their storage form, one `<id>.zst` each. The
     * child checks every part against `wanted` before it recompresses anything, so a batch of
     * parts the caller never stores costs the inflate alone. With `$room` the child recompresses nothing when the
     * accepted parts cost more than that (bytes plus lines plus `$partCharge` a part), so a request the quota will
     * refuse costs the inflate alone as well. The answer counts the work the request did: the inflated bytes, the
     * lines and the number of the parts it accepted.
     *
     * @param  array<string, int>  $wanted  the ids to store as keys, lower-case hex
     * @return array{parts: list<array{id: string, character: string, session: int, group: string, lines: int, raw_bytes: int}>, work: array{bytes: int, rows: int, parts: int}}
     */
    public function unbatch(string $batchFile, string $directory, array $wanted, ?int $maxInflated = null, ?int $room = null, int $partCharge = 0): array
    {
        $ids = $directory.DIRECTORY_SEPARATOR.'wanted';
        File::put($ids, implode("\n", array_keys($wanted)));
        $command = [$this->binary(), 'unbatch', '--stored', '--in', $batchFile, '--out-dir', $directory, '--accept', $ids];
        if ($maxInflated !== null) {
            $command[] = '--max-inflated';
            $command[] = (string) $maxInflated;
        }
        if ($room !== null) {
            array_push($command, '--room', (string) max(0, $room), '--part-charge', (string) $partCharge);
        }
        $run = Process::timeout(120)->run($command);
        if (! $run->successful()) {
            throw ApiException::invalidBatch($this->message($run->errorOutput()));
        }

        $parts = [];
        $bytes = 0;
        $rows = 0;
        $accepted = 0;
        foreach (explode("\n", trim($run->output())) as $line) {
            if ($line === '') {
                continue;
            }
            /** @var array{id: string, character: string, session: int, group: string, lines: int, raw_bytes: int} $part */
            $part = json_decode($line, associative: true, flags: JSON_THROW_ON_ERROR);
            $parts[] = $part;
            if (isset($wanted[$part['id']])) {
                $bytes += $part['raw_bytes'];
                $rows += $part['lines'];
                $accepted++;
            }
        }

        return ['parts' => $parts, 'work' => ['bytes' => $bytes, 'rows' => $rows, 'parts' => $accepted]];
    }

    /** The bytes of one zstd frame, under the protocol limits. */
    public function inflate(string $frame): string
    {
        $run = Process::timeout(60)->input($frame)->run([$this->binary(), 'inflate']);
        if (! $run->successful()) {
            throw ApiException::invalidBatch($this->message($run->errorOutput()));
        }

        return $run->output();
    }

    /**
     * Decode the stored parts of one session into newline-delimited JSON (docs/ROWS.md), written
     * to `$outputFile` by the child itself: the worker reads it back a line at a time instead of
     * holding the whole output.
     *
     * @param  list<string>  $bodies  the `body` column of each part
     */
    public function decode(array $bodies, string $outputFile): void
    {
        $input = '';
        foreach ($bodies as $body) {
            $input .= pack('V', strlen($body)).$body;
        }

        $run = Process::timeout((int) config('scarlet.ingest.timeout') - 30)
            ->input($input)
            ->run([$this->binary(), 'decode', '--stdin', '--out', $outputFile]);
        if (! $run->successful()) {
            throw new RuntimeException('scarlet-part decode failed: '.$this->message($run->errorOutput()));
        }
    }

    /**
     * Decode the stored imports parts of one day into newline-delimited JSON, one `imported` row per line.
     *
     * @param  list<string>  $bodies  the `body` column of each part
     * @return list<array<string, mixed>>
     */
    public function decodeImports(array $bodies): array
    {
        return $this->decodeRows($bodies, '--imports');
    }

    /**
     * The imported rows of `decodeImports`, written by the child to a work file and read back a row at a time, so
     * the worker never holds the day's rows. The file goes when the answer is dropped.
     *
     * @param  list<string>  $bodies  the `body` column of each part
     */
    public function importRows(array $bodies): RowFile
    {
        $input = '';
        foreach ($bodies as $body) {
            $input .= pack('V', strlen($body)).$body;
        }
        $file = rtrim((string) config('scarlet.work_dir'), '/\\').DIRECTORY_SEPARATOR.'scarlet-imports-'.uniqid().'.ndjson';

        $run = Process::timeout(60)->input($input)->run([$this->binary(), 'decode', '--stdin', '--imports', '--out', $file]);
        $rows = new RowFile($file);
        if (! $run->successful()) {
            throw new RuntimeException('scarlet-part decode --imports failed: '.$this->message($run->errorOutput()));
        }

        return $rows;
    }

    /**
     * Decode the `fishing` parts and the `addons` parts of one part key into newline-delimited JSON: a `fishing` row
     * per line and a `pn` row per pseudonym a fishing part explains.
     *
     * @param  list<string>  $bodies  the `body` column of each part
     * @return list<array<string, mixed>>
     */
    public function decodeFishing(array $bodies): array
    {
        return $this->decodeRows($bodies, '--fishing');
    }

    /**
     * Decode the `counts` parts of one day into newline-delimited JSON: a `claim` row per `YC` line and a closing `day`
     * row with the decoder version and the lines skipped.
     *
     * @param  list<string>  $bodies  the `body` column of each part
     * @return list<array<string, mixed>>
     */
    public function decodeCounts(array $bodies): array
    {
        return $this->decodeRows($bodies, '--counts');
    }

    /**
     * @param  list<string>  $bodies
     * @return list<array<string, mixed>>
     */
    private function decodeRows(array $bodies, string $mode): array
    {
        $input = '';
        foreach ($bodies as $body) {
            $input .= pack('V', strlen($body)).$body;
        }

        $run = Process::timeout(60)->input($input)->run([$this->binary(), 'decode', '--stdin', $mode]);
        if (! $run->successful()) {
            throw new RuntimeException("scarlet-part decode {$mode} failed: ".$this->message($run->errorOutput()));
        }

        $rows = [];
        foreach (explode("\n", $run->output()) as $line) {
            if ($line !== '') {
                /** @var array<string, mixed> $row */
                $row = json_decode($line, associative: true, flags: JSON_THROW_ON_ERROR);
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Decide what the claims of one character and kind count (docs/DESIGN.md, Reconcile): one verdict per claim, in
     * the order of the claims sent.
     *
     * @param  string  $input  the JSON `ReadReconcileInput` made
     * @return list<array{id: int, status: string, counted: array<string, int>, evidence: array<string, mixed>, level: int|null, zone: int|null}>
     */
    public function reconcile(string $input): array
    {
        $run = Process::timeout(120)->input($input)->run([$this->binary(), 'reconcile']);
        if (! $run->successful()) {
            throw new RuntimeException('scarlet-part reconcile failed: '.$this->message($run->errorOutput()));
        }

        /** @var array{claims: list<array{id: int, status: string, counted: array<string, int>, evidence: array<string, mixed>, level: int|null, zone: int|null}>} $answer */
        $answer = json_decode($run->output(), associative: true, flags: JSON_THROW_ON_ERROR);

        return $answer['claims'];
    }

    /** The rule version of the reconcile verdicts (`RECONCILE_VERSION` in part/src/reconcile.rs). */
    public function reconcileVersion(): int
    {
        $run = Process::timeout(30)->run([$this->binary(), 'reconcile-version']);
        if (! $run->successful()) {
            throw new RuntimeException('scarlet-part reconcile-version failed: '.$this->message($run->errorOutput()));
        }

        return (int) trim($run->output());
    }

    /**
     * The sharing groups in the order of docs/groups.tsv.
     *
     * @return list<array{key: string, label: string, switchable: bool, codes: list<string>}>
     */
    public function groups(): array
    {
        $run = Process::timeout(30)->run([$this->binary(), 'groups', '--json']);
        if (! $run->successful()) {
            throw new RuntimeException('scarlet-part groups failed: '.$this->message($run->errorOutput()));
        }

        /** @var list<array{key: string, label: string, switchable: bool, codes: list<string>}> */
        return json_decode($run->output(), associative: true, flags: JSON_THROW_ON_ERROR);
    }

    /** The rule version of the decoded rows (`DECODER_VERSION` in part/src/decode.rs). */
    public function decoderVersion(): int
    {
        $run = Process::timeout(30)->run([$this->binary(), 'decoder-version']);
        if (! $run->successful()) {
            throw new RuntimeException('scarlet-part decoder-version failed: '.$this->message($run->errorOutput()));
        }

        return (int) trim($run->output());
    }

    private function binary(): string
    {
        return $this->binary ??= (string) config('scarlet.part_bin');
    }

    private function message(string $stderr): string
    {
        $last = trim(strrchr(trim($stderr), "\n") ?: trim($stderr));

        return str_starts_with($last, 'scarlet-part: ') ? substr($last, strlen('scarlet-part: ')) : $last;
    }
}
