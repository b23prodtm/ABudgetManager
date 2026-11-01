# Web service: CakePHP 5 on Apache (multi-arch: amd64, armv7, aarch64 for Raspberry Pi)
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs

FROM php:8.3-apache
RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev \
    && docker-php-ext-install intl \
    && a2enmod rewrite \
    && sed -ri 's!/var/www/html!/var/www/html/webroot!g' /etc/apache2/sites-available/*.conf \
    && printf '<Directory /var/www/html/webroot>\n    AllowOverride All\n</Directory>\n' > /etc/apache2/conf-available/cake.conf \
    && a2enconf cake \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts \
    && mkdir -p tmp/cache/models tmp/cache/persistent tmp/cache/views tmp/sessions tmp/tests logs /data \
    && chown -R www-data:www-data tmp logs /data
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
ENV DB_PATH=/data/budget.sqlite
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
