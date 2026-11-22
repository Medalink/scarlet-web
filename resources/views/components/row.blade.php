{{-- One row: a title (a link with `href`), a line of detail, and the controls in the slot. `time` ends the line with an
     instant the browser shows in the viewer's time zone: ['instant' => ISO 8601 in UTC, 'format' => PHP date tokens,
     'text' => the UTC text]. --}}
@props(['title', 'body', 'href' => null, 'time' => null])
<div {{ $attributes->class(['row']) }}><b>@if ($href)<a href="{{ $href }}">{{ $title }}</a>@else{{ $title }}@endif</b><p>{{ $body }}@if ($time)<time datetime="{{ $time['instant'] }}" data-local="{{ $time['format'] }}">{{ $time['text'] }}</time>@endif</p><div class="act">{{ $slot }}</div></div>
