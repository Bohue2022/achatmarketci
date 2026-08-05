# Déploiement — AutoMarket CI

Cible :
- **Backend Laravel** (API) → VPS ou hébergement PHP classique
- **Frontend Next.js** → **Vercel** (sous-domaine gratuit)
- **E-mails (OTP)** → **Brevo** (Sendinblue) via SMTP

---

## Prérequis (VPS / hébergement PHP)

- **PHP 8.4+** avec extensions : `pdo_mysql`, `mbstring`, `curl`, `zip`, `gd`, `bcmath`, `openssl`
- **Composer**
- **MySQL / MariaDB** (ou PostgreSQL)
- Accès à `php artisan` (CLI ; sur un hébergeur mutualisé, généralement dispo)

---

## 1. Configurer Brevo (envoi des codes OTP)

1. Créer un compte gratuit sur <https://brevo.com> (300 e-mails/heure).
2. **SMTP & API → SMTP keys → Générer une clé**.
3. Retenir :
   - `MAIL_PASSWORD` = la clé SMTP générée
   - `MAIL_USERNAME` = l'adresse e-mail du compte Brevo
4. **Senders & IP → Senders → Ajouter + vérifier un expéditeur** (ex. `noreply@votre-domaine.ci`).
5. Reporter ces valeurs dans `.env` du backend.

---

## 2. Déployer le backend (VPS / cPanel)

### a) Transférer le code
```bash
# depuis le dossier backend/ (côté votre machine)
scp -r . user@serveur:/var/www/automarket/
```

### b) Dépendances + configuration
```bash
cd /var/www/automarket
composer install --no-dev --optimize-autoloader
cp .env.production.example .env
nano .env   # adapter : DB_*, MAIL_*, APP_URL, CORS_ALLOWED_ORIGINS
php artisan key:generate
```

### c) Base de données (MySQL/MariaDB)
```sql
CREATE DATABASE automarket CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'automarket'@'localhost' IDENTIFIED BY 'UN_MOT_DE_PASSE_SUR';
GRANT ALL PRIVILEGES ON automarket.* TO 'automarket'@'localhost';
FLUSH PRIVILEGES;
```

```bash
php artisan migrate --seed   # villes, marques, plans, comptes démo
```

> Les comptes de démo (`admin@rr.ci`, `pro@rr.ci`, …) sont *vérifiés* pour permettre les tests.
> Avant la mise en service réelle, les supprimer :
```bash
php artisan tinker --execute
#  App\Models\User::whereIn('email',['admin@rr.ci','modo@rr.ci','pro@rr.ci','particulier@rr.ci'])->delete();
```

### d) Stockage des photos / avatars
```bash
php artisan storage:link
chmod -R 775 storage bootstrap/cache
```

### e) Optimisation
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### f) Serveur web — Nginx
```nginx
server {
    listen 80;
    server_name api.votre-domaine.ci;

    root /var/www/automarket/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
```

> Sur cPanel/Plesk : déclarer `public/` comme racine du site ; l' `.htaccess` de Laravel est déjà fourni.

> **E-mails :** les OTP sont envoyés en **synchrone** — pas de worker nécessaire.
> **Dev :** avec `APP_ENV=local`, l'API renvoie `dev_code` (code en clair) dans les réponses
> register/resend, pour tester sans boîte mail. En production, ce champ est absent.
> **Mot de passe :** 8+ caractères, une majuscule, une minuscule et un caractère spécial (politique appliquée à l'inscription et à la création de modérateurs).

---

## 3. Déployer le frontend (Vercel)

1. Pousser `frontend/` sur un dépôt **GitHub**.
2. Dans **vercel.com → New Project** : importer le dépôt (détection automatique de Next.js).
3. **Project → Settings → Environment Variables** :
   - `NEXT_PUBLIC_API_URL=https://api.votre-domaine.ci/api`
4. **Deploy** — sous-domaine par défaut `mon-app.vercel.app` (ou votre domaine via **Domains**).

> `NEXT_PUBLIC_API_URL` est injectée au **build** : toute modification impose un redeploiement.

---

## 4. Vérification finale

| # | Test |
| --- | --- |
| 1 | Ouvrir le site et créer un compte |
| 2 | Recevoir le code OTP par e-mail (Brevo) |
| 3 | Saisir le code → compte vérifié, session ouverte |
| 4 | Se déconnecter puis se reconnecter |
| 5 | Déposer une annonce + téléverser des photos |
| 6 | Vérifier que l'API répond : `GET /api/health` |

---

## Sécurité — check-list

- [ ] `APP_DEBUG=false` et `SESSION_SECURE_COOKIE=true`
- [ ] `OTP_STATIC` **absent** du `.env` de production
- [ ] `CORS_ALLOWED_ORIGINS` limité au domaine du frontend
- [ ] HTTPS obligatoire (certificat Let's Encrypt / intégré Vercel)
- [ ] Comptes de démo supprimés avant mise en service réelle
- [ ] Ne jamais désactiver la vérification e-mail