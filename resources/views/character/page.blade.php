@php
    $head = $d['head'];
    $class = $d['class'];
    $styles = $class['page'] ? ['resources/css/class-section.css'] : [];
    $scripts = $class['art'] ? ['resources/js/scene.js'] : [];
@endphp
<x-layouts.sc :title="$d['title']" :description="$d['description']" :canonical="$d['path']" :index="! $d['owner']" :styles="$styles" :scripts="$scripts">
<div class="wrap pb-20 pt-9" style="--cls:{{ $head['color'] }}">
  @include('character.head')

  <x-sc.section-nav class="mt-9" :sections="$d['sections']" />

  @include('character.overview')
  @includeWhen($d['feats'], 'character.feats')
  @includeWhen($d['sessions'], 'character.sessions')
  @includeWhen($class['page'], 'character.class')
  @includeWhen($d['levelling'], 'character.levelling')
  @include('character.combat')
  @include('character.loot')
  @includeWhen($d['time'], 'character.time')
</div>
</x-layouts.sc>
