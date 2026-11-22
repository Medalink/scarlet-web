{{-- Rows with a bar each: block is App\Support\ClassPage\Rank. A row with no icon shows an empty square when another row has one. --}}
@props(['block', 'icons'])
@php
    $any = array_filter(array_map(fn ($row) => $row->icon, $block->rows)) !== [];
    $full = $block->of ?? max([0, ...array_map(fn ($row) => $row->value, $block->rows)]);
@endphp
<ol @class(['rank', 'plain' => $block->plain])>@foreach ($block->rows as $row)
@php
    $share = $row->share ?? ($full > 0 ? $row->value / $full : 0);
    $tone = $row->tone;
@endphp
<li><span class="who"><x-class.icon :icon="$row->icon" :icons="$icons" :any="$any" />{{ $row->label }}@if ($row->aside) <small class="pet">{{ $row->aside }}</small>@endif</span><span class="val">{{ $block->unit->row($row->value) }}</span><span class="track"><i @if ($tone instanceof \App\Support\ClassPage\Tone && $tone !== \App\Support\ClassPage\Tone::Scarlet) class="{{ $tone === \App\Support\ClassPage\Tone::Own ? 'cl' : $tone->value }}"@endif style="width:{{ number_format(max(1, min(100, $share * 100)), 1, '.', '') }}%@if ($tone instanceof \App\Support\ClassPage\Hue);background:{{ $tone->css }}@endif"></i></span></li>
@endforeach</ol>
