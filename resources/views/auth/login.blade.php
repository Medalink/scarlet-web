<x-layouts.sc title="Sign in to Scarlet" current="" :index="false" :brand="false">
<x-gate map-label="An invented survey map" :spec="$map">
    <h1>Sign in</h1>
    <p>See your own characters in full, choose which are public, and control what you share.</p>
@if (session('notice'))
    <p class="note" role="alert">{{ session('notice') }}</p>
@endif
    <x-sso :providers="$providers" />
    <p class="note">Scarlet stores the sign-in's id and nothing else: no email, no name, no BattleTag. Uploading from the app works without an account.</p>
</x-gate>
</x-layouts.sc>
