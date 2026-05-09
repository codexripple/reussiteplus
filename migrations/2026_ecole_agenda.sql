-- ================================================================
-- RÉUSSITE+ — Migration : Agenda quotidien + Enseignants complets
-- ================================================================

-- Table agenda quotidien élève
CREATE TABLE IF NOT EXISTS agenda_quotidien (
  id           CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  user_id      CHAR(36) NOT NULL,
  date_jour    DATE NOT NULL,
  type         ENUM('EXERCICE','DEVOIR','REVISION','COURS','QUIZ','RAPPEL') NOT NULL,
  titre        VARCHAR(255) NOT NULL,
  contenu      TEXT DEFAULT NULL,
  matiere_id   CHAR(36) DEFAULT NULL,
  question_id  CHAR(36) DEFAULT NULL,
  statut       ENUM('A_FAIRE','EN_COURS','FAIT','IGNORE') DEFAULT 'A_FAIRE',
  ordre        INT DEFAULT 0,
  created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_date (user_id, date_jour),
  INDEX idx_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Réponses aux exercices quotidiens
CREATE TABLE IF NOT EXISTS agenda_reponses (
  id             CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  agenda_id      CHAR(36) NOT NULL,
  user_id        CHAR(36) NOT NULL,
  question_id    CHAR(36) DEFAULT NULL,
  reponse_texte  TEXT DEFAULT NULL,
  option_choisie CHAR(1) DEFAULT NULL,
  est_correcte   TINYINT(1) DEFAULT NULL,
  repondu_le     DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_agenda_user (agenda_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Colonnes enseignants_ecole pour portail enseignant
ALTER TABLE enseignants_ecole
  ADD COLUMN IF NOT EXISTS user_id       CHAR(36)  NULL AFTER code_invitation,
  ADD COLUMN IF NOT EXISTS validated_at  DATETIME  NULL AFTER user_id,
  ADD COLUMN IF NOT EXISTS validated_by  CHAR(36)  NULL AFTER validated_at,
  ADD COLUMN IF NOT EXISTS statut_compte ENUM('EN_ATTENTE','VALIDE','REFUSE','SUSPENDU') DEFAULT 'EN_ATTENTE' AFTER validated_by;
