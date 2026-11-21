<?php

/**
 * This checkout's private database on the shared PostgreSQL server. Run it through the tools
 * container: bin/php scripts/dev/worktree-db.php <command>
 *
 *   status      the databases of every worktree on the server
 *   provision   create this checkout's database when it is missing (a copy of template1, which
 *               already has TimescaleDB); migrations run separately
 *   reset       drop and create it again, empty
 *   drop        drop it
 *   list        names only
 *
 * Only databases named scarlet_web_wt_* are ever touched.
 */
require __DIR__.'/worktree.php';

$command = $argv[1] ?? 'status';
$name = worktreeDatabase();
$admin = adminConnection();

$exists = fn (string $database): bool => (bool) $admin->query('select 1 from pg_database where datname = '.$admin->quote($database))->fetchColumn();
$create = function () use ($admin, $name): void {
    $admin->exec('create database "'.$name.'"');
    echo "Created {$name}.\n";
};
$drop = function () use ($admin, $name): void {
    $admin->exec('select pg_terminate_backend(pid) from pg_stat_activity where datname = '.$admin->quote($name).' and pid <> pg_backend_pid()');
    $admin->exec('drop database if exists "'.$name.'"');
    echo "Dropped {$name}.\n";
};

switch ($command) {
    case 'provision':
        if ($exists($name)) {
            echo "{$name} exists.\n";
            break;
        }
        $create();
        break;
    case 'reset':
        $drop();
        $create();
        break;
    case 'drop':
        $drop();
        break;
    case 'list':
    case 'status':
        $rows = $admin->query("select datname, pg_size_pretty(pg_database_size(datname)) as size from pg_database where datname like 'scarlet\_web\_wt\_%' order by 1")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            echo ($row['datname'] === $name ? '* ' : '  ').$row['datname'].($command === 'status' ? '  '.$row['size'] : '')."\n";
        }
        if (! $exists($name)) {
            echo "This checkout would use {$name}, which does not exist yet.\n";
        }
        break;
    default:
        fwrite(STDERR, "Unknown command {$command}. Use status, provision, reset, drop or list.\n");
        exit(2);
}
