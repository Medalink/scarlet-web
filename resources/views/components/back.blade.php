{{-- A link back to the list a page belongs to, above the page head. --}}
@props(['href', 'label'])
<a {{ $attributes->class(['back']) }} href="{{ $href }}">← {{ $label }}</a>
