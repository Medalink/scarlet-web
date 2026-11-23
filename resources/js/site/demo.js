// Invented demo data for both pages. No real character, player, place or creature is named here.
(function () {
  const DAY = 86400000;
  const utc = (y, m, d, h = 0, min = 0, s = 0) => Date.UTC(y, m - 1, d, h, min, s);
  function rng(seed) {
    return () => {
      seed = (seed + 0x6d2b79f5) | 0;
      let t = Math.imul(seed ^ (seed >>> 15), 1 | seed);
      t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
      return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
  }

  const num = (n) => Math.round(n).toLocaleString("en-US");
  function dur(sec) {
    const m = Math.round(sec / 60);
    if (m < 60) return m + "m";
    if (m < 1440) return Math.floor(m / 60) + "h " + String(m % 60).padStart(2, "0") + "m";
    return Math.floor(m / 1440) + "d " + Math.floor((m % 1440) / 60) + "h";
  }
  function money(copper, signed) {
    const v = Math.abs(Math.round(copper)), g = Math.floor(v / 10000), s = Math.floor((v % 10000) / 100), c = v % 100;
    const parts = [];
    if (g) parts.push(`${num(g)}<i class="g">g</i>`);
    if (g || s) parts.push(`${g ? String(s).padStart(2, "0") : s}<i class="s">s</i>`);
    if (!g) parts.push(`${s ? String(c).padStart(2, "0") : c}<i class="c">c</i>`);
    const sign = signed ? (copper < 0 ? "−" : "+") : "";
    return `<span class="money">${sign}${parts.join("")}</span>`;
  }
  const moneyText = (copper) => money(copper).replace(/<[^>]+>/g, "").replace(/([gs])(?=\d)/g, "$1 ");
  const WD = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"], MO = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
  const day = (ms) => { const d = new Date(ms); return `${WD[d.getUTCDay()]} ${d.getUTCDate()} ${MO[d.getUTCMonth()]}`; };
  const clock = (ms) => { const d = new Date(ms); return String(d.getUTCHours()).padStart(2, "0") + ":" + String(d.getUTCMinutes()).padStart(2, "0"); };

  const T0 = utc(2026, 11, 19, 19, 2, 13);
  const WAYPOINTS = [
    [2600, 6700, 0, "stand"], [2600, 6700, 40, "stand"], [2800, 6480, 95, "run"], [3000, 6250, 150, "run"], [3000, 6250, 176, "stand"],
    [3230, 6080, 232, "run"], [3230, 6080, 262, "stand"], [2950, 5900, 322, "run"], [2950, 5900, 352, "stand"],
    [3280, 5800, 415, "run"], [3280, 5800, 446, "stand"], [3080, 5620, 518, "run"], [3080, 5620, 548, "stand"],
    [3150, 5350, 640, "run"], [3200, 4800, 700, "swim"], [3350, 4300, 748, "run"], [3500, 3900, 790, "run"], [3500, 3900, 900, "stand"],
    [4300, 3520, 958, "mount"], [5100, 3220, 1008, "mount"], [5720, 3000, 1040, "mount"], [5800, 2950, 1108, "stand"], [5800, 2950, 1112, "stand"],
    [4700, 2350, 1115, "jump"], [5250, 2620, 1146, "ghost"], [5800, 2950, 1175, "ghost"], [5800, 2950, 1296, "stand"],
    [6030, 2790, 1354, "run"], [6030, 2790, 1380, "stand"], [6220, 3120, 1425, "run"], [6220, 3120, 1452, "stand"],
    [5960, 3380, 1488, "run"], [5960, 3380, 1512, "stand"], [5150, 3260, 1565, "mount"], [4300, 3560, 1610, "mount"], [3500, 3900, 1640, "mount"],
    [3500, 3900, 1765, "stand"], [7600, 3900, 1905, "taxi"], [7600, 3900, 1925, "stand"], [7760, 4060, 1960, "run"], [7760, 4060, 2270, "stand"],
  ];
  const END = 2270;
  const TAGS = {
    combat: [[150, 173], [232, 257], [322, 346], [415, 439], [518, 542], [1042, 1108], [1296, 1354], [1425, 1449], [1488, 1510]],
    dead: [[1108, 1178]],
    ghost: [[1112, 1178]],
    group: [[1209, END]],
    resting: [[0, 41], [1960, END]],
    window: [[14, 22], [792, 898], [1650, 1668], [1700, 1764]],
  };
  const PLACES = [
    { name: "Brackwater Camp", x: 2600, y: 6700, down: 1 }, { name: "Greywatch Ford", x: 3175, y: 5060, left: 1, down: 1 },
    { name: "Greywatch Post", x: 3500, y: 3900, left: 1, up: 1 }, { name: "Pale Rest", x: 4700, y: 2350, left: 1, up: 1 },
    { name: "Fellfoot", x: 6480, y: 2620 }, { name: "Hollowmere", x: 7760, y: 4060, left: 1, down: 1 },
    { name: "Saltmarsh Reach", x: 3450, y: 2700, region: 1 }, { name: "The Cold Fells", x: 7000, y: 2250, region: 1 },
  ];

  function sessionLines() {
    const L = [], add = (t, code, text, mark) => L.push({ t, code, text, mark });
    add(0, "S", "session started · level 37 Orc Warrior");
    add(0.3, "Z", "Saltmarsh Reach · Brackwater Camp");
    add(0.5, "RS", "resting, at a camp");
    add(1.4, "ST", "snapshot · level 37 · 11g 02s · health 1,912");
    add(14, "QD", "quest dialog opened · Tanner Hesk");
    add(21, "Q", "accepted · Hides for the Tanner");
    add(41, "RS", "no longer resting");
    const casts = ["Charge", "Rend", "Cleave", "Hamstring", "Charge"], coin = [214, 198, 231, 187, 226], xp = [212, 206, 219, 203, 214];
    TAGS.combat.slice(0, 5).forEach(([a, b], i) => {
      add(a, "F", "combat started");
      add(a + 5, "C", "cast " + casts[i]);
      add(b - 2, "K", "Reed Stalker died · by you", "kill");
      add(b - 1.2, "X", `+${xp[i]} XP · kill`);
      add(b, "F", `combat ended · ${b - a} s`);
      add(b + 0.8, "DM", `damage ${num(2700 + i * 137)} · ${(118 + i * 4.3).toFixed(1)} per second`);
      add(b + 3, "LO", "creature · Reed Stalker · 1 item, coin");
      add(b + 3.3, "O", "Stalker Hide ×1");
      add(b + 3.5, "M", `+${Math.floor(coin[i] / 100)}s ${coin[i] % 100}c`);
      add(b + 4, "QP", `Hides for the Tanner · ${i + 1} / 5`);
    });
    add(349, "SK", "Two-Handed Axes went up to 187");
    add(547, "QS", "Hides for the Tanner is complete");
    add(640, "W", "running · 941 yd · 92 s · 3 jumps");
    add(700, "W", "swimming · 52 yd · 60 s");
    add(742, "EX", "discovered Greywatch Ford · +310 XP");
    add(790, "W", "running · 688 yd · 90 s · 1 jump");
    add(792, "NW", "vendor window opened · Quartermaster Ibb");
    add(801, "I", "sold Cracked Fang ×9 · vendor · 31c each");
    add(806, "RE", "repaired · 14s 60c");
    add(812, "VB", "bought Rough Whetstone ×5 · 2s 50c");
    add(898, "NW", "vendor window closed · 106 s");
    add(900, "MO", "mounted · Dun Plainstrider");
    add(1031, "VG", "rare in range · Old Rimeclaw");
    add(1040, "W", "mounted · 1,318 yd · 140 s");
    add(1040.5, "MO", "dismounted · 140 s");
    add(1042, "F", "combat started");
    add(1049, "C", "cast Charge");
    add(1090, "CC", "lost control · stun");
    add(1108, "D", "you died", "death");
    add(1108.2, "DL", "died at 5800, 2950");
    add(1108.4, "F", "combat ended · 66 s");
    add(1108.6, "DR", "killing blow · Old Rimeclaw · Maul 418 · 96 overkill");
    add(1108.7, "DR", "1.9 s before · Rend Flesh 164");
    add(1108.8, "DR", "3.8 s before · Maul 391");
    add(1112, "R", "released spirit");
    add(1175, "W", "running as a ghost · 612 yd · 60 s");
    add(1178, "U", "back to life");
    add(1205, "GI", "invite from Tovin Greywake");
    add(1209, "GP", "joined a group of 2");
    add(1209.3, "GM", "Tovin Greywake is in your group");
    add(1210, "LM", "loot rule · group loot from uncommon");
    add(1296, "F", "combat started");
    add(1352, "K", "Old Rimeclaw died · by a party member", "kill");
    add(1352.8, "X", "+486 XP · kill");
    add(1354, "F", "combat ended · 58 s");
    add(1355, "DP", "damage · Tovin Greywake · 6,912");
    add(1357, "LO", "creature · Old Rimeclaw · 2 items, coin");
    add(1358, "RW", "roll offered · Rimeclaw Talon");
    add(1361, "RL", "you rolled need");
    add(1363, "LD", "Tovin Greywake rolled need · 41");
    add(1366, "RW", "you won · rolled 87");
    add(1366.4, "O", "Rimeclaw Talon ×1");
    add(1367, "LT", "Tovin Greywake looted Thick Bear Pelt ×1");
    add(1368, "M", "+9s 40c");
    add(1425, "F", "combat started");
    add(1447, "K", "Fellcrag Mauler died · by you", "kill");
    add(1447.8, "X", "+241 XP · kill");
    add(1449, "F", "combat ended · 24 s");
    add(1488, "F", "combat started");
    add(1508, "K", "Fellcrag Mauler died · by a party member", "kill");
    add(1508.8, "X", "+241 XP · kill");
    add(1510, "F", "combat ended · 22 s");
    add(1512, "MO", "mounted · Dun Plainstrider");
    add(1640, "W", "mounted · 2,104 yd · 128 s");
    add(1650, "QD", "reward window opened · Tanner Hesk");
    add(1660, "Q", "turned in · Hides for the Tanner · +18s", "quest");
    add(1660.8, "X", "+2,450 XP · quest");
    add(1661, "L", "reached level 38", "level");
    add(1661.5, "ST", "snapshot · level 38 · 11g 28s · health 1,984");
    add(1700, "NW", "flight master window opened");
    add(1764, "TF", "picked a flight to Hollowmere · 1s 10c");
    add(1765, "T", "flight path started");
    add(1905, "T", "flight path ended · 140 s");
    add(1906, "Z", "Hollowmere");
    add(1960, "RS", "resting, in an inn");
    add(1975, "HB", "hearthstone bound · Hollowmere");
    add(2262, "LK", "logout countdown started");
    add(END, "ES", "session ended");
    return L;
  }

  function pathPoints(samples, posAt) {
    const out = [];
    for (let t = 44; t < END; t += 20) {
      const a = posAt(samples, t), b = posAt(samples, t + 2);
      if (!["run", "mount", "swim", "ghost"].includes(b.mode) || (a.x === b.x && a.y === b.y)) continue;
      const facing = (Math.round((Math.atan2(-(b.x - a.x), -(b.y - a.y)) / (2 * Math.PI)) * 16) + 16) % 16;
      out.push({ t: t + 0.37, code: "P", text: `${Math.round(a.x)}, ${Math.round(a.y)} · facing ${facing}` });
    }
    return out;
  }
  function stamp(t) {
    const ms = T0 + Math.round(t * 1000) + 420, d = new Date(ms), p = (n) => String(n).padStart(2, "0");
    return `${p(d.getUTCHours())}:${p(d.getUTCMinutes())}:${p(d.getUTCSeconds())}.${p(Math.floor(d.getUTCMilliseconds() / 10))}`;
  }

  const xpMax = (L) => Math.round((L * L * 52 + L * 200) / 100) * 100;
  const BRACKET = [[1, "Emberwood"], [10, "Ironroot Vale"], [18, "Greywatch Coast"], [25, "Sunken Causeway"], [32, "Saltmarsh Reach"]];
  const zoneOf = (L) => BRACKET.filter(([from]) => L >= from).pop()[1];
  const DEATHS = [
    [11, "Ironroot Vale", "Rootgnaw Boar"], [13, "Ironroot Vale", "Vale Poacher"], [14, "Ironroot Vale", "Vale Poacher"], [16, "Ironroot Vale", "Ironroot Shaman"],
    [19, "Greywatch Coast", "Tidecaller"], [22, "Greywatch Coast", "Greywatch Deserter"], [24, "Greywatch Coast", "Tidecaller"],
    [26, "Sunken Causeway", "Causeway Lurker"], [27, "Sunken Causeway", "Causeway Lurker"], [29, "Sunken Causeway", "Drowned Sentry"], [30, "Sunken Causeway", "Causeway Lurker"], [31, "Sunken Causeway", "Silt Horror"],
    [34, "Thornkeep", "Thornkeep Warden"], [35, "Thornkeep", "Briar Matron"], [36, "Thornkeep", "Thornkeep Warden"], [36, "Thornkeep", "Thornkeep Warden"], [37, "Thornkeep", "Briar Matron"],
    [32, "Saltmarsh Reach", "Fellcrag Mauler"], [33, "Saltmarsh Reach", "Fellcrag Mauler"], [35, "Saltmarsh Reach", "Mire Lurker"], [36, "Saltmarsh Reach", "Fellcrag Mauler"], [37, "Saltmarsh Reach", "Fellcrag Mauler"],
  ];

  function character() {
    const r = rng(20261120), first = utc(2026, 8, 31), LEVEL = 38, PROGRESS = 0.38;
    const sessions = [];
    for (let d = 0; d < 80; d++) {
      const wd = new Date(first + d * DAY).getUTCDay(), weekend = wd === 0 || wd === 6;
      if (r() > (weekend ? 0.86 : 0.6)) continue;
      const n = r() < (weekend ? 0.5 : 0.24) ? 2 : 1;
      let hour = weekend ? 10 + r() * 6 : 18 + r() * 2.5;
      for (let i = 0; i < n; i++) {
        const p = Math.round((20 + Math.pow(r(), 1.7) * (weekend ? 185 : 130)) * 60);
        sessions.push({ t: first + d * DAY + Math.round(hour * 3600) * 1000, p });
        hour += p / 3600 + 0.6 + r() * 2.5;
      }
    }
    const hero = { t: T0, p: END, hero: true };
    sessions.push(hero, { t: utc(2026, 11, 19, 21, 10), p: 6720 }, { t: utc(2026, 11, 20, 18, 20), p: 7500, run: "Thornkeep" }, { t: utc(2026, 11, 20, 20, 41), p: 1980 });

    let cum = 0;
    for (const s of sessions) { s.c0 = cum; cum += s.p; s.c1 = cum; }
    const played = cum, at38 = hero.c0 + 1661;
    const w = Array.from({ length: LEVEL }, (_, i) => Math.pow(i + 1, 1.6) + 4), wsum = w.slice(0, LEVEL - 1).reduce((a, b) => a + b, 0);
    const reached = [0];
    for (let L = 1; L < LEVEL; L++) reached[L] = reached[L - 1] + (w[L - 1] / wsum) * at38;
    reached[LEVEL] = played;
    const levelAt = (c) => { let L = 1; while (L < LEVEL && reached[L] <= c) L++; return L; };
    const overlap = (s, L) => Math.max(0, Math.min(s.c1, reached[L]) - Math.max(s.c0, reached[L - 1])) / s.p;

    const DUNGEON = (L) => (L < 22 ? ["Cinderdeep Mine"] : L < 28 ? ["The Drowned Vault", "The Drowned Vault", "Cinderdeep Mine"] : L < 34 ? ["Ashen Cloister", "Ashen Cloister", "The Drowned Vault"] : ["Thornkeep", "Thornkeep", "Hollow King's Barrow", "Ashen Cloister"]);
    const runs = {};
    let balance = 0;
    for (const s of sessions) {
      s.l0 = levelAt(s.c0); s.l1 = levelAt(s.c1 - 1);
      const h = s.p / 3600;
      s.zn = [...new Set([zoneOf(s.l0), zoneOf(s.l1)])];
      if (s.l0 >= 12 && r() < 0.45) s.zn.push("Hollowmere");
      if (s.l0 >= 20 && r() < 0.25) s.zn.push("Cinder Row");
      s.r = 0;
      if (s.hero) {
        Object.assign(s, { q: 1, k: 8, g: 4475, s: 1820, zn: ["Saltmarsh Reach", "Hollowmere"] });
      } else {
        s.q = Math.round(h * (2.4 + r() * 2.4));
        s.k = Math.round(h * (28 + r() * 26));
        s.g = Math.round(h * (14 * s.l0 * s.l0 + 300) * (0.6 + r() * 0.9));
        s.s = Math.round(s.g * (0.2 + r() * 0.5));
        if (s.l0 < 30 && s.l1 >= 30) s.s += Math.round(balance * 0.7);
        if (s.run || (s.l0 >= 15 && h > 0.9 && r() < 0.34)) {
          s.r = r() < 0.15 ? 2 : 1;
          const pool = DUNGEON(s.l0), name = s.run || pool[Math.floor(r() * pool.length)];
          runs[name] = (runs[name] || 0) + s.r;
          s.zn.push(name);
          s.g = Math.round(s.g * 1.5);
        }
      }
      s.s = Math.min(s.s, balance + s.g);
      balance += s.g - s.s;
      s.m = balance; s.dd = 0; s.z = s.zn.length;
    }

    const deaths = DEATHS.map(([level, zone, killer]) => ({ level, zone, killer, c: reached[level - 1] + (0.1 + r() * 0.8) * (reached[level] - reached[level - 1]) }));
    deaths.push({ level: 37, zone: "Saltmarsh Reach", killer: "Old Rimeclaw", c: hero.c0 + 1108 });
    for (const d of deaths) (sessions.find((s) => d.c >= s.c0 && d.c < s.c1) || hero).dd++;
    const tally = (key) => Object.entries(deaths.reduce((o, d) => ((o[d[key]] = (o[d[key]] || 0) + 1), o), {})).sort((a, b) => b[1] - a[1]);

    const levels = [];
    for (let L = 1; L <= LEVEL; L++) {
      const row = { level: L, p: reached[L] - reached[L - 1], at: 0, zone: zoneOf(L), k: 0, q: 0, r: 0, g: 0, dd: deaths.filter((d) => d.level === L).length, zones: new Set() };
      for (const s of sessions) {
        const f = overlap(s, L);
        if (!f) continue;
        row.k += s.k * f; row.q += s.q * f; row.r += s.r * f; row.g += (s.g - s.s) * f;
        s.zn.forEach((z) => row.zones.add(z));
        if (!row.at) row.at = s.t + Math.max(0, reached[L - 1] - s.c0) * 1000;
      }
      const total = xpMax(L) * (L === LEVEL ? PROGRESS : 1);
      const xd = Math.min(0.42, row.r * 0.19), xe = 0.02 + r() * 0.05, xo = 0.01 + r() * 0.02, xq = (0.3 + r() * 0.16) * (1 - xd * 0.5), xk = 1 - xd - xe - xo - xq;
      row.xp = { xk: total * xk, xd: total * xd, xq: total * xq, xe: total * xe, xo: total * xo, total };
      levels.push(row);
    }

    const days = [];
    for (let d = 0; d < 84; d++) {
      const t = first + d * DAY, of = sessions.filter((s) => s.t >= t && s.t < t + DAY);
      days.push({ t, n: of.length, p: of.reduce((a, s) => a + s.p, 0), q: of.reduce((a, s) => a + s.q, 0), g: of.reduce((a, s) => a + s.g - s.s, 0), l0: of.length ? of[0].l0 : 0, l1: of.length ? of[of.length - 1].l1 : 0, z: new Set(of.flatMap((s) => s.zn)).size, future: t > utc(2026, 11, 20) });
    }
    const weekStart = utc(2026, 11, 16), wk = sessions.filter((s) => s.t >= weekStart);
    const week = { p: wk.reduce((a, s) => a + s.p, 0), r: wk.reduce((a, s) => a + s.r, 0), q: wk.reduce((a, s) => a + s.q, 0), g: wk.reduce((a, s) => a + s.g - s.s, 0), z: new Set(wk.flatMap((s) => s.zn)).size };

    return {
      name: "Maren Ashlow", level: LEVEL, race: "Orc", cls: "Warrior", faction: "Horde", zone: "Hollowmere",
      xp: Math.round(xpMax(LEVEL) * PROGRESS), xpMax: xpMax(LEVEL), played, money: balance,
      sessions, levels, days, week,
      runs: Object.entries(runs).sort((a, b) => b[1] - a[1]),
      mates: [["Tovin Greywake", 42000, 9], ["Isolde Fennmoor", 21900, 5], ["Dagny Stonehollow", 7920, 2]],
      deathZones: tally("zone"), killers: tally("killer"), deaths: deaths.length,
      lastDeath: { when: T0 + 1108000, zone: "Saltmarsh Reach", hits: [["killing blow", "Old Rimeclaw · Maul", "418 · 96 overkill"], ["1.9 s before", "Old Rimeclaw · Rend Flesh", "164"], ["3.8 s before", "Old Rimeclaw · Maul", "391"]] },
      travel: [["Running", 498200], ["Mounted", 163400], ["Swimming", 11950], ["Walking", 6300]],
      flights: { n: 46, p: 9060 },
    };
  }

  function routes() {
    const r = rng(77), hubs = [[2600, 6700], [3050, 5950], [3500, 3900], [4700, 2350], [5800, 2950], [7760, 4060], [6900, 5600], [4600, 7000], [7150, 2450], [5600, 4750], [2900, 2600], [4300, 5500]];
    const links = [[0, 1], [1, 2], [2, 4], [4, 3], [2, 3], [1, 11], [11, 7], [7, 6], [6, 5], [9, 6], [2, 9], [9, 4], [4, 8], [2, 10], [10, 3], [11, 9], [0, 11], [8, 5], [1, 10]];
    const out = [];
    for (let n = 0; n < 64; n++) {
      const [a, b] = links[Math.floor(Math.pow(r(), 1.5) * links.length)], A = hubs[a], B = hubs[b];
      const dx = B[0] - A[0], dy = B[1] - A[1], len = Math.hypot(dx, dy), nx = -dy / len, ny = dx / len;
      const bend = (r() - 0.5) * len * 0.28, pts = [];
      for (let k = 0; k <= 5; k++) {
        const u = k / 5, off = Math.sin(u * Math.PI) * bend + (k > 0 && k < 5 ? (r() - 0.5) * 150 : (r() - 0.5) * 60);
        pts.push([A[0] + dx * u + nx * off, A[1] + dy * u + ny * off]);
      }
      out.push(pts);
    }
    return { hubs, paths: out, flights: [[hubs[2], hubs[5]], [hubs[0], hubs[5]], [hubs[2], hubs[8]]] };
  }

  function heat(el, days, onPick) {
    const level = (p) => (p <= 0 ? 0 : p < 3600 ? 1 : p < 7200 ? 2 : p < 14400 ? 3 : 4);
    let months = "<span></span>", was = -1;
    for (let w = 0; w * 7 < days.length; w++) {
      const m = new Date(days[w * 7].t).getUTCMonth();
      months += `<span>${m === was ? "" : MO[m]}</span>`;
      was = m;
    }
    const names = ["Mon", "", "Wed", "", "Fri", "", ""].map((d) => `<span>${d}</span>`).join("");
    el.innerHTML = `<div class="heat-months" aria-hidden="true">${months}</div><div class="heat-cells">${names}${days.map((d, i) => `<i data-i="${i}" data-l="${level(d.p)}"${d.future ? ' class="off"' : ""}></i>`).join("")}</div>`;
    const pick = (e) => {
      const c = e.target.closest("i[data-i]");
      if (!c) return;
      for (const on of el.querySelectorAll("i.on")) on.classList.remove("on");
      c.classList.add("on");
      onPick(days[+c.dataset.i]);
    };
    el.addEventListener("pointerover", pick);
    el.addEventListener("click", pick);
  }

  window.ScarletDemo = { T0, END, WAYPOINTS, TAGS, PLACES, sessionLines, pathPoints, stamp, character, routes, heat, num, dur, money, moneyText, day, clock, xpMax };
})();
