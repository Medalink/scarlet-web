{{-- The band of five of the class's figures on the character page, first under the line of pages: band is App\Support\ClassPage\ClassBand. --}}
@props(['band', 'icons'])
@php($name = \App\View\Format::className($band->class->value))
<section class="week cls-band" data-class="{{ $band->class->value }}" data-n="{{ count($band->figures) }}" style="--n:{{ count($band->figures) }}" aria-labelledby="h-band">
<div><h2 id="h-band"><x-class.icon :icon="$band->icon" :icons="$icons" />{{ $name }}</h2><p><a href="{{ $band->href }}">All {{ strtolower($name) }} numbers</a></p></div>
@foreach ($band->figures as $figure)<dl><dt><x-class.icon :icon="$figure->icon" :icons="$icons" />{{ $figure->label }}</dt><dd>{{ $figure->unit->figure($figure->value) }}</dd></dl>@endforeach
</section>
