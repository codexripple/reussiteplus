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
    // ── ENAFEP 2024 ──────────────────────────────────────────
    ['ENAFEP 2024 — Mathématiques',        2024,'ENAFEP',    'maths',    0],
    ['ENAFEP 2024 — Français',             2024,'ENAFEP',    'francais', 0],
    ['ENAFEP 2024 — Sciences Naturelles',  2024,'ENAFEP',    'sciences', 0],
    ['ENAFEP 2024 — Histoire-Géographie',  2024,'ENAFEP',    'histgeo',  0],
    ['ENAFEP 2024 — Anglais',              2024,'ENAFEP',    'anglais',  0],
    // ── ENAFEP 2023 ──────────────────────────────────────────
    ['ENAFEP 2023 — Mathématiques',        2023,'ENAFEP',    'maths',    0],
    ['ENAFEP 2023 — Français',             2023,'ENAFEP',    'francais', 0],
    ['ENAFEP 2023 — Sciences Naturelles',  2023,'ENAFEP',    'sciences', 0],
    ['ENAFEP 2023 — Histoire-Géographie',  2023,'ENAFEP',    'histgeo',  0],
    ['ENAFEP 2023 — Anglais',              2023,'ENAFEP',    'anglais',  0],
    // ── ENAFEP 2022 ──────────────────────────────────────────
    ['ENAFEP 2022 — Mathématiques',        2022,'ENAFEP',    'maths',    0],
    ['ENAFEP 2022 — Français',             2022,'ENAFEP',    'francais', 0],
    ['ENAFEP 2022 — Sciences Naturelles',  2022,'ENAFEP',    'sciences', 0],
    ['ENAFEP 2022 — Histoire-Géographie',  2022,'ENAFEP',    'histgeo',  0],
    ['ENAFEP 2022 — Anglais',              2022,'ENAFEP',    'anglais',  0],
    // ── ENAFEP 2021 ──────────────────────────────────────────
    ['ENAFEP 2021 — Mathématiques',        2021,'ENAFEP',    'maths',    0],
    ['ENAFEP 2021 — Français',             2021,'ENAFEP',    'francais', 0],
    ['ENAFEP 2021 — Sciences Naturelles',  2021,'ENAFEP',    'sciences', 0],
    ['ENAFEP 2021 — Histoire-Géographie',  2021,'ENAFEP',    'histgeo',  0],
    ['ENAFEP 2021 — Anglais',              2021,'ENAFEP',    'anglais',  0],
    // ── ENAFEP 2020 ──────────────────────────────────────────
    ['ENAFEP 2020 — Mathématiques',        2020,'ENAFEP',    'maths',    0],
    ['ENAFEP 2020 — Français',             2020,'ENAFEP',    'francais', 0],
    ['ENAFEP 2020 — Sciences Naturelles',  2020,'ENAFEP',    'sciences', 0],
    ['ENAFEP 2020 — Histoire-Géographie',  2020,'ENAFEP',    'histgeo',  0],
    ['ENAFEP 2020 — Anglais',              2020,'ENAFEP',    'anglais',  0],
    // ── ENAFEP 2019 ──────────────────────────────────────────
    ['ENAFEP 2019 — Mathématiques',        2019,'ENAFEP',    'maths',    0],
    ['ENAFEP 2019 — Français',             2019,'ENAFEP',    'francais', 0],
    ['ENAFEP 2019 — Sciences Naturelles',  2019,'ENAFEP',    'sciences', 0],
    ['ENAFEP 2019 — Histoire-Géographie',  2019,'ENAFEP',    'histgeo',  0],
    ['ENAFEP 2019 — Anglais',              2019,'ENAFEP',    'anglais',  0],
    // ── ENAFEP 2018 ──────────────────────────────────────────
    ['ENAFEP 2018 — Mathématiques',        2018,'ENAFEP',    'maths',    0],
    ['ENAFEP 2018 — Français',             2018,'ENAFEP',    'francais', 0],
    ['ENAFEP 2018 — Sciences Naturelles',  2018,'ENAFEP',    'sciences', 0],
    ['ENAFEP 2018 — Histoire-Géographie',  2018,'ENAFEP',    'histgeo',  0],
    ['ENAFEP 2021 — Français',             2021,'ENAFEP',    'francais', 0],
    ['ENAFEP 2021 — Sciences Naturelles',  2021,'ENAFEP',    'sciences', 0],
    ['ENAFEP 2021 — Histoire-Géographie',  2021,'ENAFEP',    'histgeo',  0],
    ['ENAFEP 2021 — Anglais',              2021,'ENAFEP',    'anglais',  0],
    // ── ENAFEP 2020 ──────────────────────────────────────────
    ['ENAFEP 2020 — Mathématiques',        2020,'ENAFEP',    'maths',    0],
    ['ENAFEP 2020 — Français',             2020,'ENAFEP',    'francais', 0],
    ['ENAFEP 2020 — Sciences Naturelles',  2020,'ENAFEP',    'sciences', 0],
    ['ENAFEP 2020 — Anglais',              2020,'ENAFEP',    'anglais',  0],
    // ── TENASOSP 2024 ────────────────────────────────────────
    ['TENASOSP 2024 — Mathématiques',      2024,'TENASOSP',  'maths',    1],
    ['TENASOSP 2024 — Chimie',             2024,'TENASOSP',  'chimie',   1],
    ['TENASOSP 2024 — Physique',           2024,'TENASOSP',  'physique', 1],
    ['TENASOSP 2024 — Biologie',           2024,'TENASOSP',  'biologie', 1],
    ['TENASOSP 2024 — Français',           2024,'TENASOSP',  'francais', 1],
    ['TENASOSP 2024 — Anglais',            2024,'TENASOSP',  'anglais',  1],
    // ── TENASOSP 2023 ────────────────────────────────────────
    ['TENASOSP 2023 — Mathématiques',      2023,'TENASOSP',  'maths',    1],
    ['TENASOSP 2023 — Chimie',             2023,'TENASOSP',  'chimie',   1],
    ['TENASOSP 2023 — Physique',           2023,'TENASOSP',  'physique', 1],
    ['TENASOSP 2023 — Biologie',           2023,'TENASOSP',  'biologie', 1],
    ['TENASOSP 2023 — Français',           2023,'TENASOSP',  'francais', 1],
    ['TENASOSP 2023 — Anglais',            2023,'TENASOSP',  'anglais',  1],
    // ── TENASOSP 2022 ────────────────────────────────────────
    ['TENASOSP 2022 — Mathématiques',      2022,'TENASOSP',  'maths',    1],
    ['TENASOSP 2022 — Chimie',             2022,'TENASOSP',  'chimie',   1],
    ['TENASOSP 2022 — Physique',           2022,'TENASOSP',  'physique', 1],
    ['TENASOSP 2022 — Biologie',           2022,'TENASOSP',  'biologie', 1],
    ['TENASOSP 2022 — Anglais',            2022,'TENASOSP',  'anglais',  1],
    // ── TENASOSP 2021 ────────────────────────────────────────
    ['TENASOSP 2021 — Mathématiques',      2021,'TENASOSP',  'maths',    1],
    ['TENASOSP 2021 — Chimie',             2021,'TENASOSP',  'chimie',   1],
    ['TENASOSP 2021 — Physique',           2021,'TENASOSP',  'physique', 1],
    ['TENASOSP 2021 — Biologie',           2021,'TENASOSP',  'biologie', 1],
    // ── TENASOSP 2022 ────────────────────────────────────────
    ['TENASOSP 2022 — Mathématiques',      2022,'TENASOSP',  'maths',    1],
    ['TENASOSP 2022 — Chimie',             2022,'TENASOSP',  'chimie',   1],
    ['TENASOSP 2022 — Physique',           2022,'TENASOSP',  'physique', 1],
    ['TENASOSP 2022 — Biologie',           2022,'TENASOSP',  'biologie', 1],
    ['TENASOSP 2022 — Français',           2022,'TENASOSP',  'francais', 1],
    // ── TENASOSP 2023 ────────────────────────────────────────
    ['TENASOSP 2023 — Mathématiques',      2023,'TENASOSP',  'maths',    1],
    ['TENASOSP 2023 — Chimie',             2023,'TENASOSP',  'chimie',   1],
    ['TENASOSP 2023 — Physique',           2023,'TENASOSP',  'physique', 1],
    ['TENASOSP 2023 — Biologie',           2023,'TENASOSP',  'biologie', 1],
    ['TENASOSP 2023 — Français',           2023,'TENASOSP',  'francais', 1],
    ['TENASOSP 2023 — Anglais',            2023,'TENASOSP',  'anglais',  1],
    // ── TENASOSP 2024 ────────────────────────────────────────
    ['TENASOSP 2024 — Mathématiques',      2024,'TENASOSP',  'maths',    1],
    ['TENASOSP 2024 — Chimie',             2024,'TENASOSP',  'chimie',   1],
    ['TENASOSP 2024 — Physique',           2024,'TENASOSP',  'physique', 1],
    ['TENASOSP 2024 — Biologie',           2024,'TENASOSP',  'biologie', 1],
    ['TENASOSP 2024 — Français',           2024,'TENASOSP',  'francais', 1],
    ['TENASOSP 2024 — Anglais',            2024,'TENASOSP',  'anglais',  1],
    // ── EXAMEN D'ÉTAT 2024 ───────────────────────────────────
    ['Examen d\'État 2024 — Mathématiques',2024,'EXAMEN_ETAT','maths',   1],
    ['Examen d\'État 2024 — Français',     2024,'EXAMEN_ETAT','francais',1],
    ['Examen d\'État 2024 — Chimie',       2024,'EXAMEN_ETAT','chimie',  1],
    ['Examen d\'État 2024 — Physique',     2024,'EXAMEN_ETAT','physique',1],
    ['Examen d\'État 2024 — Biologie',     2024,'EXAMEN_ETAT','biologie',1],
    ['Examen d\'État 2024 — Histoire-Géo', 2024,'EXAMEN_ETAT','histgeo', 1],
    ['Examen d\'État 2024 — Anglais',      2024,'EXAMEN_ETAT','anglais', 1],
    // ── EXAMEN D'ÉTAT 2023 ───────────────────────────────────
    ['Examen d\'État 2023 — Mathématiques',2023,'EXAMEN_ETAT','maths',   1],
    ['Examen d\'État 2023 — Français',     2023,'EXAMEN_ETAT','francais',1],
    ['Examen d\'État 2023 — Chimie',       2023,'EXAMEN_ETAT','chimie',  1],
    ['Examen d\'État 2023 — Physique',     2023,'EXAMEN_ETAT','physique',1],
    ['Examen d\'État 2023 — Biologie',     2023,'EXAMEN_ETAT','biologie',1],
    ['Examen d\'État 2023 — Histoire-Géo', 2023,'EXAMEN_ETAT','histgeo', 1],
    ['Examen d\'État 2023 — Anglais',      2023,'EXAMEN_ETAT','anglais', 1],
    // ── EXAMEN D'ÉTAT 2022 ───────────────────────────────────
    ['Examen d\'État 2022 — Mathématiques',2022,'EXAMEN_ETAT','maths',   1],
    ['Examen d\'État 2022 — Français',     2022,'EXAMEN_ETAT','francais',1],
    ['Examen d\'État 2022 — Chimie',       2022,'EXAMEN_ETAT','chimie',  1],
    ['Examen d\'État 2022 — Physique',     2022,'EXAMEN_ETAT','physique',1],
    ['Examen d\'État 2022 — Biologie',     2022,'EXAMEN_ETAT','biologie',1],
    ['Examen d\'État 2022 — Histoire-Géo', 2022,'EXAMEN_ETAT','histgeo', 1],
    // ── EXAMEN D'ÉTAT 2021 ───────────────────────────────────
    ['Examen d\'État 2021 — Mathématiques',2021,'EXAMEN_ETAT','maths',   1],
    ['Examen d\'État 2021 — Français',     2021,'EXAMEN_ETAT','francais',1],
    ['Examen d\'État 2021 — Chimie',       2021,'EXAMEN_ETAT','chimie',  1],
    ['Examen d\'État 2021 — Biologie',     2021,'EXAMEN_ETAT','biologie',1],
    ['Examen d\'État 2021 — Physique',     2021,'EXAMEN_ETAT','physique',1],
    // ── EXAMEN D'ÉTAT 2020 ───────────────────────────────────
    ['Examen d\'État 2020 — Mathématiques',2020,'EXAMEN_ETAT','maths',   1],
    ['Examen d\'État 2020 — Français',     2020,'EXAMEN_ETAT','francais',1],
    ['Examen d\'État 2020 — Sciences',     2020,'EXAMEN_ETAT','sciences',1],
    ['Examen d\'État 2020 — Anglais',      2020,'EXAMEN_ETAT','anglais', 1],
    // ── EXAMEN D'ÉTAT 2019 ───────────────────────────────────
    ['Examen d\'État 2019 — Mathématiques',2019,'EXAMEN_ETAT','maths',   1],
    ['Examen d\'État 2019 — Français',     2019,'EXAMEN_ETAT','francais',1],
    ['Examen d\'État 2019 — Chimie',       2019,'EXAMEN_ETAT','chimie',  1],
    ['Examen d\'État 2019 — Physique',     2019,'EXAMEN_ETAT','physique',1],
    ['Examen d\'État 2019 — Biologie',     2019,'EXAMEN_ETAT','biologie',1],
    ['Examen d\'État 2019 — Histoire-Géo', 2019,'EXAMEN_ETAT','histgeo', 1],
    ['Examen d\'État 2019 — Anglais',      2019,'EXAMEN_ETAT','anglais', 1],
    // ── EXAMEN D'ÉTAT 2018 ───────────────────────────────────
    ['Examen d\'État 2018 — Mathématiques',2018,'EXAMEN_ETAT','maths',   1],
    ['Examen d\'État 2018 — Français',     2018,'EXAMEN_ETAT','francais',1],
    ['Examen d\'État 2018 — Chimie',       2018,'EXAMEN_ETAT','chimie',  1],
    ['Examen d\'État 2018 — Physique',     2018,'EXAMEN_ETAT','physique',1],
    ['Examen d\'État 2018 — Biologie',     2018,'EXAMEN_ETAT','biologie',1],
    ['Examen d\'État 2018 — Histoire-Géo', 2018,'EXAMEN_ETAT','histgeo', 1],
    ['Examen d\'État 2018 — Anglais',      2018,'EXAMEN_ETAT','anglais', 1],
    // ── EXAMEN D'ÉTAT 2017 ───────────────────────────────────
    ['Examen d\'État 2017 — Mathématiques',2017,'EXAMEN_ETAT','maths',   1],
    ['Examen d\'État 2017 — Français',     2017,'EXAMEN_ETAT','francais',1],
    ['Examen d\'État 2017 — Chimie',       2017,'EXAMEN_ETAT','chimie',  1],
    ['Examen d\'État 2017 — Physique',     2017,'EXAMEN_ETAT','physique',1],
    ['Examen d\'État 2017 — Biologie',     2017,'EXAMEN_ETAT','biologie',1],
    ['Examen d\'État 2017 — Histoire-Géo', 2017,'EXAMEN_ETAT','histgeo', 1],
    // ── DIOCÉSAIN — Kinshasa 2024 ────────────────────────────
    ['Test Diocésain Kinshasa 2024 — Mathématiques', 2024,'DIOCESAIN','maths',    0],
    ['Test Diocésain Kinshasa 2024 — Français',      2024,'DIOCESAIN','francais', 0],
    ['Test Diocésain Kinshasa 2024 — Sciences',      2024,'DIOCESAIN','sciences', 0],
    ['Test Diocésain Kinshasa 2024 — Histoire-Géo',  2024,'DIOCESAIN','histgeo',  0],
    // ── DIOCÉSAIN — Kinshasa 2023 ────────────────────────────
    ['Test Diocésain Kinshasa 2023 — Mathématiques', 2023,'DIOCESAIN','maths',    0],
    ['Test Diocésain Kinshasa 2023 — Français',      2023,'DIOCESAIN','francais', 0],
    ['Test Diocésain Kinshasa 2023 — Sciences',      2023,'DIOCESAIN','sciences', 0],
    ['Test Diocésain Kinshasa 2023 — Histoire-Géo',  2023,'DIOCESAIN','histgeo',  0],
    // ── DIOCÉSAIN — Lubumbashi 2024 ──────────────────────────
    ['Test Diocésain Lubumbashi 2024 — Mathématiques',2024,'DIOCESAIN','maths',   0],
    ['Test Diocésain Lubumbashi 2024 — Français',     2024,'DIOCESAIN','francais',0],
    ['Test Diocésain Lubumbashi 2024 — Chimie',       2024,'DIOCESAIN','chimie',  0],
    ['Test Diocésain Lubumbashi 2024 — Physique',     2024,'DIOCESAIN','physique',0],
    // ── DIOCÉSAIN — Lubumbashi 2023 ──────────────────────────
    ['Test Diocésain Lubumbashi 2023 — Mathématiques',2023,'DIOCESAIN','maths',   0],
    ['Test Diocésain Lubumbashi 2023 — Français',     2023,'DIOCESAIN','francais',0],
    ['Test Diocésain Lubumbashi 2023 — Chimie',       2023,'DIOCESAIN','chimie',  0],
    // ── DIOCÉSAIN — Goma 2024 ────────────────────────────────
    ['Test Diocésain Goma 2024 — Mathématiques',     2024,'DIOCESAIN','maths',    0],
    ['Test Diocésain Goma 2024 — Français',          2024,'DIOCESAIN','francais', 0],
    ['Test Diocésain Goma 2024 — Biologie',          2024,'DIOCESAIN','biologie', 0],
    // ── DIOCÉSAIN — Mbuji-Mayi 2023 ──────────────────────────
    ['Test Diocésain Mbuji-Mayi 2023 — Mathématiques',2023,'DIOCESAIN','maths',   0],
    ['Test Diocésain Mbuji-Mayi 2023 — Français',     2023,'DIOCESAIN','francais',0],
    ['Test Diocésain Mbuji-Mayi 2023 — Sciences',     2023,'DIOCESAIN','sciences',0],
    // ── EXAMEN D'ÉTAT Rattrapage 2024 ────────────────────────
    ['Examen d\'État 2024 Rattrapage — Mathématiques',2024,'EXAMEN_ETAT','maths',  1],
    ['Examen d\'État 2024 Rattrapage — Français',     2024,'EXAMEN_ETAT','francais',1],
    ['Examen d\'État 2024 Rattrapage — Chimie',       2024,'EXAMEN_ETAT','chimie', 1],
    // ── EXAMEN D'ÉTAT Rattrapage 2023 ────────────────────────
    ['Examen d\'État 2023 Rattrapage — Mathématiques',2023,'EXAMEN_ETAT','maths',  1],
    ['Examen d\'État 2023 Rattrapage — Français',     2023,'EXAMEN_ETAT','francais',1],
];
$stA = $pdo->prepare("INSERT INTO archives (id,titre,annee,exam_type,matiere_id,description,premium_only,slug,status) VALUES (UUID(),?,?,?,?,?,?,?,?)");
$descMap = [
    'ENAFEP'      => 'Épreuve nationale de fin d\'études primaires — session %d.',
    'TENASOSP'    => 'Test national d\'entrée aux humanités scientifiques — session %d.',
    'EXAMEN_ETAT' => 'Examen national de fin d\'études secondaires — session %d.',
    'DIOCESAIN'   => 'Test d\'admission diocésain — session %d.',
];
foreach ($archivesData as [$titre,$annee,$type,$mat,$premium]) {
    $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', iconv('UTF-8','ASCII//TRANSLIT',$titre))) . '-' . uniqid();
    $desc = sprintf($descMap[$type] ?? 'Épreuve officielle %d.', $annee);
    $stA->execute([$titre,$annee,$type,$matiereMap[$mat],$desc,$premium,$slug,'PUBLIE']);
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

/* ═══════════════════════════════════════════
   MATHÉMATIQUES — PACK 3 (40 questions)
═══════════════════════════════════════════ */
$mat = $matiereMap['maths'];
$qs_m3 = [
['Quel est le résultat de √144 ?',                                                          'DEBUTANT',      'ENAFEP',     [['A','12',1],           ['B','14',0],            ['C','16',0],            ['D','10',0]]],
['Quelle est l\'image de x = −2 par f(x) = x² − 3x + 1 ?',                               'ELEMENTAIRE',   'TENASOSP',   [['A','11',1],           ['B','3',0],             ['C','−9',0],            ['D','7',0]]],
['Exprimer 60° en radians.',                                                                'INTERMEDIAIRE', 'TENASOSP',   [['A','π/3',1],          ['B','π/2',0],           ['C','π/6',0],           ['D','2π/3',0]]],
['Donner le domaine de définition de f(x) = 1/(x−3).',                                    'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','ℝ \\ {3}',1],      ['B','ℝ',0],             ['C','[3;+∞[',0],        ['D','ℝ⁺',0]]],
['Calculer : 5! (5 factorielle)',                                                            'ELEMENTAIRE',   'ENAFEP',     [['A','120',1],          ['B','60',0],            ['C','25',0],            ['D','20',0]]],
['Combien de diagonales a un hexagone ?',                                                   'INTERMEDIAIRE', 'ENAFEP',     [['A','9',1],            ['B','6',0],             ['C','12',0],            ['D','15',0]]],
['Quel est le reste de la division euclidienne de 47 par 5 ?',                             'DEBUTANT',      'ENAFEP',     [['A','2',1],            ['B','4',0],             ['C','7',0],             ['D','1',0]]],
['Dans un repère, quelle est la pente de y = 3x − 5 ?',                                   'ELEMENTAIRE',   'TENASOSP',   [['A','3',1],            ['B','−5',0],            ['C','5',0],             ['D','−3',0]]],
['Quel est le volume d\'une sphère de rayon r = 3 cm ?',                                   'AVANCE',        'EXAMEN_ETAT',[['A','36π cm³',1],       ['B','9π cm³',0],        ['C','12π cm³',0],       ['D','27π cm³',0]]],
['Combien y a-t-il de nombres premiers entre 1 et 20 ?',                                   'ELEMENTAIRE',   'ENAFEP',     [['A','8',1],            ['B','6',0],             ['C','10',0],            ['D','7',0]]],
['Simplifier la fraction 36/48.',                                                            'DEBUTANT',      'ENAFEP',     [['A','3/4',1],          ['B','6/8',0],           ['C','9/12',0],          ['D','2/3',0]]],
['Calculer la pente de la droite passant par A(1;2) et B(3;8).',                           'INTERMEDIAIRE', 'TENASOSP',   [['A','3',1],            ['B','5',0],             ['C','2',0],             ['D','6',0]]],
['La valeur absolue de −7 est :',                                                           'DEBUTANT',      'ENAFEP',     [['A','7',1],            ['B','−7',0],            ['C','49',0],            ['D','1/7',0]]],
['Quel est le développement de (a − b)² ?',                                                'ELEMENTAIRE',   'TENASOSP',   [['A','a² − 2ab + b²',1],['B','a² + 2ab + b²',0], ['C','a² − b²',0],       ['D','2a − 2b',0]]],
['Si un triangle a des angles 40° et 70°, le troisième angle vaut :',                      'DEBUTANT',      'ENAFEP',     [['A','70°',1],          ['B','90°',0],           ['C','50°',0],           ['D','60°',0]]],
['Calculer : ∑ᵢ₌₁⁵ i (somme de 1 à 5)',                                                   'ELEMENTAIRE',   'ENAFEP',     [['A','15',1],           ['B','10',0],            ['C','20',0],            ['D','25',0]]],
['La probabilité de tirer un as d\'un jeu de 52 cartes est :',                             'INTERMEDIAIRE', 'TENASOSP',   [['A','1/13',1],         ['B','1/52',0],          ['C','4/52',0],          ['D','1/4',0]]],
['Résoudre : x² = 25',                                                                      'ELEMENTAIRE',   'ENAFEP',     [['A','x = 5 ou x = −5',1],['B','x = 5',0],       ['C','x = √25',0],       ['D','x = ±√5',0]]],
['Quel est le résultat de (2³)² ?',                                                         'ELEMENTAIRE',   'ENAFEP',     [['A','64',1],           ['B','12',0],            ['C','16',0],            ['D','32',0]]],
['Quelle est la formule de l\'aire d\'un triangle (base b, hauteur h) ?',                  'DEBUTANT',      'ENAFEP',     [['A','A = (b × h)/2',1],['B','A = b × h',0],      ['C','A = b + h',0],     ['D','A = 2(b + h)',0]]],
['Si log(x) = 2, alors x vaut :',                                                           'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','100',1],          ['B','20',0],            ['C','1000',0],          ['D','2',0]]],
['Donner la valeur de sin²(x) + cos²(x).',                                                 'ELEMENTAIRE',   'TENASOSP',   [['A','1',1],            ['B','0',0],             ['C','2',0],             ['D','sin(2x)',0]]],
['Combien vaut C(5,2) = nombre de combinaisons de 5 éléments pris 2 à 2 ?',               'AVANCE',        'EXAMEN_ETAT',[['A','10',1],           ['B','20',0],            ['C','5',0],             ['D','15',0]]],
['La somme des 10 premiers entiers naturels (0 à 9) est :',                                'ELEMENTAIRE',   'ENAFEP',     [['A','45',1],           ['B','55',0],            ['C','50',0],            ['D','40',0]]],
['Quel est le périmètre d\'un cercle de rayon 7 cm ?',                                     'ELEMENTAIRE',   'ENAFEP',     [['A','14π cm',1],       ['B','7π cm',0],         ['C','49π cm',0],        ['D','28 cm',0]]],
['Résoudre : 5(x − 2) = 3(x + 4)',                                                         'INTERMEDIAIRE', 'TENASOSP',   [['A','x = 11',1],       ['B','x = 7',0],         ['C','x = 3',0],         ['D','x = −1',0]]],
['Quelle est l\'équation d\'une droite parallèle à y = 2x + 1 passant par (0;3) ?',       'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','y = 2x + 3',1],   ['B','y = 3x + 2',0],    ['C','y = 2x − 3',0],    ['D','y = −x/2 + 3',0]]],
['Factoriser : 6x² − 12x',                                                                  'ELEMENTAIRE',   'ENAFEP',     [['A','6x(x − 2)',1],   ['B','6(x² − 2)',0],     ['C','12x(x − 1)',0],    ['D','6x² − 12',0]]],
['Si P(A) = 0,4, quelle est la probabilité de l\'événement complémentaire ?',              'ELEMENTAIRE',   'ENAFEP',     [['A','0,6',1],          ['B','0,4',0],           ['C','0,04',0],          ['D','1,4',0]]],
['Calculer : (−2)⁴',                                                                        'ELEMENTAIRE',   'ENAFEP',     [['A','16',1],           ['B','−16',0],           ['C','8',0],             ['D','−8',0]]],
['Quelle est la médiane d\'une série : 4, 7, 2, 9, 1, 5 ?',                               'INTERMEDIAIRE', 'TENASOSP',   [['A','4,5',1],          ['B','4',0],             ['C','5',0],             ['D','7',0]]],
['Exprimer 0,0035 en notation scientifique.',                                               'INTERMEDIAIRE', 'TENASOSP',   [['A','3,5 × 10⁻³',1],  ['B','35 × 10⁻⁴',0],    ['C','3,5 × 10³',0],     ['D','0,35 × 10⁻²',0]]],
['La droite d\'équation x = 4 est :',                                                       'ELEMENTAIRE',   'ENAFEP',     [['A','Verticale',1],    ['B','Horizontale',0],   ['C','Oblique',0],       ['D','Parallèle à y = x',0]]],
['Calculer la variance de : 2, 4, 4, 4, 5, 5, 7, 9.',                                     'AVANCE',        'EXAMEN_ETAT',[['A','4',1],             ['B','2',0],             ['C','5',0],             ['D','3',0]]],
['Si f\'(x) = 6x + 2, alors f(x) = ?',                                                     'AVANCE',        'EXAMEN_ETAT',[['A','3x² + 2x + C',1], ['B','6x² + 2x',0],      ['C','3x + 2',0],        ['D','6x² + C',0]]],
['Résoudre dans ℝ : x² − 4x + 4 = 0',                                                     'INTERMEDIAIRE', 'TENASOSP',   [['A','x = 2 (double)',1],['B','x = 2 ou x = −2',0],['C','x = −2 (double)',0],['D','Pas de solution réelle',0]]],
['Calculer l\'angle en radians correspondant à 270°.',                                     'AVANCE',        'EXAMEN_ETAT',[['A','3π/2',1],          ['B','π',0],             ['C','2π',0],            ['D','π/4',0]]],
['Quel est le nombre de termes d\'une suite arithmétique de 1 à 100 ?',                    'INTERMEDIAIRE', 'ENAFEP',     [['A','100',1],          ['B','99',0],            ['C','101',0],           ['D','50',0]]],
['La somme d\'une suite géométrique S = a(1−rⁿ)/(1−r). Si a=2, r=3, n=4, S= ?',          'EXPERT',        'EXAMEN_ETAT',[['A','80',1],            ['B','40',0],            ['C','162',0],           ['D','20',0]]],
['Calculer le déterminant de la matrice [[2,1],[3,4]].',                                   'EXPERT',        'EXAMEN_ETAT',[['A','5',1],             ['B','11',0],            ['C','14',0],            ['D','−1',0]]],
];
foreach ($qs_m3 as [$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$mat,$e,$d,$s,$o); $total_q++; }

/* ═══════════════════════════════════════════
   FRANÇAIS — PACK 3 (35 questions)
═══════════════════════════════════════════ */
$mat = $matiereMap['francais'];
$qs_fr3 = [
['Quel est le féminin de "sportif" ?',                                                      'DEBUTANT',      'ENAFEP',     [['A','sportive',1],     ['B','sportife',0],      ['C','sporteuse',0],     ['D','sportrice',0]]],
['Quel mot est invariable dans "Elle chante faux." ?',                                      'ELEMENTAIRE',   'ENAFEP',     [['A','faux (adverbe)',1],['B','chante',0],        ['C','Elle',0],          ['D','faux (adjectif)',0]]],
['Identifier le COI dans : "Il parle à ses amis de son voyage."',                           'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','à ses amis',1],   ['B','son voyage',0],    ['C','Il',0],            ['D','de son voyage',0]]],
['Quelle est la différence entre "davantage" et "d\'avantage" ?',                          'AVANCE',        'EXAMEN_ETAT',[['A','davantage = plus ; d\'avantage = d\'un bénéfice',1],['B','Ils sont synonymes',0],['C','d\'avantage = plus',0],['D','Aucune différence à l\'oral',0]]],
['"Sa voix était douce comme du miel." La figure de style est :',                           'INTERMEDIAIRE', 'ENAFEP',     [['A','Comparaison',1],  ['B','Métaphore',0],     ['C','Hyperbole',0],     ['D','Personnification',0]]],
['"Il a une mémoire d\'éléphant." La figure de style est :',                               'ELEMENTAIRE',   'ENAFEP',     [['A','Métaphore',1],    ['B','Comparaison',0],   ['C','Allégorie',0],     ['D','Litote',0]]],
['Conjuguer "vouloir" au conditionnel présent, 1ère pers. pluriel :',                      'ELEMENTAIRE',   'ENAFEP',     [['A','nous voudrions',1],['B','nous voulons',0],  ['C','nous voudrons',0], ['D','nous voulurions',0]]],
['Quel est l\'accord du participe passé dans : "Les livres qu\'il a lus..." ?',            'AVANCE',        'EXAMEN_ETAT',[['A','lus (s\'accorde avec "livres", COD avant avoir)',1],['B','lu (invariable)',0],['C','lues',0],['D','lut',0]]],
['Dans "Bien qu\'il soit fatigué, il travaille.", le temps verbal de "soit" est :',        'AVANCE',        'EXAMEN_ETAT',[['A','Subjonctif présent',1],['B','Indicatif présent',0],['C','Conditionnel',0],['D','Impératif',0]]],
['Quel registre de langue est utilisé dans : "J\'ai pas vu ce film." ?',                  'ELEMENTAIRE',   'ENAFEP',     [['A','Familier',1],     ['B','Soutenu',0],       ['C','Standard',0],      ['D','Argotique',0]]],
['Quelle est la nature de "que" dans : "Je sais que tu viendras." ?',                     'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','Conjonction de subordination',1],['B','Pronom relatif',0],['C','Adverbe',0],['D','Déterminant',0]]],
['Dans une narration, le point de vue où le narrateur sait tout s\'appelle :',             'AVANCE',        'EXAMEN_ETAT',[['A','Focalisation zéro (omniscient)',1],['B','Focalisation interne',0],['C','Focalisation externe',0],['D','Narration neutre',0]]],
['Quel est le pluriel de "corail" ?',                                                       'ELEMENTAIRE',   'ENAFEP',     [['A','coraux',1],       ['B','corails',0],       ['C','corailes',0],      ['D','corauxe',0]]],
['"Il pleut des cordes." signifie :',                                                       'ELEMENTAIRE',   'ENAFEP',     [['A','Il pleut très fort',1],['B','Des cordes tombent',0],['C','Le ciel est nuageux',0],['D','Il fait froid',0]]],
['Le mot "homophones" désigne des mots qui :',                                              'ELEMENTAIRE',   'ENAFEP',     [['A','Se prononcent de la même façon',1],['B','S\'écrivent pareil',0],['C','Ont le même sens',0],['D','Appartiennent à la même famille',0]]],
['Quelle préposition utilise-t-on avant un infinitif après "essayer" ?',                   'ELEMENTAIRE',   'ENAFEP',     [['A','de',1],           ['B','à',0],             ['C','en',0],            ['D','pour',0]]],
['Dans quel cas écrit-on "quoique" en un mot ?',                                            'AVANCE',        'EXAMEN_ETAT',[['A','Quand c\'est une conjonction de subordination (= bien que)',1],['B','Jamais',0],['C','Toujours devant un adjectif',0],['D','Devant un verbe',0]]],
['Quel est le genre du nom "tentacule" ?',                                                  'INTERMEDIAIRE', 'ENAFEP',     [['A','Masculin',1],     ['B','Féminin',0],       ['C','Épicène',0],       ['D','Neutre',0]]],
['Le préfixe "bi-" indique :',                                                              'DEBUTANT',      'ENAFEP',     [['A','Deux',1],         ['B','Trois',0],         ['C','Un',0],            ['D','Moitié',0]]],
['Dans la phrase "Viens ici !", quel est le mode ?',                                        'ELEMENTAIRE',   'ENAFEP',     [['A','Impératif',1],    ['B','Subjonctif',0],    ['C','Indicatif',0],     ['D','Infinitif',0]]],
['"Je ne mange que des légumes." La négation utilisée est une :',                          'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','Restriction (ne...que)',1],['B','Négation totale',0],['C','Négation partielle',0],['D','Double négation',0]]],
['Identifier le groupe sujet dans : "Travailler dur mène au succès."',                     'AVANCE',        'EXAMEN_ETAT',[['A','Travailler dur (groupe infinitif)',1],['B','au succès',0],['C','mène',0],['D','Travailler',0]]],
['Quel est l\'antonyme de "intrépide" ?',                                                   'INTERMEDIAIRE', 'ENAFEP',     [['A','peureux',1],      ['B','courageux',0],     ['C','audacieux',0],     ['D','téméraire',0]]],
['Le discours direct se reconnaît à :',                                                     'ELEMENTAIRE',   'ENAFEP',     [['A','L\'usage de guillemets et verbe introducteur',1],['B','L\'absence de ponctuation',0],['C','La troisième personne obligatoire',0],['D','L\'imparfait du subjonctif',0]]],
['Donner un exemple de phrase à la voix passive.',                                          'ELEMENTAIRE',   'TENASOSP',   [['A','Le livre a été lu par Marie.',1],['B','Marie lit le livre.',0],['C','Lire un livre plaît.',0],['D','Marie est lectrice.',0]]],
['Quel temps exprime une action antérieure à une autre dans le passé ?',                   'AVANCE',        'EXAMEN_ETAT',[['A','Plus-que-parfait',1],['B','Imparfait',0],    ['C','Passé simple',0],  ['D','Passé composé',0]]],
['Que signifie l\'expression "avoir le cafard" ?',                                          'ELEMENTAIRE',   'ENAFEP',     [['A','Être déprimé',1], ['B','Avoir peur des insectes',0],['C','Être curieux',0],['D','Manquer d\'argent',0]]],
['Quelle est la fonction de "silencieusement" dans : "Il parle silencieusement." ?',      'ELEMENTAIRE',   'ENAFEP',     [['A','Complément circonstanciel de manière',1],['B','Attribut du sujet',0],['C','COD',0],['D','Épithète',0]]],
['Quel est le synonyme de "labeur" ?',                                                      'ELEMENTAIRE',   'ENAFEP',     [['A','travail',1],      ['B','loisir',0],        ['C','repos',0],         ['D','fête',0]]],
['Comment appelle-t-on un mot qui peut être nom et adjectif sans changer de forme ?',      'AVANCE',        'EXAMEN_ETAT',[['A','Épicène',1],       ['B','Invariable',0],    ['C','Neutre',0],        ['D','Pléonasme',0]]],
['Dans "Le soleil se couche.", quel est le verbe pronominal ?',                             'ELEMENTAIRE',   'ENAFEP',     [['A','se couche',1],   ['B','couche',0],        ['C','se',0],            ['D','soleil',0]]],
['Quel est le superlatif absolu de "grand" ?',                                              'ELEMENTAIRE',   'ENAFEP',     [['A','très grand / grandissime',1],['B','plus grand',0],['C','le plus grand',0],['D','grand du tout',0]]],
['L\'ellipse narrative est une technique qui consiste à :',                                 'EXPERT',        'EXAMEN_ETAT',[['A','Omettre une période de temps dans le récit',1],['B','Répéter un mot pour insister',0],['C','Inverser l\'ordre des événements',0],['D','Ralentir le rythme du récit',0]]],
['Identifier la proposition principale dans : "Quand il pleut, je reste chez moi."',       'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','je reste chez moi',1],['B','Quand il pleut',0],['C','Quand il pleut, je',0],['D','il pleut',0]]],
['Quelle est la définition d\'un chiasme ?',                                                'EXPERT',        'EXAMEN_ETAT',[['A','Structure croisée : AB / BA',1],['B','Répétition en fin de vers',0],['C','Opposition de deux termes contraires',0],['D','Jeu sur le sens d\'un mot',0]]],
];
foreach ($qs_fr3 as [$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$mat,$e,$d,$s,$o); $total_q++; }

/* ═══════════════════════════════════════════
   CHIMIE — PACK 3 (35 questions)
═══════════════════════════════════════════ */
$mat = $matiereMap['chimie'];
$qs_ch3 = [
['Quel est le symbole du fer ?',                                                             'DEBUTANT',      'ENAFEP',     [['A','Fe',1],           ['B','Fr',0],            ['C','F',0],             ['D','Fi',0]]],
['Quelle est la masse atomique approx. de l\'oxygène ?',                                   'DEBUTANT',      'ENAFEP',     [['A','16 g/mol',1],     ['B','8 g/mol',0],       ['C','32 g/mol',0],      ['D','12 g/mol',0]]],
['Une solution de pH = 3 est :',                                                             'ELEMENTAIRE',   'TENASOSP',   [['A','Acide',1],        ['B','Basique',0],       ['C','Neutre',0],        ['D','Amphotère',0]]],
['Combien d\'atomes d\'hydrogène contient C₂H₆ ?',                                         'ELEMENTAIRE',   'ENAFEP',     [['A','6',1],            ['B','2',0],             ['C','8',0],             ['D','4',0]]],
['Quelle est la formule de l\'ammoniac ?',                                                   'ELEMENTAIRE',   'TENASOSP',   [['A','NH₃',1],          ['B','NaOH',0],          ['C','N₂H₄',0],          ['D','HNO₃',0]]],
['Quel est le symbole du sodium ?',                                                          'DEBUTANT',      'ENAFEP',     [['A','Na',1],           ['B','So',0],            ['C','Sd',0],            ['D','Sn',0]]],
['La formule de l\'acide nitrique est :',                                                    'ELEMENTAIRE',   'TENASOSP',   [['A','HNO₃',1],         ['B','HNO₂',0],          ['C','NO₃',0],           ['D','H₂NO₃',0]]],
['Dans la réaction Zn + H₂SO₄ → ZnSO₄ + H₂, le zinc est :',                               'INTERMEDIAIRE', 'TENASOSP',   [['A','Oxydé (perd des e⁻)',1],['B','Réduit',0],    ['C','Catalyseur',0],    ['D','Spectateur',0]]],
['Quelle liaison est formée entre Na⁺ et Cl⁻ dans NaCl ?',                                 'INTERMEDIAIRE', 'TENASOSP',   [['A','Liaison ionique',1],['B','Liaison covalente',0],['C','Liaison métallique',0],['D','Liaison hydrogène',0]]],
['La loi d\'Avogadro indique qu\'une mole contient environ :',                              'ELEMENTAIRE',   'TENASOSP',   [['A','6,022 × 10²³ entités',1],['B','3,14 × 10²³',0],['C','6,022 × 10²⁰',0],['D','1 × 10²³',0]]],
['La distillation permet de séparer deux liquides en fonction de leur :',                   'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','Point d\'ébullition',1],['B','Densité',0],   ['C','Couleur',0],       ['D','pH',0]]],
['La formule de l\'acide acétique est :',                                                    'INTERMEDIAIRE', 'TENASOSP',   [['A','CH₃COOH',1],      ['B','C₂H₅OH',0],        ['C','HCOOH',0],         ['D','CH₃OH',0]]],
['Quel est le produit de la réaction Al + O₂ → ?',                                          'INTERMEDIAIRE', 'TENASOSP',   [['A','Al₂O₃',1],        ['B','AlO',0],           ['C','AlO₂',0],          ['D','Al₂O',0]]],
['Dans l\'eau, H₂O, l\'angle H−O−H vaut environ :',                                        'AVANCE',        'EXAMEN_ETAT',[['A','104,5°',1],        ['B','90°',0],           ['C','120°',0],          ['D','180°',0]]],
['Quelle est la formule du carbonate de calcium (calcaire) ?',                              'ELEMENTAIRE',   'TENASOSP',   [['A','CaCO₃',1],        ['B','CaO',0],           ['C','Ca(OH)₂',0],       ['D','CaCl₂',0]]],
['Les alcènes sont des hydrocarbures comportant :',                                          'AVANCE',        'EXAMEN_ETAT',[['A','Une double liaison C=C',1],['B','Une triple liaison',0],['C','Uniquement des liaisons simples',0],['D','Un cycle benzénique',0]]],
['L\'éthanol a pour formule :',                                                              'ELEMENTAIRE',   'TENASOSP',   [['A','C₂H₅OH',1],       ['B','CH₄',0],           ['C','CH₃OH',0],         ['D','C₃H₇OH',0]]],
['La chromatographie sert à :',                                                              'ELEMENTAIRE',   'ENAFEP',     [['A','Séparer les constituants d\'un mélange',1],['B','Mesurer la température',0],['C','Doser un acide',0],['D','Provoquer une réaction',0]]],
['La réaction Fe + CuSO₄ → FeSO₄ + Cu est une réaction de :',                              'INTERMEDIAIRE', 'TENASOSP',   [['A','Déplacement (oxydo-réduction)',1],['B','Combustion',0],['C','Neutralisation',0],['D','Précipitation',0]]],
['Quel est le nom du composé NaHCO₃ ?',                                                     'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','Bicarbonate de sodium',1],['B','Chlorure de sodium',0],['C','Sulfate de sodium',0],['D','Hydroxyde de sodium',0]]],
['La constante d\'équilibre Kc d\'une réaction augmente quand :',                           'EXPERT',        'EXAMEN_ETAT',[['A','La température augmente (pour une réaction endothermique)',1],['B','La concentration augmente',0],['C','La pression augmente',0],['D','Un catalyseur est ajouté',0]]],
['Quelle est la charge d\'un proton ?',                                                      'DEBUTANT',      'ENAFEP',     [['A','+1,6 × 10⁻¹⁹ C',1],['B','−1,6 × 10⁻¹⁹ C',0],['C','0',0],           ['D','+1 eV',0]]],
['La structure de Lewis du CO₂ montre :',                                                    'AVANCE',        'EXAMEN_ETAT',[['A','Deux doubles liaisons C=O',1],['B','Deux liaisons simples C−O',0],['C','Une triple liaison',0],['D','Une liaison ionique',0]]],
['Quel métal réagit violemment avec l\'eau à température ambiante ?',                       'ELEMENTAIRE',   'TENASOSP',   [['A','Sodium (Na)',1],  ['B','Fer (Fe)',0],       ['C','Cuivre (Cu)',0],   ['D','Or (Au)',0]]],
['La loi de Beer-Lambert relie l\'absorbance à :',                                           'EXPERT',        'EXAMEN_ETAT',[['A','La concentration et l\'épaisseur de la solution',1],['B','La température',0],['C','La pression',0],['D','La masse molaire',0]]],
['Les alcanes ont la formule générale :',                                                    'INTERMEDIAIRE', 'TENASOSP',   [['A','CₙH₂ₙ₊₂',1],     ['B','CₙH₂ₙ',0],         ['C','CₙH₂ₙ₋₂',0],       ['D','CₙHₙ',0]]],
['Quel indicateur coloré vire au rouge en milieu acide ?',                                   'ELEMENTAIRE',   'ENAFEP',     [['A','Phénolphtaléine (incolore) et tournesol (rouge)',1],['B','Bleu de méthylène',0],['C','Hélianthine seule',0],['D','Aucun indicateur',0]]],
['La chaleur de combustion est l\'énergie dégagée lors de :',                               'INTERMEDIAIRE', 'TENASOSP',   [['A','La combustion complète d\'une mole de substance',1],['B','La fusion d\'un solide',0],['C','La dissolution d\'un sel',0],['D','La vaporisation d\'un liquide',0]]],
['Le phosphore (P) se trouve dans la période :',                                             'AVANCE',        'EXAMEN_ETAT',[['A','Période 3',1],      ['B','Période 2',0],     ['C','Période 4',0],     ['D','Période 1',0]]],
['Quelle est la réaction de saponification ?',                                               'EXPERT',        'EXAMEN_ETAT',[['A','Ester + NaOH → Carboxylate + Alcool',1],['B','Alcool + Acide → Ester',0],['C','Aldéhyde + H₂ → Alcool',0],['D','Cétone + eau → Acide',0]]],
['Dans la cellule électrolytique, la réduction a lieu à :',                                  'EXPERT',        'EXAMEN_ETAT',[['A','La cathode',1],     ['B','L\'anode',0],      ['C','Les deux électrodes',0],['D','La membrane',0]]],
['Quelle est la différence entre une réaction réversible et irréversible ?',                'AVANCE',        'EXAMEN_ETAT',[['A','Réversible peut aller dans les deux sens ; irréversible va dans un seul sens',1],['B','Elles sont identiques',0],['C','Réversible est plus rapide',0],['D','Irréversible libère plus d\'énergie',0]]],
['Quel est le rôle d\'un catalyseur ?',                                                      'INTERMEDIAIRE', 'TENASOSP',   [['A','Accélérer la réaction sans être consommé',1],['B','Augmenter le rendement',0],['C','Changer les produits de réaction',0],['D','Ralentir la réaction',0]]],
['La configuration de l\'oxygène (Z=8) dans son état fondamental est :',                    'AVANCE',        'EXAMEN_ETAT',[['A','1s² 2s² 2p⁴',1],  ['B','1s² 2s² 2p²',0],   ['C','1s² 2s⁴ 2p²',0],   ['D','1s⁴ 2s² 2p²',0]]],
['Qu\'est-ce qu\'un isomère optique ?',                                                      'EXPERT',        'EXAMEN_ETAT',[['A','Molécule image non superposable de son miroir',1],['B','Molécule de même formule brute',0],['C','Molécule plus légère',0],['D','Atome de carbone quaternaire',0]]],
];
foreach ($qs_ch3 as [$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$mat,$e,$d,$s,$o); $total_q++; }

/* ═══════════════════════════════════════════
   PHYSIQUE — PACK 3 (35 questions)
═══════════════════════════════════════════ */
$mat = $matiereMap['physique'];
$qs_ph3 = [
['Quelle est l\'unité de la résistance électrique ?',                                       'DEBUTANT',      'ENAFEP',     [['A','Ohm (Ω)',1],       ['B','Volt',0],          ['C','Ampère',0],        ['D','Watt',0]]],
['Quelle est la formule de la pression (F = force, S = surface) ?',                        'ELEMENTAIRE',   'ENAFEP',     [['A','P = F/S',1],      ['B','P = F × S',0],     ['C','P = S/F',0],       ['D','P = F − S',0]]],
['Un objet lancé à 20 m/s vers le haut : combien de secondes met-il à s\'arrêter (g=10)?', 'INTERMEDIAIRE', 'TENASOSP',   [['A','2 s',1],          ['B','4 s',0],           ['C','10 s',0],          ['D','1 s',0]]],
['La loi de Coulomb F = k·q₁·q₂/r² décrit :',                                              'AVANCE',        'EXAMEN_ETAT',[['A','La force entre deux charges électriques',1],['B','La force gravitationnelle',0],['C','La force magnétique',0],['D','La tension électrique',0]]],
['Quelle est la formule de l\'énergie potentielle gravitationnelle ?',                      'INTERMEDIAIRE', 'TENASOSP',   [['A','Ep = mgh',1],     ['B','Ep = ½mv²',0],     ['C','Ep = mv',0],       ['D','Ep = mg²h',0]]],
['Un transformateur avec N₁ = 200 et N₂ = 400 spires. Si U₁ = 120V, U₂ = ?',             'AVANCE',        'EXAMEN_ETAT',[['A','240 V',1],         ['B','60 V',0],          ['C','480 V',0],         ['D','120 V',0]]],
['La diffraction est caractéristique de :',                                                  'AVANCE',        'EXAMEN_ETAT',[['A','Tout phénomène ondulatoire',1],['B','Seulement la lumière',0],['C','Seulement le son',0],['D','Les particules massives',0]]],
['La charge électrique d\'un électron est :',                                               'ELEMENTAIRE',   'ENAFEP',     [['A','−1,6 × 10⁻¹⁹ C',1],['B','+1,6 × 10⁻¹⁹ C',0],['C','0',0],          ['D','−9,1 × 10⁻³¹ C',0]]],
['Trois résistances R = 3 Ω chacune en parallèle. La résistance totale est :',             'INTERMEDIAIRE', 'TENASOSP',   [['A','1 Ω',1],          ['B','9 Ω',0],           ['C','3 Ω',0],           ['D','6 Ω',0]]],
['L\'effet Joule correspond à :',                                                             'INTERMEDIAIRE', 'TENASOSP',   [['A','Dissipation d\'énergie sous forme de chaleur dans un conducteur',1],['B','Production de lumière',0],['C','Création d\'un courant par induction',0],['D','Refroidissement d\'un conducteur',0]]],
['Dans un mouvement uniformément accéléré, la vitesse v = v₀ + at. Si v₀=0, a=5, t=4 :',  'ELEMENTAIRE',   'ENAFEP',     [['A','v = 20 m/s',1],   ['B','v = 9 m/s',0],     ['C','v = 1,25 m/s',0],  ['D','v = 0,8 m/s',0]]],
['Le principe de conservation de l\'énergie affirme que :',                                  'INTERMEDIAIRE', 'ENAFEP',     [['A','L\'énergie totale d\'un système isolé reste constante',1],['B','L\'énergie se crée dans les réactions chimiques',0],['C','L\'énergie cinétique est toujours maximale',0],['D','L\'énergie peut disparaître',0]]],
['Quelle est l\'unité de la fréquence ?',                                                   'DEBUTANT',      'ENAFEP',     [['A','Hertz (Hz)',1],   ['B','Seconde',0],       ['C','Mètre',0],         ['D','Newton',0]]],
['La relation entre longueur d\'onde λ, fréquence f et célérité c est :',                  'INTERMEDIAIRE', 'TENASOSP',   [['A','c = λ × f',1],   ['B','c = λ + f',0],     ['C','c = λ / f',0],     ['D','λ = c × f',0]]],
['Le magnétisme et l\'électricité sont unifiés dans la théorie de :',                       'AVANCE',        'EXAMEN_ETAT',[['A','Maxwell (électromagnétisme)',1],['B','Newton',0],        ['C','Einstein (relativité)',0],['D','Planck',0]]],
['Un condensateur de capacité C = 10 µF chargé à U = 100 V stocke une énergie de :',      'AVANCE',        'EXAMEN_ETAT',[['A','0,05 J',1],        ['B','1000 J',0],        ['C','0,5 J',0],         ['D','5 J',0]]],
['La loi de Newton de gravitation universelle : F = G·M·m/r². G est :',                    'INTERMEDIAIRE', 'TENASOSP',   [['A','La constante gravitationnelle (6,67 × 10⁻¹¹)',1],['B','L\'accélération g',0],['C','La masse du soleil',0],['D','Le rayon de la Terre',0]]],
['La lumière est à la fois onde et particule : on parle de :',                               'EXPERT',        'EXAMEN_ETAT',[['A','Dualité onde-corpuscule',1],['B','Effet photoélectrique seul',0],['C','Réfraction',0],['D','Polarisation',0]]],
['La pression d\'un gaz parfait à volume constant est proportionnelle à :',                 'AVANCE',        'EXAMEN_ETAT',[['A','La température absolue T (en Kelvin)',1],['B','La masse molaire',0],['C','La pression externe',0],['D','La racine de T',0]]],
['Quelle est la couleur de la lumière ayant la plus haute fréquence visible ?',             'ELEMENTAIRE',   'ENAFEP',     [['A','Violet',1],       ['B','Rouge',0],         ['C','Vert',0],          ['D','Bleu',0]]],
['La force de frottement s\'oppose au :',                                                    'ELEMENTAIRE',   'ENAFEP',     [['A','Mouvement du corps',1],['B','Poids du corps',0],['C','Courant électrique',0],['D','Champ magnétique',0]]],
['La formule de la force magnétique sur un conducteur est F = B·I·L·sin θ. Si θ = 90° :',  'AVANCE',        'EXAMEN_ETAT',[['A','F = B·I·L',1],     ['B','F = 0',0],         ['C','F = B·I·L/2',0],   ['D','F = B²·I·L',0]]],
['Quel est l\'indice de réfraction de l\'eau (environ) ?',                                  'INTERMEDIAIRE', 'TENASOSP',   [['A','1,33',1],         ['B','1,0',0],           ['C','1,5',0],           ['D','2,0',0]]],
['L\'effet photoélectrique a été expliqué par :',                                            'AVANCE',        'EXAMEN_ETAT',[['A','Einstein (1905)',1],['B','Planck',0],         ['C','Bohr',0],          ['D','Maxwell',0]]],
['Quelle quantité physique est conservée lors d\'une collision élastique en plus de l\'énergie ?', 'AVANCE', 'EXAMEN_ETAT',[['A','La quantité de mouvement',1],['B','La vitesse',0],['C','L\'accélération',0],['D','La force',0]]],
['La distance parcourue par une lumière en 1 an-lumière vaut environ :',                   'INTERMEDIAIRE', 'ENAFEP',     [['A','9,46 × 10¹² km',1],['B','9,46 × 10⁹ km',0],['C','3 × 10⁸ km',0],  ['D','1 000 km',0]]],
['Un fusible dans un circuit électrique sert à :',                                           'DEBUTANT',      'ENAFEP',     [['A','Protéger le circuit en cas de surintensité',1],['B','Augmenter la tension',0],['C','Stocker l\'énergie',0],['D','Mesurer le courant',0]]],
['La chaleur latente de vaporisation est l\'énergie nécessaire pour :',                     'AVANCE',        'EXAMEN_ETAT',[['A','Transformer un liquide en vapeur à température constante',1],['B','Chauffer un gaz',0],['C','Fondre un solide',0],['D','Sublimer un solide',0]]],
['Le nombre de Reynolds permet de prédire :',                                                'EXPERT',        'EXAMEN_ETAT',[['A','Le caractère laminaire ou turbulent d\'un écoulement',1],['B','La pression d\'un gaz',0],['C','La vitesse de la lumière',0],['D','La résistance d\'un matériau',0]]],
['Dans quel cas deux oscillateurs sont-ils en résonance ?',                                  'EXPERT',        'EXAMEN_ETAT',[['A','Quand leurs fréquences propres sont identiques',1],['B','Quand ils ont la même amplitude',0],['C','Quand ils oscillent en sens contraire',0],['D','Quand ils sont à distance nulle',0]]],
['La première loi de Kepler dit que les planètes décrivent :',                               'AVANCE',        'EXAMEN_ETAT',[['A','Des ellipses dont le Soleil occupe un foyer',1],['B','Des cercles parfaits',0],['C','Des paraboles',0],['D','Des spirales',0]]],
['L\'énergie d\'un photon est donnée par E = h·f. h est :',                                 'AVANCE',        'EXAMEN_ETAT',[['A','La constante de Planck (6,626 × 10⁻³⁴ J·s)',1],['B','La constante de Boltzmann',0],['C','La célérité de la lumière',0],['D','La charge de l\'électron',0]]],
['Quelle est la loi de Faraday sur l\'induction électromagnétique ?',                       'EXPERT',        'EXAMEN_ETAT',[['A','La FEM induite est proportionnelle à la variation du flux magnétique',1],['B','Le courant crée un champ magnétique',0],['C','La tension est proportionnelle au courant',0],['D','L\'énergie est conservée',0]]],
['La relation de de Broglie λ = h/p associe une longueur d\'onde à :',                     'EXPERT',        'EXAMEN_ETAT',[['A','Toute particule massive en mouvement',1],['B','Seulement les photons',0],['C','Les ondes sonores',0],['D','Les charges électriques',0]]],
['La thermodynamique du second principe dit que l\'entropie d\'un système isolé :',         'EXPERT',        'EXAMEN_ETAT',[['A','Ne peut qu\'augmenter ou rester constante',1],['B','Reste toujours constante',0],['C','Peut augmenter ou diminuer librement',0],['D','Est nulle à l\'équilibre',0]]],
];
foreach ($qs_ph3 as [$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$mat,$e,$d,$s,$o); $total_q++; }

/* ═══════════════════════════════════════════
   BIOLOGIE — PACK 3 (35 questions)
═══════════════════════════════════════════ */
$mat = $matiereMap['biologie'];
$qs_bio3 = [
['Quel est le rôle de l\'ADN dans la cellule ?',                                            'ELEMENTAIRE',   'ENAFEP',     [['A','Stocker et transmettre l\'information génétique',1],['B','Produire de l\'énergie',0],['C','Digérer les nutriments',0],['D','Filtrer les toxines',0]]],
['Les anticorps sont produits par :',                                                        'INTERMEDIAIRE', 'TENASOSP',   [['A','Les lymphocytes B',1],['B','Les globules rouges',0],['C','Les plaquettes',0],['D','Les neutrophiles',0]]],
['Quel organe est responsable de la régulation de la glycémie ?',                           'INTERMEDIAIRE', 'TENASOSP',   [['A','Le pancréas',1],  ['B','Le foie uniquement',0],['C','Les reins',0],      ['D','La rate',0]]],
['La transpiration chez les plantes s\'appelle :',                                           'ELEMENTAIRE',   'ENAFEP',     [['A','Transpiration / evapotranspiration',1],['B','Photosynthèse',0],['C','Respiration',0],['D','Osmose',0]]],
['Quelle est la différence entre cellule procaryote et eucaryote ?',                        'INTERMEDIAIRE', 'TENASOSP',   [['A','Procaryote sans noyau membranaire ; eucaryote avec noyau',1],['B','Eucaryote sans noyau',0],['C','Procaryote plus grande',0],['D','Aucune différence',0]]],
['Quel est le nom du processus par lequel les plantes fabriquent leur nourriture ?',        'DEBUTANT',      'ENAFEP',     [['A','Photosynthèse',1], ['B','Respiration',0],   ['C','Fermentation',0],  ['D','Digestion',0]]],
['Le sang artériel est riche en :',                                                          'ELEMENTAIRE',   'ENAFEP',     [['A','Oxygène (O₂)',1], ['B','Dioxyde de carbone',0],['C','Glucose',0],      ['D','Azote',0]]],
['Quelle glande sécrète les hormones thyroïdiennes ?',                                      'INTERMEDIAIRE', 'TENASOSP',   [['A','La thyroïde',1],  ['B','L\'hypophyse',0],  ['C','Le pancréas',0],   ['D','Les surrénales',0]]],
['L\'osmose est le mouvement de :',                                                          'INTERMEDIAIRE', 'TENASOSP',   [['A','L\'eau d\'un milieu hypotonique vers hypertonique (à travers une membrane semiperméable)',1],['B','Les solutés du plus concentré vers le moins concentré',0],['C','L\'eau du milieu concentré vers dilué',0],['D','Les ions à travers le cytoplasme',0]]],
['Quel est le premier niveau trophique dans une chaîne alimentaire ?',                      'ELEMENTAIRE',   'ENAFEP',     [['A','Producteurs (végétaux)',1],['B','Consommateurs primaires',0],['C','Décomposeurs',0],['D','Prédateurs',0]]],
['La glycolyse se déroule dans :',                                                           'AVANCE',        'EXAMEN_ETAT',[['A','Le cytoplasme',1], ['B','La mitochondrie',0],['C','Le noyau',0],       ['D','Le ribosome',0]]],
['Quel est le rôle de l\'insuline ?',                                                        'INTERMEDIAIRE', 'TENASOSP',   [['A','Faire entrer le glucose dans les cellules (baisser la glycémie)',1],['B','Augmenter la glycémie',0],['C','Stimuler la croissance',0],['D','Réguler la tension artérielle',0]]],
['Les bactéries appartiennent au domaine :',                                                 'INTERMEDIAIRE', 'TENASOSP',   [['A','Procaryotes (Bacteria)',1],['B','Eucaryotes',0],['C','Archaea',0],         ['D','Virus',0]]],
['La double hélice de l\'ADN a été découverte par :',                                       'AVANCE',        'EXAMEN_ETAT',[['A','Watson et Crick (1953)',1],['B','Mendel',0],        ['C','Darwin',0],         ['D','Pasteur',0]]],
['Quel type de tissu forme la peau ?',                                                       'ELEMENTAIRE',   'ENAFEP',     [['A','Tissu épithélial',1],['B','Tissu conjonctif',0],['C','Tissu musculaire',0],['D','Tissu nerveux',0]]],
['Le cycle de Krebs se déroule dans :',                                                      'AVANCE',        'EXAMEN_ETAT',[['A','La matrice mitochondriale',1],['B','Le cytoplasme',0],['C','Le noyau',0],    ['D','Le réticulum',0]]],
['Quel est le rôle du nerf optique ?',                                                       'ELEMENTAIRE',   'ENAFEP',     [['A','Transmettre les signaux visuels de l\'œil au cerveau',1],['B','Contrôler les muscles oculaires',0],['C','Réguler la pupille',0],['D','Produire l\'humeur vitrée',0]]],
['La sélection naturelle est le mécanisme central de la théorie de :',                      'INTERMEDIAIRE', 'TENASOSP',   [['A','Darwin',1],       ['B','Lamarck',0],       ['C','Mendel',0],         ['D','Pasteur',0]]],
['Quelle est la loi de dominance de Mendel ?',                                               'INTERMEDIAIRE', 'TENASOSP',   [['A','Le caractère dominant masque le récessif chez l\'hybride',1],['B','Les deux allèles s\'expriment',0],['C','Les caractères se mélangent',0],['D','L\'allèle récessif disparaît',0]]],
['Un enzyme est :',                                                                           'ELEMENTAIRE',   'ENAFEP',     [['A','Un catalyseur biologique protéique',1],['B','Un lipide',0],['C','Un glucide',0],['D','Un acide nucléique',0]]],
['Le cycle menstruel dure en moyenne :',                                                     'ELEMENTAIRE',   'ENAFEP',     [['A','28 jours',1],     ['B','14 jours',0],      ['C','21 jours',0],       ['D','35 jours',0]]],
['Quel est le rôle des lysosomes dans la cellule ?',                                         'AVANCE',        'EXAMEN_ETAT',[['A','Digestion intracellulaire (hydrolyse des molécules)',1],['B','Synthèse des protéines',0],['C','Production d\'ATP',0],['D','Stockage du calcium',0]]],
['La mutation est :',                                                                         'INTERMEDIAIRE', 'TENASOSP',   [['A','Une modification permanente de la séquence d\'ADN',1],['B','Un changement temporaire du phénotype',0],['C','La division cellulaire',0],['D','La recombinaison génétique uniquement',0]]],
['Quelle est la fonction des stomites foliaires ?',                                          'INTERMEDIAIRE', 'TENASOSP',   [['A','Échanges gazeux et transpiration',1],['B','Photosynthèse directe',0],['C','Absorption d\'eau',0],['D','Stockage des nutriments',0]]],
['Les glucides sont dégradés en :',                                                          'ELEMENTAIRE',   'ENAFEP',     [['A','Glucose (monosaccharides)',1],['B','Acides aminés',0],['C','Acides gras',0],['D','Nucléotides',0]]],
['L\'hémoglobine fœtale a une affinité pour l\'O₂ :',                                      'EXPERT',        'EXAMEN_ETAT',[['A','Plus grande que l\'hémoglobine adulte',1],['B','Plus faible',0],['C','Identique',0],['D','Variable selon le pH',0]]],
['La régulation hormonale est assurée par le système :',                                     'INTERMEDIAIRE', 'TENASOSP',   [['A','Endocrinien',1],  ['B','Nerveux',0],       ['C','Immunitaire',0],    ['D','Digestif',0]]],
['Qu\'est-ce que la fermentation lactique ?',                                                'AVANCE',        'EXAMEN_ETAT',[['A','Transformation du glucose en acide lactique sans O₂',1],['B','Production d\'alcool',0],['C','Dégradation des protéines',0],['D','Synthèse de l\'ATP en présence d\'O₂',0]]],
['Quel est le rôle du placenta pendant la grossesse ?',                                      'INTERMEDIAIRE', 'TENASOSP',   [['A','Échanges nutritifs et gazeux entre mère et fœtus',1],['B','Produire des œufs',0],['C','Protéger le fœtus des chocs uniquement',0],['D','Produire l\'hormone de croissance',0]]],
['Les plaquettes (thrombocytes) jouent un rôle dans :',                                     'ELEMENTAIRE',   'ENAFEP',     [['A','La coagulation du sang',1],['B','Le transport d\'O₂',0],['C','La défense immunitaire',0],['D','La digestion',0]]],
['La biologie moléculaire étudie :',                                                         'AVANCE',        'EXAMEN_ETAT',[['A','Les mécanismes à l\'échelle des molécules biologiques (ADN, protéines)',1],['B','Le comportement des animaux',0],['C','L\'évolution des espèces',0],['D','L\'anatomie des organes',0]]],
['Quelle est la structure de la membrane cellulaire ?',                                      'AVANCE',        'EXAMEN_ETAT',[['A','Bicouche phospholipidique avec protéines',1],['B','Simple couche de protéines',0],['C','Cellulose et pectine',0],['D','Cholestérol pur',0]]],
['La vitamine C est essentielle pour :',                                                     'ELEMENTAIRE',   'ENAFEP',     [['A','La synthèse du collagène et défenses immunitaires',1],['B','La vision nocturne',0],['C','La coagulation',0],['D','La calcification des os',0]]],
['Les gènes sont localisés sur :',                                                            'ELEMENTAIRE',   'ENAFEP',     [['A','Les chromosomes',1],['B','Les ribosomes',0],['C','Les lysosomes',0],['D','La membrane cellulaire',0]]],
['L\'arthropode se caractérise par :',                                                       'ELEMENTAIRE',   'ENAFEP',     [['A','Un exosquelette et des appendices articulés',1],['B','Un endosquelette osseux',0],['C','Une colonne vertébrale',0],['D','Des branchies permanentes',0]]],
];
foreach ($qs_bio3 as [$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$mat,$e,$d,$s,$o); $total_q++; }

/* ═══════════════════════════════════════════
   HISTOIRE-GÉOGRAPHIE — PACK 3 (30 questions)
═══════════════════════════════════════════ */
$mat = $matiereMap['histgeo'];
$qs_hg3 = [
['La conférence de Bandung (1955) rassemblait des pays :',                                  'AVANCE',        'EXAMEN_ETAT',[['A','Afro-asiatiques non-alignés',1],['B','Européens',0],      ['C','Arabes',0],         ['D','Latinoaméricains',0]]],
['Qui a fondé le mouvement de résistance non-violente en Inde ?',                           'ELEMENTAIRE',   'ENAFEP',     [['A','Mahatma Gandhi',1],['B','Nehru',0],           ['C','Mandela',0],        ['D','Nkrumah',0]]],
['La Première Guerre mondiale a débuté en :',                                                'ELEMENTAIRE',   'ENAFEP',     [['A','1914',1],          ['B','1939',0],           ['C','1918',0],           ['D','1900',0]]],
['Le traité de Versailles (1919) punissait :',                                               'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','L\'Allemagne (cause de la WWI)',1],['B','La France',0],   ['C','L\'Autriche-Hongrie',0],['D','La Russie',0]]],
['Quel est le plus long mur artificiel du monde ?',                                          'ELEMENTAIRE',   'ENAFEP',     [['A','La Grande Muraille de Chine',1],['B','Le mur de Berlin',0],['C','Le mur d\'Hadrien',0],['D','Le mur de Jéricho',0]]],
['La colonisation de la RDC par la Belgique a officiellement pris fin en :',                'ELEMENTAIRE',   'ENAFEP',     [['A','1960',1],          ['B','1958',0],           ['C','1965',0],           ['D','1955',0]]],
['Où se sont déroulés les jeux Olympiques de 1996 ?',                                       'ELEMENTAIRE',   'ENAFEP',     [['A','Atlanta (USA)',1],  ['B','Sydney',0],         ['C','Barcelone',0],      ['D','Séoul',0]]],
['La Ceinture de feu du Pacifique est une zone caractérisée par :',                         'INTERMEDIAIRE', 'ENAFEP',     [['A','De nombreux volcans et séismes',1],['B','Des déserts étendus',0],['C','Des précipitations exceptionnelles',0],['D','Des températures glaciales',0]]],
['Quel continent a la plus grande biodiversité ?',                                            'ELEMENTAIRE',   'ENAFEP',     [['A','Amérique du Sud (Amazonie)',1],['B','Afrique',0],       ['C','Asie',0],           ['D','Europe',0]]],
['Qu\'est-ce que le méridien de Greenwich ?',                                                'ELEMENTAIRE',   'ENAFEP',     [['A','Le méridien d\'origine (0° longitude)',1],['B','Le tropique du Cancer',0],['C','L\'équateur',0],['D','Le cercle polaire',0]]],
['La déforestation de l\'Amazonie menace principalement :',                                  'ELEMENTAIRE',   'ENAFEP',     [['A','La biodiversité et le climat mondial',1],['B','Seulement le Brésil',0],['C','Les réserves d\'eau souterraine',0],['D','La production de pétrole',0]]],
['Quelle est la capitale du Sénégal ?',                                                      'DEBUTANT',      'ENAFEP',     [['A','Dakar',1],         ['B','Abidjan',0],        ['C','Bamako',0],         ['D','Lomé',0]]],
['Le Sahara est le plus grand désert chaud du monde. Il se trouve en :',                    'ELEMENTAIRE',   'ENAFEP',     [['A','Afrique du Nord',1],['B','Afrique centrale',0],['C','Moyen-Orient',0],  ['D','Asie centrale',0]]],
['Quelle est la densité de population de la RDC (km²) approximativement ?',                 'INTERMEDIAIRE', 'ENAFEP',     [['A','40-45 hab/km²',1], ['B','200 hab/km²',0],    ['C','10 hab/km²',0],     ['D','500 hab/km²',0]]],
['La Guerre froide a opposé principalement :',                                               'INTERMEDIAIRE', 'ENAFEP',     [['A','USA et URSS (1947-1991)',1],['B','USA et Chine',0],  ['C','France et Allemagne',0],['D','Grande-Bretagne et Russie',0]]],
['Quel est le plus haut sommet d\'Afrique ?',                                                'ELEMENTAIRE',   'ENAFEP',     [['A','Kilimandjaro (5895m)',1],['B','Mont Kenya',0],    ['C','Rwenzori',0],       ['D','Mont Cameroun',0]]],
['La CEEAC regroupe des pays d\' :',                                                         'INTERMEDIAIRE', 'ENAFEP',     [['A','Afrique centrale',1],['B','Afrique de l\'Ouest',0],['C','Afrique de l\'Est',0],['D','Afrique du Nord',0]]],
['En quelle année le mur de Berlin est-il tombé ?',                                          'ELEMENTAIRE',   'ENAFEP',     [['A','1989',1],          ['B','1991',0],           ['C','1985',0],           ['D','1975',0]]],
['La région des Grands Lacs africains comprend notamment :',                                 'INTERMEDIAIRE', 'ENAFEP',     [['A','Lacs Tanganyika, Victoria, Albert, Kivu',1],['B','Lacs Tchad et Niger',0],['C','Lac Supérieur et Michigan',0],['D','Mer Rouge et Mer Morte',0]]],
['Nelson Mandela a été président de l\'Afrique du Sud de :',                                 'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','1994 à 1999',1],   ['B','1990 à 1994',0],    ['C','1999 à 2004',0],    ['D','1990 à 2000',0]]],
['Quelle est la principale ressource naturelle du Moyen-Orient ?',                           'ELEMENTAIRE',   'ENAFEP',     [['A','Pétrole',1],       ['B','Coltan',0],         ['C','Diamant',0],        ['D','Café',0]]],
['Le tremblement de terre de 2010 a dévasté :',                                              'ELEMENTAIRE',   'ENAFEP',     [['A','Haïti',1],         ['B','Chili',0],          ['C','Japon',0],          ['D','Turquie',0]]],
['La rivière Kasaï est un affluent du :',                                                    'ELEMENTAIRE',   'ENAFEP',     [['A','Fleuve Congo',1],  ['B','Nil',0],            ['C','Niger',0],          ['D','Zambèze',0]]],
['La ville de Lubumbashi est connue pour :',                                                  'ELEMENTAIRE',   'ENAFEP',     [['A','Son industrie minière (cuivre)',1],['B','Sa pêche maritime',0],['C','Ses forêts équatoriales',0],['D','Son tourisme balnéaire',0]]],
['Le droit international humanitaire est principalement codifié dans :',                    'AVANCE',        'EXAMEN_ETAT',[['A','Les Conventions de Genève (1949)',1],['B','La Charte de l\'ONU',0],['C','Le Traité de Westphalie',0],['D','La Déclaration de Vienne',0]]],
['Quel siècle est appelé "le siècle des Lumières" ?',                                       'INTERMEDIAIRE', 'ENAFEP',     [['A','XVIIIe siècle',1], ['B','XVIIe siècle',0],  ['C','XIXe siècle',0],    ['D','XXe siècle',0]]],
['La RDC a été renommée "Zaïre" pendant la période de :',                                   'INTERMEDIAIRE', 'ENAFEP',     [['A','1971-1997 (régime Mobutu)',1],['B','1960-1965',0],   ['C','1997-2001',0],      ['D','1965-1971',0]]],
['Quel est le fleuve le plus long d\'Afrique ?',                                              'ELEMENTAIRE',   'ENAFEP',     [['A','Le Nil',1],        ['B','Le Congo',0],       ['C','Le Niger',0],       ['D','Le Zambèze',0]]],
['La tectonique des plaques explique :',                                                      'INTERMEDIAIRE', 'ENAFEP',     [['A','La formation des montagnes, océans et séismes',1],['B','La rotation de la Terre',0],['C','Les marées',0],['D','Les courants marins uniquement',0]]],
['Quelle organisation internationale a été créée en 1945 pour maintenir la paix ?',         'ELEMENTAIRE',   'ENAFEP',     [['A','L\'ONU (Organisation des Nations Unies)',1],['B','L\'OTAN',0],['C','L\'Union européenne',0],['D','La Société des Nations',0]]],
];
foreach ($qs_hg3 as [$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$mat,$e,$d,$s,$o); $total_q++; }

/* ═══════════════════════════════════════════
   ANGLAIS — PACK 3 (30 questions)
═══════════════════════════════════════════ */
$mat = $matiereMap['anglais'];
$qs_en3 = [
['What is the superlative form of "far" ?',                                                  'ELEMENTAIRE',   'ENAFEP',     [['A','farthest / furthest',1],['B','more far',0],    ['C','farrer',0],         ['D','most far',0]]],
['Choose the correct article: "___ honest man is hard to find."',                           'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','An',1],             ['B','A',0],             ['C','The',0],            ['D','No article',0]]],
['What is the meaning of "tenacious" ?',                                                     'AVANCE',        'EXAMEN_ETAT',[['A','Persistent and determined',1],['B','Friendly',0],    ['C','Reckless',0],       ['D','Generous',0]]],
['Identify the error: "If I would have known, I would have called."',                        'EXPERT',        'EXAMEN_ETAT',[['A','"would have known" → should be "had known"',1],['B','"would have called" is wrong',0],['C','No error',0],['D','"I" should be "me"',0]]],
['What does "philanthropist" mean ?',                                                         'AVANCE',        'EXAMEN_ETAT',[['A','Someone who donates to help others',1],['B','A plant lover',0],['C','A philosopher',0],['D','An athlete',0]]],
['Fill in: "She ___ to Paris three times." (go — present perfect)',                         'ELEMENTAIRE',   'ENAFEP',     [['A','has gone',1],      ['B','went',0],          ['C','has been',0],       ['D','goes',0]]],
['Which sentence uses the subjunctive correctly ?',                                           'EXPERT',        'EXAMEN_ETAT',[['A','I suggest that he be present.',1],['B','I suggest that he is present.',0],['C','I suggest him to be present.',0],['D','I suggest he was present.',0]]],
['What is the plural of "criterion" ?',                                                       'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','criteria',1],       ['B','criterions',0],    ['C','criterias',0],      ['D','criterion',0]]],
['Identify the figure of speech in: "The world is a stage."',                                'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','Metaphor',1],       ['B','Simile',0],        ['C','Personification',0],['D','Hyperbole',0]]],
['Choose the correct preposition: "She is interested ___ music."',                           'ELEMENTAIRE',   'ENAFEP',     [['A','in',1],            ['B','on',0],            ['C','at',0],             ['D','with',0]]],
['What is a "synopsis" ?',                                                                    'AVANCE',        'EXAMEN_ETAT',[['A','A brief summary of a text or film',1],['B','A detailed analysis',0],['C','A type of poem',0],['D','A bibliography',0]]],
['Rephrase in passive: "The teacher corrected the tests."',                                  'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','The tests were corrected by the teacher.',1],['B','The tests corrected by the teacher.',0],['C','The teacher has corrected the tests.',0],['D','The tests are corrected.',0]]],
['What does "albeit" mean ?',                                                                 'EXPERT',        'EXAMEN_ETAT',[['A','Although / even though',1],['B','Therefore',0],   ['C','However',0],        ['D','Furthermore',0]]],
['"She was so tired that she fell asleep instantly." This expresses:',                        'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','Result / consequence',1],['B','Concession',0],  ['C','Condition',0],      ['D','Time',0]]],
['What is the correct form? "He insisted on ___ the truth."',                                'AVANCE',        'EXAMEN_ETAT',[['A','knowing',1],         ['B','know',0],          ['C','to know',0],        ['D','known',0]]],
['Which word class is "however" in: "It was raining; however, we went out."',                'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','Conjunctive adverb',1],['B','Conjunction',0],  ['C','Preposition',0],    ['D','Adjective',0]]],
['Identify the type of noun: "courage"',                                                     'ELEMENTAIRE',   'ENAFEP',     [['A','Abstract noun',1], ['B','Proper noun',0],   ['C','Concrete noun',0],  ['D','Collective noun',0]]],
['What does "abbreviate" mean ?',                                                             'ELEMENTAIRE',   'ENAFEP',     [['A','To shorten a word or phrase',1],['B','To enlarge',0],['C','To repeat',0],      ['D','To translate',0]]],
['Choose the right option: "Neither the students nor the teacher ___ ready."',               'AVANCE',        'EXAMEN_ETAT',[['A','was',1],            ['B','were',0],          ['C','are',0],            ['D','is being',0]]],
['What is a "paradox" ?',                                                                     'AVANCE',        'EXAMEN_ETAT',[['A','A statement that contradicts itself yet reveals a truth',1],['B','A comparison using "like"',0],['C','An exaggeration for effect',0],['D','A play on words',0]]],
['The word "unprecedented" means:',                                                           'AVANCE',        'EXAMEN_ETAT',[['A','Never done before',1],['B','Often repeated',0],['C','Well-known',0],      ['D','Outdated',0]]],
['Fill in: "___ you mind closing the window?" (polite request)',                             'ELEMENTAIRE',   'ENAFEP',     [['A','Would',1],         ['B','Do',0],            ['C','Could',0],          ['D','Can',0]]],
['"No sooner had he arrived than it started raining." This is an example of:',              'EXPERT',        'EXAMEN_ETAT',[['A','Inversion for emphasis',1],['B','A relative clause',0],['C','A conditional sentence',0],['D','A nominal clause',0]]],
['What is the difference between "bring" and "take" ?',                                      'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','Bring = toward speaker ; take = away from speaker',1],['B','They are synonyms',0],['C','Bring is more formal',0],['D','Take is only for objects',0]]],
['Which sentence is grammatically correct ?',                                                 'INTERMEDIAIRE', 'ENAFEP',     [['A','He has been living here for ten years.',1],['B','He is living here since ten years.',0],['C','He lives here since ten years.',0],['D','He has lived here since ten years ago.',0]]],
['The literary device where the end of a sentence repeats the beginning is called:',        'EXPERT',        'EXAMEN_ETAT',[['A','Anadiplosis',1],    ['B','Anaphora',0],      ['C','Epistrophe',0],      ['D','Chiasmus',0]]],
['What does "connotation" mean in literary studies ?',                                       'AVANCE',        'EXAMEN_ETAT',[['A','The emotional/cultural meaning associated with a word',1],['B','The dictionary definition',0],['C','The origin of a word',0],['D','A figure of speech',0]]],
['Fill in: "He acts as if he ___ the boss." (subjunctive)',                                  'EXPERT',        'EXAMEN_ETAT',[['A','were',1],           ['B','is',0],            ['C','was',0],            ['D','had been',0]]],
['Identify the mood: "Would you please sit down?"',                                          'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','Polite imperative (request)',1],['B','Conditional',0],['C','Interrogative statement',0],['D','Subjunctive',0]]],
['What is "alliteration" ?',                                                                  'ELEMENTAIRE',   'ENAFEP',     [['A','Repetition of the same consonant sound at the start of nearby words',1],['B','A rhyme scheme',0],['C','A comparison using "like" or "as"',0],['D','An exaggeration',0]]],
];
foreach ($qs_en3 as [$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$mat,$e,$d,$s,$o); $total_q++; }

/* ═══════════════════════════════════════════
   SCIENCES — PACK 3 (30 questions)
═══════════════════════════════════════════ */
$mat = $matiereMap['sciences'];
$qs_sc3 = [
['Quelle est la température d\'ébullition de l\'eau à pression normale ?',                 'DEBUTANT',      'ENAFEP',     [['A','100°C',1],         ['B','90°C',0],           ['C','120°C',0],          ['D','80°C',0]]],
['Quel est le gaz rejeté par les animaux lors de la respiration ?',                         'DEBUTANT',      'ENAFEP',     [['A','Dioxyde de carbone (CO₂)',1],['B','Oxygène (O₂)',0],['C','Azote (N₂)',0],   ['D','Méthane (CH₄)',0]]],
['Quelle force maintient les planètes en orbite autour du Soleil ?',                        'ELEMENTAIRE',   'ENAFEP',     [['A','La gravitation',1], ['B','Le magnétisme',0],  ['C','La pression',0],    ['D','La tension de surface',0]]],
['Quel est le rôle de l\'atmosphère terrestre ?',                                            'ELEMENTAIRE',   'ENAFEP',     [['A','Protéger des UV, maintenir la chaleur, fournir l\'air',1],['B','Créer les marées',0],['C','Alimenter les volcans',0],['D','Produire l\'eau',0]]],
['Comment appelle-t-on le passage de l\'état solide à l\'état gazeux sans passer par le liquide ?', 'INTERMEDIAIRE', 'TENASOSP', [['A','Sublimation',1],    ['B','Évaporation',0],    ['C','Fusion',0],         ['D','Condensation',0]]],
['Quel instrument mesure l\'intensité des séismes ?',                                       'ELEMENTAIRE',   'ENAFEP',     [['A','Sismographe',1],   ['B','Baromètre',0],      ['C','Richter (échelle)',0],['D','Magnétomètre',0]]],
['L\'effet de serre naturel est essentiel car il :',                                         'ELEMENTAIRE',   'ENAFEP',     [['A','Maintient la température terrestre habitable',1],['B','Provoque la pollution',0],['C','Détruit la couche d\'ozone',0],['D','Cause les pluies acides',0]]],
['Quelle est la particule élémentaire de charge positive dans le noyau ?',                  'ELEMENTAIRE',   'TENASOSP',   [['A','Proton',1],        ['B','Électron',0],       ['C','Neutron',0],        ['D','Photon',0]]],
['Le microscope électronique permet de voir des objets :',                                   'AVANCE',        'EXAMEN_ETAT',[['A','De taille nanométrique (cellules, virus)',1],['B','Macroscopiques',0],['C','Uniquement des roches',0],['D','Des étoiles',0]]],
['La pression atmosphérique standard est de :',                                              'ELEMENTAIRE',   'TENASOSP',   [['A','101 325 Pa (≈ 1 atm)',1],['B','1 Pa',0],         ['C','10 000 Pa',0],      ['D','760 Pa',0]]],
['Quel phénomène explique les marées ?',                                                     'INTERMEDIAIRE', 'TENASOSP',   [['A','L\'attraction gravitationnelle de la Lune et du Soleil',1],['B','La rotation de la Terre',0],['C','Les vents',0],['D','Les courants marins',0]]],
['La photosynthèse produit du glucose à partir de CO₂, H₂O et :',                          'ELEMENTAIRE',   'ENAFEP',     [['A','Lumière solaire',1],['B','Chaleur',0],          ['C','Azote',0],          ['D','Oxygène',0]]],
['Qu\'est-ce que la radioactivité ?',                                                        'AVANCE',        'EXAMEN_ETAT',[['A','Émission spontanée de rayonnements par des noyaux instables',1],['B','Réflexion de la lumière',0],['C','Réaction chimique exothermique',0],['D','Propagation d\'ondes sonores',0]]],
['L\'énergie renouvelable inclut :',                                                         'ELEMENTAIRE',   'ENAFEP',     [['A','Solaire, éolienne, hydraulique, géothermique',1],['B','Pétrole et charbon',0],['C','Gaz naturel',0],['D','Uranium uniquement',0]]],
['Quel est l\'ordre de grandeur de la taille d\'un atome ?',                                'AVANCE',        'EXAMEN_ETAT',[['A','10⁻¹⁰ m (0,1 nm)',1],['B','10⁻⁶ m',0],        ['C','10⁻³ m',0],         ['D','10⁻¹² m',0]]],
['La flottaison d\'un objet dépend de :',                                                    'INTERMEDIAIRE', 'TENASOSP',   [['A','La densité de l\'objet par rapport au fluide',1],['B','Sa couleur',0],['C','Sa forme uniquement',0],['D','Sa température',0]]],
['Le cancer est caractérisé par :',                                                          'INTERMEDIAIRE', 'TENASOSP',   [['A','Une division cellulaire anarchique et incontrôlée',1],['B','Une infection virale',0],['C','Un manque de nutriments',0],['D','Une allergie',0]]],
['Quelle est l\'unité de mesure du courant électrique ?',                                   'DEBUTANT',      'ENAFEP',     [['A','Ampère',1],        ['B','Volt',0],           ['C','Watt',0],           ['D','Ohm',0]]],
['Le cœur humain a combien de cavités ?',                                                    'ELEMENTAIRE',   'ENAFEP',     [['A','4 (2 oreillettes + 2 ventricules)',1],['B','2',0],['C','3',0],             ['D','6',0]]],
['Comment appelle-t-on la couche externe de la Terre ?',                                    'ELEMENTAIRE',   'ENAFEP',     [['A','La croûte terrestre (lithosphère)',1],['B','Le manteau',0],['C','Le noyau',0],      ['D','L\'asthénosphère',0]]],
['La fusion nucléaire produit de l\'énergie en :',                                           'EXPERT',        'EXAMEN_ETAT',[['A','Fusionnant des noyaux légers (ex: H → He)',1],['B','Fissionnant des noyaux lourds',0],['C','Brûlant du combustible fossile',0],['D','Utilisant la photosynthèse',0]]],
['Quel est le rôle des décomposeurs dans un écosystème ?',                                   'ELEMENTAIRE',   'ENAFEP',     [['A','Dégrader les matières organiques mortes en minéraux',1],['B','Produire de l\'O₂',0],['C','Consommer les herbivores',0],['D','Absorber l\'azote',0]]],
['L\'eutrophisation d\'un lac est causée par :',                                             'AVANCE',        'EXAMEN_ETAT',[['A','Un excès de nutriments (nitrates, phosphates) entraînant la prolifération d\'algues',1],['B','Un manque d\'eau',0],['C','La pollution plastique',0],['D','Un changement climatique rapide',0]]],
['La dureté d\'un minéral se mesure avec :',                                                 'INTERMEDIAIRE', 'TENASOSP',   [['A','L\'échelle de Mohs',1],['B','L\'échelle de Richter',0],['C','L\'échelle de Beaufort',0],['D','Le pH',0]]],
['Quel est l\'indice d\'un isotope ? Ex : ¹⁴C vs ¹²C',                                     'AVANCE',        'EXAMEN_ETAT',[['A','Le nombre de neutrons est différent',1],['B','Le nombre de protons',0],['C','La charge électrique',0],['D','Le nombre d\'électrons',0]]],
['La chlorophylle absorbe principalement la lumière :',                                     'INTERMEDIAIRE', 'TENASOSP',   [['A','Rouge et bleue (réfléchit le vert)',1],['B','Verte',0],['C','Jaune et orange',0],['D','Ultraviolette',0]]],
['Qu\'est-ce que la biodégradabilité ?',                                                     'ELEMENTAIRE',   'ENAFEP',     [['A','Capacité d\'une substance à être décomposée par des micro-organismes',1],['B','Résistance aux acides',0],['C','Capacité à brûler',0],['D','Conductivité électrique',0]]],
['L\'effet Doppler explique pourquoi le son d\'une ambulance :',                             'AVANCE',        'EXAMEN_ETAT',[['A','Monte (approche) puis descend (éloignement)',1],['B','Reste constant',0],['C','Disparaît',0],['D','Change de timbre',0]]],
['Quelle énergie est stockée dans les liaisons chimiques ?',                                  'AVANCE',        'EXAMEN_ETAT',[['A','Énergie chimique',1],['B','Énergie cinétique',0],['C','Énergie nucléaire',0],['D','Énergie thermique',0]]],
['Le clonage d\'un organisme produit :',                                                     'AVANCE',        'EXAMEN_ETAT',[['A','Un individu génétiquement identique au parent',1],['B','Un mutant',0],['C','Un hybride',0],['D','Un organisme de sexe opposé',0]]],
];
foreach ($qs_sc3 as [$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$mat,$e,$d,$s,$o); $total_q++; }

/* ═══════════════════════════════════════════
   PACK CONSOLIDATION — Toutes matières (25)
═══════════════════════════════════════════ */
$qs_final = [
  [$matiereMap['maths'],    'Si la médiane d\'un triangle est tracée de A au milieu M de BC, quelle propriété a M ?',                     'AVANCE',        'EXAMEN_ETAT',[['A','M est le milieu de BC',1],['B','M est le pied de la hauteur',0],['C','M est sur la bissectrice',0],['D','AM ⊥ BC',0]]],
  [$matiereMap['maths'],    'Donner la solution de l\'inéquation x² − x − 6 > 0.',                                                         'EXPERT',        'EXAMEN_ETAT',[['A','x < −2 ou x > 3',1],['B','−2 < x < 3',0],['C','x > 3',0],['D','x < −2',0]]],
  [$matiereMap['maths'],    'Calculer : 3 × 4 + 2² − 6 ÷ 2',                                                                              'ELEMENTAIRE',   'ENAFEP',     [['A','13',1],['B','17',0],['C','10',0],['D','14',0]]],
  [$matiereMap['francais'], 'Quelle phrase contient une apposition ?',                                                                      'AVANCE',        'EXAMEN_ETAT',[['A','Kinshasa, capitale de la RDC, est une grande ville.',1],['B','Kinshasa est grande.',0],['C','La grande ville de Kinshasa.',0],['D','Kinshasa, c\'est grand.',0]]],
  [$matiereMap['francais'], 'Le verbe "partir" est un verbe :',                                                                             'ELEMENTAIRE',   'ENAFEP',     [['A','Intransitif (sans COD)',1],['B','Transitif direct',0],['C','Transitif indirect',0],['D','Pronominal',0]]],
  [$matiereMap['chimie'],   'Quel est le nom de la réaction entre un acide et un alcool pour former un ester ?',                           'INTERMEDIAIRE', 'TENASOSP',   [['A','Estérification',1],['B','Saponification',0],['C','Combustion',0],['D','Neutralisation',0]]],
  [$matiereMap['chimie'],   'La formule du sulfate de cuivre (II) est :',                                                                   'ELEMENTAIRE',   'TENASOSP',   [['A','CuSO₄',1],['B','Cu₂SO₄',0],['C','CuSO₃',0],['D','Cu(SO₄)₂',0]]],
  [$matiereMap['physique'], 'L\'énergie mécanique totale d\'un système conservatif est :',                                                  'AVANCE',        'EXAMEN_ETAT',[['A','Constante (Ec + Ep = constante)',1],['B','Toujours nulle',0],['C','Égale à la puissance',0],['D','Proportionnelle à la vitesse',0]]],
  [$matiereMap['physique'], 'Quelle est la formule de la force électromotrice d\'un générateur (ε, r, R) ?',                              'AVANCE',        'EXAMEN_ETAT',[['A','ε = U + r·I',1],['B','ε = U × r',0],['C','ε = U − R·I',0],['D','ε = I/R',0]]],
  [$matiereMap['biologie'], 'Le phénomène de translocation en génétique correspond à :',                                                    'EXPERT',        'EXAMEN_ETAT',[['A','Transfert d\'un segment chromosomique vers un autre chromosome',1],['B','Duplication de gènes',0],['C','Délétion d\'un segment',0],['D','Inversion d\'un segment',0]]],
  [$matiereMap['biologie'], 'Le paludisme est causé par :',                                                                                 'ELEMENTAIRE',   'ENAFEP',     [['A','Un protozoaire Plasmodium transmis par l\'anophèle femelle',1],['B','Un virus',0],['C','Une bactérie',0],['D','Un champignon',0]]],
  [$matiereMap['histgeo'],  'Le génocide au Rwanda a eu lieu en :',                                                                         'INTERMEDIAIRE', 'ENAFEP',     [['A','1994',1],['B','1990',0],['C','1999',0],['D','1985',0]]],
  [$matiereMap['histgeo'],  'Quelle est la principale culture de rente de la RDC dans les régions forestières ?',                          'INTERMEDIAIRE', 'ENAFEP',     [['A','Café et cacao',1],['B','Blé et riz',0],['C','Coton',0],['D','Arachide',0]]],
  [$matiereMap['anglais'],  '"The more you read, the more you learn." This structure is called:',                                           'AVANCE',        'EXAMEN_ETAT',[['A','Double comparative',1],['B','Superlative',0],['C','Parallel structure',0],['D','Hyperbole',0]]],
  [$matiereMap['anglais'],  'What does "plethora" mean ?',                                                                                  'EXPERT',        'EXAMEN_ETAT',[['A','An excess, an abundance',1],['B','A shortage',0],['C','A type of poem',0],['D','A scientific theory',0]]],
  [$matiereMap['sciences'], 'Le rayonnement infrarouge est utilisé dans :',                                                                 'INTERMEDIAIRE', 'TENASOSP',   [['A','Télécommandes, thermographie, vision nocturne',1],['B','Stérilisation médicale',0],['C','Production d\'électricité solaire',0],['D','Imagerie par résonance',0]]],
  [$matiereMap['maths'],    'La droite y = −x + 4 coupe l\'axe des x en :',                                                               'INTERMEDIAIRE', 'TENASOSP',   [['A','(4 ; 0)',1],['B','(0 ; 4)',0],['C','(−4 ; 0)',0],['D','(2 ; 2)',0]]],
  [$matiereMap['francais'], 'Quelle est la différence entre "cent" et "sans" ?',                                                            'DEBUTANT',      'ENAFEP',     [['A','cent = nombre ; sans = préposition',1],['B','Ils sont synonymes',0],['C','sans = nombre',0],['D','cent = préposition',0]]],
  [$matiereMap['chimie'],   'Quel est le produit de la réaction Ca(OH)₂ + CO₂ → ?',                                                        'INTERMEDIAIRE', 'TENASOSP',   [['A','CaCO₃ + H₂O',1],['B','CaO + H₂CO₃',0],['C','CaCO₂ + H₂O',0],['D','Ca + CO₂ + H₂O',0]]],
  [$matiereMap['physique'], 'Un son de 85 dB est considéré comme :',                                                                        'ELEMENTAIRE',   'ENAFEP',     [['A','Fort (risque auditif à long terme)',1],['B','Chuchotement',0],['C','Inaudible',0],['D','Conversation normale',0]]],
  [$matiereMap['biologie'], 'Comment appelle-t-on un animal qui se nourrit exclusivement de végétaux ?',                                   'DEBUTANT',      'ENAFEP',     [['A','Herbivore',1],['B','Carnivore',0],['C','Omnivore',0],['D','Détritivore',0]]],
  [$matiereMap['histgeo'],  'Quelle est la zone climatique de Kinshasa ?',                                                                  'ELEMENTAIRE',   'ENAFEP',     [['A','Tropical humide (Aw) avec saison sèche',1],['B','Désertique',0],['C','Tempéré',0],['D','Polaire',0]]],
  [$matiereMap['anglais'],  'Which word correctly completes: "He has a great ___ of humour." ?',                                           'ELEMENTAIRE',   'ENAFEP',     [['A','sense',1],['B','feeling',0],['C','taste',0],['D','view',0]]],
  [$matiereMap['sciences'], 'Quelle est la cause principale du réchauffement climatique actuel ?',                                          'ELEMENTAIRE',   'ENAFEP',     [['A','Les gaz à effet de serre d\'origine humaine (CO₂, CH₄...)',1],['B','Les éruptions volcaniques',0],['C','Les cycles solaires',0],['D','La déforestation seule',0]]],
  [$matiereMap['maths'],    'Résoudre graphiquement : les droites y = x + 1 et y = −x + 3 se croisent en :',                             'INTERMEDIAIRE', 'TENASOSP',   [['A','(1 ; 2)',1],['B','(2 ; 3)',0],['C','(0 ; 1)',0],['D','(3 ; 0)',0]]],
];
foreach ($qs_final as [$matId,$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$matId,$e,$d,$s,$o); $total_q++; }

/* ═══════════════════════════════════════════
   PACK ÉTENDU — 120 nouvelles questions
   Toutes matières, tous niveaux
═══════════════════════════════════════════ */

// ── MATHÉMATIQUES — pack étendu ──
$mat = $matiereMap['maths'];
$qs_maths_ext = [
  ['Quel est le résultat de 3 × (4 + 2) − 5 ?',                                          'DEBUTANT',      'ENAFEP',     [['A','13',1],           ['B','7',0],             ['C','19',0],            ['D','11',0]]],
  ['Convertir 3/5 en pourcentage.',                                                        'DEBUTANT',      'ENAFEP',     [['A','60%',1],          ['B','50%',0],           ['C','35%',0],           ['D','75%',0]]],
  ['Quel est le résultat de √144 ?',                                                       'DEBUTANT',      'ENAFEP',     [['A','12',1],           ['B','14',0],            ['C','72',0],            ['D','11',0]]],
  ['Combien vaut 20% de 350 ?',                                                             'DEBUTANT',      'ENAFEP',     [['A','70',1],           ['B','35',0],            ['C','700',0],           ['D','17,5',0]]],
  ['Un rectangle a une longueur de 8 cm et une largeur de 5 cm. Son périmètre vaut :',    'DEBUTANT',      'ENAFEP',     [['A','26 cm',1],        ['B','40 cm',0],         ['C','13 cm',0],         ['D','20 cm',0]]],
  ['Résoudre : 4x + 3 = 19',                                                               'ELEMENTAIRE',   'ENAFEP',     [['A','x = 4',1],        ['B','x = 7',0],         ['C','x = 5',0],         ['D','x = 3',0]]],
  ['Quelle est la valeur de 7² − 3² ?',                                                    'ELEMENTAIRE',   'ENAFEP',     [['A','40',1],           ['B','10',0],            ['C','58',0],            ['D','16',0]]],
  ['Dans un triangle isocèle, deux angles valent 55° chacun. Le troisième vaut :',        'ELEMENTAIRE',   'ENAFEP',     [['A','70°',1],          ['B','55°',0],           ['C','110°',0],          ['D','80°',0]]],
  ['Simplifier : (12x³) / (4x)',                                                            'ELEMENTAIRE',   'TENASOSP',   [['A','3x²',1],          ['B','8x²',0],           ['C','3x',0],            ['D','12x²',0]]],
  ['Résoudre l\'inégalité : 2x − 1 ≥ 5',                                                  'ELEMENTAIRE',   'ENAFEP',     [['A','x ≥ 3',1],        ['B','x ≥ 2',0],         ['C','x ≤ 3',0],         ['D','x ≥ 6',0]]],
  ['Calculer l\'hypoténuse d\'un triangle rectangle de côtés 5 et 12.',                   'ELEMENTAIRE',   'ENAFEP',     [['A','13',1],           ['B','17',0],            ['C','7',0],             ['D','60',0]]],
  ['Donner la valeur de 4⁰.',                                                               'DEBUTANT',      'ENAFEP',     [['A','1',1],            ['B','0',0],             ['C','4',0],             ['D','Indéfini',0]]],
  ['Quelle est la valeur de f(3) si f(x) = 2x² − x + 4 ?',                                'ELEMENTAIRE',   'TENASOSP',   [['A','19',1],           ['B','13',0],            ['C','17',0],            ['D','22',0]]],
  ['Calculer la moyenne de : 8, 12, 15, 9, 6.',                                             'ELEMENTAIRE',   'ENAFEP',     [['A','10',1],           ['B','12',0],            ['C','8',0],             ['D','9',0]]],
  ['Résoudre : x² + 4x − 5 = 0',                                                           'INTERMEDIAIRE', 'TENASOSP',   [['A','x = 1 ou x = −5',1],['B','x = 5 ou x = −1',0],['C','x = −4 ou x = 5',0],['D','x = 2 ou x = −3',0]]],
  ['Quel est le domaine de définition de f(x) = ln(2x − 4) ?',                             'AVANCE',        'EXAMEN_ETAT',[['A','x > 2',1],         ['B','x ≥ 2',0],         ['C','x > 4',0],         ['D','x > 0',0]]],
  ['Un capital de 10 000 CDF placé à 5% par an. Intérêt après 3 ans ?',                   'INTERMEDIAIRE', 'ENAFEP',     [['A','1 500 CDF',1],    ['B','500 CDF',0],       ['C','5 000 CDF',0],     ['D','150 CDF',0]]],
  ['Calculer le volume d\'un cube de côté 4 cm.',                                           'ELEMENTAIRE',   'ENAFEP',     [['A','64 cm³',1],       ['B','16 cm³',0],        ['C','48 cm³',0],        ['D','24 cm³',0]]],
  ['Quelle est la valeur de 3/4 + 5/6 ?',                                                  'ELEMENTAIRE',   'ENAFEP',     [['A','19/12',1],        ['B','8/10',0],          ['C','15/24',0],         ['D','4/5',0]]],
  ['La droite y = 0 est appelée :',                                                         'DEBUTANT',      'ENAFEP',     [['A','Axe des abscisses',1],['B','Axe des ordonnées',0],['C','Origine',0],    ['D','Bissectrice',0]]],
];
foreach ($qs_maths_ext as [$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$mat,$e,$d,$s,$o); $total_q++; }

// ── FRANÇAIS — pack étendu ──
$mat = $matiereMap['francais'];
$qs_fr_ext = [
  ['Quel est le participe passé de "naître" ?',                                             'DEBUTANT',      'ENAFEP',     [['A','né',1],           ['B','nait',0],          ['C','naissé',0],        ['D','né(e)',0]]],
  ['Identifier le sujet dans : "Les oiseaux chantent au lever du soleil."',                'DEBUTANT',      'ENAFEP',     [['A','Les oiseaux',1],  ['B','chantent',0],      ['C','au lever',0],      ['D','du soleil',0]]],
  ['Quel est le pluriel de "bail" ?',                                                       'ELEMENTAIRE',   'ENAFEP',     [['A','baux',1],         ['B','bails',0],         ['C','bailx',0],         ['D','bailes',0]]],
  ['Quelle figure de style est employée dans : "C\'est une pluie de balles." ?',           'ELEMENTAIRE',   'ENAFEP',     [['A','Métaphore',1],    ['B','Comparaison',0],   ['C','Personnification',0],['D','Hyperbole',0]]],
  ['Conjuguer "savoir" au subjonctif présent, 3e pers. sing. :',                           'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','qu\'il sache',1],  ['B','qu\'il sait',0],   ['C','qu\'il savait',0], ['D','qu\'il saurait',0]]],
  ['Dans "Il faisait beau, aussi sortirent-ils.", "aussi" a le sens de :',                 'AVANCE',        'EXAMEN_ETAT',[['A','c\'est pourquoi',1],['B','également',0],     ['C','pourtant',0],      ['D','cependant',0]]],
  ['Quel est le complément circonstanciel de temps dans : "Il part demain à l\'aube." ?', 'ELEMENTAIRE',   'ENAFEP',     [['A','demain à l\'aube',1],['B','Il',0],           ['C','part',0],          ['D','à l\'aube',0]]],
  ['Le mot "omnivore" contient le préfixe latin "omni" qui signifie :',                   'ELEMENTAIRE',   'ENAFEP',     [['A','tout',1],         ['B','beaucoup',0],      ['C','nourrir',0],       ['D','vivant',0]]],
  ['Quelle est la nature du mot "que" dans : "Je veux que tu partes." ?',                  'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','Conjonction de subordination',1],['B','Pronom relatif',0],['C','Adverbe',0],['D','Déterminant',0]]],
  ['Identifier l\'apposition dans : "Jean, médecin réputé, soigne tout le monde."',       'AVANCE',        'EXAMEN_ETAT',[['A','médecin réputé',1],['B','Jean',0],          ['C','soigne',0],        ['D','tout le monde',0]]],
  ['Quel temps est utilisé dans les propositions conditionnelles de type 3 (hypothèse passée) ?','EXPERT',  'EXAMEN_ETAT',[['A','Plus-que-parfait + conditionnel passé',1],['B','Imparfait + conditionnel',0],['C','Passé composé + futur',0],['D','Présent + conditionnel',0]]],
  ['Dans quelle phrase le "ne" est-il explétif (sans valeur négative) ?',                  'EXPERT',        'EXAMEN_ETAT',[['A','Je crains qu\'il ne vienne.',1],['B','Il ne vient pas.',0],['C','Il ne sait rien.',0],['D','Je n\'ai pas vu.',0]]],
  ['Quel est le sens littéraire du mot "périphrase" ?',                                    'AVANCE',        'EXAMEN_ETAT',[['A','Remplacer un mot par une expression qui le définit',1],['B','Répéter un même son',0],['C','Inverser l\'ordre des mots',0],['D','Exagérer une idée',0]]],
  ['Conjuguer "résoudre" au passé simple, 1ère pers. plur. :',                             'AVANCE',        'EXAMEN_ETAT',[['A','nous résolûmes',1], ['B','nous résolvions',0],['C','nous avons résolu',0],['D','nous résolurent',0]]],
];
foreach ($qs_fr_ext as [$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$mat,$e,$d,$s,$o); $total_q++; }

// ── CHIMIE — pack étendu ──
$mat = $matiereMap['chimie'];
$qs_ch_ext = [
  ['Quel est le symbole de l\'aluminium ?',                                                 'DEBUTANT',      'ENAFEP',     [['A','Al',1],           ['B','Au',0],            ['C','Ag',0],            ['D','Am',0]]],
  ['Le cuivre a pour symbole chimique :',                                                   'DEBUTANT',      'ENAFEP',     [['A','Cu',1],           ['B','Co',0],            ['C','Cr',0],            ['D','Cd',0]]],
  ['Quelle est la charge d\'un proton ?',                                                   'ELEMENTAIRE',   'ENAFEP',     [['A','+1',1],           ['B','−1',0],            ['C','0',0],             ['D','+2',0]]],
  ['Le fer rouille en formant :',                                                            'ELEMENTAIRE',   'ENAFEP',     [['A','De l\'oxyde de fer (Fe₂O₃)',1],['B','Du chlorure de fer',0],['C','Du sulfure de fer',0],['D','Du carbonate de fer',0]]],
  ['L\'eau de chaux se trouble en présence de :',                                           'ELEMENTAIRE',   'TENASOSP',   [['A','CO₂',1],          ['B','O₂',0],            ['C','N₂',0],            ['D','H₂',0]]],
  ['Quelle est la masse molaire de NaCl (Na=23, Cl=35,5) ?',                               'ELEMENTAIRE',   'TENASOSP',   [['A','58,5 g/mol',1],   ['B','46 g/mol',0],      ['C','71 g/mol',0],      ['D','35,5 g/mol',0]]],
  ['Lors d\'une électrolyse, la réduction a lieu :',                                        'AVANCE',        'EXAMEN_ETAT',[['A','À la cathode (−)',1],['B','À l\'anode (+)',0], ['C','Dans le sel fondu',0],['D','Dans l\'électrolyte',0]]],
  ['Le nombre d\'Avogadro est approximativement :',                                         'INTERMEDIAIRE', 'TENASOSP',   [['A','6,02 × 10²³',1],  ['B','6,02 × 10²⁰',0],   ['C','9,8 × 10²³',0],    ['D','1,6 × 10⁻¹⁹',0]]],
  ['Les liaisons hydrogène expliquent :',                                                   'AVANCE',        'EXAMEN_ETAT',[['A','La tension superficielle de l\'eau et son point d\'ébullition élevé',1],['B','La rigidité des métaux',0],['C','La solubilité des lipides',0],['D','La conductivité ionique',0]]],
  ['L\'oxydation correspond à :',                                                            'INTERMEDIAIRE', 'TENASOSP',   [['A','Une perte d\'électrons',1],['B','Un gain d\'électrons',0],['C','Une prise d\'hydrogène',0],['D','Un gain de protons',0]]],
  ['La formule de l\'acide sulfurique est :',                                               'ELEMENTAIRE',   'TENASOSP',   [['A','H₂SO₄',1],        ['B','H₂SO₃',0],         ['C','HSO₄',0],          ['D','SO₄',0]]],
  ['La loi de conservation de la matière (Lavoisier) dit que :',                           'ELEMENTAIRE',   'ENAFEP',     [['A','La masse totale se conserve lors d\'une réaction',1],['B','L\'énergie se crée',0],['C','Les atomes disparaissent',0],['D','Le volume est constant',0]]],
];
foreach ($qs_ch_ext as [$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$mat,$e,$d,$s,$o); $total_q++; }

// ── PHYSIQUE — pack étendu ──
$mat = $matiereMap['physique'];
$qs_ph_ext = [
  ['Une balle lancée horizontalement à v = 10 m/s depuis h = 20 m. Temps de chute (g=10)?','AVANCE',       'EXAMEN_ETAT',[['A','2 s',1],           ['B','4 s',0],           ['C','1 s',0],           ['D','√2 s',0]]],
  ['La tension aux bornes d\'un générateur idéal est égale à :',                           'ELEMENTAIRE',   'ENAFEP',     [['A','Sa force électromotrice',1],['B','Sa résistance interne × I',0],['C','La puissance divisée par I',0],['D','Toujours 12 V',0]]],
  ['Le principe d\'inertie (1ère loi de Newton) stipule :',                                'ELEMENTAIRE',   'ENAFEP',     [['A','Tout corps persévère dans son état de repos ou MRU sans force',1],['B','F = m × a',0],['C','Action = −Réaction',0],['D','L\'énergie se conserve',0]]],
  ['Quelle est la relation entre longueur d\'onde (λ), fréquence (f) et vitesse (v) ?',   'INTERMEDIAIRE', 'TENASOSP',   [['A','v = λ × f',1],    ['B','v = λ / f',0],     ['C','λ = v × f',0],     ['D','f = λ × v',0]]],
  ['Un condensateur de capacité C = 10 µF est chargé sous U = 5 V. Sa charge Q vaut :',  'AVANCE',        'EXAMEN_ETAT',[['A','50 µC',1],          ['B','2 µC',0],          ['C','0,5 µC',0],        ['D','50 mC',0]]],
  ['L\'effet Joule correspond à :',                                                         'INTERMEDIAIRE', 'TENASOSP',   [['A','La chaleur dégagée par un conducteur traversé par un courant',1],['B','La variation de résistance avec la température',0],['C','La force sur un conducteur dans un champ magnétique',0],['D','La lumière émise par un filament',0]]],
  ['La résonance d\'un circuit RLC se produit quand :',                                    'EXPERT',        'EXAMEN_ETAT',[['A','La fréquence d\'excitation = fréquence propre',1],['B','R = L',0],['C','C = 0',0],['D','L = 0',0]]],
  ['La loi de Faraday dit que la force électromotrice induite est proportionnelle à :',   'AVANCE',        'EXAMEN_ETAT',[['A','La variation du flux magnétique dans le temps',1],['B','L\'intensité du courant',0],['C','La résistance du circuit',0],['D','La tension appliquée',0]]],
  ['Quelle est l\'unité de la résistance électrique ?',                                    'DEBUTANT',      'ENAFEP',     [['A','Ohm (Ω)',1],       ['B','Volt',0],          ['C','Ampère',0],        ['D','Farad',0]]],
  ['Un miroir plan produit une image :',                                                    'ELEMENTAIRE',   'ENAFEP',     [['A','Virtuelle, droite, de même taille',1],['B','Réelle, renversée',0],['C','Agrandie, virtuelle',0],['D','Réelle, droite',0]]],
  ['La puissance dissipée dans une résistance R parcourue par I est :',                    'ELEMENTAIRE',   'TENASOSP',   [['A','P = R × I²',1],   ['B','P = R × I',0],     ['C','P = R / I²',0],    ['D','P = I / R',0]]],
  ['Quel est l\'ordre de grandeur de la masse d\'un proton ?',                             'AVANCE',        'EXAMEN_ETAT',[['A','1,67 × 10⁻²⁷ kg',1],['B','9,1 × 10⁻³¹ kg',0],['C','1 kg',0],       ['D','1,67 × 10⁻²⁴ g',0]]],
];
foreach ($qs_ph_ext as [$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$mat,$e,$d,$s,$o); $total_q++; }

// ── BIOLOGIE — pack étendu ──
$mat = $matiereMap['biologie'];
$qs_bio_ext = [
  ['Quel est le rôle du rein dans l\'organisme ?',                                         'ELEMENTAIRE',   'ENAFEP',     [['A','Filtration du sang et production d\'urine',1],['B','Production de bile',0],['C','Absorption des lipides',0],['D','Régulation de la glycémie',0]]],
  ['Les lymphocytes T sont impliqués dans :',                                               'AVANCE',        'EXAMEN_ETAT',[['A','L\'immunité cellulaire',1],['B','La production d\'anticorps',0],['C','La coagulation',0],['D','Le transport de l\'O₂',0]]],
  ['La double hélice de l\'ADN a été découverte par :',                                    'INTERMEDIAIRE', 'TENASOSP',   [['A','Watson et Crick (1953)',1],['B','Mendel',0],['C','Pasteur',0],['D','Darwin',0]]],
  ['Quel organe produit les hormones œstrogène et progestérone ?',                         'INTERMEDIAIRE', 'TENASOSP',   [['A','Les ovaires',1],  ['B','L\'utérus',0],     ['C','Le pancréas',0],   ['D','Les glandes surrénales',0]]],
  ['La glycolyse se déroule dans :',                                                        'AVANCE',        'EXAMEN_ETAT',[['A','Le cytoplasme',1],  ['B','La mitochondrie',0],['C','Le noyau',0],      ['D','Le réticulum',0]]],
  ['Les plaquettes sanguines sont responsables de :',                                      'ELEMENTAIRE',   'ENAFEP',     [['A','La coagulation',1],['B','Le transport de l\'oxygène',0],['C','La défense immunitaire',0],['D','La production d\'hormones',0]]],
  ['Quel type de virus cause le VIH/SIDA ?',                                               'INTERMEDIAIRE', 'ENAFEP',     [['A','Rétrovirus (ARN)',1],['B','Virus à ADN',0],  ['C','Bactériophage',0], ['D','Prion',0]]],
  ['La nutrition autotrophe des plantes repose sur :',                                     'ELEMENTAIRE',   'ENAFEP',     [['A','La photosynthèse (lumière + CO₂ + H₂O)',1],['B','La fermentation',0],['C','La respiration cellulaire',0],['D','L\'absorption de nitrates',0]]],
  ['Quelle loi de Mendel dit que les caractères ségrègent indépendamment ?',               'AVANCE',        'EXAMEN_ETAT',[['A','Loi de l\'assortiment indépendant (2e loi)',1],['B','Loi de dominance',0],['C','Loi de ségrégation',0],['D','Loi de Lamarck',0]]],
  ['Le système immunitaire reconnaît les agents pathogènes grâce aux :',                   'AVANCE',        'EXAMEN_ETAT',[['A','Antigènes de surface',1],['B','Anticorps',0],  ['C','Lymphocytes B',0], ['D','Globules rouges',0]]],
  ['Quel est l\'acide aminé codé par AUG (codon initiateur) ?',                            'EXPERT',        'EXAMEN_ETAT',[['A','Méthionine',1],    ['B','Lysine',0],        ['C','Leucine',0],       ['D','Valine',0]]],
  ['La cholestase correspond à un trouble de :',                                            'EXPERT',        'EXAMEN_ETAT',[['A','L\'écoulement de la bile',1],['B','La glycogénolyse',0],['C','La coagulation',0],['D','La diurèse',0]]],
];
foreach ($qs_bio_ext as [$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$mat,$e,$d,$s,$o); $total_q++; }

// ── HISTOIRE-GÉOGRAPHIE — pack étendu ──
$mat = $matiereMap['histgeo'];
$qs_hg_ext = [
  ['Quel explorateur européen a "découvert" le fleuve Congo en 1482 ?',                   'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','Diogo Cão',1],     ['B','Vasco de Gama',0], ['C','Christophe Colomb',0],['D','Magellan',0]]],
  ['Le Traité de Berlin (1884-85) a :',                                                    'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','Partagé l\'Afrique entre puissances coloniales',1],['B','Accordé l\'indépendance aux colonies',0],['C','Interdit la traite des esclaves',0],['D','Créé la SDN',0]]],
  ['Patrice Lumumba était le premier :',                                                   'ELEMENTAIRE',   'ENAFEP',     [['A','Premier ministre du Congo indépendant',1],['B','Président de la RDC',0],['C','Roi du Congo',0],['D','Chef de la rébellion katangaise',0]]],
  ['Le fleuve Congo est le _____ plus long fleuve d\'Afrique.',                            'ELEMENTAIRE',   'ENAFEP',     [['A','2e',1],           ['B','1er',0],           ['C','3e',0],            ['D','4e',0]]],
  ['Quel pays est voisin de la RDC à l\'est ?',                                            'DEBUTANT',      'ENAFEP',     [['A','Le Rwanda et le Burundi',1],['B','Le Niger',0],['C','Le Cameroun',0], ['D','L\'Angola',0]]],
  ['La ville de Lubumbashi est la capitale de la province de :',                           'ELEMENTAIRE',   'ENAFEP',     [['A','Haut-Katanga',1], ['B','Tanganyika',0],    ['C','Lualaba',0],       ['D','Kasaï',0]]],
  ['La Conférence Nationale Souveraine de la RDC a eu lieu en :',                         'INTERMEDIAIRE', 'ENAFEP',     [['A','1991-1992',1],    ['B','1980',0],          ['C','1997',0],          ['D','2003',0]]],
  ['Les Accords de paix de Lusaka (1999) concernaient :',                                  'AVANCE',        'EXAMEN_ETAT',[['A','La guerre au Congo (RDC)',1],['B','La guerre civile au Rwanda',0],['C','Le conflit angolais',0],['D','La guerre d\'Éthiopie',0]]],
  ['Quel est le principal port maritime de la RDC ?',                                      'ELEMENTAIRE',   'ENAFEP',     [['A','Matadi',1],       ['B','Boma',0],          ['C','Banana',0],        ['D','Kinshasa',0]]],
  ['La Seconde Guerre mondiale a pris fin en :',                                            'ELEMENTAIRE',   'ENAFEP',     [['A','1945',1],         ['B','1918',0],          ['C','1939',0],          ['D','1950',0]]],
  ['Le Mouvement Non-Aligné regroupe des pays qui :',                                     'AVANCE',        'EXAMEN_ETAT',[['A','Refusent de s\'aligner sur les blocs USA ou URSS pendant la Guerre froide',1],['B','Sont neutres dans toutes les guerres',0],['C','Appartiennent à l\'ONU uniquement',0],['D','Ne participent pas aux échanges commerciaux',0]]],
  ['La déforestation du bassin du Congo menace principalement :',                          'INTERMEDIAIRE', 'ENAFEP',     [['A','La biodiversité et la régulation du climat mondial',1],['B','Le trafic aérien',0],['C','Les voies navigables',0],['D','Les gisements miniers',0]]],
];
foreach ($qs_hg_ext as [$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$mat,$e,$d,$s,$o); $total_q++; }

// ── ANGLAIS — pack étendu ──
$mat = $matiereMap['anglais'];
$qs_en_ext = [
  ['What is the superlative of "far" ?',                                                    'ELEMENTAIRE',   'ENAFEP',     [['A','farthest',1],     ['B','more far',0],      ['C','farrer',0],        ['D','most far',0]]],
  ['Choose the correct verb form: "The news ___ bad."',                                    'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','is',1],            ['B','are',0],           ['C','were',0],          ['D','have been',0]]],
  ['What is the meaning of "eloquent" ?',                                                   'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','Fluent and persuasive in speech',1],['B','Angry and loud',0],['C','Confused and hesitant',0],['D','Silent and reserved',0]]],
  ['Which sentence is in the Future Perfect tense ?',                                      'AVANCE',        'EXAMEN_ETAT',[['A','By next year, I will have finished my studies.',1],['B','I will finish my studies.',0],['C','I have been studying for years.',0],['D','I was finishing my studies.',0]]],
  ['The word "although" introduces a:',                                                     'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','Concessive clause',1],['B','Conditional clause',0],['C','Relative clause',0],['D','Purpose clause',0]]],
  ['"She works hard so that she can succeed." "So that" expresses:',                       'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','Purpose',1],        ['B','Result',0],        ['C','Concession',0],    ['D','Condition',0]]],
  ['Identify the error: "He is more taller than his brother."',                             'ELEMENTAIRE',   'ENAFEP',     [['A','Double comparative — should be "taller"',1],['B','Wrong subject',0],['C','Wrong tense',0],['D','No error',0]]],
  ['What is a "thesis statement" in academic writing ?',                                   'AVANCE',        'EXAMEN_ETAT',[['A','The main argument of an essay, stated clearly',1],['B','The conclusion of an essay',0],['C','A quotation from a source',0],['D','The title of a paragraph',0]]],
  ['"He can neither read nor write." This sentence uses:',                                  'AVANCE',        'EXAMEN_ETAT',[['A','Correlative conjunctions',1],['B','Relative clause',0],['C','Parallel structure only',0],['D','Compound sentence',0]]],
  ['Which sentence uses "used to" correctly ?',                                             'ELEMENTAIRE',   'ENAFEP',     [['A','I used to play football every Saturday.',1],['B','I used to plays football.',0],['C','I am used to play football.',0],['D','I use to play football.',0]]],
  ['What does "ubiquitous" mean ?',                                                         'EXPERT',        'EXAMEN_ETAT',[['A','Found everywhere',1],['B','Very expensive',0], ['C','Extremely loud',0],['D','Unique and rare',0]]],
  ['"They had barely left when it started raining." "barely" is:',                         'AVANCE',        'EXAMEN_ETAT',[['A','An adverb of degree indicating scarcely',1],['B','A conjunction',0],['C','A preposition',0],['D','An adjective',0]]],
];
foreach ($qs_en_ext as [$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$mat,$e,$d,$s,$o); $total_q++; }

// ── SCIENCES — pack étendu ──
$mat = $matiereMap['sciences'];
$qs_sc_ext = [
  ['Quel est le rôle de l\'estomac dans la digestion ?',                                  'ELEMENTAIRE',   'ENAFEP',     [['A','Broyer les aliments et démarrer la digestion des protéines',1],['B','Absorber les nutriments',0],['C','Produire la bile',0],['D','Filtrer les déchets',0]]],
  ['La photosynthèse se déroule principalement dans les :',                                'ELEMENTAIRE',   'ENAFEP',     [['A','Feuilles (chloroplastes)',1],['B','Racines',0],         ['C','Fleurs',0],        ['D','Tiges',0]]],
  ['Quelle est la définition d\'un écosystème ?',                                         'ELEMENTAIRE',   'ENAFEP',     [['A','L\'ensemble des êtres vivants et leur environnement non vivant',1],['B','Uniquement la faune d\'une région',0],['C','Les espèces végétales d\'une zone',0],['D','Le sol et ses minéraux',0]]],
  ['Quel est le phénomène par lequel une plante perd de l\'eau par ses feuilles ?',       'ELEMENTAIRE',   'TENASOSP',   [['A','Transpiration (ou évapotranspiration)',1],['B','Photosynthèse',0],['C','Osmose',0],          ['D','Absorption',0]]],
  ['L\'ADN est constitué de :',                                                             'INTERMEDIAIRE', 'TENASOSP',   [['A','Nucléotides (bases, sucre, phosphate)',1],['B','Acides aminés',0],['C','Graisses et sucres',0],['D','Lipides et protéines',0]]],
  ['Quel est l\'effet de serre en termes simples ?',                                       'ELEMENTAIRE',   'ENAFEP',     [['A','Les gaz atmosphériques retiennent la chaleur solaire',1],['B','Le soleil chauffe directement le sol',0],['C','La couche d\'ozone réfléchit la chaleur',0],['D','La vapeur d\'eau refroidit l\'atmosphère',0]]],
  ['La marée est causée par :',                                                             'INTERMEDIAIRE', 'TENASOSP',   [['A','L\'attraction gravitationnelle de la Lune (et du Soleil)',1],['B','La rotation de la Terre',0],['C','Les vents océaniques',0],['D','Les courants thermiques',0]]],
  ['Le tremblement de terre se mesure avec :',                                              'ELEMENTAIRE',   'ENAFEP',     [['A','Le sismographe (échelle de Richter)',1],['B','Le baromètre',0],['C','Le thermomètre',0],      ['D','L\'hygromètre',0]]],
  ['Une molécule d\'eau H₂O a une géométrie :',                                            'AVANCE',        'EXAMEN_ETAT',[['A','Angulaire (coudée)',1],['B','Linéaire',0],          ['C','Tétraédrique',0],  ['D','Trigonale',0]]],
  ['Quel organisme est à la base des chaînes alimentaires marines ?',                     'ELEMENTAIRE',   'ENAFEP',     [['A','Le phytoplancton',1],['B','Le zooplancton',0],     ['C','Les algues vertes',0],['D','Les bactéries',0]]],
  ['La symbiose est une relation entre deux organismes où :',                               'INTERMEDIAIRE', 'TENASOSP',   [['A','Les deux bénéficient',1],['B','L\'un est parasité',0],['C','L\'un est indifférent',0],['D','Les deux sont lésés',0]]],
  ['Qu\'est-ce que la tectonique des plaques explique ?',                                  'INTERMEDIAIRE', 'ENAFEP',     [['A','Les tremblements de terre, volcans et formation des montagnes',1],['B','Les phénomènes météorologiques',0],['C','La formation des rivières',0],['D','L\'évolution des espèces',0]]],
];
foreach ($qs_sc_ext as [$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$mat,$e,$d,$s,$o); $total_q++; }

/* ═══════════════════════════════════════════
   MATHÉMATIQUES — PACK ENAFEP (15 questions primaire)
═══════════════════════════════════════════ */
$mat = $matiereMap['maths'];
$qs_maths_enafep = [
  ['Un marchand achète 20 mangues à 150 CDF chacune et les revend à 200 CDF. Quel est son bénéfice total ?','DEBUTANT','ENAFEP',[['A','1 000 CDF',1],['B','500 CDF',0],['C','4 000 CDF',0],['D','2 000 CDF',0]]],
  ['Quelle est la valeur du chiffre 7 dans le nombre 7 452 ?',                             'DEBUTANT',  'ENAFEP',[['A','7 000',1],       ['B','700',0],      ['C','7',0],        ['D','70',0]]],
  ['Un train part à 06h30 et arrive à 09h15. Combien de temps a duré le trajet ?',         'DEBUTANT',  'ENAFEP',[['A','2h45min',1],     ['B','3h15min',0],  ['C','2h15min',0],  ['D','3h45min',0]]],
  ['Exprimer 3/4 en décimal.',                                                              'DEBUTANT',  'ENAFEP',[['A','0,75',1],        ['B','0,34',0],     ['C','0,43',0],     ['D','0,7',0]]],
  ['Quel est le PPCM de 4 et 6 ?',                                                          'ELEMENTAIRE','ENAFEP',[['A','12',1],          ['B','24',0],       ['C','6',0],        ['D','4',0]]],
  ['Un rectangle a un périmètre de 36 cm et une largeur de 8 cm. Quelle est sa longueur ?','ELEMENTAIRE','ENAFEP',[['A','10 cm',1],       ['B','18 cm',0],    ['C','28 cm',0],    ['D','4 cm',0]]],
  ['Combien de centimètres y a-t-il dans 2,5 mètres ?',                                    'DEBUTANT',  'ENAFEP',[['A','250 cm',1],       ['B','25 cm',0],    ['C','2 500 cm',0], ['D','0,25 cm',0]]],
  ['Un robinet remplit un réservoir de 60 litres en 3 heures. Débit par heure ?',          'ELEMENTAIRE','ENAFEP',[['A','20 L/h',1],      ['B','30 L/h',0],   ['C','180 L/h',0],  ['D','10 L/h',0]]],
  ['Calculer l\'aire d\'un carré de côté 9 cm.',                                           'DEBUTANT',  'ENAFEP',[['A','81 cm²',1],       ['B','36 cm²',0],   ['C','18 cm²',0],   ['D','9 cm²',0]]],
  ['Résoudre : 5x = 45',                                                                    'DEBUTANT',  'ENAFEP',[['A','9',1],            ['B','40',0],       ['C','50',0],       ['D','5',0]]],
  ['Quelle fraction est équivalente à 0,6 ?',                                               'ELEMENTAIRE','ENAFEP',[['A','3/5',1],         ['B','6/10 simplifiée',0],['C','1/6',0],   ['D','6/100',0]]],
  ['Un élève a 72 sur 100 en maths et 84 sur 100 en français. Quelle est sa moyenne ?',   'ELEMENTAIRE','ENAFEP',[['A','78',1],           ['B','72',0],       ['C','84',0],       ['D','76',0]]],
  ['Combien de kg dans 3 500 g ?',                                                           'DEBUTANT',  'ENAFEP',[['A','3,5 kg',1],       ['B','35 kg',0],    ['C','0,35 kg',0],  ['D','350 kg',0]]],
  ['Quel est le résultat de 12 × 15 ?',                                                     'DEBUTANT',  'ENAFEP',[['A','180',1],          ['B','170',0],      ['C','195',0],      ['D','162',0]]],
  ['Dans une classe de 40 élèves, 30% sont des filles. Combien de filles y a-t-il ?',     'ELEMENTAIRE','ENAFEP',[['A','12',1],           ['B','30',0],       ['C','10',0],       ['D','20',0]]],
];
foreach ($qs_maths_enafep as [$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$mat,$e,$d,$s,$o); $total_q++; }

/* ═══════════════════════════════════════════
   FRANÇAIS — PACK ENAFEP orthographe/grammaire
═══════════════════════════════════════════ */
$mat = $matiereMap['francais'];
$qs_fr_enafep = [
  ['Choisir la bonne orthographe : "Il a acheté de ___ chaussures neuves."',               'DEBUTANT',   'ENAFEP',[['A','des',1],          ['B','de',0],       ['C','d\'',0],      ['D','les',0]]],
  ['Accorder correctement : "Les filles sont ___." (beau)',                                 'DEBUTANT',   'ENAFEP',[['A','belles',1],       ['B','beaux',0],    ['C','belle',0],    ['D','beau',0]]],
  ['Quel est le féminin du nom "acteur" ?',                                                 'DEBUTANT',   'ENAFEP',[['A','actrice',1],      ['B','acteure',0],  ['C','acteuse',0],  ['D','actress',0]]],
  ['Dans quelle phrase le verbe est-il au futur simple ?',                                  'ELEMENTAIRE','ENAFEP',[['A','Il partira demain.',1],['B','Il est parti.',0],['C','Il part.',0],['D','Il partait.',0]]],
  ['Quel est le sujet du verbe "aiment" dans : "Les chiens aiment jouer." ?',              'DEBUTANT',   'ENAFEP',[['A','Les chiens',1],   ['B','aiment',0],   ['C','jouer',0],    ['D','Les',0]]],
  ['Conjuguer "aller" à l\'imparfait, 3e pers. plur. :',                                   'ELEMENTAIRE','ENAFEP',[['A','ils allaient',1],  ['B','ils vont',0], ['C','ils iront',0],['D','ils sont allés',0]]],
  ['Quel est le pluriel de "travail" ?',                                                    'ELEMENTAIRE','ENAFEP',[['A','travaux',1],       ['B','travails',0], ['C','travailx',0], ['D','travailles',0]]],
  ['Dans "Je mange rapidement.", quelle est la fonction de "rapidement" ?',                'ELEMENTAIRE','ENAFEP',[['A','Complément circonstanciel de manière',1],['B','COD',0],['C','Sujet',0],['D','Attribut',0]]],
  ['Quel est l\'antonyme de "courageux" ?',                                                 'DEBUTANT',   'ENAFEP',[['A','peureux / lâche',1],['B','fort',0],    ['C','brave',0],    ['D','timide',0]]],
  ['Choisir la forme correcte : "Elle s\'est ___ la main." (blesser)',                     'INTERMEDIAIRE','EXAMEN_ETAT',[['A','blessé',1],  ['B','blessée',0],  ['C','blessés',0],  ['D','blessées',0]]],
  ['Identifier le complément d\'objet indirect dans : "Il téléphone à sa mère chaque soir."','INTERMEDIAIRE','EXAMEN_ETAT',[['A','à sa mère',1],['B','chaque soir',0],['C','Il',0],['D','téléphone',0]]],
  ['Quelle est la définition d\'un homonyme ?',                                             'ELEMENTAIRE','ENAFEP',[['A','Mots de même prononciation mais de sens différent',1],['B','Mots de même sens',0],['C','Mots de même famille',0],['D','Mots contraires',0]]],
  ['Quelle phrase contient une négation partielle ?',                                       'INTERMEDIAIRE','EXAMEN_ETAT',[['A','Il ne mange jamais de viande.',1],['B','Il ne mange pas.',0],['C','Il ne mange rien.',0],['D','Il ne vient pas.',0]]],
  ['Quel est le mode du verbe "viens" dans : "Viens ici !" ?',                             'ELEMENTAIRE','ENAFEP',[['A','Impératif présent',1],['B','Indicatif présent',0],['C','Subjonctif présent',0],['D','Infinitif',0]]],
  ['Quelle figure de style est employée dans : "Les étoiles me regardaient dormir." ?',   'INTERMEDIAIRE','EXAMEN_ETAT',[['A','Personnification',1],['B','Métaphore',0],['C','Hyperbole',0],['D','Comparaison',0]]],
];
foreach ($qs_fr_enafep as [$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$mat,$e,$d,$s,$o); $total_q++; }

/* ═══════════════════════════════════════════
   CHIMIE — PACK TENASOSP avancé
═══════════════════════════════════════════ */
$mat = $matiereMap['chimie'];
$qs_ch_tenasosp = [
  ['Lors de la réaction Mg + 2HCl → MgCl₂ + H₂, quel gaz est dégagé ?',                'ELEMENTAIRE','TENASOSP',[['A','Dihydrogène H₂',1],['B','Dioxygène O₂',0],['C','CO₂',0],['D','Cl₂',0]]],
  ['La masse d\'une mole de glucose C₆H₁₂O₆ (C=12, H=1, O=16) vaut :',                  'INTERMEDIAIRE','TENASOSP',[['A','180 g/mol',1],   ['B','96 g/mol',0],  ['C','162 g/mol',0],['D','192 g/mol',0]]],
  ['Les hydrocarbures aromatiques contiennent un noyau :',                                 'AVANCE',    'EXAMEN_ETAT',[['A','Benzénique',1],   ['B','Cyclohexane',0],['C','Éthylénique',0],['D','Acétylénique',0]]],
  ['Quelle est la valeur du pH d\'une solution de NaOH à 0,1 mol/L ?',                   'AVANCE',    'EXAMEN_ETAT',[['A','13',1],          ['B','7',0],         ['C','1',0],        ['D','11',0]]],
  ['La réaction de combustion du méthane : CH₄ + 2O₂ → CO₂ + 2H₂O. Le coefficient de O₂ est :','ELEMENTAIRE','TENASOSP',[['A','2',1],['B','1',0],['C','4',0],['D','3',0]]],
  ['Qu\'est-ce qu\'un acide selon Brønsted-Lowry ?',                                      'AVANCE',    'EXAMEN_ETAT',[['A','Un donneur de proton H⁺',1],['B','Un accepteur d\'électrons',0],['C','Un donneur d\'électrons',0],['D','Un sel',0]]],
  ['La formule de l\'hydroxyde de calcium (chaux éteinte) est :',                         'ELEMENTAIRE','TENASOSP',[['A','Ca(OH)₂',1],     ['B','CaO',0],       ['C','CaCO₃',0],    ['D','CaH₂',0]]],
  ['Le charbon actif est utilisé pour :',                                                  'ELEMENTAIRE','ENAFEP',[['A','Adsorber les impuretés et toxines',1],['B','Catalyser des réactions',0],['C','Neutraliser les acides',0],['D','Précipiter les sels',0]]],
  ['Quel est l\'état de l\'eau à −10°C et pression normale ?',                            'DEBUTANT',  'ENAFEP',[['A','Solide (glace)',1], ['B','Liquide',0],   ['C','Gazeux',0],   ['D','Plasma',0]]],
  ['La réaction Fe₂O₃ + 3CO → 2Fe + 3CO₂ est utilisée dans :',                           'AVANCE',    'EXAMEN_ETAT',[['A','La production de fer en haut-fourneau',1],['B','La fabrication de plastique',0],['C','La synthèse de l\'ammoniac',0],['D','La production d\'aluminium',0]]],
  ['Combien d\'électrons peut contenir la couche L (n=2) ?',                               'ELEMENTAIRE','TENASOSP',[['A','8',1],           ['B','2',0],         ['C','18',0],       ['D','32',0]]],
  ['Les ions Na⁺ et Cl⁻ forment une liaison de type :',                                   'ELEMENTAIRE','TENASOSP',[['A','Ionique',1],      ['B','Covalente',0], ['C','Métallique',0],['D','Hydrogène',0]]],
  ['La corrosion du fer est accélérée en présence de :',                                   'ELEMENTAIRE','ENAFEP',[['A','Eau et oxygène (milieu humide)',1],['B','Huile',0],['C','Peinture',0],['D','Azote',0]]],
  ['La concentration massique C (g/L) se calcule par :',                                  'INTERMEDIAIRE','TENASOSP',[['A','C = masse soluté / volume solution',1],['B','C = volume / masse',0],['C','C = mol / L × MM',0],['D','C = masse totale / densité',0]]],
  ['Dans la classification périodique, les éléments de même famille (colonne) ont :',     'AVANCE',    'EXAMEN_ETAT',[['A','Le même nombre d\'électrons de valence',1],['B','La même masse atomique',0],['C','Le même nombre de couches',0],['D','La même configuration électronique totale',0]]],
];
foreach ($qs_ch_tenasosp as [$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$mat,$e,$d,$s,$o); $total_q++; }

/* ═══════════════════════════════════════════
   PHYSIQUE — PACK mécanique / électricité
═══════════════════════════════════════════ */
$mat = $matiereMap['physique'];
$qs_ph_meca = [
  ['Un objet de 3 kg est soulevé de 2 m. L\'énergie potentielle gagnée (g=10) est :',    'ELEMENTAIRE','TENASOSP',[['A','60 J',1],        ['B','6 J',0],       ['C','30 J',0],     ['D','600 J',0]]],
  ['La vitesse moyenne d\'un objet parcourant 150 m en 5 s est :',                        'DEBUTANT',  'ENAFEP',[['A','30 m/s',1],       ['B','15 m/s',0],    ['C','750 m/s',0],  ['D','0,03 m/s',0]]],
  ['Quelle est l\'unité de la quantité de mouvement (p = mv) ?',                          'ELEMENTAIRE','TENASOSP',[['A','kg·m/s',1],      ['B','kg·m²/s',0],   ['C','N/m',0],      ['D','J',0]]],
  ['La troisième loi de Newton (action-réaction) dit :',                                   'ELEMENTAIRE','ENAFEP',[['A','Toute force exercée sur A par B est opposée à celle de A sur B',1],['B','F = ma',0],['C','Un corps au repos reste au repos',0],['D','L\'énergie est conservée',0]]],
  ['Un circuit électrique en série : si une lampe s\'éteint, les autres :',               'ELEMENTAIRE','ENAFEP',[['A','S\'éteignent aussi',1],['B','Brillent plus fort',0],['C','Ne changent pas',0],['D','Grillent',0]]],
  ['La résistivité d\'un conducteur dépend de :',                                          'AVANCE',    'EXAMEN_ETAT',[['A','Sa nature (matériau) et sa température',1],['B','Sa couleur',0],['C','La tension appliquée',0],['D','Le courant circulant',0]]],
  ['Quelle formule donne l\'énergie électrique consommée (P, t) ?',                       'ELEMENTAIRE','TENASOSP',[['A','E = P × t',1],   ['B','E = P / t',0], ['C','E = U × I²',0],['D','E = R × t',0]]],
  ['Si on double la distance entre deux charges, la force électrostatique devient :',     'AVANCE',    'EXAMEN_ETAT',[['A','4 fois plus faible',1],['B','2 fois plus faible',0],['C','4 fois plus forte',0],['D','La même',0]]],
  ['Un mouvement circulaire uniforme a une accélération :',                                'AVANCE',    'EXAMEN_ETAT',[['A','Centripète (dirigée vers le centre)',1],['B','Tangentielle',0],['C','Nulle',0],['D','Constante en direction',0]]],
  ['La loi d\'Ampère décrit :',                                                            'AVANCE',    'EXAMEN_ETAT',[['A','La relation entre courant électrique et champ magnétique',1],['B','La loi d\'Ohm généralisée',0],['C','L\'induction électromagnétique',0],['D','La conservation de l\'énergie',0]]],
  ['Un diode laisse passer le courant :',                                                  'ELEMENTAIRE','ENAFEP',[['A','Dans un seul sens (polarisation directe)',1],['B','Dans les deux sens',0],['C','Seulement en courant alternatif',0],['D','Jamais',0]]],
  ['L\'indice de réfraction d\'un verre est n = 1,5. La lumière y va à :',               'AVANCE',    'EXAMEN_ETAT',[['A','2 × 10⁸ m/s',1], ['B','3 × 10⁸ m/s',0],['C','1 × 10⁸ m/s',0],['D','4,5 × 10⁸ m/s',0]]],
  ['Dans un circuit RC, la constante de temps τ (tau) est :',                              'EXPERT',    'EXAMEN_ETAT',[['A','τ = R × C',1],   ['B','τ = R / C',0], ['C','τ = C / R',0], ['D','τ = R + C',0]]],
  ['Un alternateur convertit l\'énergie :',                                                'ELEMENTAIRE','ENAFEP',[['A','Mécanique → Électrique',1],['B','Électrique → Mécanique',0],['C','Thermique → Lumineuse',0],['D','Chimique → Électrique',0]]],
  ['La puissance d\'un moteur de 5 000 J effectuant un travail en 10 s est :',           'ELEMENTAIRE','TENASOSP',[['A','500 W',1],        ['B','50 000 W',0],  ['C','5 W',0],      ['D','50 W',0]]],
];
foreach ($qs_ph_meca as [$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$mat,$e,$d,$s,$o); $total_q++; }

/* ═══════════════════════════════════════════
   BIOLOGIE — PACK corps humain & génétique
═══════════════════════════════════════════ */
$mat = $matiereMap['biologie'];
$qs_bio_corps = [
  ['Le cerveau humain est protégé par :',                                                  'ELEMENTAIRE','ENAFEP',[['A','La boîte crânienne et les méninges',1],['B','Le cartilage',0],['C','La peau uniquement',0],['D','La colonne vertébrale',0]]],
  ['Quel est le rôle de l\'insuline produite par le pancréas ?',                          'INTERMEDIAIRE','TENASOSP',[['A','Abaisser la glycémie (favoriser l\'entrée du glucose dans les cellules)',1],['B','Augmenter la glycémie',0],['C','Digérer les graisses',0],['D','Stimuler la thyroïde',0]]],
  ['Combien de paires de chromosomes possède l\'être humain ?',                            'ELEMENTAIRE','TENASOSP',[['A','23 paires',1],   ['B','46 paires',0], ['C','23',0],       ['D','48 paires',0]]],
  ['L\'ARN messager est synthétisé lors de la :',                                          'AVANCE',    'EXAMEN_ETAT',[['A','Transcription (dans le noyau)',1],['B','Traduction',0],['C','Réplication',0],['D','Mitose',0]]],
  ['Quel est le rôle de l\'appareil de Golgi dans la cellule ?',                          'AVANCE',    'EXAMEN_ETAT',[['A','Trier, modifier et expédier les protéines',1],['B','Produire de l\'énergie',0],['C','Synthétiser les lipides',0],['D','Stocker l\'ADN',0]]],
  ['La pression artérielle systolique correspond à :',                                     'AVANCE',    'EXAMEN_ETAT',[['A','La pression lors de la contraction du cœur (systole)',1],['B','La pression lors du relâchement',0],['C','La fréquence cardiaque',0],['D','Le débit sanguin',0]]],
  ['La vitamine A est nécessaire pour :',                                                  'ELEMENTAIRE','ENAFEP',[['A','La vision (rétine) et la peau',1],['B','La coagulation du sang',0],['C','La calcification des os',0],['D','La synthèse de collagène',0]]],
  ['Les nerfs crâniens sont des nerfs du :',                                               'AVANCE',    'EXAMEN_ETAT',[['A','Système nerveux central (encéphale)',1],['B','Système nerveux périphérique uniquement',0],['C','Moelle épinière uniquement',0],['D','Système endocrinien',0]]],
  ['Le syndrome de Down (trisomie 21) résulte de :',                                      'EXPERT',    'EXAMEN_ETAT',[['A','La présence d\'un 3e chromosome 21',1],['B','La délétion du chromosome 21',0],['C','Une mutation du gène BRCA',0],['D','Une infection virale',0]]],
  ['Quel type d\'immunité est transmis par la mère au fœtus via le placenta ?',           'AVANCE',    'EXAMEN_ETAT',[['A','Immunité passive (anticorps maternels)',1],['B','Immunité active',0],['C','Immunité cellulaire',0],['D','Immunité innée',0]]],
  ['Le glucagon est sécrété par le pancréas pour :',                                      'AVANCE',    'EXAMEN_ETAT',[['A','Augmenter la glycémie (glycogénolyse hépatique)',1],['B','Baisser la glycémie',0],['C','Stimuler la digestion',0],['D','Réguler la pression artérielle',0]]],
  ['La courbe de croissance humaine est caractérisée par deux pics : à la naissance et :','INTERMEDIAIRE','TENASOSP',[['A','À la puberté',1],['B','À 5 ans',0],    ['C','À 20 ans',0],  ['D','À 30 ans',0]]],
  ['Le groupe sanguin AB est dit "receveur universel" car :',                              'INTERMEDIAIRE','EXAMEN_ETAT',[['A','Son plasma ne contient ni anticorps anti-A ni anti-B',1],['B','Ses hématies n\'ont pas d\'antigènes',0],['C','Il est le plus répandu',0],['D','Il peut donner à tous',0]]],
  ['La production de gamètes (ovules/spermatozoïdes) implique la :',                      'INTERMEDIAIRE','TENASOSP',[['A','Méiose',1],       ['B','Mitose',0],    ['C','Amitose',0],  ['D','Cytodiérèse',0]]],
  ['Quel est le rôle de la myéline autour des axones ?',                                  'AVANCE',    'EXAMEN_ETAT',[['A','Accélérer la conduction nerveuse',1],['B','Protéger le neurone des virus',0],['C','Produire les neurotransmetteurs',0],['D','Stocker l\'énergie',0]]],
];
foreach ($qs_bio_corps as [$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$mat,$e,$d,$s,$o); $total_q++; }

/* ═══════════════════════════════════════════
   HISTOIRE-GÉO — PACK RDC & Afrique approfond.
═══════════════════════════════════════════ */
$mat = $matiereMap['histgeo'];
$qs_hg_rdc = [
  ['Quel est le nom du premier gouverneur général du Congo belge ?',                      'AVANCE',    'EXAMEN_ETAT',[['A','Théophile Wahis',1],['B','Léopold Ier',0],['C','Henri Morton Stanley',0],['D','Albert Ier',0]]],
  ['La RDC est traversée par la ligne de l\'équateur au niveau de :',                    'ELEMENTAIRE','ENAFEP',[['A','La province de l\'Équateur et Kisangani',1],['B','Kinshasa',0],['C','Lubumbashi',0],['D','Goma',0]]],
  ['Quel est le nom du barrage hydroélectrique le plus puissant de la RDC ?',            'ELEMENTAIRE','ENAFEP',[['A','Inga',1],          ['B','Kariba',0],    ['C','Cahora Bassa',0],['D','Akosombo',0]]],
  ['La guerre de l\'AFDL (1996-1997) a mené à :',                                        'INTERMEDIAIRE','EXAMEN_ETAT',[['A','La chute de Mobutu et l\'arrivée de L.-D. Kabila',1],['B','La partition du Congo',0],['C','L\'indépendance du Katanga',0],['D','L\'élection de Tshisekedi',0]]],
  ['La province du Nord-Kivu est connue pour ses :',                                     'ELEMENTAIRE','ENAFEP',[['A','Volcans (Nyiragongo), minerais et conflits armés',1],['B','Déserts',0],['C','Pêcheries maritimes',0],['D','Cultures de blé',0]]],
  ['Le parc national de la Garamba abrite :',                                              'INTERMEDIAIRE','ENAFEP',[['A','Le rhinocéros blanc du Nord (espèce en danger critique)',1],['B','Les gorilles',0],['C','Les éléphants de mer',0],['D','Les lions de mer',0]]],
  ['Le fleuve Congo est le _____ plus profond du monde.',                                  'INTERMEDIAIRE','ENAFEP',[['A','Plus profond (270 m max)',1],['B','Plus long',0],['C','Plus large',0],['D','Plus rapide en débit',0]]],
  ['Quelle est la principale langue nationale de la RDC parmi les quatre officielles ?', 'DEBUTANT',  'ENAFEP',[['A','Le lingala (à Kinshasa et dans l\'ouest)',1],['B','Le français',0],['C','L\'anglais',0],['D','Le swahili uniquement',0]]],
  ['L\'Accord-Cadre d\'Addis-Abeba (2013) visait à :',                                   'AVANCE',    'EXAMEN_ETAT',[['A','Stabiliser l\'est de la RDC et réformer les groupes armés',1],['B','Créer une union douanière',0],['C','Organiser des élections panafricaines',0],['D','Délimiter les frontières de la RDC',0]]],
  ['La ville de Kolwezi est connue pour l\'extraction de :',                               'ELEMENTAIRE','ENAFEP',[['A','Cobalt et cuivre',1],['B','Diamants',0],['C','Pétrole',0],['D','Or uniquement',0]]],
  ['Le Nil Blanc prend sa source au :',                                                    'INTERMEDIAIRE','ENAFEP',[['A','Lac Victoria (Ouganda)',1],['B','Lac Tanganyika',0],['C','Mont Kilimandjaro',0],['D','Lac Tchad',0]]],
  ['La CEPGL est une organisation régionale regroupant :',                                 'INTERMEDIAIRE','ENAFEP',[['A','RDC, Rwanda, Burundi',1],['B','RDC, Congo-Brazzaville, Gabon',0],['C','Kenya, Tanzanie, Ouganda',0],['D','RDC, Angola, Zambie',0]]],
  ['Le principal exportateur mondial de cobalt est :',                                    'INTERMEDIAIRE','EXAMEN_ETAT',[['A','La RDC (plus de 70% de la production mondiale)',1],['B','La Chine',0],['C','L\'Australie',0],['D','Le Canada',0]]],
  ['La ville historique de Mbanza-Kongo était la capitale de :',                          'AVANCE',    'EXAMEN_ETAT',[['A','L\'Ancien royaume du Kongo',1],['B','L\'Empire du Mali',0],['C','Le Royaume Luba',0],['D','L\'Empire du Monomotapa',0]]],
  ['Quelle est la religion majoritaire en RDC ?',                                          'DEBUTANT',  'ENAFEP',[['A','Christianisme',1], ['B','Islam',0],     ['C','Animisme',0],  ['D','Bouddhisme',0]]],
];
foreach ($qs_hg_rdc as [$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$mat,$e,$d,$s,$o); $total_q++; }

/* ═══════════════════════════════════════════
   ANGLAIS — PACK vocabulaire & grammaire avancée
═══════════════════════════════════════════ */
$mat = $matiereMap['anglais'];
$qs_en_vocab = [
  ['What is the meaning of "ameliorate" ?',                                                'AVANCE',    'EXAMEN_ETAT',[['A','To improve or make better',1],['B','To worsen',0],   ['C','To destroy',0],   ['D','To duplicate',0]]],
  ['Choose the correct sentence with "lay" vs "lie" :',                                   'AVANCE',    'EXAMEN_ETAT',[['A','Please lie down and rest.',1],['B','Please lay down.',0],['C','I laid down on the bed. (present)',0],['D','She lays in bed. (past)',0]]],
  ['What does "albeit" mean ?',                                                             'EXPERT',    'EXAMEN_ETAT',[['A','Although',1],      ['B','Also',0],      ['C','Therefore',0],   ['D','Never',0]]],
  ['Identify the phrasal verb meaning "to postpone" in: "They put off the meeting."',     'ELEMENTAIRE','ENAFEP',[['A','put off',1],       ['B','put out',0],   ['C','put up',0],    ['D','put on',0]]],
  ['Which sentence correctly uses a relative clause ?',                                    'INTERMEDIAIRE','EXAMEN_ETAT',[['A','The student who works hard will succeed.',1],['B','The student that he works hard will succeed.',0],['C','The student works hard which will succeed.',0],['D','Who works hard the student will succeed.',0]]],
  ['The word "dilapidated" means:',                                                        'AVANCE',    'EXAMEN_ETAT',[['A','Falling apart, in a state of disrepair',1],['B','Very beautiful',0],['C','Newly built',0],   ['D','Expensive',0]]],
  ['What is "onomatopoeia" ?',                                                              'INTERMEDIAIRE','EXAMEN_ETAT',[['A','A word that imitates a sound (e.g. buzz, hiss)',1],['B','A comparison using "like"',0],['C','An exaggeration',0],['D','A play on words',0]]],
  ['Choose the correct form: "She is used to ___ early." (wake)',                         'INTERMEDIAIRE','EXAMEN_ETAT',[['A','waking',1],      ['B','wake',0],     ['C','woke',0],     ['D','to wake',0]]],
  ['What does "circumlocution" mean ?',                                                    'EXPERT',    'EXAMEN_ETAT',[['A','Using many words to express something that could be said briefly',1],['B','A direct statement',0],['C','A metaphor',0],['D','A concise summary',0]]],
  ['"Time flies" is an example of:',                                                       'INTERMEDIAIRE','EXAMEN_ETAT',[['A','Personification / Metaphor',1],['B','Simile',0],['C','Hyperbole',0],['D','Alliteration',0]]],
  ['Which preposition follows "depend"?',                                                   'ELEMENTAIRE','ENAFEP',[['A','on',1],            ['B','in',0],        ['C','at',0],       ['D','with',0]]],
  ['The prefix "un-" in "unlikely" means:',                                                'DEBUTANT',  'ENAFEP',[['A','not',1],           ['B','very',0],      ['C','before',0],   ['D','again',0]]],
  ['"She can swim, and so can he." This sentence uses:',                                   'INTERMEDIAIRE','EXAMEN_ETAT',[['A','Ellipsis (avoiding repetition)',1],['B','Parallelism',0],['C','Inversion',0],['D','A conditional',0]]],
  ['What is the correct order of adjectives before a noun ?',                              'AVANCE',    'EXAMEN_ETAT',[['A','Opinion-Size-Age-Shape-Colour-Origin-Material',1],['B','Colour-Size-Opinion',0],['C','Material-Age-Origin',0],['D','No fixed order',0]]],
  ['Identify the gerund phrase in: "Running every day keeps him fit."',                   'AVANCE',    'EXAMEN_ETAT',[['A','Running every day',1],['B','keeps him fit',0],['C','him fit',0],['D','every day keeps',0]]],
];
foreach ($qs_en_vocab as [$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$mat,$e,$d,$s,$o); $total_q++; }

/* ═══════════════════════════════════════════
   SCIENCES — PACK environnement / astronomie
═══════════════════════════════════════════ */
$mat = $matiereMap['sciences'];
$qs_sc_env = [
  ['Qu\'est-ce que la biodiversité ?',                                                     'ELEMENTAIRE','ENAFEP',[['A','La variété du vivant (gènes, espèces, écosystèmes)',1],['B','La diversité des minéraux',0],['C','La variété des paysages seulement',0],['D','L\'ensemble des fossiles',0]]],
  ['Le polluant principal des pluies acides est :',                                        'INTERMEDIAIRE','TENASOSP',[['A','SO₂ et NOₓ (dioxyde de soufre et oxydes d\'azote)',1],['B','CO₂',0],['C','CH₄',0],['D','O₃',0]]],
  ['Quelle est la principale cause de la destruction de la couche d\'ozone ?',            'ELEMENTAIRE','ENAFEP',[['A','Les CFC (chlorofluorocarbures)',1],['B','Le CO₂',0],   ['C','La vapeur d\'eau',0],['D','L\'azote',0]]],
  ['La distance Terre-Soleil est d\'environ :',                                            'ELEMENTAIRE','TENASOSP',[['A','150 millions de km (1 UA)',1],['B','1 milliard de km',0],['C','1 million de km',0],['D','300 000 km',0]]],
  ['La planète la plus grande du système solaire est :',                                   'DEBUTANT',  'ENAFEP',[['A','Jupiter',1],      ['B','Saturne',0],   ['C','Uranus',0],   ['D','Neptune',0]]],
  ['La durée de révolution de la Terre autour du Soleil est de :',                        'DEBUTANT',  'ENAFEP',[['A','365,25 jours',1],['B','24 heures',0],  ['C','30 jours',0], ['D','366 jours exactement',0]]],
  ['La gravité sur la Lune est environ ___ fois celle de la Terre.',                      'ELEMENTAIRE','ENAFEP',[['A','1/6',1],          ['B','1/3',0],       ['C','1/2',0],      ['D','Identique',0]]],
  ['Qu\'est-ce qu\'une éclipse solaire ?',                                                 'ELEMENTAIRE','ENAFEP',[['A','La Lune passe devant le Soleil et cache la lumière',1],['B','La Terre passe devant le Soleil',0],['C','Le Soleil s\'éteint temporairement',0],['D','La Terre entre dans l\'ombre de la Lune',0]]],
  ['Le recyclage des déchets contribue à :',                                               'DEBUTANT',  'ENAFEP',[['A','Réduire la pollution et économiser les ressources',1],['B','Augmenter les déchets',0],['C','Consommer plus d\'énergie',0],['D','Créer plus de CO₂',0]]],
  ['Quel est le rôle principal des forêts pour la planète ?',                             'ELEMENTAIRE','ENAFEP',[['A','Absorber le CO₂, produire de l\'O₂, régulation du climat',1],['B','Produire du pétrole',0],['C','Stocker l\'eau des mers',0],['D','Filtrer les ultraviolets',0]]],
  ['La photodégradation des plastiques produit des :',                                    'AVANCE',    'EXAMEN_ETAT',[['A','Microplastiques',1],['B','Polymères',0],  ['C','Minéraux',0],   ['D','Acides aminés',0]]],
  ['Quel est le nom de la théorie sur l\'origine de l\'univers ?',                        'ELEMENTAIRE','TENASOSP',[['A','Le Big Bang',1],  ['B','La théorie du chaos',0],['C','La théorie de la relativité',0],['D','La théorie de l\'état stationnaire',0]]],
  ['L\'eau douce ne représente que ___ de toute l\'eau sur Terre.',                       'ELEMENTAIRE','ENAFEP',[['A','3%',1],           ['B','30%',0],       ['C','10%',0],      ['D','50%',0]]],
  ['Un écosystème aquatique est menacé par l\'eutrophisation quand :',                    'AVANCE',    'EXAMEN_ETAT',[['A','L\'excès de nitrates et phosphates provoque une prolifération d\'algues',1],['B','La température de l\'eau monte légèrement',0],['C','La salinité augmente',0],['D','Le pH devient acide',0]]],
  ['L\'énergie géothermique provient de :',                                                'ELEMENTAIRE','TENASOSP',[['A','La chaleur interne de la Terre',1],['B','L\'énergie solaire stockée dans le sol',0],['C','La combustion des minéraux',0],['D','Les marées',0]]],
];
foreach ($qs_sc_env as [$e,$d,$s,$o]) { insert_question($pdo,$stQ,$stO,$mat,$e,$d,$s,$o); $total_q++; }

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
