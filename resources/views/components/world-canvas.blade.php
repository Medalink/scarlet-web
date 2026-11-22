{{-- A world map drawn by resources/js/pages/maps.js over the tile pyramid. spec: App\View\Pages\MapSpec::build(). Fills its parent, which sets the size.
     behindHead: true where the map lies behind a page head (a character, a zone): the wheel scrolls the page there until the map is
     clicked. Everywhere else the wheel zooms. fillsWindow is no longer read and goes in the next change. --}}
@props(['id', 'label', 'spec', 'fillsWindow' => false, 'behindHead' => false])
<canvas id="{{ $id }}" role="img" aria-label="{{ $label }}" data-world-map="{{ json_encode($spec) }}"@if ($behindHead) data-behind-head @endif></canvas>
