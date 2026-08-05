# Déploiement gratuit — alwaysdata (sans carte bancaire) + Vercel

Guide pas-à-pas pour mettre AchatMarketCI en ligne **gratuitement et sans carte bancaire** :
- **API Laravel** → **alwaysdata** (plan *Free* : 0 € à vie, sans carte, HTTPS inclus, PHP 8, MySQL/Postgres, SSH)
- **Frontend Next.js** → **Vercel** (domaine `achatmarketci.vercel.app`)
- **E-mails OTP** → **Brevo SMTP** (déjà configuré : `MAIL_USERNAME=b47b0b001@smtp-brevo.com`, expéditeur `bohuegnonzansara@gmail.com` vérifié)

> Pourquoi pas Oracle/Render ? Oracle demande une carte (refusée pour vous), Render n'est pas souhaité.
> alwaysdata ne demande **aucune carte** à l'inscription (vérifié sur le site officiel).

Dépôt : https://github.com/Bohue2022/achatmarketci

---

## Ce qu'on obtient

| Élément | Valeur |
| --- | --- |
| URL de l'API | `https://achatmarketci.alwaysdata.net` |
| URL du site | `https://achatmarketci.vercel.app` |
| Base de données | MySQL (offerte) |
| HTTPS | Inclus sur l'API (nécessaire, sinon le navigateur bloque les appels depuis Vercel) |

---

## Partie 1 — Créer le compte alwaysdata

1. Aller sur https://www.alwaysdata.com → **Sign up / Inscription**
   - E-mail + mot de passe, **aucune carte bancaire**.
2. Confirmer l'e-mail de validation reçu.
3. Vous arrivez sur l'**Admin** (panel alwaysdata).

---

## Partie 2 — Créer la base de données

Dans l'admin alwaysdata :
1. **SQL → MySQL** → **Create a database** :
   - Nom : `achatmarketci`
   - *Serveur : laisser le serveur par défaut (`mysql-...`)*
   - **Create**.
2. Notez : **nom du serveur MySQL**, **nom de la base**, **utilisateur** et **mot de passe**
   (utilisateur = votre compte alwaysdata, mot de passe = celui du compte admin).
   Ces 4 valeurs iront dans le `.env` du backend.

---

## Partie 3 — Héberger le backend (Laravel)

### a) Activer SSH + ouvrir un terminal

1. Dans l'admin : **SSH → Features** → activer.
2. Sur votre machine, se connecter (remplacer `alice` par votre identifiant alwaysdata) :
   ```powershell
   ssh alice@ssh-alice.alwaysdata.net
   ```
   (le mot de passe = celui de votre compte alwaysdata).

### b) Installer le code

Sur le serveur (SSH) :
```bash
cd ~/www
git clone https://github.com/Bohue2022/achatmarketci.git
cd achatmarketci/backend
# Dépendances (sans les outils de dev)
composer install --no-dev --optimize-autoloader

# Fichier de configuration de prod
cp .env.production.example .env
nano .env   # ⬇ adapter (voir ci-dessous)

# Clé de chiffrement
php artisan key:generate
```

### c) Contenu du `.env`

```env
APP_NAME=AchatMarketCI
APP_ENV=production
APP_DEBUG=false
APP_URL=https://achatmarketci.alwaysdata.net

# Base de données (valeurs de la Partie 2)
DB_CONNECTION=mysql
DB_HOST=mysql-XXXXX.alwaysdata.net    # serveur MySQL alwaysdata
DB_PORT=3306
DB_DATABASE=achatmarketci
DB_USERNAME=<votre-identifiant-alwaysdata>
DB_PASSWORD=<mot-de-passe-du-compte-alwaysdata>

# E-mail (Brevo) — MAIL_* déjà pré-remplis dans .env.production.example
# ⚠️ MAIL_PASSWORD = la vraie clé SMTP xsmtpsib-...
MAIL_FROM_ADDRESS=bohuegnonzansara@gmail.com

# CORS : autoriser le frontend Vercel
CORS_ALLOWED_ORIGINS=https://achatmarketci.vercel.app

SESSION_SECURE_COOKIE=true
```

### d) Migrations + données de base

```bash
php artisan migrate --seed          # villes, marques, plans
php artisan storage:link            # stockage des photos
# Supprimer les comptes de démo avant mise en service réelle :
php artisan tinker --execute="App\Models\User::whereIn('email',['admin@rr.ci','modo@rr.ci','pro@rr.ci','particulier@rr.ci'])->delete(); echo 'ok';"
# Cache de production :
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

### e) Exposer la bonne racine du site

1. Dans l'admin alwaysdata : **Web → Sites** → modifier le site créé par défaut
   (ou en créer un).
2. **Directory (racine du site)** : pointer vers `www/achatmarketci/backend/public`
   (c'est ce que fait l'installateur Laravel d'alwaysdata automatiquement).
3. **URL** : `achatmarketci.alwaysdata.net` (sous-domaine gratuit) → **Save**.
4. HTTPS : dans **Web → Sites → votre site → SSL/TLS**, activer le certificat gratuit.

### f) Vérifier l'API

Ouvrir `https://achatmarketci.alwaysdata.net/api/health` → doit répondre `{"status":"ok"}`.

---

## Partie 4 — Déployer le frontend sur Vercel

1. **Compte Vercel** : https://vercel.com → **Sign up** → continuer **avec GitHub** (compte `Bohue2022`). *(Aucune carte : Vercel est gratuit pour ce projet.)*
2. **Add New… → Project** → importer le dépôt `achatmarketci`.
3. Configuration :
   - Framework : **Next.js**
   - **Root Directory : `frontend`**
   - **Environment Variables** :
     | Nom | Valeur |
     | --- | --- |
     | `NEXT_PUBLIC_API_URL` | `https://achatmarketci.alwaysdata.net/api` |
   - **Deploy**.
4. Site en ligne : `https://achatmarketci.vercel.app`
   (si ce nom est pris, Vercel en proposera un autre → mettre à jour `CORS_ALLOWED_ORIGINS` dans le `.env` puis `php artisan config:cache`).

---

## Partie 5 — Vérification finale

| # | Test |
| --- | --- |
| 1 | Ouvrir `https://achatmarketci.vercel.app` |
| 2 | Créer un compte → recevoir le code OTP (Brevo) → vérifier |
| 3 | Se connecter / déconnecter |
| 4 | Déposer une annonce + photos |
| 5 | `https://achatmarketci.alwaysdata.net/api/health` répond |

---

## Mises à jour du code

```bash
cd ~/www/achatmarketci
git pull origin master
cd backend
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

---

## Limites de l'offre gratuite alwaysdata (à connaître)

- 1 Go de disque, 256 Mo de RAM, 0,25 CPU : **suffisant pour démarrer**, pas pour la grosse charge.
- Usage **personnel** uniquement (passage payant à ~5 €/mois si le site grossit).
- Si jamais le SMTP Brevo (port 587) est bloqué par alwaysdata, passer sur le **port 2525** de Brevo (`MAIL_PORT=2525`) — à tester.
- Les photos sont stockées sur le disque local (1 Go) : pour beaucoup de photos, prévoir S3/Cloudinary plus tard.
