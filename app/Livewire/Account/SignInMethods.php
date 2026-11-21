<?php

namespace App\Livewire\Account;

use App\Actions\Account\ShowSignInMethods;
use App\Actions\Auth\UnlinkIdentity;
use App\Actions\Auth\UnlinkOutcome;
use App\Livewire\Concerns\ActsForAccount;
use App\Models\IdentityProvider;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/** The Sign-in methods module of /account: which providers are linked, and unlinking one while another remains. */
class SignInMethods extends Component
{
    use ActsForAccount;

    public ?string $notice = null;

    public function unlink(string $provider, UnlinkIdentity $unlink): void
    {
        $choice = IdentityProvider::tryFrom($provider);
        abort_if($choice === null || ! $choice->isOAuth(), 404);

        $this->notice = match ($unlink->handle($this->account(), $choice)) {
            UnlinkOutcome::Unlinked => null,
            UnlinkOutcome::Last => "{$choice->label()} is the only way you sign in. Link another one first.",
            UnlinkOutcome::NotLinked => "{$choice->label()} is not linked.",
        };
    }

    public function render(ShowSignInMethods $methods): View
    {
        $linked = [];
        foreach ($methods->handle($this->account()) as $identity) {
            $linked[$identity->provider->value] = $identity;
        }

        return view('livewire.account.sign-in-methods', ['linked' => $linked, 'providers' => IdentityProvider::REAL]);
    }
}
