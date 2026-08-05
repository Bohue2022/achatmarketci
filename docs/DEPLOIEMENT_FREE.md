# Déploiement gratuit — Oracle Cloud Free VPS + Vercel

Guide pas-à-pas pour mettre AchatMarketCI en ligne **gratuitement** :
- **API Laravel** → Oracle Cloud **Free Tier** (VPS ARM always-free : 4 vCPU, 24 Go RAM, 200 Go disque)
- **Frontend Next.js** → **Vercel** (domaine `achatmarketci.vercel.app`)
- **E-mails OTP** → **Brevo SMTP** (déjà configuré : `MAIL_USERNAME=b47b0b001@smtp-brevo.com`, expéditeur `bohuegnonzansara@gmail.com` vérifié)
- **HTTPS pour l'API sans payer de domaine** → **DuckDNS** (sous-domaine gratuit) + **Certbot**

Dépôt : https://github.com/Bohue2022/achatmarketci

---

## Pourquoi un sous-domaine DuckDNS ?

Vercel sert le frontend **uniquement en HTTPS**. Le navigateur **bloque** les requêtes du frontend
(https) vers une API en http (contenu mixte). Il faut donc l'API en HTTPS → il faut un nom de domaine.
DuckDNS offre un sous-domaine gratuit (ex. `api-achatmarketci.duckdns.org`) pointant vers l'IP du VPS,
sur lequel Certbot installe un certificat Let's Encrypt.

> Si vous avez (ou achetez) un vrai domaine `achatmarketci.ci`, la partie DuckDNS se remplace par :
> créer un enregistrement A `api` → IP du VPS, puis certbot sur `api.achatmarketci.ci`.

---

## Partie 1 — Créer le VPS Oracle Cloud

1. **Compte** : https://signup.oraclecloud.com — e-mail + carte bancaire (sert à vérifier
   l'identité, **jamais débitée** sur le Free Tier). Choisir la zone « toujours gratuit ».
2. **Créer l'instance** : menu ☰ → **Compute → Instances → Create instance** :
   - Nom : `achatmarketci-api`
   - Image : **Ubuntu 24.04** (PHP 8.3 inclus dans les dépôts)
   - Shape : **Ampere A1 (ARM)** — 4 OCPU / 24 Go RAM (toujours gratuit)
   - Clés SSH : **Generate a key pair** → télécharger le `.pem` (ex. `achatmarketci.pem`)
   - **Create** puis attendre l'état *Running*. Noter l'**IP publique**.
3. **Ouvrir les ports** : ☰ → **Networking → Virtual Cloud Networks** → le réseau → la
   subnet `public` → **Security Lists** → la liste par défaut → **Add Ingress Rules** :
   - TCP `80` de `0.0.0.0/0` (HTTP)
   - TCP `443` de `0.0.0.0/0` (HTTPS)
4. **Tester la connexion** (depuis PowerShell, dans le dossier où est le `.pem`) :
   ```powershell
   ssh -i .\achatmarketci.pem ubuntu@<IP_PUBLIQUE>
   ```

---

## Partie 2 — Sous-domaine gratuit (DuckDNS)

1. Créer un compte sur https://duckdns.org (se connecter avec GitHub/Google ou un e-mail).
2. Cliquer **Add Domain** → nom `api-achatmarketci` (→ `api-achatmarketci.duckdns.org`).
3. Dans le tableau : **Current IP** = l'IP publique du VPS → **Update IP**.
4. (Optionnel) installer le script auto-update DuckDNS sur le VPS pour garder l'IP à jour.

---

## Partie 3 — Installer et configurer le backend sur le VPS

Connecté en SSH sur le VPS, exécuter :

```bash
# 1) Paquets
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mysql-server certbot python3-certbot-nginx \
  php-fpm php-mysql php-mbstring php-xml php-curl php-zip php-gd php-bcmath \
  composer git unzip

# 2) PHP
php -v   # doit afficher 8.3+

# 3) Récupérer le code
sudo git clone https://github.com/Bohue2022/achatmarketci.git /var/www/achatmarketci
sudo chown -R $USER:$USER /var/www/achatmarketci

# 4) Dépendances + config
cd /var/www/achatmarketci/backend
composer install --no-dev --optimize-autoloader
cp .env.production.example .env
nano .env   # ⬇ voir le bloc « Valeurs à mettre dans .env » ci-dessous
php artisan key:generate

# 5) Base de données MySQL
sudo mysql <<'SQL'
CREATE DATABASE achatmarketci CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'achatmarketci'@'localhost' IDENTIFIED BY 'UN_MOT_DE_PASSE_FORT';
GRANT ALL PRIVILEGES ON achatmarketci.* TO 'achatmarketci'@'localhost';
FLUSH PRIVILEGES;
SQL

# 6) Schéma + données de base (villes, marques, plans)
php artisan migrate --seed

# 7) Stockage des photos
php artisan storage:link
sudo chmod -R 775 storage bootstrap/cache

# 8) Supprimer les comptes de démo AVANT la mise en service
php artisan tinker --execute="App\Models\User::whereIn('email',['admin@rr.ci','modo@rr.ci','pro@rr.ci','particulier@rr.ci'])->delete(); echo 'demo comptes supprimés';"

# 9) Cache de production
php artisan config:cache && php artisan route:cache && php artisan view:cache

# 10) Permissions fpm
sudo chown -R www-data:www-data storage bootstrap/cache
```

### Valeurs à mettre dans `.env`
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api-achatmarketci.duckdns.org
DB_CONNECTION=mysql
DB_DATABASE=achatmarketci
DB_USERNAME=achatmarketci
DB_PASSWORD=UN_MOT_DE_PASSE_FORT
CORS_ALLOWED_ORIGINS=https://achatmarketci.vercel.app
SESSION_SECURE_COOKIE=true
# MAIL_* : déjà pré-remplis dans .env.production.example (Brevo)
#          ⚠️ mettre la vraie clé SMTP dans MAIL_PASSWORD
```

---

## Partie 4 — Nginx + HTTPS (Let's Encrypt)

Créer `/etc/nginx/sites-available/achatmarketci` :

```nginx
server {
    listen 80;
    server_name api-achatmarketci.duckdns.org;

    root /var/www/achatmarketci/backend/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
```

Activer + obtenir le certificat :

```bash
sudo ln -s /etc/nginx/sites-available/achatmarketci /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
sudo certbot --nginx -d api-achatmarketci.duckdns.org   # suivez les instructions
# Renouvellement automatique :
echo "0 3 * * * root certbot renew --quiet --deploy-hook 'systemctl reload nginx'" | sudo tee /etc/cron.d/certbot-renew
```

Vérifier : ouvrir `https://api-achatmarketci.duckdns.org/api/health` → doit répondre `{"status":"ok"}`.

---

## Partie 5 — Déployer le frontend sur Vercel

1. **Compte Vercel** : https://vercel.com → **Sign up** → continuer **avec GitHub**
   (compte `Bohue2022`).
2. **Import** : **Add New… → Project** → choisir le dépôt `achatmarketci`.
3. **Configuration du projet** :
   - Framework : **Next.js**
   - **Root Directory : `frontend`**
   - **Environment Variables** :
     | Nom | Valeur |
     | --- | --- |
     | `NEXT_PUBLIC_API_URL` | `https://api-achatmarketci.duckdns.org/api` |
   - **Deploy**.
4. Résultat : `https://achatmarketci.vercel.app` (modifiable dans **Project → Settings → Domains**).
5. Si le nom `achatmarketci` est pris, Vercel proposera `achatmarketci-<suffixe>.vercel.app` →
   **mettre à jour `CORS_ALLOWED_ORIGINS`** dans le `.env` du VPS puis `php artisan config:cache`.

---

## Partie 6 — Vérification finale

| # | Test |
| --- | --- |
| 1 | Ouvrir `https://achatmarketci.vercel.app` |
| 2 | Créer un compte → recevoir le code OTP (Brevo) → vérifier |
| 3 | Se connecter / déconnecter |
| 4 | Déposer une annonce + photos (stockées sur le VPS) |
| 5 | `https://api-achatmarketci.duckdns.org/api/health` répond |

---

## Mises à jour du code (après un changement)

```bash
cd /var/www/achatmarketci
git pull origin master
cd backend
composer install --no-dev --optimize-autoloader   # si nouvelles dépendances
php artisan migrate --force                       # si nouvelles migrations
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo systemctl reload php8.3-fpm
```
