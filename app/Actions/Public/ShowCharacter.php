<?php

namespace App\Actions\Public;

use App\Actions\Account\ShowImportedHistory;
use App\Actions\Feats\BuildCharacterFeats;
use App\Actions\Feats\ShowFeatCatalog;
use App\Actions\Public\Character\CharacterRates;
use App\Actions\Public\Character\TimeSplits;
use App\Actions\Public\ClassPage\BuildClassPage;
use App\Actions\Rollups\PlayTimeByWeekday;
use App\Support\ClassPage\ClassPage;
use App\View\Character\SessionsSection;

/** Everything the page of one character draws: the overview answer, its feats, its rates against its class, its sessions, its class section and its time. */
class ShowCharacter
{
    public function __construct(
        private readonly CharacterOverview $overview,
        private readonly ShowFeatCatalog $catalog,
        private readonly BuildCharacterFeats $feats,
        private readonly CharacterRates $rates,
        private readonly BuildClassPage $class,
        private readonly CharacterBattlegrounds $battlegrounds,
        private readonly TimeSplits $splits,
        private readonly PlayTimeByWeekday $everyone,
        private readonly SessionsSection $sessions,
        private readonly ShowImportedHistory $imported,
    ) {}

    /**
     * Null when the character is unknown or private to this viewer. `$viewer` is the account the page is shown to, `$sessions` how many sessions the section lists.
     *
     * @return array{overview: array<string, mixed>, feats: array<string, mixed>, rates: array<string, mixed>|null, class: ClassPage|null, battlegrounds: array<string, mixed>|null, splits: array<string, mixed>, everyone: list<array<string, mixed>>, sessions: array<string, mixed>|null}|null
     */
    public function handle(string $name, ?int $viewer, int $sessions, bool $addons): ?array
    {
        $r = $this->overview->handle($name, $viewer, 1, $addons);
        if ($r === null) {
            return null;
        }
        $character = $r['character'];
        $rulesetId = $r['ruleset']['id'] ?? null;

        return [
            'overview' => $r,
            'feats' => $this->feats->handle($character, $this->catalog->handle()['feats'], false, (bool) $r['economy']),
            'rates' => $this->rates->handle($character),
            'class' => $this->class->handle($character),
            'battlegrounds' => $this->battlegrounds->handle($name, $viewer),
            'splits' => $this->splits->handle($character->id),
            'everyone' => $rulesetId === null ? [] : $this->everyone->handle($rulesetId),
            'sessions' => $this->sessions->handle($character, $viewer, (bool) $r['economy'], $sessions, $r['days']),
            'imported' => $r['owner'] ? ($this->imported->handle([$character->id])[$character->id] ?? null) : null,
        ];
    }
}
