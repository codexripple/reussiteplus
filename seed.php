<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_admin(); // Script restreint aux admins
/**
 * RÉUSSITE+ — Seeder de données de démonstration
 * Accessible uniquement depuis localhost
 */
if (php_sapi_name() !== 'cli' && (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1']) || ($_ENV['APP_ENV'] ?? 'production') !== 'development')) {
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
        random_int(0,0xffff),random_int(0,0xffff),random_int(0,0xffff),
        random_int(0,0x0fff)|0x4000,random_int(0,0x3fff)|0x8000,
        random_int(0,0xffff),random_int(0,0xffff),random_int(0,0xffff));
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
$pdo->exec("DELETE FROM utilisateurs WHERE email IN ('demo@reussiteplus.cd','xenora@reussiteplus.cd','prof@reussiteplus.cd')");
$demoId  = make_uuid();
$adminId = make_uuid();
$profId  = make_uuid();
$stU = $pdo->prepare("INSERT INTO utilisateurs (id,prenom,nom,email,password_hash,role,plan,province_id,classe,score_moyen,total_examens,total_questions,referral_code,is_active,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1,NOW())");
$stU->execute([$demoId, 'Amani','Kanda',   'demo@reussiteplus.cd',  password_hash('Demo1234!', PASSWORD_BCRYPT,['cost'=>12]),'ELEVE',       'BASIQUE', $kinshasaId,'Terminale',67.5,12,120,'AMANI2025']);
$stU->execute([$adminId,'Xenora','',   'xenora@reussiteplus.cd', password_hash('Admin2025!',PASSWORD_BCRYPT,['cost'=>12]),'SUPER_ADMIN',  'GRATUIT', $kinshasaId, null,       0,    0,   0,  'ADMIN2025']);
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
// difficulte: DEBUTANT | ELEMENTAIRE | INTERMEDIAIRE | AVANCE | EXPERT
$questions = [
    [$matiereMap['maths'],    'Quel est le résultat de 15² - 10² ?',                           'ELEMENTAIRE',   'ENAFEP',     [['A','125',1],['B','225',0],['C','100',0],['D','175',0]]],
    [$matiereMap['maths'],    'Si f(x) = 3x² + 2x - 1, calculer f(2).',                       'ELEMENTAIRE',   'ENAFEP',     [['A','15',1], ['B','11',0], ['C','13',0], ['D','9',0]]],
    [$matiereMap['maths'],    'Résoudre : 2x + 7 = 15',                                        'DEBUTANT',      'ENAFEP',     [['A','4',1],  ['B','3',0],  ['C','5',0],  ['D','6',0]]],
    [$matiereMap['maths'],    'Quelle est la valeur de sin(30°) ?',                             'INTERMEDIAIRE', 'TENASOSP',   [['A','0,5',1],['B','0,866',0],['C','1',0],['D','0,707',0]]],
    [$matiereMap['maths'],    'Calculer la dérivée de f(x) = x³ - 3x.',                        'AVANCE',        'EXAMEN_ETAT',[['A','3x² - 3',1],['B','x² - 3',0],['C','3x - 3',0],['D','3x²',0]]],
    [$matiereMap['francais'], 'Quel est le pluriel de "œil" en français ?',                    'DEBUTANT',      'ENAFEP',     [['A','yeux',1],['B','œils',0],['C','yaux',0],['D','œillets',0]]],
    [$matiereMap['francais'], '"Le cœur de Pierre est de pierre" — quelle figure de style ?',  'AVANCE',        'EXAMEN_ETAT',[['A','Antanaclase',1],['B','Métaphore',0],['C','Oxymore',0],['D','Allitération',0]]],
    [$matiereMap['francais'], 'Quel est l\'antonyme de "prospère" ?',                          'INTERMEDIAIRE', 'ENAFEP',     [['A','décadent',1],['B','riche',0],['C','heureux',0],['D','florissant',0]]],
    [$matiereMap['sciences'], 'Quelle est la formule chimique de l\'eau ?',                    'DEBUTANT',      'ENAFEP',     [['A','H₂O',1], ['B','HO₂',0], ['C','H₂O₂',0],['D','OH',0]]],
    [$matiereMap['sciences'], 'Quel organe produit la bile dans le corps humain ?',             'INTERMEDIAIRE', 'TENASOSP',   [['A','Le foie',1],['B','Le pancréas',0],['C','L\'estomac',0],['D','Les reins',0]]],
    [$matiereMap['sciences'], 'Combien d\'os compte le corps humain adulte ?',                 'ELEMENTAIRE',   'ENAFEP',     [['A','206',1], ['B','208',0], ['C','198',0], ['D','215',0]]],
    [$matiereMap['chimie'],   'Quel est le numéro atomique du carbone ?',                      'ELEMENTAIRE',   'TENASOSP',   [['A','6',1],   ['B','8',0],   ['C','12',0],  ['D','4',0]]],
    [$matiereMap['chimie'],   'Quelle est la valeur du pH d\'une solution neutre à 25°C ?',   'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','7',1],   ['B','0',0],   ['C','14',0],  ['D','6,5',0]]],
    [$matiereMap['chimie'],   'Combien d\'électrons peut contenir la couche M ?',              'AVANCE',        'TENASOSP',   [['A','18',1],  ['B','8',0],   ['C','2',0],   ['D','32',0]]],
    [$matiereMap['physique'], 'Quelle est l\'unité de la force dans le SI ?',                  'DEBUTANT',      'ENAFEP',     [['A','Newton (N)',1],['B','Pascal',0],['C','Joule',0],['D','Watt',0]]],
    [$matiereMap['physique'], 'Quelle est la vitesse de la lumière dans le vide ?',            'ELEMENTAIRE',   'EXAMEN_ETAT',[['A','3 × 10⁸ m/s',1],['B','3 × 10⁶ m/s',0],['C','3 × 10¹⁰ m/s',0],['D','3 × 10⁴ m/s',0]]],
    [$matiereMap['histgeo'],  'En quelle année la RDC a-t-elle obtenu son indépendance ?',     'DEBUTANT',      'ENAFEP',     [['A','1960',1],['B','1956',0],['C','1964',0],['D','1958',0]]],
    [$matiereMap['histgeo'],  'Quel est le plus grand fleuve de la RDC ?',                     'DEBUTANT',      'ENAFEP',     [['A','Le Congo',1],['B','L\'Ubangi',0],['C','Le Kasaï',0],['D','Le Lomami',0]]],
    [$matiereMap['biologie'], 'Quelle est la fonction principale des mitochondries ?',         'INTERMEDIAIRE', 'TENASOSP',   [['A','Production d\'ATP',1],['B','Synthèse protéines',0],['C','Division cellulaire',0],['D','Stockage ADN',0]]],
    [$matiereMap['biologie'], 'Combien de chromosomes a une cellule humaine diploïde ?',       'ELEMENTAIRE',   'EXAMEN_ETAT',[['A','46',1],   ['B','23',0],  ['C','48',0],  ['D','44',0]]],
    [$matiereMap['anglais'],  'What is the past tense of "go" ?',                              'DEBUTANT',      'ENAFEP',     [['A','went',1],['B','goed',0],['C','gone',0],['D','goes',0]]],
    [$matiereMap['anglais'],  'Choose the correct sentence:',                                  'ELEMENTAIRE',   'ENAFEP',     [['A','She doesn\'t know the answer.',1],['B','She don\'t know.',0],['C','She not knows.',0],['D','She knows not.',0]]],
];
$stQ = $pdo->prepare("INSERT INTO question_bank (id,matiere_id,enonce,difficulte,exam_type,status) VALUES (UUID(),?,?,?,?,?)");
$stO = $pdo->prepare("INSERT INTO question_options (id,question_id,lettre,texte,est_correcte) VALUES (UUID(),?,?,?,?)");
foreach ($questions as [$matId,$enonce,$diff,$src,$opts]) {
    $stQ->execute([$matId,$enonce,$diff,$src,'PUBLIE']);
    $qRow = $pdo->prepare("SELECT id FROM question_bank WHERE enonce=? ORDER BY created_at DESC LIMIT 1");
    $qRow->execute([$enonce]);
    $qId = $qRow->fetchColumn();
    foreach ($opts as [$l,$t,$ok]) $stO->execute([$qId,$l,$t,$ok]);
}
seed_log("  ✓ " . count($questions) . " questions");

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
echo "  xenora@reussiteplus.cd → Admin2025!\n";
echo "  prof@reussiteplus.cd  → Prof2025!\n";
echo "  Code promo : BIENVENUE2025 (20%)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

