# Feuille de route — AutoMarket CI

## ✅ Phase 1 — Socle (livrée)
- [x] Backend Laravel 13 + Sanctum, auth (register/login/me/logout, rôle au register)
- [x] Modèle de données complet : users enrichi, référentiels (villes CI, marques/modèles), annonces, modération, favoris, contacts, abonnements, plans
- [x] CRUD annonces + slug unique + statuts (draft→pending→published/rejected/expired)
- [x] Quota particuliers (2 annonces/30 j) + suggestion de plan à la 422
- [x] Recherche publique avec filtres (ville, commune, marque, carburant, prix, dédouané…)
- [x] Fiche annonce + compteur de vues + prise de contact (throttle)
- [x] Favoris (toggle)
- [x] Modération admin : file, vue détaillée + historique vendeur, décisions tracées (moderation_actions), actions en masse, refus avec motif obligatoire
- [x] Admin : gestion utilisateurs (ban/unban, KYC, abonnement), dashboard stats (annonces actives, MRR, taux approbation, temps moyen)
- [x] Frontend Next.js 16 : accueil (recherche+filtres), fiche SSR, connexion/inscription, dépôt annonce, dashboard admin + file de modération
- [x] Seeders (villes, marques, plans, comptes démo) + 18 tests Feature

## 📐 Phase 2 — Monétisation (reportée après le lancement gratuit)

Le site lance en **100 % gratuit** (aucun quota d'annonces, pas d'option d'abonnement).
Le code backend d'abonnement (modèles, migrations, `SubscriptionService`) est conservé dormant.
- [ ] Réactiver les routes `subscriptions/*` et la page `/abonnement`
- [ ] Intégration Mobile Money CinetPay (Orange Money, Wave, Moov) — voir `docs/PAIEMENTS.md`
- [ ] Table `payments` + webhooks signés + scheduler d'expiration des souscriptions
- [ ] Baisse du quota pro selon plan actif ; possibilité de booster/rehausser une annonce
- [ ] Tableau de bord pro (vues, contacts, stats)

## ⏳ Phase 3 — Optimisation & SEO
- [ ] SEO technique : Schema.org `Vehicle` (JSON-LD) sur les fiches, sitemap, opengraph
- [ ] Moteur de recherche : ElasticSearch (ou Meilisearch) pour les filtres combinés
- [ ] Messagerie interne (au lieu d'exposer téléphone) + alertes de recherche
- [ ] Notifications email/SMS (Laravel Notifications + Redis queue)
- [ ] Middleware de détection d'annonces dupliquées / spam (heure de parution, IP, phone)

## 🚀 Phase 4 — Scale & confiance
- [ ] KYC pro renforcé (pièces d'identité + justificatif, scan)
- [ ] Anti-fraude : scoring, typologie des vendeurs, flag automatique
- [ ] Avis / réputation des vendeurs suite à une transaction
- [ ] Simulateur de financement / apport
- [ ] Application mobile (webview ou RN) + notifications push

## 🚢 Déploiement visé (Phase 2+)
- PostgreSQL + Redis, Laravel PHP-FPM (Docker), disque S3 pour photos, worker de queues.
- Nuages CI avec certification SSL, domaine `auto-market.ci` + `www`.