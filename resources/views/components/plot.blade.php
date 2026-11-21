{{-- A line chart or a bar chart drawn by resources/js/charts.js from `spec`. Line spec: kind line, w, h, x0, x1, y1, yt and xt as [[value, label]],
     series [{cls, pts [[x, y]], band [[x, lo, hi]], mark}], say [[bold, rest]] per point, rest, out (the id of its readout).
     Bar spec: kind bars, the same axes, cls, data [[x, value, lo, hi]], say, rest, out. The label is what a screen reader hears. --}}
@props(['id', 'label', 'spec'])
<div class="plot"><svg id="{{ $id }}" role="img" aria-label="{{ $label }}" data-chart="{{ json_encode($spec) }}"></svg></div>
