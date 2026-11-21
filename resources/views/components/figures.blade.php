{{-- The figure strip under a page head. items: [['label' => 'Characters', 'value' => '1,284', 'unit' => 'played']]. Each figure is a term and its value in a list of its own. --}}
@props(['items'])
<section @class(['week', 'n6' => count($items) === 6]) aria-label="Totals">@foreach ($items as $item)<dl><dt>{{ $item['label'] }}</dt><dd>{{ $item['value'] }}@isset($item['unit'])<small>{{ $item['unit'] }}</small>@endisset</dd></dl>@endforeach</section>
