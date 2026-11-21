{{-- A line chart or a bar chart drawn by resources/js/charts.js from `spec`. Line spec: kind line, w, h, x0, x1, y1, yt and xt as [[value, label]],
     series [{cls, pts [[x, y]], band [[x, lo, hi]], mark}], say [[bold, rest]] per point, rest, out (the id of its readout).
     Bar spec: kind bars, the same axes, cls, data [[x, value, lo, hi]], say, rest, out. The label is what a screen reader hears.
     A chart with readout lines (say) and no readout of its own gets one under it, so every chart can be read. --}}
@props(['id', 'label', 'spec'])
@php
    $own = isset($spec['say']) && ! isset($spec['out']);
    if ($own) {
        $spec['out'] = $id.'-out';
    }
    $line = $own ? ($spec['say'][$spec['rest'] ?? count($spec['say']) - 1] ?? null) : null;
@endphp
<div class="plot"><svg id="{{ $id }}" role="img" aria-label="{{ $label }}" data-chart="{{ json_encode($spec) }}"></svg></div>
@if ($line)<x-readout :id="$id.'-out'" :line="$line" />@endif
