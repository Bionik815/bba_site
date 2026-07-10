FROM wordpress:php8.2-apache

RUN docker-php-ext-install pdo_mysql

# Raise PHP upload/memory limits for large catalog CSV imports.
COPY uploads.ini /usr/local/etc/php/conf.d/zz-uploads.ini
