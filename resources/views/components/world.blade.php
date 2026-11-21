{{-- The world map page: the map fills the window under the header and the panel sits on its right. Slots: `map` (use x-world-map)
     and the default slot for the panel (use x-world-side). --}}
<section class="world" aria-labelledby="h1">
  {{ $map }}
  {{ $slot }}
</section>
