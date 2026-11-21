# The one image every local Scarlet process runs in: composer, artisan, Pint, PHPStan,
# pnpm and Vite, the web server, Horizon and the scheduler.
# Build it with `bin/dev image`. Every FROM is a release that was public for at least
# 7 days when it was chosen, pinned by digest; DEV.md lists the release dates.
FROM node:24.22.2-bookworm-slim@sha256:01d566e8d94d8c619591d78652babfccd3c6992a3dffa9ed5ab39e11cf6c70e9 AS node
FROM composer:2.11.1@sha256:8de2184c6de224f4689e8f80ed5f5edaf0030b6b8b7839a22a2bcb6722ac87d0 AS composer
FROM rust:1.100.0-bookworm@sha256:ade39a710ef85f3506cffcfec3e8a24ba090badef1dff2fb798f71d878833fea AS part

# scarlet-part for Linux, built from part/ (the build context is the checkout, trimmed by .dockerignore).
# The queue job runs it to decode parts: config('scarlet.part_bin').
COPY part/ /src/part/
COPY docs/groups.tsv docs/player-fields.tsv docs/name-refs.tsv /src/docs/
RUN cargo build --release --locked --manifest-path /src/part/Cargo.toml --target-dir /src/target

FROM php:8.5.12-cli-bookworm@sha256:050713ce008e2360ea9dc96e1f9a075690132388d917fc3552806f7a7be6f2a0

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git unzip less procps postgresql-client libpq-dev libicu-dev libzip-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pcntl intl zip \
    && pecl install redis-6.3.0 \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/* /tmp/pear \
    && git config --global --add safe.directory '*'

COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY --from=part /src/target/release/scarlet-part /usr/local/bin/scarlet-part

RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
    && npm install -g pnpm@12.21.0 \
    && printf 'memory_limit=2G\ndate.timezone=UTC\n' > /usr/local/etc/php/conf.d/zz-scarlet.ini

# Composer caches live on a volume shared by every worktree container (see scripts/wslc/dev.ps1).
ENV COMPOSER_HOME=/cache/composer-home \
    COMPOSER_CACHE_DIR=/cache/composer \
    SCARLET_PART_BIN=/usr/local/bin/scarlet-part \
    COMPOSER_ALLOW_SUPERUSER=1 \
    PHP_CLI_SERVER_WORKERS=4

WORKDIR /app
EXPOSE 8000
CMD ["sleep", "infinity"]
