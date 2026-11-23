<x-layouts.sc :title="$d['name'].($d['makes'] !== null ? ': what it makes and needs' : ': cast time, range and cost')" :description="trim($d['sub'].' '.($d['makes'] !== null ? 'What '.$d['name'].' makes and needs in WoW Forever.' : 'Cast time, range and cost of '.$d['name'].' in WoW Forever.'))">
<div class="wrap">
  <x-page-head :title="$d['name']" :single="true">
    <x-slot:sub>{{ $d['sub'] }}</x-slot:sub>
  </x-page-head>
  @if ($d['figures'])
  <x-figures :items="$d['figures']" />
  @endif
  <x-sheet>
    @if ($d['makes'] !== null)
    <x-module span="w12" :first="true" :last="true" id="h-r" heading="What it makes and needs">
      @if ($d['makes'] !== '')
      <p style="margin-top:1.1rem">Makes @if ($d['makesHref'])<a href="{{ $d['makesHref'] }}">{{ $d['makes'] }}</a>@else{{ $d['makes'] }}@endif.</p>
      @endif
      @if ($d['reagents'])
      <x-table :rows="$d['reagents']" :columns="[['label' => 'Reagent'], ['label' => 'Count', 'numeric' => true]]" style="margin-top:1.1rem" />
      @else
      <x-note>No reagents are recorded for this recipe.</x-note>
      @endif
      @if ($d['optional'])
      <p class="note">{{ \App\View\Format::count($d['optional'], 'slot') }} may stay empty.</p>
      @endif
    </x-module>
    @elseif (! $d['figures'])
    <x-module span="w12" :first="true" :last="true" id="h-n" heading="Facts">
      <x-note>Nothing is recorded about this spell yet.</x-note>
    </x-module>
    @endif
  </x-sheet>
</div>
</x-layouts.sc>
