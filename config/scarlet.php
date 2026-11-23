<?php

return [

    // A bucket on a public page is shown only when at least this many characters contributed
    // to it: 5 on a public deploy, 1 in development.
    'min_characters' => (int) env('SCARLET_MIN_CHARACTERS', 1),

    'continuity_seconds' => 300,

    'addon_counts_pages' => (bool) env('ADDON_COUNTS_PAGES', true),

    'admins' => array_values(array_filter(array_map(trim(...), explode(',', (string) env('SCARLET_ADMINS', ''))))),

    'api_calls_days' => 30,

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
        'max_bytes_per_install_per_day' => 2 << 30,
        'max_characters_per_install' => 50,
        'clock_window_seconds' => 300,
        'max_manifest_bytes' => 32 << 20,
        'max_manifest_values' => 6_000_000,
        'max_manifest_parts' => 200_000,
        'max_wanted_ids' => (int) env('SCARLET_MAX_WANTED_IDS', 50_000),
        'link_code_minutes' => 10,
    ],

    // Laravel rate limiter budgets: `registration` per IP, `upload` per install and per IP.
    'rate' => [
        'registration' => ['per_ip' => (int) env('SCARLET_RATE_REGISTRATION_PER_IP', 5), 'seconds' => 3600],
        'upload' => [
            'per_install' => (int) env('SCARLET_RATE_UPLOAD_PER_INSTALL', 240),
            'per_ip' => (int) env('SCARLET_RATE_UPLOAD_PER_IP', 600),
            'seconds' => 60,
        ],
        'public' => [
            'anonymous' => (int) env('SCARLET_RATE_PUBLIC_ANONYMOUS', 60),
            'token' => (int) env('SCARLET_RATE_PUBLIC_TOKEN', 600),
        ],
    ],

    'page' => [
        'per_address' => (int) env('SCARLET_RATE_PAGE_PER_ADDRESS', 240),
        'seconds' => 60,
        'statement_seconds' => 10,
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
        'backfill' => 'backfill',
        'rollups' => 'rollups',
        'armory' => 'armory',
    ],
    'ingest' => [
        'timeout' => 240,
        'tries' => 3,
        'memory_mb' => 768,
        'insert_rows' => 4000,
        'sweep_after_seconds' => 300,
        'sweep_give_up_hours' => 72,
        'sweep_limit' => 500,
        'max_parts_per_session' => 64,
        'max_session_bytes' => 128 << 20,
        'max_session_lines' => 4_000_000,
        'name_votes_per_install_day' => 20_000,
        'sweep_max_failures' => 3,
    ],
    // The Forever Armory names the ruleset of a character (docs/DESIGN.md, Rulesets). One request at a time.
    'armory' => [
        'region' => env('SCARLET_ARMORY_REGION', 'us'),
        'user_agent' => 'scarletweb.io ruleset lookup (https://scarletweb.io)',
        'timeout' => 20,
        'pause_ms' => 2000,
        // How long one run of the job looks characters up before the next run takes the rest.
        'job_seconds' => 200,
        'realm_quorum' => (int) env('SCARLET_ARMORY_REALM_QUORUM', 2),
    ],
    'ahledger' => [
        'base_url' => env('SCARLET_AHLEDGER_URL', 'https://api.ahledger.com'),
        'regions' => array_values(array_filter(explode(',', (string) env('SCARLET_AHLEDGER_REGIONS', 'us')))),
        'user_agent' => 'scarletweb.io price import (https://scarletweb.io)',
        'timeout' => 30,
        'pause_ms' => 1000,
    ],
    'edge' => [
        'page_seconds' => 60,
        'api_seconds' => 300,
    ],
    'storage' => [
        'chunk_dense' => env('SCARLET_CHUNK_DENSE', '1 day'),
        'chunk_sparse' => env('SCARLET_CHUNK_SPARSE', '7 days'),
        'compress_after' => env('SCARLET_COMPRESS_AFTER', '7 days'),
        'segmentby' => env('SCARLET_COMPRESS_SEGMENTBY', 'character_id'),
        'orderby' => env('SCARLET_COMPRESS_ORDERBY', 'session_start, at'),
        'refresh_start_offset' => env('SCARLET_REFRESH_START_OFFSET', '3 days'),
        'refresh_slice_days' => (int) env('SCARLET_REFRESH_SLICE_DAYS', 7),
        'retention' => env('SCARLET_STORAGE_RETENTION'),
    ],
    'rollups' => [
        'timeout' => 900,
        'tries' => 6,
        'memory_mb' => 256,
        // How long a refresh waits so the ingests of one upload share it.
        'debounce_seconds' => 5,
        'read_fresh_seconds' => 60,
        'read_stale_seconds' => 300,
    ],
    'feats' => [
        'timeout' => 120,
        'tries' => 2,
        'memory_mb' => 256,
        'holders_seconds' => 900,
    ],
    'standings' => [
        'timeout' => 120,
        'tries' => 2,
        'memory_mb' => 256,
    ],

];
