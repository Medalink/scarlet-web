<x-module :span="$span" :first="$first" :last="$last" :level="$level" id="h-talents" heading="Latest builds">
  @foreach ($d['talents']['groups'] as $group)
  <div @class(['talent-group', 'first' => $loop->first])>
    @if ($group['label'])<h3 class="sub">{{ $group['label'] }}</h3>@endif
    <p class="total">{{ $group['summary'] }}</p>
    @if ($group['tabs'])<x-rank :rows="$group['tabs']" />@endif
    @if ($group['tree'])<x-talent-tree :tree="$group['tree']" :label="'Talent tree of '.$d['name'].', group '.$loop->iteration" />@endif
    @foreach ($group['sections'] as $section)
    @if ($section['heading'])<h4 class="sub">{{ $section['heading'] }}</h4>@endif
    <x-table :rows="$section['rows']" :columns="$group['columns']" style="margin-top:.6rem" />
    @endforeach
  </div>
  @endforeach
</x-module>
