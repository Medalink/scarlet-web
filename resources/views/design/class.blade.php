@php($base = App\View\Design\Gallery::BASE)
@php($scene = \App\Support\Scene::tryFrom($d['page']->slug()))
@php($art = $scene ? \App\Support\SceneArt::stage($scene) : null)
<x-layouts.app :title="$d['title']" :base="$base" :index="false" :styles="['resources/css/class.css']">
@if ($art)
@vite('resources/js/scene.js')
@endif
<x-class.page :page="$d['page']" :icons="$d['icons']" :scene="$scene" :art="$art" />
<div class="wrap">
  <x-class.band :band="$d['band']" :icons="$d['icons']" />
</div>
</x-layouts.app>
