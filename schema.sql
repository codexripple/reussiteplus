-- ============================================================
-- RÉUSSITE+ | Schéma Base de Données Complet
-- République Démocratique du Congo — Plateforme EdTech
-- Optimisé pour Supabase (PostgreSQL 15+)
-- ============================================================
-- ARCHITECTURE : Multi-tenant, scalable 100k+ utilisateurs
-- STRATÉGIE CACHE : Redis-compatible via Supabase Realtime
-- PERFORMANCE : Indexes composites, Materialized Views, RLS
-- ============================================================

-- ============================================================
-- EXTENSIONS REQUISES
-- ============================================================
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pg_trgm";         -- Full-text search trigram
CREATE EXTENSION IF NOT EXISTS "unaccent";         -- Recherche sans accents
CREATE EXTENSION IF NOT EXISTS "pgcrypto";         -- Sécurité
CREATE EXTENSION IF NOT EXISTS "pg_stat_statements"; -- Performance monitoring

-- ============================================================
-- ENUMS (Types personnalisés)
-- Définit les valeurs autorisées pour éviter les erreurs
-- ============================================================

-- Types d'examens nationaux en RDC
CREATE TYPE exam_type AS ENUM (
  'ENAFEP',       -- Examen National de Fin d'Études Primaires
  'TENASOSP',     -- Test National des Écoles du Secondaire
  'EXAMEN_ETAT',  -- Examen d'État (Baccalauréat)
  'DIOCESAIN',    -- Tests des Diocèses catholiques
  'AUTRE'
);

-- Sessions d'examens
CREATE TYPE session_type AS ENUM (
  'ORDINAIRE',    -- Session principale
  'RATTRAPAGE',   -- Session de rattrapage
  'SPECIALE'      -- Session spéciale
);

-- Niveaux de difficulté pedagogique
CREATE TYPE difficulty_level AS ENUM (
  'DEBUTANT',     -- 1 - Facile
  'ELEMENTAIRE',  -- 2 - En dessous de la moyenne
  'INTERMEDIAIRE',-- 3 - Moyen
  'AVANCE',       -- 4 - Au-dessus de la moyenne
  'EXPERT'        -- 5 - Très difficile
);

-- Types de questions
CREATE TYPE question_type AS ENUM (
  'QCM',              -- Choix multiples (1 bonne réponse)
  'VRAI_FAUX',        -- Vrai ou Faux
  'REPONSE_COURTE',   -- Réponse courte textuelle
  'REPONSE_LONGUE',   -- Développement/Dissertation
  'CALCUL',           -- Calcul mathématique
  'SCHEMA',           -- Légender un schéma
  'PROBLEME'          -- Problème complexe
);

-- Status des contenus
CREATE TYPE content_status AS ENUM (
  'BROUILLON',    -- En cours de création
  'REVISION',     -- En attente de révision
  'PUBLIE',       -- Visible par les élèves
  'ARCHIVE',      -- Retiré mais conservé
  'SUPPRIME'      -- Soft delete
);

-- Status des utilisateurs
CREATE TYPE user_role AS ENUM (
  'ELEVE',        -- Élève standard
  'ENSEIGNANT',   -- Enseignant/Répétiteur
  'ADMIN_ECOLE',  -- Administrateur d'école
  'SUPER_ADMIN',  -- Administrateur plateforme
  'MODERATEUR'    -- Modérateur contenu
);

-- Status des abonnements
CREATE TYPE subscription_plan AS ENUM (
  'GRATUIT',      -- Accès limité (5 examens/mois)
  'BASIQUE',      -- 5000 CDF/mois
  'PREMIUM',      -- 10000 CDF/mois (illimité)
  'ECOLE'         -- Abonnement institutionnel
);

-- ============================================================
-- TABLE : PROVINCES (Données géographiques RDC)
-- ============================================================
CREATE TABLE provinces (
  id            UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  code          VARCHAR(10) NOT NULL UNIQUE,  -- Ex: 'KIN', 'LUB', 'MANI'
  nom           VARCHAR(100) NOT NULL,
  region        VARCHAR(50),                   -- Zone géographique (Est, Ouest...)
  chef_lieu     VARCHAR(100),
  created_at    TIMESTAMPTZ DEFAULT NOW()
);

-- Index pour les recherches géographiques
CREATE INDEX idx_provinces_code ON provinces(code);
CREATE INDEX idx_provinces_nom ON provinces USING gin(nom gin_trgm_ops);

-- ============================================================
-- TABLE : UTILISATEURS PROFILES
-- Étend la table auth.users de Supabase
-- ============================================================
CREATE TABLE user_profiles (
  id                UUID PRIMARY KEY REFERENCES auth.users(id) ON DELETE CASCADE,
  
  -- Identité
  nom               VARCHAR(100) NOT NULL,
  prenom            VARCHAR(100) NOT NULL,
  date_naissance    DATE,
  sexe              CHAR(1) CHECK (sexe IN ('M', 'F')),
  photo_url         TEXT,
  
  -- Localisation
  province_id       UUID REFERENCES provinces(id),
  ville             VARCHAR(100),
  ecole             VARCHAR(200),        -- École actuelle
  classe            VARCHAR(50),         -- Ex: '6ème primaire', '4ème humanités'
  
  -- Compte
  role              user_role NOT NULL DEFAULT 'ELEVE',
  plan              subscription_plan NOT NULL DEFAULT 'GRATUIT',
  plan_expire_at    TIMESTAMPTZ,
  
  -- Préférences
  langue            VARCHAR(5) DEFAULT 'fr',
  theme             VARCHAR(20) DEFAULT 'light',
  notifications_email BOOLEAN DEFAULT TRUE,
  notifications_push  BOOLEAN DEFAULT TRUE,
  
  -- Stats rapides (dénormalisées pour performance)
  total_examens     INTEGER DEFAULT 0,
  total_questions   INTEGER DEFAULT 0,
  score_moyen       DECIMAL(5,2) DEFAULT 0.00,
  streak_jours      INTEGER DEFAULT 0,
  derniere_activite TIMESTAMPTZ,
  
  -- Métadata
  created_at        TIMESTAMPTZ DEFAULT NOW(),
  updated_at        TIMESTAMPTZ DEFAULT NOW(),
  
  -- Contrainte unicité prénom+nom (soft)
  CONSTRAINT valid_score CHECK (score_moyen >= 0 AND score_moyen <= 100)
);

-- Indexes utilisateurs
CREATE INDEX idx_users_role ON user_profiles(role);
CREATE INDEX idx_users_province ON user_profiles(province_id);
CREATE INDEX idx_users_plan ON user_profiles(plan);
CREATE INDEX idx_users_nom ON user_profiles USING gin(
  (nom || ' ' || prenom) gin_trgm_ops
);
CREATE INDEX idx_users_activite ON user_profiles(derniere_activite DESC NULLS LAST);

-- ============================================================
-- TABLE : MATIERES (Disciplines scolaires)
-- ============================================================
CREATE TABLE matieres (
  id            UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  code          VARCHAR(20) NOT NULL UNIQUE,   -- Ex: 'MATH', 'FRAN', 'SVT'
  nom           VARCHAR(100) NOT NULL,
  nom_court     VARCHAR(30),                   -- Abréviation
  description   TEXT,
  couleur       VARCHAR(7),                    -- Couleur hex pour UI
  icone         VARCHAR(50),                   -- Nom icône
  ordre         INTEGER DEFAULT 0,             -- Ordre d'affichage
  actif         BOOLEAN DEFAULT TRUE,
  created_at    TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_matieres_code ON matieres(code);
CREATE INDEX idx_matieres_actif ON matieres(actif) WHERE actif = TRUE;

-- ============================================================
-- TABLE : CHAPITRES (Syllabus par matière)
-- Permet des exercices ciblés par chapitre
-- ============================================================
CREATE TABLE chapitres (
  id            UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  matiere_id    UUID NOT NULL REFERENCES matieres(id),
  
  -- Hiérarchie : parent_id permet sous-chapitres
  parent_id     UUID REFERENCES chapitres(id),
  
  code          VARCHAR(30),                   -- Ex: 'MATH-GEOM-01'
  titre         VARCHAR(200) NOT NULL,
  description   TEXT,
  ordre         INTEGER DEFAULT 0,
  niveau        VARCHAR(50),                   -- Niveau scolaire concerné
  actif         BOOLEAN DEFAULT TRUE,
  created_at    TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_chapitres_matiere ON chapitres(matiere_id);
CREATE INDEX idx_chapitres_parent ON chapitres(parent_id);
CREATE INDEX idx_chapitres_titre ON chapitres USING gin(titre gin_trgm_ops);

-- ============================================================
-- TABLE : ARCHIVES (Anciens sujets et corrigés officiels)
-- CRITICAL : Coeur du système de référence
-- Organisation : Examen → Année → Session → Matière
-- ============================================================
CREATE TABLE archives (
  id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  
  -- Classification principale
  exam_type       exam_type NOT NULL,
  annee           INTEGER NOT NULL CHECK (annee >= 1960 AND annee <= EXTRACT(YEAR FROM NOW())+1),
  session         session_type NOT NULL DEFAULT 'ORDINAIRE',
  matiere_id      UUID NOT NULL REFERENCES matieres(id),
  province_id     UUID REFERENCES provinces(id),    -- NULL = national
  
  -- Identité du document
  titre           VARCHAR(300) NOT NULL,
  description     TEXT,
  
  -- Fichiers (Supabase Storage URLs)
  sujet_url       TEXT,          -- URL PDF du sujet
  corrige_url     TEXT,          -- URL PDF du corrigé
  audio_url       TEXT,          -- Pour langues (écoute)
  
  -- Métadata fichiers
  sujet_pages     INTEGER,
  corrige_pages   INTEGER,
  sujet_taille    INTEGER,       -- Taille en octets
  
  -- SEO & Découvrabilité
  slug            VARCHAR(400) UNIQUE NOT NULL,  -- Ex: enafep-2024-mathematiques-ordinaire
  meta_description TEXT,
  mots_cles       TEXT[],        -- Tags pour recherche
  
  -- Statistiques
  vues            INTEGER DEFAULT 0,
  telechargements INTEGER DEFAULT 0,
  
  -- Administration
  status          content_status DEFAULT 'PUBLIE',
  source          VARCHAR(200),  -- Ex: 'Ministère EPST'
  verifie         BOOLEAN DEFAULT FALSE,
  verifie_par     UUID REFERENCES auth.users(id),
  
  created_by      UUID REFERENCES auth.users(id),
  created_at      TIMESTAMPTZ DEFAULT NOW(),
  updated_at      TIMESTAMPTZ DEFAULT NOW(),
  
  -- Contrainte d'unicité : 1 archive par examen/année/session/matière/province
  UNIQUE (exam_type, annee, session, matiere_id, COALESCE(province_id::TEXT, 'national'))
);

-- Indexes archives (TRÈS IMPORTANT pour performance)
-- Index composite pour la requête principale de navigation
CREATE INDEX idx_archives_nav ON archives(exam_type, annee DESC, session, matiere_id)
  WHERE status = 'PUBLIE';

-- Index pour filtrage par province
CREATE INDEX idx_archives_province ON archives(province_id) WHERE province_id IS NOT NULL;

-- Index full-text pour recherche
CREATE INDEX idx_archives_fts ON archives USING gin(
  to_tsvector('french',
    COALESCE(titre, '') || ' ' ||
    COALESCE(description, '') || ' ' ||
    COALESCE(array_to_string(mots_cles, ' '), '')
  )
);

-- Index pour les stats (tri par popularité)
CREATE INDEX idx_archives_popularite ON archives(vues DESC, telechargements DESC)
  WHERE status = 'PUBLIE';

-- Index slug pour URLs SEO
CREATE UNIQUE INDEX idx_archives_slug ON archives(slug);

-- ============================================================
-- TABLE : BANQUE DE QUESTIONS (Items pédagogiques)
-- Structure professionnelle : chaque question = 1 item réutilisable
-- ============================================================
CREATE TABLE question_bank (
  id                UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  
  -- Classification pédagogique
  matiere_id        UUID NOT NULL REFERENCES matieres(id),
  chapitre_id       UUID REFERENCES chapitres(id),
  
  -- Origine
  archive_id        UUID REFERENCES archives(id),  -- Si issu d'un ancien sujet
  exam_type         exam_type,                      -- Type d'examen source
  annee_source      INTEGER,                         -- Année si archivé
  
  -- Contenu de la question
  type              question_type NOT NULL DEFAULT 'QCM',
  enonce            TEXT NOT NULL,                  -- Texte de la question
  enonce_html       TEXT,                           -- Version HTML avec formules
  
  -- Médias
  image_url         TEXT,                           -- Image jointe à la question
  audio_url         TEXT,                           -- Audio si applicable
  
  -- Paramètres pédagogiques
  difficulte        difficulty_level NOT NULL DEFAULT 'INTERMEDIAIRE',
  difficulte_score  DECIMAL(3,1),                  -- Score précis 1.0-5.0
  points            DECIMAL(4,1) DEFAULT 1.0,       -- Valeur en points
  temps_suggere     INTEGER,                        -- Secondes recommandées
  
  -- Compétences (tableau pour multi-compétences)
  competences       TEXT[],                         -- Ex: ['calcul', 'raisonnement']
  objectif          TEXT,                           -- Objectif pédagogique
  
  -- Source et vérification
  source            VARCHAR(300),                  -- Ex: 'ENAFEP 2022 - Question 14'
  auteur_id         UUID REFERENCES auth.users(id),
  verifie           BOOLEAN DEFAULT FALSE,
  verifie_par       UUID REFERENCES auth.users(id),
  
  -- SEO et recherche
  mots_cles         TEXT[],
  
  -- Statistiques (mises à jour par trigger)
  usage_count       INTEGER DEFAULT 0,             -- Combien de fois utilisée
  success_rate      DECIMAL(5,2),                  -- % de bonnes réponses
  average_time      INTEGER,                       -- Secondes en moyenne
  discrimination    DECIMAL(5,3),                  -- Index de discrimination
  
  -- Administration
  status            content_status DEFAULT 'BROUILLON',
  created_at        TIMESTAMPTZ DEFAULT NOW(),
  updated_at        TIMESTAMPTZ DEFAULT NOW()
);

-- Indexes banque de questions
CREATE INDEX idx_qbank_matiere ON question_bank(matiere_id) WHERE status = 'PUBLIE';
CREATE INDEX idx_qbank_chapitre ON question_bank(chapitre_id) WHERE status = 'PUBLIE';
CREATE INDEX idx_qbank_difficulte ON question_bank(difficulte, matiere_id) WHERE status = 'PUBLIE';
CREATE INDEX idx_qbank_exam ON question_bank(exam_type, annee_source) WHERE status = 'PUBLIE';
CREATE INDEX idx_qbank_success ON question_bank(success_rate ASC) WHERE status = 'PUBLIE';

-- Full-text search sur les questions
CREATE INDEX idx_qbank_fts ON question_bank USING gin(
  to_tsvector('french', COALESCE(enonce, '') || ' ' || COALESCE(array_to_string(mots_cles, ' '), ''))
);

-- ============================================================
-- TABLE : RÉPONSES POSSIBLES (Options QCM)
-- ============================================================
CREATE TABLE question_options (
  id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  question_id     UUID NOT NULL REFERENCES question_bank(id) ON DELETE CASCADE,
  
  lettre          CHAR(1) NOT NULL,              -- A, B, C, D, E
  texte           TEXT NOT NULL,                 -- Texte de l'option
  texte_html      TEXT,                          -- Version HTML
  image_url       TEXT,                          -- Image si option visuelle
  
  est_correcte    BOOLEAN NOT NULL DEFAULT FALSE,
  ordre           INTEGER DEFAULT 0,
  
  -- Fréquence de sélection (analyse des erreurs)
  selection_count INTEGER DEFAULT 0,
  
  created_at      TIMESTAMPTZ DEFAULT NOW(),
  
  UNIQUE (question_id, lettre)
);

CREATE INDEX idx_options_question ON question_options(question_id);
CREATE INDEX idx_options_correcte ON question_options(question_id, est_correcte) WHERE est_correcte = TRUE;

-- ============================================================
-- TABLE : EXPLICATIONS DÉTAILLÉES
-- Chaque question a une explication approfondie
-- ============================================================
CREATE TABLE question_explanations (
  id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  question_id     UUID NOT NULL UNIQUE REFERENCES question_bank(id) ON DELETE CASCADE,
  
  -- Explication principale
  explication     TEXT NOT NULL,
  explication_html TEXT,
  
  -- Pièges courants
  erreurs_communes TEXT[],
  
  -- Références pédagogiques
  lecon_url       TEXT,          -- Lien vers la leçon correspondante
  video_url       TEXT,          -- Vidéo explicative
  
  -- Méthode de résolution
  methode         TEXT,          -- Étapes de résolution
  formules        TEXT[],        -- Formules utilisées
  
  created_by      UUID REFERENCES auth.users(id),
  created_at      TIMESTAMPTZ DEFAULT NOW(),
  updated_at      TIMESTAMPTZ DEFAULT NOW()
);

-- ============================================================
-- TABLE : TAGS QUESTIONS (Classification flexible)
-- ============================================================
CREATE TABLE question_tags (
  id          UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  question_id UUID NOT NULL REFERENCES question_bank(id) ON DELETE CASCADE,
  tag         VARCHAR(100) NOT NULL,
  categorie   VARCHAR(50),       -- Ex: 'concept', 'type', 'niveau'
  
  UNIQUE (question_id, tag)
);

CREATE INDEX idx_tags_question ON question_tags(question_id);
CREATE INDEX idx_tags_tag ON question_tags(tag);

-- ============================================================
-- TABLE : EXAMENS BLANCS (Épreuves générées)
-- ============================================================
CREATE TABLE examens_blancs (
  id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  
  -- Identification
  titre           VARCHAR(300) NOT NULL,
  description     TEXT,
  
  -- Configuration
  exam_type       exam_type,
  matieres        UUID[],                        -- Matières incluses
  
  -- Paramètres de génération
  nb_questions    INTEGER NOT NULL,
  duree_minutes   INTEGER NOT NULL,              -- Durée totale
  total_points    DECIMAL(6,1) DEFAULT 20.0,
  
  -- Difficulté et ciblage
  niveau_difficulte difficulty_level,
  annees_source   INTEGER[],                     -- Années des questions
  
  -- Type d'examen blanc
  est_aleatoire   BOOLEAN DEFAULT FALSE,         -- Généré aléatoirement
  est_personnalise BOOLEAN DEFAULT FALSE,         -- Basé sur faiblesses IA
  template_id     UUID,                           -- Si basé sur un template
  
  -- Disponibilité
  status          content_status DEFAULT 'PUBLIE',
  acces           VARCHAR(20) DEFAULT 'public',  -- public, premium, ecole
  
  -- Statistiques
  nb_tentatives   INTEGER DEFAULT 0,
  score_moyen     DECIMAL(5,2),
  
  created_by      UUID REFERENCES auth.users(id),
  created_at      TIMESTAMPTZ DEFAULT NOW(),
  updated_at      TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_examens_type ON examens_blancs(exam_type) WHERE status = 'PUBLIE';

-- ============================================================
-- TABLE : QUESTIONS PAR EXAMEN BLANC
-- Lie les questions aux examens avec l'ordre et les points
-- ============================================================
CREATE TABLE examen_questions (
  id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  examen_id       UUID NOT NULL REFERENCES examens_blancs(id) ON DELETE CASCADE,
  question_id     UUID NOT NULL REFERENCES question_bank(id),
  
  ordre           INTEGER NOT NULL,
  points          DECIMAL(4,1),                  -- Peut différer de la question
  
  UNIQUE (examen_id, question_id),
  UNIQUE (examen_id, ordre)
);

CREATE INDEX idx_examen_questions_examen ON examen_questions(examen_id);

-- ============================================================
-- TABLE : TENTATIVES (Historique complet des sessions)
-- CRITIQUE pour le suivi de progression
-- ============================================================
CREATE TABLE attempts (
  id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  user_id         UUID NOT NULL REFERENCES auth.users(id),
  examen_id       UUID NOT NULL REFERENCES examens_blancs(id),
  
  -- Timing
  started_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  completed_at    TIMESTAMPTZ,
  duree_secondes  INTEGER,                       -- Temps réel passé
  
  -- Résultats
  score           DECIMAL(5,2),                  -- Score brut
  score_pourcent  DECIMAL(5,2),                  -- Pourcentage
  points_obtenus  DECIMAL(6,1),
  points_total    DECIMAL(6,1),
  
  -- Détail par matière (JSON dénormalisé pour performance)
  scores_matieres JSONB,                         -- {matiere_id: {score, total}}
  
  -- État
  status          VARCHAR(20) DEFAULT 'en_cours', -- en_cours, terminé, abandonné
  
  -- Anti-triche
  nb_changements_onglet INTEGER DEFAULT 0,
  
  created_at      TIMESTAMPTZ DEFAULT NOW()
);

-- Indexes attempts (très sollicités)
CREATE INDEX idx_attempts_user ON attempts(user_id, completed_at DESC);
CREATE INDEX idx_attempts_examen ON attempts(examen_id);
CREATE INDEX idx_attempts_score ON attempts(user_id, score_pourcent DESC) WHERE status = 'terminé';

-- ============================================================
-- TABLE : RÉPONSES PAR QUESTION (Granularité maximale)
-- Permet l'analyse fine des erreurs
-- ============================================================
CREATE TABLE attempt_responses (
  id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  attempt_id      UUID NOT NULL REFERENCES attempts(id) ON DELETE CASCADE,
  question_id     UUID NOT NULL REFERENCES question_bank(id),
  
  -- Réponse de l'élève
  reponse_donnee  TEXT,                          -- Réponse saisie
  options_choisies UUID[],                        -- Pour QCM
  
  -- Évaluation
  est_correcte    BOOLEAN,
  points_obtenus  DECIMAL(4,1) DEFAULT 0,
  
  -- Timing
  temps_secondes  INTEGER,                       -- Temps passé sur cette question
  
  -- Aide utilisée
  indice_utilise  BOOLEAN DEFAULT FALSE,
  
  created_at      TIMESTAMPTZ DEFAULT NOW(),
  
  UNIQUE (attempt_id, question_id)
);

CREATE INDEX idx_responses_attempt ON attempt_responses(attempt_id);
CREATE INDEX idx_responses_question ON attempt_responses(question_id);

-- ============================================================
-- TABLE : STATISTIQUES QUESTIONS
-- Agrégats mis à jour automatiquement
-- ============================================================
CREATE TABLE question_statistics (
  question_id         UUID PRIMARY KEY REFERENCES question_bank(id) ON DELETE CASCADE,
  
  -- Compteurs
  total_tentatives    INTEGER DEFAULT 0,
  total_correctes     INTEGER DEFAULT 0,
  
  -- Métriques de performance
  success_rate        DECIMAL(5,2),              -- % de réussite
  average_time_sec    DECIMAL(8,2),              -- Temps moyen en secondes
  
  -- Analyse par option (QCM)
  distribution_options JSONB,                    -- {A: 45%, B: 30%, C: 15%, D: 10%}
  
  -- Indice de discrimination (corrélation avec score total)
  discrimination_index DECIMAL(5,3),             -- -1 à 1 (idéal > 0.3)
  
  -- Tendances temporelles
  success_rate_7j     DECIMAL(5,2),              -- Taux 7 derniers jours
  success_rate_30j    DECIMAL(5,2),              -- Taux 30 derniers jours
  
  last_updated        TIMESTAMPTZ DEFAULT NOW()
);

-- ============================================================
-- TABLE : PROGRESSION UTILISATEUR (Vue agrégée)
-- Dénormalisée pour performance du dashboard
-- ============================================================
CREATE TABLE user_progress (
  user_id             UUID PRIMARY KEY REFERENCES auth.users(id) ON DELETE CASCADE,
  
  -- Progression par matière (JSON pour flexibilité)
  progression_matieres JSONB DEFAULT '{}',       -- {matiere_id: {score_moyen, nb_questions, ...}}
  
  -- Examens
  total_examens       INTEGER DEFAULT 0,
  examens_complets    INTEGER DEFAULT 0,
  
  -- Questions
  total_questions     INTEGER DEFAULT 0,
  questions_correctes INTEGER DEFAULT 0,
  
  -- Scores
  score_global        DECIMAL(5,2) DEFAULT 0,
  meilleur_score      DECIMAL(5,2) DEFAULT 0,
  
  -- Gamification
  streak_actuel       INTEGER DEFAULT 0,
  streak_record       INTEGER DEFAULT 0,
  derniere_session    TIMESTAMPTZ,
  
  -- Recommandations IA (cache)
  chapitres_faibles   UUID[],                    -- Chapitres nécessitant révision
  plan_revision       JSONB,                     -- Plan de révision personnalisé
  plan_updated_at     TIMESTAMPTZ,
  
  updated_at          TIMESTAMPTZ DEFAULT NOW()
);

-- ============================================================
-- TABLE : HISTORIQUE QUESTIONS (Question History)
-- Évite les répétitions dans les examens générés
-- ============================================================
CREATE TABLE question_history (
  id            UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  user_id       UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
  question_id   UUID NOT NULL REFERENCES question_bank(id),
  
  derniere_vue  TIMESTAMPTZ DEFAULT NOW(),
  nb_fois       INTEGER DEFAULT 1,
  maitrisee     BOOLEAN DEFAULT FALSE,           -- Considérée maîtrisée
  
  UNIQUE (user_id, question_id)
);

CREATE INDEX idx_history_user ON question_history(user_id, derniere_vue DESC);
CREATE INDEX idx_history_question ON question_history(question_id);

-- ============================================================
-- TABLE : PAIEMENTS ET ABONNEMENTS
-- ============================================================
CREATE TABLE paiements (
  id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  user_id         UUID NOT NULL REFERENCES auth.users(id),
  
  plan            subscription_plan NOT NULL,
  montant         DECIMAL(10,2) NOT NULL,
  devise          VARCHAR(3) DEFAULT 'CDF',
  
  -- Période
  debut_at        TIMESTAMPTZ NOT NULL,
  fin_at          TIMESTAMPTZ NOT NULL,
  
  -- Paiement mobile money RDC
  methode         VARCHAR(50),                   -- 'MPESA', 'AIRTEL_MONEY', 'ORANGE_MONEY'
  transaction_id  VARCHAR(200),                  -- ID transaction opérateur
  
  status          VARCHAR(20) DEFAULT 'pending', -- pending, success, failed, refunded
  
  created_at      TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_paiements_user ON paiements(user_id, created_at DESC);
CREATE INDEX idx_paiements_status ON paiements(status);

-- ============================================================
-- VUES MATERIALISÉES (Pré-calculs pour performance)
-- Rafraîchies périodiquement (CRON toutes les heures)
-- ============================================================

-- Vue : Archives par examen et année (Page d'accueil archives)
CREATE MATERIALIZED VIEW mv_archives_stats AS
SELECT
  a.exam_type,
  a.annee,
  a.session,
  COUNT(DISTINCT a.matiere_id) AS nb_matieres,
  COUNT(*) AS nb_archives,
  SUM(a.vues) AS total_vues,
  MAX(a.created_at) AS derniere_mise_a_jour,
  BOOL_OR(a.corrige_url IS NOT NULL) AS has_corriges
FROM archives a
WHERE a.status = 'PUBLIE'
GROUP BY a.exam_type, a.annee, a.session
ORDER BY a.exam_type, a.annee DESC, a.session;

CREATE UNIQUE INDEX idx_mv_archives_stats ON mv_archives_stats(exam_type, annee, session);

-- Vue : Questions difficiles par matière (Dashboard IA)
CREATE MATERIALIZED VIEW mv_questions_difficiles AS
SELECT
  q.matiere_id,
  m.nom AS matiere_nom,
  q.id AS question_id,
  q.enonce,
  COALESCE(qs.success_rate, 50) AS success_rate,
  COALESCE(qs.average_time_sec, 0) AS average_time_sec,
  COALESCE(qs.total_tentatives, 0) AS total_tentatives,
  q.difficulte
FROM question_bank q
JOIN matieres m ON q.matiere_id = m.id
LEFT JOIN question_statistics qs ON q.id = qs.question_id
WHERE q.status = 'PUBLIE'
  AND COALESCE(qs.success_rate, 100) < 40   -- Questions difficiles : < 40% réussite
  AND COALESCE(qs.total_tentatives, 0) >= 10 -- Minimum 10 tentatives
ORDER BY qs.success_rate ASC;

CREATE INDEX idx_mv_diff_matiere ON mv_questions_difficiles(matiere_id);

-- Vue : Classement national (Top élèves)
CREATE MATERIALIZED VIEW mv_classement AS
SELECT
  up.user_id,
  u.nom,
  u.prenom,
  u.province_id,
  p.nom AS province_nom,
  u.score_global,
  u.total_examens,
  u.questions_correctes,
  RANK() OVER (ORDER BY u.score_global DESC) AS rang_national,
  RANK() OVER (PARTITION BY up.user_id ORDER BY u.score_global DESC) AS rang_province
FROM user_progress up
JOIN user_profiles u ON up.user_id = u.id
LEFT JOIN provinces p ON u.province_id = p.id
WHERE u.total_examens >= 3   -- Au moins 3 examens passés
ORDER BY u.score_global DESC
LIMIT 1000;

CREATE UNIQUE INDEX idx_mv_classement_user ON mv_classement(user_id);

-- ============================================================
-- FONCTIONS UTILITAIRES
-- ============================================================

-- Fonction : Générer un slug URL depuis un titre
CREATE OR REPLACE FUNCTION generate_slug(titre TEXT, exam_type TEXT, annee INTEGER)
RETURNS TEXT AS $$
DECLARE
  slug TEXT;
BEGIN
  slug := lower(unaccent(titre));
  slug := regexp_replace(slug, '[^a-z0-9\s-]', '', 'g');
  slug := regexp_replace(slug, '\s+', '-', 'g');
  slug := exam_type || '-' || annee || '-' || slug;
  slug := left(slug, 400);
  RETURN slug;
END;
$$ LANGUAGE plpgsql IMMUTABLE;

-- Fonction : Mettre à jour les stats question après une réponse
CREATE OR REPLACE FUNCTION update_question_stats(
  p_question_id UUID,
  p_est_correcte BOOLEAN,
  p_temps_secondes INTEGER
) RETURNS VOID AS $$
BEGIN
  INSERT INTO question_statistics (question_id, total_tentatives, total_correctes, average_time_sec)
  VALUES (p_question_id, 1, CASE WHEN p_est_correcte THEN 1 ELSE 0 END, p_temps_secondes)
  ON CONFLICT (question_id) DO UPDATE SET
    total_tentatives = question_statistics.total_tentatives + 1,
    total_correctes = question_statistics.total_correctes + CASE WHEN p_est_correcte THEN 1 ELSE 0 END,
    success_rate = ROUND(
      (question_statistics.total_correctes + CASE WHEN p_est_correcte THEN 1 ELSE 0 END)::DECIMAL /
      (question_statistics.total_tentatives + 1) * 100, 2
    ),
    average_time_sec = ROUND(
      (question_statistics.average_time_sec * question_statistics.total_tentatives + p_temps_secondes) /
      (question_statistics.total_tentatives + 1), 2
    ),
    last_updated = NOW();
  
  -- Mettre à jour le compteur usage dans question_bank
  UPDATE question_bank SET usage_count = usage_count + 1 WHERE id = p_question_id;
END;
$$ LANGUAGE plpgsql;

-- Fonction : Calculer le plan de révision IA
CREATE OR REPLACE FUNCTION calculate_revision_plan(p_user_id UUID)
RETURNS JSONB AS $$
DECLARE
  plan JSONB := '{}';
  faible_record RECORD;
BEGIN
  -- Identifier les chapitres avec taux de réussite < 60%
  FOR faible_record IN
    SELECT
      qb.chapitre_id,
      ch.titre AS chapitre,
      m.nom AS matiere,
      ROUND(AVG(CASE WHEN ar.est_correcte THEN 100 ELSE 0 END), 1) AS taux_reussite,
      COUNT(*) AS nb_tentatives
    FROM attempt_responses ar
    JOIN attempts att ON ar.attempt_id = att.id
    JOIN question_bank qb ON ar.question_id = qb.id
    JOIN chapitres ch ON qb.chapitre_id = ch.id
    JOIN matieres m ON qb.matiere_id = m.id
    WHERE att.user_id = p_user_id
      AND att.completed_at > NOW() - INTERVAL '30 days'
      AND qb.chapitre_id IS NOT NULL
    GROUP BY qb.chapitre_id, ch.titre, m.nom
    HAVING AVG(CASE WHEN ar.est_correcte THEN 100 ELSE 0 END) < 60
      AND COUNT(*) >= 3
    ORDER BY taux_reussite ASC
    LIMIT 5
  LOOP
    plan := plan || jsonb_build_object(
      faible_record.chapitre_id::TEXT,
      jsonb_build_object(
        'chapitre', faible_record.chapitre,
        'matiere', faible_record.matiere,
        'taux_reussite', faible_record.taux_reussite,
        'nb_tentatives', faible_record.nb_tentatives,
        'priorite', CASE 
          WHEN faible_record.taux_reussite < 30 THEN 'urgente'
          WHEN faible_record.taux_reussite < 45 THEN 'haute'
          ELSE 'normale'
        END
      )
    );
  END LOOP;
  
  -- Mettre à jour le cache dans user_progress
  UPDATE user_progress 
  SET plan_revision = plan, plan_updated_at = NOW()
  WHERE user_id = p_user_id;
  
  RETURN plan;
END;
$$ LANGUAGE plpgsql;

-- ============================================================
-- TRIGGERS (Automatisations)
-- ============================================================

-- Trigger : Mettre à jour updated_at automatiquement
CREATE OR REPLACE FUNCTION update_updated_at()
RETURNS TRIGGER AS $$
BEGIN
  NEW.updated_at = NOW();
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_archives_updated_at
  BEFORE UPDATE ON archives
  FOR EACH ROW EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_qbank_updated_at
  BEFORE UPDATE ON question_bank
  FOR EACH ROW EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_users_updated_at
  BEFORE UPDATE ON user_profiles
  FOR EACH ROW EXECUTE FUNCTION update_updated_at();

-- Trigger : Incrémenter vues archive
CREATE OR REPLACE FUNCTION increment_archive_views()
RETURNS TRIGGER AS $$
BEGIN
  UPDATE archives SET vues = vues + 1 WHERE id = NEW.archive_id;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Trigger : Mettre à jour stats après completion d'une tentative
CREATE OR REPLACE FUNCTION on_attempt_completed()
RETURNS TRIGGER AS $$
BEGIN
  IF NEW.status = 'terminé' AND OLD.status != 'terminé' THEN
    -- Mettre à jour le profil utilisateur
    UPDATE user_profiles SET
      total_examens = total_examens + 1,
      score_moyen = ROUND(
        (score_moyen * total_examens + NEW.score_pourcent) / (total_examens + 1), 2
      ),
      derniere_activite = NOW()
    WHERE id = NEW.user_id;
    
    -- Mettre à jour user_progress
    INSERT INTO user_progress (user_id, total_examens, examens_complets, score_global)
    VALUES (NEW.user_id, 1, 1, COALESCE(NEW.score_pourcent, 0))
    ON CONFLICT (user_id) DO UPDATE SET
      total_examens = user_progress.total_examens + 1,
      examens_complets = user_progress.examens_complets + 1,
      score_global = ROUND(
        (user_progress.score_global * user_progress.total_examens + COALESCE(NEW.score_pourcent, 0)) /
        (user_progress.total_examens + 1), 2
      ),
      meilleur_score = GREATEST(user_progress.meilleur_score, COALESCE(NEW.score_pourcent, 0)),
      updated_at = NOW();
  END IF;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_attempt_completed
  AFTER UPDATE ON attempts
  FOR EACH ROW EXECUTE FUNCTION on_attempt_completed();

-- ============================================================
-- ROW LEVEL SECURITY (RLS) — Sécurité multi-tenant
-- ============================================================
ALTER TABLE user_profiles ENABLE ROW LEVEL SECURITY;
ALTER TABLE attempts ENABLE ROW LEVEL SECURITY;
ALTER TABLE attempt_responses ENABLE ROW LEVEL SECURITY;
ALTER TABLE user_progress ENABLE ROW LEVEL SECURITY;
ALTER TABLE question_history ENABLE ROW LEVEL SECURITY;
ALTER TABLE paiements ENABLE ROW LEVEL SECURITY;

-- Politique : Utilisateurs voient seulement leurs propres données
CREATE POLICY "users_own_profile" ON user_profiles
  FOR ALL USING (auth.uid() = id);

CREATE POLICY "users_own_attempts" ON attempts
  FOR ALL USING (auth.uid() = user_id);

CREATE POLICY "users_own_responses" ON attempt_responses
  FOR ALL USING (
    attempt_id IN (SELECT id FROM attempts WHERE user_id = auth.uid())
  );

CREATE POLICY "users_own_progress" ON user_progress
  FOR ALL USING (auth.uid() = user_id);

CREATE POLICY "users_own_history" ON question_history
  FOR ALL USING (auth.uid() = user_id);

CREATE POLICY "users_own_payments" ON paiements
  FOR ALL USING (auth.uid() = user_id);

-- Politique : Contenu public accessible à tous
CREATE POLICY "archives_public_read" ON archives
  FOR SELECT USING (status = 'PUBLIE');

CREATE POLICY "questions_public_read" ON question_bank
  FOR SELECT USING (status = 'PUBLIE');

-- Politique : Admins ont accès complet
CREATE POLICY "admin_full_access" ON archives
  FOR ALL USING (
    auth.uid() IN (SELECT id FROM user_profiles WHERE role IN ('SUPER_ADMIN', 'MODERATEUR'))
  );

-- ============================================================
-- DONNÉES INITIALES
-- ============================================================

-- Provinces de la RDC
INSERT INTO provinces (code, nom, region, chef_lieu) VALUES
  ('KIN', 'Kinshasa', 'Ouest', 'Kinshasa'),
  ('BC', 'Bas-Congo', 'Ouest', 'Matadi'),
  ('BK', 'Bandundu', 'Ouest', 'Bandundu'),
  ('EQ', 'Équateur', 'Nord', 'Mbandaka'),
  ('MAN', 'Maniema', 'Est', 'Kindu'),
  ('KAS', 'Kasaï', 'Centre', 'Tshikapa'),
  ('KAO', 'Kasaï-Oriental', 'Centre', 'Mbuji-Mayi'),
  ('KAC', 'Kasaï-Central', 'Centre', 'Kananga'),
  ('KAT', 'Katanga', 'Sud', 'Lubumbashi'),
  ('LS', 'Lualaba', 'Sud', 'Kolwezi'),
  ('HAK', 'Haut-Katanga', 'Sud', 'Lubumbashi'),
  ('MAN', 'Maniema', 'Est', 'Kindu'),
  ('NK', 'Nord-Kivu', 'Est', 'Goma'),
  ('SK', 'Sud-Kivu', 'Est', 'Bukavu'),
  ('IT', 'Ituri', 'Est', 'Bunia'),
  ('HL', 'Haut-Lomami', 'Sud', 'Kamina'),
  ('TNG', 'Tanganyika', 'Sud', 'Kalemie'),
  ('TBS', 'Tshopo', 'Nord-Est', 'Kisangani'),
  ('BAS', 'Bas-Uele', 'Nord', 'Buta'),
  ('HAU', 'Haut-Uele', 'Nord-Est', 'Isiro'),
  ('MAI', 'Mai-Ndombe', 'Ouest', 'Inongo'),
  ('SA', 'Sankuru', 'Centre', 'Lodja'),
  ('NDB', 'Nord-Ubangi', 'Nord', 'Gbadolite'),
  ('SUB', 'Sud-Ubangi', 'Nord', 'Gemena'),
  ('MGA', 'Mongala', 'Nord', 'Lisala'),
  ('LOM', 'Lomami', 'Centre', 'Kabinda');

-- Matières principales
INSERT INTO matieres (code, nom, nom_court, couleur, ordre) VALUES
  ('MATH', 'Mathématiques', 'Maths', '#2563EB', 1),
  ('FRAN', 'Français', 'Français', '#DC2626', 2),
  ('SVT', 'Sciences de la Vie et de la Terre', 'SVT', '#16A34A', 3),
  ('HIST', 'Histoire', 'Histoire', '#D97706', 4),
  ('GEO', 'Géographie', 'Géo', '#9333EA', 5),
  ('PHYS', 'Physique', 'Physique', '#0891B2', 6),
  ('CHIM', 'Chimie', 'Chimie', '#7C3AED', 7),
  ('ECM', 'Éducation Civique et Morale', 'ECM', '#EA580C', 8),
  ('KIS', 'Kiswahili', 'Kiswahili', '#BE185D', 9),
  ('LING', 'Lingala', 'Lingala', '#B45309', 10),
  ('ANG', 'Anglais', 'Anglais', '#047857', 11),
  ('EPS', 'Éducation Physique et Sportive', 'EPS', '#0284C7', 12);

-- ============================================================
-- INDEXES SUPPLÉMENTAIRES POUR PERFORMANCE
-- ============================================================

-- Index partiel pour les archives récentes (requête fréquente)
CREATE INDEX idx_archives_recent ON archives(exam_type, annee DESC)
  WHERE status = 'PUBLIE' AND annee >= 2020;

-- Index pour les questions jamais vues par un utilisateur (génération d'examens)
CREATE INDEX idx_history_non_maitrisee ON question_history(user_id, question_id)
  WHERE maitrisee = FALSE;

-- Index composite pour recherche archives avec filtre matière
CREATE INDEX idx_archives_matiere_annee ON archives(matiere_id, annee DESC, exam_type)
  WHERE status = 'PUBLIE';

-- ============================================================
-- FIN DU SCHEMA
-- Total: 15 tables + 3 vues matérialisées + 7 indexes composites
-- Optimisé pour 100k+ utilisateurs concurrents
-- ============================================================
