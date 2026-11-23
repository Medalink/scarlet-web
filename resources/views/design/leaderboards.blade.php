@php
    use App\View\Design\Gallery;
    $f = Gallery::filters();
@endphp
<x-layouts.app title="Scarlet leaderboards" current="leaderboards" :base="Gallery::BASE">
<div class="wrap">
  <x-page-head title="Fastest to level 60">
    <x-slot:sub>Played time from the first recorded session to level 60. Only characters whose owner made them public, and whose recording covers every level, are listed.</x-slot:sub>
    <x-slot:filters>
      <x-pick label="Board" :options="$d['boards']" />
      <x-ruleset-filters :rulesets="$f['rulesets']" :servers="$f['servers']" :classes="$f['classes']" />
    </x-slot:filters>
  </x-page-head>
  <x-sheet>
    <x-module span="w12" :first="true" :last="true">
      <x-table style="font-size:1rem" :rows="$d['rows']" :columns="[['label' => '', 'class' => 'rk'], ['label' => 'Character'], ['label' => 'Class'], ['label' => 'Played to 60', 'numeric' => true], ['label' => 'Ruleset'], ['label' => 'Deaths', 'numeric' => true], ['label' => 'Reached', 'numeric' => true]]" />
      <x-note>Your character is highlighted when you are signed in. 212 public characters have reached level 60. <a href="#">Next 50</a></x-note>
    </x-module>
  </x-sheet>
</div>
</x-layouts.app>
