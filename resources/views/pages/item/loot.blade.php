@if ($loot['holds'])
<x-module span="w12" :first="true" :last="true" id="h-h" heading="What it holds">
  <x-table :rows="$loot['holds']" :columns="[['label' => 'Item'], ['label' => 'Drop rate', 'numeric' => true], ['label' => 'Range', 'numeric' => true], ['label' => 'Opened', 'numeric' => true], ['label' => 'Per drop', 'numeric' => true]]" style="margin-top:1.1rem" /></x-module>
@endif
@php($both = $loot['zones'] && $loot['hasMap'])
@if ($loot['zones'])
<x-module :span="$both ? null : 'w12'" :first="true" :last="! $both" id="h-z" heading="By zone">
  <x-table :rows="$loot['zones']" :columns="[['label' => 'Zone'], ['label' => 'Dropped', 'numeric' => true], ['label' => 'Per 100 corpses', 'numeric' => true]]" style="margin-top:1.1rem" />
  <p class="note">Per 100 corpses is out of every creature looted in the zone.</p>
</x-module>
@endif
@if ($loot['hasMap'])
<x-module :span="$both ? 'w8' : 'w12'" :first="! $both" :last="true" id="h-w" heading="Where it was looted">
  <div class="mapbox" style="aspect-ratio:16 / 9"><x-world-canvas id="map" label="Where players stood when they looted this item. Drag to move, scroll to zoom." :spec="$loot['map']" /></div>
  <p class="note">Marks show where the player stood, not where the creature or object was.</p>
</x-module>
@endif
