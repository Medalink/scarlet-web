{{-- A button or, with `href`, a link that looks like one. kind: none (scarlet), quiet or danger. --}}
@props(['kind' => null, 'href' => null, 'type' => 'button'])
@if ($href)
<a {{ $attributes->class(['btn', $kind]) }} href="{{ $href }}">{{ $slot }}</a>
@else
<button {{ $attributes->class(['btn', $kind]) }} type="{{ $type }}">{{ $slot }}</button>
@endif
