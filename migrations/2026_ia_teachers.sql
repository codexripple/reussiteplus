-- ================================================================
-- RÉUSSITE+ — Migration : Tables enseignants IA virtuels
-- ================================================================

-- Ratings des enseignants IA par les élèves
CREATE TABLE IF NOT EXISTS ia_teacher_ratings (
  id               CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  teacher_code     VARCHAR(50)  NOT NULL,
  student_id       CHAR(36)     NOT NULL,
  school_admin_id  CHAR(36)     NOT NULL,
  note             DECIMAL(2,1) NOT NULL CHECK (note BETWEEN 1 AND 5),
  clarte           DECIMAL(2,1) DEFAULT NULL COMMENT 'Clarté des explications /5',
  aide             DECIMAL(2,1) DEFAULT NULL COMMENT 'Aide apportée /5',
  commentaire      TEXT         DEFAULT NULL,
  created_at       DATETIME     DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_rating (teacher_code, student_id),
  INDEX idx_teacher (teacher_code),
  INDEX idx_school  (school_admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sessions de chat avec les enseignants IA (pour les stats)
CREATE TABLE IF NOT EXISTS ia_teacher_sessions (
  id               CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  teacher_code     VARCHAR(50) NOT NULL,
  student_id       CHAR(36)    NOT NULL,
  school_admin_id  CHAR(36)    DEFAULT NULL,
  created_at       DATETIME    DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_teacher_session (teacher_code),
  INDEX idx_student_session (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
