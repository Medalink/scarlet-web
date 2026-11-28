<x-layouts.sc title="Scarlet API" current="developers" description="Scarlet as read-only JSON with an API key: statistics, camps, professions, rulesets, leaderboards, search, characters, records and map heat." :brand="false">
<div class="wrap">
  <x-page-head title="API" :single="true" />
  <div class="ref">
    <x-api-nav :groups="$d['nav']" />
    <div>
      <section class="ep" id="use"><h2>Using the API</h2>
        <p>The base is <code>{{ $d['host'] }}/api/v1/public</code>. Answers are JSON in UTF-8, wrapped as <code>{"data": ..., "meta": {...}}</code>. Times are ISO 8601 in UTC, durations are seconds, money is copper. Every request sends an API key. Any origin may call it from a browser. Answers are private to the caller and can be cached by it for {{ $d['maxAge'] / 60 }} minutes, those that name characters (leaderboards, camps, professions, search and characters) for {{ $d['namedMaxAge'] }} seconds, and carry an <code>ETag</code>: send it back as <code>If-None-Match</code> and an unchanged answer is <code>304</code>.</p>
        <x-json>curl {{ $d['keyHeader'] }} {{ $d['host'] }}/api/v1/public/stats</x-json></section>
      <section class="ep" id="keys"><h2>API keys</h2>
        <p>Sign in with Battle.net, make a key in the API keys section of your <a href="/account/settings#keys">account settings</a> and send it as <code>Authorization: Bearer &lt;key&gt;</code>. A key expires after {{ $d['keyDays'] }} days by default and after {{ $d['keyMaxDays'] }} at most, as you choose when you make it. A key in the query string is not read. Your account may make {{ $d['maxKeys'] }} keys and may send {{ $d['token'] }} requests a minute.</p>
        <x-json>export SCARLET_API_KEY=&lt;key&gt;</x-json></section>
      <section class="ep" id="limits"><h2>Limits</h2>
        <p>{{ $d['token'] }} requests a minute for your account, shared by all its keys: any key with the <code>{{ $d['ability'] }}</code> ability. Over the limit the answer is <code>429</code> with <code>Retry-After</code> in seconds. A request with no key is <code>401 missing_key</code>. A key that is unknown, expired or lacks the ability is <code>401 invalid_token</code>, and after {{ $d['badTokens'] }} of those in a minute an address gets <code>429</code>. Scarlet counts requests per day by address, endpoint, key and install, counts each character looked up by name apart from who asked, and keeps those counts {{ $d['keepDays'] }} days.</p></section>
      <section class="ep" id="pages"><h2>Pagination</h2>
        <p>A list answers its rows in <code>data</code> and <code>meta.next_cursor</code>. Pass the cursor back as <code>?cursor=</code> with the same other parameters. A <code>null</code> cursor is the end. <code>limit</code> is 1 to 100, 25 by default. A number a bucket cannot show because fewer than <code>meta.min_characters</code> characters contributed comes back as <code>null</code> or is left out.</p></section>
      <section class="ep" id="errors"><h2>Errors</h2>
        <p>A refusal is <code>{"error": {"code": "...", "message": "..."}}</code> with the HTTP status: <code>404 not_found</code>, <code>422 invalid_request</code> (with <code>fields</code>), <code>401 missing_key</code>, <code>401 invalid_token</code>, <code>429 rate_limited</code> (with <code>retry_after</code>). Codes do not change; messages are for people.</p></section>
      @foreach ($d['endpoints'] as $endpoint)
      <x-api-section :id="$endpoint['id']" method="GET" :title="$endpoint['title']" :summary="$endpoint['summary']" :params="$endpoint['params']">
        @if ($endpoint['long'])
        <details class="fields"><summary>Response fields</summary><p>{{ $endpoint['returns'] }}</p></details>
        @else
        <p>{{ $endpoint['returns'] }}</p>
        @endif
        <x-json>{{ $endpoint['curl'] }}</x-json>
        @if ($endpoint['example'])
        <details class="fields" data-example><summary>Example response</summary><x-json>{{ $endpoint['example'] }}</x-json></details>
        @endif
      </x-api-section>
      @endforeach
    </div>
  </div>
</div>
</x-layouts.sc>
