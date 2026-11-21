<x-layouts.app :title="$d['name'].': a Scarlet character overview'" current="search" description="Levelling pace, experience, dungeons, deaths and play history of {{ $d['name'] }}, from what the player chose to upload.">
<section class="ch-head" aria-labelledby="h1">
  <div class="stage"><x-world-canvas id="map" label="Every path {{ $d['name'] }} walked, with deaths as crosses. Drag to move, scroll to zoom." :spec="$d['map']" /></div>
  <div class="ch-id wrap" style="padding-top:3rem">
    <div><h1 class="ch-name" id="h1">{{ $d['name'] }}</h1>
      <p class="ch-sub"><i class="dot" aria-hidden="true"></i>@foreach ($d['facts'] as $fact)<span>{{ $fact }}</span>@endforeach</p></div>
    <ul class="legend ch-map" aria-label="Map marks">
      <li><svg viewBox="0 0 24 14" aria-hidden="true"><path d="M1 7h22" stroke="#ff2d55" stroke-width="2"/></svg>every path walked</li>
      @if ($d['deathCount'])<li><svg viewBox="0 0 24 14" aria-hidden="true"><path d="M7.5 2.5l9 9M16.5 2.5l-9 9" stroke="#f0f1f3" stroke-width="1.7" stroke-linecap="round"/></svg>death</li>@endif
    </ul>
  </div>
</section>
<div class="wrap">
  @if ($d['owner'])<livewire:owner-bar :character-id="$d['id']" />@endif
  @if ($d['week'])
  <section class="week" aria-labelledby="h-week">
    <div><h2 id="h-week">This week</h2><p>{{ $d['week']['range'] }}</p></div>
    @foreach ($d['week']['figures'] as $figure)<dl><dt>{{ $figure['label'] }}</dt><dd>{{ $figure['value'] }}</dd></dl>@endforeach
  </section>
  @endif
  <x-sheet>
    <x-module :span="$d['gold'] ? 'w8' : 'w12'" :first="true" :last="! $d['gold']" id="h-pace" :heading="$d['paceTitle']">
      @if ($d['pace'])
      <x-plot id="pace" :label="$d['paceLabel']" :spec="$d['pace']['spec']" />
      <x-key :items="$d['pace']['key']" />
      <x-readout id="pace-out" :line="$d['pace']['line']" />
      @else
      <x-note>Fewer than two levels are recorded.</x-note>
      @endif
    </x-module>
    @if ($d['gold'])
    <x-module :last="true" id="h-gold">
      <h2 id="h-gold">Gold <span class="priv">{{ $d['owner'] ? 'Only you' : 'Shared by the owner' }}</span></h2>
      <p class="big"><span class="money">{{ $d['gold']['balance'] }}</span></p>
      <p class="sub">{{ $d['gold']['change'] }}</p>
      @if ($d['gold']['spec'])<x-plot id="gold" label="Balance at the end of each of the last sessions" :spec="$d['gold']['spec']" />@endif
    </x-module>
    @endif

    <x-module span="w8" :first="true" id="h-xp" heading="Experience by level">
      @if ($d['xp'])
      <x-shares-chart id="xp" label="Share of experience from kills, dungeon kills, quests, exploring and other, one bar per level." :spec="$d['xp']['spec']" :y="['100%', '50%', '0']" :x="$d['xp']['x']" :line="$d['xp']['line']"
        :key="[['class' => 'c-kill', 'label' => 'Kills'], ['class' => 'c-dungeon', 'label' => 'Dungeon kills'], ['class' => 'c-quest', 'label' => 'Quests'], ['class' => 'c-explore', 'label' => 'Exploring'], ['class' => 'c-other', 'label' => 'Other']]" />
      @else
      <x-note>No experience recorded yet.</x-note>
      @endif
    </x-module>
    <x-module :last="true" id="h-zones" heading="Hours by zone">
      @if ($d['zoneHours']['rows'])
      <x-rank :rows="$d['zoneHours']['rows']" />
      @if ($d['zoneHours']['others'])<p class="total">{{ $d['zoneHours']['others'] }}</p>@endif
      @else
      <x-note>No play time recorded yet.</x-note>
      @endif
    </x-module>

    <x-module span="w12" :first="true" :last="true" id="h-deaths" heading="Deaths by level">
      @if ($d['deaths']['strip'])
      <x-level-strip :cells="$d['deaths']['strip']" label="Deaths at each level" />
      <x-table :rows="$d['deaths']['rows']" :columns="$d['deaths']['columns']" style="margin-top:1.4rem" />
      @endif
      <p class="total">{{ $d['deaths']['total'] }}</p>
    </x-module>

    <x-module span="w8" :first="true" id="h-items" heading="Items found">
      @if ($d['items'])
      <x-stack-bar :segments="$d['items']['segments']" :label="$d['items']['say']" />
      <x-key :items="$d['items']['key']" />
      @if ($d['items']['best'])<x-rank :rows="$d['items']['best']" />@endif
      <p class="total">{{ $d['items']['total'] }}</p>
      @else
      <x-note>No looted items recorded.</x-note>
      @endif
    </x-module>
    <x-module :last="true" id="h-skills" heading="Skills">
      @if ($d['skills'])
      <x-rank :rows="$d['skills']" />
      @else
      <x-note>No skills recorded.</x-note>
      @endif
    </x-module>

    <x-module :first="true" id="h-runs" heading="Dungeons and raids">
      @if ($d['dungeons'])
      <x-rank :rows="$d['dungeons']" plain />
      <p class="total">{{ $d['dungeonTotal'] }}</p>
      @else
      <x-note>No dungeon runs recorded.</x-note>
      @endif
    </x-module>
    <x-module span="w8" :last="true" id="h-days" heading="Play time by day">
      @if ($d['days'])
      <x-day-heat id="heat" :spec="$d['days']['spec']" />
      <x-heat-key low="0" high="over 4h" />
      <x-readout id="days-out" :line="$d['days']['line']" />
      @else
      <x-note>No play time recorded yet.</x-note>
      @endif
      @if ($d['distance'])<p class="total">{{ $d['distance'] }}.</p>@endif
    </x-module>

    <x-module span="w12" :first="true" :last="true" id="h-hist" heading="Play history">
      @if ($d['sessions']['rows'])
      <x-table :rows="$d['sessions']['rows']" :columns="$d['sessions']['columns']" />
      @if ($d['more'])<p class="note"><a href="{{ $d['more'] }}">Show more sessions</a></p>@endif
      @else
      <x-note>No sessions recorded yet.</x-note>
      @endif
    </x-module>
  </x-sheet>
</div>
</x-layouts.app>
