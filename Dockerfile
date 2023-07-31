FROM php:8.2-cli

COPY --from=composer:2 /usr/bin/composer /usr/bin/
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/bin/

ENV TZ=Europe/Amsterdam

RUN apt update \
    && apt install -y \
        libmariadb-dev \
        unzip \
        wget

RUN set -x \
    && install-php-extensions \
        bcmath \
        pdo \
        pdo_mysql \
        zip

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone
RUN echo "date.timezone = $TZ" >> /usr/local/etc/php/php.ini

WORKDIR /var/www/html

COPY . .

RUN composer install

ENTRYPOINT ["php", "-S", "0.0.0.0:8000", "-t", "public"]
