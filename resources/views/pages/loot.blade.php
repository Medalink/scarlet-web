<x-layouts.app title="Loot: what creatures and objects give and how often" description="What WoW Forever players looted, from which creatures, objects and containers, and how often each item dropped, with the counts behind every rate." current="loot">
<div class="wrap">
  <x-page-head title="Loot" :single="true">
    <x-slot:sub>{{ $d['sub'] }}</x-slot:sub>
  </x-page-head>
  <x-figures :items="$d['figures']" />
  <x-sheet>
    <x-module span="w12" :first="true" :last="true" id="h-s" :heading="$d['heading']">
      <x-slot:controls><x-seg label="Show" :items="$d['views']" /></x-slot:controls>
      @php($f = $d['form'])
      <form class="refine" role="search" action="/loot" method="get">
        @if ($f['view'])<input type="hidden" name="view" value="{{ $f['view'] }}">@endif
        <label class="vh" for="f-q">Search</label><input id="f-q" name="q" type="search" value="{{ $f['text'] }}" placeholder="{{ $f['placeholder'] }}" maxlength="60">
        <select class="pick" name="zone" aria-label="Zone"><option value="">Every zone</option>@foreach ($f['zones'] as $zone)<option value="{{ $zone['value'] }}" @selected($zone['value'] === $f['zone'])>{{ $zone['label'] }}</option>@endforeach</select>
        @if ($f['levels'])<input name="min" type="number" min="-1" max="80" value="{{ $f['min'] }}" placeholder="Level from" aria-label="Level from"><input name="max" type="number" min="-1" max="80" value="{{ $f['max'] }}" placeholder="to" aria-label="Level to">@endif
        <select class="pick" name="quality" aria-label="Quality"><option value="">Any quality</option>@foreach ($f['qualities'] as $quality)<option value="{{ $quality['value'] }}" @selected($quality['value'] === $f['quality'])>{{ $quality['label'] }}</option>@endforeach</select>
        <select class="pick" name="sort" aria-label="Sort by">@foreach ($f['sorts'] as $sort)<option value="{{ $sort['value'] }}" @selected($sort['value'] === $f['sort'])>{{ $sort['label'] }}</option>@endforeach</select>
        <button class="btn quiet" type="submit">Apply</button>
      </form>
      @if ($d['rows'])
      <x-table :rows="$d['rows']" :columns="$d['columns']" style="margin-top:1.1rem" />
      @if ($d['prev'] || $d['next'])<p class="pager">@if ($d['prev'])<a href="{{ $d['prev'] }}">Previous {{ $d['step'] }}</a>@endif @if ($d['next'])<a href="{{ $d['next'] }}">Next {{ $d['step'] }}</a>@endif</p>@endif
      <p class="note">{{ $d['note'] }}@if ($d['fishing']) {{ $d['fishing'] }} are on <a href="/professions/fishing">Fishing</a>.@endif</p>
      @else
      <x-note>{{ $d['empty'] }}@if ($d['fishing']) {{ $d['fishing'] }} are on <a href="/professions/fishing">Fishing</a>.@endif</x-note>
      @endif
    </x-module>
    <x-module span="w7" :first="true" id="h-z" heading="Zones">
      @if ($d['zones'])
      <x-rank :rows="$d['zones']" plain still />
      @else
      <x-note>No zone is recorded yet.</x-note>
      @endif
    </x-module>
    <x-module span="w5" :last="true" id="h-r" heading="Loot rule and group size">
      @if ($d['rules'])
      <x-rank :rows="$d['rules']" plain still />
      @else
      <x-note>No loot rule is recorded yet.</x-note>
      @endif
    </x-module>
  </x-sheet>
</div>
</x-layouts.app>
