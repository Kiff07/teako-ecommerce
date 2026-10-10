# Audit & correctifs — Teako E-commerce (Symfony 7 + Stimulus + Tailwind)

Audit complet du projet : bugs fonctionnels, sécurité, rendu mobile et menus
(boutique + admin). Tous les correctifs listés ci-dessous sont **déjà appliqués**
dans cette copie. Aucune modification de base de données / migration requise.

---

## 1. Bugs critiques corrigés

### 1.1 Panier : bouton « − » qui supprime la ligne, « + » bloqué à 2
**Fichier :** `assets/js/controllers/cart_controller.js`

- **Cause :** la quantité courante était lue **dans le DOM** (`_lineQtyFrom()` →
  premier `<span>` trouvé dans `[data-line-widget]`). Dès que cette lecture
  échouait (ligne rendue par le serveur avec un markup différent, build Encore
  périmé, re-rendu interrompu…), la fonction retombait sur `return 1` :
  - « − » → `1 − 1 = 0` → suppression immédiate de la ligne (même affichée à 3 ou 4) ;
  - « + » → `1 + 1 = 2` → quantité écrasée/bloquée à 2 ;
  - le bouton « Ajouter » des cartes produit ne passait pas par cette lecture →
    seul lui fonctionnait (d'où le symptôme observé).
- **Correctifs :**
  1. La quantité est désormais lue dans `this.state.lines`, l'état synchronisé
     avec le serveur (`/panier/etat` + réponse de chaque mutation). La lecture
     DOM n'est qu'un filet de secours multi-sélecteurs.
  2. Normalisation des réponses serveur : les mutations renvoient
     `{ok, message, data: état}` et `/panier/etat` l'état au premier niveau —
     les deux formes sont acceptées ; forme inconnue → resynchronisation
     automatique via `/panier/etat` (plus d'état `undefined` qui casse `_applyState()`).
  3. Normalisation des champs de ligne (`lineId|id|productId`, `qty|quantity`,
     `lineTotalCents|totalCents|priceCents×qty`).
  4. Garde anti double-clic par ligne (requêtes en cours).
  5. Respect du stock sur « + » avec toast « Stock maximum atteint ».
  6. Si le tiroir ne peut pas être re-rendu (targets absents), la ligne affichée
     est retouchée en place (quantité, total, suppression).

### 1.2 Menu mobile boutique cassé (panneau écrasé / invisible)
**Fichiers :** `templates/base.html.twig`, `assets/styles/app.css`

- **Cause :** `<header>` portait `backdrop-blur-xl`. Or un `backdrop-filter`
  crée un **containing block** pour les descendants en `position: fixed` : le
  panneau mobile (`fixed top-0 left-0 bottom-0`) et son backdrop étaient donc
  positionnés **relativement au header** (64 px de haut) au lieu du viewport.
- **Correctif :** le flou est déplacé sur une couche interne
  `<div class="absolute inset-0 -z-10 backdrop-blur-xl bg-surface/85">` ; le
  header reste sticky et visuellement identique, le panneau redevient fullscreen.
- Même piège latent supprimé dans le CSS : `[data-reveal]` gardait
  `will-change: transform` en permanence (autre créateur de containing block).

### 1.3 Admin : aucun menu sur mobile
**Fichier :** `templates/admin/base.html.twig`

- La sidebar est `hidden lg:flex` et le header mobile ne proposait que 2 liens :
  impossible d'accéder à Catégories / Commandes / Paramètres / Profil /
  Déconnexion depuis un téléphone.
- **Correctif :** burger + panneau off-canvas (placé **hors** du header bluré)
  reprenant toute la navigation, les liens vitrine, le profil et la déconnexion.
  Fermeture par backdrop, ✕, Échap, navigation ou retour desktop.

### 1.4 Contrôleur `menu` durci
**Fichier :** `assets/js/controllers/menu_controller.js`

- Fermeture par touche **Échap**, fermeture automatique au retour en viewport
  desktop (panneau « fantôme » après rotation), restauration du scroll du body
  au disconnect, méthodes `open()/close()/isOpen()` propres.

### 1.5 Doublons / fichiers morts
- `public/js/app.js` et `public/css/app.css` (ancien bundle manuel, contrôleur
  `cart` inline contradictoire : `/panier/modifier`, champs `qty/priceCents`) :
  **supprimés** — aucune template ne les référençait ; ils entraient en conflit
  conceptuel avec le build Encore (`assets/app.js`).
- Balise `<script src="https://unpkg.com/@hotwired/stimulus/umd/stimulus.umd.js">`
  supprimée des deux bases : Stimulus est déjà embarqué par le build webpack
  (`@hotwired/stimulus` dans `package.json`) → un seul chargement, une seule
  `Application`, moins de dépendance CDN.

---

## 2. Sécurité — correctifs appliqués

| # | Correctif | Fichiers |
|---|-----------|----------|
| 1 | **Anti force brute login admin** : 5 échecs / 15 min par IP, blocage avant le firewall (429 + page login), remise à zéro au succès. Sans dépendance ajoutée (cache applicatif). | `src/EventListener/LoginThrottlerSubscriber.php` (nouveau), `templates/admin/login.html.twig` |
| 2 | **En-têtes HTTP** : `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY` sur `/admin` et `SAMEORIGIN` ailleurs, `Referrer-Policy`, `Permissions-Policy`. | `src/EventListener/SecurityHeadersSubscriber.php` (nouveau) |
| 3 | **Cookies durcis** : cookie panier `secure` quand la requête est HTTPS ; cookies de session `cookie_secure: auto`, `httponly`, `samesite: lax`. | `src/Cart/CartSession.php`, `src/Controller/CartController.php`, `config/packages/framework.yaml` |
| 4 | **Secrets committés tournés** : nouveaux `APP_SECRET` aléatoires dans `.env` / `.env.dev` + rappel : en prod, secrets via `.env.local` / env serveur, jamais committés. | `.env`, `.env.dev` |
| 5 | **Uploads durcis** : `crop()` et `deleteFile()` n'acceptent que les préfixes `/media/…` et `/uploads/tmp/…` (defense in depth, en plus du contrôle déjà présent côté contrôleur). | `src/Service/ImageProcessor.php` |

### Déjà bien en place (vérifié, non modifié)
- CSRF vérifié serveur sur **tous** les POST AJAX (`X-CSRF-TOKEN`) et formulaires
  Symfony (login inclus) ; jetons distincts `cart` / `admin_actions` / `admin_settings`.
- `access_control: ^/admin → ROLE_ADMIN` + firewall `form_login` avec CSRF.
- Uploads images : whitelist MIME (JPEG/PNG/WebP) + 5 Mo + noms de fichiers
  aléatoires ; suppression physique encadrée.
- Aucune requête SQL brute : QueryBuilder Doctrine avec `setParameter` partout.
- Commande : prix et stock **recalculés serveur** (`OrderService`), exception
  `OutOfStockException` si le stock a bougé depuis le remplissage du panier.
- Panier : identification par cookie token aléatoire 24 octets (httponly, lax)
  + contrôle d'appartenance sur `/panier/maj` et `/panier/supprimer` (pas d'IDOR).
- Twig : échappement automatique partout ; seul `|raw` = `extra` de
  `blur_picture.html.twig`, paramètre interne passé par les templates (risque
  faible, à garder sous contrôle si vous l'ouvrez à des données utilisateur).

### Recommandations non bloquantes (à faire en production)
1. `APP_ENV=prod` + `APP_DEBUG=0` (`php bin/console cache:clear` ensuite).
2. HTTPS partout + HSTS (`Strict-Transport-Security`) au niveau Apache/nginx.
3. Build local de Tailwind et Stimulus (package.json) au lieu des CDN, puis CSP
   avec nonces possible (`default-src 'self'`).
4. Sauvegardes MySQL régulières + rotation des logs.
5. `composer audit` / `npm audit` périodiques.
6. Le dossier vide `corrige tea` (vestige) peut être supprimé.

---

## 3. Mobile & menus — vérifications

- **Boutique :** burger + panneau off-canvas (accueil, carte, best-sellers,
  histoire, contact + catégories) désormais fullscreen ; barre de catégories de
  `menu.html.twig` scrollable horizontalement (`overflow-x-auto`) conservée ;
  grille produits 1→4 colonnes OK ; page panier en grille 2 colonnes mobile OK ;
  tiroir panier `min(92vw, 26rem)` OK ; targets de tap ≥ 40 px.
- **Admin :** sidebar desktop inchangée ; nouvel off-canvas mobile complet
  (cf. 1.3) ; header mobile sticky conservé avec burger.
- **Divers :** Échap ferme menu et tiroir ; scroll du body verrouillé pendant
  l'ouverture d'un panneau ; fermeture auto au passage desktop.

---

## 4. Fichiers modifiés / ajoutés / supprimés

**Modifiés :** `assets/js/controllers/cart_controller.js`,
`assets/js/controllers/menu_controller.js`, `assets/styles/app.css`,
`templates/base.html.twig`, `templates/admin/base.html.twig`,
`templates/admin/login.html.twig`, `src/Cart/CartSession.php`,
`src/Controller/CartController.php`, `src/Service/ImageProcessor.php`,
`config/packages/framework.yaml`, `.env`, `.env.dev`

**Ajoutés :** `src/EventListener/SecurityHeadersSubscriber.php`,
`src/EventListener/LoginThrottlerSubscriber.php`, `AUDIT.md`

**Supprimés :** `public/js/app.js`, `public/css/app.css`

---

## 5. Installation des correctifs chez vous

```bash
# 1. Remplacez votre dossier C:\laragon\www\teako-ecommerce-main par cette copie
#    (ou copiez uniquement les fichiers listés ci-dessus).
cd C:\laragon\www\teako-ecommerce-main

# 2. INDISPENSABLE : recompiler le JS/CSS (les correctifs panier/menu sont dans assets/)
npm ci
npm run build        # ou : npm run watch pendant le développement

# 3. Vider le cache Symfony (nouvelles config + subscribers)
php bin/console cache:clear

# 4. Recharger la page : aucun changement de BDD nécessaire.
```

> Si le navigateur garde l'ancien bundle en cache : Ctrl+F5 (ou vider le cache
> du navigateur). Le build Encore en dev n'est pas versionné (`entrypoints.json`
> pointe vers `public/build/`), un rebuild suffit.
