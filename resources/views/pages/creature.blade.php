<x-layouts.app :title="$d['name'].': kills, drops and where it is found'" :description="$d['sub'].' Kills, deaths, drops and where players met '.$d['name'].' in WoW Forever.'">
<div class="wrap">
  <x-page-head :title="$d['name']">
    <x-slot:sub>{{ $d['sub'] }}</x-slot:sub>
    <x-slot:filters><x-scope-filters :filters="$d['filters']" /></x-slot:filters>
  </x-page-head>
  <x-figures :items="$d['figures']" :five="true" />
  <x-sheet>
    @if ($d['threat'])
    <x-threat :threat="$d['threat']" />
    @endif
    <x-module span="w12" :first="true" :last="true" id="h-d" heading="Drops">
      @if ($d['rules'])
      <x-slot:controls><x-seg label="Loot rule" :items="$d['rules']" /></x-slot:controls>
      @endif
      @if ($d['drops'])
      <x-table :rows="$d['drops']" :columns="[['label' => 'Item'], ['label' => 'Drop rate', 'numeric' => true], ['label' => 'Range', 'numeric' => true], ['label' => 'Corpses', 'numeric' => true], ['label' => 'Per drop', 'numeric' => true], ['label' => 'Value', 'numeric' => true], ...($d['ofKills'] ? [['label' => 'Of all kills', 'numeric' => true]] : [])]" style="margin-top:1.1rem" />
      @if ($d['tags'] || $d['unread'])
      <p class="note">@foreach ($d['tags'] as $tag)<span class="tag">{{ $tag }}</span> @endforeach @if ($d['unread']){{ $d['unread'] }}@endif</p>
      @endif
      @if ($d['dropWorth'])
      <x-loot-worth :worth="$d['dropWorth']" noun="corpse" />
      @endif
      @else
      <x-note>No drops recorded for this creature yet.@if ($d['unread']) {{ $d['unread'] }}@endif</x-note>
      @endif
      <p class="note"><a href="/loot">All loot</a></p>
    </x-module>
    <x-module span="w7" :first="true" id="h-l" heading="Deaths to it, by the character's level">
      @if ($d['byLevel'])
      <x-plot id="by-level" label="Deaths to this creature at each character level" :spec="$d['byLevel']" />
      @else
      <x-note>No character has died to it yet.</x-note>
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
    @if ($d['skinning'])
    <x-module span="w12" :first="true" :last="true" id="h-k" heading="Skinning">
      <x-table :rows="$d['skinning']" :columns="[['label' => 'Item'], ['label' => 'Share of skins', 'numeric' => true], ['label' => 'Range', 'numeric' => true], ['label' => 'Skins', 'numeric' => true], ['label' => 'Value', 'numeric' => true]]" style="margin-top:1.1rem" />
      @if ($d['skinWorth'])
      <x-loot-worth :worth="$d['skinWorth']" noun="skin" />
      @endif
    </x-module>
    @endif
    @if ($d['ruleArms'])
    <x-module span="w12" :first="true" :last="true" id="h-r" :heading="$d['ruleArms']['heading']">
      <x-arms :chart="$d['ruleArms']" />
    </x-module>
    @endif
    @if ($d['sizeArms'])
    <x-module span="w12" :first="true" :last="true" id="h-z" :heading="$d['sizeArms']['heading']">
      <x-arms :chart="$d['sizeArms']" />
    </x-module>
    @endif
  </x-sheet>
</div>
</x-layouts.app>
