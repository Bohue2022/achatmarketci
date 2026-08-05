# Déploiement gratuit — InfinityFree (sans carte bancaire) + Vercel

Déploiement sans SSH. Pour Tester/n°2, le socle du projet : **InfinityFree (PHP 8.3, MySQL)**.
Pendant le déploiement on utilise PHP **local** pour générer le bundle.

---

## Contexte — ce qui fait bloquer sans SSH

| Problème | Solution appliquée |
| --- | --- |
| `php artisan` indisponible (console) | On prépare tout côté **local** |
| Ici, les migrations `php artisan migrate` ne « marchent pas » | On les **recrée** une fois |

---

## Étape 1 — Compte InfinityFree

1. https://www.infinityfree.com → **Register** (e-mail + mdp, **aucune carte**).
2. Créer un site → sous-domaine `achatmarketci` → **`achatmarketfree.je`**.
3. Noter dans le panneau :
   - **FTP** : `ftpupload.net` / user / mdp
   - **MySQL** : `sqlXXX.infinityfree.com` / database / user / mdp

---

## Étape 2 — Préparer le bundle (déjà fait ici)

Le bundle est prêt sur le Bureau : `C:\Users\kamag\Desktop\deploy-infinityfree\`
- `1_app.zip` : code Laravel **sans vendor** (menu, config, .env, install.php, etc.)
- `2_vendor.zip` : dossier `vendor/` (Composer n'est pas dispo sur le serveur)

> Le bundle contient les fichiers **secrets** (`.env` + clés) → à garder uniquement pour déployer.
> Repassez-les dans Git **seuls** si besoin de refaire ce bundle.

---

## Étape 3 — Upload du bundle sur InfinityFree

1. Dans le panneau InfinityFree : **Open File Manager** → ouvrir `htdocs/`.
2. Vider `htdocs/` (supprimer les fichiers par défaut de démonstration).
3. **Upload** `1_app.zip` (depuis le Bureau) → clic droit → **Extract** (dans le dossier courant).
4. **Upload** `2_vendor.zip` → **Extract** (dans le même dossier).
   - Résultat attendu dans `htdocs/` : `app/`, `bootstrap/`, `config/`, `database/`, `public/`, `routes/`, `storage/`, `vendor/`, `.env`, `.htaccess`, `install.php`, `artisan`, `composer.json`, …

> ⚠️ Si l'extraction crée un sous-dossier supplémentaire (ex. `htdocs/1/x/`), re-déplacer les éléments
> à la racine de `htdocs/`. Le contenu du zip doit DEVENIR `htdocs/`.

---

## Étape 4 — Lancer l'installation de la base

1. Ouvrir : `https://achatmarketci.free/ext/install.php?key=ACHATMARKET_SETUP`
   → doit afficher **`MIGRATION_OK`** (+ la liste des migrations).
   - Si erreur affichée, fermer/fermer au besoin, `APP_DEBUG=true` aide au diagnostic.
2. **Supprimer `install.php`** dans le File Manager** (obligatoire, c'est une porte).
3. Vérifier l'API : `https://achatmarketci.free.je/api/health` → `{"status":"ok"}`.

---

## Étape 5 — Sécuriser / finaliser le backend

Une fois en ligne :
```env
APP_DEBUG=false
```
Puis re-uploader `.env` modifié (File Manager → remplacer).

Supprimer les comptes de démo (post-setup) via une base mysql (phpMyAdmin du panneau) :
```
DELETE FROM users WHERE email IN ('admin@rr.ji','modo@rr.ji','pro@rr.ji','particulier@rr.ji');
```

---

## Étape 6 — Déployer le frontend (Vercel)

1. https://vercel.com → **Sign up** → avec **GitHub** (`Bohue2022`).
2. **Add New → Project** → importer `achatmarketci`.
3. Réglages :
   - Framework : **Next.js**
   - **Root Directory : `frontend`**
   - Variables d'env : `NEXT_PUBLIC_API_URL=https://achatmarketci.free.je/api`
   - **Deploy**.
4. Site : `https://achatmarketci.vercel.app`.

---

## Vérifications finales

| # | Test |
| --- | --- |
| 1 | `https://achatmarketci.vercel.app` s'ouvre |
| 2 | Créer un compte → code OTP reçu (Brevo) → vérifier |
| 3 | Connexion / déconnexion |
| 4 | Déposer une annonce + photos |
| 5 | `https://achatmarketci.free.je/api/health` |

---

## Limites connues d'InfinityFree

- **Pas de SSH** ni queue workers ni scheduler → envoi d'OTP **synchrone** (déjà le cas).
- **`open_basedir`** : tout doit rester dans `htdocs/` (respecté).
- MySQL accessible **uniquement** depuis le réseau Infinity → migrations via `install.php` (pas en local).
- **SMTP sortant (Brevo port 587)** : à tester ; si bloqué, passer sur le **port 2525** de Brevo.
- Photos : `storage:link` non dispo → les uploads ne sont pas servis publiquement via `/storage` ; à traiter plus tard (S3/CDN ou controller adapté).
- Hobby hosting : prévoir un VPS payant (~5 €/mois) quand le trafic grossit.