{{-- One row: a title (a link with `href`), a line of detail, and the controls in the slot. --}}
@props(['title', 'body', 'href' => null])
<div {{ $attributes->class(['row']) }}><b>@if ($href)<a href="{{ $href }}">{{ $title }}</a>@else{{ $title }}@endif</b><p>{{ $body }}</p><div class="act">{{ $slot }}</div></div>
