<x-layouts.app title="Your Scarlet account" current="" :signed-in="true">
<div class="wrap">
  <x-page-head title="Your account">
    <x-slot:sub>Scarlet stores the sign-in's id and nothing else about you: no email, no name.</x-slot:sub>
    <x-slot:filters><form method="post" action="/logout">@csrf<x-btn kind="quiet" type="submit">Sign out</x-btn></form></x-slot:filters>
  </x-page-head>
@if (session('notice'))
  <x-note role="status" style="margin-top:1rem">{{ session('notice') }}</x-note>
@endif
  <x-sheet>
    <x-module span="w12" :first="true" :last="true" id="h-i">
      <livewire:account.sign-in-methods />
    </x-module>
  </x-sheet>
</div>
</x-layouts.app>
