{{-- Named charts side by side: block is App\Support\ClassPage\Columns. --}}
@props(['block', 'icons'])
<div class="cls-cols" data-n="{{ count($block->columns) }}" style="--n:{{ count($block->columns) }}">@foreach ($block->columns as $column)<div><x-class.titled :block="$column" :icons="$icons" /></div>@endforeach</div>
