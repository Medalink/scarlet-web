<?php

namespace App\Support\ClassPage;

use App\Support\LevelBracket;
use App\Support\PlayerClass;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The stored standings of the other public characters of a class in the level bracket of one character
 * (`class_standings`). Every read answers null until the bracket holds `MIN_CHARACTERS` characters, so a figure is never
 * set against two or three others.
 */
final class ClassPeers
{
    /** Characters of a class in a level bracket that a comparison needs before it is drawn. */
    public const int MIN_CHARACTERS = 20;

    public function __construct(private readonly PlayerClass $class, private readonly int $characterId, private readonly ?LevelBracket $bracket) {}

    public function bracket(): ?LevelBracket
    {
        return $this->bracket;
    }

    /** The mean of a figure over the other characters of the class in the bracket, or over every class in it. */
    public function mean(string $figure, bool $everyClass = false): ?float
    {
        if ($this->bracket === null) {
            return null;
        }
        $row = $this->others($figure, $everyClass)->selectRaw('count(*) as characters, avg(s.value) as mean')->first();

        return $row === null || (int) $row->characters + 1 < self::MIN_CHARACTERS ? null : (float) $row->mean;
    }

    /**
     * How many characters of the class hold a figure in the bracket, the character itself included, and how many of the others hold less.
     *
     * @return array{characters: int, below: int}|null null under the minimum
     */
    public function place(string $figure, int|float $value): ?array
    {
        if ($this->bracket === null) {
            return null;
        }
        $row = $this->others($figure, false)->selectRaw('count(*) as characters, count(*) filter (where s.value < ?) as below', [$value])->first();
        if ($row === null || (int) $row->characters + 1 < self::MIN_CHARACTERS) {
            return null;
        }

        return ['characters' => (int) $row->characters + 1, 'below' => (int) $row->below];
    }

    private function others(string $figure, bool $everyClass): Builder
    {
        [$from, $to] = $this->bracket?->range() ?? [1, 255];

        return DB::table('class_standings as s')->join('characters as c', 'c.id', '=', 's.character_id')
            ->where('c.is_public', true)->where('s.figure', $figure)->where('s.character_id', '<>', $this->characterId)
            ->whereBetween('s.level', [$from, $to])
            ->when(! $everyClass, fn (Builder $query): Builder => $query->where('s.class', $this->class->value));
    }
}
