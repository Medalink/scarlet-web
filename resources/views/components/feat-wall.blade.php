{{-- The Feats section of the character page. feats: App\View\Pages\CharacterFeatWall::section. pin: the owner's
     toggles in the dialog, which need the Livewire component around this one (App\Livewire\FeatWall). --}}
@props(['feats', 'pin' => false])
<section class="recs" aria-labelledby="h-feats">
  <h2 id="h-feats">Feats</h2>
  <p class="feats-sum"><span><b>{{ $feats['held'] }}</b>of {{ $feats['total'] }} feats</span>@foreach ($feats['tiers'] as $tier)<span><b>{{ $tier['count'] }}</b>{{ $tier['name'] }}</span>@endforeach</p>
  @if ($feats['since'])<p class="note">Counted since {{ $feats['since'] }}.</p>@endif
  @if ($feats['pinned'])<ul class="feats feats-top feats-pinned">@foreach ($feats['pinned'] as $feat)<x-feat :feat="$feat" wire:key="pinned-{{ $feat['key'] }}" />@endforeach</ul>@endif
  <ul class="feats feats-top">@foreach ($feats['top'] as $feat)<x-feat :feat="$feat" wire:key="top-{{ $feat['key'] }}" />@endforeach</ul>
  <x-btn kind="quiet" commandfor="feats-all" command="show-modal">View all</x-btn>
  <dialog id="feats-all" class="feats-all" closedby="any" aria-labelledby="h-feats-all" wire:ignore.self>
    <header><div><h2 id="h-feats-all">Feats</h2>@if ($pin)<span class="feats-pins">{{ count($feats['pinned']) }} of {{ $feats['limit'] }} pinned</span>@error('feat')<span class="feats-pins" role="alert">{{ $message }}</span>@enderror @endif</div><x-btn kind="quiet" commandfor="feats-all" command="close" aria-label="Close">&times;</x-btn></header>
    <div class="feats-body">
      @if ($feats['earned'])
      <h3>Earned <span>{{ count($feats['earned']) }}</span></h3>
      <ul class="feats">@foreach ($feats['earned'] as $feat)<x-feat :feat="$feat" :pin="$pin" :full="count($feats['pinned']) >= $feats['limit']" wire:key="earned-{{ $feat['key'] }}" />@endforeach</ul>
      @endif
      @if ($feats['unearned'])
      <h3>Not yet earned <span>{{ count($feats['unearned']) }}</span></h3>
      <ul class="feats">@foreach ($feats['unearned'] as $feat)<x-feat :feat="$feat" :pin="$pin" :full="count($feats['pinned']) >= $feats['limit']" wire:key="unearned-{{ $feat['key'] }}" />@endforeach</ul>
      @endif
    </div>
  </dialog>
</section>
