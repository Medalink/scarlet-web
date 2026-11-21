{{-- Stacked share bars, one per step, with its axes, key and readout. spec: kind shares, rows [[kill, dungeon, quest, explore, other]] as shares,
     say [[bold, rest]] per bar, rest. y and x are lists of axis labels; key is a chart key. Arrow keys step through the bars. --}}
@props(['id', 'label', 'spec', 'y', 'x', 'key', 'line'])
@php($spec = [...$spec, 'out' => $id.'-out'])
<div class="xp"><div class="xp-plot">
  <div class="xp-y" aria-hidden="true">@foreach ($y as $text)<span>{{ $text }}</span>@endforeach</div>
  <div class="xp-bars" id="{{ $id }}" tabindex="0" role="group" aria-label="{{ $label }} Use the left and right arrow keys to read a level." data-chart="{{ json_encode($spec) }}"></div>
  <div class="xp-x" aria-hidden="true">@foreach ($x as $text)<span>{{ $text }}</span>@endforeach</div>
</div>
<x-key :items="$key" />
<x-readout :id="$id.'-out'" :line="$line" /></div>
