{{-- The page frame of the web app: the comps' header, the page, the footer. `base` puts every link under a
     prefix, which the design gallery uses to stay inside /design. --}}
@props(['title', 'description' => null, 'current' => '', 'signedIn' => false, 'base' => ''])
@php
    $items = ['stats' => ['Stats', '/stats'], 'world' => ['Map', '/map'], 'rulesets' => ['Rulesets', '/rulesets'], 'leaderboards' => ['Leaderboards', '/leaderboards'], 'search' => ['Search', '/search'], 'developers' => ['API', '/developers']];
@endphp
<!doctype html>
<html lang="en">
<head>
<x-layouts.head :title="$title" :description="$description" :entries="['resources/css/site.css', 'resources/css/app.css', 'resources/js/app.js']" />
</head>
<body>
<header class="top solid">
  <div class="top-in wrap">
    <a class="brand" href="{{ $base ?: '/' }}" aria-label="Scarlet, home"><x-mark />Scarlet</a>
    <nav class="nav" aria-label="Site">@foreach ($items as $key => [$label, $path])<a href="{{ $base.$path }}"@if ($key === $current) aria-current="page"@endif>{{ $label }}</a>@endforeach</nav>
    <form class="top-find" role="search" action="{{ $base }}/search"><label class="vh" for="q">Search</label><input id="q" name="q" type="search" placeholder="Character, creature, item, zone, quest"></form>
    <a class="acct" href="{{ $base }}{{ $signedIn ? '/account' : '/login' }}">{{ $signedIn ? 'Account' : 'Sign in' }}</a>
  </div>
</header>
<main>
{{ $slot }}
</main>
<footer class="foot">
  <div class="wrap"><span>Scarlet</span><span>Numbers come from players who chose to upload. <a href="{{ $base }}/developers">Public API</a></span></div>
</footer>
</body>
</html>
