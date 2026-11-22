{{-- A grid of records. items: App\View\Pages\StatsGame Record. The first is the lead tile. --}}
@props(['items'])
<ul class="rec-grid">@foreach ($items as $item)<li @if ($item['class']) class="{{ $item['class'] }}" @endif><x-icon :name="$item['icon']" class="rec-ico" /><span class="rec-v">@if ($item['href'])<a href="{{ $item['href'] }}">{{ $item['value'] }}</a>@else{{ $item['value'] }}@endif</span><span class="rec-l">{{ $item['label'] }}</span>@if ($item['detail'] !== '')<span class="rec-d">{{ $item['detail'] }}</span>@endif</li>@endforeach</ul>
