# Stage 1: build the frontend assets
FROM node:22 AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js tsconfig.json ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# Stage 2: the PHP application
FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg62-turbo-dev libwebp-dev libzip-dev unzip \
        poppler-utils \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql gd zip exif \
    # PHP requires the prefork MPM; ensure no second MPM stays enabled or
    # Apache refuses to start ("More than one MPM loaded").
    && (a2dismod -f mpm_event mpm_worker || true) \
    && a2enmod mpm_prefork rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# Serve from Laravel's public directory
RUN sed -ri 's!/var/www/html!/var/www/html/public!g' \
    /etc/apache2/sites-available/000-default.conf /etc/apache2/apache2.conf

# Phone photographs exceed PHP's 2M default, and a 100-page scanner PDF
# (a 50-card session) can run well past 50M.
RUN { \
        echo 'upload_max_filesize=200M'; \
        echo 'post_max_size=210M'; \
        echo 'max_execution_time=120'; \
    } > /usr/local/etc/php/conf.d/uploads.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY . .
RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi

COPY --from=assets /app/public/build ./public/build

RUN chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]
