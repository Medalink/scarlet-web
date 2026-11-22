<div>
@if ($characters === [])
  <x-note>No characters uploaded yet.</x-note>
@else
  <x-rows>
@foreach ($characters as $character)
    @php
        $detail = \App\View\Format::character($character->level ?: null, $character->race, $character->class);
        $line = collect([$detail ?: null, $character->sessions_decoded.' '.\Illuminate\Support\Str::plural('session', $character->sessions_decoded), $character->last_seen_at ? 'last upload '.$character->last_seen_at->diffForHumans() : 'nothing uploaded yet'])->filter()->implode(' · ');
    @endphp
    <x-row :title="\App\View\Format::characterName($character->name)" :href="'/c/'.rawurlencode($character->name)" :body="$line" wire:key="c-{{ $character->id }}">
      <x-state :on="$character->is_public">{{ $character->is_public ? 'Public' : 'Private' }}</x-state>
      <x-switch :label="'Public page for '.\App\View\Format::characterName($character->name)" :checked="$character->is_public" wire:change="setPublic({{ $character->id }}, $event.target.checked)" />
    </x-row>
@if ($character->is_public)
    <x-row title="Show gold and trades on the public page" :body="'Off: visitors see no balance, vendors, auctions, mail or trades for '.\App\View\Format::characterName($character->name).'.'" wire:key="e-{{ $character->id }}">
      <x-switch :label="'Show economy for '.\App\View\Format::characterName($character->name)" :checked="$character->show_economy" wire:change="setEconomy({{ $character->id }}, $event.target.checked)" />
    </x-row>
@endif
@endforeach
  </x-rows>
@endif
</div>
