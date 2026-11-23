@props(['recap', 'compact' => false, 'families' => []])
<div {{ $attributes }} data-strip="{{ json_encode(App\View\Sc\RecapView::strip($recap, $families), JSON_THROW_ON_ERROR) }}"@if ($compact) data-compact="1"@endif></div>
