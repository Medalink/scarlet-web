<x-layouts.sc :styles="$art ? ['resources/css/class-section.css'] : []" :title="$own ? 'Your camps: how you use the campsite in WoW Forever' : 'Camps: how players use the campsite in WoW Forever'" :description="$own ? null : 'How long players stay at a campfire in WoW Forever, which Camp Benefits they leave with, what they set up and where they camp.'" current="camps" :canonical="$canonical" :index="! $own">
@if ($art)
@vite('resources/js/scene.js')
@endif
<div class="wrap">
  <x-page-head title="Camps">
    <x-slot:sub>@if ($own){{ $d['whose'] }}. <a href="/camps">Everyone's camps</a>@endif</x-slot:sub>
    <x-slot:filters><x-scope-switch :choice="$scope['choice']" path="/camps" :names="$scope['names']" :as="$scope['as']" :query="$scope['query']" /><x-scope-filters :filters="$filters" /></x-slot:filters>
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
  @if ($own)
  <x-trades :items="$trades" />
  @endif
  @if ($art)<p class="sc-note mt-2">{{ $scene->credit() }}</p>@endif
  @unless ($d['none'])<x-figures :items="$d['figures']" />@endunless
  @if ($scope['versus'] && ! $d['none'])<x-scope-compare :compare="$scope['versus']" />@endif
  @if ($d['note'])<x-note>{{ $d['note'] }}</x-note>@endif
  @php
    $ben = $d['benefits']['rows'] !== [];
    $set = (bool) $d['setup'];
    $charts = array_values(array_filter([$d['lengths'] ? 'len' : null, $d['visitRate'] ? 'lvl' : null, $d['restRate'] ? 'rest' : null]));
    $chartSpan = count($charts) === 3 ? null : (count($charts) === 2 ? 'w6' : 'w12');
    $buff = $d['buffed'] !== [];
    $hasMap = (bool) $map;
    $zon = (bool) $d['zones'];
    $doing = (bool) $d['doing'];
    $ends = (bool) $d['ends'];
  @endphp
  @if ($d['none'])
  <x-note>{{ $own ? 'No camp visits of yours are recorded yet.' : 'No camp visits are recorded yet.' }}</x-note>
  @else
  <x-sheet>
    @if ($ben || $set)
    @if ($ben)
    <x-module :span="$set ? 'w7' : 'w12'" :first="true" :last="! $set" class="camp-mod" id="h-ben" :heading="'Camp Benefits '.$d['who'].' leave with'">
      <div class="scroll-x"><table class="data benefits" style="margin-top:1.1rem">
        <thead><tr><th>Placed at the camp</th><th>Benefit</th><th class="n">Of Camp Benefits</th>@if ($d['benefits']['lost'])<th class="n">Lost to a death</th>@endif</tr></thead>
        <tbody>@foreach ($d['benefits']['rows'] as $row)
          <tr><td><span class="buff">@if ($row['icon'])<img src="{{ $row['icon'] }}" alt="" width="26" height="26" loading="lazy">@elseif ($d['benefits']['icons'])<i class="buff-none" aria-hidden="true"></i>@endif{{ $row['label'] }}</span></td><td>{{ $row['effect'] }}</td><td class="n">@if ($row['bar'])<i class="bar" style="width:{{ $row['bar'] }}"></i>@endif{{ $row['share'] }}</td>@if ($d['benefits']['lost'])<td class="n">{{ $row['lost'] ?? '–' }}</td>@endif</tr>@endforeach
        </tbody></table></div>
      @if ($d['twoHours'])<x-note>{{ $d['twoHours'] }} of Camp Benefits are given for two hours instead of one.</x-note>@endif
    </x-module>
    @endif
    @if ($set)
    <x-module :span="$ben ? 'w5' : 'w12'" :first="! $ben" :last="true" id="h-set" heading="What gets set up">
      <x-rank :rows="$d['setup']['fires']" />
      <x-rank :rows="$d['setup']['placed']" plain />
    </x-module>
    @endif
    @endif

    @foreach ($charts as $i => $chart)
    @if ($chart === 'len')
    <x-module :span="$chartSpan" :first="$i === 0" :last="$i === count($charts) - 1" id="h-len" heading="How long a visit lasts">
      <x-plot id="len" label="Visits by length, as a share of all visits" :spec="$d['lengths']['spec']" />
      @if ($d['lengths']['note'])<x-note>{{ $d['lengths']['note'] }}</x-note>@endif
    </x-module>
    @elseif ($chart === 'lvl')
    <x-module :span="$chartSpan" :first="$i === 0" :last="$i === count($charts) - 1" id="h-lvl" heading="Visits a played hour, by level">
      <x-plot id="lvl" label="Camp visits a played hour at each level" :spec="$d['visitRate']['spec']" />
      <x-note>{{ $d['visitRate']['note'] }}</x-note>
    </x-module>
    @else
    <x-module :span="$chartSpan" :first="$i === 0" :last="$i === count($charts) - 1" id="h-rest" heading="Tent rests a played hour, by level">
      <x-plot id="rest" label="Tent rests a played hour at each level" :spec="$d['restRate']['spec']" />
      <x-note>{{ $d['restRate']['note'] }}</x-note>
    </x-module>
    @endif
    @endforeach

    @if ($d['hours'] || $buff)
    @if ($d['hours'])
    <x-module :span="$buff ? 'w8' : 'w12'" :first="true" :last="! $buff" id="h-when" :heading="'When '.$d['who'].' camp'">
      <x-week-heat :spec="$d['hours']" :line="$d['hoursSummary']" label="Camp visits" />
    </x-module>
    @endif
    @if ($buff)
    <x-module :span="$d['hours'] ? null : 'w12'" :first="! $d['hours']" :last="true" id="h-buffed" heading="Play with Camp Benefits">
      @foreach ($d['buffed'] as $row)
      <dl class="pair one" @if (! $loop->first) style="margin-top:0.9rem" @endif>
        <div><dt>{{ $row['label'] }}</dt><dd @class(['up' => $row['tone'] === 'up'])>{{ $row['value'] }}<small>{{ $row['detail'] }}</small></dd></div>
      </dl>
      @endforeach
    </x-module>
    @endif
    @endif

    @if ($hasMap || $zon)
    @if ($hasMap)
    <x-module :span="$zon ? 'w7' : 'w12'" :first="true" :last="! $zon" id="h-where" :heading="'Where '.$d['who'].' camp'">
      <div class="mapbox" style="aspect-ratio:3/2"><x-world-canvas id="map" label="Camps on the world map{{ $d['heatName'] ? ', in '.$d['heatName'] : '' }}. Drag to move, scroll to zoom." :spec="$map" /></div>
      <p class="note"><a href="/map">Open the world map</a></p>
    </x-module>
    @endif
    @if ($zon)
    <x-module :span="$hasMap ? 'w5' : 'w12'" :first="! $hasMap" :last="true" id="h-zones" heading="Zones with the most camps">
      <x-rank :rows="$d['zones']" />
    </x-module>
    @endif
    @endif

    @if ($doing || $ends)
    @if ($doing)
    <x-module :span="$ends ? 'w7' : 'w12'" :first="true" :last="! $ends" id="h-do" :heading="'What '.$d['who'].' do by the fire'">
      <x-rank :rows="$d['doing']" plain />
    </x-module>
    @endif
    @if ($ends)
    <x-module :span="$doing ? 'w5' : 'w12'" :first="! $doing" :last="true" id="h-end" heading="How a visit ends">
      <x-rank :rows="$d['ends']" />
    </x-module>
    @endif
    @endif

    @if ($d['board'])
    <x-module span="w12" :first="true" :last="true" class="camp-mod" id="h-top" :heading="$own ? 'Your characters by time by the fire' : 'Most time by the fire'">
      <x-table style="margin-top:1.1rem" :columns="[['label' => '#', 'class' => 'rk'], ['label' => 'Character'], ['label' => 'Class'], ['label' => 'Time by the fire', 'numeric' => true], ['label' => 'Ruleset']]" :rows="$d['board']" />
    </x-module>
    @endif
  </x-sheet>
  @endif
</div>
</x-layouts.sc>
