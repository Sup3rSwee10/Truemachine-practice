FROM php:8.4-fpm

# Устанавливаем системные зависимости и расширения
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    unzip \
    libpq-dev \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Устанавливаем Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Копируем код
COPY . .

# Устанавливаем зависимости. Если возникнут проблемы с платформой, можно добавить --ignore-platform-req=php,
# но пока попробуем без.
RUN composer install --no-interaction --optimize-autoloader

# Генерируем ключ
RUN php artisan key:generate || true

# Права на storage и bootstrap/cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]