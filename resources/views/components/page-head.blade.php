{{-- The head of a page: the h1, a line of facts (slot `sub`), anything between them (slot `body`) and the filters on the right (slot `filters`). --}}
@props(['title', 'class' => null, 'single' => false])
<section class="pg-head" aria-labelledby="h1"@if ($single) style="grid-template-columns:minmax(0,1fr)"@endif>
  <div>
    <h1 id="h1"@if ($class) class="{{ $class }}"@endif>{{ $title }}</h1>
    @isset($body){{ $body }}@endisset
    @isset($sub)<p class="sub">{{ $sub }}</p>@endisset
  </div>
  @isset($filters)<div class="filters">{{ $filters }}</div>@endisset
</section>
