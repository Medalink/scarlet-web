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
      @if ($d['trees'] !== [])
      <div class="tt-trees mt-5">
        @foreach ($d['trees'] as $tree)
          <div class="tt-tree" data-tree>
            <h3 class="sc-h" style="font-size:22px">{{ $tree['name'] }}</h3>
            <div class="tt-scroll">
              <div class="tt-frame" style="width:{{ $tree['width'] }}px;height:{{ $tree['height'] }}px">
                <svg class="tt-lines" width="{{ $tree['width'] }}" height="{{ $tree['height'] }}" aria-hidden="true">
                  @foreach ($tree['arrows'] as $arrow)
                    <g class="tt-arrow{{ $arrow['lit'] ? ' lit' : '' }}"><line x1="{{ $arrow['line']['x1'] }}" y1="{{ $arrow['line']['y1'] }}" x2="{{ $arrow['line']['x2'] }}" y2="{{ $arrow['line']['y2'] }}"/><polygon points="{{ $arrow['head'] }}"/></g>
                  @endforeach
                </svg>
                @foreach ($tree['nodes'] as $node)
                  <a @if ($node['href']) href="{{ $node['href'] }}" @endif class="tt-node{{ $node['taken'] ? '' : ' off' }}{{ $node['icon'] ? '' : ' plain' }}{{ $node['choice'] ? ' choice' : '' }}" style="left:{{ $node['left'] }}px;top:{{ $node['top'] }}px;--r:{{ $node['share'] }}" title="{{ $node['title'] }}" aria-label="{{ $node['title'] }}" data-node="{{ $node['id'] }}">
                    @if ($node['icon'])<img class="tt-ico" src="{{ $node['icon'] }}" alt="" width="48" height="48" loading="lazy">@else<span class="tt-init" aria-hidden="true">{{ $node['initial'] }}</span>@endif
                    <b class="tt-rate">{{ $node['rate'] }}</b>
                  </a>
                @endforeach
              </div>
            </div>
          </div>
        @endforeach
      </div>
      @if ($d['outside'])
      <h3 class="sc-h mt-8" style="font-size:22px">Talents outside the layout</h3>
      <ol class="rank mt-3">
        @foreach ($d['outside'] as $row)
          <li title="{{ $row['title'] }}"><span class="who">@if ($row['href'])<a href="{{ $row['href'] }}">{{ $row['name'] }}</a>@else{{ $row['name'] }}@endif</span><span class="val">{{ $row['rate'] }}</span><span class="track"><i style="width:{{ $row['width'] }}%"></i></span></li>
        @endforeach
      </ol>
      @endif
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
