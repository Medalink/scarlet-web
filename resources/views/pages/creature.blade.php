<x-layouts.app :title="$d['name'].': kills, drops and where it is found'" :description="$d['sub'].' Kills, deaths, drops and where players met '.$d['name'].' in WoW Forever.'">
<div class="wrap">
  <x-page-head :title="$d['name']">
    <x-slot:sub>{{ $d['sub'] }}</x-slot:sub>
    <x-slot:filters><x-scope-filters :filters="$d['filters']" /></x-slot:filters>
  </x-page-head>
  <x-figures :items="$d['figures']" />
  <x-sheet>
    @if ($d['threat'])
    <x-threat :threat="$d['threat']" />
    @endif
    <x-module span="w7" :first="true" id="h-d" heading="Drops">
      @if ($d['rules'])
      <x-slot:controls><x-seg label="Loot rule" :items="$d['rules']" /></x-slot:controls>
      @endif
      @if ($d['drops'])
      <x-table :rows="$d['drops']" :columns="[['label' => 'Item'], ['label' => 'Drop rate', 'numeric' => true], ['label' => 'Range', 'numeric' => true], ['label' => 'Corpses', 'numeric' => true], ['label' => 'Per drop', 'numeric' => true]]" style="margin-top:1.1rem" />
      <p class="note">@foreach ($d['tags'] as $tag)<span class="tag">{{ $tag }}</span> @endforeach A drop rate is corpses that held the item over corpses looted for the first time. The band is the 95% range; from 10 to 29 corpses it is hollow, and under 10 the row shows its counts only. Drop rates cover every ruleset.@if ($d['skins']) Skinning is counted as looting here, so these rates are off until it is counted apart.@endif</p>
      @else
      <x-note>No drops recorded for this creature yet.</x-note>
      @endif
    </x-module>
    <x-module span="w5" :last="true" id="h-w" heading="Where it was met">
      @if ($d['hasMap'])
      <div class="mapbox" style="aspect-ratio:1 / 1"><x-world-canvas id="map" label="Where players met this creature. Drag to move, scroll to zoom." :spec="$d['map']" /></div>
      @unless ($d['dots'])<x-density-key low="Few" high="Many characters" />@endunless
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
