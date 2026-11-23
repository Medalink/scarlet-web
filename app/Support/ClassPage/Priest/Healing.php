<?php

namespace App\Support\ClassPage\Priest;

use App\Support\ClassPage\Casts;
use App\Support\ClassPage\Hue;
use App\Support\ClassPage\Icon;
use App\Support\ClassPage\Mirror;
use App\Support\ClassPage\MirrorPoint;
use App\Support\ClassPage\Module;
use App\Support\ClassPage\Pair;
use App\Support\ClassPage\PairRow;
use App\Support\ClassPage\Rank;
use App\Support\ClassPage\RankRow;
use App\Support\ClassPage\Series;
use App\Support\ClassPage\Span;
use App\Support\ClassPage\Split;
use App\Support\ClassPage\SplitPart;
use App\Support\ClassPage\Tone;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\Meter;
use App\Support\ClassRows\MeterPlace;
use App\Support\ClassRows\SpellTotals;

/** The healing and the shields: the client's healing and absorb meters after each fight, and the heals cast from `spell_casts`. */
final readonly class Healing
{
    private const array PLACES = [
        MeterPlace::World->value => ['The open world', 'var(--class)'],
        MeterPlace::Dungeon->value => ['Dungeons', '#7cb7ff'],
        MeterPlace::Raid->value => ['Raids', '#d58cff'],
        MeterPlace::Battleground->value => ['Battlegrounds', '#f2d06b'],
    ];

    private const int MOST_SPELLS = 6;

    public function __construct(private ClassRows $rows, private Spells $spells) {}

    public function done(): int
    {
        return $this->rows->meters()->total(Meter::Healing);
    }

    /** Healing done in each fight the meter reported. */
    public function perFight(): float
    {
        return Casts::perFight($this->done(), $this->rows->meters()->fights(Meter::Healing)['fights']);
    }

    public function absorbed(): int
    {
        return $this->rows->meters()->total(Meter::Absorbs);
    }

    /** The heal cast most, power word shield counted among them as the comp counts it. */
    public function top(): ?SpellTotals
    {
        return Casts::cast($this->spells->heals)[0] ?? null;
    }

    public function topShare(): float
    {
        $all = array_sum(array_map(fn (SpellTotals $spell): int => $spell->casts, $this->spells->heals));

        return Casts::share($this->top()->casts ?? 0, $all);
    }

    public function mirror(): ?Module
    {
        $meters = $this->rows->meters();
        $healing = $meters->byLevel(Meter::Healing);
        if ($healing === []) {
            return null;
        }
        $damage = $meters->byLevel(Meter::Damage);
        $points = [];
        $passed = null;
        foreach ($this->rows->levels() as $level) {
            $up = Casts::perFight($healing[$level] ?? 0, $meters->fights(Meter::Healing, $level)['fights']);
            $down = Casts::perFight($damage[$level] ?? 0, $meters->fights(Meter::Damage, $level)['fights']);
            $points[] = new MirrorPoint($level, $up, $down);
            if ($passed === null && $up > $down && $up > 0) {
                $passed = [$level, $up, $down];
            }
        }
        $note = $passed === null ? null : 'Healing first passed damage at level '.$passed[0].', with '.Unit::Count->say($passed[1]).' against '.Unit::Count->say($passed[2]).'.';

        return new Module('Healing and damage a fight, by level', [new Mirror(
            $points,
            new Series('Healing done', Hue::ofClass()),
            new Series('Damage done', new Hue('#9b7fe0')),
            'Healing done a fight, above the line, and damage done a fight, below, at each level from '.$points[0]->level.' to '.$points[count($points) - 1]->level,
        )], Span::W8, $note);
    }

    public function where(): ?Module
    {
        $meters = $this->rows->meters();
        $places = $meters->byPlace(Meter::Healing);
        $all = array_sum($places);
        if ($all === 0) {
            return null;
        }
        $parts = [];
        foreach (self::PLACES as $place => [$label, $css]) {
            if (($places[$place] ?? 0) > 0) {
                $parts[] = new SplitPart($label.', '.Unit::Count->say(Casts::share($places[$place], $all)).'%', $places[$place], new Hue($css));
            }
        }
        $cells = [];
        $group = $meters->group(Meter::Healing);
        if ($group['total'] > 0) {
            $cells[] = new PairRow('Share of group healing', Casts::share($group['own'], $group['total']), Unit::Percent);
        }
        $cells[] = new PairRow('Fights healed', $meters->fights(Meter::Healing)['fights']);
        $cells[] = new PairRow('Most in one fight', $meters->best(Meter::Healing));

        return new Module('Where the healing was done', [new Pair($cells), new Split($parts)]);
    }

    public function bySpell(): ?Module
    {
        $spells = array_slice($this->rows->meters()->spells(Meter::Healing), 0, self::MOST_SPELLS);
        if ($spells === []) {
            return null;
        }

        return new Module('Healing by spell', [new Rank(
            array_map(fn ($spell): RankRow => new RankRow($spell->name, $spell->amount, Icon::spell($spell->iconId), Tone::Own), $spells),
            Unit::Short,
        )], Span::W5, 'The meter names the top five spells of a fight.');
    }

    public function stack(): ?Module
    {
        $stack = Casts::stack($this->spells->heals, $this->rows->levels(), Spells::hues(), 'heals cast');

        return $stack === null ? null : new Module('The heal cast, by level', [$stack], Span::W7);
    }

    public function shields(): ?Module
    {
        $shield = $this->spells->shield;
        if ($shield->casts === 0) {
            return null;
        }
        $cells = [new PairRow('Cast', $shield->casts)];
        $fights = $this->rows->meters()->fights(Meter::Absorbs)['fights'];
        if ($this->absorbed() > 0) {
            $cells[] = new PairRow('Absorbed', $this->absorbed());
            $cells[] = new PairRow('Each fight', Casts::perFight($this->absorbed(), $fights));
        }

        return new Module('Shields', [new Pair($cells)], icon: Casts::icon($shield));
    }
}
