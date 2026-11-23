@php($base = App\View\Design\Gallery::BASE)
<x-layouts.sc title="Saltmarsh Reach: paths, deaths and levels" current="search" :base="$base">
<x-map-head name="Saltmarsh Reach" min-height="27rem" :facts="$d['facts']" :map="$d['map']" map-label="Heat from every recorded path in Saltmarsh Reach, with deaths as crosses"
  :legend="[['mark' => 'heat', 'label' => 'paths, by how many characters'], ['mark' => 'death', 'label' => 'deaths']]" />
<div class="wrap">
  <x-figures :items="$d['figures']" />
  <x-sheet>
    <x-module :first="true" id="h-k" heading="Most killed"><x-rank :plain="true" :rows="$d['killed']" /></x-module>
    <x-module id="h-d" heading="Deadliest"><x-rank :rows="$d['deadliest']" /></x-module>
    <x-module :last="true" id="h-x" heading="Experience here"><x-rank :rows="$d['xp']" /></x-module>
    <x-module span="w12" :first="true" :last="true" id="h-q" heading="Quests turned in here">
      <x-table style="margin-top:1.1rem" :rows="$d['quests']" :columns="[['label' => 'Quest'], ['label' => 'Turn-ins', 'numeric' => true], ['label' => 'Median level', 'numeric' => true], ['label' => 'XP', 'numeric' => true], ['label' => 'Abandoned', 'numeric' => true]]" />
    </x-module>
  </x-sheet>
</div>
</x-layouts.sc>
