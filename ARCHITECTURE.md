# RÉUSSITE+ — Architecture Technique
## Plateforme EdTech · République Démocratique du Congo

---

## Table des matières

1. [Vue d'ensemble](#1-vue-densemble)
2. [Stack technique](#2-stack-technique)
3. [Structure du projet](#3-structure-du-projet)
4. [Base de données](#4-base-de-données)
5. [Couche applicative PHP](#5-couche-applicative-php)
6. [Système de monétisation](#6-système-de-monétisation)
7. [Sécurité](#7-sécurité)
8. [API interne](#8-api-interne)
9. [Design system](#9-design-system)
10. [Guide de démarrage](#10-guide-de-démarrage)
11. [Comptes de démonstration](#11-comptes-de-démonstration)

---

## 1. Vue d'ensemble

RÉUSSITE+ est une plateforme d'apprentissage et de préparation aux examens officiels congolais (ENAFEP, TENASOSP, Examen d'État). Elle cible les élèves du primaire et du secondaire de la RDC, avec un modèle **freemium** basé sur Mobile Money.

```
┌─────────────────────────────────────────────────────────────────┐
│                       RÉUSSITE+                                  │
├──────────────────┬──────────────────┬───────────────────────────┤
│    FRONTEND      │    BACKEND       │    PERSISTANCE            │
│                  │                  │                           │
│  HTML5 + CSS3    │  PHP 8.2         │  MariaDB 10.4             │
│  Vanilla JS      │  Sessions PHP    │  15 tables                │
│  Design system   │  PDO / MySQL     │  FULLTEXT indexes         │
│  CSS Variables   │  MPA (Multi-page)│  UUID primary keys        │
└──────────────────┴──────────────────┴───────────────────────────┘
```

### Principes de conception

| Principe | Implémentation |
|----------|----------------|
| **Simplicité opérationnelle** | XAMPP — déployable sans cloud |
| **Sécurité by default** | CSRF, PDO prepared statements, BCRYPT |
| **Freemium native** | Plan vérifié à chaque route critique |
| **Accessibilité réseau** | Pas de dépendances CDN externes |
| **Identité RDC** | Couleurs nationales, provinces, types d'examens locaux |

---

## 2. Stack technique

### Environnement

| Composant | Technologie | Version |
|-----------|-------------|---------|
| Serveur web | Apache (XAMPP) | 2.4 |
| Langage backend | PHP | 8.2.12 |
| Base de données | MariaDB | 10.4.32 |
| Frontend | HTML5 / CSS3 / Vanilla JS | — |
| Typographies | Syne (titres) + DM Sans (corps) | Google Fonts |

### Dépendances

Aucune dépendance externe — le projet est **100 % autonome** :

- Pas de Composer / pas de framework PHP
- Pas de npm / Node.js
- Pas de framework CSS (Tailwind, Bootstrap)
- Pas de bibliothèque JS (React, Vue, jQuery)

---

## 3. Structure du projet

```
reussiteplus/
│
├── index.php                   # Landing page publique + pricing
├── connexion.php               # Authentification
├── inscription.php             # Inscription + système de référral
├── deconnexion.php             # Déconnexion (détruit la session)
│
├── dashboard.php               # Tableau de bord étudiant
├── archives.php                # Navigateur d'archives (liste + détail)
├── examen.php                  # Moteur d'examen interactif
├── resultat.php                # Résultats + correction par question
├── progression.php             # Statistiques et historique
├── questions.php               # Banque de questions QCM
├── notifications.php           # Centre de notifications
│
├── tarifs.php                  # Comparateur de plans
├── paiement.php                # Formulaire de paiement Mobile Money
├── abonnement.php              # Gestion abonnement + historique
│
├── admin/
│   ├── index.php               # Dashboard admin (stats + KPIs)
│   ├── users.php               # Gestion utilisateurs
│   ├── paiements.php           # Validation des paiements
│   └── archives.php            # CRUD archives
│
├── api/
│   ├── archives.php            # AJAX : compteur téléchargements
│   ├── signets.php             # AJAX : toggle signet
│   └── notifications.php       # AJAX : badge non lues (polling)
│
├── includes/
│   ├── config.php              # Configuration centrale (constantes, plans)
│   ├── db.php                  # Singleton PDO + helpers SQL
│   ├── auth.php                # Authentification, sessions, rôles
│   ├── helpers.php             # Fonctions utilitaires (CSRF, flash, ...)
│   ├── header_app.php          # Template : <head> + sidebar + topbar
│   └── footer_app.php          # Template : fermeture HTML + scripts
│
├── assets/
│   ├── css/app.css             # Design system complet
│   └── js/app.js               # JavaScript global
│
├── setup_db.sql                # Schéma MySQL complet (DDL)
├── seed.php                    # Seeder données de démonstration
└── ARCHITECTURE.md             # Ce fichier
```

---

## 4. Base de données

### Relations principales

```
utilisateurs ────┬──── abonnements
                 ├──── exam_sessions ─── exam_answers ─── question_bank
                 ├──── user_progression ─── matieres
                 ├──── activite_journaliere
                 ├──── notifications
                 └──── signets

archives ────────────── matieres
question_bank ──────────┬── question_options
                        └── matieres
codes_promo ─────────── abonnements
provinces ──────────── utilisateurs
```

### Tables principales

#### `utilisateurs`

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | VARCHAR(36) | UUID v4 généré en PHP |
| `email` | VARCHAR(255) UNIQUE | Identifiant de connexion |
| `password_hash` | VARCHAR(255) | bcrypt (cost=12) |
| `role` | ENUM | ELEVE / ENSEIGNANT / ADMIN_ECOLE / MODERATEUR / SUPER_ADMIN |
| `plan` | ENUM | GRATUIT / BASIQUE / PREMIUM / ECOLE |
| `plan_expire_at` | DATE | NULL pour GRATUIT |
| `score_moyen` | DECIMAL(5,2) | Moyenne globale calculée |
| `total_examens` | INT | Compteur d'examens terminés |
| `referral_code` | VARCHAR(20) | Code unique de parrainage |
| `province_id` | VARCHAR(36) | FK → provinces |

#### `exam_sessions`

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | VARCHAR(36) | UUID v4 |
| `user_id` | VARCHAR(36) | FK → utilisateurs |
| `matiere_id` | VARCHAR(36) | FK → matieres (nullable) |
| `nb_questions` | INT | Nombre de questions de la session |
| `score` | INT | Nombre de bonnes réponses |
| `pourcentage` | DECIMAL(5,2) | Score en pourcentage |
| `temps_passe` | INT | Durée en secondes |
| `statut` | ENUM | EN_COURS / TERMINE / ABANDONNE |

#### `abonnements`

| Colonne | Type | Description |
|---------|------|-------------|
| `plan` | ENUM | BASIQUE / PREMIUM / ECOLE |
| `montant` | DECIMAL(10,2) | Montant après remise |
| `methode_paiement` | VARCHAR(50) | MPESA / AIRTEL_MONEY / ORANGE_MONEY |
| `reference_paiement` | VARCHAR(50) | Référence unique RP-XXXXXXXX |
| `statut` | ENUM | EN_ATTENTE / CONFIRME / REFUSE / EXPIRE |
| `code_promo` | VARCHAR(30) | Code promo appliqué (nullable) |
| `remise` | DECIMAL(10,2) | Montant de la remise en CDF |

### Index et performances

```sql
-- Recherche fulltext sur les archives
ALTER TABLE archives ADD FULLTEXT INDEX ft_archives (titre, description);

-- Index sur les colonnes de filtrage fréquent
CREATE INDEX idx_archives_type_annee ON archives (exam_type, annee);
CREATE INDEX idx_sessions_user_statut ON exam_sessions (user_id, statut);
CREATE INDEX idx_abonnements_statut   ON abonnements (statut);
CREATE INDEX idx_notifs_user_lu       ON notifications (user_id, lu);
```

### Conventions

- **UUID** : générés en PHP via `sprintf('%04x%04x-...')` — compatibilité MariaDB totale
- **Soft delete** : colonne `is_active TINYINT(1)` — jamais de `DELETE` sur les entités principales
- **Pas de ORM** : requêtes PDO directes via helpers `dbQuery()`, `dbRow()`, `dbAll()`

---

## 5. Couche applicative PHP

### Flux d'une requête

```
HTTP Request
     │
     ▼
[Page PHP]
     │── require includes/config.php   (constantes, session)
     │── require includes/db.php        (singleton PDO)
     │── require includes/auth.php      (current_user, require_login)
     │── require includes/helpers.php   (CSRF, flash, formatters)
     │
     ├── Vérification auth / rôle
     ├── Traitement POST (actions, CSRF verify)
     ├── Requêtes DB (dbAll / dbRow / dbInsert)
     │
     ├── include includes/header_app.php  → <html>...<main>
     ├── [Génération HTML de la page]
     └── include includes/footer_app.php  → </main>...</html>
```

### Helpers SQL (`includes/db.php`)

```php
db()                              // Retourne le singleton PDO
dbQuery($sql, $params)            // Exécute une requête (INSERT, UPDATE, DELETE)
dbRow($sql, $params)              // Retourne une seule ligne associative
dbAll($sql, $params)              // Retourne toutes les lignes
dbInsert($table, $data)           // Insert avec UUID auto-généré, retourne l'UUID
dbUpdate($table, $data, $where)   // UPDATE sécurisé par tableau de conditions
```

### Authentification (`includes/auth.php`)

```php
auth_login($email, $password)   // Vérifie BCRYPT, crée la session
auth_register($data)            // Inscription + code référral + notification
auth_logout()                   // Détruit la session, redirige
current_user()                  // Retourne le tableau utilisateur courant
is_logged()                     // Booléen
is_admin()                      // true si rôle >= MODERATEUR
require_login()                 // Redirige vers connexion.php si non authentifié
require_admin()                 // Redirige si non admin
can_take_exam($user)            // Vérifie la limite mensuelle du plan GRATUIT
refresh_user()                  // Recharge $_SESSION['user'] depuis la DB
```

### Gestion des plans

Centralisée dans `includes/config.php` :

```php
const PLANS = [
  'GRATUIT' => ['prix' => 0,     'examens_mois' => 5,   'ia' => false],
  'BASIQUE' => ['prix' => 5000,  'examens_mois' => 30,  'ia' => false],
  'PREMIUM' => ['prix' => 10000, 'examens_mois' => -1,  'ia' => true], // -1 = illimité
  'ECOLE'   => ['prix' => 50000, 'examens_mois' => -1,  'ia' => true, 'eleves_max' => 50],
];
```

Vérification à l'exécution dans `examen.php` :

```php
if (!can_take_exam($user)) {
    redirect('/reussiteplus/tarifs.php', [
        'info' => 'Limite mensuelle atteinte — passez à Premium !'
    ]);
}
```

---

## 6. Système de monétisation

### Modèle freemium

```
GRATUIT  ──→  5 examens/mois · Pas de corrigés · Pas d'explications
BASIQUE  ──→  30 examens/mois · Corrigés PDF · Résultats détaillés
PREMIUM  ──→  Illimité · Tout BASIQUE + explications IA · Plan de révision
ECOLE    ──→  PREMIUM × 50 élèves · Tableau de bord enseignant
```

### Flux de paiement Mobile Money

```
1. Choix du plan sur tarifs.php
        │
        ▼
2. Formulaire paiement.php
   ─ Méthode (M-Pesa / Airtel Money / Orange Money)
   ─ Numéro de téléphone
   ─ Durée 1 / 3 / 6 / 12 mois (remises automatiques)
   ─ Code promo (validation AJAX)
        │
        ▼
3. INSERT abonnements (statut = EN_ATTENTE)
   ─ Notification créée pour l'utilisateur
   ─ Référence unique affichée : RP-XXXXXXXX
        │
        ▼
4. Utilisateur vire via Mobile Money
   ─ Envoie capture à paiement@reussiteplus.cd
        │
        ▼
5. Admin confirme dans admin/paiements.php
   ─ statut = CONFIRME
   ─ UPDATE utilisateurs SET plan, plan_expire_at
   ─ Notification de confirmation envoyée
```

### Remises par durée

| Durée | Remise |
|-------|--------|
| 1 mois | 0 % |
| 3 mois | 5 % |
| 6 mois | 10 % |
| 12 mois | 15 % |

---

## 7. Sécurité

### Mesures implémentées

| Vecteur | Contre-mesure |
|---------|---------------|
| Injection SQL | PDO `prepare()` + `execute()` sur **toutes** les requêtes |
| XSS | `htmlspecialchars()` via helper `e()` sur toute sortie HTML |
| CSRF | Token synchronisé (`csrf_field()` + `csrf_verify()`) sur chaque formulaire POST |
| Brute force | Sessions PHP avec expiration, mots de passe BCRYPT cost=12 |
| Elevation de privilège | `require_admin()` en tête de chaque page admin |
| Isolation des données | Filtre `WHERE user_id = ?` systématique sur les données personnelles |
| Cookies de session | `HttpOnly`, `SameSite=Strict`, `Secure` (configurable) |
| Seed script | Accessible uniquement depuis `127.0.0.1` |

### Helpers de sécurité clés

```php
// Échappe toute valeur avant affichage HTML
function e(string $val): string {
    return htmlspecialchars($val, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// Token CSRF avec rotation à chaque vérification
function csrf_verify(): bool {
    $token = $_POST['csrf_token'] ?? '';
    $valid = hash_equals($_SESSION['csrf_token'] ?? '', $token);
    unset($_SESSION['csrf_token']); // invalidation immédiate
    return $valid;
}
```

---

## 8. API interne

Tous les endpoints retournent du JSON et requièrent une session active (HTTP 401 sinon).

### `POST /api/archives.php`

| Paramètre | Description |
|-----------|-------------|
| `action=download` | Incrémente `nb_telechargements` |
| `archive_id` | UUID de l'archive |

### `POST /api/signets.php`

| Paramètre | Description |
|-----------|-------------|
| `type` | `ARCHIVE` ou `QUESTION` |
| `ref_id` | UUID de la ressource |

Réponse : `{"ok": true, "added": true}` — toggle automatique.

### `GET /api/notifications.php`

Retourne le nombre de notifications non lues.  
Réponse : `{"count": 3}`  
Polling toutes les 60 secondes via `setInterval` dans `assets/js/app.js`.

---

## 9. Design system

### Palette

```css
--primary:     #007A5E;   /* Vert national RDC */
--gold:        #C9972A;   /* Or — plan Premium / CTA */
--rouge:       #DC2626;   /* Erreurs, danger */
--bleu:        #2563EB;   /* Info, liens */
--gris-900:    #111827;   /* Texte principal */
--gris-500:    #6B7280;   /* Texte secondaire */
--gris-200:    #E5E7EB;   /* Bordures */
--gris-50:     #F9FAFB;   /* Fonds de carte */
```

### Typographies

```
Syne     → Titres, logo, chiffres clés  (font-weight: 700–900)
DM Sans  → Corps du texte, labels       (font-weight: 400–600)
```

### Classes utilitaires CSS

| Classe | Usage |
|--------|-------|
| `.btn .btn-primary / .btn-gold / .btn-ghost / .btn-danger` | Boutons |
| `.btn-sm / .btn-lg / .btn-full` | Tailles et largeur |
| `.card / .card-header / .card-title` | Conteneurs |
| `.stats-grid` | Grille responsive de statistiques |
| `.stat-card.green / .gold / .bleu / .rouge` | Cartes colorées |
| `.badge-plan / .badge-difficulte` | Badges |
| `.progress-bar / .progress-bar-fill` | Barres de progression |
| `.alert .alert-info / .alert-success / .alert-error` | Messages flash |
| `.table-wrap / .table` | Tableaux responsives |
| `.form-group / .form-label / .form-control` | Formulaires |
| `.premium-lock` | Overlay de verrouillage contenu Premium |
| `.sidebar / .main-content / .topbar` | Layout de l'application |

---

## 10. Guide de démarrage

### Prérequis

- XAMPP ≥ 8.2 (PHP 8.2, MariaDB 10.4, Apache 2.4)
- Navigateur moderne (Chrome, Firefox, Edge)

### Installation

```bash
# 1. Cloner dans htdocs/
git clone https://github.com/codexripple/reussiteplus.git C:\xampp\htdocs\reussiteplus

# 2. Démarrer Apache et MySQL via le panneau XAMPP

# 3. Créer la base de données
mysql -u root -e "CREATE DATABASE reussiteplus CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 4. Importer le schéma
mysql -u root reussiteplus < setup_db.sql

# 5. Insérer les données de démonstration (localhost uniquement)
# Ouvrir dans le navigateur :
http://localhost/reussiteplus/seed.php
```

> ⚠️ **Supprimer `seed.php` avant tout déploiement en production.**

### Configuration (`includes/config.php`)

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'reussiteplus');
define('DB_USER', 'root');
define('DB_PASS', '');          // Modifier en production
define('SECRET_KEY', '...');    // Changer la valeur par défaut
define('FREE_EXAMS_PER_MONTH', 5);
define('BCRYPT_COST', 12);
```

### URLs

| Page | URL |
|------|-----|
| Accueil | `http://localhost/reussiteplus/` |
| Connexion | `http://localhost/reussiteplus/connexion.php` |
| Dashboard | `http://localhost/reussiteplus/dashboard.php` |
| Administration | `http://localhost/reussiteplus/admin/` |
| Seeder | `http://localhost/reussiteplus/seed.php` |

---

## 11. Comptes de démonstration

> Créés par `seed.php`

| Rôle | Email | Mot de passe | Plan |
|------|-------|-------------|------|
| Élève | `demo@reussiteplus.cd` | `Demo1234!` | BASIQUE |
| Super Admin | `admin@reussiteplus.cd` | `Admin2025!` | — |
| Enseignant | `prof@reussiteplus.cd` | `Prof2025!` | PREMIUM |

**Code promo de test :** `BIENVENUE2025` — 20 % de réduction, valide jusqu'au 31/12/2025.

---

## Décisions d'architecture

| Décision | Choix retenu | Justification |
|----------|-------------|---------------|
| Framework backend | PHP natif (pas de Laravel) | Déployable sur XAMPP sans Composer ni CLI |
| Framework frontend | Vanilla JS + CSS custom | Zéro dépendance, performances sur connexion lente |
| Base de données | MariaDB vs PostgreSQL | Natif XAMPP, pas de cloud requis |
| UUID | Génération PHP, pas `UUID()` MySQL | Récupération fiable après INSERT sur toutes versions MariaDB |
| Architecture | MPA vs SPA | Simplicité, SEO natif, JavaScript non requis pour la navigation |
| ORM | Aucun — PDO direct | Requêtes optimisées, lisibilité maximale, pas de surcharge |
| Paiement | Validation manuelle par admin | Adapté à Mobile Money RDC, sans API bancaire tierce |

---

*RÉUSSITE+ v1.0.0 — Documentation technique — Mai 2026*
