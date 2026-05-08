-- ================================================================
-- RÉUSSITE+ — Migration : Correction IA et réponses texte devoirs
-- ================================================================

ALTER TABLE soumissions_devoirs
  ADD COLUMN IF NOT EXISTS reponse_texte  TEXT          AFTER commentaire,
  ADD COLUMN IF NOT EXISTS feedback_ia    TEXT          AFTER feedback,
  ADD COLUMN IF NOT EXISTS note_ia        DECIMAL(4,1)  AFTER note,
  ADD COLUMN IF NOT EXISTS corrige_par    CHAR(36)      AFTER feedback_ia,
  ADD COLUMN IF NOT EXISTS corrige_le     DATETIME      AFTER corrige_par;

-- Assurer que les colonnes note et feedback acceptent NULL
ALTER TABLE soumissions_devoirs
  MODIFY COLUMN note     DECIMAL(4,1) NULL DEFAULT NULL,
  MODIFY COLUMN feedback TEXT         NULL DEFAULT NULL;
