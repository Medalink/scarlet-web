@props(['title', 'current', 'heading' => null])
<x-layouts.sc :title="$title.' · Scarlet'" current="account" :signed-in="true" :index="false" :brand="false">
<div class="wrap pb-20 pt-10">
  <div class="flex flex-wrap items-end gap-x-6 gap-y-2">
    <h1 class="sc-title" style="font-size:60px">{{ $heading ?? $title }}</h1>
    <form method="post" action="/logout" class="mb-2 ml-auto">@csrf<button type="submit" class="sc-btn quiet small">Sign out</button></form>
  </div>
  <x-sc.page-nav class="mt-6" :pages="['/account' => 'Account', '/account/privacy' => 'Privacy', '/account/api-keys' => 'API keys', '/account/storage' => 'Storage']" :current="$current" />
@if (session('notice'))
  <p class="sc-note mt-4" role="status">{{ session('notice') }}</p>
@endif
{{ $slot }}
</div>
</x-layouts.sc>
