@php($base = App\View\Design\Gallery::BASE)
<x-layouts.sc title="Scarlet API reference" current="developers" :base="$base">
<div class="wrap">
  <x-page-head title="API">
    <x-slot:sub>Everything public on this site is readable as JSON with an API key. Read-only, version 1.</x-slot:sub>
  </x-page-head>
  <div class="ref">
    <x-api-nav :groups="$d['nav']" />
    <div>
      <x-api-section id="use" title="Using the API"><p>The base is <code>https://&lt;host&gt;/api/v1/public</code>. Answers are JSON in UTF-8. Times are ISO 8601 in UTC, durations are seconds, money is copper. Every request sends an API key. Any origin may call it from a browser. Answers are private and carry an <code>ETag</code>.</p>
        <x-json>curl -H "Authorization: Bearer $SCARLET_API_KEY" https://&lt;host&gt;/api/v1/public/stats?ruleset=3</x-json></x-api-section>
      <x-api-section id="limits" title="Limits and keys"><p>Sign in with Battle.net and make a key on your account page. It expires after 30 days by default, 90 at most. Send it as <code>Authorization: Bearer &lt;key&gt;</code>. 600 requests a minute per account. Over the limit the answer is <code>429</code> with <code>Retry-After</code>.</p></x-api-section>
      <x-api-section id="pages" title="Pagination"><p>Lists return <code>data</code> and <code>next_cursor</code>. Pass it back as <code>?cursor=</code>. A null cursor is the end. Numbers a bucket cannot show because too few characters contributed come back as <code>null</code>.</p></x-api-section>
      @foreach ($d['endpoints'] as $endpoint)
      <x-api-section :id="$endpoint['id']" :method="$endpoint['method']" :title="$endpoint['path']" :summary="$endpoint['summary']" :params="$endpoint['params']">@if ($endpoint['short'] === '/stats')<x-json>{
  <b>"characters"</b>: 1284,
  <b>"played_seconds"</b>: 150865200,
  <b>"levels"</b>: [{ <b>"level"</b>: 40, <b>"median_seconds"</b>: 220800, <b>"p25"</b>: 172800, <b>"p75"</b>: 284400, <b>"characters"</b>: 311 }],
  <b>"updated_at"</b>: <i>"2026-11-20T21:08:00Z"</i>
}</x-json>@endif</x-api-section>
      @endforeach
    </div>
  </div>
</div>
</x-layouts.sc>
