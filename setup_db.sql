-- ============================================================
-- RÉUSSITE+ | Schéma MySQL/MariaDB
-- Plateforme EdTech — République Démocratique du Congo
-- Compatible XAMPP (MariaDB 10.4+)
-- ============================================================

CREATE DATABASE IF NOT EXISTS reussiteplus CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE reussiteplus;

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- PROVINCES
-- ============================================================
CREATE TABLE IF NOT EXISTS provinces (
  id         CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  code       VARCHAR(10) NOT NULL UNIQUE,
  nom        VARCHAR(100) NOT NULL,
  region     VARCHAR(50),
  chef_lieu  VARCHAR(100),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_code (code),
  FULLTEXT idx_nom (nom)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- UTILISATEURS
-- ============================================================
CREATE TABLE IF NOT EXISTS utilisateurs (
  id                   CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  email                VARCHAR(200) NOT NULL UNIQUE,
  password_hash        VARCHAR(255) NOT NULL,
  nom                  VARCHAR(100) NOT NULL,
  prenom               VARCHAR(100) NOT NULL,
  date_naissance       DATE,
  sexe                 CHAR(1),
  photo_url            TEXT,
  province_id          CHAR(36),
  ville                VARCHAR(100),
  ecole                VARCHAR(200),
  classe               VARCHAR(50),
  role                 ENUM('ELEVE','ENSEIGNANT','ADMIN_ECOLE','SUPER_ADMIN','MODERATEUR') DEFAULT 'ELEVE',
  plan                 ENUM('GRATUIT','BASIQUE','PREMIUM','ECOLE') DEFAULT 'GRATUIT',
  plan_expire_at       DATETIME,
  examens_mois         INT DEFAULT 0,
  examens_mois_reset   DATE,
  total_examens        INT DEFAULT 0,
  total_questions      INT DEFAULT 0,
  score_moyen          DECIMAL(5,2) DEFAULT 0.00,
  streak_jours         INT DEFAULT 0,
  derniere_activite    DATETIME,
  referral_code        VARCHAR(20) UNIQUE,
  referral_par         CHAR(36),
  is_active            TINYINT(1) DEFAULT 1,
  email_verified       TINYINT(1) DEFAULT 0,
  token_verification   VARCHAR(100),
  token_reset          VARCHAR(100),
  token_reset_expire   DATETIME,
  created_at           DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (province_id) REFERENCES provinces(id),
  INDEX idx_email (email),
  INDEX idx_role (role),
  INDEX idx_plan (plan),
  FULLTEXT idx_nom_prenom (nom, prenom)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- MATIERES
-- ============================================================
CREATE TABLE IF NOT EXISTS matieres (
  id          CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  code        VARCHAR(20) NOT NULL UNIQUE,
  nom         VARCHAR(100) NOT NULL,
  nom_court   VARCHAR(30),
  description TEXT,
  couleur     VARCHAR(7) DEFAULT '#007A5E',
  icone       VARCHAR(50) DEFAULT '📚',
  ordre       INT DEFAULT 0,
  actif       TINYINT(1) DEFAULT 1,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_code (code),
  INDEX idx_actif (actif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- CHAPITRES
-- ============================================================
CREATE TABLE IF NOT EXISTS chapitres (
  id          CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  matiere_id  CHAR(36) NOT NULL,
  parent_id   CHAR(36),
  code        VARCHAR(30),
  titre       VARCHAR(200) NOT NULL,
  description TEXT,
  ordre       INT DEFAULT 0,
  niveau      VARCHAR(50),
  actif       TINYINT(1) DEFAULT 1,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (matiere_id) REFERENCES matieres(id),
  FOREIGN KEY (parent_id) REFERENCES chapitres(id),
  INDEX idx_matiere (matiere_id),
  FULLTEXT idx_titre (titre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- ARCHIVES
-- ============================================================
CREATE TABLE IF NOT EXISTS archives (
  id               CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  exam_type        ENUM('ENAFEP','TENASOSP','EXAMEN_ETAT','DIOCESAIN','AUTRE') NOT NULL,
  annee            YEAR NOT NULL,
  session_type     ENUM('ORDINAIRE','RATTRAPAGE','SPECIALE') DEFAULT 'ORDINAIRE',
  matiere_id       CHAR(36) NOT NULL,
  province_id      CHAR(36),
  titre            VARCHAR(300) NOT NULL,
  description      TEXT,
  sujet_url        TEXT,
  corrige_url      TEXT,
  audio_url        TEXT,
  sujet_pages      INT,
  corrige_pages    INT,
  slug             VARCHAR(400) NOT NULL UNIQUE,
  meta_description TEXT,
  mots_cles        TEXT,
  vues             INT DEFAULT 0,
  telechargements  INT DEFAULT 0,
  premium_only     TINYINT(1) DEFAULT 0,
  status           ENUM('BROUILLON','REVISION','PUBLIE','ARCHIVE','SUPPRIME') DEFAULT 'PUBLIE',
  source           VARCHAR(200),
  verifie          TINYINT(1) DEFAULT 0,
  created_by       CHAR(36),
  created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (matiere_id) REFERENCES matieres(id),
  FOREIGN KEY (province_id) REFERENCES provinces(id),
  INDEX idx_nav (exam_type, annee, session_type, matiere_id),
  INDEX idx_status (status),
  INDEX idx_popularite (vues DESC, telechargements DESC),
  FULLTEXT idx_fts (titre, description, mots_cles)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- BANQUE DE QUESTIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS question_bank (
  id               CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  matiere_id       CHAR(36) NOT NULL,
  chapitre_id      CHAR(36),
  archive_id       CHAR(36),
  exam_type        ENUM('ENAFEP','TENASOSP','EXAMEN_ETAT','DIOCESAIN','AUTRE'),
  annee_source     YEAR,
  type_question    ENUM('QCM','VRAI_FAUX','REPONSE_COURTE','CALCUL','PROBLEME') DEFAULT 'QCM',
  enonce           TEXT NOT NULL,
  image_url        TEXT,
  difficulte       ENUM('DEBUTANT','ELEMENTAIRE','INTERMEDIAIRE','AVANCE','EXPERT') DEFAULT 'INTERMEDIAIRE',
  points           DECIMAL(4,1) DEFAULT 1.0,
  temps_suggere    INT DEFAULT 60,
  objectif         TEXT,
  source           VARCHAR(300),
  usage_count      INT DEFAULT 0,
  success_rate     DECIMAL(5,2),
  premium_only     TINYINT(1) DEFAULT 0,
  status           ENUM('BROUILLON','REVISION','PUBLIE','ARCHIVE','SUPPRIME') DEFAULT 'PUBLIE',
  created_by       CHAR(36),
  created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (matiere_id) REFERENCES matieres(id),
  FOREIGN KEY (chapitre_id) REFERENCES chapitres(id),
  FOREIGN KEY (archive_id) REFERENCES archives(id),
  INDEX idx_matiere (matiere_id),
  INDEX idx_difficulte (difficulte, matiere_id),
  FULLTEXT idx_fts (enonce)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- OPTIONS DE RÉPONSE (QCM)
-- ============================================================
CREATE TABLE IF NOT EXISTS question_options (
  id              CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  question_id     CHAR(36) NOT NULL,
  lettre          CHAR(1) NOT NULL,
  texte           TEXT NOT NULL,
  est_correcte    TINYINT(1) DEFAULT 0,
  ordre           INT DEFAULT 0,
  explication     TEXT,
  selection_count INT DEFAULT 0,
  FOREIGN KEY (question_id) REFERENCES question_bank(id) ON DELETE CASCADE,
  UNIQUE KEY uq_question_lettre (question_id, lettre),
  INDEX idx_question (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SESSIONS D'EXAMEN (tentatives)
-- ============================================================
CREATE TABLE IF NOT EXISTS exam_sessions (
  id               CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  user_id          CHAR(36) NOT NULL,
  archive_id       CHAR(36),
  matiere_id       CHAR(36),
  exam_type        ENUM('ENAFEP','TENASOSP','EXAMEN_ETAT','DIOCESAIN','AUTRE','ENTRAINEMENT'),
  titre            VARCHAR(300),
  nb_questions     INT DEFAULT 0,
  score            DECIMAL(5,2),
  score_max        DECIMAL(5,2),
  pourcentage      DECIMAL(5,2),
  temps_passe      INT DEFAULT 0,
  temps_limite     INT,
  statut           ENUM('EN_COURS','TERMINE','ABANDONNE') DEFAULT 'EN_COURS',
  started_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
  finished_at      DATETIME,
  FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
  FOREIGN KEY (matiere_id) REFERENCES matieres(id),
  INDEX idx_user (user_id),
  INDEX idx_statut (statut),
  INDEX idx_date (started_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- RÉPONSES AUX QUESTIONS (dans une session)
-- ============================================================
CREATE TABLE IF NOT EXISTS exam_answers (
  id              CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  session_id      CHAR(36) NOT NULL,
  question_id     CHAR(36) NOT NULL,
  option_id       CHAR(36),
  reponse_texte   TEXT,
  est_correcte    TINYINT(1),
  points_obtenus  DECIMAL(4,1) DEFAULT 0,
  temps_reponse   INT,
  created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (session_id) REFERENCES exam_sessions(id) ON DELETE CASCADE,
  FOREIGN KEY (question_id) REFERENCES question_bank(id),
  INDEX idx_session (session_id),
  INDEX idx_question (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- ABONNEMENTS & PAIEMENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS abonnements (
  id              CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  user_id         CHAR(36) NOT NULL,
  plan            ENUM('BASIQUE','PREMIUM','ECOLE') NOT NULL,
  montant         DECIMAL(10,2) NOT NULL,
  devise          VARCHAR(5) DEFAULT 'CDF',
  methode_paiement ENUM('MPESA','AIRTEL_MONEY','ORANGE_MONEY','CARTE','VIREMENT','ADMIN') NOT NULL,
  reference_paiement VARCHAR(100),
  telephone       VARCHAR(20),
  statut          ENUM('EN_ATTENTE','CONFIRME','ECHEC','REMBOURSE') DEFAULT 'EN_ATTENTE',
  date_debut      DATE,
  date_fin        DATE,
  duree_mois      INT DEFAULT 1,
  code_promo      VARCHAR(30),
  remise          DECIMAL(5,2) DEFAULT 0,
  notes           TEXT,
  confirmed_by    CHAR(36),
  confirmed_at    DATETIME,
  created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES utilisateurs(id),
  INDEX idx_user (user_id),
  INDEX idx_statut (statut),
  INDEX idx_date (date_debut, date_fin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- CODES PROMO
-- ============================================================
CREATE TABLE IF NOT EXISTS codes_promo (
  id              CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  code            VARCHAR(30) NOT NULL UNIQUE,
  description     VARCHAR(200),
  type_remise     ENUM('POURCENTAGE','MONTANT_FIXE') DEFAULT 'POURCENTAGE',
  valeur_remise   DECIMAL(8,2) NOT NULL,
  plan_applicable ENUM('BASIQUE','PREMIUM','ECOLE','TOUS') DEFAULT 'TOUS',
  nb_utilisations INT DEFAULT 0,
  nb_max          INT,
  date_expiration DATETIME,
  actif           TINYINT(1) DEFAULT 1,
  created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_code (code),
  INDEX idx_actif (actif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- PROGRESSION UTILISATEUR (par matière)
-- ============================================================
CREATE TABLE IF NOT EXISTS user_progression (
  id              CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  user_id         CHAR(36) NOT NULL,
  matiere_id      CHAR(36) NOT NULL,
  questions_vues  INT DEFAULT 0,
  bonnes_reponses INT DEFAULT 0,
  mauvaises_rep   INT DEFAULT 0,
  score_moyen     DECIMAL(5,2) DEFAULT 0,
  derniere_session DATETIME,
  updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
  FOREIGN KEY (matiere_id) REFERENCES matieres(id),
  UNIQUE KEY uq_user_matiere (user_id, matiere_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- ACTIVITÉ QUOTIDIENNE (streak)
-- ============================================================
CREATE TABLE IF NOT EXISTS activite_journaliere (
  id       CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  user_id  CHAR(36) NOT NULL,
  date_act DATE NOT NULL,
  examens  INT DEFAULT 0,
  questions INT DEFAULT 0,
  FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
  UNIQUE KEY uq_user_date (user_id, date_act),
  INDEX idx_user_date (user_id, date_act DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- NOTIFICATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
  id         CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  user_id    CHAR(36) NOT NULL,
  type       ENUM('SYSTEME','PAIEMENT','RESULTAT','PROMO','RAPPEL') DEFAULT 'SYSTEME',
  titre      VARCHAR(200) NOT NULL,
  message    TEXT NOT NULL,
  lien       VARCHAR(300),
  lu         TINYINT(1) DEFAULT 0,
  lu_at      DATETIME DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
  INDEX idx_user_lu (user_id, lu),
  INDEX idx_created (created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SIGNETS (favoris)
-- ============================================================
CREATE TABLE IF NOT EXISTS signets (
  id          CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  user_id     CHAR(36) NOT NULL,
  archive_id  CHAR(36),
  question_id CHAR(36),
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
  FOREIGN KEY (archive_id) REFERENCES archives(id) ON DELETE CASCADE,
  FOREIGN KEY (question_id) REFERENCES question_bank(id) ON DELETE CASCADE,
  UNIQUE KEY uq_user_archive (user_id, archive_id),
  UNIQUE KEY uq_user_question (user_id, question_id),
  INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- LOGS ADMIN
-- ============================================================
CREATE TABLE IF NOT EXISTS admin_logs (
  id         CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  user_id    CHAR(36),
  action     VARCHAR(100) NOT NULL,
  table_name VARCHAR(100),
  record_id  VARCHAR(100),
  details    JSON,
  ip_address VARCHAR(45),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_id),
  INDEX idx_action (action),
  INDEX idx_date (created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
