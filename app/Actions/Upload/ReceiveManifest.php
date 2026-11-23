<?php

namespace App\Actions\Upload;

use App\Jobs\IngestSession;
use App\Models\Install;
use App\Models\Part;
use App\Models\PartClaim;
use App\Support\Database\PartIds;
use App\Support\Exceptions\ApiException;
use App\Support\GroupRefusals;
use App\Support\Manifest;
use App\Support\PartClaims;
use App\Support\SharingGroups;
use App\Support\WantedParts;
use Illuminate\Support\Facades\DB;

class ReceiveManifest
{
    private const int IDS_PER_QUERY = 5000;

    public function __construct(
        private readonly ClaimCharacter $claim,
        private readonly GroupRefusals $refusals,
        private readonly SharingGroups $groups,
    ) {}

    /**
     * Step 2 of the upload protocol: answer with the part ids the server needs. The install claims the listed
     * parts the server already holds, and drops its claim on the parts in the groups that are on that the manifest
     * no longer lists; a part nobody claims any more is deleted. Parts another install of the character claims stay.
     * Parts of a group the app has switched off stay unless the app removed the group (DELETE /upload/groups/{key}).
     *
     * The answer lists the first `scarlet.upload.max_wanted_ids` of the ids the server lacks; `remaining` counts the
     * rest, which the next manifest of the install lists once these are stored.
     *
     * @return array{need: list<string>, remaining: int, present: int, removed: int}
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

        $stored = array_flip(PartIds::held($character->id, $manifest->groups));
        $claimed = array_flip(PartIds::held($character->id, $manifest->groups, $install));
        $need = array_values(array_filter(array_keys($manifest->parts), fn (string $id): bool => ! isset($stored[$id])));
        $asked = array_slice($need, 0, WantedParts::limit());
        WantedParts::remember($install->id, $character->id, $asked);

        $listed = array_values(array_filter(array_keys($manifest->parts), fn (string $id): bool => isset($stored[$id]) && ! isset($claimed[$id])));
        $dropped = array_values(array_filter(array_keys($claimed), fn (string $id): bool => ! isset($manifest->parts[$id])));
        [$removed, $sessions] = $this->settle($install, $character->id, $listed, $dropped);

        foreach ($sessions as $session) {
            IngestSession::dispatch($character->id, $session);
        }

        return ['need' => $asked, 'remaining' => count($need) - count($asked), 'present' => count($manifest->parts) - count($need), 'removed' => $removed];
    }

    /**
     * @param  list<string>  $listed  stored parts the install now claims
     * @param  list<string>  $dropped  parts the install no longer lists
     * @return array{int, list<int>} the parts deleted and the sessions that lost a part
     */
    private function settle(Install $install, int $characterId, array $listed, array $dropped): array
    {
        if ($listed === [] && $dropped === []) {
            return [0, []];
        }

        return DB::transaction(function () use ($install, $characterId, $listed, $dropped): array {
            PartClaims::add($install, $listed);

            $removed = 0;
            $sessions = [];
            foreach (array_chunk($dropped, self::IDS_PER_QUERY) as $chunk) {
                $found = PartIds::whereIn(Part::query()->where('character_id', $characterId), $chunk);
                PartClaim::query()->where('install_id', $install->id)->whereIn('part_id', (clone $found)->select('parts.id'))->delete();
                $unclaimed = $found->whereDoesntHave('claims');
                $sessions = [...$sessions, ...(clone $unclaimed)->distinct()->pluck('session_start')->map(fn ($s): int => (int) $s)->all()];
                $removed += $unclaimed->delete();
            }

            return [$removed, array_values(array_unique($sessions))];
        });
    }
}
