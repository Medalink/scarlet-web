<?php

use Medalink\AppVersion\Models\ReleaseNote;
use Medalink\AppVersion\Models\ReleaseNoteRead;
use Medalink\AppVersion\ReleaseNotes\SecurityClassifier;

return [

    /*
    |--------------------------------------------------------------------------
    | Version sources
    |--------------------------------------------------------------------------
    |
    | `version_file` holds the semantic version (X.Y.Z) that `app:version:set`
    | bumps. `app:version` combines it with git metadata (semver tags, commit
    | counts, numstat totals) and writes the result to `json_path`, which is
    | what `AppVersion::data()` reads at runtime. When the JSON is missing the
    | reader falls back to the VERSION file with a build of 0 and commit "dev".
    |
    */

    'version_file' => env('APP_VERSION_FILE', base_path('VERSION')),

    'json_path' => env('APP_VERSION_JSON_PATH', storage_path('app/version.json')),

    'flat' => (bool) env('APP_VERSION_FLAT', false),

    'flat_path' => env('APP_VERSION_FLAT_PATH', base_path('version-info.json')),

    'repository_path' => base_path(),

    'tag_prefix' => 'v',

    /*
    |--------------------------------------------------------------------------
    | app:version:set behaviour
    |--------------------------------------------------------------------------
    |
    | Env files (relative to the repository path) whose APP_VERSION line is
    | rewritten when present. `.env` is never staged for commit.
    |
    */

    'env_files' => ['.env', '.env.example'],

    'commit_on_set' => true,

    'tag_on_set' => true,

    /*
    |--------------------------------------------------------------------------
    | Git hooks (app:version:install-hooks)
    |--------------------------------------------------------------------------
    |
    | Each listed hook gets an idempotent block that regenerates version.json
    | so the build number and diff stats stay current on every commit, merge,
    | checkout, and rebase. Honors `core.hooksPath` when the repository sets it.
    |
    */

    'hooks' => ['post-commit', 'post-merge', 'post-checkout', 'post-rewrite'],

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Set `migrations` to false when the host application ships its own
    | migrations for the two tables (for example to add grants, foreign keys,
    | or row-level security). Swap the models to host subclasses when the
    | application needs extra casts or relations on them.
    |
    */

    'migrations' => true,

    'models' => [
        'release_note' => ReleaseNote::class,
        'release_note_read' => ReleaseNoteRead::class,
    ],

    'cache' => [
        'store' => null,
        'ttl' => 3600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Release notes generation
    |--------------------------------------------------------------------------
    */

    'release_notes' => [

        // Hand-written notes keyed by literal semantic version. Parsed commit
        // subjects are merged in underneath unless they duplicate an entry.
        'custom_releases' => [],

        // What one release-notes item is: "commit" (every commit subject) or
        // "pull_request" (every merged pull request, titled by its merge body,
        // its commits folded in). Pull requests need the Git commit source and
        // a previous release to diff against; otherwise commits are used.
        'unit' => 'commit',

        // Pull request mode: branch-name regex => section, checked before the
        // title's own conventional type and leading verb.
        'branch_types' => [
            '/^(?:feat|feature)\b/i' => ReleaseNote::SECTION_NEW,
            '/^(?:fix|bugfix|hotfix|security)\b/i' => ReleaseNote::SECTION_FIXED,
            '/^(?:perf|refactor|style|improve|ui|ux)\b/i' => ReleaseNote::SECTION_IMPROVED,
        ],

        // Pull request mode: pull requests from matching branches are dropped.
        'ignore_branches' => [
            '/^(?:docs?|tests?|chore|ci|build)\b/i',
            '/^dependabot\//i',
        ],

        // Pull request mode: regexes with one capture group, tried against the
        // title and the branch. Pull requests capturing the same value become
        // one change (e.g. '/\bplan (\d+)\b/i' joins the phases of a plan),
        // read as its biggest member with the others as details.
        'rollup_keys' => [],

        // Commit subjects (and pull request titles) matching any pattern are
        // dropped before parsing.
        'ignore_patterns' => [
            '/^\s*merge\b/i',
            '/\bdependabot\b/i',
            '/^(docs?|test|tests|chore|ci|build)(\(.+\))?:/i',
            '/\b(?:test|tests|testing|spec)\b/i',
            '/\b(?:cursor|codex|claude|copilot)\b/i',
            '/\bAGENTS\.md\b/i',
            '/\bCLAUDE\.md\b/i',
            '/\bsettings\.json\b/i',
            '/\b\.plan\.md\b/i',
            '/\bimplementation plans?\b/i',
            '/\bversion bump\b/i',
            '/\bbump version\b/i',
            '/\bschema dump\b/i',
            '/\bfor reference\b/i',
            '/^\s*ignore(?:s|d)?\b/i',
            '/^\s*wip\b/i',
            '/^\s*bump\b/i',
            '/^\s*revert\s+"?revert\b/i',

            // Commit subjects are public text on /release-notes. A link to another site and anything that looks
            // like a key stay off the page. AGENTS.md keeps player and service names out of subjects, and the
            // history was searched for them before the first release.
            '/https?:\/\//i',
            '/\b(?:ghp_|github_pat_|sk-)[A-Za-z0-9_]{8,}|[A-Za-z0-9+\/=_-]{40,}/',

            // Work on the repo's own tooling, docs and layout, which no visitor can see.
            '/\bclippy\b|\bcygpath\b|\bbin\/dev\b|\bdev image\b/i',
            '/\bworktree\b|\bpage gallery\b|\bscreenshot tool\b|\bone read Action\b/i',
            '/^(?:Bring|Describe|Design|Write the design|State the local|Say when|Move the static|Measure)\b/',
        ],

        // Ordered list of {title, summary, patterns[], short?}; the first
        // match wins and anything unmatched lands in "General Improvements".
        // `short` is the label used in headlines ("New in Editor and Billing").
        'feature_groups' => [
            [
                'title' => 'Maps',
                'short' => 'Maps',
                'summary' => 'The world map, its tiles and its zones.',
                'patterns' => ['/\bmap\b|\btiles\b|\bzone rectangles\b|\bheat\b/i'],
            ],
            [
                'title' => 'Accounts and uploads',
                'short' => 'Accounts',
                'summary' => 'Signing in, linking the app and uploading sessions.',
                'patterns' => ['/\bsign(?:ed|ing)? in\b|\/account\b|\bupload|\bsharing\b|\bdecod|\bsessions?\b/i'],
            ],
            [
                'title' => 'Public API',
                'short' => 'API',
                'summary' => 'The read API and its reference.',
                'patterns' => ['/\bAPI\b/'],
            ],
            [
                'title' => 'Pages',
                'short' => 'Pages',
                'summary' => 'Character, creature, item, zone and quest pages, boards and search.',
                'patterns' => ['/\bcharacter|\bcreature|\bitem\b|\bquest|\bleaderboard|\bsearch\b|\bstats\b|\brulesets?\b|\blanding\b|\bdemo\b|\bpage\b|\bchart\b|\bheader\b|\bboss\b/i'],
            ],
            [
                'title' => 'Part format',
                'short' => 'Parts',
                'summary' => 'The format the app records and uploads.',
                'patterns' => ['/\bscarlet-part\b|\bpart format\b|\bparts?\b|\bdecoder\b|\bSZ\b/i'],
            ],
        ],

        // Extra imperative verbs (base form) per section, added on top of the
        // compiler's built-in lexicon. Subjects are classified by conventional
        // type first, then the leading verb, then fix-signal keywords.
        'section_prefixes' => [
            ReleaseNote::SECTION_NEW => ['draw', 'serve', 'control', 'sign', 'publish'],
            ReleaseNote::SECTION_IMPROVED => [],
            ReleaseNote::SECTION_FIXED => [],
        ],

        // "past" turns leading verbs into past tense ("Added…", "Fixed…",
        // "Pinned X and linked Y"); "imperative" keeps subjects as written.
        'voice' => 'past',

        // Regex => replacement applied to every subject before rewriting.
        'cleanup_replacements' => [
            '/\bUI\b/i' => 'interface',
            '/\bauth\b/i' => 'sign-in',
        ],

        // Regex => full replacement sentence for subjects that deserve a
        // hand-written explanation.
        // These subjects mix two verbs, put a colon after a long lead-in, or name a tool; the sentence is written by hand.
        'detail_rewrites' => [
            '/^Show a boss as/i' => 'Bosses show as "Boss" instead of "Level -1", and leaderboard columns have more room.',
            '/^Draw the creature page/i' => 'The creature page draws its map, and the pace chart names its ruleset.',
            '/^Cut page time/i' => 'Pages load faster, and the public API reference is written.',
            '/^Name rulesets without raw numbers/i' => 'Rulesets are named without raw numbers, and counts use the right plural.',
            '/^Control your characters/i' => 'The /account page controls your characters, installs, sharing and API keys, and links the app.',
            '/^Sign in with Google or Battle\.net/i' => 'Sign in with Google or Battle.net works, and /account shows your sign-in methods.',
            '/^Place recorded points on the world map/i' => 'Recorded points are placed on the world map, and world heat is counted on square cells.',
            '/^Serve the world map tiles/i' => 'The world map tiles are served, and zone rectangles are loaded into the database.',
            '/^Build the world map tiles/i' => 'Built the world map tiles and zone rectangles.',
            '/^Serve the landing page/i' => 'The landing page and the demo character are served by the app.',
            '/^Publish the Scarlet website/i' => 'Published the Scarlet website: a landing page and a demo character overview.',
            '/^License the Scarlet website/i' => 'Licensed the website under GPL-3.0.',
            '/^Add the upload schema/i' => 'Added the upload schema: installs, characters, parts, sessions and the fact tables.',
            '/^Add the Laravel app/i' => 'Added the web app.',
            '/^Add the decoder, the row reference/i' => 'Added the session decoder.',
            '/^Build parts, wire batches/i' => 'Added parts, wire batches, and the pack, batch and unbatch commands.',
            '/^Add the scarlet-part crate/i' => 'Added scarlet-part, the part format with its sharing groups and name references.',
            '/^Add the page layout/i' => 'Added the page layout, the formatters and the chart module.',
            '/^Decode uploaded sessions/i' => 'Uploaded sessions are decoded into the fact tables, and 20 rollups stay current.',
            '/^Show the stats dashboard/i' => 'The stats dashboard and the rulesets comparison show real data.',
        ],

        // Which changes a release lists as security fixes (SecurityClassifier,
        // read from the stored notes, so it covers every published release).
        // Only `sections` count; `high` and `medium` patterns rate a change by
        // its text and details; `exclude` drops medium matches on the text
        // alone; a fix in an area matching `areas` counts as low. A key left
        // out keeps SecurityClassifier::DEFAULTS.
        'security' => SecurityClassifier::DEFAULTS,

        'limits' => [
            'per_section' => 3,
            'max_releases_in_modal' => 3,
            'max_items_in_modal' => 9,
            'initial_range_commits' => 1000,
            'manual_history_releases' => 1,
            'headline_areas' => 3,
            // How many of the newest releases a version-history page renders
            // in full; everything older is a collapsed digest (ReleaseNotesArchive).
            'archive_expanded_releases' => 2,
            // Pull request mode: the changes each feature area shows as its
            // highlights, and how many areas the update modal lists.
            'highlights_per_group' => 2,
            'modal_groups' => 7,
        ],

        // Null headline/summary resolve to "{app.name} has been updated" and
        // a generic sentence at runtime.
        'fallback' => [
            'headline' => null,
            'summary' => null,
            'sections' => [
                ReleaseNote::SECTION_NEW => [],
                ReleaseNote::SECTION_IMPROVED => [
                    'Improved reliability and polished the overall experience.',
                ],
                ReleaseNote::SECTION_FIXED => [],
            ],
        ],
    ],
];
