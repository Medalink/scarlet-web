@props(['title', 'description' => null, 'entries', 'canonical' => null, 'index' => true, 'brand' => true])
@php
    $path = $canonical ?? rtrim(request()->getPathInfo(), '/');
    $path = $path === '' ? '/' : $path;
    $origin = rtrim((string) config('app.url'), '/');
@endphp
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title }}{{ $brand ? ' | Scarlet' : '' }}</title>
@if ($description)
<meta name="description" content="{{ $description }}">
@endif
@if (! $index)
<meta name="robots" content="noindex">
@else
<link rel="canonical" href="{{ $origin.$path }}">
@endif
<meta property="og:site_name" content="Scarlet">
<meta property="og:type" content="website">
<meta property="og:title" content="{{ $title }}">
@if ($description)
<meta property="og:description" content="{{ $description }}">
@endif
<meta property="og:url" content="{{ $origin.$path }}">
<meta property="og:image" content="{{ $origin }}/share.png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="Scarlet: a record of what you do in WoW Forever">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#141416">
{{-- Cross-page transitions. Chromium decides at each sheet load whether the page opted in, so the opt-in sits inline
     ahead of every stylesheet: site.css, the fonts sheet and the page sheets can arrive in any order without cancelling it. --}}
<style>@media (prefers-reduced-motion: no-preference) { @view-transition { navigation: auto; } }</style>
<link rel="icon" href="/mark.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Martian+Mono:wdth,wght@82..100,400..700&family=Sofia+Sans:wght@400..800&family=Sofia+Sans+Extra+Condensed:wght@500..900&display=swap">
@vite($entries)
