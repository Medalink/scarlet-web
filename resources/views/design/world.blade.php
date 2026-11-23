@php($base = App\View\Design\Gallery::BASE)
<x-layouts.sc title="Scarlet world map: where characters go and die" current="map" :base="$base">
<x-world>
  <x-slot:map>
    <x-world-map :credit="['text' => 'Map art © Blizzard Entertainment. Map by', 'name' => 'OpenMap', 'href' => '#']">
      <x-map-canvas id="map" label="The world map with heat from every recorded path. Drag to move, scroll to zoom." :spec="$d['map']" />
    </x-world-map>
  </x-slot:map>
  <x-world-side title="World map" sub="Every path, death and kill that players chose to upload, on one map. Zoom into a zone to open it." :controls="$d['controls']"
    :key="['low' => 'Few', 'high' => 'Many characters']" :line="$d['line']" zones-title="Busiest zones" :zones="$d['zones']" />
</x-world>
</x-layouts.sc>
