{{-- One kind of search result: a heading with its glyph, its count and the hits. hits: [['href', 'title', 'meta', 'value', 'quality' => 'q3', 'class' => 'HUNTER']];
     icon: a name of the icon component; more: ['href', 'label'] adds a link under the hits; spaced puts a gap above the heading. --}}
@props(['title', 'count', 'hits', 'icon' => null, 'more' => null, 'spaced' => false])
<h2 class="kind"@if ($spaced) style="margin-top:2rem"@endif>@if ($icon)<x-icon :name="$icon" />@endif{{ $title }} <span>{{ $count }}</span></h2>
@foreach ($hits as $hit)
<a class="hit" href="{{ $hit['href'] }}">@if (! empty($hit['class']))<b data-class="{{ $hit['class'] }}"><i class="dotc" aria-hidden="true"></i>{{ $hit['title'] }}</b>@else<b @class([$hit['quality'] ?? null])>{{ $hit['title'] }}</b>@endif<span>{{ $hit['meta'] }}</span><span class="val">{{ $hit['value'] }}</span></a>
@endforeach
@if ($more)
<p class="note"><a href="{{ $more['href'] }}">{{ $more['label'] }}</a></p>
@endif
