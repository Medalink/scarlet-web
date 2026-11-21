<?php

return [

    // A bucket on a public page is shown only when at least this many characters contributed
    // to it: 5 on a public deploy, 1 in development.
    'min_characters' => (int) env('SCARLET_MIN_CHARACTERS', 1),

    // The scarlet-part binary the queue job runs to decode an uploaded part (built from part/).
    'part_bin' => env('SCARLET_PART_BIN') ?: base_path('part/target/release/scarlet-part'),

];
