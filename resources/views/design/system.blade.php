@php
    $base = App\View\Design\Gallery::BASE;
    $surfaces = [['bg', '#141416', 'page'], ['win', '#101012', 'addon window, app chrome'], ['panel', '#1b1b1f', ''], ['card', '#232328', 'tooltips, menus'], ['fg', '#f0f1f3', 'headings, numbers'], ['text', '#cdced4', 'body'], ['muted', '#8a8a93', 'labels, 5.4:1'], ['dim', '#6e6e7a', 'lone grey marks']];
    $accents = [['accent', '#ff2d55', 'you, recorded data'], ['soft', '#ff6b8e', 'emphasis in text'], ['deep', '#c0102e', 'combat marks'], ['ink', '#141416', 'text on scarlet'], ['copper', '#c58a63', 'tier 1, 3rd place'], ['silver', '#c3c7cf', 'tier 2, 2nd place'], ['gold', '#d9b25f', 'tier 3, 1st place'], ['line, line2', 'rgba(255,255,255,0.13)', 'white 7% and 13%']];
    $series = [['s1', '#ff2d55', 'Kills · On foot'], ['s2', '#2f9ec4', 'Exploring · Swimming'], ['s3', '#98721c', 'Quests · Mounted'], ['s4', '#9a7df2', 'Dungeons · Flight paths'], ['rest', '#54545d', 'Other · Standing still']];
    $heats = [['heat 1', 'rgba(255,45,85,0.14)', '14%'], ['heat 2', 'rgba(255,45,85,0.32)', '32%'], ['heat 3', 'rgba(255,45,85,0.56)', '56%'], ['heat 4', 'rgba(255,45,85,0.8)', '80%'], ['heat 5', '#ff2d55', '100%']];
    $experience = [['Kills', 41200, '--s-kill'], ['Exploring', 2100, '--s-explore'], ['Quests', 38900, '--s-quest'], ['Dungeons', 12400, '--s-dungeon'], ['Other', 4200, '--s-other']];
    $travel = [['Swimming', 9, '--m-swim', '9m'], ['On foot', 65, '--m-foot', '1h 05m'], ['Mounted', 18, '--m-mount', '18m'], ['Flight paths', 6, '--m-flight', '6m'], ['Standing still', 64, '--m-still', '1h 04m']];
    $distance = [['Swimming', 0.9, '--m-swim'], ['On foot', 6, '--m-foot'], ['Flight paths', 2.5, '--m-flight']];
    $types = [
        ['<p style="font:800 92px/0.9 var(--display);color:#8788ee">Sable Varrow</p>', 'Character name · 92 web and app · 40 addon · class colour'],
        ['<p class="sc-num sc-num-hero">86,400</p>', 'Hero number · 84 · 48 addon'],
        ['<p class="sc-title">Level 41 to 42 in Tanaris</p>', 'Page title · 44 to 60 · 30 addon'],
        ['<p class="sc-num sc-num-xl">9.4<small>mi</small> <span class="ml-6">+14<small class="sc-g">g</small> 62<small class="sc-s">s</small></span></p>', 'Tile number, large · 52 · unit at 42%'],
        ['<p class="sc-num">9,912</p>', 'Tile number · 34 · 30 addon'],
        ['<p class="sc-h">Experience every five minutes</p>', 'Section heading · 24 to 32 · 19 addon'],
        ['<p class="text-[15px] text-copy">Life Taps more often than 91% of Warlocks at these levels, and earns XP faster than 78% of them.</p>', 'Body · Sofia Sans 15 · 13 addon'],
        ['<p class="sc-label">Played since Monday</p>', 'Label and note · 12.5 muted · 11.5 addon'],
        ['<p class="sc-mono">19:42 to 22:06 · 2h 24m</p>', 'Clock times · Martian Mono 11.5 · Sofia Sans SemiBold in the addon'],
    ];
@endphp
<x-layouts.sc title="Scarlet design system" :base="$base">
<div class="wrap pb-16 pt-9">
  <h1 class="sc-title">Scarlet design system: tokens and components</h1>
  <p class="mt-3 max-w-[760px] text-[15px] text-copy">Every component on this sheet is drawn from solid fills, lines, rotated squares, text and four authored textures, so the addon, the app and the website render the same thing. The values are the ones in DESIGN.md.</p>

  <section class="mt-14">
    <h2 class="sc-h">Surfaces, text and accent</h2>
    @foreach ([$surfaces, $accents] as $row)
      <div class="sc-sw mt-4 grid-cols-4 lg:grid-cols-8">
        @foreach ($row as [$name, $value, $role])<div><i style="--c:{{ $value }}"></i><b>{{ $name }}</b><span>{{ $value }}@if ($role) · {{ $role }}@endif</span></div>@endforeach
      </div>
    @endforeach
  </section>

  <section class="mt-14">
    <h2 class="sc-h">Chart series, in the order they are drawn</h2>
    <div class="sc-sw mt-4 grid-cols-2 md:grid-cols-5">
      @foreach ($series as [$name, $value, $role])<div><i style="--c:{{ $value }}"></i><b>{{ $name }} {{ $value }}</b><span>{{ $role }}</span></div>@endforeach
    </div>
    <div class="mt-6 grid gap-8 md:grid-cols-2">
      <div><p class="sc-label mb-2">Experience by source</p><x-sc.stack :rows="$experience" /></div>
      <div><p class="sc-label mb-2">Time by travel mode</p><x-sc.stack :rows="$travel" /></div>
    </div>
    <p class="sc-cap">Four hues and one grey. Quests moves from #b98b2d to #98721c: beside scarlet the old ochre measures 2.8 for deuteranopes, the new one 8.4. Travel runs slowest to fastest, which keeps blue and violet apart. Each stack keeps its order, a 2 px gap and a key with values.</p>
    <div class="sc-sw mt-5 max-w-[560px] grid-cols-5">
      @foreach ($heats as [$name, $value, $role])<div><i style="--c:{{ $value }}"></i><b>{{ $name }}</b><span>{{ $role }}</span></div>@endforeach
    </div>
  </section>

  <section class="mt-14">
    <h2 class="sc-h">Type</h2>
    <div class="mt-4">
      @foreach ($types as [$sample, $role])<div class="sc-type">{!! $sample !!}<span>{{ $role }}</span></div>@endforeach
    </div>
  </section>

  <section class="mt-14">
    <h2 class="sc-h">Numbers: tiles, hero, tick ring</h2>
    <div class="mt-4 grid gap-6 lg:grid-cols-[minmax(0,1fr)_330px]">
      <x-sc.tiles :cols="4">
        <x-sc.tile label="Kills" value="9,912" sub="63 an hour" />
        <x-sc.tile label="Played since Monday" value="16h 20m"><x-sc.spark :values="[0, 40, 140, 150, 210, 180, 260]" form="cols" :w="120" :h="26" /></x-sc.tile>
        <x-sc.tile label="Hours recorded" value="1,284,000"><x-sc.spark :values="[40, 44, 47, 52, 55, 61, 66, 70, 78]" :w="120" :h="26" /></x-sc.tile>
        <x-sc.tile label="Distance" value="9.4" unit="mi"><x-sc.stack :rows="$distance" :key="false" :height="8" /></x-sc.tile>
      </x-sc.tiles>
      <div class="sc-panel flex items-center gap-5 px-5 py-4">
        <x-sc.ring :value="0.78" :size="112"><b class="sc-num" style="font-size:40px">72</b><span class="sc-label">of 92</span></x-sc.ring>
        <div><p class="sc-h3">Tick ring</p><p class="sc-label mt-1">36 ticks, lit clockwise from the top. The seal's dial uses the same tick, 20 of them, with a gap at the foot.</p></div>
      </div>
    </div>
    <p class="sc-cap">Tiles share one hairline grid and are never separate cards. A tile whose value is zero is left out.</p>
    <div class="mt-6 grid gap-6 md:grid-cols-2">
      <div class="sc-panel px-5 py-4"><p class="sc-label mb-2">Play time by day, stacked by character</p><x-sc.days :spec="$d['days']" /></div>
      <div class="sc-panel px-5 py-4"><p class="sc-label mb-2">Gold held</p><x-sc.area :spec="$d['area']" /></div>
    </div>
  </section>

  <section class="mt-14">
    <h2 class="sc-h">Session strip, session card, route</h2>
    <div class="sc-panel mt-4 px-5 py-4"><x-sc.strip :recap="$d['recap']" /></div>
    <p class="sc-cap">Zones on top, experience in five-minute columns, combat time as a red line, then levels, deaths, feats and quests at their minute. Local clock times along the foot. Hover for the minute, the zone and the nearest event.</p>
    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,420px)]">
      <x-sc.session-card :recap="$d['recap']" :chip="['glyph' => 'kills', 'name' => 'Rampage', 'tier' => 2]" />
      <div class="sc-panel px-3 py-3"><p class="sc-label mb-2 px-2">Route</p><x-sc.route :recap="$d['recap']" /></div>
    </div>
    <p class="sc-cap">The compact strip drops the clock row. The route is the session's path fitted to one sheet: scarlet fades in from the oldest part, flight paths are dashed violet, level-ups are diamonds, deaths a skull.</p>
  </section>

  <section class="mt-14">
    <h2 class="sc-h">Feat seal: the family is the shape, the tier is the metal</h2>
    <x-sc.tiles :cols="6" class="mt-4">
      @foreach ([[0, 0.3, 'Not earned', 'The dial fills in copper'], [1, 0.9, null, '1 pip · fills in silver'], [2, 0.5, null, '2 pips · fills in gold'], [3, 0.7, null, '3 pips · fills in scarlet'], [4, 0.4, null, '4 pips · fills in gold'], [5, 1, null, '5 gold pips · all lit']] as [$tier, $progress, $name, $note])
        <div class="px-5 pb-5 pt-[18px] text-center"><x-sc.seal glyph="kills" :tier="$tier" :progress="$progress" :size="104" /><p class="sc-h3 mt-3">@if ($name){{ $name }}@else<span class="sc-chip t{{ $tier }}">{{ App\View\Sc\Seal::TIER_NAMES[$tier] }}</span>@endif</p><p class="sc-label">{{ $note }}</p></div>
      @endforeach
    </x-sc.tiles>
    <div class="mt-6 flex flex-wrap items-end gap-7">
      @foreach ([['hours', 'Journey', null], ['kills', 'Combat', null], ['quests', 'Quests', null], ['dungeons', 'Dungeons', null], ['zones', 'World', null], ['fish', 'Trade', null], ['m-bolt', 'Class', null], ['heal', 'Role', null]] as [$glyph, $name])
        <div class="text-center"><x-sc.seal :glyph="$glyph" :tier="2" :progress="0.5" :size="64" /><p class="sc-label mt-2">{{ $name }}</p></div>
      @endforeach
      <div class="ml-6 text-center"><x-sc.seal glyph="level" :tier="3" :size="28" /><p class="sc-label mt-2">28 · row</p></div>
      <div class="text-center"><x-sc.seal glyph="level" :tier="3" :size="18" /><p class="sc-label mt-2">18 · inline</p></div>
    </div>
    <p class="sc-cap">Web and app: one inline SVG. Addon: a cell of Plates.tga drawn two to five times, 20 Tick.tga placed with SetRotation, a cell of Glyphs.tga or the spell's texture. Under 48 px the dial and pips are dropped.</p>
  </section>

  <section class="mt-14">
    <h2 class="sc-h">Feat card</h2>
    @php
        $cards = [
            ['name' => 'Soloist', 'glyph' => 'dungeons', 'tier' => 0, 'count' => 'No dungeon run alone yet', 'to' => '1 to Copper', 'share' => '6% hold Copper or higher'],
            ['name' => 'Slayer', 'glyph' => 'kills', 'tier' => 2, 'progress' => 0.99, 'count' => '9,912 kills', 'to' => '88 to Gold', 'share' => '64% hold Silver or higher'],
            ['name' => 'Rampage', 'glyph' => 'kills', 'tier' => 2, 'progress' => 0.04, 'count' => '312 kills in one session', 'to' => '288 to Gold', 'new' => true],
            ['name' => 'Flawless', 'glyph' => 'shield', 'family' => 'dungeons', 'tier' => 5, 'progress' => 1, 'count' => '100 dungeon runs with no deaths in the party', 'to' => 'Legend since 4 November', 'share' => '<1% hold Legend'],
        ];
    @endphp
    <div class="mt-5 grid gap-x-10 gap-y-6 md:grid-cols-2">
      @foreach ($cards as $card)<x-sc.feat :feat="$card" />@endforeach
    </div>
    <p class="sc-cap">The card has no bar: the dial on the seal is the progress.</p>
  </section>

  <section class="mt-14">
    <h2 class="sc-h">A collection</h2>
    @php
        $quests = [
            ['name' => 'Questing', 'glyph' => 'quests', 'tier' => 3, 'progress' => 0.3, 'share' => '41%'],
            ['name' => 'Errand Runner', 'glyph' => 'quests', 'tier' => 2, 'progress' => 0.4, 'share' => '58%', 'on' => true],
            ['name' => 'Fickle', 'glyph' => 'quests', 'tier' => 2, 'progress' => 0.5, 'share' => '52%'],
            ['name' => 'Local Hero', 'glyph' => 'quests', 'tier' => 2, 'progress' => 0.6, 'share' => '47%'],
            ['name' => 'Thorough', 'glyph' => 'quests', 'tier' => 2, 'progress' => 0.2, 'share' => '44%'],
        ];
    @endphp
    <x-sc.collection class="mt-4 !border-t-0" name="Quests" family="quests" :feats="$quests" note="A Silver collection: every feat in it is Silver or better." />
  </section>

  <section class="mt-14">
    <h2 class="sc-h">The toast, frame by frame</h2>
    @php
        $rampage = ['name' => 'Rampage', 'glyph' => 'kills', 'tier' => 2, 'progress' => 0.04, 'count' => '300 kills in one session'];
        $jumper = ['name' => 'Jumper', 'glyph' => 'jumps', 'tier' => 5, 'progress' => 1, 'count' => '250,000 jumps'];
    @endphp
    <div class="mt-5 grid gap-x-7 gap-y-6 md:grid-cols-2 xl:grid-cols-3">
      <figure><x-sc.feat-toast :feat="$rampage" kicker="Upgraded from Copper" :play="true" /><figcaption class="sc-note mt-2">Plays once when the page opens: the plate rises, the seal is stamped, the pips light, a sweep crosses, the dial runs to full and settles at the real progress.</figcaption></figure>
      <figure><x-sc.feat-toast :feat="$rampage" kicker="Upgraded from Copper" /><figcaption class="sc-note mt-2">At rest, 1,200 ms to 5.2 s.</figcaption></figure>
      <figure><x-sc.feat-toast :feat="$jumper" kicker="Upgraded from Scarlet" :play="true" /><figcaption class="sc-note mt-2">A Legend: gold rail, two gold bursts 260 ms apart.</figcaption></figure>
    </div>
  </section>

  <section class="mt-14">
    <h2 class="sc-h">Compare strip, bar list, heat grid</h2>
    @php
        $compare = [
            ['Kills an hour', ['lo' => 0, 'hi' => 160, 'p10' => 38, 'p25' => 61, 'p50' => 84, 'p75' => 108, 'p90' => 124, 'you' => 104], '104'],
            ['Deaths an hour', ['lo' => 0, 'hi' => 1.4, 'p10' => 0.1, 'p25' => 0.22, 'p50' => 0.36, 'p75' => 0.6, 'p90' => 0.9, 'you' => 0.21], '0.21'],
            ['Jumps an hour', ['lo' => 0, 'hi' => 3000, 'p10' => 90, 'p25' => 210, 'p50' => 420, 'p75' => 760, 'p90' => 1300, 'you' => 2770, 'youText' => '2,770'], '2,770'],
        ];
        $bars = [['Wastewander Bandit', 58], ['Dunemaul Ogre', 44], ['Scorpid Hunter', 41], ['Wastewander Assassin', 37], ['Steeljaw Snapper', 29]];
    @endphp
    <div class="mt-4 grid gap-8 lg:grid-cols-[1.2fr_1fr_1fr]">
      <div>
        <div class="sc-key mb-3"><span style="--c:var(--accent)">You</span><span style="--c:var(--s-0)">Middle half</span><span style="--c:var(--text)">Median</span></div>
        <x-sc.compare :rows="$compare" :n="212" />
        <p class="sc-cap">Thin band: 10th to 90th percentile. Thick band: the middle half. Tick: the median. Diamond: you. Drawn only with 20 or more in the group.</p>
        <div class="mt-6">
          <x-sc.compare :rows="array_slice($compare, 0, 1)" :n="12" />
          <p class="sc-cap">Twelve in the group: the value stays, the strip is not drawn.</p>
        </div>
        @if ($d['rates'])
          <div class="mt-6">
            <x-sc.compare :rows="$d['rates']['rows']" :min="1" />
            <p class="sc-cap">Read from character_rates_bracket for a character of this database: {{ $d['rates']['class'] }}s at levels {{ $d['rates']['bracket'][0] }} to {{ $d['rates']['bracket'][1] }}, {{ $d['rates']['n'] }} characters. A page draws the strips from 20. @if ($d['rates']['sentence']){{ $d['rates']['sentence'] }}.@endif</p>
          </div>
        @endif
      </div>
      <div>
        <x-sc.bars :rows="$bars" />
        <p class="sc-cap">Label left, value right, a 4 px bar under both. Square ends.</p>
      </div>
      <div>
        <x-sc.heat :cells="$d['heat']" />
        <p class="sc-cap">Seven days by 24 hours, 2 px gap, five scarlet steps.</p>
      </div>
    </div>
  </section>

  <section class="mt-14">
    <h2 class="sc-h">Controls</h2>
    <div class="mt-4 flex flex-wrap items-center gap-x-8 gap-y-5">
      <span class="flex gap-2"><a class="sc-btn" href="#">Get the app</a><a class="sc-btn quiet" href="#">All sessions</a><a class="sc-btn quiet small" href="#">Link</a></span>
      <span class="flex gap-2"><button class="sc-chipbtn on">All tiers</button><button class="sc-chipbtn">Silver</button><button class="sc-chipbtn">Gold</button></span>
      <span class="flex items-center gap-2 text-[13.5px]"><span class="sc-toggle on"></span> Play a sound with a feat toast</span>
      <span class="flex items-center gap-2 text-[13.5px]"><span class="sc-toggle"></span> Animate feats</span>
    </div>
    <div class="mt-6 grid gap-y-5">
      <x-sc.scope current="everyone" url="/design/system" />
      <x-sc.scope current="compare" url="/design/system" :characters="['Sable Varrow', 'Odile Fenn']" />
      <x-sc.scope current="mine" url="/design/system" />
    </div>
    <p class="sc-cap">The scope switch on every website hub. Signed out it is not drawn. You vs everyone adds a select of the account's characters.</p>
    <x-sc.section-nav class="mt-6" :sections="['overview' => 'Overview', 'feats' => 'Feats', 'sessions' => 'Sessions', 'class' => 'Warlock', 'levelling' => 'Levelling', 'combat' => 'Combat', 'loot' => 'Loot and professions', 'time' => 'Play time']" :sticky="false" />
    <p class="sc-empty">No sessions recorded for Odile Fenn yet. They appear after the next export.</p>
    <p class="sc-cap" style="margin-top:0">An empty state is one plain sentence where the content would be. A filled scarlet button appears at most once on a view. The row of links is the section nav of a character's page: it sticks under the header on a real page, and the section in view gets the scarlet underline.</p>
  </section>

  <section class="mt-14">
    <h2 class="sc-h">Link entry points: four states on three surfaces</h2>
    <div class="sc-states mt-4">
      <div class="hd">State</div><div class="hd">Addon</div><div class="hd">App</div><div class="hd">Website</div>

      <div class="st">No app</div>
      <div><div class="wow"><p class="foot">Website: no app</p><p>The Scarlet app adds your history, all 241 feats and your pages on the website.</p><span class="sc-addr">scarletweb.io/download</span></div></div>
      <div class="none">Does not apply.</div>
      <div><x-sc.link-row action="Get the app" :quiet="false"><b class="text-fg">Record your own characters</b><br>The desktop app uploads what the addon records.</x-sc.link-row></div>

      <div class="st">App, uploading off</div>
      <div><div class="wow"><p class="foot">Website: not linked</p><p>Uploading is off in the Scarlet app.</p></div></div>
      <div><p class="mb-2 text-[12px] text-muted"><span class="sc-dot off"></span>&nbsp; Uploading is off</p><x-sc.link-row action="Website settings">40 more feats are counted on the website. Uploading is off.</x-sc.link-row></div>
      <div class="none">Nothing. The site has no data for this app.</div>

      <div class="st">Uploading, not linked</div>
      <div><div class="wow"><p class="foot">Website: not linked</p><p>Your page:</p><span class="sc-addr">scarletweb.io/c/Sable-Varrow</span><p class="mt-2 text-muted">Link in the Scarlet app: Settings, Website.</p></div></div>
      <div><p class="mb-2 text-[12px] text-muted"><span class="sc-dot off"></span>&nbsp; Not linked</p><x-sc.link-row action="Link" hide="#">Link this app to your account to pin feats and choose what is public.</x-sc.link-row></div>
      <div><p>On the character's page, one line in the head:</p><p class="mt-2 text-muted">Is this yours? <a href="#">Link the app to manage it</a></p><p class="mt-3">On /account with no app linked, the first module:</p><x-sc.link-row class="mt-2">In the Scarlet app, open Settings, Website and choose Link. It opens this site to confirm.</x-sc.link-row></div>

      <div class="st">Linked</div>
      <div><div class="wow"><p class="foot">Website: linked</p><p>Your page:</p><span class="sc-addr">scarletweb.io/c/Sable-Varrow</span><p class="mt-2 text-muted">Last upload today 22:07.</p></div></div>
      <div><p class="text-[12px]"><span class="sc-dot"></span>&nbsp; Linked to scarletweb.io</p><p class="none mt-2">No rows anywhere.</p></div>
      <div><x-sc.link-row dot="on" action="Settings">Scarlet app linked on 1 computer · last upload today 22:07</x-sc.link-row></div>
    </div>
    <p class="sc-cap">One line, in one fixed place per surface. It never opens by itself and no toast, nudge or login message mentions it. The addon cannot open a browser, so the address sits in a read-only box, selected for Ctrl+C.</p>
  </section>
</div>
</x-layouts.sc>
