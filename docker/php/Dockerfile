FROM php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    $PHPIZE_DEPS \
    git \
    curl \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    oniguruma-dev \
    icu-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libxml2-dev \
    supervisor \
    linux-headers

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pgsql \
        zip \
        mbstring \
        intl \
        gd \
        pcntl \
        sockets \
        bcmath \
        opcache

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Clean up build dependencies to keep image small
RUN apk del $PHPIZE_DEPS

# Install Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

EXPOSE 9000

CMD ["php-fpm"]
