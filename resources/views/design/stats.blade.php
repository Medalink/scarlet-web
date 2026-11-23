@php
    use App\View\Design\Gallery;
    use App\View\Format;
    $f = Gallery::filters();
    $dungeons = array_map(fn (array $r): array => ['cells' => [['text' => $r[0], 'href' => Gallery::url('zone')], Format::number($r[1]), Format::duration($r[2]), Format::number($r[3], 1), Format::number($r[4])]], $d['dungeons']);
@endphp
<x-layouts.app title="Scarlet stats: how characters level in WoW Forever" current="stats" :base="Gallery::BASE">
<div class="wrap">
  <x-page-head title="All characters">
    <x-slot:sub>1,284 characters, 41,907 hours of play, recorded since 14 Nov 2026. Updated 6 minutes ago.</x-slot:sub>
    <x-slot:filters><x-ruleset-filters :rulesets="$f['rulesets']" :servers="$f['servers']" :classes="$f['classes']" /></x-slot:filters>
  </x-page-head>

  <x-figures :items="$d['figures']" />

  <x-sheet>
    <x-module span="w8" :first="true" id="h-curve" heading="Played time to reach each level">
      <x-slot:controls><x-seg label="Scale" :buttons="true" :items="[['label' => 'Hours', 'on' => true], ['label' => 'Days played']]" /></x-slot:controls>
      <x-plot id="curve" label="Median played hours to reach each level, with the middle half of characters as a band" :spec="$d['curve']['spec']" />
      <x-key :items="[['label' => 'Median', 'class' => 'k-line', 'style' => 'background:var(--accent)'], ['label' => 'Middle half of characters', 'style' => 'background:rgba(255,45,85,.3)'], ['label' => 'Fastest tenth', 'class' => 'k-line', 'style' => 'background:var(--fg)']]" />
      <x-readout id="curve-out" :line="$d['curve']['line']" />
    </x-module>
    <x-module :last="true" id="h-reach" heading="Characters at each level">
      <x-plot id="reach" label="How many characters are currently at each level" :spec="$d['reach']" />
      <x-note>Half of all characters are below level 23.</x-note>
    </x-module>

    <x-module span="w8" :first="true" id="h-xp" heading="Where experience comes from, by level">
      <x-shares-chart id="xp" label="Share of experience from kills, dungeon kills, quests, exploring and other, one bar per level." :spec="$d['xp']['spec']" :y="['100%', '50%', '0']" :x="[1, 10, 20, 30, 40, 50, 59]" :line="$d['xp']['line']"
        :key="[['class' => 'c-kill', 'label' => 'Kills'], ['class' => 'c-dungeon', 'label' => 'Dungeon kills'], ['class' => 'c-quest', 'label' => 'Quests'], ['class' => 'c-explore', 'label' => 'Exploring'], ['class' => 'c-other', 'label' => 'Other']]" />
    </x-module>
    <x-module :last="true" id="h-time" heading="Hours spent in each level">
      <x-plot id="ltime" label="Median hours a character spends in each level" :spec="$d['ltime']" />
      <x-note>The slowest level is 58, at a median of 5h 12m.</x-note>
    </x-module>

    <x-module :first="true" id="h-deaths" heading="Deaths per hour, by level">
      <x-plot id="dph" label="Deaths per played hour at each level" :spec="$d['dph']" />
      <x-note>Most dangerous: levels 24 to 27.</x-note>
    </x-module>
    <x-module id="h-killers" heading="What kills characters">
      <x-rank :rows="$d['killers']" />
    </x-module>
    <x-module :last="true" id="h-dz" heading="Where characters die">
      <x-rank :rows="$d['deathZones']" plain />
    </x-module>

    <x-module span="w7" :first="true" id="h-dung" heading="Dungeons">
      <x-table style="margin-top:1.1rem" :columns="[['label' => 'Dungeon'], ['label' => 'Runs', 'numeric' => true], ['label' => 'Median time', 'numeric' => true], ['label' => 'Deaths a run', 'numeric' => true], ['label' => 'Median level', 'numeric' => true]]" :rows="$dungeons" />
    </x-module>
    <x-module span="w5" :last="true" id="h-prof" heading="Professions">
      <x-rank :rows="$d['professions']" />
    </x-module>

    <x-module span="w5" :first="true" id="h-gold" heading="Gold carried, by level">
      <x-plot id="gold" label="Median gold a character carries at each level, with the middle half as a band" :spec="$d['gold']" />
      <x-note>Median at level 40: <x-money :copper="314000" />. Characters who shared their economy data only.</x-note>
    </x-module>
    <x-module span="w7" :last="true" id="h-when" heading="When people play">
      <x-hours-grid id="hours" label="Play time by weekday and hour of day, in UTC" :spec="$d['hours']" />
      <x-heat-key low="Quiet" high="Busiest" :zero="true" extra="Hours are UTC" />
    </x-module>

    <x-module span="w12" :first="true" :last="true" id="h-map" heading="Where characters go in Saltmarsh Reach">
      <x-slot:controls><x-pick label="Zone" :options="['Saltmarsh Reach', 'Hollowmere']" /></x-slot:controls>
      <x-map-box id="map" label="Heat from every recorded path in the zone, with deaths as crosses" ratio="21/9" :spec="$d['map']" />
      <x-heat-key low="Few paths" high="Many" />
    </x-module>
  </x-sheet>
</div>
</x-layouts.app>
