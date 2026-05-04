<?php
/**
 * seed_questionnaires.php
 * Crée de vrais questionnaires liés aux archives (question_bank + question_options)
 * Inspiré du programme EPSP RDC — questions originales
 * Usage: php seed_questionnaires.php  (ou via navigateur localhost)
 */

if (php_sapi_name() !== 'cli' && ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1') {
    http_response_code(403); exit('Accès interdit.');
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$pdo = db();
$pdo->exec("SET NAMES utf8mb4");

// ── IDs matières ──────────────────────────────────────────
$M = [
    'maths'    => '0d8077c4-47bd-11f1-84f5-f0d5bfbb3a4f',
    'francais' => '0d80fad3-47bd-11f1-84f5-f0d5bfbb3a4f',
    'sciences' => '0d818604-47bd-11f1-84f5-f0d5bfbb3a4f',
    'histgeo'  => '0d81f29b-47bd-11f1-84f5-f0d5bfbb3a4f',
    'chimie'   => '0d82633d-47bd-11f1-84f5-f0d5bfbb3a4f',
    'physique' => '0d829f20-47bd-11f1-84f5-f0d5bfbb3a4f',
    'biologie' => '0d82d977-47bd-11f1-84f5-f0d5bfbb3a4f',
    'anglais'  => '0d8313f0-47bd-11f1-84f5-f0d5bfbb3a4f',
];

// ── Compteur global ───────────────────────────────────────
$inserted = 0;
$skipped  = 0;

// ── Helpers ───────────────────────────────────────────────
function insertQ(PDO $pdo, string $matiereId, string $examType, int $annee,
                 string $enonce, array $opts, string $diff = 'INTERMEDIAIRE',
                 float $pts = 1.0, ?string $objectif = null): void {
    global $inserted, $skipped;

    // Éviter doublons sur enoncé + type + année
    $exists = $pdo->prepare("SELECT id FROM question_bank WHERE enonce=? AND exam_type=? AND annee_source=?");
    $exists->execute([$enonce, $examType, $annee]);
    if ($exists->fetch()) { $skipped++; return; }

    $qId = strtolower(sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0,0xffff),mt_rand(0,0xffff),mt_rand(0,0xffff),
        mt_rand(0,0x0fff)|0x4000,mt_rand(0,0x3fff)|0x8000,
        mt_rand(0,0xffff),mt_rand(0,0xffff),mt_rand(0,0xffff)));

    $stQ = $pdo->prepare(
        "INSERT INTO question_bank (id,matiere_id,exam_type,annee_source,enonce,difficulte,points,objectif,status,type_question)
         VALUES (?,?,?,?,?,?,?,?,'PUBLIE','QCM')"
    );
    $stQ->execute([$qId, $matiereId, $examType, $annee, $enonce, $diff, $pts, $objectif]);

    $stO = $pdo->prepare(
        "INSERT INTO question_options (id,question_id,lettre,texte,est_correcte,explication,ordre)
         VALUES (UUID(),?,?,?,?,?,?)"
    );
    foreach ($opts as $i => $opt) {
        [$lettre, $texte, $correct] = $opt;
        $expl  = $opt[3] ?? null;
        $ordre = $i + 1;
        $stO->execute([$qId, $lettre, $texte, (int)$correct, $expl, $ordre]);
    }
    $inserted++;
}

// ══════════════════════════════════════════════════════════
// ██  ENAFEP — FIN DE PRIMAIRE (6ème année)
// ══════════════════════════════════════════════════════════

// ── ENAFEP Mathématiques ──────────────────────────────────
$enafepMaths = [
    // 2024
    [2024,'Quelle est la valeur de 5² + 3² ?',[
        ['A','25',false],['B','34',true,'5²=25 et 3²=9, donc 25+9=34.'],['C','28',false],['D','40',false]],'ELEMENTAIRE',1.0],
    [2024,'Un rectangle a une longueur de 12 cm et une largeur de 7 cm. Quelle est son aire ?',[
        ['A','38 cm²',false],['B','84 cm²',true,'Aire = longueur × largeur = 12×7 = 84 cm².'],['C','19 cm²',false],['D','96 cm²',false]],'ELEMENTAIRE',1.0],
    [2024,'Combien y a-t-il de minutes dans 2 heures et demie ?',[
        ['A','120 min',false],['B','130 min',false],['C','150 min',true,'1h=60min, 2h=120min, +30min=150min.'],['D','160 min',false]],'ELEMENTAIRE',1.0],
    [2024,'Quel est le plus grand commun diviseur (PGCD) de 24 et 36 ?',[
        ['A','6',false],['B','12',true,'Diviseurs de 24 : 1,2,3,4,6,8,12,24. Diviseurs de 36 : 1,2,3,4,6,9,12,18,36. PGCD = 12.'],['C','4',false],['D','18',false]],'INTERMEDIAIRE',1.0],
    [2024,'Un robinet remplit un réservoir de 120 litres en 4 heures. Combien de litres remplit-il par heure ?',[
        ['A','20 L',false],['B','24 L',false],['C','30 L',true,'120 ÷ 4 = 30 litres par heure.'],['D','40 L',false]],'ELEMENTAIRE',1.0],
    [2024,'Quelle fraction est égale à 0,75 ?',[
        ['A','1/4',false],['B','3/5',false],['C','3/4',true,'0,75 = 75/100 = 3/4.'],['D','2/3',false]],'ELEMENTAIRE',1.0],
    [2024,'Un commerçant achète un article à 5 000 FC et le vend à 6 500 FC. Quel est son bénéfice ?',[
        ['A','1 000 FC',false],['B','1 500 FC',true,'Bénéfice = prix de vente − prix d\'achat = 6 500 − 5 000 = 1 500 FC.'],['C','2 000 FC',false],['D','500 FC',false]],'ELEMENTAIRE',1.0],
    [2024,'Quel est le périmètre d\'un carré de côté 9 cm ?',[
        ['A','18 cm',false],['B','27 cm',false],['C','36 cm',true,'Périmètre d\'un carré = 4 × côté = 4 × 9 = 36 cm.'],['D','81 cm',false]],'ELEMENTAIRE',1.0],
    [2024,'Quel nombre complète la suite : 2, 6, 18, 54, ... ?',[
        ['A','108',false],['B','162',true,'Chaque terme est multiplié par 3 : 54×3=162.'],['C','144',false],['D','216',false]],'INTERMEDIAIRE',1.0],
    [2024,'Un élève obtient 85 points sur 100 à un test. Quel est son pourcentage ?',[
        ['A','80%',false],['B','82%',false],['C','85%',true,'Pourcentage = (points obtenus ÷ points totaux) × 100 = (85÷100)×100 = 85%.'],['D','90%',false]],'ELEMENTAIRE',1.0],

    // 2023
    [2023,'Quel est le double de 247 ?',[
        ['A','449',false],['B','494',true,'Double = 247×2 = 494.'],['C','474',false],['D','484',false]],'ELEMENTAIRE',1.0],
    [2023,'Un triangle équilatéral a un périmètre de 39 cm. Quelle est la longueur d\'un côté ?',[
        ['A','11 cm',false],['B','12 cm',false],['C','13 cm',true,'Périmètre ÷ 3 = 39 ÷ 3 = 13 cm.'],['D','15 cm',false]],'ELEMENTAIRE',1.0],
    [2023,'Combien y a-t-il de secondes dans une heure ?',[
        ['A','600 s',false],['B','3 600 s',true,'1h = 60 min × 60 s = 3 600 secondes.'],['C','1 200 s',false],['D','7 200 s',false]],'ELEMENTAIRE',1.0],
    [2023,'Quelle est la valeur de 8 × 7 − 4 × 6 ?',[
        ['A','28',false],['B','32',true,'8×7=56, 4×6=24, 56−24=32.'],['C','40',false],['D','36',false]],'INTERMEDIAIRE',1.0],
    [2023,'Un père a 42 ans et son fils a 14 ans. Dans combien d\'années le père aura-t-il le double de l\'âge du fils ?',[
        ['A','12 ans',false],['B','14 ans',true,'Père: 42+x, Fils: 14+x. 42+x=2(14+x) → 42+x=28+2x → x=14.'],['C','16 ans',false],['D','10 ans',false]],'AVANCE',1.0],

    // 2022
    [2022,'Quel est le résultat de 1 000 − 378 ?',[
        ['A','628',false],['B','622',true,'1 000 − 378 = 622.'],['C','632',false],['D','612',false]],'ELEMENTAIRE',1.0],
    [2022,'Un terrain rectangulaire mesure 50 m × 30 m. Quelle est sa superficie en ares ?',[
        ['A','10 ares',false],['B','15 ares',true,'Superficie = 50×30 = 1 500 m². 1 are = 100 m², donc 1 500 ÷ 100 = 15 ares.'],['C','150 ares',false],['D','1,5 ares',false]],'INTERMEDIAIRE',1.0],
    [2022,'Quelle est la valeur de 4/5 de 200 ?',[
        ['A','100',false],['B','150',false],['C','160',true,'4/5 × 200 = (4×200)÷5 = 800÷5 = 160.'],['D','180',false]],'ELEMENTAIRE',1.0],

    // 2021
    [2021,'Un train parcourt 480 km en 4 heures. Quelle est sa vitesse moyenne ?',[
        ['A','100 km/h',false],['B','120 km/h',true,'Vitesse = distance ÷ temps = 480÷4 = 120 km/h.'],['C','140 km/h',false],['D','110 km/h',false]],'ELEMENTAIRE',1.0],
    [2021,'Quel est le plus petit commun multiple (PPCM) de 4 et 6 ?',[
        ['A','12',true,'Multiples de 4 : 4,8,12... Multiples de 6 : 6,12... PPCM = 12.'],['B','24',false],['C','8',false],['D','6',false]],'INTERMEDIAIRE',1.0],
    [2021,'Si un article coûte 3 500 FC et que tu paies avec 5 000 FC, quelle monnaie recevras-tu ?',[
        ['A','1 000 FC',false],['B','1 500 FC',true,'Monnaie = 5 000 − 3 500 = 1 500 FC.'],['C','2 000 FC',false],['D','500 FC',false]],'ELEMENTAIRE',1.0],
];

foreach ($enafepMaths as [$an, $enonce, $opts, $diff, $pts]) {
    insertQ($pdo, $M['maths'], 'ENAFEP', $an, $enonce, $opts, $diff, $pts, 'Mathématiques ENAFEP');
}

// ── ENAFEP Français ───────────────────────────────────────
$enafepFr = [
    [2024,'Quel est le pluriel du mot « bal » ?',[
        ['A','bals',true,'Le mot « bal » fait « bals » au pluriel (pluriel régulier avec s).'],['B','baux',false],['C','bales',false],['D','bal',false]],'ELEMENTAIRE',1.0],
    [2024,'Quelle est la nature du mot souligné dans : « Il court rapidement. » (rapidement)',[
        ['A','adjectif',false],['B','adverbe',true,'« Rapidement » modifie le verbe « court » — c\'est un adverbe de manière.'],['C','nom',false],['D','pronom',false]],'ELEMENTAIRE',1.0],
    [2024,'Trouve le synonyme du mot « courageux ».',[
        ['A','lâche',false],['B','vaillant',true,'« Vaillant » est synonyme de courageux — les deux signifient brave, intrépide.'],['C','timide',false],['D','paresseux',false]],'ELEMENTAIRE',1.0],
    [2024,'Quelle est la forme passive de : « Le chat mange la souris. » ?',[
        ['A','La souris mange le chat.',false],['B','La souris est mangée par le chat.',true,'Forme passive : sujet passif + auxiliaire être + participe passé + « par » + agent.'],['C','La souris a mangé le chat.',false],['D','Le chat est mangé par la souris.',false]],'INTERMEDIAIRE',1.0],
    [2024,'Quel temps est utilisé dans : « Demain, je partirai à Kinshasa. » ?',[
        ['A','Présent',false],['B','Imparfait',false],['C','Futur simple',true,'« Partirai » est conjugué au futur simple de l\'indicatif.'],['D','Conditionnel',false]],'ELEMENTAIRE',1.0],
    [2024,'Quel est l\'antonyme du mot « genereux » ?',[
        ['A','avare',true,'« Avare » est le contraire de généreux.'],['B','riche',false],['C','doux',false],['D','grand',false]],'ELEMENTAIRE',1.0],
    [2023,'Complète avec le bon article : « ___ eau est fraîche. »',[
        ['A','Le',false],['B','La',false],['C','L\'',true,'Devant un mot commençant par une voyelle, on utilise l\'article élidé L\'.'],['D','Les',false]],'ELEMENTAIRE',1.0],
    [2023,'Dans la phrase « Le professeur corrige les cahiers », quel est le COD ?',[
        ['A','le professeur',false],['B','corrige',false],['C','les cahiers',true,'« Les cahiers » répond à la question « corrige quoi ? » — c\'est le COD.'],['D','le',false]],'ELEMENTAIRE',1.0],
    [2023,'Quel est le féminin du mot « instituteur » ?',[
        ['A','institutrice',true,'Le féminin de instituteur est institutrice.'],['B','instituteuse',false],['C','instituteresse',false],['D','instituteure',false]],'ELEMENTAIRE',1.0],
    [2022,'Comment dit-on « chien » au féminin ?',[
        ['A','chiène',false],['B','chienne',true,'Le féminin de chien est chienne.'],['C','chienette',false],['D','chien',false]],'ELEMENTAIRE',1.0],
    [2022,'Quelle phrase est à la forme négative ?',[
        ['A','Marie chante bien.',false],['B','Il fait beau.',false],['C','Je ne mange pas de viande.',true,'La forme négative utilise « ne...pas ».'],['D','Nous allons à l\'école.',false]],'ELEMENTAIRE',1.0],
    [2021,'Quel est le participe passé du verbe « écrire » ?',[
        ['A','écrit',true,'Le participe passé de « écrire » est « écrit ».'],['B','écrivé',false],['C','écrivant',false],['D','écris',false]],'ELEMENTAIRE',1.0],
];

foreach ($enafepFr as [$an, $enonce, $opts, $diff, $pts]) {
    insertQ($pdo, $M['francais'], 'ENAFEP', $an, $enonce, $opts, $diff, $pts, 'Français ENAFEP');
}

// ── ENAFEP Sciences Naturelles ────────────────────────────
$enafepSci = [
    [2024,'Quel organe du corps humain pompe le sang ?',[
        ['A','Le foie',false],['B','Le cœur',true,'Le cœur est le muscle qui pompe le sang dans tout le corps.'],['C','Les poumons',false],['D','Le cerveau',false]],'ELEMENTAIRE',1.0],
    [2024,'Quelle planète est la plus proche du Soleil ?',[
        ['A','Vénus',false],['B','Mercure',true,'Mercure est la première planète du système solaire, la plus proche du Soleil.'],['C','Mars',false],['D','Terre',false]],'ELEMENTAIRE',1.0],
    [2024,'Comment s\'appelle le processus par lequel les plantes fabriquent leur nourriture ?',[
        ['A','Respiration',false],['B','Digestion',false],['C','Photosynthèse',true,'La photosynthèse est la fabrication de glucose par les plantes grâce à la lumière solaire et au CO₂.'],['D','Transpiration',false]],'ELEMENTAIRE',1.0],
    [2024,'Quelle est la température d\'ébullition de l\'eau à pression normale ?',[
        ['A','80°C',false],['B','90°C',false],['C','100°C',true,'L\'eau bout à 100°C (ou 212°F) à pression atmosphérique normale.'],['D','120°C',false]],'ELEMENTAIRE',1.0],
    [2024,'Quel gaz les humains inspirent-ils pour vivre ?',[
        ['A','CO₂',false],['B','Azote',false],['C','Oxygène',true,'Les humains inspirent l\'oxygène (O₂) et expirent le dioxyde de carbone (CO₂).'],['D','Hydrogène',false]],'ELEMENTAIRE',1.0],
    [2023,'Combien d\'os y a-t-il dans le corps humain adulte ?',[
        ['A','106',false],['B','206',true,'Le squelette humain adulte est composé de 206 os.'],['C','306',false],['D','156',false]],'INTERMEDIAIRE',1.0],
    [2023,'Quel animal est le plus grand mammifère terrestre ?',[
        ['A','Rhinocéros',false],['B','Hippopotame',false],['C','Éléphant d\'Afrique',true,'L\'éléphant d\'Afrique est le plus grand animal terrestre, pouvant peser jusqu\'à 7 tonnes.'],['D','Girafe',false]],'ELEMENTAIRE',1.0],
    [2022,'Quelle est la formule chimique de l\'eau ?',[
        ['A','CO₂',false],['B','NaCl',false],['C','H₂O',true,'L\'eau est composée de deux atomes d\'hydrogène (H) et un atome d\'oxygène (O).'],['D','O₂',false]],'ELEMENTAIRE',1.0],
    [2022,'D\'où vient principalement l\'énergie du Soleil ?',[
        ['A','Combustion du charbon',false],['B','Fusion nucléaire',true,'Le Soleil produit son énergie par fusion nucléaire : des atomes d\'hydrogène fusionnent pour former de l\'hélium.'],['C','Fission nucléaire',false],['D','Énergie géothermique',false]],'AVANCE',1.0],
    [2021,'Quel est le rôle principal du cerveau ?',[
        ['A','Pomper le sang',false],['B','Filtrer l\'urine',false],['C','Contrôler le corps et la pensée',true,'Le cerveau est le centre de commande du corps humain — il contrôle les mouvements, les sens et la pensée.'],['D','Digérer les aliments',false]],'ELEMENTAIRE',1.0],
];

foreach ($enafepSci as [$an, $enonce, $opts, $diff, $pts]) {
    insertQ($pdo, $M['sciences'], 'ENAFEP', $an, $enonce, $opts, $diff, $pts, 'Sciences Naturelles ENAFEP');
}

// ── ENAFEP Histoire-Géographie ────────────────────────────
$enafepHG = [
    [2024,'Quelle est la capitale de la République Démocratique du Congo ?',[
        ['A','Brazzaville',false],['B','Lubumbashi',false],['C','Kinshasa',true,'Kinshasa est la capitale et la plus grande ville de la RDC, anciennement appelée Léopoldville.'],['D','Kisangani',false]],'ELEMENTAIRE',1.0],
    [2024,'Quel est le plus grand pays d\'Afrique par sa superficie ?',[
        ['A','RDC',false],['B','Soudan',false],['C','Algérie',true,'L\'Algérie est le plus grand pays d\'Afrique avec 2,38 millions de km².'],['D','Nigeria',false]],'ELEMENTAIRE',1.0],
    [2024,'Quel fleuve traverse la ville de Kinshasa ?',[
        ['A','Nil',false],['B','Congo',true,'Le fleuve Congo traverse Kinshasa. C\'est le deuxième plus long fleuve d\'Afrique et le plus profond du monde.'],['C','Niger',false],['D','Zambèze',false]],'ELEMENTAIRE',1.0],
    [2024,'En quelle année la RDC a-t-elle obtenu son indépendance ?',[
        ['A','1958',false],['B','1960',true,'La RDC (alors Congo-Kinshasa) a obtenu son indépendance de la Belgique le 30 juin 1960.'],['C','1962',false],['D','1965',false]],'ELEMENTAIRE',1.0],
    [2024,'Qui était le premier Premier Ministre de la RDC après l\'indépendance ?',[
        ['A','Joseph Kasavubu',false],['B','Mobutu Sese Seko',false],['C','Patrice Lumumba',true,'Patrice Lumumba fut le premier Premier Ministre de la RDC, après l\'indépendance du 30 juin 1960.'],['D','Moise Tshombe',false]],'ELEMENTAIRE',1.0],
    [2023,'Quel est le plus grand lac d\'Afrique ?',[
        ['A','Lac Tanganyika',false],['B','Lac Victoria',true,'Le lac Victoria est le plus grand lac d\'Afrique par sa superficie (environ 68 800 km²).'],['C','Lac Malawi',false],['D','Lac Kivu',false]],'ELEMENTAIRE',1.0],
    [2023,'Combien de provinces compte la RDC depuis 2015 ?',[
        ['A','11',false],['B','26',true,'Depuis 2015, la RDC est divisée en 26 provinces plus la ville-province de Kinshasa.'],['C','10',false],['D','21',false]],'INTERMEDIAIRE',1.0],
    [2022,'Quel est le continent le plus chaud ?',[
        ['A','Asie',false],['B','Afrique',true,'L\'Afrique est le continent le plus chaud de la Terre, traversé par l\'équateur et les tropiques.'],['C','Amérique du Sud',false],['D','Océanie',false]],'ELEMENTAIRE',1.0],
    [2022,'Quelle mer sépare l\'Europe de l\'Afrique ?',[
        ['A','Mer Rouge',false],['B','Mer Noire',false],['C','Mer Méditerranée',true,'La Méditerranée sépare l\'Europe (au nord) de l\'Afrique (au sud).'],['D','Mer Caspienne',false]],'ELEMENTAIRE',1.0],
    [2021,'Quel est le symbole de la paix dans le monde ?',[
        ['A','L\'aigle',false],['B','La colombe blanche',true,'La colombe blanche est universellement reconnue comme le symbole de la paix.'],['C','Le lion',false],['D','Le drapeau rouge',false]],'ELEMENTAIRE',1.0],
];

foreach ($enafepHG as [$an, $enonce, $opts, $diff, $pts]) {
    insertQ($pdo, $M['histgeo'], 'ENAFEP', $an, $enonce, $opts, $diff, $pts, 'Histoire-Géo ENAFEP');
}

// ── ENAFEP Anglais ────────────────────────────────────────
$enafepAnglais = [
    [2024,'What is the plural of "child"?',[
        ['A','childs',false],['B','childes',false],['C','children',true,'"Child" has an irregular plural: children (not childs).'],['D','childrens',false]],'ELEMENTAIRE',1.0],
    [2024,'Choose the correct sentence.',[
        ['A','She go to school every day.',false],['B','She goes to school every day.',true,'Third person singular (she/he/it) takes an -s in simple present: goes.'],['C','She going to school every day.',false],['D','She goed to school every day.',false]],'ELEMENTAIRE',1.0],
    [2024,'What is the opposite of "hot"?',[
        ['A','warm',false],['B','cold',true,'"Hot" and "cold" are antonyms (opposites).'],['C','big',false],['D','tall',false]],'ELEMENTAIRE',1.0],
    [2024,'Which word means "happy"?',[
        ['A','sad',false],['B','angry',false],['C','joyful',true,'"Joyful" is a synonym of "happy".'],['D','tired',false]],'ELEMENTAIRE',1.0],
    [2023,'Complete: "I ___ my homework yesterday."',[
        ['A','do',false],['B','does',false],['C','did',true,'"Did" is the past tense of "do". Yesterday indicates simple past tense.'],['D','done',false]],'ELEMENTAIRE',1.0],
    [2023,'What does "beautiful" mean in French?',[
        ['A','laid',false],['B','beau/belle',true,'"Beautiful" means "beau" (masculine) or "belle" (feminine) in French.'],['C','grand',false],['D','petit',false]],'ELEMENTAIRE',1.0],
    [2022,'How do you say "bonjour" in English?',[
        ['A','Goodbye',false],['B','Good night',false],['C','Good morning / Hello',true,'"Bonjour" translates to "Good morning" or simply "Hello" in English.'],['D','Good evening',false]],'ELEMENTAIRE',1.0],
    [2022,'Choose the correct article: "___ apple a day keeps the doctor away."',[
        ['A','A',false],['B','An',true,'"An" is used before words starting with a vowel sound. "Apple" starts with a vowel "a".'],['C','The',false],['D','Some',false]],'ELEMENTAIRE',1.0],
    [2021,'What is the past tense of "go"?',[
        ['A','goed',false],['B','gone',false],['C','went',true,'"Went" is the simple past tense of the irregular verb "go".'],['D','going',false]],'ELEMENTAIRE',1.0],
];

foreach ($enafepAnglais as [$an, $enonce, $opts, $diff, $pts]) {
    insertQ($pdo, $M['anglais'], 'ENAFEP', $an, $enonce, $opts, $diff, $pts, 'Anglais ENAFEP');
}

// ══════════════════════════════════════════════════════════
// ██  TENASOSP — FIN DE SECONDAIRE (Humanités)
// ══════════════════════════════════════════════════════════

// ── TENASOSP Mathématiques ────────────────────────────────
$tenasospMaths = [
    [2024,'Résoudre l\'équation : 3x + 7 = 22',[
        ['A','x = 4',false],['B','x = 5',true,'3x = 22−7 = 15, donc x = 15÷3 = 5.'],['C','x = 6',false],['D','x = 3',false]],'ELEMENTAIRE',1.0],
    [2024,'Quelle est la valeur de sin(30°) ?',[
        ['A','√2/2',false],['B','√3/2',false],['C','1/2',true,'sin(30°) = 1/2. C\'est une valeur remarquable à mémoriser.'],['D','1',false]],'INTERMEDIAIRE',1.0],
    [2024,'Calculer la dérivée de f(x) = x³ − 5x + 2',[
        ['A','f\'(x) = 3x² + 5',false],['B','f\'(x) = 3x² − 5',true,'La dérivée de xⁿ est nxⁿ⁻¹. Dérivée de x³=3x², de -5x=-5, constante=0.'],['C','f\'(x) = x² − 5',false],['D','f\'(x) = 3x − 5',false]],'INTERMEDIAIRE',1.5],
    [2024,'Quel est le discriminant de x² − 6x + 9 = 0 ?',[
        ['A','27',false],['B','0',true,'Δ = b²−4ac = 36−4×1×9 = 36−36 = 0. Racine double : x=3.'],['C','-9',false],['D','3',false]],'INTERMEDIAIRE',1.5],
    [2024,'Simplifier : (x²−4) ÷ (x−2)',[
        ['A','x−2',false],['B','x+2',true,'x²−4 = (x−2)(x+2). On divise par (x−2) → résultat : (x+2).'],['C','x²−2',false],['D','x+4',false]],'INTERMEDIAIRE',1.0],
    [2024,'Calculer : log₁₀(1000)',[
        ['A','2',false],['B','3',true,'log₁₀(1000) = log₁₀(10³) = 3.'],['C','10',false],['D','100',false]],'ELEMENTAIRE',1.0],
    [2024,'Quelle est l\'équation d\'une droite passant par (0,3) avec une pente de 2 ?',[
        ['A','y = 2x − 3',false],['B','y = 3x + 2',false],['C','y = 2x + 3',true,'Forme pente-ordonnée : y = mx + b. m=2, b=3 (ordonnée à l\'origine). Donc y = 2x + 3.'],['D','y = x + 3',false]],'ELEMENTAIRE',1.0],
    [2023,'Calculer l\'intégrale ∫ 4x dx',[
        ['A','4x² + C',false],['B','2x² + C',true,'∫ 4x dx = 4(x²/2) + C = 2x² + C.'],['C','4x + C',false],['D','x² + C',false]],'INTERMEDIAIRE',1.5],
    [2023,'Dans un triangle rectangle, si les deux cathètes mesurent 3 et 4, quelle est l\'hypoténuse ?',[
        ['A','6',false],['B','5',true,'Théorème de Pythagore : c² = 3²+4² = 9+16 = 25, c = 5.'],['C','7',false],['D','25',false]],'ELEMENTAIRE',1.0],
    [2023,'Quel est le résultat de 2³ × 2⁴ ?',[
        ['A','2⁷',true,'aᵐ × aⁿ = aᵐ⁺ⁿ, donc 2³×2⁴ = 2³⁺⁴ = 2⁷ = 128.'],['B','4⁷',false],['C','2¹²',false],['D','2⁶',false]],'ELEMENTAIRE',1.0],
    [2022,'Un capital de 200 000 FC est placé à 5% par an pendant 3 ans. Quel est l\'intérêt simple ?',[
        ['A','10 000 FC',false],['B','30 000 FC',true,'Intérêt simple = Capital × taux × durée = 200 000 × 0,05 × 3 = 30 000 FC.'],['C','50 000 FC',false],['D','60 000 FC',false]],'INTERMEDIAIRE',1.0],
    [2022,'Résoudre le système : x + y = 7 et x − y = 3',[
        ['A','x=3, y=4',false],['B','x=5, y=2',true,'Addition : 2x=10 → x=5. Puis y=7−5=2.'],['C','x=4, y=3',false],['D','x=6, y=1',false]],'INTERMEDIAIRE',1.5],
];

foreach ($tenasospMaths as [$an, $enonce, $opts, $diff, $pts]) {
    insertQ($pdo, $M['maths'], 'TENASOSP', $an, $enonce, $opts, $diff, $pts, 'Mathématiques TENASOSP');
}

// ── TENASOSP Chimie ───────────────────────────────────────
$tenasospChimie = [
    [2024,'Quelle est la masse molaire de l\'eau (H₂O) ?',[
        ['A','10 g/mol',false],['B','18 g/mol',true,'M(H₂O) = 2×M(H) + M(O) = 2×1 + 16 = 18 g/mol.'],['C','20 g/mol',false],['D','16 g/mol',false]],'ELEMENTAIRE',1.0],
    [2024,'Quel type de liaison chimique implique le partage d\'électrons ?',[
        ['A','Liaison ionique',false],['B','Liaison covalente',true,'La liaison covalente = partage d\'électrons entre deux atomes non métalliques.'],['C','Liaison métallique',false],['D','Liaison hydrogène',false]],'ELEMENTAIRE',1.0],
    [2024,'Équilibrer : H₂ + O₂ → H₂O. Quelle est l\'équation équilibrée ?',[
        ['A','H₂ + O₂ → H₂O',false],['B','2H₂ + O₂ → 2H₂O',true,'Pour équilibrer : 4H à gauche (2×H₂), 4H à droite (2×H₂O), 2O à gauche (O₂), 2O à droite.'],['C','H₂ + 2O₂ → 2H₂O',false],['D','H₂ + O → H₂O',false]],'INTERMEDIAIRE',1.0],
    [2024,'Quel est le numéro atomique du carbone (C) ?',[
        ['A','6',true,'Le carbone a 6 protons, donc son numéro atomique Z=6.'],['B','12',false],['C','8',false],['D','14',false]],'ELEMENTAIRE',1.0],
    [2024,'Quel ion est formé lorsque le sodium perd un électron ?',[
        ['A','Na²⁺',false],['B','Na⁻',false],['C','Na⁺',true,'Le sodium (Na) a 11 électrons. En perdant 1, il devient Na⁺ (charge +1).'],['D','Na²⁻',false]],'ELEMENTAIRE',1.0],
    [2024,'Quelle est la concentration molaire si on dissout 2 mol de NaCl dans 4 L d\'eau ?',[
        ['A','0,25 mol/L',false],['B','0,5 mol/L',true,'C = n/V = 2 mol ÷ 4 L = 0,5 mol/L.'],['C','2 mol/L',false],['D','8 mol/L',false]],'INTERMEDIAIRE',1.0],
    [2023,'Qu\'est-ce qu\'une réaction d\'oxydoréduction ?',[
        ['A','Réaction entre un acide et une base',false],['B','Réaction avec transfert d\'électrons entre réactifs',true,'L\'oxydoréduction implique un transfert d\'électrons : un réducteur cède des électrons à un oxydant.'],['C','Réaction de précipitation',false],['D','Réaction de neutralisation',false]],'INTERMEDIAIRE',1.5],
    [2023,'Quel est le pH d\'une solution neutre à 25°C ?',[
        ['A','0',false],['B','7',true,'Le pH 7 correspond à la neutralité. pH < 7 = acide, pH > 7 = basique.'],['C','14',false],['D','10',false]],'ELEMENTAIRE',1.0],
    [2022,'Quelle est la formule du chlorure de sodium (sel de table) ?',[
        ['A','NaCl',true,'Le sel de table est le chlorure de sodium : Na⁺ et Cl⁻ s\'associent pour former NaCl.'],['B','KCl',false],['C','Na₂O',false],['D','NaOH',false]],'ELEMENTAIRE',1.0],
    [2022,'Dans une réaction endothermique, l\'énergie est...',[
        ['A','libérée',false],['B','absorbée',true,'Endothermique = la réaction absorbe de l\'énergie (chaleur). Exothermique = elle libère de l\'énergie.'],['C','nulle',false],['D','négative',false]],'INTERMEDIAIRE',1.0],
];

foreach ($tenasospChimie as [$an, $enonce, $opts, $diff, $pts]) {
    insertQ($pdo, $M['chimie'], 'TENASOSP', $an, $enonce, $opts, $diff, $pts, 'Chimie TENASOSP');
}

// ── TENASOSP Physique ─────────────────────────────────────
$tenasospPhysique = [
    [2024,'Quelle est l\'unité SI de la force ?',[
        ['A','Joule',false],['B','Pascal',false],['C','Newton',true,'La force se mesure en Newton (N). 1 N = 1 kg·m/s².'],['D','Watt',false]],'ELEMENTAIRE',1.0],
    [2024,'Énoncer la deuxième loi de Newton.',[
        ['A','F = mv',false],['B','F = ma',true,'La 2ème loi de Newton : la force = masse × accélération (F = ma).'],['C','F = m/a',false],['D','F = mv²',false]],'ELEMENTAIRE',1.0],
    [2024,'Un objet de masse 5 kg est soumis à une accélération de 3 m/s². Quelle est la force appliquée ?',[
        ['A','1,67 N',false],['B','8 N',false],['C','15 N',true,'F = ma = 5 × 3 = 15 N.'],['D','20 N',false]],'ELEMENTAIRE',1.0],
    [2024,'Quelle est la vitesse de la lumière dans le vide ?',[
        ['A','3 × 10⁶ m/s',false],['B','3 × 10⁸ m/s',true,'La vitesse de la lumière dans le vide est c = 3×10⁸ m/s (environ 300 000 km/s).'],['C','3 × 10¹⁰ m/s',false],['D','3 × 10⁴ m/s',false]],'ELEMENTAIRE',1.0],
    [2024,'Dans un circuit en série, la résistance totale de deux résistances R₁=10Ω et R₂=20Ω est :',[
        ['A','10 Ω',false],['B','30 Ω',true,'En série : R_total = R₁ + R₂ = 10 + 20 = 30 Ω.'],['C','6,7 Ω',false],['D','200 Ω',false]],'ELEMENTAIRE',1.0],
    [2024,'Quelle est la loi d\'Ohm ?',[
        ['A','U = R/I',false],['B','U = IR',true,'Loi d\'Ohm : U = RI (tension = résistance × intensité).'],['C','I = UR',false],['D','R = U + I',false]],'ELEMENTAIRE',1.0],
    [2023,'Une voiture passe de 0 à 90 km/h en 10 secondes. Quelle est son accélération en m/s² ?',[
        ['A','2,5 m/s²',true,'90 km/h = 25 m/s. a = Δv/Δt = 25÷10 = 2,5 m/s².'],['B','9 m/s²',false],['C','90 m/s²',false],['D','1 m/s²',false]],'INTERMEDIAIRE',1.5],
    [2023,'Quelle est l\'énergie cinétique d\'un objet de masse 2 kg se déplaçant à 10 m/s ?',[
        ['A','20 J',false],['B','100 J',true,'Ec = ½mv² = ½×2×10² = ½×2×100 = 100 J.'],['C','200 J',false],['D','50 J',false]],'INTERMEDIAIRE',1.5],
    [2022,'Quel phénomène explique la formation d\'un arc-en-ciel ?',[
        ['A','Réflexion totale',false],['B','Dispersion de la lumière',true,'L\'arc-en-ciel est dû à la dispersion de la lumière blanche en ses composantes (rouge à violet) par les gouttes d\'eau.'],['C','Absorption lumineuse',false],['D','Diffraction',false]],'INTERMEDIAIRE',1.0],
    [2022,'L\'unité de mesure de la pression est :',[
        ['A','Newton',false],['B','Joule',false],['C','Pascal',true,'La pression se mesure en Pascal (Pa). 1 Pa = 1 N/m².'],['D','Watt',false]],'ELEMENTAIRE',1.0],
];

foreach ($tenasospPhysique as [$an, $enonce, $opts, $diff, $pts]) {
    insertQ($pdo, $M['physique'], 'TENASOSP', $an, $enonce, $opts, $diff, $pts, 'Physique TENASOSP');
}

// ── TENASOSP Biologie ─────────────────────────────────────
$tenasospBio = [
    [2024,'Quel est le rôle de l\'ADN dans la cellule ?',[
        ['A','Produire l\'énergie',false],['B','Porter l\'information génétique',true,'L\'ADN (acide désoxyribonucléique) contient le code génétique qui contrôle le développement et le fonctionnement des organismes.'],['C','Transporter l\'oxygène',false],['D','Synthétiser les lipides',false]],'ELEMENTAIRE',1.0],
    [2024,'Combien de chromosomes possède une cellule humaine normale ?',[
        ['A','23',false],['B','46',true,'Les cellules humaines somatiques contiennent 46 chromosomes (23 paires). Les gamètes en ont 23.'],['C','48',false],['D','92',false]],'ELEMENTAIRE',1.0],
    [2024,'Quel organite cellulaire est responsable de la synthèse des protéines ?',[
        ['A','Mitochondrie',false],['B','Ribosome',true,'Les ribosomes sont les sites de la synthèse des protéines. Ils lisent l\'ARNm pour assembler les acides aminés.'],['C','Noyau',false],['D','Vacuole',false]],'INTERMEDIAIRE',1.0],
    [2024,'Comment appelle-t-on la division cellulaire produisant deux cellules identiques ?',[
        ['A','Méiose',false],['B','Mitose',true,'La mitose produit deux cellules filles identiques à la cellule mère (même nombre de chromosomes). La méiose produit des gamètes.'],['C','Cytokinèse',false],['D','Transcription',false]],'ELEMENTAIRE',1.0],
    [2023,'Quel hormone régule la glycémie (taux de sucre dans le sang) ?',[
        ['A','Adrénaline',false],['B','Insuline',true,'L\'insuline, produite par le pancréas, fait baisser la glycémie en facilitant l\'entrée du glucose dans les cellules.'],['C','Thyroxine',false],['D','Cortisol',false]],'ELEMENTAIRE',1.0],
    [2023,'Comment s\'appelle la structure qui protège et nourrit l\'embryon chez les mammifères ?',[
        ['A','Amnios',false],['B','Placenta',true,'Le placenta relie l\'embryon à l\'utérus maternel et assure les échanges nutritifs et gazeux.'],['C','Chorion',false],['D','Vésicule vitelline',false]],'ELEMENTAIRE',1.0],
    [2022,'Quelle est la fonction des globules rouges ?',[
        ['A','Défense immunitaire',false],['B','Transport de l\'oxygène',true,'Les érythrocytes (globules rouges) contiennent l\'hémoglobine qui transporte l\'O₂ des poumons vers les tissus.'],['C','Coagulation',false],['D','Production d\'anticorps',false]],'ELEMENTAIRE',1.0],
    [2022,'Qu\'est-ce que la photosynthèse produit en plus du glucose ?',[
        ['A','CO₂',false],['B','Azote',false],['C','Oxygène (O₂)',true,'La photosynthèse : CO₂ + H₂O + lumière → glucose + O₂. L\'O₂ est un sous-produit essentiel à la vie.'],['D','Eau',false]],'ELEMENTAIRE',1.0],
    [2021,'Le VIH attaque principalement quel type de cellule ?',[
        ['A','Globules rouges',false],['B','Lymphocytes T CD4⁺',true,'Le VIH infecte et détruit les lymphocytes T CD4⁺, affaiblissant le système immunitaire et menant au SIDA.'],['C','Plaquettes',false],['D','Neurones',false]],'INTERMEDIAIRE',1.5],
];

foreach ($tenasospBio as [$an, $enonce, $opts, $diff, $pts]) {
    insertQ($pdo, $M['biologie'], 'TENASOSP', $an, $enonce, $opts, $diff, $pts, 'Biologie TENASOSP');
}

// ── TENASOSP Français ─────────────────────────────────────
$tenasospFr = [
    [2024,'Qu\'est-ce qu\'une métaphore ?',[
        ['A','Une comparaison avec « comme »',false],['B','Une figure de style sans outil de comparaison',true,'La métaphore affirme directement qu\'un objet est un autre : « La vie est un long fleuve tranquille ». La comparaison utilise « comme ».'],['C','Une répétition de sons',false],['D','Une exagération',false]],'INTERMEDIAIRE',1.0],
    [2024,'Conjuguer « finir » à la 1ère personne du pluriel du subjonctif présent.',[
        ['A','nous finissons',false],['B','nous finissions',false],['C','que nous finissions',true,'Subjonctif présent de « finir » : que je finisse, que tu finisses, qu\'il finisse, que nous finissions...'],['D','que nous finirons',false]],'INTERMEDIAIRE',1.5],
    [2024,'Quelle est la fonction de « rapidement » dans : « Il court rapidement » ?',[
        ['A','Complément d\'objet direct',false],['B','Complément circonstanciel de manière',true,'« Rapidement » indique la façon dont il court — c\'est un CCM (complément circonstanciel de manière).'],['C','Attribut du sujet',false],['D','Complément du nom',false]],'INTERMEDIAIRE',1.0],
    [2023,'Quel est le mode verbal de : « Prends ton livre ! »',[
        ['A','Indicatif',false],['B','Subjonctif',false],['C','Impératif',true,'L\'impératif exprime un ordre, un conseil ou une interdiction. Ici : ordre direct.'],['D','Conditionnel',false]],'ELEMENTAIRE',1.0],
    [2023,'Définir le terme « hyperbole ».',[
        ['A','Figure d\'insistance par la répétition',false],['B','Figure d\'exagération pour renforcer l\'effet',true,'L\'hyperbole est une exagération stylistique : « J\'ai attendu une éternité. »'],['C','Comparaison sans outil',false],['D','Personnification d\'un objet',false]],'INTERMEDIAIRE',1.0],
];

foreach ($tenasospFr as [$an, $enonce, $opts, $diff, $pts]) {
    insertQ($pdo, $M['francais'], 'TENASOSP', $an, $enonce, $opts, $diff, $pts, 'Français TENASOSP');
}

// ══════════════════════════════════════════════════════════
// ██  EXAMEN D'ÉTAT — Fin secondaire (toutes sections)
// ══════════════════════════════════════════════════════════

// ── EXAMEN D'ÉTAT Mathématiques ───────────────────────────
$etatMaths = [
    [2024,'Résoudre : 2x² − 8 = 0',[
        ['A','x = ±4',false],['B','x = ±2',true,'2x² = 8, x² = 4, x = ±2.'],['C','x = ±8',false],['D','x = 2 seulement',false]],'ELEMENTAIRE',1.0],
    [2024,'Quelle est la limite de (x²−1)/(x−1) quand x→1 ?',[
        ['A','0',false],['B','1',false],['C','2',true,'(x²−1)/(x−1) = (x+1)(x−1)/(x−1) = x+1. Quand x→1 : 1+1=2.'],['D','∞',false]],'INTERMEDIAIRE',2.0],
    [2024,'Calculer C(5,2) (combinaisons de 5 éléments pris 2 à 2).',[
        ['A','10',true,'C(5,2) = 5!/(2!×3!) = (5×4)/(2×1) = 10.'],['B','20',false],['C','15',false],['D','25',false]],'INTERMEDIAIRE',1.5],
    [2024,'Dans un repère orthonormé, quelle est la distance entre A(1,2) et B(4,6) ?',[
        ['A','3',false],['B','4',false],['C','5',true,'d = √((4−1)²+(6−2)²) = √(9+16) = √25 = 5.'],['D','7',false]],'INTERMEDIAIRE',2.0],
    [2024,'Résoudre l\'inéquation : 2x − 3 > 7',[
        ['A','x > 2',false],['B','x > 5',true,'2x > 7+3 = 10, x > 10/2 = 5.'],['C','x > 3',false],['D','x > 4',false]],'ELEMENTAIRE',1.0],
    [2023,'Quel est le domaine de définition de f(x) = √(x−3) ?',[
        ['A','x < 3',false],['B','x = 3',false],['C','x ≥ 3',true,'La racine carrée est définie pour x−3 ≥ 0, soit x ≥ 3.'],['D','x > 0',false]],'INTERMEDIAIRE',1.5],
    [2023,'Calculer la somme des 10 premiers entiers naturels.',[
        ['A','45',false],['B','50',false],['C','55',true,'Sn = n(n+1)/2 = 10×11/2 = 55.'],['D','60',false]],'ELEMENTAIRE',1.0],
    [2023,'Quelle est la valeur de cos(π/3) ?',[
        ['A','√3/2',false],['B','1/2',true,'cos(60°) = cos(π/3) = 1/2. À mémoriser avec sin(60°)=√3/2.'],['C','√2/2',false],['D','1',false]],'INTERMEDIAIRE',1.5],
    [2022,'Calculer la dérivée de g(x) = sin(2x)',[
        ['A','cos(2x)',false],['B','2cos(2x)',true,'Dérivée de sin(u) = u\'·cos(u). Ici u=2x, u\'=2, donc g\'(x)=2cos(2x).'],['C','−2cos(2x)',false],['D','2sin(2x)',false]],'INTERMEDIAIRE',2.0],
    [2022,'Quel est le nombre complexe conjugué de z = 3 + 4i ?',[
        ['A','3 − 4i',true,'Le conjugué de z = a + bi est z̄ = a − bi. Donc conjugué de 3+4i est 3−4i.'],['B','−3 + 4i',false],['C','4 + 3i',false],['D','3 + 4i',false]],'ELEMENTAIRE',1.0],
    [2021,'Quelle est la valeur de tan(45°) ?',[
        ['A','0',false],['B','√3/2',false],['C','1',true,'tan(45°) = sin(45°)/cos(45°) = (√2/2)/(√2/2) = 1.'],['D','√3',false]],'ELEMENTAIRE',1.0],
];

foreach ($etatMaths as [$an, $enonce, $opts, $diff, $pts]) {
    insertQ($pdo, $M['maths'], 'EXAMEN_ETAT', $an, $enonce, $opts, $diff, $pts, 'Mathématiques Examen d\'État');
}

// ── EXAMEN D'ÉTAT Français ────────────────────────────────
$etatFr = [
    [2024,'Quelle figure de style apparaît dans : « Le vent hurlait dans la nuit » ?',[
        ['A','Métaphore',false],['B','Personnification',true,'Attribuer des actions humaines (hurler) à une chose inanimée (le vent) est une personnification.'],['C','Allitération',false],['D','Hyperbole',false]],'INTERMEDIAIRE',1.0],
    [2024,'Quel est le mode de « il faut que tu viennes » ?',[
        ['A','Indicatif',false],['B','Conditionnel',false],['C','Subjonctif',true,'Après « il faut que », on utilise le subjonctif.'],['D','Infinitif',false]],'ELEMENTAIRE',1.0],
    [2024,'Expliquer la différence entre « davantage » et « d\'avantage ».',[
        ['A','Aucune différence',false],['B','Davantage = plus ; d\'avantage = de bénéfice',true,'« Davantage » (adverbe) = plus. « D\'avantage » = de [l\']avantage (nom). Ex : « Je n\'ai d\'avantage sur personne. »'],['C','D\'avantage = plus ; davantage = bénéfice',false],['D','L\'un est masculin, l\'autre féminin',false]],'AVANCE',2.0],
    [2023,'Conjuguer « vouloir » à la 2ème personne du pluriel du conditionnel présent.',[
        ['A','vous voulez',false],['B','vous voudrez',false],['C','vous voudriez',true,'Conditionnel présent de « vouloir » : je voudrais, tu voudrais, il voudrait, nous voudrions, vous voudriez, ils voudraient.'],['D','vous voudriez',true]],'INTERMEDIAIRE',1.5],
    [2023,'Quelle est la proposition subordonnée dans : « Je pense qu\'il viendra. » ?',[
        ['A','Je pense',false],['B','qu\'il viendra',true,'« qu\'il viendra » est une proposition subordonnée conjonctive complétive (COD du verbe penser).'],['C','il viendra',false],['D','Je pense qu\'il',false]],'INTERMEDIAIRE',1.0],
    [2022,'Qu\'est-ce qu\'un oxymore ?',[
        ['A','Répétition d\'un même mot',false],['B','Association de termes contradictoires',true,'L\'oxymore rapproche deux mots de sens opposés : « une obscure clarté », « un silence éloquent ».'],['C','Comparaison explicite',false],['D','Inversion du sujet et du verbe',false]],'INTERMEDIAIRE',1.0],
    [2021,'Distinguer les homophones « leur » et « leurs ».',[
        ['A','Leur est toujours invariable',false],['B','Leurs prend un s quand il précède un nom pluriel',true,'« Leur » pronom personnel est invariable. « Leurs » déterminant possessif s\'accorde en nombre : leurs livres.'],['C','Ils s\'emploient toujours de la même façon',false],['D','Leur ne s\'emploie qu\'avec des animaux',false]],'INTERMEDIAIRE',1.5],
];

foreach ($etatFr as [$an, $enonce, $opts, $diff, $pts]) {
    insertQ($pdo, $M['francais'], 'EXAMEN_ETAT', $an, $enonce, $opts, $diff, $pts, 'Français Examen d\'État');
}

// ── EXAMEN D'ÉTAT Chimie ──────────────────────────────────
$etatChimie = [
    [2024,'Quel est le principe de la conservation de la masse dans une réaction chimique ?',[
        ['A','La masse augmente pendant la réaction',false],['B','La masse des réactifs = masse des produits',true,'Loi de Lavoisier : « Rien ne se perd, rien ne se crée, tout se transforme. » La masse totale est conservée.'],['C','La masse des produits est toujours inférieure',false],['D','Les réactifs disparaissent complètement',false]],'ELEMENTAIRE',1.0],
    [2024,'Quel est le volume molaire d\'un gaz dans les conditions normales (0°C, 1 atm) ?',[
        ['A','18 L/mol',false],['B','22,4 L/mol',true,'À 0°C et 1 atm (CNTP), 1 mole de tout gaz idéal occupe 22,4 litres.'],['C','24 L/mol',false],['D','11,2 L/mol',false]],'ELEMENTAIRE',1.0],
    [2024,'Quelle est la formule de l\'acide sulfurique ?',[
        ['A','HCl',false],['B','HNO₃',false],['C','H₂SO₄',true,'L\'acide sulfurique : H₂SO₄ (deux protons H⁺ et un ion sulfate SO₄²⁻).'],['D','H₃PO₄',false]],'ELEMENTAIRE',1.0],
    [2023,'Dans la réaction : Zn + H₂SO₄ → ZnSO₄ + H₂, quel élément est oxydé ?',[
        ['A','H',false],['B','S',false],['C','Zn',true,'Zn passe de l\'état 0 (métal) à Zn²⁺ (dans ZnSO₄) → il perd des électrons → il est oxydé.'],['D','O',false]],'INTERMEDIAIRE',2.0],
    [2023,'Combien de moles de NaOH sont nécessaires pour neutraliser 2 moles de H₂SO₄ ?',[
        ['A','1 mol',false],['B','2 mol',false],['C','4 mol',true,'H₂SO₄ + 2NaOH → Na₂SO₄ + 2H₂O. Le rapport est 1:2, donc 2 mol H₂SO₄ × 2 = 4 mol NaOH.'],['D','3 mol',false]],'INTERMEDIAIRE',2.0],
    [2022,'Quel est le nom de CH₃COOH ?',[
        ['A','Méthanol',false],['B','Acide acétique',true,'CH₃COOH est l\'acide acétique (ou acide éthanoïque) — principal composant du vinaigre.'],['C','Éthanol',false],['D','Acide formique',false]],'ELEMENTAIRE',1.0],
    [2021,'Comment calcule-t-on le nombre de moles d\'un gaz à T et P données avec la loi des gaz parfaits ?',[
        ['A','n = PV/RT',true,'Loi des gaz parfaits : PV = nRT. Donc n = PV/RT où R = 8,314 J/(mol·K).'],['B','n = RT/PV',false],['C','n = P/VRT',false],['D','n = PVT/R',false]],'AVANCE',2.0],
];

foreach ($etatChimie as [$an, $enonce, $opts, $diff, $pts]) {
    insertQ($pdo, $M['chimie'], 'EXAMEN_ETAT', $an, $enonce, $opts, $diff, $pts, 'Chimie Examen d\'État');
}

// ── EXAMEN D'ÉTAT Physique ────────────────────────────────
$etatPhysique = [
    [2024,'Énoncer le principe de conservation de l\'énergie.',[
        ['A','L\'énergie peut être créée lors d\'un choc',false],['B','L\'énergie totale d\'un système isolé reste constante',true,'L\'énergie ne peut ni être créée ni être détruite — elle se transforme d\'une forme à une autre.'],['C','L\'énergie cinétique est toujours maximale',false],['D','L\'énergie augmente avec la chaleur',false]],'ELEMENTAIRE',1.0],
    [2024,'Calculer la puissance d\'un appareil consommant 3 000 J en 60 secondes.',[
        ['A','20 W',false],['B','50 W',true,'P = E/t = 3 000 J ÷ 60 s = 50 W.'],['C','180 000 W',false],['D','3 060 W',false]],'ELEMENTAIRE',1.0],
    [2024,'Quel est l\'indice de réfraction si la vitesse de la lumière dans un milieu est 2×10⁸ m/s ?',[
        ['A','1',false],['B','1,5',true,'n = c/v = (3×10⁸)/(2×10⁸) = 1,5.'],['C','2',false],['D','0,5',false]],'INTERMEDIAIRE',2.0],
    [2023,'Un fil conducteur de résistance 10 Ω est parcouru par un courant de 3 A. Quelle est la tension ?',[
        ['A','3,3 V',false],['B','13 V',false],['C','30 V',true,'U = RI = 10 × 3 = 30 V (loi d\'Ohm).'],['D','7 V',false]],'ELEMENTAIRE',1.0],
    [2023,'Qu\'est-ce que la fréquence d\'une onde sonore exprime ?',[
        ['A','L\'amplitude du son',false],['B','Le nombre de vibrations par seconde',true,'La fréquence (en Hz) est le nombre d\'oscillations complètes par seconde. Elle détermine la hauteur (grave ou aigu) du son.'],['C','La vitesse de propagation',false],['D','L\'intensité du son',false]],'ELEMENTAIRE',1.0],
    [2022,'La force gravitationnelle entre deux masses m₁ et m₂ séparées par une distance d est :',[
        ['A','F = Gm₁m₂/d',false],['B','F = Gm₁m₂/d²',true,'Loi de Newton : F = G·m₁·m₂/d² où G = 6,67×10⁻¹¹ N·m²/kg².'],['C','F = G(m₁+m₂)/d²',false],['D','F = m₁m₂d/G',false]],'INTERMEDIAIRE',2.0],
    [2021,'Quel est le type de miroir utilisé dans les phares de voiture ?',[
        ['A','Miroir plan',false],['B','Miroir concave',true,'Les phares utilisent un miroir concave parabolique qui concentre la lumière en un faisceau parallèle.'],['C','Miroir convexe',false],['D','Lentille convergente',false]],'ELEMENTAIRE',1.0],
];

foreach ($etatPhysique as [$an, $enonce, $opts, $diff, $pts]) {
    insertQ($pdo, $M['physique'], 'EXAMEN_ETAT', $an, $enonce, $opts, $diff, $pts, 'Physique Examen d\'État');
}

// ── EXAMEN D'ÉTAT Biologie ────────────────────────────────
$etatBio = [
    [2024,'Qu\'est-ce que la méiose produit ?',[
        ['A','Deux cellules identiques à la cellule mère',false],['B','Quatre cellules haploïdes (gamètes)',true,'La méiose produit 4 cellules avec la moitié des chromosomes (n=23 chez l\'humain) — ce sont les gamètes (spermatozoïdes, ovules).'],['C','Une seule cellule diploïde',false],['D','Deux cellules diploïdes différentes',false]],'ELEMENTAIRE',1.0],
    [2024,'Quel est le rôle de la mitochondrie ?',[
        ['A','Synthèse des protéines',false],['B','Production d\'énergie (ATP) par respiration cellulaire',true,'La mitochondrie est le « générateur » de la cellule — elle produit l\'ATP par la respiration cellulaire aérobie.'],['C','Stockage de l\'ADN',false],['D','Transport des lipides',false]],'ELEMENTAIRE',1.0],
    [2024,'Comment s\'appelle la mutation où un chromosome surnuméraire apparaît ?',[
        ['A','Délétion',false],['B','Translocation',false],['C','Trisomie',true,'La trisomie est la présence d\'un chromosome en 3 exemplaires au lieu de 2. La trisomie 21 cause le syndrome de Down.'],['D','Inversion',false]],'INTERMEDIAIRE',1.5],
    [2023,'Quelle est la différence entre une cellule procaryote et eucaryote ?',[
        ['A','Les procaryotes ont un noyau, les eucaryotes non',false],['B','Les procaryotes n\'ont pas de noyau délimité par une membrane',true,'Procaryotes (bactéries) = pas de vrai noyau. Eucaryotes (animaux, plantes, champignons) = noyau avec membrane nucléaire.'],['C','Les deux types ont un noyau',false],['D','Les eucaryotes sont toujours plus petits',false]],'INTERMEDIAIRE',1.0],
    [2023,'Quel processus permet à l\'organisme de lutter contre les agents pathogènes ?',[
        ['A','Phagocytose uniquement',false],['B','Réponse immunitaire (immunité)',true,'L\'immunité comprend l\'immunité innée (phagocytose, inflammation) et adaptative (lymphocytes T et B, anticorps).'],['C','Respiration cellulaire',false],['D','Division cellulaire',false]],'ELEMENTAIRE',1.0],
    [2022,'Quel gène est porté sur le chromosome Y ?',[
        ['A','Gène de l\'hémophilie',false],['B','Gène SRY (détermine le sexe masculin)',true,'Le gène SRY sur le chromosome Y déclenche le développement masculin chez les mammifères.'],['C','Gène de la drépanocytose',false],['D','Gène BRCA1',false]],'AVANCE',2.0],
    [2021,'Qu\'est-ce que la sélection naturelle selon Darwin ?',[
        ['A','Les organismes les plus forts survivent toujours',false],['B','Les individus les mieux adaptés survivent et se reproduisent davantage',true,'Darwin : les individus avec des traits avantageux survivent et transmettent leurs gènes — c\'est la sélection naturelle.'],['C','L\'environnement change les gènes directement',false],['D','Les organismes choisissent leurs mutations',false]],'ELEMENTAIRE',1.0],
];

foreach ($etatBio as [$an, $enonce, $opts, $diff, $pts]) {
    insertQ($pdo, $M['biologie'], 'EXAMEN_ETAT', $an, $enonce, $opts, $diff, $pts, 'Biologie Examen d\'État');
}

// ── EXAMEN D'ÉTAT Histoire-Géo ────────────────────────────
$etatHG = [
    [2024,'Quand et où a été fondée l\'Organisation des Nations Unies (ONU) ?',[
        ['A','1919, Paris',false],['B','1945, San Francisco',true,'L\'ONU a été fondée le 24 octobre 1945 à San Francisco après la Seconde Guerre mondiale.'],['C','1948, Genève',false],['D','1950, New York',false]],'ELEMENTAIRE',1.0],
    [2024,'Quel événement a déclenché la Première Guerre mondiale en 1914 ?',[
        ['A','Invasion de la Pologne',false],['B','Assassinat de l\'archiduc François-Ferdinand à Sarajevo',true,'L\'attentat contre l\'archiduc austro-hongrois à Sarajevo le 28 juin 1914 a déclenché la Première Guerre mondiale.'],['C','Révolution russe',false],['D','Traité de Versailles',false]],'ELEMENTAIRE',1.0],
    [2024,'Quel pays a colonisé le Congo (actuelle RDC) jusqu\'en 1960 ?',[
        ['A','France',false],['B','Portugal',false],['C','Belgique',true,'Le Congo a été colonisé par la Belgique depuis 1908 (après le Congo de Léopold II) jusqu\'à l\'indépendance du 30 juin 1960.'],['D','Angleterre',false]],'ELEMENTAIRE',1.0],
    [2023,'Quel est le premier pays africain à avoir obtenu son indépendance ?',[
        ['A','Ghana',false],['B','Éthiopie',false],['C','Haïti (Caraïbes — premier État noir libre, 1804)',false],['D','Liberia (1847) — 1er pays africain indépendant reconnu',true,'Le Liberia (1847) est généralement cité comme le premier pays africain indépendant. L\'Éthiopie n\'a jamais été réellement colonisée.'],],'AVANCE',2.0],
    [2023,'Où se situe le mont Kilimandjaro ?',[
        ['A','Kenya',false],['B','Tanzanie',true,'Le Kilimandjaro (5 895 m), point culminant de l\'Afrique, se trouve en Tanzanie.'],['C','Éthiopie',false],['D','RDC',false]],'ELEMENTAIRE',1.0],
    [2022,'Quel traité a mis fin à la Première Guerre mondiale ?',[
        ['A','Traité de Paris',false],['B','Traité de Versailles',true,'Le Traité de Versailles (28 juin 1919) a officiellement mis fin à la Première Guerre mondiale.'],['C','Accord de Munich',false],['D','Pacte de Genève',false]],'ELEMENTAIRE',1.0],
    [2021,'Quel est le désert le plus grand du monde ?',[
        ['A','Sahara',false],['B','Gobi',false],['C','Antarctique',true,'L\'Antarctique est le plus grand désert du monde (14 millions de km²). Le Sahara est le plus grand désert chaud.'],['D','Arabian Desert',false]],'INTERMEDIAIRE',1.0],
];

foreach ($etatHG as [$an, $enonce, $opts, $diff, $pts]) {
    insertQ($pdo, $M['histgeo'], 'EXAMEN_ETAT', $an, $enonce, $opts, $diff, $pts, 'Histoire-Géo Examen d\'État');
}

// ── EXAMEN D'ÉTAT Anglais ─────────────────────────────────
$etatAnglais = [
    [2024,'Rewrite in passive voice: "The teacher corrects the tests."',[
        ['A','The tests corrected by the teacher.',false],['B','The tests are corrected by the teacher.',true,'Passive voice: subject + to be + past participle + by + agent. Present: are corrected.'],['C','The tests were corrected by the teacher.',false],['D','The teacher is corrected the tests.',false]],'INTERMEDIAIRE',1.0],
    [2024,'Choose the correct form: "If I ___ you, I would study harder."',[
        ['A','am',false],['B','was/were',true,'Second conditional (unreal present/future): "If I were you..." — "were" is the subjunctive form used here.'],['C','will be',false],['D','have been',false]],'INTERMEDIAIRE',1.5],
    [2024,'What does "nevertheless" mean?',[
        ['A','As a result',false],['B','However / in spite of that',true,'"Nevertheless" is a conjunction meaning "however" or "in spite of that" — used to contrast two ideas.'],['C','Moreover',false],['D','Therefore',false]],'INTERMEDIAIRE',1.0],
    [2023,'Complete: "She has lived in Kinshasa ___ 2015."',[
        ['A','for',false],['B','since',true,'"Since" is used with a specific point in time (2015). "For" is used with a duration (for 9 years).'],['C','during',false],['D','from',false]],'ELEMENTAIRE',1.0],
    [2023,'What is the difference between "affect" and "effect"?',[
        ['A','They are synonyms',false],['B','"Affect" is a verb; "effect" is usually a noun',true,'"Affect" (verb): The rain affected our plans. "Effect" (noun): The effect was immediate.'],['C','"Effect" is a verb; "affect" is a noun',false],['D','Both are only adjectives',false]],'AVANCE',2.0],
    [2022,'Choose the sentence with the correct use of the present perfect.',[
        ['A','I have seen him yesterday.',false],['B','I have just seen him.',true,'Present perfect with "just" indicates a very recent completed action. "Yesterday" requires simple past.'],['C','I seen him recently.',false],['D','I have see him today.',false]],'INTERMEDIAIRE',1.5],
    [2021,'What literary device is used in: "The stars danced in the night sky"?',[
        ['A','Simile',false],['B','Personification',true,'Attributing a human action (dancing) to non-human stars is personification.'],['C','Metaphor',false],['D','Alliteration',false]],'INTERMEDIAIRE',1.0],
];

foreach ($etatAnglais as [$an, $enonce, $opts, $diff, $pts]) {
    insertQ($pdo, $M['anglais'], 'EXAMEN_ETAT', $an, $enonce, $opts, $diff, $pts, 'Anglais Examen d\'État');
}

// ══════════════════════════════════════════════════════════
// Résumé
// ══════════════════════════════════════════════════════════
echo "✅ Terminé !\n";
echo "   Questions insérées : $inserted\n";
echo "   Questions ignorées (doublons) : $skipped\n";

// Résumé par type
$stats = $pdo->query(
    "SELECT exam_type, COUNT(*) as nb FROM question_bank WHERE status='PUBLIE' GROUP BY exam_type"
)->fetchAll(PDO::FETCH_ASSOC);
echo "\nDistribution totale :\n";
foreach ($stats as $s) {
    echo "  {$s['exam_type']} : {$s['nb']} questions\n";
}
