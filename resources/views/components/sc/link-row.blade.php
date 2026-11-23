@props(['dot' => null, 'action' => null, 'href' => '#', 'quiet' => true, 'hide' => null])
<div {{ $attributes->class('sc-link') }}>
  <span>@if ($dot)<span @class(['sc-dot', 'off' => $dot === 'off'])></span>&nbsp; @endif{{ $slot }}</span>
  @if ($action)<a @class(['sc-btn small', 'quiet' => $quiet]) href="{{ $href }}">{{ $action }}</a>@endif
  @if ($hide)<a class="text-[12.5px] text-muted" href="{{ $hide }}">Hide</a>@endif
</div>
