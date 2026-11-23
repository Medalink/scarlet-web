<x-layouts.sc title="WoW Forever professions" current="professions" :description="$d['sub']">
<div class="wrap pb-20">
  <x-page-head :title="$d['title']">
    <x-slot:sub>{{ $d['sub'] }}</x-slot:sub>
    <x-slot:filters><x-scope-switch :choice="$scope['choice']" path="/professions" :names="$scope['names']" :query="$scope['query']" :compare="false" /><x-scope-filters :filters="$filters" /></x-slot:filters>
  </x-page-head>
  @php
    $most = max(1, ...array_map(fn (array $item): int => $item['characters'] ?? 0, $d['items']));
    $ups = max(1, ...array_map(fn (array $item): int => $item['ups'] ?? 0, $d['items']));
  @endphp
  <h2 class="vh" id="h-professions">Professions</h2>
  <div class="mt-4">
    <div class="sc-prof sc-label hidden md:grid" aria-hidden="true"><span>Profession</span><span>Characters</span><span>Highest rank</span><span>Skill-ups</span></div>
    @foreach ($d['items'] as $item)
      <a class="sc-prof" href="{{ $item['href'] }}">
        <span class="nm">{{ $item['name'] }}</span>
        @if ($item['characters'] === null)
          <span class="sc-label md:col-span-3 col-span-2">{{ \App\View\Format::TOO_FEW }}</span>
        @else
          <span class="m"><span class="sc-label md:hidden">Characters</span><b>{{ number_format($item['characters']) }}</b><i style="--w:{{ round($item['characters'] / $most * 100, 1) }}%"></i></span>
          <span class="m"><span class="sc-label md:hidden">Highest rank</span><b>{{ number_format($item['rank']) }}</b><i style="--w:{{ min(100, round($item['rank'] / 300 * 100, 1)) }}%;--c:var(--dim)"></i></span>
          <span class="m"><span class="sc-label md:hidden">Skill-ups</span><b>{{ number_format($item['ups']) }}</b><i style="--w:{{ round($item['ups'] / $ups * 100, 1) }}%;--c:var(--dim)"></i></span>
        @endif
      </a>
    @endforeach
  </div>
</div>
</x-layouts.sc>
