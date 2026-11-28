<x-layouts.sc :title="$d['title'].': WoW Forever levelling stats'" current="stats" description="How characters level in WoW Forever: time to each level, deaths, dungeons, skills and where people play." :canonical="$canonical">
<div class="wrap pb-20">
  <x-page-head :title="$d['headline'] ?? $d['title']">
    <x-slot:sub>{{ $d['title'] }}. {{ $d['sub'] }}</x-slot:sub>
    <x-slot:filters><x-scope-switch :choice="$scope['choice']" :path="$path" :names="$scope['names']" :as="$scope['as']" :query="$scope['query']" :mine="false" /><x-scope-filters :filters="$filters" /><a class="text-[14px]" href="/stats/compare">Compare rulesets</a></x-slot:filters>
  </x-page-head>
  <x-tally :items="$d['game']['tally']" />
  @if ($scope['versus'])<x-scope-compare :compare="$scope['versus']" />@endif
  <x-sc.section-nav class="mt-8" :sections="array_filter(['levelling' => 'Levelling', 'danger' => 'Danger', 'deaths' => $deaths ? 'Deaths' : null, 'dungeons-skills' => 'Dungeons and skills', 'who-plays' => 'Who plays', 'gold-time' => 'Gold and time', 'world' => 'The world'])" />
  <x-sheet>
    @if ($d['game']['records'])
    <x-module span="w12" :first="true" :last="true" id="h-records" heading="Records across every character">
      <x-rec-grid :items="$d['game']['records']" />
    </x-module>
    @endif
    @if ($d['game']['rules'])
    <x-module span="w12" :first="true" :last="true" id="h-rules" heading="The next round numbers">
      <x-rules :rows="$d['game']['rules']" />
    </x-module>
    @endif

    <h2 class="sect" id="levelling">Levelling</h2>
    @if ($d['game']['ascent'])
    <x-module span="w12" :first="true" :last="true" class="plate" id="h-climb" :heading="'Where every character stands on the way to '.$d['game']['cap']">
      <x-ascent :spec="$d['game']['ascent']" />
      <p class="note">The ridge is the median played time to reach each level. A flag marks a level where characters are now, with how many.</p>
    </x-module>
    @endif

    <x-module span="w8" :first="true" id="h-curve" heading="Played time to reach each level">
      @if ($d['curve'])
      <x-plot id="curve" label="Median played hours to reach each level, with the middle half of characters as a band" :spec="$d['curve']['spec']" />
      <x-key :items="[['label' => 'Median', 'class' => 'k-line', 'style' => 'background:var(--accent)'], ['label' => 'Middle half of characters', 'style' => 'background:rgba(255,45,85,.3)'], ['label' => 'Fastest tenth', 'class' => 'k-line', 'style' => 'background:var(--fg)']]" />
      <x-readout id="curve-out" :line="$d['curve']['line']" />
      @else
      <x-note>No levelling recorded yet.</x-note>
      @endif
    </x-module>
    <x-module :last="true" id="h-reach" heading="Characters that reached each level">
      @if ($d['reach'])
      <x-plot id="reach" label="How many characters reached each level" :spec="$d['reach']" />
      @else
      <x-note>No levelling recorded yet.</x-note>
      @endif
    </x-module>

    <x-module span="w8" :first="true" id="h-xp" heading="Where experience comes from, by level">
      @if ($d['xp'])
      <x-shares-chart id="xp" label="Share of experience from kills, dungeon kills, quests, exploring and other, one bar per level." :spec="$d['xp']['spec']" :y="['100%', '50%', '0']" :x="$d['xp']['x']" :line="$d['xp']['line']"
        :key="[['class' => 'c-kill', 'label' => 'Kills'], ['class' => 'c-dungeon', 'label' => 'Dungeon kills'], ['class' => 'c-quest', 'label' => 'Quests'], ['class' => 'c-explore', 'label' => 'Exploring'], ['class' => 'c-other', 'label' => 'Other']]" />
      @else
      <x-note>No experience recorded yet.</x-note>
      @endif
    </x-module>
    <x-module :last="true" id="h-time" heading="Hours spent in each level">
      @if ($d['ltime'])
      <x-plot id="ltime" label="Median hours a character spends in each level" :spec="$d['ltime']['chart']" />
      <x-note>{{ $d['ltime']['note'] }}</x-note>
      @else
      <x-note>No completed levels recorded yet.</x-note>
      @endif
    </x-module>

    <x-module span="w12" :first="true" :last="true" id="h-cap" :heading="$d['cap']['heading']">
      @if ($d['cap']['rows'])
      <x-table :columns="[['label' => 'Measure'], ['label' => 'Total', 'numeric' => true], ['label' => 'Based on', 'numeric' => true]]" :rows="$d['cap']['rows']" />
      @else
      <x-note>{{ $d['cap']['note'] }}</x-note>
      @endif
    </x-module>

    <h2 class="sect" id="danger">Danger</h2>
    <x-module :first="true" id="h-deaths" heading="Deaths per hour, by level">
      @if ($d['dph'])
      <x-plot id="dph" label="Deaths per played hour at each level" :spec="$d['dph']['chart']" />
      <x-note>{{ $d['dph']['note'] }}</x-note>
      @else
      <x-note>Not enough play time recorded to rate a level.</x-note>
      @endif
    </x-module>
    <x-module id="h-killers" heading="What kills characters">
      @if ($d['killers'])<x-rank :rows="$d['killers']" />@else<x-note>No deaths with a known killer yet.</x-note>@endif
    </x-module>
    <x-module :last="true" id="h-dz" heading="Where characters die">
      @if ($d['deathZones'])<x-rank :rows="$d['deathZones']" plain />@else<x-note>No deaths with a known place yet.</x-note>@endif
    </x-module>

    @if ($deaths)
    <h2 class="sect" id="deaths">Deaths</h2>
    <x-module :first="true" id="h-death-levels" heading="Hardcore deaths by level">
      @if ($deaths['levels'])
      <x-plot id="levels" label="Hardcore deaths at each level" :spec="$deaths['levels']" />
      @else
      <x-note>No death with a level recorded yet.</x-note>
      @endif
    </x-module>
    <x-module :last="true" id="h-death-days" heading="Hardcore deaths by day">
      @if ($deaths['days'])
      <x-plot id="days" label="Hardcore deaths on each of the last {{ \App\View\Pages\HardcoreDeathsPage::DAYS }} days, in UTC" :spec="$deaths['days']" />
      @else
      <x-note>No hardcore deaths recorded yet.</x-note>
      @endif
    </x-module>
    @endif

    <h2 class="sect" id="dungeons-skills">Dungeons and skills</h2>
    <x-module span="w7" :first="true" id="h-dung" heading="Dungeons">
      @if ($d['dungeons'])
      <x-table style="margin-top:1.1rem" :columns="[['label' => 'Dungeon'], ['label' => 'Runs', 'numeric' => true], ['label' => 'Median time', 'numeric' => true], ['label' => 'Deaths a run', 'numeric' => true], ['label' => 'Bosses a run', 'numeric' => true]]" :rows="$d['dungeons']" />
      @else
      <x-note>No dungeon runs recorded yet.</x-note>
      @endif
    </x-module>
    <x-module span="w5" :last="true" id="h-prof" heading="Skills">
      @if ($d['professions'])<x-rank :rows="$d['professions']" />@else<x-note>No skill-ups recorded yet.</x-note>@endif
    </x-module>

    <h2 class="sect" id="who-plays">Who plays</h2>
    <x-module span="w7" :first="true" id="h-classes" heading="Classes played">
      @if ($d['game']['classes'])
      <ol class="rank">@foreach ($d['game']['classes'] as $row)<li data-class="{{ $row['class'] }}"><span class="who">{{ $row['name'] }}</span><span class="val">{{ $row['value'] }}</span><span class="track"><i class="cl" style="width:{{ $row['share'] }}%"></i></span></li>@endforeach</ol>
      @else
      <x-note>{{ \App\View\Format::TOO_FEW }}.</x-note>
      @endif
    </x-module>
    <x-module span="w5" :last="true" id="h-factions" heading="Horde and Alliance">
      @if ($d['game']['split'])
      <x-split :split="$d['game']['split']" />
      @else
      <x-note>{{ \App\View\Format::TOO_FEW }}.</x-note>
      @endif
    </x-module>

    <x-module span="w12" :first="true" :last="true" id="h-talents" heading="Talents">
      @if ($talents['rows'])
      <x-table :columns="$talents['columns']" :rows="$talents['rows']" />
      <p class="note">{{ \App\View\Format::count($talents['total'], 'character') }} with a talent build. <a href="/talents">All specs</a></p>
      @else
      <x-note>No talent builds recorded yet.</x-note>
      @endif
    </x-module>

    <h2 class="sect" id="gold-time">Gold and time</h2>
    <x-module span="w5" :first="true" id="h-gold" heading="Gold earned and spent, by level">
      @if ($d['gold'])
      <x-plot id="gold" label="Gold a character earned and spent at each level" :spec="$d['gold']" />
      <x-key :items="[['label' => 'Earned', 'class' => 'k-line', 'style' => 'background:var(--gold)'], ['label' => 'Spent', 'class' => 'k-line', 'style' => 'background:var(--fg)']]" />
      <x-note>Characters who shared their economy data only.</x-note>
      @else
      <x-note>No economy data shared yet.</x-note>
      @endif
    </x-module>
    <x-module span="w7" :last="true" id="h-when" heading="When people play">
      @if ($d['hours'])
      <x-week-heat :spec="$d['hours']" :line="$d['hoursSummary']" />
      @else
      <x-note>No play time recorded yet.</x-note>
      @endif
    </x-module>

    <h2 class="sect" id="world">The world</h2>
    <x-module span="w12" :first="true" :last="true" id="h-map" heading="Where characters go">
      @if ($d['zones'])
      <x-slot:controls><x-pick label="Zone" fit="map" :options="array_merge([['All zones', '']], array_map(fn ($zone) => [$zone['name'], $zone['id']], $d['zones']))" /></x-slot:controls>
      @endif
      <div class="mapbox" style="aspect-ratio:21/9"><x-world-canvas id="map" label="Heat from every recorded path in the world. Drag to move, scroll to zoom." :spec="$map" /></div>
      <x-density-key low="Few paths" high="Many" />
      <p class="note"><a href="/map">Open the world map</a></p>
    </x-module>
  </x-sheet>
</div>
</x-layouts.sc>
