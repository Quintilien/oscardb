FROM php:8.3-apache

# System dependencies
RUN apt-get update && apt-get install -y sqlite3 libyaml-dev zlib1g-dev \
    && pecl install yaml \
    && docker-php-ext-enable yaml \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

# Only public/ is served; config/, data/, src/ and vendor/ stay
# next to it, outside the web root.
ENV APACHE_DOCUMENT_ROOT=/var/www/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}/!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

COPY config/ /var/www/config/
COPY src/    /var/www/src/
COPY public/ /var/www/public/
COPY vendor/ /var/www/vendor/
