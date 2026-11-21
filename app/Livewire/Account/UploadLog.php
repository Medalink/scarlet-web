<?php

namespace App\Livewire\Account;

use App\Actions\Account\ShowUploadLog;
use App\Livewire\Concerns\ActsForAccount;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/** The Recent uploads module of /account: when each install last contacted the site and the newest uploads. */
class UploadLog extends Component
{
    use ActsForAccount;

    public function render(ShowUploadLog $log): View
    {
        return view('livewire.account.upload-log', ['log' => $log->handle($this->account())]);
    }
}
