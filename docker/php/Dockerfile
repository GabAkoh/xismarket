FROM php:8.4-fpm-alpine

# Runtime tools
RUN apk add --no-cache bash git curl

# Reliable PHP extension installer (handles build deps + cleanup automatically)
COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
        pdo_mysql \
        mysqli \
        bcmath \
        gd \
        zip \
        intl \
        mbstring \
        exif \
        pcntl \
        redis

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Non-root user matching typical host uid
RUN addgroup -g 1000 -S www && adduser -u 1000 -S www -G www

WORKDIR /var/www

# PHP config tweaks
RUN { \
        echo "upload_max_filesize=64M"; \
        echo "post_max_size=64M"; \
        echo "memory_limit=512M"; \
        echo "max_execution_time=120"; \
    } > /usr/local/etc/php/conf.d/zz-app.ini

# Entrypoint fixes writable-dir ownership (bind mounts arrive owned by root)
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
