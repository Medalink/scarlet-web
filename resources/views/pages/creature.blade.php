<x-layouts.sc :title="$d['name'].': kills, drops and where it is found'" :description="trim($d['sub'].' Kills, deaths, drops and where players met '.$d['name'].' in WoW Forever.')">
<div class="wrap">
  <x-back href="/loot" label="Loot" />
  <x-page-head :title="$d['name']">
    <x-slot:sub>{{ $d['sub'] }}</x-slot:sub>
    <x-slot:filters><x-scope-filters :filters="$d['filters']" /></x-slot:filters>
  </x-page-head>
  @if ($d['figures'])<x-figures :items="$d['figures']" :five="true" />@endif
  <x-sheet>
    @if ($d['threat'])
    <x-threat :threat="$d['threat']" />
    @endif
    @if ($d['drops'] || $d['unread'])
    <x-module span="w12" :first="true" :last="true" id="h-d" heading="Drops">
      @if ($d['rules'])
      <x-slot:controls><x-seg label="Loot rule" :items="$d['rules']" /></x-slot:controls>
      @endif
      @if ($d['drops'])
      <x-table :rows="$d['drops']" :columns="$d['dropColumns']" style="margin-top:1.1rem" />
      @if ($d['tags'] || $d['unread'])
      <p class="note">@foreach ($d['tags'] as $tag)<span class="tag">{{ $tag }}</span> @endforeach @if ($d['unread']){{ $d['unread'] }}@endif</p>
      @endif
      @if ($d['dropWorth'])
      <x-loot-worth :worth="$d['dropWorth']" noun="corpse" />
      @endif
      @else
      <x-note>{{ $d['unread'] }}</x-note>
      @endif
    </x-module>
    @endif
    @if ($d['byLevel'] || $d['hasMap'])
    <x-module :span="$d['hasMap'] ? 'w7' : 'w12'" :first="true" :last="! $d['hasMap']" id="h-l" heading="Deaths to it, by the character's level">
      @if ($d['byLevel'])
      <x-plot id="by-level" label="Deaths to this creature at each character level" :spec="$d['byLevel']" />
      @endif
    </x-module>
    @endif
    @if ($d['hasMap'])
    <x-module :span="$d['byLevel'] ? 'w5' : 'w12'" :first="! $d['byLevel']" :last="true" id="h-w" heading="Where it was met">
      <div class="mapbox" style="aspect-ratio:1 / 1"><x-world-canvas id="map" label="Where players met this creature. Drag to move, scroll to zoom." :spec="$d['map']" /></div>
      @unless ($d['dots'])<x-density-key low="Few" high="Many characters" />@endunless
    </x-module>
    @endif
    @if ($d['skinning'])
    <x-module span="w12" :first="true" :last="true" id="h-k" heading="Skinning">
      <x-table :rows="$d['skinning']" :columns="$d['skinColumns']" style="margin-top:1.1rem" />
      @if ($d['skinWorth'])
      <x-loot-worth :worth="$d['skinWorth']" noun="skin" />
      @endif
    </x-module>
    @endif
    @if ($d['ruleArms'] && $d['ruleArms']['rows'])
    <x-module span="w12" :first="true" :last="true" id="h-r" :heading="$d['ruleArms']['heading']">
      <x-arms :chart="$d['ruleArms']" />
    </x-module>
    @endif
    @if ($d['sizeArms'] && $d['sizeArms']['rows'])
    <x-module span="w12" :first="true" :last="true" id="h-z" :heading="$d['sizeArms']['heading']">
      <x-arms :chart="$d['sizeArms']" />
    </x-module>
    @endif
  </x-sheet>
</div>
</x-layouts.sc>
