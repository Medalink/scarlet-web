<?php

namespace App\Actions\Upload;

use App\Models\Install;
use App\Support\Exceptions\ApiException;
use App\Support\GroupRefusals;
use App\Support\PartDigest;
use App\Support\SharingGroups;

class CompareParts
{
    public function __construct(
        private readonly ClaimCharacter $claim,
        private readonly GroupRefusals $refusals,
        private readonly SharingGroups $groups,
    ) {}

    /**
     * Step 1 of the upload protocol: does the server hold exactly the parts the app holds for this
     * character in the groups that are on? Compared by count and by the XOR of the part ids.
     *
     * @param  list<string>  $groups
     * @return array{match: bool, count: int, digest: string}
     */
    public function handle(Install $install, string $character, array $groups, int $count, string $digest): array
    {
        foreach ($groups as $group) {
            if (! $this->groups->has($group)) {
                throw ApiException::unknownGroup($group);
            }
        }
        $refused = $this->refusals->among($install, $groups);
        if ($refused !== []) {
            throw ApiException::groupRefused($refused);
        }

        $held = $this->claim->handle($install, $character, create: false);
        $server = $held === null ? ['count' => 0, 'digest' => PartDigest::EMPTY] : PartDigest::of($held->id, $groups);

        return ['match' => $server['count'] === $count && $server['digest'] === $digest] + $server;
    }
}
