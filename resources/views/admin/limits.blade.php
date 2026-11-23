<x-layouts.sc title="Scarlet admin: limits" :index="false" :brand="false">
<div class="wrap">
  <x-page-head title="Limits">
    <x-slot:sub>{{ $d['sub'] }}</x-slot:sub>
    <x-slot:filters><x-seg label="Admin pages" :items="$d['nav']" /></x-slot:filters>
  </x-page-head>
  <x-figures :items="$d['figures']" />
  <x-sheet>
    <x-module span="w8" :first="true" id="h-refusals" heading="Refused requests per day">
      <x-plot id="refusals" label="Requests answered 429 and requests refused otherwise on each of the last 30 days" :spec="$d['refusals']" />
      <x-key :items="[['label' => 'Answered 429', 'class' => 'c-s1'], ['label' => 'Refused otherwise', 'class' => 'c-s5']]" />
      <x-note>{{ $d['refusalsNote'] }}</x-note>
    </x-module>
    <x-module :last="true" id="h-requests" heading="Requests per day">
      <x-plot id="requests" label="Requests to the API on each of the last 30 days" :spec="$d['requests']" />
      <x-note>{{ $d['requestsNote'] }}</x-note>
    </x-module>

    <x-module span="w12" :first="true" :last="true" id="h-busiest" heading="Busiest addresses today">
@if ($d['busiest'] === [])
      <x-note>No request has reached the API today.</x-note>
@else
      <x-table style="margin-top:1.1rem" :columns="[['label' => 'Address'], ['label' => 'Requests', 'numeric' => true], ['label' => 'Average a minute', 'numeric' => true], ['label' => 'Answered 429', 'numeric' => true], ['label' => 'Refused', 'numeric' => true], ['label' => 'Account'], ['label' => 'First', 'numeric' => true], ['label' => 'Last', 'numeric' => true]]" :rows="$d['busiest']" />
      <x-note>The average runs from the address's first request today to its last. A highlighted row averages at least the keyless public limit.</x-note>
@endif
    </x-module>

    <x-module span="w12" :first="true" :last="true" id="h-limited" heading="Addresses answered 429 most, last 30 days">
@if ($d['limited'] === [])
      <x-note>No request was answered 429 in the last 30 days.</x-note>
@else
      <x-table style="margin-top:1.1rem" :columns="[['label' => 'Address'], ['label' => 'Answered 429', 'numeric' => true], ['label' => 'Refused', 'numeric' => true], ['label' => 'Requests', 'numeric' => true], ['label' => 'Endpoints limited', 'numeric' => true], ['label' => 'Account'], ['label' => 'Last request', 'numeric' => true]]" :rows="$d['limited']" />
@endif
    </x-module>

    <x-module span="w7" :first="true" id="h-uploads" heading="Upload bytes today against the daily cap">
@if ($d['uploads'] === [])
      <x-note>No install has uploaded today.</x-note>
@else
      <x-table style="margin-top:1.1rem" :columns="[['label' => 'Install'], ['label' => 'Name'], ['label' => 'Account'], ['label' => 'Uploaded', 'numeric' => true], ['label' => 'Of the cap', 'numeric' => true], ['label' => 'Requests', 'numeric' => true]]" :rows="$d['uploads']" />
@endif
    </x-module>
    <x-module span="w5" :last="true" id="h-budgets" heading="Limits in force">
      <dl class="pair one">@foreach ($d['budgets'] as [$label, $value])<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endforeach</dl>
    </x-module>

    <x-module span="w12" :first="true" :last="true" id="h-characters" heading="Installs with the most characters">
@if ($d['characters'] === [])
      <x-note>No install has uploaded a character yet.</x-note>
@else
      <x-table style="margin-top:1.1rem" :columns="[['label' => 'Install'], ['label' => 'Name'], ['label' => 'Account'], ['label' => 'Characters', 'numeric' => true]]" :rows="$d['characters']" />
@endif
    </x-module>
  </x-sheet>
</div>
</x-layouts.sc>
