<?php

namespace App\Actions\Upload;

use App\Jobs\IngestSession;
use App\Models\Install;
use App\Models\Part;
use App\Support\Database\PartIds;
use App\Support\Exceptions\ApiException;
use App\Support\GroupRefusals;
use App\Support\Manifest;
use App\Support\SharingGroups;
use App\Support\WantedParts;
use Illuminate\Support\Facades\DB;

class ReceiveManifest
{
    public function __construct(
        private readonly ClaimCharacter $claim,
        private readonly GroupRefusals $refusals,
        private readonly SharingGroups $groups,
    ) {}

    /**
     * Step 2 of the upload protocol: answer with the part ids the server needs, and delete the parts it
     * holds in the groups that are on that the manifest no longer lists. Parts of a group the app has
     * switched off stay unless the app removed the group (DELETE /upload/groups/{key}).
     *
     * @return array{need: list<string>, present: int, removed: int}
     */
    public function handle(Install $install, Manifest $manifest): array
    {
        foreach ($manifest->groups as $group) {
            if (! $this->groups->has($group)) {
                throw ApiException::unknownGroup($group);
            }
        }
        foreach ($manifest->parts as $part) {
            if (! in_array($part['group'], $manifest->groups, strict: true)) {
                throw ApiException::invalidRequest('A part is in a group that is not on.', ['group' => [$part['group']]]);
            }
        }
        $refused = $this->refusals->among($install, $manifest->groups);
        if ($refused !== []) {
            throw ApiException::groupRefused($refused);
        }

        $character = $this->claim->handle($install, $manifest->character, create: true);
        if ($character === null) {
            throw ApiException::unknownCharacter($manifest->character);
        }

        $held = PartIds::held($character->id, $manifest->groups);
        $heldSet = array_flip($held);
        $need = array_values(array_filter(array_keys($manifest->parts), fn (string $id): bool => ! isset($heldSet[$id])));
        WantedParts::remember($install->id, $character->id, $need);

        $gone = array_values(array_filter($held, fn (string $id): bool => ! isset($manifest->parts[$id])));
        $sessions = $gone === [] ? [] : $this->remove($character->id, $gone);

        foreach ($sessions as $session) {
            IngestSession::dispatch($character->id, $session);
        }

        return ['need' => $need, 'present' => count($held) - count($gone), 'removed' => count($gone)];
    }

    /**
     * @param  list<string>  $ids
     * @return list<int> the sessions that lost a part
     */
    private function remove(int $characterId, array $ids): array
    {
        return DB::transaction(function () use ($characterId, $ids): array {
            $sessions = [];
            foreach (array_chunk($ids, 5000) as $chunk) {
                $found = PartIds::whereIn(Part::query()->where('character_id', $characterId), $chunk);
                $sessions = [...$sessions, ...(clone $found)->distinct()->pluck('session_start')->map(fn ($s): int => (int) $s)->all()];
                $found->delete();
            }

            return array_values(array_unique($sessions));
        });
    }
}
