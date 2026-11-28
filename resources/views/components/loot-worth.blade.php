{{-- What a drop table's items are worth a corpse or an opening, from LootRows::worth, with the credit AHledger's terms ask for.
     worth: ['per', 'windows', 'unpriced', 'scanned_at']; noun: corpse, opening or skin. --}}
@props(['worth', 'noun'])
@php($unpriced = $worth['unpriced'] ? ' or '.\App\View\Format::count($worth['unpriced'], 'item').' with no price' : '')
<p class="note">Items worth <strong>{{ $worth['per'] }}</strong> a {{ $noun }} on average over {{ \App\View\Format::count($worth['windows'], $noun) }}, not counting coin{{ $unpriced }}. Each item is valued at the median of <a href="{{ \App\Models\Market::SITE }}" rel="noopener">AHledger</a>'s US auction house prices
@if ($worth['scanned_at'])
(scans to {{ \App\View\Dates::moment($worth['scanned_at']) }})
@endif
or at the vendor price when that is higher, the item is poor or it binds when picked up.</p>
