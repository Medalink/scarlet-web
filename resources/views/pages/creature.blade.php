<x-layouts.app :title="'Scarlet creature: '.$d['name']" current="search" description="Kills, deaths, drops and where players met {{ $d['name'] }}.">
<div class="wrap">
  <x-page-head :title="$d['name']">
    <x-slot:sub>{{ $d['sub'] }}</x-slot:sub>
    <x-slot:filters><x-scope-filters :filters="$d['filters']" /></x-slot:filters>
  </x-page-head>
  <x-figures :items="$d['figures']" />
  <x-sheet>
    <x-module span="w7" :first="true" id="h-d" heading="Drops">
      @if ($d['rules'])
      <x-slot:controls><x-seg label="Loot rule" :items="$d['rules']" /></x-slot:controls>
      @endif
      @if ($d['drops'])
      <x-table :rows="$d['drops']" :columns="[['label' => 'Item'], ['label' => 'Drop rate', 'numeric' => true], ['label' => 'Corpses', 'numeric' => true], ['label' => 'Dropped', 'numeric' => true]]" style="margin-top:1.1rem" />
      <p class="note">A drop rate is drops over corpses looted for the first time under that loot rule. Skinning and second looks are not counted. Drop rates cover every ruleset.</p>
      @else
      <x-note>No drops recorded for this creature yet.</x-note>
      @endif
    </x-module>
    <x-module span="w5" :last="true" id="h-w" heading="Where it was met">
      @if ($d['hasMap'])
      <div class="mapbox" style="aspect-ratio:1 / 1"><x-world-canvas id="map" label="Map cells where players stood when they met this creature. Drag to move, scroll to zoom." :spec="$d['map']" /></div>
      <x-heat-key low="Few" high="Many characters" />
      <p class="note">Cells mark where the player stood, not where the creature was.</p>
      @else
      <x-note>No sightings are placed on the world map yet.</x-note>
      @endif
    </x-module>
    <x-module span="w7" :first="true" :last="true" id="h-l" heading="Deaths to it, by the character's level">
      @if ($d['byLevel'])
      <x-plot id="by-level" label="Deaths to this creature at each character level" :spec="$d['byLevel']" />
      @else
      <x-note>No character has died to it yet.</x-note>
      @endif
    </x-module>
  </x-sheet>
</div>
</x-layouts.app>
