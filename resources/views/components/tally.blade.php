{{-- The figures under a page hero. items: App\View\Pages\StatsGame Tile. A whole number counts up once on load. --}}
@props(['items'])
<x-sc.tiles :cols="min(6, max(2, count($items)))" class="mt-6 tally" aria-label="Totals">@foreach ($items as $item)<x-sc.tile :label="$item['label']"><span class="sc-num">@if ($item['count'] !== null)<span data-count="{{ $item['count'] }}">{{ $item['value'] }}</span>@else{{ $item['value'] }}@endif@if ($item['unit'])<small>{{ $item['unit'] }}</small>@endif</span></x-sc.tile>@endforeach</x-sc.tiles>
