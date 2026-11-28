{{-- One row: a title (a link with `href`), a line of detail, and the controls in the slot. `time` ends the line with a
     date from the Dates helper. --}}
@props(['title', 'body', 'href' => null, 'time' => null])
<div {{ $attributes->class(['row']) }}><b>@if ($href)<a href="{{ $href }}">{{ $title }}</a>@else{{ $title }}@endif</b><p>{{ $body }}@if ($time){{ $time }}@endif</p><div class="act">{{ $slot }}</div></div>
