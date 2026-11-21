{{-- The map area of the world page. The drawing is the slot: the survey sheet (x-map-canvas) today, the tile viewer later, so the area
     does not care what draws. Zoom buttons and the credit line sit over it. credit: ['text' => 'Map art ...', 'name' => 'OpenMap', 'href']. --}}
@props(['credit'])
<div class="world-map">{{ $slot }}
  <div class="world-zoom" role="group" aria-label="Zoom"><button class="iconbtn" type="button" aria-label="Zoom in">+</button><button class="iconbtn" type="button" aria-label="Zoom out">&minus;</button></div>
  <p class="world-credit">{{ $credit['text'] }} <a href="{{ $credit['href'] }}">{{ $credit['name'] }}</a>.</p>
</div>
