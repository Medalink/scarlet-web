@php
    $totals = $storage['totals'];
    $max = max(1, ...array_column($storage['characters'], 'bytes'));
    $installMax = max(1, ...array_column($installs, 'bytes'));
    $groupMax = max(1, ...array_column($storage['groups'], 'bytes'));
    $top = array_slice($storage['groups'], 0, 4);
    $rest = array_slice($storage['groups'], 4);
    $stack = [];
    foreach ($top as $i => $group) {
        $stack[] = [$group['label'], $group['bytes'], '--s-'.($i + 1), \App\View\Format::bytes($group['bytes'])];
    }
    if ($rest !== []) {
        $stack[] = [\App\View\Format::count(count($rest), 'other group'), array_sum(array_column($rest, 'bytes')), '--s-0', \App\View\Format::bytes(array_sum(array_column($rest, 'bytes')))];
    }
@endphp
<x-account.shell title="Storage" current="/account/storage">
  <section class="mt-10" id="totals">
    <h2 class="sc-h" style="font-size:30px">What is stored</h2>
    <x-sc.tiles :cols="5" class="mt-4">
      <x-sc.tile label="Parts" :value="\App\View\Format::number($totals['parts'])" :sub="\App\View\Format::count($totals['lines'], 'line')" />
      <x-sc.tile label="Size" :value="\App\View\Format::bytes($totals['bytes'])" sub="before compression" />
      <x-sc.tile label="Characters" :value="\App\View\Format::number($totals['characters'])" />
      <x-sc.tile label="Sessions" :value="\App\View\Format::number($totals['sessions'])" />
      <x-sc.tile label="Installs" :value="\App\View\Format::number($totals['installs'])" :sub="$totals['revoked'] > 0 ? $totals['revoked'].' revoked' : null" />
    </x-sc.tiles>
  </section>

@if ($totals['parts'] > 0)
  <section class="mt-12 grid grid-cols-[minmax(0,1fr)] gap-10 lg:grid-cols-2" id="breakdown">
    <div id="by-character">
      <h2 class="sc-h" style="font-size:30px">Size by character</h2>
      <div class="sc-bars mt-4">
@foreach ($storage['characters'] as $character)
        <div class="row"><span>{{ \App\View\Format::characterName($character['name']) }}</span><b>{{ \App\View\Format::bytes($character['bytes']) }} · {{ \App\View\Format::count($character['parts'], 'part') }}</b><i style="--w:{{ round($character['bytes'] / $max * 100, 1) }}%;--c:{{ \App\View\Sc\ClassColor::of($character['class']) }}"></i></div>
@endforeach
      </div>
    </div>
    <div id="by-group">
      <h2 class="sc-h" style="font-size:30px">Size by sharing group</h2>
      <x-sc.stack class="mt-4" :rows="$stack" :height="14" />
      <div class="sc-bars mt-5">
@foreach ($storage['groups'] as $group)
        <div class="row"><span>{{ $group['label'] }}</span><b>{{ \App\View\Format::bytes($group['bytes']) }} · {{ \App\View\Format::count($group['parts'], 'part') }}</b><i style="--w:{{ round($group['bytes'] / $groupMax * 100, 1) }}%"></i></div>
@endforeach
      </div>
    </div>
  </section>

@if (count($installs) > 1)
  <section class="mt-12" id="by-install">
    <h2 class="sc-h" style="font-size:30px">Size by install</h2>
    <div class="sc-bars mt-4">
@foreach ($installs as $install)
      <div class="row"><span>{{ $install['name'] }}@if ($install['revoked']) (revoked)@endif</span><b>{{ \App\View\Format::bytes($install['bytes']) }} · {{ \App\View\Format::count($install['parts'], 'part') }}</b><i style="--w:{{ round($install['bytes'] / $installMax * 100, 1) }}%"></i></div>
@endforeach
    </div>
    <p class="sc-note mt-3">A part that two installs list counts for both.</p>
  </section>
@endif
@endif

  <section class="mt-12" id="uploads">
    <h2 class="sc-h" style="font-size:30px">Uploads in the last 24 hours</h2>
    <livewire:account.upload-log />
  </section>

  <section class="mt-12" id="delete">
    <h2 class="sc-h" style="font-size:30px">Delete everything you uploaded</h2>
    <livewire:account.delete-uploads />
  </section>
</x-account.shell>
