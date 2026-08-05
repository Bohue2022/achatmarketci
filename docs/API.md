# API REST — AutoMarket CI

Base URL dev : `http://127.0.0.1:8000/api`

## Authentification

Headers requis :
```
Authorization: Bearer <token>        # routes authentifiées
Accept: application/json             # obligatoire (middleware ForceJsonResponse)
```

## Référentiels / publiques

| Méthode | Endpoint | Note |
| ------- | -------- | ---- |
| GET | `/health` | état API + horodatage |
| GET | `/references/brands` | marques + modèles enfants |
| GET | `/references/cities` | villes hiérarchiques (communes) |

## Auth

| Méthode | Endpoint | Body |
| ------- | -------- | ---- |
| POST | `/auth/register` | `name, email, phone, password, password_confirmation, account_type` (particulier\|professionnel) → crée le compte (non vérifié) et **envoie un code OTP** par e-mail ; ne renvoie pas de token. Mot de passe : **8+ caractères avec majuscule, minuscule et caractère spécial**. En `APP_ENV=local`, la réponse contient `dev_code` (le code en clair) |
| POST | `/auth/verify-otp` | `email, otp` (6 chiffres) → vérifie l'e-mail et renvoie `{ token, user }` |
| POST | `/auth/resend-otp` | `email` → renvoie un nouveau code (throttle 5/10 min) |
| POST | `/auth/login` | `email, password` → `{ token, user }` ; **403 `email_not_verified`** si l'e-mail n'est pas encore vérifié |
| POST | `/auth/logout` | 🔒 révoque le token |
| GET | `/auth/me` | 🔒 profil + `email_verified` + quota d'annonces (illimité au lancement) |
| POST | `/auth/profile` | 🔒 mise à jour profil (multipart, avatar ≤2 Mo) |

## Recherche publique

**GET** `/announcements` — filtres optionnels :
`city_id, commune_id, brand_id, model_id, fuel, condition, transmission, color, min_price, max_price, is_dedouane, has_carte_grise, sort` (`newest|cheapest|most_expensive|views`).

**GET** `/announcements/{slug}` — détail complet, incrémente `viewed_count`.

**POST** `/announcements/{slug}/contact` — `name, phone, message` (throttle 10/min).

## Annonces (🔒 auth:sanctum)

| Méthode | Endpoint | Note |
| ------- | -------- | ---- |
| GET | `/my/announcements` | mes annonces (tous statuts) |
| POST | `/announcements` | création → `pending` (quota illimité au lancement) |
| PUT | `/announcements/{id}` | mise à jour |
| DELETE | `/announcements/{id}` | suppression |
| GET | `/favorites` | mes favoris |
| POST | `/announcements/{slug}/favorite` | toggle favori |

### Corps de création d'annonce
```json
{
  "title": "Toyota Prado TX 2020",
  "description": "…",
  "brand_id": 1,
  "model_id": 8,
  "year": 2020,
  "body_type": "suv",
  "color": "Gris",
  "price": 42000000,
  "condition": "used",
  "fuel": "diesel",
  "transmission": "automatic",
  "mileage": 85000,
  "city_id": 1,
  "commune_id": 3,
  "is_dedouane": true,
  "has_carte_grise": true
}
```

## Modération & Admin (🔒 auth:sanctum + rôle moderator/admin)| Méthode | Endpoint | Note |
| ------- | -------- | ---- |
| GET | `/admin/moderation/queue` | file pending (priorité/antécédents) |
| GET | `/admin/moderation/{id}` | vue détaillée + historique vendeur |
| POST | `/admin/moderation/{id}/moderate` | `action: approved\|rejected\|request_changes\|held` + `reason` |
| POST | `/admin/moderation/bulk` | `{ ids: [], action, reason }` |
| GET | `/admin/users` | liste (filtres `role`, `q`, `status: banned\|verified_pro`, pagination) — chaque user expose `created_at`, `rccm_number`, `announcements_count`, `pending_count`, `published_count` |
| GET | `/admin/users/{id}` | détail |
| POST | `/admin/users/{id}/ban` · `/unban` | bannissement (`reason` obligatoire) |
| POST | `/admin/users/{id}/kyc` | `{ verified: bool, rccm_number? }` |
| GET | `/admin/moderators` | **admin uniquement** — liste des comptes modérateurs dédiés |
| POST | `/admin/moderators` | **admin uniquement** — crée un compte modérateur `{ name, email, phone?, password, password_confirmation }` (identifiants = email + mot de passe) |
| DELETE | `/admin/moderators/{id}` | **admin uniquement** — retire le compte (soft delete : plus de connexion, historique conservé) |
| GET | `/admin/stats` | dashboard : sections `listings` (active/pending/published_total/rejected_total/expired/suspended), `moderation` (pending, total_actions, `avg_moderation_minutes`, `approval_rate`, `by_moderator`), `users` (total, particuliers, pros, verified_pros, banned, `new_this_month`), `daily` (14 jours : annonces créées + inscriptions), `recents` (annonces, actions de modération, utilisateurs) |
| GET | `/admin/announcements` | toutes annonces (filtres `status`, `q`, `seller_type: user\|pro`, tri `sort: newest\|oldest\|expensive\|cheap`, pagination) — réponse avec `counts` par statut |

## Codes d'erreur
- `401` non authentifié / token invalide
- `403` accès refusé (rôle insuffisant, banni)
- `422` validation (motif manquant)
- `404` ressource introuvable