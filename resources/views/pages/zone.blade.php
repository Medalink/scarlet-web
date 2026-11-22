<x-layouts.app :title="'Scarlet zone: '.$d['name']" description="Time played, deaths and creatures met in {{ $d['name'] }}.">
<section class="ch-head" aria-labelledby="h1" style="min-height:27rem">
  <div class="stage"><x-world-canvas id="map" label="Heat from every recorded path, with the zone {{ $d['name'] }} in view. Drag to move, scroll to zoom." :spec="$d['map']" /></div>
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
  </x-sheet>
</div>
</x-layouts.app>
