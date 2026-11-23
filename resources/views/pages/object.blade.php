<x-layouts.sc :title="$d['name'].': what it gives and how often'" :description="$d['sub'].' What '.$d['name'].' gives in WoW Forever and how often.'">
<div class="wrap">
  <x-page-head :title="$d['name']" :single="true">
    <x-slot:sub>{{ $d['sub'] }}</x-slot:sub>
  </x-page-head>
  <x-figures :items="$d['figures']" :five="true" />
  <x-sheet>
    <x-module span="w12" :first="true" :last="true" id="h-g" heading="What it gives">
      @if ($d['rules'])
      <x-slot:controls><x-seg label="Loot rule" :items="$d['rules']" /></x-slot:controls>
      @endif
      @if ($d['drops'])
      <x-table :rows="$d['drops']" :columns="[['label' => 'Item'], ['label' => 'Drop rate', 'numeric' => true], ['label' => 'Range', 'numeric' => true], ['label' => 'Opened', 'numeric' => true], ['label' => 'Per drop', 'numeric' => true], ['label' => 'Value', 'numeric' => true]]" style="margin-top:1.1rem" />
      @if ($d['worth'])
      <x-loot-worth :worth="$d['worth']" noun="opening" />
      @endif
      @if ($d['tags'] || $d['unread'])
      <p class="note">@foreach ($d['tags'] as $tag)<span class="tag">{{ $tag }}</span> @endforeach @if ($d['unread']){{ $d['unread'] }}@endif</p>
      @endif
      @else
      <x-note>No drops recorded for this object yet.@if ($d['unread']) {{ $d['unread'] }}@endif</x-note>
      @endif
      <p class="note"><a href="/loot">All loot</a></p>
    </x-module>
    <x-module span="w12" :first="true" :last="true" id="h-w" heading="Where it was opened">
      @if ($d['hasMap'])
      <div class="mapbox" style="aspect-ratio:16 / 9"><x-world-canvas id="map" label="Where players stood when they opened this object. Drag to move, scroll to zoom." :spec="$d['map']" /></div>
      @else
      <x-note>No openings are placed on the world map yet.</x-note>
      @endif
    </x-module>
  </x-sheet>
</div>
</x-layouts.sc>
