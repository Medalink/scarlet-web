{{-- The heat palette with its ends named. `zero` adds the empty swatch; `extra` is a trailing fact and `extraLocal` its
     wording once resources/js/local-time.js shows the hours in the viewer's time zone. --}}
@props(['low', 'high', 'zero' => false, 'extra' => null, 'extraLocal' => null])
<p class="heat-key"><span>{{ $low }}</span>@if ($zero)<i style="background:rgba(255,255,255,.055)"></i>@endif<i style="background:#4b1626"></i><i style="background:#861a34"></i><i style="background:#cf1f45"></i><i style="background:#ff5d7e"></i><span>{{ $high }}</span>@if ($extra)<span style="margin-left:1rem"@if ($extraLocal !== null) data-local-label="{{ $extraLocal }}"@endif>{{ $extra }}</span>@endif</p>
