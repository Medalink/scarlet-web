<?php

return [

    // A bucket on a public page is shown only when at least this many characters contributed
    // to it: 5 on a public deploy, 1 in development.
    'min_characters' => (int) env('SCARLET_MIN_CHARACTERS', 1),

    // A third sign-in button for local development. It exists only in the `local` environment, whatever this says.
    'dev_login' => (bool) env('SCARLET_DEV_LOGIN', false),

    // The scarlet-part binary the queue job runs to decode an uploaded part (built from part/).
    'part_bin' => env('SCARLET_PART_BIN') ?: base_path('part/target/release/scarlet-part'),

    // Where unpacked request bodies and decoder input live while one request or job runs.
    'work_dir' => env('SCARLET_WORK_DIR') ?: sys_get_temp_dir(),

    // The limits of docs/DESIGN.md "Upload protocol" and "Security".
    'upload' => [
        'max_body_bytes' => 4 << 20,
        'max_inflated_bytes' => 64 << 20,
        'max_parts_per_request' => 20_000,
        'max_bytes_per_install_per_day' => 200 << 20,
        'max_characters_per_install' => 50,
        'clock_window_seconds' => 300,
        // The most that sync, manifest and status answer in one response.
        'max_manifest_parts' => 200_000,
        'link_code_minutes' => 10,
    ],

    // Laravel rate limiter budgets: `registration` per IP, `upload` per install and per IP.
    'rate' => [
        'registration' => ['per_ip' => 5, 'seconds' => 3600],
        'upload' => ['per_install' => 240, 'per_ip' => 600, 'seconds' => 60],
    ],

    // The newest desktop app build, read from the GitHub releases of Medalink/scarlet-app (App\Actions\Release\ShowAppDownload).
    // An answer is reused for `fresh_seconds`; after a failed read GitHub is not asked again for `retry_seconds`.
    'app_download' => [
        'fresh_seconds' => 600,
        'retry_seconds' => 60,
        'timeout_seconds' => 3,
    ],

    // Queue names and the bounded limits of the jobs (App\Contracts\BoundedJob).
    'queues' => [
        'ingest' => 'ingest',
        'rollups' => 'rollups',
    ],
    'ingest' => [
        'timeout' => 240,
        'tries' => 3,
        'memory_mb' => 768,
        'insert_rows' => 4000,
    ],
    'rollups' => [
        'timeout' => 900,
        'tries' => 2,
        'memory_mb' => 256,
        // How long a refresh waits so the ingests of one upload share it.
        'debounce_seconds' => 5,
    ],

];
