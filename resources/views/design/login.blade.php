@php($base = App\View\Design\Gallery::BASE)
<x-layouts.app title="Sign in to Scarlet" :base="$base">
<x-gate map-label="An invented survey map" :spec="$d['map']">
    <h1>Sign in</h1>
    <p>See your own characters in full, choose which are public, and control what you share.</p>
    <x-sso :providers="[['label' => 'Continue with Google', 'href' => '#', 'icon' => 'google'], ['label' => 'Continue with Battle.net', 'href' => '#', 'icon' => 'battlenet']]" />
    <p class="note">Scarlet stores the sign-in's id and nothing else: no email, no name, no BattleTag. Uploading from the app works without an account.</p>
</x-gate>
</x-layouts.app>
