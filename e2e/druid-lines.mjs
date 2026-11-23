/**
 * The session of the fixture character Moon-Walker (e2e/fixture/Moon-Walker): six hours of a druid that plays levels 50,
 * 51 and 52 for two hours each, with the lines every module of the Druid class page reads (docs/ROWS.md, "The class
 * tables"): the forms worn with the shifts between them (some back into the form just left), the spells of each form, the
 * casts of a caster, control and the wild, Prowl, Clearcasting and the pack aura, travel on Dash and a mount, Rebirth and
 * Innervate, fights, a healing meter, and the talents Feral Charge, Omen of Clarity and Leader of the Pack. Nature's Grasp,
 * Moonkin Form, Nature's Swiftness and Swiftmend are cast or worn too, so a build that holds them has rows. Each line is
 * `[milliseconds since the session began, code, ...fields]`, as e2e/build-fixture.mjs reads them. The figures the page
 * must show are in e2e/druid-expected.mjs, written from the plan below before the page was read.
 */

const TELDRASSIL = 141;

const BEAR = 5487;
const DIRE_BEAR = 9634;
const CAT = 768;
const TRAVEL = 783;
const AQUATIC = 1066;
const MOONKIN = 24858;
const FORMS = { bear: BEAR, dire: DIRE_BEAR, cat: CAT, travel: TRAVEL, aquatic: AQUATIC, moonkin: MOONKIN };

const MAUL = 6807;
const SWIPE = 769;
const GROWL = 6795;
const ROAR = 99;
const BASH = 5211;
const CLAW = 1082;
const SHRED = 5221;
const RAKE = 1822;
const RIP = 1079;
const BITE = 22568;
const RAVAGE = 6785;
const WRATH = 5176;
const MOONFIRE = 8921;
const STARFIRE = 2912;
const HEALING_TOUCH = 5185;
const REGROWTH = 8936;
const REJUVENATION = 774;
const FAERIE_FIRE = 770;
const ROOTS = 339;
const HIBERNATE = 2637;
const SOOTHE = 2908;
const GRASP = 16689;
const CHARGE = 16979;
const OMEN = 16864;
const CLEARCASTING = 16870;
const LEADER = 17007;
const PACK = 24932;
const MOONGLADE = 18960;
const MARK = 1126;
const THORNS = 467;
const REMOVE_CURSE = 2782;
const ABOLISH = 2893;
const CURE = 526;
const DASH = 1850;
const PROWL = 5215;
const INNERVATE = 29166;
const REBIRTH = 20484;
const SWIFTNESS = 17116;
const SWIFTMEND = 18562;
const MOUNT = 458;

const TREE = 1021;
const BALANCE = 401;
const FERAL = 402;
const RESTORATION = 403;

const HOUR = 3_600_000;
const SESSION_END = 6 * HOUR;

const ms = (seconds) => Math.round(seconds * 1000);
const rep = (count, id) => Array.from({ length: count }, () => id);

class Track {
  lines = [];
  form = null;
  since = 0;

  constructor(start) {
    this.t = start;
  }

  at(seconds, ...line) {
    this.t += ms(seconds);
    this.lines.push([this.t, ...line]);
  }

  wait(seconds) {
    this.t += ms(seconds);
  }

  seconds(from) {
    return Math.round((this.t - from) / 100) / 10;
  }

  shift(to, gap) {
    if (this.form !== null) this.at(0, "A", 0, FORMS[this.form], this.seconds(this.since));
    if (gap) this.wait(gap);
    if (to !== null) {
      this.at(0, "A", 1, FORMS[to], 1, 0, 1, "");
      this.since = this.t;
    }
    this.form = to;
  }
}

const cast = (k, id) => k.at(0, "C", id, "");

function spread(k, id, count) {
  for (let at = 0; at < count; at += 1) k.at(2, "C", id, "");
}

function fight(k, { seconds, casts, charge }) {
  if (charge) {
    k.at(5, "C", CHARGE, "");
    k.wait(0.5);
  } else {
    k.wait(5);
  }
  const opened = k.t;
  k.at(0, "F", 1);
  const gap = seconds / (casts.length + 1);
  for (const id of casts) k.at(gap, "C", id, "");
  k.at(Math.max(0, seconds - (k.t - opened) / 1000), "F", 0, seconds);
}

function prowl(k, seconds, opener) {
  k.at(2, "C", PROWL, "");
  k.at(0, "A", 1, PROWL, 1, 0, 1, "");
  const gained = k.t;
  k.wait(seconds - 1);
  cast(k, opener);
  k.at(1, "A", 0, PROWL, k.seconds(gained) + 1);
}

function clearcasting(k, used) {
  k.at(5, "A", 1, CLEARCASTING, 1, 15, 1, "");
  const gained = k.t;
  if (used) {
    k.at(1.5, "C", SHRED, "");
    k.at(0.1, "A", 0, CLEARCASTING, k.seconds(gained));
    return;
  }
  k.at(15, "A", 0, CLEARCASTING, 15.0);
}

function swiftness(k, spent) {
  k.at(5, "C", SWIFTNESS, "");
  k.at(0, "A", 1, SWIFTNESS, 1, 0, 1, "");
  const gained = k.t;
  if (spent) {
    k.at(1.5, "C", HEALING_TOUCH, "");
    k.at(0.1, "A", 0, SWIFTNESS, k.seconds(gained));
    return;
  }
  k.at(30, "A", 0, SWIFTNESS, 30.0);
}

function step(k, plan) {
  k.shift(plan.form, plan.power ? 0.5 : 0);
  const began = k.t;
  const entered = k.t;
  if (plan.pack) k.at(0, "A", 1, PACK, 1, 0, 1, "");
  if (plan.mount) k.at(1, "MO", 1, MOUNT, 1, 0, "");
  const mounted = k.t;
  for (const id of plan.free ?? []) k.at(2, "C", id, "");
  if (plan.prowl) prowl(k, plan.prowl.seconds, plan.prowl.opener);
  for (const used of plan.clearcasting ?? []) clearcasting(k, used);
  for (const spent of plan.swiftness ?? []) swiftness(k, spent);
  for (const one of plan.fights ?? []) fight(k, one);
  if (plan.mount) {
    k.wait(plan.mount - (k.t - mounted) / 1000);
    k.at(0, "MO", 0, MOUNT, 1, plan.mount);
  }
  const left = plan.seconds - (k.t - began) / 1000;
  if (left < 0) throw new Error(`a step of ${plan.seconds} s needs ${plan.seconds - left} s`);
  k.wait(left);
  if (plan.pack) k.at(0, "A", 0, PACK, k.seconds(entered));
}

const BEAR_FIGHT = (counts, charge = true) => ({
  charge,
  seconds: counts.seconds,
  casts: [...rep(counts.maul, MAUL), ...rep(counts.swipe, SWIPE), ...rep(counts.growl, GROWL), ...rep(counts.roar, ROAR), ...rep(counts.bash, BASH)],
});

const CAT_FIGHT = (counts) => ({
  seconds: counts.seconds,
  casts: [...rep(counts.claw, CLAW), ...rep(counts.shred, SHRED), ...rep(counts.rake ?? 0, RAKE), ...rep(counts.rip ?? 0, RIP), ...rep(counts.bite ?? 0, BITE)],
});

const CASTER_FIGHT = (counts) => ({
  seconds: counts.seconds,
  casts: [
    ...rep(counts.wrath ?? 0, WRATH), ...rep(counts.moonfire ?? 0, MOONFIRE), ...rep(counts.starfire ?? 0, STARFIRE), ...rep(counts.touch ?? 0, HEALING_TOUCH),
    ...rep(counts.rejuvenation ?? 0, REJUVENATION), ...rep(counts.regrowth ?? 0, REGROWTH), ...rep(counts.faerie ?? 0, FAERIE_FIRE), ...rep(counts.roots ?? 0, ROOTS),
    ...rep(counts.grasp ?? 0, GRASP), ...rep(counts.swiftmend ?? 0, SWIFTMEND), ...rep(counts.rebirth ?? 0, REBIRTH),
  ],
});

const PLANS = [
  {
    start: 60_000,
    bear: "bear",
    steps: [
      { form: null, seconds: 240, free: [MARK, MARK, THORNS, MOONGLADE, REMOVE_CURSE, ABOLISH, SOOTHE, HIBERNATE, INNERVATE], fights: [CASTER_FIGHT({ seconds: 60, wrath: 4, moonfire: 2, starfire: 2, touch: 2, rejuvenation: 3, regrowth: 1, faerie: 2, roots: 2, grasp: 1, rebirth: 1 })] },
      { form: "cat", seconds: 600, pack: true, prowl: { seconds: 120, opener: RAVAGE }, clearcasting: [true, false], fights: [CAT_FIGHT({ seconds: 90, claw: 6, shred: 4, rake: 3, rip: 2, bite: 1 })] },
      { form: "cat", power: true, seconds: 300, fights: [CAT_FIGHT({ seconds: 60, claw: 4, shred: 2 })] },
      { form: "bear", seconds: 450, pack: true, fights: [BEAR_FIGHT({ seconds: 100, maul: 5, swipe: 3, growl: 1, roar: 1, bash: 1 })] },
      { form: null, seconds: 30 },
      { form: "travel", seconds: 600, free: [DASH, DASH] },
      { form: "aquatic", seconds: 150 },
      { form: null, seconds: 330, mount: 300 },
    ],
    heal: [9_000, [[REJUVENATION, 4000], [REGROWTH, 2500], [HEALING_TOUCH, 1500]]],
  },
  {
    start: 7_260_000,
    bear: "dire",
    steps: [
      { form: null, seconds: 240, free: [MARK, THORNS, MOONGLADE, CURE, REMOVE_CURSE, INNERVATE], swiftness: [true], fights: [CASTER_FIGHT({ seconds: 50, wrath: 3, moonfire: 3, starfire: 1, touch: 2, rejuvenation: 2, regrowth: 2, faerie: 1, roots: 1, grasp: 1, swiftmend: 1 })] },
      { form: "dire", seconds: 600, pack: true, fights: [BEAR_FIGHT({ seconds: 120, maul: 6, swipe: 4, growl: 2, roar: 1, bash: 2 })] },
      { form: "cat", seconds: 300, pack: true, prowl: { seconds: 120, opener: RAKE }, clearcasting: [true], fights: [CAT_FIGHT({ seconds: 60, claw: 5, shred: 3, rake: 2, rip: 1, bite: 1 })] },
      { form: "cat", power: true, seconds: 300, pack: true, fights: [CAT_FIGHT({ seconds: 40, claw: 3, shred: 2 })] },
      { form: "cat", power: true, seconds: 150 },
      { form: "travel", seconds: 300, free: [DASH] },
      { form: null, seconds: 30 },
      { form: "aquatic", seconds: 120 },
      { form: null, seconds: 180, mount: 150 },
    ],
    heal: [12_000, [[REJUVENATION, 5000], [REGROWTH, 4000], [HEALING_TOUCH, 2000]]],
  },
  {
    start: 14_460_000,
    bear: "dire",
    steps: [
      { form: null, seconds: 240, free: [MARK, THORNS, MOONGLADE, ABOLISH, REBIRTH, INNERVATE, HIBERNATE, GRASP], swiftness: [false], fights: [CASTER_FIGHT({ seconds: 40, wrath: 2, moonfire: 2, starfire: 2, touch: 1, rejuvenation: 3, regrowth: 1, faerie: 2, roots: 1, swiftmend: 1 })] },
      { form: "moonkin", seconds: 360, fights: [{ seconds: 90, casts: [] }] },
      { form: "dire", seconds: 450, pack: true, fights: [BEAR_FIGHT({ seconds: 100, maul: 7, swipe: 4, growl: 1, roar: 2, bash: 1 })] },
      { form: "dire", power: true, seconds: 300, pack: true, fights: [BEAR_FIGHT({ seconds: 60, maul: 3, swipe: 2 }, false)] },
      { form: "cat", seconds: 450, pack: true, prowl: { seconds: 120, opener: RAVAGE }, clearcasting: [true, false], fights: [CAT_FIGHT({ seconds: 90, claw: 6, shred: 4, rake: 2, rip: 2, bite: 2 })] },
      { form: "travel", seconds: 420, free: [DASH, DASH] },
      { form: "aquatic", seconds: 120 },
      { form: null, seconds: 330, mount: 300 },
    ],
    heal: [16_000, [[REJUVENATION, 7000], [REGROWTH, 3000], [HEALING_TOUCH, 3000], [SWIFTMEND, 1000]]],
  },
];

function cluster(plan) {
  const k = new Track(plan.start);
  for (const one of plan.steps) {
    const form = one.form === "dire" || one.form === "bear" ? plan.bear : one.form;
    step(k, { ...one, form });
  }
  k.shift(null, 0);
  const [total, spells] = plan.heal;
  k.at(2, "DM", "h", total, (total / 100).toFixed(1) * 1, 100.0);
  for (const [spell, amount] of spells) k.at(0.2, "DS", "h", spell, amount);
  return k.lines;
}

const SPELL_NAMES = [
  [BEAR, "Bear Form"], [DIRE_BEAR, "Dire Bear Form"], [CAT, "Cat Form"], [TRAVEL, "Travel Form"], [AQUATIC, "Aquatic Form"], [MOONKIN, "Moonkin Form"],
  [MAUL, "Maul"], [SWIPE, "Swipe"], [GROWL, "Growl"], [ROAR, "Demoralizing Roar"], [BASH, "Bash"], [CLAW, "Claw"], [SHRED, "Shred"], [RAKE, "Rake"], [RIP, "Rip"],
  [BITE, "Ferocious Bite"], [RAVAGE, "Ravage"], [WRATH, "Wrath"], [MOONFIRE, "Moonfire"], [STARFIRE, "Starfire"], [HEALING_TOUCH, "Healing Touch"],
  [REGROWTH, "Regrowth"], [REJUVENATION, "Rejuvenation"], [FAERIE_FIRE, "Faerie Fire"], [ROOTS, "Entangling Roots"], [HIBERNATE, "Hibernate"],
  [SOOTHE, "Soothe Animal"], [GRASP, "Nature's Grasp"], [CHARGE, "Feral Charge (Bear)"], [OMEN, "Omen of Clarity"], [CLEARCASTING, "Clearcasting"],
  [LEADER, "Leader of the Pack"], [PACK, "Leader of the Pack"], [MOONGLADE, "Teleport: Moonglade"], [MARK, "Mark of the Wild"], [THORNS, "Thorns"],
  [REMOVE_CURSE, "Remove Curse"], [ABOLISH, "Abolish Poison"], [CURE, "Cure Poison"], [DASH, "Dash"], [PROWL, "Prowl"], [INNERVATE, "Innervate"],
  [REBIRTH, "Rebirth"], [SWIFTNESS, "Nature's Swiftness"], [SWIFTMEND, "Swiftmend"], [MOUNT, "Brown Horse"],
];

const TALENTS = [
  ["Feral Charge (Bear)", CHARGE, FERAL, 2001, 3001],
  ["Omen of Clarity", OMEN, BALANCE, 2002, 3002],
  ["Leader of the Pack", LEADER, FERAL, 2003, 3003],
];

const talentLines = () => [
  [500, "TY", BALANCE, "Balance"],
  [510, "TY", FERAL, "Feral Combat"],
  [520, "TY", RESTORATION, "Restoration"],
  [800, "TV", TREE, 8, TALENTS.length, "5b7e19c3"],
  ...TALENTS.map(([, spell, tab, node, entry], at) => [810 + at * 10, "TD", TREE, node, 100 + at * 100, 200, "single", 1, tab, `${entry}:${spell}:1`, ""]),
  ...TALENTS.map(([, spell, tab, node, entry], at) => [1500 + at * 10, "TA", 1, node, entry, 1, "", 1, spell, tab, "s"]),
  [2000, "TG", "s", 1, 1, "", "Feral Combat", TREE, `${BALANCE}:11:0,${FERAL}:31:0,${RESTORATION}:1:0`, 0, 0, 1, ""],
];

export const DRUID_LINES = [
  [0, "S", "1.15.8", "DRUID", "NightElf", 50, "Alliance", "0.9.0", 1, "Forever", 1, 11508, 300, 1400, 120, 60],
  [1000, "Z", TELDRASSIL, 0, "none", 0, "Teldrassil", "Shadowglen"],
  ...SPELL_NAMES.map(([id, name], at) => [100 + at, "SN", id, name]),
  ...talentLines(),
  ...PLANS.flatMap((plan) => cluster(plan)),
  [2 * HOUR, "L", 51],
  [4 * HOUR, "L", 52],
  [SESSION_END, "ES"],
];
