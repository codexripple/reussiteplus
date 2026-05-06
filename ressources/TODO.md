# TODO LISTE PRIORISÉE — RÉUSSITE+

## 🔴 P0 — Sécurité critique (immédiat)
- [ ] Corriger l’Open Redirect dans `connexion.php`
- [ ] Supprimer la fuite de `dev_url` en production dans `mot_de_passe_oublie.php`
- [ ] Externaliser les secrets dans un fichier `.env` et ajouter `.gitignore`
- [ ] Recréer `schema.sql` en version MySQL (supprimer la version PostgreSQL)
- [ ] Protéger les scripts seeds (`seed.php`, etc.) contre l’accès public

## 🟠 P1 — Fonctionnel bloquant (cette semaine)
- [ ] Implémenter l’envoi d’email (PHPMailer + Brevo/Mailgun)
- [ ] Ajouter le rate limiting sur login, register, reset (table SQL)
- [ ] Finaliser le paiement : intégrer un webhook opérateur ou interface admin de validation + notification utilisateur
- [ ] Corriger la race condition sur le score moyen dans `examen.php`

## 🟡 P2 — Stabilité (prochaines 2 semaines)
- [ ] Corriger la génération d’UUIDs (remplacer `mt_rand()` par `random_bytes()`)
- [ ] Corriger le bug `lastInsertId` + UUID dans les seeds
- [ ] Corriger la fonction `matiere_icon()` (HTML/style)
- [ ] Ajouter les index SQL recommandés (voir rapport)
- [ ] Ajouter les headers HTTP de sécurité
- [ ] Ajouter la pagination dans les listes admin
- [ ] Créer la page profil utilisateur (nom, email, mot de passe)

## 🟢 P3 — Backlog (améliorations)
- [ ] Vérification d’email à l’inscription
- [ ] Export CSV admin (utilisateurs, paiements)
- [ ] Gestion des remboursements
- [ ] Compteur d’examens restants visible dans le dashboard
- [ ] Cache des questions de la banque
- [ ] Leaderboard / classement
- [ ] Rappels email d’inactivité
- [ ] Centraliser la gestion d’erreurs PHP
