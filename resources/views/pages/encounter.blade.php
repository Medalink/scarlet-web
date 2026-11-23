<x-layouts.sc :title="'Encounter '.$d['id'].': what it drops'" :description="'Items that boss encounter '.$d['id'].' dropped in WoW Forever.'">
<div class="wrap">
  <x-page-head :title="'Encounter '.$d['id']" :single="true">
    <x-slot:sub>{{ $d['sub'] }}</x-slot:sub>
  </x-page-head>
  <x-sheet>
    <x-module span="w12" :first="true" :last="true" id="h-d" heading="Drops">
      <x-table :rows="$d['rows']" :columns="[['label' => 'Item'], ['label' => 'Dropped', 'numeric' => true], ['label' => 'Characters', 'numeric' => true]]" style="margin-top:1.1rem" />
      <p class="note">A drop is counted once however many players of the group uploaded it.</p>
    </x-module>
  </x-sheet>
</div>
</x-layouts.sc>
