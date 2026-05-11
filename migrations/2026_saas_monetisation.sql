-- ================================================================
-- RÉUSSITE+ — Migration SaaS Monétisation
-- Ad Manager + API Keys + Analytics
-- ================================================================

-- Publicités internes
CREATE TABLE IF NOT EXISTS publicites (
  id           CHAR(36)     PRIMARY KEY DEFAULT (UUID()),
  titre        VARCHAR(100) NOT NULL,
  description  TEXT,
  image_url    TEXT,
  lien_url     TEXT,
  cta_texte    VARCHAR(60)  DEFAULT 'En savoir plus',
  position     ENUM('BANNER_TOP','SIDEBAR','FEED','BOTTOM') DEFAULT 'FEED',
  plans_cibles JSON         DEFAULT '["GRATUIT"]' COMMENT 'Plans qui voient cette pub',
  pages_cibles JSON         DEFAULT '["*"]'       COMMENT 'Pages ciblées (* = toutes)',
  actif        TINYINT(1)   DEFAULT 1,
  date_debut   DATE,
  date_fin     DATE,
  budget_cdf   DECIMAL(12,2) DEFAULT 0,
  annonceur    VARCHAR(100),
  created_by   CHAR(36),
  created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_actif (actif),
  INDEX idx_position (position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Impressions et clics
CREATE TABLE IF NOT EXISTS ad_impressions (
  id           CHAR(36)   PRIMARY KEY DEFAULT (UUID()),
  pub_id       CHAR(36)   NOT NULL,
  user_id      CHAR(36),
  type         ENUM('IMPRESSION','CLICK') DEFAULT 'IMPRESSION',
  page         VARCHAR(200),
  ip_address   VARCHAR(45),
  user_agent   VARCHAR(255),
  created_at   DATETIME   DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pub (pub_id),
  INDEX idx_type (type),
  INDEX idx_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Clés API externes (partenaires)
CREATE TABLE IF NOT EXISTS api_keys (
  id           CHAR(36)     PRIMARY KEY DEFAULT (UUID()),
  nom          VARCHAR(100) NOT NULL,
  description  TEXT,
  api_key      VARCHAR(64)  NOT NULL UNIQUE,
  secret_hash  VARCHAR(255),
  permissions  JSON         DEFAULT '["read"]',
  rate_limit   INT          DEFAULT 1000 COMMENT 'Requêtes/heure',
  user_id      CHAR(36),
  is_active    TINYINT(1)   DEFAULT 1,
  last_used_at DATETIME,
  expires_at   DATETIME,
  created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_key (api_key),
  INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Données démo publicités
INSERT IGNORE INTO publicites (id, titre, description, image_url, lien_url, cta_texte, position, plans_cibles, actif, annonceur) VALUES
(UUID(), 'Passez à Premium', 'Débloquez examens illimités, archives et Coach IA personnalisé.', NULL, '/reussiteplus/tarifs.php', 'Voir les offres Premium', 'FEED', '["GRATUIT"]', 1, 'RÉUSSITE+'),
(UUID(), 'Plan École — Pour vos établissements', 'Gérez jusqu\'à 50 élèves, enseignants IA et bulletins automatiques.', NULL, '/reussiteplus/tarifs.php?plan=ECOLE', 'Découvrir le Plan École', 'BANNER_TOP', '["GRATUIT","BASIQUE"]', 1, 'RÉUSSITE+');
