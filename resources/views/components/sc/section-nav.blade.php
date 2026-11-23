@props(['sections', 'current' => null, 'sticky' => true])
@php($current = $current ?? array_key_first($sections))
<nav {{ $attributes->class(['sc-secnav overflow-x-auto overflow-y-hidden', '!static' => ! $sticky]) }} aria-label="Sections" data-spy>
  @foreach ($sections as $id => $label)<a href="#{{ $id }}" @if ($id === $current) aria-current="true" @endif>{{ $label }}</a>@endforeach
</nav>
