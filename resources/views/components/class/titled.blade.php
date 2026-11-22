{{-- A named chart inside a module: block is App\Support\ClassPage\Titled. --}}
@props(['block', 'icons'])
<h3 class="chart-name"><x-class.icon :icon="$block->icon" :icons="$icons" />{{ $block->title }}</h3>
@foreach ($block->blocks as $inner)<x-class.block :block="$inner" :icons="$icons" />@endforeach
