/**
 * Reads the lines of a class fixture the way docs/ROWS.md says the tables count them, so the figures a class page must
 * show are worked out from the lines and not from the page or the decoder: casts by spell name, fights, the time an
 * aura was up inside fights, killing blows after a spell, what a fight opened with, deaths after a spell and casts under
 * an aura. Times are milliseconds since the session began.
 */

const KILL_LOOKBACK = 3000;
const OPEN_BEFORE = 1500;
const OPEN_AFTER = 3000;
const DEATH_LOOKBACK = 10000;
const UNDER_GRACE = 300;

export function readLines(lines) {
  const sorted = [...lines].sort((a, b) => a[0] - b[0]);
  const spells = new Map();
  const items = new Map();
  const levels = [[0, Number(sorted.find((line) => line[1] === "S")[5])]];
  const casts = [];
  const fights = [];
  const kills = [];
  const deaths = [];
  const meters = [];
  const top = [];
  const gear = [];
  const gains = new Map();
  const spans = new Map();
  let end = 0;
  let open = null;
  for (const [t, code, ...f] of sorted) {
    end = Math.max(end, t);
    if (code === "SN") spells.set(Number(f[0]), f[1]);
    if (code === "IN") items.set(Number(f[0]), f[1]);
    if (code === "L") levels.push([t, Number(f[0])]);
    if (code === "C") casts.push({ t, id: Number(f[0]), level: levels.at(-1)[1] });
    if (code === "F" && Number(f[0]) === 1) open = t;
    if (code === "F" && Number(f[0]) === 0 && open !== null) {
      fights.push({ start: open, end: t, level: levels.at(-1)[1] });
      open = null;
    }
    if (code === "DM") meters.push({ t, meter: f[0], total: Number(f[1]), level: levels.at(-1)[1] });
    if (code === "DS") top.push({ meter: f[0], spell: Number(f[1]), amount: Number(f[2]) });
    if (code === "G") gear.push({ t, slot: Number(f[0]), item: Number(f[1]) });
    if (code === "K") kills.push({ t, by: Number(f[1]), level: levels.at(-1)[1] });
    if (code === "D") deaths.push({ t, level: levels.at(-1)[1] });
    if (code === "A" && Number(f[0]) === 1 && String(f[4]) === "1" && f[5] !== "s") gains.set(Number(f[1]), t);
    if (code === "A" && Number(f[0]) === 0 && gains.has(Number(f[1]))) {
      const id = Number(f[1]);
      spans.set(id, [...(spans.get(id) ?? []), { start: gains.get(id), end: t }]);
      gains.delete(id);
    }
  }
  for (const [id, start] of gains) spans.set(id, [...(spans.get(id) ?? []), { start, end }]);
  const named = casts.map((cast) => ({ ...cast, name: spells.get(cast.id) }));
  const ids = (...names) => [...spells].filter(([, name]) => names.includes(name)).map(([id]) => id);
  const overlap = (a, b) => Math.max(0, Math.min(a.end, b.end) - Math.max(a.start, b.start));

  const facts = {
    end,
    spells,
    items,
    casts: named,
    fights,
    kills,
    deaths,
    meterTotal: (meter, level) => meters.filter((one) => one.meter === meter && (level === undefined || one.level === level)).reduce((sum, one) => sum + one.total, 0),
    meterFights: (meter, level) => meters.filter((one) => one.meter === meter && (level === undefined || one.level === level)).length,
    meterSpells: (meter) => {
      const by = new Map();
      for (const one of top.filter((row) => row.meter === meter)) by.set(spells.get(one.spell), (by.get(spells.get(one.spell)) ?? 0) + one.amount);
      return [...by].sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0]));
    },
    gear: (slot) => {
      const own = gear.filter((one) => one.slot === slot);
      const by = new Map();
      own.forEach((one, at) => {
        if (one.item === 0) return;
        const span = { start: one.t, end: own[at + 1]?.t ?? end };
        const held = by.get(one.item) ?? { ms: 0, fightMs: 0 };
        held.ms += span.end - span.start;
        held.fightMs += fights.reduce((sum, fight) => sum + overlap(span, fight), 0);
        by.set(one.item, held);
      });
      return by;
    },
    castCount: (...names) => named.filter((cast) => names.includes(cast.name)).length,
    castLevels: (name) => named.filter((cast) => cast.name === name).map((cast) => cast.level),
    fightMs: (level) => fights.filter((fight) => level === undefined || fight.level === level).reduce((sum, fight) => sum + fight.end - fight.start, 0),
    inFight: (t) => fights.some((fight) => t >= fight.start && t <= fight.end),
    auraSpans: (...names) => ids(...names).flatMap((id) => spans.get(id) ?? []),
    auraStarts: (...names) => facts.auraSpans(...names).length,
    auraMs: (...names) => facts.auraSpans(...names).reduce((sum, span) => sum + span.end - span.start, 0),
    auraFightMs: (name, level) => facts.auraSpans(name).reduce((sum, span) => sum + fights.filter((fight) => level === undefined || fight.level === level).reduce((inner, fight) => inner + overlap(span, fight), 0), 0),
    killBlowsAfter: (...names) => kills.filter((kill) => kill.by === 1).filter((kill) => {
      const last = named.filter((cast) => cast.t <= kill.t && kill.t - cast.t <= KILL_LOOKBACK).at(-1);
      return last !== undefined && names.includes(last.name);
    }).length,
    killBlows: () => kills.filter((kill) => kill.by === 1).length,
    kills: () => kills.length,
    firstCasts: () => fights.map((fight) => named.find((cast) => cast.t >= fight.start - OPEN_BEFORE && cast.t <= fight.start + OPEN_AFTER)?.name ?? null),
    deathsAfter: (names, seconds) => deaths.filter((death) => {
      const last = new Map();
      for (const cast of named.filter((one) => one.t <= death.t && death.t - one.t <= DEATH_LOOKBACK)) last.set(cast.name, cast);
      return names.some((name) => last.has(name) && Math.floor((death.t - last.get(name).t) / 1000) < seconds);
    }).length,
    castsUnder: (auraName) => named.filter((cast) => facts.auraSpans(auraName).some((span) => cast.t >= span.start && cast.t <= span.end + UNDER_GRACE)).length,
  };

  return facts;
}

export const number = (value) => Math.round(value).toLocaleString("en-US");
export const percent = (value) => `${number(value)}%`;
export const decimal = (value, places = 1) => value.toLocaleString("en-US", { minimumFractionDigits: places, maximumFractionDigits: places });
export const hours = (value) => (Math.abs(value) < 10 ? decimal(value) : number(value));
