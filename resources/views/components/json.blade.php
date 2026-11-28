{{-- A code block of the reference. The slot is raw text; wrap a key in <b> and a string value in <i> by hand. The button copies the block's text. --}}
<div class="json-box"><button type="button" class="json-copy" data-copy>Copy</button><pre class="json">{{ $slot }}</pre></div>
