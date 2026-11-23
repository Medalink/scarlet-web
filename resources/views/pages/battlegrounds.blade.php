<x-layouts.app :title="'Battlegrounds of '.$d['name']" :description="'Scoreboards of the battlegrounds '.$d['name'].' played in WoW Forever.'" :canonical="$d['path'].'/battlegrounds'" :index="! $d['owner']">
<div class="wrap">
  <x-page-head :title="'Battlegrounds of '.$d['name']">
    @unless ($d['links']->classPage || $d['links']->battlegrounds)
    <x-slot:sub><a href="{{ $d['path'] }}">Back to {{ $d['name'] }}</a></x-slot:sub>
    @endunless
  </x-page-head>
  <x-class.nav :links="$d['links']" :name="$d['name']" :class-name="$d['className']" current="battlegrounds" />
  <x-sheet>
    @forelse ($d['matches'] as $match)
    <x-module :first="$loop->first" :last="$loop->last" :id="'m-'.$loop->index" :heading="$match['heading']">
      <p class="note"><time datetime="{{ $match['instant'] }}" data-local="j M Y H:i">{{ $match['at'] }}</time></p>
      <x-table :columns="$columns" :rows="$match['rows']" />
    </x-module>
    @empty
    <x-module :first="true" :last="true" id="m-none" heading="No scoreboards">
      <x-note>No battleground scoreboards recorded for this character yet.</x-note>
    </x-module>
    @endforelse
  </x-sheet>
</div>
</x-layouts.app>
