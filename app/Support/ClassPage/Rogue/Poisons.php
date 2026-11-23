<?php

namespace App\Support\ClassPage\Rogue;

use App\Support\ClassPage\Icon;
use App\Support\ClassPage\Module;
use App\Support\ClassPage\Rank;
use App\Support\ClassPage\RankRow;
use App\Support\ClassPage\Tone;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\FlowDirection;
use App\Support\ClassRows\FlowPlace;

/**
 * The poisons applied: a poison is an item, so a row carries the item's name and icon. An application is a poison that
 * left the bags with no vendor, bank, mail, trade or auction beside it, and a rank of a poison folds into its name
 * ("Instant Poison II" is "Instant Poison").
 */
final readonly class Poisons
{
    public const array NAMES = ['Instant Poison', 'Deadly Poison', 'Crippling Poison', 'Mind-numbing Poison', 'Wound Poison'];

    /** @param  list<array{name: string, icon: int, applied: int, crafted: int}>  $poisons  the most applied first */
    private function __construct(private array $poisons) {}

    public static function of(ClassRows $rows): self
    {
        $items = $rows->items(...array_merge(...array_map(self::patterns(...), self::NAMES)));
        $by = [];
        foreach (self::NAMES as $name) {
            $best = 0;
            $most = -1;
            $applied = $crafted = 0;
            foreach ($items->idsNamed(...self::patterns($name)) as $id) {
                $flow = $items->ofItems($id);
                $out = $flow->spent();
                $applied += $out;
                $crafted += $flow->sum(FlowDirection::In, FlowPlace::Profession);
                if ($out > $most) {
                    $best = $id;
                    $most = $out;
                }
            }
            if ($applied > 0 || $crafted > 0) {
                $by[] = ['name' => $name, 'icon' => $best, 'applied' => $applied, 'crafted' => $crafted];
            }
        }
        usort($by, fn (array $a, array $b): int => [$b['applied'], $a['name']] <=> [$a['applied'], $b['name']]);

        return new self($by);
    }

    /**
     * The item names a poison of one name goes by: the bare name and its ranks.
     *
     * @return list<string>
     */
    private static function patterns(string $name): array
    {
        return [$name, $name.' I*', $name.' V*'];
    }

    public function applied(): int
    {
        return array_sum(array_column($this->poisons, 'applied'));
    }

    public function crafted(): int
    {
        return array_sum(array_column($this->poisons, 'crafted'));
    }

    /**
     * The name and item of the poison applied most, or null when none was.
     *
     * @return array{name: string, icon: Icon, applied: int}|null
     */
    public function top(): ?array
    {
        return $this->poisons === [] || $this->poisons[0]['applied'] === 0 ? null : ['name' => $this->poisons[0]['name'], 'icon' => Icon::item($this->poisons[0]['icon']), 'applied' => $this->poisons[0]['applied']];
    }

    public function module(): ?Module
    {
        $rows = array_values(array_filter($this->poisons, fn (array $poison): bool => $poison['applied'] > 0));
        if ($rows === []) {
            return null;
        }
        $crafted = $this->crafted();
        $unused = array_values(array_map(fn (array $poison): string => $poison['name'], array_filter($this->poisons, fn (array $poison): bool => $poison['applied'] === 0)));
        $says = [];
        if ($crafted > 0) {
            $says[] = number_format($crafted).($crafted === 1 ? ' poison was' : ' poisons were').' crafted.';
        }
        if ($unused !== []) {
            $says[] = implode(' and ', $unused).(count($unused) === 1 ? ' was' : ' were').' crafted and never applied.';
        }

        return new Module('Poisons applied', [new Rank(array_map(
            fn (array $poison): RankRow => new RankRow($poison['name'], $poison['applied'], Icon::item($poison['icon']), Tone::Own),
            $rows,
        ))], note: $says === [] ? null : implode(' ', $says));
    }
}
