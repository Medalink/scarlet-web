@props(['rows', 'feat'])
<div {{ $attributes->class('sc-ladder') }}>
  @foreach ($rows as $row)
    @isset ($row['you'])
      <span class="you">{{ $row['you'] }}</span>
    @else
      <x-sc.seal :glyph="$feat['glyph']" :family="$feat['family']" :tier="$row['tier']" :size="28" :spell="$feat['spell'] ?? null" :label="$row['name']" />
      <span>{{ $row['name'] }}</span>
      <span class="bar" style="--w:{{ $row['width'] }}%"></span>
      <b class="text-right text-fg">{{ $row['share'] }}</b>
      <span class="at">{{ $row['at'] }}</span>
    @endisset
  @endforeach
</div>
