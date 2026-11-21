(function () {
  const M = window.ScarletMap, D = window.ScarletDemo, ch = D.character();
  const $ = (s) => document.querySelector(s);
  const set = (id, html) => { $(id).innerHTML = html; };
  const pct = (a, b) => Math.round((a / b) * 100);
  const plural = (n, one, many) => `${D.num(n)} ${n === 1 ? one : many || one + "s"}`;

  // Header -----------------------------------------------------------------------
  set("#c-line", `Level ${ch.level} ${ch.race} ${ch.cls}`);
  set("#c-zone", `in ${ch.zone}`);
  set("#c-played", `${D.dur(ch.played)} played`);
  $("#c-bar").style.width = pct(ch.xp, ch.xpMax) + "%";
  set("#c-xp", `${pct(ch.xp, ch.xpMax)}% to level ${ch.level + 1} · ${D.num(ch.xpMax - ch.xp)} XP to go`);

  // Every path walked: many thin lines, so shared routes add up to a glow -----------
  const stage = $(".ch-head .stage"), canvas = stage.querySelector("canvas"), R = D.routes();
  const BOX = { x0: 1900, x1: 8100, y0: 1900, y1: 7300 };
  const DIED = [[5800, 2950], [6150, 3250], [3100, 5800], [5500, 4650], [4400, 5450], [6750, 5400]];
  function map() {
    const r = stage.getBoundingClientRect();
    if (!r.width || !r.height) return;
    const wide = getComputedStyle($(".ch-head")).display === "grid";
    const view = M.fit(BOX, wide ? { x: r.width * 0.4, y: 78, w: r.width * 0.58, h: r.height - 78 - 64 } : { x: 14, y: 14, w: r.width - 28, h: r.height - 28 });
    M.terrain(canvas, view, r.width, r.height);
    const ctx = canvas.getContext("2d");
    ctx.lineCap = ctx.lineJoin = "round";
    ctx.globalCompositeOperation = "lighter";
    for (const pass of [[7, "rgba(255,45,85,0.035)"], [1.5, "rgba(255,45,85,0.3)"]]) {
      ctx.lineWidth = pass[0]; ctx.strokeStyle = pass[1];
      for (const p of R.paths) {
        ctx.beginPath();
        M.smooth(p, 8).forEach(([x, y], i) => (i ? ctx.lineTo(M.px(view, x), M.py(view, y)) : ctx.moveTo(M.px(view, x), M.py(view, y))));
        ctx.stroke();
      }
    }
    ctx.globalCompositeOperation = "source-over";
    ctx.setLineDash([7, 6]); ctx.lineWidth = 1.4; ctx.strokeStyle = "rgba(255,107,142,0.7)";
    for (const [a, b] of R.flights) { ctx.beginPath(); ctx.moveTo(M.px(view, a[0]), M.py(view, a[1])); ctx.lineTo(M.px(view, b[0]), M.py(view, b[1])); ctx.stroke(); }
    ctx.setLineDash([]);
    M.labels(ctx, view, D.PLACES.filter((p) => !p.region));
    for (const [x, y] of DIED) M.mark(ctx, "death", M.px(view, x), M.py(view, y));
  }
  let mw = 0;
  new ResizeObserver(() => { if (stage.clientWidth !== mw) { mw = stage.clientWidth; map(); } }).observe(stage);
  document.fonts.ready.then(map);

  // This week ------------------------------------------------------------------------
  set("#w-p", D.dur(ch.week.p)); set("#w-r", ch.week.r); set("#w-q", ch.week.q); set("#w-g", D.money(ch.week.g, true)); set("#w-z", ch.week.z);

  // Experience by level -------------------------------------------------------------------
  const bars = $("#xp-bars"), out = $("#xp-out"), SRC = [["xk", "kills"], ["xd", "dungeon kills"], ["xq", "quests"], ["xe", "exploring"], ["xo", "other"]];
  const maxXp = Math.max(...ch.levels.map((l) => l.xp.total)), maxP = Math.max(...ch.levels.map((l) => l.p));
  let mode = "xp", pinned = ch.level, shown = 0;
  bars.innerHTML = ch.levels.map(() => '<div class="xp-bar"><i class="c-kill"></i><i class="c-dungeon"></i><i class="c-quest"></i><i class="c-explore"></i><i class="c-other"></i></div>').join("");
  set("#xp-x", ch.levels.map((l) => `<span>${l.level === 1 || l.level % 5 === 0 || l.level === ch.level ? l.level : ""}</span>`).join(""));
  function paint() {
    [...bars.children].forEach((bar, i) => {
      const l = ch.levels[i];
      [...bar.children].forEach((seg, k) => {
        seg.style.height = (mode === "xp" ? (l.xp[SRC[k][0]] / maxXp) * 100 : k === 0 ? (l.p / maxP) * 100 : 0) + "%";
        seg.className = mode === "time" && k === 0 ? "c-time" : ["c-kill", "c-dungeon", "c-quest", "c-explore", "c-other"][k];
        seg.style.borderRadius = mode === "time" && k === 0 ? "3px 3px 1px 1px" : "";
      });
    });
    const top = mode === "xp" ? `${Math.round(maxXp / 1000)}k XP` : `${Math.round(maxP / 3600)}h`, mid = mode === "xp" ? `${Math.round(maxXp / 2000)}k` : `${(maxP / 7200).toFixed(1)}h`;
    set("#xp-y", `<span>${top}</span><span>${mid}</span><span>0</span>`);
    $("#xp-key").hidden = mode === "time";
    for (const b of document.querySelectorAll("[data-xp]")) b.setAttribute("aria-pressed", String(b.dataset.xp === mode));
    read(pinned);
  }
  // live: the reader is pointing at a level, so the others step back. At rest every bar is full strength.
  function read(level, live) {
    shown = level;
    const l = ch.levels[level - 1];
    [...bars.children].forEach((b, i) => b.classList.toggle("on", i === level - 1));
    bars.classList.toggle("has-on", !!live);
    const head = `<b>Level ${level}${level === ch.level ? " so far" : ""}</b>`;
    out.innerHTML = mode === "xp"
      ? `${head} · ${D.num(l.xp.total)} XP · ` + SRC.filter(([k]) => l.xp[k] / l.xp.total >= 0.005).map(([k, name]) => `${name} ${pct(l.xp[k], l.xp.total)}%`).join(" · ")
      : `${head} · ${D.dur(l.p)} · reached ${D.day(l.at)} · mostly in ${l.zone} · ${plural(Math.round(l.k), "kill")} · ${plural(Math.round(l.q), "quest")} · ${plural(l.dd, "death")}`;
  }
  const levelAt = (e) => { const r = bars.getBoundingClientRect(); return Math.max(1, Math.min(ch.level, Math.floor(((e.clientX - r.left) / r.width) * ch.level) + 1)); };
  bars.addEventListener("pointermove", (e) => { if (e.pointerType === "mouse") read(levelAt(e), true); });
  bars.addEventListener("pointerleave", () => read(pinned));
  bars.addEventListener("click", (e) => { pinned = levelAt(e); read(pinned, true); });
  bars.addEventListener("blur", () => read(pinned));
  bars.addEventListener("keydown", (e) => {
    const step = e.key === "ArrowLeft" ? -1 : e.key === "ArrowRight" ? 1 : 0;
    if (!step) return;
    e.preventDefault();
    pinned = Math.max(1, Math.min(ch.level, shown + step));
    read(pinned, true);
  });
  for (const b of document.querySelectorAll("[data-xp]")) b.addEventListener("click", () => { mode = b.dataset.xp; paint(); });
  paint();

  // Gold ---------------------------------------------------------------------------------------
  const pts = ch.sessions.slice(-30).map((s) => ({ t: s.t + s.p * 1000, m: s.m })), svg = $("#g-svg"), gOut = $("#g-out");
  const lo = Math.min(...pts.map((p) => p.m)), hi = Math.max(...pts.map((p) => p.m)), gx = (i) => (i / (pts.length - 1)) * 300, gy = (m) => 94 - ((m - lo) / (hi - lo || 1)) * 86;
  const line = pts.map((p, i) => `${i ? "L" : "M"}${gx(i).toFixed(1)} ${gy(p.m).toFixed(1)}`).join("");
  svg.innerHTML = `<path d="${line}L300 100L0 100Z" fill="rgba(217,178,95,0.1)"/><path d="${line}" fill="none" stroke="#d9b25f" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/><line id="g-x" y1="0" y2="100" stroke="rgba(240,241,243,0.5)" stroke-width="1" vector-effect="non-scaling-stroke" visibility="hidden"/>`;
  set("#g-bal", D.money(ch.money));
  const seven = ch.money - ch.sessions[ch.sessions.length - 8].m;
  set("#g-chg", `${D.money(seven, true)} over the last 7 sessions`);
  set("#g-from", D.day(pts[0].t)); set("#g-to", D.day(pts[pts.length - 1].t));
  const gRest = () => { gOut.innerHTML = `Between ${D.money(lo)} and ${D.money(hi)} over the last 30 sessions`; $("#g-x").setAttribute("visibility", "hidden"); };
  svg.addEventListener("pointermove", (e) => {
    const r = svg.getBoundingClientRect(), i = Math.max(0, Math.min(pts.length - 1, Math.round(((e.clientX - r.left) / r.width) * (pts.length - 1))));
    const cross = $("#g-x");
    cross.setAttribute("x1", gx(i)); cross.setAttribute("x2", gx(i)); cross.setAttribute("visibility", "visible");
    gOut.innerHTML = `<b>${D.day(pts[i].t)} ${D.clock(pts[i].t)}</b> · ${D.money(pts[i].m)}`;
  });
  svg.addEventListener("pointerleave", gRest);
  gRest();

  // Ranked lists ---------------------------------------------------------------------------------
  const rank = (id, rows) => { const top = Math.max(...rows.map((r) => r[1])); set(id, rows.map(([who, v, val]) => `<li><span class="who">${who}</span><span class="val">${val}</span><div class="track"><i style="width:${(v / top) * 100}%"></i></div></li>`).join("")); };
  rank("#runs", ch.runs.slice(0, 5).map(([n, v]) => [n, v, plural(v, "run")]));
  set("#runs-total", `${plural(ch.runs.reduce((a, r) => a + r[1], 0), "run")} in total`);
  rank("#mates", ch.mates.map(([n, p, s]) => [n, p, `${D.dur(p)} · ${plural(s, "session")}`]));
  rank("#deaths", ch.deathZones.slice(0, 4).map(([n, v]) => [n, v, v]));
  set("#killer", `${ch.deaths} deaths. Killed most by ${ch.killers[0][0]}, ${ch.killers[0][1]} times.`);
  set("#recap-h", `Last death: ${D.day(ch.lastDeath.when)} ${D.clock(ch.lastDeath.when)}, ${ch.lastDeath.zone}`);
  set("#recap", ch.lastDeath.hits.map((h) => `<li><span>${h[0]}</span><span>${h[1]}</span><span>${h[2]}</span></li>`).join(""));
  rank("#travel", ch.travel.map(([n, v]) => [n, v, `${D.num(v)} yd`]));
  set("#flights", `${ch.flights.n} flight paths, ${D.dur(ch.flights.p)} in the air.`);

  // Play time by day --------------------------------------------------------------------------------
  const hOut = $("#heat-out");
  const say = (d) => { hOut.innerHTML = d.p ? `<b>${D.day(d.t)}</b> · ${D.dur(d.p)} in ${plural(d.n, "session")}` : `<b>${D.day(d.t)}</b> · not played`; };
  D.heat($("#heat"), ch.days, say);
  say(ch.days.filter((d) => d.p).pop());

  // Play history ----------------------------------------------------------------------------------------
  const hist = $("#hist"), more = $("#hist-more"), span = (a, b) => (a === b ? a : `${a} → ${b}`), net = (g) => `<td class="n ${g < 0 ? "neg" : "pos"}">${D.money(g, true)}</td>`;
  const VIEWS = {
    sessions: { head: ["When", "Played", "Levels", "Quests", "Gold", "Zones"], name: "sessions", rows: () => ch.sessions.slice().reverse().map((s) => `<td>${D.day(s.t)} ${D.clock(s.t)}</td><td>${D.dur(s.p)}</td><td>${span(s.l0, s.l1)}</td><td class="n">${s.q}</td>${net(s.g - s.s)}<td class="n">${s.z}</td>`) },
    days: { head: ["Day", "Played", "Levels", "Quests", "Gold", "Zones"], name: "days", rows: () => ch.days.filter((d) => d.p).reverse().map((d) => `<td>${D.day(d.t)}</td><td>${D.dur(d.p)}</td><td>${span(d.l0, d.l1)}</td><td class="n">${d.q}</td>${net(d.g)}<td class="n">${d.z}</td>`) },
    levels: { head: ["Level", "Played", "Reached", "Quests", "Gold", "Zones"], name: "levels", rows: () => ch.levels.slice().reverse().map((l) => `<td>Level ${l.level}</td><td>${D.dur(l.p)}</td><td>${D.day(l.at)}</td><td class="n">${Math.round(l.q)}</td>${net(l.g)}<td class="n">${l.zones.size}</td>`) },
  };
  let view = "sessions", all = false;
  function table() {
    const v = VIEWS[view], rows = v.rows();
    hist.innerHTML = `<thead><tr>${v.head.map((h, i) => `<th scope="col"${i > 2 ? ' class="n"' : ""}>${h}</th>`).join("")}</tr></thead><tbody>${(all ? rows : rows.slice(0, 10)).map((r) => `<tr>${r}</tr>`).join("")}</tbody>`;
    more.textContent = all ? "Show the latest 10" : `Show all ${rows.length} ${v.name}`;
    more.hidden = rows.length <= 10;
    for (const b of document.querySelectorAll("[data-hist]")) b.setAttribute("aria-pressed", String(b.dataset.hist === view));
  }
  for (const b of document.querySelectorAll("[data-hist]")) b.addEventListener("click", () => { view = b.dataset.hist; all = false; table(); });
  more.addEventListener("click", () => { all = !all; table(); });
  table();

  // The in-game page, with the same rows --------------------------------------------------------------------
  const cur = ch.levels[ch.level - 1], dl = (rows) => rows.map(([k, v]) => `<div><dt>${k}</dt><dd>${v}</dd></div>`).join("");
  set("#i-line", `Level ${ch.level} · ${ch.zone}`);
  set("#i-level", `${pct(ch.xp, ch.xpMax)}% to ${ch.level + 1} · this level ${D.dur(cur.p)} so far`);
  set("#i-week", dl([["Played", D.dur(ch.week.p)], ["Quests", ch.week.q], ["Gold", D.money(ch.week.g, true)], ["Dungeons", ch.week.r]]));
  set("#i-cur-h", `Level ${ch.level} so far`);
  set("#i-cur", dl([["XP from kills", D.num(cur.xp.xk)], ["XP from quests", D.num(cur.xp.xq)], ["Kills", Math.round(cur.k)], ["Deaths", cur.dd]]));
  set("#i-mates", dl(ch.mates.map(([n, p]) => [n, D.dur(p)])));
  set("#i-deaths", dl(ch.deathZones.slice(0, 3)));
})();
