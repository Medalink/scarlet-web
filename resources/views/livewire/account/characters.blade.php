<div>
@if ($characters === [])
  <p class="sc-note mt-4">No characters uploaded yet.</p>
@else
  <div class="sc-set">
@foreach ($characters as $character)
    @php
        $name = \App\View\Format::characterName($character->name);
        $detail = \App\View\Format::character($character->level ?: null, $character->race, $character->class);
        $line = collect([$detail ?: null, \App\View\Format::count($character->sessions_decoded, 'session'), $character->last_seen_at ? 'last upload '.$character->last_seen_at->diffForHumans() : 'nothing uploaded yet'])->filter()->implode(' · ');
    @endphp
    <x-sc.set-row :title="$name" :href="'/c/'.rawurlencode($character->name)" :body="$line" wire:key="c-{{ $character->id }}">
      <span @class(['sc-state', 'on' => $character->is_public])>{{ $character->is_public ? 'Public' : 'Private' }}</span>
      <x-sc.toggle :label="'Public page for '.$name" :checked="$character->is_public" wire:change="setPublic({{ $character->id }}, $event.target.checked)" />
    </x-sc.set-row>
@if ($character->is_public)
    <x-sc.set-row title="Show gold and trades" :body="'Off: visitors see no balance, vendors, auctions, mail or trades for '.$name.'.'" wire:key="e-{{ $character->id }}">
      <x-sc.toggle :label="'Show economy for '.$name" :checked="$character->show_economy" wire:change="setEconomy({{ $character->id }}, $event.target.checked)" />
    </x-sc.set-row>
    <x-sc.set-row title="Show session pages" :body="'Off: visitors cannot open the pages of single sessions of '.$name.'. The map and its trails follow the public page.'" wire:key="s-{{ $character->id }}">
      <x-sc.toggle :label="'Show sessions for '.$name" :checked="$character->show_sessions" wire:change="setSessions({{ $character->id }}, $event.target.checked)" />
    </x-sc.set-row>
@endif
@endforeach
  </div>
@endif
</div>
