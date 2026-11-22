{{-- The links between the profession pages (and the camps page on a player's own pages); `items` come from App\View\Pages\Trades. --}}
@props(['items'])
<nav class="trades" aria-label="Professions">@foreach ($items as $trade)<a href="{{ $trade['href'] }}"@if ($trade['current']) aria-current="page"@endif>{{ $trade['label'] }}</a>@endforeach</nav>
