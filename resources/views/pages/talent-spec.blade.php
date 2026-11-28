<x-layouts.sc :title="$d['title'].' talents in WoW Forever'" current="talents" :description="'The talents '.$d['title'].' characters take in WoW Forever and how many take each. '.$d['sub']">
<div class="wrap">
  <x-page-head :title="$d['title']" :rule="false">
    <x-slot:sub><span data-class="{{ $d['classKey'] }}"><i class="dotc" aria-hidden="true"></i>{{ $d['className'] }}</span> · {{ $d['sub'] }}</x-slot:sub>
    <x-slot:filters>
      <x-scope-filters :filters="$filters" />
    </x-slot:filters>
  </x-page-head>
  <x-sheet>
    <x-module span="w12" :first="true" :last="true" id="h-pick" heading="Pick rate by talent">
      <x-slot:controls><x-seg label="Level" :items="$d['brackets']" /></x-slot:controls>
      @if ($d['n'] === 0)
      <x-note>No character has a build at these levels yet.</x-note>
      @else
      @if ($d['columns'] !== [])
      <div class="mt-5 grid gap-x-10 gap-y-8 md:grid-cols-[repeat(var(--trees),minmax(0,1fr))]" style="--trees:{{ count($d['columns']) }}">
        @foreach ($d['columns'] as $column)
          <div>
            <h3 class="sc-h" style="font-size:22px">{{ $column['name'] }}</h3>
            <ol class="rank mt-3">
              @foreach ($column['rows'] as $row)
                <li title="{{ $row['title'] }}"><span class="who">@if ($row['href'])<a href="{{ $row['href'] }}">{{ $row['name'] }}</a>@else{{ $row['name'] }}@endif</span><span class="val">{{ $row['rate'] }}</span><span class="track"><i style="width:{{ $row['width'] }}%"></i></span></li>
              @endforeach
            </ol>
          </div>
        @endforeach
      </div>
      @else
      <x-table :rows="$d['nodes']['rows']" :columns="$d['nodes']['columns']" style="margin-top:1rem" />
      @endif
      @endif
    </x-module>
    @if ($d['order']['rows'])
    <x-module span="w5" :first="true" id="h-order" heading="Most common order">
      <x-table :rows="$d['order']['rows']" :columns="$d['order']['columns']" />
    </x-module>
    @endif
    <x-module :span="$d['order']['rows'] ? 'w7' : 'w12'" :first="! $d['order']['rows']" :last="true" id="h-brackets" heading="Builds by level bracket">
      <x-table :rows="$d['builds']['rows']" :columns="$d['builds']['columns']" />
    </x-module>
    <x-module span="w12" :first="true" :last="true" id="h-outcomes" heading="Builds and outcomes">
      @if ($d['outcomes']['rows'])
      <x-table :rows="$d['outcomes']['rows']" :columns="$d['outcomes']['columns']" />
      @else
      <x-note>No build recorded at these levels yet.</x-note>
      @endif
    </x-module>
  </x-sheet>
</div>
</x-layouts.sc>
