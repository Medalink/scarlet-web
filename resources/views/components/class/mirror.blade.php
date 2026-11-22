{{-- A mirror chart: block is App\Support\ClassPage\Mirror. --}}
@props(['block', 'icons'])
@php($m = \App\View\ClassPage\MirrorLayout::of($block))
@if ($m)
<div class="plot taps"><svg viewBox="0 0 {{ $m['width'] }} {{ $m['height'] }}" role="img" aria-label="{{ $block->label }}">
@foreach ($m['grid'] as $g)<line class="{{ $g['axis'] ? 'axis' : 'grid' }}" x1="44" x2="754" y1="{{ $g['y'] }}" y2="{{ $g['y'] }}"/><text x="36" y="{{ $g['ly'] }}" text-anchor="end">{{ $g['label'] }}</text>@endforeach
@foreach ($m['ticks'] as $t)<text x="{{ $t['x'] }}" y="284" text-anchor="middle">{{ $t['label'] }}</text>@endforeach
@foreach ($m['bars'] as $b)<rect @class(['bar', 'up', 'on' => $b['on']]) style="fill:{{ $block->up->hue->css }}" x="{{ $b['x'] }}" y="{{ $b['upY'] }}" width="{{ $b['w'] }}" height="{{ $b['upH'] }}" rx="1.5"/><rect @class(['bar', 'down', 'on' => $b['on']]) style="fill:{{ $block->down->hue->css }}" x="{{ $b['x'] }}" y="{{ $b['downY'] }}" width="{{ $b['w'] }}" height="{{ $b['downH'] }}" rx="1.5"/>@endforeach
<text class="lab" x="{{ $m['peak']['x'] }}" y="{{ $m['peak']['y'] }}" text-anchor="middle">{{ $m['peak']['label'] }}</text>
</svg></div>
<ul class="key"><li><i style="background:{{ $block->up->hue->css }}"></i>{{ $block->up->label }}</li><li><i style="background:{{ $block->down->hue->css }}"></i>{{ $block->down->label }}</li></ul>
@endif
