<?php

namespace App\Actions\Upload;

use App\Jobs\IngestSession;
use App\Models\Character;
use App\Models\Install;
use App\Models\Part;
use App\Support\Database\BinaryValue;
use App\Support\Database\PartIds;
use App\Support\Exceptions\ApiException;
use App\Support\GroupRefusals;
use App\Support\PartClaims;
use App\Support\PartTool;
use App\Support\SharingGroups;
use App\Support\UploadQuota;
use App\Support\WantedParts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class StoreParts
{
    private const int ROWS_PER_INSERT = 100;

    public function __construct(
        private readonly PartTool $tool,
        private readonly ClaimCharacter $claim,
        private readonly GroupRefusals $refusals,
        private readonly SharingGroups $groups,
        private readonly UploadQuota $quota,
    ) {}

    /**
     * Step 3 of the upload protocol: open the batch, check every part against what the last manifest
     * asked for, store the new ones and queue one decode per session that got a part. Repeating a
     * request stores nothing and answers the same. The install claims every part of the request, new or already held.
     * The child unwraps only the parts the checks accept, so a batch of strangers costs the
     * inflate alone, and the quota is billed for the work the request did.
     *
     * @return array{stored: list<string>, created: int}
     */
    public function handle(Install $install, string $batch): array
    {
        $directory = rtrim((string) config('scarlet.work_dir'), '/\\').DIRECTORY_SEPARATOR.'scarlet-batch-'.Str::random(16);
        File::ensureDirectoryExists($directory);

        try {
            File::put($directory.DIRECTORY_SEPARATOR.'batch.zst', $batch);
            $acceptable = $this->acceptable($install);
            $opened = $this->tool->unbatch(
                $directory.DIRECTORY_SEPARATOR.'batch.zst',
                $directory,
                $acceptable,
                (int) config('scarlet.upload.max_inflated_bytes'),
            );
            $characters = $this->check($install, $opened['parts'], $acceptable);

            return $this->store($install, $opened['parts'], $characters, $directory, $opened['work']);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    /**
     * What the last manifest asked for, and what is already stored (a repeated request is a
     * no-op), per character: the ids the request may add.
     *
     * @return array<string, int> keyed by lower-case hex id
     */
    private function acceptable(Install $install): array
    {
        $acceptable = [];
        foreach ($install->characters as $character) {
            $acceptable += WantedParts::ids($install->id, $character->id) + array_flip(PartIds::held($character->id));
        }

        return $acceptable;
    }

    /**
     * @param  list<array{id: string, character: string, session: int, group: string, lines: int, raw_bytes: int}>  $parts
     * @param  array<string, int>  $acceptable
     * @return array<string, Character> by lower-case name
     */
    private function check(Install $install, array $parts, array $acceptable): array
    {
        $refused = $this->refusals->among($install, array_values(array_unique(array_column($parts, 'group'))));
        if ($refused !== []) {
            throw ApiException::groupRefused($refused);
        }

        $sessionMin = (int) config('scarlet.ingest.session_min');
        $sessionMax = time() + (int) config('scarlet.ingest.session_future');
        foreach ($parts as $part) {
            if ($part['session'] < $sessionMin || $part['session'] > $sessionMax) {
                throw ApiException::invalidRequest("part {$part['id']} has a session outside the accepted window.", ['parts.'.$part['id'] => ["A session between {$sessionMin} and {$sessionMax} (epoch seconds)."]]);
            }
        }

        $characters = [];
        $known = [];
        foreach ($parts as $part) {
            if (! $this->groups->has($part['group'])) {
                throw ApiException::unknownGroup($part['group']);
            }
            $key = Character::keyFor($part['character']);
            $characters[$key] ??= $this->claim->handle($install, $part['character'], create: false) ?? throw ApiException::unknownCharacter($part['character']);
            $known[$key] ??= $this->owned($install, $characters[$key]->id);
            if (! isset($known[$key][$part['id']]) || ! isset($acceptable[$part['id']])) {
                throw ApiException::partNotRequested($part['id']);
            }
        }

        return $characters;
    }

    /** @return array<string, int> the character's own accepted ids, keyed by lower-case hex */
    private function owned(Install $install, int $characterId): array
    {
        return WantedParts::ids($install->id, $characterId) + array_flip(PartIds::held($characterId));
    }

    /**
     * @param  list<array{id: string, character: string, session: int, group: string, lines: int, raw_bytes: int}>  $parts
     * @param  array<string, Character>  $characters
     * @param  array{bytes: int, rows: int}  $work  the inflated bytes and rows the request carries
     * @return array{stored: list<string>, created: int}
     */
    private function store(Install $install, array $parts, array $characters, string $directory, array $work): array
    {
        $held = [];
        foreach ($characters as $character) {
            $held += array_flip(PartIds::held($character->id));
        }
        $new = array_values(array_filter($parts, fn (array $part): bool => ! isset($held[$part['id']])));

        if ($new !== []) {
            $this->quota->assertRoom($install, $work['bytes'], $work['rows']);

            DB::transaction(function () use ($install, $new, $characters, $directory, $work): void {
                foreach (array_chunk($new, self::ROWS_PER_INSERT) as $chunk) {
                    Part::query()->insertOrIgnore(array_map(fn (array $part): array => [
                        'id' => BinaryValue::bind((string) hex2bin($part['id'])),
                        'character_id' => $characters[Character::keyFor($part['character'])]->id,
                        'session_start' => $part['session'],
                        'group_key' => $part['group'],
                        'lines' => $part['lines'],
                        'raw_bytes' => $part['raw_bytes'],
                        'body' => BinaryValue::bind((string) file_get_contents($directory.DIRECTORY_SEPARATOR.$part['id'].'.zst')),
                    ], $chunk));
                }
                $this->quota->record($install, $work['bytes'], $work['rows']);
            });

            $sessions = [];
            foreach ($new as $part) {
                $characterId = $characters[Character::keyFor($part['character'])]->id;
                $sessions[$characterId.':'.$part['session']] = [$characterId, $part['session']];
            }
            foreach ($sessions as [$characterId, $session]) {
                IngestSession::dispatch($characterId, $session);
            }
        }

        PartClaims::add($install, array_column($parts, 'id'));

        return ['stored' => array_column($parts, 'id'), 'created' => count($new)];
    }
}
