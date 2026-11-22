<?php

namespace App\Support\ClassPage\Common;

use App\Support\ClassPage\Icon;
use App\Support\ClassPage\Module;
use App\Support\ClassPage\Rank;
use App\Support\ClassPage\RankRow;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\Meter;
use App\Support\ClassRows\MeterSpell;

/** The spells the client's damage meter listed, as shares of the damage it reported. */
final readonly class DamageRank
{
    public const int SPELLS = 6;

    public function __construct(private ClassRows $rows) {}

    public function module(string $heading = 'Damage by spell'): ?Module
    {
        $meters = $this->rows->meters();
        $listed = array_slice($meters->spells(Meter::Damage), 0, self::SPELLS);
        if ($listed === []) {
            return null;
        }
        $shown = array_sum(array_map(fn (MeterSpell $spell): int => $spell->amount, $listed));
        $whole = max($meters->total(Meter::Damage), $shown);
        $rows = array_map(fn (MeterSpell $spell): RankRow => new RankRow($spell->name, $spell->amount / $whole * 100, Icon::spell($spell->iconId)), $listed);
        $rows[] = new RankRow('Every other spell', ($whole - $shown) / $whole * 100);

        return new Module($heading, [new Rank($rows, Unit::Percent)], note: 'The meter lists the five largest spells of each fight.');
    }

    /** The share of the damage the meter reported that one spell name holds, 0 when it was never listed. */
    public function share(string $name): int|float
    {
        $meters = $this->rows->meters();
        $total = $meters->total(Meter::Damage);
        foreach ($meters->spells(Meter::Damage) as $spell) {
            if ($spell->name === $name) {
                return $total === 0 ? 0 : min(100, $spell->amount / $total * 100);
            }
        }

        return 0;
    }
}
