{{-- The Threat module of a creature or zone page: a seal, what it says, and a gauge of the five steps with the rate
     marked. threat: App\View\Pages\RecordThreat. The gauge repeats the sentence beside it, so it is hidden from
     assistive technology. --}}
@props(['threat'])
<x-module span="w12" :first="true" :last="true" id="h-threat" heading="Threat">
  <div class="feat threat t{{ $threat['tier'] }}">
    <span class="seal"><x-icon name="deaths" /></span>
    <div><p class="feat-n">{{ $threat['name'] }}</p><p class="feat-d">{{ $threat['detail'] }}</p></div>
    <ol class="gauge" style="--at:{{ $threat['at'] }}%" aria-hidden="true">@foreach ($threat['steps'] as $i => $step)<li @class(['on' => $i === $threat['tier']])><b>{{ $step['name'] }}</b><span>{{ $step['from'] }}</span></li>@endforeach</ol>
  </div>
</x-module>
