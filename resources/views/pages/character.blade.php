<x-layouts.app :title="$d['name'].': a Scarlet character overview'" description="Records, time in each level, experience, dungeons, deaths and play history of {{ $d['name'] }}, from what the player chose to upload.">
<section class="ch-head ch-rich" aria-labelledby="h1">
  <div class="stage"><x-world-canvas id="map" label="Every path {{ $d['name'] }} walked, with deaths as crosses. Drag to move, scroll to zoom." :spec="$d['map']" />
    <ul class="legend ch-legend" aria-label="Map marks">
      <li><svg viewBox="0 0 24 14" aria-hidden="true"><path d="M1 7h22" stroke="#ff2d55" stroke-width="2"/></svg>every path walked</li>
      @if ($d['deathCount'])<li><svg viewBox="0 0 24 14" aria-hidden="true"><path d="M7.5 2.5l9 9M16.5 2.5l-9 9" stroke="#f0f1f3" stroke-width="1.7" stroke-linecap="round"/></svg>death</li>@endif
    </ul>
  </div>
  <div class="ch-id wrap">
    <div><h1 class="ch-name" id="h1">{{ $d['name'] }}</h1>
      <p class="ch-sub"><i class="dot" aria-hidden="true"></i>@foreach ($d['facts'] as $fact)<span>{{ $fact }}</span>@endforeach</p>
      <dl class="ch-figs">@foreach ($d['figures'] as $figure)<div><dt>{{ $figure['label'] }}</dt><dd>{{ $figure['value'] }}</dd></div>@endforeach</dl></div>
  </div>
</section>
<div class="wrap">
  @if ($d['owner'])<livewire:owner-bar :character-id="$d['id']" />@endif
  @if ($d['records'])
  <section class="recs" aria-labelledby="h-records">
    <h2 id="h-records">Records</h2>
    <ul class="rec-grid">@foreach ($d['records'] as $tile)<li>
      @if ($tile['text'] && $tile['href'])<a @class(['rec-v', 'txt', $tile['class']]) href="{{ $tile['href'] }}">{{ $tile['value'] }}</a>@else<b @class(['rec-v', 'txt' => $tile['text']])>{{ $tile['value'] }}</b>@endif
      <span class="rec-l">{{ $tile['label'] }}</span>
      @if ($tile['detail'])<span class="rec-d">@if ($tile['href'] && ! $tile['text'])<a href="{{ $tile['href'] }}">{{ $tile['detail'] }}</a>@else{{ $tile['detail'] }}@endif</span>@endif
    </li>@endforeach</ul>
  </section>
  @endif
  @if ($d['week'])
  <section class="week" aria-labelledby="h-week">
    <div><h2 id="h-week">This week</h2><p>{{ $d['week']['range'] }}</p></div>
    @foreach ($d['week']['figures'] as $figure)<dl><dt>{{ $figure['label'] }}</dt><dd>{{ $figure['value'] }}</dd></dl>@endforeach
  </section>
  @endif
  <x-sheet>
    @foreach ($d['rows'] as $row)
    @foreach ($row as $m)
    @include('pages.character.'.$m['module'], ['span' => $m['span'], 'level' => $m['level'], 'first' => $loop->first, 'last' => $loop->last])
    @endforeach
    @endforeach
  </x-sheet>
</div>
</x-layouts.app>
