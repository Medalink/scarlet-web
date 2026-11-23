<?php

namespace App\Livewire;

use App\Actions\Account\MarkOwnedFeatsSeen;
use App\Actions\Account\SetFeatPin;
use App\Actions\Feats\BuildCharacterFeats;
use App\Actions\Feats\ShowFeatCatalog;
use App\Actions\Feats\ShowNewFeats;
use App\Livewire\Concerns\ActsForAccount;
use App\Support\Feat;
use App\View\Character\FeatsSection;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;
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

    /**
     * The toasts have played: record the tier shown of each feat (feat key => tier, 1 to 5, at most one per feat).
     *
     * @param  array<array-key, mixed>  $shown
     */
    #[Renderless]
    public function seen(array $shown, MarkOwnedFeatsSeen $mark): void
    {
        $valid = Validator::make(['shown' => $shown], [
            'shown' => ['array', 'max:'.count(Feat::cases()), fn (string $attribute, mixed $value, Closure $fail) => $this->onlyFeatKeys($value) ?: $fail('Unknown feat.')],
            'shown.*' => ['integer', 'between:1,'.count(Feat::TIERS)],
        ])->validate();

        $mark->handle($this->account(), $this->characterId, $valid['shown']);
    }

    public function render(BuildCharacterFeats $feats, ShowFeatCatalog $catalog, ShowNewFeats $new): View
    {
        $character = $this->account()->characters()->whereKey($this->characterId)->firstOrFail();
        $hub = $feats->handle($character, $catalog->handle()['feats'], false, true);

        return view('livewire.character-feats', ['f' => FeatsSection::data($hub, $character, true), 'toasts' => FeatsSection::toasts($hub['feats'], $new->handle($character))]);
    }

    private function onlyFeatKeys(mixed $shown): bool
    {
        foreach (is_array($shown) ? array_keys($shown) : [] as $key) {
            if (Feat::tryFrom((string) $key) === null) {
                return false;
            }
        }

        return true;
    }

    private function feat(string $key): Feat
    {
        return Feat::tryFrom($key) ?? throw ValidationException::withMessages(['feat' => 'Unknown feat.']);
    }
}
