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
  <x-figures :items="$d['figures']" />
  @if ($scope['versus'])<x-scope-compare :compare="$scope['versus']" />@endif
  @if ($d['note'])<x-note>{{ $d['note'] }}</x-note>@endif
  <x-sheet>
    <x-module span="w7" :first="true" class="camp-mod" id="h-ben" :heading="'Camp Benefits '.$d['who'].' leave with'">
      @if ($d['benefits']['rows'])
      <div class="scroll-x"><table class="data benefits" style="margin-top:1.1rem">
        <thead><tr><th>Placed at the camp</th><th>Benefit</th><th class="n">Of Camp Benefits</th><th class="n">Lost to a death</th></tr></thead>
        <tbody>@foreach ($d['benefits']['rows'] as $row)
          <tr><td><span class="buff">@if ($row['icon'])<img src="{{ $row['icon'] }}" alt="" width="26" height="26" loading="lazy">@else<i class="buff-none" aria-hidden="true"></i>@endif{{ $row['label'] }}</span></td><td>{{ $row['effect'] }}</td><td class="n">@if ($row['bar'])<i class="bar" style="width:{{ $row['bar'] }}"></i>@endif{{ $row['share'] ?? '–' }}</td><td class="n">{{ $row['lost'] ?? '–' }}</td></tr>@endforeach
        </tbody></table></div>
      @if ($d['twoHours'])<x-note>{{ $d['twoHours'] }} of Camp Benefits are given for two hours instead of one.</x-note>@endif
      @else
      <x-note>{{ $d['benefits']['note'] }}</x-note>
      @endif
    </x-module>
    <x-module span="w5" :last="true" id="h-set" heading="What gets set up">
      @if ($d['setup'])
      <x-rank :rows="$d['setup']['fires']" />
      <x-rank :rows="$d['setup']['placed']" plain />
      @else
      <x-note>No camp visits recorded yet.</x-note>
      @endif
    </x-module>

    <x-module :first="true" id="h-len" heading="How long a visit lasts">
      @if ($d['lengths'])
      <x-plot id="len" label="Visits by length, as a share of all visits" :spec="$d['lengths']['spec']" />
      <x-note>{{ $d['lengths']['note'] }}</x-note>
      @else
      <x-note>No camp visits recorded yet.</x-note>
      @endif
    </x-module>
    <x-module id="h-lvl" heading="Visits a played hour, by level">
      @if ($d['visitRate'])
      <x-plot id="lvl" label="Camp visits a played hour at each level" :spec="$d['visitRate']['spec']" />
      <x-note>{{ $d['visitRate']['note'] }}</x-note>
      @else
      <x-note>Not enough play time recorded to rate a level.</x-note>
      @endif
    </x-module>
    <x-module :last="true" id="h-rest" heading="Tent rests a played hour, by level">
      @if ($d['restRate'])
      <x-plot id="rest" label="Tent rests a played hour at each level" :spec="$d['restRate']['spec']" />
      <x-note>{{ $d['restRate']['note'] }}</x-note>
      @else
      <x-note>No tent rests recorded yet.</x-note>
      @endif
    </x-module>

    <x-module span="w8" :first="true" id="h-when" :heading="'When '.$d['who'].' camp'">
      @if ($d['hours'])
      <x-hours-grid id="hours" label="Camp visits by weekday and hour of day, in UTC" :spec="$d['hours']" />
      <x-readout id="hours-out" :line="$d['hoursSummary'] ?? ['', '']" />
      <x-heat-key low="Quiet" high="Busiest" :zero="true" extra="Hours are UTC" extra-local="" />
      @else
      <x-note>No camp visits recorded yet.</x-note>
      @endif
    </x-module>
    <x-module :last="true" id="h-buffed" heading="Play with Camp Benefits">
      @if ($d['buffed'])
      <dl class="pair one">
        <div><dt>{{ $d['buffed'][0]['label'] }}</dt><dd @class(['up' => $d['buffed'][0]['tone'] === 'up'])>{{ $d['buffed'][0]['value'] }}<small>against the same levels across all play</small></dd></div>
      </dl>
      <dl class="pair one" style="margin-top:0.9rem">
        @foreach (array_slice($d['buffed'], 1) as $row)
        <div><dt>{{ $row['label'] }}</dt><dd @class(['up' => $row['tone'] === 'up'])>{{ $row['value'] }}</dd></div>
        @endforeach
      </dl>
      @else
      <x-note>No play with Camp Benefits recorded yet.</x-note>
      @endif
    </x-module>

    <x-module span="w7" :first="true" id="h-where" :heading="'Where '.$d['who'].' camp'">
      @if ($map)
      <div class="mapbox" style="aspect-ratio:3/2"><x-world-canvas id="map" label="Camps on the world map{{ $d['heatName'] ? ', in '.$d['heatName'] : '' }}. Drag to move, scroll to zoom." :spec="$map" /></div>
      <p class="note"><a href="/map">Open the world map</a></p>
      @else
      <x-note>No camps placed on a map yet.</x-note>
      @endif
    </x-module>
    <x-module span="w5" :last="true" id="h-zones" heading="Zones with the most camps">
      @if ($d['zones'])<x-rank :rows="$d['zones']" />@else<x-note>No camp visits with a known zone yet.</x-note>@endif
    </x-module>

    <x-module span="w7" :first="true" id="h-do" :heading="'What '.$d['who'].' do by the fire'">
      @if ($d['doing'])<x-rank :rows="$d['doing']" plain />@else<x-note>No camp activity recorded yet.</x-note>@endif
    </x-module>
    <x-module span="w5" :last="true" id="h-end" heading="How a visit ends">
      @if ($d['ends'])<x-rank :rows="$d['ends']" />@else<x-note>No camp visits recorded yet.</x-note>@endif
    </x-module>

    <x-module span="w12" :first="true" :last="true" class="camp-mod" id="h-top" :heading="$own ? 'Your characters by time by the fire' : 'Most time by the fire'">
      @if ($d['board'])
      <x-table style="margin-top:1.1rem" :columns="[['label' => '#', 'class' => 'rk'], ['label' => 'Character'], ['label' => 'Class'], ['label' => 'Time by the fire', 'numeric' => true], ['label' => 'Ruleset']]" :rows="$d['board']" />
      @else
      <x-note>{{ $own ? 'No time by the fire recorded yet.' : 'No public characters have time by the fire yet.' }}</x-note>
      @endif
    </x-module>
  </x-sheet>
</div>
</x-layouts.sc>
