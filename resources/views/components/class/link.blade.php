{{-- A link chart: block is App\Support\ClassPage\Link. Nothing is drawn when no pair has a count. --}}
@props(['block', 'icons'])
@php($f = \App\View\ClassPage\LinkLayout::of($block))
@if ($f)
<div class="plot flow"><svg viewBox="0 0 {{ $f['width'] }} {{ $f['height'] }}" role="img" aria-label="{{ $block->label }}">
@foreach ($f['ribbons'] as $r)<path class="fl {{ $r['tone'] }}" d="{{ $r['d'] }}"/>@endforeach
@foreach ($f['left'] as $s)
<rect class="fn {{ $s['tone'] }}" x="{{ $s['x'] }}" y="{{ $s['y'] }}" width="{{ $s['w'] }}" height="{{ $s['h'] }}" rx="1.5"/><x-class.pin :pin="$s['icon']" :icons="$icons" /><text class="fl-l" x="{{ $s['tx'] }}" y="{{ $s['ty'] }}" text-anchor="end">{{ $s['label'] }}</text><text class="fl-v" x="{{ $s['tx'] }}" y="{{ $s['vy'] }}" text-anchor="end">{{ $s['value'] }}</text>
@endforeach
@foreach ($f['right'] as $s)
<rect class="fn {{ $s['tone'] }}" x="{{ $s['x'] }}" y="{{ $s['y'] }}" width="{{ $s['w'] }}" height="{{ $s['h'] }}" rx="1.5"/><x-class.pin :pin="$s['icon']" :icons="$icons" /><text x="{{ $s['tx'] }}" y="{{ $s['ty'] }}"><tspan class="fl-l">{{ $s['label'] }}</tspan><tspan class="fl-v" dx="9">{{ $s['value'] }}</tspan></text>
@endforeach
<text class="fl-n" x="{{ $f['totalX'] }}" y="27" text-anchor="middle">{{ $f['total'] }} {{ $block->unit }}</text>
</svg></div>
@endif
