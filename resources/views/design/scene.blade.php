<x-layouts.app :title="$d['title'].' scene'" :base="$base" :index="false">
@if ($art)
@vite('resources/js/scene.js')
@endif
<section @class(['ch-head', 'scene-head', 'ch-bare' => ! $art]) aria-labelledby="h1">
  @if ($art)
  <div class="stage" data-scene="{{ $art['scene'] }}" data-models="{{ $art['models'] }}" data-texture="{{ $art['texture'] }}" data-state="loading" role="img" aria-label="{{ $d['label'] }}">
    <div class="callouts" aria-hidden="true">@foreach ($d['callouts'] as $callout)
      <p class="callout left" data-side="left" data-key="{{ $callout['key'] }}" hidden><b>{{ $callout['title'] }}</b><span>{{ $callout['text'] }}</span><em>{{ $callout['share'] }}</em></p>@endforeach
    </div>
  </div>
  @endif
  <div class="ch-id wrap">
    <div><h1 class="ch-name" id="h1">{{ $d['title'] }}</h1>
      <p class="ch-sub">@foreach ($d['facts'] as $fact)<span>{{ $fact }}</span>@endforeach</p></div>
    <div class="filters"><div class="seg" role="group" aria-label="Ruleset"><a href="#" aria-current="true">All</a><a href="#">Standard</a><a href="#">Hardcore</a><a href="#">Slow</a></div></div>
  </div>
</section>
<div class="wrap">
  @if ($art)
  <p class="credit">{{ $d['credit'] }}</p>
  @endif
</div>
</x-layouts.app>
