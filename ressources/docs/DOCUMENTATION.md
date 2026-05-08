# RÉUSSITE+ — Documentation Exhaustive du Projet v2.0.0

> **Version** : 2.0.0  
> **Date** : 2026-05-07  
> **Auteur** : Équipe ReussitePlus  
> **Stack cible** : Laravel 11 · Nuxt.js 3 · Capacitor 6 · MySQL 8.4 · Redis 7  

---

## Table des matières

| # | Section |
|---|---------|
| 1 | [Vue d'ensemble & Objectifs](#1-vue-densemble--objectifs) |
| 2 | [Architecture système](#2-architecture-système) |
| 3 | [Structure des dossiers](#3-structure-des-dossiers) |
| 4 | [Stack technique détaillée](#4-stack-technique-détaillée) |
| 5 | [Base de données — 32 tables](#5-base-de-données--32-tables) |
| 6 | [Rôles & Permissions](#6-rôles--permissions) |
| 7 | [Plans freemium & Tarification](#7-plans-freemium--tarification) |
| 8 | [Flux utilisateurs](#8-flux-utilisateurs) |
| 9 | [Modules fonctionnels](#9-modules-fonctionnels) |
| 10 | [Routes API (100+)](#10-routes-api-100) |
| 11 | [Authentification & Sécurité](#11-authentification--sécurité) |
| 12 | [Paiements Mobile Money](#12-paiements-mobile-money) |
| 13 | [Intégrations externes](#13-intégrations-externes) |
| 14 | [Module École](#14-module-école) |
| 15 | [Frontend Nuxt.js 3](#15-frontend-nuxtjs-3) |
| 16 | [Application mobile Capacitor 6](#16-application-mobile-capacitor-6) |
| 17 | [Variables d'environnement](#17-variables-denvironnement) |
| 18 | [Déploiement Nginx / Supervisor](#18-déploiement-nginx--supervisor) |
| 19 | [Roadmap & Versions](#19-roadmap--versions) |
| 20 | [Glossaire & Annexes](#20-glossaire--annexes) |

---

## 1. Vue d'ensemble & Objectifs

```
╔════════════════════════════════════════════════════════════════════════════════╗
║                                                                                ║
║   ██████╗ ███████╗██╗   ██╗███████╗███████╗██╗████████╗███████╗                ║
║   ██╔══██╗██╔════╝██║   ██║██╔════╝██╔════╝██║╚══██╔══╝██╔════╝      ██╗       ║
║   ██████╔╝█████╗  ██║   ██║███████╗███████╗██║   ██║   █████╗     ████████╗    ║
║   ██╔══██╗██╔══╝  ██║   ██║╚════██║╚════██║██║   ██║   ██╔══╝     ╚══██╔══╝    ║
║   ██║  ██║███████╗╚██████╔╝███████║███████║██║   ██║   ███████╗      ╚═╝       ║
║   ╚═╝  ╚═╝╚══════╝ ╚═════╝ ╚══════╝╚══════╝╚═╝   ╚═╝   ╚══════╝                ║
║                                                                                ║
║         Plateforme de préparation aux examens officiels RDC                    ║
║         ENAFEP · TENASOSP · Examen d'État                                      ║
╚════════════════════════════════════════════════════════════════════════════════╝
```

### 1.1 Présentation

**Réussite+** est une plateforme éducative numérique conçue pour les élèves, enseignants et
établissements scolaires de la République Démocratique du Congo.  
Elle couvre l'ensemble du parcours scolaire (primaire → secondaire) et propose :

- Des **cours** structurés par matière, niveau et programme officiel (EPSP/MINAS)
- Des **exercices interactifs** avec correction immédiate
- Des **examens de certification** (TENA, TENAFEP, EXETAT simulés)
- Un **assistant IA** (Groq/Llama3) pour l'aide aux devoirs
- Un **module École** pour la gestion complète d'un établissement
- Des **abonnements freemium** payables par Mobile Money (M-Pesa, Airtel Money) en CDF

### 1.2 Objectifs stratégiques

| Objectif | Indicateur |
|----------|-----------|
| Démocratiser l'accès à un enseignement de qualité | Disponibilité hors-ligne (PWA + APK) |
| Réduire les inégalités régionales | Couverture des 26 provinces RDC |
| Accompagner les révisions nationales | Banque de questions TENA/EXETAT officielle |
| Soutenir les établissements scolaires | Module de gestion intégré |
| Générer des revenus durables | Modèle freemium + abonnements Mobile Money |

### 1.3 Public cible

| Profil | Usage principal |
|--------|----------------|
| Élève (6–20 ans) | Cours, exercices, révisions, examens blancs |
| Enseignant | Création de contenu, suivi des classes |
| Directeur d'école | Gestion établissement, bulletins, emploi du temps |
| Parent | Suivi de la progression de l'enfant |
| Administrateur plateforme | Modération, analytics, gestion abonnements |

---

## 2. Architecture système

### 2.1 Vue macro

```
┌─────────────────────────────────────────────────────────────┐
│                    CLIENTS                                   │
│  Navigateur Web  │  App Android (APK)  │  App iOS (IPA)    │
│  Nuxt.js 3 PWA   │  Capacitor 6        │  Capacitor 6      │
└──────────────────────────┬──────────────────────────────────┘
                           │ HTTPS / REST JSON
                           │ /api/v1/
┌──────────────────────────▼──────────────────────────────────┐
│                  API BACKEND                                 │
│              Laravel 11  (PHP 8.3)                          │
│  Sanctum 4 · Eloquent · Queue · Events · Notifications      │
└────┬─────────────┬─────────────┬──────────────┬────────────┘
     │             │             │              │
┌────▼──┐   ┌─────▼──┐   ┌──────▼───┐   ┌──────▼────┐
│MySQL  │   │ Redis  │   │  Storage │   │ Queues    │
│ 8.4   │   │  7.x   │   │  (S3/   │   │ (Redis /  │
│32 tbl │   │ Cache  │   │  Local)  │   │ Horizon)  │
└───────┘   └────────┘   └──────────┘   └──────┬────┘
                                                │
                               ┌────────────────▼────────────────┐
                               │       Services externes          │
                               │  Groq API · Brevo · M-Pesa      │
                               │  Airtel Money · Firebase FCM    │
                               └─────────────────────────────────┘
```

### 2.2 Principes architecturaux

- **API-first** : le backend expose uniquement du JSON (`/api/v1/`) ; aucun HTML serveur
- **Stateless** : authentification par token Sanctum (Bearer) — pas de sessions serveur
- **Mono-repo ou multi-repo** : `backend/` (Laravel) + `frontend/` (Nuxt) + `mobile/` (Capacitor)
- **Queue-driven** : emails, notifications push, rapports IA traitées en background
- **Feature flags** : accès aux fonctionnalités contrôlé par le plan d'abonnement
- **Soft-delete** : la majorité des entités utilisent `deleted_at` (pas de suppression physique)
- **UUID** : toutes les clés primaires sont des UUID v4 (pas d'entiers auto-incrémentés)

### 2.3 Sécurité en couches

```
1. HTTPS (TLS 1.3) — Nginx
2. Rate limiting (Laravel Throttle Middleware) — 60 req/min standard, 10/min auth
3. CORS (laravel/sanctum + config/cors.php)
4. Authentification Sanctum Bearer Token
5. Authorization Laravel Gates & Policies
6. Validation entrées (FormRequest classes)
7. Paramètres préparés Eloquent (anti SQL-injection)
8. XSS — échappement Blade/Nuxt automatique
9. CSRF — tokens SPA via /sanctum/csrf-cookie
```

---

## 3. Structure des dossiers

### 3.1 Backend Laravel

```
backend/
├── app/
│   ├── Console/
│   ├── Events/
│   │   ├── PaiementConfirme.php
│   │   ├── NouveauResultatExamen.php
│   │   └── MessageRecu.php
│   ├── Exceptions/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/
│   │   │   │   ├── AuthController.php
│   │   │   │   ├── CoursController.php
│   │   │   │   ├── ExerciceController.php
│   │   │   │   ├── ExamenController.php
│   │   │   │   ├── PaiementController.php
│   │   │   │   ├── AbonnementController.php
│   │   │   │   ├── IaController.php
│   │   │   │   ├── EcoleController.php
│   │   │   │   ├── NotificationController.php
│   │   │   │   └── AdminController.php
│   │   │   └── Web/
│   │   ├── Middleware/
│   │   │   ├── CheckAbonnement.php
│   │   │   ├── CheckRole.php
│   │   │   └── SetLocale.php
│   │   └── Requests/
│   ├── Jobs/
│   │   ├── EnvoyerEmailBienvenue.php
│   │   ├── GenererRapportIA.php
│   │   └── SyncPaiementMobile.php
│   ├── Listeners/
│   ├── Models/
│   │   ├── Utilisateur.php
│   │   ├── Cours.php
│   │   ├── Exercice.php
│   │   ├── Question.php
│   │   ├── Examen.php
│   │   ├── Abonnement.php
│   │   ├── Paiement.php
│   │   ├── Ecole.php
│   │   └── ... (32 modèles)
│   ├── Notifications/
│   ├── Policies/
│   └── Services/
│       ├── GroqService.php
│       ├── MPesaService.php
│       ├── AirtelMoneyService.php
│       └── BrevoMailService.php
├── bootstrap/
├── config/
│   ├── groq.php
│   ├── mpesa.php
│   ├── airtel.php
│   └── sanctum.php
├── database/
│   ├── migrations/
│   ├── seeders/
│   └── factories/
├── routes/
│   ├── api.php          # toutes les routes /api/v1/
│   └── web.php          # minimal (health check, docs)
├── storage/
└── tests/
    ├── Feature/
    └── Unit/
```

### 3.2 Frontend Nuxt.js 3

```
frontend/
├── assets/
│   ├── css/
│   │   ├── main.css      # Tailwind directives
│   │   └── variables.css
│   └── img/
├── components/
│   ├── Cours/
│   ├── Exercice/
│   ├── Examen/
│   ├── Ecole/
│   ├── Ui/               # Button, Card, Modal, Badge...
│   └── Layout/
├── composables/
│   ├── useAuth.ts
│   ├── useCours.ts
│   ├── useAbonnement.ts
│   └── useNotifications.ts
├── layouts/
│   ├── default.vue       # Sidebar + Topbar
│   ├── auth.vue          # Centré, minimal
│   └── ecole.vue         # Dashboard école
├── middleware/
│   ├── auth.ts
│   ├── guest.ts
│   └── checkPlan.ts
├── pages/
│   ├── index.vue
│   ├── connexion.vue
│   ├── inscription.vue
│   ├── dashboard.vue
│   ├── cours/
│   ├── exercices/
│   ├── examens/
│   ├── ia.vue
│   ├── profil.vue
│   ├── abonnement.vue
│   └── ecole/
├── plugins/
│   ├── axios.ts
│   └── pinia.ts
├── server/               # Nuxt server routes (proxy minimal)
├── stores/
│   ├── auth.ts
│   ├── cours.ts
│   └── ecole.ts
├── nuxt.config.ts
├── tailwind.config.ts
└── package.json
```

### 3.3 Mobile Capacitor

```
mobile/
├── android/              # Projet Android natif (Gradle)
├── ios/                  # Projet iOS natif (Xcode)
├── src/                  # Même code que frontend (symlink ou build copy)
├── capacitor.config.ts
└── package.json
```

---

## 4. Stack technique détaillée

### 4.1 Backend

| Couche | Technologie | Version | Rôle |
|--------|------------|---------|------|
| Langage | PHP | 8.3 | Runtime |
| Framework | Laravel | 11.x | API REST, ORM, Auth, Queue |
| Auth | Laravel Sanctum | 4.x | Token Bearer SPA/Mobile |
| ORM | Eloquent | (Laravel) | Modèles, relations, migrations |
| DB principale | MySQL | 8.4 | Persistence données |
| Cache / Queue | Redis | 7.x | Cache requêtes, sessions, queues |
| Queue worker | Laravel Horizon | 5.x | Surveillance des queues Redis |
| Emails | Brevo (Sendinblue) | REST v3 | Transactionnel SMTP/API |
| IA | Groq Cloud | llama3-70b-8192 | Assistant IA, correction auto |
| Storage | Laravel Storage | (local / S3) | Fichiers cours, avatars |
| Tests | Pest PHP | 2.x | Tests unitaires & fonctionnels |
| Logs | Laravel Log / Sentry | — | Monitoring erreurs |

### 4.2 Frontend / Mobile

| Couche | Technologie | Version | Rôle |
|--------|------------|---------|------|
| Framework JS | Nuxt.js | 3.x | SSR/SPA/PWA |
| UI | Vue.js | 3.x (composition) | Composants réactifs |
| CSS | Tailwind CSS | 3.x | Utility-first styling |
| State | Pinia | 2.x | Store global |
| HTTP | Axios / useFetch | — | Appels API |
| Mobile | Capacitor | 6.x | Wrapper natif Android/iOS |
| App ID | cd.reussiteplus.app | — | Identifiant Google Play / App Store |
| Push | Firebase FCM | — | Notifications mobiles |
| Offline | Service Worker (Workbox) | — | Cache hors-ligne PWA |
| Icons | Heroicons / Bootstrap Icons | — | Interface |

### 4.3 Infrastructure serveur (production)

| Service | Config |
|---------|--------|
| Serveur web | Nginx 1.26 (reverse proxy Laravel + serveur fichiers statiques Nuxt) |
| PHP-FPM | PHP 8.3-FPM (pool dédié Laravel) |
| Process manager | Supervisor (queue:work, horizon) |
| SSL | Let's Encrypt / Certbot (renouvellement auto) |
| OS | Ubuntu 22.04 LTS |
| Domaine API | api.reussiteplus.cd |
| Domaine Web | app.reussiteplus.cd |

---

## 5. Base de données — 32 tables

### 5.1 Conventions

- Toutes les PK sont `CHAR(36)` UUID v4
- `created_at`, `updated_at` sur toutes les tables
- `deleted_at` nullable pour soft-delete (tables principales)
- Charset : `utf8mb4`, Collation : `utf8mb4_unicode_ci`
- Engine : InnoDB avec FK contraintes

### 5.2 Schéma complet

#### Table `utilisateurs`
```sql
CREATE TABLE utilisateurs (
    id              CHAR(36) PRIMARY KEY,
    nom             VARCHAR(100) NOT NULL,
    prenom          VARCHAR(100) NOT NULL,
    email           VARCHAR(191) UNIQUE NOT NULL,
    telephone       VARCHAR(20) UNIQUE,
    mot_de_passe    VARCHAR(255) NOT NULL,
    role            ENUM('ELEVE','ENSEIGNANT','ADMIN_ECOLE','SUPER_ADMIN','MODERATEUR')
                    NOT NULL DEFAULT 'ELEVE',
    niveau          VARCHAR(50),                    -- ex: 5ème primaire, 4ème humanités
    province        VARCHAR(100),
    ville           VARCHAR(100),
    sexe            ENUM('M','F','AUTRE'),
    date_naissance  DATE,
    avatar          VARCHAR(500),
    ecole_id        CHAR(36),
    actif           BOOLEAN DEFAULT TRUE,
    email_verifie_a TIMESTAMP NULL,
    dernier_connexion TIMESTAMP NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_ecole (ecole_id)
);
```

#### Table `abonnements`
```sql
CREATE TABLE abonnements (
    id              CHAR(36) PRIMARY KEY,
    utilisateur_id  CHAR(36) NOT NULL,
    plan            ENUM('GRATUIT','BASIQUE','PREMIUM','ECOLE') NOT NULL,
    statut          ENUM('ACTIF','EXPIRE','SUSPENDU','ANNULE') NOT NULL DEFAULT 'ACTIF',
    date_debut      DATE NOT NULL,
    date_fin        DATE,
    prix_cdf        INT UNSIGNED NOT NULL DEFAULT 0,
    renouvellement_auto BOOLEAN DEFAULT FALSE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    INDEX idx_utilisateur (utilisateur_id),
    INDEX idx_statut (statut)
);
```

#### Table `paiements`
```sql
CREATE TABLE paiements (
    id              CHAR(36) PRIMARY KEY,
    utilisateur_id  CHAR(36) NOT NULL,
    abonnement_id   CHAR(36),
    ecole_id        CHAR(36),
    montant_cdf     INT UNSIGNED NOT NULL,
    methode         ENUM('MPESA','AIRTEL_MONEY','ORANGE_MONEY','VIREMENT','ADMIN') NOT NULL,
    statut          ENUM('EN_ATTENTE','CONFIRME','ECHEC','REMBOURSE') NOT NULL DEFAULT 'EN_ATTENTE',
    transaction_id  VARCHAR(100) UNIQUE,          -- ID opérateur mobile
    telephone       VARCHAR(20),
    description     TEXT,
    metadata        JSON,
    confirme_a      TIMESTAMP NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id),
    INDEX idx_transaction (transaction_id),
    INDEX idx_statut (statut)
);
```

#### Table `cours`
```sql
CREATE TABLE cours (
    id              CHAR(36) PRIMARY KEY,
    titre           VARCHAR(255) NOT NULL,
    description     TEXT,
    matiere         VARCHAR(100) NOT NULL,
    niveau          VARCHAR(50) NOT NULL,
    programme       ENUM('EPSP','MINAS','AUTRE') DEFAULT 'EPSP',
    contenu         LONGTEXT,                     -- HTML ou Markdown
    fichier_url     VARCHAR(500),
    video_url       VARCHAR(500),
    image_url       VARCHAR(500),
    duree_minutes   SMALLINT UNSIGNED,
    ordre           SMALLINT UNSIGNED DEFAULT 0,
    plan_requis     ENUM('GRATUIT','BASIQUE','PREMIUM') NOT NULL DEFAULT 'GRATUIT',
    enseignant_id   CHAR(36),
    ecole_id        CHAR(36),
    publie          BOOLEAN DEFAULT FALSE,
    vues            INT UNSIGNED DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      TIMESTAMP NULL,
    FULLTEXT INDEX ft_cours (titre, description),
    INDEX idx_matiere (matiere),
    INDEX idx_niveau (niveau)
);
```

#### Table `exercices`
```sql
CREATE TABLE exercices (
    id              CHAR(36) PRIMARY KEY,
    cours_id        CHAR(36),
    titre           VARCHAR(255) NOT NULL,
    description     TEXT,
    matiere         VARCHAR(100) NOT NULL,
    niveau          VARCHAR(50) NOT NULL,
    type            ENUM('QCM','VRAI_FAUX','TEXTE_LIBRE','CALCUL') NOT NULL DEFAULT 'QCM',
    difficulte      ENUM('FACILE','MOYEN','DIFFICILE') DEFAULT 'MOYEN',
    duree_minutes   SMALLINT UNSIGNED DEFAULT 30,
    plan_requis     ENUM('GRATUIT','BASIQUE','PREMIUM') NOT NULL DEFAULT 'GRATUIT',
    points_total    SMALLINT UNSIGNED DEFAULT 100,
    publie          BOOLEAN DEFAULT FALSE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      TIMESTAMP NULL,
    FOREIGN KEY (cours_id) REFERENCES cours(id) ON DELETE SET NULL
);
```

#### Table `questions`
```sql
CREATE TABLE questions (
    id              CHAR(36) PRIMARY KEY,
    exercice_id     CHAR(36) NOT NULL,
    examen_id       CHAR(36),
    enonce          TEXT NOT NULL,
    type            ENUM('QCM','VRAI_FAUX','TEXTE_LIBRE','CALCUL') NOT NULL,
    options         JSON,                         -- tableau des choix pour QCM
    reponse_correcte TEXT NOT NULL,
    explication     TEXT,
    points          TINYINT UNSIGNED DEFAULT 1,
    ordre           SMALLINT UNSIGNED DEFAULT 0,
    media_url       VARCHAR(500),
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (exercice_id) REFERENCES exercices(id) ON DELETE CASCADE
);
```

#### Table `tentatives_exercice`
```sql
CREATE TABLE tentatives_exercice (
    id              CHAR(36) PRIMARY KEY,
    utilisateur_id  CHAR(36) NOT NULL,
    exercice_id     CHAR(36) NOT NULL,
    reponses        JSON NOT NULL,                -- {question_id: reponse_donnee}
    score           DECIMAL(5,2),
    points_obtenus  SMALLINT UNSIGNED,
    points_total    SMALLINT UNSIGNED,
    duree_secondes  SMALLINT UNSIGNED,
    termine         BOOLEAN DEFAULT FALSE,
    commence_a      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    termine_a       TIMESTAMP NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (exercice_id) REFERENCES exercices(id),
    INDEX idx_user_exercice (utilisateur_id, exercice_id)
);
```

#### Table `examens`
```sql
CREATE TABLE examens (
    id              CHAR(36) PRIMARY KEY,
    titre           VARCHAR(255) NOT NULL,
    description     TEXT,
    type            ENUM('BLANC','OFFICIEL','PRATIQUE','CERTIFICATION') NOT NULL,
    matiere         VARCHAR(100),
    niveau          VARCHAR(50) NOT NULL,
    annee           YEAR,
    duree_minutes   SMALLINT UNSIGNED DEFAULT 120,
    questions_count SMALLINT UNSIGNED DEFAULT 50,
    plan_requis     ENUM('GRATUIT','BASIQUE','PREMIUM') NOT NULL DEFAULT 'BASIQUE',
    instructions    TEXT,
    publie          BOOLEAN DEFAULT FALSE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      TIMESTAMP NULL
);
```

#### Table `resultats_examens`
```sql
CREATE TABLE resultats_examens (
    id              CHAR(36) PRIMARY KEY,
    utilisateur_id  CHAR(36) NOT NULL,
    examen_id       CHAR(36) NOT NULL,
    reponses        JSON NOT NULL,
    score           DECIMAL(5,2),
    mention         ENUM('ECHEC','PASSABLE','ASSEZ_BIEN','BIEN','TRES_BIEN','EXCELLENT'),
    classement      INT UNSIGNED,
    duree_secondes  SMALLINT UNSIGNED,
    certificat_url  VARCHAR(500),
    commence_a      TIMESTAMP NOT NULL,
    termine_a       TIMESTAMP NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (examen_id) REFERENCES examens(id)
);
```

#### Table `progressions`
```sql
CREATE TABLE progressions (
    id              CHAR(36) PRIMARY KEY,
    utilisateur_id  CHAR(36) NOT NULL,
    cours_id        CHAR(36),
    exercice_id     CHAR(36),
    type            ENUM('COURS','EXERCICE') NOT NULL,
    progression_pct TINYINT UNSIGNED DEFAULT 0,   -- 0–100
    termine         BOOLEAN DEFAULT FALSE,
    derniere_page   SMALLINT UNSIGNED,
    temps_total_s   INT UNSIGNED DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_cours (utilisateur_id, cours_id),
    UNIQUE KEY uk_user_exercice (utilisateur_id, exercice_id)
);
```

#### Table `ecoles`
```sql
CREATE TABLE ecoles (
    id              CHAR(36) PRIMARY KEY,
    nom             VARCHAR(255) NOT NULL,
    code_ecole      VARCHAR(50) UNIQUE,
    type            ENUM('PRIMAIRE','SECONDAIRE','MIXTE') DEFAULT 'MIXTE',
    province        VARCHAR(100) NOT NULL,
    ville           VARCHAR(100) NOT NULL,
    adresse         TEXT,
    telephone       VARCHAR(20),
    email           VARCHAR(191),
    logo_url        VARCHAR(500),
    admin_id        CHAR(36),
    abonnement_actif BOOLEAN DEFAULT FALSE,
    plan            ENUM('BASIQUE_ECOLE','PREMIUM_ECOLE') DEFAULT 'BASIQUE_ECOLE',
    max_eleves      INT UNSIGNED DEFAULT 500,
    actif           BOOLEAN DEFAULT TRUE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      TIMESTAMP NULL
);
```

#### Table `classes_ecole`
```sql
CREATE TABLE classes_ecole (
    id              CHAR(36) PRIMARY KEY,
    ecole_id        CHAR(36) NOT NULL,
    nom             VARCHAR(100) NOT NULL,          -- ex: 6ème A
    niveau          VARCHAR(50) NOT NULL,
    annee_scolaire  VARCHAR(9) NOT NULL,            -- ex: 2025-2026
    titulaire_id    CHAR(36),
    max_eleves      TINYINT UNSIGNED DEFAULT 60,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ecole_id) REFERENCES ecoles(id) ON DELETE CASCADE
);
```

#### Table `inscriptions_classes`
```sql
CREATE TABLE inscriptions_classes (
    id              CHAR(36) PRIMARY KEY,
    eleve_id        CHAR(36) NOT NULL,
    classe_id       CHAR(36) NOT NULL,
    annee_scolaire  VARCHAR(9) NOT NULL,
    statut          ENUM('ACTIF','DIPLOME','TRANSFÈRE','ABANDON') DEFAULT 'ACTIF',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_eleve_classe_annee (eleve_id, classe_id, annee_scolaire),
    FOREIGN KEY (eleve_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (classe_id) REFERENCES classes_ecole(id)
);
```

#### Table `emplois_temps`
```sql
CREATE TABLE emplois_temps (
    id              CHAR(36) PRIMARY KEY,
    classe_id       CHAR(36) NOT NULL,
    jour            ENUM('LUNDI','MARDI','MERCREDI','JEUDI','VENDREDI','SAMEDI') NOT NULL,
    heure_debut     TIME NOT NULL,
    heure_fin       TIME NOT NULL,
    matiere         VARCHAR(100) NOT NULL,
    enseignant_id   CHAR(36),
    salle           VARCHAR(50),
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (classe_id) REFERENCES classes_ecole(id)
);
```

#### Table `absences`
```sql
CREATE TABLE absences (
    id              CHAR(36) PRIMARY KEY,
    eleve_id        CHAR(36) NOT NULL,
    classe_id       CHAR(36) NOT NULL,
    date_absence    DATE NOT NULL,
    matiere         VARCHAR(100),
    type            ENUM('INJUSTIFIEE','JUSTIFIEE','RETARD') DEFAULT 'INJUSTIFIEE',
    motif           TEXT,
    justificatif_url VARCHAR(500),
    saisi_par       CHAR(36),
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (eleve_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (classe_id) REFERENCES classes_ecole(id)
);
```

#### Table `notes`
```sql
CREATE TABLE notes (
    id              CHAR(36) PRIMARY KEY,
    eleve_id        CHAR(36) NOT NULL,
    classe_id       CHAR(36) NOT NULL,
    matiere         VARCHAR(100) NOT NULL,
    periode         ENUM('TRIMESTRE1','TRIMESTRE2','TRIMESTRE3','EXAMEN_ANNUEL') NOT NULL,
    note            DECIMAL(5,2) NOT NULL,
    note_max        DECIMAL(5,2) DEFAULT 100,
    commentaire     TEXT,
    enseignant_id   CHAR(36),
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (eleve_id) REFERENCES utilisateurs(id),
    FOREIGN KEY (classe_id) REFERENCES classes_ecole(id)
);
```

#### Table `devoirs`
```sql
CREATE TABLE devoirs (
    id              CHAR(36) PRIMARY KEY,
    classe_id       CHAR(36) NOT NULL,
    enseignant_id   CHAR(36) NOT NULL,
    titre           VARCHAR(255) NOT NULL,
    description     TEXT,
    matiere         VARCHAR(100) NOT NULL,
    date_limite     DATETIME NOT NULL,
    fichier_url     VARCHAR(500),
    points          TINYINT UNSIGNED DEFAULT 10,
    publie          BOOLEAN DEFAULT FALSE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (classe_id) REFERENCES classes_ecole(id)
);
```

#### Table `remises_devoirs`
```sql
CREATE TABLE remises_devoirs (
    id              CHAR(36) PRIMARY KEY,
    devoir_id       CHAR(36) NOT NULL,
    eleve_id        CHAR(36) NOT NULL,
    commentaire     TEXT,
    fichier_url     VARCHAR(500),
    note            DECIMAL(5,2),
    feedback        TEXT,
    statut          ENUM('SOUMIS','NOTE','RETARD','NON_RENDU') DEFAULT 'SOUMIS',
    soumis_a        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_devoir_eleve (devoir_id, eleve_id),
    FOREIGN KEY (devoir_id) REFERENCES devoirs(id),
    FOREIGN KEY (eleve_id) REFERENCES utilisateurs(id)
);
```

#### Table `messages`
```sql
CREATE TABLE messages (
    id              CHAR(36) PRIMARY KEY,
    expediteur_id   CHAR(36) NOT NULL,
    destinataire_id CHAR(36),
    classe_id       CHAR(36),                     -- si message de classe
    ecole_id        CHAR(36),                     -- si annonce école
    contenu         TEXT NOT NULL,
    type            ENUM('DIRECT','CLASSE','ANNONCE','SYSTEME') DEFAULT 'DIRECT',
    lu              BOOLEAN DEFAULT FALSE,
    lu_a            TIMESTAMP NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      TIMESTAMP NULL,
    FOREIGN KEY (expediteur_id) REFERENCES utilisateurs(id),
    INDEX idx_destinataire (destinataire_id),
    INDEX idx_classe (classe_id)
);
```

#### Table `notifications`
```sql
CREATE TABLE notifications (
    id              CHAR(36) PRIMARY KEY,
    utilisateur_id  CHAR(36) NOT NULL,
    type            VARCHAR(100) NOT NULL,
    titre           VARCHAR(255) NOT NULL,
    message         TEXT NOT NULL,
    lien            VARCHAR(500),
    icone           VARCHAR(100),
    lu              BOOLEAN DEFAULT FALSE,
    lu_a            TIMESTAMP NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    INDEX idx_utilisateur_lu (utilisateur_id, lu)
);
```

#### Table `conversations_ia`
```sql
CREATE TABLE conversations_ia (
    id              CHAR(36) PRIMARY KEY,
    utilisateur_id  CHAR(36) NOT NULL,
    titre           VARCHAR(255),
    contexte        ENUM('COURS','EXERCICE','EXAMEN','GENERAL') DEFAULT 'GENERAL',
    reference_id    CHAR(36),                     -- cours_id ou exercice_id
    messages        JSON NOT NULL,                -- [{role, content, timestamp}]
    tokens_utilises INT UNSIGNED DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
);
```

#### Table `bibliotheque`
```sql
CREATE TABLE bibliotheque (
    id              CHAR(36) PRIMARY KEY,
    titre           VARCHAR(255) NOT NULL,
    auteur          VARCHAR(255),
    description     TEXT,
    matiere         VARCHAR(100),
    niveau          VARCHAR(50),
    type            ENUM('LIVRE','MANUEL','EXERCICES','REFERENCE','AUTRE') DEFAULT 'LIVRE',
    fichier_url     VARCHAR(500),
    couverture_url  VARCHAR(500),
    taille_mb       DECIMAL(8,2),
    telechargements INT UNSIGNED DEFAULT 0,
    gratuit         BOOLEAN DEFAULT TRUE,
    ecole_id        CHAR(36),                     -- null = bibliothèque globale
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      TIMESTAMP NULL
);
```

#### Table `signets`
```sql
CREATE TABLE signets (
    id              CHAR(36) PRIMARY KEY,
    utilisateur_id  CHAR(36) NOT NULL,
    type            ENUM('COURS','EXERCICE','EXAMEN','BIBLIOTHEQUE') NOT NULL,
    reference_id    CHAR(36) NOT NULL,
    note_personnelle TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_ref (utilisateur_id, type, reference_id),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
);
```

#### Table `codes_promo`
```sql
CREATE TABLE codes_promo (
    id              CHAR(36) PRIMARY KEY,
    code            VARCHAR(50) UNIQUE NOT NULL,
    description     VARCHAR(255),
    type_reduction  ENUM('POURCENTAGE','MONTANT_FIXE','GRATUIT') NOT NULL,
    valeur          DECIMAL(10,2) NOT NULL,
    plan_applicable ENUM('BASIQUE','PREMIUM','ECOLE','TOUS') DEFAULT 'TOUS',
    utilisations_max INT UNSIGNED,
    utilisations    INT UNSIGNED DEFAULT 0,
    valide_du       DATETIME,
    valide_jusqu_a  DATETIME,
    actif           BOOLEAN DEFAULT TRUE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### Table `utilisations_codes_promo`
```sql
CREATE TABLE utilisations_codes_promo (
    id              CHAR(36) PRIMARY KEY,
    code_promo_id   CHAR(36) NOT NULL,
    utilisateur_id  CHAR(36) NOT NULL,
    paiement_id     CHAR(36),
    reduction_cdf   INT UNSIGNED NOT NULL,
    utilise_a       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (code_promo_id) REFERENCES codes_promo(id),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
);
```

#### Table `actualites`
```sql
CREATE TABLE actualites (
    id              CHAR(36) PRIMARY KEY,
    titre           VARCHAR(255) NOT NULL,
    contenu         LONGTEXT NOT NULL,
    resume          TEXT,
    image_url       VARCHAR(500),
    categorie       ENUM('EDUCATION','EXAMENS','PLATEFORME','SCOLAIRE','AUTRE') DEFAULT 'EDUCATION',
    auteur_id       CHAR(36),
    ecole_id        CHAR(36),
    publie          BOOLEAN DEFAULT FALSE,
    publie_a        TIMESTAMP NULL,
    vues            INT UNSIGNED DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      TIMESTAMP NULL
);
```

#### Table `certificats`
```sql
CREATE TABLE certificats (
    id              CHAR(36) PRIMARY KEY,
    utilisateur_id  CHAR(36) NOT NULL,
    examen_id       CHAR(36),
    type            ENUM('EXAMEN','COURS','PARCOURS') NOT NULL,
    reference_id    CHAR(36),
    code_verification VARCHAR(50) UNIQUE NOT NULL,
    score           DECIMAL(5,2),
    mention         VARCHAR(50),
    fichier_url     VARCHAR(500),
    delivre_a       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    valide_jusqu_a  DATE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id),
    INDEX idx_code (code_verification)
);
```

#### Table `rapports`
```sql
CREATE TABLE rapports (
    id              CHAR(36) PRIMARY KEY,
    utilisateur_id  CHAR(36),
    ecole_id        CHAR(36),
    type            ENUM('PROGRESSION','EXAMEN','BULLETIN','PRESENCE','FINANCIER') NOT NULL,
    periode         VARCHAR(50),
    donnees         JSON NOT NULL,
    fichier_url     VARCHAR(500),
    genere_par_ia   BOOLEAN DEFAULT FALSE,
    genere_a        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### Table `sessions_revision`
```sql
CREATE TABLE sessions_revision (
    id              CHAR(36) PRIMARY KEY,
    utilisateur_id  CHAR(36) NOT NULL,
    matiere         VARCHAR(100) NOT NULL,
    niveau          VARCHAR(50),
    questions       JSON NOT NULL,                -- IDs questions générées
    reponses        JSON,
    score           DECIMAL(5,2),
    duree_secondes  INT UNSIGNED,
    termine         BOOLEAN DEFAULT FALSE,
    commence_a      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    termine_a       TIMESTAMP NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
);
```

#### Table `archives`
```sql
CREATE TABLE archives (
    id              CHAR(36) PRIMARY KEY,
    type            ENUM('EXAMEN','COURS','BIBLIOTHEQUE') NOT NULL,
    reference_id    CHAR(36) NOT NULL,
    annee           YEAR NOT NULL,
    matiere         VARCHAR(100),
    niveau          VARCHAR(50),
    description     TEXT,
    fichier_url     VARCHAR(500),
    telechargements INT UNSIGNED DEFAULT 0,
    publie          BOOLEAN DEFAULT TRUE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### Table `password_reset_tokens`
```sql
CREATE TABLE password_reset_tokens (
    email           VARCHAR(191) NOT NULL,
    token           VARCHAR(255) NOT NULL,
    created_at      TIMESTAMP NULL,
    PRIMARY KEY (email)
);
```

#### Table `personal_access_tokens` (Sanctum)
```sql
CREATE TABLE personal_access_tokens (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tokenable_type  VARCHAR(255) NOT NULL,
    tokenable_id    CHAR(36) NOT NULL,
    name            VARCHAR(255) NOT NULL,
    token           VARCHAR(64) UNIQUE NOT NULL,
    abilities       TEXT,
    last_used_at    TIMESTAMP NULL,
    expires_at      TIMESTAMP NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    INDEX idx_tokenable (tokenable_type, tokenable_id)
);
```

#### Table `jobs` (Queue Laravel)
```sql
CREATE TABLE jobs (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue           VARCHAR(255) NOT NULL,
    payload         LONGTEXT NOT NULL,
    attempts        TINYINT UNSIGNED NOT NULL,
    reserved_at     INT UNSIGNED NULL,
    available_at    INT UNSIGNED NOT NULL,
    created_at      INT UNSIGNED NOT NULL,
    INDEX idx_queue_reserved_available (queue, reserved_at, available_at)
);
```

#### Table `failed_jobs`
```sql
CREATE TABLE failed_jobs (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid            VARCHAR(255) UNIQUE NOT NULL,
    connection      TEXT NOT NULL,
    queue           TEXT NOT NULL,
    payload         LONGTEXT NOT NULL,
    exception       LONGTEXT NOT NULL,
    failed_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### 5.3 Récapitulatif des 32 tables

| # | Table | Description |
|---|-------|-------------|
| 1 | `utilisateurs` | Comptes utilisateurs (tous rôles) |
| 2 | `abonnements` | Plans actifs par utilisateur |
| 3 | `paiements` | Transactions Mobile Money |
| 4 | `cours` | Contenu pédagogique |
| 5 | `exercices` | Ensembles de questions |
| 6 | `questions` | Questions individuelles |
| 7 | `tentatives_exercice` | Sessions de travail élève |
| 8 | `examens` | Examens blancs / officiels |
| 9 | `resultats_examens` | Résultats d'examens |
| 10 | `progressions` | Suivi avancement cours/exercices |
| 11 | `ecoles` | Établissements scolaires |
| 12 | `classes_ecole` | Classes d'un établissement |
| 13 | `inscriptions_classes` | Élèves inscrits dans des classes |
| 14 | `emplois_temps` | Horaires de cours |
| 15 | `absences` | Suivi des absences |
| 16 | `notes` | Bulletins scolaires |
| 17 | `devoirs` | Devoirs assignés |
| 18 | `remises_devoirs` | Soumissions des élèves |
| 19 | `messages` | Messagerie interne |
| 20 | `notifications` | Notifications système |
| 21 | `conversations_ia` | Historique assistant IA |
| 22 | `bibliotheque` | Ressources documentaires |
| 23 | `signets` | Favoris utilisateur |
| 24 | `codes_promo` | Codes de réduction |
| 25 | `utilisations_codes_promo` | Utilisation des codes |
| 26 | `actualites` | Articles / annonces |
| 27 | `certificats` | Certificats de réussite |
| 28 | `rapports` | Rapports générés |
| 29 | `sessions_revision` | Révisions intelligentes |
| 30 | `archives` | Anciens sujets / documents |
| 31 | `password_reset_tokens` | Réinitialisation mot de passe |
| 32 | `personal_access_tokens` | Tokens Sanctum |

> Tables techniques supplémentaires : `jobs`, `failed_jobs`, `migrations`, `cache`

---

## 6. Rôles & Permissions

### 6.1 Matrice des rôles

| Rôle | Code | Description |
|------|------|-------------|
| Élève | `ELEVE` | Utilisateur apprenant (défaut) |
| Enseignant | `ENSEIGNANT` | Crée du contenu, suit ses classes |
| Admin École | `ADMIN_ECOLE` | Gère un établissement complet |
| Modérateur | `MODERATEUR` | Modère contenu + utilisateurs |
| Super Admin | `SUPER_ADMIN` | Accès total à la plateforme |

### 6.2 Permissions détaillées

| Fonctionnalité | ELEVE | ENSEIGNANT | ADMIN_ECOLE | MODERATEUR | SUPER_ADMIN |
|---------------|-------|-----------|------------|-----------|------------|
| Voir cours gratuits | ✅ | ✅ | ✅ | ✅ | ✅ |
| Voir cours BASIQUE | 🔒 Basique | ✅ | ✅ | ✅ | ✅ |
| Voir cours PREMIUM | 🔒 Premium | ✅ | ✅ | ✅ | ✅ |
| Faire exercices | ✅ | ✅ | ✅ | ✅ | ✅ |
| Passer examens | 🔒 Basique | ✅ | ✅ | ✅ | ✅ |
| Utiliser IA | 🔒 Premium | ✅ | ✅ | ✅ | ✅ |
| Créer cours | ❌ | ✅ | ✅ | ✅ | ✅ |
| Créer exercices | ❌ | ✅ | ✅ | ✅ | ✅ |
| Gérer sa classe | ❌ | ✅ (propre) | ✅ | ❌ | ✅ |
| Gérer école complète | ❌ | ❌ | ✅ | ❌ | ✅ |
| Modérer contenu | ❌ | ❌ | ❌ | ✅ | ✅ |
| Dashboard admin | ❌ | ❌ | ❌ | ✅ | ✅ |
| Gérer abonnements | ❌ | ❌ | ❌ | ❌ | ✅ |
| Voir stats globales | ❌ | ❌ | 🏫 École | ✅ | ✅ |

### 6.3 Middleware de contrôle d'accès

```php
// routes/api.php
Route::middleware(['auth:sanctum', 'role:SUPER_ADMIN,MODERATEUR'])->group(function () {
    Route::get('/admin/stats', [AdminController::class, 'stats']);
});

Route::middleware(['auth:sanctum', 'check.abonnement:PREMIUM'])->group(function () {
    Route::post('/ia/chat', [IaController::class, 'chat']);
});
```

---

## 7. Plans freemium & Tarification

### 7.1 Plans disponibles

| Plan | Prix mensuel | Cible | Fonctionnalités |
|------|-------------|-------|----------------|
| **GRATUIT** | 0 CDF | Tous | Cours gratuits, exercices limités, profil basique |
| **BASIQUE** | 3 000 CDF | Élèves | Tous les cours, examens blancs, progression, messagerie |
| **PREMIUM** | 7 500 CDF | Élèves avancés | Tout Basique + IA illimitée, rapports détaillés, certification |
| **ECOLE** | Sur devis | Établissements | Module école complet + abonnements élèves inclus |

### 7.2 Limites du plan GRATUIT

| Ressource | Limite |
|-----------|--------|
| Cours accessibles | 10 cours gratuits seulement |
| Exercices par jour | 5 exercices/jour |
| Questions IA | 0 (désactivé) |
| Examens blancs | 0 (désactivé) |
| Téléchargements bibliothèque | 3/mois |
| Historique progression | 30 jours |

### 7.3 Avantages PREMIUM

- IA Groq illimitée (contexte cours/examen)
- Tous les cours et exercices
- Examens blancs avec correction IA
- Rapports de progression mensuels auto-générés
- Téléchargement illimité bibliothèque
- Certificats de réussite officiels
- Accès archives 5 ans EXETAT/TENA/TENAFEP

### 7.4 Règle de vérification d'abonnement

```php
// app/Http/Middleware/CheckAbonnement.php
public function handle(Request $request, Closure $next, string $planRequis)
{
    $user = $request->user();
    $planHierarchie = ['GRATUIT' => 0, 'BASIQUE' => 1, 'PREMIUM' => 2, 'ECOLE' => 3];

    $planActuel = $user->abonnementActif?->plan ?? 'GRATUIT';

    if ($planHierarchie[$planActuel] < $planHierarchie[$planRequis]) {
        return response()->json([
            'message' => 'Abonnement insuffisant',
            'plan_requis' => $planRequis,
            'plan_actuel' => $planActuel,
            'lien_upgrade' => '/abonnement/upgrade'
        ], 403);
    }

    return $next($request);
}
```

---

## 8. Flux utilisateurs

### 8.1 Inscription

```
Utilisateur
    │
    ├─► POST /api/v1/auth/register
    │       { nom, prenom, email, telephone, mot_de_passe, role, niveau, province }
    │
    ├─► Validation FormRequest (unicité email/tel, complexité MDP)
    │
    ├─► Création utilisateur + abonnement GRATUIT auto
    │
    ├─► Job: EnvoyerEmailBienvenue (Brevo SMTP)
    │
    └─► Retour: { token: "sanctum_token", utilisateur: {...} }
```

### 8.2 Connexion

```
POST /api/v1/auth/login { email, mot_de_passe }
    │
    ├─► Vérification credentials (Hash::check)
    ├─► Vérification compte actif + email vérifié
    ├─► createToken('web|mobile', abilities: [...])
    ├─► Mise à jour dernier_connexion
    └─► Retour { token, utilisateur, abonnement }
```

### 8.3 Paiement d'abonnement

```
1. POST /api/v1/paiements/initier
   { plan: 'PREMIUM', methode: 'MPESA', telephone: '0812345678' }

2. API M-Pesa → STK Push envoyé sur le téléphone

3. Utilisateur confirme sur son téléphone

4. Webhook M-Pesa → POST /api/v1/paiements/webhook/mpesa
   { transaction_id, statut, montant, telephone }

5. Job: SyncPaiementMobile
   - Vérification signature webhook
   - Mise à jour paiement + création abonnement
   - Notification push + email confirmation

6. Accès débloqué immédiatement
```

### 8.4 Passage d'un exercice

```
1. GET /api/v1/exercices/{id}          → métadonnées + questions
2. POST /api/v1/exercices/{id}/commencer → crée tentative_exercice (id)
3. PUT /api/v1/tentatives/{id}/reponse  → sauvegarde réponses progressives
4. POST /api/v1/tentatives/{id}/terminer → calcul score + correction IA
5. GET /api/v1/tentatives/{id}/resultats → affichage détaillé
```

---

## 9. Modules fonctionnels

### 9.1 Module Cours

**Fonctionnalités :**
- Catalogue filtré par matière / niveau / programme / plan
- Lecture avec table des matières dynamique
- Suivi de progression automatique (temps + pages lues)
- Signets personnels
- Téléchargement PDF (plan Basique+)
- Commentaires et questions à l'enseignant

**Matières disponibles :** Français · Mathématiques · Sciences · Géographie · Histoire ·
Biologie · Chimie · Physique · Civisme · Anglais · Religion · Éducation physique

**Niveaux :** 1ère–6ème Primaire · 1ère–6ème Secondaire (options A, B, C, D)

### 9.2 Module Exercices

- Création par enseignants (QCM, Vrai/Faux, Texte libre, Calcul)
- Validation et publication par modérateurs
- Mode pratique (correction immédiate) et mode examen (correction finale)
- Statistiques de performance par question
- Suggestions IA basées sur les erreurs récurrentes

### 9.3 Module Examens

- **Examens blancs** : simulation EXETAT, TENA, TENAFEP
- **Examens officiels archivés** : sujets 2010–2025
- Minuterie intégrée avec alerte 10 minutes
- Correction automatique + explication IA (Premium)
- Classement anonyme entre pairs
- Génération certificat de réussite (score ≥ 50%)

### 9.4 Module Révision intelligente

- Algorithme de répétition espacée (SRS inspiré Anki)
- Sélection adaptative des questions selon les faiblesses
- Sessions de 10, 20 ou 30 questions
- Graphique de progression hebdomadaire

### 9.5 Module IA (Groq)

- Chat contextuel (cours actif / exercice en cours)
- Explication de concepts difficiles
- Correction de devoirs libres
- Génération de résumés de cours
- Mode "Pose une question" libre
- Limité à 20 messages/jour pour Premium (illimité enseignants)

### 9.6 Module Bibliothèque

- Manuels scolaires officiels EPSP en PDF
- Ouvrages de référence par matière
- Dictionnaires, atlas, encyclopédies
- Ressources uploadées par enseignants
- Recherche full-text

### 9.7 Module Actualités

- Articles éducatifs et annonces EPSP
- Calendrier scolaire national
- Résultats officiels EXETAT
- Alertes examens nationaux
- Annonces spécifiques à l'école (module École)

### 9.8 Module Notifications

- Notifications in-app (temps réel via polling ou WebSocket)
- Push mobile (Firebase FCM via Capacitor)
- Email transactionnel (Brevo)
- Types : Nouveau cours · Devoir dû · Résultat examen · Paiement · Message

---

## 10. Routes API (100+)

**Base URL :** `https://api.reussiteplus.cd/api/v1/`  
**Format :** JSON · **Auth :** `Authorization: Bearer {token}`

### 10.1 Authentification

```
POST   /auth/register               Inscription
POST   /auth/login                  Connexion
POST   /auth/logout                 Déconnexion (révoke token)
POST   /auth/refresh                Renouveler token
GET    /auth/me                     Profil connecté
POST   /auth/mot-de-passe/oublie    Demande reset MDP
POST   /auth/mot-de-passe/reset     Réinitialisation MDP
POST   /auth/email/verifier         Vérification email
POST   /auth/email/renvoi            Renvoi email vérification
```

### 10.2 Profil utilisateur

```
GET    /profil                      Détails profil
PUT    /profil                      Mise à jour profil
POST   /profil/avatar               Upload avatar
DELETE /profil/avatar               Supprimer avatar
GET    /profil/stats                Statistiques personnelles
GET    /profil/progression          Progression globale
```

### 10.3 Cours

```
GET    /cours                       Liste (filtres: matiere, niveau, plan, q)
GET    /cours/{id}                  Détail cours
GET    /cours/{id}/contenu          Contenu complet (vérifie plan)
POST   /cours/{id}/commencer        Démarrer session lecture
PUT    /cours/{id}/progression      Mettre à jour progression
GET    /cours/{id}/exercices        Exercices liés au cours
POST   /cours                       Créer cours (ENSEIGNANT+)
PUT    /cours/{id}                  Modifier cours (auteur / ADMIN+)
DELETE /cours/{id}                  Archiver cours (auteur / ADMIN+)
POST   /cours/{id}/publier          Publier cours (MODERATEUR+)
```

### 10.4 Exercices

```
GET    /exercices                   Liste exercices
GET    /exercices/{id}              Détail + questions
POST   /exercices/{id}/commencer    Créer tentative
GET    /tentatives/{id}             État tentative
PUT    /tentatives/{id}/reponse     Sauvegarder réponse
POST   /tentatives/{id}/terminer    Terminer + calculer score
GET    /tentatives/{id}/resultats   Résultats détaillés
GET    /mes-tentatives              Historique tentatives
POST   /exercices                   Créer exercice (ENSEIGNANT+)
PUT    /exercices/{id}              Modifier exercice
DELETE /exercices/{id}              Supprimer exercice
POST   /exercices/{id}/questions    Ajouter question
PUT    /questions/{id}              Modifier question
DELETE /questions/{id}              Supprimer question
```

### 10.5 Examens

```
GET    /examens                     Liste examens
GET    /examens/{id}                Détail examen
POST   /examens/{id}/commencer      Démarrer examen
POST   /examens/{id}/soumettre      Soumettre examen
GET    /resultats-examens/{id}      Résultat détaillé
GET    /mes-resultats               Historique résultats
GET    /examens/{id}/classement     Classement anonyme
```

### 10.6 Révision

```
POST   /revision/demarrer           Démarrer session révision
GET    /revision/{id}               Session en cours
PUT    /revision/{id}/reponse       Répondre à une question
POST   /revision/{id}/terminer      Terminer session
GET    /revision/suggestions        Questions suggérées
```

### 10.7 IA (Groq)

```
POST   /ia/chat                     Message IA (Premium)
GET    /ia/conversations             Historique conversations
GET    /ia/conversations/{id}        Conversation spécifique
DELETE /ia/conversations/{id}        Supprimer conversation
POST   /ia/resumer-cours            Résumé d'un cours
POST   /ia/corriger-devoir          Correction libre devoir
```

### 10.8 Bibliothèque

```
GET    /bibliotheque                Liste ressources
GET    /bibliotheque/{id}           Détail ressource
GET    /bibliotheque/{id}/telecharger Téléchargement (plans)
POST   /bibliotheque                Ajouter ressource (ENSEIGNANT+)
```

### 10.9 Signets & Progression

```
GET    /signets                     Mes favoris
POST   /signets                     Ajouter signet
DELETE /signets/{id}                Supprimer signet
GET    /progression                 Progression complète
GET    /progression/matiere/{m}     Progression par matière
GET    /progression/rapport         Rapport PDF progression
```

### 10.10 Notifications

```
GET    /notifications               Liste (non lues d'abord)
PUT    /notifications/{id}/lire     Marquer comme lue
POST   /notifications/tout-lire     Tout marquer lu
DELETE /notifications/{id}          Supprimer notification
POST   /notifications/token-fcm     Enregistrer token FCM
```

### 10.11 Messages

```
GET    /messages                    Conversations
GET    /messages/{utilisateur_id}   Fil de discussion
POST   /messages                    Envoyer message
DELETE /messages/{id}               Supprimer message
```

### 10.12 Actualités

```
GET    /actualites                  Liste articles
GET    /actualites/{id}             Détail article
POST   /actualites                  Créer article (MODERATEUR+)
PUT    /actualites/{id}             Modifier article
DELETE /actualites/{id}             Archiver article
```

### 10.13 Paiements & Abonnements

```
GET    /abonnement                  Abonnement actif
GET    /abonnement/plans            Plans disponibles
POST   /paiements/initier           Initier paiement Mobile Money
GET    /paiements/{id}/statut       Statut paiement
GET    /mes-paiements               Historique paiements
POST   /paiements/webhook/mpesa     Webhook M-Pesa (public)
POST   /paiements/webhook/airtel    Webhook Airtel Money (public)
POST   /codes-promo/valider         Vérifier code promo
```

### 10.14 Archives

```
GET    /archives                    Liste archives
GET    /archives?annee=2023         Filtrer par année
GET    /archives/{id}               Détail archive
GET    /archives/{id}/telecharger   Télécharger fichier
```

### 10.15 Certificats

```
GET    /certificats                 Mes certificats
GET    /certificats/{id}            Détail certificat
GET    /certificats/{id}/pdf        Télécharger PDF
GET    /certificats/verifier/{code} Vérification publique (sans auth)
```

### 10.16 Module École — Général

```
GET    /ecole                       Infos école (ADMIN_ECOLE)
PUT    /ecole                       Modifier infos école
GET    /ecole/stats                 Statistiques école
POST   /ecole/logo                  Upload logo
```

### 10.17 Module École — Classes

```
GET    /ecole/classes               Liste classes
POST   /ecole/classes               Créer classe
GET    /ecole/classes/{id}          Détail classe
PUT    /ecole/classes/{id}          Modifier classe
DELETE /ecole/classes/{id}          Supprimer classe
GET    /ecole/classes/{id}/eleves   Élèves de la classe
POST   /ecole/classes/{id}/inscrire Inscrire un élève
DELETE /ecole/classes/{id}/eleves/{eid} Désinscrire élève
```

### 10.18 Module École — Emploi du temps

```
GET    /ecole/classes/{id}/emploi-temps    Planning classe
POST   /ecole/emploi-temps               Ajouter créneau
PUT    /ecole/emploi-temps/{id}           Modifier créneau
DELETE /ecole/emploi-temps/{id}           Supprimer créneau
```

### 10.19 Module École — Absences

```
GET    /ecole/classes/{id}/absences        Absences de la classe
POST   /ecole/absences                    Enregistrer absence
PUT    /ecole/absences/{id}/justifier     Justifier absence
GET    /ecole/eleves/{id}/absences        Absences d'un élève
```

### 10.20 Module École — Notes & Bulletins

```
GET    /ecole/classes/{id}/notes          Notes de la classe
POST   /ecole/notes                       Saisir note
PUT    /ecole/notes/{id}                  Modifier note
GET    /ecole/eleves/{id}/bulletin        Bulletin élève
GET    /ecole/classes/{id}/bulletin-pdf   Bulletins PDF classe
```

### 10.21 Module École — Devoirs

```
GET    /ecole/classes/{id}/devoirs        Devoirs de la classe
POST   /ecole/devoirs                     Créer devoir
PUT    /ecole/devoirs/{id}                Modifier devoir
DELETE /ecole/devoirs/{id}                Supprimer devoir
GET    /ecole/devoirs/{id}/remises        Remises reçues
PUT    /ecole/remises/{id}/noter          Noter une remise
GET    /mes-devoirs                       Devoirs de l'élève
POST   /mes-devoirs/{id}/remettre         Soumettre devoir
```

### 10.22 Module École — Enseignants

```
GET    /ecole/enseignants                 Liste enseignants
POST   /ecole/enseignants/inviter         Inviter enseignant
DELETE /ecole/enseignants/{id}            Retirer enseignant
GET    /ecole/enseignants/{id}/cours      Cours de l'enseignant
```

### 10.23 Module École — Rapports

```
GET    /ecole/rapports                    Rapports disponibles
POST   /ecole/rapports/generer            Générer rapport
GET    /ecole/rapports/{id}/pdf           Télécharger rapport PDF
```

### 10.24 Administration (SUPER_ADMIN / MODERATEUR)

```
GET    /admin/stats                       Statistiques globales
GET    /admin/utilisateurs                Liste tous utilisateurs
GET    /admin/utilisateurs/{id}           Détail utilisateur
PUT    /admin/utilisateurs/{id}           Modifier utilisateur
PUT    /admin/utilisateurs/{id}/suspendre Suspendre compte
DELETE /admin/utilisateurs/{id}           Supprimer compte
GET    /admin/abonnements                 Tous les abonnements
GET    /admin/paiements                   Tous les paiements
GET    /admin/ecoles                      Liste écoles
PUT    /admin/ecoles/{id}/activer         Activer école
GET    /admin/codes-promo                 Gestion codes promo
POST   /admin/codes-promo                 Créer code promo
PUT    /admin/codes-promo/{id}            Modifier code promo
GET    /admin/rapports/financier          Rapport financier
GET    /admin/ia/stats                    Stats utilisation IA
POST   /admin/notifications/broadcast     Notification globale
```

---

## 11. Authentification & Sécurité

### 11.1 Laravel Sanctum 4

Sanctum est configuré pour authentifier deux types de clients :
1. **SPA** (Nuxt.js) : via cookies de session CSRF-protégés
2. **Mobile** (Capacitor) : via token Bearer dans header Authorization

```php
// config/sanctum.php
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'app.reussiteplus.cd')),
'expiration' => 60 * 24 * 30,  // 30 jours
'token_prefix' => 'rp_',
```

### 11.2 Flux d'authentification SPA

```
1. GET  /sanctum/csrf-cookie          → Set XSRF-TOKEN cookie
2. POST /api/v1/auth/login            → { token: "rp_xxx..." }
3. Stockage sécurisé token (memory store Pinia, pas localStorage)
4. Chaque requête : Authorization: Bearer rp_xxx...
5. POST /api/v1/auth/logout           → Révocation token Sanctum
```

### 11.3 Politiques de sécurité

| Aspect | Implémentation |
|--------|---------------|
| Hachage MDP | `bcrypt` (cost=12) via `Hash::make()` |
| Rate limiting auth | 10 tentatives / 15 minutes par IP |
| Rate limiting API | 60 req/min (GRATUIT), 200 req/min (PREMIUM) |
| Validation inputs | `FormRequest` avec règles strictes |
| CORS | Origines autorisées : `app.reussiteplus.cd`, localhost dev |
| Tokens expiration | 30 jours (mobile), session (web) |
| Webhook signature | HMAC-SHA256 (M-Pesa / Airtel) |

### 11.4 Validation exemple

```php
// app/Http/Requests/Auth/RegisterRequest.php
public function rules(): array
{
    return [
        'nom'           => ['required', 'string', 'max:100', 'regex:/^[\pL\s\-]+$/u'],
        'prenom'        => ['required', 'string', 'max:100', 'regex:/^[\pL\s\-]+$/u'],
        'email'         => ['required', 'email:rfc,dns', 'unique:utilisateurs,email', 'max:191'],
        'telephone'     => ['nullable', 'string', 'regex:/^(\+?243|0)[0-9]{9}$/', 'unique:utilisateurs,telephone'],
        'mot_de_passe'  => ['required', 'string', 'min:8', 'confirmed', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/'],
        'role'          => ['sometimes', Rule::in(['ELEVE', 'ENSEIGNANT'])],
        'niveau'        => ['nullable', 'string', 'max:50'],
        'province'      => ['nullable', 'string', 'max:100'],
    ];
}
```

---

## 12. Paiements Mobile Money

### 12.1 Opérateurs supportés

| Opérateur | API | Préfixes numéros | Couverture |
|-----------|-----|-----------------|-----------|
| M-Pesa (Vodacom) | M-Pesa API v2 | 081x, 082x | Kinshasa, Lubumbashi, 20 provinces |
| Airtel Money | Airtel Money API | 099x, 097x | Kinshasa, Est, Kasaï |
| Orange Money | (futur) | 084x, 085x | Prévu Q3 2026 |

**Monnaie :** Franc Congolais (CDF)  
**Pas de carte bancaire** (contexte RDC)

### 12.2 Flux M-Pesa (STK Push)

```php
// app/Services/MPesaService.php
class MPesaService
{
    public function initierPaiement(string $telephone, int $montantCdf, string $reference): array
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->post($this->baseUrl . '/mpesa/stkpush/v1/processrequest', [
                'BusinessShortCode' => config('mpesa.shortcode'),
                'Password'          => $this->generatePassword(),
                'Timestamp'         => now()->format('YmdHis'),
                'TransactionType'   => 'CustomerPayBillOnline',
                'Amount'            => $montantCdf,
                'PartyA'            => $telephone,
                'PartyB'            => config('mpesa.shortcode'),
                'PhoneNumber'       => $telephone,
                'CallBackURL'       => route('api.paiements.webhook.mpesa'),
                'AccountReference'  => $reference,
                'TransactionDesc'   => 'Abonnement ReussitePlus',
            ]);

        return $response->json();
    }

    public function verifierWebhook(Request $request): bool
    {
        $signature = $request->header('X-Mpesa-Signature');
        $computed = hash_hmac('sha256', $request->getContent(), config('mpesa.webhook_secret'));
        return hash_equals($computed, $signature);
    }
}
```

### 12.3 Webhook handler

```php
// app/Http/Controllers/Api/PaiementController.php
public function webhookMpesa(Request $request): JsonResponse
{
    if (!$this->mpesaService->verifierWebhook($request)) {
        return response()->json(['error' => 'Invalid signature'], 401);
    }

    $data = $request->json()->all();
    $transactionId = $data['Body']['stkCallback']['CheckoutRequestID'];
    $resultCode = $data['Body']['stkCallback']['ResultCode'];

    $paiement = Paiement::where('transaction_id', $transactionId)->firstOrFail();

    if ($resultCode === 0) {
        $paiement->update(['statut' => 'CONFIRME', 'confirme_a' => now()]);
        SyncPaiementMobile::dispatch($paiement);
    } else {
        $paiement->update(['statut' => 'ECHEC']);
    }

    return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
}
```

### 12.4 Tarifs en CDF

| Plan | Mensuel | Trimestriel (-10%) | Annuel (-20%) |
|------|---------|-------------------|--------------|
| BASIQUE | 3 000 CDF | 8 100 CDF | 28 800 CDF |
| PREMIUM | 7 500 CDF | 20 250 CDF | 72 000 CDF |
| ECOLE Basique | 50 000 CDF | — | 540 000 CDF |
| ECOLE Premium | 100 000 CDF | — | 1 080 000 CDF |

---

## 13. Intégrations externes

### 13.1 Groq IA (llama3-70b-8192)

```php
// app/Services/GroqService.php
class GroqService
{
    private string $model = 'llama3-70b-8192';
    private int $maxTokens = 1024;

    public function chat(array $messages, string $contexte = 'GENERAL'): string
    {
        $systemPrompt = $this->buildSystemPrompt($contexte);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('groq.api_key'),
            'Content-Type'  => 'application/json',
        ])->post('https://api.groq.com/openai/v1/chat/completions', [
            'model'       => $this->model,
            'messages'    => array_merge([
                ['role' => 'system', 'content' => $systemPrompt]
            ], $messages),
            'max_tokens'  => $this->maxTokens,
            'temperature' => 0.7,
        ]);

        return $response->json('choices.0.message.content', '');
    }

    private function buildSystemPrompt(string $contexte): string
    {
        $base = "Tu es un assistant pédagogique expert pour les élèves congolais (RDC), ";
        $base .= "du niveau primaire et secondaire. Tu suis le programme EPSP/MINAS. ";
        $base .= "Réponds en français simple et clair, adapté à l'âge de l'élève.";

        return match($contexte) {
            'COURS'    => $base . " Tu expliques le cours actuellement étudié par l'élève.",
            'EXERCICE' => $base . " Tu aides l'élève à comprendre ses erreurs dans un exercice.",
            'EXAMEN'   => $base . " Tu prépares l'élève aux examens nationaux (TENA, TENAFEP, EXETAT).",
            default    => $base,
        };
    }
}
```

**Quotas Groq :**
- `llama3-70b-8192` : 6 000 tokens/minute (plan gratuit Groq)
- Plateforme gère la queue et le throttling côté Laravel

### 13.2 Brevo (emails transactionnels)

```php
// app/Services/BrevoMailService.php
class BrevoMailService
{
    public function envoyer(string $destinataire, string $nom, string $sujet, string $htmlContent): void
    {
        Http::withHeaders([
            'api-key'      => config('services.brevo.api_key'),
            'Content-Type' => 'application/json',
        ])->post('https://api.brevo.com/v3/smtp/email', [
            'sender'      => ['name' => 'Réussite+', 'email' => 'noreply@reussiteplus.cd'],
            'to'          => [['email' => $destinataire, 'name' => $nom]],
            'subject'     => $sujet,
            'htmlContent' => $htmlContent,
        ]);
    }
}
```

**Templates emails :**
- Bienvenue à l'inscription
- Vérification email
- Réinitialisation mot de passe
- Confirmation paiement
- Résultat examen (score ≥ 70%)
- Rappel abonnement expirant (J-7, J-1)

### 13.3 Firebase Cloud Messaging (push mobile)

```php
// Notification push via FCM
public function toFcm(mixed $notifiable): array
{
    return [
        'title'   => $this->titre,
        'body'    => $this->message,
        'data'    => ['lien' => $this->lien, 'type' => $this->type],
        'android' => ['priority' => 'high', 'notification' => ['sound' => 'default']],
        'apns'    => ['payload' => ['aps' => ['sound' => 'default']]],
    ];
}
```

---

## 14. Module École

### 14.1 Description

Le module École permet à un établissement scolaire RDC d'utiliser Réussite+ comme outil
de gestion numérique complet, intégrant :

- Gestion des classes et des élèves
- Saisie des notes et génération automatique des bulletins
- Emploi du temps interactif
- Suivi des absences (justifiées/injustifiées)
- Devoirs en ligne avec remise numérique
- Messagerie interne école
- Bibliothèque numérique de l'école
- Rapports et statistiques de la direction
- Accès cours Réussite+ pour tous les élèves inscrits

### 14.2 Onboarding d'une école

```
1. SUPER_ADMIN crée l'école dans le panel admin
2. Email envoyé au directeur (ADMIN_ECOLE) avec lien activation
3. Directeur configure : nom, logo, classes, programme
4. Invitation des enseignants par email ou code d'invitation
5. Import CSV des élèves OU inscription individuelle
6. Démarrage de l'année scolaire
```

### 14.3 Génération de bulletin

```php
// app/Services/BulletinService.php
public function generer(string $eleveId, string $classeId, string $periode): array
{
    $notes = Note::where('eleve_id', $eleveId)
        ->where('classe_id', $classeId)
        ->where('periode', $periode)
        ->get();

    $matieres = $notes->groupBy('matiere')->map(function ($notesMatiere) {
        return [
            'note'    => $notesMatiere->avg('note'),
            'note_max' => $notesMatiere->first()->note_max,
            'rang'    => null, // calculé séparément
        ];
    });

    $moyenne = $matieres->avg('note');
    $mention = $this->calculerMention($moyenne);

    return [
        'eleve'     => Utilisateur::find($eleveId),
        'classe'    => ClasseEcole::find($classeId),
        'periode'   => $periode,
        'matieres'  => $matieres,
        'moyenne'   => $moyenne,
        'mention'   => $mention,
        'absences'  => $this->compterAbsences($eleveId, $classeId, $periode),
    ];
}
```

### 14.4 Rôles dans le contexte école

| Rôle | Portée dans l'école |
|------|-------------------|
| ADMIN_ECOLE | Toutes les classes, tous les enseignants, tous les élèves |
| ENSEIGNANT | Uniquement ses classes assignées |
| ELEVE | Uniquement ses cours, ses devoirs, son bulletin |

---

## 15. Frontend Nuxt.js 3

### 15.1 Configuration Nuxt

```typescript
// nuxt.config.ts
export default defineNuxtConfig({
  modules: [
    '@pinia/nuxt',
    '@nuxtjs/tailwindcss',
    '@nuxtjs/google-fonts',
    '@vite-pwa/nuxt',
    '@nuxtjs/color-mode',
  ],
  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'https://api.reussiteplus.cd/api/v1',
      appName: 'Réussite+',
    },
  },
  pwa: {
    manifest: {
      name: 'Réussite+',
      short_name: 'Réussite+',
      theme_color: '#1d4ed8',
      background_color: '#ffffff',
      display: 'standalone',
      orientation: 'portrait',
      lang: 'fr',
    },
    workbox: {
      navigateFallback: '/offline',
      globPatterns: ['**/*.{js,css,html,ico,png,svg,woff2}'],
      runtimeCaching: [
        {
          urlPattern: /^https:\/\/api\.reussiteplus\.cd\/api\/v1\/cours/,
          handler: 'NetworkFirst',
          options: { cacheName: 'cours-cache', expiration: { maxAgeSeconds: 86400 } },
        },
      ],
    },
  },
  ssr: false, // SPA mode (déployé comme fichiers statiques)
})
```

### 15.2 Store d'authentification (Pinia)

```typescript
// stores/auth.ts
export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(null)
  const utilisateur = ref<Utilisateur | null>(null)

  const estConnecte = computed(() => !!token.value)
  const planActuel = computed(() => utilisateur.value?.abonnement?.plan ?? 'GRATUIT')

  async function connexion(email: string, motDePasse: string) {
    const { data } = await useApiFetch('/auth/login', {
      method: 'POST',
      body: { email, mot_de_passe: motDePasse },
    })
    token.value = data.value?.token
    utilisateur.value = data.value?.utilisateur
  }

  function peutAcceder(planRequis: PlanEnum): boolean {
    const hierarchy = { GRATUIT: 0, BASIQUE: 1, PREMIUM: 2, ECOLE: 3 }
    return hierarchy[planActuel.value] >= hierarchy[planRequis]
  }

  return { token, utilisateur, estConnecte, planActuel, connexion, peutAcceder }
}, {
  persist: { storage: sessionStorage }, // pas localStorage (sécurité)
})
```

### 15.3 Composable API

```typescript
// composables/useApiFetch.ts
export function useApiFetch<T>(url: string, options?: UseFetchOptions<T>) {
  const { apiBase } = useRuntimeConfig().public
  const authStore = useAuthStore()

  return useFetch<T>(`${apiBase}${url}`, {
    ...options,
    headers: {
      ...options?.headers,
      Authorization: authStore.token ? `Bearer ${authStore.token}` : '',
      Accept: 'application/json',
    },
    onResponseError({ response }) {
      if (response.status === 401) {
        authStore.token = null
        navigateTo('/connexion')
      }
      if (response.status === 403 && response._data?.plan_requis) {
        navigateTo(`/abonnement/upgrade?plan=${response._data.plan_requis}`)
      }
    },
  })
}
```

### 15.4 Thème et Design system

- **Couleurs principales :** Bleu RDC (`#1d4ed8`), Vert succès (`#16a34a`), Orange avertissement (`#ea580c`)
- **Typographie :** Inter (latin), Noto Sans (caractères spéciaux)
- **Mode sombre** : supporté via `@nuxtjs/color-mode`
- **Responsive** : mobile-first (Tailwind breakpoints sm/md/lg/xl)
- **Accessibilité** : WCAG 2.1 AA (contrastes, aria-labels, focus-visible)

---

## 16. Application mobile Capacitor 6

### 16.1 Configuration

```typescript
// capacitor.config.ts
import type { CapacitorConfig } from '@capacitor/cli'

const config: CapacitorConfig = {
  appId: 'cd.reussiteplus.app',
  appName: 'Réussite+',
  webDir: 'dist',           // build Nuxt statique
  server: {
    androidScheme: 'https',
  },
  plugins: {
    PushNotifications: {
      presentationOptions: ['badge', 'sound', 'alert'],
    },
    SplashScreen: {
      launchShowDuration: 2000,
      backgroundColor: '#1d4ed8',
      showSpinner: false,
    },
    StatusBar: {
      style: 'DEFAULT',
      backgroundColor: '#1d4ed8',
    },
  },
}

export default config
```

### 16.2 Plugins Capacitor utilisés

| Plugin | Usage |
|--------|-------|
| `@capacitor/push-notifications` | Notifications Firebase FCM |
| `@capacitor/storage` | Stockage sécurisé token |
| `@capacitor/network` | Détection hors-ligne |
| `@capacitor/splash-screen` | Écran de démarrage |
| `@capacitor/status-bar` | Barre statut Android/iOS |
| `@capacitor/camera` | Photo de profil |
| `@capacitor/filesystem` | Téléchargement PDF cours |
| `@capacitor/share` | Partage de ressources |

### 16.3 Gestion hors-ligne

```typescript
// composables/useNetwork.ts
export function useNetwork() {
  const estEnLigne = ref(true)

  onMounted(async () => {
    const { Network } = await import('@capacitor/network')
    const status = await Network.getStatus()
    estEnLigne.value = status.connected

    Network.addListener('networkStatusChange', (status) => {
      estEnLigne.value = status.connected
    })
  })

  return { estEnLigne }
}
```

### 16.4 Build et déploiement

```bash
# Build Nuxt
cd frontend
npm run generate     # génère dist/

# Sync Capacitor
cd mobile
npx cap sync android
npx cap sync ios

# Build Android
npx cap open android   # → Android Studio → Generate Signed APK/AAB

# Build iOS
npx cap open ios       # → Xcode → Archive → Upload to App Store
```

**Stores :**
- Google Play : `cd.reussiteplus.app`
- Apple App Store : `cd.reussiteplus.app`

---

## 17. Variables d'environnement

### 17.1 Backend Laravel (.env)

```ini
# Application
APP_NAME="Réussite+"
APP_ENV=production
APP_KEY=base64:XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
APP_DEBUG=false
APP_URL=https://api.reussiteplus.cd

# Base de données
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=reussiteplus
DB_USERNAME=rp_user
DB_PASSWORD=XXXXXXXXXXXXXXXXXX

# Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=XXXXXXXXXXXXXXXXXX
REDIS_DB=0

# Queue
QUEUE_CONNECTION=redis
HORIZON_PREFIX=rp_

# Cache
CACHE_DRIVER=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120

# Sanctum
SANCTUM_STATEFUL_DOMAINS=app.reussiteplus.cd
SESSION_DOMAIN=.reussiteplus.cd

# CORS
CORS_ALLOWED_ORIGINS=https://app.reussiteplus.cd

# Mail (Brevo)
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=your@brevo-account.com
MAIL_PASSWORD=xsmtpib-XXXXXXXXXXXXXXXXXX
MAIL_FROM_ADDRESS=noreply@reussiteplus.cd
MAIL_FROM_NAME="Réussite+"
BREVO_API_KEY=xkeysib-XXXXXXXXXXXXXXXXXX

# Groq IA
GROQ_API_KEY=gsk_XXXXXXXXXXXXXXXXXX
GROQ_MODEL=llama3-70b-8192
GROQ_MAX_TOKENS=1024

# M-Pesa
MPESA_BASE_URL=https://api.safaricom.co.cd
MPESA_CONSUMER_KEY=XXXXXXXXXXXXXXXXXX
MPESA_CONSUMER_SECRET=XXXXXXXXXXXXXXXXXX
MPESA_SHORTCODE=123456
MPESA_PASSKEY=XXXXXXXXXXXXXXXXXX
MPESA_WEBHOOK_SECRET=XXXXXXXXXXXXXXXXXX

# Airtel Money
AIRTEL_BASE_URL=https://openapi.airtel.africa
AIRTEL_CLIENT_ID=XXXXXXXXXXXXXXXXXX
AIRTEL_CLIENT_SECRET=XXXXXXXXXXXXXXXXXX
AIRTEL_WEBHOOK_SECRET=XXXXXXXXXXXXXXXXXX

# Firebase (notifications)
FIREBASE_CREDENTIALS=storage/app/firebase-credentials.json
FIREBASE_PROJECT_ID=reussiteplus-cd

# Storage
FILESYSTEM_DISK=local   # ou 's3'
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=
AWS_BUCKET=
```

### 17.2 Frontend Nuxt (.env)

```ini
NUXT_PUBLIC_API_BASE=https://api.reussiteplus.cd/api/v1
NUXT_PUBLIC_APP_NAME=Réussite+
NUXT_PUBLIC_APP_VERSION=2.0.0
NUXT_PUBLIC_FIREBASE_API_KEY=XXXXXXXXXXXXXXXXXX
NUXT_PUBLIC_FIREBASE_PROJECT_ID=reussiteplus-cd
NUXT_PUBLIC_FIREBASE_MESSAGING_SENDER_ID=XXXXXXXXXXXXXXXXXX
NUXT_PUBLIC_FIREBASE_APP_ID=XXXXXXXXXXXXXXXXXX
```

---

## 18. Déploiement Nginx / Supervisor

### 18.1 Configuration Nginx

```nginx
# /etc/nginx/sites-available/reussiteplus-api
server {
    listen 443 ssl http2;
    server_name api.reussiteplus.cd;

    ssl_certificate     /etc/letsencrypt/live/api.reussiteplus.cd/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.reussiteplus.cd/privkey.pem;
    ssl_protocols       TLSv1.2 TLSv1.3;

    root /var/www/reussiteplus/backend/public;
    index index.php;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";
    add_header Strict-Transport-Security "max-age=63072000; includeSubDomains; preload";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass   unix:/run/php/php8.3-fpm.sock;
        fastcgi_index  index.php;
        fastcgi_param  SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include        fastcgi_params;
        fastcgi_read_timeout 60;
    }

    location ~* \.(jpg|jpeg|png|gif|ico|css|js|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    client_max_body_size 20M;
}

# /etc/nginx/sites-available/reussiteplus-app
server {
    listen 443 ssl http2;
    server_name app.reussiteplus.cd;

    ssl_certificate     /etc/letsencrypt/live/app.reussiteplus.cd/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/app.reussiteplus.cd/privkey.pem;

    root /var/www/reussiteplus/frontend/.output/public;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;   # SPA fallback
    }

    location ~* \.(js|css|png|jpg|jpeg|woff2|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

### 18.2 Supervisor (queue workers)

```ini
; /etc/supervisor/conf.d/reussiteplus-queue.conf
[program:rp-queue-default]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/reussiteplus/backend/artisan queue:work redis --queue=default --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/rp-queue-default.log
stdout_logfile_maxbytes=10MB

[program:rp-queue-emails]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/reussiteplus/backend/artisan queue:work redis --queue=emails --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=1
stdout_logfile=/var/log/supervisor/rp-queue-emails.log

[program:rp-horizon]
process_name=%(program_name)s
command=php /var/www/reussiteplus/backend/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/supervisor/rp-horizon.log
```

### 18.3 Commandes de déploiement

```bash
#!/bin/bash
# deploy.sh — Déploiement production Réussite+

set -e

echo "==> Déploiement Réussite+ v$(cat VERSION)"

# Backend
cd /var/www/reussiteplus/backend
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Restart queue
sudo supervisorctl restart rp-queue-default:*
sudo supervisorctl restart rp-queue-emails:*
sudo supervisorctl restart rp-horizon

# Frontend
cd /var/www/reussiteplus/frontend
npm ci --production
npm run generate

# Reload Nginx
sudo nginx -t && sudo systemctl reload nginx

echo "==> Déploiement terminé ✓"
```

### 18.4 Cron tasks (Laravel Scheduler)

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    // Vérifier abonnements expirés
    $schedule->command('abonnements:verifier-expiration')->daily();

    // Rappels abonnements (J-7)
    $schedule->command('abonnements:envoyer-rappels')->dailyAt('09:00');

    // Génération rapports hebdomadaires
    $schedule->command('rapports:generer-hebdo')->weekly()->sundays()->at('23:00');

    // Nettoyage tokens expirés Sanctum
    $schedule->command('sanctum:prune-expired')->daily();

    // Sync statuts paiements en attente
    $schedule->command('paiements:sync-pending')->everyFiveMinutes();

    // Purge failed jobs > 7 jours
    $schedule->command('queue:flush')->weekly();
}
```

---

## 19. Roadmap & Versions

### 19.1 Versions publiées

| Version | Date | Highlights |
|---------|------|-----------|
| v1.0.0 | Jan 2025 | MVP : cours, exercices, examens, auth, paiement M-Pesa |
| v1.1.0 | Fév 2025 | Bibliothèque, signets, module actualités |
| v1.2.0 | Mar 2025 | Module révision intelligente (SRS) |
| v1.3.0 | Avr 2025 | Module École bêta (classes, notes, emploi du temps) |
| v2.0.0 | Mai 2026 | Refonte Laravel 11 + Nuxt.js 3 + Capacitor 6, 32 tables |

### 19.2 Roadmap v2.x

| Version | Trimestre | Fonctionnalité |
|---------|-----------|---------------|
| v2.1.0 | Q2 2026 | Orange Money + intégration eCash RDC |
| v2.1.1 | Q2 2026 | Mode hors-ligne complet (exercices en cache) |
| v2.2.0 | Q3 2026 | Live classes (Zoom/Jitsi intégration enseignant) |
| v2.2.1 | Q3 2026 | App iOS disponible sur App Store |
| v2.3.0 | Q4 2026 | IA génération automatique exercices pour enseignants |
| v2.3.1 | Q4 2026 | Traduction Lingala / Swahili (UI partielle) |
| v3.0.0 | Q1 2027 | API publique partenaires + widget embarquable |

### 19.3 Dette technique connue

| Priorité | Sujet | Action |
|----------|-------|--------|
| 🔴 Haute | Tests couvrant < 60% | Atteindre 80% avec Pest |
| 🔴 Haute | Pas de monitoring erreurs prod | Intégrer Sentry |
| 🟡 Moyenne | Pas de CDN pour fichiers statiques | Cloudflare R2 |
| 🟡 Moyenne | Pagination API incohérente | Standardiser curseur/offset |
| 🟢 Basse | Documentation OpenAPI manquante | Générer via Scribe |

---

## 20. Glossaire & Annexes

### 20.1 Glossaire

| Terme | Définition |
|-------|-----------|
| **EXETAT** | Examen d'État (fin du secondaire, RDC) — équivalent Baccalauréat |
| **TENAFEP** | Test National de Fin d'Études Primaires (fin du primaire, RDC) |
| **TENA** | Test Normalisé d'Accès (entrée en 1ère année secondaire) |
| **EPSP** | Enseignement Primaire, Secondaire et Professionnel (ministère RDC) |
| **MINAS** | Ministère des Affaires Sociales (pour certains programmes) |
| **CDF** | Franc Congolais — monnaie officielle RDC |
| **M-Pesa** | Service Mobile Money de Vodacom RDC |
| **Airtel Money** | Service Mobile Money d'Airtel RDC |
| **SRS** | Spaced Repetition System — répétition espacée pour mémorisation |
| **FCM** | Firebase Cloud Messaging — push notifications Google |
| **Sanctum** | Package Laravel d'authentification API légère |
| **STK Push** | Solicitation To Kundisha — requête de paiement envoyée au téléphone M-Pesa |
| **UUID v4** | Identifiant universel unique version 4 (aléatoire) |

### 20.2 Provinces RDC couvertes

| Code | Province | Chef-lieu |
|------|----------|-----------|
| KIN | Kinshasa (capitale) | Kinshasa |
| KCG | Kongo Central | Matadi |
| KWG | Kwango | Kenge |
| KWL | Kwilu | Bandundu |
| MAI | Maï-Ndombe | Inongo |
| KAS | Kasaï | Luebo |
| KAC | Kasaï Central | Kananga |
| KAO | Kasaï Oriental | Mbuji-Mayi |
| SAN | Sankuru | Lodja |
| MAN | Maniema | Kindu |
| SUD | Sud-Kivu | Bukavu |
| NOR | Nord-Kivu | Goma |
| ITO | Ituri | Bunia |
| BAS | Bas-Uélé | Buta |
| HAU | Haut-Uélé | Isiro |
| TSH | Tshopo | Kisangani |
| TAN | Tanganyika | Kalemie |
| HKT | Haut-Katanga | Lubumbashi |
| LOM | Lualaba | Kolwezi |
| HLO | Haut-Lomami | Kamina |
| LOM | Lomami | Kabinda |
| LUA | Lualaba | Kolwezi |
| NUB | Nord-Ubangi | Gbadolite |
| SUB | Sud-Ubangi | Gemena |
| EQU | Équateur | Mbandaka |
| MON | Mongala | Lisala |

### 20.3 Niveaux scolaires couverts

**Primaire (EPSP) :**
1ère — 2ème — 3ème — 4ème — 5ème — 6ème année primaire

**Secondaire (Options) :**

| Niveau | Options |
|--------|---------|
| 1ère année | Commune (tronc commun) |
| 2ème — 6ème | Option A (Sciences) · Option B (Pédagogie) · Option C (Commercial) · Option D (Technique) · Option G (Latin) |

### 20.4 Matières couvertes par niveau

| Matière | Primaire | Secondaire |
|---------|----------|-----------|
| Français | ✅ 1–6 | ✅ 1–6 |
| Mathématiques | ✅ 1–6 | ✅ 1–6 |
| Sciences | ✅ 3–6 | ✅ 1–3 |
| Biologie | — | ✅ 4–6 (A) |
| Chimie | — | ✅ 4–6 (A) |
| Physique | — | ✅ 4–6 (A) |
| Histoire | ✅ 4–6 | ✅ 1–6 |
| Géographie | ✅ 4–6 | ✅ 1–6 |
| Civisme & Morale | ✅ 1–6 | ✅ 1–6 |
| Anglais | ✅ 4–6 | ✅ 1–6 |
| Religion | ✅ 1–6 | ✅ 1–6 |
| Éducation physique | ✅ 1–6 | ✅ 1–4 |
| Économie | — | ✅ 1–6 (C) |
| Informatique | — | ✅ 4–6 (D) |

### 20.5 Format des réponses API

**Succès :**
```json
{
  "data": { ... },
  "message": "Opération réussie",
  "meta": { "page": 1, "per_page": 20, "total": 150 }
}
```

**Erreur validation :**
```json
{
  "message": "Les données fournies sont invalides.",
  "errors": {
    "email": ["L'adresse email est déjà utilisée."],
    "mot_de_passe": ["Le mot de passe doit contenir au moins une majuscule."]
  }
}
```

**Erreur d'autorisation :**
```json
{
  "message": "Abonnement insuffisant",
  "plan_requis": "PREMIUM",
  "plan_actuel": "GRATUIT",
  "lien_upgrade": "/abonnement/upgrade"
}
```

**Erreur serveur :**
```json
{
  "message": "Une erreur interne est survenue.",
  "code": "INTERNAL_ERROR",
  "reference": "ERR-20260507-001"
}
```

---

*Documentation Réussite+ v2.0.0 — Confidentiel — © 2026 ReussitePlus SARL, Kinshasa RDC*
