FROM php:8.3-apache

# Prepare a writable directory for dashboard-controlled LAN state. When the
# named Docker volume is first created, it inherits this directory ownership.
RUN mkdir -p /var/www/runtime \
    && chown www-data:www-data /var/www/runtime

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        exif \
        gd \
        intl \
        mbstring \
        mysqli \
        opcache \
        pdo_mysql \
        zip \
    && a2enmod rewrite headers vhost_alias \
    && rm -rf /var/lib/apt/lists/*

COPY apache/hosting-vhosts.conf /etc/apache2/sites-available/000-default.conf
COPY php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html
