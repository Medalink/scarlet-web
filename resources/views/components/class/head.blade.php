{{-- The head of a class page: the scene of the class's models when its art is on the store, a plain head when it is not. --}}
@props(['page', 'icons', 'scene' => null, 'art' => null])
@php($line = $page->character)
<section @class(['ch-head', 'scene-head', 'cls-head', 'ch-bare' => ! $art]) aria-labelledby="h1">
@if ($art && $scene)
  <div class="stage" data-scene="{{ $art['scene'] }}" data-models="{{ $art['models'] }}" data-texture="{{ $art['texture'] }}" data-state="loading" role="img" aria-label="{{ $scene->label() }}">
    <div class="callouts" aria-hidden="true">@foreach ($page->callouts as $callout)
      <p @class(['callout', 'left' => $callout->left]) @if ($callout->left) data-side="left"@endif data-key="{{ $callout->key }}" hidden><b>{{ $callout->title }}</b><span>{{ $callout->text }}</span>@if ($callout->note)<em>{{ $callout->note }}</em>@endif</p>@endforeach
    </div>
  </div>
@endif
  <div class="ch-id wrap">
    <div><h1 class="ch-name" id="h1">{{ $page->name() }}</h1>
      <p class="ch-sub"><x-class.icon :icon="$line->icon" :icons="$icons" :any="true" /><span><a href="{{ $page->links->character }}">{{ $line->name }}</a></span><span>Level {{ $line->level }}</span>@if ($line->build)<span>@if ($page->links->build)<a href="{{ $page->links->build }}">@endif{{ $line->build->tree }} {{ implode(' / ', $line->build->points) }}@if ($page->links->build)</a>@endif</span>@endif<span>{{ \App\Support\ClassPage\Unit::Hours->say($line->hours) }} hours played</span></p></div>
  </div>
</section>
