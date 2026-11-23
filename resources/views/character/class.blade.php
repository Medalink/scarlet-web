@php
    $c = $d['class'];
    $page = $c['page'];
    $icons = $c['icons'];
@endphp
<section id="class" class="sc-sec">
  <div class="sc-sec-head"><h2 style="color:var(--cls)">{{ $d['className'] }}</h2>@if ($c['build'])<p class="ml-auto pb-1 text-[14px]"><a href="{{ $c['build'] }}">Open the build</a></p>@endif</div>
  @if ($page->callouts !== [] || $c['art'])
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
    @if ($page->figures !== [])
      <x-sc.tiles class="mt-4" :cols="min(7, count($page->figures))">
        @foreach ($page->figures as $figure)
          <x-sc.tile :label="$figure->label" :value="$figure->unit->figure($figure->value)" :sub="$figure->of" />
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
          <x-class.module :id="'h-t'.$m" :heading="$talent->name" :icon="$talent->icon" :tag="$talent->points.' of '.$talent->max" :blocks="$talent->blocks" :note="$talent->note" :first="$m === 0" :last="$m === count($page->talents) - 1" :icons="$icons" />
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
