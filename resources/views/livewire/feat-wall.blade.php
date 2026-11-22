<div>
@if ($section)
<x-feat-wall :feats="$section" :pin="true" />
@endif
<x-feat-toasts :new="$new" />
</div>
