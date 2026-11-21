<?php

namespace App\Livewire\Account;

use App\Actions\Account\CreateApiKey;
use App\Actions\Account\DeleteApiKey;
use App\Actions\Account\ShowApiKeys;
use App\Livewire\Concerns\ActsForAccount;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The API keys module of /account. A new key is shown once: its text goes to the flash session, never to a
 * component property, so it is not part of the page state the browser sends back.
 */
class ApiKeys extends Component
{
    use ActsForAccount;

    public string $name = '';

    public ?string $notice = null;

    public function make(CreateApiKey $create): void
    {
        $this->validate(['name' => ['required', 'string', 'max:60']]);

        $key = $create->handle($this->account(), trim($this->name));
        if ($key === null) {
            $this->notice = 'You have the most keys you can hold. Delete one first.';

            return;
        }
        $this->notice = null;
        $this->name = '';
        session()->flash('new-api-key', $key);
    }

    public function delete(int $keyId, DeleteApiKey $delete): void
    {
        $delete->handle($this->account(), $keyId);
    }

    public function render(ShowApiKeys $keys): View
    {
        return view('livewire.account.api-keys', ['keys' => $keys->handle($this->account())]);
    }
}
