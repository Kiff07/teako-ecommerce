#!/bin/sh
set -e

# Exécution des migrations Doctrine au démarrage du conteneur
php bin/console doctrine:migrations:migrate --no-interaction

# Démarrage d'Apache
exec apache2-foreground