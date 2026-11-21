{{-- The heat palette with its ends named. `zero` adds the empty swatch; `extra` is a trailing fact. --}}
@props(['low', 'high', 'zero' => false, 'extra' => null])
<p class="heat-key"><span>{{ $low }}</span>@if ($zero)<i style="background:rgba(255,255,255,.055)"></i>@endif<i style="background:#4b1626"></i><i style="background:#861a34"></i><i style="background:#cf1f45"></i><i style="background:#ff5d7e"></i><span>{{ $high }}</span>@if ($extra)<span style="margin-left:1rem">{{ $extra }}</span>@endif</p>
