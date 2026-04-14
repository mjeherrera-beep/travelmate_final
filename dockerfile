# Use the official PHP Apache image
FROM php:8.2-apache

# Install mysqli extension (required for MySQL)
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Enable Apache mod_rewrite (optional, for clean URLs)
RUN a2enmod rewrite

# Copy your PHP application into the container
COPY . /var/www/html/

# Set proper permissions (optional)
RUN chown -R www-data:www-data /var/www/html