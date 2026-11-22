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
    case BattleCry = 'battle-cry';
    case Charger = 'charger';
    case Executioner = 'executioner';
    case StanceDancer = 'stance-dancer';
    case MortalStriker = 'mortal-striker';
    case Overpowering = 'overpowering';
    case Bloodletter = 'bloodletter';
    case WeaponMaster = 'weapon-master';
    case Bloodthirsty = 'bloodthirsty';
    case Whirling = 'whirling';
    case SeeingRed = 'seeing-red';
    case Berserker = 'berserker';
    case Sunderer = 'sunderer';
    case Vengeful = 'vengeful';
    case Blockade = 'blockade';
    case Shieldbearer = 'shieldbearer';
    case Judicator = 'judicator';
    case Benediction = 'benediction';
    case LastResort = 'last-resort';
    case Untouchable = 'untouchable';
    case Radiant = 'radiant';
    case Quickening = 'quickening';
    case Purifier = 'purifier';
    case Lightbringer = 'lightbringer';
    case Bastion = 'bastion';
    case Lawbringer = 'lawbringer';
    case Consecrator = 'consecrator';
    case Templar = 'templar';
    case Sealbearer = 'sealbearer';
    case Hammerfall = 'hammerfall';
    case Zealot = 'zealot';
    case Crusader = 'crusader';
    case PlayingDead = 'playing-dead';
    case Trapper = 'trapper';
    case MarkedPrey = 'marked-prey';
    case BeastTamer = 'beast-tamer';
    case Unleashed = 'unleashed';
    case Intimidating = 'intimidating';
    case PetMender = 'pet-mender';
    case PackLeader = 'pack-leader';
    case Deadeye = 'deadeye';
    case Spreadshot = 'spreadshot';
    case ArcaneArcher = 'arcane-archer';
    case Sharpshooter = 'sharpshooter';
    case CloseQuarters = 'close-quarters';
    case WingClipper = 'wing-clipper';
    case Escapist = 'escapist';
    case Survivalist = 'survivalist';
    case Unseen = 'unseen';
    case Pickpocket = 'pickpocket';
    case Locksmith = 'locksmith';
    case VanishingAct = 'vanishing-act';
    case Eviscerator = 'eviscerator';
    case Bleeder = 'bleeder';
    case ColdBlooded = 'cold-blooded';
    case Assassin = 'assassin';
    case Cutthroat = 'cutthroat';
    case Gouger = 'gouger';
    case BladeDancer = 'blade-dancer';
    case Swashbuckler = 'swashbuckler';
    case FirstStrike = 'first-strike';
    case Sandman = 'sandman';
    case Bloodspiller = 'bloodspiller';
    case Shadowstalker = 'shadowstalker';
    case Fortifier = 'fortifier';
    case Aegis = 'aegis';
    case Puppeteer = 'puppeteer';
    case Shrieker = 'shrieker';
    case InnerFlame = 'inner-flame';
    case Dispeller = 'dispeller';
    case Infuser = 'infuser';
    case Disciplinarian = 'disciplinarian';
    case Mender = 'mender';
    case Renewer = 'renewer';
    case PrayerLeader = 'prayer-leader';
    case HighPriest = 'high-priest';
    case MindFlayer = 'mind-flayer';
    case PainBringer = 'pain-bringer';
    case MindBlaster = 'mind-blaster';
    case Voidspeaker = 'voidspeaker';
    case Totemic = 'totemic';
    case PackRunner = 'pack-runner';
    case ShockTherapy = 'shock-therapy';
    case TwiceBorn = 'twice-born';
    case BoltThrower = 'bolt-thrower';
    case ChainReaction = 'chain-reaction';
    case Purger = 'purger';
    case Stormcaller = 'stormcaller';
    case Stormstriker = 'stormstriker';
    case WeaponImbuer = 'weapon-imbuer';
    case Charged = 'charged';
    case Spiritwalker = 'spiritwalker';
    case WaveRider = 'wave-rider';
    case ChainHealer = 'chain-healer';
    case TideTurner = 'tide-turner';
    case Tidecaller = 'tidecaller';
    case SheepHerder = 'sheep-herder';
    case PortalMaster = 'portal-master';
    case Quartermaster = 'quartermaster';
    case Blinker = 'blinker';
    case MissileBarrage = 'missile-barrage';
    case Detonator = 'detonator';
    case Channeler = 'channeler';
    case Arcanist = 'arcanist';
    case Firestarter = 'firestarter';
    case Pyromaniac = 'pyromaniac';
    case Scorcher = 'scorcher';
    case Pyromancer = 'pyromancer';
    case Frostbinder = 'frostbinder';
    case Shatterer = 'shatterer';
    case Iceborn = 'iceborn';
    case Cryomancer = 'cryomancer';
    case SoulHarvester = 'soul-harvester';
    case LifeTapper = 'life-tapper';
    case Fearmonger = 'fearmonger';
    case Summoner = 'summoner';
    case Corruptor = 'corruptor';
    case Agonizer = 'agonizer';
    case Leech = 'leech';
    case Hexweaver = 'hexweaver';
    case DemonCaller = 'demon-caller';
    case Funneler = 'funneler';
    case Pactbinder = 'pactbinder';
    case Demonologist = 'demonologist';
    case BoltSlinger = 'bolt-slinger';
    case Immolator = 'immolator';
    case Searing = 'searing';
    case Destroyer = 'destroyer';
    case Shapeshifter = 'shapeshifter';
    case WildGift = 'wild-gift';
    case BackFromTheBrink = 'back-from-the-brink';
    case Entangler = 'entangler';
    case Wrathbringer = 'wrathbringer';
    case Starcaller = 'starcaller';
    case Moonlit = 'moonlit';
    case Stargazer = 'stargazer';
    case Mauler = 'mauler';
    case Shredder = 'shredder';
    case Savage = 'savage';
    case Wildheart = 'wildheart';
    case Greenthumb = 'greenthumb';
    case Rejuvenator = 'rejuvenator';
    case Swiftmender = 'swiftmender';
    case Grovekeeper = 'grovekeeper';
    case Caretaker = 'caretaker';
    case Lifegiver = 'lifegiver';
    case MiracleWorker = 'miracle-worker';
    case FieldMedic = 'field-medic';
    case Chaplain = 'chaplain';
    case RaidMedic = 'raid-medic';
    case GuardianAngel = 'guardian-angel';
    case Resurrector = 'resurrector';
    case Cleanser = 'cleanser';
    case BattleMedic = 'battle-medic';
    case Stalwart = 'stalwart';
    case DamageSponge = 'damage-sponge';
    case Unbreakable = 'unbreakable';
    case Vanguard = 'vanguard';
    case Bulwark = 'bulwark';
    case Juggernaut = 'juggernaut';
    case Immovable = 'immovable';
    case Provoker = 'provoker';
    case Frontliner = 'frontliner';
    case FirstToFall = 'first-to-fall';
    case Brawler = 'brawler';
    case Bruiser = 'bruiser';
    case Onslaught = 'onslaught';
    case Skirmisher = 'skirmisher';
    case Headhunter = 'headhunter';
    case Warbringer = 'warbringer';
    case Untouched = 'untouched';
    case Silencer = 'silencer';
    case Cleaver = 'cleaver';
    case Duelist = 'duelist';
    case Longshot = 'longshot';
    case Artillery = 'artillery';
    case Barrage = 'barrage';
    case Backliner = 'backliner';
    case Spellslinger = 'spellslinger';
    case Siegebreaker = 'siegebreaker';
    case OutOfReach = 'out-of-reach';
    case Warden = 'warden';
    case Sniper = 'sniper';
    case Bombardier = 'bombardier';

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

    /** The feat's card: icon, singular and plural noun, thresholds (App\Support\FeatRows).
     *
     * @return array{0: string, 1: string, 2: string, 3: array{0: int, 1: int, 2: int, 3: int, 4: int}}
     */
    private function row(): array
    {
        return FeatRows::row($this);
    }
}
