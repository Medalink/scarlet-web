@php($base = App\View\Design\Gallery::BASE)
<x-layouts.app title="Your Scarlet account" :signed-in="true" :base="$base">
<div class="wrap">
  <x-page-head title="Your account">
    <x-slot:sub>Signed in with Google. Scarlet stores the sign-in's id and nothing else about you: no email, no name.</x-slot:sub>
    <x-slot:filters><x-btn kind="quiet" href="#">Sign out</x-btn></x-slot:filters>
  </x-page-head>
  <x-sheet>
    <x-module span="w7" :first="true" id="h-c" heading="Characters">
      <x-rows>
        <x-row title="Maren-Ashlow" :href="$base.'/character'" body="Level 38 Orc Warrior · 214 sessions · last upload 12 minutes ago"><x-state :on="true">Public</x-state><x-switch label="Public page for Maren-Ashlow" :checked="true" /></x-row>
        <x-row title="Show gold and trades on the public page" body="Off: visitors see no balance, vendors, auctions, mail or trades for Maren-Ashlow."><x-switch label="Show economy for Maren-Ashlow" /></x-row>
        <x-row title="Tolvir-Greane" :href="$base.'/character'" body="Level 12 Troll Mage · 31 sessions · last upload 3 days ago"><x-state>Private</x-state><x-switch label="Public page for Tolvir-Greane" /></x-row>
      </x-rows>
    </x-module>
    <x-module span="w5" :last="true" id="h-i" heading="Installs">
      <x-rows>
        <x-row title="Windows PC, linked 20 Nov 2026" body="Last upload 12 minutes ago · 2 characters"><x-btn kind="danger">Revoke</x-btn></x-row>
        <x-row title="Windows PC, linked 2 Nov 2026" body="Last upload 18 days ago · 1 character"><x-btn kind="danger">Revoke</x-btn></x-row>
      </x-rows>
      <x-note>A revoked install can no longer upload. What it uploaded stays until you remove it.</x-note>
      <h2 style="margin-top:2rem;font-size:1.9rem">Sign-in methods</h2>
      <x-rows>
        <x-row title="Google" body="Linked 2 Nov 2026"><x-state :on="true">Linked</x-state></x-row>
        <x-row title="Battle.net" body="Not linked. Your BattleTag is never stored."><x-btn kind="quiet" href="#">Link</x-btn></x-row>
      </x-rows>
    </x-module>
    <x-module span="w12" :first="true" :last="true" id="h-s" heading="What you share">
      <x-slot:controls><x-state>Switches change what the app sends next. Recording and the app on your PC are never affected.</x-state></x-slot:controls>
      <x-rows>
        @foreach ($d['groups'] as [$name, $what, $on, $locked])
        <x-row :title="$name" :body="$what">@unless ($on)<x-btn kind="danger">Remove from server</x-btn>@endunless<x-switch :label="'Share '.$name" :checked="$on" :disabled="$locked" /></x-row>
        @endforeach
      </x-rows>
    </x-module>
    <x-module span="w7" :first="true" id="h-k" heading="API keys">
      <x-rows>
        <x-row title="guild dashboard" body="Made 19 Nov 2026 · last used today · 600 requests a minute"><x-btn kind="danger">Delete</x-btn></x-row>
      </x-rows>
      <p style="margin-top:1rem"><x-btn kind="quiet" href="#">Make a key</x-btn></p>
      <x-note>The <a href="{{ $base }}/developers">public API</a> needs no key at 60 requests a minute.</x-note>
    </x-module>
    <x-module span="w5" :last="true" id="h-x" heading="Delete everything you uploaded">
      <p class="sub">Removes every character, session and number that came from your installs, and takes them out of the totals. Your recordings on your PC are not touched.</p>
      <p style="margin-top:1.1rem"><x-btn kind="danger">Delete everything I uploaded</x-btn></p>
    </x-module>
  </x-sheet>
</div>
</x-layouts.app>
