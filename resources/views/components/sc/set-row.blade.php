@props(['title', 'body' => null, 'href' => null, 'time' => null])
<div {{ $attributes->class('sc-set-row') }}><b>@if ($href)<a href="{{ $href }}">{{ $title }}</a>@else{{ $title }}@endif</b><p>{{ $body }}@if ($time)<time datetime="{{ $time['instant'] }}" data-local="{{ $time['format'] }}">{{ $time['text'] }}</time>@endif</p><div class="act">{{ $slot }}</div></div>
