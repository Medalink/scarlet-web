@props(['current' => 'everyone', 'url', 'characters' => [], 'as' => null, 'signedIn' => true])
@php
    $as = $as ?? ($characters[0] ?? null);
    $modes = [
        'everyone' => ['Everyone', $url],
        'compare' => ['You vs everyone', $url.'?'.http_build_query(array_filter(['scope' => 'compare', 'as' => $as]))],
        'mine' => ['Only you', $url.'?scope=mine'],
    ];
@endphp
@if ($signedIn)
<div {{ $attributes->class('flex flex-wrap items-center gap-3') }}>
  <nav class="sc-seg" aria-label="Scope">
    @foreach ($modes as $key => [$label, $href])<a href="{{ $href }}" @class(['on' => $key === $current]) @if ($key === $current) aria-current="true" @endif>{{ $label }}</a>@endforeach
  </nav>
  @if ($current === 'compare' && count($characters) > 0)
    <form method="get" action="{{ $url }}"><input type="hidden" name="scope" value="compare"><select class="sc-select" name="as" aria-label="Character" data-autosubmit>@foreach ($characters as $name)<option value="{{ $name }}" @selected($name === $as)>{{ $name }}</option>@endforeach</select></form>
  @endif
</div>
@endif
