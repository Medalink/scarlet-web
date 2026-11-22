// An invented survey map, drawn in code. World units are map coordinates times 10000, the way
// Scarlet's P lines store them. Nothing here is game art.
(function () {
  const C = { bg: [20, 20, 22], hi: [38, 38, 44], sea: [14, 14, 16], accent: "#ff2d55", soft: "#ff6b8e", fg: "#f0f1f3" };

  function hash(ix, iy) {
    let h = Math.imul(ix, 374761393) ^ Math.imul(iy, 668265263);
    h = Math.imul(h ^ (h >>> 13), 1274126177);
    return ((h ^ (h >>> 16)) >>> 0) / 4294967295;
  }
  function vnoise(x, y) {
    const ix = Math.floor(x), iy = Math.floor(y), fx = x - ix, fy = y - iy;
    const u = fx * fx * (3 - 2 * fx), v = fy * fy * (3 - 2 * fy);
    const a = hash(ix, iy), b = hash(ix + 1, iy), c = hash(ix, iy + 1), d = hash(ix + 1, iy + 1);
    return a + (b - a) * u + (c - a) * v + (a - b - c + d) * u * v;
  }
  const fbm = (x, y) => vnoise(x, y) * 0.55 + vnoise(x * 2.1 + 7.3, y * 2.1 + 1.7) * 0.3 + vnoise(x * 4.3 + 3.1, y * 4.3 + 9.2) * 0.15;

  // x, y, height, radius, in thousands of map units.
  const PEAKS = [[7.2, 1.8, 1.0, 1.3], [8.9, 3.0, 0.5, 0.9], [4.7, 5.9, 0.42, 0.8], [2.4, 8.4, 0.5, 1.0], [6.4, 7.1, 0.5, 1.1], [2.9, 1.5, 0.5, 0.95], [9.5, 5.5, 0.7, 1.3], [5.1, 0.1, 0.6, 1.2], [8.7, 8.7, 0.5, 1.2]];
  const coast = (Y) => 1.75 + 0.42 * Math.sin(Y * 0.85 + 0.4) + 0.2 * Math.sin(Y * 2.2 + 1.3);

  function height(x, y) {
    const X = x / 1000, Y = y / 1000, d = X - coast(Y);
    if (d <= 0) return d * 0.3;
    let h = 0.04;
    for (const [px, py, amp, r] of PEAKS) {
      const q = ((X - px) * (X - px) + (Y - py) * (Y - py)) / (r * r);
      if (q < 9) h += amp * Math.exp(-q);
    }
    // The land rises from zero at the shore, so the coastline is a smooth contour and not a step.
    const k = Math.min(1, d / 0.3), shore = k * k * (3 - 2 * k);
    return h * shore + 0.1 * (1 - Math.exp(-d * 0.9)) + (fbm(X * 0.9, Y * 0.9) - 0.5) * 0.24 * Math.min(1, d / 0.7);
  }

  const RIVER = [[7000, 3250], [6300, 3800], [5300, 4250], [4200, 4700], [3300, 5020], [2500, 4900], [1700, 5200]];

  function fit(box, rect) {
    const scale = Math.min(rect.w / (box.x1 - box.x0), rect.h / (box.y1 - box.y0));
    return {
      scale,
      ox: box.x0 - (rect.x + (rect.w - (box.x1 - box.x0) * scale) / 2) / scale,
      oy: box.y0 - (rect.y + (rect.h - (box.y1 - box.y0) * scale) / 2) / scale,
    };
  }
  const px = (v, x) => (x - v.ox) * v.scale;
  const py = (v, y) => (y - v.oy) * v.scale;

  function smooth(points, n) {
    const out = [];
    for (let i = 0; i < points.length - 1; i++) {
      const p0 = points[Math.max(i - 1, 0)], p1 = points[i], p2 = points[i + 1], p3 = points[Math.min(i + 2, points.length - 1)];
      for (let k = 0; k < n; k++) {
        const u = k / n, u2 = u * u, u3 = u2 * u;
        out.push([0, 1].map((a) => 0.5 * (2 * p1[a] + (p2[a] - p0[a]) * u + (2 * p0[a] - 5 * p1[a] + 4 * p2[a] - p3[a]) * u2 + (3 * p1[a] - p0[a] - 3 * p2[a] + p3[a]) * u3)));
      }
    }
    out.push(points[points.length - 1]);
    return out;
  }

  // Terrain, river and labels onto a canvas, once per size.
  function terrain(canvas, view, cssW, cssH, places) {
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    canvas.width = Math.round(cssW * dpr);
    canvas.height = Math.round(cssH * dpr);
    const ctx = canvas.getContext("2d");
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

    const step = 6, cols = Math.ceil(cssW / step) + 2, rows = Math.ceil(cssH / step) + 2;
    const g = new Float32Array(cols * rows);
    for (let j = 0; j < rows; j++) for (let i = 0; i < cols; i++) g[j * cols + i] = height(view.ox + (i * step) / view.scale, view.oy + (j * step) / view.scale);

    const tone = document.createElement("canvas");
    tone.width = cols; tone.height = rows;
    const tctx = tone.getContext("2d"), img = tctx.createImageData(cols, rows);
    for (let j = 0; j < rows; j++) {
      for (let i = 0; i < cols; i++) {
        const k = j * cols + i, h = g[k];
        const dx = g[j * cols + Math.min(i + 1, cols - 1)] - g[j * cols + Math.max(i - 1, 0)];
        const dy = g[Math.min(j + 1, rows - 1) * cols + i] - g[Math.max(j - 1, 0) * cols + i];
        const shade = h > 0 ? Math.max(-1, Math.min(1, -(dx + dy) * 9)) * 7 : 0;
        const t = h > 0 ? Math.min(1, h / 1.1) : 0, e = Math.max(0, Math.min(1, (h + 0.015) / 0.045)), land = e * e * (3 - 2 * e);
        for (let c = 0; c < 3; c++) img.data[k * 4 + c] = C.sea[c] + (C.bg[c] + (C.hi[c] - C.bg[c]) * t + shade - C.sea[c]) * land;
        img.data[k * 4 + 3] = 255;
      }
    }
    tctx.putImageData(img, 0, 0);
    ctx.imageSmoothingQuality = "high";
    ctx.drawImage(tone, 0, 0, cols, rows, 0, 0, cols * step, rows * step);

    const iso = (level) => {
      ctx.beginPath();
      for (let j = 0; j < rows - 1; j++) {
        for (let i = 0; i < cols - 1; i++) {
          const a = g[j * cols + i], b = g[j * cols + i + 1], c = g[(j + 1) * cols + i + 1], d = g[(j + 1) * cols + i];
          const idx = (a > level ? 8 : 0) | (b > level ? 4 : 0) | (c > level ? 2 : 0) | (d > level ? 1 : 0);
          if (idx === 0 || idx === 15) continue;
          const x = i * step, y = j * step;
          const T = [x + (step * (level - a)) / (b - a), y], R = [x + step, y + (step * (level - b)) / (c - b)];
          const B = [x + (step * (level - d)) / (c - d), y + step], L = [x, y + (step * (level - a)) / (d - a)];
          const seg = (p, q) => { ctx.moveTo(p[0], p[1]); ctx.lineTo(q[0], q[1]); };
          if (idx === 1 || idx === 14) seg(L, B);
          else if (idx === 2 || idx === 13) seg(B, R);
          else if (idx === 3 || idx === 12) seg(L, R);
          else if (idx === 4 || idx === 11) seg(T, R);
          else if (idx === 6 || idx === 9) seg(T, B);
          else if (idx === 7 || idx === 8) seg(L, T);
          else if (idx === 5) { seg(L, T); seg(B, R); }
          else { seg(T, R); seg(L, B); }
        }
      }
      ctx.stroke();
    };
    ctx.lineCap = "round";
    ctx.lineWidth = 1;
    for (let n = 1; n <= 22; n++) {
      ctx.strokeStyle = n % 5 === 0 ? "rgba(255,255,255,0.13)" : "rgba(255,255,255,0.07)";
      iso(n * 0.085);
    }
    ctx.strokeStyle = "rgba(255,255,255,0.045)";
    iso(-0.07); iso(-0.17); iso(-0.3);
    ctx.strokeStyle = "rgba(255,255,255,0.26)";
    ctx.lineWidth = 1.25;
    iso(0);

    ctx.strokeStyle = "rgba(47,158,196,0.5)";
    ctx.lineWidth = 1.5;
    ctx.beginPath();
    smooth(RIVER, 10).forEach(([x, y], i) => (i ? ctx.lineTo(px(view, x), py(view, y)) : ctx.moveTo(px(view, x), py(view, y))));
    ctx.stroke();

    labels(ctx, view, places);
  }

  // Place names get a dark halo, so they stay readable where a path crosses them.
  function labels(ctx, view, list) {
    ctx.lineJoin = "round";
    for (const l of list || []) {
      const x = px(view, l.x), y = py(view, l.y);
      if (l.region) {
        ctx.font = '600 11px "Sofia Sans", "Bahnschrift", sans-serif';
        ctx.fillStyle = "rgba(240,241,243,0.3)";
        ctx.textAlign = "center";
        if ("letterSpacing" in ctx) ctx.letterSpacing = "4px";
        ctx.fillText(l.name.toUpperCase(), x, y);
        if ("letterSpacing" in ctx) ctx.letterSpacing = "0px";
      } else {
        const tx = x + (l.left ? -8 : 8), ty = y + (l.up ? -6 : l.down ? 13 : 4);
        ctx.font = '500 11.5px "Sofia Sans", "Bahnschrift", sans-serif';
        ctx.textAlign = l.left ? "right" : "left";
        ctx.strokeStyle = "rgba(20,20,22,0.82)";
        ctx.lineWidth = 3.5;
        ctx.strokeText(l.name, tx, ty);
        ctx.strokeRect(x - 2, y - 2, 4, 4);
        ctx.fillStyle = "rgba(240,241,243,0.72)";
        ctx.fillRect(x - 2, y - 2, 4, 4);
        ctx.fillText(l.name, tx, ty);
      }
    }
    ctx.textAlign = "left";
  }

  // samples: [{x, y, t, mode}], built from waypoints [x, y, t, mode of the leg that ends here].
  function buildPath(wps) {
    const out = [], hard = (m) => m === "taxi" || m === "jump";
    for (let i = 0; i < wps.length - 1; i++) {
      const p1 = wps[i], p2 = wps[i + 1], mode = p2[3];
      const still = p1[0] === p2[0] && p1[1] === p2[1];
      const p0 = i > 0 && !hard(p1[3]) && !hard(mode) ? wps[i - 1] : p1;
      const p3 = i + 2 < wps.length && !hard(wps[i + 2][3]) && !hard(mode) ? wps[i + 2] : p2;
      const n = still || hard(mode) ? 1 : 12;
      for (let k = 0; k < n; k++) {
        const u = k / n, u2 = u * u, u3 = u2 * u;
        const at = (a) => (n === 1 ? p1[a] : 0.5 * (2 * p1[a] + (p2[a] - p0[a]) * u + (2 * p0[a] - 5 * p1[a] + 4 * p2[a] - p3[a]) * u2 + (3 * p1[a] - p0[a] - 3 * p2[a] + p3[a]) * u3));
        out.push({ x: at(0), y: at(1), t: p1[2] + (p2[2] - p1[2]) * u, mode });
      }
    }
    const last = wps[wps.length - 1];
    out.push({ x: last[0], y: last[1], t: last[2], mode: last[3] });
    return out;
  }

  function posAt(s, t) {
    if (t <= s[0].t) return s[0];
    if (t >= s[s.length - 1].t) return s[s.length - 1];
    let lo = 0, hi = s.length - 1;
    while (hi - lo > 1) { const mid = (lo + hi) >> 1; if (s[mid].t <= t) lo = mid; else hi = mid; }
    const a = s[lo], b = s[hi], u = (t - a.t) / (b.t - a.t || 1);
    if (b.mode === "jump") return a;
    return { x: a.x + (b.x - a.x) * u, y: a.y + (b.y - a.y) * u, t, mode: b.mode, i: lo };
  }

  const STYLE = {
    run: { w: 2.25, color: C.accent, glow: 12 },
    mount: { w: 2.25, color: C.accent, glow: 12 },
    swim: { w: 2.25, color: C.accent, glow: 12 },
    taxi: { w: 1.5, color: C.soft, dash: [7, 6], glow: 0 },
    ghost: { w: 1.5, color: "rgba(240,241,243,0.6)", dash: [1.5, 5], glow: 0 },
  };

  // The path up to time t. alpha lets many overlaid paths build a density.
  function strokePath(ctx, view, s, t, alpha) {
    const end = posAt(s, t);
    let i = 1;
    while (i < s.length && s[i - 1].t < t) {
      const mode = s[i].mode, st = STYLE[mode];
      const start = i - 1;
      while (i < s.length && s[i].mode === mode && s[i - 1].t < t) i++;
      if (!st) continue;
      ctx.beginPath();
      ctx.moveTo(px(view, s[start].x), py(view, s[start].y));
      for (let k = start + 1; k < i; k++) {
        const p = s[k].t > t ? end : s[k];
        ctx.lineTo(px(view, p.x), py(view, p.y));
      }
      ctx.setLineDash(st.dash || []);
      ctx.lineJoin = ctx.lineCap = "round";
      ctx.globalAlpha = alpha == null ? 1 : alpha;
      if (st.glow && alpha == null) {
        ctx.strokeStyle = "rgba(255,45,85,0.12)";
        ctx.lineWidth = 11;
        ctx.stroke();
        ctx.strokeStyle = "rgba(255,45,85,0.28)";
        ctx.lineWidth = 5.5;
        ctx.stroke();
      }
      ctx.strokeStyle = st.color;
      ctx.lineWidth = st.w;
      ctx.stroke();
      ctx.globalAlpha = 1;
    }
    ctx.setLineDash([]);
    return end;
  }

  // One mark vocabulary for both the canvas and the legends.
  function mark(ctx, kind, x, y) {
    ctx.save();
    ctx.translate(x, y);
    ctx.lineCap = "round";
    ctx.lineJoin = "round";
    const ring = (draw) => { ctx.strokeStyle = "#141416"; ctx.lineWidth = 4.5; draw(); ctx.stroke(); ctx.strokeStyle = C.fg; ctx.lineWidth = 1.6; draw(); ctx.stroke(); };
    if (kind === "kill") {
      ctx.fillStyle = "#141416";
      ctx.beginPath(); ctx.arc(0, 0, 3.4, 0, 6.3); ctx.fill();
      ctx.strokeStyle = C.fg; ctx.lineWidth = 1.5; ctx.stroke();
    } else if (kind === "death") {
      ring(() => { ctx.beginPath(); ctx.moveTo(-4.5, -4.5); ctx.lineTo(4.5, 4.5); ctx.moveTo(4.5, -4.5); ctx.lineTo(-4.5, 4.5); });
    } else if (kind === "quest") {
      ctx.beginPath(); ctx.moveTo(0, -5.5); ctx.lineTo(5.5, 0); ctx.lineTo(0, 5.5); ctx.lineTo(-5.5, 0); ctx.closePath();
      ctx.strokeStyle = "#141416"; ctx.lineWidth = 3; ctx.stroke();
      ctx.fillStyle = C.fg; ctx.fill();
    } else if (kind === "level") {
      ring(() => { ctx.beginPath(); ctx.moveTo(-5, 1); ctx.lineTo(0, -4); ctx.lineTo(5, 1); ctx.moveTo(-5, 6); ctx.lineTo(0, 1); ctx.lineTo(5, 6); });
    }
    ctx.restore();
  }

  window.ScarletMap = { height, fit, px, py, terrain, labels, buildPath, posAt, strokePath, mark, smooth };
})();
