{{-- The character page's figure strip: a title with its date range, then the figures, each a term and its value in a list of its own. --}}
@props(['title', 'range', 'items'])
<section class="week" aria-labelledby="h-week">
  <div><h2 id="h-week">{{ $title }}</h2><p>{{ $range }}</p></div>
  @foreach ($items as $item)<dl><dt>{{ $item['label'] }}</dt><dd>{{ $item['value'] }}</dd></dl>@endforeach
</section>
