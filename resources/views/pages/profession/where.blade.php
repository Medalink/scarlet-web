<div class="mapbox" style="aspect-ratio:3/2"><x-world-canvas id="map" :label="'Where '.$w['players'].' '.$w['doing'].' on the world map'.($m['name'] ? ', in '.$m['name'] : '').'. Drag to move, scroll to zoom.'" :spec="$map" /></div>
<p class="note"><a href="/map">Open the world map</a></p>
