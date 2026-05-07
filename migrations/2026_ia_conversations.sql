-- ============================================================
-- TABLE : IA_CONVERSATIONS (Historique IA par utilisateur)
-- ============================================================
CREATE TABLE IF NOT EXISTS ia_conversations (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    CHAR(36) NOT NULL,
  messages   JSON NOT NULL,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES user_profiles(id)
);
CREATE INDEX idx_ia_conv_user ON ia_conversations(user_id);
