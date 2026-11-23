<?php

namespace App\Actions\Account;

use App\Support\CharacterScope;
use App\Support\Profession;
use App\Support\Rollup;
use App\Support\RollupRead;

class MostWorkedProfession
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * The profession with a page that the characters have the most attempts or crafts in, Fishing when they have none.
     *
     * @param  list<int>  $characterIds
     */
    public function handle(array $characterIds): Profession
    {
        $scope = new CharacterScope($characterIds, null);
        $gathered = array_column($this->read->rows($this->read->table(Rollup::GathersDailyCharacter->value, $scope)->groupBy('kind')->selectRaw('kind, sum(attempts) as total')), 'total', 'kind');
        $crafted = array_column($this->read->rows($this->read->table(Rollup::CraftsDailyCharacter->value, $scope)->groupBy('profession_id')->selectRaw('profession_id, sum(crafts) as total')), 'total', 'profession_id');

        $best = Profession::Fishing;
        $most = 0;
        foreach (Profession::withPages() as $profession) {
            $kind = $profession->gatherKind();
            $line = $profession->skillLine();
            $total = (int) ($kind === null ? ($line === null ? 0 : $crafted[$line] ?? 0) : $gathered[$kind->value] ?? 0);
            if ($total > $most) {
                $best = $profession;
                $most = $total;
            }
        }

        return $best;
    }
}
