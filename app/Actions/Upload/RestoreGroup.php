<?php

namespace App\Actions\Upload;

use App\Models\Install;
use App\Support\GroupRefusals;
use App\Support\SharingGroups;

class RestoreGroup
{
    public function __construct(
        private readonly SharingGroups $groups,
        private readonly GroupRefusals $refusals,
    ) {}

    /**
     * Accept a group again: the app turned it back on and will send its parts from the local history.
     *
     * @return array{group: string, refused: bool}
     */
    public function handle(Install $install, string $group): array
    {
        $this->groups->requireSwitchable($group);
        $this->refusals->clear($install, $group);

        return ['group' => $group, 'refused' => false];
    }
}
