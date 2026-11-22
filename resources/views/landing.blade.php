@php($origin = rtrim((string) config('app.url'), '/'))
@php($website = ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => 'Scarlet', 'url' => $origin.'/'])
<x-layouts.site title="Scarlet: a record of what you do in WoW Forever" description="Scarlet is an addon and desktop app for WoW Forever. The addon writes a timestamped line for each thing you do and keeps it on your PC." :brand="false">
  <x-slot:head>
    <script type="application/ld+json">@json($website)</script>
  </x-slot:head>
  <section class="hero" aria-labelledby="h1">
    <div class="hero-copy">
      <h1 id="h1">Scarlet records what you do in WoW Forever and keeps it on your PC</h1>
      <p class="lead">The addon writes a timestamped line for each thing that happens: a step, a cast, a kill, a death, a coin looted. The desktop app reads those lines and draws your character's overview, timeline, maps, charts and loot tables. Uploading to the website is optional and off until you turn it on.</p>
      <p class="hero-links">
        <x-get-app />
        <a href="/demo">See a character overview</a>
        <a href="#events">All <span data-codes>227</span> event codes</a>
      </p>
    </div>

    <div class="stage"><canvas role="img" aria-label="An invented map. A scarlet line follows one character through a 38-minute session: out of a camp, through five kills, across a ford, to a death and back, then a flight to a town."></canvas></div>

    <div class="scrub">
      <button class="iconbtn" id="play" type="button" aria-label="Pause the replay"></button>
      <div class="scrub-track">
        <canvas id="bands" aria-hidden="true"></canvas>
        <input id="scrub" type="range" min="0" max="2270" step="1" value="1430" aria-label="Time in the demo session">
      </div>
      <div class="scrub-meta">
        <p>Demo session of 19 Nov 2026, 19:02 to 19:40. The character and places are invented.</p>
        <ul class="legend" aria-label="Map marks">
          <li><svg viewBox="0 0 24 14" aria-hidden="true"><circle cx="12" cy="7" r="3.6" fill="none" stroke="#f0f1f3" stroke-width="1.6"/></svg>kill</li>
          <li><svg viewBox="0 0 24 14" aria-hidden="true"><path d="M7.5 2.5l9 9M16.5 2.5l-9 9" stroke="#f0f1f3" stroke-width="1.7" stroke-linecap="round"/></svg>death</li>
          <li><svg viewBox="0 0 24 14" aria-hidden="true"><path d="M12 1.5L17.5 7 12 12.5 6.5 7z" fill="#f0f1f3"/></svg>quest turned in</li>
          <li><svg viewBox="0 0 24 14" aria-hidden="true"><path d="M7 7l5-5 5 5M7 12l5-5 5 5" fill="none" stroke="#f0f1f3" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>new level</li>
          <li><svg viewBox="0 0 24 14" aria-hidden="true"><path d="M1 7h22" stroke="#ff6b8e" stroke-width="1.6" stroke-dasharray="6 5"/></svg>flight path</li>
          <li><svg viewBox="0 0 24 14" aria-hidden="true"><path d="M1 7h22" stroke="#f0f1f3" stroke-opacity=".7" stroke-width="1.8" stroke-dasharray="0.1 5" stroke-linecap="round"/></svg>ghost run</li>
        </ul>
      </div>
    </div>

    <div class="tape">
      <div class="tape-head"><span>scarlet.txt</span><output id="clock" for="scrub">19:26:03.42</output></div>
      <ol class="tape-lines" id="tape" aria-label="The lines Scarlet wrote up to this moment"></ol>
    </div>
  </section>

  <section class="band" id="events" aria-labelledby="h-events">
    <div class="wrap">
      <h2 id="h-events">Scarlet writes <span data-codes>227</span> kinds of event lines</h2>
      <p class="intro">Every line has a time to the hundredth of a second, a code and its fields. These codes are the whole format. Pick one to read what it records.</p>
      <div class="events">
        <div class="reader" aria-live="polite">
          <p class="reader-code" id="r-code">K</p>
          <p class="reader-what" id="r-what">A mob died to your group, and whether you landed the killing blow.</p>
          <p class="reader-fields" id="r-fields"></p>
          <p class="reader-group" id="r-group">Combat</p>
          <label class="find"><span id="find-n">Search the codes</span><input id="find" type="search" placeholder="loot, death, mail" autocomplete="off" spellcheck="false"></label>
        </div>
        <div class="groups" id="groups"></div>
      </div>
    </div>
  </section>

  <section class="band" id="never" aria-labelledby="h-never">
    <div class="wrap">
      <h2 id="h-never">What Scarlet never records</h2>
      <p class="intro">Scarlet does not store free text. Where a window holds someone's words, it keeps a count, a name or a price instead.</p>
      <table class="ledger">
        <thead><tr><th scope="col">Not recorded</th><th scope="col">Kept instead</th></tr></thead>
        <tbody>
          <tr><td>Chat text and whispers</td><td>Message and word counts per channel.</td></tr>
          <tr><td>Mail subjects and bodies</td><td>Who sent or received it, the money and the items.</td></tr>
          <tr><td>Auction notification text</td><td>The item, the count and the price.</td></tr>
          <tr><td>The text of UI errors</td><td>The error's name and how often it appeared.</td></tr>
          <tr><td>Support ticket and report text, and who a report was about</td><td>That a report was sent, and its type.</td></tr>
          <tr><td>Guild notes, the message of the day and rank names</td><td>Your rank number, and whether you are an officer or the leader.</td></tr>
          <tr><td>Calendar titles, friend notes and group finder comments</td><td>Counts: invites waiting, list sizes, listings found.</td></tr>
          <tr><td>Other players' GUIDs, account names and account friend tags</td><td>The character names of players you group, trade, mail or roll against.</td></tr>
          <tr><td>Screenshots</td><td>The file name and the time. Scarlet takes none and never reads one.</td></tr>
        </tbody>
      </table>
      <p class="aside-fact">Your own chat is kept as counts too. Scarlet counts each <code>lol</code> and <code>rip</code> you type and stores the number, not the sentence.</p>
    </div>
  </section>

  <section class="band" id="capture" aria-labelledby="h-capture">
    <div class="wrap">
      <h2 id="h-capture">The capture map: all <span data-cap="recorded">624</span> client data points worth recording are recorded</h2>
      <div class="capture">
      <div>
        <p class="intro">The capture map lists every function and event the WoW Forever client documents, build <span id="build">1.60.1.70959</span>, sorted by what it is about. A data point is an event, or a function that reads state. It is live when the client's own interface files call it.</p>
        <dl class="funnel">
          <div><dt data-cap="documented">3,482</dt><dd>data points documented in game domains</dd></div>
          <div><dt data-cap="live">2,522</dt><dd>live in the WoW Forever client</dd></div>
          <div><dt data-cap="leftOut">1,898</dt><dd>reviewed and left out, each with a written reason</dd></div>
          <div><dt data-cap="recorded">624</dt><dd>recorded by Scarlet, which is every one that remains</dd></div>
        </dl>
      </div>
      <div id="matrix">
        <p class="matrix-key"><span><i></i>recorded</span><span><i class="out"></i>left out, with a reason</span></p>
      </div>
      </div>
    </div>
  </section>

  <section class="band" id="app" aria-labelledby="h-app">
    <div class="wrap">
      <h2 id="h-app">The desktop app draws the log as a timeline, a heatmap, maps and loot tables</h2>
      <p class="intro">The app has eight views: Overview, Activity, Timeline, Sessions, Map, Charts, Compress and Settings. The figures here are drawn from the demo session and the demo character on this site.</p>
      <div class="figs">
        <figure>
          <h3>Timeline: the state you were in at each moment</h3>
          <div class="scroll-x" id="lanes" tabindex="0" role="group" aria-label="Timeline of context tags"></div>
          <figcaption>Each event is exported with the tags that were on when it happened, so a kill can be read as mounted or on foot, solo or grouped, in a dungeon or outside.</figcaption>
        </figure>
        <figure class="f-heat">
          <h3>Activity: play time by day</h3>
          <div class="heat" id="heat"></div>
          <p class="heat-key"><span>0</span><i style="background:rgba(255,255,255,.055)"></i><i style="background:#4b1626"></i><i style="background:#861a34"></i><i style="background:#cf1f45"></i><i style="background:#ff5d7e"></i><span>over 4h</span></p>
          <p class="readout" id="heat-out"></p>
        </figure>
        <figure class="f-loot">
          <h3>Loot: drop rates and who looted what</h3>
          <div class="scroll-x" tabindex="0" role="group" aria-label="Loot tables">
            <table class="data">
              <caption>tables/drops.csv</caption>
              <thead><tr><th scope="col">Creature</th><th scope="col">Item</th><th scope="col">Loot rule</th><th scope="col" class="n">Looted</th><th scope="col" class="n">Drops</th><th scope="col" class="n">Rate</th></tr></thead>
              <tbody>
                <tr><td>Reed Stalker</td><td>Stalker Hide</td><td>solo</td><td class="n">212</td><td class="n">131</td><td class="n">62%</td></tr>
                <tr><td>Reed Stalker</td><td>Cracked Fang</td><td>solo</td><td class="n">212</td><td class="n">88</td><td class="n">42%</td></tr>
                <tr><td>Fellcrag Mauler</td><td>Thick Bear Pelt</td><td>group loot, 2 players</td><td class="n">64</td><td class="n">21</td><td class="n">33%</td></tr>
                <tr><td>Fellcrag Mauler</td><td class="q2">Mauler's Clasp</td><td>group loot, 2 players</td><td class="n">64</td><td class="n">3</td><td class="n">5%</td></tr>
                <tr><td>Old Rimeclaw</td><td class="q3">Rimeclaw Talon</td><td>group loot, 2 players</td><td class="n">2</td><td class="n">2</td><td class="n">100%</td></tr>
              </tbody>
            </table>
            <table class="data">
              <caption>tables/attribution.csv</caption>
              <thead><tr><th scope="col">Character</th><th scope="col" class="n">Items</th><th scope="col" class="n">Uncommon</th><th scope="col" class="n">Rare</th><th scope="col" class="n">Epic</th><th scope="col" class="n">Rolls won</th><th scope="col" class="n">Lost</th><th scope="col" class="n">Passed</th></tr></thead>
              <tbody>
                <tr><td>Maren-Ashlow</td><td class="n">1,904</td><td class="n">212</td><td class="n">19</td><td class="n">1</td><td class="n">31</td><td class="n">22</td><td class="n">14</td></tr>
                <tr><td>Tovin-Greywake</td><td class="n">412</td><td class="n">58</td><td class="n">7</td><td class="n">0</td><td class="n">24</td><td class="n">27</td><td class="n">9</td></tr>
                <tr><td>Isolde-Fennmoor</td><td class="n">187</td><td class="n">31</td><td class="n">4</td><td class="n">1</td><td class="n">12</td><td class="n">10</td><td class="n">6</td></tr>
              </tbody>
            </table>
          </div>
          <figcaption>A rate is drops per corpse you looted, split by the loot rule in force. Group members' items are read from the client's loot messages; the message text is not stored.</figcaption>
        </figure>
      </div>
    </div>
  </section>

  <section class="band" id="files" aria-labelledby="h-files">
    <div class="wrap">
      <h2 id="h-files">The log is plain files on your PC</h2>
      <ol class="pipe">
        <li><h3>The addon writes compressed chunks</h3><p>In game, lines collect in a buffer and are sealed into compressed chunks in the addon's SavedVariables file. The game rewrites that file on <code>/reload</code>, logout or disconnect.</p></li>
        <li><h3>The companion exports them</h3><p><code>scarlet export</code> reads every account folder and writes a readable <code>scarlet.txt</code>, compressed payloads and CSV tables under <code>out/Name-Surname/</code>.</p></li>
        <li><h3>The app reads the folder</h3><p>The desktop app opens <code>out/</code> and can run the export itself. It keeps its settings next to the exe.</p></li>
      </ol>
      <div class="store">
        <ul class="tree" aria-label="The folder the companion writes">
          <li><p><b>out/</b></p>
            <ul>
              <li><p><b>Maren-Ashlow/</b></p>
                <ul>
                  <li><p><b>scarlet.txt</b><span>every event, one readable line each</span></p></li>
                  <li><p>payloads/</p>
                    <ul>
                      <li><p><b>412-431.scl</b><span>one export run, compressed</span></p></li>
                    </ul>
                  </li>
                  <li><p>tables/</p>
                    <ul>
                      <li><p><b>events.csv</b><span>every event with its place and tags</span></p></li>
                      <li><p><b>sessions.csv</b><span>start, end, class and levels</span></p></li>
                      <li><p><b>names.csv</b><span>ids to names</span></p></li>
                      <li><p><b>stats.csv</b><span>character stats over time</span></p></li>
                      <li><p><b>statistics.csv</b><span>the client's Statistics panel</span></p></li>
                      <li><p><b>drops.csv</b><span>drop rates per creature and loot rule</span></p></li>
                      <li><p><b>attribution.csv</b><span>who looted what</span></p></li>
                    </ul>
                  </li>
                </ul>
              </li>
            </ul>
          </li>
        </ul>
        <ul class="facts">
          <li>The addon keeps up to 8 MB of chunks and warns in chat at 6 MB. Past that it drops the oldest, so the companion tracks which chunks it has already exported.</li>
          <li>Lines not yet sealed are saved with the file on reload, logout or disconnect, and sealed at the next login.</li>
          <li>A client crash writes no SavedVariables. Lines since the last save are lost.</li>
          <li>A field the client marks secret is written as <code>?</code>.</li>
        </ul>
      </div>
    </div>
  </section>

  <section class="close" aria-labelledby="h-close">
    <div class="wrap">
      <h2 id="h-close">The demo character Maren Ashlow has <span data-hours>96</span> hours of play on record</h2>
      <p>The overview shows experience by level and source, gold, dungeon runs, deaths and every session, all drawn from invented data.</p>
      <p class="close-links">
        <a class="btn on-scarlet" href="/demo">Open the character overview
          <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M3 8h9M8.5 4l4 4-4 4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
        <a href="/download">Download the app for Windows</a>
      </p>
    </div>
  </section>
</main>
</x-layouts.site>
