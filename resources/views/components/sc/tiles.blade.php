@props(['cols' => 4, 'panel' => false])
<div {{ $attributes->class(['sc-grid grid-cols-2 md:grid-cols-[repeat(var(--cols),minmax(0,1fr))]', 'on-panel' => $panel])->merge(['style' => '--cols:'.$cols]) }}>{{ $slot }}</div>
