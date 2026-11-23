<?php

namespace App\Actions\Account;

use App\Actions\Feats\BuildCharacterFeats;
use App\Actions\Feats\ShowFeatCatalog;
use App\Actions\Public\Character\CharacterRates;
use App\Actions\Public\Character\StandingsOfMany;
use App\Actions\Sessions\BuildRecap;
use App\Models\Account;
use App\Models\Character;
use Illuminate\Support\Facades\DB;

/** Everything /account draws: the characters with their feats and best places, the latest session, the week, 30 days of play and the comparison of the character played last. */
class ShowAccountHome
{
    /** Characters whose feats and places are read, most played first; the page links the rest to the side-by-side page. */
    public const int ROWS = 12;

    public function __construct(
        private readonly ShowCharacterRoster $roster,
        private readonly ShowCharacterPlayByDay $playByDay,
        private readonly ShowCharacterGold $gold,
        private readonly ShowCharacterLevelTimes $levelTimes,
        private readonly ShowAccountPlayBuckets $buckets,
        private readonly ShowWeekOfPlay $week,
        private readonly ShowInstalls $installs,
        private readonly ShowFeatCatalog $catalog,
        private readonly BuildCharacterFeats $feats,
        private readonly StandingsOfMany $standings,
        private readonly CharacterRates $rates,
        private readonly BuildRecap $recap,
    ) {}

    /** @return array<string, mixed> */
    public function handle(Account $account): array
    {
        $characters = $this->roster->handle($account);
        $ids = array_column($characters, 'id');
        $models = $account->characters()->get()->keyBy('id');
        $latest = $this->latest($ids);
        $featured = $latest === null ? ($characters[0]['id'] ?? null) : $latest['character_id'];
        $catalog = $this->catalog->handle()['feats'];
        $shown = array_slice($characters, 0, self::ROWS);
        $feats = [];
        $shownModels = [];
        foreach ($shown as $row) {
            /** @var Character $model */
            $model = $models[$row['id']];
            $shownModels[] = $model;
            $feats[$row['id']] = $this->feats->handle($model, $catalog, false, true);
        }
        $places = $this->standings->handle($shownModels);
        $mainRow = null;
        foreach ($characters as $row) {
            $mainRow = $row['id'] === $featured ? $row : $mainRow;
        }
        $main = $mainRow === null ? null : $models->get($mainRow['id']);

        return [
            'characters' => $characters,
            'shown' => $shown,
            'days' => $this->playByDay->handle($ids),
            'gold' => $this->gold->handle($ids),
            'buckets' => $this->buckets->handle($ids),
            'week' => $this->week->handle($ids),
            'installs' => $this->installs->handle($account),
            'catalog' => $catalog,
            'feats' => $feats,
            'places' => $places,
            'featured' => $featured,
            'rates' => $main === null ? null : $this->rates->handle($main),
            'levelTimes' => $mainRow === null ? null : ($this->levelTimes->handle([$mainRow])[$mainRow['id']] ?? null),
            'latest' => $latest === null || $main === null ? null : ['character_id' => $latest['character_id'], 'recap' => $this->recap->handle($main, $latest['start'], true)],
            'private' => array_values(array_map(fn (array $row): string => $row['name'], array_filter($characters, fn (array $row): bool => ! $row['is_public']))),
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return array{character_id: int, start: int}|null
     */
    private function latest(array $ids): ?array
    {
        $row = DB::table('sessions')->whereIn('character_id', $ids)->where('counted', true)->orderByDesc('end_at')->first(['character_id', 'session_start']);

        return $row === null ? null : ['character_id' => (int) $row->character_id, 'start' => (int) $row->session_start];
    }
}
