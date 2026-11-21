<?php

namespace App\Support;

use App\Support\Exceptions\ApiException;
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
     * go into $directory in their storage form, one `<id>.zst` each.
     *
     * @return list<array{id: string, character: string, session: int, group: string, lines: int, raw_bytes: int}>
     */
    public function unbatch(string $batchFile, string $directory): array
    {
        $run = Process::timeout(120)->run([$this->binary(), 'unbatch', '--stored', '--in', $batchFile, '--out-dir', $directory]);
        if (! $run->successful()) {
            throw ApiException::invalidBatch($this->message($run->errorOutput()));
        }

        $parts = [];
        foreach (explode("\n", trim($run->output())) as $line) {
            if ($line === '') {
                continue;
            }
            /** @var array{id: string, character: string, session: int, group: string, lines: int, raw_bytes: int} $part */
            $part = json_decode($line, associative: true, flags: JSON_THROW_ON_ERROR);
            $parts[] = $part;
        }

        return $parts;
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
     * Decode the stored parts of one session into newline-delimited JSON (docs/ROWS.md).
     *
     * @param  list<string>  $bodies  the `body` column of each part
     */
    public function decode(array $bodies): string
    {
        $input = '';
        foreach ($bodies as $body) {
            $input .= pack('V', strlen($body)).$body;
        }

        $run = Process::timeout((int) config('scarlet.ingest.timeout') - 30)->input($input)->run([$this->binary(), 'decode', '--stdin']);
        if (! $run->successful()) {
            throw new RuntimeException('scarlet-part decode failed: '.$this->message($run->errorOutput()));
        }

        return $run->output();
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
