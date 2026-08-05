# Architecture & Base de données — AutoMarket CI

## 1. Vue d'ensemble

```
                    ┌────────────┐      REST/JSON (Sanctum)      ┌──────────────┐
  Côté client (SSR)│  Next.js 16 │ ◄──────────────────────────►  │  Laravel 13  │
  + navigation JS  │   :3000     │                                │  API :8000   │
                    └────────────┘                                    └─────┬────┘
                                                                             │
                                                    ┌───────────┬───────────┴───────────┐
                                                    │  Postgres │   Redis (queues/cache) │
                                                    │  (SQLite  │                        │
                                                    │   en dev) │                        │
                                                    └───────────┴───────────────────────┘
```

- **Élévation de rôles** côté API uniquement. Le frontend ne sert que l'UI selon le rôle
  renvoyé par `/auth/me`.
- **Aucune logique métier côté client** : quota, modération, priorité, durées, MRR → backend.

## 2. Schéma relationnel

### Identité & sécurité
```
users
  - id, name, email (unique), password, phone
  - role: enum [user|pro|moderator|admin]
  - is_banned (bool), banned_at
  - kyc_status: enum [none|pending|verified|rejected], kyc_documents (JSON nullable)
  - company_name, company_siret, city_id, commune_id (nullable)
  - timestamps, remembered_at

personal_access_tokens   (Sanctum)
  - id, tokenable (users), name, token, abilities (JSON), last_used_at, expires_at
```

### Référentiels
```
brands
  - id, name (unique), slug, logo_url (nullable)

vehicle_models
  - id, brand_id (FK), name, slug

cities                      (hiérarchie Ville → Commune)
  - id, name, slug, parent_id (nullable, FK self), type: enum [city|commune]
  - ex: "Abidjan" (city) ← "Cocody", "Yopougon"… (communes)
```

### Annonces & modération
```
announcements
  - id, user_id (FK)
  - title, slug (unique), description
  - brand_id, model_id, year, body_type(FK), color
  - price (integer FCFA), currency (défaut "XOF")
  - condition: [new|used], fuel: [diesel|essence|hybride|electrique]
  - transmission: [manual|automatic], mileage (km)
  - spec (JSON)              # vers libre optionnel
  - status: [draft|pending|published|rejected|expired]
  - published_at, expires_at, viewed_count
  - is_dedouane (bool), has_carte_grise (bool), origin (nullable)
  - timestamps (soft deletes réutilisables si besoin)

announcement_photos
  - id, announcement_id (FK, cascade), url, position (int), is_cover (bool)

announcement_reports            (signalement public)
  - id, announcement_id (FK), reporter_user_id (nullable), reason, status, resolved_at

announcement_contacts           (demandes de contact)
  - id, announcement_id (FK), name, phone, email (nullable), message, status

favorites
  - id, user_id (FK), announcement_id (FK), unique(user_id, announcement_id)

moderation_actions              (traçabilité de chaque décision)
  - id, announcement_id (FK), moderator_id (FK users)
  - action: [approved|rejected|request_changes|held]
  - reason (nullable, obligatoire pour rejected)
```

### Abonnement / monétisation
```
plans
  - id, name, slug, price (FCFA), duration_days, max_active_announcements, positions_boost, features (JSON)

user_subscriptions
  - id, user_id (FK), plan_id (FK), status: [active|expired|cancelled]
  - starts_at, expires_at, amount_paid, payment_method, reference (mobile money)
```

## 3. Contraintes clés
- `announcements.slug` unique → URL SEO stable.
- `favorites` contrainte unique composite.
- FK avec `ON DELETE CASCADE` sur `announcement_photos`.
- Query agnostique SGBD : **pas** de `EXTRACT(EPOCH…)` postgres-only (voir `AdminStatsController`).

## 4. Règles métier
**Quota (particulier `user`)**
- `max_active = 2` annonces actives simultanément ; durée de vie **30 jours**.
- Dépassement → exception `QuotaExceededException` → HTTP 422 avec suggestion du plan "Starter".
- Professionnels (`pro`/plans) : déblocage par abonnement (voir `docs/PAIEMENTS.md`).

**Modération**
- Toute nouvelle annonce passe `pending`.
- Le modérateur arrive sur `queue` ; les annonces de vendeurs ayant des antécédents
  (rejets antérieurs) peuvent être priorisées.
- `approved → published` ; `rejected` requiert un motif (422 sinon) ; `request_changes` retourne au vendeur.

## 5. Déploiement (erreurs classiques)
- **Exécution serveur PHP** : ne pas utiliser `php artisan serve` derrière un reverse proxy seul ;
  préférer PHP-FPM (Nginx/Apache). `docker-compose.yml` fournit un socle.
- **Env de prod** : `APP_ENV=production`, `APP_DEBUG=false`, `DB_CONNECTION=pgsql`.
- **Cache/queues** : démarrer worker `php artisan queue:work` pour les notifications/emails.
- **Vue de fichiers** : disque S3 compatible (Cloudinary/S3) via driver `disque` ; photos stockées hors badge.
- **Sanctum** : `STATE_DOMAIN` / `FRONTEND_URL` corrects pour les cookies ; en mode token, header `Authorization: Bearer`.