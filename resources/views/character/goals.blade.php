@php($g = $d['goals'])
@if ($g['level'] || $g['skills'] || $g['reputation'] || $g['records'])
  <div id="goals" class="grid grid-cols-[minmax(0,1fr)] gap-10 lg:grid-cols-2">
    @if ($g['level'])
      <div id="goals-level">
        <h2 class="sc-h" style="font-size:30px">Level</h2>
        <x-sc.bars class="mt-4" :rows="$g['level']['rows']" :of="100" />
      </div>
    @endif
    @if ($g['skills'])
      <div id="goals-skills">
        <h2 class="sc-h" style="font-size:30px">Skills</h2>
        @foreach ($g['skills'] as $group)
          <p class="sc-label mt-4">{{ $group['title'] }}</p>
          <x-sc.bars class="mt-3" :rows="$group['rows']" :of="100" />
        @endforeach
      </div>
    @endif
    @if ($g['reputation'])
      <div id="goals-reputation" @class(['lg:col-span-2' => $g['reputationWide']])>
        <h2 class="sc-h" style="font-size:30px">Reputation</h2>
        <p class="sc-sub mt-3">{{ $g['reputation']['summary'] }}</p>
        @if ($g['reputation']['closest'] === [])
          <p class="sc-note mt-4">All exalted</p>
        @else
          <x-sc.bars class="mt-4" :rows="$g['reputation']['closest']" :of="100" />
        @endif
        @if ($g['reputation']['rest'] !== [])
          <details class="mt-4">
            <summary class="sc-btn quiet small">Show all {{ $g['reputation']['count'] }}</summary>
            <x-sc.bars class="mt-4" :rows="$g['reputation']['rest']" :of="100" />
          </details>
        @endif
      </div>
    @endif
    @if ($g['records'])
      <div id="goals-records" class="lg:col-span-2">
        <h2 class="sc-h" style="font-size:30px">Records</h2>
        <x-sc.tiles class="mt-4" :cols="$g['records']['cols']">
          @foreach ($g['records']['tiles'] as $tile)
            <x-sc.tile :label="$tile['label']" :value="$tile['value']" :sub="$tile['sub']" />
          @endforeach
        </x-sc.tiles>
      </div>
    @endif
  </div>
@endif
