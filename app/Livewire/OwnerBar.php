<?php

namespace App\Livewire;

use App\Actions\Account\CharacterSwitch;
use App\Actions\Account\SetCharacterSwitch;
use App\Actions\Account\ShowOtherCharacters;
use App\Actions\Account\ShowOwnedCharacter;
use App\Livewire\Concerns\ActsForAccount;
use App\View\Format;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The bar only a character's owner sees, with the public switch. A character page drops it in with
 * `<livewire:owner-bar :character-id="$character->id" />`; the id is locked and every request checks it
 * against the signed-in account.
 */
class OwnerBar extends Component
{
    use ActsForAccount;

    #[Locked]
    public int $characterId = 0;

    public function mount(int $characterId, ShowOwnedCharacter $owned): void
    {
        $owned->handle($this->account(), $characterId);
        $this->characterId = $characterId;
    }

    public function setPublic(bool $on, SetCharacterSwitch $set): void
    {
        $set->handle($this->account(), $this->characterId, CharacterSwitch::PublicPage, $on);
    }

    public function render(ShowOwnedCharacter $owned, ShowOtherCharacters $others): View
    {
        $character = $owned->handle($this->account(), $this->characterId);
        $facts = [$character->show_economy ? 'Gold and trades shown to visitors' : 'Gold and trades hidden from visitors'];
        $facts[] = $character->last_seen_at === null
            ? 'Nothing uploaded yet'
            : 'Last upload '.$character->last_seen_at->diffForHumans().', '.Format::count($character->sessions_decoded, 'session');

        $visitor = $character->is_public ? '/c/'.rawurlencode($character->name).'?as=visitor' : null;

        return view('livewire.owner-bar', ['public' => $character->is_public, 'visitor' => $visitor, 'facts' => $facts, 'others' => $others->handle($this->account(), $this->characterId)]);
    }
}
