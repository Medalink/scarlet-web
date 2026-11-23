<x-layouts.sc :styles="$art ? ['resources/css/class-section.css'] : []" :title="$title" :description="$own ? null : $d['words']['description']" current="professions" :canonical="$canonical" :index="! $own">
@if ($art)
@vite('resources/js/scene.js')
@endif
<div class="wrap">
  <x-page-head :title="$d['words']['title']">
    <x-slot:sub>@foreach ($d['facts'] as $fact){{ $fact }}@if (! $loop->last) · @endif @endforeach @if ($own)· <a href="{{ $everyone }}">Everyone's {{ $d['words']['title'] }}</a>@endif</x-slot:sub>
    <x-slot:filters><x-scope-switch :choice="$scope['choice']" :path="'/professions/'.$slug" :names="$scope['names']" :as="$scope['as']" :query="$scope['query']" /><x-scope-filters :filters="$filters" /></x-slot:filters>
  </x-page-head>
  @if ($art)
  <div class="cls-page">
    <div class="sc-scene sc-panel mt-6">
      <div class="stage" data-scene="{{ $art['scene'] }}" data-models="{{ $art['models'] }}" data-texture="{{ $art['texture'] }}" data-state="loading" role="img" aria-label="{{ $scene->label() }}"></div>
      <div class="callouts" aria-hidden="true">@foreach ($d['callouts'] as $callout)
        <p @class(['callout', 'left' => $callout['key'] !== 'fire']) data-side="{{ $callout['key'] !== 'fire' ? 'left' : 'right' }}" data-key="{{ $callout['key'] }}"><b>{{ $callout['title'] }}</b><span>{{ $callout['text'] }}</span>@if ($callout['share'])<em>{{ $callout['share'] }}</em>@endif</p>@endforeach
      </div>
    </div>
  </div>
  @endif
  <x-trades :items="$trades" />
  @if ($art)<p class="sc-note mt-2">{{ $scene->credit() }}</p>@endif
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
</x-layouts.sc>
