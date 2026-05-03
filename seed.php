<?php
/**
 * RÉUSSITE+ — Seeder de données de démonstration
 * URL d'accès : http://localhost/reussiteplus/seed.php
 * ⚠️  À supprimer ou protéger après usage en production !
 */

// Protection basique — exécutable uniquement en CLI ou localhost
if (php_sapi_name() !== 'cli' && $_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
    http_response_code(403);
    die('Accès refusé. Ce script ne peut être exécuté que depuis localhost.');
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$pdo = db();
$pdo->exec("SET FOREIGN_KEY_CHECKS=0");

function log_step(string $msg): void {
    echo "[" . date('H:i:s') . "] $msg\n";
    ob_flush(); flush();
}

header('Content-Type: text/plain; charset=utf-8');
log_step("🚀 Démarrage du seeder RÉUSSITE+...\n");

// ─── 1. Provinces ────────────────────────────────────────────
log_step("📍 Insertion des provinces...");
$provinces = [
    'Kinshasa','Kongo-Central','Kwango','Kwilu','Mai-Ndombe',
    'Kasaï','Kasaï-Central','Kasaï-Oriental','Lomami','Sankuru',
    'Maniema','Sud-Kivu','Nord-Kivu','Ituri','Haut-Uele',
    'Tshopo','Bas-Uele','Nord-Ubangi','Mongala','Sud-Ubangi',
    'Équateur','Tshuapa','Tanganyika','Haut-Lomami','Lualaba','Haut-Katanga',
];
$pdo->exec("TRUNCATE TABLE provinces");
$stmtP = $pdo->prepare("INSERT INTO provinces (id, nom) VALUES (UUID(), ?)");
foreach ($provinces as $p) $stmtP->execute([$p]);
log_step("  ✓ " . count($provinces) . " provinces insérées.");

// Récupérer IDs
$provinceMap = [];
foreach ($pdo->query("SELECT id, nom FROM provinces")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $provinceMap[$r['nom']] = $r['id'];
}
$kinshasaId = $provinceMap['Kinshasa'];

// ─── 2. Matières ────────────────────────────────────────────
log_step("📚 Insertion des matières...");
$matieres = [
    ['Mathématiques',   '#2563EB', '🔢', 'maths'],
    ['Français',        '#059669', '📝', 'francais'],
    ['Sciences',        '#7C3AED', '🔬', 'sciences'],
    ['Histoire-Géo',    '#D97706', '🌍', 'histgeo'],
    ['Chimie',          '#DC2626', '⚗️',  'chimie'],
    ['Physique',        '#0891B2', '⚡', 'physique'],
    ['Biologie',        '#16A34A', '🧬', 'biologie'],
    ['Anglais',         '#9333EA', '🇬🇧', 'anglais'],
];
$pdo->exec("TRUNCATE TABLE matieres");
$stmtM = $pdo->prepare("INSERT INTO matieres (id, nom, couleur, icone, code, is_active) VALUES (UUID(),?,?,?,?,1)");
foreach ($matieres as [$nom, $couleur, $icone, $code]) $stmtM->execute([$nom, $couleur, $icone, $code]);
$matiereMap = [];
foreach ($pdo->query("SELECT id, code FROM matieres")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $matiereMap[$r['code']] = $r['id'];
}
log_step("  ✓ " . count($matieres) . " matières insérées.");

// ─── 3. Utilisateurs de démo ────────────────────────────────
log_step("👤 Création des utilisateurs de démo...");
$pdo->exec("DELETE FROM utilisateurs WHERE email IN ('demo@reussiteplus.cd','admin@reussiteplus.cd','prof@reussiteplus.cd')");

function make_uuid(): string {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff),
        mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff));
}

$demoId  = make_uuid();
$adminId = make_uuid();
$profId  = make_uuid();

$stmtU = $pdo->prepare(
    "INSERT INTO utilisateurs (id,prenom,nom,email,password_hash,role,plan,province_id,classe,score_moyen,total_examens,total_questions,referral_code,is_active,created_at)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1,NOW())"
);
$stmtU->execute([$demoId,  'Amani',   'Kanda',    'demo@reussiteplus.cd',  password_hash('Demo1234!',  PASSWORD_BCRYPT,['cost'=>12]), 'ELEVE',       'BASIQUE',  $kinshasaId, 'Terminale', 67.5, 12, 120, 'AMANI2025']);
$stmtU->execute([$adminId, 'Superadmin','RÉUSSITE+','admin@reussiteplus.cd', password_hash('Admin2025!', PASSWORD_BCRYPT,['cost'=>12]), 'SUPER_ADMIN', 'GRATUIT',  $kinshasaId, null,        0,    0,  0,   'ADMIN2025']);
$stmtU->execute([$profId,  'Marie',   'Kalombo',  'prof@reussiteplus.cd',   password_hash('Prof2025!',  PASSWORD_BCRYPT,['cost'=>12]), 'ENSEIGNANT',  'PREMIUM',  $kinshasaId, null,        72.0, 5,  45,  'MARIE2025']);
log_step("  ✓ 3 utilisateurs créés (demo / admin / prof).");

// ─── 4. Archives ────────────────────────────────────────────
log_step("📂 Insertion des archives...");
$pdo->exec("TRUNCATE TABLE archives");
$archivesData = [
    ['ENAFEP 2024 — Mathématiques Nationales', 2024, 'ENAFEP', 'maths', 0],
    ['ENAFEP 2024 — Français Nationales',      2024, 'ENAFEP', 'francais', 0],
    ['ENAFEP 2023 — Sciences',                 2023, 'ENAFEP', 'sciences', 0],
    ['ENAFEP 2023 — Mathématiques',            2023, 'ENAFEP', 'maths', 0],
    ['TENASOSP 2024 — Mathématiques',          2024, 'TENASOSP','maths', 1],
    ['TENASOSP 2024 — Chimie',                 2024, 'TENASOSP','chimie', 1],
    ['TENASOSP 2023 — Physique',               2023, 'TENASOSP','physique', 1],
    ['EXAMEN D\'ÉTAT 2024 — Mathématiques',    2024, 'EXAMEN_ETAT','maths', 1],
    ['EXAMEN D\'ÉTAT 2024 — Français',         2024, 'EXAMEN_ETAT','francais', 1],
    ['EXAMEN D\'ÉTAT 2023 — Biologie',         2023, 'EXAMEN_ETAT','biologie', 1],
    ['ENAFEP 2022 — Histoire-Géo',             2022, 'ENAFEP', 'histgeo', 0],
    ['ENAFEP 2022 — Anglais',                  2022, 'ENAFEP', 'anglais', 0],
    ['EXAMEN D\'ÉTAT 2022 — Chimie',           2022, 'EXAMEN_ETAT','chimie', 1],
    ['TENASOSP 2022 — Biologie',               2022, 'TENASOSP','biologie', 1],
    ['ENAFEP 2021 — Mathématiques',            2021, 'ENAFEP', 'maths', 0],
];
$stmtA = $pdo->prepare(
    "INSERT INTO archives (id,titre,annee,exam_type,matiere_id,description,premium_only,is_active)
     VALUES (UUID(),?,?,?,?,?,?,1)"
);
foreach ($archivesData as [$titre, $annee, $type, $mat, $premium]) {
    $desc = "Épreuve officielle $type $annee — " . str_replace(['EXAMEN D\'ÉTAT','ENAFEP','TENASOSP'],['Examen d\'État','ENAFEP','TENASOSP'], $type) . ". Disponible avec corrigé commenté.";
    $stmtA->execute([$titre, $annee, $type, $matiereMap[$mat] ?? null, $desc, $premium]);
}
log_step("  ✓ " . count($archivesData) . " archives insérées.");

// ─── 5. Questions QCM ────────────────────────────────────────
log_step("🧠 Insertion des questions QCM...");
$pdo->exec("TRUNCATE TABLE question_options");
$pdo->exec("TRUNCATE TABLE question_bank");

$questions = [
    // Mathématiques
    [$matiereMap['maths'], 'Quel est le résultat de 15² - 10² ?', 'MOYEN',   'EXAMEN_ETAT',
     [['A','125',1],['B','225',0],['C','100',0],['D','175',0]],
     'Utiliser la différence de carrés : (15+10)(15-10) = 25×5 = 125.'],
    [$matiereMap['maths'], 'Si f(x) = 3x² + 2x - 1, calculer f(2).', 'FACILE', 'ENAFEP',
     [['A','15',1],['B','11',0],['C','13',0],['D','9',0]],
     'f(2) = 3(4) + 4 - 1 = 12 + 4 - 1 = 15.'],
    [$matiereMap['maths'], 'Résoudre : 2x + 7 = 15', 'FACILE', 'ENAFEP',
     [['A','4',1],['B','3',0],['C','5',0],['D','6',0]],
     '2x = 15 - 7 = 8, donc x = 4.'],
    [$matiereMap['maths'], 'Quelle est la valeur de sin(30°) ?', 'MOYEN', 'TENASOSP',
     [['A','0,5',1],['B','0,866',0],['C','1',0],['D','0,707',0]],
     'sin(30°) = 1/2 = 0,5 par définition.'],
    [$matiereMap['maths'], 'Calculer la dérivée de f(x) = x³ - 3x.', 'DIFFICILE', 'EXAMEN_ETAT',
     [['A','3x² - 3',1],['B','x² - 3',0],['C','3x - 3',0],['D','3x²',0]],
     'f\'(x) = 3x² - 3 par dérivation terme à terme.'],

    // Français
    [$matiereMap['francais'], 'Quel est le pluriel de "œil" en français ?', 'FACILE', 'ENAFEP',
     [['A','yeux',1],['B','œils',0],['C','yaux',0],['D','œillets',0]],
     'Le pluriel irrégulier de "œil" est "yeux".'],
    [$matiereMap['francais'], 'Quelle figure de style est utilisée dans "Le cœur de Pierre est de pierre" ?', 'DIFFICILE', 'EXAMEN_ETAT',
     [['A','Antanaclase',1],['B','Métaphore',0],['C','Oxymore',0],['D','Allitération',0]],
     'L\'antanaclase répète un mot avec deux sens différents.'],
    [$matiereMap['francais'], 'Quel est l\'antonyme de "prospère" ?', 'MOYEN', 'ENAFEP',
     [['A','décadent',1],['B','riche',0],['C','heureux',0],['D','florissant',0]],
     'L\'antonyme de prospère est décadent (en déclin).'],

    // Sciences
    [$matiereMap['sciences'], 'Quelle est la formule chimique de l\'eau ?', 'FACILE', 'ENAFEP',
     [['A','H₂O',1],['B','HO₂',0],['C','H₂O₂',0],['D','OH',0]],
     'L\'eau est une molécule constituée de 2 atomes d\'hydrogène et 1 d\'oxygène.'],
    [$matiereMap['sciences'], 'Quel organe produit la bile dans le corps humain ?', 'MOYEN', 'TENASOSP',
     [['A','Le foie',1],['B','Le pancréas',0],['C','L\'estomac',0],['D','Les reins',0]],
     'Le foie produit la bile, stockée dans la vésicule biliaire.'],
    [$matiereMap['sciences'], 'Combien d\'os compte le corps humain adulte ?', 'MOYEN', 'ENAFEP',
     [['A','206',1],['B','208',0],['C','198',0],['D','215',0]],
     'Le squelette adulte compte 206 os.'],

    // Chimie
    [$matiereMap['chimie'], 'Quel est le numéro atomique du carbone ?', 'FACILE', 'TENASOSP',
     [['A','6',1],['B','8',0],['C','12',0],['D','4',0]],
     'Le carbone (C) a le numéro atomique 6 dans le tableau périodique.'],
    [$matiereMap['chimie'], 'Quelle est la valeur du pH d\'une solution neutre à 25°C ?', 'MOYEN', 'EXAMEN_ETAT',
     [['A','7',1],['B','0',0],['C','14',0],['D','6,5',0]],
     'Une solution neutre a un pH = 7 à 25°C.'],
    [$matiereMap['chimie'], 'Combien d\'électrons peut contenir la couche M ?', 'DIFFICILE', 'TENASOSP',
     [['A','18',1],['B','8',0],['C','2',0],['D','32',0]],
     'La couche M (n=3) peut contenir au maximum 2n² = 18 électrons.'],

    // Physique
    [$matiereMap['physique'], 'Quelle est l\'unité de la force dans le système international ?', 'FACILE', 'ENAFEP',
     [['A','Newton (N)',1],['B','Pascal (Pa)',0],['C','Joule (J)',0],['D','Watt (W)',0]],
     'La force se mesure en Newton (N) = kg·m/s².'],
    [$matiereMap['physique'], 'Quelle est la vitesse de la lumière dans le vide ?', 'MOYEN', 'EXAMEN_ETAT',
     [['A','3 × 10⁸ m/s',1],['B','3 × 10⁶ m/s',0],['C','3 × 10¹⁰ m/s',0],['D','3 × 10⁴ m/s',0]],
     'La vitesse de la lumière dans le vide est c ≈ 3 × 10⁸ m/s.'],

    // Histoire-Géo
    [$matiereMap['histgeo'], 'En quelle année la RDC a-t-elle obtenu son indépendance ?', 'FACILE', 'ENAFEP',
     [['A','1960',1],['B','1956',0],['C','1964',0],['D','1958',0]],
     'La RDC (alors Congo-Belge) obtient son indépendance le 30 juin 1960.'],
    [$matiereMap['histgeo'], 'Quel est le plus grand fleuve de la RDC ?', 'FACILE', 'ENAFEP',
     [['A','Le Congo',1],['B','L\'Ubangi',0],['C','Le Kasaï',0],['D','Le Lomami',0]],
     'Le fleuve Congo est le plus grand fleuve de la RDC et le deuxième au monde par débit.'],

    // Biologie
    [$matiereMap['biologie'], 'Quelle est la fonction principale des mitochondries ?', 'MOYEN', 'TENASOSP',
     [['A','Production d\'énergie (ATP)',1],['B','Synthèse des protéines',0],['C','Division cellulaire',0],['D','Stockage de l\'ADN',0]],
     'Les mitochondries produisent l\'ATP via la respiration cellulaire.'],
    [$matiereMap['biologie'], 'Combien de chromosomes possède une cellule humaine diploïde normale ?', 'MOYEN', 'EXAMEN_ETAT',
     [['A','46',1],['B','23',0],['C','48',0],['D','44',0]],
     'Les cellules somatiques humaines contiennent 46 chromosomes (23 paires).'],

    // Anglais
    [$matiereMap['anglais'], 'What is the past tense of "go"?', 'FACILE', 'ENAFEP',
     [['A','went',1],['B','goed',0],['C','gone',0],['D','goes',0]],
     '"Go" is an irregular verb. Its simple past is "went".'],
    [$matiereMap['anglais'], 'Choose the correct sentence:', 'MOYEN', 'ENAFEP',
     [['A','She doesn\'t know the answer.',1],['B','She don\'t know the answer.',0],['C','She not knows the answer.',0],['D','She knows not the answer.',0]],
     'With third person singular (she/he/it), use "doesn\'t" for negation.'],
];

$stmtQ = $pdo->prepare(
    "INSERT INTO question_bank (id,matiere_id,enonce,difficulte,source,explication,is_active)
     VALUES (UUID(),?,?,?,?,?,1)"
);
$stmtO = $pdo->prepare(
    "INSERT INTO question_options (id,question_id,lettre,texte,est_correcte)
     VALUES (UUID(),?,?,?,?)"
);

foreach ($questions as [$matId, $enonce, $diff, $source, $opts, $expl]) {
    $stmtQ->execute([$matId, $enonce, $diff, $source, $expl]);
    $qId = $pdo->lastInsertId();
    // Récupérer l'UUID réel
    $qRow = $pdo->query("SELECT id FROM question_bank ORDER BY RAND() LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    // Chercher par enonce pour l'UUID
    $qRow = $pdo->prepare("SELECT id FROM question_bank WHERE enonce=? ORDER BY created_at DESC LIMIT 1");
    $qRow->execute([$enonce]);
    $qId = $qRow->fetchColumn();
    foreach ($opts as [$lettre, $texte, $correct]) {
        $stmtO->execute([$qId, $lettre, $texte, $correct]);
    }
}
log_step("  ✓ " . count($questions) . " questions avec options insérées.");

// ─── 6. Abonnement démo ─────────────────────────────────────
log_step("💳 Abonnement BASIQUE pour le compte démo...");
$pdo->exec("DELETE FROM abonnements WHERE user_id='$demoId'");
$pdo->prepare(
    "INSERT INTO abonnements (id,user_id,plan,montant,devise,methode_paiement,reference_paiement,telephone,statut,date_debut,date_fin,duree_mois,confirmed_at)
     VALUES (UUID(),?,?,?,?,?,?,?,?,?,?,?,NOW())"
)->execute([$demoId,'BASIQUE',5000,'CDF','MPESA','RP-SEEDDEMO','+243810000000','CONFIRME',date('Y-m-01'),date('Y-m-d', strtotime('+1 month')),1]);
$pdo->prepare("UPDATE utilisateurs SET plan='BASIQUE', plan_expire_at=? WHERE id=?")->execute([date('Y-m-d', strtotime('+1 month')), $demoId]);

// ─── 7. Activité journalière démo ───────────────────────────
log_step("📅 Activité journalière pour le compte démo...");
$pdo->exec("DELETE FROM activite_journaliere WHERE user_id='$demoId'");
$stmtAct = $pdo->prepare("INSERT INTO activite_journaliere (id,user_id,date_act,examens,questions) VALUES (UUID(),?,?,?,?) ON DUPLICATE KEY UPDATE examens=VALUES(examens)");
for ($i = 0; $i < 14; $i++) {
    if ($i % 3 === 2) continue; // quelques jours off
    $d = date('Y-m-d', strtotime("-$i days"));
    $stmtAct->execute([$demoId, $d, rand(1,3), rand(5,25)]);
}

// ─── 8. Progression par matière démo ───────────────────────
log_step("📊 Progression par matière...");
$pdo->exec("DELETE FROM user_progression WHERE user_id='$demoId'");
$stmtProg = $pdo->prepare("INSERT INTO user_progression (id,user_id,matiere_id,score_moyen,nb_examens,questions_vues) VALUES (UUID(),?,?,?,?,?)");
$progressData = [
    'maths'    => [72.5, 4, 40],
    'francais' => [65.0, 3, 30],
    'sciences' => [80.0, 2, 20],
    'histgeo'  => [55.0, 1, 10],
    'chimie'   => [60.0, 2, 20],
];
foreach ($progressData as $code => [$score, $nb, $q]) {
    if (!isset($matiereMap[$code])) continue;
    $stmtProg->execute([$demoId, $matiereMap[$code], $score, $nb, $q]);
}

// ─── 9. Sessions d'examen démo ─────────────────────────────
log_step("✏️ Sessions d'examen de démo...");
$pdo->exec("DELETE FROM exam_sessions WHERE user_id='$demoId'");
$stmtSess = $pdo->prepare(
    "INSERT INTO exam_sessions (id,user_id,matiere_id,titre,exam_type,nb_questions,score,pourcentage,temps_passe,statut,started_at,finished_at)
     VALUES (UUID(),?,?,?,?,?,?,?,?,?,?,?)"
);
$sessData = [
    ['maths',    'ENAFEP Maths 2024',  'ENAFEP',    10, 7,  70.0, 900],
    ['francais', 'ENAFEP Français',     'ENAFEP',    10, 6,  60.0, 720],
    ['sciences', 'Sciences Révision',   'TENASOSP',  5,  4,  80.0, 400],
    ['maths',    'TENASOSP Maths',      'TENASOSP',  10, 8,  80.0, 840],
    ['chimie',   'Chimie Pratique',     'EXAMEN_ETAT',5, 3,  60.0, 500],
];
foreach ($sessData as $i => [$mat, $titre, $type, $nb, $bon, $pct, $tps]) {
    $started = date('Y-m-d H:i:s', strtotime("-" . ($i + 1) . " days"));
    $ended   = date('Y-m-d H:i:s', strtotime($started) + $tps);
    $stmtSess->execute([$demoId, $matiereMap[$mat]??null, $titre, $type, $nb, $bon, $pct, $tps, 'TERMINE', $started, $ended]);
}
log_step("  ✓ 5 sessions d'examen créées.");

// ─── 10. Notification bienvenue ─────────────────────────────
log_step("🔔 Notifications de bienvenue...");
$pdo->exec("DELETE FROM notifications WHERE user_id='$demoId'");
$pdo->prepare(
    "INSERT INTO notifications (id,user_id,type,titre,message,lien) VALUES (UUID(),?,?,?,?,?)"
)->execute([$demoId, 'SYSTEME', '👋 Bienvenue sur RÉUSSITE+ !',
    'Bonjour Amani ! Votre compte de démonstration est prêt. Explorez les archives, passez des examens et suivez votre progression.',
    '/reussiteplus/dashboard.php'
]);
$pdo->prepare(
    "INSERT INTO notifications (id,user_id,type,titre,message,lien) VALUES (UUID(),?,?,?,?,?)"
)->execute([$demoId, 'PAIEMENT', '✅ Abonnement BASIQUE activé',
    'Votre abonnement BASIQUE a été activé avec succès. Vous avez accès à 30 examens par mois.',
    '/reussiteplus/abonnement.php'
]);

// ─── 11. Code promo démo ────────────────────────────────────
log_step("🎁 Code promo BIENVENUE2025...");
$pdo->exec("DELETE FROM codes_promo WHERE code='BIENVENUE2025'");
$pdo->prepare(
    "INSERT INTO codes_promo (id,code,type_remise,valeur_remise,plan_applicable,nb_max,actif,date_expiration)
     VALUES (UUID(),'BIENVENUE2025','POURCENTAGE',20,'TOUS',100,1,?)"
)->execute([date('Y-12-31')]);

$pdo->exec("SET FOREIGN_KEY_CHECKS=1");

echo "\n";
log_step("✅ Seeder terminé avec succès !\n");
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🔑 Comptes créés :\n";
echo "   Élève   : demo@reussiteplus.cd  / Demo1234!\n";
echo "   Admin   : admin@reussiteplus.cd / Admin2025!\n";
echo "   Prof    : prof@reussiteplus.cd  / Prof2025!\n";
echo "🎟  Code promo : BIENVENUE2025 (20% de réduction)\n";
echo "🌐 URL : http://localhost/reussiteplus\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
