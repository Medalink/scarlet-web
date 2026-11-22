<?php

namespace App\Actions\Rollups;

use App\Support\RollupRead;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class Roster
{
    public const string HORDE = 'Horde';

    public const string ALLIANCE = 'Alliance';

    public function __construct(private readonly RollupRead $read) {}

    /**
     * Who plays: the characters that have at least one counted session in the scope, by class and by faction, most
     * first. A group that fewer than `config('scarlet.min_characters')` characters make up is left out.
     *
     * @param  int|list<int>|null  $rulesets
     * @return array{classes: list<array{class: string, characters: int}>, factions: list<array{faction: string, characters: int}>}
     */
    public function handle(int|array|null $rulesets = null): array
    {
        $played = $this->read->table('sessions', $rulesets)->where('counted', true)->select('character_id')->distinct();

        $classes = [];
        foreach ($this->groups($played, 'characters.class') as $row) {
            $classes[] = ['class' => (string) $row['name'], 'characters' => (int) $row['characters']];
        }
        $factions = [];
        foreach ($this->groups($played, 'characters.faction') as $row) {
            $factions[] = ['faction' => (string) $row['name'], 'characters' => (int) $row['characters']];
        }

        return ['classes' => $classes, 'factions' => $factions];
    }

    /**
     * @return list<array<string, mixed>> name, characters
     */
    private function groups(Builder $played, string $column): array
    {
        $query = DB::query()->fromSub($played, 'p')
            ->join('characters', 'characters.id', '=', 'p.character_id')
            ->whereNotNull($column)
            ->groupBy($column)
            ->select($column.' as name')
            ->selectRaw('count(*) as characters')
            ->havingRaw('count(*) >= ?', [(int) config('scarlet.min_characters')])
            ->orderByRaw('count(*) desc')
            ->orderBy($column);

        return $this->read->rows($query);
    }
}
