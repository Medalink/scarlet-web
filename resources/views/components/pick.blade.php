{{-- A drop-down filter. options: a list of labels, or of [label, value] pairs. A pair's value is a link the page opens when it is chosen,
     or with `fit` the id of a zone the world map with that canvas id moves to. selected: the chosen label, the first by default. --}}
@props(['label', 'options', 'selected' => null, 'fit' => null])
@php($chosen = $selected ?? (is_array($options[0]) ? $options[0][0] : $options[0]))
<select class="pick" aria-label="{{ $label }}"@if ($fit) data-fit-map="{{ $fit }}"@elseif (is_array($options[0])) data-nav @endif>@foreach ($options as $option)@php([$text, $value] = is_array($option) ? $option : [$option, null])<option @selected($text === $chosen)@if ($value !== null) value="{{ $value }}"@endif>{{ $text }}</option>@endforeach</select>
