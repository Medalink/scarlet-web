<x-layouts.app title="Scarlet admin: the database" :index="false" :brand="false">
<div class="wrap">
  <x-page-head title="Database">
    <x-slot:sub>{{ $d['sub'] }}</x-slot:sub>
    <x-slot:filters><x-seg label="Admin pages" :items="$d['nav']" /></x-slot:filters>
  </x-page-head>
  <x-figures :items="$d['figures']" />
  <x-sheet>
    <x-module :first="true" id="h-conn" heading="Connections by state">
      <x-rank :rows="$d['connections']" />
@if ($d['longest'] > 0)
      <x-note>The longest running statement has run {{ number_format($d['longest'], 1) }}s.</x-note>
@endif
    </x-module>
    <x-module id="h-cache" heading="Block reads served from cache">
      <x-rank :rows="$d['cache']" :plain="true" />
    </x-module>
    <x-module :last="true" id="h-packed" heading="Bytes before and after compression">
@if ($d['packed'] === [])
      <x-note>No chunk is compressed yet.</x-note>
@else
      <x-rank :rows="$d['packed']" />
      <x-note>Compressed chunks only.</x-note>
@endif
    </x-module>

    <x-module span="w7" :first="true" id="h-size" heading="Size by table">
      <x-key :items="[['label' => 'Heap', 'class' => 'c-s1'], ['label' => 'Indexes', 'class' => 'c-s5'], ['label' => 'TOAST', 'class' => 'c-s2']]" />
      <x-table style="margin-top:1.1rem" :columns="[['label' => 'Table'], ['label' => 'Heap, indexes and TOAST as one bar', 'hide' => true], ['label' => 'Size', 'numeric' => true], ['label' => 'Heap', 'numeric' => true], ['label' => 'Indexes', 'numeric' => true], ['label' => 'TOAST', 'numeric' => true]]" :rows="$d['sizes']" />
@if ($d['sizesNote'])
      <x-note>{{ $d['sizesNote'] }}</x-note>
@endif
    </x-module>
    <x-module span="w5" :last="true" id="h-rows" heading="Estimated rows by table">
      <x-rank :rows="$d['rows']" :plain="true" />
    </x-module>

    <x-module span="w12" :first="true" :last="true" id="h-hyper" heading="Hypertables">
      <x-table style="margin-top:1.1rem" :columns="[['label' => 'Hypertable'], ['label' => 'Chunks', 'numeric' => true], ['label' => 'Compressed chunks', 'numeric' => true], ['label' => 'Before compression', 'numeric' => true], ['label' => 'After compression', 'numeric' => true], ['label' => 'Ratio', 'numeric' => true]]" :rows="$d['hypertables']" />
@if ($d['hypertablesNote'])
      <x-note>{{ $d['hypertablesNote'] }}</x-note>
@endif
    </x-module>

    <x-module span="w12" :first="true" :last="true" id="h-jobs" heading="Continuous aggregates and background jobs">
      <x-table style="margin-top:1.1rem" :columns="[['label' => 'Name'], ['label' => 'Kind'], ['label' => 'Schedule'], ['label' => 'Last run', 'numeric' => true], ['label' => 'Last status'], ['label' => 'Run time', 'numeric' => true], ['label' => 'Next run', 'numeric' => true]]" :rows="$d['jobs']" />
      <x-note>{{ $d['jobsNote'] }}</x-note>
    </x-module>

    <x-module span="w5" :first="true" id="h-scans" heading="Scans per index">
      <x-rank :rows="$d['scans']" />
      <x-note>Scans are counted since the statistics were last reset. Hypertable indexes live on their chunks and are left out.</x-note>
    </x-module>
    <x-module span="w7" :last="true" id="h-idle" heading="Indexes with no scans">
@if ($d['unused'] === [])
      <x-note>Every index that enforces nothing has been scanned.</x-note>
@else
      <x-rank :rows="$d['unused']" :plain="true" />
      <x-note>Unique indexes are left out: they enforce a constraint whether or not a query scans them.</x-note>
@endif
    </x-module>

    <x-module span="w7" :first="true" id="h-vacuum" heading="Dead rows and the last autovacuum">
      <x-table style="margin-top:1.1rem" :columns="[['label' => 'Table'], ['label' => 'Rows, estimated', 'numeric' => true], ['label' => 'Dead rows', 'numeric' => true], ['label' => 'Dead share', 'numeric' => true], ['label' => 'Last autovacuum', 'numeric' => true], ['label' => 'Last autoanalyze', 'numeric' => true]]" :rows="$d['vacuum']" />
    </x-module>
    <x-module span="w5" :last="true" id="h-counters" heading="Counters since the last reset">
      <dl class="pair one">@foreach ($d['counters'] as [$label, $value])<div><dt>{{ $label }}</dt><dd style="font-size:1.4rem">{{ $value }}</dd></div>@endforeach</dl>
    </x-module>
  </x-sheet>
</div>
</x-layouts.app>
