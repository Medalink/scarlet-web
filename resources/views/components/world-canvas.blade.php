{{-- A world map drawn by resources/js/pages/maps.js over the tile pyramid. spec: App\View\Pages\MapSpec::build(). Fills its parent, which sets the size.
     fillsWindow: true only where the map is the page (/map), so the wheel zooms; everywhere else the wheel scrolls the page. --}}
@props(['id', 'label', 'spec', 'fillsWindow' => false])
<canvas id="{{ $id }}" role="img" aria-label="{{ $label }}" data-world-map="{{ json_encode($spec) }}"@if ($fillsWindow) data-fills-window @endif></canvas>
