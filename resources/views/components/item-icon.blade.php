{{-- The icon of an item or spell row: the image at its final size, so the row never moves when it loads. `icon` is an address from ItemIcons or SpellIcons; `spell` asks for a spell's icon by its id and shows an empty square when it has none. With no image an item row shows the empty square when `any` says another row has one. --}}
@props(['icon' => null, 'any' => false, 'spell' => null])
@php($src = $icon ?? ($spell === null ? null : \App\Support\SpellIcons::icon($spell)))
@if ($src)<img src="{{ $src }}" alt="" width="26" height="26" loading="lazy">@endif
