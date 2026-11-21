<?php

namespace App\Actions\Account;

use App\Actions\Rollups\TimePerLevel;
use Illuminate\Support\Facades\DB;

class ShowCharacterLevelTimes
{
    public function __construct(private readonly TimePerLevel $timePerLevel) {}

    /**
     * The played seconds each character spent in the levels it has left, with the median of its ruleset in each.
     *
     * @param  list<array{id: int, ruleset_id: int|null}>  $characters
     * @return array<int, array{character: list<array{level: int, seconds: int}>, ruleset: list<array<string, mixed>>}> by character id
     */
    public function handle(array $characters): array
    {
        $medians = $answer = [];
        foreach ($characters as $character) {
            $rulesetId = $character['ruleset_id'] ?? 0;
            $medians[$rulesetId] ??= $rulesetId === 0 ? [] : $this->timePerLevel->handle($rulesetId);
            $answer[$character['id']] = [
                'character' => array_values(DB::table('character_levels')->where('character_id', $character['id'])->where('counted', true)->where('completed', true)->whereNotNull('level')
                    ->groupBy('level')->orderBy('level')->selectRaw('level, max(seconds) as seconds')->get()
                    ->map(fn (object $row): array => ['level' => (int) $row->level, 'seconds' => (int) $row->seconds])->all()),
                'ruleset' => $medians[$rulesetId],
            ];
        }

        return $answer;
    }
}
