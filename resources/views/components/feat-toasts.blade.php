{{-- The owner's toasts for feats earned or upgraded since they were last shown (resources/js/site/feat-toasts.js plays
     them one at a time). new: App\Actions\Feats\ShowNewFeats, highest tier first. At most five toasts; the rest are one
     "more feats" toast that opens the dialog. data-shown on a toast is what the server records once it has shown. --}}
@props(['new'])
@php
    $toasts = array_slice($new, 0, 5);
    $rest = array_slice($new, 5);
@endphp
<div class="feat-toasts" role="region" aria-label="New feats" wire:ignore>
@foreach ($toasts as $feat)
  <div class="feat-toast feat t{{ $feat['tier'] }}" id="feat-toast-{{ $feat['key'] }}" popover="manual" role="status" data-shown='@json([$feat['key'] => $feat['tier']])'>
    <span class="seal-slot">@if ($feat['from'])<span class="seal was f{{ $feat['from'] }}" aria-hidden="true"><x-icon :name="$feat['icon']" /></span>@endif<span class="seal"><x-icon :name="$feat['icon']" /></span></span>
    <div class="feat-toast-say"><p class="feat-toast-l">{{ $feat['from'] ? 'Upgraded from '.$feat['from_name'] : 'Feat earned' }}</p><p class="feat-n">{{ $feat['name'] }}<small>{{ $feat['tier_name'] }}</small></p></div>
    <button type="button" class="feat-toast-x" popovertarget="feat-toast-{{ $feat['key'] }}" popovertargetaction="hide" aria-label="Close">&times;</button>
  </div>
@endforeach
@if ($rest)
  <div class="feat-toast feat t0" id="feat-toast-more" popover="manual" role="status" data-shown='@json(collect($rest)->pluck('tier', 'key'))'>
    <div class="feat-toast-say"><p class="feat-n">{{ count($rest) }} more {{ count($rest) === 1 ? 'feat' : 'feats' }}</p></div>
    <x-btn kind="quiet" commandfor="feats-all" command="show-modal">View all</x-btn>
    <button type="button" class="feat-toast-x" popovertarget="feat-toast-more" popovertargetaction="hide" aria-label="Close">&times;</button>
  </div>
@endif
  <div class="vh" aria-live="polite" data-live></div>
</div>
