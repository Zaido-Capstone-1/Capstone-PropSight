FROM php:8.2-apache

# ── System packages + PHP extensions ────────────────────────────────────────
RUN apt-get update && apt-get install -y \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libcurl4-openssl-dev \
        unzip \
        git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) mysqli gd curl zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# ── Apache config ────────────────────────────────────────────────────────────
# Enable mod_rewrite / mod_headers (used by .htaccess) and allow .htaccess
# overrides for the whole docroot.
# The apt-get install above can re-enable mpm_event alongside the mpm_prefork
# that mod_php requires — Apache refuses to start with two MPMs loaded, so
# explicitly force prefork-only here.
RUN a2dismod mpm_event mpm_worker 2>/dev/null; \
    a2enmod mpm_prefork rewrite headers
RUN sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

# Railway provides the port to listen on via $PORT at runtime, so Apache's
# fixed "Listen 80" / vhost port has to be rewritten at container start —
# see docker-entrypoint.sh.
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

WORKDIR /var/www/html

# ── Composer ─────────────────────────────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --optimize-autoloader

# ── App source ───────────────────────────────────────────────────────────────
COPY . .

# Writable dirs for uploads / logs / storage (gitignored, so they won't exist
# in a fresh checkout — create them so the app can write to them at runtime).
RUN mkdir -p uploads storage logs \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 uploads storage logs

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]