@php($base = App\View\Design\Gallery::BASE)
<x-layouts.sc title="Link the Scarlet app to your account" :signed-in="true" :base="$base">
<x-gate map-label="An invented survey map" :spec="$d['map']">
    <h1>Link this app</h1>
    <p>The Scarlet app on your PC is showing this code. Check that it matches, then link it.</p>
    <x-code-box code="K7QM-2XVD" />
    <div class="rows" style="margin-bottom:1.6rem"><div class="row">
        <b>Warden's PC</b>
        <p>Added 3 Nov 2026 · key 4f1c9a2b7e33 · 2 characters linked</p>
    </div></div>
    <p style="margin-top:1.6rem;display:flex;gap:.8rem;flex-wrap:wrap"><x-btn>Link to my account</x-btn><x-btn kind="quiet" href="#">Cancel</x-btn></p>
    <p class="note">Linking puts the characters this app uploads under your account. You can revoke the app at any time. The code works once and expires in 10 minutes.</p>
</x-gate>
</x-layouts.sc>
