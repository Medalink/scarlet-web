<?php

namespace App\Actions\Rollups;

use App\Support\RollupRead;
use Illuminate\Support\Facades\DB;

class ReachedLevels
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * How many characters have reached each level, from the level the game reports for them: a character at level 60
     * has reached every level from 1 to 60, whether or not Scarlet saw it level up. A level that fewer than
     * `config('scarlet.min_characters')` characters have reached is left out.
     *
     * @param  int|list<int>|null  $rulesetId
     * @return list<array{level: int, characters: int}> every level from 1 to the highest, lowest first
     */
    public function handle(int|array|null $rulesetId = null): array
    {
        $highest = $this->read->table('sessions', $rulesetId)
            ->where('counted', true)
            ->whereNotNull('level_end')
            ->groupBy('character_id')
            ->selectRaw('max(level_end) as top');
        $query = DB::query()->fromSub($highest, 'h')
            ->selectRaw('top as level, count(*) as characters')
            ->groupBy('top')
            ->orderByDesc('top');

        $reached = 0;
        $levels = [];
        foreach ($this->read->rows($query) as $row) {
            $top = (int) $row['level'];
            $levels[$top] = (int) $row['characters'];
        }
        $min = (int) config('scarlet.min_characters');
        $rows = [];
        for ($level = (int) max([0, ...array_keys($levels)]); $level >= 1; $level--) {
            $reached += $levels[$level] ?? 0;
            if ($reached >= $min) {
                $rows[] = ['level' => $level, 'characters' => $reached];
            }
        }

        return array_reverse($rows);
    }
}
