# ==============================================================================
# AquaOptom Wholesale Beverage CRM — Production Dockerfile
# Multi-stage reproducible build for PHP-FPM 8.3 / PostgreSQL / Redis
# ==============================================================================

FROM php:8.3-fpm-alpine AS base

# Install system dependencies
RUN apk add --no-cache \
    curl \
    git \
    libpq-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    linux-headers \
    supervisor \
    nginx \
    postgresql-client

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_pgsql \
    pgsql \
    bcmath \
    intl \
    zip \
    opcache \
    pcntl

# Install Redis extension via PECL
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Copy custom PHP configuration
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ==============================================================================
# Vendor Builder Stage
# ==============================================================================
FROM base AS vendor

COPY backend/composer.json backend/composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --no-scripts \
    --optimize-autoloader

# ==============================================================================
# Frontend Asset Builder Stage
# ==============================================================================
FROM node:22-alpine AS frontend

WORKDIR /app
COPY backend/package.json backend/package-lock.json ./
RUN npm ci

COPY backend/resources ./resources
COPY backend/vite.config.js ./
RUN npm run build

# ==============================================================================
# Production Runtime Stage
# ==============================================================================
FROM base AS production

# Create dedicated application user
RUN addgroup -g 1000 aquaoptom && \
    adduser -u 1000 -G aquaoptom -s /bin/sh -D aquaoptom

# Copy application source code
COPY --chown=aquaoptom:aquaoptom backend /var/www/html

# Copy pre-built vendor and public frontend assets
COPY --chown=aquaoptom:aquaoptom --from=vendor /var/www/html/vendor /var/www/html/vendor
COPY --chown=aquaoptom:aquaoptom --from=frontend /app/public/build /var/www/html/public/build

# Ensure proper storage permissions
RUN mkdir -p /var/www/html/storage/app/private \
    /var/www/html/storage/app/public \
    /var/www/html/storage/framework/cache \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/logs \
    && chown -R aquaoptom:aquaoptom /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

USER aquaoptom

EXPOSE 9000

CMD ["php-fpm", "-F"]
