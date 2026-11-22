{{-- A flow chart: block is App\Support\ClassPage\Flow. Nothing is drawn for an empty pool. --}}
@props(['block', 'icons'])
@php($f = \App\View\ClassPage\FlowLayout::of($block))
@if ($f)
<div class="plot flow"><svg viewBox="0 0 {{ $f['width'] }} {{ $f['height'] }}" role="img" aria-label="{{ $block->label }}">
@foreach ($f['left'] as $s)
<path class="fl {{ $s['tone'] }}" d="{{ $s['ribbon'] }}"/><rect class="fn {{ $s['tone'] }}" x="{{ $s['x'] }}" y="{{ $s['y'] }}" width="{{ $s['w'] }}" height="{{ $s['h'] }}" rx="1.5"/><x-class.pin :pin="$s['icon']" :icons="$icons" /><text class="fl-l" x="{{ $s['tx'] }}" y="{{ $s['ty'] }}" text-anchor="end">{{ $s['label'] }}</text><text class="fl-v" x="{{ $s['tx'] }}" y="{{ $s['vy'] }}" text-anchor="end">{{ $s['value'] }}</text>
@endforeach
@foreach ($f['right'] as $s)
<path class="fl {{ $s['tone'] }}" d="{{ $s['ribbon'] }}"/><rect class="fn {{ $s['tone'] }}" x="{{ $s['x'] }}" y="{{ $s['y'] }}" width="{{ $s['w'] }}" height="{{ $s['h'] }}" rx="1.5"/><x-class.pin :pin="$s['icon']" :icons="$icons" /><text x="{{ $s['tx'] }}" y="{{ $s['ty'] }}"><tspan class="fl-l">{{ $s['label'] }}</tspan><tspan class="fl-v" dx="9">{{ $s['value'] }}</tspan></text>
@endforeach
<rect class="fn held" x="{{ $f['poolX'] }}" y="{{ $f['poolY'] }}" width="10" height="300" rx="1.5"/><text class="fl-n" x="{{ $f['poolX'] + 5 }}" y="{{ round((float) $f['poolY'] - 13, 1) }}" text-anchor="middle">{{ $f['total'] }} {{ $block->unit }}</text>
</svg></div>
@endif
