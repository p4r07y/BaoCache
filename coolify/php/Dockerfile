# syntax=docker/dockerfile:1

FROM alpine:3.23 AS redis-plugin

ARG REDIS_CACHE_VERSION=2.8.0
ARG REDIS_CACHE_SHA256=f077ac8b9c154cee936d3872b0734d42f7fd7f350e68fe8b2b20a69e854980cd

RUN set -eux; \
    apk add --no-cache curl unzip; \
    curl --fail --show-error --silent --location --retry 3 \
        --output /tmp/redis-cache.zip \
        "https://downloads.wordpress.org/plugin/redis-cache.${REDIS_CACHE_VERSION}.zip"; \
    echo "${REDIS_CACHE_SHA256}  /tmp/redis-cache.zip" | sha256sum -c -; \
    unzip -q /tmp/redis-cache.zip -d /plugins; \
    test -f /plugins/redis-cache/redis-cache.php; \
    test -f /plugins/redis-cache/includes/object-cache.php

FROM wordpress:php8.5-fpm-alpine

ARG PHPREDIS_VERSION=6.3.0

RUN apk add --no-cache ca-certificates curl openssl unzip \
    && apk add --no-cache --virtual .phpredis-build-deps $PHPIZE_DEPS \
    && pecl install "redis-${PHPREDIS_VERSION}" \
    && docker-php-ext-enable redis \
    && apk del .phpredis-build-deps \
    && rm -rf /tmp/pear \
    && curl --fail --show-error --silent --location --retry 3 \
        --output /usr/local/bin/wp \
        https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod 0555 /usr/local/bin/wp \
    && php /usr/local/bin/wp --info >/dev/null \
    && php -m | grep -qx redis

COPY php/conf.d/99-wordpress.ini /usr/local/etc/php/conf.d/99-wordpress.ini
COPY php/conf.d/10-opcache.ini /usr/local/etc/php/conf.d/10-opcache.ini
COPY php/php-fpm.d/zz-wordpress.conf /usr/local/etc/php-fpm.d/zz-wordpress.conf
COPY php/check-managed-db.php /usr/local/bin/check-managed-db.php
COPY --chmod=0555 php/docker-entrypoint-v4.sh /usr/local/bin/docker-entrypoint-v4
COPY wordpress/plugins/ /opt/bundled-plugins/
COPY wordpress/themes/ /opt/bundled-themes/
COPY --from=redis-plugin --chown=www-data:www-data /plugins/redis-cache /opt/bundled-plugins/redis-cache
COPY --from=redis-plugin --chown=www-data:www-data /plugins/redis-cache/includes/object-cache.php /opt/bundled-dropins/object-cache.php

RUN set -eux; \
    for archive in /opt/bundled-plugins/*.zip; do \
        [ -f "$archive" ] || continue; \
        unzip -q -o "$archive" -d /opt/bundled-plugins; \
        rm -f "$archive"; \
    done; \
    rm -rf /opt/bundled-plugins/__MACOSX; \
    chown -R www-data:www-data /opt/bundled-plugins /opt/bundled-themes /opt/bundled-dropins; \
    php -m | grep -qi opcache; \
    php -m | grep -qx redis; \
    test -f /opt/bundled-plugins/redis-cache/redis-cache.php; \
    find /opt/bundled-plugins/baocache /opt/bundled-plugins/power-schedule-manager -type f -name '*.php' -exec php -l {} \;; \
    test -f /opt/bundled-dropins/object-cache.php; \
    php-fpm -t

WORKDIR /var/www/html

ENTRYPOINT ["docker-entrypoint-v4"]
CMD ["php-fpm"]
