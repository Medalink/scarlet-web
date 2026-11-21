<?php

namespace App\Livewire\Account;

use App\Actions\Account\RemoveGroupFromServer;
use App\Actions\Account\SetGroupShared;
use App\Actions\Account\ShowSharing;
use App\Livewire\Concerns\ActsForAccount;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/** The What you share module of /account: one row per sharing group, a refusal when it is off, and its removal. */
class Sharing extends Component
{
    use ActsForAccount;

    public ?string $notice = null;

    public function setShared(string $group, bool $shared, SetGroupShared $set): void
    {
        $set->handle($this->account(), $group, $shared);
        $this->notice = null;
    }

    public function remove(string $group, RemoveGroupFromServer $remove): void
    {
        $removed = $remove->handle($this->account(), $group);
        $this->notice = "Removed {$removed['parts_removed']} parts from the server.";
        $this->dispatch('account-changed');
    }

    public function render(ShowSharing $sharing): View
    {
        return view('livewire.account.sharing', ['groups' => $sharing->handle($this->account())]);
    }
}
