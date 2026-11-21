{{-- A world map drawn by resources/js/pages/maps.js over the tile pyramid. spec: App\View\Pages\MapSpec::build(). Fills its parent, which sets the size. --}}
@props(['id', 'label', 'spec'])
<canvas id="{{ $id }}" role="img" aria-label="{{ $label }}" data-world-map="{{ json_encode($spec) }}"></canvas>
