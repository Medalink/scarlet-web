{{-- The button that downloads the newest Windows installer, and the version it is. Without a release it links the
     releases page. `class` is added to the button (on-scarlet on the red band). --}}
@props(['version' => true])
@php($app = app(\App\Actions\Release\ShowAppDownload::class)->handle())
@if ($app['installer'])
<a {{ $attributes->class(['sc-btn']) }} href="{{ $app['installer'] }}">Download for Windows
  <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M8 2.5v8M4.5 7.5L8 11l3.5-3.5M3 13.5h10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
</a>
@if ($version)
<span class="get-ver">{{ $app['prerelease'] ? 'Beta version' : 'Version' }} {{ $app['version'] }}</span>
@endif
@else
<a {{ $attributes->class(['sc-btn']) }} href="{{ $app['releases'] }}">Download from GitHub
  <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M3 8h9M8.5 4l4 4-4 4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
</a>
@endif
