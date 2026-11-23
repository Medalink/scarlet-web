<?php

namespace Database\Seeders\Population;

use App\Actions\Upload\ReceiveManifest;
use App\Actions\Upload\StoreParts;
use App\Models\Character;
use App\Models\Install;
use App\Support\Database\BinaryValue;
use App\Support\Manifest;
use App\Support\PartTool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Uploads the recordings of one character the way the app does: the session lines become parts, one for each
 * sharing group, `scarlet-part batch` packs them, the manifest tells the server what the install holds and the
 * batches go to the same action the upload API calls, which queues one decode for each session that got a part.
 */
final class Recorder
{
    private const string HEADER = 'scarlet-part';

    /** @var array<string, string>|null line code => sharing group */
    private ?array $groups = null;

    public function __construct(
        private readonly PartTool $tool,
        private readonly ReceiveManifest $manifests,
        private readonly StoreParts $store,
    ) {}

    /**
     * Upload the sessions of a character that the server does not hold yet.
     *
     * @param  list<array{start: int, lines: list<array{0: int, 1: list<int|float|string>}>}>  $sessions
     */
    public function upload(Install $install, string $name, array $sessions): void
    {
        if ($sessions === []) {
            return;
        }
        $directory = rtrim((string) config('scarlet.work_dir'), '/\\').DIRECTORY_SEPARATOR.'scarlet-seed-'.md5($name);
        File::ensureDirectoryExists($directory);

        try {
            $new = $this->partTexts($name, $sessions);
            foreach ($new as $id => $part) {
                File::put($directory.DIRECTORY_SEPARATOR.$id.'.part', $part['text']);
            }
            $held = $this->held($name);
            $parts = $held + array_map(fn (array $part): array => $part['entry'], $new);
            $this->manifests->handle($install, new Manifest($name, array_values(array_unique(array_column($parts, 'group'))), $parts));

            $this->send($install, $directory);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    /** @param  list<array{start: int, lines: list<array{0: int, 1: list<int|float|string>}>}>  $sessions */
    private function partTexts(string $character, array $sessions): array
    {
        $parts = [];

        foreach ($sessions as $session) {
            $byGroup = [];

            foreach ($session['lines'] as [$ms, $fields]) {
                $byGroup[$this->groupOf((string) $fields[0])][] = [$ms, implode("\t", $fields)];
            }

            foreach ($byGroup as $group => $rows) {
                $text = implode("\t", [self::HEADER, 1, $character, $session['start'], $group])."\n";
                $before = 0;

                foreach ($rows as [$ms, $line]) {
                    $text .= ($ms - $before)."\t".$line."\n";
                    $before = $ms;
                }
                $id = hash('sha256', $text);
                $parts[$id] = [
                    'text' => $text,
                    'entry' => ['id' => $id, 'session' => $session['start'], 'group' => $group, 'lines' => count($rows), 'raw_bytes' => strlen($text)],
                ];
            }
        }

        return $parts;
    }

    /**
     * The parts the server holds for the character, which the manifest must list again or the install would drop them.
     *
     * @return array<string, array{id: string, session: int, group: string, lines: int, raw_bytes: int}>
     */
    private function held(string $name): array
    {
        $character = Character::query()->where('name_key', Character::keyFor($name))->first();

        if ($character === null) {
            return [];
        }
        $held = [];

        foreach (DB::table('parts')->where('character_id', $character->id)->get(['id', 'session_start', 'group_key', 'lines', 'raw_bytes']) as $row) {
            $id = bin2hex((string) BinaryValue::read($row->id));
            $held[$id] = ['id' => $id, 'session' => (int) $row->session_start, 'group' => (string) $row->group_key, 'lines' => (int) $row->lines, 'raw_bytes' => (int) $row->raw_bytes];
        }

        return $held;
    }

    /** Pack the part files into requests under the protocol limits and hand each to the upload action. */
    private function send(Install $install, string $directory): void
    {
        $out = $directory.DIRECTORY_SEPARATOR.'batch';
        $run = Process::timeout(300)->run([(string) config('scarlet.part_bin'), 'batch', '--parts-dir', $directory, '--out', $out, '--split']);

        if (! $run->successful()) {
            throw new RuntimeException('scarlet-part batch failed: '.$run->errorOutput());
        }

        foreach (glob($out.'.*') ?: [] as $file) {
            $this->store->handle($install, (string) file_get_contents($file));
        }
    }

    private function groupOf(string $code): string
    {
        if ($this->groups === null) {
            $this->groups = [];

            foreach ($this->tool->groups() as $group) {
                foreach ($group['codes'] as $one) {
                    $this->groups[$one] = $group['key'];
                }
            }
        }

        return $this->groups[$code] ?? throw new RuntimeException("The line code $code has no sharing group.");
    }
}
