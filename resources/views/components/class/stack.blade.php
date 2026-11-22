{{-- A stack chart of shares by level: block is App\Support\ClassPage\Stack. --}}
@props(['block', 'icons'])
@if ($block->columns !== [])
<div class="xp-plot"><div class="xp-y" aria-hidden="true"><span>100%</span><span>50%</span><span>0</span></div>
<div class="xp-bars" role="img" aria-label="{{ $block->label }}">@foreach ($block->columns as $column)<div class="xp-bar">@foreach ($block->series as $i => $series)<i style="height:{{ number_format(max(0, min(1, $column->shares[$i] ?? 0)) * 100, 1, '.', '') }}%;background:{{ $series->hue->css }}"></i>@endforeach</div>@endforeach</div>
<div class="xp-x" aria-hidden="true">@foreach ($block->columns as $i => $column)<span>@if ($block->labelled($i)){{ $column->label }}@endif</span>@endforeach</div></div>
<ul class="key">@foreach ($block->series as $series)<li><i style="background:{{ $series->hue->css }}"></i>{{ $series->label }}</li>@endforeach</ul>
@endif
