{{-- The filters most pages share: ruleset, server and class. rulesets and servers are seg items, classes a list of labels. --}}
@props(['rulesets', 'servers', 'classes', 'class' => null])
<x-seg label="Ruleset" :items="$rulesets" />
<x-seg label="Server" :items="$servers" />
<x-pick label="Class" :options="$classes" :selected="$class" />
