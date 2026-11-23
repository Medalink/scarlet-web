<?php

namespace App\Actions\Public\Character;

use App\Actions\Rollups\RebuildCharacterRates as Rates;
use App\Models\Character;
use App\Support\MovementMode;
use App\Support\Recap\Peers;
use App\View\Format;
use App\View\Sc\Compare;
use Illuminate\Support\Facades\DB;

/**
 * A character's kills, XP, deaths, quests, miles and jumps an hour at the levels it is at, against the characters of its class that played in the same
 * ten levels (`character_rates_bracket`). A strip is drawn only from a row of {@see Compare::MIN_GROUP} characters.
 */
class CharacterRates
{
    private const array CUTS = ['p10' => 1, 'p25' => 4, 'p50' => 9, 'p75' => 14, 'p90' => 17];

    /** @var array<string, array{0: string, 1: string, 2: int}> name, the words after the number in the sentence, decimals of the value */
    private const array METRICS = [
        Rates::KILLS => ['Kills an hour', ' kills an hour', 0],
        Rates::XP => ['XP an hour', ' XP an hour', 0],
        Rates::DEATHS => ['Deaths an hour', ' deaths an hour', 2],
        Rates::QUESTS => ['Quests an hour', ' quests an hour', 1],
        Rates::MILES => ['Miles an hour', ' miles an hour', 1],
        Rates::JUMPS => ['Jumps an hour', ' jumps an hour', 0],
    ];

    private const int SENTENCE_FROM = 55;

    /**
     * @return array{bracket: array{0: int, 1: int}, n: int, class: string, rows: list<array{0: string, 1: array<string, float|int|string>, 2: string, 3: int}>, sentence: string|null}|null null when the character has no level or no session at its levels; `$min` is the group size a strip needs
     */
    public function handle(Character $character, int $min = Compare::MIN_GROUP): ?array
    {
        if ($character->level === null || $character->class === null) {
            return null;
        }
        $bracket = intdiv((int) $character->level, 10) * 10;
        $rulesetId = DB::table('sessions')->where('character_id', $character->id)->where('counted', true)->orderByDesc('session_start')->value('ruleset_id');
        if ($rulesetId === null) {
            return null;
        }
        $you = $this->own($character->id, $bracket);
        $cuts = DB::table('character_rates_bracket')->where('ruleset_id', $rulesetId)->where('class', $character->class)->where('bracket', $bracket)->get(['metric', 'n', 'cuts'])->keyBy('metric');
        $rows = [];
        $spreads = [];
        $n = 0;
        foreach (self::METRICS as $metric => [$name, , $decimals]) {
            $value = $you[$metric] ?? null;
            if ($value === null) {
                continue;
            }
            $row = $cuts->get($metric);
            $text = Format::number($value, $value < 10 ? $decimals : 0);
            if ($row === null || (int) $row->n < $min) {
                $rows[] = [$name, ['lo' => 0, 'hi' => 1, 'p10' => 0, 'p25' => 0, 'p50' => 0, 'p75' => 0, 'p90' => 0, 'you' => $value], $text, 0];

                continue;
            }
            /** @var list<float> $list */
            $list = json_decode((string) $row->cuts, true, flags: JSON_THROW_ON_ERROR);
            $spread = array_map(fn (int $i): float => $list[$i], self::CUTS);
            $rows[] = [$name, ['lo' => 0, 'hi' => max($spread['p90'], $value) * 1.08, ...$spread, 'you' => $value], $text, (int) $row->n];
            $spreads[$metric] = ['percent' => Peers::percent($list, $value), 'you' => $value];
            $n = max($n, (int) $row->n);
        }
        if ($rows === []) {
            return null;
        }
        $class = Format::className($character->class);

        return [
            'bracket' => [max(1, $bracket), $bracket + 9],
            'n' => $n,
            'class' => $class,
            'rows' => $rows,
            'sentence' => $this->sentence($spreads, $class, $bracket),
        ];
    }

    /**
     * @param  array<string, array{percent: int, you: float|int}>  $spreads
     */
    private function sentence(array $spreads, string $class, int $bracket): ?string
    {
        $best = null;
        foreach ($spreads as $metric => $spread) {
            if ($spread['you'] > 0 && $spread['percent'] >= self::SENTENCE_FROM && ($best === null || $spread['percent'] > $spreads[$best]['percent'])) {
                $best = $metric;
            }
        }
        if ($best === null) {
            return null;
        }
        $you = $spreads[$best]['you'];

        return Format::number($you, $you < 10 ? self::METRICS[$best][2] : 0).self::METRICS[$best][1].': more than '.$spreads[$best]['percent'].'% of '.$class.'s who played at levels '.max(1, $bracket).' to '.($bracket + 9);
    }

    /** @return array<string, float> the character's own rates over its counted sessions that began in the bracket */
    private function own(int $characterId, int $bracket): array
    {
        $sessions = DB::table('sessions')->where('character_id', $characterId)->where('counted', true)->whereBetween('level_start', [$bracket, $bracket + 9]);
        $total = (clone $sessions)->selectRaw('coalesce(sum(played_ms), 0) as played, coalesce(sum(kills), 0) as kills, coalesce(sum(xp_total), 0) as xp, coalesce(sum(deaths), 0) as deaths, coalesce(sum(quests_done), 0) as quests')->first();
        if ($total === null || (int) $total->played < Rates::MIN_PLAYED_MS) {
            return [];
        }
        $hours = (int) $total->played / 3_600_000;
        $moved = DB::table('movement_stretches')->where('character_id', $characterId)->where('counted', true)
            ->whereIn('session_start', (clone $sessions)->select('session_start'))
            ->selectRaw('coalesce(sum(case when mode in (?, ?, ?, ?) then yards else 0 end), 0) as yards, coalesce(sum(jumps), 0) as jumps', [MovementMode::Run->value, MovementMode::Walk->value, MovementMode::Mount->value, MovementMode::Swim->value])->first();

        return [
            Rates::KILLS => (int) $total->kills / $hours,
            Rates::XP => (int) $total->xp / $hours,
            Rates::DEATHS => (int) $total->deaths / $hours,
            Rates::QUESTS => (int) $total->quests / $hours,
            Rates::MILES => (int) ($moved->yards ?? 0) / 1760 / $hours,
            Rates::JUMPS => (int) ($moved->jumps ?? 0) / $hours,
        ];
    }
}
