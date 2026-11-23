<?php

namespace Database\Seeders\Population;

use App\Models\RulesetKind;
use App\Support\PlayerClass;
use InvalidArgumentException;

/**
 * One character of the population before anything is recorded: who it is, who plays it and what it does.
 */
final readonly class CharacterPlan
{
    /** The xp the game asks for to leave each level, from level 1 (the decoder's own table). */
    private const array NEED = [
        400, 900, 1400, 2100, 2800, 3600, 4500, 5400, 6500, 7600, 8800, 10100, 11400, 12900, 14400,
        16000, 17700, 19400, 21300, 23200, 25200, 27300, 29400, 31700, 34000, 36400, 38900, 41400,
        44300, 47400, 50800, 54500, 58600, 62800, 67100, 71600, 76100, 80800, 85700, 90700, 95800,
        101000, 106300, 111800, 117500, 123200, 129100, 135100, 141200, 147500, 153900, 160400, 167100,
        173900, 180800, 187900, 195000, 202300, 209800,
    ];

    public function __construct(
        public string $name,
        public PlayerClass $class,
        public string $race,
        public Persona $persona,
        public RulesetKind $kind,
        public int $realm,
        public int $level,
        public int $install,
        public int $firstSeenDays,
        public ?int $account,
        /** @var list<string> the other characters it groups with */
        public array $party = [],
    ) {}

    /** @param  list<string>  $party */
    public function withParty(array $party): self
    {
        return new self($this->name, $this->class, $this->race, $this->persona, $this->kind, $this->realm, $this->level, $this->install, $this->firstSeenDays, $this->account, $party);
    }

    public function faction(): int
    {
        return World::factionOf($this->race);
    }

    public function factionName(): string
    {
        return $this->faction() === World::ALLIANCE ? 'Alliance' : 'Horde';
    }

    public function homeZone(): int
    {
        return World::HOME_ZONE[$this->race];
    }

    public static function need(int $level): int
    {
        if ($level < 1 || $level > 60) {
            throw new InvalidArgumentException("No xp curve for level $level.");
        }

        return self::NEED[$level - 1];
    }
}
