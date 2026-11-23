<?php

namespace App\Livewire;

use App\Actions\Account\SetFeatPin;
use App\Actions\Feats\BuildCharacterFeats;
use App\Actions\Feats\ShowFeatCatalog;
use App\Livewire\Concerns\ActsForAccount;
use App\Support\Feat;
use App\View\Character\FeatsSection;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** The `#feats` section of /c/{name} for the character's owner, with a pin toggle on each earned feat. */
class CharacterFeats extends Component
{
    use ActsForAccount;

    #[Locked]
    public int $characterId = 0;

    public function mount(int $characterId): void
    {
        $this->account()->characters()->whereKey($characterId)->firstOrFail();
        $this->characterId = $characterId;
    }

    public function pin(string $feat, SetFeatPin $set): void
    {
        $set->handle($this->account(), $this->characterId, $this->feat($feat), true);
    }

    public function unpin(string $feat, SetFeatPin $set): void
    {
        $set->handle($this->account(), $this->characterId, $this->feat($feat), false);
    }

    public function render(BuildCharacterFeats $feats, ShowFeatCatalog $catalog): View
    {
        $character = $this->account()->characters()->whereKey($this->characterId)->firstOrFail();
        $hub = $feats->handle($character, $catalog->handle()['feats'], false);

        return view('livewire.character-feats', ['f' => FeatsSection::data($hub, $character, true)]);
    }

    private function feat(string $key): Feat
    {
        return Feat::tryFrom($key) ?? throw ValidationException::withMessages(['feat' => 'Unknown feat.']);
    }
}
