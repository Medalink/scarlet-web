{{-- The head of a page: the h1, a line of facts (slot `sub`), anything between them (slot `body`) and the filters on the right (slot `filters`). --}}
@props(['title', 'class' => null, 'single' => false, 'rule' => true])
<section @class(['grid items-end gap-x-12 gap-y-4 pb-6 pt-10 grid-cols-[minmax(0,1fr)] md:grid-cols-[minmax(0,1fr)_auto]', 'border-b border-line' => $rule]) aria-labelledby="h1">
  <div class="min-w-0">
    <h1 id="h1" @class(['sc-title', $class]) style="font-size:clamp(44px,6vw,60px)">{{ $title }}</h1>
    @isset($body){{ $body }}@endisset
    @if (isset($sub) && trim((string) $sub) !== '')<p class="mt-3 max-w-[62ch] text-[15.5px]">{{ $sub }}</p>@endif
  </div>
  @isset($filters)<div class="flex min-w-0 flex-wrap items-center gap-3 md:justify-end">{{ $filters }}</div>@endisset
</section>
