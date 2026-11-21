{{-- One bar split into parts. segments: [['class' => 'c-q2', 'count', 'say']]; a part's width is its count. --}}
@props(['segments', 'label'])
<div class="stack" role="img" aria-label="{{ $label }}">@foreach ($segments as $segment)<i class="{{ $segment['class'] }}" style="flex-grow:{{ $segment['count'] }}" title="{{ $segment['say'] }}"></i>@endforeach</div>
