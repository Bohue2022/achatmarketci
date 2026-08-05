# Paiements & Monétisation — AutoMarket CI

> **Statut : SUSPENDU pour le lancement.**
> Le site lance en **100 % gratuit** (aucune option d'abonnement, aucun quota d'annonces).
> Ce document reste la référence de conception pour réactiver la monétisation plus tard
> (le code backend d'abonnement a été conservé dormant : modèles/migrations/contrôleur/service).

## 1. Offre

| Plan      | Prix (FCFA) | Durée  | Annonces actives | Extras |
| --------- | ----------- | ------ | ---------------- | ------ |
| Free      | 0           | —      | 2 (particularités) | bagde "Pro" sans débloquage |
| Starter   | 15 000      | 30 j   | 10               | rehaussement standard |
| Business  | 35 000      | 30 j   | 50               | rehaussement, stats, 3 utilisateurs |
| Premium   | 75 000      | 30 j   | illimité         | booster, vitrine, API |

Déjà présents dans `plans` (seeder). Le **particulier** reste limité par défaut :
2 annonces / 30 jours. Un pro **souscrit** un plan pour débloquer le quota
(voir `AnnouncementService::assertCanPublish` + `QuotaExceededException`).

## 1bis. Retour en arrière pour le lancement (gratuit)

- `User::activeAnnouncementLimit()` → renvoie toujours `null` (illimité pour tous).
- Routes `subscriptions/*` et `/admin/users/{id}/subscription/cancel` supprimées.
- Frontend : pages `/abonnement`, `/abonnement/payer/[id]`, `/admin/abonnements` supprimées,
  lien Navbar retiré, stats d'abonnement retirées du tableau de bord admin.
- Le code backend (SubscriptionController, SubscriptionService, Payment, migrations) est
  **conservé dormant** pour réactiver la monétisation ; il suffira de recréer les routes.

## 2. Agrégateur cible : CinetPay / PayDunya

Pour la Côte d'Ivoire, passer par un agrégateur qui expose **Orange Money, Wave et Moov Money**
via un seul webhook, plutôt qu'une intégration par opérateur.

### Flux choisi : paiement initié côté serveur + webhook
```
Frontend (Next)                      Laravel                      CinetPay
     │  POST /api/subscribe            │                            │
     ├─(plan_id)──────────────────────►│  Créer UserSubscription      │
     │                                 │  status=pending              │
     │                                 │  appeler création paiement   │
     │                                 │──────────────────────────────►│
     │                                 │◄─────────────────────────────┤ payment check
     │                                 │  renvoyer payment_url         │
     │◄────────────────────────────────┤  + transaction_id             │
     │  redirige user vers le checkout opérateur / page CinetPay       │
     │                                 │  WEBHOOK : payment_success / failed ──►
     │                                 │  vérifier signature (secret)   │
     │                                 │  signature == hash(cp_order_id+…)│
     │                                 │  activer subscription (active) │
     │                                 │  job en file (Redis) + email   │
```

## 3. Modèle de données prévu
- `user_subscriptions` (déjà migré) :
  `status: active|expired|cancelled`, `amount_paid`, `payment_method`, `reference` (n° transaction).
- `payments` (à ajouter) :
  `user_id, user_subscription_id, provider (cinetpay|paydunya), transaction_id (unique),
   amount, currency (XOF), status: pending|succeeded|failed|refunded, raw (JSON)`.

## 4. Sécurité
1. **Signature webhook** : vérifier `sha256(secret + données)` côté serveur — ne jamais
   faire confiance au payload seul.
2. **Idempotence** : `transaction_id` unique en base ; ignorer les doublons.
3. **Montant** : toujours relire le montant annoncé côté serveur, ne jamais accepter celui du webhook.
4. **Expiration** : cron/worker `subscriptions.expire` → passe les `active` dépassées à `expired`
   et recalcule le quota en conséquence.

## 5. Variables d'environnement (à créer en Phase 2)
```
CINETPAY_API_KEY=…
CINETPAY_SITE_ID=…
CINETPAY_SECRET=…
CINETPAY_WEBHOOK_BASE=https://api.auto-market.ci/webhooks/cinetpay
PASSPORT_FRONT_URL=https://auto-market.ci/checkout/return
```