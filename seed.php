<?php
/**
 * RÉUSSITE+ — Seeder de données de démonstration
 * Accessible uniquement depuis localhost
 */
if (php_sapi_name() !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'])) {
    http_response_code(403); die('Accès refusé.');
}
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/plain; charset=utf-8');

$pdo = db();
$pdo->exec("SET FOREIGN_KEY_CHECKS=0");

function seed_log(string $msg): void { echo "[" . date('H:i:s') . "] $msg\n"; ob_flush(); flush(); }
function make_uuid(): string {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0,0xffff),mt_rand(0,0xffff),mt_rand(0,0xffff),
        mt_rand(0,0x0fff)|0x4000,mt_rand(0,0x3fff)|0x8000,
        mt_rand(0,0xffff),mt_rand(0,0xffff),mt_rand(0,0xffff));
}

seed_log("🚀 Démarrage du seeder RÉUSSITE+\n");

/* ── 1. PROVINCES ──────────────────────────────────────────── */
seed_log("📍 Provinces...");
$pdo->exec("TRUNCATE TABLE provinces");
$provinces = [
    ['KIN','Kinshasa'],['KOC','Kongo-Central'],['KWA','Kwango'],['KWI','Kwilu'],
    ['MAN','Mai-Ndombe'],['KAS','Kasaï'],['KAC','Kasaï-Central'],['KAO','Kasaï-Oriental'],
    ['LOM','Lomami'],['SAN','Sankuru'],['MAI','Maniema'],['SKV','Sud-Kivu'],
    ['NKV','Nord-Kivu'],['ITU','Ituri'],['HUE','Haut-Uele'],['TSH','Tshopo'],
    ['BUE','Bas-Uele'],['NUB','Nord-Ubangi'],['MON','Mongala'],['SUB','Sud-Ubangi'],
    ['EQU','Équateur'],['TSU','Tshuapa'],['TAN','Tanganyika'],['HLO','Haut-Lomami'],
    ['LUA','Lualaba'],['HKA','Haut-Katanga'],
];
$stP = $pdo->prepare("INSERT INTO provinces (id,code,nom) VALUES (UUID(),?,?)");
foreach ($provinces as [$c,$n]) $stP->execute([$c,$n]);
$provinceMap = [];
foreach ($pdo->query("SELECT id,code FROM provinces")->fetchAll(PDO::FETCH_ASSOC) as $r) $provinceMap[$r['code']]=$r['id'];
$kinshasaId = $provinceMap['KIN'];
seed_log("  ✓ " . count($provinces) . " provinces");

/* ── 2. MATIÈRES ───────────────────────────────────────────── */
seed_log("📚 Matières...");
$pdo->exec("TRUNCATE TABLE matieres");
$matieres = [
    ['maths',    'Mathématiques',   'Maths',   '#2563EB','🔢'],
    ['francais', 'Français',        'Français','#059669','📝'],
    ['sciences', 'Sciences',        'Sciences','#7C3AED','🔬'],
    ['histgeo',  'Histoire-Géo',    'H-Géo',   '#D97706','🌍'],
    ['chimie',   'Chimie',          'Chimie',  '#DC2626','⚗️'],
    ['physique', 'Physique',        'Physique','#0891B2','⚡'],
    ['biologie', 'Biologie',        'Bio',     '#16A34A','🧬'],
    ['anglais',  'Anglais',         'Anglais', '#9333EA','🇬🇧'],
];
$stM = $pdo->prepare("INSERT INTO matieres (id,code,nom,nom_court,couleur,icone,actif) VALUES (UUID(),?,?,?,?,?,1)");
foreach ($matieres as [$code,$nom,$court,$couleur,$icone]) $stM->execute([$code,$nom,$court,$couleur,$icone]);
$matiereMap = [];
foreach ($pdo->query("SELECT id,code FROM matieres")->fetchAll(PDO::FETCH_ASSOC) as $r) $matiereMap[$r['code']]=$r['id'];
seed_log("  ✓ " . count($matieres) . " matières");

/* ── 3. UTILISATEURS ───────────────────────────────────────── */
seed_log("👤 Utilisateurs...");
$pdo->exec("DELETE FROM utilisateurs WHERE email IN ('demo@reussiteplus.cd','admin@reussiteplus.cd','prof@reussiteplus.cd')");
$demoId  = make_uuid();
$adminId = make_uuid();
$profId  = make_uuid();
$stU = $pdo->prepare("INSERT INTO utilisateurs (id,prenom,nom,email,password_hash,role,plan,province_id,classe,score_moyen,total_examens,total_questions,referral_code,is_active,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1,NOW())");
$stU->execute([$demoId, 'Amani','Kanda',   'demo@reussiteplus.cd',  password_hash('Demo1234!', PASSWORD_BCRYPT,['cost'=>12]),'ELEVE',       'BASIQUE', $kinshasaId,'Terminale',67.5,12,120,'AMANI2025']);
$stU->execute([$adminId,'Super','Admin',   'admin@reussiteplus.cd', password_hash('Admin2025!',PASSWORD_BCRYPT,['cost'=>12]),'SUPER_ADMIN',  'GRATUIT', $kinshasaId, null,       0,    0,   0,  'ADMIN2025']);
$stU->execute([$profId, 'Marie','Kalombo', 'prof@reussiteplus.cd',  password_hash('Prof2025!', PASSWORD_BCRYPT,['cost'=>12]),'ENSEIGNANT',   'PREMIUM', $kinshasaId, null,       72.0, 5,  45, 'MARIE2025']);
seed_log("  ✓ 3 utilisateurs (demo / admin / prof)");

/* ── 4. ARCHIVES ───────────────────────────────────────────── */
seed_log("📂 Archives...");
$pdo->exec("TRUNCATE TABLE archives");
$archivesData = [
    ['ENAFEP 2024 — Mathématiques', 2024,'ENAFEP',    'maths',    0],
    ['ENAFEP 2024 — Français',      2024,'ENAFEP',    'francais', 0],
    ['ENAFEP 2023 — Sciences',      2023,'ENAFEP',    'sciences', 0],
    ['ENAFEP 2023 — Mathématiques', 2023,'ENAFEP',    'maths',    0],
    ['TENASOSP 2024 — Maths',       2024,'TENASOSP',  'maths',    1],
    ['TENASOSP 2024 — Chimie',      2024,'TENASOSP',  'chimie',   1],
    ['TENASOSP 2023 — Physique',    2023,'TENASOSP',  'physique', 1],
    ['Examen État 2024 — Maths',    2024,'EXAMEN_ETAT','maths',   1],
    ['Examen État 2024 — Français', 2024,'EXAMEN_ETAT','francais',1],
    ['Examen État 2023 — Biologie', 2023,'EXAMEN_ETAT','biologie',1],
    ['ENAFEP 2022 — Histoire-Géo',  2022,'ENAFEP',    'histgeo',  0],
    ['ENAFEP 2022 — Anglais',       2022,'ENAFEP',    'anglais',  0],
    ['Examen État 2022 — Chimie',   2022,'EXAMEN_ETAT','chimie',  1],
    ['TENASOSP 2022 — Biologie',    2022,'TENASOSP',  'biologie', 1],
    ['ENAFEP 2021 — Mathématiques', 2021,'ENAFEP',    'maths',    0],
];
$stA = $pdo->prepare("INSERT INTO archives (id,titre,annee,exam_type,matiere_id,description,premium_only,slug,status) VALUES (UUID(),?,?,?,?,?,?,?,?)");
foreach ($archivesData as [$titre,$annee,$type,$mat,$premium]) {
    $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', iconv('UTF-8','ASCII//TRANSLIT',$titre))) . '-' . uniqid();
    $stA->execute([$titre,$annee,$type,$matiereMap[$mat],"Épreuve officielle $type $annee.",$premium,$slug,'PUBLIE']);
}
seed_log("  ✓ " . count($archivesData) . " archives");

/* ── 5. QUESTIONS QCM ──────────────────────────────────────── */
seed_log("🧠 Questions QCM...");
$pdo->exec("TRUNCATE TABLE question_options");
$pdo->exec("TRUNCATE TABLE question_bank");

$stQ = $pdo->prepare("INSERT INTO question_bank (id,matiere_id,enonce,difficulte,exam_type,status) VALUES (UUID(),?,?,?,?,?)");
$stO = $pdo->prepare("INSERT INTO question_options (id,question_id,lettre,texte,est_correcte) VALUES (UUID(),?,?,?,?)");

function insert_question($pdo, $stQ, $stO, $matId, $enonce, $diff, $src, $opts) {
    $stQ->execute([$matId, $enonce, $diff, $src, 'PUBLIE']);
    $row = $pdo->prepare("SELECT id FROM question_bank WHERE enonce=? ORDER BY created_at DESC LIMIT 1");
    $row->execute([$enonce]);
    $qId = $row->fetchColumn();
    foreach ($opts as [$l,$t,$ok]) $stO->execute([$qId, $l, $t, (int)$ok]);
    return $qId;
}

$total_q = 0;

/* ═══════════════════════════════════════════
   MATHÉMATIQUES
═══════════════════════════════════════════ */
$mat = $matiereMap['maths'];
$qs_maths = [
  // DÉBUTANT
  ['Quel est le résultat de 8 × 7 ?',                                              'DEBUTANT',      'ENAFEP',     [['A','56',1],['B','48',0],['C','63',0],['D','54',0]]],
  ['Quelle est la valeur de x dans : x + 12 = 20 ?',                              'DEBUTANT',      'ENAFEP',     [['A','8',1], ['B','10',0],['C','6',0], ['D','32',0]]],
  ['Combien vaut 15% de 200 ?',                                                    'DEBUTANT',      'ENAFEP',     [['A','30',1],['B','20',0],['C','25',0],['D','15',0]]],
  ['Quel est le PGCD de 12 et 18 ?',                                               'DEBUTANT',      'ENAFEP',     [['A','6',1], ['B','3',0], ['C','4',0], ['D','9',0]]],
  // ÉLÉMENTAIRE
  ['Calculer : 3² + 4²',                                                           'ELEMENTAIRE',   'ENAFEP',     [['A','25',1],['B','49',0],['C','7',0], ['D','12',0]]],
  ['Résoudre : 2x - 5 = 11',                                                       'ELEMENTAIRE',   'ENAFEP',     [['A','8',1], ['B','6',0], ['C','3',0], ['D','16',0]]],
  ['Quel est le résultat de 15² - 10² ?',                                          'ELEMENTAIRE',   'ENAFEP',     [['A','125',1],['B','225',0],['C','100',0],['D','175',0]]],
  ['Si f(x) = 3x² + 2x - 1, calculer f(2).',                                      'ELEMENTAIRE',   'ENAFEP',     [['A','15',1],['B','11',0],['C','13',0],['D','9',0]]],
  // INTERMÉDIAIRE
  ['Calculer la valeur de sin(30°).',                                               'INTERMEDIAIRE', 'TENASOSP',   [['A','0,5',1],['B','0,866',0],['C','1',0],['D','0,707',0]]],
  ['Résoudre l\'équation du second degré : x² - 5x + 6 = 0',                      'INTERMEDIAIRE', 'TENASOSP',   [['A','x = 2 ou x = 3',1],['B','x = 1 ou x = 6',0],['C','x = -2 ou -3',0],['D','x = 5 ou x = 1',0]]],
  ['Dans un triangle rectangle, si sin(A) = 3/5, quelle est la valeur de cos(A) ?','INTERMEDIAIRE', 'TENASOSP',   [['A','4/5',1],['B','3/4',0],['C','5/3',0],['D','1/2',0]]],
  ['Quelle est la dérivée de f(x) = 2x³ - 4x + 1 ?',                              'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','6x² - 4',1],['B','2x² - 4',0],['C','6x - 4',0],['D','6x² + 1',0]]],
  // AVANCÉ
  ['Calculer la dérivée de f(x) = x³ - 3x.',                                       'AVANCE',        'EXAMEN_ETAT',[['A','3x² - 3',1],['B','x² - 3',0],['C','3x - 3',0],['D','3x²',0]]],
  ['Calculer la limite de (x² - 4)/(x - 2) quand x → 2.',                         'AVANCE',        'EXAMEN_ETAT',[['A','4',1],  ['B','0',0], ['C','∞',0],  ['D','2',0]]],
  ['∫₀¹ x² dx est égal à :',                                                       'AVANCE',        'EXAMEN_ETAT',[['A','1/3',1],['B','1/2',0],['C','1',0],  ['D','2/3',0]]],
  // EXPERT
  ['La somme de la série géométrique 1 + 1/2 + 1/4 + ... converge vers :',        'EXPERT',        'EXAMEN_ETAT',[['A','2',1],  ['B','1',0], ['C','∞',0],  ['D','4',0]]],
  ['Résoudre dans ℝ : |2x - 3| < 5',                                               'EXPERT',        'EXAMEN_ETAT',[['A','-1 < x < 4',1],['B','x > 4',0],['C','-5 < x < 5',0],['D','x < -1',0]]],
];
foreach ($qs_maths as [$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$mat,$e,$d,$s,$o); $total_q++; }

/* ═══════════════════════════════════════════
   FRANÇAIS
═══════════════════════════════════════════ */
$mat = $matiereMap['francais'];
$qs_fr = [
  // DÉBUTANT
  ['Quel est le pluriel de "œil" en français ?',                                   'DEBUTANT',      'ENAFEP',     [['A','yeux',1],['B','œils',0],['C','yaux',0],['D','œillets',0]]],
  ['Conjuguer "aller" au présent de l\'indicatif, 1ère personne du singulier :',   'DEBUTANT',      'ENAFEP',     [['A','je vais',1],['B','je alle',0],['C','j\'alle',0],['D','je vas',0]]],
  ['Quel est l\'antonyme de "rapide" ?',                                            'DEBUTANT',      'ENAFEP',     [['A','lent',1],['B','vite',0],['C','agile',0],['D','prompt',0]]],
  // ÉLÉMENTAIRE
  ['Identifier la nature du mot souligné dans : "Il court vite."',                  'ELEMENTAIRE',   'ENAFEP',     [['A','Adverbe',1],['B','Adjectif',0],['C','Verbe',0],['D','Nom',0]]],
  ['Quel est le synonyme de "perspicace" ?',                                        'ELEMENTAIRE',   'ENAFEP',     [['A','clairvoyant',1],['B','stupide',0],['C','distrait',0],['D','confus',0]]],
  ['Quel est l\'antonyme de "prospère" ?',                                          'ELEMENTAIRE',   'ENAFEP',     [['A','décadent',1],['B','riche',0],['C','heureux',0],['D','florissant',0]]],
  // INTERMÉDIAIRE
  ['"Le cœur de Pierre est de pierre" — quelle figure de style ?',                 'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','Antanaclase',1],['B','Métaphore',0],['C','Oxymore',0],['D','Allitération',0]]],
  ['"La lune était sereine et jouait sur les flots" (Hugo) — figure de style ?',   'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','Personnification',1],['B','Comparaison',0],['C','Antithèse',0],['D','Hyperbole',0]]],
  ['Dans quelle phrase le pronom "y" remplace-t-il un complément de lieu ?',        'INTERMEDIAIRE', 'ENAFEP',     [['A','J\'y vais souvent.',1],['B','Je l\'y ai vu.',0],['C','Il y pense.',0],['D','Je n\'y crois pas.',0]]],
  // AVANCÉ
  ['Quel est le mode verbal de "Bien que tu sois parti tôt" ?',                     'AVANCE',        'EXAMEN_ETAT',[['A','Subjonctif',1],['B','Indicatif',0],['C','Conditionnel',0],['D','Infinitif',0]]],
  ['Identifier le COD dans : "Marie offre une fleur à son père."',                  'AVANCE',        'EXAMEN_ETAT',[['A','une fleur',1],['B','à son père',0],['C','Marie',0],['D','offre',0]]],
  // EXPERT
  ['Quelle est la particularité du discours indirect libre ?',                      'EXPERT',        'EXAMEN_ETAT',[['A','Mélange voix narrateur et personnage sans verbe introducteur',1],['B','Utilise des guillemets',0],['C','Verbe de parole obligatoire',0],['D','Pas de pronom personnel',0]]],
];
foreach ($qs_fr as [$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$mat,$e,$d,$s,$o); $total_q++; }

/* ═══════════════════════════════════════════
   SCIENCES
═══════════════════════════════════════════ */
$mat = $matiereMap['sciences'];
$qs_sc = [
  ['Quelle est la formule chimique de l\'eau ?',                                   'DEBUTANT',      'ENAFEP',     [['A','H₂O',1], ['B','HO₂',0],['C','H₂O₂',0],['D','OH',0]]],
  ['Quel organe produit la bile dans le corps humain ?',                            'ELEMENTAIRE',   'ENAFEP',     [['A','Le foie',1],['B','Le pancréas',0],['C','L\'estomac',0],['D','Les reins',0]]],
  ['Combien d\'os compte le corps humain adulte ?',                                 'ELEMENTAIRE',   'ENAFEP',     [['A','206',1], ['B','208',0],['C','198',0],['D','215',0]]],
  ['Quel est le rôle principal des globules rouges ?',                              'INTERMEDIAIRE', 'TENASOSP',   [['A','Transporter l\'oxygène',1],['B','Défense immunitaire',0],['C','Coagulation',0],['D','Digestion',0]]],
  ['Dans la photosynthèse, les plantes utilisent CO₂ + H₂O pour produire :',       'INTERMEDIAIRE', 'TENASOSP',   [['A','Glucose et O₂',1],['B','ATP et CO₂',0],['C','Eau et azote',0],['D','Amidon et N₂',0]]],
  ['Quelle est la vitesse de propagation du son dans l\'air à 20°C ?',             'AVANCE',        'EXAMEN_ETAT',[['A','340 m/s',1],['B','300 m/s',0],['C','1500 m/s',0],['D','30 m/s',0]]],
  ['Quelle loi décrit la relation entre pression et volume d\'un gaz parfait ?',   'AVANCE',        'EXAMEN_ETAT',[['A','Loi de Boyle-Mariotte',1],['B','Loi de Joule',0],['C','Loi d\'Ohm',0],['D','Loi de Newton',0]]],
  ['Quelle est la formule de la loi d\'Ohm ?',                                     'ELEMENTAIRE',   'ENAFEP',     [['A','U = R × I',1],['B','P = U × I',0],['C','I = U × R',0],['D','R = U + I',0]]],
];
foreach ($qs_sc as [$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$mat,$e,$d,$s,$o); $total_q++; }

/* ═══════════════════════════════════════════
   CHIMIE
═══════════════════════════════════════════ */
$mat = $matiereMap['chimie'];
$qs_ch = [
  ['Quel est le numéro atomique du carbone ?',                                      'ELEMENTAIRE',   'TENASOSP',   [['A','6',1],  ['B','8',0], ['C','12',0], ['D','4',0]]],
  ['Quelle est la valeur du pH d\'une solution neutre à 25°C ?',                   'INTERMEDIAIRE', 'TENASOSP',   [['A','7',1],  ['B','0',0], ['C','14',0], ['D','6,5',0]]],
  ['Combien d\'électrons peut contenir la couche M ?',                              'AVANCE',        'TENASOSP',   [['A','18',1], ['B','8',0], ['C','2',0],  ['D','32',0]]],
  ['La formule du dioxyde de carbone est :',                                        'DEBUTANT',      'ENAFEP',     [['A','CO₂',1],['B','CO',0],['C','C₂O',0],['D','C₂O₃',0]]],
  ['L\'acide chlorhydrique a pour formule :',                                       'ELEMENTAIRE',   'TENASOSP',   [['A','HCl',1],['B','H₂Cl',0],['C','NaCl',0],['D','HCl₂',0]]],
  ['Que se forme-t-il lors de la neutralisation d\'un acide par une base ?',       'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','Sel + Eau',1],['B','Gaz + Précipité',0],['C','Hydroxyde',0],['D','Oxyde',0]]],
  ['Quel est le symbole chimique du Potassium ?',                                   'DEBUTANT',      'ENAFEP',     [['A','K',1],  ['B','P',0], ['C','Po',0], ['D','Pt',0]]],
  ['Dans CH₄, le carbone est en état d\'hybridation :',                             'EXPERT',        'EXAMEN_ETAT',[['A','sp³',1], ['B','sp²',0],['C','sp',0], ['D','d²sp³',0]]],
  ['L\'électronégativité la plus élevée appartient à :',                            'AVANCE',        'EXAMEN_ETAT',[['A','Fluor (F)',1],['B','Oxygène (O)',0],['C','Chlore (Cl)',0],['D','Azote (N)',0]]],
];
foreach ($qs_ch as [$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$mat,$e,$d,$s,$o); $total_q++; }

/* ═══════════════════════════════════════════
   PHYSIQUE
═══════════════════════════════════════════ */
$mat = $matiereMap['physique'];
$qs_ph = [
  ['Quelle est l\'unité de la force dans le SI ?',                                  'DEBUTANT',      'ENAFEP',     [['A','Newton (N)',1],['B','Pascal',0],['C','Joule',0],['D','Watt',0]]],
  ['Quelle est la vitesse de la lumière dans le vide ?',                            'ELEMENTAIRE',   'EXAMEN_ETAT',[['A','3 × 10⁸ m/s',1],['B','3 × 10⁶ m/s',0],['C','3 × 10¹⁰ m/s',0],['D','3 × 10⁴ m/s',0]]],
  ['Quelle formule exprime l\'énergie cinétique ?',                                 'ELEMENTAIRE',   'TENASOSP',   [['A','Ec = ½mv²',1],['B','Ec = mv',0],['C','Ec = mgh',0],['D','Ec = mv²',0]]],
  ['La loi d\'Ohm est U = R × I. Si R = 10Ω et I = 2A, U vaut :',                 'ELEMENTAIRE',   'ENAFEP',     [['A','20 V',1],['B','5 V',0],['C','12 V',0],['D','0,2 V',0]]],
  ['Quelle est la période d\'un pendule simple de longueur L = 1 m (g ≈ 10 m/s²)?','INTERMEDIAIRE', 'TENASOSP',   [['A','≈ 2 s',1],['B','≈ 1 s',0],['C','≈ 4 s',0],['D','≈ 0,5 s',0]]],
  ['La deuxième loi de Newton : F = m × a. Si m = 5 kg et a = 3 m/s², F vaut :',  'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','15 N',1], ['B','8 N',0],['C','1,67 N',0],['D','2 N',0]]],
  ['L\'unité de la pression dans le SI est :',                                      'ELEMENTAIRE',   'ENAFEP',     [['A','Pascal (Pa)',1],['B','Newton',0],['C','Bar',0],['D','Joule',0]]],
  ['Lors d\'un choc élastique, quelle grandeur est conservée ?',                   'AVANCE',        'EXAMEN_ETAT',[['A','L\'énergie cinétique et la quantité de mouvement',1],['B','Seulement l\'énergie',0],['C','Seulement la QM',0],['D','Aucune des deux',0]]],
  ['La chaleur se propage par trois modes. Lequel implique un support matériel ?', 'INTERMEDIAIRE', 'TENASOSP',   [['A','Conduction et convection',1],['B','Rayonnement',0],['C','Induction',0],['D','Fusion',0]]],
  ['Quelle est la fréquence d\'un signal dont la période est T = 0,02 s ?',        'AVANCE',        'EXAMEN_ETAT',[['A','50 Hz',1],['B','20 Hz',0],['C','0,02 Hz',0],['D','200 Hz',0]]],
];
foreach ($qs_ph as [$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$mat,$e,$d,$s,$o); $total_q++; }

/* ═══════════════════════════════════════════
   BIOLOGIE
═══════════════════════════════════════════ */
$mat = $matiereMap['biologie'];
$qs_bio = [
  ['Quelle est la fonction principale des mitochondries ?',                         'INTERMEDIAIRE', 'TENASOSP',   [['A','Production d\'ATP',1],['B','Synthèse de protéines',0],['C','Division cellulaire',0],['D','Stockage de l\'ADN',0]]],
  ['Combien de chromosomes a une cellule humaine diploïde ?',                       'ELEMENTAIRE',   'EXAMEN_ETAT',[['A','46',1], ['B','23',0],['C','48',0],['D','44',0]]],
  ['Quel est le rôle du ribosomes dans la cellule ?',                               'INTERMEDIAIRE', 'TENASOSP',   [['A','Synthèse des protéines',1],['B','Production d\'énergie',0],['C','Digestion cellulaire',0],['D','Transport des ions',0]]],
  ['Le code génétique est composé de triplets appelés :',                           'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','Codons',1],['B','Gènes',0],['C','Allèles',0],['D','Nucléotides',0]]],
  ['Quel est l\'organite responsable de la photosynthèse chez les plantes ?',       'ELEMENTAIRE',   'ENAFEP',     [['A','Chloroplaste',1],['B','Mitochondrie',0],['C','Noyau',0],['D','Réticulum',0]]],
  ['La méiose produit des cellules avec un nombre de chromosomes :',                'AVANCE',        'EXAMEN_ETAT',[['A','Réduit de moitié (n)',1],['B','Identique (2n)',0],['C','Doublé (4n)',0],['D','Multiplié (8n)',0]]],
  ['Quel type de liaison unit les bases azotées dans l\'ADN ?',                    'AVANCE',        'EXAMEN_ETAT',[['A','Liaisons hydrogène',1],['B','Liaisons ioniques',0],['C','Liaisons covalentes',0],['D','Liaisons peptidiques',0]]],
  ['L\'adénine (A) se lie spécifiquement avec :',                                  'INTERMEDIAIRE', 'TENASOSP',   [['A','Thymine (T)',1],['B','Guanine (G)',0],['C','Cytosine (C)',0],['D','Uracile (U)',0]]],
  ['Lors de la mitose, combien de cellules filles sont produites ?',                'DEBUTANT',      'ENAFEP',     [['A','2 cellules identiques',1],['B','4 cellules',0],['C','1 cellule',0],['D','2 cellules différentes',0]]],
  ['Quelle molécule transporte l\'oxygène dans le sang ?',                         'ELEMENTAIRE',   'ENAFEP',     [['A','Hémoglobine',1],['B','Albumine',0],['C','Fibrinogène',0],['D','Insuline',0]]],
];
foreach ($qs_bio as [$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$mat,$e,$d,$s,$o); $total_q++; }

/* ═══════════════════════════════════════════
   HISTOIRE-GÉOGRAPHIE
═══════════════════════════════════════════ */
$mat = $matiereMap['histgeo'];
$qs_hg = [
  ['En quelle année la RDC a-t-elle obtenu son indépendance ?',                    'DEBUTANT',      'ENAFEP',     [['A','1960',1],['B','1956',0],['C','1964',0],['D','1958',0]]],
  ['Quel est le plus grand fleuve de la RDC ?',                                    'DEBUTANT',      'ENAFEP',     [['A','Le Congo',1],['B','L\'Ubangi',0],['C','Le Kasaï',0],['D','Le Lomami',0]]],
  ['Quelle est la capitale de la RDC ?',                                           'DEBUTANT',      'ENAFEP',     [['A','Kinshasa',1],['B','Lubumbashi',0],['C','Kisangani',0],['D','Goma',0]]],
  ['Qui était le premier président de la RDC indépendante ?',                      'ELEMENTAIRE',   'ENAFEP',     [['A','Joseph Kasa-Vubu',1],['B','Patrice Lumumba',0],['C','Mobutu',0],['D','Laurent-Désiré Kabila',0]]],
  ['La RDC partage ses frontières avec combien de pays ?',                          'ELEMENTAIRE',   'ENAFEP',     [['A','9 pays',1],['B','7 pays',0],['C','6 pays',0],['D','11 pays',0]]],
  ['La Révolution française a eu lieu en :',                                        'ELEMENTAIRE',   'ENAFEP',     [['A','1789',1],['B','1776',0],['C','1815',0],['D','1793',0]]],
  ['Quelle organisation continentale regroupe les pays africains ?',                'ELEMENTAIRE',   'ENAFEP',     [['A','Union Africaine (UA)',1],['B','CEDEAO',0],['C','ONU',0],['D','SADC',0]]],
  ['Quelle est la population approximative de la RDC (2024) ?',                    'INTERMEDIAIRE', 'ENAFEP',     [['A','100 millions',1],['B','50 millions',0],['C','200 millions',0],['D','30 millions',0]]],
  ['Où se trouve le parc national des Virunga ?',                                   'INTERMEDIAIRE', 'ENAFEP',     [['A','Nord-Kivu',1],['B','Katanga',0],['C','Équateur',0],['D','Kasaï',0]]],
  ['Quel est le principal minerai exporté par le Katanga ?',                        'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','Cuivre et cobalt',1],['B','Or et diamant',0],['C','Coltan uniquement',0],['D','Pétrole',0]]],
];
foreach ($qs_hg as [$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$mat,$e,$d,$s,$o); $total_q++; }

/* ═══════════════════════════════════════════
   ANGLAIS
═══════════════════════════════════════════ */
$mat = $matiereMap['anglais'];
$qs_en = [
  ['What is the past tense of "go" ?',                                              'DEBUTANT',      'ENAFEP',     [['A','went',1], ['B','goed',0],  ['C','gone',0],  ['D','goes',0]]],
  ['Choose the correct sentence:',                                                  'DEBUTANT',      'ENAFEP',     [['A','She doesn\'t know the answer.',1],['B','She don\'t know.',0],['C','She not know.',0],['D','She knows not.',0]]],
  ['What is the plural of "child" ?',                                               'DEBUTANT',      'ENAFEP',     [['A','children',1],['B','childs',0],['C','childes',0],['D','children\'s',0]]],
  ['Fill in the blank: "I ___ to school every day."',                               'DEBUTANT',      'ENAFEP',     [['A','go',1],   ['B','goes',0],  ['C','going',0], ['D','gone',0]]],
  ['Which sentence uses the Present Perfect correctly ?',                           'ELEMENTAIRE',   'ENAFEP',     [['A','I have visited Paris twice.',1],['B','I have visit Paris.',0],['C','I visited Paris since 2020.',0],['D','I have been visiting Paris yesterday.',0]]],
  ['What does "ambitious" mean ?',                                                  'ELEMENTAIRE',   'ENAFEP',     [['A','Having a strong desire to succeed',1],['B','Being lazy',0],['C','Feeling sad',0],['D','Being generous',0]]],
  ['Choose the correct passive form of "They built this house in 1990."',           'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','This house was built in 1990.',1],['B','This house is built in 1990.',0],['C','This house has been built in 1990.',0],['D','This house built in 1990.',0]]],
  ['Which word is a synonym of "benevolent" ?',                                     'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','kind',1],  ['B','cruel',0], ['C','shy',0],   ['D','angry',0]]],
  ['Identify the gerund in: "Swimming is my favourite hobby."',                     'AVANCE',        'EXAMEN_ETAT',[['A','Swimming',1],['B','favourite',0],['C','hobby',0],['D','is',0]]],
  ['Which sentence contains a conditional type 2 ?',                                'AVANCE',        'EXAMEN_ETAT',[['A','If I had money, I would travel.',1],['B','If I have money, I will travel.',0],['C','If I had had money, I would have travelled.',0],['D','I travel if I have money.',0]]],
];
foreach ($qs_en as [$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$mat,$e,$d,$s,$o); $total_q++; }

/* ═══════════════════════════════════════════
   MATHÉMATIQUES — PACK 2 (18 questions)
═══════════════════════════════════════════ */
$mat = $matiereMap['maths'];
$qs_maths2 = [
  ['Quel est le résultat de 2⁵ ?',                                                             'DEBUTANT',      'ENAFEP',     [['A','32',1],     ['B','25',0],      ['C','16',0],      ['D','64',0]]],
  ['Convertir 0,75 en fraction irréductible.',                                                 'DEBUTANT',      'ENAFEP',     [['A','3/4',1],    ['B','7/5',0],     ['C','7/10',0],    ['D','3/5',0]]],
  ['Quel est le résultat de (−3)² ?',                                                          'DEBUTANT',      'ENAFEP',     [['A','9',1],      ['B','−9',0],      ['C','6',0],       ['D','−6',0]]],
  ['Quelle est l\'aire d\'un cercle de rayon r = 5 cm ?',                                     'ELEMENTAIRE',   'ENAFEP',     [['A','25π cm²',1],['B','10π cm²',0],  ['C','5π cm²',0],  ['D','50π cm²',0]]],
  ['Calculer : log₁₀(1000)',                                                                   'ELEMENTAIRE',   'TENASOSP',   [['A','3',1],      ['B','10',0],      ['C','100',0],     ['D','1',0]]],
  ['Dans un triangle ABC rectangle en C, AC = 6 et BC = 8. Calculer AB.',                     'ELEMENTAIRE',   'ENAFEP',     [['A','10',1],     ['B','14',0],      ['C','100',0],     ['D','√28',0]]],
  ['Quel est le résultat de (a + b)² ?',                                                       'ELEMENTAIRE',   'TENASOSP',   [['A','a² + 2ab + b²',1],['B','a² + b²',0],['C','a² − b²',0],['D','2a + 2b',0]]],
  ['Résoudre le système : x + y = 5 et x − y = 1',                                            'INTERMEDIAIRE', 'ENAFEP',     [['A','x = 3, y = 2',1],['B','x = 2, y = 3',0],['C','x = 4, y = 1',0],['D','x = 5, y = 0',0]]],
  ['Calculer tan(45°).',                                                                        'INTERMEDIAIRE', 'TENASOSP',   [['A','1',1],      ['B','0',0],       ['C','√2/2',0],    ['D','∞',0]]],
  ['Quelle est la valeur de cos(60°) ?',                                                       'INTERMEDIAIRE', 'TENASOSP',   [['A','0,5',1],    ['B','√3/2',0],    ['C','1',0],       ['D','0',0]]],
  ['Combien vaut ln(e) ?',                                                                     'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','1',1],      ['B','e',0],       ['C','0',0],       ['D','2',0]]],
  ['Développer et simplifier : (2x − 1)(x + 3)',                                               'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','2x² + 5x − 3',1],['B','2x² − 3',0],['C','2x + 2',0],['D','2x² + 6x',0]]],
  ['La somme des angles d\'un quadrilatère vaut :',                                            'ELEMENTAIRE',   'ENAFEP',     [['A','360°',1],   ['B','180°',0],    ['C','270°',0],    ['D','540°',0]]],
  ['Factoriser : x² − 9',                                                                      'INTERMEDIAIRE', 'TENASOSP',   [['A','(x − 3)(x + 3)',1],['B','(x − 9)(x + 1)',0],['C','(x − 3)²',0],['D','x(x − 9)',0]]],
  ['Calculer la primitive F(x) de f(x) = 4x³.',                                               'AVANCE',        'EXAMEN_ETAT',[['A','x⁴ + C',1], ['B','12x² + C',0],['C','4x⁴ + C',0], ['D','x³ + C',0]]],
  ['Quelle est la valeur de Δ pour ax² + bx + c = 0 ?',                                       'AVANCE',        'EXAMEN_ETAT',[['A','b² − 4ac',1],['B','b² + 4ac',0], ['C','4ac − b²',0], ['D','b² / 4ac',0]]],
  ['Le vecteur AB où A(1;2) et B(4;6) a pour coordonnées :',                                  'AVANCE',        'EXAMEN_ETAT',[['A','(3 ; 4)',1], ['B','(5 ; 8)',0],  ['C','(−3 ; −4)',0],['D','(4 ; 6)',0]]],
  ['Résoudre dans ℝ : 3x − 7 > 2',                                                            'EXPERT',        'EXAMEN_ETAT',[['A','x > 3',1],  ['B','x > 5',0],   ['C','x < 3',0],   ['D','x > 1,67',0]]],
];
foreach ($qs_maths2 as [$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$mat,$e,$d,$s,$o); $total_q++; }

/* ═══════════════════════════════════════════
   FRANÇAIS — PACK 2 (14 questions)
═══════════════════════════════════════════ */
$mat = $matiereMap['francais'];
$qs_fr2 = [
  ['Quel est le féminin de "acteur" ?',                                                        'DEBUTANT',      'ENAFEP',     [['A','actrice',1],  ['B','acteure',0],   ['C','acteuse',0],    ['D','actresse',0]]],
  ['Choisir la bonne orthographe : "il les a ___" (avoir).',                                  'DEBUTANT',      'ENAFEP',     [['A','vus',1],      ['B','vue',0],       ['C','vu',0],         ['D','vues',0]]],
  ['Quel est le genre du nom "tentacule" ?',                                                   'ELEMENTAIRE',   'ENAFEP',     [['A','Masculin',1], ['B','Féminin',0],   ['C','Les deux',0],   ['D','Neutre',0]]],
  ['Dans quelle phrase le subjonctif est-il obligatoire ?',                                    'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','Il faut que tu viennes.',1],['B','Je sais que tu viens.',0],['C','Je pense qu\'il vient.',0],['D','Il dit qu\'il vient.',0]]],
  ['Quelle est la nature de "rapidement" dans : "Il court rapidement." ?',                    'ELEMENTAIRE',   'ENAFEP',     [['A','Adverbe de manière',1],['B','Adjectif qualificatif',0],['C','Participe présent',0],['D','Nom',0]]],
  ['Identifier la proposition subordonnée relative dans : "L\'homme que tu vois travaille."', 'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','que tu vois',1],['B','L\'homme',0],['C','travaille',0],['D','tu vois travaille',0]]],
  ['Quelle est la valeur du conditionnel présent dans : "Si j\'avais le temps, j\'irais." ?', 'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','Hypothèse irréelle du présent',1],['B','Ordre',0],['C','Futur certain',0],['D','Souhait dans le passé',0]]],
  ['Le mot "analphabète" vient du grec. Son préfixe "an-" signifie :',                        'ELEMENTAIRE',   'ENAFEP',     [['A','sans / privé de',1],['B','double',0],['C','contre',0],['D','avant',0]]],
  ['Quel temps verbal est utilisé dans : "Dès qu\'il arriva, elle sortit." ?',                'AVANCE',        'EXAMEN_ETAT',[['A','Passé simple',1],['B','Imparfait',0],['C','Plus-que-parfait',0],['D','Passé composé',0]]],
  ['Quelle est la fonction de "à Marie" dans : "Je lui offre ce livre à Marie." ?',           'AVANCE',        'EXAMEN_ETAT',[['A','COI (apposition du pronom lui)',1],['B','COD',0],['C','Sujet',0],['D','Complément circonstanciel',0]]],
  ['"Toute la forêt tremble" — cette personnification exprime :',                             'AVANCE',        'EXAMEN_ETAT',[['A','La puissance de la nature',1],['B','La peur du narrateur',0],['C','Un tremblement de terre',0],['D','Une métaphore animale',0]]],
  ['Quel est le pluriel de "bail" ?',                                                          'AVANCE',        'EXAMEN_ETAT',[['A','baux',1],      ['B','bails',0],     ['C','bailes',0],     ['D','bail',0]]],
  ['La ponctuation ";" sert à :',                                                              'ELEMENTAIRE',   'ENAFEP',     [['A','Séparer deux propositions liées logiquement',1],['B','Finir une phrase',0],['C','Indiquer une liste',0],['D','Marquer une exclamation',0]]],
  ['Dans "Mange tes légumes !", quel est le mode verbal ?',                                   'ELEMENTAIRE',   'ENAFEP',     [['A','Impératif',1],['B','Indicatif',0],['C','Subjonctif',0],['D','Infinitif',0]]],
];
foreach ($qs_fr2 as [$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$mat,$e,$d,$s,$o); $total_q++; }

/* ═══════════════════════════════════════════
   CHIMIE — PACK 2 (12 questions)
═══════════════════════════════════════════ */
$mat = $matiereMap['chimie'];
$qs_ch2 = [
  ['Quelle est la masse molaire de l\'eau (H₂O) ? (H=1, O=16)',                              'ELEMENTAIRE',   'TENASOSP',   [['A','18 g/mol',1],  ['B','16 g/mol',0],  ['C','20 g/mol',0],  ['D','2 g/mol',0]]],
  ['Quelle est la configuration électronique du Sodium (Z=11) ?',                            'INTERMEDIAIRE', 'TENASOSP',   [['A','2,8,1',1],     ['B','2,9',0],       ['C','2,8,0,1',0],   ['D','3,7,1',0]]],
  ['Équilibrer : H₂ + O₂ → H₂O. Le coefficient de H₂ est :',                               'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','2',1],          ['B','1',0],         ['C','4',0],         ['D','3',0]]],
  ['Quelle est la formule de l\'acide sulfurique ?',                                          'ELEMENTAIRE',   'TENASOSP',   [['A','H₂SO₄',1],     ['B','H₂SO₃',0],     ['C','HSO₄',0],      ['D','SO₄',0]]],
  ['La réaction NaOH + HCl → NaCl + H₂O est une réaction de :',                             'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','Neutralisation',1],['B','Oxydation',0],['C','Précipitation',0],['D','Combustion',0]]],
  ['Quel est le symbole de l\'argent ?',                                                       'DEBUTANT',      'ENAFEP',     [['A','Ag',1],        ['B','Ar',0],        ['C','Au',0],        ['D','Al',0]]],
  ['La liaison covalente est formée par :',                                                   'INTERMEDIAIRE', 'TENASOSP',   [['A','Partage d\'électrons',1],['B','Transfert d\'électrons',0],['C','Attraction ionique',0],['D','Force de van der Waals',0]]],
  ['Dans la classification périodique, les éléments d\'une même période ont :',              'AVANCE',        'EXAMEN_ETAT',[['A','Le même nombre de couches électroniques',1],['B','Mêmes propriétés chimiques',0],['C','Même nombre de protons',0],['D','Même masse atomique',0]]],
  ['La formule moléculaire du glucose est :',                                                  'ELEMENTAIRE',   'TENASOSP',   [['A','C₆H₁₂O₆',1],  ['B','C₁₂H₂₂O₁₁',0],['C','C₂H₅OH',0],    ['D','CH₄',0]]],
  ['Quel est le produit de la combustion complète du méthane CH₄ ?',                         'INTERMEDIAIRE', 'TENASOSP',   [['A','CO₂ + H₂O',1], ['B','CO + H₂O',0],  ['C','C + H₂',0],    ['D','CO₂ + H₂',0]]],
  ['La concentration molaire C s\'exprime en :',                                              'ELEMENTAIRE',   'ENAFEP',     [['A','mol/L',1],      ['B','g/L',0],       ['C','mol/kg',0],    ['D','mol/m²',0]]],
  ['Quel type d\'isomères ont la même formule brute mais des structures différentes ?',       'EXPERT',        'EXAMEN_ETAT',[['A','Isomères de constitution',1],['B','Énantiomères',0],['C','Isotopes',0],['D','Diastéréoisomères',0]]],
];
foreach ($qs_ch2 as [$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$mat,$e,$d,$s,$o); $total_q++; }

/* ═══════════════════════════════════════════
   PHYSIQUE — PACK 2 (12 questions)
═══════════════════════════════════════════ */
$mat = $matiereMap['physique'];
$qs_ph2 = [
  ['Quelle est l\'unité de l\'énergie dans le SI ?',                                          'DEBUTANT',      'ENAFEP',     [['A','Joule (J)',1],   ['B','Newton',0],    ['C','Watt',0],      ['D','Pascal',0]]],
  ['Un objet de masse 2 kg tombe librement. Son poids vaut (g = 10 m/s²) :',                 'ELEMENTAIRE',   'ENAFEP',     [['A','20 N',1],        ['B','2 N',0],       ['C','10 N',0],      ['D','5 N',0]]],
  ['La puissance électrique s\'exprime par :',                                                'ELEMENTAIRE',   'TENASOSP',   [['A','P = U × I',1],   ['B','P = U + I',0], ['C','P = U / I',0], ['D','P = R × I',0]]],
  ['Un condensateur se charge sous une tension de 12 V. Si C = 2 F, l\'énergie stockée est :','AVANCE',       'EXAMEN_ETAT',[['A','144 J',1],        ['B','24 J',0],      ['C','6 J',0],       ['D','72 J',0]]],
  ['Quelle est la loi de la réfraction de la lumière ?',                                      'INTERMEDIAIRE', 'TENASOSP',   [['A','Loi de Snell-Descartes : n₁sin θ₁ = n₂sin θ₂',1],['B','Loi d\'Ohm',0],['C','Loi de Lenz',0],['D','Principe de Fermat',0]]],
  ['L\'unité du courant électrique est :',                                                    'DEBUTANT',      'ENAFEP',     [['A','Ampère (A)',1],  ['B','Volt',0],      ['C','Ohm',0],       ['D','Coulomb',0]]],
  ['Deux résistances R₁ = 4 Ω et R₂ = 6 Ω montées en série. La résistance totale est :',    'ELEMENTAIRE',   'ENAFEP',     [['A','10 Ω',1],        ['B','2,4 Ω',0],     ['C','24 Ω',0],      ['D','5 Ω',0]]],
  ['Deux résistances R₁ = 4 Ω et R₂ = 4 Ω montées en parallèle. La résistance totale est :','INTERMEDIAIRE', 'TENASOSP',   [['A','2 Ω',1],         ['B','8 Ω',0],       ['C','4 Ω',0],       ['D','1 Ω',0]]],
  ['Quelle propriété de la lumière explique l\'arc-en-ciel ?',                                'INTERMEDIAIRE', 'ENAFEP',     [['A','Dispersion (décomposition)',1],['B','Réflexion totale',0],['C','Diffraction',0],['D','Polarisation',0]]],
  ['Le travail d\'une force F = 10 N sur un déplacement d = 5 m (angle 0°) vaut :',          'ELEMENTAIRE',   'TENASOSP',   [['A','50 J',1],        ['B','2 J',0],       ['C','15 J',0],      ['D','500 J',0]]],
  ['La fréquence d\'un son de 440 Hz correspond à :',                                         'INTERMEDIAIRE', 'ENAFEP',     [['A','La note La (A4)',1],['B','La note Do',0],['C','La note Sol',0],['D','Un ultrason',0]]],
  ['Quel est le phénomène qui permet aux fibres optiques de transporter la lumière ?',        'AVANCE',        'EXAMEN_ETAT',[['A','Réflexion totale interne',1],['B','Réfraction',0],['C','Diffraction',0],['D','Absorption',0]]],
];
foreach ($qs_ph2 as [$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$mat,$e,$d,$s,$o); $total_q++; }

/* ═══════════════════════════════════════════
   BIOLOGIE — PACK 2 (12 questions)
═══════════════════════════════════════════ */
$mat = $matiereMap['biologie'];
$qs_bio2 = [
  ['Quel est le rôle du pancréas dans la digestion ?',                                        'INTERMEDIAIRE', 'TENASOSP',   [['A','Sécréter les enzymes digestives et l\'insuline',1],['B','Filtrer le sang',0],['C','Stocker la bile',0],['D','Absorber les nutriments',0]]],
  ['Quelle vitamine est synthétisée par la peau sous l\'effet du soleil ?',                   'ELEMENTAIRE',   'ENAFEP',     [['A','Vitamine D',1],  ['B','Vitamine C',0],  ['C','Vitamine A',0],   ['D','Vitamine B12',0]]],
  ['Les vaisseaux sanguins qui transportent le sang vers le cœur sont :',                     'ELEMENTAIRE',   'ENAFEP',     [['A','Les veines',1],  ['B','Les artères',0], ['C','Les capillaires',0],['D','Les lymphatiques',0]]],
  ['Le système nerveux central est composé de :',                                              'ELEMENTAIRE',   'TENASOSP',   [['A','Cerveau + moelle épinière',1],['B','Nerfs périphériques',0],['C','Cerveau + cœur',0],['D','Moelle + reins',0]]],
  ['Quel type de reproduction ne nécessite qu\'un seul parent ?',                              'ELEMENTAIRE',   'ENAFEP',     [['A','Reproduction asexuée',1],['B','Reproduction sexuée',0],['C','Méiose',0],['D','Fécondation',0]]],
  ['La respiration cellulaire se résume par :',                                                'INTERMEDIAIRE', 'TENASOSP',   [['A','Glucose + O₂ → CO₂ + H₂O + ATP',1],['B','CO₂ + H₂O → Glucose + O₂',0],['C','Glucose → Éthanol + CO₂',0],['D','ATP → ADP + Énergie',0]]],
  ['Quelle est la fonction de la membrane cellulaire ?',                                       'ELEMENTAIRE',   'ENAFEP',     [['A','Contrôler les échanges entre cellule et milieu',1],['B','Produire de l\'énergie',0],['C','Fabriquer des protéines',0],['D','Stocker l\'ADN',0]]],
  ['Le groupe sanguin O est dit "donneur universel" car :',                                   'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','Ses globules rouges n\'ont pas d\'antigènes A ni B',1],['B','Il possède tous les antigènes',0],['C','Son plasma n\'a pas d\'anticorps',0],['D','Il est le plus rare',0]]],
  ['La structure qui produit les spermatozoïdes s\'appelle :',                                 'ELEMENTAIRE',   'TENASOSP',   [['A','Testicule',1],    ['B','Ovaire',0],      ['C','Prostate',0],      ['D','Épididyme',0]]],
  ['Quelle est la durée moyenne d\'une grossesse humaine ?',                                   'DEBUTANT',      'ENAFEP',     [['A','9 mois (38 semaines)',1],['B','12 mois',0],['C','6 mois',0],['D','10 mois',0]]],
  ['Le VIH attaque principalement quel type de cellules ?',                                    'AVANCE',        'EXAMEN_ETAT',[['A','Lymphocytes T CD4+',1],['B','Globules rouges',0],['C','Plaquettes',0],['D','Neutrophiles',0]]],
  ['La vaccination crée une immunité en induisant la production de :',                        'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','Anticorps spécifiques',1],['B','Globules rouges',0],['C','Enzymes digestives',0],['D','Hormones',0]]],
];
foreach ($qs_bio2 as [$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$mat,$e,$d,$s,$o); $total_q++; }

/* ═══════════════════════════════════════════
   HISTOIRE-GÉOGRAPHIE — PACK 2 (12 questions)
═══════════════════════════════════════════ */
$mat = $matiereMap['histgeo'];
$qs_hg2 = [
  ['La conférence de Berlin (1884-1885) a abouti à :',                                        'INTERMEDIAIRE', 'ENAFEP',     [['A','Le partage de l\'Afrique entre puissances européennes',1],['B','La fin de l\'esclavage',0],['C','L\'indépendance du Congo',0],['D','La création de l\'ONU',0]]],
  ['Qui était Patrice Lumumba ?',                                                              'ELEMENTAIRE',   'ENAFEP',     [['A','Premier ministre de la RDC indépendante',1],['B','Premier président',0],['C','Chef rebelle',0],['D','Colonisateur belge',0]]],
  ['Le Kilimanjaro, plus haute montagne d\'Afrique, se trouve en :',                          'ELEMENTAIRE',   'ENAFEP',     [['A','Tanzanie',1],    ['B','Kenya',0],       ['C','Éthiopie',0],      ['D','RDC',0]]],
  ['La deuxième guerre mondiale s\'est terminée en :',                                         'ELEMENTAIRE',   'ENAFEP',     [['A','1945',1],        ['B','1918',0],        ['C','1939',0],          ['D','1950',0]]],
  ['Quel est le plus grand pays d\'Afrique par sa superficie ?',                               'ELEMENTAIRE',   'ENAFEP',     [['A','Algérie',1],     ['B','RDC',0],         ['C','Soudan',0],        ['D','Mali',0]]],
  ['Le fleuve Nil prend sa source principalement au :',                                        'INTERMEDIAIRE', 'ENAFEP',     [['A','Lac Victoria (Ouganda/Tanzanie)',1],['B','Lac Tanganyika',0],['C','Lac Tchad',0],['D','Mont Kenya',0]]],
  ['Quelle est la monnaie officielle de la RDC ?',                                             'DEBUTANT',      'ENAFEP',     [['A','Franc congolais (CDF)',1],['B','Franc belge',0],['C','Dollar congolais',0],['D','Zaïre',0]]],
  ['La SADC est une organisation régionale regroupant les pays d\' :',                        'INTERMEDIAIRE', 'ENAFEP',     [['A','Afrique australe',1],['B','Afrique de l\'Ouest',0],['C','Afrique du Nord',0],['D','Afrique centrale',0]]],
  ['En quelle année l\'ONU a-t-elle été fondée ?',                                             'ELEMENTAIRE',   'ENAFEP',     [['A','1945',1],        ['B','1919',0],        ['C','1960',0],          ['D','1948',0]]],
  ['La Révolution industrielle a débuté au :',                                                 'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','Royaume-Uni (XVIIIe siècle)',1],['B','France',0],['C','États-Unis',0],['D','Allemagne',0]]],
  ['Quel est le plus grand lac d\'Afrique ?',                                                  'ELEMENTAIRE',   'ENAFEP',     [['A','Lac Victoria',1], ['B','Lac Tanganyika',0],['C','Lac Malawi',0],  ['D','Lac Albert',0]]],
  ['La Province du Katanga est riche en :',                                                    'ELEMENTAIRE',   'ENAFEP',     [['A','Minerais (cuivre, cobalt, uranium)',1],['B','Pétrole',0],['C','Cacao et café',0],['D','Diamants uniquement',0]]],
];
foreach ($qs_hg2 as [$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$mat,$e,$d,$s,$o); $total_q++; }

/* ═══════════════════════════════════════════
   ANGLAIS — PACK 2 (12 questions)
═══════════════════════════════════════════ */
$mat = $matiereMap['anglais'];
$qs_en2 = [
  ['What is the comparative form of "good" ?',                                                 'DEBUTANT',      'ENAFEP',     [['A','better',1],      ['B','gooder',0],      ['C','more good',0],   ['D','best',0]]],
  ['Choose the correct question tag: "She is a teacher, ___" ?',                              'ELEMENTAIRE',   'ENAFEP',     [['A','isn\'t she?',1], ['B','is she?',0],     ['C','wasn\'t she?',0],['D','doesn\'t she?',0]]],
  ['What does "perseverance" mean ?',                                                           'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','Continued effort despite difficulty',1],['B','Quick success',0],['C','Natural talent',0],['D','Giving up',0]]],
  ['Fill in: "By the time she arrived, they ___ the meeting." (start)',                        'AVANCE',        'EXAMEN_ETAT',[['A','had started',1],  ['B','started',0],     ['C','have started',0],['D','were starting',0]]],
  ['Which sentence is correct ?',                                                               'ELEMENTAIRE',   'ENAFEP',     [['A','Neither of them was right.',1],['B','Neither of them were right.',0],['C','None of them was right.',0],['D','Both is wrong.',0]]],
  ['The passive voice of "They built the bridge in 1950" is :',                                'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','The bridge was built in 1950.',1],['B','The bridge is built.',0],['C','The bridge has been built.',0],['D','They had built the bridge.',0]]],
  ['What is an antonym of "courageous" ?',                                                     'ELEMENTAIRE',   'ENAFEP',     [['A','cowardly',1],    ['B','brave',0],       ['C','strong',0],      ['D','bold',0]]],
  ['Identify the type of clause in: "Unless you study, you will fail."',                       'AVANCE',        'EXAMEN_ETAT',[['A','Conditional clause (type 1)',1],['B','Relative clause',0],['C','Noun clause',0],['D','Result clause',0]]],
  ['"She has been working here since 2020." This tense is:',                                   'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','Present Perfect Continuous',1],['B','Past Perfect',0],['C','Present Perfect Simple',0],['D','Simple Past',0]]],
  ['What does the prefix "mis-" mean in "misunderstand" ?',                                    'ELEMENTAIRE',   'ENAFEP',     [['A','wrongly',1],     ['B','not',0],         ['C','again',0],       ['D','before',0]]],
  ['Choose the correct reported speech: He said, "I am tired." →',                             'AVANCE',        'EXAMEN_ETAT',[['A','He said that he was tired.',1],['B','He said that he is tired.',0],['C','He told that he was tired.',0],['D','He said he am tired.',0]]],
  ['Which word is a homophone of "write" ?',                                                    'INTERMEDIAIRE', 'ENAFEP',     [['A','right',1],       ['B','ride',0],        ['C','white',0],       ['D','rite',0]]],
];
foreach ($qs_en2 as [$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$mat,$e,$d,$s,$o); $total_q++; }

/* ═══════════════════════════════════════════
   SCIENCES — PACK 2 (12 questions)
═══════════════════════════════════════════ */
$mat = $matiereMap['sciences'];
$qs_sc2 = [
  ['Quel est l\'organe qui filtre le sang et produit l\'urine ?',                             'ELEMENTAIRE',   'ENAFEP',     [['A','Les reins',1],  ['B','Le foie',0],    ['C','Le cœur',0],     ['D','Les poumons',0]]],
  ['Quelle est la planète la plus proche du Soleil ?',                                         'DEBUTANT',      'ENAFEP',     [['A','Mercure',1],    ['B','Vénus',0],      ['C','Mars',0],        ['D','Terre',0]]],
  ['Le Soleil est une étoile de type :',                                                       'ELEMENTAIRE',   'TENASOSP',   [['A','Naine jaune',1],['B','Géante rouge',0],['C','Supernova',0],  ['D','Naine blanche',0]]],
  ['Quel gaz représente environ 78% de l\'atmosphère terrestre ?',                             'ELEMENTAIRE',   'ENAFEP',     [['A','Azote (N₂)',1],  ['B','Oxygène',0],    ['C','CO₂',0],         ['D','Argon',0]]],
  ['La force qui attire les objets vers le centre de la Terre s\'appelle :',                  'DEBUTANT',      'ENAFEP',     [['A','La gravité',1], ['B','La tension',0], ['C','La friction',0], ['D','La pression',0]]],
  ['Quelle transformation de l\'eau correspond au passage de l\'état liquide à gazeux ?',     'DEBUTANT',      'ENAFEP',     [['A','Évaporation',1],['B','Fusion',0],      ['C','Condensation',0],['D','Solidification',0]]],
  ['Quel est l\'appareil utilisé pour mesurer la pression atmosphérique ?',                   'ELEMENTAIRE',   'TENASOSP',   [['A','Baromètre',1],  ['B','Thermomètre',0],['C','Voltmètre',0],   ['D','Manomètre',0]]],
  ['Le principe d\'Archimède stipule qu\'un corps immergé :',                                 'INTERMEDIAIRE', 'TENASOSP',   [['A','Reçoit une poussée vers le haut égale au poids du fluide déplacé',1],['B','Perd la moitié de son poids',0],['C','Devient plus léger que l\'eau',0],['D','Subit une force égale à sa masse',0]]],
  ['Combien de planètes compte notre système solaire ?',                                       'DEBUTANT',      'ENAFEP',     [['A','8',1],          ['B','9',0],          ['C','7',0],           ['D','10',0]]],
  ['La plaque tectonique africaine est principalement de type :',                              'AVANCE',        'EXAMEN_ETAT',[['A','Continentale',1], ['B','Océanique',0],  ['C','Mixte',0],       ['D','Tectonique',0]]],
  ['Qu\'est-ce que la biodiversité ?',                                                         'ELEMENTAIRE',   'ENAFEP',     [['A','La variété des espèces vivantes sur Terre',1],['B','La diversité des minéraux',0],['C','La variété des paysages',0],['D','L\'ensemble des fossiles',0]]],
  ['La couche d\'ozone protège la Terre des rayons :',                                         'ELEMENTAIRE',   'ENAFEP',     [['A','Ultraviolets (UV)',1],['B','Infrarouges',0],['C','X',0],         ['D','Gamma',0]]],
];
foreach ($qs_sc2 as [$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$mat,$e,$d,$s,$o); $total_q++; }

/* ═══════════════════════════════════════════
   PACK FINAL — Questions transversales (8)
═══════════════════════════════════════════ */
$qs_bonus = [
  [$matiereMap['maths'],    'Calculer la médiane de la série : 3, 7, 2, 9, 5.',              'INTERMEDIAIRE', 'ENAFEP',     [['A','5',1],   ['B','7',0],  ['C','4',0],  ['D','3',0]]],
  [$matiereMap['maths'],    'Un train roule à 120 km/h pendant 2h30. Distance parcourue ?',  'ELEMENTAIRE',   'ENAFEP',     [['A','300 km',1],['B','240 km',0],['C','360 km',0],['D','120 km',0]]],
  [$matiereMap['francais'], 'Quel est le passé simple de "venir" à la 3e pers. sing. ?',     'AVANCE',        'EXAMEN_ETAT',[['A','vint',1],  ['B','venait',0],['C','viendra',0],['D','venu',0]]],
  [$matiereMap['chimie'],   'Quelle est la valence du carbone ?',                             'AVANCE',        'EXAMEN_ETAT',[['A','4',1],   ['B','2',0],  ['C','6',0],  ['D','1',0]]],
  [$matiereMap['physique'], 'Un objet lancé verticalement vers le haut décélère à cause :',  'ELEMENTAIRE',   'ENAFEP',     [['A','De la gravité (g ≈ 10 m/s²)',1],['B','Du frottement de l\'air uniquement',0],['C','De la résistance magnétique',0],['D','De la pression atmosphérique',0]]],
  [$matiereMap['biologie'], 'La chlorophylle est le pigment végétal responsable de :',       'ELEMENTAIRE',   'ENAFEP',     [['A','La couleur verte et la photosynthèse',1],['B','La croissance',0],['C','L\'absorption d\'eau',0],['D','La respiration',0]]],
  [$matiereMap['histgeo'],  'Kinshasa s\'appelait avant :',                                   'ELEMENTAIRE',   'ENAFEP',     [['A','Léopoldville',1],['B','Élisabethville',0],['C','Stanleyville',0],['D','Coquilhatville',0]]],
  [$matiereMap['anglais'],  '"Despite his efforts, he failed." "Despite" introduces a:',     'AVANCE',        'EXAMEN_ETAT',[['A','Concession',1],['B','Condition',0],['C','Cause',0],['D','Consequence',0]]],
];
foreach ($qs_bonus as [$matId,$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$matId,$e,$d,$s,$o); $total_q++; }

seed_log("  ✓ $total_q questions insérées");

/* ── 6. ABONNEMENT DÉMO ─────────────────────────────────────── */
seed_log("💳 Abonnement BASIQUE (démo)...");
$pdo->exec("DELETE FROM abonnements WHERE user_id='$demoId'");
$pdo->prepare("INSERT INTO abonnements (id,user_id,plan,montant,devise,methode_paiement,reference_paiement,telephone,statut,date_debut,date_fin,duree_mois,confirmed_at) VALUES (UUID(),?,?,?,?,?,?,?,?,?,?,?,NOW())")
    ->execute([$demoId,'BASIQUE',5000,'CDF','MPESA','RP-SEEDDEMO','+243810000000','CONFIRME',date('Y-m-01'),date('Y-m-d',strtotime('+1 month')),1]);
$pdo->prepare("UPDATE utilisateurs SET plan='BASIQUE', plan_expire_at=? WHERE id=?")
    ->execute([date('Y-m-d',strtotime('+1 month')),$demoId]);

/* ── 7. ACTIVITÉ JOURNALIÈRE ────────────────────────────────── */
seed_log("📅 Activité journalière...");
$pdo->exec("DELETE FROM activite_journaliere WHERE user_id='$demoId'");
$stAct = $pdo->prepare("INSERT INTO activite_journaliere (id,user_id,date_act,examens,questions) VALUES (UUID(),?,?,?,?) ON DUPLICATE KEY UPDATE examens=VALUES(examens)");
for ($i=0;$i<14;$i++) {
    if ($i%3===2) continue;
    $stAct->execute([$demoId, date('Y-m-d',strtotime("-$i days")), rand(1,3), rand(5,25)]);
}

/* ── 8. PROGRESSION PAR MATIÈRE ─────────────────────────────── */
seed_log("📊 Progression matières...");
$pdo->exec("DELETE FROM user_progression WHERE user_id='$demoId'");
$stPg = $pdo->prepare("INSERT INTO user_progression (id,user_id,matiere_id,score_moyen,questions_vues,bonnes_reponses) VALUES (UUID(),?,?,?,?,?)");
foreach (['maths'=>[72.5,40,29],'francais'=>[65.0,30,20],'sciences'=>[80.0,20,16],'histgeo'=>[55.0,10,6],'chimie'=>[60.0,20,12]] as $code=>[$s,$q,$b]) {
    if (isset($matiereMap[$code])) $stPg->execute([$demoId,$matiereMap[$code],$s,$q,$b]);
}

/* ── 9. SESSIONS D'EXAMEN ───────────────────────────────────── */
seed_log("✏️ Sessions d'examen...");
$pdo->exec("DELETE FROM exam_sessions WHERE user_id='$demoId'");
$stSess = $pdo->prepare("INSERT INTO exam_sessions (id,user_id,matiere_id,titre,exam_type,nb_questions,score,pourcentage,temps_passe,statut,started_at,finished_at) VALUES (UUID(),?,?,?,?,?,?,?,?,?,?,?)");
foreach ([['maths','ENAFEP Maths 2024','ENAFEP',10,7,70.0,900],['francais','ENAFEP Français','ENAFEP',10,6,60.0,720],['sciences','Sciences Révision','TENASOSP',5,4,80.0,400],['maths','TENASOSP Maths','TENASOSP',10,8,80.0,840],['chimie','Chimie Pratique','EXAMEN_ETAT',5,3,60.0,500]] as $i=>[$mat,$titre,$type,$nb,$bon,$pct,$tps]) {
    $st = date('Y-m-d H:i:s',strtotime("-".($i+1)." days"));
    $en = date('Y-m-d H:i:s',strtotime($st)+$tps);
    $stSess->execute([$demoId,$matiereMap[$mat]??null,$titre,$type,$nb,$bon,$pct,$tps,'TERMINE',$st,$en]);
}
seed_log("  ✓ 5 sessions");

/* ── 10. CODE PROMO ─────────────────────────────────────────── */
seed_log("🎁 Code promo BIENVENUE2025...");
$pdo->exec("DELETE FROM codes_promo WHERE code='BIENVENUE2025'");
$pdo->prepare("INSERT INTO codes_promo (id,code,type_remise,valeur_remise,plan_applicable,nb_max,actif,date_expiration) VALUES (UUID(),'BIENVENUE2025','POURCENTAGE',20,'TOUS',100,1,?)")
    ->execute([date('Y-12-31')]);

$pdo->exec("SET FOREIGN_KEY_CHECKS=1");
echo "\n";
seed_log("✅ Seeder terminé !\n");
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  demo@reussiteplus.cd  → Demo1234!\n";
echo "  admin@reussiteplus.cd → Admin2025!\n";
echo "  prof@reussiteplus.cd  → Prof2025!\n";
echo "  Code promo : BIENVENUE2025 (20%)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
