{{-- Sign-in providers. providers: [['label' => 'Continue with Google', 'href', 'icon' => 'google|battlenet']]. --}}
@props(['providers'])
<div class="sso">
@foreach ($providers as $provider)
  <a href="{{ $provider['href'] }}">@if ($provider['icon'] === 'google')<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#f0f1f3" d="M21.6 12.2c0-.7-.1-1.3-.2-1.9H12v3.7h5.4a4.6 4.6 0 0 1-2 3v2.5h3.2c1.9-1.7 3-4.3 3-7.3z"/><path fill="#cdced4" d="M12 22c2.7 0 5-.9 6.6-2.4l-3.2-2.5c-.9.6-2 1-3.4 1-2.6 0-4.8-1.8-5.6-4.1H3.100v2.6A10 10 0 0 0 12 22z"/><path fill="#8a8a93" d="M6.400 14a6 6 0 0 1 0-3.900V7.500H3.100a10 10 0 0 0 0 9z"/><path fill="#f0f1f3" d="M12 5.900c1.500 0 2.800.5 3.800 1.500l2.900-2.800A10 10 0 0 0 3.100 7.500l3.300 2.600C7.200 7.700 9.400 5.900 12 5.900z"/></svg>@else<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" fill="none" stroke="#f0f1f3" stroke-width="2"/><path d="M12 3c3 3.500 3 14.500 0 18M12 3c-3 3.500-3 14.500 0 18M3.500 9h17M3.500 15h17" fill="none" stroke="#f0f1f3" stroke-width="1.4"/></svg>@endif{{ $provider['label'] }}</a>
@endforeach
</div>
