@php($base = App\View\Design\Gallery::BASE)
<x-layouts.app title="Scarlet rulesets compared" current="stats" :base="$base">
<div class="wrap">
  <x-page-head title="Rulesets compared">
    <x-slot:sub>A ruleset is a permanent choice for each character. Each row links to its own dashboard.</x-slot:sub>
  </x-page-head>
  <x-sheet>
    <x-module span="w12" :first="true" :last="true">
      <h2 id="h-tbl" class="vh">Every ruleset</h2>
      <x-table :rows="$d['rows']" :columns="[['label' => 'Ruleset'], ['label' => 'Characters', 'numeric' => true], ['label' => 'Hours', 'numeric' => true], ['label' => 'Median to 60', 'numeric' => true], ['label' => 'Deaths an hour', 'numeric' => true], ['label' => 'Reached 60', 'numeric' => true], ['label' => 'XP from quests', 'numeric' => true]]" />
    </x-module>
    <x-module span="w8" :first="true" id="h-c" heading="Played time to reach each level">
      <x-plot id="cmp" label="Median played hours to each level for three rulesets" :spec="$d['compare']" />
      <x-key :items="[['label' => 'Normal', 'class' => 'k-line', 'style' => 'background:var(--accent)'], ['label' => 'Roleplay', 'class' => 'k-line', 'style' => 'background:var(--fg)'], ['label' => 'PvP', 'class' => 'k-line', 'style' => 'background:var(--s-dungeon)']]" />
    </x-module>
    <x-module :last="true" id="h-s" heading="Hardcore characters still alive">
      <x-plot id="surv" label="Share of hardcore characters that reached each level without dying" :spec="$d['survival']" />
      <x-note>Half of hardcore characters die before level 19.</x-note>
    </x-module>
  </x-sheet>
</div>
</x-layouts.app>
