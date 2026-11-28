@props(['pages', 'current'])
<nav {{ $attributes->class('sc-secnav overflow-x-auto overflow-y-hidden !static') }} aria-label="Account">
  @foreach ($pages as $href => $label)<a href="{{ $href }}"@if ($href === $current) aria-current="page"@endif>{{ $label }}</a>@endforeach
</nav>
