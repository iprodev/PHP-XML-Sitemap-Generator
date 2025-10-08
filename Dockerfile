FROM php:8.1-cli

WORKDIR /app
COPY . /app

RUN apt-get update && apt-get install -y unzip git libzip-dev zlib1g-dev \
    && docker-php-ext-install zip

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install --no-dev --optimize-autoloader

VOLUME /app/output
ENTRYPOINT ["php", "bin/sitemap"]
