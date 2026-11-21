@php
    use App\View\Design\Gallery;
    $f = Gallery::filters();
@endphp
<x-layouts.app title="Marshwalker Greaves: where it drops" current="search" :base="Gallery::BASE">
<div class="wrap">
  <x-page-head title="Marshwalker Greaves" class="q3">
    <x-slot:sub>Item level 28 mail feet. Requires level 23. Binds when equipped. Sells for <x-money :copper="2140" />.</x-slot:sub>
    <x-slot:filters><x-ruleset-filters :rulesets="$f['rulesets']" :servers="$f['servers']" :classes="$f['classes']" /></x-slot:filters>
  </x-page-head>
  <x-figures :items="$d['figures']" />
  <x-sheet>
    <x-module span="w8" :first="true" id="h-s" heading="Where it drops">
      <x-table style="margin-top:1.1rem" :rows="$d['sources']" :columns="[['label' => 'Source'], ['label' => 'Loot rule'], ['label' => 'Drop rate', 'numeric' => true], ['label' => 'Looted', 'numeric' => true], ['label' => 'Dropped', 'numeric' => true]]" />
    </x-module>
    <x-module :last="true" id="h-r" heading="Rolls in groups">
      <x-rank :rows="$d['rolls']" />
    </x-module>
  </x-sheet>
</div>
</x-layouts.app>
