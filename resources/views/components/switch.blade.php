{{-- An on/off switch. `label` is what a screen reader hears; name is the field it submits. --}}
@props(['label', 'checked' => false, 'disabled' => false, 'name' => null])
<label class="sw"><input type="checkbox"@if ($name) name="{{ $name }}"@endif @checked($checked) @disabled($disabled) aria-label="{{ $label }}"><i></i></label>
