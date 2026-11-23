<x-layouts.app title="Your characters" current="" :signed-in="true" :index="false">
<div class="wrap">
  <x-page-head title="Your characters">
    <x-slot:filters><x-btn kind="quiet" href="/account/professions">Your professions</x-btn> <x-btn kind="quiet" href="/account/camps">Your camps</x-btn> <x-btn kind="quiet" href="/account">Account</x-btn></x-slot:filters>
  </x-page-head>
@if ($d['empty'])
  <x-note>No characters uploaded yet.</x-note>
@else
  <x-tally :items="$d['game']['tally']" />
  <x-sheet>
    <x-module span="w12" :first="true" :last="true" id="h-characters" heading="Characters">
      <x-table style="margin-top:1.1rem" :columns="$d['table']['columns']" :rows="$d['table']['rows']" />
    </x-module>
@if ($d['game']['records'])
    <x-module span="w12" :first="true" :last="true" id="h-records" heading="Your records">
      <x-rec-grid :items="$d['game']['records']" />
    </x-module>
@endif
@if ($d['game']['rules'])
    <x-module span="w12" :first="true" :last="true" id="h-rules" heading="The next round numbers">
      <x-rules :rows="$d['game']['rules']" />
    </x-module>
@endif
    <x-module span="w12" :first="true" :last="true" id="h-days" heading="Play time by day">
@if ($d['days'])
      <x-plot id="days" label="Hours played each day, stacked by character" :spec="$d['days']['spec']" />
      <x-key :items="$d['days']['key']" />
@else
      <x-note>No play time recorded yet.</x-note>
@endif
    </x-module>
    <x-module span="w12" :first="true" :last="true" id="h-gold" heading="Gold">
@if ($d['gold'])
      <x-table style="margin-top:1.1rem" :columns="$d['gold']['columns']" :rows="$d['gold']['rows']" />
@if ($d['gold']['spec'])
      <h3 class="chart-name">All characters together</h3>
      <x-plot id="gold" label="Gold of all characters together at the end of each day with a session" :spec="$d['gold']['spec']" />
@endif
@else
      <x-note>No gold recorded yet.</x-note>
@endif
    </x-module>
@if ($d['imported'])
    <x-module span="w12" :first="true" :last="true" id="h-imported" heading="Imported history">
      <x-table style="margin-top:1.1rem" :columns="$d['imported']['columns']" :rows="$d['imported']['rows']" />
    </x-module>
@endif
@foreach ($d['references'] as $i => $reference)
    <x-module span="w12" :first="true" :last="true" id="h-reference-{{ $i }}" :heading="$reference['heading']">
      <x-table style="margin-top:1.1rem" :columns="$reference['table']['columns']" :rows="$reference['table']['rows']" />
@foreach ($reference['notes'] as $note)
      <x-note>{{ $note }}</x-note>
@endforeach
    </x-module>
@endforeach
    <x-module span="w12" :first="true" :last="true" id="h-together" heading="Played together">
@if ($d['together'])
      <x-table style="margin-top:1.1rem" :columns="$d['together']['columns']" :rows="$d['together']['rows']" />
@else
      <x-note>No two characters were online at the same time.</x-note>
@endif
    </x-module>
    <x-module span="w12" :first="true" :last="true" id="h-levels" heading="Time in each level">
@forelse ($d['levels'] as $chart)
      <h3 class="chart-name">{{ $chart['name'] }}</h3>
      <x-plot :id="$chart['id']" label="Hours {{ $chart['name'] }} spent in each completed level, with the ruleset median as a tick where at least two characters completed it." :spec="$chart['spec']" />
      <x-key :items="$chart['key']" />
@empty
      <x-note>No character has completed two levels yet.</x-note>
@endforelse
    </x-module>
    <x-module span="w12" :first="true" :last="true" id="h-professions" heading="Professions">
@if ($d['professions'])
      <x-table style="margin-top:1.1rem" :columns="$d['professions']['columns']" :rows="$d['professions']['rows']" />
@else
      <x-note>No profession skill recorded yet.</x-note>
@endif
    </x-module>
  </x-sheet>
@endif
</div>
</x-layouts.app>
