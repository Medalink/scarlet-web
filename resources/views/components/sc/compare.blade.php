@props(['rows', 'n' => 0, 'label' => 110])
<div {{ $attributes->class('grid items-center gap-x-3 gap-y-3.5') }} style="grid-template-columns:{{ $label }}px minmax(0,1fr) 60px">
  @foreach ($rows as [$name, $spec, $text])
    <span class="sc-sub">{{ $name }}</span>
    @if ($n >= App\View\Sc\Compare::MIN_GROUP){!! App\View\Sc\Compare::html($name, $spec) !!}@else<span></span>@endif
    <b class="text-fg text-right">{{ $text }}</b>
  @endforeach
</div>
