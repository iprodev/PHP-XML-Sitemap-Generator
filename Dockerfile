FROM php:8.2-cli-alpine

LABEL maintainer="iprodev"
LABEL description="PHP XML Sitemap Generator Pro"

# Install dependencies
RUN apk add --no-cache \
    curl \
    git \
    unzip \
    libzip-dev \
    libxml2-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    zlib-dev \
    icu-dev \
    chromium \
    chromium-chromedriver \
    sqlite \
    mysql-client \
    postgresql-client

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        pdo_pgsql \
        pdo_sqlite \
        zip \
        xml \
        mbstring \
        gd \
        intl \
        pcntl \
        opcache

# Install PECL extensions
RUN pecl install redis \
    && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy application files
COPY . /app

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Create necessary directories
RUN mkdir -p /app/output /app/cache /app/logs \
    && chmod -R 777 /app/output /app/cache /app/logs

# Configure PHP
RUN echo "memory_limit = 512M" > /usr/local/etc/php/conf.d/memory.ini \
    && echo "max_execution_time = 0" > /usr/local/etc/php/conf.d/execution.ini \
    && echo "opcache.enable=1" > /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/opcache.ini

# Set environment variables
ENV CHROME_PATH=/usr/bin/chromium-browser
ENV PATH="/app/bin:${PATH}"

# Make bin files executable
RUN chmod +x /app/bin/sitemap /app/bin/scheduler

# Health check
HEALTHCHECK --interval=30s --timeout=10s --retries=3 \
    CMD php -v || exit 1

# Default command
ENTRYPOINT ["php", "/app/bin/sitemap"]
CMD ["--help"]
