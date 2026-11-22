<?php

namespace App\Livewire;

use App\Actions\Account\MarkOwnedFeatsSeen;
use App\Actions\Account\SetFeatPin;
use App\Actions\Account\ShowOwnedCharacter;
use App\Actions\Account\ShowOwnedFeats;
use App\Livewire\Concerns\ActsForAccount;
use App\Support\Feat;
use App\View\Pages\CharacterFeatWall;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;
use Livewire\Component;

/**
 * The Feats section of a character page for the character's owner, with the pin toggles in its dialog. A character
 * page drops it in with `<livewire:feat-wall :character-id="$d['id']" />`; the id is locked and every request checks
 * it against the signed-in account. Visitors get the same markup from `<x-feat-wall>` without Livewire.
 */
class FeatWall extends Component
{
    use ActsForAccount;

    #[Locked]
    public int $characterId = 0;

    public function mount(int $characterId, ShowOwnedCharacter $owned): void
    {
        $owned->handle($this->account(), $characterId);
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
     * The toasts have played: record the tier shown of each feat (feat key => tier, 1 to 5, at most 75 feats).
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

    public function render(ShowOwnedFeats $owned): View
    {
        $shown = $owned->handle($this->account(), $this->characterId);

        return view('livewire.feat-wall', [
            'section' => CharacterFeatWall::section($shown['feats'], $shown['character']),
            'new' => $shown['new'],
        ]);
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
