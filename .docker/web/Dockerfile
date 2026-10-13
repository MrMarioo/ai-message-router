FROM php:8.4-apache

# System packages (single layer)
RUN apt-get update && apt-get install -y --no-install-recommends \
        curl \
        zip \
        unzip \
        git \
        ca-certificates \
        libzip-dev \
        libsqlite3-dev \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions (single layer)
RUN docker-php-ext-install -j"$(nproc)" \
        pdo_sqlite \
        zip \
        opcache

# Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# PHP ini - a local CPU-bound model can take a while to answer, do not let PHP cut the request short
RUN sed -i -e "s/memory_limit = 128M/memory_limit = 256M/g" /usr/local/etc/php/php.ini-production \
    && sed -i -e "s/max_execution_time = 30/max_execution_time = 300/g" /usr/local/etc/php/php.ini-production \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Apache: docroot -> /var/www/public
RUN a2enmod rewrite headers \
    && sed -ri -e 's!/var/www/html!/var/www/public!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!/var/www/public!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && rm -rf /var/www/html \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

COPY .docker/web/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www

# Dependencies first so the layer is cached as long as composer.lock does not change;
# dev dependencies stay on purpose so `php artisan test` works inside the container
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY . .

RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache database

COPY .docker/web/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]
CMD ["apache2-foreground"]
