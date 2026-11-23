@php($t = $d['time'])
<section id="time" class="sc-sec">
  <h2>Play time</h2>
  <div class="mt-6 grid grid-cols-[minmax(0,1fr)] gap-4 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
    <div class="sc-panel px-5 py-4">
      <h3 class="sc-h">When {{ $t['first'] }} plays</h3>
      <div class="sc-heat mt-4">@foreach ($t['cells'] as $cell)<i data-v="{{ $cell['level'] }}" data-tip="{{ $cell['tip'] }}"></i>@endforeach</div>
      @if ($t['say'])<p class="sc-say mt-4" style="font-size:15.5px">{!! $t['say'] !!}</p>@endif
    </div>
    <div class="sc-panel px-5 py-4">
      <h3 class="sc-h">Where the time went</h3>
      @if ($t['moving'] !== [])
        <p class="sc-label mb-2 mt-3">Moving and standing</p>
        <x-sc.stack :rows="$t['moving']" />
      @endif
      @if ($t['combat'] !== [])
        <p class="sc-label mb-2 mt-4">In combat</p>
        <x-sc.stack :rows="$t['combat']" />
      @endif
      @if ($t['group'] !== [])
        <p class="sc-label mb-2 mt-4">In a group</p>
        <x-sc.stack :rows="$t['group']" />
      @endif
    </div>
  </div>
</section>
