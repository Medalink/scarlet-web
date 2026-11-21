{{-- A state label such as Public or Private. `on` marks the active state with the scarlet dot. --}}
@props(['on' => false])
<span {{ $attributes->class(['state', 'on' => $on]) }}>{{ $slot }}</span>
