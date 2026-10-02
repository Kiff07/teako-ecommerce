#!/usr/bin/env bash
# Stop le script dès qu'une erreur survient
set -o errexit

# 1. Installation des dépendances sans les packages de dev (PHP)
composer install --no-dev --optimize-autoloader

# 2. Nettoyage et préchauffage du cache Symfony en mode production
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

# 3. (Optionnel) Décommentez si vous avez une base de données avec des migrations
# php bin/console doctrine:migrations:migrate --no-interaction