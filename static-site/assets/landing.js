(function () {
  const M = window.ScarletMap, D = window.ScarletDemo, F = window.SCARLET_FORMAT, CAP = window.SCARLET_CAPTURE;
  const $ = (s, el) => (el || document).querySelector(s);
  const still = matchMedia("(prefers-reduced-motion: reduce)").matches;
  const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" })[c]);

  // The replay: one path, the lines it wrote, and a bar to move through them ---------
  const samples = M.buildPath(D.WAYPOINTS);
  const lines = [...D.sessionLines(), ...D.pathPoints(samples, M.posAt)].sort((a, b) => a.t - b.t);
  const marks = lines.filter((l) => l.mark).map((l) => { const p = M.posAt(samples, l.t); return { t: l.t, kind: l.mark, x: p.x, y: p.y }; });
  // The route runs up the left of this box and across its top, so the log panel can sit in the empty corner:
  // nothing east of CLEAR.x lies south of CLEAR.y.
  const BOX = { x0: 2050, x1: 7950, y0: 1950, y1: 7050 }, CLEAR = { x: 3700, y: 4500 };

  const hero = $(".hero"), stage = $(".stage", hero), canvas = $("canvas", stage), ctx = canvas.getContext("2d");
  const base = document.createElement("canvas"), tape = $("#tape"), clock = $("#clock"), scrub = $("#scrub"), play = $("#play");
  const bands = $("#bands"), bctx = bands.getContext("2d");
  let view, W = 0, H = 0, dpr = 1, t = still ? D.END : 1430, playing = !still, shown = -1, hold = 0, last = 0, visible = true;

  function layout() {
    const r = stage.getBoundingClientRect();
    if (!r.width || !r.height) return;
    W = r.width; H = r.height;
    view = M.fit(BOX, { x: 18, y: 18, w: W - 36, h: H - 36 });
    if (getComputedStyle(hero).display === "grid") {
      const x0 = $(".hero-copy").getBoundingClientRect().right - r.left + 34, y0 = 92;
      const panel = $(".tape").getBoundingClientRect(), bar = $(".scrub").getBoundingClientRect();
      const scale = Math.min(
        (W - 40 - x0) / (BOX.x1 - BOX.x0),
        (bar.top - r.top - 26 - y0) / (BOX.y1 - BOX.y0),
        (panel.top - r.top - 30 - y0) / (CLEAR.y - BOX.y0),
        (panel.left - r.left - 18 - x0) / (CLEAR.x - BOX.x0),
      );
      view = { scale, ox: BOX.x0 - x0 / scale, oy: BOX.y0 - y0 / scale };
    }
    M.terrain(base, view, W, H, D.PLACES);
    canvas.width = base.width; canvas.height = base.height;
    dpr = base.width / W;
    const b = bands.getBoundingClientRect();
    bands.width = Math.round(b.width * dpr); bands.height = Math.round(b.height * dpr);
    draw(performance.now());
  }

  function draw(now) {
    if (!view) return;
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.drawImage(base, 0, 0);
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    const head = M.strokePath(ctx, view, samples, t);
    for (const m of marks) if (m.t <= t) M.mark(ctx, m.kind, M.px(view, m.x), M.py(view, m.y) - (m.kind === "level" ? 15 : 0));
    const x = M.px(view, head.x), y = M.py(view, head.y);
    if (playing && !still) {
      const ph = (now / 1400) % 1;
      ctx.strokeStyle = `rgba(255,107,142,${0.7 * (1 - ph)})`;
      ctx.lineWidth = 1.5;
      ctx.beginPath(); ctx.arc(x, y, 6 + ph * 13, 0, 6.3); ctx.stroke();
    }
    ctx.fillStyle = "#141416"; ctx.beginPath(); ctx.arc(x, y, 6.5, 0, 6.3); ctx.fill();
    ctx.fillStyle = "#f0f1f3"; ctx.beginPath(); ctx.arc(x, y, 4, 0, 6.3); ctx.fill();

    const bw = bands.width, bh = bands.height;
    bctx.clearRect(0, 0, bw, bh);
    bctx.fillStyle = "rgba(255,45,85,0.14)";
    bctx.fillRect(0, 0, (t / D.END) * bw, bh);
    for (const l of lines) {
      const hot = l.code === "K" || l.code === "D" || l.code === "L";
      bctx.fillStyle = hot ? "#ff2d55" : l.code === "P" ? "rgba(240,241,243,0.16)" : "rgba(240,241,243,0.36)";
      bctx.fillRect(Math.round((l.t / D.END) * (bw - dpr)), hot ? 0 : bh * 0.3, dpr, hot ? bh : bh * 0.4);
    }

    let i = shown;
    if (i >= lines.length || (i >= 0 && lines[i].t > t)) i = -1;
    while (i + 1 < lines.length && lines[i + 1].t <= t) i++;
    if (i !== shown) {
      shown = i;
      tape.innerHTML = lines.slice(Math.max(0, i - 8), i + 1).map((l) => `<li><span>${D.stamp(l.t)}</span><b>${l.code}</b><span>${esc(l.text)}</span></li>`).join("");
    }
    clock.textContent = D.stamp(t);
    scrub.value = Math.round(t);
    scrub.setAttribute("aria-valuetext", D.stamp(t).slice(0, 8));
  }

  function frame(now) {
    if (playing && visible) {
      const dt = Math.min(0.1, (now - last) / 1000);
      if (t >= D.END) { hold += dt; if (hold > 2.4) { t = 0; hold = 0; } }
      else t = Math.min(D.END, t + dt * (D.END / 74));
      draw(now);
    }
    last = now;
    requestAnimationFrame(frame);
  }

  function setPlaying(on) {
    playing = on;
    play.setAttribute("aria-label", on ? "Pause the replay" : "Play the replay");
    play.innerHTML = on
      ? '<svg viewBox="0 0 16 16" aria-hidden="true"><path d="M4 2.5h3v11H4zM9 2.5h3v11H9z" fill="currentColor"/></svg>'
      : '<svg viewBox="0 0 16 16" aria-hidden="true"><path d="M4.5 2.2v11.6L13.5 8z" fill="currentColor"/></svg>';
    draw(performance.now());
  }
  play.addEventListener("click", () => { if (!playing && t >= D.END) t = 0; setPlaying(!playing); });
  scrub.max = D.END;
  scrub.addEventListener("input", () => { t = +scrub.value; hold = 0; if (playing) setPlaying(false); else draw(performance.now()); });
  new IntersectionObserver(([e]) => { visible = e.isIntersecting; }).observe(hero);
  new ResizeObserver(layout).observe(stage);
  document.fonts.ready.then(layout);
  setPlaying(playing);
  requestAnimationFrame(frame);

  // Every event code ----------------------------------------------------------------------
  const groups = $("#groups"), byCode = new Map();
  groups.innerHTML = F.groups.map((g) => `<section class="group"><h3>${esc(g.name)} <span>${g.codes.length}</span></h3><div class="chips">${g.codes.map(([c]) => `<button class="chip" type="button" aria-pressed="false" data-code="${c}">${c}</button>`).join("")}</div></section>`).join("");
  for (const g of F.groups) for (const [code, what, fields] of g.codes) byCode.set(code, { what, fields, group: g.name });
  let picked = "K";
  function read(code) {
    const e = byCode.get(code);
    $("#r-code").textContent = code;
    $("#r-what").textContent = e.what + ".";
    $("#r-fields").innerHTML = e.fields.map((f) => `<span>${esc(f)}</span>`).join("");
    $("#r-group").textContent = e.group;
  }
  function pick(code) {
    picked = code;
    for (const b of groups.querySelectorAll(".chip")) b.setAttribute("aria-pressed", String(b.dataset.code === code));
    read(code);
  }
  groups.addEventListener("click", (e) => { const b = e.target.closest(".chip"); if (b) pick(b.dataset.code); });
  groups.addEventListener("pointerover", (e) => { const b = e.target.closest(".chip"); if (b && e.pointerType === "mouse") read(b.dataset.code); });
  groups.addEventListener("pointerleave", () => read(picked));
  groups.addEventListener("focusin", (e) => { const b = e.target.closest(".chip"); if (b) read(b.dataset.code); });
  $("#find").addEventListener("input", (e) => {
    const q = e.target.value.trim().toLowerCase();
    let n = 0;
    for (const b of groups.querySelectorAll(".chip")) {
      const c = byCode.get(b.dataset.code), hit = !q || b.dataset.code.toLowerCase() === q || c.what.toLowerCase().includes(q) || c.group.toLowerCase().includes(q);
      b.classList.toggle("dim", !hit);
      n += hit;
    }
    $("#find-n").textContent = q ? `${n} of ${F.count} codes match` : "Search the codes";
  });
  pick(picked);

  // The capture map ----------------------------------------------------------------------------
  for (const el of document.querySelectorAll("[data-cap]")) el.textContent = D.num(CAP[el.dataset.cap]);
  for (const el of document.querySelectorAll("[data-codes]")) el.textContent = F.count;
  $("#build").textContent = CAP.build;
  const matrix = $("#matrix");
  matrix.insertAdjacentHTML("beforeend", CAP.domains.map((d, i) => `<div class="dom"><h3>${esc(d.name)}</h3><p>${d.recorded} of ${d.points} recorded · ${d.leftOut} left out</p><canvas data-i="${i}" role="img" aria-label="${d.recorded} recorded and ${d.leftOut} left out of ${d.points} data points"></canvas></div>`).join(""));
  function cells() {
    const ratio = Math.min(window.devicePixelRatio || 1, 2), pitch = 7;
    for (const cv of matrix.querySelectorAll("canvas")) {
      cv.style.width = "100%";
      const d = CAP.domains[+cv.dataset.i], per = Math.max(10, Math.floor(cv.clientWidth / pitch)), rows = Math.ceil(d.points / per);
      cv.style.width = per * pitch + "px";
      cv.style.height = rows * pitch + "px";
      cv.width = Math.round(per * pitch * ratio); cv.height = Math.round(rows * pitch * ratio);
      const c = cv.getContext("2d");
      c.setTransform(ratio, 0, 0, ratio, 0, 0);
      for (let n = 0; n < d.points; n++) {
        c.fillStyle = n < d.recorded ? "#ff2d55" : "rgba(255,255,255,0.13)";
        c.fillRect((n % per) * pitch, Math.floor(n / per) * pitch, 5, 5);
      }
    }
  }
  let mw = 0;
  new ResizeObserver(() => { if (matrix.clientWidth !== mw) { mw = matrix.clientWidth; cells(); } }).observe(matrix);

  // Timeline lanes for the demo session --------------------------------------------------------------
  const lanesEl = $("#lanes");
  function lanes() {
    const moving = { running: "run", mounted: "mount", swimming: "swim", taxi: "taxi" }, rowsOut = [];
    for (const [name, mode] of Object.entries(moving)) {
      const spans = [];
      for (let i = 1; i < samples.length; i++) {
        if (samples[i].mode !== mode) continue;
        const a = samples[i - 1].t, b = samples[i].t, lastSpan = spans[spans.length - 1];
        if (lastSpan && Math.abs(lastSpan[1] - a) < 0.01) lastSpan[1] = b; else spans.push([a, b]);
      }
      rowsOut.push([name, spans]);
    }
    for (const name of ["combat", "dead", "ghost", "group", "resting", "window"]) rowsOut.push([name, D.TAGS[name]]);
    const w = Math.max(640, lanesEl.clientWidth), label = 76, rh = 19, top = 4, h = top + rowsOut.length * rh + 24;
    const X = (s) => label + (s / D.END) * (w - label - 4);
    let svg = `<svg class="lanes" width="${w}" height="${h}" viewBox="0 0 ${w} ${h}" role="img" aria-label="Context tags over the 38 minutes of the demo session">`;
    for (let m = 0; m * 300 <= D.END; m++) {
      const tx = X(m * 300 + 167); // 19:05:00 is 167 s into the session
      svg += `<line x1="${tx}" x2="${tx}" y1="${top}" y2="${h - 20}" stroke="rgba(255,255,255,0.07)"/><text x="${tx}" y="${h - 5}" text-anchor="${tx > w - 24 ? "end" : "middle"}">${D.stamp(m * 300 + 167).slice(0, 5)}</text>`;
    }
    rowsOut.forEach(([name, spans], i) => {
      const y = top + i * rh;
      svg += `<text class="lane-name" x="0" y="${y + 12}">${name}</text><line x1="${label}" x2="${w - 4}" y1="${y + 9}" y2="${y + 9}" stroke="rgba(255,255,255,0.07)"/>`;
      const fill = name === "combat" ? "#ff2d55" : name === "dead" ? "#f0f1f3" : "rgba(240,241,243,0.5)";
      for (const [a, b] of spans) svg += `<rect x="${X(a).toFixed(1)}" y="${y + 5}" width="${Math.max(2, X(b) - X(a)).toFixed(1)}" height="8" rx="2" fill="${fill}"/>`;
    });
    lanesEl.innerHTML = svg + "</svg>";
  }
  let lw = 0;
  new ResizeObserver(() => { if (lanesEl.clientWidth !== lw) { lw = lanesEl.clientWidth; lanes(); } }).observe(lanesEl);

  // Activity heatmap and the closing line, from the demo character ----------------------------------------------
  const ch = D.character(), heatOut = $("#heat-out");
  const say = (d) => { heatOut.innerHTML = d.p ? `<b>${D.day(d.t)}</b> · ${D.dur(d.p)} in ${d.n} session${d.n > 1 ? "s" : ""}` : `<b>${D.day(d.t)}</b> · not played`; };
  D.heat($("#heat"), ch.days, say);
  say(ch.days.filter((d) => d.p).pop());
  for (const el of document.querySelectorAll("[data-hours]")) el.textContent = Math.round(ch.played / 3600);
})();
