<x-layouts.sc :title="$d['personal'] ? $d['title'].' on the WoW Forever world map' : 'WoW Forever world map: where characters go and die'" current="map" :description="$d['personal'] ? null : 'Paths, deaths and creatures met on one world map, from what players chose to upload.'" :index="! $d['personal']">
<x-world>
  <x-slot:map>
    <div class="world-map">
      @if ($d['personal'])
      <x-world-canvas id="map" label="Every path {{ $d['title'] }} walked and every flight taken. Drag to move, scroll to zoom." :spec="$d['map']" />
      @else
      <x-world-canvas id="map" label="The world map with heat from recorded {{ $d['layerWord'] }}. Drag to move, scroll to zoom." :spec="$d['map']" />
      @endif
    </div>
  </x-slot:map>
  <aside class="world-side">
    <h1 id="h1">{{ $d['title'] }}</h1>
    @unless ($d['personal'])<p class="sub">Every path, death and creature that players chose to upload, on one map. Zoom into a zone to open it.</p>@endunless
    <div class="world-ctl">
      @if ($d['whose'])@if ($d['whose']['pick'])<x-pick label="Whose map" :options="$d['whose']['pick']" :selected="$d['whose']['selected']" />@else<x-seg label="Whose map" :items="$d['whose']['items']" />@endif @endif
      @if ($d['personal'])
      <x-seg label="Travel" :buttons="true" :items="$d['personal']['travel']" />
      <x-seg label="Flight paths" :buttons="true" :items="$d['personal']['flights']" />
      @else
      <x-seg label="Layer" :buttons="true" :items="$d['layers']" />
      @endif
      <div class="sc-seg" id="map-look"></div>
      @if ($d['rulesetPick'])<x-pick label="Ruleset" :options="$d['rulesetPick']" :selected="$d['rulesetSelected']" />@elseif ($d['rulesets'])<x-seg label="Ruleset" :items="$d['rulesets']" />@endif
    </div>
    @if ($d['personal'])<x-density-key low="Once" high="Often walked" />@else<x-density-key low="Few" high="Many characters" />@endif
    <x-readout id="map-out" :line="$d['line']" />
    <p class="world-zone"><a id="{{ \App\View\Pages\MapPage::ZONE_PAGE }}" href="{{ $d['zonePage']['href'] ?? '' }}" @if (! $d['zonePage']) hidden @endif>{{ $d['zonePage']['label'] ?? '' }}</a></p>
    @if ($d['empty'])<x-note>{{ $d['empty'] }}</x-note>@endif
    <h2>Busiest zones</h2>
    @if ($d['zonesList'])
    <ol class="rank plain world-zones">
      <li><a class="who" href="{{ $d['world']['href'] }}" data-map-sheet="{{ $d['world']['sheet'] }}" data-map-for="map" data-map-world @if ($d['world']['on']) aria-current="true" @endif>World</a></li>
      @foreach ($d['zonesList'] as $row)<li><a class="who" href="{{ $row['href'] }}" data-map-sheet="{{ $row['sheet'] }}" data-map-for="map" @if ($row['on']) aria-current="true" @endif>{{ $row['label'] }}</a><span class="val">{{ $row['value'] }}</span><span class="track"><i style="width:{{ round($row['share'], 1) }}%"></i></span></li>@endforeach
    </ol>
    @else<x-note>No play time recorded yet.</x-note>@endif
  </aside>
</x-world>
</x-layouts.sc>
