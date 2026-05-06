-- ============================================================
-- RÉUSSITE+ | Schéma Base de Données Complet (MySQL/MariaDB)
-- République Démocratique du Congo — Plateforme EdTech
-- Optimisé pour MariaDB 10.4+ / MySQL 8+
-- ============================================================

-- ============================================================
-- ENUMS (Types personnalisés)
-- ============================================================

-- Types d'examens nationaux en RDC
CREATE TABLE IF NOT EXISTS enums_exam_type (
  value ENUM('ENAFEP','TENASOSP','EXAMEN_ETAT','DIOCESAIN','AUTRE') NOT NULL PRIMARY KEY
);

-- Sessions d'examens
CREATE TABLE IF NOT EXISTS enums_session_type (
  value ENUM('ORDINAIRE','RATTRAPAGE','SPECIALE') NOT NULL PRIMARY KEY
);

-- Niveaux de difficulté pedagogique
CREATE TABLE IF NOT EXISTS enums_difficulty_level (
  value ENUM('DEBUTANT','ELEMENTAIRE','INTERMEDIAIRE','AVANCE','EXPERT') NOT NULL PRIMARY KEY
);

-- Types de questions
CREATE TABLE IF NOT EXISTS enums_question_type (
  value ENUM('QCM','VRAI_FAUX','REPONSE_COURTE','REPONSE_LONGUE','CALCUL','SCHEMA','PROBLEME') NOT NULL PRIMARY KEY
);

-- Status des contenus
CREATE TABLE IF NOT EXISTS enums_content_status (
  value ENUM('BROUILLON','REVISION','PUBLIE','ARCHIVE','SUPPRIME') NOT NULL PRIMARY KEY
);

-- Status des utilisateurs
CREATE TABLE IF NOT EXISTS enums_user_role (
  value ENUM('ELEVE','ENSEIGNANT','ADMIN_ECOLE','SUPER_ADMIN','MODERATEUR') NOT NULL PRIMARY KEY
);

-- Status des abonnements
CREATE TABLE IF NOT EXISTS enums_subscription_plan (
  value ENUM('GRATUIT','BASIQUE','PREMIUM','ECOLE') NOT NULL PRIMARY KEY
);

-- ============================================================
-- TABLE : PROVINCES (Données géographiques RDC)
-- ============================================================
CREATE TABLE IF NOT EXISTS provinces (
  id            CHAR(36) PRIMARY KEY,
  code          VARCHAR(10) NOT NULL UNIQUE,
  nom           VARCHAR(100) NOT NULL,
  region        VARCHAR(50),
  chef_lieu     VARCHAR(100),
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_provinces_code ON provinces(code);
CREATE INDEX idx_provinces_nom ON provinces(nom);

-- ============================================================
-- TABLE : UTILISATEURS PROFILES
-- ============================================================
CREATE TABLE IF NOT EXISTS user_profiles (
  id                CHAR(36) PRIMARY KEY,
  nom               VARCHAR(100) NOT NULL,
  prenom            VARCHAR(100) NOT NULL,
  date_naissance    DATE,
  sexe              CHAR(1) CHECK (sexe IN ('M', 'F')),
  photo_url         TEXT,
  province_id       CHAR(36),
  ville             VARCHAR(100),
  ecole             VARCHAR(200),
  classe            VARCHAR(50),
  role              ENUM('ELEVE','ENSEIGNANT','ADMIN_ECOLE','SUPER_ADMIN','MODERATEUR') NOT NULL DEFAULT 'ELEVE',
  plan              ENUM('GRATUIT','BASIQUE','PREMIUM','ECOLE') NOT NULL DEFAULT 'GRATUIT',
  plan_expire_at    DATETIME,
  langue            VARCHAR(5) DEFAULT 'fr',
  theme             VARCHAR(20) DEFAULT 'light',
  notifications_email BOOLEAN DEFAULT TRUE,
  notifications_push  BOOLEAN DEFAULT TRUE,
  total_examens     INT DEFAULT 0,
  total_questions   INT DEFAULT 0,
  score_moyen       DECIMAL(5,2) DEFAULT 0.00,
  streak_jours      INT DEFAULT 0,
  derniere_activite DATETIME,
  created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT valid_score CHECK (score_moyen >= 0 AND score_moyen <= 100),
  FOREIGN KEY (province_id) REFERENCES provinces(id)
);
CREATE INDEX idx_users_role ON user_profiles(role);
CREATE INDEX idx_users_province ON user_profiles(province_id);
CREATE INDEX idx_users_plan ON user_profiles(plan);
CREATE INDEX idx_users_nom ON user_profiles(nom, prenom);
CREATE INDEX idx_users_activite ON user_profiles(derniere_activite);

-- ============================================================
-- TABLE : MATIERES (Disciplines scolaires)
-- ============================================================
CREATE TABLE IF NOT EXISTS matieres (
  id            CHAR(36) PRIMARY KEY,
  code          VARCHAR(20) NOT NULL UNIQUE,
  nom           VARCHAR(100) NOT NULL,
  nom_court     VARCHAR(30),
  description   TEXT,
  couleur       VARCHAR(7),
  icone         VARCHAR(50),
  ordre         INT DEFAULT 0,
  actif         BOOLEAN DEFAULT TRUE,
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_matieres_code ON matieres(code);
CREATE INDEX idx_matieres_actif ON matieres(actif);

-- ============================================================
-- TABLE : CHAPITRES (Syllabus par matière)
-- (À compléter selon besoins)
-- ============================================================
-- ...
