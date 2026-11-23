<?php

namespace Database\Seeders\Population;

/**
 * The trade blocks of a session: gathering, fishing, crafting at a profession window and a rest at a campfire,
 * in the shapes of the e2e fixtures. A character keeps the same trades from session to session, so the skill it
 * reports only goes up.
 */
final class Trades
{
    private const int MINING = 186;

    private const int HERBALISM = 182;

    private const int SKINNING = 393;

    private const int FISHING = 356;

    private const int CAMPFIRE = 1307227;

    private const int NEAR_CAMPFIRE = 1283391;

    private const int SITTING = 1229739;

    private const int CAMP_BENEFITS = 1229741;

    private const int FISHING_CAST = 7620;

    private const int FISHING_POLE = 6256;

    private const int BOBBER = 35591;

    /** @var array<string, array{skill: int, name: string, recipe: int, item: int}> */
    private const array CRAFTS = [
        'smith' => ['skill' => 164, 'name' => 'Blacksmithing', 'recipe' => 2660, 'item' => 2862],
        'cook' => ['skill' => 185, 'name' => 'Cooking', 'recipe' => 2538, 'item' => 2679],
        'tailor' => ['skill' => 197, 'name' => 'Tailoring', 'recipe' => 2963, 'item' => 2996],
    ];

    private const array SPELL_NAMES = [
        2575 => 'Mining', 2366 => 'Herb Gathering', 8613 => 'Skinning', 7620 => 'Fishing',
        2660 => 'Rough Sharpening Stone', 2538 => 'Charred Wolf Meat', 2963 => 'Bolt of Linen Cloth',
        self::CAMPFIRE => 'Basic Campfire', self::NEAR_CAMPFIRE => 'Near a Campfire',
        self::SITTING => 'Sitting', self::CAMP_BENEFITS => 'Campfire Rest',
    ];

    private readonly Rng $rng;

    /** @var array<int, int> skill id => the rank the character holds now */
    private array $ranks = [];

    public function __construct(
        private readonly LineBook $book,
        private readonly CharacterPlan $plan,
        private readonly int $index,
        private readonly int $level,
    ) {
        $this->rng = Rng::for('trades:'.$plan->name.':'.$index);
    }

    /**
     * The gathering trades of a character in the order it prefers them, the same on every session.
     *
     * @return list<string>
     */
    public function gathering(): array
    {
        return Rng::for('gathering:'.$this->plan->name)->shuffled(['mine', 'herb', 'skin', 'fish']);
    }

    public function craftKey(): string
    {
        return Rng::for('crafting:'.$this->plan->name)->pick(array_keys(self::CRAFTS));
    }

    /** One gather block at the second `$at`; answers the seconds it took. */
    public function gather(int $at): int
    {
        $trade = $this->rng->pick(array_slice($this->gathering(), 0, 2));

        return match ($trade) {
            'fish' => $this->fish($at),
            'skin' => $this->skin($at),
            default => $this->node($at, $trade),
        };
    }

    private function node(int $at, string $trade): int
    {
        $entry = $this->rng->pick(World::NODES[$trade]);
        $skill = $trade === 'mine' ? self::MINING : self::HERBALISM;
        $seconds = $trade === 'mine' ? 3.0 : 2.0;
        $this->book->spell($entry['spell'], self::SPELL_NAMES[$entry['spell']]);
        $this->book->item($entry['item']);
        $this->book->at($at * 1000, 'C', $entry['spell'], $seconds);
        $this->book->at($at * 1000 + 600, 'LO', 'g', $entry['node'], 1, $entry['item'].'x'.$this->rng->interval(1, 3), 0);
        $this->skillUp($at * 1000 + 1000, $skill);

        return $this->rng->interval(25, 60);
    }

    private function skin(int $at): int
    {
        $entry = $this->rng->pick(World::NODES['skin']);
        $this->book->spell($entry['spell'], self::SPELL_NAMES[$entry['spell']]);
        $this->book->item($entry['item']);
        $this->book->creature($entry['creature']);
        $this->book->at($at * 1000 - 3000, 'K', $entry['creature'], 1);
        $this->book->at($at * 1000 - 2000, 'LO', 'c', $entry['creature'], 1, 'mx'.$this->rng->interval(8, 90), 0);
        $this->book->at($at * 1000, 'C', $entry['spell'], 1.5);
        $this->book->at($at * 1000 + 500, 'LO', 'c', $entry['creature'], 1, $entry['item'].'x'.$this->rng->interval(1, 2), 1);
        $this->skillUp($at * 1000 + 900, self::SKINNING);

        return $this->rng->interval(20, 45);
    }

    private function fish(int $at): int
    {
        $catch = $this->rng->pick([6291, 6303, 7973, 7971]);
        $this->book->spell(self::FISHING_CAST, self::SPELL_NAMES[self::FISHING_CAST]);
        $this->book->item($catch);
        $this->book->item(self::FISHING_POLE);
        $this->book->at($at * 1000, 'C', self::FISHING_CAST);
        $waited = $this->rng->float(4.5, 9.0);
        $landed = $at * 1000 + (int) ($waited * 1000);
        $this->book->at($landed, 'CH', self::FISHING_CAST, $waited);

        if ($this->rng->chance(80)) {
            $this->book->at($landed + 500, 'LO', 'g', self::BOBBER, 1, $catch.'x1', 0);
            $this->skillUp($landed + 900, self::FISHING);
        }

        return (int) ceil($waited) + $this->rng->interval(8, 20);
    }

    /** A block at a profession window: the recipe started, the crafts and the skill points they gave. */
    public function craft(int $at): int
    {
        $craft = self::CRAFTS[$this->craftKey()];
        $this->book->spell($craft['recipe'], self::SPELL_NAMES[$craft['recipe']]);
        $this->book->item($craft['item']);
        $made = $this->rng->interval(2, 6);
        $this->book->at($at * 1000, 'TS', 1, $craft['skill'], $craft['name'], $this->rankOf($craft['skill']), 300, 0, '');
        $this->book->at(($at + 1) * 1000, 'CB', $craft['recipe']);
        $this->book->at(($at + 1) * 1000 + 100, 'RD', $craft['recipe'], $this->rng->interval(0, 2), 1, 1, 0, 0, 0, 0, '');

        for ($one = 0; $one < $made; $one++) {
            $this->book->at(($at + 4 + $one * 3) * 1000, 'CR', $craft['item'], 1, 0, 0, $craft['recipe'], '', '', '', '', '', '', 0);
            $this->skillUp(($at + 4 + $one * 3) * 1000 + 800, $craft['skill']);
        }
        $end = $at + 5 + $made * 3;
        $this->book->at($end * 1000, 'CS', 0);
        $this->book->at(($end + 1) * 1000, 'TS', 0);

        return $end + 5 - $at;
    }

    /** A rest at a campfire; it takes `campSeconds()` from `$at`. */
    public function camp(int $at): void
    {
        $afk = $this->afk();
        $this->book->spell(self::CAMPFIRE, self::SPELL_NAMES[self::CAMPFIRE]);
        $this->book->spell(self::NEAR_CAMPFIRE, self::SPELL_NAMES[self::NEAR_CAMPFIRE]);
        $this->book->spell(self::SITTING, self::SPELL_NAMES[self::SITTING]);
        $this->book->spell(self::CAMP_BENEFITS, self::SPELL_NAMES[self::CAMP_BENEFITS]);
        $this->book->at($at * 1000, 'C', self::CAMPFIRE, '');
        $this->book->at(($at + 8) * 1000, 'A', 1, self::NEAR_CAMPFIRE, '', 0, '', '');
        $this->book->at(($at + 12) * 1000, 'A', 1, self::SITTING, '', 0, '', '');
        $this->book->at(($at + 15) * 1000, 'AFK', 1);
        $this->book->at(($at + 20) * 1000, 'A', 1, self::CAMP_BENEFITS, '', 3600, '', '');
        $this->book->at(($at + 20 + $afk) * 1000, 'AFK', 0);
        $this->book->at(($at + 25 + $afk) * 1000, 'A', 0, self::SITTING, $afk + 10);
        $this->book->at(($at + 30 + $afk) * 1000, 'A', 0, self::NEAR_CAMPFIRE, $afk + 25);
    }

    public function campSeconds(): int
    {
        return 40 + $this->afk();
    }

    private function afk(): int
    {
        $rng = Rng::for('afk:'.$this->plan->name.':'.$this->index);

        return $this->plan->persona === Persona::Camper ? $rng->interval(300, 1200) : $rng->interval(60, 240);
    }

    private function skillUp(int $ms, int $skill): void
    {
        $rank = $this->rankOf($skill) + 1;
        $this->ranks[$skill] = $rank;
        $this->book->at($ms, 'SL', $skill, $rank, 300, 0, '');
    }

    private function rankOf(int $skill): int
    {
        return $this->ranks[$skill] ??= min(295, 5 + $this->level * 4 + $this->index);
    }
}
