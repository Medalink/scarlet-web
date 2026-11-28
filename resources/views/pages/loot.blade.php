<x-layouts.sc title="Loot: what creatures and objects give and how often" description="What WoW Forever players looted, from which creatures, objects and containers, and how often each item dropped, with the counts behind every rate." current="loot">
<div class="wrap">
  <x-page-head title="Loot" :single="true">
    <x-slot:sub>{{ $d['sub'] }}</x-slot:sub>
  </x-page-head>
  @if ($d['figures'])<x-figures :items="$d['figures']" />@endif
  <x-sheet>
    <x-module span="w12" :first="true" :last="true" id="h-s" :heading="$d['heading']">
      <x-slot:controls><x-seg label="Show" :items="$d['views']" /></x-slot:controls>
      @php($f = $d['form'])
      <form class="refine" role="search" action="/loot" method="get">
        @if ($f['view'])<input type="hidden" name="view" value="{{ $f['view'] }}">@endif
        <label class="vh" for="f-q">Search</label><input id="f-q" name="q" type="search" value="{{ $f['text'] }}" placeholder="{{ $f['placeholder'] }}" maxlength="60">
        <select class="pick" name="zone" aria-label="Zone" data-autosubmit><option value="">Every zone</option>@foreach ($f['zones'] as $zone)<option value="{{ $zone['value'] }}" @selected($zone['value'] === $f['zone'])>{{ $zone['label'] }}</option>@endforeach</select>
        @if ($f['levels'])<span class="lvl" role="group" aria-label="Level"><span>Level</span><input name="min" type="number" min="-1" max="80" value="{{ $f['min'] }}" aria-label="Level from" data-autosubmit><span>to</span><input name="max" type="number" min="-1" max="80" value="{{ $f['max'] }}" aria-label="Level to" data-autosubmit></span>@endif
        <select class="pick" name="quality" aria-label="Quality" data-autosubmit><option value="">Any quality</option>@foreach ($f['qualities'] as $quality)<option value="{{ $quality['value'] }}" @selected($quality['value'] === $f['quality'])>{{ $quality['label'] }}</option>@endforeach</select>
        <label class="lvl"><span>Sort</span><select class="pick" name="sort" data-autosubmit>@foreach ($f['sorts'] as $sort)<option value="{{ $sort['value'] }}" @selected($sort['value'] === $f['sort'])>{{ $sort['label'] }}</option>@endforeach</select></label>
        <noscript><button class="sc-btn quiet" type="submit">Apply</button></noscript>
      </form>
      @if ($d['rows'])
      <x-table :rows="$d['rows']" :columns="$d['columns']" style="margin-top:1.1rem" />
      @if ($d['prev'] || $d['next'])<p class="pager">@if ($d['prev'])<a href="{{ $d['prev'] }}">Previous {{ $d['step'] }}</a>@endif <span class="mut">{{ $d['range'] }}</span> @if ($d['next'])<a href="{{ $d['next'] }}">Next {{ $d['step'] }}</a>@endif</p>@endif
      @if ($d['fishing'])<p class="note">{{ $d['fishing'] }} are on <a href="/professions/fishing">Fishing</a>.</p>@endif
      @else
      <x-note>{{ $d['empty'] }}@if ($d['fishing']) {{ $d['fishing'] }} are on <a href="/professions/fishing">Fishing</a>.@endif</x-note>
      @endif
    </x-module>
    @if ($d['zones'])
    <x-module :span="$d['rules'] ? 'w7' : 'w12'" :first="true" :last="! $d['rules']" id="h-z" heading="Zones">
      <x-rank :rows="$d['zones']" plain still />
    </x-module>
    @endif
    @if ($d['rules'])
    <x-module :span="$d['zones'] ? 'w5' : 'w12'" :first="! $d['zones']" :last="true" id="h-r" heading="Loot rule and group size">
      <x-rank :rows="$d['rules']" plain still />
    </x-module>
    @endif
  </x-sheet>
</div>
</x-layouts.sc>
