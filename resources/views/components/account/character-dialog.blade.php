@props(['character'])
@php
    $id = $character['id'];
    $name = \App\View\Format::characterName($character['name']);
    $public = $character['is_public'];
@endphp
<dialog id="privacy-{{ $id }}" class="sc-dialog" aria-labelledby="privacy-{{ $id }}-title" wire:ignore.self wire:key="privacy-dialog-{{ $id }}">
  <div class="bar">
    <h2 id="privacy-{{ $id }}-title" class="sc-h" style="font-size:30px"><span class="sc-cls" style="--cls:{{ \App\View\Sc\ClassColor::of($character['class']) }}"></span> {{ $name }}</h2>
    <button type="button" class="sc-btn quiet small" data-dialog-close>Close</button>
  </div>
  <div class="sc-set">
    <x-sc.set-row title="Public page" :body="'Off: only you can open the pages of '.$name.'.'">
      <span @class(['sc-state', 'on' => $public])>{{ $public ? 'Public' : 'Private' }}</span>
      <x-sc.toggle :label="'Public page for '.$name" :checked="$public" wire:change="setPublic({{ $id }}, $event.target.checked)" />
    </x-sc.set-row>
    <x-sc.set-row title="Show gold and trades" :body="$public ? 'Off: visitors see no balance, vendors, auctions, mail or trades for '.$name.'.' : 'Needs a public page.'">
      <x-sc.toggle :label="'Show economy for '.$name" :checked="$character['show_economy']" :disabled="! $public" wire:change="setEconomy({{ $id }}, $event.target.checked)" />
    </x-sc.set-row>
    <x-sc.set-row title="Show session pages" :body="$public ? 'Off: visitors cannot open the pages of single sessions of '.$name.'. The map and its trails follow the public page.' : 'Needs a public page.'">
      <x-sc.toggle :label="'Show sessions for '.$name" :checked="$character['show_sessions']" :disabled="! $public" wire:change="setSessions({{ $id }}, $event.target.checked)" />
    </x-sc.set-row>
  </div>
</dialog>
