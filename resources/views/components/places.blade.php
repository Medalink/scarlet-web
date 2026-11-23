{{-- Where characters stand on the boards. groups: [['name', 'rows' => App\View\Pages\CharacterStandings::rows()]]; one table a character. --}}
@props(['groups', 'heading'])
@if ($groups !== [])
<section class="places" aria-label="{{ $heading }}">
  @foreach ($groups as $group)
  <h2>{{ $group['name'] }}</h2>
  <x-table :columns="[['label' => 'Place', 'numeric' => true], ['label' => 'Board'], ['label' => 'Value'], ['label' => 'Of', 'numeric' => true]]" :rows="array_map(fn (array $row): array => ['class' => $row['medal'] ? 'me' : null, 'cells' => [(string) $row['place'], ['text' => $row['title'], 'href' => $row['href']], $row['value'], $row['of']]], $group['rows'])" />
  @endforeach
</section>
@endif
