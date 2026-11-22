{{-- What players search for, one module per group. groups: SearchPage::start(). --}}
@props(['groups'])
<x-sheet>
  @foreach ($groups as $group)
  <x-module :first="$loop->first" :last="$loop->last">
    <x-search-group :title="$group['title']" :icon="$group['icon']" :count="$group['count']" :hits="$group['hits']" />
  </x-module>
  @endforeach
</x-sheet>
