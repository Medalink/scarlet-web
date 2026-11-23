<?php

namespace App\Actions\Public;

use App\Actions\Account\OwnCharacterNames;
use App\Models\Account;
use App\Support\CharacterScope;

class PersonalMapPage
{
    public function __construct(
        private readonly MapSubject $subject,
        private readonly OwnCharacterNames $ownNames,
        private readonly MapTrails $trails,
        private readonly MapZones $zones,
    ) {}

    /**
     * What the world map shows for one character or for every character of the signed-in viewer (`MapSubject`): the
     * world rectangle its trails fill and whether it has any, the zones with its own play in them, and the viewer's own
     * character names for the switch between them. Null when `MapSubject` finds nobody.
     *
     * @return array{name: string|null, fit: array{0: float, 1: float, 2: float, 3: float}|null, empty: bool, zones: list<array<string, mixed>>, own: list<string>}|null
     */
    public function handle(?string $character, ?Account $viewer): ?array
    {
        $subject = $this->subject->handle($character, $viewer);
        if ($subject === null) {
            return null;
        }
        $trails = $this->trails->handle($subject['ids']);

        return [
            'name' => $subject['name'],
            'fit' => $trails['fit'],
            'empty' => $trails['cells'] === [] && $trails['flights'] === [],
            'zones' => $this->zones->handle(new CharacterScope($subject['ids'], null)),
            'own' => $viewer === null ? [] : $this->ownNames->handle($viewer),
        ];
    }
}
