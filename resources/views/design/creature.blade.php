@php
    use App\View\Design\Gallery;
    $f = Gallery::filters();
@endphp
<x-layouts.sc title="Marsh Stalker: kills, deaths and drops" current="search" :base="Gallery::BASE">
<div class="wrap">
  <x-page-head title="Marsh Stalker">
    <x-slot:sub>Level 22 to 24 beast (crocolisk), normal. Seen in <a href="{{ Gallery::url('zone') }}">Saltmarsh Reach</a>.</x-slot:sub>
    <x-slot:filters><x-ruleset-filters :rulesets="$f['rulesets']" :servers="$f['servers']" :classes="$f['classes']" /></x-slot:filters>
  </x-page-head>
  <x-figures :items="$d['figures']" />
  <x-sheet>
    <x-module span="w7" :first="true" id="h-d" heading="Drops">
      <x-slot:controls><x-seg label="Loot rule" :buttons="true" :items="[['label' => 'Solo', 'on' => true], ['label' => 'Group loot'], ['label' => 'Free for all']]" /></x-slot:controls>
      <x-table style="margin-top:1.1rem" :rows="$d['drops']" :columns="[['label' => 'Item'], ['label' => 'Drop rate', 'numeric' => true], ['label' => 'Corpses', 'numeric' => true], ['label' => 'Dropped', 'numeric' => true]]" />
      <x-note>A drop rate is drops over corpses looted for the first time under that loot rule. Skinning and second looks are not counted.</x-note>
    </x-module>
    <x-module span="w5" :last="true" id="h-w" heading="Where it was met">
      <x-map-box id="map" label="Map cells where players stood when they met this creature" :spec="$d['map']" />
      <x-note>Cells mark where the player stood, not where the creature was.</x-note>
    </x-module>
    <x-module span="w7" :first="true" id="h-l" heading="Deaths to it, by the character's level">
      <x-plot id="dl" label="Deaths to this creature by character level" :spec="$d['deaths']" />
    </x-module>
    <x-module span="w5" :last="true" id="h-h" heading="How it kills">
      <x-rank :rows="$d['how']" />
    </x-module>
  </x-sheet>
</div>
</x-layouts.sc>
