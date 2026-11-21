{{-- The figure strip under a page head. items: [['label' => 'Characters', 'value' => '1,284', 'unit' => 'played']]. --}}
@props(['items'])
<section @class(['week', 'n6' => count($items) === 6]) aria-label="Totals">@foreach ($items as $item)<div><dt>{{ $item['label'] }}</dt><dd>{{ $item['value'] }}@isset($item['unit'])<small>{{ $item['unit'] }}</small>@endisset</dd></div>@endforeach</section>
