{{-- The survey map as a canvas, drawn by resources/js/charts.js. spec: kind map, box {x0, y0, x1, y1}, cells [[cell x, cell y, level 1 to 4]],
     cell (map units a cell spans, 83 by default), deaths [[x, y]], places. Fills its parent, which sets the size. --}}
@props(['id', 'label', 'spec'])
<canvas id="{{ $id }}" role="img" aria-label="{{ $label }}" data-chart="{{ json_encode($spec) }}"></canvas>
