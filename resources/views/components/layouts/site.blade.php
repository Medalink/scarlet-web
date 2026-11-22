{{-- The frame of the landing page and the demo character: the header lies over the page, as on the static site. --}}
@props(['title', 'description', 'demo' => false, 'canonical' => null, 'index' => true, 'brand' => true])
@php
    $jump = $demo ? '/' : '';
    // Stats and Map stay in the header on a phone; the other pages drop out first (site.css, landing.css).
    $items = ['stats' => ['Stats', '/stats', ''], 'world' => ['Map', '/map', ''], 'camps' => ['Camps', '/camps', 'jump app'], 'rulesets' => ['Rulesets', '/rulesets', 'jump app'], 'leaderboards' => ['Leaderboards', '/leaderboards', 'jump app'], 'search' => ['Search', '/search', 'jump app'], 'developers' => ['API', '/developers', 'jump app']];
@endphp
<!doctype html>
<html lang="en">
<head>
<x-layouts.head :title="$title" :description="$description" :entries="$demo ? ['resources/css/site.css', 'resources/css/landing.css', 'resources/js/demo.js'] : ['resources/css/site.css', 'resources/css/landing.css', 'resources/js/landing.js']" :canonical="$canonical" :index="$index" :brand="$brand" />
@isset($head){{ $head }}@endisset
</head>
<body>

<header class="top">
  <div class="top-in wrap">
    <a class="brand" href="/" aria-label="Scarlet, home">
      <x-mark />
      Scarlet
    </a>
    <nav class="nav" aria-label="Site">
      <a class="jump" href="{{ $jump }}#events">Events</a>
      <a class="jump" href="{{ $jump }}#never">Never recorded</a>
      <a class="jump" href="{{ $jump }}#capture">Capture map</a>
      <a class="demo" href="/demo"@if ($demo) aria-current="page"@endif>Character overview</a>
      @foreach ($items as [$label, $path, $class])
      <a @if ($class) class="{{ $class }}" @endif href="{{ $path }}">{{ $label }}</a>
      @endforeach
      <a class="get" href="/download">Get the app</a>
    </nav>
    <a class="acct" href="{{ auth()->check() ? '/account' : '/login' }}">{{ auth()->check() ? 'Account' : 'Sign in' }}</a>
  </div>
</header>

<main>
{{ $slot }}
</main>

<footer class="foot">
  <div class="wrap"><span>Scarlet</span><span>The character, places, creatures and numbers on this site are invented demo data. <x-version /></span></div>
</footer>
</body>
</html>
