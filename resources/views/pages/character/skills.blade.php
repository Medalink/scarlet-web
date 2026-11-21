<x-module :span="$span" :first="$first" :last="$last" :level="$level" id="h-skills" heading="Skills">
  @if ($d['skills']['rows'] || $d['crafted'])
  @if ($d['skills']['steps'])
  <x-plot id="skills" label="Rank of each profession of {{ $d['name'] }} over its played time. Use the left and right arrow keys to read the ranks at a played time." :spec="$d['skills']['steps']['spec']" />
  <x-readout id="skills-out" :line="$d['skills']['steps']['line']" />
  @endif
  <div @class(['pair' => $span === 'w12' || $d['skills']['steps'], 'steps' => $d['skills']['steps']])>
    @if ($d['skills']['rows'])<div><x-rank :rows="$d['skills']['rows']" /></div>@endif
    @if ($d['crafted'])<div>
      <p @class(['sub', 'gap' => $d['skills']['rows'] && $span !== 'w12' && ! $d['skills']['steps']])>Most crafted{{ $d['since'] }}</p>
      <x-rank :rows="$d['crafted']" />
    </div>@endif
  </div>
  @else
  <x-note>No skills recorded.</x-note>
  @endif
</x-module>
