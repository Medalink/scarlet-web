@props(['rows', 'feat', 'scope' => null])
@php($measured = array_any($rows, fn (array $row): bool => isset($row['share']) && $row['share'] !== '–'))
<div {{ $attributes->class(['sc-ladder', 'thin' => ! $measured]) }}>
  @foreach ($rows as $row)
    @isset ($row['you'])
      <span class="you">{{ $row['you'] }}</span>
    @else
      <x-crest :glyph="$feat['glyph']" :feat-key="$feat['key'] ?? null" :scope="$scope ?? null" :family="$feat['family'] ?? null" :tier="$row['tier']" :size="28" :spell="$feat['spell'] ?? null" :label="$row['name']" />
      <span>{{ $row['name'] }}</span>
      @if ($measured)
        <span class="bar" style="--w:{{ $row['width'] }}%"></span>
        <b class="text-right text-fg">{{ $row['share'] }}</b>
      @endif
      <span class="at">{{ $row['at'] }}</span>
    @endisset
  @endforeach
</div>
