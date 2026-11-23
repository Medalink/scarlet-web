<x-layouts.app title="Link the Scarlet app to your account" current="" :signed-in="true" :index="false" :brand="false">
<x-gate map-label="An invented survey map" :spec="$map">
    <h1>Link this app</h1>
@if ($redeem === null)
    <p role="alert">This link was not opened by the app. Press "Link to my account" in the Scarlet app.</p>
    <x-btn kind="quiet" href="/account">Cancel</x-btn>
@else
    <p>Type the code the Scarlet app on your PC shows.</p>
@if ($message !== null)
    <p role="alert">{{ $message }}</p>
@endif
    <form method="post" action="/link" style="margin-top:1.6rem;display:flex;gap:.8rem;flex-wrap:wrap;align-items:center">@csrf
      <label class="vh" for="code">Code from the app</label>
      <input id="code" class="field" name="code" type="text" maxlength="9" placeholder="XXXX-XXXX" autocomplete="off" autocapitalize="characters" required style="flex:1 1 9rem;min-width:0">
      <input type="hidden" name="redeem" value="{{ $redeem }}">
      <x-btn type="submit">Link to my account</x-btn><x-btn kind="quiet" href="/account">Cancel</x-btn>
    </form>
    <p class="note">Linking puts the characters this app uploads under your account. You can revoke the app at any time. The code works once and expires in 10 minutes.</p>
@endif
</x-gate>
</x-layouts.app>
