{{-- The icon of an item row: the image at its final size, so the row never moves when it loads. With no image it is an empty square when `any` says another row has one. --}}
@props(['icon', 'any' => false])
@if ($icon)<img src="{{ $icon }}" alt="" width="26" height="26" loading="lazy">@elseif ($any)<i class="buff-none" aria-hidden="true"></i>@endif