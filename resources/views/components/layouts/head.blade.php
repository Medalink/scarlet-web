@props(['title', 'description' => null, 'entries'])
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title }}</title>
@if ($description)
<meta name="description" content="{{ $description }}">
@endif
<meta name="theme-color" content="#141416">
<link rel="icon" href="/mark.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Martian+Mono:wdth,wght@75..112.5,100..800&family=Sofia+Sans:ital,wght@0,400..800;1,400..800&family=Sofia+Sans+Extra+Condensed:wght@500..900&display=swap">
@vite($entries)
