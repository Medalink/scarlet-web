<?php

namespace App\Livewire\Account;

use App\Actions\Account\CharacterSwitch;
use App\Actions\Account\SetCharacterSwitch;
use App\Actions\Account\ShowCharacters;
use App\Livewire\Concerns\ActsForAccount;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/** The Characters module of /account: the public switch of each character and, when public, its economy switch. */
class Characters extends Component
{
    use ActsForAccount;

    public function setPublic(int $characterId, bool $on, SetCharacterSwitch $set): void
    {
        $set->handle($this->account(), $characterId, CharacterSwitch::PublicPage, $on);
    }

    public function setEconomy(int $characterId, bool $on, SetCharacterSwitch $set): void
    {
        $set->handle($this->account(), $characterId, CharacterSwitch::Economy, $on);
    }

    public function render(ShowCharacters $characters): View
    {
        return view('livewire.account.characters', ['characters' => $characters->handle($this->account())]);
    }
}
