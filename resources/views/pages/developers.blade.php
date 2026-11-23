<x-layouts.sc title="Scarlet public API" current="developers" description="Everything public on Scarlet as read-only JSON: statistics, camps, professions, rulesets, leaderboards, search, characters, records and map heat." :brand="false">
<div class="wrap">
  <x-page-head title="Public API" :single="true">
    <x-slot:sub>Everything public on this site is readable as JSON. Read-only, no sign-up, version 1.</x-slot:sub>
  </x-page-head>
  <div class="ref">
    <x-api-nav :groups="$d['nav']" />
    <div>
      <section class="ep" id="use"><h2>Using the API</h2>
        <p>The base is <code>{{ $d['host'] }}/api/v1/public</code>. Answers are JSON in UTF-8, wrapped as <code>{"data": ..., "meta": {...}}</code>. Times are ISO 8601 in UTC, durations are seconds, money is copper. Any origin may call it from a browser. Answers can be cached for {{ $d['maxAge'] / 60 }} minutes, those that name characters (leaderboards, camps, professions, search and characters) for {{ $d['namedMaxAge'] }} seconds, and carry an <code>ETag</code>: send it back as <code>If-None-Match</code> and an unchanged answer is <code>304</code>.</p>
        <x-json>curl {{ $d['host'] }}/api/v1/public/stats</x-json></section>
      <section class="ep" id="limits"><h2>Limits and keys</h2>
        <p>{{ $d['anonymous'] }} requests a minute per IP address without a key; IPv6 addresses count by their /64. A key from your account page raises that to {{ $d['token'] }} for your account, shared by all its keys: any personal access token with the <code>{{ $d['ability'] }}</code> ability, sent as <code>Authorization: Bearer &lt;key&gt;</code>. Over the limit the answer is <code>429</code> with <code>Retry-After</code> in seconds. A key that is unknown, expired or lacks the ability is <code>401 invalid_token</code>, and after {{ $d['badTokens'] }} of those in a minute an address gets <code>429</code>. Scarlet counts requests per day by address, endpoint, key and install, counts each character looked up by name apart from who asked, and keeps those counts {{ $d['keepDays'] }} days.</p></section>
      <section class="ep" id="pages"><h2>Pagination</h2>
        <p>A list answers its rows in <code>data</code> and <code>meta.next_cursor</code>. Pass the cursor back as <code>?cursor=</code> with the same other parameters. A <code>null</code> cursor is the end. <code>limit</code> is 1 to 100, 25 by default. A number a bucket cannot show because fewer than <code>meta.min_characters</code> characters contributed comes back as <code>null</code> or is left out.</p></section>
      <section class="ep" id="errors"><h2>Errors</h2>
        <p>A refusal is <code>{"error": {"code": "...", "message": "..."}}</code> with the HTTP status: <code>404 not_found</code>, <code>422 invalid_request</code> (with <code>fields</code>), <code>401 invalid_token</code>, <code>429 rate_limited</code> (with <code>retry_after</code>). Codes do not change; messages are for people.</p></section>
      @foreach ($d['endpoints'] as $endpoint)
      <x-api-section :id="$endpoint['id']" method="GET" :title="$endpoint['title']" :summary="$endpoint['summary']" :params="$endpoint['params']">
        <p>{{ $endpoint['returns'] }}</p>
        <x-json>{{ $endpoint['curl'] }}</x-json>
      </x-api-section>
      @endforeach
    </div>
  </div>
</div>
</x-layouts.sc>
