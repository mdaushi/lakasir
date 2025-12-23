FROM dunglas/frankenphp:php8.4.16

RUN install-php-extensions \
    pcntl \
    pdo_mysql \
    intl \
    zip \
    opcache
    # Add other PHP extensions here...

COPY . /app

ENTRYPOINT ["php", "artisan", "octane:frankenphp"]