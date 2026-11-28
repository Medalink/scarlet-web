<x-layouts.sc title="WoW Forever rulesets compared" current="stats" canonical="/stats/compare" :description="'Characters, hours, time to level '.$target.' and deaths for each ruleset.'">
<div class="wrap">
  <x-page-head title="Rulesets compared">
  </x-page-head>
  <x-sheet>
    <x-module span="w12" :first="true" :last="true" id="h-k" heading="The rulesets">
      <dl class="kinds">@foreach ($d['kinds'] as $kind)<dt @class(['off' => str_contains($kind['name'], 'not out')])>{{ $kind['name'] }}</dt><dd @class(['off' => str_contains($kind['name'], 'not out')])>{{ $kind['summary'] }}</dd>@endforeach</dl>
    </x-module>
    <x-module span="w12" :first="true" :last="true">
      <h2 id="h-tbl" class="vh">Every ruleset</h2>
      @if ($d['rows'])
      <x-table :rows="$d['rows']" :columns="[['label' => 'Ruleset'], ['label' => 'Characters', 'numeric' => true], ['label' => 'Hours', 'numeric' => true], ['label' => 'Median to '.$target, 'numeric' => true], ['label' => 'Deaths an hour', 'numeric' => true], ['label' => 'Reached '.$target, 'numeric' => true], ['label' => 'XP from quests', 'numeric' => true]]" />
      @else
      <x-note>No rulesets recorded yet.</x-note>
      @endif
    </x-module>
    <x-module span="w8" :first="true" id="h-c" heading="Played time to reach each level">
      @if ($d['compare'])
      <x-plot id="cmp" label="Median played hours to each level for the busiest rulesets" :spec="$d['compare']['spec']" />
      <x-key :items="$d['compare']['key']" />
      @else
      <x-note>No ruleset has two levels recorded yet.</x-note>
      @endif
    </x-module>
    <x-module :last="true" id="h-s" heading="Hardcore characters reaching each level">
      @if ($d['survival'])
      <x-plot id="surv" label="Share of hardcore characters that reached each level" :spec="$d['survival']" />
      @else
      <x-note>No hardcore characters recorded yet.</x-note>
      @endif
    </x-module>
  </x-sheet>
</div>
</x-layouts.sc>
