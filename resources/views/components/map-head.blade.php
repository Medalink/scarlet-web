{{-- The head of a zone or a character: a map behind the name, a line of facts, an optional level bar and the map's legend.
     facts: strings; progress: ['percent' => 38, 'text' => '...']; legend: [['mark' => 'path|death|heat', 'label']]; dot adds the class dot. --}}
@props(['name', 'facts', 'map', 'mapId' => 'map', 'mapLabel', 'legend', 'dot' => false, 'progress' => null, 'minHeight' => null])
<section class="ch-head" aria-labelledby="h1"@if ($minHeight) style="min-height:{{ $minHeight }}"@endif>
  <div class="stage"><x-map-canvas :id="$mapId" :label="$mapLabel" :spec="$map" /></div>
  <div class="ch-id wrap" style="padding-top:3rem">
    <div><h1 class="ch-name" id="h1">{{ $name }}</h1>
      <p class="ch-sub">@if ($dot)<i class="dot" aria-hidden="true"></i>@endif@foreach ($facts as $fact)<span>{{ $fact }}</span>@endforeach</p>
      @if ($progress)<div class="lvl"><div class="lvl-bar" role="img" aria-label="Progress to the next level"><i style="width:{{ $progress['percent'] }}%"></i></div><p>{{ $progress['text'] }}</p></div>@endif</div>
    <ul class="legend ch-map" aria-label="Map marks">@foreach ($legend as $item)<li><svg viewBox="0 0 24 14" aria-hidden="true">@switch($item['mark'])@case('death')<path d="M7.5 2.5l9 9M16.5 2.5l-9 9" stroke="#f0f1f3" stroke-width="1.7" stroke-linecap="round"/>@break@case('path')<path d="M1 7h22" stroke="#ff2d55" stroke-width="2"/>@break@default<rect x="5" y="1" width="12" height="12" fill="#cf1f45"/>@endswitch</svg>{{ $item['label'] }}</li>@endforeach</ul>
  </div>
</section>
