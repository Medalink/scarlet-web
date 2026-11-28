<?php

namespace App\Livewire\Account;

use App\Actions\Account\CharacterSwitch;
use App\Actions\Account\SetCharacterSwitch;
use App\Actions\Account\ShowCharacters;
use App\Livewire\Concerns\ActsForAccount;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The characters of the account, as cards on /account or as rows with their switches' states on /account/privacy.
 * Either way each character has a modal with its three switches (public page, gold and trades, session pages), and
 * each switch saves when it changes.
 */
class Characters extends Component
{
    use ActsForAccount;

    public const string CARDS = 'cards';

    public const string PRIVACY = 'privacy';

    #[Locked]
    public string $mode = self::CARDS;

    public function setPublic(int $characterId, bool $on, SetCharacterSwitch $set): void
    {
        $set->handle($this->account(), $characterId, CharacterSwitch::PublicPage, $on);
    }

    public function setEconomy(int $characterId, bool $on, SetCharacterSwitch $set): void
    {
        $set->handle($this->account(), $characterId, CharacterSwitch::Economy, $on);
    }

    public function setSessions(int $characterId, bool $on, SetCharacterSwitch $set): void
    {
        $set->handle($this->account(), $characterId, CharacterSwitch::Sessions, $on);
    }

    public function render(ShowCharacters $characters): View
    {
        return view('livewire.account.characters', ['characters' => $characters->handle($this->account())]);
    }
}
