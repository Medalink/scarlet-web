<x-layouts.site :demo="true" title="Maren Ashlow: a Scarlet character overview with demo data" description="The character overview Scarlet builds from its log: experience by level and source, gold, dungeon runs, deaths, travel and every session. The data on this page is invented." :brand="false">
  <section class="ch-head" aria-labelledby="h1">
    <div class="stage"><canvas role="img" aria-label="An invented map of Saltmarsh Reach. Every path the character walked is drawn as a thin scarlet line, so the routes used most glow brightest. Crosses mark six deaths."></canvas></div>
    <div class="ch-id wrap">
      <div>
        <h1 class="ch-name" id="h1">Maren Ashlow</h1>
        <p class="ch-sub"><i class="dot" aria-hidden="true"></i><span id="c-line">Level 38 Orc Warrior</span><span>Horde</span><span id="c-zone">in Hollowmere</span><span id="c-played">4d 0h played</span></p>
        <div class="lvl">
          <div class="lvl-bar" role="img" aria-label="Progress to the next level"><i id="c-bar" style="width:38%"></i></div>
          <p id="c-xp">38% to level 39 · 51,274 XP to go</p>
        </div>
        <p class="demo-note">Invented demo data, exported Fri 20 Nov 2026 at 21:14.</p>
      </div>
      <ul class="legend ch-map" aria-label="Map marks">
        <li><svg viewBox="0 0 24 14" aria-hidden="true"><path d="M1 7h22" stroke="#ff2d55" stroke-width="2"/></svg>every path walked in Saltmarsh Reach</li>
        <li><svg viewBox="0 0 24 14" aria-hidden="true"><path d="M1 7h22" stroke="#ff6b8e" stroke-width="1.6" stroke-dasharray="6 5"/></svg>flight path</li>
        <li><svg viewBox="0 0 24 14" aria-hidden="true"><path d="M7.5 2.5l9 9M16.5 2.5l-9 9" stroke="#f0f1f3" stroke-width="1.7" stroke-linecap="round"/></svg>death</li>
      </ul>
    </div>
  </section>

  <div class="wrap">
    <section class="week" aria-labelledby="h-week">
      <div><h2 id="h-week">This week</h2><p>Mon 16 to Sun 22 Nov</p></div>
      <dl><dt>Played</dt><dd id="w-p">8h 48m</dd></dl>
      <dl><dt>Dungeons</dt><dd id="w-r">1</dd></dl>
      <dl><dt>Quests</dt><dd id="w-q">26</dd></dl>
      <dl><dt>Gold</dt><dd id="w-g">+9g 52s</dd></dl>
      <dl><dt>Zones</dt><dd id="w-z">4</dd></dl>
    </section>

    <div class="sheet">
      <section class="mod w8 first" aria-labelledby="h-xp">
        <div class="mod-head">
          <h2 id="h-xp">Experience by level</h2>
          <div class="seg" role="group" aria-label="What the bars show">
            <button type="button" data-xp="xp" aria-pressed="true">XP sources</button><button type="button" data-xp="time" aria-pressed="false">Time per level</button>
          </div>
        </div>
        <div class="xp">
          <div class="xp-plot">
            <div class="xp-y" id="xp-y" aria-hidden="true"></div>
            <div class="xp-bars" id="xp-bars" tabindex="0" role="group" aria-label="One bar per level. Use the left and right arrow keys to read a level."></div>
            <div class="xp-x" id="xp-x" aria-hidden="true"></div>
          </div>
          <ul class="key" id="xp-key">
            <li><i class="c-kill"></i>Kills</li><li><i class="c-dungeon"></i>Dungeon kills</li><li><i class="c-quest"></i>Quests</li><li><i class="c-explore"></i>Exploring</li><li><i class="c-other"></i>Other</li>
          </ul>
          <p class="readout" id="xp-out" aria-live="polite"></p>
        </div>
      </section>

      <section class="mod last" aria-labelledby="h-gold">
        <h2 id="h-gold">Gold</h2>
        <p class="big" id="g-bal"></p>
        <p class="sub" id="g-chg"></p>
        <div class="gold-plot">
          <svg id="g-svg" viewBox="0 0 300 100" preserveAspectRatio="none" role="img" aria-label="Balance at the end of each of the last 30 sessions"></svg>
          <div class="gold-axis"><span id="g-from"></span><span id="g-to"></span></div>
        </div>
        <p class="readout" id="g-out"></p>
      </section>

      <section class="mod first" aria-labelledby="h-runs">
        <h2 id="h-runs">Dungeons and raids</h2>
        <ol class="rank" id="runs"></ol>
        <p class="total" id="runs-total"></p>
      </section>

      <section class="mod" aria-labelledby="h-mates">
        <h2 id="h-mates">Played with</h2>
        <ol class="rank" id="mates"></ol>
        <p class="total">Time grouped together, from the lines written when a player joins or leaves your group.</p>
      </section>

      <section class="mod last" aria-labelledby="h-deaths">
        <h2 id="h-deaths">Deaths</h2>
        <ol class="rank" id="deaths"></ol>
        <p class="total" id="killer"></p>
        <div class="recap">
          <h3 id="recap-h"></h3>
          <ol id="recap"></ol>
        </div>
      </section>

      <section class="mod w5 first" aria-labelledby="h-days">
        <h2 id="h-days">Play time by day</h2>
        <div class="heat" id="heat"></div>
        <p class="heat-key"><span>0</span><i style="background:rgba(255,255,255,.055)"></i><i style="background:#4b1626"></i><i style="background:#861a34"></i><i style="background:#cf1f45"></i><i style="background:#ff5d7e"></i><span>over 4h</span></p>
        <p class="readout" id="heat-out"></p>
      </section>

      <section class="mod w7 last" aria-labelledby="h-travel">
        <h2 id="h-travel">Distance by how you moved</h2>
        <ol class="rank" id="travel"></ol>
        <p class="total" id="flights"></p>
      </section>

      <section class="mod w12 first last" aria-labelledby="h-hist">
        <div class="mod-head">
          <h2 id="h-hist">Play history</h2>
          <div class="seg" role="group" aria-label="Rows">
            <button type="button" data-hist="sessions" aria-pressed="true">Sessions</button><button type="button" data-hist="days" aria-pressed="false">Days</button><button type="button" data-hist="levels" aria-pressed="false">Levels</button>
          </div>
        </div>
        <div class="history scroll-x" tabindex="0" role="group" aria-label="Play history table">
          <table class="data" id="hist"></table>
        </div>
        <button class="linkbtn" id="hist-more" type="button"></button>
      </section>
    </div>
  </div>

  <section class="band" aria-labelledby="h-game" style="border-top:0">
    <div class="wrap ingame">
      <div>
        <h2 id="h-game">The same page is in game at /scarlet</h2>
        <p>The Character page of the addon's window reads the same rows. The companion writes your history up to the last export, and the addon adds the sessions since then and the one in progress.</p>
        <p>In game the page is shorter: the header, this week, the current level so far, the players you grouped with and deaths.</p>
      </div>
      <div class="win" role="img" aria-label="A drawing of the addon's in-game Character page with the same demo numbers">
        <div class="win-bar"><span>/scarlet</span><span>Scarlet</span></div>
        <ul class="win-nav"><li aria-current="page">Character</li><li>This session</li><li>Stats</li><li>People</li><li>Live</li><li>Settings</li></ul>
        <div class="win-page">
          <p class="nm"><b>Maren Ashlow</b><span id="i-line">Level 38 · Hollowmere</span></p>
          <div class="lvl-bar"><i style="width:38%"></i></div>
          <p class="win-note" id="i-level">38% to 39 · this level 4h 40m so far</p>
          <div class="win-grid">
            <section><h3>This week</h3><dl id="i-week"></dl></section>
            <section><h3 id="i-cur-h">Level 38 so far</h3><dl id="i-cur"></dl></section>
            <section><h3>Played with</h3><dl id="i-mates"></dl></section>
            <section><h3>Deaths</h3><dl id="i-deaths"></dl></section>
          </div>
          <p class="win-note">History through your last export · <span class="live">this session live</span></p>
        </div>
      </div>
    </div>
  </section>
</x-layouts.site>
