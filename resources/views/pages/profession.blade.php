<x-layouts.app :title="$title" :description="$own ? null : $d['words']['description']" current="professions" :canonical="$canonical" :index="! $own">
@if ($art)
@vite('resources/js/scene.js')
@endif
<section @class(['ch-head', 'scene-head', 'ch-bare' => ! $art]) aria-labelledby="h1">
  @if ($art)
  <div class="stage" data-scene="{{ $art['scene'] }}" data-models="{{ $art['models'] }}" data-texture="{{ $art['texture'] }}" data-state="loading" role="img" aria-label="{{ $scene->label() }}">
    <div class="callouts" aria-hidden="true">@foreach ($d['callouts'] as $callout)
      <p class="callout left" data-side="left" data-key="{{ $callout['key'] }}" hidden><b>{{ $callout['title'] }}</b><span>{{ $callout['text'] }}</span>@if ($callout['share'])<em>{{ $callout['share'] }}</em>@endif</p>@endforeach
    </div>
  </div>
  @endif
  <div class="ch-id wrap">
    <div><h1 class="ch-name" id="h1">{{ $d['words']['title'] }}</h1>
      <p class="ch-sub">@foreach ($d['facts'] as $fact)<span>{{ $fact }}</span>@endforeach{{ '' }}@if ($own)<span><a href="{{ $everyone }}">Everyone's {{ $d['words']['title'] }}</a></span>@endif</p></div>
    <div class="filters"><x-scope-switch :choice="$scope['choice']" :path="'/professions/'.$slug" :names="$scope['names']" :as="$scope['as']" :query="$scope['query']" /><x-scope-filters :filters="$filters" /></div>
  </div>
</section>
<div class="wrap">
  <x-trades :items="$trades" />
  @if ($art)
  @if (array_filter(array_column($d['callouts'], 'share')))
  <ul class="camp-shares">@foreach ($d['callouts'] as $callout)@if ($callout['share'])<li><b>{{ $callout['title'] }}</b> {{ $callout['share'] }}</li>@endif @endforeach</ul>
  @endif
  <p class="credit">{{ $scene->credit() }}</p>
  @endif
  <x-figures :items="$d['figures']" :five="$own" />
  @if ($scope['versus'])<x-scope-compare :compare="$scope['versus']" />@endif
  @if ($d['note'])<x-note>{{ $d['note'] }}</x-note>@endif
  <x-sheet>
    @foreach ($d['layout'] as $row)
    @foreach ($row as $i => $module)
    <x-module :span="$module['span']" :first="$i === 0" :last="$i === count($row) - 1" :class="$module['class']" :id="'h-'.$module['key']" :heading="$d['heads'][$module['key']]">
      @include('pages.profession.'.$module['key'], ['m' => $d['modules'][$module['key']], 'w' => $d['words']])
    </x-module>
    @endforeach
    @endforeach
  </x-sheet>
</div>
</x-layouts.app>
