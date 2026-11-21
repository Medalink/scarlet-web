<?php

namespace App\Livewire;

use App\Actions\Release\DismissUpdates;
use App\Actions\Release\MarkUpdatesRead;
use App\Actions\Release\ShowUpdates;
use App\Livewire\Concerns\ActsForAccount;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/** The strip under the header that tells a signed-in account what changed since it last read the release notes. */
class UpdatesBanner extends Component
{
    use ActsForAccount;

    public function dismiss(DismissUpdates $dismiss): void
    {
        $dismiss->handle($this->account());
    }

    public function markRead(MarkUpdatesRead $mark): void
    {
        $mark->handle($this->account());
    }

    public function render(ShowUpdates $updates): View
    {
        return view('livewire.updates-banner', ['feed' => $updates->handle($this->account())]);
    }
}
