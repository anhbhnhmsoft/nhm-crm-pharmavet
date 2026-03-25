# 1. Nâng cấp lên PHP 8.3
FROM php:8.3-fpm

WORKDIR /var/www

# 2. Cài đặt System deps + Thêm libicu-dev cho ext-intl
RUN apt-get update && apt-get install -y \
    git unzip libpng-dev libjpeg62-turbo-dev libfreetype6-dev libpq-dev \
    libzip-dev libonig-dev curl libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_pgsql zip mbstring exif intl \
    && rm -rf /var/lib/apt/lists/*

# Cài Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Cài các dependency trước (tận dụng Docker Cache)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader

# Cài đặt Node.js và Yarn
COPY package.json yarn.lock vite.config.js ./
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && corepack enable \
    && yarn install --frozen-lockfile

# Copy toàn bộ SOURCE CODE
COPY . .

# Build Autoload và Asset (Vite/Mix)
RUN composer dump-autoload --optimize \
    && yarn build \
    && rm -rf node_modules \
    && apt-get purge -y nodejs \
    && apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/*

# Thiết lập quyền và Tối ưu cache Laravel
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    && php artisan config:clear \
    && php artisan route:clear \
    && php artisan view:clear

EXPOSE 9000
CMD ["php-fpm"]
