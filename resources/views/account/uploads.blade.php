<x-account.shell title="Upload history" current="/account/storage">
  <p class="mt-6 text-[14px]"><a href="/account/storage">Back to storage</a></p>
@if ($history['uploads'] === [])
  <p class="sc-note mt-6">Nothing has been uploaded yet.</p>
@else
  <div class="mt-4 overflow-x-auto">
    <table class="w-full text-left text-[14px]" id="history">
      <thead class="sc-label"><tr><th class="py-2 pr-4 font-normal">Uploaded</th><th class="py-2 pr-4 font-normal">Character</th><th class="py-2 pr-4 text-right font-normal">Parts</th><th class="py-2 pr-4 text-right font-normal">Sessions</th><th class="py-2 text-right font-normal">Size</th></tr></thead>
      <tbody>
@foreach ($history['uploads'] as $upload)
        <tr class="border-t border-line"><td class="py-2 pr-4">{{ \App\View\Dates::moment($upload['at']) }}</td><td class="py-2 pr-4">{{ \App\View\Format::characterName($upload['character']) }}</td><td class="py-2 pr-4 text-right">{{ \App\View\Format::number($upload['parts']) }}</td><td class="py-2 pr-4 text-right">{{ \App\View\Format::number($upload['sessions']) }}</td><td class="py-2 text-right">{{ \App\View\Format::bytes($upload['bytes']) }}</td></tr>
@endforeach
      </tbody>
    </table>
  </div>
  <p class="mt-6 flex gap-6 text-[14px]">
@if ($page > 1)<a href="/account/storage/history{{ $page > 2 ? '?page='.($page - 1) : '' }}">Newer</a>@endif
@if ($history['more'])<a href="/account/storage/history?page={{ $page + 1 }}">Older</a>@endif
  </p>
@endif
</x-account.shell>
