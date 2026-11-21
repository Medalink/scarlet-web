{{-- The sign-in and link pages: one narrow column in the slot and the survey map beside it. spec is a map-canvas spec without cells. --}}
@props(['mapLabel', 'spec'])
<div class="gate">
  <div class="gate-in">
{{ $slot }}
  </div>
  <div class="stage"><x-map-canvas id="map" :label="$mapLabel" :spec="$spec" /></div>
</div>
