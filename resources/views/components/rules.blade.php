{{-- Counts on a survey rule, each ending at the next round number. rows: App\View\Pages\StatsGame Rule. --}}
@props(['rows'])
<ol class="rules">@foreach ($rows as $row)<li><span class="rule-l"><x-icon :name="$row['icon']" />{{ $row['label'] }}</span><b class="rule-v">{{ $row['value'] }}</b><span class="rule" style="--at:{{ $row['at'] }}%"><i></i></span><span class="rule-t"><b>{{ $row['left'] }}</b><span>{{ $row['target'] }}</span></span></li>@endforeach</ol>
