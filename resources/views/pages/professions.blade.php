<x-layouts.sc title="WoW Forever professions" current="professions" :description="$d['sub']">
<div class="wrap pb-20">
  <x-page-head :title="$d['title']">
    <x-slot:filters><x-scope-switch :choice="$scope['choice']" path="/professions" :names="$scope['names']" :query="$scope['query']" :compare="false" /><x-scope-filters :filters="$filters" /></x-slot:filters>
  </x-page-head>
  @if ($d['groups'] === [])
  <x-note>No characters yet.</x-note>
  @else
  @foreach ($d['groups'] as $group)
  <h2 class="sc-group" id="h-{{ strtolower($group['title']) }}">{{ $group['title'] }}</h2>
  <div class="mt-3">
    @if ($loop->first)<div class="sc-prof sc-label hidden md:grid" aria-hidden="true"><span>Profession</span><span>Characters</span><span>Highest rank</span><span>Skill-ups</span></div>@endif
    @foreach ($group['items'] as $item)
      <a class="sc-prof" href="{{ $item['href'] }}">
        <span class="nm">{{ $item['name'] }}</span>
        <span class="m"><span class="sc-label md:hidden">Characters</span><b>{{ number_format($item['characters']) }}</b><i style="--w:{{ round($item['characters'] / $d['most'] * 100, 1) }}%"></i></span>
        <span class="m"><span class="sc-label md:hidden">Highest rank</span><b>{{ number_format($item['rank']) }} <span class="mut">/ {{ \App\Support\Profession::SKILL_CAP }}</span></b><i style="--w:{{ min(100, round($item['rank'] / \App\Support\Profession::SKILL_CAP * 100, 1)) }}%;--c:var(--dim)"></i></span>
        <span class="m"><span class="sc-label md:hidden">Skill-ups</span><b>{{ number_format($item['ups']) }}</b><i style="--w:{{ round($item['ups'] / $d['ups'] * 100, 1) }}%;--c:var(--dim)"></i></span>
      </a>
    @endforeach
  </div>
  @endforeach
  @endif
  @if ($d['missing'] && $d['groups'])
  <p class="note mt-8">{{ \App\View\Format::TOO_FEW }}: @foreach ($d['missing'] as $item)<a href="{{ $item['href'] }}">{{ $item['name'] }}</a>@if (! $loop->last), @endif @endforeach</p>
  @endif
</div>
</x-layouts.sc>
