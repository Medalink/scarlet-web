{{-- A chart of one rate in each arm of a source, a row for each item (docs/comps/creature.html). chart: App\View\Pages\ArmChart. --}}
@props(['chart'])
@if ($chart['rows'])
<div class="arms">
  <div class="arms-scale" aria-hidden="true"><div class="arms-ticks"><i></i><div><span>0</span><span>50</span><span>100%</span></div><i></i></div></div>
  @foreach ($chart['rows'] as $row)
  <div class="arm-row">
    <div><a class="arm-item" href="{{ $row['href'] }}">{{ $row['name'] }}</a>@if ($row['tag']) <span class="tag hc">{{ $row['tag'] }}</span>@endif</div>
    <div class="arm-set">
      @foreach ($row['arms'] as $arm)
      <div class="arm"><span class="al">{{ $arm['label'] }}@foreach ($arm['tags'] as $tag)<span class="tag">{{ $tag }}</span>@endforeach</span><x-rate-mark :estimate="$arm['estimate']" /><span class="kn">{{ $arm['estimate']->counts() }}</span></div>
      @endforeach
      @if ($row['verdict'] && $row['verdict']['marked'])
      <p class="arm-verdict"><b class="mark">{{ $row['verdict']['text'] }}</b>@foreach ($row['verdict']['tags'] as $set)@if ($set['arm'] !== '')<span class="mut">{{ $set['arm'] }}</span>@endif @foreach ($set['tags'] as $tag)<span class="tag">{{ $tag }}</span>@endforeach @endforeach</p>
      @elseif ($row['verdict'])
      <p class="arm-verdict mut">{{ $row['verdict']['text'] }}</p>
      @endif
    </div>
  </div>
  @endforeach
</div>
@endif
@if ($chart['empty'])
<x-note>{{ $chart['empty'] }}</x-note>
@endif
@if ($chart['note'] || $chart['groups'])
<p class="note">{{ $chart['note'] }}@if ($chart['groups']) <a href="{{ $chart['groups'] }}">Loot rules and group size</a> for this creature.@endif</p>
@endif
