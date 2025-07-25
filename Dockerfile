FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    librdkafka-dev \
    git \
    unzip \
    pkg-config \
    libssl-dev \
    build-essential \
    libcurl4-openssl-dev \
    curl

# Kafka və MySQL üçün extensions
RUN pecl install rdkafka && docker-php-ext-enable rdkafka
RUN docker-php-ext-install pdo_mysql

# Composer əlavə et
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Layihə fayllarını əlavə et
COPY . /app

# PHP ClickHouse lib əlavə et (vendor qovluğuna yüklənir)
RUN composer require smi2/phpclickhouse

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "/app"]
