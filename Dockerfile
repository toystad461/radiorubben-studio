FROM php:8.4-apache-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libcurl4-openssl-dev libonig-dev libzip-dev unzip apache2-utils \
    && docker-php-ext-install curl mbstring zip \
    && a2enmod headers \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /opt/studio
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --no-plugins --optimize-autoloader
COPY app/ ./app/
COPY public/ ./public/
COPY config/example.php /opt/studio-defaults/example.php
COPY deploy/render/ /opt/studio-deploy/
RUN ln -s /var/studio/config /opt/studio/config \
    && cp /opt/studio-deploy/apache.conf /etc/apache2/sites-available/000-default.conf \
    && printf 'Listen 10000\n' > /etc/apache2/ports.conf \
    && cp /opt/studio-deploy/php.ini /usr/local/etc/php/conf.d/studio.ini \
    && cp /opt/studio-deploy/healthz.php /opt/studio/public/healthz.php \
    && chmod 755 /opt/studio-deploy/entrypoint.sh
EXPOSE 10000
ENTRYPOINT ["/opt/studio-deploy/entrypoint.sh"]
CMD ["apache2-foreground"]
