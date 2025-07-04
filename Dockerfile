FROM php:8.4

ARG DRIVER_VERSION=2.1.0

RUN apt-get update && apt-get install -y libssl-dev && apt-get clean && rm -rf /var/lib/apt/lists/*
# Install mongodb extension
RUN pecl install mongodb-${DRIVER_VERSION}   && docker-php-ext-enable mongodb

# Install composer
COPY --from=composer/composer:lts /usr/bin/composer /usr/bin/composer

ENTRYPOINT ["bash", "-c"]
