<x-layouts.sc title="Scarlet admin: ingest" :index="false" :brand="false">
<div class="wrap">
  <x-page-head title="Ingest">
    <x-slot:sub>{{ $d['sub'] }}</x-slot:sub>
    <x-slot:filters><x-seg label="Admin pages" :items="$d['nav']" /></x-slot:filters>
  </x-page-head>
  <x-figures :items="$d['figures']" />
  <x-sheet>
    <x-module span="w8" :first="true" id="h-groups" heading="Parts uploaded per day, by sharing group">
      <x-plot id="groups" label="Parts uploaded on each of the last 30 days, stacked by sharing group" :spec="$d['groups']" />
      <x-key :items="$d['groupKey']" />
    </x-module>
    <x-module :last="true" id="h-queues" heading="Jobs waiting in each queue">
      <x-rank :rows="$d['queues']" />
    </x-module>

    <x-module span="w7" :first="true" id="h-wait" heading="Seconds from upload to decode, per day">
      <x-plot id="wait" label="Median and 90th percentile seconds from a part's upload to its decode, on each of the last 30 days" :spec="$d['wait']" />
      <x-key :items="[['label' => 'Median', 'class' => 'k-line', 'style' => 'background:var(--accent)'], ['label' => 'Nine parts in ten', 'class' => 'k-line', 'style' => 'background:var(--fg)']]" />
      <x-note>{{ $d['waitNote'] }}</x-note>
    </x-module>
    <x-module span="w5" :last="true" id="h-decoded" heading="Sessions decoded per day">
      <x-plot id="decoded" label="Sessions decoded on each of the last 30 days" :spec="$d['decoded']" />
      <x-note>{{ $d['decodedNote'] }}</x-note>
    </x-module>

    <x-module span="w7" :first="true" id="h-when" heading="Uploads by weekday and hour">
      <x-hours-grid id="when" label="Parts uploaded by weekday and hour of day in the last four weeks, in UTC" :spec="$d['hours']['spec']" />
      <x-readout id="when-out" :line="$d['hours']['summary']" />
      <p class="heat-key"><span>Quiet</span><i style="background:rgba(255,255,255,.055)"></i><i style="background:#4b1626"></i><i style="background:#861a34"></i><i style="background:#cf1f45"></i><i style="background:#ff5d7e"></i><span>Busiest</span><span style="margin-left:1rem">Hours are UTC</span></p>
    </x-module>
    <x-module span="w5" :last="true" id="h-today" heading="Installs that uploaded the most today">
@if ($d['today'] === [])
      <x-note>No install has uploaded today.</x-note>
@else
      <x-table style="margin-top:1.1rem" :columns="[['label' => 'Install'], ['label' => 'Account'], ['label' => 'Requests', 'numeric' => true], ['label' => 'Uploaded', 'numeric' => true]]" :rows="$d['today']" />
@endif
    </x-module>

    <x-module span="w7" :first="true" id="h-addon" heading="Sessions by addon version, last 30 days">
@if ($d['addons'] === [])
      <x-note>No session was decoded in the last 30 days.</x-note>
@else
      <x-rank :rows="$d['addons']" />
@endif
    </x-module>
    <x-module span="w5" :last="true" id="h-decoder" heading="Sessions by decoder version, last 30 days">
@if ($d['decoders'] === [])
      <x-note>No session was decoded in the last 30 days.</x-note>
@else
      <x-rank :rows="$d['decoders']" :plain="true" />
@endif
    </x-module>
  </x-sheet>
</div>
</x-layouts.sc>
