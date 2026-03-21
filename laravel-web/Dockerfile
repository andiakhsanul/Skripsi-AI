FROM php:8.4-apache

WORKDIR /var/www/html

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    && docker-php-ext-install pdo pdo_pgsql zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

COPY . /var/www/html

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN if [ -f composer.json ]; then composer install --no-interaction --prefer-dist --optimize-autoloader; fi

RUN chown -R www-data:www-data /var/www/html \
    && mkdir -p /var/www/html/storage/app/public/ditmawa-documents \
    && mkdir -p /var/www/html/storage/app/public/application-documents \
    && chown -R www-data:www-data /var/www/html/storage

# PHP upload settings untuk PDF Ditmawa (maks 15MB)
RUN echo "upload_max_filesize=15M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size=16M"        >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time=120"   >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit=256M"        >> /usr/local/etc/php/conf.d/uploads.ini

EXPOSE 80
