{{-- One kind of search result: a heading with its count and the hits. hits: [['href', 'title', 'meta', 'value', 'quality' => 'q3']];
     more: ['href', 'label'] adds a link under the hits; spaced puts a gap above the heading. --}}
@props(['title', 'count', 'hits', 'more' => null, 'spaced' => false])
<h2 class="kind"@if ($spaced) style="margin-top:2rem"@endif>{{ $title }} <span>{{ $count }}</span></h2>
@foreach ($hits as $hit)
<a class="hit" href="{{ $hit['href'] }}"><b @class([$hit['quality'] ?? null])>{{ $hit['title'] }}</b><span>{{ $hit['meta'] }}</span><span class="val">{{ $hit['value'] }}</span></a>
@endforeach
@if ($more)
<p class="note"><a href="{{ $more['href'] }}">{{ $more['label'] }}</a></p>
@endif
