{{-- One block of a module, drawn by the component of its type. --}}
@props(['block', 'icons'])
<x-dynamic-component :component="'class.'.strtolower(class_basename($block))" :block="$block" :icons="$icons" />
