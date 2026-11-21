# Runs Scarlet's local development containers on wslc. Start it as `bin/dev <command>`.
#
#   image                 build scarlet-web-dev:latest (docker/dev.Dockerfile)
#   infra up|down|status  the shared PostgreSQL 18 + TimescaleDB and Redis containers
#   up                    infra, then this worktree's tools container (composer, artisan, pnpm)
#   bootstrap             inside the tools container: .env, composer, private database, pnpm, build, migrate
#   start [role...]       web, horizon, schedule (default: all three)
#   stop [role...]        stop those containers (default: all three)
#   down                  stop this worktree's containers
#   remove                remove this worktree's containers; its volumes stay
#   status                containers, ports and database of this worktree
#
# All state is in wslc volumes (inside wslc's storage.vhdx, which lives on E:) and in the
# checkout. The names follow the checkout folder, so each worktree gets its own containers,
# its own vendor/ and node_modules/ volumes and its own database.

param(
    [Parameter(Position = 0)] [string] $Command = 'status',
    [Parameter(Position = 1, ValueFromRemainingArguments = $true)] [string[]] $Rest = @(),
    [int] $Port = 0
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$wslc = 'C:\Program Files\WSL\wslc.exe'
if (-not (Test-Path $wslc)) { throw 'wslc is not installed. Run `wsl --update` in an elevated PowerShell, then `wslc info`.' }

$slug = ((Split-Path -Leaf $root).ToLower() -replace '^scarlet-(api|web)(-|$)', '' -replace '[^a-z0-9]+', '-').Trim('-')
if ($slug -eq '') { $slug = 'main' }
$tools = "scarlet-web-wt-$slug"
$database = 'scarlet_web_wt_' + ($slug -replace '-', '_')
$network = 'scarlet-web'
$postgresPort = 5440
$redisPort = 6390
$roles = 'web', 'horizon', 'schedule'
$pins = @{}
Get-Content (Join-Path $root 'docker\pins.tsv') | Select-Object -Skip 1 | ForEach-Object { $c = $_ -split "`t"; $pins[$c[0]] = $c[1] }

function Wslc {
    & $wslc @args
    if ($LASTEXITCODE -ne 0) { throw "wslc $($args -join ' ') failed with exit code $LASTEXITCODE" }
}

function Exists([string] $name) {
    & $wslc container inspect $name *> $null
    return $LASTEXITCODE -eq 0
}

function Containers {
    return @(& $wslc container list -a --format json | Where-Object { $_ } | ForEach-Object { $_ | ConvertFrom-Json } | Where-Object { $_.Names -like 'scarlet-web-*' })
}

function Running([string] $name) {
    return $null -ne (Containers | Where-Object { $_.Names -eq $name -and $_.State -eq 'running' })
}

function ShowContainers {
    Containers | Sort-Object Names | Format-Table @{ n = 'Name'; e = { $_.Names } }, State, Status, Ports -AutoSize | Out-String -Width 200 | Write-Host
}

function EnsureStarted([string] $name) {
    if (-not (Running $name)) { Wslc container start $name | Out-Null }
}

function EnsureNetwork {
    & $wslc network inspect $network *> $null
    if ($LASTEXITCODE -ne 0) { Wslc network create $network | Out-Null }
}

function WaitFor([string] $label, [scriptblock] $ready, [int] $seconds = 90) {
    $deadline = (Get-Date).AddSeconds($seconds)
    while ((Get-Date) -lt $deadline) {
        if (& $ready) { return }
        Start-Sleep -Seconds 1
    }
    throw "$label did not become ready within $seconds seconds. See 'wslc logs'."
}

function InfraUp {
    EnsureNetwork
    Wslc pull $pins['postgres'] | Out-Null
    Wslc pull $pins['redis'] | Out-Null
    if (-not (Exists 'scarlet-web-pg')) {
        Wslc run --detach --name scarlet-web-pg --network $network --network-alias scarlet-web-pg `
            --publish "127.0.0.1:${postgresPort}:5432" `
            --mount 'type=volume,source=scarlet-web-pg-data,target=/home/postgres/pgdata' `
            --shm-size 256M `
            -e POSTGRES_HOST_AUTH_METHOD=trust -e TIMESCALEDB_TELEMETRY=off `
            $pins['postgres'] | Out-Null
    }
    if (-not (Exists 'scarlet-web-redis')) {
        Wslc run --detach --name scarlet-web-redis --network $network --network-alias scarlet-web-redis `
            --publish "127.0.0.1:${redisPort}:6379" `
            --mount 'type=volume,source=scarlet-web-redis-data,target=/data' `
            $pins['redis'] redis-server --save 60 1 --appendonly no | Out-Null
    }
    EnsureStarted 'scarlet-web-pg'
    EnsureStarted 'scarlet-web-redis'
    WaitFor 'PostgreSQL' { (& $wslc exec scarlet-web-pg pg_isready -U postgres 2>$null) -match 'accepting connections' }
    WaitFor 'Redis' { (& $wslc exec scarlet-web-redis redis-cli ping 2>$null) -match 'PONG' }
    Write-Host "PostgreSQL scarlet-web-pg (127.0.0.1:$postgresPort) and Redis scarlet-web-redis (127.0.0.1:$redisPort) are up."
}

function ToolsUp {
    InfraUp
    $image = 'scarlet-web-dev:latest'
    & $wslc image inspect $image *> $null
    if ($LASTEXITCODE -ne 0) { throw 'The image scarlet-web-dev:latest does not exist. Run `bin/dev image` first.' }
    if (-not (Exists $tools)) {
        Wslc run --detach --name $tools --network $network @(Mounts) $image | Out-Null
    }
    EnsureStarted $tools
    Write-Host "Tools container $tools is up (checkout at /app)."
}

function Mounts {
    $source = $root
    return @(
        '--mount', "type=bind,source=$source,target=/app",
        '--mount', "type=volume,source=$tools-vendor,target=/app/vendor",
        '--mount', "type=volume,source=$tools-node_modules,target=/app/node_modules",
        '--mount', 'type=volume,source=scarlet-web-cache,target=/cache'
    )
}

function HostPort {
    if ($Port -gt 0) { return $Port }
    $existing = & $wslc container inspect "$tools-web" 2>$null | ConvertFrom-Json
    if ($null -ne $existing -and $existing[0].Ports.'8000/tcp') { return [int] $existing[0].Ports.'8000/tcp'[0].HostPort }
    $hash = 0
    foreach ($char in $slug.ToCharArray()) { $hash = ($hash * 31 + [int] $char) % 100000 }
    $candidate = 8470 + ($hash % 100)
    $busy = @(Get-NetTCPConnection -State Listen -ErrorAction SilentlyContinue | ForEach-Object { $_.LocalPort })
    while ($busy -contains $candidate) { $candidate++ }
    return $candidate
}

function RoleCommand([string] $role) {
    switch ($role) {
        'web' { return @('php', 'artisan', 'serve', '--host=0.0.0.0', '--port=8000') }
        'horizon' { return @('php', 'artisan', 'horizon') }
        'schedule' { return @('php', 'artisan', 'schedule:work') }
    }
    throw "Unknown role '$role'. Roles: $($roles -join ', ')."
}

function Start-Roles([string[]] $names) {
    ToolsUp
    foreach ($role in $names) {
        $name = "$tools-$role"
        if (-not (Exists $name)) {
            $arguments = @('run', '--detach', '--name', $name, '--network', $network) + @(Mounts)
            if ($role -eq 'web') { $arguments += @('--publish', "127.0.0.1:$(HostPort):8000") }
            Wslc @arguments 'scarlet-web-dev:latest' @(RoleCommand $role) | Out-Null
        }
        EnsureStarted $name
        Write-Host "$name is running."
    }
    if ($names -contains 'web') { Write-Host "Web: http://127.0.0.1:$(HostPort)" }
}

function Stop-Roles([string[]] $names) {
    foreach ($role in $names) {
        $name = "$tools-$role"
        if (Running $name) { Wslc container stop $name | Out-Null }
    }
}

function Pick([string[]] $names) {
    if ($names.Count -eq 0) { return $roles }
    foreach ($name in $names) { RoleCommand $name | Out-Null }
    return $names
}

switch ($Command) {
    'image' {
        Wslc build --tag scarlet-web-dev:latest --file (Join-Path $root 'docker\dev.Dockerfile') (Join-Path $root 'docker')
    }
    'infra' {
        switch ($Rest[0]) {
            'up' { InfraUp }
            'down' { foreach ($name in 'scarlet-web-pg', 'scarlet-web-redis') { if (Running $name) { Wslc container stop $name | Out-Null } }; Write-Host 'PostgreSQL and Redis stopped; their volumes are kept.' }
            default { ShowContainers }
        }
    }
    'up' { ToolsUp }
    'bootstrap' {
        ToolsUp
        Wslc exec -e "SCARLET_HOST_PORT=$(HostPort)" -w /app $tools php scripts/dev/worktree-bootstrap.php
    }
    'start' { Start-Roles (Pick $Rest) }
    'stop' { Stop-Roles (Pick $Rest) }
    'down' {
        Stop-Roles $roles
        if (Running $tools) { Wslc container stop $tools | Out-Null }
        Write-Host "Stopped the containers of $tools. The shared PostgreSQL and Redis keep running; stop them with 'bin/dev infra down'."
    }
    'remove' {
        foreach ($name in ($roles | ForEach-Object { "$tools-$_" }) + $tools) {
            if (Exists $name) { Wslc container remove --force $name | Out-Null; Write-Host "Removed $name." }
        }
    }
    default {
        Write-Host "Checkout $root"
        Write-Host "Database $database (user postgres, no password) on scarlet-web-pg"
        ShowContainers
    }
}
