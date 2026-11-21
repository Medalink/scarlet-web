<?php

namespace App\Livewire;

use App\Actions\Account\CharacterSwitch;
use App\Actions\Account\SetCharacterSwitch;
use App\Actions\Account\ShowOwnedCharacter;
use App\Livewire\Concerns\ActsForAccount;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
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

    public function render(ShowOwnedCharacter $owned): View
    {
        $character = $owned->handle($this->account(), $this->characterId);
        $facts = [$character->show_economy ? 'Gold and trades shown to visitors' : 'Gold and trades hidden from visitors'];
        $facts[] = $character->last_seen_at === null
            ? 'Nothing uploaded yet'
            : 'Last upload '.$character->last_seen_at->diffForHumans().', '.$character->sessions_decoded.' '.Str::plural('session', $character->sessions_decoded);

        return view('livewire.owner-bar', ['public' => $character->is_public, 'facts' => $facts]);
    }
}
