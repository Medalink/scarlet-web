<div>
  <p class="sc-note">Removes every character, session and number that came from your installs, and takes them out of the totals. Your recordings on your PC are not touched.</p>
@if ($confirming)
  <p class="mt-4 text-[14px] text-fg" role="alert">This deletes {{ number_format($uploads['characters']) }} {{ \Illuminate\Support\Str::plural('character', $uploads['characters']) }}, {{ number_format($uploads['sessions']) }} {{ \Illuminate\Support\Str::plural('session', $uploads['sessions']) }} and {{ number_format($uploads['parts']) }} uploaded {{ \Illuminate\Support\Str::plural('part', $uploads['parts']) }}. It cannot be undone.</p>
  <p class="mt-4 flex flex-wrap gap-3"><button type="button" class="sc-btn danger" wire:click="delete">Delete everything I uploaded</button><button type="button" class="sc-btn quiet" wire:click="cancel">Cancel</button></p>
@else
  <p class="mt-4"><button type="button" class="sc-btn danger" wire:click="ask">Delete everything I uploaded</button></p>
@endif
@if ($notice)
  <p class="sc-note mt-3" role="status">{{ $notice }}</p>
@endif
</div>
