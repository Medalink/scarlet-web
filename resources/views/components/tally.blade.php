{{-- The figures under a page hero, each with its glyph. items: App\View\Pages\StatsGame Tile. A whole number counts up once on load. --}}
@props(['items'])
<dl class="tally" style="--n:{{ count($items) }}" aria-label="Totals">@foreach ($items as $item)<div><dt><x-icon :name="$item['icon']" />{{ $item['label'] }}</dt><dd>@if ($item['count'] !== null)<span data-count="{{ $item['count'] }}">{{ $item['value'] }}</span>@else{{ $item['value'] }}@endif@if ($item['unit'])<small>{{ $item['unit'] }}</small>@endif</dd></div>@endforeach</dl>
