<?php

namespace App\Support\ClassPage\Druid;

use App\Support\ClassPage\Hue;
use App\Support\ClassPage\Module;
use App\Support\ClassPage\Pair;
use App\Support\ClassPage\PairRow;
use App\Support\ClassPage\Row;
use App\Support\ClassPage\Split;
use App\Support\ClassPage\SplitPart;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\SpellTotals;

/** What the druid does for others and the form it fights in: Rebirth, Innervate and the share of fight time in each form. */
final readonly class Party
{
    public function __construct(private ClassRows $rows, private Spells $spells, private Forms $forms) {}

    /** @return list<Row> */
    public function rows(): array
    {
        $modules = array_values(array_filter([$this->rebirth(), $this->innervate(), $this->role()]));

        return $modules === [] ? [] : [new Row($modules)];
    }

    private function rebirth(): ?Module
    {
        $rebirth = $this->spells->rebirth;
        if ($rebirth->casts === 0) {
            return null;
        }

        return new Module('Rebirth', [new Pair([
            new PairRow('Casts', $rebirth->casts),
            new PairRow('Inside a fight', self::share($rebirth), Unit::Percent),
            new PairRow('Interrupted', $rebirth->interrupts),
        ])], icon: Spells::icon($rebirth));
    }

    private function innervate(): ?Module
    {
        $innervate = $this->spells->innervate;
        if ($innervate->casts === 0) {
            return null;
        }
        $hours = $this->rows->playedMs() / 3_600_000;

        return new Module('Innervate', [new Pair([
            new PairRow('Casts', $innervate->casts),
            new PairRow('Inside a fight', self::share($innervate), Unit::Percent),
            new PairRow('Hours between casts', $hours / $innervate->casts, Unit::Decimal),
        ])], note: 'Who a spell was cast on is not recorded, so the page counts casts.', icon: Spells::icon($innervate));
    }

    private function role(): ?Module
    {
        $fights = $this->rows->states()->fights()->ms;
        $in = $this->forms->fightMs();
        $whole = max($fights, $in);
        if ($in === 0 || $whole === 0) {
            return null;
        }
        $parts = [];
        foreach (array_keys(Forms::ALL) as $key) {
            $ms = $this->forms->fightMs($key);
            if ($ms > 0) {
                $parts[] = new SplitPart('In '.Forms::label($key).', '.Unit::Count->say($ms / $whole * 100).'%', $ms, new Hue(Forms::ALL[$key][3]));
            }
        }
        $parts[] = new SplitPart('In no form, '.Unit::Count->say(($whole - $in) / $whole * 100).'%', $whole - $in, new Hue('#3d3d46'));

        return new Module('The form worn in fights', [new Split($parts)]);
    }

    private static function share(SpellTotals $spell): int|float
    {
        return $spell->casts === 0 ? 0 : min(100, $spell->fightCasts / $spell->casts * 100);
    }
}
