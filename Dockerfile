# syntax=docker/dockerfile:1.7

FROM composer:2 AS bedrock_vendor

WORKDIR /app

COPY composer.json composer.lock ./
COPY config ./config
COPY web ./web

RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader


FROM composer:2 AS plugin_vendor

WORKDIR /plugin

COPY web/app/plugins/b2b-core/ ./

RUN if [ -f composer.json ]; then \
      composer install \
        --no-dev \
        --prefer-dist \
        --no-interaction \
        --no-progress \
        --optimize-autoloader ; \
    fi


FROM node:20-bookworm AS theme_assets

WORKDIR /theme

COPY web/app/themes/my-theme/package*.json ./

RUN if [ -f package-lock.json ]; then \
      npm ci; \
    else \
      npm install; \
    fi

COPY web/app/themes/my-theme/ ./

RUN npm run build


FROM composer:2 AS theme_vendor

WORKDIR /theme

COPY web/app/themes/my-theme/ ./

RUN if [ -f composer.json ]; then \
      composer install \
        --no-dev \
        --prefer-dist \
        --no-interaction \
        --no-progress \
        --optimize-autoloader ; \
    fi


FROM php:8.2-apache-bookworm AS production

ENV APACHE_DOCUMENT_ROOT=/var/www/html/web

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    curl \
    less \
    default-mysql-client \
    libzip-dev \
    libicu-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        mysqli \
        pdo_mysql \
        zip \
        intl \
        gd \
        exif \
        bcmath \
        opcache \
    && a2enmod rewrite headers expires remoteip \
    && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && rm -rf /var/lib/apt/lists/*

RUN { \
        echo "memory_limit=512M"; \
        echo "upload_max_filesize=128M"; \
        echo "post_max_size=128M"; \
        echo "max_execution_time=300"; \
        echo "max_input_vars=5000"; \
        echo "opcache.enable=1"; \
        echo "opcache.memory_consumption=256"; \
        echo "opcache.interned_strings_buffer=16"; \
        echo "opcache.max_accelerated_files=20000"; \
        echo "opcache.revalidate_freq=0"; \
        echo "opcache.validate_timestamps=0"; \
    } > /usr/local/etc/php/conf.d/zz-sanoto.ini
    
RUN cat > /etc/apache2/conf-available/bedrock.conf <<'EOF'
<Directory /var/www/html/web>
    AllowOverride All
    Require all granted
</Directory>
EOF

RUN a2enconf bedrock

RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

RUN curl -fsSL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o /usr/local/bin/wp \
    && chmod +x /usr/local/bin/wp

COPY --from=bedrock_vendor /app /var/www/html
COPY --from=plugin_vendor /plugin /var/www/html/web/app/plugins/b2b-core
COPY --from=theme_vendor /theme /var/www/html/web/app/themes/my-theme
COPY --from=theme_assets /theme/public /var/www/html/web/app/themes/my-theme/public

RUN mkdir -p \
    /var/www/html/web/app/uploads \
    /var/www/html/web/app/cache \
    && chown -R www-data:www-data /var/www/html

HEALTHCHECK --interval=30s --timeout=10s --retries=3 CMD curl -f http://localhost || exit 1

EXPOSE 80

CMD ["apache2-foreground"]