# Audit admin/index.php — RÉUSSITE+

## Problèmes et améliorations à corriger

### 1. Sécurité & Accès
- [ ] Vérifier que `require_admin()` protège bien toutes les routes (pas de contournement possible)
- [ ] Éviter l'affichage d'informations sensibles dans les messages d'erreur ou via GET
- [ ] Protéger les actions critiques (paiements, export) par confirmation et CSRF token

### 2. SQL & Données
- [ ] Utiliser des alias SQL cohérents (`as n` → plus explicite)
- [ ] Vérifier la robustesse des requêtes (gestion NULL, erreurs SQL, injection)
- [ ] Ajouter des index sur les colonnes filtrées fréquemment (ex: `created_at`, `statut`)
- [ ] Limiter les requêtes lourdes (ex: COUNT(*) sur grosses tables)

### 3. Code PHP
- [ ] Factoriser les accès à la BDD (éviter la répétition de `dbRow`, `dbAll`)
- [ ] Centraliser les couleurs/plans dans un fichier de config (éviter la duplication)
- [ ] Utiliser des helpers pour le formatage des dates, montants, etc.
- [ ] Gérer les cas où les données sont absentes (ex: pas d'inscrits, pas de paiements)
- [ ] Séparer la logique métier et la vue (MVC ou au moins des includes dédiés)

### 4. Frontend (HTML/CSS/JS)
- [ ] Rendre le dashboard 100% responsive (vérifier sur mobile/tablette)
- [ ] Réduire la densité d'informations sur la page d'accueil admin (trop de KPIs ?)
- [ ] Ajouter des tooltips explicatifs sur les indicateurs
- [ ] Améliorer l'accessibilité (contraste, aria-labels, navigation clavier)
- [ ] Charger les icônes Lucide uniquement si nécessaire
- [ ] Optimiser le CSS (mutualiser les classes, éviter le style inline)
- [ ] Ajouter un loader global pour les actions asynchrones (ex: IA)

### 5. UX & Fonctionnalités
- [ ] Ajouter une pagination sur les tableaux (paiements, inscrits, messages)
- [ ] Permettre la recherche/filtrage sur les utilisateurs, paiements, archives
- [ ] Ajouter des exports (CSV, Excel) pour toutes les données clés
- [ ] Afficher des alertes claires en cas d'erreur (ex: IA indisponible)
- [ ] Historiser les actions admin (logs, audit trail)
- [ ] Ajouter des confirmations pour les actions destructives

### 6. Performances
- [ ] Charger les données lourdes (ex: stats, messages) en AJAX pour accélérer le rendu initial
- [ ] Mettre en cache les statistiques globales (si possible)
- [ ] Limiter le nombre d'éléments affichés par défaut (ex: 5-10 derniers inscrits)

### 7. Divers
- [ ] Traduire toutes les chaînes en français (pas de mix anglais/français)
- [ ] Ajouter des tests automatisés pour les routes admin
- [ ] Documenter le code et les endpoints critiques

---

## TODO LISTE PRIORITAIRE

- [ ] Sécuriser toutes les actions critiques (CSRF, confirmation)
- [ ] Rendre le dashboard admin responsive et accessible
- [ ] Ajouter pagination/recherche sur les tableaux
- [ ] Optimiser les requêtes SQL et ajouter des index
- [ ] Factoriser le code PHP (helpers, config)
- [ ] Améliorer l'expérience utilisateur (tooltips, alertes, loaders)
- [ ] Mettre en place un système de logs admin
- [ ] Documenter le code et les endpoints

---

> Ce fichier doit être mis à jour à chaque évolution majeure de l'admin.
