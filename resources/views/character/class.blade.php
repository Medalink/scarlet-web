@php
    $c = $d['class'];
    $page = $c['page'];
    $icons = $c['icons'];
@endphp
<section id="class" class="sc-sec">
  <div class="sc-sec-head"><h2 style="color:var(--cls)">{{ $d['className'] }}</h2>@if ($c['build'])<p class="ml-auto pb-1 text-[14px]"><a href="{{ $c['build'] }}">Open the build</a></p>@endif</div>
  @if ($c['art'])
    <div class="sc-scene sc-panel mt-6">
      @if ($c['art'])
        <div class="stage" data-scene="{{ $c['art']['scene'] }}" data-models="{{ $c['art']['models'] }}" data-texture="{{ $c['art']['texture'] }}" data-state="loading" role="img" aria-label="{{ $c['scene']->label() }}"></div>
      @endif
      <div class="callouts" @if ($c['art']) aria-hidden="true" @endif>
        @foreach ($page->callouts as $callout)
          <p @class(['callout', 'left' => $callout->left]) data-side="{{ $callout->left ? 'left' : 'right' }}" data-key="{{ $callout->key }}"><b>{{ $callout->title }}</b><span>{{ $callout->text }}</span>@if ($callout->note)<em>{{ $callout->note }}</em>@endif</p>
        @endforeach
      </div>
    </div>
    @if ($c['art'])<p class="sc-note mt-2">{{ $c['scene']->credit() }}</p>@endif
  @endif
  <div class="cls-page" data-class="{{ $page->class->value }}">
    @php
      $facts = $c['art'] ? [] : $page->callouts;
    @endphp
    @if ($page->figures !== [] || $facts !== [])
      <x-sc.tiles class="mt-4" :cols="min(7, count($page->figures) + count($facts))">
        @foreach ($page->figures as $figure)
          <x-sc.tile :label="$figure->label" :value="$figure->unit->figure($figure->value)" :sub="$figure->of" />
        @endforeach
        @foreach ($facts as $callout)
          <x-sc.tile :label="$callout->title" :sub="$callout->text" />
        @endforeach
      </x-sc.tiles>
    @endif
    <div class="sheet mt-4">
      @foreach ($page->sheet as $r => $row)@foreach ($row->modules as $m => $module)
        <x-class.module :id="'h-r'.$r.'-'.$m" :heading="$module->heading" :icon="$module->icon" :blocks="$module->blocks" :span="$module->span" :note="$module->note" :first="$m === 0" :last="$m === count($row->modules) - 1" :icons="$icons" />
      @endforeach @endforeach
      @if ($page->talents !== [])
        <h3 class="sect" id="talents">From the talents</h3>
        @foreach ($page->talents as $m => $talent)
          @php
            $figures = array_merge(...array_map(fn ($block) => $block instanceof \App\Support\ClassPage\Pair ? $block->rows : [], $talent->blocks));
            $unused = $figures !== [] && array_all($figures, fn ($row) => $row->value === 0 || $row->value === 0.0);
            $blocks = $unused ? array_values(array_filter($talent->blocks, fn ($block) => ! $block instanceof \App\Support\ClassPage\Split)) : $talent->blocks;
          @endphp
          <x-class.module :id="'h-t'.$m" :heading="$talent->name" :icon="$talent->icon" :tag="$talent->points.' of '.$talent->max" :blocks="$blocks" :note="$talent->note" :first="$m === 0" :last="$m === count($page->talents) - 1" :icons="$icons" />
        @endforeach
      @endif
      @if ($page->party !== [])
        <h3 class="sect" id="party">For the party</h3>
        @foreach ($page->party as $r => $row)@foreach ($row->modules as $m => $module)
          <x-class.module :id="'h-p'.$r.'-'.$m" :heading="$module->heading" :icon="$module->icon" :blocks="$module->blocks" :span="$module->span" :note="$module->note" :first="$m === 0" :last="$m === count($row->modules) - 1" :icons="$icons" />
        @endforeach @endforeach
      @endif
    </div>
    @if ($c['tabs'] !== [])
      <div class="sc-panel mt-4 px-5 py-4">
        <h3 class="sc-h">Talents at level {{ $c['level'] }}</h3>
        <div class="sc-tree mt-3">@foreach ($c['tabs'] as $tab)<span>{{ $tab['name'] }}</span><i style="--w:{{ $tab['share'] }}%"></i><b class="text-right text-fg">{{ $tab['points'] }}</b>@endforeach</div>
      </div>
    @endif
  </div>
</section>
