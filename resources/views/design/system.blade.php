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
</div>
</x-layouts.sc>
