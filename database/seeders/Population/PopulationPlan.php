<?php

namespace Database\Seeders\Population;

use App\Models\RulesetKind;
use App\Support\PlayerClass;

/**
 * The shape of the population: who plays, since when, at what level and on whose install. Everything is decided
 * here, once and from fixed keys, so a run records what the plan says and a second run plans the same people.
 */
final class PopulationPlan
{
    /** The bracket each class crowds, so the class pages find their twenty peers; a pair of classes shares one. */
    private const array DENSE = [
        'WARRIOR' => [60, 60], 'HUNTER' => [60, 60],
        'PALADIN' => [50, 59], 'PRIEST' => [50, 59],
        'ROGUE' => [40, 49], 'MAGE' => [40, 49],
        'SHAMAN' => [30, 39], 'WARLOCK' => [30, 39],
        'DRUID' => [20, 29],
    ];

    /** Accounts of the population; the first holds the development sign-in. */
    public const int ACCOUNTS = 5;

    public const int LINKED_PER_ACCOUNT = 6;

    public const int PER_UNLINKED_INSTALL = 15;

    private const array FIRST_NAMES = [
        'Iron', 'Storm', 'Grim', 'Ember', 'Thorn', 'Silver', 'Ash', 'Rune', 'Dusk', 'Dawn', 'Amber', 'Cold',
        'Wild', 'Sun', 'Moon', 'Stone', 'Frost', 'Shadow', 'Swift', 'Bright', 'Deep', 'High', 'Wind', 'Elder',
    ];

    private const array LAST_NAMES = [
        'Vanguard', 'Keeper', 'Walker', 'Strider', 'Weaver', 'Caller', 'Bearer', 'Warden',
        'Seeker', 'Mender', 'Smith', 'Binder',
    ];

    /** @var list<CharacterPlan> */
    public readonly array $characters;

    /**
     * @param  list<string>  $foreignNames  lower-case names of characters that are not the population's, never reused
     */
    public function __construct(int $perClass, array $foreignNames)
    {
        $classes = PlayerClass::cases();
        $names = $this->namePool($foreignNames);
        $characters = [];

        for ($at = 0; $at < $perClass * count($classes); $at++) {
            $characters[] = $this->character($classes[$at % count($classes)], intdiv($at, count($classes)), $at, $perClass, $names[$at]);
        }
        $this->characters = $this->parties($characters);
    }

    /**
     * Characters that run dungeons or level in company are put in parties of four by level, so the ones that group
     * name each other in their group lines.
     *
     * @param  list<CharacterPlan>  $characters
     * @return list<CharacterPlan>
     */
    private function parties(array $characters): array
    {
        $grouping = array_filter($characters, fn (CharacterPlan $one): bool => in_array($one->persona, [Persona::DungeonRunner, Persona::Leveler, Persona::Hardcore], true));
        usort($grouping, fn (CharacterPlan $one, CharacterPlan $other): int => [$one->level, $one->name] <=> [$other->level, $other->name]);
        $party = [];

        foreach (array_chunk($grouping, 4) as $chunk) {
            foreach ($chunk as $one) {
                $party[$one->name] = array_values(array_map(fn (CharacterPlan $member): string => $member->name, array_filter($chunk, fn (CharacterPlan $member): bool => $member->name !== $one->name)));
            }
        }

        return array_map(fn (CharacterPlan $one): CharacterPlan => $one->withParty($party[$one->name] ?? []), $characters);
    }

    /** The installs a population of $perClass characters of each class uses: the accounts' and the unlinked ones. */
    public static function places(int $perClass): int
    {
        $loose = max(0, $perClass * count(PlayerClass::cases()) - self::ACCOUNTS * self::LINKED_PER_ACCOUNT);

        return self::ACCOUNTS + (int) ceil($loose / self::PER_UNLINKED_INSTALL);
    }

    /** Whether a character sits in the bracket its class crowds, where every one of the twenty peers has to be public. */
    public static function crowded(CharacterPlan $one): bool
    {
        [$low, $high] = self::DENSE[$one->class->value];

        return $one->level >= $low && $one->level <= $high;
    }

    /** Whether the install is one of an account (the first ones), rather than an unlinked install. */
    public static function accountInstall(int $install): bool
    {
        return $install < self::ACCOUNTS;
    }

    /**
     * @param  list<string>  $foreignNames
     * @return list<string>
     */
    private function namePool(array $foreignNames): array
    {
        $all = [];

        foreach (self::FIRST_NAMES as $first) {
            foreach (self::LAST_NAMES as $last) {
                $all[] = $first.'-'.$last;
            }
        }
        $free = array_values(array_filter(
            Rng::for('names')->shuffled($all),
            fn (string $name): bool => ! in_array(mb_strtolower($name), $foreignNames, true),
        ));

        return $free;
    }

    private function character(PlayerClass $class, int $nth, int $at, int $perClass, string $name): CharacterPlan
    {
        $rng = Rng::for('character:'.$name);
        $dense = max(1, $perClass - 2);
        [$low, $high] = self::DENSE[$class->value];
        $level = match (true) {
            $nth < $dense => $rng->interval($low, $high),
            $nth === $dense => $rng->interval(10, 19),
            default => $rng->interval(1, 9),
        };
        $persona = $at < 8 ? Persona::cases()[$at % count(Persona::cases())] : Persona::draw($rng);
        $kind = $this->kind($persona, $rng);
        $race = $rng->pick(World::racesOf($class));
        $linked = self::ACCOUNTS * self::LINKED_PER_ACCOUNT;

        return new CharacterPlan(
            name: $name,
            class: $class,
            race: $race,
            persona: $persona,
            kind: $kind,
            realm: World::realmFor($kind === RulesetKind::Hardcore ? RulesetKind::Normal : $kind, $rng),
            level: $level,
            install: $at < $linked ? intdiv($at, self::LINKED_PER_ACCOUNT) : self::ACCOUNTS + intdiv($at - $linked, self::PER_UNLINKED_INSTALL),
            firstSeenDays: $this->firstSeen($level, $rng),
            account: $at < $linked ? intdiv($at, self::LINKED_PER_ACCOUNT) : null,
        );
    }

    private function kind(Persona $persona, Rng $rng): RulesetKind
    {
        if ($persona === Persona::Hardcore) {
            return RulesetKind::Hardcore;
        }
        if ($persona === Persona::PvPer) {
            return $rng->chance(70) ? RulesetKind::Pvp : RulesetKind::RoleplayPvp;
        }
        $roll = $rng->interval(1, 100);

        return match (true) {
            $roll <= 80 => RulesetKind::Normal,
            $roll <= 95 => RulesetKind::Roleplay,
            default => RulesetKind::Pvp,
        };
    }

    /** The days ago a character of this level was first recorded: the higher the level, the longer ago. */
    private function firstSeen(int $level, Rng $rng): int
    {
        $pace = $rng->float(0.75, 1.25);

        return min(58, max(1, (int) ceil($level * $level / 62 * $pace)));
    }
}
