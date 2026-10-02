FROM php:8.3-apache

# Installation des paquets système et extensions PHP
RUN apt-get update && apt-get install -y \
    libicu-dev libzip-dev zip unzip git libpq-dev \
    && docker-php-ext-install intl pdo pdo_pgsql pdo_mysql zip

# Configuration du DocumentRoot vers /public pour Symfony
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/conf-available/*.conf

# Activation du module rewrite d'Apache
RUN a2enmod rewrite

# Copie de Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

ENV COMPOSER_ALLOW_SUPERUSER=1

# Copie des fichiers du projet
COPY . .

# Installation des dépendances
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Création du dossier var et permissions
RUN mkdir -p /var/www/html/var && chown -R www-data:www-data /var/www/html/var

EXPOSE 80

CMD ["apache2-foreground"]