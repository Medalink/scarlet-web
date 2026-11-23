<?php

namespace App\Support\ClassPage\Druid;

use App\Support\ClassPage\Block;
use App\Support\ClassPage\Columns;
use App\Support\ClassPage\Icon;
use App\Support\ClassPage\Module;
use App\Support\ClassPage\Pair;
use App\Support\ClassPage\PairRow;
use App\Support\ClassPage\Rank;
use App\Support\ClassPage\RankRow;
use App\Support\ClassPage\Row;
use App\Support\ClassPage\Span;
use App\Support\ClassPage\Titled;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\Meter;
use App\Support\ClassRows\MeterSpell;
use App\Support\ClassRows\SpellTotals;
use App\Support\ClassRows\Tally;
use App\Support\StateKind;

/** What the druid cast: the spells of each form, healing by spell, control, Prowl and the spells of the wild. */
final readonly class Casting
{
    private const int HEALS = 4;

    private const int FROM_PROWL = 3;

    public function __construct(private ClassRows $rows, private Spells $spells) {}

    public function didRow(): ?Row
    {
        $modules = array_values(array_filter([$this->did(), $this->healing()]));

        return $modules === [] ? null : new Row($modules);
    }

    public function sheetRow(): ?Row
    {
        $modules = array_values(array_filter([$this->control(), $this->prowl(), $this->wild()]));

        return $modules === [] ? null : new Row($modules);
    }

    private function did(): ?Module
    {
        $columns = [];
        foreach ([['In Bear Form', 'bear', Spells::BEAR], ['In Cat Form', 'cat', Spells::CAT], ['In no form', null, Spells::CASTER]] as [$title, $form, $names]) {
            $cast = $this->spells->cast($names);
            if ($cast !== []) {
                $columns[] = new Titled($title, [self::rank($cast)], $form === null ? null : Forms::icon($form));
            }
        }
        if ($columns === []) {
            return null;
        }
        $blocks = count($columns) === 1 ? [$columns[0]] : [new Columns($columns)];

        return new Module('What each form did', $blocks, Span::W8, 'Each spell is listed under the form it is cast in. The addon does not record which form was worn at a cast.');
    }

    private function healing(): ?Module
    {
        $meters = $this->rows->meters();
        $listed = array_slice($meters->spells(Meter::Healing), 0, self::HEALS);
        if ($listed === []) {
            return null;
        }
        $shown = array_sum(array_map(fn (MeterSpell $spell): int => $spell->amount, $listed));
        $whole = max($meters->total(Meter::Healing), $shown);
        $rows = array_map(fn (MeterSpell $spell): RankRow => new RankRow($spell->name, $spell->amount / $whole * 100, Icon::spell($spell->iconId)), $listed);
        $rows[] = new RankRow('Every other spell', ($whole - $shown) / $whole * 100);

        return new Module('Healing by spell', [new Rank($rows, Unit::Percent)], note: 'The meter lists the five largest spells of each fight.');
    }

    private function control(): ?Module
    {
        $cast = $this->spells->cast(Spells::CONTROL);

        return $cast === [] ? null : new Module('Control', [self::rank($cast)]);
    }

    private function wild(): ?Module
    {
        $cast = $this->spells->cast(Spells::WILD);

        return $cast === [] ? null : new Module('Moonglade and the wild', [self::rank($cast)]);
    }

    private function prowl(): ?Module
    {
        $prowling = $this->rows->states()->sum(StateKind::OwnBuff, 'Prowl');
        if ($prowling->ms === 0 && $this->spells->prowl->casts === 0) {
            return null;
        }
        $auras = array_map(fn ($state): int => $state->refId, $this->rows->states()->of(StateKind::OwnBuff, 'Prowl'));
        $from = [];
        foreach ($auras === [] ? [] : $this->rows->tallies()->of(Tally::CastUnder) as $row) {
            if (in_array($row->a, $auras, true) && ! in_array($row->b, $this->spells->prowl->ids(), true)) {
                $from[$row->b] = ($from[$row->b] ?? 0) + $row->n;
            }
        }
        arsort($from);
        $blocks = [new Pair([
            new PairRow('Hours prowling', $prowling->hours(), Unit::Hours),
            new PairRow('Prowl casts', $this->spells->prowl->casts),
            new PairRow('Spells cast from Prowl', array_sum($from)),
        ])];
        $rank = $this->fromProwl($from);
        if ($rank !== null) {
            $blocks[] = $rank;
        }

        return new Module('Prowl', $blocks, icon: Spells::icon($this->spells->prowl));
    }

    /** @param  array<int, int>  $from  casts under Prowl, by the spell's id */
    private function fromProwl(array $from): ?Block
    {
        $names = $this->rows->spellNames(array_keys($from));
        $by = [];
        foreach ($from as $id => $count) {
            $name = $names[$id] ?? null;
            if ($name !== null) {
                $by[$name] = ['count' => ($by[$name]['count'] ?? 0) + $count, 'id' => $by[$name]['id'] ?? $id];
            }
        }
        uasort($by, fn (array $a, array $b): int => $b['count'] <=> $a['count']);
        $rows = [];
        foreach (array_slice($by, 0, self::FROM_PROWL, true) as $name => $one) {
            $rows[] = new RankRow($name, $one['count'], Icon::spell($one['id']));
        }

        return $rows === [] ? null : new Rank($rows);
    }

    /** @param  list<SpellTotals>  $cast */
    private static function rank(array $cast): Rank
    {
        return new Rank(array_map(fn (SpellTotals $spell): RankRow => new RankRow($spell->name, $spell->casts, Spells::icon($spell)), $cast));
    }
}
