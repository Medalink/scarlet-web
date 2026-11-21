<?php

namespace App\Actions\Public\Character;

use App\Http\Middleware\PublicApi;
use App\Support\HeatLevels;
use App\Support\Rollup;
use App\Support\RollupRead;
use App\Support\WalkOrder;
use App\Support\WorldGrid;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HeatCells
{
    /** Cells read at most. */
    private const int READ = 30000;

    public function __construct(private readonly RollupRead $read) {}

    /**
     * Every cell of the world grid a character walked, with its heat level, and the world rectangle that fits them. The
     * pages tell clients an answer is good for `PublicApi::MAX_AGE`, so the cells are read and levelled once in that time.
     *
     * The cells come oldest first with the number first walked on each day (`App\Support\WalkOrder`); an answer cached before
     * that has no `days`.
     *
     * @return array{cells: list<array{0: int, 1: int, 2: int}>, fit: array{0: float, 1: float, 2: float, 3: float}|null, days?: list<array{0: string, 1: int}>}
     */
    public function handle(int $characterId): array
    {
        /** @var array{cells: list<array{0: int, 1: int, 2: int}>, fit: array{0: float, 1: float, 2: float, 3: float}|null, days?: list<array{0: string, 1: int}>} */
        return Cache::remember(self::key($characterId), PublicApi::MAX_AGE, function () use ($characterId): array {
            $rows = $this->read->rows(
                DB::table(Rollup::WorldHeatDailyCharacter->value)->selectRaw("cell_i, cell_j, sum(points) as value, to_char(min(bucket) at time zone 'UTC', 'YYYY-MM-DD') as first_day")->where('character_id', $characterId)
                    ->groupBy('cell_i', 'cell_j')->orderByRaw('sum(points) desc')->orderBy('cell_i')->orderBy('cell_j')->limit(self::READ),
            );

            $walk = WalkOrder::arrange(HeatLevels::assign($rows), array_column($rows, 'first_day'));

            return ['cells' => $walk['cells'], 'fit' => WorldGrid::around($rows), 'days' => $walk['days']];
        });
    }

    private static function key(int $characterId): string
    {
        return 'character-heat:'.$characterId;
    }
}
