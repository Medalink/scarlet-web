<x-account.shell title="Privacy" current="/account/privacy">
  <section class="mt-10" id="characters">
    <h2 class="sc-h" style="font-size:30px">Character pages</h2>
    <livewire:account.characters mode="privacy" />
  </section>

  <section class="mt-12" id="sharing">
    <h2 class="sc-h" style="font-size:30px">What the app sends</h2>
    <p class="sc-note mt-2">Turning a group off stops the app sending it. What is already stored stays until you remove it. Recording and the app on your PC are never affected.</p>
    <livewire:account.sharing />
  </section>
</x-account.shell>
