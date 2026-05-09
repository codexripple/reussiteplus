-- ================================================================
-- RÉUSSITE+ — Extension du catalogue de matières scolaires
-- Programme EPST — RDC
-- ================================================================

INSERT IGNORE INTO matieres (id, code, nom, nom_court, couleur, ordre, actif) VALUES
-- Sciences exactes (déjà partiellement)
(UUID(), 'svt',       'Sciences de la Vie et de la Terre',  'SVT',       '#16A34A', 11, 1),
-- Langues
(UUID(), 'espagnol',  'Espagnol',                            'Espagnol',  '#DC2626', 20, 1),
(UUID(), 'allemand',  'Allemand',                            'Allemand',  '#1D4ED8', 21, 1),
(UUID(), 'latin',     'Latin',                               'Latin',     '#92400E', 22, 1),
-- Sciences humaines
(UUID(), 'philo',     'Philosophie',                         'Philo',     '#7C3AED', 30, 1),
(UUID(), 'histoire',  'Histoire',                            'Histoire',  '#B45309', 31, 1),
(UUID(), 'geo',       'Géographie',                          'Géo',       '#0891B2', 32, 1),
(UUID(), 'sociologie','Sociologie',                          'Socio',     '#6B7280', 33, 1),
-- Citoyenneté et valeurs
(UUID(), 'edcivique', 'Éducation civique et morale',         'Éd. Civ.',  '#059669', 40, 1),
(UUID(), 'edvie',     'Éducation à la vie',                  'Éd. Vie',   '#DB2777', 41, 1),
(UUID(), 'religion',  'Religion',                            'Religion',  '#D97706', 42, 1),
-- Technologie & Informatique
(UUID(), 'info',      'Informatique',                        'Info',      '#0F172A', 50, 1),
(UUID(), 'progr',     'Initiation à la programmation',       'Progr.',    '#1E40AF', 51, 1),
(UUID(), 'culture_num','Culture numérique',                  'Num.',      '#0369A1', 52, 1),
-- Sciences économiques
(UUID(), 'ecopol',    'Économie politique',                  'Écopol',    '#C9972A', 60, 1),
(UUID(), 'gestion',   'Gestion',                             'Gestion',   '#9D174D', 61, 1),
(UUID(), 'compta',    'Comptabilité',                        'Compta',    '#065F46', 62, 1),
(UUID(), 'droit',     'Droit',                               'Droit',     '#1E3A5F', 63, 1),
-- Sciences appliquées
(UUID(), 'dessin',    'Dessin technique et scientifique',    'Dessin',    '#64748B', 70, 1),
(UUID(), 'techno',    'Technologie appliquée',               'Techno',    '#78350F', 71, 1),
(UUID(), 'socaf',     'SOCAF',                               'SOCAF',     '#701A75', 72, 1),
-- Sciences médicales et paramédicales
(UUID(), 'sante',     'Éducation à la santé',                'Santé',     '#10B981', 80, 1),
(UUID(), 'nutrition', 'Nutrition et diététique',             'Nutrition', '#F97316', 81, 1),
-- Mise à jour ordre pour les matières existantes (elles restent)
(UUID(), 'psyco',     'Psychologie',                         'Psycho',   '#8B5CF6', 34, 1),
(UUID(), 'litterature','Littérature africaine',              'Littérat.', '#047857', 25, 1);

-- Mise à jour des ordres existants
UPDATE matieres SET ordre=1  WHERE code='maths';
UPDATE matieres SET ordre=2  WHERE code='physique';
UPDATE matieres SET ordre=3  WHERE code='chimie';
UPDATE matieres SET ordre=4  WHERE code='biologie';
UPDATE matieres SET ordre=5  WHERE code='sciences';
UPDATE matieres SET ordre=10 WHERE code='francais';
UPDATE matieres SET ordre=12 WHERE code='anglais';
UPDATE matieres SET ordre=13 WHERE code='histgeo';
