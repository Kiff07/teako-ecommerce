FROM php:8.3-apache

# Installation des dépendances système et extensions PHP nécessaires (intl, pdo_pgsql, pdo_mysql, zip)
RUN apt-get update && apt-get install -y \
    libicu-dev libzip-dev zip unzip git libpq-dev \
    && docker-php-ext-install intl pdo pdo_pgsql pdo_mysql zip

# Configuration du DocumentRoot vers /public pour Symfony
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/conf-available/*.conf

# Activation de la réécriture d'URL Apache (.htaccess Symfony)
RUN a2enmod rewrite

# Copie de Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

ENV COMPOSER_ALLOW_SUPERUSER=1

# Copie des fichiers et installation des dépendances sans scripts de build
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Permissions sur le dossier var
RUN chown -R www-data:www-data /var/www/html/var

EXPOSE 80