<x-layouts.app title="Your Scarlet account" current="" :signed-in="true" :index="false" :brand="false">
<div class="wrap">
  <x-page-head title="Your account">
    <x-slot:sub>Scarlet stores the sign-in's id and nothing else about you: no email, no name.</x-slot:sub>
    <x-slot:filters><form method="post" action="/logout">@csrf<x-btn kind="quiet" type="submit">Sign out</x-btn></form></x-slot:filters>
  </x-page-head>
@if (session('notice'))
  <x-note role="status" style="margin-top:1rem">{{ session('notice') }}</x-note>
@endif
  <x-sheet>
    <x-module span="w7" :first="true" id="h-c" heading="Characters">
      <x-slot:controls><span><a href="/account/characters">Compare characters</a> · <a href="/account/professions">Your professions</a> · <a href="/account/camps">Your camps</a></span></x-slot:controls>
      <livewire:account.characters />
    </x-module>
    <x-module span="w5" :last="true" id="h-i" heading="Installs">
      <livewire:account.installs />
      <livewire:account.sign-in-methods />
    </x-module>
    <x-module span="w12" :first="true" :last="true" id="h-u" heading="Recent uploads">
      <livewire:account.upload-log />
    </x-module>
    <x-module span="w12" :first="true" :last="true" id="h-s" heading="What you share">
      <x-slot:controls><x-state>Switches change what the app sends next. Recording and the app on your PC are never affected.</x-state></x-slot:controls>
      <livewire:account.sharing />
    </x-module>
    <x-module span="w7" :first="true" id="h-k" heading="API keys">
      <livewire:account.api-keys />
    </x-module>
    <x-module span="w5" :last="true" id="h-x" heading="Delete everything you uploaded">
      <livewire:account.delete-uploads />
    </x-module>
  </x-sheet>
</div>
</x-layouts.app>
