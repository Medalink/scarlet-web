{{-- The figure strip under a page head. items: [['label' => 'Characters', 'value' => '1,284', 'unit' => 'played']]. Tiles on a hairline grid. --}}
@props(['items', 'five' => false])
<x-sc.tiles :cols="min(6, max(2, count($items)))" class="mt-6" aria-label="Totals">@foreach ($items as $item)<x-sc.tile :label="$item['label']" :value="$item['value']" :unit="$item['unit'] ?? null" />@endforeach</x-sc.tiles>
