{{-- The play time band in UTC; resources/js/play.js draws it again in the viewer's time zone. play: App\View\Pages\CharacterPlay::band(). --}}
@props(['play'])
@php($bars = $play['bars'])
@php($grid = $play['grid'])
<div class="ch-band" data-chart="{{ json_encode($play['spec']) }}">
  <div class="pt">
    <h4 class="chart-name">Last {{ $play['spec']['days'] }} days</h4>
    <div class="pt-plot">
      @if ($bars['max'])<p class="pt-max" aria-hidden="true">{{ $bars['max'] }}</p>@endif
      <div class="pt-bars" role="group" aria-label="Hours played in each of the last {{ $play['spec']['days'] }} days, in UTC">@foreach ($bars['columns'] as $column)<span @class([$column['class'] => $column['class'] !== '']) title="{{ $column['title'] }}"><i style="height:{{ $column['height'] }}%"></i></span>@endforeach</div>
    </div>
    <p class="pt-x" aria-hidden="true">@foreach ($bars['labels'] as $label)<span>{{ $label }}</span>@endforeach</p>
    <x-readout id="days-out" :line="$bars['line']" />
  </div>
  <div class="pg">
    <h4 class="chart-name">Hour of the week</h4>
    <div class="hours" role="group" aria-label="Play time by weekday and hour of day, in UTC"><span></span>@for ($h = 0; $h < 24; $h++)<span class="h">{{ $h % 3 ? '' : \App\View\Dates::hourMark($h) }}</span>@endfor
@foreach ($grid['rows'] as $name => $cells)<span>{{ $name }}</span>@foreach ($cells as [$level, $title])<i data-l="{{ $level }}" title="{{ $title }}"></i>@endforeach
@endforeach</div>
    <x-readout id="hours-out" :line="$grid['line']" />
    <x-heat-key low="Quiet" high="Busiest" :zero="true" />
  </div>
</div>
