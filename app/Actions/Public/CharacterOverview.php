<?php

namespace App\Actions\Public;

use App\Actions\Feats\ShowCharacterFeats;
use App\Actions\Public\Character\CapState;
use App\Actions\Public\Character\CreaturesMet;
use App\Actions\Public\Character\DeathLog;
use App\Actions\Public\Character\DungeonRuns;
use App\Actions\Public\Character\HeatCells;
use App\Actions\Public\Character\HourlyPlay;
use App\Actions\Public\Character\ItemsFound;
use App\Actions\Public\Character\LifetimeBaselines;
use App\Actions\Public\Character\MostCrafted;
use App\Actions\Public\Character\MostKilled;
use App\Actions\Public\Character\QuestsDone;
use App\Actions\Public\Character\Records;
use App\Actions\Public\Character\SessionTotals;
use App\Actions\Public\Character\SkillHistory;
use App\Actions\Public\Character\SkillRanks;
use App\Actions\Public\Character\Standings;
use App\Actions\Public\Character\Talents;
use App\Actions\Public\Character\ZoneHours;
use App\Actions\Rollups\LevellingCurve;
use App\Actions\Rollups\TimePerLevel;
use App\Models\Character;
use App\Support\KillerKind;
use App\Support\PlayerNameShape;
use App\Support\Rollup;
use App\Support\RollupRead;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CharacterOverview
{
    /** Sessions the play history lists in the answer of the API. */
    public const int HISTORY = 25;

    /** Sessions the character page lists first. */
    public const int PAGE_HISTORY = 10;

    /** Sessions the gold line covers. */
    public const int GOLD_SESSIONS = 30;

    public function __construct(
        private readonly RollupRead $read,
        private readonly LevellingCurve $curve,
        private readonly ListRulesets $rulesets,
        private readonly ResolvePlayerName $players,
        private readonly ZoneHours $zoneHours,
        private readonly DeathLog $deathLog,
        private readonly ItemsFound $itemsFound,
        private readonly SkillRanks $skillRanks,
        private readonly SessionTotals $totals,
        private readonly Records $records,
        private readonly TimePerLevel $timePerLevel,
        private readonly MostKilled $mostKilled,
        private readonly MostCrafted $mostCrafted,
        private readonly DungeonRuns $dungeonRuns,
        private readonly HourlyPlay $hourlyPlay,
        private readonly HeatCells $heatCells,
        private readonly CapState $capState,
        private readonly Talents $talents,
        private readonly QuestsDone $questsDone,
        private readonly CreaturesMet $creaturesMet,
        private readonly SkillHistory $skillHistory,
        private readonly ShowCharacterFeats $feats,
        private readonly Standings $standings,
        private readonly LifetimeBaselines $lifetime,
    ) {}

    /**
     * Everything the character page and `/public/characters/{name}` show. A character is visible when its owner made
     * it public, and always to its owner (`$viewerAccountId` is the signed-in account). Money is in the answer only
     * for the owner, or when the owner chose to show the economy. Null when the character is unknown or private.
     *
     * @return array<string, mixed>|null
     */
    public function handle(string $name, ?int $viewerAccountId, int $history = self::HISTORY): ?array
    {
        $character = Character::query()->where('name_key', Character::keyFor($name))->first();
        if ($character === null || ! $character->visibleTo($viewerAccountId)) {
            return null;
        }
        $owner = $viewerAccountId !== null && $character->account_id === $viewerAccountId;
        $id = $character->id;
        $economy = $owner || $character->show_economy;
        $last = DB::table('sessions')->where('character_id', $id)->orderByDesc('end_at')->first(['ruleset_id', 'end_at', 'level_end']);
        $rulesetId = $last === null ? null : (int) $last->ruleset_id;
        $ruleset = collect($this->rulesets->handle())->firstWhere('id', $rulesetId);
        $totals = $this->totals->handle($id);
        $play = $this->hourlyPlay->handle($id);
        $dungeons = $this->dungeons($id);
        $skills = $this->skillRanks->handle($id);
        $records = $this->records->handle($id);

        return [
            'character' => $character,
            'owner' => $owner,
            'economy' => $economy,
            'ruleset' => $ruleset,
            'cap' => $this->capState->handle($id),
            'heat' => $this->heatCells->handle($id),
            'marks' => $this->marks($id),
            'week' => $this->week($id, $last === null ? null : Carbon::parse((string) $last->end_at, 'UTC'), $economy),
            'pace' => ['character' => $this->levels($id), 'ruleset' => $rulesetId === null ? [] : $this->curve->handle($rulesetId)],
            'levelTimes' => ['character' => $this->levelTimes($id), 'ruleset' => $rulesetId === null ? [] : $this->timePerLevel->handle($rulesetId)],
            'xp' => $this->xp($id),
            'dungeons' => $dungeons,
            'deaths' => $this->deaths($id, $owner),
            'deathLevels' => $this->deathLevels($id),
            'zoneHours' => $this->zoneHours->handle($id),
            'deathLog' => $this->deathLog->handle($id, $owner),
            'items' => $this->itemsFound->handle($id),
            'skills' => $skills,
            'talents' => $this->talents->handle($id),
            'records' => $records,
            'feats' => $last === null ? [] : $this->feats->handle($character, $economy),
            'standings' => $this->standings->handle($character),
            'killed' => $this->mostKilled->handle($id),
            'crafted' => $this->mostCrafted->handle($id),
            'dungeonRuns' => $this->dungeonRuns->handle($id),
            'questsDone' => $this->questsDone->handle($id),
            'lifetime' => $this->lifetime->handle($id),
            'creaturesMet' => $this->creaturesMet->handle($id),
            'totals' => $totals,
            'days' => $play['days'],
            'playBuckets' => $play['buckets'],
            'skillHistory' => $this->skillHistory->handle($id, $play['buckets']),
            'gold' => $economy ? $this->gold($id) : null,
            'sessions' => $this->sessions($id, $economy, $history),
            'distance' => $totals['distance'],
            'history' => $history,
        ];
    }

    /** @return list<array{kind: string, u: float, v: float, day: string}> the deaths on the map, oldest first, each with its UTC day */
    private function marks(int $id): array
    {
        return array_values(DB::table('deaths')->where('character_id', $id)->whereNotNull('u')->orderBy('at')->limit(500)->get(['u', 'v', 'at'])
            ->map(fn (object $row): array => ['kind' => 'death', 'u' => round((float) $row->u, 5), 'v' => round((float) $row->v, 5), 'day' => Carbon::parse((string) $row->at)->utc()->toDateString()])->all());
    }

    /**
     * The week (Monday to Sunday, UTC) of the last session.
     *
     * @return array<string, mixed>|null
     */
    private function week(int $id, ?Carbon $end, bool $economy): ?array
    {
        if ($end === null) {
            return null;
        }
        $from = $end->copy()->startOfWeek();
        $to = $from->copy()->addWeek();
        $sum = DB::table('sessions')->where('character_id', $id)->where('end_at', '>=', $from)->where('end_at', '<', $to)->where('counted', true)
            ->selectRaw('coalesce(sum(played_ms), 0) / 1000.0 as seconds, coalesce(sum(quests_done), 0) as quests')
            ->selectRaw('coalesce(sum(copper_earned), 0) - coalesce(sum(copper_spent), 0) as gold')->first();
        $runs = DB::table('dungeon_runs')->where('character_id', $id)->where('at', '>=', $from)->where('at', '<', $to)->where('counted', true)->where('continues_previous', false)->count();
        $zones = DB::table('play_spans')->where('character_id', $id)->where('at', '>=', $from)->where('at', '<', $to)->whereNotNull('zone_id')->distinct()->count('zone_id');

        return [
            'from' => $from->toDateString(), 'to' => $to->copy()->subDay()->toDateString(),
            'seconds' => (float) ($sum->seconds ?? 0), 'quests' => (int) ($sum->quests ?? 0), 'runs' => $runs,
            'zones' => $zones, 'gold' => $economy ? (int) ($sum->gold ?? 0) : null,
        ];
    }

    /** @return list<array{level: int, seconds: int}> the client's played total at the level-up into each level Scarlet saw; a level the character was first seen at has none */
    private function levels(int $id): array
    {
        return array_values(DB::table('character_levels')->where('character_id', $id)->where('counted', true)->orderBy('level')
            ->get(['level', 'before_seconds'])->map(fn (object $row): array => ['level' => (int) $row->level, 'seconds' => (int) $row->before_seconds])->all());
    }

    /** @return list<array{level: int, seconds: int}> the played time spent at each level the character has left */
    private function levelTimes(int $id): array
    {
        return array_values(DB::table('character_levels')->where('character_id', $id)->where('counted', true)->where('completed', true)->whereNotNull('level')
            ->groupBy('level')->orderBy('level')->selectRaw('level, max(seconds) as seconds')->get()
            ->map(fn (object $row): array => ['level' => (int) $row->level, 'seconds' => (int) $row->seconds])->all());
    }

    /** @return list<array<string, mixed>> level, source, xp */
    private function xp(int $id): array
    {
        return $this->read->rows(
            DB::table(Rollup::XpDailyCharacter->value)->selectRaw('level, source, sum(xp) as xp')->where('character_id', $id)->whereNotNull('level')
                ->groupBy('level', 'source')->orderBy('level'),
        );
    }

    /** @return list<array<string, mixed>> instance_id, name, runs, inside_seconds, bosses, deaths */
    private function dungeons(int $id): array
    {
        return $this->read->rows(
            DB::table('dungeon_runs')->where('character_id', $id)->where('counted', true)->groupBy('instance_id')
                ->selectRaw('instance_id, max(name) as name, count(*) filter (where not continues_previous) as runs, (sum(inside_ms) / 1000.0)::float as inside_seconds, sum(bosses) as bosses, sum(deaths) as deaths')
                ->orderByRaw('count(*) filter (where not continues_previous) desc')->orderBy('instance_id'),
        );
    }

    /** @return array{total: int, killers: list<array<string, mixed>>, zones: list<array<string, mixed>>} */
    private function deaths(int $id, bool $owner): array
    {
        $killers = DB::table('deaths')->where('character_id', $id)->whereNotNull('killer')->groupBy('killer_kind', 'killer')
            ->selectRaw('killer_kind as kind, killer, count(*) as deaths')->orderByRaw('count(*) desc')->orderBy('killer')->limit(6)->get();
        $pseudonyms = [];
        foreach ($killers as $killer) {
            if ($killer->kind === KillerKind::Player->value || PlayerNameShape::isPseudonym((string) $killer->killer)) {
                $pseudonyms[] = (string) $killer->killer;
            }
        }
        $names = $this->players->handle($id, $owner, $pseudonyms);
        $zones = DB::table('deaths')->where('character_id', $id)->whereNotNull('zone_id')->groupBy('zone_id')
            ->selectRaw('zone_id, (select z.name from zones z where z.id = zone_id) as zone, count(*) as deaths')->orderByRaw('count(*) desc')->orderBy('zone_id')->limit(5)->get();

        return [
            'total' => (int) DB::table('deaths')->where('character_id', $id)->count(),
            'killers' => array_values($killers->map(fn (object $row): array => [
                'kind' => $row->kind, 'killer' => match (true) {
                    $row->kind === KillerKind::Player->value || PlayerNameShape::isPseudonym((string) $row->killer) => $names[(string) $row->killer] ?? ResolvePlayerName::UNKNOWN,
                    PlayerNameShape::matches((string) $row->killer) => ResolvePlayerName::UNKNOWN,
                    default => (string) $row->killer,
                }, 'deaths' => (int) $row->deaths,
            ])->all()),
            'zones' => array_values($zones->map(fn (object $row): array => ['zone_id' => (int) $row->zone_id, 'zone' => $row->zone, 'deaths' => (int) $row->deaths])->all()),
        ];
    }

    /** @return array<int, int> the deaths at each level the character died at */
    private function deathLevels(int $id): array
    {
        return array_map(fn (mixed $count): int => (int) $count, DB::table('deaths')->where('character_id', $id)->whereNotNull('level')->groupBy('level')
            ->orderBy('level')->selectRaw('level, count(*) as deaths')->pluck('deaths', 'level')->all());
    }

    /** @return list<array{at: string, balance: int}> the balance at the end of the last sessions, oldest first */
    private function gold(int $id): array
    {
        $rows = DB::table('sessions')->where('character_id', $id)->whereNotNull('money_end')->orderByDesc('end_at')->limit(self::GOLD_SESSIONS)->get(['end_at', 'money_end']);

        return array_reverse(array_values($rows->map(fn (object $row): array => ['at' => Carbon::parse((string) $row->end_at)->utc()->toIso8601ZuluString(), 'balance' => (int) $row->money_end])->all()));
    }

    /** @return list<array<string, mixed>> */
    private function sessions(int $id, bool $economy, int $limit): array
    {
        return array_values(DB::table('sessions')->where('character_id', $id)->orderByDesc('start_at')->limit($limit)
            ->get(['start_at', 'played_ms', 'level_start', 'level_end', 'xp_total', 'kills', 'deaths', 'quests_done', 'copper_earned', 'copper_spent', 'counted'])
            ->map(fn (object $row): array => [
                'at' => Carbon::parse((string) $row->start_at)->utc()->toIso8601ZuluString(), 'seconds' => (int) round($row->played_ms / 1000),
                'level_start' => $row->level_start, 'level_end' => $row->level_end, 'xp' => $row->xp_total, 'kills' => $row->kills, 'deaths' => $row->deaths,
                'quests' => $row->quests_done, 'counted' => (bool) $row->counted,
                ...($economy ? ['gold' => (int) $row->copper_earned - (int) $row->copper_spent] : []),
            ])->all());
    }
}
