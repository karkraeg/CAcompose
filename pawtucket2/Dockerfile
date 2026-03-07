FROM php:8.2-apache

# ── System dependencies ────────────────────────────────────────────────────────
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        zip \
        curl \
        wget \
        libpng-dev \
        libjpeg62-turbo-dev \
        libwebp-dev \
        libfreetype6-dev \
        libxml2-dev \
        libzip-dev \
        libicu-dev \
        libpspell-dev \
        aspell-en \
        libmagickwand-dev \
        libonig-dev \
        libzstd-dev \
        libmemcached-dev \
        ghostscript \
        imagemagick \
        ffmpeg \
        exiftool \
        poppler-utils \
    && rm -rf /var/lib/apt/lists/*

# ── PHP extensions ─────────────────────────────────────────────────────────────
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        gd \
        pdo_mysql \
        mysqli \
        mbstring \
        xml \
        simplexml \
        zip \
        intl \
        exif \
        opcache \
        bcmath \
        soap \
        sockets \
        pcntl \
        pspell

RUN pecl install redis   && docker-php-ext-enable redis
RUN pecl install imagick && docker-php-ext-enable imagick

# ── PHP runtime configuration ──────────────────────────────────────────────────
RUN { \
        echo 'memory_limit = 512M'; \
        echo 'upload_max_filesize = 256M'; \
        echo 'post_max_size = 256M'; \
        echo 'max_execution_time = 300'; \
        echo 'max_input_time = 300'; \
        echo 'opcache.enable = 1'; \
        echo 'opcache.memory_consumption = 128'; \
        echo 'opcache.interned_strings_buffer = 16'; \
        echo 'opcache.max_accelerated_files = 10000'; \
        echo 'opcache.revalidate_freq = 60'; \
    } > /usr/local/etc/php/conf.d/ca.ini

RUN sed -i 's|<policy domain="coder" rights="none" pattern="PDF" />|<policy domain="coder" rights="read|write" pattern="PDF" />|' \
    /etc/ImageMagick-6/policy.xml || true

# ── Apache configuration ───────────────────────────────────────────────────────
RUN a2enmod rewrite

COPY apache.conf /etc/apache2/sites-available/pawtucket2.conf
RUN a2dissite 000-default && a2ensite pawtucket2

# ── Composer ───────────────────────────────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ── Clone Pawtucket2 ───────────────────────────────────────────────────────────
ARG CA_VERSION=master
RUN git clone --depth=1 --branch="${CA_VERSION}" \
        https://github.com/collectiveaccess/pawtucket2.git \
        /var/www/pawtucket2

WORKDIR /var/www/pawtucket2

RUN COMPOSER_ALLOW_SUPERUSER=1 \
    composer config --global audit.block-insecure false \
    && composer install \
        --no-dev \
        --optimize-autoloader \
        --no-interaction \
        --prefer-dist

# ── Setup.php ──────────────────────────────────────────────────────────────────
COPY setup.php /var/www/pawtucket2/setup.php

# ── Permissions ────────────────────────────────────────────────────────────────
RUN mkdir -p /var/www/pawtucket2/media /var/www/pawtucket2/app/tmp \
    && chown -R www-data:www-data /var/www/pawtucket2 \
    && chmod -R 755 /var/www/pawtucket2/app/tmp \
    && chmod -R 755 /var/www/pawtucket2/media

# ── Entrypoint ─────────────────────────────────────────────────────────────────
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
