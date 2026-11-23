@php
    use App\View\Design\Gallery;
    $f = Gallery::filters();
@endphp
<x-layouts.sc title="The Marsh Takes Its Due: turn-ins and levels" current="search" :base="Gallery::BASE">
<div class="wrap">
  <x-page-head title="The Marsh Takes Its Due">
    <x-slot:sub>Level 23 quest in <a href="{{ Gallery::url('zone') }}">Saltmarsh Reach</a>. Pays 2,450 XP and <x-money :copper="1800" />.</x-slot:sub>
    <x-slot:filters><x-ruleset-filters :rulesets="$f['rulesets']" :servers="$f['servers']" :classes="$f['classes']" /></x-slot:filters>
  </x-page-head>
  <x-figures :items="$d['figures']" />
  <x-sheet>
    <x-module span="w7" :first="true" id="h-l" heading="Level when turned in">
      <x-plot id="ql" label="Turn-ins by character level" :spec="$d['levels']" />
    </x-module>
    <x-module span="w5" :last="true" id="h-r" heading="Reward chosen"><x-rank :rows="$d['rewards']" /></x-module>
  </x-sheet>
</div>
</x-layouts.sc>
