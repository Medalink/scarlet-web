<x-layouts.sc title="Scarlet admin: who uses the API" :index="false" :brand="false">
<div class="wrap">
  <x-page-head title="API use">
    <x-slot:sub>{{ $d['sub'] }}</x-slot:sub>
    <x-slot:filters><x-seg label="Admin pages" :items="$d['nav']" /></x-slot:filters>
  </x-page-head>
  <x-figures :items="$d['figures']" />
  <x-sheet>
    <x-module span="w8" :first="true" id="h-public" heading="Public API requests per day">
      <x-plot id="public" label="Requests to the public read API on each of the last 30 days, anonymous and with an API key" :spec="$d['public']" />
      <x-key :items="[['label' => 'Anonymous', 'class' => 'c-s1'], ['label' => 'With an API key', 'class' => 'c-s5']]" />
    </x-module>
    <x-module :last="true" id="h-upload" heading="Upload API requests per day">
      <x-plot id="upload" label="Requests to the upload API on each of the last 30 days" :spec="$d['upload']" />
      <x-note>Every upload request is signed by an app install.</x-note>
    </x-module>

    <x-module :first="true" id="h-addr-day" heading="Addresses per day">
      <x-plot id="addr-day" label="Distinct addresses that called the API on each of the last 30 days" :spec="$d['addresses']" />
      <x-note>{{ $d['addressesNote'] }}</x-note>
    </x-module>
    <x-module id="h-acct-day" heading="Accounts using a key per day">
      <x-plot id="acct-day" label="Distinct accounts whose API key was used on each of the last 30 days" :spec="$d['accounts']" />
      <x-note>{{ $d['accountsNote'] }}</x-note>
    </x-module>
    <x-module :last="true" id="h-inst-day" heading="Installs uploading per day">
      <x-plot id="inst-day" label="Distinct app installs that uploaded on each of the last 30 days" :spec="$d['installs']" />
      <x-note>{{ $d['installsNote'] }}</x-note>
    </x-module>

    <x-module span="w12" :first="true" :last="true" id="h-addresses" heading="Addresses with the most requests">
@if ($d['addressRows'] === [])
      <x-note>No request has reached the API in these days.</x-note>
@else
      <x-table style="margin-top:1.1rem" :columns="[['label' => '#', 'class' => 'rk'], ['label' => 'Address'], ['label' => 'Requests', 'numeric' => true], ['label' => 'Endpoints', 'numeric' => true], ['label' => 'First seen', 'numeric' => true], ['label' => 'Last seen', 'numeric' => true], ['label' => 'Account']]" :rows="$d['addressRows']" />
      <x-note>An IPv6 caller is counted as its /64 prefix.</x-note>
@endif
    </x-module>

    <x-module span="w7" :first="true" id="h-keys" heading="Accounts by API key use">
@if ($d['accountRows'] === [])
      <x-note>No account has made an API key.</x-note>
@else
      <x-table style="margin-top:1.1rem" :columns="[['label' => 'Account'], ['label' => 'Keys', 'numeric' => true], ['label' => 'Requests', 'numeric' => true], ['label' => 'Last used', 'numeric' => true]]" :rows="$d['accountRows']" />
@endif
    </x-module>
    <x-module span="w5" :last="true" id="h-endpoints" heading="Endpoints by requests">
@if ($d['endpoints'] === [])
      <x-note>No request has reached the API in these days.</x-note>
@else
      <x-rank :rows="$d['endpoints']" />
@endif
    </x-module>

    <x-module span="w7" :first="true" id="h-uploaders" heading="Installs that upload the most">
@if ($d['installRows'] === [])
      <x-note>No install has uploaded in these days.</x-note>
@else
      <x-table style="margin-top:1.1rem" :columns="[['label' => 'Install'], ['label' => 'Account'], ['label' => 'Characters'], ['label' => 'Uploaded', 'numeric' => true], ['label' => 'Requests', 'numeric' => true]]" :rows="$d['installRows']" />
@endif
    </x-module>
    <x-module span="w5" :last="true" id="h-looked" heading="Characters looked up most">
@if ($d['characters'] === [])
      <x-note>No character has been asked for by name in these days.</x-note>
@else
      <x-rank :rows="$d['characters']" :plain="true" />
      <x-note>Requests to the public API that named the character and were answered.</x-note>
@endif
    </x-module>
  </x-sheet>
</div>
</x-layouts.sc>
