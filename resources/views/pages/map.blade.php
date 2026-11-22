<x-layouts.app title="Scarlet world map: where characters go and die" current="world" description="Paths, deaths and creatures met on one world map, from what players chose to upload.">
<x-world>
  <x-slot:map>
    <div class="world-map">
      <x-world-canvas id="map" :fills-window="true" label="The world map with heat from recorded {{ $d['layerWord'] }}. Drag to move, scroll to zoom." :spec="$d['map']" />
    </div>
  </x-slot:map>
  <aside class="world-side">
    <h1 id="h1">World map</h1>
    <p class="sub">Every path, death and creature that players chose to upload, on one map. Zoom into a zone to open it.</p>
    <div class="world-ctl">
      <x-seg label="Layer" :buttons="true" :items="$d['layers']" />
      <div class="seg" id="map-look"></div>
      @if ($d['rulesetPick'])<x-pick label="Ruleset" :options="$d['rulesetPick']" :selected="$d['rulesetSelected']" />@else<x-seg label="Ruleset" :items="$d['rulesets']" />@endif
    </div>
    <x-density-key low="Few" high="Many characters" />
    <x-readout id="map-out" :line="$d['line']" />
    @if ($d['empty'])<x-note>No {{ $d['layerWord'] }} are recorded on the world map yet.</x-note>@endif
    <h2>Busiest zones</h2>
    @if ($d['zonesList'])<x-rank :rows="$d['zonesList']" plain />@else<x-note>No play time recorded yet.</x-note>@endif
  </aside>
</x-world>
</x-layouts.app>
