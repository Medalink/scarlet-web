<?php

namespace App\Livewire\Account;

use App\Actions\Account\ApiKeyOutcome;
use App\Actions\Account\CreateApiKey;
use App\Actions\Account\DeleteApiKey;
use App\Actions\Account\ShowApiKeys;
use App\Livewire\Concerns\ActsForAccount;
use App\Models\Account;
use App\Models\IdentityProvider;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The API keys module of /account. A new key is shown once: its text goes to the flash session, never to a
 * component property, so it is not part of the page state the browser sends back. Only an account with a Battle.net
 * sign-in can make a key; any other sees one line that links Battle.net.
 */
class ApiKeys extends Component
{
    use ActsForAccount;

    public string $name = '';

    public int $days = Account::API_KEY_DAYS;

    public ?string $notice = null;

    public function make(CreateApiKey $create): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:60'],
            'days' => ['required', 'integer', 'between:1,'.Account::API_KEY_MAX_DAYS],
        ]);

        $result = $create->handle($this->account(), trim($this->name), $this->days);
        $this->notice = match ($result->outcome) {
            ApiKeyOutcome::Created => null,
            ApiKeyOutcome::TooMany => 'You have the most keys you can hold. Delete one first.',
            ApiKeyOutcome::NoBattlenet => 'Link Battle.net to make a key.',
            ApiKeyOutcome::BadLifetime => 'A key lasts 1 to '.Account::API_KEY_MAX_DAYS.' days.',
        };
        if ($result->key === null) {
            return;
        }
        $this->name = '';
        $this->days = Account::API_KEY_DAYS;
        session()->flash('new-api-key', $result->key);
    }

    public function delete(int $keyId, DeleteApiKey $delete): void
    {
        $delete->handle($this->account(), $keyId);
    }

    public function render(ShowApiKeys $keys): View
    {
        $account = $this->account();

        return view('livewire.account.api-keys', [
            'keys' => $keys->handle($account),
            'battlenet' => $account->hasBattlenet(),
            'provider' => IdentityProvider::Battlenet,
            'choices' => Account::API_KEY_CHOICES,
            'development' => IdentityProvider::developmentAllowed(),
        ]);
    }
}
