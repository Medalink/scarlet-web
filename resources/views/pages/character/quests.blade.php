<x-module :span="$span" :first="$first" :last="$last" :level="$level" id="h-quests" heading="Quests">
  <div class="pair">
    <div>
      <p class="sub">{{ $d['quests']['total'] }}</p>
      <x-rank :rows="$d['quests']['zones']" />
      @if ($d['quests']['others'])<p class="total">{{ $d['quests']['others'] }}</p>@endif
    </div>
    @if ($d['quests']['spec'])
    <div>
      <p class="sub">Turned in at each level</p>
      <x-plot id="quests" label="Quests {{ $d['name'] }} turned in at each level." :spec="$d['quests']['spec']" />
    </div>
    @endif
  </div>
</x-module>
