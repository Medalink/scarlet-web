{{-- A module of the class page's sheet. blocks: list of App\Support\ClassPage\Block; span: Span; tag: the points of a talent, "2 of 2". --}}
@props(['id', 'heading', 'blocks', 'icons', 'span' => \App\Support\ClassPage\Span::Third, 'note' => null, 'icon' => null, 'tag' => null, 'first' => false, 'last' => false])
<section @class(['mod', $span->value => $span->value !== '', 'first' => $first, 'last' => $last]) aria-labelledby="{{ $id }}">
<h2 id="{{ $id }}"><x-class.icon :icon="$icon" :icons="$icons" />{{ $heading }}@if ($tag) <span class="tal">{{ $tag }}</span>@endif</h2>
@foreach ($blocks as $block)<x-class.block :block="$block" :icons="$icons" />@endforeach
@if ($note)<p class="note">{{ $note }}</p>@endif
</section>
