<x-layouts.app title="Link the Scarlet app to your account" current="" :signed-in="true">
<x-gate map-label="An invented survey map" :spec="$map">
    <h1>Link this app</h1>
@if ($state === \App\Actions\Account\LinkState::Ready)
    <p>The Scarlet app on your PC is showing this code. Check that it matches, then link it.</p>
    <x-code-box :code="$code" />
    <form method="post" action="/link" style="margin-top:1.6rem;display:flex;gap:.8rem;flex-wrap:wrap">@csrf
      <input type="hidden" name="code" value="{{ $code }}">
      <x-btn type="submit">Link to my account</x-btn><x-btn kind="quiet" href="/account">Cancel</x-btn>
    </form>
    <p class="note">Linking puts the characters this app uploads under your account. You can revoke the app at any time. The code works once and expires in 10 minutes.</p>
@else
    <p role="alert">{{ $message }}</p>
    <form method="get" action="/link" style="margin-top:1.6rem;display:flex;gap:.8rem;flex-wrap:wrap;align-items:center">
      <label class="vh" for="code">Code from the app</label>
      <input id="code" class="field" name="code" type="text" value="{{ $code }}" maxlength="9" placeholder="XXXX-XXXX" autocomplete="off" autocapitalize="characters" style="flex:1 1 9rem;min-width:0">
      <x-btn type="submit">Check code</x-btn><x-btn kind="quiet" href="/account">Cancel</x-btn>
    </form>
@endif
</x-gate>
</x-layouts.app>
