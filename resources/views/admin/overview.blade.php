<x-layouts.app title="Scarlet admin: totals and growth" :index="false" :brand="false">
<div class="wrap">
  <x-page-head title="Admin">
    <x-slot:sub>{{ $d['sub'] }}</x-slot:sub>
    <x-slot:filters><x-seg label="Admin pages" :items="$d['nav']" /></x-slot:filters>
  </x-page-head>
  <x-figures :items="$d['figures']" />
  <x-sheet>
    <x-module :first="true" id="h-keys" heading="Accounts with an API key">
      <x-parts :parts="$d['keys']" label="Accounts with and without an API key" />
    </x-module>
    <x-module id="h-links" heading="Installs linked to an account">
      <x-parts :parts="$d['links']" label="Installs linked to an account, not linked and revoked" />
    </x-module>
    <x-module :last="true" id="h-vis" heading="Public and private characters">
      <x-parts :parts="$d['visibility']" label="Public and private characters" />
    </x-module>

    <x-module span="w8" :first="true" id="h-new" heading="New accounts, installs and characters per day">
      <x-plot id="new" label="New accounts, new installs and new characters on each of the last 90 days" :spec="$d['new']" />
      <x-key :items="[['label' => 'Accounts', 'class' => 'k-line', 'style' => 'background:var(--accent)'], ['label' => 'Installs', 'class' => 'k-line', 'style' => 'background:var(--fg)'], ['label' => 'Characters', 'class' => 'k-line', 'style' => 'background:var(--s-dungeon)']]" />
    </x-module>
    <x-module :last="true" id="h-active" heading="Installs uploading per day">
      <x-plot id="active" label="Installs with at least one upload on each of the last 90 days" :spec="$d['active']" />
      <dl class="pair">@foreach ($d['activeSpans'] as [$span, $count])<div><dt>{{ $span }}</dt><dd>{{ number_format($count) }}</dd></div>@endforeach</dl>
    </x-module>

    <x-module :first="true" id="h-parts" heading="Parts uploaded per day">
      <x-plot id="parts" label="Parts uploaded on each of the last 90 days" :spec="$d['parts']" />
      <x-note>{{ $d['partsNote'] }}</x-note>
    </x-module>
    <x-module id="h-bytes" heading="Megabytes uploaded per day">
      <x-plot id="bytes" label="Megabytes of compressed parts uploaded on each of the last 90 days" :spec="$d['bytes']" />
      <x-note>{{ $d['bytesNote'] }}</x-note>
    </x-module>
    <x-module :last="true" id="h-decoded" heading="Sessions decoded per day">
      <x-plot id="decoded" label="Sessions decoded on each of the last 90 days" :spec="$d['sessions']" />
      <x-note>{{ $d['sessionsNote'] }}</x-note>
    </x-module>

    <x-module span="w7" :first="true" id="h-class" heading="Characters by class">
@if ($d['classes'] === [])
      <x-note>No characters yet.</x-note>
@else
      <x-rank :rows="$d['classes']" />
@endif
    </x-module>
    <x-module span="w5" :last="true" id="h-levels" heading="Characters by level">
      <x-plot id="levels" label="Characters in each band of ten levels" :spec="$d['levels']" />
      <x-note>{{ $d['levelsNote'] }}</x-note>
    </x-module>

    <x-module span="w5" :first="true" id="h-faction" heading="Characters by faction">
@if ($d['factions'] === [])
      <x-note>No characters yet.</x-note>
@else
      <x-parts :parts="$d['factions']" label="Characters by faction" />
@endif
    </x-module>
    <x-module span="w7" :last="true" id="h-rulesets" heading="Characters by ruleset">
@if ($d['rulesets'] === [])
      <x-note>No session has been decoded yet.</x-note>
@else
      <x-table style="margin-top:1.1rem" :columns="[['label' => 'Ruleset'], ['label' => 'Characters', 'numeric' => true], ['label' => 'Of all characters', 'numeric' => true]]" :rows="$d['rulesets']" />
@endif
    </x-module>
  </x-sheet>
</div>
</x-layouts.app>
