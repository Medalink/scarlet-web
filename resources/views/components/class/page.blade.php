{{-- A class page from its page object: page is App\Support\ClassPage\ClassPage, icons its IconMap, scene and art the head's scene when the art exists. --}}
@props(['page', 'icons', 'scene' => null, 'art' => null])
<div class="cls-page" data-class="{{ $page->class->value }}">
<x-class.head :page="$page" :icons="$icons" :scene="$scene" :art="$art" />
<div class="wrap">
  <nav class="trades" aria-label="Pages of {{ $page->character->name }}"><a href="{{ $page->links->character }}">Character</a><a href="{{ $page->links->classPage }}" aria-current="page">{{ $page->name() }}</a>@if ($page->links->battlegrounds)<a href="{{ $page->links->battlegrounds }}">Battlegrounds</a>@endif</nav>
  @if ($art && $scene)<p class="credit">{{ $scene->credit() }}</p>@endif
  @if ($page->figures !== [])<x-class.tally :items="$page->figures" :icons="$icons" />@endif

  <div class="sheet">
    @foreach ($page->sheet as $r => $row)@foreach ($row->modules as $m => $module)
    <x-class.module :id="'h-r'.$r.'-'.$m" :heading="$module->heading" :icon="$module->icon" :blocks="$module->blocks" :span="$module->span" :note="$module->note" :first="$m === 0" :last="$m === count($row->modules) - 1" :icons="$icons" />
    @endforeach @endforeach
    @if ($page->talents !== [])
    <h2 class="sect" id="talents">From the talents</h2>
    @foreach ($page->talents as $m => $talent)
    <x-class.module :id="'h-t'.$m" :heading="$talent->name" :icon="$talent->icon" :tag="$talent->points.' of '.$talent->max" :blocks="$talent->blocks" :note="$talent->note" :first="$m === 0" :last="$m === count($page->talents) - 1" :icons="$icons" />
    @endforeach
    @endif
    @if ($page->party !== [])
    <h2 class="sect" id="party">For the party</h2>
    @foreach ($page->party as $r => $row)@foreach ($row->modules as $m => $module)
    <x-class.module :id="'h-p'.$r.'-'.$m" :heading="$module->heading" :icon="$module->icon" :blocks="$module->blocks" :span="$module->span" :note="$module->note" :first="$m === 0" :last="$m === count($row->modules) - 1" :icons="$icons" />
    @endforeach @endforeach
    @endif
    @if ($page->comparison)<x-class.comparison :comparison="$page->comparison" :icons="$icons" />@endif
  </div>

  @if ($page->feats !== [])<x-class.feats :feats="$page->feats" :name="$page->name()" :icons="$icons" />@endif
</div>
</div>
