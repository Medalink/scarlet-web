@if ($loot['holds'])
<x-module span="w12" :first="true" :last="true" id="h-h" heading="What it holds">
  <x-table :rows="$loot['holds']" :columns="[['label' => 'Item'], ['label' => 'Drop rate', 'numeric' => true], ['label' => 'Range', 'numeric' => true], ['label' => 'Opened', 'numeric' => true], ['label' => 'Per drop', 'numeric' => true]]" style="margin-top:1.1rem" />
  <p class="note">A drop rate is times opened that held the item over times opened for the first time. The band is the 95% range; from 10 to 29 openings it is hollow, and under 10 the row shows its counts only. Drop rates cover every ruleset.</p>
</x-module>
@endif
@php($both = $loot['zones'] && $loot['hasMap'])
@if ($loot['zones'])
<x-module :span="$both ? null : 'w12'" :first="true" :last="! $both" id="h-z" heading="By zone">
  <x-table :rows="$loot['zones']" :columns="[['label' => 'Zone'], ['label' => 'Dropped', 'numeric' => true], ['label' => 'Per 100 corpses', 'numeric' => true]]" style="margin-top:1.1rem" />
  <p class="note">Creatures looted in the zone, whatever they were. Per 100 corpses is how many of every 100 corpses looted there held the item. Under 10 corpses the row shows its counts only.</p>
</x-module>
@endif
@if ($loot['hasMap'])
<x-module :span="$both ? 'w8' : 'w12'" :first="! $both" :last="true" id="h-w" heading="Where it was looted">
  <div class="mapbox" style="aspect-ratio:16 / 9"><x-world-canvas id="map" label="Where players stood when they looted this item. Drag to move, scroll to zoom." :spec="$loot['map']" /></div>
  <p class="note">Marks show where the player stood, not where the creature or object was.</p>
</x-module>
@endif
