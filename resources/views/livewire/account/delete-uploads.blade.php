<div>
  <p class="sub">Removes every character, session and number that came from your installs, and takes them out of the totals. Your recordings on your PC are not touched.</p>
@if ($confirming)
  <p class="sub" style="margin-top:1.1rem;color:var(--fg)" role="alert">This deletes {{ number_format($uploads['characters']) }} {{ \Illuminate\Support\Str::plural('character', $uploads['characters']) }}, {{ number_format($uploads['sessions']) }} {{ \Illuminate\Support\Str::plural('session', $uploads['sessions']) }} and {{ number_format($uploads['parts']) }} uploaded {{ \Illuminate\Support\Str::plural('part', $uploads['parts']) }}. It cannot be undone.</p>
  <p style="margin-top:1.1rem;display:flex;gap:.8rem;flex-wrap:wrap"><x-btn kind="danger" wire:click="delete">Delete everything I uploaded</x-btn><x-btn kind="quiet" wire:click="cancel">Cancel</x-btn></p>
@else
  <p style="margin-top:1.1rem"><x-btn kind="danger" wire:click="ask">Delete everything I uploaded</x-btn></p>
@endif
@if ($notice)
  <x-note role="status">{{ $notice }}</x-note>
@endif
</div>
