# ─────────────────────────────────────────────────────────────
# Stage 1: Node.js — Build Vite/Tailwind assets
# ─────────────────────────────────────────────────────────────
FROM node:20-alpine AS node-builder

WORKDIR /app

# Install dependencies dulu (cache layer)
COPY package.json ./
RUN npm install

# Copy semua source lalu build
COPY . .
RUN npm run build

# ─────────────────────────────────────────────────────────────
# Stage 2: FrankenPHP — PHP runtime + HTTP server (tanpa Nginx)
# ─────────────────────────────────────────────────────────────
FROM dunglas/frankenphp

# FrankenPHP listen di port 80 (plain HTTP, tanpa TLS — TLS oleh load balancer/CDN)
ENV SERVER_NAME=:80

# Install PHP extensions
# FrankenPHP sudah include OPcache (built-in ZTS) — tidak perlu install lagi
# Gunakan install-php-extensions yang tersedia di image FrankenPHP
RUN install-php-extensions \
    pdo_pgsql \
    zip

# PHP runtime tuning: upload limit, memory, execution time
RUN { \
    echo "upload_max_filesize=15M"; \
    echo "post_max_size=16M"; \
    echo "max_execution_time=120"; \
    echo "memory_limit=256M"; \
    } > /usr/local/etc/php/conf.d/laravel.ini

# OPcache tuning — validate timestamps untuk development (detect file changes)
RUN { \
    echo "opcache.validate_timestamps=1"; \
    echo "opcache.revalidate_freq=0"; \
    echo "opcache.max_accelerated_files=10000"; \
    echo "opcache.memory_consumption=192"; \
    echo "opcache.interned_strings_buffer=16"; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# Composer dari official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# FrankenPHP menggunakan /app sebagai working directory
WORKDIR /app

# Copy kode Laravel
COPY . /app

# Ambil hasil Vite build dari node-builder stage
COPY --from=node-builder /app/public/build /app/public/build

# Install PHP dependencies (tanpa dev, optimized autoloader)
RUN composer install \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-dev

# Buat direktori storage yang dibutuhkan & set permission
RUN chown -R www-data:www-data /app \
    && mkdir -p /app/storage/app/public/ditmawa-documents \
    && mkdir -p /app/storage/app/public/application-documents \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache

# Copy Caddyfile custom (menentukan root → /app/public)
COPY Caddyfile /etc/caddy/Caddyfile

EXPOSE 80
