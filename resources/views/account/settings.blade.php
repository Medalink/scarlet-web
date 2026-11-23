<x-layouts.sc title="Account settings · Scarlet" current="account" :signed-in="true" :index="false" :brand="false">
<div class="wrap pb-20 pt-10">
  <div class="flex flex-wrap items-end gap-x-6 gap-y-3">
    <h1 class="sc-title" style="font-size:60px">Settings</h1>
    <a href="/account" class="ml-auto text-[14px]">Back to your characters</a>
    <form method="post" action="/logout">@csrf<button type="submit" class="sc-btn quiet small">Sign out</button></form>
  </div>
@if (session('notice'))
  <p class="sc-note mt-4" role="status">{{ session('notice') }}</p>
@endif
  <p class="sc-note mt-4">Scarlet stores the sign-in's id and nothing else about you: no email, no name.</p>

  <section class="mt-12" id="characters">
    <h2 class="sc-h" style="font-size:30px">Sharing per character</h2>
    <livewire:account.characters />
  </section>

  <section class="mt-12" id="sharing">
    <h2 class="sc-h" style="font-size:30px">What the app sends</h2>
    <p class="sc-note mt-2">Switches change what the app sends next. Recording and the app on your PC are never affected.</p>
    <livewire:account.sharing />
  </section>

  <section class="mt-12" id="installs">
    <h2 class="sc-h" style="font-size:30px">Linked installs</h2>
    <livewire:account.installs />
  </section>

  <section class="mt-12" id="uploads">
    <h2 class="sc-h" style="font-size:30px">Recent uploads</h2>
    <livewire:account.upload-log />
  </section>

  <section class="mt-12" id="sign-in">
    <h2 class="sc-h" style="font-size:30px">Sign-in methods</h2>
    <livewire:account.sign-in-methods />
  </section>

  <section class="mt-12" id="keys">
    <h2 class="sc-h" style="font-size:30px">API keys</h2>
    <livewire:account.api-keys />
  </section>

  <section class="mt-12" id="delete">
    <h2 class="sc-h" style="font-size:30px">Delete everything you uploaded</h2>
    <livewire:account.delete-uploads />
  </section>
</div>
</x-layouts.sc>
