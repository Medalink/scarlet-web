<x-layouts.app :title="$d['name'].': time played, deaths and creatures'" :description="implode(', ', $d['facts']).'. Time played, deaths and creatures met in '.$d['name'].' in WoW Forever.'">
<section class="ch-head" aria-labelledby="h1" style="min-height:27rem">
  <div class="stage"><x-world-canvas id="map" :behind-head="true" label="Heat from every recorded path, with the zone {{ $d['name'] }} in view. Drag to move, click the map and scroll to zoom." :spec="$d['map']" /></div>
  <div class="ch-id wrap" style="padding-top:3rem">
    <div><h1 class="ch-name" id="h1">{{ $d['name'] }}</h1>
      <p class="ch-sub">@foreach ($d['facts'] as $fact)<span>{{ $fact }}</span>@endforeach</p></div>
  </div>
</section>
<div class="wrap">
  <section class="pg-head" aria-label="Filters" style="grid-template-columns:minmax(0,1fr)"><div class="filters"><x-scope-filters :filters="$d['filters']" /></div></section>
  <x-figures :items="$d['figures']" />
  <x-sheet>
    @if ($d['threat'])
    <x-threat :threat="$d['threat']" />
    @endif
    @if ($d['floors'])
    <x-module span="w12" :first="true" :last="true" class="floors" id="h-map" heading="Map">
      @if (count($d['floors']) > 1)
      <noscript><style>.floors .seg { display: none; } .floors .floor[hidden] { display: grid; }</style></noscript>
      <x-slot:controls><x-seg label="Floor" buttons :items="array_map(fn (array $floor, int $n): array => ['label' => $floor['label'], 'on' => $n === 0, 'data' => ['floor' => $n]], $d['floors'], array_keys($d['floors']))" /></x-slot:controls>
      @endif
      @foreach ($d['floors'] as $n => $floor)
      <figure class="floor" data-floor="{{ $n }}"@if ($n > 0) hidden @endif>
        <div class="floor-map"><img src="{{ $floor['src'] }}" width="{{ $floor['width'] }}" height="{{ $floor['height'] }}" alt="Map of {{ $floor['label'] }}" loading="{{ $n > 0 ? 'lazy' : 'eager' }}" decoding="async">
          @foreach ($floor['bosses'] as $b => $boss)
          <span class="floor-pin" style="left:{{ $boss['left'] }}%;top:{{ $boss['top'] }}%" title="{{ $boss['label'] }}" aria-hidden="true">{{ $b + 1 }}</span>
          @endforeach
        </div>
        @if ($floor['bosses'])
        <ol class="floor-bosses" aria-label="Bosses on {{ $floor['label'] }}">
          @foreach ($floor['bosses'] as $boss)
          <li>@if ($boss['href'])<a href="{{ $boss['href'] }}">{{ $boss['label'] }}</a>@else{{ $boss['label'] }}@endif</li>
          @endforeach
        </ol>
        @endif
        <figcaption>@if ($floor['credit'])Map drawn by {{ $floor['credit'] }} for <a href="https://github.com/openmap-world/openmap" rel="noopener">OpenMap</a>.@else Map art © Blizzard Entertainment. Map by <a href="https://github.com/openmap-world/openmap" rel="noopener">OpenMap</a>.@endif</figcaption>
      </figure>
      @endforeach
    </x-module>
    @endif
    <x-module span="w5" :first="true" id="h-k" heading="Deadliest here">
      @if ($d['killers'])
      <x-rank :rows="$d['killers']" />
      @else
      <x-note>No deaths recorded here yet.</x-note>
      @endif
    </x-module>
    <x-module span="w7" :last="true" id="h-c" heading="Creatures met here">
      @if ($d['creatures'])
      <x-rank :rows="$d['creatures']" plain />
      @else
      <x-note>No creatures recorded here yet.</x-note>
      @endif
    </x-module>
    @if ($loot)
    <x-module span="w12" :first="true" :last="true" id="h-l" heading="Looted here">
      @if ($loot['sources'])
      <x-table :rows="$loot['sources']" :columns="[['label' => 'Source'], ['label' => 'Corpses looted', 'numeric' => true], ['label' => 'Most common item']]" style="margin-top:1.1rem" />
      @endif
      @if ($loot['items'])
      <x-table :rows="$loot['items']" :columns="[['label' => 'Item'], ['label' => 'Dropped', 'numeric' => true], ['label' => 'Best source']]" style="margin-top:1.1rem" />
      @endif
      <p class="note">Loot covers every ruleset. <a href="{{ $loot['href'] }}">{{ $loot['label'] }}</a></p>
    </x-module>
    @endif
  </x-sheet>
</div>
</x-layouts.app>
