// Draws the contour sheet behind the app pages: isolines of a seeded noise field, every fifth one heavier, with
// spot-height crosses on the summits. Writes one SVG; the same seed always writes the same bytes.
//
//   node tools/art/contours.mjs [--seed 1107] [--out public/art/contours.svg]
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const arg = (name, fallback) => { const i = process.argv.indexOf("--" + name); return i > 0 ? process.argv[i + 1] : fallback; };
const repo = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..", "..");
const seed = Number(arg("seed", "1107"));
const out = path.resolve(repo, arg("out", "public/art/contours.svg"));

const WIDTH = 1600, HEIGHT = 1000, CELL = 12.5, LEVELS = 22, MAJOR_EVERY = 5;
const COLUMNS = WIDTH / CELL, ROWS = HEIGHT / CELL;

function mulberry(a) {
  return () => { a |= 0; a = (a + 0x6d2b79f5) | 0; let t = Math.imul(a ^ (a >>> 15), 1 | a); t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t; return ((t ^ (t >>> 14)) >>> 0) / 4294967296; };
}

function lattice(size, random) {
  const values = Float64Array.from({ length: size * size }, random);
  const fade = (t) => t * t * t * (t * (t * 6 - 15) + 10);
  return (x, y) => {
    const x0 = Math.floor(x), y0 = Math.floor(y), fx = fade(x - x0), fy = fade(y - y0);
    const at = (i, j) => values[(((j % size) + size) % size) * size + (((i % size) + size) % size)];
    const top = at(x0, y0) + (at(x0 + 1, y0) - at(x0, y0)) * fx, bottom = at(x0, y0 + 1) + (at(x0 + 1, y0 + 1) - at(x0, y0 + 1)) * fx;
    return top + (bottom - top) * fy;
  };
}

const random = mulberry(seed);
const octaves = [[lattice(64, random), 1 / 420, 1], [lattice(64, random), 1 / 190, 0.45], [lattice(64, random), 1 / 85, 0.16]];
const height = (x, y) => octaves.reduce((sum, [noise, scale, weight]) => sum + noise(x * scale + 7.3, y * scale * 1.25 + 3.1) * weight, 0) / 1.61;

const field = [];
let low = Infinity, high = -Infinity;
for (let j = 0; j <= ROWS; j++) {
  field.push([]);
  for (let i = 0; i <= COLUMNS; i++) { const h = height(i * CELL, j * CELL); field[j].push(h); low = Math.min(low, h); high = Math.max(high, h); }
}

const SIDES = { 1: [[3, 0, 2, 3]], 2: [[2, 3, 1, 2]], 3: [[3, 0, 1, 2]], 4: [[0, 1, 1, 2]], 5: [[3, 0, 0, 1], [2, 3, 1, 2]], 6: [[0, 1, 2, 3]], 7: [[3, 0, 0, 1]], 8: [[3, 0, 0, 1]], 9: [[0, 1, 2, 3]], 10: [[3, 0, 2, 3], [0, 1, 1, 2]], 11: [[0, 1, 1, 2]], 12: [[3, 0, 1, 2]], 13: [[2, 3, 1, 2]], 14: [[3, 0, 2, 3]] };

function segments(level) {
  const found = [];
  for (let j = 0; j < ROWS; j++) {
    for (let i = 0; i < COLUMNS; i++) {
      const corners = [[i, j], [i + 1, j], [i + 1, j + 1], [i, j + 1]];
      const heights = corners.map(([x, y]) => field[y][x]);
      const kind = (heights[0] > level ? 8 : 0) | (heights[1] > level ? 4 : 0) | (heights[2] > level ? 2 : 0) | (heights[3] > level ? 1 : 0);
      const cross = (a, b) => { const t = (level - heights[a]) / (heights[b] - heights[a]); return [(corners[a][0] + (corners[b][0] - corners[a][0]) * t) * CELL, (corners[a][1] + (corners[b][1] - corners[a][1]) * t) * CELL]; };
      for (const [a, b, c, d] of SIDES[kind] ?? []) found.push([cross(a, b), cross(c, d)]);
    }
  }
  return found;
}

function lines(found) {
  const key = ([x, y]) => Math.round(x * 8) + ":" + Math.round(y * 8);
  const ends = new Map();
  found.forEach((segment, index) => segment.forEach((point) => { const k = key(point); ends.set(k, [...(ends.get(k) ?? []), index]); }));
  const used = new Set(), result = [];
  const grow = (line, forward) => {
    for (;;) {
      const tip = forward ? line[line.length - 1] : line[0];
      const next = (ends.get(key(tip)) ?? []).find((index) => !used.has(index));
      if (next === undefined) return;
      used.add(next);
      const [a, b] = found[next];
      const far = key(a) === key(tip) ? b : a;
      if (forward) line.push(far); else line.unshift(far);
    }
  };
  found.forEach((segment, index) => {
    if (used.has(index)) return;
    used.add(index);
    const line = [...segment];
    grow(line, true);
    grow(line, false);
    if (line.length > 5) result.push(line);
  });
  return result;
}

const smooth = (line) => line.flatMap((point, index) => {
  if (index === line.length - 1) return [point];
  const next = line[index + 1];
  return [[point[0] * 0.75 + next[0] * 0.25, point[1] * 0.75 + next[1] * 0.25], [point[0] * 0.25 + next[0] * 0.75, point[1] * 0.25 + next[1] * 0.75]];
});

const trace = (line) => "M" + smooth(line).map(([x, y]) => Math.round(x) + " " + Math.round(y)).join("L");

const minor = [], major = [];
for (let n = 1; n < LEVELS; n++) {
  const level = low + ((high - low) * n) / LEVELS;
  (n % MAJOR_EVERY === 0 ? major : minor).push(...lines(segments(level)).map(trace));
}

const summits = [];
for (let j = 3; j < ROWS - 2; j++) {
  for (let i = 3; i < COLUMNS - 2; i++) {
    const h = field[j][i];
    let peak = h > low + (high - low) * 0.72;
    for (let dj = -3; dj <= 3 && peak; dj++) for (let di = -3; di <= 3; di++) if ((di || dj) && field[j + dj]?.[i + di] >= h) { peak = false; break; }
    if (peak) summits.push([Math.round(i * CELL), Math.round(j * CELL)]);
  }
}
const crosses = summits.map(([x, y]) => `M${x - 5} ${y}h10M${x} ${y - 5}v10`).join("");

const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${WIDTH} ${HEIGHT}" preserveAspectRatio="xMidYMid slice" fill="none" stroke="#fff" stroke-linecap="round" stroke-linejoin="round">`
  + `<path stroke-opacity=".07" d="${minor.join("")}"/><path stroke-opacity=".13" stroke-width="1.3" d="${major.join("")}"/><path stroke-opacity=".3" d="${crosses}"/></svg>\n`;

fs.mkdirSync(path.dirname(out), { recursive: true });
fs.writeFileSync(out, svg);
console.log(`${path.relative(repo, out)}: ${minor.length} minor and ${major.length} major lines, ${summits.length} summits, ${(svg.length / 1024).toFixed(1)} KiB`);
