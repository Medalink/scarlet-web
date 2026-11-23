<div>
@if ($f)
@include('character.feats', ['f' => $f, 'pin' => true])
@endif
@if ($toasts !== [])
<div class="feat-toasts fixed bottom-4 right-4 z-50 grid gap-2" role="region" aria-label="New feats" wire:ignore
     x-data="{ shown: @js(collect($toasts)->pluck('tier', 'key')) }" x-init="setTimeout(() => $wire.seen(shown), 6000)">
  @foreach ($toasts as $toast)
    <x-sc.feat-toast class="feat-toast" :id="'feat-toast-'.$toast['key']" :feat="$toast['feat']" :kicker="$toast['kicker']" :play="true" />
  @endforeach
</div>
@endif
</div>
