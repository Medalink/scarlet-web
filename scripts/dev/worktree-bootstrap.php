<?php

/**
 * Gets a checkout ready to run: .env with this worktree's database and Redis prefixes, composer
 * and pnpm dependencies, the private database, the Vite build and the migrations. Idempotent.
 * Run it inside the tools container with `bin/dev bootstrap`.
 */
require __DIR__.'/worktree.php';

$root = checkoutRoot();
chdir($root);
$slug = worktreeSlug();
$prefix = DATABASE_PREFIX.$slug;

$step = function (string $name, string $command) {
    echo "==> {$name}\n";
    passthru($command, $status);
    if ($status !== 0) {
        fwrite(STDERR, "{$name} failed with exit code {$status}.\n");
        exit($status);
    }
};

echo "==> .env\n";
if (! is_file('.env')) {
    copy('.env.example', '.env');
}
writeEnv('.env', 'DB_DATABASE', $prefix);
writeEnv('.env', 'REDIS_PREFIX', $prefix.'_');
writeEnv('.env', 'CACHE_PREFIX', $prefix.'_cache_');
writeEnv('.env', 'HORIZON_PREFIX', $prefix.'_horizon:');
writeEnv('.env', 'SESSION_COOKIE', $prefix.'_session');
$port = getenv('SCARLET_HOST_PORT');
if ($port !== false && $port !== '') {
    writeEnv('.env', 'APP_URL', 'http://127.0.0.1:'.$port);
}

$step('Composer dependencies', 'composer install --no-interaction --no-progress --no-ansi');
if ((readEnv('.env')['APP_KEY'] ?? '') === '') {
    $step('Application key', 'php artisan key:generate --no-interaction');
}
$step('Private database', 'php scripts/dev/worktree-db.php provision');
$step('pnpm dependencies', 'pnpm install --frozen-lockfile');
$step('Vite build', 'pnpm run build');
$step('Migrations', 'php artisan migrate --force --no-interaction');
echo "Ready: database {$prefix}.\n";
