<?php

/**
 * What the worktree scripts share: the name of this checkout's database and Redis prefix, and
 * the settings of the PostgreSQL server. They run inside the tools container (bin/dev).
 */
const DATABASE_PREFIX = 'scarlet_web_wt_';

function checkoutRoot(): string
{
    return dirname(__DIR__, levels: 2);
}

/** The checkout folder as a short name: `scarlet-api-app` becomes `app`, the main `scarlet-web` becomes `main`. */
function worktreeSlug(): string
{
    $slug = basename(checkoutRoot())
        |> strtolower(...)
        |> (static fn (string $name): string => preg_replace('/[^a-z0-9]+/', '_', $name) ?? $name)
        |> (static fn (string $name): string => preg_replace('/^scarlet_(api|web)(_|$)/', '', $name) ?? $name)
        |> (static fn (string $name): string => trim($name, '_'));

    return $slug === '' ? 'main' : $slug;
}

function worktreeDatabase(): string
{
    return DATABASE_PREFIX.worktreeSlug();
}

/** @return array<string, string> the KEY=value lines of .env */
function readEnv(string $path): array
{
    $values = [];
    foreach (is_file($path) ? file($path, FILE_IGNORE_NEW_LINES) : [] as $line) {
        if (preg_match('/^([A-Z][A-Z0-9_]*)=(.*)$/', $line, $match) === 1) {
            $values[$match[1]] = trim($match[2], " \t\"'");
        }
    }

    return $values;
}

/** Sets KEY=value in .env, appending the line when the key is missing. */
function writeEnv(string $path, string $key, string $value): void
{
    $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
    $found = false;
    foreach ($lines as $index => $line) {
        if (str_starts_with($line, $key.'=')) {
            $lines[$index] = $key.'='.$value;
            $found = true;
        }
    }
    if (! $found) {
        $lines[] = $key.'='.$value;
    }
    file_put_contents($path, implode("\n", $lines)."\n");
}

/** @return array{host: string, port: string, user: string, password: string} */
function serverSettings(): array
{
    $env = readEnv(checkoutRoot().'/.env');

    return [
        'host' => getenv('DB_HOST') ?: ($env['DB_HOST'] ?? 'scarlet-web-pg'),
        'port' => getenv('DB_PORT') ?: ($env['DB_PORT'] ?? '5432'),
        'user' => getenv('DB_USERNAME') ?: ($env['DB_USERNAME'] ?? 'postgres'),
        'password' => getenv('DB_PASSWORD') ?: ($env['DB_PASSWORD'] ?? ''),
    ];
}

function adminConnection(): PDO
{
    $server = serverSettings();

    return new PDO(
        "pgsql:host={$server['host']};port={$server['port']};dbname=postgres",
        $server['user'],
        $server['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5],
    );
}
