{{-- The page frame of the web app: the comps' header, the page, the footer. `base` puts every link under a
     prefix, which the design gallery uses to stay inside /design. --}}
@props(['title', 'description' => null, 'current' => '', 'signedIn' => false, 'base' => '', 'canonical' => null, 'index' => true, 'brand' => true])
@php
    $signedIn = $signedIn || auth()->check();
    $items = ['stats' => ['Stats', '/stats'], 'world' => ['Map', '/map'], 'camps' => ['Camps', '/camps'], 'rulesets' => ['Rulesets', '/rulesets'], 'talents' => ['Talents', '/talents'], 'leaderboards' => ['Leaderboards', '/leaderboards'], 'search' => ['Search', '/search'], 'developers' => ['API', '/developers']];
@endphp
<!doctype html>
<html lang="en">
<head>
<x-layouts.head :title="$title" :description="$description" :entries="['resources/css/site.css', 'resources/css/app.css', 'resources/css/game.css', 'resources/js/app.js']" :canonical="$canonical" :index="$index" :brand="$brand" />
</head>
<body class="art">
<header class="top solid">
  <div class="top-in wrap">
    <a class="brand" href="{{ $base ?: '/' }}" aria-label="Scarlet, home"><x-mark />Scarlet</a>
    <nav class="nav" aria-label="Site">@foreach ($items as $key => [$label, $path])<a href="{{ $base.$path }}"@if ($key === $current) aria-current="page"@endif>{{ $label }}</a>@endforeach</nav>
    <form class="top-find" role="search" action="{{ $base }}/search"@if ($base === '') data-suggest="/search/suggest"@endif><label class="vh" for="q">Search</label><input id="q" name="q" type="search" placeholder="Character, creature, item, zone, quest">@if ($base === '')<template data-search-icons>@foreach (\App\View\Pages\SearchSuggest::glyphs() as $glyph)<x-icon :name="$glyph" :data-glyph="$glyph" />@endforeach</template>@endif</form>
    <a class="get" href="{{ $base }}/download"@if ($current === 'download') aria-current="page"@endif>Get the app</a>
    <a class="acct" href="{{ $base }}{{ $signedIn ? '/account' : '/login' }}">{{ $signedIn ? 'Account' : 'Sign in' }}</a>
  </div>
</header>
@auth
<livewire:updates-banner />
@endauth
<main>
{{ $slot }}
</main>
<footer class="foot">
  <div class="wrap"><span>Scarlet</span><span>Numbers come from players who chose to upload. <a href="{{ $base }}/developers">Public API</a> · <x-version :base="$base" /></span></div>
</footer>
</body>
</html>
