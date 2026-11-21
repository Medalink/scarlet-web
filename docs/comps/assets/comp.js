// Chart drawing for the comps, with invented numbers. The built pages draw the same marks from API data:
// this file is the spec for the chart module (resources/js/charts.js), not a library to ship as is.
(function () {
  const NS = "http://www.w3.org/2000/svg";
  const el = (name, attrs, text) => {
    const n = document.createElementNS(NS, name);
    for (const k in attrs) n.setAttribute(k, attrs[k]);
    if (text != null) n.textContent = text;
    return n;
  };
  // A seeded random walk, so every comp draws the same picture each time.
  function rng(seed) { let s = seed >>> 0; return () => ((s = Math.imul(s ^ (s >>> 15), 2246822507) + 0x9e3779b9 >>> 0) / 4294967296); }

  // Plot frame: W x H box, left gutter for the y labels, bottom gutter for x labels. Returns scales.
  function frame(svg, o) {
    const W = o.w || 760, H = o.h || 250, L = 42, B = 22, T = 8, R = 6;
    svg.setAttribute("viewBox", `0 0 ${W} ${H}`);
    const x = (v) => L + ((v - o.x0) / (o.x1 - o.x0)) * (W - L - R);
    const y = (v) => H - B - (v / o.y1) * (H - B - T);
    for (const t of o.yt) {
      svg.append(el("line", { class: t ? "grid" : "axis", x1: L, x2: W - R, y1: y(t), y2: y(t) }));
      svg.append(el("text", { x: L - 8, y: y(t) + 3.5, "text-anchor": "end" }, o.yf ? o.yf(t) : t));
    }
    for (const t of o.xt) svg.append(el("text", { x: x(t), y: H - 5, "text-anchor": "middle" }, o.xf ? o.xf(t) : t));
    return { x, y, W, H, L, B };
  }

  // Line chart with an optional quartile band. series: [{ cls, pts: [[x, y]] , band: [[x, lo, hi]] }]
  function lines(id, o) {
    const svg = document.getElementById(id); if (!svg) return;
    const f = frame(svg, o);
    for (const s of o.series) {
      if (s.band) {
        const up = s.band.map(([a, , hi]) => `${f.x(a)},${f.y(hi)}`), dn = s.band.map(([a, lo]) => `${f.x(a)},${f.y(lo)}`).reverse();
        svg.append(el("polygon", { class: "band", points: up.concat(dn).join(" ") }));
      }
      svg.append(el("polyline", { class: "ln " + s.cls, points: s.pts.map(([a, b]) => `${f.x(a)},${f.y(b)}`).join(" ") }));
      if (s.mark != null) { const p = s.pts[s.mark]; svg.append(el("circle", { class: "dot", cx: f.x(p[0]), cy: f.y(p[1]), r: 4.5 })); }
    }
  }

  // Bars per x step, with optional whiskers. data: [[x, value, lo, hi]]
  function bars(id, o) {
    const svg = document.getElementById(id); if (!svg) return;
    const f = frame(svg, o), bw = ((f.W - f.L - 6) / (o.x1 - o.x0)) * 0.72;
    for (const [a, v, lo, hi] of o.data) {
      svg.append(el("rect", { class: "bar " + (o.cls || ""), x: f.x(a) - bw / 2, width: bw, y: f.y(v), height: Math.max(0, f.y(0) - f.y(v)), rx: 1.5 }));
      if (hi != null) svg.append(el("line", { class: "whisk", x1: f.x(a), x2: f.x(a), y1: f.y(lo), y2: f.y(hi) }));
    }
  }

  // Stacked share bars in the page's own .xp-bars markup. rows: [[kill, dungeon, quest, explore, other]] as shares.
  function shares(id, rows) {
    const box = document.getElementById(id); if (!box) return;
    const cls = ["c-kill", "c-dungeon", "c-quest", "c-explore", "c-other"];
    for (const r of rows) {
      const bar = document.createElement("div"); bar.className = "xp-bar";
      r.forEach((v, i) => { const s = document.createElement("i"); s.className = cls[i]; s.style.height = v * 100 + "%"; bar.append(s); });
      box.append(bar);
    }
  }

  // Hour by weekday grid. level(day, hour) gives 0..4.
  function hours(id, level) {
    const box = document.getElementById(id); if (!box) return;
    const days = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
    box.append(document.createElement("span"));
    for (let h = 0; h < 24; h++) { const s = document.createElement("span"); s.className = "h"; s.textContent = h % 3 ? "" : h; box.append(s); }
    days.forEach((d, di) => {
      const s = document.createElement("span"); s.textContent = d; box.append(s);
      for (let h = 0; h < 24; h++) { const i = document.createElement("i"); i.dataset.l = level(di, h); box.append(i); }
    });
  }

  // The survey map with heat cells and death crosses, on a .mapbox or .stage canvas.
  function heatmap(id, o) {
    const cv = document.getElementById(id); if (!cv || !window.ScarletMap) return;
    const M = window.ScarletMap, r = cv.getBoundingClientRect();
    const view = M.fit(o.box || { x0: 1500, y0: 500, x1: 9800, y1: 6100 }, { x: 0, y: 0, w: r.width, h: r.height });
    M.terrain(cv, view, r.width, r.height, o.places || []);
    const ctx = cv.getContext("2d"), rand = rng(o.seed || 7);
    const cell = 83;
    for (const [cx, cy, spread, n] of o.hot) {
      for (let i = 0; i < n; i++) {
        const a = rand() * 6.283, d = Math.pow(rand(), 1.6) * spread;
        const x = Math.floor((cx + Math.cos(a) * d) / cell) * cell, y = Math.floor((cy + Math.sin(a) * d * 0.8) / cell) * cell;
        const k = 1 - d / spread;
        ctx.fillStyle = k > 0.75 ? "#ff5d7e" : k > 0.5 ? "#cf1f45" : k > 0.25 ? "#861a34" : "#4b1626";
        ctx.globalAlpha = 0.9;
        ctx.fillRect(M.px(view, x) + 0.5, M.py(view, y) + 0.5, M.px(view, x + cell) - M.px(view, x) - 1, M.py(view, y + cell) - M.py(view, y) - 1);
      }
    }
    ctx.globalAlpha = 1;
    for (const [x, y] of o.deaths || []) M.mark(ctx, "death", M.px(view, x), M.py(view, y));
  }

  window.Comp = { lines, bars, shares, hours, heatmap, rng };
})();
