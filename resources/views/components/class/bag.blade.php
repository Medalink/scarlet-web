{{-- The bag's slots: block is App\Support\ClassPage\Bag. A held slot shows the item's icon, or the class colour when the item has no file. --}}
@props(['block', 'icons'])
@php
    $held = [];
    foreach ($block->items as $item) {
        for ($i = 0; $i < $item->slots && count($held) < $block->slots; $i++) {
            $held[] = $icons->url($item->icon);
        }
    }
@endphp
@if ($block->slots > 0)
<div class="bagslots" role="img" aria-label="{{ count($held) }} of {{ $block->slots }} bag slots hold a {{ $block->noun }}">@for ($i = 0; $i < $block->slots; $i++)@if (! array_key_exists($i, $held))<i class="slot"></i>@elseif ($held[$i])<i class="slot held pic" style="background-image:url('{{ $held[$i] }}')"></i>@else<i class="slot held"></i>@endif @endfor</div>
@endif
