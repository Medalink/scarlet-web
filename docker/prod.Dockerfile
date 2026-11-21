# The production image `app` (php-fpm; Horizon, the scheduler and the artisan commands run the same image; nginx is the
# upstream image, it takes the built assets from this one). `bin/deploy` builds it from a full clone and passes the clone's git history as the named context
# `gitdir`. Every FROM is a release that was public for at least 7 days when it was chosen, pinned by digest in
# docker/pins.tsv.
#
#   docker build -f docker/prod.Dockerfile --target app --build-context gitdir=<clone>/.git -t scarlet-web-app:<sha> <clone>
FROM composer:2.11.1@sha256:8de2184c6de224f4689e8f80ed5f5edaf0030b6b8b7839a22a2bcb6722ac87d0 AS composer
FROM rust:1.100.0-bookworm@sha256:ade39a710ef85f3506cffcfec3e8a24ba090badef1dff2fb798f71d878833fea AS part

# scarlet-part for Linux, the decoder the queue job runs: config('scarlet.part_bin').
COPY part/ /src/part/
COPY docs/groups.tsv docs/player-fields.tsv docs/name-refs.tsv /src/docs/
RUN cargo build --release --locked --manifest-path /src/part/Cargo.toml --target-dir /src/target

# Vite and Tailwind. The build reads the views and the PHP that hold class names.
FROM node:24.22.2-bookworm-slim@sha256:01d566e8d94d8c619591d78652babfccd3c6992a3dffa9ed5ab39e11cf6c70e9 AS assets
RUN npm install -g pnpm@12.21.0
WORKDIR /app
COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./
RUN pnpm install --frozen-lockfile
COPY vite.config.js ./
COPY resources/ resources/
COPY app/ app/
COPY public/ public/
RUN pnpm run build

FROM php:8.5.12-fpm-bookworm@sha256:eec275cd99580e0a6e103dd343a7c160c86917003f6b9bba2d4c75d82419c683 AS base

ENV DEBIAN_FRONTEND=noninteractive COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_NO_INTERACTION=1

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip libpq-dev libicu-dev libzip-dev \
    && docker-php-ext-install -j4 pdo_pgsql pcntl zip \
    && docker-php-ext-install intl \
    && pecl install redis-6.3.0 \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY --from=part /src/target/release/scarlet-part /usr/local/bin/scarlet-part
COPY docker/prod/php.ini /usr/local/etc/php/conf.d/zz-scarlet.ini
COPY docker/prod/fpm.conf /usr/local/etc/php-fpm.d/zz-scarlet.conf
COPY docker/prod/entrypoint.sh /usr/local/bin/scarlet-entrypoint

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# `app:version` and `app:release-notes:backfill` read the whole git history. This stage installs git (a cached layer) and
# mounts the named context `gitdir` for the one step that runs them, and only their two output files go on to `app`:
# no git and no .git land in the image.
FROM base AS history
RUN apt-get update \
    && apt-get install -y --no-install-recommends git \
    && git config --global --add safe.directory '*' \
    && rm -rf /var/lib/apt/lists/*
COPY . /app
RUN --mount=type=bind,from=gitdir,target=/app/.git \
    export APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= APP_ENV=production \
    && composer dump-autoload --no-dev --classmap-authoritative \
    && php artisan app:version --strict --no-interaction \
    && php artisan app:release-notes:backfill --all --output=storage/app/release-notes.json --no-interaction

FROM base AS app
COPY . /app
COPY --from=assets /app/public/build /app/public/build
COPY --from=history /app/storage/app/version.json /app/storage/app/release-notes.json /app/storage/app/
# The dummy key is for this step only; the running containers get the real one from their environment.
RUN export APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= APP_ENV=production \
    && composer dump-autoload --no-dev --classmap-authoritative \
    && php artisan view:cache --no-interaction \
    && rm -rf /root/.composer \
    && chmod +x /usr/local/bin/scarlet-entrypoint \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["scarlet-entrypoint"]
CMD ["php-fpm"]
EXPOSE 9000
