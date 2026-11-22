// Writes the page comps into <repo>/docs/comps. Every name and number is invented.
// node docs/comps/build.mjs docs/comps
import fs from "node:fs";
import path from "node:path";

const out = process.argv[2];
const NAV = [["stats", "Stats"], ["world", "Map"], ["loot", "Loot"], ["camps", "Camps"], ["fishing", "Professions"], ["rulesets", "Rulesets"], ["leaderboards", "Leaderboards"], ["search", "Search"], ["developers", "API"]];
const TRADES_ALL = ["Fishing", "Herbalism", "Mining", "Skinning", "Alchemy", "Blacksmithing", "Enchanting", "Engineering", "Leatherworking", "Tailoring", "Cooking", "First Aid"];
const TRADES = (cur) => TRADES_ALL.map((name) => `<a href="#"${name === cur ? ' aria-current="page"' : ""}>${name}</a>`).join("");
const mark = `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 19c5 0 3.4-7 8-7s3.4-6.4 8-6.4" fill="none" stroke="#ff2d55" stroke-width="2.6" stroke-linecap="round"/><circle cx="19.6" cy="5.4" r="2.8" fill="#ff2d55"/></svg>`;

function page(file, title, cur, body, script = "", signedIn = false, { sheets = ["assets/site.css", "assets/app.css"], bodyClass = "", main = "" } = {}) {
  const nav = NAV.map(([k, l]) => `<a href="${k}.html"${k === cur ? ' aria-current="page"' : ""}>${l}</a>`).join("");
  const html = `<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${title}</title>
<meta name="theme-color" content="#141416">
<link rel="icon" href="assets/mark.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Martian+Mono:wdth,wght@75..112.5,100..800&family=Sofia+Sans:ital,wght@0,400..800;1,400..800&family=Sofia+Sans+Extra+Condensed:wght@500..900&display=swap">
${sheets.map((href) => `<link rel="stylesheet" href="${href}">`).join("\n")}
</head>
<body${bodyClass ? ` class="${bodyClass}"` : ""}>
<header class="top solid">
  <div class="top-in wrap">
    <a class="brand" href="stats.html" aria-label="Scarlet, home">${mark}Scarlet</a>
    <nav class="nav" aria-label="Site">${nav}</nav>
    <form class="top-find" role="search" action="search.html"><label class="vh" for="q">Search</label><input id="q" name="q" type="search" placeholder="Character, creature, item, zone, quest"></form>
    <a class="acct" href="${signedIn ? "account.html" : "login.html"}">${signedIn ? "Account" : "Sign in"}</a>
  </div>
</header>
<main${main ? ` ${main}` : ""}>
${body}
</main>
<footer class="foot">
  <div class="wrap"><span>Scarlet</span><span>Numbers come from players who chose to upload. <a href="developers.html">Public API</a></span></div>
</footer>
<script src="assets/map.js"></script>
<script src="assets/comp.js"></script>
<script>${script}</script>
</body>
</html>
`;
  fs.writeFileSync(path.join(out, file), html);
}

const key = (items) => `<ul class="key">${items.map(([c, l]) => `<li><i class="${c}"></i>${l}</li>`).join("")}</ul>`;
const rulesetSeg = `<div class="seg" role="group" aria-label="Ruleset"><a href="#" aria-current="true">All</a><a href="#">Standard</a><a href="#">Hardcore</a><a href="#">Slow</a></div><div class="seg" role="group" aria-label="Server"><a href="#" aria-current="true">PvE and PvP</a><a href="#">PvE</a><a href="#">PvP</a></div><select class="pick" aria-label="Class"><option>Every class</option><option>Warrior</option><option>Mage</option></select>`;

// ---------------------------------------------------------------------------------- stats
page("stats.html", "Scarlet stats: how characters level in WoW Forever", "stats", `
<div class="wrap">
  <section class="pg-head" aria-labelledby="h1">
    <div>
      <h1 id="h1">All characters</h1>
      <p class="sub">1,284 characters, 41,907 hours of play, recorded since 14 Nov 2026. Updated 6 minutes ago.</p>
    </div>
    <div class="filters">${rulesetSeg}</div>
  </section>

  <section class="week n6" aria-label="Totals">
    <div><dt>Characters</dt><dd>1,284</dd></div>
    <div><dt>Hours played</dt><dd>41,907</dd></div>
    <div><dt>Median to level 60</dt><dd>6d 4h<small>played</small></dd></div>
    <div><dt>Deaths</dt><dd>58,310</dd></div>
    <div><dt>Quests turned in</dt><dd>402,118</dd></div>
    <div><dt>Dungeon runs</dt><dd>9,446</dd></div>
  </section>

  <div class="sheet">
    <section class="mod w8 first" aria-labelledby="h-curve">
      <div class="mod-head"><h2 id="h-curve">Played time to reach each level</h2>
        <div class="seg" role="group" aria-label="Scale"><button type="button" aria-pressed="true">Hours</button><button type="button" aria-pressed="false">Days played</button></div></div>
      <div class="plot"><svg id="curve" role="img" aria-label="Median played hours to reach each level, with the middle half of characters as a band"></svg></div>
      <ul class="key"><li><i class="k-line" style="background:var(--accent)"></i>Median</li><li><i style="background:rgba(255,45,85,.3)"></i>Middle half of characters</li><li><i class="k-line" style="background:var(--fg)"></i>Fastest tenth</li></ul>
      <p class="readout"><b>Level 40</b> takes a median of 61h 20m played. The middle half is 48h to 79h.</p>
    </section>
    <section class="mod last" aria-labelledby="h-reach">
      <h2 id="h-reach">Characters at each level</h2>
      <div class="plot"><svg id="reach" role="img" aria-label="How many characters are currently at each level"></svg></div>
      <p class="note">Half of all characters are below level 23.</p>
    </section>

    <section class="mod w8 first" aria-labelledby="h-xp">
      <div class="mod-head"><h2 id="h-xp">Where experience comes from, by level</h2></div>
      <div class="xp"><div class="xp-plot">
        <div class="xp-y" aria-hidden="true"><span>100%</span><span>50%</span><span>0</span></div>
        <div class="xp-bars" id="xp" role="img" aria-label="Share of experience from kills, dungeon kills, quests, exploring and other, one bar per level"></div>
        <div class="xp-x" aria-hidden="true"><span>1</span><span>10</span><span>20</span><span>30</span><span>40</span><span>50</span><span>59</span></div>
      </div>
      ${key([["c-kill", "Kills"], ["c-dungeon", "Dungeon kills"], ["c-quest", "Quests"], ["c-explore", "Exploring"], ["c-other", "Other"]])}
      <p class="readout"><b>Level 34</b>: quests 46%, kills 31%, dungeon kills 19%, exploring 3%, other 1%.</p></div>
    </section>
    <section class="mod last" aria-labelledby="h-time">
      <h2 id="h-time">Hours spent in each level</h2>
      <div class="plot"><svg id="ltime" role="img" aria-label="Median hours a character spends in each level"></svg></div>
      <p class="note">The slowest level is 58, at a median of 5h 12m.</p>
    </section>

    <section class="mod first" aria-labelledby="h-deaths">
      <h2 id="h-deaths">Deaths per hour, by level</h2>
      <div class="plot"><svg id="dph" role="img" aria-label="Deaths per played hour at each level"></svg></div>
      <p class="note">Most dangerous: levels 24 to 27.</p>
    </section>
    <section class="mod" aria-labelledby="h-killers">
      <h2 id="h-killers">What kills characters</h2>
      <ol class="rank">
        <li><a class="who" href="creature.html">Marsh Stalker</a><span class="val">2,104 deaths</span><span class="track"><i style="width:100%"></i></span></li>
        <li><a class="who" href="creature.html">Hollow Warden</a><span class="val">1,655</span><span class="track"><i style="width:79%"></i></span></li>
        <li><span class="who">Falling</span><span class="val">1,402</span><span class="track"><i style="width:67%"></i></span></li>
        <li><a class="who" href="creature.html">Saltmarsh Raider</a><span class="val">1,118</span><span class="track"><i style="width:53%"></i></span></li>
        <li><span class="who">Another player</span><span class="val">940</span><span class="track"><i style="width:45%"></i></span></li>
        <li><span class="who">Drowning</span><span class="val">611</span><span class="track"><i style="width:29%"></i></span></li>
      </ol>
    </section>
    <section class="mod last" aria-labelledby="h-dz">
      <h2 id="h-dz">Where characters die</h2>
      <ol class="rank plain">
        <li><a class="who" href="zone.html">Saltmarsh Reach</a><span class="val">6,480 deaths</span><span class="track"><i style="width:100%"></i></span></li>
        <li><a class="who" href="zone.html">Hollowmere</a><span class="val">5,022</span><span class="track"><i style="width:77%"></i></span></li>
        <li><a class="who" href="zone.html">The Cinder Flats</a><span class="val">3,870</span><span class="track"><i style="width:60%"></i></span></li>
        <li><a class="who" href="zone.html">Greywater Deep</a><span class="val">2,911</span><span class="track"><i style="width:45%"></i></span></li>
        <li><a class="who" href="zone.html">Thornwall</a><span class="val">2,240</span><span class="track"><i style="width:35%"></i></span></li>
      </ol>
    </section>

    <section class="mod w7 first" aria-labelledby="h-dung">
      <h2 id="h-dung">Dungeons</h2>
      <div class="scroll-x"><table class="data" style="margin-top:1.1rem">
        <thead><tr><th>Dungeon</th><th class="n">Runs</th><th class="n">Median time</th><th class="n">Deaths a run</th><th class="n">Median level</th></tr></thead>
        <tbody>
          <tr><td><a href="zone.html">Greywater Deep</a></td><td class="n">2,418</td><td class="n">42m</td><td class="n">1.8</td><td class="n">22</td></tr>
          <tr><td><a href="zone.html">The Ember Vault</a></td><td class="n">1,960</td><td class="n">58m</td><td class="n">2.6</td><td class="n">31</td></tr>
          <tr><td><a href="zone.html">Thornwall Keep</a></td><td class="n">1,512</td><td class="n">1h 14m</td><td class="n">3.1</td><td class="n">38</td></tr>
          <tr><td><a href="zone.html">Sunken Archive</a></td><td class="n">1,203</td><td class="n">51m</td><td class="n">2.2</td><td class="n">44</td></tr>
          <tr><td><a href="zone.html">Hall of Ash</a></td><td class="n">877</td><td class="n">1h 32m</td><td class="n">4.4</td><td class="n">55</td></tr>
        </tbody></table></div>
    </section>
    <section class="mod w5 last" aria-labelledby="h-prof">
      <h2 id="h-prof">Professions</h2>
      <ol class="rank">
        <li><span class="who">Fishing</span><span class="val">71% of characters · median 118</span><span class="track"><i style="width:71%"></i></span></li>
        <li><span class="who">Cooking</span><span class="val">64% · 142</span><span class="track"><i style="width:64%"></i></span></li>
        <li><span class="who">Mining</span><span class="val">38% · 165</span><span class="track"><i style="width:38%"></i></span></li>
        <li><span class="who">Herbalism</span><span class="val">35% · 171</span><span class="track"><i style="width:35%"></i></span></li>
        <li><span class="who">Skinning</span><span class="val">33% · 190</span><span class="track"><i style="width:33%"></i></span></li>
      </ol>
    </section>

    <section class="mod w5 first" aria-labelledby="h-gold">
      <h2 id="h-gold">Gold carried, by level</h2>
      <div class="plot"><svg id="gold" role="img" aria-label="Median gold a character carries at each level, with the middle half as a band"></svg></div>
      <p class="note">Median at level 40: <span class="money">31<i class="g">g</i>40<i class="s">s</i></span>. Characters who shared their economy data only.</p>
    </section>
    <section class="mod w7 last" aria-labelledby="h-when">
      <h2 id="h-when">When people play</h2>
      <div class="hours" id="hours" role="img" aria-label="Play time by weekday and hour of day, in UTC"></div>
      <p class="heat-key"><span>Quiet</span><i style="background:rgba(255,255,255,.055)"></i><i style="background:#4b1626"></i><i style="background:#861a34"></i><i style="background:#cf1f45"></i><i style="background:#ff5d7e"></i><span>Busiest</span><span style="margin-left:1rem">Hours are UTC</span></p>
    </section>

    <section class="mod w12 first last" aria-labelledby="h-map">
      <div class="mod-head"><h2 id="h-map">Where characters go in Saltmarsh Reach</h2>
        <select class="pick" aria-label="Zone"><option>Saltmarsh Reach</option><option>Hollowmere</option></select></div>
      <div class="mapbox" style="aspect-ratio:21/9"><canvas id="map" role="img" aria-label="Heat from every recorded path in the zone, with deaths as crosses"></canvas></div>
      <p class="heat-key"><span>Few paths</span><i style="background:#4b1626"></i><i style="background:#861a34"></i><i style="background:#cf1f45"></i><i style="background:#ff5d7e"></i><span>Many</span></p>
    </section>
  </div>
</div>`, `
const C = Comp, r = C.rng(11);
const med = [], band = [], fast = []; let t = 0;
for (let l = 1; l <= 60; l++) { t += 0.12 + Math.pow(l / 60, 2.1) * 5.2; med.push([l, t]); band.push([l, t * 0.78, t * 1.29]); fast.push([l, t * 0.6]); }
C.lines("curve", { x0: 1, x1: 60, y1: 200, yt: [0, 50, 100, 150, 200], yf: (v) => v + "h", xt: [1, 10, 20, 30, 40, 50, 60], series: [{ cls: "a", pts: med, band, mark: 39 }, { cls: "b thin", pts: fast }] });
C.bars("reach", { w: 380, h: 250, x0: 0, x1: 61, y1: 80, yt: [0, 40, 80], xt: [1, 20, 40, 60], cls: "t", data: Array.from({ length: 60 }, (_, i) => [i + 1, 70 * Math.exp(-i / 21) * (0.75 + r() * 0.5) + (i === 59 ? 34 : 0)]) });
C.shares("xp", Array.from({ length: 59 }, (_, i) => { const q = 0.5 - i * 0.002 + r() * 0.06, d = i < 14 ? 0 : 0.1 + r() * 0.14, e = 0.07 - i * 0.001, o = 0.015; return [1 - q - d - e - o, d, q, e, o]; }));
C.bars("ltime", { w: 380, h: 250, x0: 0, x1: 60, y1: 6, yt: [0, 2, 4, 6], yf: (v) => v + "h", xt: [1, 20, 40, 59], data: Array.from({ length: 59 }, (_, i) => { const v = 0.15 + Math.pow((i + 1) / 59, 1.8) * 4.6 * (0.9 + r() * 0.2); return [i + 1, v]; }) });
C.lines("dph", { w: 380, h: 250, x0: 1, x1: 60, y1: 1.2, yt: [0, 0.4, 0.8, 1.2], xt: [1, 20, 40, 60], series: [{ cls: "a", pts: Array.from({ length: 60 }, (_, i) => [i + 1, 0.25 + 0.6 * Math.exp(-Math.pow((i - 25) / 9, 2)) + r() * 0.08]) }] });
const g = [], gb = []; let m = 0; for (let l = 1; l <= 60; l++) { m += Math.pow(l / 60, 1.7) * 6; g.push([l, m]); gb.push([l, m * 0.55, m * 1.7]); }
C.lines("gold", { w: 480, h: 250, x0: 1, x1: 60, y1: 260, yt: [0, 100, 200], yf: (v) => v + "g", xt: [1, 20, 40, 60], series: [{ cls: "g", pts: g, band: gb }] });
C.hours("hours", (d, h) => { const eve = Math.exp(-Math.pow((h - 20) / 3.2, 2)), day = d > 4 ? Math.exp(-Math.pow((h - 15) / 4.5, 2)) : 0; return Math.min(4, Math.round((eve + day * 0.8) * 4 + r() * 0.5)); });
C.heatmap("map", { seed: 5, hot: [[3600, 4800, 900, 520], [5600, 4200, 700, 380], [7200, 3300, 650, 300], [4700, 2600, 500, 160], [6400, 5300, 520, 150]], deaths: [[3900, 4500], [5200, 4400], [5750, 3900], [7100, 3000], [6900, 3600], [4500, 2700], [3300, 5100]] });
`);

// ---------------------------------------------------------------------------------- world
page("world.html", "Scarlet world map: where characters go and die", "world", `
<section class="world" aria-labelledby="h1">
  <div class="world-map"><canvas id="map" role="img" aria-label="The world map with heat from every recorded path. Drag to move, scroll to zoom."></canvas>
    <div class="world-zoom" role="group" aria-label="Zoom"><button class="iconbtn" type="button" aria-label="Zoom in">+</button><button class="iconbtn" type="button" aria-label="Zoom out">&minus;</button></div>
    <p class="world-credit">Map art &copy; Blizzard Entertainment. Map by <a href="#">OpenMap</a>.</p>
  </div>
  <aside class="world-side">
    <h1 id="h1">World map</h1>
    <p class="sub">Every path, death and kill that players chose to upload, on one map. Zoom into a zone to open it.</p>
    <div class="world-ctl">
      <div class="seg" role="group" aria-label="Layer"><button type="button" aria-pressed="true">Paths</button><button type="button" aria-pressed="false">Deaths</button><button type="button" aria-pressed="false">Kills</button></div>
      <div class="seg" role="group" aria-label="Look"><button type="button" aria-pressed="true">Survey</button><button type="button" aria-pressed="false">Game</button></div>
      <div class="seg" role="group" aria-label="Ruleset"><a href="#" aria-current="true">All</a><a href="#">Standard</a><a href="#">Hardcore</a><a href="#">Slow</a></div>
    </div>
    <p class="heat-key"><span>Few</span><i style="background:#4b1626"></i><i style="background:#861a34"></i><i style="background:#cf1f45"></i><i style="background:#ff5d7e"></i><span>Many characters</span></p>
    <p class="readout"><b>Saltmarsh Reach</b>, under the pointer: 811 characters, 3,912 hours, 6,480 deaths.</p>
    <h2>Busiest zones</h2>
    <ol class="rank plain">
      <li><a class="who" href="zone.html">Saltmarsh Reach</a><span class="val">3,912 hours</span><span class="track"><i style="width:100%"></i></span></li>
      <li><a class="who" href="zone.html">Hollowmere</a><span class="val">3,105</span><span class="track"><i style="width:79%"></i></span></li>
      <li><a class="who" href="zone.html">The Cinder Flats</a><span class="val">2,480</span><span class="track"><i style="width:63%"></i></span></li>
      <li><a class="who" href="zone.html">Thornwall</a><span class="val">1,766</span><span class="track"><i style="width:45%"></i></span></li>
      <li><a class="who" href="zone.html">Marshlight Fen</a><span class="val">804</span><span class="track"><i style="width:21%"></i></span></li>
    </ol>
  </aside>
</section>`, `
Comp.heatmap("map", { seed: 8, box: { x0: 600, y0: 200, x1: 9900, y1: 9300 }, hot: [[3600, 4800, 900, 620], [5600, 4200, 700, 420], [7200, 3300, 650, 340], [4700, 2600, 500, 200], [6400, 5300, 520, 180], [2900, 7900, 800, 260], [7600, 7200, 900, 300], [8600, 4800, 600, 160]], deaths: [[3900, 4500], [5200, 4400], [7100, 3000], [6900, 3600], [4500, 2700], [7700, 7000]] });
`);

page("camps.html", "Scarlet camps: how players use the campsite", "camps", `
<section class="ch-head camp-head" aria-labelledby="h1">
  <div class="stage" data-state="off">
    <div class="callouts">
      <p class="callout" data-key="fire"><b>Campfire</b><span>Sit 60 seconds for Camp Benefits</span><em>88% of visits sit down</em></p>
      <p class="callout left" data-key="tent"><b>Camp Tent</b><span>Rest 30 seconds for rested experience, once an hour</span><em>61% of visits use a tent</em></p>
      <p class="callout left" data-key="chair"><b>Camp Chair</b><span>Adds 2% critical strike to the benefits</span><em>44% of Camp Benefits come with a chair</em></p>
    </div>
  </div>
  <div class="ch-id wrap">
    <div><h1 class="ch-name" id="h1">Camps</h1></div>
    <div class="filters"><div class="seg" role="group" aria-label="Ruleset"><a href="#" aria-current="true">All</a><a href="#">Standard</a><a href="#">Hardcore</a><a href="#">Slow</a></div></div>
  </div>
</section>
<div class="wrap">
  <ul class="camp-shares"><li><b>Campfire</b> 88% of visits sit down</li><li><b>Camp Tent</b> 61% of visits use a tent</li><li><b>Camp Chair</b> 44% of Camp Benefits come with a chair</li></ul>
  <p class="credit">Campfire, tent and chair models, textures, flames, sparks and light &copy; Blizzard Entertainment, read from the WoW Forever client. The smoke and the ground are drawn by this page.</p>
  <section class="week n6" aria-label="Totals">
    <div><dt>Visits</dt><dd>41,280</dd></div><div><dt>Characters</dt><dd>3,104</dd></div><div><dt>Hours by the fire</dt><dd>9,870h</dd></div>
    <div><dt>Median visit</dt><dd>4m 10s</dd></div><div><dt>Visits a played hour</dt><dd>0.31</dd></div><div><dt>Earn Camp Benefits</dt><dd>71%</dd></div>
  </section>

  <div class="sheet">
    <section class="mod w7 first camp-mod" aria-labelledby="h-ben">
      <h2 id="h-ben">Camp Benefits players leave with</h2>
      <div class="scroll-x"><table class="data benefits" style="margin-top:1.1rem">
        <thead><tr><th>Placed at the camp</th><th>Benefit</th><th class="n">Of Camp Benefits</th><th class="n">Lost to a death</th></tr></thead>
        <tbody>
          <tr><td><span class="buff"><i class="buff-none" aria-hidden="true"></i>Camp Chair</span></td><td>+2% critical strike chance</td><td class="n"><i class="bar" style="width:5.6rem"></i>62%</td><td class="n">4%</td></tr>
          <tr><td><span class="buff"><i class="buff-none" aria-hidden="true"></i>First Aid Kit</span></td><td>+56 Stamina</td><td class="n"><i class="bar" style="width:4.9rem"></i>54%</td><td class="n">5%</td></tr>
          <tr><td><span class="buff"><i class="buff-none" aria-hidden="true"></i>Sharpening Wheel</span></td><td>+34 Strength</td><td class="n"><i class="bar" style="width:3.7rem"></i>41%</td><td class="n">5%</td></tr>
          <tr><td><span class="buff"><i class="buff-none" aria-hidden="true"></i>Lodestone</span></td><td>+90 melee attack power</td><td class="n"><i class="bar" style="width:3.3rem"></i>37%</td><td class="n">6%</td></tr>
          <tr><td><span class="buff"><i class="buff-none" aria-hidden="true"></i>Mana Well</span></td><td>29 mana every 5 seconds</td><td class="n"><i class="bar" style="width:3rem"></i>33%</td><td class="n">4%</td></tr>
          <tr><td><span class="buff"><i class="buff-none" aria-hidden="true"></i>Incense Candle</span></td><td>+25 Intellect</td><td class="n"><i class="bar" style="width:2.6rem"></i>29%</td><td class="n">5%</td></tr>
          <tr><td><span class="buff"><i class="buff-none" aria-hidden="true"></i>Faction Banner</span></td><td>+32 Spirit</td><td class="n"><i class="bar" style="width:1.9rem"></i>21%</td><td class="n">7%</td></tr>
          <tr><td><span class="buff"><i class="buff-none" aria-hidden="true"></i>Fish Bowl</span></td><td>+8% to all stats</td><td class="n"><i class="bar" style="width:1.1rem"></i>12%</td><td class="n">3%</td></tr>
          <tr><td><span class="buff"><i class="buff-none" aria-hidden="true"></i>Enchanted Lute</span></td><td>+308 armor, +13 to all attributes, +22 to all resistances</td><td class="n">Too few characters</td><td class="n">&ndash;</td></tr>
        </tbody></table></div>
    </section>
    <section class="mod w5 last" aria-labelledby="h-set">
      <h2 id="h-set">What gets set up</h2>
      <ol class="rank">
        <li><span class="who">Basic Campfire</span><span class="val">52% of visits</span><span class="track"><i style="width:100%"></i></span></li>
        <li><span class="who">Journeyman Campfire</span><span class="val">27%</span><span class="track"><i style="width:52%"></i></span></li>
        <li><span class="who">Expert Campfire</span><span class="val">9%</span><span class="track"><i style="width:17%"></i></span></li>
        <li><span class="who">A fire already there</span><span class="val">12%</span><span class="track"><i style="width:23%"></i></span></li>
      </ol>
      <ol class="rank plain">
        <li><span class="who">Camp Tent</span><span class="val">61%</span><span class="track"><i style="width:61%"></i></span></li>
        <li><span class="who">Camp Chair</span><span class="val">44%</span><span class="track"><i style="width:44%"></i></span></li>
      </ol>
    </section>

    <section class="mod first" aria-labelledby="h-len">
      <h2 id="h-len">How long a visit lasts</h2>
      <div class="plot"><svg id="len" role="img" aria-label="Visits by length, as a share of all visits"></svg></div>
      <p class="note">29% of visits end before the 60 seconds Camp Benefits need.</p>
    </section>
    <section class="mod" aria-labelledby="h-lvl">
      <h2 id="h-lvl">Visits a played hour, by level</h2>
      <div class="plot"><svg id="lvl" role="img" aria-label="Camp visits a played hour at each level"></svg></div>
      <p class="note">Most: level 27, at 0.42 visits a played hour.</p>
    </section>
    <section class="mod last" aria-labelledby="h-rest">
      <h2 id="h-rest">Tent rests a played hour, by level</h2>
      <div class="plot"><svg id="rest" role="img" aria-label="Tent rests a played hour at each level"></svg></div>
      <p class="note">Most: level 30, at 0.24 tent rests a played hour.</p>
    </section>

    <section class="mod w8 first" aria-labelledby="h-when">
      <h2 id="h-when">When players camp</h2>
      <div class="hours" id="when" role="img" aria-label="Camp visits by weekday and hour of day, in UTC"></div>
      <p class="readout"><b>Busiest hour</b>: Sun 21:00 UTC</p>
      <p class="heat-key"><span>Quiet</span><i style="background:rgba(255,255,255,.055)"></i><i style="background:#4b1626"></i><i style="background:#861a34"></i><i style="background:#cf1f45"></i><i style="background:#ff5d7e"></i><span>Busiest</span><span style="margin-left:1rem">Hours are UTC</span></p>
    </section>
    <section class="mod last" aria-labelledby="h-buffed">
      <h2 id="h-buffed">Play with Camp Benefits</h2>
      <dl class="pair" style="grid-template-columns:minmax(0,1fr)">
        <div><dt>Experience an hour</dt><dd class="up">+14%<small>against the same levels across all play</small></dd></div>
      </dl>
      <dl class="pair" style="grid-template-columns:minmax(0,1fr);margin-top:0.9rem">
        <div><dt>Deaths an hour</dt><dd>&minus;22%</dd></div>
      </dl>
    </section>

    <section class="mod w7 first" aria-labelledby="h-where">
      <h2 id="h-where">Where players camp</h2>
      <div class="mapbox" style="aspect-ratio:3/2"><canvas id="where" role="img" aria-label="Camps on the world map, in Saltmarsh Reach"></canvas></div>
      <p class="note"><a href="world.html">Open the world map</a></p>
    </section>
    <section class="mod w5 last" aria-labelledby="h-zones">
      <h2 id="h-zones">Zones with the most camps</h2>
      <ol class="rank">
        <li><a class="who" href="zone.html">Saltmarsh Reach</a><span class="val">5,210 visits</span><span class="track"><i style="width:100%"></i></span></li>
        <li><a class="who" href="zone.html">Hollowmere</a><span class="val">4,402</span><span class="track"><i style="width:84%"></i></span></li>
        <li><a class="who" href="zone.html">The Cinder Flats</a><span class="val">3,618</span><span class="track"><i style="width:69%"></i></span></li>
        <li><a class="who" href="zone.html">Thornwall</a><span class="val">2,950</span><span class="track"><i style="width:57%"></i></span></li>
        <li><a class="who" href="zone.html">Pale Rest</a><span class="val">1,877</span><span class="track"><i style="width:36%"></i></span></li>
      </ol>
    </section>

    <section class="mod w7 first" aria-labelledby="h-do">
      <h2 id="h-do">What players do by the fire</h2>
      <ol class="rank plain">
        <li><span class="who">Away from the keyboard</span><span class="val">34% of camp time</span><span class="track"><i style="width:34%"></i></span></li>
        <li><span class="who">Crafting</span><span class="val">19%</span><span class="track"><i style="width:19%"></i></span></li>
      </ol>
    </section>
    <section class="mod w5 last" aria-labelledby="h-end">
      <h2 id="h-end">How a visit ends</h2>
      <ol class="rank">
        <li><span class="who">Walks away</span><span class="val">71%</span><span class="track"><i style="width:71%"></i></span></li>
        <li><span class="who">Logs out by the fire</span><span class="val">27%</span><span class="track"><i style="width:27%"></i></span></li>
        <li><span class="who">Dies within five minutes</span><span class="val">2%</span><span class="track"><i style="width:2%"></i></span></li>
      </ol>
    </section>

    <section class="mod w12 first last camp-mod" aria-labelledby="h-top">
      <h2 id="h-top">Most time by the fire</h2>
      <div class="scroll-x"><table class="data" style="margin-top:1.1rem">
        <thead><tr><th class="rk">#</th><th>Character</th><th>Class</th><th class="n">Time by the fire</th><th>Ruleset</th></tr></thead>
        <tbody>
          <tr><td class="rk">1</td><td><a href="character.html">Maren-Ashlow</a></td><td>Orc Warrior</td><td class="n">1d 02h 40m</td><td>Hardcore</td></tr>
          <tr><td class="rk">2</td><td><a href="character.html">Tolvir-Greane</a></td><td>Human Mage</td><td class="n">21h 12m</td><td>Standard</td></tr>
          <tr><td class="rk">3</td><td><a href="character.html">Hesk-Brannoch</a></td><td>Tauren Druid</td><td class="n">19h 55m</td><td>Slow</td></tr>
          <tr><td class="rk">4</td><td><a href="character.html">Ysolde-Varr</a></td><td>Night Elf Hunter</td><td class="n">17h 03m</td><td>Standard</td></tr>
          <tr><td class="rk">5</td><td><a href="character.html">Dunmar-Keel</a></td><td>Dwarf Paladin</td><td class="n">15h 48m</td><td>Hardcore</td></tr>
        </tbody></table></div>
    </section>
  </div>
</div>`, `
const rand = Comp.rng(21);
Comp.bars("len", { w: 400, h: 250, x0: -0.5, x1: 5.5, y1: 30, yt: [0, 10, 20, 30], yf: (v) => v + "%", xt: [0, 1, 2, 3, 4, 5], xf: (v) => ["<30s", "30s", "1m", "2m", "5m", "10m+"][v],
  data: [[0, 17], [1, 12], [2, 26], [3, 24], [4, 13], [5, 8]] });
const lvl = [], rest = [];
for (let l = 1; l <= 60; l++) {
  lvl.push([l, 0.08 + 0.34 * Math.exp(-((l - 27) ** 2) / 260) + 0.1 * Math.exp(-((l - 52) ** 2) / 60) + (rand() - 0.5) * 0.03]);
  rest.push([l, 0.04 + 0.2 * Math.exp(-((l - 30) ** 2) / 300) + (rand() - 0.5) * 0.02]);
}
Comp.lines("lvl", { w: 380, h: 250, x0: 1, x1: 60, y1: 0.6, yt: [0, 0.2, 0.4, 0.6], xt: [1, 20, 40, 60], series: [{ cls: "a", pts: lvl, mark: 26 }] });
Comp.lines("rest", { w: 380, h: 250, x0: 1, x1: 60, y1: 0.3, yt: [0, 0.1, 0.2, 0.3], xt: [1, 20, 40, 60], series: [{ cls: "a", pts: rest, mark: 29 }] });
Comp.hours("when", (d, h) => {
  const night = Math.exp(-((h - 21) ** 2) / 14) + 0.5 * Math.exp(-((h - 13) ** 2) / 10), week = d >= 5 ? 1.25 : d === 4 ? 1.05 : 0.85;
  return Math.max(0, Math.min(4, Math.round(night * week * 3.6 + (rand() - 0.5) * 0.9)));
});
Comp.heatmap("where", { seed: 9, hot: [[2700, 6500, 520, 260], [3300, 4900, 600, 300], [4700, 2500, 480, 220], [6400, 2700, 420, 170], [7700, 4000, 520, 240], [5600, 4300, 380, 120]] });
`);

// ------------------------------------------------------------------------------- rulesets
page("rulesets.html", "Scarlet rulesets compared", "rulesets", `
<div class="wrap">
  <section class="pg-head" aria-labelledby="h1">
    <div><h1 id="h1">Rulesets compared</h1>
    <p class="sub">A ruleset is how a character plays: the experience preset, hardcore or not, and a PvE or PvP server. Each row links to its own dashboard.</p></div>
  </section>
  <div class="sheet">
    <section class="mod w12 first last" aria-labelledby="h-tbl">
      <h2 id="h-tbl" class="vh">Every ruleset</h2>
      <div class="scroll-x"><table class="data">
        <thead><tr><th>Ruleset</th><th class="n">Characters</th><th class="n">Hours</th><th class="n">Median to 60</th><th class="n">Deaths an hour</th><th class="n">Reached 60</th><th class="n">XP from quests</th></tr></thead>
        <tbody>
          <tr><td><a href="stats.html">Standard, PvE</a></td><td class="n">612</td><td class="n">19,870</td><td class="n">5d 21h</td><td class="n">0.41</td><td class="n">14%</td><td class="n">47%</td></tr>
          <tr><td><a href="stats.html">Standard, PvP</a></td><td class="n">288</td><td class="n">9,412</td><td class="n">6d 9h</td><td class="n">0.58</td><td class="n">11%</td><td class="n">44%</td></tr>
          <tr><td><a href="stats.html">Hardcore, PvE</a><span class="tag hc">HC</span></td><td class="n">204</td><td class="n">6,330</td><td class="n">7d 2h</td><td class="n">0.03</td><td class="n">4%</td><td class="n">52%</td></tr>
          <tr><td><a href="stats.html">Slow, PvE</a></td><td class="n">131</td><td class="n">5,105</td><td class="n">11d 6h</td><td class="n">0.37</td><td class="n">3%</td><td class="n">49%</td></tr>
          <tr><td><a href="stats.html">Hardcore, PvP</a><span class="tag hc">HC</span></td><td class="n">49</td><td class="n">1,190</td><td class="n">Too few characters</td><td class="n">0.05</td><td class="n">0%</td><td class="n">50%</td></tr>
        </tbody></table></div>
    </section>
    <section class="mod w8 first" aria-labelledby="h-c">
      <h2 id="h-c">Played time to reach each level</h2>
      <div class="plot"><svg id="cmp" role="img" aria-label="Median played hours to each level for three rulesets"></svg></div>
      <ul class="key"><li><i class="k-line" style="background:var(--accent)"></i>Standard, PvE</li><li><i class="k-line" style="background:var(--fg)"></i>Hardcore, PvE</li><li><i class="k-line" style="background:var(--s-dungeon)"></i>Slow, PvE</li></ul>
    </section>
    <section class="mod last" aria-labelledby="h-s">
      <h2 id="h-s">Hardcore characters still alive</h2>
      <div class="plot"><svg id="surv" role="img" aria-label="Share of hardcore characters that reached each level without dying"></svg></div>
      <p class="note">Half of hardcore characters die before level 19.</p>
    </section>
  </div>
</div>`, `
const mk = (k) => { const a = []; let t = 0; for (let l = 1; l <= 60; l++) { t += (0.12 + Math.pow(l / 60, 2.1) * 5.2) * k; a.push([l, t]); } return a; };
Comp.lines("cmp", { x0: 1, x1: 60, y1: 300, yt: [0, 100, 200, 300], yf: (v) => v + "h", xt: [1, 10, 20, 30, 40, 50, 60], series: [{ cls: "c", pts: mk(1.85) }, { cls: "b", pts: mk(1.2) }, { cls: "a", pts: mk(1) }] });
Comp.lines("surv", { w: 380, h: 250, x0: 1, x1: 60, y1: 100, yt: [0, 50, 100], yf: (v) => v + "%", xt: [1, 20, 40, 60], series: [{ cls: "a", pts: Array.from({ length: 60 }, (_, i) => [i + 1, 100 * Math.exp(-i / 26)]) }] });
`);

// --------------------------------------------------------------------------- leaderboards
const lbRows = [["Maren-Ashlow", "Orc Warrior", "3d 19h 02m", "Standard, PvE", ""], ["Tolvir-Greane", "Troll Mage", "3d 22h 41m", "Standard, PvE", ""], ["Ysolde-Harrowick", "Undead Priest", "4d 01h 16m", "Standard, PvP", ""], ["Bram-Coldwater", "Tauren Druid", "4d 03h 50m", "Standard, PvE", ""], ["Senna-Vale", "Orc Hunter", "4d 06h 12m", "Hardcore, PvE", "hc"], ["Dorrin-Ashby", "Troll Shaman", "4d 09h 33m", "Standard, PvE", ""], ["Liss-Underhill", "Undead Rogue", "4d 11h 08m", "Standard, PvP", ""], ["Corwen-Pike", "Tauren Warrior", "4d 14h 47m", "Slow, PvE", ""]];
page("leaderboards.html", "Scarlet leaderboards", "leaderboards", `
<div class="wrap">
  <section class="pg-head" aria-labelledby="h1">
    <div><h1 id="h1">Fastest to level 60</h1>
    <p class="sub">Played time from the first recorded session to level 60. Only characters whose owner made them public, and whose recording covers every level, are listed.</p></div>
    <div class="filters">
      <select class="pick" aria-label="Board"><option>Fastest to level 60</option><option>Fastest to level 40</option><option>Fastest to level 20</option><option>Highest level without dying</option><option>Most quests turned in</option><option>Most dungeon runs</option><option>Most distance travelled</option></select>
      ${rulesetSeg}
    </div>
  </section>
  <div class="sheet">
    <section class="mod w12 first last">
      <div class="scroll-x"><table class="data" style="font-size:1rem">
        <thead><tr><th></th><th>Character</th><th>Class</th><th class="n">Played to 60</th><th>Ruleset</th><th class="n">Deaths</th><th class="n">Reached</th></tr></thead>
        <tbody>${lbRows.map((r, i) => `<tr${i === 4 ? ' class="me"' : ""}><td class="rk">${i + 1}</td><td><i class="dotc" aria-hidden="true"></i><a href="character.html">${r[0]}</a></td><td>${r[1]}</td><td class="n">${r[2]}</td><td>${r[3]}${r[4] ? '<span class="tag hc">HC</span>' : ""}</td><td class="n">${r[4] ? 0 : 20 + i * 7}</td><td class="n">${18 + i} Nov</td></tr>`).join("")}</tbody>
      </table></div>
      <p class="note">Your character is highlighted when you are signed in. 212 public characters have reached level 60. <a href="#">Next 50</a></p>
    </section>
  </div>
</div>`);

// --------------------------------------------------------------------------------- search
page("search.html", "Search Scarlet", "search", `
<div class="wrap">
  <section class="pg-head" aria-labelledby="h1" style="grid-template-columns:minmax(0,1fr)">
    <div><h1 id="h1">Search</h1>
      <form class="search-box" role="search"><label class="vh" for="sq">Search for</label><input id="sq" type="search" value="marsh"><button class="btn" type="submit">Search</button></form>
      <p class="sub">24 results for "marsh". Characters appear only when their owner made them public.</p></div>
  </section>
  <div class="sheet">
    <section class="mod w12 first last" style="padding-bottom:1rem"><div class="seg" role="group" aria-label="Kind"><a href="#" aria-current="true">All 24</a><a href="#">Characters 2</a><a href="#">Creatures 9</a><a href="#">Items 6</a><a href="#">Objects 1</a><a href="#">Zones 2</a><a href="#">Quests 4</a></div></section>
    <section class="mod w5 first"><h2 class="kind">Characters <span>2</span></h2>
      <a class="hit" href="character.html"><b>Marsha-Venn</b><span>Level 44 Troll Hunter · Standard, PvE</span><span class="val">5d 2h played</span></a>
      <a class="hit" href="character.html"><b>Tolmarsh-Greane</b><span>Level 12 Orc Warrior · Hardcore, PvE</span><span class="val">9h played</span></a>
      <h2 class="kind" style="margin-top:2rem">Zones <span>2</span></h2>
      <a class="hit" href="zone.html"><b>Saltmarsh Reach</b><span>Levels 18 to 27 · 6,480 deaths</span><span class="val">3,912 hours</span></a>
      <a class="hit" href="zone.html"><b>Marshlight Fen</b><span>Levels 33 to 38 · 1,022 deaths</span><span class="val">804 hours</span></a>
    </section>
    <section class="mod w7 last"><h2 class="kind">Creatures <span>9</span></h2>
      <a class="hit" href="creature.html"><b>Marsh Stalker</b><span>Level 22 to 24 beast · Saltmarsh Reach</span><span class="val">88,412 kills · 2,104 deaths</span></a>
      <a class="hit" href="creature.html"><b>Saltmarsh Raider</b><span>Level 23 to 25 humanoid · Saltmarsh Reach</span><span class="val">61,090 kills · 1,118 deaths</span></a>
      <a class="hit" href="creature.html"><b>Marshlight Wisp</b><span>Level 34 elemental · Marshlight Fen</span><span class="val">12,733 kills · 96 deaths</span></a>
      <p class="note"><a href="#">6 more creatures</a></p>
      <h2 class="kind" style="margin-top:2rem">Items <span>6</span></h2>
      <a class="hit" href="item.html"><b class="q3">Marshwalker Greaves</b><span>Item level 28 mail feet</span><span class="val">Drops from 3 creatures</span></a>
      <a class="hit" href="item.html"><b class="q2">Saltmarsh Cleaver</b><span>Item level 26 two-handed axe</span><span class="val">Drops from 5 creatures</span></a>
      <h2 class="kind" style="margin-top:2rem">Objects <span>1</span></h2>
      <a class="hit" href="object.html"><b>Marsh Reed Cache</b><span>Object</span><span class="val">48 openings</span></a>
      <h2 class="kind" style="margin-top:2rem">Quests <span>4</span></h2>
      <a class="hit" href="quest.html"><b>The Marsh Takes Its Due</b><span>Level 23 · Saltmarsh Reach</span><span class="val">4,210 turn-ins</span></a>
    </section>
  </div>
</div>`);

// ------------------------------------------------------------------------------- creature
const Z95 = 1.959964;
const wilson = (k, n, z = Z95) => {
  const z2 = z ** 2, p = k / n, centre = (p + z2 / (2 * n)) / (1 + z2 / n);
  const spread = z * Math.sqrt(p * (1 - p) / n + z2 / (4 * n * n)) / (1 + z2 / n);
  return { p, low: Math.max(0, centre - spread), high: Math.min(1, centre + spread) };
};
const newcombe = (first, second, z = Z95) => {
  const a = wilson(first[0], first[1], z), b = wilson(second[0], second[1], z), d = b.p - a.p;
  return { d, low: d - Math.sqrt((b.p - b.low) ** 2 + (a.high - a.p) ** 2), high: d + Math.sqrt((b.high - b.p) ** 2 + (a.p - a.low) ** 2) };
};
const pct = (share) => (share * 100).toFixed(share > 0 && share < 0.01 ? 2 : 1);
const points = (share) => `${share < 0 ? "-" : "+"}${Math.abs(share * 100).toFixed(1)}`;
const armMark = (k, n) => {
  const { p, low, high } = wilson(k, n);
  return `<span class="rb${n < 30 ? " hollow" : ""}" role="img" aria-label="${pct(p)}, range ${pct(low)} to ${pct(high)} percent"><i class="rng" style="left:${(low * 100).toFixed(1)}%;width:${((high - low) * 100).toFixed(1)}%"></i><i class="pt" style="left:${(p * 100).toFixed(1)}%"></i></span>`;
};
const armsScale = `<div class="arms-scale" aria-hidden="true"><div class="arms-ticks"><i></i><div><span>0</span><span>50</span><span>100%</span></div><i></i></div></div>`;
const armRow = (name, href, arms, z, cls = "") => {
  const cells = arms.map(([label, k, n, tags = []]) => `<div class="arm"><span class="al">${label}${tags.map((tag) => `<span class="tag">${tag}</span>`).join("")}</span>${armMark(k, n)}<span class="kn">${k} of ${n}</span></div>`).join("");
  const [a, b] = arms, wide = newcombe([a[1], a[2]], [b[1], b[2]]), adjusted = newcombe([a[1], a[2]], [b[1], b[2]], z);
  const marked = a[2] >= 30 && b[2] >= 30 && (adjusted.low > 0 || adjusted.high < 0);
  const tagsOf = (arm) => arm[3] ?? [];
  const verdict = marked
    ? `<p class="arm-verdict"><b class="mark">${b[0]} ${points(wide.d)} points, ${pct(wide.low)} to ${pct(wide.high)}</b>${[a, b].map((arm) => tagsOf(arm).length ? `<span class="mut">${arm[0]}</span>${tagsOf(arm).map((tag) => `<span class="tag">${tag}</span>`).join("")}` : "").join("")}<span class="tag">different characters</span></p>`
    : `<p class="arm-verdict mut">no difference found</p>`;
  return `<div class="arm-row"><a class="arm-item${cls ? ` ${cls}` : ""}" href="${href}">${name}</a><div class="arm-set">${cells}${verdict}</div></div>`;
};
const dropRow = (name, href, k, n, per, cls = "", how = null, ofKills = null) => {
  const { p, low, high } = wilson(k, n), tag = cls === "quest" ? `<span class="tag hc">quest item</span>` : "";
  const link = `<a href="${href}"${cls && cls !== "quest" ? ` class="${cls}"` : ""}>${name}</a>${tag}`;
  const rate = n < 10
    ? `<td class="n"><span class="mut">–</span></td><td class="n">–</td>`
    : `<td class="n"><span class="rb${n < 30 ? " hollow" : ""}" role="img" aria-label="${pct(p)}, range ${pct(low)} to ${pct(high)} percent"><i class="rng" style="left:${(low * 100).toFixed(1)}%;width:${((high - low) * 100).toFixed(1)}%"></i><i class="pt" style="left:${(p * 100).toFixed(1)}%"></i></span>${pct(p)}%</td><td class="n">${pct(low)} to ${pct(high)}</td>`;
  return { low: n < 10 ? -1 : low, k, html: `<tr><td>${link}</td>${how === null ? "" : `<td>${how}</td>`}${rate}<td class="n">${k} of ${n}</td>${per === null ? "" : `<td class="n">${per}</td>`}${ofKills === null ? "" : `<td class="n">${ofKills}</td>`}</tr>` };
};
const ranked = (rows) => rows.sort((a, b) => b.low - a.low || b.k - a.k).map((row) => row.html).join("\n          ");

page("creature.html", "Marsh Stalker: kills, deaths and drops", "search", `
<div class="wrap">
  <section class="pg-head" aria-labelledby="h1">
    <div><h1 id="h1">Marsh Stalker</h1><p class="sub">Level 22 to 24 beast (crocolisk), normal. Seen in <a href="zone.html">Saltmarsh Reach</a>.</p></div>
    <div class="filters">${rulesetSeg}</div>
  </section>
  <section class="week n7" aria-label="Totals">
    <div><dt>Kills</dt><dd>412</dd></div><div><dt>Characters killed</dt><dd>9</dd></div><div><dt>Deaths per 1,000 kills</dt><dd>21.8</dd></div>
    <div><dt>Median killer level</dt><dd>23</dd></div><div><dt>XP a kill at level 23</dt><dd>214</dd></div><div><dt>Corpses looted</dt><dd>126</dd></div>
    <div><dt>Corpses looted of solo kills</dt><dd>77%<small>49 of 64</small></dd></div>
  </section>
  <div class="sheet">
    <section class="mod w7 first" aria-labelledby="h-d">
      <div class="mod-head"><h2 id="h-d">Drops</h2>
        <div class="seg" role="group" aria-label="Loot rule"><a href="#" aria-current="true">All rules</a><a href="#">Group loot</a><a href="#">Free for all</a><span class="off" aria-disabled="true">Solo</span></div></div>
      <div class="scroll-x"><table class="data" style="margin-top:1.1rem">
        <thead><tr><th>Item</th><th class="n">Drop rate</th><th class="n">Range</th><th class="n">Corpses</th><th class="n">Per drop</th><th class="n">Of kills</th></tr></thead>
        <tbody>
          ${ranked([dropRow("Stalker Hide", "item.html", 52, 126, "1.0", "", null, "26 of 64"), dropRow("Cracked Tooth", "item.html", 36, 126, "1.0", "", null, "18 of 64"), dropRow("Saltmarsh Cleaver", "item.html", 2, 126, "1.0", "q2", null, "1 of 64"), dropRow("Marshwalker Greaves", "item.html", 1, 126, "1.0", "q3", null, "0 of 64")])}
        </tbody></table></div>
      <p class="note">A drop rate is corpses that held the item over corpses looted for the first time. The band is the 95% range; from 10 to 29 corpses it is hollow, and under 10 the row shows its counts only. Drop rates cover every ruleset. Of kills is corpses that held the item over the kills the character got credit for while playing alone; a kill nobody looted counts against it.</p>
      <p class="note"><a href="loot.html">All loot</a></p>
    </section>
    <section class="mod w5 last" aria-labelledby="h-w">
      <h2 id="h-w">Where it was met</h2>
      <div class="mapbox"><canvas id="map" role="img" aria-label="Map cells where players stood when they met this creature"></canvas></div>
      <p class="note">Cells mark where the player stood, not where the creature was.</p>
    </section>
    <section class="mod w7 first" aria-labelledby="h-l">
      <h2 id="h-l">Deaths to it, by the character's level</h2>
      <div class="plot"><svg id="dl" role="img" aria-label="Deaths to this creature by character level"></svg></div>
    </section>
    <section class="mod w5 last" aria-labelledby="h-k">
      <h2 id="h-k">Skinning</h2>
      <div class="scroll-x"><table class="data" style="margin-top:1.1rem">
        <thead><tr><th>Item</th><th class="n">Share of skins</th><th class="n">Range</th><th class="n">Skins</th></tr></thead>
        <tbody>
          ${ranked([dropRow("Light Leather", "item.html", 52, 78, null), dropRow("Ruined Leather Scraps", "item.html", 9, 78, null), dropRow("Scaly Marsh Hide", "item.html", 15, 78, null)])}
        </tbody></table></div>
      <p class="note">A share is skins that held the item over skins of this creature read for the first time. The band is the 95% range. Skinning does not depend on the loot rule.</p>
    </section>
    <section class="mod w12 first last" aria-labelledby="h-r">
      <h2 id="h-r">Drop rate by loot rule</h2>
      <div class="arms">
        ${armsScale}
        ${armRow("Stalker Hide", "item.html", [["Group loot", 41, 71], ["Free for all", 8, 42, ["one reader"]]], 2.2414)}
        ${armRow("Cracked Tooth", "item.html", [["Group loot", 22, 71], ["Free for all", 8, 42, ["one reader"]]], 2.2414)}
      </div>
      <p class="note">Items with 10 drops or more. A mark is 10 to 29 corpses hollow and 30 or more solid; a rule under 10 corpses is not drawn: solo 8 and round robin 5. A difference is marked only when both rules have 30 corpses and the 95% range of the difference stays off zero after the number of items shown is taken into account. Free for all counts the one character who read most of its corpses, since two players of a group open the same corpse. <a href="groups.html">Loot rules and group size</a> for this creature.</p>
    </section>
    <section class="mod w12 first last" aria-labelledby="h-z">
      <h2 id="h-z">Drop rate by group size under group loot</h2>
      <div class="arms">
        ${armsScale}
        ${armRow("Stalker Hide", "item.html", [["Size 2", 31, 52], ["Size 3", 8, 14]], 2.2414)}
        ${armRow("Cracked Tooth", "item.html", [["Size 2", 16, 52], ["Size 3", 5, 14]], 2.2414)}
      </div>
      <p class="note">Size 5 has 5 corpses and is not drawn.</p>
    </section>
    <section class="mod w7 first last" aria-labelledby="h-h">
      <h2 id="h-h">How it kills</h2>
      <ol class="rank"><li><span class="who">Melee</span><span class="val">71% of killing blows</span><span class="track"><i style="width:71%"></i></span></li><li><span class="who">Rending Bite</span><span class="val">24%</span><span class="track"><i style="width:24%"></i></span></li><li><span class="who">Thrash</span><span class="val">5%</span><span class="track"><i style="width:5%"></i></span></li></ol>
    </section>
  </div>
</div>`, `
Comp.heatmap("map", { seed: 3, hot: [[3600, 4800, 800, 260], [5300, 4300, 500, 120]] });
Comp.bars("dl", { x0: 14, x1: 34, y1: 400, yt: [0, 200, 400], xt: [15, 20, 25, 30], data: Array.from({ length: 19 }, (_, i) => [15 + i, 380 * Math.exp(-Math.pow((i - 7) / 3.4, 2)) + 6]) });
`);

// ----------------------------------------------------------------------------------- item
const zoneRate = (zone, k, n) => {
  const { p, low, high } = wilson(k, n);
  return `<tr><td><a href="zone.html">${zone}</a></td><td class="n">${k}</td><td class="n">${n < 10 ? `<span class="mut">${k} of ${n}</span>` : `${(p * 100).toFixed(1)} <span class="mut">${pct(low)} to ${pct(high)}</span>`}</td></tr>`;
};
const itemZones = `
    <section class="mod first" aria-labelledby="h-z"><h2 id="h-z">By zone</h2>
      <div class="scroll-x"><table class="data" style="margin-top:1.1rem"><thead><tr><th>Zone</th><th class="n">Dropped</th><th class="n">Per 100 corpses</th></tr></thead>
        <tbody>${zoneRate("Saltmarsh Reach", 19, 251)}${zoneRate("Hollowmere", 2, 18)}${zoneRate("Thornwall", 1, 7)}</tbody></table></div>
      <p class="note">Creatures looted in the zone, whatever they were. Per 100 corpses is how many of every 100 corpses looted there held the item.</p>
    </section>
    <section class="mod w8 last" aria-labelledby="h-m2"><h2 id="h-m2">Where it was looted</h2>
      <div class="mapbox" style="aspect-ratio:16 / 9"><canvas id="map" role="img" aria-label="Map cells where players stood when they looted this item"></canvas></div>
      <p class="note">Cells mark where the player stood, not where the creature was.</p>
    </section>`;
page("item.html", "Marshwalker Greaves: where it drops", "search", `
<div class="wrap">
  <section class="pg-head" aria-labelledby="h1">
    <div><h1 id="h1" class="q3">Marshwalker Greaves</h1><p class="sub">Item level 28 mail feet. Requires level 23. Binds when equipped. Sells for <span class="money">21<i class="s">s</i>40<i class="c">c</i></span>.</p></div>
    <div class="filters">${rulesetSeg}</div>
  </section>
  <section class="week n6" aria-label="Totals">
    <div><dt>Dropped</dt><dd>23</dd></div><div><dt>Sources</dt><dd>5</dd></div><div><dt>Best drop rate</dt><dd>21.0%</dd></div>
    <div><dt>Won by need</dt><dd>61%</dd></div><div><dt>Median looter level</dt><dd>24</dd></div><div><dt>First recorded</dt><dd>15 Nov</dd></div>
  </section>
  <div class="sheet">
    <section class="mod w8 first" aria-labelledby="h-s">
      <div class="mod-head"><h2 id="h-s">Where it drops</h2>
        <div class="seg" role="group" aria-label="Loot rule"><a href="#" aria-current="true">All rules</a><a href="#">Solo</a><a href="#">Group loot</a><a href="#">Free for all</a></div></div>
      <div class="scroll-x"><table class="data" style="margin-top:1.1rem">
        <thead><tr><th>Source</th><th>How</th><th class="n">Drop rate</th><th class="n">Range</th><th class="n">Corpses</th><th class="n">Per drop</th></tr></thead>
        <tbody>
          ${ranked([dropRow("Hollow Warden", "creature.html", 13, 62, "1.0", "", "Loot"), dropRow("Marsh Stalker", "creature.html", 6, 126, "1.0", "", "Loot"), dropRow("Saltmarsh Raider", "creature.html", 2, 24, "1.0", "", "Loot")])}
          <tr class="hair"><td colspan="6"></td></tr>
          ${ranked([dropRow("Reed Snapper", "creature.html", 1, 7, "1.0", "", "Loot"), dropRow("Silt-Crusted Chest", "item.html", 1, 3, "1.0", "", "Container")])}
        </tbody></table></div>
      <p class="note">A drop rate is corpses of the source that held the item over corpses of the source read the same way for the first time, one row for each source and way it was read. Sources read 10 times or more rank by the low end of the 95% range; below the line, sources read fewer times show their counts only. Drop rates cover every ruleset.</p>
      <p class="note"><a href="loot.html">All loot</a></p>
    </section>
    <section class="mod last" aria-labelledby="h-r"><h2 id="h-r">Rolls in groups</h2>
      <ol class="rank"><li><span class="who">Need</span><span class="val">61% · 14 of 23</span><span class="track"><i style="width:61%"></i></span></li><li><span class="who">Greed</span><span class="val">30% · 7 of 23</span><span class="track"><i style="width:30%"></i></span></li><li><span class="who">Pass</span><span class="val">9% · 2 of 23</span><span class="track"><i style="width:9%"></i></span></li></ol>
      <p class="note">Rolls of the characters that uploaded, 23 of them in 9 groups. 3 of 9 rolls were passed by everyone.</p>
    </section>
    <section class="mod w12 first last" aria-labelledby="h-c">
      <h2 id="h-c">Who received it</h2>
      <div class="scroll-x"><table class="data" style="margin-top:1.1rem">
        <thead><tr><th>Class</th><th class="n">Received</th><th class="n">Share</th><th class="n">Won by roll</th></tr></thead>
        <tbody>
          <tr><td data-class="WARRIOR"><i class="dotc" aria-hidden="true"></i>Warrior</td><td class="n">9</td><td class="n">39.1%</td><td class="n">6 of 8</td></tr>
          <tr><td data-class="ROGUE"><i class="dotc" aria-hidden="true"></i>Rogue</td><td class="n">6</td><td class="n">26.1%</td><td class="n">2 of 5</td></tr>
          <tr><td data-class="PALADIN"><i class="dotc" aria-hidden="true"></i>Paladin</td><td class="n">5</td><td class="n">21.7%</td><td class="n">3 of 4</td></tr>
          <tr><td data-class="HUNTER"><i class="dotc" aria-hidden="true"></i>Hunter</td><td class="n">3</td><td class="n">13.0%</td><td class="n">0 of 2</td></tr>
        </tbody></table></div>
      <p class="note">What other members of a group received (their class, never their name) and what the uploader received. A member whose class is not recorded is left out.</p>
    </section>${itemZones}
  </div>
</div>`, `
Comp.heatmap("map", { seed: 11, hot: [[3600, 4800, 700, 300], [5300, 4300, 450, 160], [4100, 2900, 380, 90]] });
`);

page("container.html", "Silt-Crusted Chest: what it holds", "search", `
<div class="wrap">
  <section class="pg-head" aria-labelledby="h1" style="grid-template-columns:minmax(0,1fr)">
    <div><h1 id="h1" class="q1">Silt-Crusted Chest</h1><p class="sub">Container. Opened 14 times in <a href="zone.html">Saltmarsh Reach</a>. Holds Reed Twine, Salt-Cured Pelt or Brackwater Pearl.</p></div>
  </section>
  <section class="week" aria-label="Totals">
    <div><dt>Opened</dt><dd>14</dd></div><div><dt>Items</dt><dd>3</dd></div><div><dt>Characters</dt><dd>2</dd></div><div><dt>Dropped</dt><dd>3</dd></div>
  </section>
  <div class="sheet">
    <section class="mod w12 first last" aria-labelledby="h-h"><h2 id="h-h">What it holds</h2>
      <div class="scroll-x"><table class="data" style="margin-top:1.1rem">
        <thead><tr><th>Item</th><th class="n">Drop rate</th><th class="n">Range</th><th class="n">Opened</th><th class="n">Per drop</th></tr></thead>
        <tbody>
          ${ranked([dropRow("Reed Twine", "item.html", 11, 14, "2.1"), dropRow("Salt-Cured Pelt", "item.html", 6, 14, "1.0"), dropRow("Brackwater Pearl", "item.html", 1, 14, "1.0", "q2")])}
        </tbody></table></div>
      <p class="note">A drop rate is times opened that held the item over times opened for the first time. The band is the 95% range; from 10 to 29 openings it is hollow, and under 10 the row shows its counts only. Drop rates cover every ruleset.</p>
    </section>
  </div>
</div>`);

page("object.html", "Weathered Reed Chest: what it gives", "search", `
<div class="wrap">
  <section class="pg-head" aria-labelledby="h1" style="grid-template-columns:minmax(0,1fr)">
    <div><h1 id="h1">Weathered Reed Chest</h1><p class="sub">Opened in <a href="zone.html">Saltmarsh Reach</a> at levels 21 to 24. Gives Reed Twine, Salt-Cured Pelt or Marshwalker Greaves.</p></div>
  </section>
  <section class="week" aria-label="Totals">
    <div><dt>Opened</dt><dd>48</dd></div><div><dt>Items</dt><dd>3</dd></div><div><dt>Characters</dt><dd>2</dd></div><div><dt>Zones</dt><dd>1</dd></div>
  </section>
  <div class="sheet">
    <section class="mod w7 first" aria-labelledby="h-g">
      <div class="mod-head"><h2 id="h-g">What it gives</h2>
        <div class="seg" role="group" aria-label="Loot rule"><a href="#" aria-current="true">All rules</a><a href="#">Group loot</a><a href="#">Round robin</a><span class="off" aria-disabled="true">Free for all</span></div></div>
      <div class="scroll-x"><table class="data" style="margin-top:1.1rem">
        <thead><tr><th>Item</th><th class="n">Drop rate</th><th class="n">Range</th><th class="n">Opened</th><th class="n">Per drop</th></tr></thead>
        <tbody>
          ${ranked([dropRow("Reed Twine", "item.html", 28, 48, "1.0"), dropRow("Salt-Cured Pelt", "item.html", 19, 48, "1.0"), dropRow("Marshwalker Greaves", "item.html", 1, 48, "1.0", "q3")])}
        </tbody></table></div>
      <p class="note">A drop rate is times opened that held the item over times opened for the first time. The band is the 95% range; from 10 to 29 openings it is hollow, and under 10 the row shows its counts only. Drop rates cover every ruleset.</p>
      <p class="note"><a href="loot.html">All loot</a></p>
    </section>
    <section class="mod w5 last" aria-labelledby="h-w">
      <h2 id="h-w">Where it was opened</h2>
      <div class="mapbox" style="aspect-ratio:1 / 1"><canvas id="map" role="img" aria-label="Map cells where players stood when they opened this object"></canvas></div>
      <p class="note">Cells mark where the player stood, not where the object was.</p>
    </section>
  </div>
</div>`, `
Comp.heatmap("map", { seed: 7, hot: [[3600, 4800, 600, 260], [4100, 4500, 400, 120]] });
`);

const lootSource = (name, href, zone, level, opened, items, top, characters) => {
  const [item, k] = top, { p, low, high } = wilson(k, opened);
  const common = opened < 10 ? `<span class="mut">${k} of ${opened}</span>` : `<a href="item.html">${item}</a> <span class="mut">${pct(p)}%, ${pct(low)} to ${pct(high)}</span>`;
  return `<tr><td><a href="${href}">${name}</a></td><td><a href="zone.html">${zone}</a></td><td class="n">${level}</td><td class="n">${opened}</td><td class="n">${items}</td><td>${opened < 10 ? `<a href="item.html">${item}</a> ` : ""}${common}</td><td class="n">${characters}</td></tr>`;
};
page("loot.html", "Scarlet loot: what creatures and objects give and how often", "loot", `
<div class="wrap">
  <section class="pg-head" aria-labelledby="h1" style="grid-template-columns:minmax(0,1fr)">
    <div><h1 id="h1">Loot</h1><p class="sub">1,284 corpses looted and 412 skinned by 17 characters, 5 Nov to 22 Nov 2026.</p></div>
  </section>
  <section class="week n6" aria-label="Totals">
    <div><dt>Corpses looted</dt><dd>1,284</dd></div><div><dt>Skinned</dt><dd>412</dd></div><div><dt>Sources</dt><dd>61</dd></div>
    <div><dt>Items</dt><dd>188</dd></div><div><dt>Uncommon or better</dt><dd>34</dd></div><div><dt>Characters</dt><dd>17</dd></div>
  </section>
  <div class="sheet">
    <section class="mod w12 first last" aria-labelledby="h-s">
      <div class="mod-head"><h2 id="h-s">Sources</h2>
        <div class="seg" role="group" aria-label="Show"><a href="#" aria-current="true">Creatures</a><a href="#">Objects</a><a href="#">Containers</a><a href="#">Items</a></div></div>
      <form class="refine" role="search" action="loot.html">
        <label class="vh" for="f-q">Search</label><input id="f-q" name="q" type="search" placeholder="Creature, object or item">
        <select class="pick" name="zone" aria-label="Zone"><option value="">Every zone</option><option>Saltmarsh Reach</option><option>Hollowmere</option><option>The Cinder Flats</option></select>
        <input name="min" type="number" min="1" max="70" placeholder="Level from" aria-label="Level from"><input name="max" type="number" min="1" max="70" placeholder="to" aria-label="Level to">
        <select class="pick" name="quality" aria-label="Quality"><option value="">Any quality</option><option>Uncommon or better</option><option>Rare or better</option><option>Epic</option></select>
        <select class="pick" name="sort" aria-label="Sort by"><option>Corpses looted</option><option>Items seen</option><option>Level</option></select>
        <button class="btn quiet" type="submit">Apply</button>
      </form>
      <div class="scroll-x"><table class="data" style="margin-top:1.1rem">
        <thead><tr><th>Source</th><th>Zone</th><th class="n">Level</th><th class="n">Corpses looted</th><th class="n">Items seen</th><th>Most common item</th><th class="n">Characters</th></tr></thead>
        <tbody>
          ${lootSource("Marsh Stalker", "creature.html", "Saltmarsh Reach", "22 to 24", 126, 14, ["Stalker Hide", 52], 6)}
          ${lootSource("Hollow Warden", "creature.html", "Hollowmere", "25 to 27", 62, 9, ["Warden's Cloth", 21], 4)}
          ${lootSource("Saltmarsh Raider", "creature.html", "Saltmarsh Reach", "23 to 25", 24, 7, ["Raider's Wrap", 9], 3)}
          ${lootSource("Reed Snapper", "creature.html", "Saltmarsh Reach", "19 to 21", 7, 4, ["Snapper Tooth", 4], 2)}
        </tbody></table></div>
      <p class="pager"><a href="#">Next 25</a></p>
      <p class="note">Corpses looted counts each corpse once, the first time a character read it. Most common item is the item that was in the most of them, with its 95% range; under 10 corpses it shows its counts only. Skinning is counted apart. 295 fishing catches are on <a href="fishing.html">Fishing</a>.</p>
    </section>
    <section class="mod w7 first" aria-labelledby="h-z">
      <h2 id="h-z">Zones</h2>
      <ol class="rank plain"><li><a class="who" href="loot.html">Saltmarsh Reach</a><span class="val">612</span><span class="track"><i style="width:100%"></i></span></li><li><a class="who" href="loot.html">Hollowmere</a><span class="val">340</span><span class="track"><i style="width:56%"></i></span></li><li><a class="who" href="loot.html">The Cinder Flats</a><span class="val">201</span><span class="track"><i style="width:33%"></i></span></li><li><a class="who" href="loot.html">Greywater Deep</a><span class="val">88</span><span class="track"><i style="width:14%"></i></span></li></ol>
    </section>
    <section class="mod w5 last" aria-labelledby="h-r">
      <h2 id="h-r">Loot rule and group size</h2>
      <ol class="rank plain"><li><a class="who" href="groups.html">Solo</a><span class="val">311</span><span class="track"><i style="width:60%"></i></span></li><li><a class="who" href="groups.html">Group loot, 2 players</a><span class="val">520</span><span class="track"><i style="width:100%"></i></span></li><li><a class="who" href="groups.html">Free for all, 2 players</a><span class="val">210</span><span class="track"><i style="width:40%"></i></span></li><li><a class="who" href="groups.html">Group loot, 5 players</a><span class="val">78</span><span class="track"><i style="width:15%"></i></span></li></ol>
    </section>
  </div>
</div>`);

const rateTd = (k, n) => {
  const { p, low, high } = wilson(k, n);
  return n < 10
    ? `<td class="n"><span class="mut">–</span></td>`
    : `<td class="n"><span class="rb${n < 30 ? " hollow" : ""}" role="img" aria-label="${pct(p)}, range ${pct(low)} to ${pct(high)} percent"><i class="rng" style="left:${(low * 100).toFixed(1)}%;width:${((high - low) * 100).toFixed(1)}%"></i><i class="pt" style="left:${(p * 100).toFixed(1)}%"></i></span>${pct(p)}%</td>`;
};
const classTd = (token, label) => `<td data-class="${token}"><i class="dotc" aria-hidden="true"></i>${label}</td>`;
const rollRow = (rule, quality, need, greed, pass) => `<tr><td>${rule}</td><td>${quality}</td><td class="n">${need + greed + pass}</td>${rateTd(need, need + greed + pass)}${rateTd(greed, need + greed + pass)}${rateTd(pass, need + greed + pass)}</tr>`;
page("groups.html", "Loot rules and group size", "search", `
<div class="wrap">
  <section class="pg-head" aria-labelledby="h1">
    <div><h1 id="h1">Loot rules and group size</h1><p class="sub">874 corpses looted under 4 loot rules at group sizes 1 to 5.</p></div>
  </section>
  <section class="week n6" aria-label="Totals">
    <div><dt>Corpses looted</dt><dd>874</dd></div><div><dt>Loot rules seen</dt><dd>4</dd></div><div><dt>Largest group</dt><dd>5</dd></div>
    <div><dt>Creatures under two rules</dt><dd>9</dd></div><div><dt>Rolls</dt><dd>46</dd></div><div><dt>Groups recorded</dt><dd>112</dd></div>
  </section>
  <div class="sheet">
    <section class="mod w5 first" aria-labelledby="h-h">
      <h2 id="h-h">What a corpse held, by loot rule</h2>
      <div class="scroll-x"><table class="data" style="margin-top:1.1rem">
        <thead><tr><th>Rule</th><th class="n">Items per corpse</th><th class="n">Corpses</th><th class="n">Creatures</th></tr></thead>
        <tbody><tr><td>Group loot</td><td class="n">1.13</td><td class="n">40</td><td class="n">1</td></tr><tr><td>Free for all</td><td class="n">1.34</td><td class="n">59</td><td class="n">1</td></tr></tbody></table></div>
      <p class="note">Free for all holds 0.21 items more per corpse than group loot, range -0.18 to 0.60. This rests on 1 creature with 10 corpses or more under both rules, 99 corpses, and counts each creature by the corpses it has under both. 8 more creatures were seen under both rules with fewer corpses.</p>
    </section>
    <section class="mod w7 last" aria-labelledby="h-k">
      <h2 id="h-k">What the looter kept</h2>
      <div class="scroll-x"><table class="data" style="margin-top:1.1rem">
        <thead><tr><th>Rule</th><th class="n">Size</th><th class="n">Corpses</th><th class="n">Received</th><th class="n">Items</th></tr></thead>
        <tbody>
          <tr><td>Group loot</td><td class="n">2</td><td class="n">493</td><td class="n">97%</td><td class="n">421 of 434</td></tr>
          <tr><td>Free for all</td><td class="n">2</td><td class="n">360</td><td class="n">102%</td><td class="n">386 of 378</td></tr>
          <tr><td>Group loot</td><td class="n">3</td><td class="n">31</td><td class="n">96%</td><td class="n">24 of 25</td></tr>
          <tr><td>Free for all</td><td class="n">3</td><td class="n">20</td><td class="n">81%</td><td class="n">17 of 21</td></tr>
          <tr><td>Group loot<span class="tag">other creatures</span></td><td class="n">5</td><td class="n">78</td><td class="n">74%</td><td class="n">43 of 58</td></tr>
          <tr><td>Solo</td><td class="n">1</td><td class="n">21</td><td class="n">100%</td><td class="n">17 of 17</td></tr>
        </tbody></table></div>
      <p class="note">Items the uploader received from a corpse over the items the corpse held, by the rule and size in force when it was looted. Under 100% means other members took items.</p>
    </section>
    <section class="mod w7 first" aria-labelledby="h-s">
      <h2 id="h-s">Your share of group loot</h2>
      <div class="scroll-x"><table class="data" style="margin-top:1.1rem">
        <thead><tr><th>Rule</th><th class="n">Size</th><th class="n">Yours</th><th class="n">Others</th><th class="n">Your share</th><th class="n">Equal share</th></tr></thead>
        <tbody>
          <tr><td>Group loot</td><td class="n">2</td><td class="n">31</td><td class="n">29</td>${rateTd(31, 60)}<td class="n">50.0%</td></tr>
          <tr><td>Group loot</td><td class="n">3</td><td class="n">9</td><td class="n">14</td>${rateTd(9, 23)}<td class="n">33.3%</td></tr>
          <tr><td>Group loot</td><td class="n">5</td><td class="n">4</td><td class="n">11</td>${rateTd(4, 15)}<td class="n">20.0%</td></tr>
        </tbody></table></div>
      <p class="note">Yours is what the uploaders received from corpses. Others is what the uploaders' group mates received from corpses, as the client reported it; an item pushed to a mate is left out. Pooled over uploaders, so each member's mean share is the figure.</p>
    </section>
    <section class="mod w5 last" aria-labelledby="h-z">
      <h2 id="h-z">Kills an hour by group size</h2>
      <div class="scroll-x"><table class="data" style="margin-top:1.1rem">
        <thead><tr><th class="n">Size</th><th class="n">Kills</th><th class="n">Hours</th><th class="n">Kills an hour</th></tr></thead>
        <tbody>
          <tr><td class="n">2</td><td class="n">1,204</td><td class="n">31.4</td><td class="n">38.3</td></tr>
          <tr><td class="n">3</td><td class="n">212</td><td class="n">5.9</td><td class="n">35.9</td></tr>
          <tr><td class="n">5</td><td class="n">96</td><td class="n">3.1</td><td class="n">31.0</td></tr>
        </tbody></table></div>
      <p class="note">Kills a member of a group got credit for, over the hours that member spent in a group of that size. Time alone is not counted.</p>
    </section>
    <section class="mod w12 first last" aria-labelledby="h-r">
      <h2 id="h-r">Rolls</h2>
      <div class="scroll-x"><table class="data" style="margin-top:1.1rem">
        <thead><tr><th>Rule</th><th>Item quality</th><th class="n">Rolls</th><th class="n">Need</th><th class="n">Greed</th><th class="n">Pass</th></tr></thead>
        <tbody>${rollRow("Group loot", "Uncommon", 14, 7, 2)}${rollRow("Group loot", "Rare", 5, 3, 1)}${rollRow("Need before greed", "Uncommon", 6, 9, 3)}</tbody></table></div>
      <div class="scroll-x"><table class="data" style="margin-top:1.4rem">
        <thead><tr><th>Rule</th><th class="n">Threshold</th><th class="n">Corpses in groups</th><th class="n">Items rolled for per 100 corpses</th></tr></thead>
        <tbody><tr><td>Group loot</td><td class="n">Uncommon</td><td class="n">604</td><td class="n">5.1</td></tr><tr><td>Need before greed</td><td class="n">Uncommon</td><td class="n">88</td><td class="n">6.8</td></tr></tbody></table></div>
      <p class="note">Rolls of the characters that uploaded. A roll another member made reaches every uploader in the group, so it is counted by none of them.</p>
    </section>
    <section class="mod w12 first last" aria-labelledby="h-g">
      <h2 id="h-g">Group composition</h2>
      <div class="scroll-x"><table class="data" style="margin-top:1.1rem">
        <thead><tr><th>Class</th><th>Armour</th><th class="n">Rolls</th><th class="n">Need</th></tr></thead>
        <tbody>
          <tr>${classTd("WARRIOR", "Warrior")}<td>Plate</td><td class="n">12</td>${rateTd(9, 12)}</tr>
          <tr>${classTd("ROGUE", "Rogue")}<td>Leather</td><td class="n">11</td>${rateTd(5, 11)}</tr>
          <tr>${classTd("PRIEST", "Priest")}<td>Cloth</td><td class="n">4</td><td class="n"><span class="mut">–</span></td></tr>
        </tbody></table></div>
      <div class="scroll-x"><table class="data" style="margin-top:1.4rem">
        <thead><tr><th>Skinner in the group</th><th class="n">Corpses looted</th><th class="n">Corpses skinned</th><th class="n">Skins per corpse</th></tr></thead>
        <tbody><tr><td>Yes</td><td class="n">96</td><td class="n">71</td><td class="n">0.74</td></tr><tr><td>No</td><td class="n">311</td><td class="n">0</td><td class="n">0.00</td></tr></tbody></table></div>
      <div class="scroll-x"><table class="data" style="margin-top:1.4rem">
        <thead><tr><th>Tank</th><th>Healer</th><th class="n">Kills</th><th class="n">Hours</th><th class="n">Kills an hour</th></tr></thead>
        <tbody>
          <tr><td>Yes</td><td>Yes</td><td class="n">96</td><td class="n">3.1</td><td class="n">31.0</td></tr>
          <tr><td>No</td><td>No</td><td class="n">1,416</td><td class="n">37.3</td><td class="n">38.0</td></tr>
        </tbody></table></div>
      <p class="note">Classes, roles and levels of the members a group's uploader could see, with no names. Need is the share of rolls of the characters that uploaded and that class. A character with the social group off adds nothing here.</p>
    </section>
    <section class="mod w12 first last" aria-labelledby="h-m">
      <h2 id="h-m">Marked differences</h2>
      <div class="scroll-x"><table class="data" style="margin-top:1.1rem">
        <thead><tr><th>Source</th><th>Item</th><th>Compared</th><th class="n">Difference</th><th>Tags</th></tr></thead>
        <tbody><tr><td><a href="creature.html">Marsh Stalker</a></td><td><a href="item.html">Stalker Hide</a></td><td>Group loot 41 of 71, Free for all 8 of 42</td><td class="n">-38.7 points, -52.8 to -20.3</td><td><span class="tag">one reader</span><span class="tag">different characters</span></td></tr></tbody></table></div>
      <p class="note">A difference between loot rules or group sizes is a counting fault until shown otherwise: the game's drop table does not depend on either.</p>
    </section>
  </div>
</div>`);

// ----------------------------------------------------------------------------------- zone
const hereRate = (name, href, k, n, cls = "") => {
  const { p, low, high } = wilson(k, n);
  return `<a href="${href}"${cls ? ` class="${cls}"` : ""}>${name}</a> <span class="mut">${pct(p)}%, ${pct(low)} to ${pct(high)}</span>`;
};
const hereSources = [["Marsh Stalker", 214, "Stalker Hide", 121], ["Saltmarsh Raider", 188, "Raider's Wristwraps", 36], ["Reedwing", 97, "Reed Feather", 61], ["Mire Crawler", 64, "Crawler Chitin", 22], ["Brackwater Snapper", 41, "Snapper Meat", 27]]
  .map(([name, corpses, item, k]) => `<tr><td><a href="creature.html">${name}</a></td><td class="n">${corpses}</td><td>${hereRate(item, "item.html", k, corpses)}</td></tr>`).join("");
const hereItems = [["Stalker Hide", "", 121, "Marsh Stalker", 214], ["Reed Feather", "", 74, "Reedwing", 97], ["Raider's Wristwraps", "q2", 36, "Saltmarsh Raider", 188], ["Snapper Meat", "", 27, "Brackwater Snapper", 41], ["Crawler Chitin", "", 26, "Mire Crawler", 64]]
  .map(([item, cls, dropped, source, n]) => `<tr><td><a href="item.html"${cls ? ` class="${cls}"` : ""}>${item}</a></td><td class="n">${dropped}</td><td>${hereRate(source, "creature.html", dropped, n)}</td></tr>`).join("");
const hereLoot = `
    <section class="mod w12 first last" aria-labelledby="h-l"><h2 id="h-l">Looted here</h2>
      <div class="scroll-x"><table class="data" style="margin-top:1.1rem"><thead><tr><th>Source</th><th class="n">Corpses looted</th><th>Most common item</th></tr></thead><tbody>${hereSources}</tbody></table></div>
      <div class="scroll-x"><table class="data" style="margin-top:1.1rem"><thead><tr><th>Item</th><th class="n">Dropped</th><th>Best source</th></tr></thead><tbody>${hereItems}</tbody></table></div>
      <p class="note">Loot covers every ruleset. <a href="loot.html">All loot in Saltmarsh Reach</a></p>
    </section>`;
page("zone.html", "Saltmarsh Reach: paths, deaths and levels", "search", `
<section class="ch-head" aria-labelledby="h1" style="min-height:27rem">
  <div class="stage"><canvas id="map" role="img" aria-label="Heat from every recorded path in Saltmarsh Reach, with deaths as crosses"></canvas></div>
  <div class="ch-id wrap" style="padding-top:3rem">
    <div><h1 class="ch-name" id="h1">Saltmarsh Reach</h1>
      <p class="ch-sub"><span>Levels 18 to 27</span><span>3,912 hours played here</span><span>6,480 deaths</span></p></div>
    <ul class="legend ch-map" aria-label="Map marks">
      <li><svg viewBox="0 0 24 14" aria-hidden="true"><rect x="5" y="1" width="12" height="12" fill="#cf1f45"/></svg>paths, by how many characters</li>
      <li><svg viewBox="0 0 24 14" aria-hidden="true"><path d="M7.5 2.5l9 9M16.5 2.5l-9 9" stroke="#f0f1f3" stroke-width="1.7" stroke-linecap="round"/></svg>deaths</li>
    </ul>
  </div>
</section>
<div class="wrap">
  <section class="week n6" aria-label="Totals">
    <div><dt>Characters</dt><dd>811</dd></div><div><dt>Median stay</dt><dd>4h 50m</dd></div><div><dt>Arrive at level</dt><dd>19</dd></div>
    <div><dt>Leave at level</dt><dd>26</dd></div><div><dt>Deaths an hour</dt><dd>1.66</dd></div><div><dt>Quests</dt><dd>62</dd></div>
  </section>
  <div class="sheet">
    <section class="mod first" aria-labelledby="h-k"><h2 id="h-k">Most killed</h2>
      <ol class="rank plain"><li><a class="who" href="creature.html">Marsh Stalker</a><span class="val">88,412</span><span class="track"><i style="width:100%"></i></span></li><li><a class="who" href="creature.html">Saltmarsh Raider</a><span class="val">61,090</span><span class="track"><i style="width:69%"></i></span></li><li><a class="who" href="creature.html">Reed Snapper</a><span class="val">40,255</span><span class="track"><i style="width:46%"></i></span></li></ol></section>
    <section class="mod" aria-labelledby="h-d"><h2 id="h-d">Deadliest</h2>
      <ol class="rank"><li><a class="who" href="creature.html">Marsh Stalker</a><span class="val">2,104 deaths</span><span class="track"><i style="width:100%"></i></span></li><li><a class="who" href="creature.html">Saltmarsh Raider</a><span class="val">1,118</span><span class="track"><i style="width:53%"></i></span></li><li><span class="who">Drowning</span><span class="val">402</span><span class="track"><i style="width:19%"></i></span></li></ol></section>
    <section class="mod last" aria-labelledby="h-x"><h2 id="h-x">Experience here</h2>
      <ol class="rank"><li><span class="who">Quests</span><span class="val">49%</span><span class="track"><i class="c-quest" style="width:49%"></i></span></li><li><span class="who">Kills</span><span class="val">44%</span><span class="track"><i style="width:44%"></i></span></li><li><span class="who">Exploring</span><span class="val">7%</span><span class="track"><i class="c-explore" style="width:7%"></i></span></li></ol></section>
    <section class="mod w12 first last" aria-labelledby="h-q"><h2 id="h-q">Quests turned in here</h2>
      <div class="scroll-x"><table class="data" style="margin-top:1.1rem"><thead><tr><th>Quest</th><th class="n">Turn-ins</th><th class="n">Median level</th><th class="n">XP</th><th class="n">Abandoned</th></tr></thead>
      <tbody><tr><td><a href="quest.html">The Marsh Takes Its Due</a></td><td class="n">4,210</td><td class="n">23</td><td class="n">2,450</td><td class="n">3%</td></tr><tr><td><a href="quest.html">Reeds for the Weaver</a></td><td class="n">3,980</td><td class="n">20</td><td class="n">1,700</td><td class="n">1%</td></tr><tr><td><a href="quest.html">What the Tide Left</a></td><td class="n">2,644</td><td class="n">25</td><td class="n">2,900</td><td class="n">11%</td></tr></tbody></table></div></section>${hereLoot}
  </div>
</div>`, `
Comp.heatmap("map", { seed: 5, hot: [[3600, 4800, 900, 620], [5600, 4200, 700, 420], [7200, 3300, 650, 340], [4700, 2600, 500, 200], [6400, 5300, 520, 180]], deaths: [[3900, 4500], [5200, 4400], [5750, 3900], [7100, 3000], [6900, 3600], [4500, 2700], [3300, 5100], [6100, 5200]] });
`);

// ---------------------------------------------------------------------------------- quest
page("quest.html", "The Marsh Takes Its Due: turn-ins and levels", "search", `
<div class="wrap">
  <section class="pg-head" aria-labelledby="h1">
    <div><h1 id="h1">The Marsh Takes Its Due</h1><p class="sub">Level 23 quest in <a href="zone.html">Saltmarsh Reach</a>. Pays 2,450 XP and <span class="money">18<i class="s">s</i></span>.</p></div>
    <div class="filters">${rulesetSeg}</div>
  </section>
  <section class="week n6" aria-label="Totals">
    <div><dt>Turn-ins</dt><dd>4,210</dd></div><div><dt>Accepted</dt><dd>4,366</dd></div><div><dt>Abandoned</dt><dd>3%</dd></div>
    <div><dt>Median level</dt><dd>23</dd></div><div><dt>Median time to finish</dt><dd>26m</dd></div><div><dt>Deaths while on it</dt><dd>0.4</dd></div>
  </section>
  <div class="sheet">
    <section class="mod w7 first" aria-labelledby="h-l"><h2 id="h-l">Level when turned in</h2><div class="plot"><svg id="ql" role="img" aria-label="Turn-ins by character level"></svg></div></section>
    <section class="mod w5 last" aria-labelledby="h-r"><h2 id="h-r">Reward chosen</h2>
      <ol class="rank"><li><a class="who q2" href="item.html">Reedbound Bracers</a><span class="val">58%</span><span class="track"><i style="width:58%"></i></span></li><li><a class="who q2" href="item.html">Tidewatcher Cord</a><span class="val">42%</span><span class="track"><i style="width:42%"></i></span></li></ol></section>
  </div>
</div>`, `
Comp.bars("ql", { x0: 17, x1: 32, y1: 1200, yt: [0, 600, 1200], xt: [18, 22, 26, 30], cls: "t", data: Array.from({ length: 14 }, (_, i) => [18 + i, 1150 * Math.exp(-Math.pow((i - 5) / 2.3, 2)) + 10]) });
`);

const warlock = (() => {
  const n = (v) => Math.round(v).toLocaleString("en-US");
  const short = (v) => (v >= 1e6 ? (v / 1e6).toFixed(1) + "M" : v >= 1e3 ? Math.round(v / 1e3) + "k" : String(Math.round(v)));
  const pct = (a, b) => Math.round((a / b) * 100) + "%";
  const sum = (a) => a.reduce((x, y) => x + y, 0);
  const rng = (seed) => { let s = seed >>> 0; return () => ((s = Math.imul(s ^ (s >>> 15), 2246822507) + 0x9e3779b9 >>> 0) / 4294967296); };

  const NAMES = {
    drainsoul: "Drain Soul", shadowburn: "Shadowburn", soulfire: "Soul Fire", ritual: "Ritual of Summoning", corruption: "Corruption", agony: "Bane of Agony",
    elements: "Curse of the Elements", recklessness: "Curse of Recklessness", tongues: "Curse of Tongues", weakness: "Curse of Weakness", exhaustion: "Curse of Exhaustion", doom: "Bane of Doom",
    shadowbolt: "Shadow Bolt", siphon: "Siphon Life", drainlife: "Drain Life", nightfall: "Nightfall", fear: "Fear", deathcoil: "Death Coil", howl: "Howl of Terror", banish: "Banish",
    enslave: "Subjugate Demon", funnel: "Health Funnel",
  };
  const gi = () => `<i class="buff-none" aria-hidden="true"></i>`;
  const sp = (k) => gi() + NAMES[k];

  const DEMONS = [["imp", "Imp", "Piptik", "d-imp"], ["voidwalker", "Voidwalker", "Zharmok", "d-void"], ["succubus", "Succubus", "Nethira", "d-succ"], ["felhunter", "Felhunter", "Shaadrun", "d-fel"], [null, "No demon out", "", "d-none"]];
  const RANKS = [[6, 30], [16, 75], [26, 140], [36, 220], [46, 310]];
  const r1 = rng(11), tapRows = [];
  for (let level = 6; level <= 52; level++) {
    const base = RANKS.filter(([at]) => at <= level).pop()[1];
    const taps = Math.round((70 + level * 5.4) * (0.72 + r1() * 0.6));
    const health = taps * Math.round(base * (1 + level * 0.012));
    tapRows.push({ level, taps, health, mana: Math.round(health * (level >= 14 ? 1.2 : 1)) });
  }
  const TAPS = sum(tapRows.map((t) => t.taps)), HEALTH = sum(tapRows.map((t) => t.health));
  const TAPS_MAX = Math.ceil(Math.max(...tapRows.map((t) => t.mana)) / 50000) * 50000;
  const peak = tapRows.reduce((a, b) => (b.mana > a.mana ? b : a));

  const r2 = rng(5), demonRows = [];
  for (let level = 1; level <= 52; level++) {
    const w = [level < 10 ? 1 : 0.5 - Math.min(0.32, (level - 10) * 0.02), level < 10 ? 0 : level < 20 ? 1.1 : 0.95 - Math.min(0.4, (level - 20) * 0.014),
      level < 20 ? 0 : 0.22 + (level > 34 ? 0.2 : 0), level < 30 ? 0 : 0.16 + (level > 44 ? 0.16 : 0), 0.35 + (level < 4 ? 0.5 : 0)];
    const j = w.map((v) => v * (0.75 + r2() * 0.5)), t = sum(j);
    demonRows.push(j.map((v) => v / t));
  }
  const HOURS = 212;
  const demonHours = DEMONS.map((_, i) => (sum(demonRows.map((row) => row[i])) / demonRows.length) * HOURS);
  const WITH = HOURS - demonHours[4];
  const outMost = DEMONS.slice(0, 4).sort((a, b) => demonHours[DEMONS.indexOf(b)] - demonHours[DEMONS.indexOf(a)])[0];

  const CURSES = [["agony", 6120], ["elements", 1380], ["recklessness", 890], ["tongues", 610], ["weakness", 480], ["exhaustion", 300], ["doom", 90]];
  const DAMAGE = [["corruption", 24], ["shadowbolt", 22], ["agony", 19], ["siphon", 11], ["drainlife", 9], ["shadowburn", 8], [null, 7]];
  const KILLS = [["Your killing blow", 58], ["A curse, a bane or the demon, after you looked away", 31], ["A party member", 11]];
  const CONTROL = [["fear", 2210], ["deathcoil", 301], ["howl", 96], ["banish", 44], ["enslave", 38]];
  const FEATS = [
    [3, "Soul Harvester", "Gold", "5, 640 of 6, 000 casts of Drain Soul", [100, 100, 100, 91, 0]],
    [3, "Leech", "Gold", "2, 940 of 6, 000 casts of Drain Life and Siphon Life", [100, 100, 100, 24, 0]],
    [3, "Summoner", "Gold", "186 of 400 casts of Ritual of Summoning", [100, 100, 100, 22, 0]],
    [3, "Life Tapper", "Gold", "11, 065 of 25, 000 casts of Life Tap", [100, 100, 100, 20, 0]],
    [3, "Fearmonger", "Gold", "2, 607 of 6, 000 casts of Fear, Howl of Terror and Death Coil", [100, 100, 100, 15, 0]],
    [3, "Hexweaver", "Gold", "188 of 400 hours with Affliction as the main tree", [100, 100, 100, 15, 0]],
    [3, "Corruptor", "Gold", "9, 480 of 25, 000 casts of Corruption", [100, 100, 100, 11, 0]],
    [2, "Agonizer", "Silver", "6, 120 of 7, 500 casts of Bane of Agony", [100, 100, 77, 0, 0]],
  ];
  const SOURCES = [["drainsoul", NAMES.drainsoul, 4314], ["shadowburn", NAMES.shadowburn, 498]];
  const SPENDS = [
    ["voidwalker", "Summoning a demon", 1480, "class"], ["shadowburn", NAMES.shadowburn, 1190, "class"], ["shard", "Destroyed to make room", 870, "lost"], ["soulfire", NAMES.soulfire, 402, "class"],
    ["createhealthstone", "Healthstones", 312, "class"], ["createsoulstone", "Soulstones", 268, "class"], ["ritual", NAMES.ritual, 186, "class"], [null, "Other spells", 82, "class"], ["shard", "In the bags now", 22, "held"],
  ];
  const SHARDS = sum(SOURCES.map((s) => s[2]));
  if (SHARDS !== sum(SPENDS.map((s) => s[2]))) throw new Error("shard flow does not balance");
  const AGAINST = [
    ["Soul shards a played hour", (SHARDS / HOURS).toFixed(1), 71], ["Life Taps an hour in combat", "142", 88], ["Time with a demon out", pct(WITH, HOURS), 34],
    ["Time spent drinking", "1.1%", 79], ["Healthstones handed out", "41", 62], ["Shards destroyed", "18%", 22],
  ];

  const flowChart = ({ sources, spends, unit, label }) => {
    const total = sum(sources.map((s) => s[2]));
    const W = 900, k = 300 / total, xL = 204, xT = 445, xR = 686, nw = 10, gapL = 18, gapR = 5, slotMin = 26, is = 20;
    const lh = sources.map((s) => s[2] * k), rh = spends.map((s) => s[2] * k), slots = rh.map((h) => Math.max(h, slotMin));
    const leftH = sum(lh) + gapL * (lh.length - 1), rightH = sum(slots) + gapR * (slots.length - 1), body = Math.max(300, leftH, rightH), top = 40, H = top + body + 12;
    const f = (v) => v.toFixed(1);
    const ribbon = (x0, a0, b0, x1, a1, b1, cls) => { const m = (x0 + x1) / 2; return `<path class="fl ${cls}" d="M${x0} ${f(a0)}C${m} ${f(a0)} ${m} ${f(a1)} ${x1} ${f(a1)}L${x1} ${f(b1)}C${m} ${f(b1)} ${m} ${f(b0)} ${x0} ${f(b0)}Z"/>`; };
    const pic = (key, x, y, cls = "") => (key ? `<rect class="fi${cls ? ` ${cls}` : ""}" x="${f(x)}" y="${f(y - is / 2)}" width="${is}" height="${is}" rx="3"/>` : "");
    let out = "", t0 = top + (body - 300) / 2, t = t0, y = top + (body - leftH) / 2;
    sources.forEach(([key, name, v], i) => {
      const mid = y + lh[i] / 2;
      out += ribbon(xL + nw, y, y + lh[i], xT, t, t + lh[i], "class") + `<rect class="fn class" x="${xL}" y="${f(y)}" width="${nw}" height="${f(lh[i])}" rx="1.5"/>` + pic(key, xL - 12 - is, mid);
      out += `<text class="fl-l" x="${xL - 20 - is}" y="${f(mid - 2)}" text-anchor="end">${name}</text><text class="fl-v" x="${xL - 20 - is}" y="${f(mid + 14)}" text-anchor="end">${n(v)} · ${pct(v, total)}</text>`;
      y += lh[i] + gapL; t += lh[i];
    });
    t = t0; y = top + (body - rightH) / 2;
    spends.forEach(([key, name, v, cls], i) => {
      const a = y + (slots[i] - rh[i]) / 2, h = Math.max(rh[i], 1.6), mid = y + slots[i] / 2;
      out += ribbon(xT + nw, t, t + rh[i], xR, a, a + h, cls) + `<rect class="fn ${cls}" x="${xR}" y="${f(a)}" width="${nw}" height="${f(h)}" rx="1.5"/>` + pic(key, xR + nw + 10, mid, cls === "lost" ? "dim" : "");
      out += `<text x="${xR + nw + 18 + is}" y="${f(mid + 4.5)}"><tspan class="fl-l">${name}</tspan><tspan class="fl-v" dx="9">${n(v)}</tspan></text>`;
      y += slots[i] + gapR; t += rh[i];
    });
    out += `<rect class="fn held" x="${xT}" y="${t0}" width="${nw}" height="300" rx="1.5"/><text class="fl-n" x="${xT + nw / 2}" y="${t0 - 13}" text-anchor="middle">${n(total)} ${unit}</text>`;
    return `<svg viewBox="0 0 ${W} ${H}" role="img" aria-label="${label}">${out}</svg>`;
  };

  const mirrorChart = ({ rows, series, max, ticks, peakLabel, label }) => {
    const W = 760, H = 290, L = 44, R = 6, T = 10, B = 24, half = (H - T - B) / 2, mid = T + half;
    const lo = rows[0].x, span = rows[rows.length - 1].x - lo + 1;
    const x = (v) => L + ((v - (lo - 0.5)) / span) * (W - L - R), bw = ((W - L - R) / span) * 0.72;
    let out = "";
    for (const tick of [max, max / 2, 0, -max / 2, -max]) {
      const y = mid - (tick / max) * half;
      out += `<line class="${tick ? "grid" : "axis"}" x1="${L}" x2="${W - R}" y1="${y}" y2="${y}"/><text x="${L - 8}" y="${y + 3.5}" text-anchor="end">${tick ? short(Math.abs(tick)) : 0}</text>`;
    }
    for (const t of ticks) out += `<text x="${x(t)}" y="${H - 6}" text-anchor="middle">${t}</text>`;
    const hi = rows.reduce((a, b) => (b.up > a.up ? b : a));
    for (const t of rows) {
      const up = (t.up / max) * half, down = (t.down / max) * half, on = t === hi ? " on" : "";
      out += `<rect class="bar ${series[0]}${on}" x="${(x(t.x) - bw / 2).toFixed(1)}" y="${(mid - up).toFixed(1)}" width="${bw.toFixed(1)}" height="${up.toFixed(1)}" rx="1.5"/><rect class="bar ${series[1]}${on}" x="${(x(t.x) - bw / 2).toFixed(1)}" y="${mid + 1}" width="${bw.toFixed(1)}" height="${down.toFixed(1)}" rx="1.5"/>`;
    }
    out += `<text class="lab" x="${x(hi.x)}" y="${(mid - (hi.up / max) * half - 6).toFixed(1)}" text-anchor="middle">${peakLabel(hi.x)}</text>`;
    return `<svg viewBox="0 0 ${W} ${H}" role="img" aria-label="${label}">${out}</svg>`;
  };

  const bag = (held, slots, noun) => `<div class="bagslots" role="img" aria-label="${held} of ${slots} bag slots hold a ${noun}">${Array.from({ length: slots }, (_, i) => `<i class="slot${i < held ? " held" : ""}"></i>`).join("")}</div>`;
  const rank = (rows, total, o = {}) => `<ol class="rank${o.plain ? " plain" : ""}">${rows.map(([who, v, cls]) => `<li><span class="who">${who}</span><span class="val">${o.unit ? o.unit(v) : n(v)}</span><span class="track"><i${cls || o.cls ? ` class="${cls || o.cls}"` : ""} style="width:${Math.max(1, (v / total) * 100).toFixed(1)}%"></i></span></li>`).join("")}</ol>`;
  const pair = (rows) => `<dl class="pair">${rows.map(([l, v]) => `<div><dt>${l}</dt><dd>${v}</dd></div>`).join("")}</dl>`;
  const tal = (text) => `<span class="tal">${text}</span>`;
  const split = (used) => `<div class="split" aria-hidden="true"><i class="used" style="flex:${used}"></i><i class="lost" style="flex:${100 - used}"></i></div>`;
  const splitKey = (a, b) => `<p class="split-key"><span><i class="used"></i>${a}</span><span><i class="lost"></i>${b}</span></p>`;
  const stackBars = ({ rows, series, label }) => `<div class="xp-plot"><div class="xp-y" aria-hidden="true"><span>100%</span><span>50%</span><span>0</span></div>
  <div class="xp-bars" role="img" aria-label="${label}">${rows.map((row) => `<div class="xp-bar">${row.map((v, i) => `<i class="${series[i].cls}" style="height:${(v * 100).toFixed(1)}%"></i>`).join("")}</div>`).join("")}</div>
  <div class="xp-x" aria-hidden="true">${rows.map((_, i) => `<span>${(i + 1) % 10 === 0 || i === 0 ? i + 1 : ""}</span>`).join("")}</div></div>
  <ul class="key">${series.map((s) => `<li><i class="${s.cls}"></i>${s.label}</li>`).join("")}</ul>`;
  const callout = (i, side) => `<p class="callout${side === "left" ? " left" : ""}" data-key="${DEMONS[i][0]}" hidden><b>${DEMONS[i][2]}</b><span>${DEMONS[i][1]}, out ${n(demonHours[i])} hours</span><em>${pct(demonHours[i], WITH)} of demon time</em></p>`;

  const FLOW = { sources: SOURCES, spends: SPENDS, unit: "shards", label: "Soul shards by the spell that made them, on the left, and by what they were spent on, on the right" };
  const MIRROR = { rows: tapRows.map((t) => ({ x: t.level, up: t.mana, down: t.health })), series: ["mana", "health"], max: TAPS_MAX, ticks: [10, 20, 30, 40, 50], peakLabel: (x) => `level ${x}`, label: "Mana gained from Life Tap, above the line, and the health it cost, below, at each level from 6 to 52" };
  const STACK = { rows: demonRows, series: DEMONS.map(([, name, , cls]) => ({ label: name, cls })), label: "Share of played time each demon was out at each level from 1 to 52" };

  const body = `
<section class="ch-head scene-head cls-head" aria-labelledby="h1">
  <div class="stage" data-state="off" role="img" aria-label="The Voidwalker, the Imp, the Succubus and the Felhunter standing on a warlock's ritual circle, drawn from the game's own models">
    <div class="callouts" aria-hidden="true">${callout(1)}${callout(2)}${callout(0, "left")}${callout(3)}</div>
  </div>
  <div class="ch-id wrap">
    <div><h1 class="ch-name" id="h1">Warlock</h1>
      <p class="ch-sub">${gi()}<span><a href="character.html">Name-Surname</a></span><span>Level 52</span><span><a href="#">Affliction 30 / 0 / 12</a></span><span>${HOURS} hours played</span></p></div>
  </div>
</section>
<div class="wrap">
  <nav class="trades" aria-label="Pages of Name-Surname"><a href="character.html">Character</a><a href="class.html" aria-current="page">Warlock</a><a href="#">Battlegrounds</a></nav>
  <p class="credit">Imp, Voidwalker, Succubus and Felhunter models, the ritual circle, their textures and every spell and item icon © Blizzard Entertainment, read from the WoW Forever client. The ground and the light are drawn by this page.</p>
  <dl class="tally" style="--n:6" aria-label="Totals">
    <div><dt>${gi()}Soul shards made</dt><dd>${n(SHARDS)}</dd></div>
    <div><dt>${gi()}Life Taps</dt><dd>${n(TAPS)}</dd></div>
    <div><dt>${gi()}Hours with a demon</dt><dd>${n(WITH)}<small>of ${HOURS}</small></dd></div>
    <div><dt>${gi()}Healthstones made</dt><dd>312</dd></div>
    <div><dt>${gi()}Soulstones made</dt><dd>268</dd></div>
    <div><dt>${gi()}Players summoned</dt><dd>186</dd></div>
  </dl>

  <div class="sheet">
    <section class="mod w8 first" aria-labelledby="h-flow">
      <h2 id="h-flow">Where soul shards came from and what they bought</h2>
      <div class="plot flow">${flowChart(FLOW)}</div>
      <p class="note">870 shards were destroyed to make room in the bags, more than went into Healthstones and Soulstones together.</p>
    </section>
    <section class="mod last" aria-labelledby="h-bag">
      <h2 id="h-bag">Shards in the bags</h2>
      ${bag(22, 32, "soul shard")}
      ${pair([["Most held at once", "32"], ["Held at a typical pull", "9"], ["Pulls with none", "4%"]])}
    </section>

    <section class="mod w7 first" aria-labelledby="h-tap">
      <h2 id="h-tap">Health traded for mana, by level</h2>
      <div class="plot taps">${mirrorChart(MIRROR)}</div>
      <ul class="key"><li><i class="k-mana"></i>Mana gained</li><li><i class="c-kill"></i>Health spent</li></ul>
      <p class="note">Level ${peak.level} had the most: ${n(peak.taps)} taps, ${short(peak.health)} health for ${short(peak.mana)} mana.</p>
    </section>
    <section class="mod w5 last" aria-labelledby="h-taps">
      <h2 id="h-taps">${gi()}Life Tap</h2>
      ${pair([["Health traded", short(HEALTH)], ["Taps in combat", "71%"], ["Most taps in a row", "7"]])}
      <h3 class="chart-name">${gi()}Time spent drinking, of played time</h3>
      ${rank([["Name-Surname", 1.1, "cl"], ["Warlocks", 1.6], ["Every class", 5.9]], 5.9, { plain: true, unit: (v) => v + "%" })}
      <p class="note">6 deaths came within 5 seconds of a Life Tap.</p>
    </section>

    <section class="mod w8 first" aria-labelledby="h-out">
      <h2 id="h-out">The demon that was out, by level</h2>
      ${stackBars(STACK)}
      <p class="note">The Voidwalker was out for most of every level from 10 to 24.</p>
    </section>
    <section class="mod last" aria-labelledby="h-demons">
      <h2 id="h-demons">Demons</h2>
      ${rank(DEMONS.map(([, name, pet, cls], i) => [gi() + (pet ? `${name} <small class="pet">${pet}</small>` : name), demonHours[i], cls]), Math.max(...demonHours), { unit: (v) => n(v) + " h" })}
      ${pair([["Summons", "1,480"], [NAMES.funnel, "3.2 h"], ["Sacrificed", "0"]])}
    </section>

    <section class="mod first" aria-labelledby="h-curse">
      <h2 id="h-curse">Curses and banes cast</h2>
      ${rank(CURSES.map(([key, v]) => [sp(key), v]), CURSES[0][1], { cls: "cl" })}
    </section>
    <section class="mod" aria-labelledby="h-dmg">
      <h2 id="h-dmg">Damage by spell</h2>
      ${rank(DAMAGE.map(([key, v]) => [key ? sp(key) : gi() + "Every other spell", v]), DAMAGE[0][1], { unit: (v) => v + "%" })}
    </section>
    <section class="mod last" aria-labelledby="h-kill">
      <h2 id="h-kill">What finished the kill</h2>
      ${rank(KILLS, KILLS[0][1], { plain: true, unit: (v) => v + "%" })}
      <p class="note">8,912 creatures died to a curse, a bane or the demon with no killing blow of yours.</p>
    </section>

    <h2 class="sect" id="talents">From the talents</h2>
    <section class="mod first" aria-labelledby="h-night">
      <h2 id="h-night">${gi()}${NAMES.nightfall} ${tal("2 of 2")}</h2>
      ${pair([["Shadow Trances", "1,204"], ["Used", "81%"], ["Seconds to use one", "2.1"]])}
      ${split(81)}
      ${splitKey("Spent on an instant Shadow Bolt", "Ran out")}
    </section>
    <section class="mod" aria-labelledby="h-burn">
      <h2 id="h-burn">${gi()}${NAMES.shadowburn} ${tal("1 of 1")}</h2>
      ${pair([["Casts", "1,190"], ["Paid the shard back", "42%"], ["Shards lost to it", "692"]])}
      ${split(42)}
      ${splitKey("The target died in 5 seconds", "It lived")}
    </section>
    <section class="mod last" aria-labelledby="h-siphon">
      <h2 id="h-siphon">${gi()}${NAMES.siphon} ${tal("1 of 1")}</h2>
      ${pair([["Casts", "3,412"], ["Of your healing", "38%"], ["Fights with it", "64%"]])}
      ${split(64)}
      ${splitKey("Fights it was cast in", "Fights without")}
    </section>

    <h2 class="sect" id="party">For the party</h2>
    <section class="mod first" aria-labelledby="h-stones">
      <h2 id="h-stones">Stones</h2>
      ${rank([[gi() + "Healthstones made", 312, "cl"], [gi() + "Eaten", 204], [gi() + "Handed to another player", 41], [gi() + "Soulstones made", 268, "cl"], [gi() + "Deaths undone by your own", 9]], 312)}
    </section>
    <section class="mod" aria-labelledby="h-summon">
      <h2 id="h-summon">${gi()}Summons</h2>
      ${pair([["Players summoned", "186"], ["At a dungeon door", "71%"], ["Most in a day", "14"]])}
      ${rank([["Greywatch Ford", 48], ["Saltmarsh Reach", 31], ["Pale Rest", 22], ["Hollowmere", 17]], 48, { plain: true })}
    </section>
    <section class="mod last" aria-labelledby="h-fear">
      <h2 id="h-fear">Fear and control</h2>
      ${rank(CONTROL.map(([key, v]) => [sp(key), v]), CONTROL[0][1], { cls: "cl" })}
      <p class="note">3% of Fear casts were cut short.</p>
    </section>

    <section class="mod w12 first last" aria-labelledby="h-vs">
      <h2 id="h-vs">Against other warlocks</h2>
      <p class="sub">Public warlocks of every ruleset, level 50 to 59. 1,204 characters.</p>
      <ol class="rules">${AGAINST.map(([label, value, at]) => `<li><span class="rule-l">${gi()}${label}</span><b class="rule-v">${value}</b><span class="rule" style="--at:${at}%"><i></i></span><span class="rule-t"><b>More than ${at}% of warlocks</b><span></span></span></li>`).join("")}</ol>
    </section>
  </div>

  <section class="recs" aria-labelledby="h-feats">
    <h2 id="h-feats">Warlock feats</h2>
    <p class="feats-sum"><span><b>8</b>of 8 feats</span><span><b>7</b>Gold</span><span><b>1</b>Silver</span><span><b>0</b>Copper</span></p>
    <ul class="feats feats-top">${FEATS.map(([tier, name, tierName, detail, track]) => `<li class="feat t${tier}"><span class="seal">${gi()}</span><div><p class="feat-n">${name}${tierName ? `<small>${tierName}</small>` : ""}</p><p class="feat-d">${detail}</p><span class="feat-to" aria-hidden="true">${track.map((s) => `<i style="--at:${s}%"></i>`).join("")}</span></div></li>`).join("")}</ul>
  </section>
</div>`;

  const band = `
  <section class="week cls-band" aria-labelledby="h-band">
    <div><h2 id="h-band">${gi()}Warlock</h2><p><a href="class.html">All warlock numbers</a></p></div>
    <dl><dt>${gi()}Soul shards made</dt><dd>${n(SHARDS)}</dd></dl>
    <dl><dt>${gi()}Life Taps</dt><dd>${n(TAPS)}</dd></dl>
    <dl><dt>${gi()}Demon out most</dt><dd>${outMost[1]}</dd></dl>
    <dl><dt>${gi()}Healthstones made</dt><dd>312</dd></dl>
    <dl><dt>${gi()}Players summoned</dt><dd>186</dd></dl>
  </section>`;
  return { body, band };
})();

page("class.html", "Name-Surname the warlock: shards, Life Taps and demons", "search", warlock.body, "", false, { sheets: ["../../resources/css/site.css", "../../resources/css/app.css", "../../resources/css/game.css", "assets/class.css"], bodyClass: "art", main: 'data-class="WARLOCK"' });

// ------------------------------------------------------------------------------ character
page("character.html", "Maren-Ashlow: character", "search", `
<section class="ch-head" aria-labelledby="h1">
  <div class="stage"><canvas id="map" role="img" aria-label="Every path the character walked in Saltmarsh Reach, with deaths as crosses"></canvas></div>
  <div class="ch-id wrap" style="padding-top:3rem">
    <div><h1 class="ch-name" id="h1">Maren-Ashlow</h1>
      <p class="ch-sub"><i class="dot" aria-hidden="true"></i><span>Level 38 Orc Warlock</span><span>Horde</span><span>Standard, PvE</span><span>4d 0h played</span></p>
      <div class="lvl"><div class="lvl-bar" role="img" aria-label="Progress to the next level"><i style="width:38%"></i></div><p>38% to level 39 · 51,274 XP to go · level 40 in about 6h 10m played</p></div></div>
    <ul class="legend ch-map" aria-label="Map marks"><li><svg viewBox="0 0 24 14" aria-hidden="true"><path d="M1 7h22" stroke="#ff2d55" stroke-width="2"/></svg>every path walked in Saltmarsh Reach</li><li><svg viewBox="0 0 24 14" aria-hidden="true"><path d="M7.5 2.5l9 9M16.5 2.5l-9 9" stroke="#f0f1f3" stroke-width="1.7" stroke-linecap="round"/></svg>death</li></ul>
  </div>
</section>
<div class="wrap">
  <div class="own" aria-label="Only you see this bar">
    <span class="state on">Public page</span><span>Economy hidden from visitors</span><span>Last upload 12 minutes ago, 214 sessions</span>
    <span class="sp"><a href="account.html">Sharing and privacy</a><label class="sw"><input type="checkbox" checked aria-label="Public page"><i></i></label></span>
  </div>${warlock.band}
  <section class="week" aria-labelledby="h-week">
    <div><h2 id="h-week">This week</h2><p>Mon 16 to Sun 22 Nov</p></div>
    <div><dt>Played</dt><dd>8h 48m</dd></div><div><dt>Dungeons</dt><dd>1</dd></div><div><dt>Quests</dt><dd>26</dd></div><div><dt>Gold</dt><dd>+9g 52s</dd></div><div><dt>Zones</dt><dd>4</dd></div>
  </section>
  <div class="sheet">
    <section class="mod w8 first" aria-labelledby="h-pace">
      <div class="mod-head"><h2 id="h-pace">Levelling against other Standard, PvE characters</h2></div>
      <div class="plot"><svg id="pace" role="img" aria-label="This character's played hours to each level against the median and middle half of its ruleset"></svg></div>
      <ul class="key"><li><i class="k-line" style="background:var(--fg)"></i>Maren-Ashlow</li><li><i class="k-line" style="background:var(--accent)"></i>Median</li><li><i style="background:rgba(255,45,85,.3)"></i>Middle half</li></ul>
      <p class="readout">Reached <b>level 38</b> in 96h played, 9h faster than the median. Faster than 68% of characters.</p>
    </section>
    <section class="mod last" aria-labelledby="h-gold"><h2 id="h-gold">Gold <span class="priv">Only you</span></h2>
      <p class="big"><span class="money">64<i class="g">g</i>12<i class="s">s</i></span></p><p class="sub">+9g 52s over the last 7 sessions</p>
      <div class="plot"><svg id="gold" role="img" aria-label="Balance at the end of each of the last 30 sessions"></svg></div></section>
    <section class="mod w12 first last"><p class="note" style="margin:0">Below this the page is the character overview as it is today: experience by level, dungeons and raids, played with, deaths, play time by day, distance, play history. "Played with" shows names to the owner only when Social is shared; visitors see a name only when that player's own character is public.</p></section>
  </div>
</div>`, `
const mk = (k, n, j) => { const a = [], r = Comp.rng(4); let t = 0; for (let l = 1; l <= n; l++) { t += (0.12 + Math.pow(l / 60, 2.1) * 5.2) * k * (j ? 0.8 + r() * 0.4 : 1); a.push([l, t]); } return a; };
const med = mk(1, 60);
Comp.lines("pace", { x0: 1, x1: 60, y1: 200, yt: [0, 50, 100, 150, 200], yf: (v) => v + "h", xt: [1, 10, 20, 30, 40, 50, 60], series: [{ cls: "a thin", pts: med, band: med.map(([l, t]) => [l, t * 0.78, t * 1.29]) }, { cls: "b", pts: mk(0.9, 38, true), mark: 37 }] });
const r = Comp.rng(9); let m = 20; Comp.lines("gold", { w: 380, h: 130, x0: 0, x1: 29, y1: 80, yt: [0, 40, 80], yf: (v) => v + "g", xt: [], series: [{ cls: "g", pts: Array.from({ length: 30 }, (_, i) => [i, (m += r() * 4 - 0.6)]) }] });
(function () { const cv = document.getElementById("map"), M = ScarletMap, b = cv.getBoundingClientRect(); const view = M.fit({ x0: 1500, y0: 500, x1: 9800, y1: 6100 }, { x: 0, y: 0, w: b.width, h: b.height }); M.terrain(cv, view, b.width, b.height, []); })();
`, true, { sheets: ["assets/site.css", "assets/app.css", "assets/class.css"], main: 'style="--class: #a79bf5"' });

// -------------------------------------------------------------------------------- account
const GROUPS = [["Character and sessions", "Sessions, levels, the zones you enter, your ruleset. Needed by every chart, so it is always sent while uploading is on.", 1, 1], ["Experience and levelling", "Experience gains and where they came from.", 1], ["Quests", "Accepts, turn-ins, objectives and rewards.", 1], ["Combat, kills and deaths", "Kills, deaths and what killed you, fights, bosses, casts.", 1], ["PvP and duels", "Honor, player kills, battlegrounds, duels.", 1], ["Movement and map paths", "Where you walked, flew and teleported.", 1], ["Creatures and places seen", "Creatures and objects you met and where you stood.", 1], ["Loot, items and drop rates", "What you looted and from what, loot rules and rolls.", 1], ["Gold, vendors, auctions, mail and trade", "Money, purchases, auctions, mail and trades.", 0], ["Professions and crafting", "Skills, crafts and recipes.", 1], ["Character build and gear", "Stats, gear, talents, spells and pets.", 1], ["Social and the players you meet", "Groups, guild, friends and players seen. Other players' names are sent only with this on.", 0], ["Collections, achievements, Legacy and reputation", "Legacy Challenges and points, mounts, pets, reputation.", 1], ["Game client and addons loaded", "Client checks, the addon list, UI errors, screenshots taken.", 1], ["Other addons", "What Scarlet records from other addons you run.", 1]];
page("account.html", "Your Scarlet account", "", `
<div class="wrap">
  <section class="pg-head" aria-labelledby="h1"><div><h1 id="h1">Your account</h1><p class="sub">Signed in with Google. Scarlet stores the sign-in's id and nothing else about you: no email, no name.</p></div>
    <div class="filters"><a class="btn quiet" href="#">Sign out</a></div></section>
  <div class="sheet">
    <section class="mod w7 first" aria-labelledby="h-c"><h2 id="h-c">Characters</h2>
      <div class="rows">
        <div class="row"><b><a href="character.html">Maren-Ashlow</a></b><p>Level 38 Orc Warrior · 214 sessions · last upload 12 minutes ago</p><div class="act"><span class="state on">Public</span><label class="sw"><input type="checkbox" checked aria-label="Public page for Maren-Ashlow"><i></i></label></div></div>
        <div class="row"><b>Show gold and trades on the public page</b><p>Off: visitors see no balance, vendors, auctions, mail or trades for Maren-Ashlow.</p><div class="act"><label class="sw"><input type="checkbox" aria-label="Show economy for Maren-Ashlow"><i></i></label></div></div>
        <div class="row"><b><a href="character.html">Tolvir-Greane</a></b><p>Level 12 Troll Mage · 31 sessions · last upload 3 days ago</p><div class="act"><span class="state">Private</span><label class="sw"><input type="checkbox" aria-label="Public page for Tolvir-Greane"><i></i></label></div></div>
      </div>
    </section>
    <section class="mod w5 last" aria-labelledby="h-i"><h2 id="h-i">Installs</h2>
      <div class="rows">
        <div class="row"><b>Windows PC, linked 20 Nov 2026</b><p>Last upload 12 minutes ago · 2 characters</p><div class="act"><button class="btn danger" type="button">Revoke</button></div></div>
        <div class="row"><b>Windows PC, linked 2 Nov 2026</b><p>Last upload 18 days ago · 1 character</p><div class="act"><button class="btn danger" type="button">Revoke</button></div></div>
      </div>
      <p class="note">A revoked install can no longer upload. What it uploaded stays until you remove it.</p>
      <h2 style="margin-top:2rem;font-size:1.9rem">Sign-in methods</h2>
      <div class="rows"><div class="row"><b>Google</b><p>Linked 2 Nov 2026</p><div class="act"><span class="state on">Linked</span></div></div><div class="row"><b>Battle.net</b><p>Not linked. Your BattleTag is never stored.</p><div class="act"><a class="btn quiet" href="#">Link</a></div></div></div>
    </section>
    <section class="mod w12 first last" aria-labelledby="h-s"><div class="mod-head"><h2 id="h-s">What you share</h2><p class="state">Switches change what the app sends next. Recording and the app on your PC are never affected.</p></div>
      <div class="rows">${GROUPS.map(([n, d, on, lock]) => `<div class="row"><b>${n}</b><p>${d}</p><div class="act">${on ? "" : '<button class="btn danger" type="button">Remove from server</button>'}<label class="sw"><input type="checkbox"${on ? " checked" : ""}${lock ? " disabled" : ""} aria-label="Share ${n}"><i></i></label></div></div>`).join("")}</div>
    </section>
    <section class="mod w7 first" aria-labelledby="h-k"><h2 id="h-k">API keys</h2>
      <div class="rows"><div class="row"><b>guild dashboard</b><p>Made 19 Nov 2026 · last used today · 600 requests a minute</p><div class="act"><button class="btn danger" type="button">Delete</button></div></div></div>
      <p style="margin-top:1rem"><a class="btn quiet" href="#">Make a key</a></p>
      <p class="note">The <a href="developers.html">public API</a> needs no key at 60 requests a minute.</p></section>
    <section class="mod w5 last" aria-labelledby="h-x"><h2 id="h-x">Delete everything you uploaded</h2>
      <p class="sub">Removes every character, session and number that came from your installs, and takes them out of the totals. Your recordings on your PC are not touched.</p>
      <p style="margin-top:1.1rem"><button class="btn danger" type="button">Delete everything I uploaded</button></p></section>
  </div>
</div>`, "", true);

// ------------------------------------------------------------------------- login and link
const sso = `<div class="sso">
  <a href="#"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#f0f1f3" d="M21.6 12.2c0-.7-.1-1.3-.2-1.9H12v3.7h5.4a4.6 4.6 0 0 1-2 3v2.5h3.2c1.9-1.7 3-4.3 3-7.3z"/><path fill="#cdced4" d="M12 22c2.7 0 5-.9 6.6-2.4l-3.2-2.5c-.9.6-2 1-3.4 1-2.6 0-4.8-1.8-5.6-4.1H3.100v2.6A10 10 0 0 0 12 22z"/><path fill="#8a8a93" d="M6.400 14a6 6 0 0 1 0-3.900V7.500H3.100a10 10 0 0 0 0 9z"/><path fill="#f0f1f3" d="M12 5.900c1.500 0 2.800.5 3.800 1.500l2.900-2.800A10 10 0 0 0 3.100 7.500l3.300 2.600C7.200 7.700 9.400 5.900 12 5.900z"/></svg>Continue with Google</a>
  <a href="#"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" fill="none" stroke="#f0f1f3" stroke-width="2"/><path d="M12 3c3 3.500 3 14.500 0 18M12 3c-3 3.500-3 14.500 0 18M3.500 9h17M3.500 15h17" fill="none" stroke="#f0f1f3" stroke-width="1.4"/></svg>Continue with Battle.net</a>
</div>`;
const mapOnly = `(function () { const cv = document.getElementById("map"), M = ScarletMap, b = cv.getBoundingClientRect(); const view = M.fit({ x0: 1500, y0: 500, x1: 9800, y1: 6100 }, { x: 0, y: 0, w: b.width, h: b.height }); M.terrain(cv, view, b.width, b.height, []); })();`;
page("login.html", "Sign in to Scarlet", "", `
<div class="gate">
  <div class="gate-in">
    <h1>Sign in</h1>
    <p>See your own characters in full, choose which are public, and control what you share.</p>
    ${sso}
    <p class="note">Scarlet stores the sign-in's id and nothing else: no email, no name, no BattleTag. Uploading from the app works without an account.</p>
  </div>
  <div class="stage"><canvas id="map" role="img" aria-label="An invented survey map"></canvas></div>
</div>`, mapOnly);
page("link.html", "Link the Scarlet app to your account", "", `
<div class="gate">
  <div class="gate-in">
    <h1>Link this app</h1>
    <p>The Scarlet app on your PC is showing this code. Check that it matches, then link it.</p>
    <p class="code" aria-label="Link code">K7QM-2XVD</p>
    <p style="margin-top:1.6rem;display:flex;gap:.8rem;flex-wrap:wrap"><button class="btn" type="button">Link to my account</button><a class="btn quiet" href="#">Cancel</a></p>
    <p class="note">Linking puts the characters this app uploads under your account. You can revoke the app at any time. The code works once and expires in 10 minutes.</p>
  </div>
  <div class="stage"><canvas id="map" role="img" aria-label="An invented survey map"></canvas></div>
</div>`, mapOnly, true);

// ----------------------------------------------------------------------------- developers
const eps = [
  ["GET", "/api/v1/public/stats", "Totals and the series of the global dashboard.", [["ruleset", "A ruleset id. Leave out for all rulesets"], ["class", "A class file, such as WARRIOR"]]],
  ["GET", "/api/v1/public/rulesets", "Every ruleset with its totals.", []],
  ["GET", "/api/v1/public/rulesets/{id}/levels", "Played time to reach each level: median, quartiles, fastest tenth, characters.", []],
  ["GET", "/api/v1/public/leaderboards/{board}", "A leaderboard of public characters.", [["ruleset", "A ruleset id"], ["cursor", "From next_cursor of the last answer"]]],
  ["GET", "/api/v1/public/search", "Public characters, creatures, items, zones and quests by name.", [["q", "At least 2 characters"], ["kind", "character, creature, item, zone or quest"]]],
  ["GET", "/api/v1/public/characters/{name}", "A character whose owner made it public. 404 otherwise.", []],
  ["GET", "/api/v1/public/creatures/{id}", "Kills, deaths and drop rates by loot rule.", [["ruleset", "A ruleset id"]]],
  ["GET", "/api/v1/public/items/{id}", "Item facts and where it drops.", []],
  ["GET", "/api/v1/public/zones/{id}", "Time, deaths, kills and quests in a zone.", []],
  ["GET", "/api/v1/public/maps/{id}/heat", "Path and death counts per map cell.", [["layer", "paths or deaths"]]],
];
const slug = (p) => p.replace(/[^a-z]+/g, "-").replace(/^-|-$/g, "");
page("developers.html", "Scarlet public API reference", "developers", `
<div class="wrap">
  <section class="pg-head" aria-labelledby="h1"><div><h1 id="h1">Public API</h1><p class="sub">Everything public on this site is readable as JSON. Read-only, no sign-up, version 1.</p></div></section>
  <div class="ref">
    <nav class="ref-nav" aria-label="Endpoints"><div><h3>Basics</h3><a href="#use">Using the API</a><a href="#limits">Limits and keys</a><a href="#pages">Pagination</a></div><div><h3>Endpoints</h3>${eps.map((e) => `<a href="#${slug(e[1])}">${e[1].replace("/api/v1/public", "")}</a>`).join("")}</div></nav>
    <div>
      <section class="ep" id="use"><h2>Using the API</h2><p>The base is <code>https://&lt;host&gt;/api/v1/public</code>. Answers are JSON in UTF-8. Times are ISO 8601 in UTC, durations are seconds, money is copper. Any origin may call it from a browser. Answers can be cached for five minutes and carry an <code>ETag</code>.</p>
        <pre class="json">curl https://&lt;host&gt;/api/v1/public/stats?ruleset=3</pre></section>
      <section class="ep" id="limits"><h2>Limits and keys</h2><p>60 requests a minute per IP address without a key. A free key from your account page raises that to 600. Send it as <code>Authorization: Bearer &lt;key&gt;</code>. Over the limit the answer is <code>429</code> with <code>Retry-After</code>.</p></section>
      <section class="ep" id="pages"><h2>Pagination</h2><p>Lists return <code>data</code> and <code>next_cursor</code>. Pass it back as <code>?cursor=</code>. A null cursor is the end. Numbers a bucket cannot show because too few characters contributed come back as <code>null</code>.</p></section>
      ${eps.map((e) => `<section class="ep" id="${slug(e[1])}"><h2><b>${e[0]}</b>${e[1]}</h2><p>${e[2]}</p>${e[3].length ? `<table class="data"><thead><tr><th>Parameter</th><th>Meaning</th></tr></thead><tbody>${e[3].map((p) => `<tr><td><code>${p[0]}</code></td><td>${p[1]}</td></tr>`).join("")}</tbody></table>` : ""}${e[1].endsWith("/stats") ? `<pre class="json">{
  <b>"characters"</b>: 1284,
  <b>"played_seconds"</b>: 150865200,
  <b>"levels"</b>: [{ <b>"level"</b>: 40, <b>"median_seconds"</b>: 220800, <b>"p25"</b>: 172800, <b>"p75"</b>: 284400, <b>"characters"</b>: 311 }],
  <b>"updated_at"</b>: <i>"2026-11-20T21:08:00Z"</i>
}</pre>` : ""}</section>`).join("")}
    </div>
  </div>
</div>`);
page("fishing.html", "Scarlet fishing: casts, catches and skill", "fishing", `
<section class="ch-head scene-head" aria-labelledby="h1">
  <div class="stage" data-state="off" role="img" aria-label="A fishing spot at night, drawn from the game's own models: a bobber on the water beside a school of fish, with a tackle box, a pole and a rack of fish on the bank">
    <div class="callouts" aria-hidden="true">
      <p class="callout left" data-key="bobber" hidden><b>Bobber</b><span>The wait for a bite is 15 seconds</span><em>82% of casts catch</em></p>
      <p class="callout left" data-key="rack" hidden><b>Raw Spotted Yellowtail</b><span>The most caught fish</span><em>43% of catches</em></p>
      <p class="callout left" data-key="box" hidden><b>Clams, crates and trunks</b><span>Catches that open for more</span><em>1 catch in 13</em></p>
    </div>
  </div>
  <div class="ch-id wrap">
    <div><h1 class="ch-name" id="h1">Fishing</h1>
      <p class="ch-sub"><span>1,204,880 casts</span><span>4,310 characters</span><span>6,120 hours at the water</span></p></div>
    <div class="filters"><div class="seg" role="group" aria-label="Ruleset"><a href="#" aria-current="true">All</a><a href="#">Standard</a><a href="#">Hardcore</a><a href="#">Slow</a></div></div>
  </div>
</section>

<div class="wrap">
  <nav class="trades" aria-label="Professions">
    ${TRADES("Fishing")}
  </nav>
  <p class="credit">Bobber, fish school, tackle box, pole and fish rack models, textures, ripples and splashes © Blizzard Entertainment, read from the WoW Forever client. The water, the bank and the moonlight are drawn by this page.</p>
  <dl class="week n6" aria-label="Totals">
    <div><dt>Casts that catch</dt><dd>82%</dd></div><div><dt>Median wait for a bite</dt><dd>15.4s</dd></div><div><dt>Catches an hour</dt><dd>164</dd></div>
    <div><dt>Characters who fish</dt><dd>38%</dd></div><div><dt>Median skill</dt><dd>142</dd></div><div><dt>Reached 300</dt><dd>11%</dd></div>
  </dl>

  <div class="sheet">
    <section class="mod w7 first camp-mod" aria-labelledby="h-catch">
      <h2 id="h-catch">What comes up</h2>
      <div class="scroll-x"><table class="data catches" style="margin-top:1.1rem">
        <thead><tr><th>Catch</th><th class="n">Of catches</th><th class="n">Median skill</th><th>Most common in</th></tr></thead>
        <tbody>
          <tr><td><a class="buff" href="#"><i class="buff-none" aria-hidden="true"></i>Raw Spotted Yellowtail</a></td><td class="n"><i class="bar" style="width:5.6rem"></i>43%</td><td class="n">268</td><td><a href="#">Saltmarsh Reach</a></td></tr>
          <tr><td><a class="buff" href="#"><i class="buff-none" aria-hidden="true"></i>Raw Winter Squid</a></td><td class="n"><i class="bar" style="width:2rem"></i>15%</td><td class="n">281</td><td><a href="#">Saltmarsh Reach</a></td></tr>
          <tr><td><a class="buff" href="#"><i class="buff-none" aria-hidden="true"></i>Stonescale Eel</a></td><td class="n"><i class="bar" style="width:1.45rem"></i>11%</td><td class="n">290</td><td><a href="#">Pale Rest</a></td></tr>
          <tr><td><a class="buff" href="#"><i class="buff-none" aria-hidden="true"></i>Raw Rockscale Cod</a></td><td class="n"><i class="bar" style="width:1.15rem"></i>9%</td><td class="n">212</td><td><a href="#">Greywatch Ford</a></td></tr>
          <tr><td><a class="buff" href="#"><i class="buff-none" aria-hidden="true"></i>Firefin Snapper</a></td><td class="n"><i class="bar" style="width:1.1rem"></i>8%</td><td class="n">196</td><td><a href="#">Greywatch Ford</a></td></tr>
          <tr><td><a class="buff" href="#"><i class="buff-none" aria-hidden="true"></i>Big-mouth Clam</a></td><td class="n"><i class="bar" style="width:0.8rem"></i>6%</td><td class="n">255</td><td><a href="#">Saltmarsh Reach</a></td></tr>
          <tr><td><a class="buff" href="#"><i class="buff-none" aria-hidden="true"></i>Raw Glossy Mightfish</a></td><td class="n"><i class="bar" style="width:0.5rem"></i>4%</td><td class="n">277</td><td><a href="#">Pale Rest</a></td></tr>
          <tr><td><a class="buff" href="#"><i class="buff-none" aria-hidden="true"></i>Raw Greater Sagefish</a></td><td class="n"><i class="bar" style="width:0.3rem"></i>2%</td><td class="n">171</td><td><a href="#">Hollowmere</a></td></tr>
        </tbody></table></div>
      <p class="note">212 different items came up from casts.</p>
    </section>
    <section class="mod w5 last" aria-labelledby="h-cast">
      <h2 id="h-cast">How a cast ends</h2>
      <ol class="rank">
        <li><span class="who">Something on the line</span><span class="val">82% of casts</span><span class="track"><i style="width:100%"></i></span></li>
        <li><span class="who">No bite, or the fish got away</span><span class="val">12%</span><span class="track"><i style="width:15%"></i></span></li>
        <li><span class="who">In combat</span><span class="val">3%</span><span class="track"><i style="width:3%"></i></span></li>
      </ol>
      <h2 id="h-pole-on" style="margin-top:2rem">What is on the pole</h2>
      <ol class="rank plain">
        <li><span class="who">A lure</span><span class="val">34% of casts</span><span class="track"><i style="width:34%"></i></span></li>
        <li><span class="who">Bright Baubles</span><span class="val">19%</span><span class="track"><i style="width:19%"></i></span></li>
        <li><span class="who">Nightcrawlers</span><span class="val">9%</span><span class="track"><i style="width:9%"></i></span></li>
        <li><span class="who">Shiny Bauble</span><span class="val">6%</span><span class="track"><i style="width:6%"></i></span></li>
      </ol>
    </section>

    <section class="mod first" aria-labelledby="h-wait">
      <h2 id="h-wait">How long a bite takes</h2>
      <div class="plot"><svg id="wait" role="img" aria-label="Catches by the seconds between the cast and the bite"></svg></div>
      <p class="note">Half of all bites come between 10 and 22 seconds.</p>
    </section>
    <section class="mod" aria-labelledby="h-pts">
      <h2 id="h-pts">Catches for a skill point, by skill</h2>
      <div class="plot"><svg id="pts" role="img" aria-label="Median catches between two skill points at each skill, with the middle half of characters"></svg></div>
      <p class="note">The last 25 points take as many catches as the first 200.</p>
    </section>
    <section class="mod last" aria-labelledby="h-lvl">
      <h2 id="h-lvl">Fishing skill, by character level</h2>
      <div class="plot"><svg id="lvl" role="img" aria-label="Median fishing skill at each character level, with the middle half of characters who fish"></svg></div>
    </section>

    <section class="mod w8 first" aria-labelledby="h-when">
      <h2 id="h-when">When players fish</h2>
      <div class="hours" id="when" role="img" aria-label="Casts by weekday and hour of the player's own day"></div>
      <p class="readout"><b>Busiest hour</b>: Tue 23:00 UTC</p>
    </section>
    <section class="mod last" aria-labelledby="h-trip">
      <h2 id="h-trip">A fishing trip</h2>
      <dl class="pair" style="grid-template-columns:minmax(0,1fr)">
        <div><dt>Median length</dt><dd>14m 20s<small>casts no more than two minutes apart, over the last 90 days</small></dd></div>
      </dl>
      <dl class="pair" style="grid-template-columns:repeat(2,minmax(0,1fr));margin-top:0.9rem">
        <div><dt>Median casts</dt><dd>41</dd></div>
        <div><dt>Trips</dt><dd>29,410</dd></div>
      </dl>
    </section>

    <section class="mod w7 first" aria-labelledby="h-where">
      <h2 id="h-where">Where players fish</h2>
      <div class="mapbox" style="aspect-ratio:3/2"><canvas id="where" role="img" aria-label="Heat of casts on the world map"></canvas></div>
      <p class="note"><a href="world.html">Open the world map</a></p>
    </section>
    <section class="mod w5 last" aria-labelledby="h-zones">
      <h2 id="h-zones">Zones with the most catches</h2>
      <ol class="rank">
        <li><a class="who" href="#">Saltmarsh Reach</a><span class="val">212,400 catches</span><span class="track"><i style="width:100%"></i></span></li>
        <li><a class="who" href="#">Pale Rest</a><span class="val">148,010</span><span class="track"><i style="width:70%"></i></span></li>
        <li><a class="who" href="#">Greywatch Ford</a><span class="val">96,318</span><span class="track"><i style="width:45%"></i></span></li>
        <li><a class="who" href="#">Hollowmere</a><span class="val">71,950</span><span class="track"><i style="width:34%"></i></span></li>
        <li><a class="who" href="#">Thornwall</a><span class="val">40,877</span><span class="track"><i style="width:19%"></i></span></li>
        <li><a class="who" href="#">The Cinder Flats</a><span class="val">22,204</span><span class="track"><i style="width:10%"></i></span></li>
      </ol>
    </section>

    <section class="mod w7 first" aria-labelledby="h-open">
      <h2 id="h-open">What the clams, crates and trunks hold</h2>
      <ol class="rank plain">
        <li><span class="who">Big-mouth Clam Meat</span><span class="val">found 18,204 times</span><span class="track"><i style="width:71%"></i></span></li>
        <li><span class="who">Golden Pearl</span><span class="val">1,538</span><span class="track"><i style="width:6%"></i></span></li>
        <li><span class="who">Coin</span><span class="val">1,282</span><span class="track"><i style="width:5%"></i></span></li>
        <li><span class="who">Black Pearl</span><span class="val">513</span><span class="track"><i style="width:2%"></i></span></li>
      </ol>
    </section>
    <section class="mod w5 last" aria-labelledby="h-pole">
      <h2 id="h-pole">Poles in hand</h2>
      <ol class="rank">
        <li><a class="who" href="#">Fishing Pole</a><span class="val">58% of casts</span><span class="track"><i style="width:58%"></i></span></li>
        <li><a class="who" href="#">Strong Fishing Pole</a><span class="val">23%</span><span class="track"><i style="width:23%"></i></span></li>
        <li><a class="who" href="#">Big Iron Fishing Pole</a><span class="val">12%</span><span class="track"><i style="width:12%"></i></span></li>
        <li><a class="who" href="#">Darkwood Fishing Pole</a><span class="val">7%</span><span class="track"><i style="width:7%"></i></span></li>
      </ol>
    </section>

    <section class="mod w12 first last camp-mod" aria-labelledby="h-top">
      <h2 id="h-top">Most catches</h2>
      <div class="scroll-x"><table class="data" style="margin-top:1.1rem">
        <thead><tr><th class="rk">#</th><th>Character</th><th>Class</th><th class="n">Catches</th><th>Ruleset</th></tr></thead>
        <tbody>
          <tr><td class="rk">1</td><td><a href="character.html">Name-Surname</a></td><td>Orc Warrior</td><td class="n">18,402</td><td>Hardcore</td></tr>
          <tr><td class="rk">2</td><td><a href="character.html">Name-Surname</a></td><td>Human Mage</td><td class="n">15,977</td><td>Standard</td></tr>
          <tr><td class="rk">3</td><td><a href="character.html">Name-Surname</a></td><td>Tauren Druid</td><td class="n">12,260</td><td>Slow</td></tr>
          <tr><td class="rk">4</td><td><a href="character.html">Name-Surname</a></td><td>Night Elf Hunter</td><td class="n">9,871</td><td>Standard</td></tr>
          <tr><td class="rk">5</td><td><a href="character.html">Name-Surname</a></td><td>Dwarf Paladin</td><td class="n">8,114</td><td>Hardcore</td></tr>
        </tbody></table></div>
    </section>
  </div>
</div>
</div>`, `
(() => {
  const rand = Comp.rng(33);
  Comp.bars("wait", { w: 400, h: 250, x0: -1, x1: 31, y1: 14, yt: [0, 4, 8, 12], yf: (v) => v + "%", xt: [0, 6, 12, 18, 24, 30], xf: (v) => v + "s",
    data: [[1, 0.4], [3, 0.6], [5, 0.9], [7, 5.1], [9, 10.4], [11, 12.9], [13, 9.6], [15, 10.2], [17, 7.8], [19, 11.1], [21, 10.7], [23, 11.2], [25, 6.4], [27, 2.5], [29, 0.2]] });
  const pts = [], pband = [];
  for (let s = 1; s <= 300; s += 5) {
    const v = 1 + 9.5 * Math.pow(s / 300, 2.6) + (rand() - 0.5) * 0.4;
    pts.push([s, v]); pband.push([s, Math.max(1, v * 0.7), v * 1.45]);
  }
  Comp.lines("pts", { w: 400, h: 250, x0: 1, x1: 300, y1: 16, yt: [0, 4, 8, 12, 16], xt: [1, 75, 150, 225, 300], series: [{ cls: "a", pts, band: pband, mark: 45 }] });
  const lvl = [], lband = [];
  for (let l = 5; l <= 60; l++) {
    const v = Math.min(300, 6 + 260 * (1 - Math.exp(-(l - 5) / 26)) + (rand() - 0.5) * 8);
    lvl.push([l, v]); lband.push([l, v * 0.45, Math.min(300, v * 1.5)]);
  }
  Comp.lines("lvl", { w: 400, h: 250, x0: 1, x1: 60, y1: 300, yt: [0, 75, 150, 225, 300], xt: [1, 10, 20, 30, 40, 50, 60], series: [{ cls: "a", pts: lvl, band: lband, mark: 34 }] });
  Comp.hours("when", (d, h) => {
    const late = Math.exp(-((h - 23) ** 2) / 9) + Math.exp(-((h + 1) ** 2) / 9) + 0.45 * Math.exp(-((h - 15) ** 2) / 16), week = d >= 5 ? 1.1 : 0.95;
    return Math.max(0, Math.min(4, Math.round(late * week * 3.5 + (rand() - 0.5) * 0.9)));
  });
  Comp.heatmap("where", { seed: 14, hot: [[2100, 6900, 520, 300], [2500, 5200, 380, 190], [5200, 7600, 600, 260], [7900, 6800, 420, 210], [6900, 2100, 440, 150], [3700, 1700, 360, 120]] });
})();
`);

console.log("wrote", fs.readdirSync(out).filter((f) => f.endsWith(".html")).join(" "));
page("cooking.html", "Scarlet cooking: crafts, recipes and skill", "fishing", `
<section class="ch-head scene-head" aria-labelledby="h1">
  <div class="stage" data-state="off" role="img" aria-label="A cook's fire, drawn from the game's own models: a cooking pot over a fire, a boar on a spit, a rack of smoked meat, a table of food and a sack">
    <div class="callouts" aria-hidden="true">
      <p class="callout left" data-key="station" hidden><b>Spotted Yellowtail</b><span>The most made recipe</span><em>38% of crafts</em></p>
      <p class="callout left" data-key="work" hidden><b>A sitting</b><span>31 crafts in a row</span><em>2.4 seconds apart</em></p>
      <p class="callout left" data-key="stock" hidden><b>Raw Spotted Yellowtail</b><span>The most used material</span><em>27% of materials</em></p>
    </div>
  </div>
  <div class="ch-id wrap">
    <div><h1 class="ch-name" id="h1">Cooking</h1>
      <p class="ch-sub"><span>812,440 crafts</span><span>3,190 characters</span><span>1,204,300 items made</span></p></div>
    <div class="filters"><div class="seg" role="group" aria-label="Ruleset"><a href="#" aria-current="true">All</a><a href="#">Standard</a><a href="#">Hardcore</a><a href="#">Slow</a></div></div>
  </div>
</section>

<div class="wrap">
  <nav class="trades" aria-label="Professions">${TRADES("Cooking")}</nav>
  <p class="credit">Cooking pot, spit, smoked meat rack, table, food and sack models, textures and effects © Blizzard Entertainment, read from the WoW Forever client. The ground and the light are drawn by this page.</p>
  <dl class="week n6" aria-label="Totals">
    <div><dt>Crafts that give a skill point</dt><dd>61%</dd></div><div><dt>Recipes crafted</dt><dd>74</dd></div><div><dt>Items made</dt><dd>1,204,300</dd></div>
    <div><dt>Characters who craft</dt><dd>29%</dd></div><div><dt>Median skill</dt><dd>212</dd></div><div><dt>Characters at 300</dt><dd>402</dd></div>
  </dl>

  <div class="sheet">
    <section class="mod w12 first last camp-mod" aria-labelledby="h-recipes">
      <h2 id="h-recipes">What gets made</h2>
      <div class="scroll-x"><table class="data catches" style="margin-top:1.1rem">
        <thead><tr><th>Recipe</th><th>Makes</th><th class="n">Crafts</th><th class="n">Made</th><th class="n">Characters</th><th class="n">Median skill</th></tr></thead>
        <tbody>
          <tr><td><a href="#">Spotted Yellowtail</a><small class="uses">Raw Spotted Yellowtail</small></td><td><a href="#">Spotted Yellowtail</a></td><td class="n"><i class="bar" style="width:5.6rem"></i>309,420</td><td class="n">309,420</td><td class="n">1,940</td><td class="n">252</td></tr>
          <tr><td><a href="#">Roast Raptor</a><small class="uses">Raptor Flesh, Mild Spices</small></td><td><a href="#">Roast Raptor</a></td><td class="n"><i class="bar" style="width:3.3rem"></i>182,100</td><td class="n">182,100</td><td class="n">1,610</td><td class="n">184</td></tr>
          <tr><td><a href="#">Herb Baked Egg</a><small class="uses">Small Egg</small></td><td><a href="#">Herb Baked Egg</a></td><td class="n"><i class="bar" style="width:2.2rem"></i>121,360</td><td class="n">121,360</td><td class="n">2,205</td><td class="n">24</td></tr>
          <tr><td><a href="#">Boiled Clams</a><small class="uses">Clam Meat, Refreshing Spring Water ×2</small></td><td><a href="#">Boiled Clams</a></td><td class="n"><i class="bar" style="width:1.4rem"></i>76,880</td><td class="n">76,880</td><td class="n">980</td><td class="n">78</td></tr>
          <tr><td><a href="#">Spiced Wolf Meat</a><small class="uses">Wolf Meat, Mild Spices</small></td><td><a href="#">Spiced Wolf Meat</a></td><td class="n"><i class="bar" style="width:0.9rem"></i>49,610</td><td class="n">49,610</td><td class="n">1,330</td><td class="n">97</td></tr>
        </tbody></table></div>
      <p class="note">The 5 recipes with the most crafts, of 74 crafted.</p>
    </section>

    <section class="mod w7 first" aria-labelledby="h-diff">
      <h2 id="h-diff">How hard the recipe was when it was made</h2>
      <div class="scroll-x"><table class="data" style="margin-top:1.1rem">
        <thead><tr><th>The recipe was</th><th class="n">Crafts</th><th class="n">Gave a skill point</th></tr></thead>
        <tbody>
          <tr><td>Optimal</td><td class="n">402,118</td><td class="n">96%</td></tr>
          <tr><td>Medium</td><td class="n">211,903</td><td class="n">71%</td></tr>
          <tr><td>Easy</td><td class="n">118,742</td><td class="n">22%</td></tr>
          <tr><td>Trivial</td><td class="n">79,677</td><td class="n">0%</td></tr>
        </tbody></table></div>
    </section>
    <section class="mod w5 last" aria-labelledby="h-pts">
      <h2 id="h-pts">Crafts for a skill point, by skill</h2>
      <div class="plot"><svg id="pts" role="img" aria-label="Crafts between two skill points at each skill"></svg></div>
      <p class="note">Slowest: skill 275 to 299, at 9.4 crafts for a skill point.</p>
    </section>

    <section class="mod w8 first" aria-labelledby="h-when">
      <h2 id="h-when">When players craft</h2>
      <div class="hours" id="when" role="img" aria-label="Crafts by weekday and hour of the player's own day"></div>
      <p class="readout"><b>Busiest hour</b>: Sat 20:00 UTC</p>
    </section>
    <section class="mod last" aria-labelledby="h-sit">
      <h2 id="h-sit">A sitting</h2>
      <dl class="pair" style="grid-template-columns:repeat(2,minmax(0,1fr))">
        <div><dt>Median length</dt><dd>1m 12s<small>crafts no more than two minutes apart, over the last 90 days</small></dd></div>
        <div><dt>Median crafts</dt><dd>31<small>over the last 90 days</small></dd></div>
      </dl>
      <dl class="pair" style="grid-template-columns:repeat(2,minmax(0,1fr));margin-top:0.9rem">
        <div><dt>Sittings</dt><dd>41,806<small>of all time</small></dd></div>
        <div><dt>Seconds between crafts</dt><dd>2.4<small>median within a sitting, of all time</small></dd></div>
      </dl>
    </section>

    <section class="mod w7 first" aria-labelledby="h-reagents">
      <h2 id="h-reagents">What the crafts used</h2>
      <ol class="rank plain">
        <li><a class="who" href="#">Raw Spotted Yellowtail</a><span class="val">309,420 used</span><span class="track"><i style="width:100%"></i></span></li>
        <li><a class="who" href="#">Mild Spices</a><span class="val">231,710</span><span class="track"><i style="width:75%"></i></span></li>
        <li><a class="who" href="#">Raptor Flesh</a><span class="val">182,100</span><span class="track"><i style="width:59%"></i></span></li>
        <li><a class="who" href="#">Refreshing Spring Water</a><span class="val">153,760</span><span class="track"><i style="width:50%"></i></span></li>
        <li><a class="who" href="#">Small Egg</a><span class="val">121,360</span><span class="track"><i style="width:39%"></i></span></li>
      </ol>
      <p class="note">From the recipes whose materials are on record.</p>
    </section>
    <section class="mod w5 last" aria-labelledby="h-zones">
      <h2 id="h-zones">Zones with the most crafts</h2>
      <ol class="rank">
        <li><a class="who" href="#">Saltmarsh Reach</a><span class="val">212,400 crafts</span><span class="track"><i style="width:100%"></i></span></li>
        <li><a class="who" href="#">Pale Rest</a><span class="val">148,010</span><span class="track"><i style="width:70%"></i></span></li>
        <li><a class="who" href="#">Greywatch Ford</a><span class="val">96,318</span><span class="track"><i style="width:45%"></i></span></li>
        <li><a class="who" href="#">Hollowmere</a><span class="val">71,950</span><span class="track"><i style="width:34%"></i></span></li>
      </ol>
    </section>

    <section class="mod w12 first last camp-mod" aria-labelledby="h-top">
      <h2 id="h-top">Most crafts</h2>
      <div class="scroll-x"><table class="data" style="margin-top:1.1rem">
        <thead><tr><th class="rk">#</th><th>Character</th><th>Class</th><th class="n">Crafts</th><th>Ruleset</th></tr></thead>
        <tbody>
          <tr><td class="rk">1</td><td><a href="character.html">Name-Surname</a></td><td>Orc Warrior</td><td class="n">9,402</td><td>Hardcore</td></tr>
          <tr><td class="rk">2</td><td><a href="character.html">Name-Surname</a></td><td>Human Mage</td><td class="n">7,977</td><td>Standard</td></tr>
          <tr><td class="rk">3</td><td><a href="character.html">Name-Surname</a></td><td>Tauren Druid</td><td class="n">6,260</td><td>Slow</td></tr>
          <tr><td class="rk">4</td><td><a href="character.html">Name-Surname</a></td><td>Night Elf Hunter</td><td class="n">4,871</td><td>Standard</td></tr>
          <tr><td class="rk">5</td><td><a href="character.html">Name-Surname</a></td><td>Dwarf Paladin</td><td class="n">3,114</td><td>Hardcore</td></tr>
        </tbody></table></div>
    </section>
  </div>
</div>
</div>`, `
(() => {
  const rand = Comp.rng(41);
  Comp.bars("pts", { w: 400, h: 250, x0: -0.5, x1: 11.5, y1: 12, yt: [0, 4, 8, 12], xt: [0, 2, 4, 6, 8, 10],
    data: Array.from({ length: 12 }, (_, i) => [i, 1 + 8.4 * Math.pow(i / 11, 2.2) + rand() * 0.3]) });
  Comp.hours("when", (d, h) => {
    const evening = Math.exp(-((h - 20) ** 2) / 8) + 0.4 * Math.exp(-((h - 13) ** 2) / 14), week = d >= 5 ? 1.15 : 0.9;
    return Math.max(0, Math.min(4, Math.round(evening * week * 3.4 + (rand() - 0.5) * 0.9)));
  });
})();
`);
