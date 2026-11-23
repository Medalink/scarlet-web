<?php

namespace App\Support\ClassPage;

use App\Support\LevelBracket;
use App\Support\PlayerClass;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * The stored standings of the other public characters of a class in the level bracket of one character
 * (`class_standings`), read once for every figure by one grouped query. A figure answers only once enough characters
 * hold it, so it is never set against two or three others: `MIN_CHARACTERS` of the class, the character itself
 * included, for the class; `MIN_CHARACTERS` characters of other classes for "every class". The character's place is
 * counted against its own stored figure, so a character whose figures are not stored yet has no place.
 */
final class ClassPeers
{
    /** Characters of a class in a level bracket that a comparison needs before it is drawn. */
    public const int MIN_CHARACTERS = 20;

    /** @var array<string, array{same: int, mean: float, foreign: int, every: float, below: int, mine: bool}>|null by figure */
    private ?array $figures = null;

    public function __construct(private readonly PlayerClass $class, private readonly int $characterId, private readonly ?LevelBracket $bracket) {}

    public function bracket(): ?LevelBracket
    {
        return $this->bracket;
    }

    /** The mean of a figure over the other characters of the class in the bracket, or over every class in it; null under the minimum. */
    public function mean(string $figure, bool $everyClass = false): ?float
    {
        $row = $this->figures()[$figure] ?? null;
        if ($row === null) {
            return null;
        }
        if ($everyClass) {
            return $row['foreign'] >= self::MIN_CHARACTERS ? $row['every'] : null;
        }

        return $row['same'] + 1 >= self::MIN_CHARACTERS ? $row['mean'] : null;
    }

    /**
     * How many characters of the class hold a figure in the bracket, the character itself included, and how many of
     * the others hold less than it did when it was last stored.
     *
     * @return array{characters: int, below: int}|null null under the minimum, or for a figure of the character that is not stored
     */
    public function place(string $figure): ?array
    {
        $row = $this->figures()[$figure] ?? null;
        if ($row === null || ! $row['mine'] || $row['same'] + 1 < self::MIN_CHARACTERS) {
            return null;
        }

        return ['characters' => $row['same'] + 1, 'below' => $row['below']];
    }

    /** @return array<string, array{same: int, mean: float, foreign: int, every: float, below: int, mine: bool}> */
    private function figures(): array
    {
        if ($this->figures !== null) {
            return $this->figures;
        }
        $this->figures = [];
        if ($this->bracket === null) {
            return $this->figures;
        }
        [$from, $to] = $this->bracket->range();
        $class = $this->class->value;
        $rows = DB::table('class_standings as s')
            ->join('characters as c', 'c.id', '=', 's.character_id')
            ->leftJoin('class_standings as me', fn (JoinClause $join) => $join->on('me.figure', '=', 's.figure')->where('me.character_id', '=', $this->characterId))
            ->where('c.is_public', true)->where('s.character_id', '<>', $this->characterId)->whereBetween('s.level', [$from, $to])
            ->groupBy('s.figure')
            ->selectRaw(
                's.figure, count(*) filter (where s.class = ?) as same, avg(s.value) filter (where s.class = ?) as same_mean, count(*) filter (where s.class <> ?) as foreign_count, avg(s.value) as every_mean, count(*) filter (where s.class = ? and s.value < me.value) as below, coalesce(bool_or(me.value is not null), false) as mine',
                [$class, $class, $class, $class],
            )
            ->get();
        foreach ($rows as $row) {
            $this->figures[(string) $row->figure] = [
                'same' => (int) $row->same,
                'mean' => (float) $row->same_mean,
                'foreign' => (int) $row->foreign_count,
                'every' => (float) $row->every_mean,
                'below' => (int) $row->below,
                'mine' => (bool) $row->mine,
            ];
        }

        return $this->figures;
    }
}
