{{-- A drop-down filter. options: a list of labels; selected: the chosen label, the first by default. --}}
@props(['label', 'options', 'selected' => null])
<select class="pick" aria-label="{{ $label }}">@foreach ($options as $option)<option @selected($option === ($selected ?? $options[0]))>{{ $option }}</option>@endforeach</select>
