@props(['label', 'checked' => false, 'disabled' => false])
<label class="sc-toggle"><input {{ $attributes }} type="checkbox" @checked($checked) @disabled($disabled) aria-label="{{ $label }}"><i></i></label>
