# 🚗 AutoMarket CI — Marketplace de véhicules (Côte d'Ivoire)

Plateforme de vente de véhicules neufs et d'occasion dédiée au marché ivoirien.
Architecture **freemium/payant** pour les professionnels (concessionnaires, garages, importateurs)
avec **back-office complet de modération** des annonces.

> **Phase 1 livrée** : socle fonctionnel (auth, annonces CRUD + quota, recherche/filtres,
> dashboard admin de validation). Les paiements Mobile Money sont **conçus mais non codés** (Phase 2).

---

## 🧱 Stack

| Couche    | Technologie                                                     |
| --------- | --------------------------------------------------------------- |
| Backend   | Laravel 13 (PHP 8.3) + API REST Sanctum                          |
| Frontend  | Next.js 16 (App Router) + TypeScript + TailwindCSS               |
| BDD       | PostgreSQL (prod) · SQLite (dev local, tests en mémoire)         |
| Cache/Queue| Redis (prod, prévu) · stockage S3/Cloudinary (prévu)             |
| Paiement  | Agrégateur local (CinetPay/PayDunya) — conçu, non implémenté     |

---

## 📁 Structure du monorepo

```
RR/
├── backend/          # API Laravel
│   ├── app/Http/Controllers/     # Contrôleurs API + Admin
│   ├── app/Services/             # Logique métier (quota, modération)
│   ├── app/Enums/                # Role, statuts, carburant, etc.
│   ├── app/Models/               # Eloquent
│   ├── database/migrations/      # Schéma complet
│   ├── database/seeders/         # Villes CI, marques/modèles, démo
│   ├── routes/api.php            # Toutes les routes API
│   └── tests/Feature/            # 18 tests de parcours
├── frontend/         # Next.js
│   ├── app/                      # Routes (listing, fiche, auth, admin)
│   ├── components/               # Navbar, cartes annonces
│   └── lib/                      # Client API + types
└── docs/             # Architecture, BDD, paiements, API, phases
```

---

## 🚀 Démarrage rapide

### Prérequis
- PHP 8.3+ et Composer
- Node.js 20+

### Backend

```bash
cd backend
composer install
cp .env.example .env                 # DB sqlite par défaut pour le dev
php artisan key:generate
php artisan migrate:fresh --seed     # schéma + villes CI + marques + démo
php artisan serve --port=8000
```

### Frontend

```bash
cd frontend
npm install
npm run dev                          # http://localhost:3000
```

Le frontend appelle l'API sur `http://127.0.0.1:8000/api` (variable `NEXT_PUBLIC_API_URL`).

### Comptes de démonstration (seed)

| Rôle       | Email                | Mot de passe |
| ---------- | -------------------- | ------------ |
| Admin      | `admin@rr.ci`        | `password`   |
| Modérateur | `modo@rr.ci`         | `password`   |
| Professionnel | `pro@rr.ci`       | `password`   |
| Particulier | `particulier@rr.ci`  | `password`   |

---

## ✅ Ce qui est livré (Phase 1)

- **Auth** : inscription (particulier/pro), connexion, token Sanctum, profil
- **Rôles** : `user` (particulier) · `pro` · `moderator` · `admin`
- **Annonces** : CRUD complet, statuts `draft → pending → published / rejected / expired`,
  slug SEO unique, **quota** (2 annonces actives max pour un particulier, illimité pro),
  durée de vie 30 jours, multi-photos
- **Recherche/filtres** : ville + commune (Abidjan Cocody, Yopougon…), marque/modèle,
  prix FCFA, carburant, boîte, état, dédouané, carte grise, origine, tri
- **Admin** : file de modération (priorité, tri), vue détaillée avec historique vendeur,
  valider/refuser (motif obligatoire)/demander modification/attente, actions en masse,
  traçabilité `moderation_actions`, gestion utilisateurs (ban/KYC/abonnement),
  dashboard statistiques (annonces actives, MRR, taux d'approbation, temps moyen)
- **Frontend** : accueil avec recherche/filtres, fiche annonce SSR, connexion/inscription,
  dépôt d'annonce, dashboard admin + file de modération
- **Tests** : 18 tests de fonctionnalités (auth, quota, filtres, modération, bulk)

---

## 🔭 Roadmap

| Phase | Contenu | Statut |
| ----- | ------- | ------ |
| **1 — Socle** | Auth, annonces, recherche, modération | ✅ Livrée |
| **2 — Monétisation** | Abonnements pro, Mobile Money (Orange Money/Wave/Moov), page vitrine, stats | 📐 Conçue |
| **3 — Optimisation** | SEO avancé (Schema.org Vehicle), Elasticsearch, messagerie, alertes, notifications | ⏳ |
| **4 — Scale & confiance** | KYC pro, détection fraude, avis clients, simulateur financement, app mobile | ⏳ |

Détails : [`docs/PHASES.md`](docs/PHASES.md)

---

## 📚 Documentation

- [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) — architecture, schéma BDD, déploiement
- [`docs/API.md`](docs/API.md) — endpoints REST
- [`docs/PAIEMENTS.md`](docs/PAIEMENTS.md) — conception paiement Mobile Money
- [`docs/PHASES.md`](docs/PHASES.md) — feuille de route détaillée

---

## 🧪 Tests

```bash
cd backend && php artisan test
```

## 🐘 Production (PostgreSQL)

Voir `docs/ARCHITECTURE.md` : `DB_CONNECTION=pgsql`, Redis pour cache/queues,
disque S3 pour les photos, déploiement conteneurisé avec le `docker-compose.yml` fourni.
