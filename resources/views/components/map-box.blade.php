{{-- A map in a framed box inside a module. ratio is the CSS aspect ratio, 3 / 2 by default. --}}
@props(['id', 'label', 'spec', 'ratio' => null])
<div class="mapbox"@if ($ratio) style="aspect-ratio:{{ $ratio }}"@endif><x-map-canvas :id="$id" :label="$label" :spec="$spec" /></div>
