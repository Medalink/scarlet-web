<div>
@if ($characters === [])
  <p class="sc-note mt-4">No characters uploaded yet.</p>
@elseif ($mode === \App\Livewire\Account\Characters::CARDS)
  <div class="sc-cards mt-4">
@foreach ($characters as $character)
@php
    $name = \App\View\Format::characterName($character['name']);
    $detail = \App\View\Dates::implode(' · ', array_values(array_filter([\App\View\Format::character($character['level'], $character['race'], $character['class']), $character['ruleset']])));
@endphp
    <article class="sc-card" style="--cls:{{ \App\View\Sc\ClassColor::of($character['class']) }}" wire:key="card-{{ $character['id'] }}">
      <div>
        <div class="flex items-baseline gap-3"><a class="nm" href="/c/{{ rawurlencode($character['name']) }}">{{ $name }}</a><span @class(['sc-state', 'on' => $character['is_public']])>{{ $character['is_public'] ? 'Public' : 'Private' }}</span></div>
        <p class="sc-sub mt-1">{{ $detail }}</p>
      </div>
      <dl class="facts">
        <div><dt class="sc-label">Sessions</dt><dd class="sc-num sc-num-md">{{ \App\View\Format::number($character['sessions']) }}</dd></div>
        <div><dt class="sc-label">Played</dt><dd class="sc-num sc-num-md">{{ \App\View\Format::hours($character['played_seconds'], false) }}</dd></div>
        <div><dt class="sc-label">Parts stored</dt><dd class="sc-num sc-num-md">{{ \App\View\Format::number($character['parts']) }}</dd></div>
        <div><dt class="sc-label">Size stored</dt><dd class="sc-num sc-num-md">{{ \App\View\Format::bytes($character['bytes']) }}</dd></div>
      </dl>
      <div class="foot">
        <span class="text-[13px] text-muted">{{ $character['last_upload'] ? \App\View\Dates::join('Last upload ', \App\View\Dates::moment($character['last_upload'])) : 'Nothing uploaded yet' }}</span>
        <button type="button" class="sc-btn quiet small" data-dialog-open="privacy-{{ $character['id'] }}" aria-label="Privacy of {{ $name }}" wire:key="open-{{ $character['id'] }}">Privacy</button>
      </div>
    </article>
@endforeach
  </div>
@else
  <div class="sc-set">
@foreach ($characters as $character)
@php
    $name = \App\View\Format::characterName($character['name']);
    $state = $character['is_public']
        ? 'Public page · '.($character['show_economy'] ? 'gold and trades shown' : 'gold and trades hidden').' · '.($character['show_sessions'] ? 'session pages shown' : 'session pages hidden')
        : 'Private page. Only you can open it.';
@endphp
    <x-sc.set-row :title="$name" :href="'/c/'.rawurlencode($character['name'])" :body="$state" wire:key="row-{{ $character['id'] }}">
      <span @class(['sc-state', 'on' => $character['is_public']])>{{ $character['is_public'] ? 'Public' : 'Private' }}</span>
      <button type="button" class="sc-btn quiet small" data-dialog-open="privacy-{{ $character['id'] }}" aria-label="Privacy of {{ $name }}" wire:key="open-{{ $character['id'] }}">Privacy</button>
    </x-sc.set-row>
@endforeach
  </div>
@endif
@foreach ($characters as $character)
  <x-account.character-dialog :character="$character" />
@endforeach
</div>
