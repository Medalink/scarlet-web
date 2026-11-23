<?php

namespace Database\Seeders\Population;

use App\Models\RulesetKind;

/**
 * The lines of one recording session, as the client would have written them: a login, the fights, kills, loot,
 * quests, trades, camps, travels and deaths the persona plays, and the level ups its xp pays for. A session ends
 * on the level, xp, copper and played time the next one starts from, so a character's history is one story.
 */
final class SessionScript
{
    private const string BUILD = '1.15.8';

    private const string ADDON = '0.9.0';

    private const int CLIENT_BUILD = 11508;

    private const int LEVEL_CAP = 60;

    private readonly Rng $rng;

    private readonly LineBook $book;

    private readonly Trades $trades;

    private int $level;

    private int $xp;

    private int $copper;

    private readonly int $endXp;

    private readonly int $endCopper;

    private readonly bool $dies;

    /**
     * @param  array{start: int, seconds: int, startLevel: int, endLevel: int, last: bool, final: int, played: int}  $slot
     */
    public function __construct(
        private readonly CharacterPlan $plan,
        private readonly int $index,
        private readonly array $slot,
    ) {
        $this->rng = Rng::for('session:'.$plan->name.':'.$index);
        $this->book = new LineBook;
        $this->trades = new Trades($this->book, $plan, $index, $slot['startLevel']);
        $this->level = $slot['startLevel'];
        $this->xp = $index === 0 || $this->level >= self::LEVEL_CAP ? 0 : $this->xpAtEnd($index - 1, $this->level);
        $this->endXp = $slot['endLevel'] >= self::LEVEL_CAP ? 0 : $this->xpAtEnd($index, $slot['endLevel']);
        $this->copper = $this->wealth($index);
        $this->endCopper = $this->wealth($index + 1);
        $this->dies = $slot['last'] && $plan->persona === Persona::Hardcore && Rng::for('hardcore-death:'.$plan->name)->chance(60);
    }

    /** @return list<array{0: int, 1: list<int|float|string>}> the lines of the session, in time order */
    public function compose(): array
    {
        $seconds = $this->slot['seconds'];
        $this->login();

        if ($this->index === 0) {
            $this->talents();
            $this->gear();
        }
        $camp = ! $this->dies && $this->rng->chance($this->plan->persona->campChance());
        $limit = $seconds - 120 - ($camp ? $this->trades->campSeconds() + 30 : 0);
        $dungeon = $this->dungeonFor();

        if ($dungeon !== null && $this->rng->chance($this->plan->persona->dungeonChance())) {
            $this->dungeon($dungeon, $limit);
        } else {
            $this->outdoor($limit);
        }

        if ($camp) {
            $this->trades->camp($limit + 30);
        }
        $this->settle($seconds - 10);
        $this->book->at($seconds * 1000, 'ES');

        return $this->book->sorted();
    }

    private function login(): void
    {
        $cap = $this->level >= self::LEVEL_CAP;
        $pvp = in_array($this->plan->kind, [RulesetKind::Pvp, RulesetKind::RoleplayPvp], true) ? 1 : 0;
        $zone = $this->zones()[0];
        $this->book->at(0, 'S', self::BUILD, $this->plan->class->value, $this->plan->race, $this->level,
            $this->plan->factionName(), self::ADDON, 1, 'Forever', 1, self::CLIENT_BUILD,
            $cap ? '' : $this->xp, $cap ? '' : CharacterPlan::need($this->level), $this->copper, self::LEVEL_CAP);
        $this->book->at(120, 'RU', 0, 1, 1, 1, $pvp, $pvp, $this->plan->factionName(), $pvp, $this->plan->realm, $this->plan->realm, '', 's', 0, 0);
        $this->book->at(240, 'GR', 0, $this->plan->kind === RulesetKind::Hardcore ? 1 : 0);
        $this->book->at(360, 'TP', $this->slot['played'] + 4, $this->rng->interval(300, 7200));
        $this->zone($zone, 1500);
    }

    /** @return non-empty-list<int> the zones this session's level and side hunt in, the home zone first on a young character */
    private function zones(): array
    {
        if ($this->level <= 10) {
            return [$this->plan->homeZone()];
        }

        return Rng::for('zones:'.$this->plan->name.':'.$this->index)->shuffled(World::zonesFor($this->level, $this->plan->faction()));
    }

    private function zone(int $zone, int $ms): void
    {
        $this->book->at($ms, 'Z', $zone, 0, 'none', 0, World::zoneName($zone), '');
    }

    private function talents(): void
    {
        $build = ClassKit::build($this->plan->class);
        $hash = substr(md5('talents:'.$this->plan->name), 0, 8);
        $tabs = [];

        foreach ($build['tabs'] as $tabAt => $tab) {
            $this->book->at(700 + $tabAt, 'TY', $tab[0], $tab[1]);
            $tabs[$tab[0]] = 0;
        }
        $this->book->at(800, 'TV', $build['tree'], $build['classId'], count($build['nodes']), $hash);
        $points = max(0, $this->slot['endLevel'] - 9);

        foreach ($build['nodes'] as $nodeAt => $node) {
            $entry = $node['node'] + 1000;
            $this->book->spell($node['spell'], ClassKit::talentName($node['spell']));
            $this->book->at(810 + $nodeAt * 10, 'TD', $build['tree'], $node['node'], 100 + $nodeAt * 100, 200, 'single',
                $node['ranks'], $node['tab'], "$entry:$node[spell]:$node[ranks]", '');
            $this->book->at(1000 + $nodeAt * 10, 'TA', 1, $node['node'], $entry, $node['ranks'], '', $node['ranks'], $node['spell'], $node['tab'], 's');
            $tabs[$node['tab']] += (int) round($points / count($build['nodes']));
        }
        $spent = implode(',', array_map(fn (int $tab, int $spent): string => "$tab:$spent:0", array_keys($tabs), $tabs));
        $this->book->at(2000, 'TG', 's', 1, 1, '', $build['spec'], $build['tree'], $spent, 0, 0, 1, '');
    }

    private function gear(): void
    {
        $weapon = ClassKit::weapon($this->plan->class);
        $this->book->item($weapon);
        $this->book->at(2500, 'G', 16, $weapon, '', '', '');
    }

    /** @return array{0: int, 1: array{0: string, 1: int, 2: list<string>}}|null the dungeon this session may run, by level */
    private function dungeonFor(): ?array
    {
        $open = array_filter(World::DUNGEONS, fn (array $dungeon): bool => $dungeon[1] <= $this->level + 3);

        if ($open === []) {
            return null;
        }
        $ids = array_keys($open);
        $id = $ids[$this->index % count($ids)];

        return [$id, $open[$id]];
    }

    /** @param  array{0: int, 1: array{0: string, 1: int, 2: list<string>}}  $dungeon */
    private function dungeon(array $dungeon, int $limit): void
    {
        [$instance, [$name, , $bosses]] = $dungeon;
        $this->book->at(60_000, 'Z', '', $instance, 'party', 1, $name, '');
        $this->group(60_100);
        $at = 90;

        foreach ($bosses as $bossAt => $boss) {
            $trashCount = $this->rng->interval(1, 3);

            for ($trash = 0; $trash < $trashCount && $at < $limit - 150; $trash++) {
                $at = $this->fight($at, $this->rng->pick(World::preyFor($this->level)));
            }
            $id = 900_000 + $instance * 10 + $bossAt;

            if ($at >= $limit - 100) {
                break;
            }
            $this->book->creature($id, $boss);
            $this->book->at($at * 1000, 'E', 1, $instance * 100 + $bossAt, 1, 5, $boss);
            $end = $this->fight($at + 2, $id, true);
            $this->book->at($end * 1000, 'E', 0, $instance * 100 + $bossAt, 1, $end - $at);
            $this->book->at($end * 1000 + 100, 'B', $instance * 100 + $bossAt, $boss);
            $at = $end + $this->rng->interval(30, 80);
        }
        $outside = $this->zones()[0];
        $this->zone($outside, ($at + 5) * 1000);
        $this->ungroup(($at + 6) * 1000);
        $this->money($at + 20, $this->rng->interval(500, 6000));
    }

    /** The party joins: a group line and a member line for each character it holds. */
    private function group(int $ms): void
    {
        $members = $this->plan->party;
        $this->book->at($ms, 'GP', 1, max(2, count($members) + 1));

        foreach ($members as $name) {
            $this->book->at($ms + 10, 'GM', 1, $name);
        }
    }

    private function ungroup(int $ms): void
    {
        foreach ($this->plan->party as $name) {
            $this->book->at($ms, 'GM', 0, $name, $this->rng->interval(900, 3000));
        }
        $this->book->at($ms + 10, 'GP', 0, 1);
    }

    private function outdoor(int $limit): void
    {
        $persona = $this->plan->persona;
        $hours = max(0.5, ($limit - 30) / 3600);
        $fights = (int) max(3, $hours * $this->rng->interval(...$persona->fightsPerHour()));
        $blocks = $this->rng->interval(...$persona->gatherBlocks());
        $crafts = $this->rng->interval(...$persona->craftBlocks());
        $deaths = $this->rng->interval(...$persona->deathsPerSession());
        $prey = World::preyFor($this->level);
        $zones = $this->zones();
        $zone = $zones[0];
        $gap = max(12, intdiv($limit - 30, $fights + $blocks + $crafts + 2));
        $at = 30;
        $died = 0;
        $steps = $fights + $blocks + $crafts;
        $grouped = $this->plan->party !== [] && $this->rng->chance(35);

        if ($grouped) {
            $this->group(20_000);
        }

        if ($this->index > 0 && $this->level > 10 && $this->rng->chance(25) && count($zones) > 1) {
            [$at, $zone] = $this->travel($at, $zone, $zones[1]);
        }

        for ($step = 0; $step < $steps && $at < $limit - 60; $step++) {
            if ($blocks > 0 && $step % 3 === 2) {
                $at += $this->trades->gather($at);
                $blocks--;

                continue;
            }
            if ($crafts > 0 && $step % 4 === 3) {
                $at += $this->trades->craft($at);
                $crafts--;

                continue;
            }
            $at = $this->move($at, $zone);
            $at = $this->fight($at, $this->rng->pick($prey));
            $at += $gap - 6;

            if ($died < $deaths && $this->rng->chance(15)) {
                $at = $this->death($at, $this->dies && $this->slot['last'] && $died === $deaths - 1);
                $died++;
            }
        }

        if ($this->dies && $died === 0) {
            $this->death(min($limit, $at), true);
        }
        if ($grouped) {
            $this->ungroup(($limit - 30) * 1000);
        }
        $this->quests(min($limit - 40, $at));
        $this->money($limit - 20, $this->rng->interval(-3000, 6000));
    }

    private function fight(int $at, int $creature, bool $boss = false): int
    {
        $spells = ClassKit::spells($this->plan->class);
        $ids = array_keys($spells);
        $finisher = ClassKit::shape($this->plan->class)['finisher'];
        $start = $at;
        $this->book->creature($creature);
        $this->book->at($at * 1000, 'F', 1);

        $casts = $this->rng->interval($boss ? 8 : 3, $boss ? 20 : 6);
        $at += 2;

        for ($hit = 0; $hit < $casts; $hit++) {
            $spell = $ids[$hit % count($ids)];
            $this->book->spell($spell, $spells[$spell]);
            $this->book->at($at * 1000, 'C', $spell);
            $at += $this->rng->interval(1, 3);
        }
        $this->book->spell($finisher, $spells[$finisher] ?? null);
        $this->book->at($at * 1000, 'C', $finisher);
        $aura = $this->aura($start + 1);
        $at += 2;
        $length = max(1, $at - $start);
        $this->book->at($at * 1000, 'K', $creature, 1);
        $this->gain($at + 1, $this->xpPerKill() * ($boss ? 3 : 1), 'k', $creature);
        $this->book->at($at * 1000 + 400, 'F', 0, $length);
        $dealt = $this->rng->interval(40, 120) * $length * (1 + intdiv($this->level, 12));
        $this->book->at($at * 1000 + 500, 'DM', 'd', $dealt, round($dealt / $length, 1), $length);
        $this->book->at($at * 1000 + 600, 'DM', 't', (int) ($dealt * 0.4), round($dealt * 0.4 / $length, 1), $length);
        $this->book->at($at * 1000 + 700, 'DS', 'd', $finisher, (int) ($dealt * 0.3));

        if ($aura !== null) {
            $this->book->at($at * 1000 + 300, 'A', 0, $aura, max(1, $at - $start));
        }
        $this->loot($at + 2, $creature, $boss);

        return $at + 3;
    }

    /** The id of the aura the fight opened with, or null when none was. */
    private function aura(int $at): ?int
    {
        $auras = ClassKit::auras($this->plan->class);

        if ($auras === [] || ! $this->rng->chance(60)) {
            return null;
        }
        $id = $this->rng->pick(array_keys($auras));
        $this->book->spell($id, ClassKit::spells($this->plan->class)[$id] ?? null);
        $this->book->at($at * 1000, 'A', 1, $id, 1, $auras[$id], 1, '');

        return $id;
    }

    private function loot(int $at, int $creature, bool $boss): void
    {
        if (! $boss && ! $this->rng->chance(55)) {
            return;
        }
        $items = Rng::for('loot:'.$creature)->shuffled(array_keys(World::ITEMS));
        $chances = $boss ? [100, 100, 60, 30, 12, 4] : [70, 38, 18, 7, 3, 1];
        $drops = [];

        foreach ($chances as $slot => $chance) {
            if (! $this->rng->chance($chance)) {
                continue;
            }
            $item = $items[$slot];
            $this->book->item($item);
            $drops[] = $item.'x'.($slot < 2 ? $this->rng->interval(1, $boss ? 3 : 2) : 1);
        }
        if ($drops === []) {
            return;
        }
        $this->book->at($at * 1000, 'LO', 'c', $creature, count($drops), implode(',', $drops), 0);
    }

    private function xpPerKill(): int
    {
        $budget = $this->xpBudget();

        return max(1, intdiv($budget, max(8, (int) ($this->slot['seconds'] / 150))));
    }

    private function xpBudget(): int
    {
        $total = max(0, $this->needFrom($this->slot['endLevel']) - $this->needFrom($this->slot['startLevel']));

        return $total + $this->endXp - $this->xp;
    }

    private function needFrom(int $level): int
    {
        $sum = 0;

        for ($at = 1; $at < $level; $at++) {
            $sum += CharacterPlan::need($at);
        }

        return $sum;
    }

    /** One gain of xp, paid level by level; stops at the xp the session is to end on. */
    private function gain(int $at, int $amount, string $source, int|string $id): void
    {
        while ($amount > 0 && $this->level < self::LEVEL_CAP) {
            $need = CharacterPlan::need($this->level);

            if ($this->level >= $this->slot['endLevel']) {
                $amount = min($amount, $this->endXp - $this->xp);
                if ($amount <= 0) {
                    return;
                }
            }
            $room = $need - $this->xp;

            if ($amount < $room || $this->level >= $this->slot['endLevel']) {
                $this->xp += $amount;
                $this->book->at($at * 1000, 'X', $amount, $this->xp, $need, $source, $id);

                return;
            }
            $this->level++;
            $this->xp = 0;
            $amount -= $room;
            $this->book->at($at * 1000, 'X', $room, 0, $this->level >= self::LEVEL_CAP ? '' : CharacterPlan::need($this->level), $source, $id);
            $this->book->at($at * 1000 + 200, 'L', $this->level);
            $at++;
        }
    }

    /** What is left to reach the level and xp the next session starts from, paid as exploration, and the copper too. */
    private function settle(int $at): void
    {
        $guard = 0;

        while ($this->level < $this->slot['endLevel'] && $guard++ < 80) {
            $this->gain($at, CharacterPlan::need($this->level) - $this->xp, 'e', '');
            $at++;
        }
        $this->gain($at, $this->endXp - $this->xp, 'e', '');
        $this->money($at, $this->endCopper - $this->copper);
    }

    private function quests(int $at): void
    {
        $count = $this->rng->interval(...$this->plan->persona->questsPerSession());
        $open = array_keys(array_filter(World::QUESTS, fn (array $quest): bool => $quest[0] <= $this->level + 2));
        $rng = Rng::for('quests:'.$this->plan->name.':'.$this->index);

        foreach (array_slice($rng->shuffled($open), 0, $count) as $one => $id) {
            [, $xp, $copper] = World::QUESTS[$id];
            $base = $at + 5 + $one * 25;
            $this->book->at($base * 1000, 'Q', 1, $id);
            $this->book->at(($base + 15) * 1000, 'Q', 2, $id, $xp, $copper);
            $this->gain($base + 16, $xp, 'q', $id);
            $this->money($base + 17, $copper);
        }
    }

    private function money(int $at, int $delta): void
    {
        $delta = max($delta, -$this->copper);

        if ($delta === 0) {
            return;
        }
        $this->copper += $delta;
        $this->book->at(max(1, min($at, $this->slot['seconds'] - 5)) * 1000, 'M', $delta, '', $this->copper);
    }

    /**
     * A walk of a few steps inside the zone, ending with the stretch's `W` line; answers the second after it.
     */
    private function move(int $at, int $zone): int
    {
        [$x, $y] = World::spot($this->plan->name.':'.$this->index.':'.$at, $zone);
        $steps = $this->rng->interval(2, 5);

        for ($step = 0; $step < $steps; $step++) {
            $x = max(2000, min(8000, $x + $this->rng->interval(-90, 90)));
            $y = max(2000, min(8000, $y + $this->rng->interval(-90, 90)));
            $this->book->at(($at + $step) * 1000, 'P', $zone, $x, $y, $this->rng->interval(0, 15));
        }
        $this->book->at(($at + $steps) * 1000 + 400, 'W', $this->level >= 40 ? 'm' : 'f', $steps * 8, $steps, 0, '');

        return $at + $steps + 2;
    }

    /**
     * A flight to another zone.
     *
     * @return array{int, int} the second after it and the zone landed in
     */
    private function travel(int $at, int $from, int $to): array
    {
        [$x, $y] = World::spot($this->plan->name.':fly:'.$this->index, $to);
        $seconds = $this->rng->interval(90, 200);
        $this->book->at($at * 1000, 'T', 1, $from, $x, $y);
        $this->book->at(($at + $seconds) * 1000, 'T', 0, $to, $x + 120, $y - 80, $seconds);
        $this->zone($to, ($at + $seconds + 2) * 1000);

        return [$at + $seconds + 5, $to];
    }

    private function death(int $at, bool $final): int
    {
        $this->book->at($at * 1000, 'D');

        if ($final) {
            return $at + 10;
        }
        $this->book->at(($at + 6) * 1000, 'U');

        return $at + 20;
    }

    /** The xp a character stands on at the end of a session, a share of its level's bar both sessions agree on. */
    private function xpAtEnd(int $index, int $level): int
    {
        $share = Rng::for('xp-end:'.$this->plan->name.':'.min($index, $this->slot['final']))->interval(5, 90);

        return (int) (CharacterPlan::need($level) * $share / 100);
    }

    /** The copper a character carries when session `$index` starts. */
    private function wealth(int $index): int
    {
        $noise = Rng::for('copper:'.$this->plan->name.':'.$index)->interval(0, 900);

        return (int) (($this->plan->level ** 1.6) * 14 * (1 + $index * 0.12)) + $noise;
    }
}
