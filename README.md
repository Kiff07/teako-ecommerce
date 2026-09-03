# Teako — Boutique e‑commerce « Clair & artisanal »

Boutique en ligne complète de boissons gourmandes (menu **Teako**), réalisée avec **Symfony 7**.
Design clair et artisanal (crème, terracotta, épices), vitrine animée + back-office complet.

## Fonctionnalités

**Vitrine**
- Page d'accueil plein écran (parallaxe, Ken Burns, apparitions en cascade)
- Carte / catalogue par catégories, fiches produit avec galerie (zoom, recadrage visuel, vignettes, clavier, plein écran, swipe, blur-up, fondu 300 ms)
- Panier animé (AJAX, quantités, totaux, vignettes), favoris, fil d'Ariane
- Parcours de commande complet : checkout (retrait / livraison), confirmation, décrément de stock automatique
- Modes sombre/clair, toasts, squelettes de chargement

**Back-office (admin)**
- Tableau de bord avec graphiques SVG animés (ventes, statuts, top produits)
- Gestion produits : drag & drop d'upload, réordonnancement, recadrage, image principale, aperçu
- Gestion catégories et statuts de commande (En attente → … → Livrée / Annulée avec remise en stock)
- CSRF sur toutes les actions AJAX

**Technique**
- PHP 8.4, Symfony 7.4, Doctrine (SQLite par défaut), Security (login admin)
- Encore / Stimulus 3, Tailwind CSS 3
- Traitement d'images GD : redimensionnement, variantes mises en cache (`-thumb/-card/-big`), recadrage

## Démarrage rapide

```bash
# 1. Variables d'environnement (ne jamais committer les secrets réels)
cp .env.example .env
# éventuellement : copier .env.example vers .env.dev pour APP_SECRET en environnement dev

# 2. Dépendances
composer install
npm install
npx encore production        # assets JS/CSS compilés dans public/build

# 3. Base de données (SQLite par défaut : var/teako.db)
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:fixtures:load --no-interaction   # produits / photos / commandes de démo

# 4. Compte administrateur (single-user)
php bin/console app:create-admin "admin@example.com" "unMotDePasse" "Admin Teako"

# 5. Serveur de dev
php -S 0.0.0.0:8000 -t public
```

Puis ouvrir `http://localhost:8000` (boutique) et `http://localhost:8000/admin` (back-office).

> Les photos originales des produits sont servies depuis `public/media/{productId}/` ; les
> variantes (`-thumb`, `-card`, `-big`, `-blur`) sont générées automatiquement et mises en
> cache. Ces dossiers (`public/media/`, `public/uploads/`, `var/`, `public/build/`) sont
> régénérés en local et ne sont pas versionnés.

## Structure

```
config/     services, packages, routes, sécurité
src/Entity/ Product, ProductImage, Category, Order, OrderItem, Customer, Cart, CartItem, Admin
src/Controller/  Store*, Cart, Favorite, Order + Admin/*
src/Service/     ImageProcessor (GD), OrderService, CartSession…
src/DataFixtures/AppFixtures      produits/photos/commandes de démo
templates/       vitrine + back-office
assets/          JS (Stimulus controllers) + CSS (Tailwind)
```

## Licence

Projet de démonstration. Images et contenu « Teako » fournis à titre d'exemple.
