FROM php:8.2-apache

# ── System dependencies ────────────────────────────────────────────────────────
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        zip \
        curl \
        wget \
        # GD image library
        libpng-dev \
        libjpeg62-turbo-dev \
        libwebp-dev \
        libfreetype6-dev \
        # XML
        libxml2-dev \
        # ZIP
        libzip-dev \
        # ICU / intl
        libicu-dev \
        # Spell check (pspell)
        libpspell-dev \
        aspell-en \
        # ImageMagick (for imagick PECL extension)
        libmagickwand-dev \
        # mbstring requires oniguruma
        libonig-dev \
        # Redis (for phpredis PECL extension)
        libzstd-dev \
        # Memcached (optional)
        libmemcached-dev \
        # Media processing tools
        ghostscript \
        imagemagick \
        ffmpeg \
        exiftool \
        # PDF handling
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

# PECL: Redis (with zstd for compression)
RUN pecl install redis && docker-php-ext-enable redis

# PECL: ImageMagick
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

# Allow ImageMagick to process PDFs (needed for CA)
RUN sed -i 's|<policy domain="coder" rights="none" pattern="PDF" />|<policy domain="coder" rights="read|write" pattern="PDF" />|' \
    /etc/ImageMagick-6/policy.xml || true

# ── Apache configuration ───────────────────────────────────────────────────────
RUN a2enmod rewrite

COPY apache.conf /etc/apache2/sites-available/providence.conf
RUN a2dissite 000-default && a2ensite providence

# ── Composer ───────────────────────────────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ── Clone Providence ───────────────────────────────────────────────────────────
ARG CA_VERSION=master
RUN git clone --depth=1 --branch="${CA_VERSION}" \
        https://github.com/collectiveaccess/providence.git \
        /var/www/providence

WORKDIR /var/www/providence

# Install Composer dependencies
# Disable Composer 2.7+ security-advisory blocking during resolution; CA's
# dependency tree (firebase/php-jwt via google/auth) hits known advisories
# that are acceptable in this deployment context.
RUN COMPOSER_ALLOW_SUPERUSER=1 \
    composer config --global audit.block-insecure false \
    && composer install \
        --no-dev \
        --optimize-autoloader \
        --no-interaction \
        --prefer-dist

# ── Setup.php (reads environment variables at runtime) ─────────────────────────
COPY setup.php /var/www/providence/setup.php

# ── Permissions ────────────────────────────────────────────────────────────────
RUN mkdir -p /var/www/providence/media /var/www/providence/app/tmp \
    && chown -R www-data:www-data /var/www/providence \
    && chmod -R 755 /var/www/providence/app/tmp \
    && chmod -R 755 /var/www/providence/media

# ── Entrypoint ─────────────────────────────────────────────────────────────────
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
