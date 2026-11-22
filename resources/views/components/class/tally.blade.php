{{-- The figures under the head: items is a list of App\Support\ClassPage\Figure. --}}
@props(['items', 'icons'])
<dl class="tally" style="--n:{{ count($items) }}" aria-label="Totals">@foreach ($items as $item)<div><dt><x-class.icon :icon="$item->icon" :icons="$icons" />{{ $item->label }}</dt><dd>{{ $item->unit->figure($item->value) }}@if ($item->of)<small>{{ $item->of }}</small>@endif</dd></div>@endforeach</dl>
