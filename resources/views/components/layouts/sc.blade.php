@props(['title', 'description' => null, 'current' => '', 'signedIn' => false, 'base' => '', 'canonical' => null, 'index' => true, 'brand' => true])
@php
    $signedIn = $signedIn || auth()->check();
    $links = App\View\Sc\Nav::LINKS;
@endphp
<!doctype html>
<html lang="en">
<head>
<x-layouts.head :title="$title" :description="$description" :entries="['resources/css/tailwind.css', 'resources/js/sc.js']" :canonical="$canonical" :index="$index" :brand="$brand" />
</head>
<body class="bg-bg font-body text-copy">
<a class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-card focus:px-3 focus:py-2" href="#main">Skip to main content</a>
<header class="top border-b border-line">
  <div class="wrap flex h-[60px] items-center gap-3 sm:gap-6">
    <a href="{{ $base ?: '/' }}" class="flex items-center gap-2.5" aria-label="Scarlet, home"><span class="sc-mark" style="font-size:20px"></span><b class="font-display text-[27px] font-extrabold text-fg">Scarlet</b></a>
    <nav class="hidden gap-5 text-[15px] lg:flex" aria-label="Site">@foreach ($links as $key => [$label, $path])<a href="{{ $base.$path }}"@if ($key === $current) aria-current="page"@endif>{{ $label }}</a>@endforeach</nav>
    <details class="relative lg:hidden">
      <summary class="sc-btn quiet small list-none">Menu</summary>
      <nav class="absolute left-0 top-10 z-40 grid w-48 gap-px border border-line2 bg-card p-2 shadow-[0_6px_18px_rgb(0_0_0/0.45)]" aria-label="Site">@foreach ($links as $key => [$label, $path])<a class="px-2 py-1.5" href="{{ $base.$path }}"@if ($key === $current) aria-current="page"@endif>{{ $label }}</a>@endforeach</nav>
    </details>
    <form class="search ml-auto" role="search" action="{{ $base }}/search"><x-sc.glyph name="search" :size="15" /><label class="sr-only" for="sc-q">Search</label><input id="sc-q" name="q" type="search" placeholder="Search" class="w-full bg-transparent text-fg outline-none placeholder:text-muted"></form>
    @unless ($signedIn)<a href="{{ $base }}/download" class="sc-btn quiet small max-sm:hidden">Get the app</a>@endunless
    <a href="{{ $base }}{{ $signedIn ? '/account' : '/login' }}" class="text-[15px]"@if ($current === 'account') aria-current="page"@endif>{{ $signedIn ? 'Account' : 'Sign in' }}</a>
  </div>
</header>
<main id="main">
{{ $slot }}
</main>
<footer class="wrap border-t border-line py-6 text-[13px] text-muted">
  <span>Numbers come from players who chose to upload. <a href="{{ $base }}/developers">Public API</a> · <x-version :base="$base" /></span>
</footer>
</body>
</html>
