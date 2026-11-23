@php($scene = \App\Support\Scene::tryFrom($page->slug()))
@php($art = $scene ? \App\Support\SceneArt::stage($scene) : null)
<x-layouts.app :title="$title" :description="$description" current="" :canonical="$canonical" :index="$index" :styles="['resources/css/class.css']">
@if ($art)
@vite('resources/js/scene.js')
@endif
<x-class.page :page="$page" :icons="$icons" :scene="$scene" :art="$art" />
</x-layouts.app>
