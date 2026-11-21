<?php

namespace App\Livewire\Account;

use App\Actions\Account\RemoveAccountUploads;
use App\Actions\Account\ShowUploads;
use App\Livewire\Concerns\ActsForAccount;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/** The Delete everything you uploaded module of /account: a confirm step that names what goes. */
class DeleteUploads extends Component
{
    use ActsForAccount;

    public bool $confirming = false;

    public ?string $notice = null;

    public function ask(): void
    {
        $this->confirming = true;
        $this->notice = null;
    }

    public function cancel(): void
    {
        $this->confirming = false;
    }

    public function delete(RemoveAccountUploads $remove): void
    {
        $removed = $remove->handle($this->account());
        $this->confirming = false;
        $this->notice = "Removed {$removed['characters_removed']} characters and {$removed['parts_removed']} parts.";
        $this->dispatch('account-changed');
    }

    public function render(ShowUploads $uploads): View
    {
        return view('livewire.account.delete-uploads', ['uploads' => $uploads->handle($this->account())]);
    }
}
