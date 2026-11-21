<?php

namespace App\Livewire\Account;

use App\Actions\Account\RevokeInstall;
use App\Actions\Account\ShowInstalls;
use App\Livewire\Concerns\ActsForAccount;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/** The Installs module of /account: when each was linked, its last upload, and Revoke. */
class Installs extends Component
{
    use ActsForAccount;

    public function revoke(string $installId, RevokeInstall $revoke): void
    {
        $revoke->handle($this->account(), $installId);
    }

    public function render(ShowInstalls $installs): View
    {
        return view('livewire.account.installs', ['installs' => $installs->handle($this->account())]);
    }
}
