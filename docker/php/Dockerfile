FROM php:8.5-fpm-alpine3.23

RUN apk add --no-cache icu-dev curl unzip git php-pear autoconf gcc make musl-dev \
    && docker-php-ext-install intl mysqli \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del autoconf gcc make musl-dev php-pear \
    && rm -rf /tmp/pecl /usr/local/src /var/cache/apk/* \
    && curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php \
    && php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm /tmp/composer-setup.php

# Add composer global bin to PATH
ENV COMPOSER_HOME=/var/www/html/.composer
ENV PATH="/var/www/html/vendor/bin:${PATH}"


WORKDIR /var/www/html

# Install dependencies
COPY composer.json composer.lock ./
RUN composer install --dev --no-interaction --prefer-dist --no-scripts \
    && composer dump-autoload --optimize
