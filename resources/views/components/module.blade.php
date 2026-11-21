{{-- A module on the 12-column sheet. span: w5, w7, w8 or w12 (none is a third). `heading` is a string or a slot;
     the slot `controls` puts a control beside the heading. A module with a heading needs an id. --}}
@props(['span' => null, 'first' => false, 'last' => false, 'heading' => null, 'id' => null, 'hideHeading' => false, 'level' => 2])
<section {{ $attributes->class(['mod', $span, 'first' => $first, 'last' => $last]) }}@if ($heading && $id) aria-labelledby="{{ $id }}"@endif>
@if ($heading && isset($controls))
  <div class="mod-head"><h2 id="{{ $id }}">{{ $heading }}</h2>
    {{ $controls }}</div>
@elseif ($heading)
  <h{{ $level }} id="{{ $id }}"@if ($hideHeading) class="vh"@endif>{{ $heading }}</h{{ $level }}>
@endif
{{ $slot }}
</section>
