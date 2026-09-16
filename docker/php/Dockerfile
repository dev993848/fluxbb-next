FROM php:8.4-fpm-alpine

# Install system deps & PHP extensions from source (no PECL network)
RUN apk add --no-cache \
    git \
    unzip \
    icu-dev \
    oniguruma-dev \
    libzip-dev \
    postgresql-dev \
    linux-headers \
    file \
    autoconf \
    g++ \
    make \
    && docker-php-ext-install -j$(nproc) \
    intl \
    mbstring \
    pdo_pgsql \
    zip \
    opcache

# Build Redis from PECL source (included in PHP 8.4)
RUN curl -sL https://pecl.php.net/get/redis -o /tmp/redis.tgz \
    && pecl install /tmp/redis.tgz \
    && docker-php-ext-enable redis \
    && rm -rf /tmp/redis.tgz

# APCu — optional, skip if network down
RUN curl -sL https://pecl.php.net/get/apcu -o /tmp/apcu.tgz \
    && pecl install /tmp/apcu.tgz \
    && docker-php-ext-enable apcu \
    && rm -rf /tmp/apcu.tgz \
    || echo "APCu skipped (network unavailable)"

# Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# OPcache+JIT
RUN { \
    echo 'opcache.memory_consumption=256'; \
    echo 'opcache.interned_strings_buffer=16'; \
    echo 'opcache.max_accelerated_files=20000'; \
    echo 'opcache.revalidate_freq=60'; \
    echo 'opcache.enable_cli=1'; \
    echo 'opcache.jit=1255'; \
    echo 'opcache.jit_buffer_size=128M'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# PHP-FPM tuning
RUN { \
    echo 'pm.max_children=50'; \
    echo 'pm.start_servers=5'; \
    echo 'pm.min_spare_servers=5'; \
    echo 'pm.max_spare_servers=15'; \
    echo 'pm.max_requests=500'; \
    echo 'clear_env=no'; \
    echo 'catch_workers_output=yes'; \
    echo 'php_admin_value[error_log] = /var/log/fpm-php.log'; \
    echo 'php_admin_flag[log_errors] = on'; \
    } > /usr/local/etc/php-fpm.d/zz-www.conf

# Copy application
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts \
    --ignore-platform-req=php --ignore-platform-req=ext-redis \
    && rm -rf /root/.composer/cache

COPY --chown=www-data:www-data . .

RUN mkdir -p var/cache var/log var/twig && chmod -R 775 var/

USER www-data

HEALTHCHECK --interval=30s --timeout=3s --retries=3 \
    CMD php -r "exit(extension_loaded('pdo_pgsql') ? 0 : 1);"

CMD ["php-fpm"]