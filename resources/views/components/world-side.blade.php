{{-- The panel of the world page. controls: seg groups as [['label', 'items', 'buttons']]; key: x-heat-key props; line: the readout; zones: x-rank rows.
     The slot is anything the page adds below the list. --}}
@props(['title', 'sub', 'controls', 'key', 'line', 'zonesTitle', 'zones'])
<aside class="world-side">
  <h1 id="h1">{{ $title }}</h1>
  <p class="sub">{{ $sub }}</p>
  <div class="world-ctl">
    @foreach ($controls as $control)<x-seg :label="$control['label']" :items="$control['items']" :buttons="$control['buttons'] ?? false" />@endforeach
  </div>
  <x-heat-key :low="$key['low']" :high="$key['high']" />
  <x-readout :line="$line" />
  <h2>{{ $zonesTitle }}</h2>
  <x-rank :rows="$zones" plain />
  {{ $slot }}
</aside>
