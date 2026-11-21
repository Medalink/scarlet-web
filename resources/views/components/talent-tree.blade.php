{{-- A talent tree drawn from its layout. tree: App\View\TalentTree::geometry(). A node's class is off, part, full or h1 to h4 (heat). --}}
@props(['tree', 'label'])
<svg {{ $attributes->class(['tree']) }} viewBox="{{ $tree['box'] }}" role="img" aria-label="{{ $label }}" style="--r:{{ $tree['r'] }}px">
@foreach ($tree['edges'] as $edge)<line @class(['edge', 'on' => $edge['on']]) x1="{{ $edge['x1'] }}" y1="{{ $edge['y1'] }}" x2="{{ $edge['x2'] }}" y2="{{ $edge['y2'] }}" />@endforeach
@foreach ($tree['headings'] as $heading)<text class="head" x="{{ $heading['x'] }}" y="{{ $heading['y'] }}" text-anchor="middle" font-size="{{ round($tree['r'] * 1.15, 2) }}">{{ $heading['text'] }}</text>@endforeach
@foreach ($tree['nodes'] as $node)<g class="node {{ $node['class'] }}"><title>{{ $node['title'] }}</title><circle cx="{{ $node['x'] }}" cy="{{ $node['y'] }}" r="{{ $tree['r'] }}" />@if ($node['text'] !== '')<text x="{{ $node['x'] }}" y="{{ $node['y'] }}" text-anchor="middle" dominant-baseline="central" font-size="{{ round($tree['r'] * 0.9, 2) }}">{{ $node['text'] }}</text>@endif</g>@endforeach
</svg>
