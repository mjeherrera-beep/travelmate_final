FROM php:8.2-apache

RUN docker-php-ext-install mysqli && \
    a2dismod mpm_event && \
    a2enmod mpm_prefork rewrite

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
