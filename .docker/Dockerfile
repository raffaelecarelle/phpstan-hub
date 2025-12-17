FROM php:8.2-cli-bullseye

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libxml2-dev \
    libzip-dev \
    libnotify-bin \
    dbus-x11 \
    dbus \
    inotify-tools \
    libonig-dev \
    && rm -rf /var/lib/apt/lists/*

RUN pecl install pcov  \
    && docker-php-ext-install xml mbstring zip

WORKDIR /app

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY --from=node:latest /usr/local/bin /usr/local/bin
COPY --from=node:latest /usr/local/lib/node_modules /usr/local/lib/node_modules

COPY . /app

RUN composer install --no-dev --optimize-autoloader \
    && npm install \
    && chmod +x bin/phpstan-hub

EXPOSE 8080

CMD ["bin/phpstan-hub"]