{{-- The character against others of its class: comparison is App\Support\ClassPage\Comparison, drawn on the site's rules. --}}
@props(['comparison', 'icons', 'id' => 'h-vs'])
<section class="mod w12 first last" aria-labelledby="{{ $id }}">
<h2 id="{{ $id }}">Against other {{ $comparison->of }}</h2>
<p class="sub">Public {{ $comparison->of }} of every ruleset, level {{ $comparison->from }} to {{ $comparison->to }}. {{ number_format($comparison->characters) }} characters.</p>
<ol class="rules">@foreach ($comparison->standings as $row)<li><span class="rule-l"><x-class.icon :icon="$row->icon" :icons="$icons" />{{ $row->label }}</span><b class="rule-v">{{ $row->unit->figure($row->value) }}</b><span class="rule" style="--at:{{ max(0, min(100, $row->percent)) }}%"><i></i></span><span class="rule-t"><b>More than {{ max(0, min(100, $row->percent)) }}% of {{ $comparison->of }}</b><span></span></span></li>@endforeach</ol>
</section>
