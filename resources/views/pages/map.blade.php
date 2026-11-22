<x-layouts.app title="WoW Forever world map: where characters go and die" current="world" description="Paths, deaths and creatures met on one world map, from what players chose to upload.">
<x-world>
  <x-slot:map>
    <div class="world-map">
      <x-world-canvas id="map" label="The world map with heat from recorded {{ $d['layerWord'] }}. Drag to move, scroll to zoom." :spec="$d['map']" />
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
    <p class="world-zone"><a id="{{ \App\View\Pages\MapPage::ZONE_PAGE }}" href="{{ $d['zonePage']['href'] ?? '' }}" @if (! $d['zonePage']) hidden @endif>{{ $d['zonePage']['label'] ?? '' }}</a></p>
    @if ($d['empty'])<x-note>No {{ $d['layerWord'] }} are recorded on the world map yet.</x-note>@endif
    <h2>Busiest zones</h2>
    @if ($d['zonesList'])
    <ol class="rank plain world-zones">
      <li><a class="who" href="{{ $d['world']['href'] }}" data-map-sheet="{{ $d['world']['sheet'] }}" data-map-for="map" data-map-world @if ($d['world']['on']) aria-current="true" @endif>World</a></li>
      @foreach ($d['zonesList'] as $row)<li><a class="who" href="{{ $row['href'] }}" data-map-sheet="{{ $row['sheet'] }}" data-map-for="map" @if ($row['on']) aria-current="true" @endif>{{ $row['label'] }}</a><span class="val">{{ $row['value'] }}</span><span class="track"><i style="width:{{ round($row['share'], 1) }}%"></i></span></li>@endforeach
    </ol>
    @else<x-note>No play time recorded yet.</x-note>@endif
  </aside>
</x-world>
</x-layouts.app>
