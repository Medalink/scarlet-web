<?php

namespace App\Support;

use Illuminate\Support\Str;

/** The feats of a character page: what each counts, its icon and the five thresholds of its tiers (docs/DESIGN.md "Art and game layer"). */
enum Feat: string
{
    case Summit = 'summit';
    case Veteran = 'veteran';
    case Devoted = 'devoted';
    case Steadfast = 'steadfast';
    case Seasoned = 'seasoned';
    case Regular = 'regular';
    case LongHaul = 'long-haul';
    case Longstanding = 'longstanding';
    case Unbroken = 'unbroken';
    case Slayer = 'slayer';
    case Bestiary = 'bestiary';
    case Finisher = 'finisher';
    case Gladiator = 'gladiator';
    case Rampage = 'rampage';
    case Fallen = 'fallen';
    case Flattened = 'flattened';
    case Nemesis = 'nemesis';
    case Territorial = 'territorial';
    case Roamer = 'roamer';
    case Questing = 'questing';
    case ErrandRunner = 'errand-runner';
    case Fickle = 'fickle';
    case Cartographer = 'cartographer';
    case LocalHero = 'local-hero';
    case Thorough = 'thorough';
    case Delver = 'delver';
    case Vanquisher = 'vanquisher';
    case Spelunker = 'spelunker';
    case Raider = 'raider';
    case Conqueror = 'conqueror';
    case DeepDweller = 'deep-dweller';
    case Flawless = 'flawless';
    case Familiar = 'familiar';
    case Soloist = 'soloist';
    case FullHouse = 'full-house';
    case Explorer = 'explorer';
    case Globetrotter = 'globetrotter';
    case Local = 'local';
    case Wayfarer = 'wayfarer';
    case Jumper = 'jumper';
    case Swimmer = 'swimmer';
    case Runner = 'runner';
    case Rider = 'rider';
    case Stroller = 'stroller';
    case Flyer = 'flyer';
    case Tracker = 'tracker';
    case Scout = 'scout';
    case Witness = 'witness';
    case Naturalist = 'naturalist';
    case Skullseer = 'skullseer';
    case Looter = 'looter';
    case Scavenger = 'scavenger';
    case Lucky = 'lucky';
    case Collector = 'collector';
    case Jackpot = 'jackpot';
    case Curator = 'curator';
    case Unboxer = 'unboxer';
    case Angler = 'angler';
    case Fishmonger = 'fishmonger';
    case Wading = 'wading';
    case Gatherer = 'gatherer';
    case Forager = 'forager';
    case Artisan = 'artisan';
    case Master = 'master';
    case Practised = 'practised';
    case Crafter = 'crafter';
    case Repertoire = 'repertoire';
    case Earner = 'earner';
    case Spender = 'spender';
    case Tycoon = 'tycoon';
    case Windfall = 'windfall';
    case Expressive = 'expressive';
    case Mime = 'mime';
    case Signature = 'signature';
    case Indecisive = 'indecisive';

    /** The tiers in order, copper first: tier `n` of a feat is `TIERS[n - 1]`, and 0 is no tier. */
    public const array TIERS = ['Copper', 'Silver', 'Gold', 'Scarlet', 'Legend'];

    private const array ECONOMY = [self::Earner, self::Spender, self::Tycoon, self::Windfall];

    /** The tier a count reaches: 0 for none, 1 copper to 5 legend. */
    public function tier(int|float $value): int
    {
        $tier = 0;
        foreach ($this->thresholds() as $i => $threshold) {
            if ($value >= $threshold) {
                $tier = $i + 1;
            }
        }

        return $tier;
    }

    /** The tier a stored count holds: the count rounded the way a card shows it, then read with `tier()`. */
    public function held(int|float $stored): int
    {
        $decimals = $this->decimals();

        return $this->tier($decimals === 0 ? floor($stored) : round($stored, $decimals));
    }

    /** @return array{0: int, 1: int, 2: int, 3: int, 4: int} the counts that earn copper, silver, gold, scarlet and legend */
    public function thresholds(): array
    {
        return $this->row()[3];
    }

    /** The name of the icon in `<x-icon>`. */
    public function icon(): string
    {
        return $this->row()[0];
    }

    /** The name a card shows: "Long Haul", "Errand Runner". */
    public function label(): string
    {
        return Str::headline($this->name);
    }

    /** Decimal places of a value: 1 for Wayfarer, 0 for every other feat. */
    public function decimals(): int
    {
        return $this === self::Wayfarer ? 1 : 0;
    }

    /** The count as the page says it: "6,969 kills", "1 kill", and "Level 42" for a feat that is a level or a rank. */
    public function says(string $number, bool $one = false): string
    {
        return match ($this) {
            self::Summit => 'Level '.$number,
            self::Unbroken => 'Level '.$number.' without dying',
            default => $number.' '.$this->noun($one),
        };
    }

    /** What the count is of, after the number: "6,969 kills", or "1 kill" when `$one`. */
    public function noun(bool $one = false): string
    {
        $row = $this->row();

        return $one ? $row[1] : $row[2];
    }

    /** True for the feats a character page shows only when the owner chose to show the economy. */
    public function economy(): bool
    {
        return in_array($this, self::ECONOMY, true);
    }

    /** @return array{0: string, 1: string, 2: string, 3: array{0: int, 1: int, 2: int, 3: int, 4: int}} icon, singular and plural noun, thresholds */
    private function row(): array
    {
        return match ($this) {
            self::Summit => ['level', 'level reached', 'levels reached', [10, 25, 45, 55, 60]],
            self::Veteran => ['hours', 'hour played', 'hours played', [2, 50, 200, 500, 2500]],
            self::Devoted => ['clock', 'day played', 'days played', [3, 14, 60, 150, 365]],
            self::Steadfast => ['clock', 'day played in a row', 'days played in a row', [2, 7, 21, 60, 180]],
            self::Seasoned => ['clock', 'week played', 'weeks played', [1, 4, 12, 26, 52]],
            self::Regular => ['hours', 'session played', 'sessions played', [5, 60, 250, 600, 3000]],
            self::LongHaul => ['hours', 'two-hour session', 'two-hour sessions', [1, 15, 60, 150, 600]],
            self::Longstanding => ['clock', 'day since the first session', 'days since the first session', [7, 30, 90, 180, 365]],
            self::Unbroken => ['shield', 'level reached without dying', 'levels reached without dying', [10, 20, 35, 50, 60]],
            self::Slayer => ['kills', 'kill', 'kills', [100, 2500, 10000, 30000, 150000]],
            self::Bestiary => ['creature', 'kind of creature killed', 'kinds of creature killed', [10, 250, 750, 1250, 2000]],
            self::Finisher => ['kills', 'killing blow', 'killing blows', [50, 1000, 5000, 15000, 75000]],
            self::Gladiator => ['kills', 'player killed', 'players killed', [1, 25, 100, 500, 2500]],
            self::Rampage => ['kills', 'kill in one session', 'kills in one session', [50, 300, 600, 1000, 2000]],
            self::Fallen => ['deaths', 'death', 'deaths', [1, 10, 40, 100, 400]],
            self::Flattened => ['deaths', 'damage in one killing blow', 'damage in one killing blow', [50, 300, 1000, 2500, 5000]],
            self::Nemesis => ['deaths', 'death to one killer', 'deaths to one killer', [2, 4, 8, 15, 30]],
            self::Territorial => ['pin', 'kill in one zone', 'kills in one zone', [50, 500, 1500, 4000, 12000]],
            self::Roamer => ['pin', 'zone with 100 kills', 'zones with 100 kills', [1, 8, 16, 24, 32]],
            self::Questing => ['quests', 'quest turned in', 'quests turned in', [10, 150, 500, 1000, 1500]],
            self::ErrandRunner => ['quests', 'quest in one session', 'quests in one session', [3, 10, 20, 35, 50]],
            self::Fickle => ['quests', 'quest abandoned', 'quests abandoned', [1, 20, 75, 150, 300]],
            self::Cartographer => ['zones', 'area discovered', 'areas discovered', [5, 100, 300, 600, 800]],
            self::LocalHero => ['quests', 'zone with 25 quests turned in', 'zones with 25 quests turned in', [1, 4, 8, 12, 18]],
            self::Thorough => ['quests', 'quest in one zone', 'quests in one zone', [10, 30, 60, 90, 110]],
            self::Delver => ['dungeons', 'dungeon run', 'dungeon runs', [1, 20, 75, 200, 500]],
            self::Vanquisher => ['crown', 'dungeon boss killed', 'dungeon bosses killed', [1, 50, 250, 1000, 2500]],
            self::Spelunker => ['dungeons', 'dungeon or raid entered', 'dungeons and raids entered', [1, 5, 12, 18, 22]],
            self::Raider => ['crown', 'raid run', 'raid runs', [1, 10, 40, 100, 250]],
            self::Conqueror => ['crown', 'raid boss killed', 'raid bosses killed', [1, 25, 100, 300, 800]],
            self::DeepDweller => ['dungeons', 'hour inside dungeons', 'hours inside dungeons', [1, 20, 75, 200, 600]],
            self::Flawless => ['shield', 'deathless dungeon run', 'deathless dungeon runs', [1, 10, 40, 100, 300]],
            self::Familiar => ['dungeons', 'run of one dungeon', 'runs of one dungeon', [2, 8, 20, 50, 120]],
            self::Soloist => ['dungeons', 'dungeon run alone', 'dungeon runs alone', [1, 5, 20, 50, 150]],
            self::FullHouse => ['characters', 'dungeon run in a group of five', 'dungeon runs in a group of five', [1, 20, 75, 200, 500]],
            self::Explorer => ['zones', 'zone entered', 'zones entered', [5, 20, 40, 55, 65]],
            self::Globetrotter => ['zones', 'zone played for an hour', 'zones played for an hour', [2, 10, 20, 30, 40]],
            self::Local => ['pin', 'hour in one zone', 'hours in one zone', [2, 15, 40, 100, 300]],
            self::Wayfarer => ['distance', 'world width walked', 'world widths walked', [1, 15, 60, 150, 750]],
            self::Jumper => ['jumps', 'jump', 'jumps', [100, 5000, 20000, 50000, 250000]],
            self::Swimmer => ['swim', 'yard swum', 'yards swum', [500, 10000, 50000, 125000, 600000]],
            self::Runner => ['distance', 'yard run', 'yards run', [1000, 400000, 1500000, 4000000, 15000000]],
            self::Rider => ['distance', 'yard ridden', 'yards ridden', [1000, 100000, 1000000, 4000000, 20000000]],
            self::Stroller => ['distance', 'yard walked', 'yards walked', [100, 2000, 10000, 50000, 250000]],
            self::Flyer => ['distance', 'yard flown', 'yards flown', [1000, 150000, 600000, 1500000, 7500000]],
            self::Tracker => ['pin', 'rare creature seen', 'rare creatures seen', [1, 10, 30, 60, 100]],
            self::Scout => ['creature', 'elite creature seen', 'elite creatures seen', [5, 75, 250, 500, 900]],
            self::Witness => ['crown', 'boss seen', 'bosses seen', [1, 5, 15, 30, 50]],
            self::Naturalist => ['creature', 'kind of creature seen', 'kinds of creature seen', [25, 750, 2000, 3500, 5000]],
            self::Skullseer => ['creature', 'skull-level creature seen', 'skull-level creatures seen', [1, 5, 20, 50, 100]],
            self::Looter => ['item', 'corpse looted', 'corpses looted', [10, 1500, 6000, 15000, 75000]],
            self::Scavenger => ['item', 'poor item looted', 'poor items looted', [5, 250, 1000, 3000, 12000]],
            self::Lucky => ['item', 'uncommon item looted', 'uncommon items looted', [1, 100, 400, 1000, 4000]],
            self::Collector => ['item', 'rare item looted', 'rare items looted', [1, 15, 60, 150, 500]],
            self::Jackpot => ['item', 'epic item looted', 'epic items looted', [1, 3, 10, 25, 75]],
            self::Curator => ['item', 'kind of item received', 'kinds of item received', [25, 600, 1500, 2500, 4000]],
            self::Unboxer => ['item', 'container opened', 'containers opened', [1, 25, 100, 300, 1000]],
            self::Angler => ['fish', 'fish caught', 'fish caught', [10, 250, 1500, 5000, 15000]],
            self::Fishmonger => ['fish', 'kind of catch', 'kinds of catch', [3, 15, 30, 45, 60]],
            self::Wading => ['fish', 'zone fished in', 'zones fished in', [1, 5, 10, 20, 30]],
            self::Gatherer => ['gather', 'node gathered', 'nodes gathered', [10, 250, 1500, 5000, 15000]],
            self::Forager => ['gather', 'zone gathered in', 'zones gathered in', [1, 5, 10, 20, 30]],
            self::Artisan => ['skills', 'profession rank', 'profession ranks', [75, 300, 750, 1350, 1500]],
            self::Master => ['skills', 'profession at rank 300', 'professions at rank 300', [1, 2, 3, 4, 5]],
            self::Practised => ['skills', 'skill-up', 'skill-ups', [10, 300, 1000, 1800, 2500]],
            self::Crafter => ['item', 'item crafted', 'items crafted', [5, 500, 2500, 10000, 25000]],
            self::Repertoire => ['skills', 'kind of item crafted', 'kinds of item crafted', [3, 25, 75, 150, 300]],
            self::Earner => ['gold', 'gold earned', 'gold earned', [1, 100, 1000, 5000, 25000]],
            self::Spender => ['gold', 'gold spent', 'gold spent', [1, 100, 1000, 5000, 25000]],
            self::Tycoon => ['gold', 'gold carried at once', 'gold carried at once', [1, 50, 500, 2000, 10000]],
            self::Windfall => ['gold', 'gold earned in one session', 'gold earned in one session', [1, 10, 50, 200, 1000]],
            self::Expressive => ['emote', 'emote used', 'emotes used', [10, 250, 1000, 5000, 25000]],
            self::Mime => ['emote', 'kind of emote used', 'kinds of emote used', [3, 15, 40, 80, 120]],
            self::Signature => ['emote', 'use of one emote', 'uses of one emote', [5, 100, 500, 2000, 10000]],
            self::Indecisive => ['talents', 'talent respec', 'talent respecs', [1, 3, 10, 25, 60]],
        };
    }
}
