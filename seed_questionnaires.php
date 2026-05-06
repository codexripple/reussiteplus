<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_admin(); // Script restreint aux admins
/**
 * seed_questionnaires.php
 * CrÃ©e de vrais questionnaires liÃ©s aux archives (question_bank + question_options)
 * InspirÃ© du programme EPSP RDC â€” questions originales
 * Usage: php seed_questionnaires.php  (ou via navigateur localhost)
 */

if (php_sapi_name() !== 'cli' && ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1') {
    http_response_code(403); exit('AccÃ¨s interdit.');
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$pdo = db();
$pdo->exec("SET NAMES utf8mb4");

// â”€â”€ IDs matiÃ¨res â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

// â”€â”€ Compteur global â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$inserted = 0;
$skipped  = 0;

// â”€â”€ Helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function insertQ(PDO $pdo, string $matiereId, string $examType, int $annee,
                 string $enonce, array $opts, string $diff = 'INTERMEDIAIRE',
                 float $pts = 1.0, ?string $objectif = null): void {
    global $inserted, $skipped;

    // Ã‰viter doublons sur enoncÃ© + type + annÃ©e
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

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// â–ˆâ–ˆ  ENAFEP â€” FIN DE PRIMAIRE (6Ã¨me annÃ©e)
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

// â”€â”€ ENAFEP MathÃ©matiques â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$enafepMaths = [
    // 2024
    [2024,'Quelle est la valeur de 5Â² + 3Â² ?',[
        ['A','25',false],['B','34',true,'5Â²=25 et 3Â²=9, donc 25+9=34.'],['C','28',false],['D','40',false]],'ELEMENTAIRE',1.0],
    [2024,'Un rectangle a une longueur de 12 cm et une largeur de 7 cm. Quelle est son aire ?',[
        ['A','38 cmÂ²',false],['B','84 cmÂ²',true,'Aire = longueur Ã— largeur = 12Ã—7 = 84 cmÂ².'],['C','19 cmÂ²',false],['D','96 cmÂ²',false]],'ELEMENTAIRE',1.0],
    [2024,'Combien y a-t-il de minutes dans 2 heures et demie ?',[
        ['A','120 min',false],['B','130 min',false],['C','150 min',true,'1h=60min, 2h=120min, +30min=150min.'],['D','160 min',false]],'ELEMENTAIRE',1.0],
    [2024,'Quel est le plus grand commun diviseur (PGCD) de 24 et 36 ?',[
        ['A','6',false],['B','12',true,'Diviseurs de 24 : 1,2,3,4,6,8,12,24. Diviseurs de 36 : 1,2,3,4,6,9,12,18,36. PGCD = 12.'],['C','4',false],['D','18',false]],'INTERMEDIAIRE',1.0],
    [2024,'Un robinet remplit un rÃ©servoir de 120 litres en 4 heures. Combien de litres remplit-il par heure ?',[
        ['A','20 L',false],['B','24 L',false],['C','30 L',true,'120 Ã· 4 = 30 litres par heure.'],['D','40 L',false]],'ELEMENTAIRE',1.0],
    [2024,'Quelle fraction est Ã©gale Ã  0,75 ?',[
        ['A','1/4',false],['B','3/5',false],['C','3/4',true,'0,75 = 75/100 = 3/4.'],['D','2/3',false]],'ELEMENTAIRE',1.0],
    [2024,'Un commerÃ§ant achÃ¨te un article Ã  5 000 FC et le vend Ã  6 500 FC. Quel est son bÃ©nÃ©fice ?',[
        ['A','1 000 FC',false],['B','1 500 FC',true,'BÃ©nÃ©fice = prix de vente âˆ’ prix d\'achat = 6 500 âˆ’ 5 000 = 1 500 FC.'],['C','2 000 FC',false],['D','500 FC',false]],'ELEMENTAIRE',1.0],
    [2024,'Quel est le pÃ©rimÃ¨tre d\'un carrÃ© de cÃ´tÃ© 9 cm ?',[
        ['A','18 cm',false],['B','27 cm',false],['C','36 cm',true,'PÃ©rimÃ¨tre d\'un carrÃ© = 4 Ã— cÃ´tÃ© = 4 Ã— 9 = 36 cm.'],['D','81 cm',false]],'ELEMENTAIRE',1.0],
    [2024,'Quel nombre complÃ¨te la suite : 2, 6, 18, 54, ... ?',[
        ['A','108',false],['B','162',true,'Chaque terme est multipliÃ© par 3 : 54Ã—3=162.'],['C','144',false],['D','216',false]],'INTERMEDIAIRE',1.0],
    [2024,'Un Ã©lÃ¨ve obtient 85 points sur 100 Ã  un test. Quel est son pourcentage ?',[
        ['A','80%',false],['B','82%',false],['C','85%',true,'Pourcentage = (points obtenus Ã· points totaux) Ã— 100 = (85Ã·100)Ã—100 = 85%.'],['D','90%',false]],'ELEMENTAIRE',1.0],

    // 2023
    [2023,'Quel est le double de 247 ?',[
        ['A','449',false],['B','494',true,'Double = 247Ã—2 = 494.'],['C','474',false],['D','484',false]],'ELEMENTAIRE',1.0],
    [2023,'Un triangle Ã©quilatÃ©ral a un pÃ©rimÃ¨tre de 39 cm. Quelle est la longueur d\'un cÃ´tÃ© ?',[
        ['A','11 cm',false],['B','12 cm',false],['C','13 cm',true,'PÃ©rimÃ¨tre Ã· 3 = 39 Ã· 3 = 13 cm.'],['D','15 cm',false]],'ELEMENTAIRE',1.0],
    [2023,'Combien y a-t-il de secondes dans une heure ?',[
        ['A','600 s',false],['B','3 600 s',true,'1h = 60 min Ã— 60 s = 3 600 secondes.'],['C','1 200 s',false],['D','7 200 s',false]],'ELEMENTAIRE',1.0],
    [2023,'Quelle est la valeur de 8 Ã— 7 âˆ’ 4 Ã— 6 ?',[
        ['A','28',false],['B','32',true,'8Ã—7=56, 4Ã—6=24, 56âˆ’24=32.'],['C','40',false],['D','36',false]],'INTERMEDIAIRE',1.0],
    [2023,'Un pÃ¨re a 42 ans et son fils a 14 ans. Dans combien d\'annÃ©es le pÃ¨re aura-t-il le double de l\'Ã¢ge du fils ?',[
        ['A','12 ans',false],['B','14 ans',true,'PÃ¨re: 42+x, Fils: 14+x. 42+x=2(14+x) â†’ 42+x=28+2x â†’ x=14.'],['C','16 ans',false],['D','10 ans',false]],'AVANCE',1.0],

    // 2022
    [2022,'Quel est le rÃ©sultat de 1 000 âˆ’ 378 ?',[
        ['A','628',false],['B','622',true,'1 000 âˆ’ 378 = 622.'],['C','632',false],['D','612',false]],'ELEMENTAIRE',1.0],
    [2022,'Un terrain rectangulaire mesure 50 m Ã— 30 m. Quelle est sa superficie en ares ?',[
        ['A','10 ares',false],['B','15 ares',true,'Superficie = 50Ã—30 = 1 500 mÂ². 1 are = 100 mÂ², donc 1 500 Ã· 100 = 15 ares.'],['C','150 ares',false],['D','1,5 ares',false]],'INTERMEDIAIRE',1.0],
    [2022,'Quelle est la valeur de 4/5 de 200 ?',[
        ['A','100',false],['B','150',false],['C','160',true,'4/5 Ã— 200 = (4Ã—200)Ã·5 = 800Ã·5 = 160.'],['D','180',false]],'ELEMENTAIRE',1.0],

    // 2021
    [2021,'Un train parcourt 480 km en 4 heures. Quelle est sa vitesse moyenne ?',[
        ['A','100 km/h',false],['B','120 km/h',true,'Vitesse = distance Ã· temps = 480Ã·4 = 120 km/h.'],['C','140 km/h',false],['D','110 km/h',false]],'ELEMENTAIRE',1.0],
    [2021,'Quel est le plus petit commun multiple (PPCM) de 4 et 6 ?',[
        ['A','12',true,'Multiples de 4 : 4,8,12... Multiples de 6 : 6,12... PPCM = 12.'],['B','24',false],['C','8',false],['D','6',false]],'INTERMEDIAIRE',1.0],
    [2021,'Si un article coÃ»te 3 500 FC et que tu paies avec 5 000 FC, quelle monnaie recevras-tu ?',[
        ['A','1 000 FC',false],['B','1 500 FC',true,'Monnaie = 5 000 âˆ’ 3 500 = 1 500 FC.'],['C','2 000 FC',false],['D','500 FC',false]],'ELEMENTAIRE',1.0],
];

foreach ($enafepMaths as [$an, $enonce, $opts, $diff, $pts]) {
    insertQ($pdo, $M['maths'], 'ENAFEP', $an, $enonce, $opts, $diff, $pts, 'MathÃ©matiques ENAFEP');
}

// â”€â”€ ENAFEP FranÃ§ais â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$enafepFr = [
    [2024,'Quel est le pluriel du mot Â« bal Â» ?',[
        ['A','bals',true,'Le mot Â« bal Â» fait Â« bals Â» au pluriel (pluriel rÃ©gulier avec s).'],['B','baux',false],['C','bales',false],['D','bal',false]],'ELEMENTAIRE',1.0],
    [2024,'Quelle est la nature du mot soulignÃ© dans : Â« Il court rapidement. Â» (rapidement)',[
        ['A','adjectif',false],['B','adverbe',true,'Â« Rapidement Â» modifie le verbe Â« court Â» â€” c\'est un adverbe de maniÃ¨re.'],['C','nom',false],['D','pronom',false]],'ELEMENTAIRE',1.0],
    [2024,'Trouve le synonyme du mot Â« courageux Â».',[
        ['A','lÃ¢che',false],['B','vaillant',true,'Â« Vaillant Â» est synonyme de courageux â€” les deux signifient brave, intrÃ©pide.'],['C','timide',false],['D','paresseux',false]],'ELEMENTAIRE',1.0],
    [2024,'Quelle est la forme passive de : Â« Le chat mange la souris. Â» ?',[
        ['A','La souris mange le chat.',false],['B','La souris est mangÃ©e par le chat.',true,'Forme passive : sujet passif + auxiliaire Ãªtre + participe passÃ© + Â« par Â» + agent.'],['C','La souris a mangÃ© le chat.',false],['D','Le chat est mangÃ© par la souris.',false]],'INTERMEDIAIRE',1.0],
    [2024,'Quel temps est utilisÃ© dans : Â« Demain, je partirai Ã  Kinshasa. Â» ?',[
        ['A','PrÃ©sent',false],['B','Imparfait',false],['C','Futur simple',true,'Â« Partirai Â» est conjuguÃ© au futur simple de l\'indicatif.'],['D','Conditionnel',false]],'ELEMENTAIRE',1.0],
    [2024,'Quel est l\'antonyme du mot Â« genereux Â» ?',[
        ['A','avare',true,'Â« Avare Â» est le contraire de gÃ©nÃ©reux.'],['B','riche',false],['C','doux',false],['D','grand',false]],'ELEMENTAIRE',1.0],
    [2023,'ComplÃ¨te avec le bon article : Â« ___ eau est fraÃ®che. Â»',[
        ['A','Le',false],['B','La',false],['C','L\'',true,'Devant un mot commenÃ§ant par une voyelle, on utilise l\'article Ã©lidÃ© L\'.'],['D','Les',false]],'ELEMENTAIRE',1.0],
    [2023,'Dans la phrase Â« Le professeur corrige les cahiers Â», quel est le COD ?',[
        ['A','le professeur',false],['B','corrige',false],['C','les cahiers',true,'Â« Les cahiers Â» rÃ©pond Ã  la question Â« corrige quoi ? Â» â€” c\'est le COD.'],['D','le',false]],'ELEMENTAIRE',1.0],
    [2023,'Quel est le fÃ©minin du mot Â« instituteur Â» ?',[
        ['A','institutrice',true,'Le fÃ©minin de instituteur est institutrice.'],['B','instituteuse',false],['C','instituteresse',false],['D','instituteure',false]],'ELEMENTAIRE',1.0],
    [2022,'Comment dit-on Â« chien Â» au fÃ©minin ?',[
        ['A','chiÃ¨ne',false],['B','chienne',true,'Le fÃ©minin de chien est chienne.'],['C','chienette',false],['D','chien',false]],'ELEMENTAIRE',1.0],
    [2022,'Quelle phrase est Ã  la forme nÃ©gative ?',[
        ['A','Marie chante bien.',false],['B','Il fait beau.',false],['C','Je ne mange pas de viande.',true,'La forme nÃ©gative utilise Â« ne...pas Â».'],['D','Nous allons Ã  l\'Ã©cole.',false]],'ELEMENTAIRE',1.0],
    [2021,'Quel est le participe passÃ© du verbe Â« Ã©crire Â» ?',[
        ['A','Ã©crit',true,'Le participe passÃ© de Â« Ã©crire Â» est Â« Ã©crit Â».'],['B','Ã©crivÃ©',false],['C','Ã©crivant',false],['D','Ã©cris',false]],'ELEMENTAIRE',1.0],
];

foreach ($enafepFr as [$an, $enonce, $opts, $diff, $pts]) {
    insertQ($pdo, $M['francais'], 'ENAFEP', $an, $enonce, $opts, $diff, $pts, 'FranÃ§ais ENAFEP');
}

// â”€â”€ ENAFEP Sciences Naturelles â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$enafepSci = [
    [2024,'Quel organe du corps humain pompe le sang ?',[
        ['A','Le foie',false],['B','Le cÅ“ur',true,'Le cÅ“ur est le muscle qui pompe le sang dans tout le corps.'],['C','Les poumons',false],['D','Le cerveau',false]],'ELEMENTAIRE',1.0],
    [2024,'Quelle planÃ¨te est la plus proche du Soleil ?',[
        ['A','VÃ©nus',false],['B','Mercure',true,'Mercure est la premiÃ¨re planÃ¨te du systÃ¨me solaire, la plus proche du Soleil.'],['C','Mars',false],['D','Terre',false]],'ELEMENTAIRE',1.0],
    [2024,'Comment s\'appelle le processus par lequel les plantes fabriquent leur nourriture ?',[
        ['A','Respiration',false],['B','Digestion',false],['C','PhotosynthÃ¨se',true,'La photosynthÃ¨se est la fabrication de glucose par les plantes grÃ¢ce Ã  la lumiÃ¨re solaire et au COâ‚‚.'],['D','Transpiration',false]],'ELEMENTAIRE',1.0],
    [2024,'Quelle est la tempÃ©rature d\'Ã©bullition de l\'eau Ã  pression normale ?',[
        ['A','80Â°C',false],['B','90Â°C',false],['C','100Â°C',true,'L\'eau bout Ã  100Â°C (ou 212Â°F) Ã  pression atmosphÃ©rique normale.'],['D','120Â°C',false]],'ELEMENTAIRE',1.0],
    [2024,'Quel gaz les humains inspirent-ils pour vivre ?',[
        ['A','COâ‚‚',false],['B','Azote',false],['C','OxygÃ¨ne',true,'Les humains inspirent l\'oxygÃ¨ne (Oâ‚‚) et expirent le dioxyde de carbone (COâ‚‚).'],['D','HydrogÃ¨ne',false]],'ELEMENTAIRE',1.0],
    [2023,'Combien d\'os y a-t-il dans le corps humain adulte ?',[
        ['A','106',false],['B','206',true,'Le squelette humain adulte est composÃ© de 206 os.'],['C','306',false],['D','156',false]],'INTERMEDIAIRE',1.0],
    [2023,'Quel animal est le plus grand mammifÃ¨re terrestre ?',[
        ['A','RhinocÃ©ros',false],['B','Hippopotame',false],['C','Ã‰lÃ©phant d\'Afrique',true,'L\'Ã©lÃ©phant d\'Afrique est le plus grand animal terrestre, pouvant peser jusqu\'Ã  7 tonnes.'],['D','Girafe',false]],'ELEMENTAIRE',1.0],
    [2022,'Quelle est la formule chimique de l\'eau ?',[
        ['A','COâ‚‚',false],['B','NaCl',false],['C','Hâ‚‚O',true,'L\'eau est composÃ©e de deux atomes d\'hydrogÃ¨ne (H) et un atome d\'oxygÃ¨ne (O).'],['D','Oâ‚‚',false]],'ELEMENTAIRE',1.0],
    [2022,'D\'oÃ¹ vient principalement l\'Ã©nergie du Soleil ?',[
        ['A','Combustion du charbon',false],['B','Fusion nuclÃ©aire',true,'Le Soleil produit son Ã©nergie par fusion nuclÃ©aire : des atomes d\'hydrogÃ¨ne fusionnent pour former de l\'hÃ©lium.'],['C','Fission nuclÃ©aire',false],['D','Ã‰nergie gÃ©othermique',false]],'AVANCE',1.0],
    [2021,'Quel est le rÃ´le principal du cerveau ?',[
        ['A','Pomper le sang',false],['B','Filtrer l\'urine',false],['C','ContrÃ´ler le corps et la pensÃ©e',true,'Le cerveau est le centre de commande du corps humain â€” il contrÃ´le les mouvements, les sens et la pensÃ©e.'],['D','DigÃ©rer les aliments',false]],'ELEMENTAIRE',1.0],
];

foreach ($enafepSci as [$an, $enonce, $opts, $diff, $pts]) {
    insertQ($pdo, $M['sciences'], 'ENAFEP', $an, $enonce, $opts, $diff, $pts, 'Sciences Naturelles ENAFEP');
}

// â”€â”€ ENAFEP Histoire-GÃ©ographie â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$enafepHG = [
    [2024,'Quelle est la capitale de la RÃ©publique DÃ©mocratique du Congo ?',[
        ['A','Brazzaville',false],['B','Lubumbashi',false],['C','Kinshasa',true,'Kinshasa est la capitale et la plus grande ville de la RDC, anciennement appelÃ©e LÃ©opoldville.'],['D','Kisangani',false]],'ELEMENTAIRE',1.0],
    [2024,'Quel est le plus grand pays d\'Afrique par sa superficie ?',[
        ['A','RDC',false],['B','Soudan',false],['C','AlgÃ©rie',true,'L\'AlgÃ©rie est le plus grand pays d\'Afrique avec 2,38 millions de kmÂ².'],['D','Nigeria',false]],'ELEMENTAIRE',1.0],
    [2024,'Quel fleuve traverse la ville de Kinshasa ?',[
        ['A','Nil',false],['B','Congo',true,'Le fleuve Congo traverse Kinshasa. C\'est le deuxiÃ¨me plus long fleuve d\'Afrique et le plus profond du monde.'],['C','Niger',false],['D','ZambÃ¨ze',false]],'ELEMENTAIRE',1.0],
    [2024,'En quelle annÃ©e la RDC a-t-elle obtenu son indÃ©pendance ?',[
        ['A','1958',false],['B','1960',true,'La RDC (alors Congo-Kinshasa) a obtenu son indÃ©pendance de la Belgique le 30 juin 1960.'],['C','1962',false],['D','1965',false]],'ELEMENTAIRE',1.0],
    [2024,'Qui Ã©tait le premier Premier Ministre de la RDC aprÃ¨s l\'indÃ©pendance ?',[
        ['A','Joseph Kasavubu',false],['B','Mobutu Sese Seko',false],['C','Patrice Lumumba',true,'Patrice Lumumba fut le premier Premier Ministre de la RDC, aprÃ¨s l\'indÃ©pendance du 30 juin 1960.'],['D','Moise Tshombe',false]],'ELEMENTAIRE',1.0],
    [2023,'Quel est le plus grand lac d\'Afrique ?',[
        ['A','Lac Tanganyika',false],['B','Lac Victoria',true,'Le lac Victoria est le plus grand lac d\'Afrique par sa superficie (environ 68 800 kmÂ²).'],['C','Lac Malawi',false],['D','Lac Kivu',false]],'ELEMENTAIRE',1.0],
    [2023,'Combien de provinces compte la RDC depuis 2015 ?',[
        ['A','11',false],['B','26',true,'Depuis 2015, la RDC est divisÃ©e en 26 provinces plus la ville-province de Kinshasa.'],['C','10',false],['D','21',false]],'INTERMEDIAIRE',1.0],
    [2022,'Quel est le continent le plus chaud ?',[
        ['A','Asie',false],['B','Afrique',true,'L\'Afrique est le continent le plus chaud de la Terre, traversÃ© par l\'Ã©quateur et les tropiques.'],['C','AmÃ©rique du Sud',false],['D','OcÃ©anie',false]],'ELEMENTAIRE',1.0],
    [2022,'Quelle mer sÃ©pare l\'Europe de l\'Afrique ?',[
        ['A','Mer Rouge',false],['B','Mer Noire',false],['C','Mer MÃ©diterranÃ©e',true,'La MÃ©diterranÃ©e sÃ©pare l\'Europe (au nord) de l\'Afrique (au sud).'],['D','Mer Caspienne',false]],'ELEMENTAIRE',1.0],
    [2021,'Quel est le symbole de la paix dans le monde ?',[
        ['A','L\'aigle',false],['B','La colombe blanche',true,'La colombe blanche est universellement reconnue comme le symbole de la paix.'],['C','Le lion',false],['D','Le drapeau rouge',false]],'ELEMENTAIRE',1.0],
];

foreach ($enafepHG as [$an, $enonce, $opts, $diff, $pts]) {
    insertQ($pdo, $M['histgeo'], 'ENAFEP', $an, $enonce, $opts, $diff, $pts, 'Histoire-GÃ©o ENAFEP');
}

// â”€â”€ ENAFEP Anglais â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// â–ˆâ–ˆ  TENASOSP â€” FIN DE SECONDAIRE (HumanitÃ©s)
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

// â”€â”€ TENASOSP MathÃ©matiques â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$tenasospMaths = [
    [2024,'RÃ©soudre l\'Ã©quation : 3x + 7 = 22',[
        ['A','x = 4',false],['B','x = 5',true,'3x = 22âˆ’7 = 15, donc x = 15Ã·3 = 5.'],['C','x = 6',false],['D','x = 3',false]],'ELEMENTAIRE',1.0],
    [2024,'Quelle est la valeur de sin(30Â°) ?',[
        ['A','âˆš2/2',false],['B','âˆš3/2',false],['C','1/2',true,'sin(30Â°) = 1/2. C\'est une valeur remarquable Ã  mÃ©moriser.'],['D','1',false]],'INTERMEDIAIRE',1.0],
    [2024,'Calculer la dÃ©rivÃ©e de f(x) = xÂ³ âˆ’ 5x + 2',[
        ['A','f\'(x) = 3xÂ² + 5',false],['B','f\'(x) = 3xÂ² âˆ’ 5',true,'La dÃ©rivÃ©e de xâ¿ est nxâ¿â»Â¹. DÃ©rivÃ©e de xÂ³=3xÂ², de -5x=-5, constante=0.'],['C','f\'(x) = xÂ² âˆ’ 5',false],['D','f\'(x) = 3x âˆ’ 5',false]],'INTERMEDIAIRE',1.5],
    [2024,'Quel est le discriminant de xÂ² âˆ’ 6x + 9 = 0 ?',[
        ['A','27',false],['B','0',true,'Î” = bÂ²âˆ’4ac = 36âˆ’4Ã—1Ã—9 = 36âˆ’36 = 0. Racine double : x=3.'],['C','-9',false],['D','3',false]],'INTERMEDIAIRE',1.5],
    [2024,'Simplifier : (xÂ²âˆ’4) Ã· (xâˆ’2)',[
        ['A','xâˆ’2',false],['B','x+2',true,'xÂ²âˆ’4 = (xâˆ’2)(x+2). On divise par (xâˆ’2) â†’ rÃ©sultat : (x+2).'],['C','xÂ²âˆ’2',false],['D','x+4',false]],'INTERMEDIAIRE',1.0],
    [2024,'Calculer : logâ‚â‚€(1000)',[
        ['A','2',false],['B','3',true,'logâ‚â‚€(1000) = logâ‚â‚€(10Â³) = 3.'],['C','10',false],['D','100',false]],'ELEMENTAIRE',1.0],
    [2024,'Quelle est l\'Ã©quation d\'une droite passant par (0,3) avec une pente de 2 ?',[
        ['A','y = 2x âˆ’ 3',false],['B','y = 3x + 2',false],['C','y = 2x + 3',true,'Forme pente-ordonnÃ©e : y = mx + b. m=2, b=3 (ordonnÃ©e Ã  l\'origine). Donc y = 2x + 3.'],['D','y = x + 3',false]],'ELEMENTAIRE',1.0],
    [2023,'Calculer l\'intÃ©grale âˆ« 4x dx',[
        ['A','4xÂ² + C',false],['B','2xÂ² + C',true,'âˆ« 4x dx = 4(xÂ²/2) + C = 2xÂ² + C.'],['C','4x + C',false],['D','xÂ² + C',false]],'INTERMEDIAIRE',1.5],
    [2023,'Dans un triangle rectangle, si les deux cathÃ¨tes mesurent 3 et 4, quelle est l\'hypotÃ©nuse ?',[
        ['A','6',false],['B','5',true,'ThÃ©orÃ¨me de Pythagore : cÂ² = 3Â²+4Â² = 9+16 = 25, c = 5.'],['C','7',false],['D','25',false]],'ELEMENTAIRE',1.0],
    [2023,'Quel est le rÃ©sultat de 2Â³ Ã— 2â´ ?',[
        ['A','2â·',true,'aáµ Ã— aâ¿ = aáµâºâ¿, donc 2Â³Ã—2â´ = 2Â³âºâ´ = 2â· = 128.'],['B','4â·',false],['C','2Â¹Â²',false],['D','2â¶',false]],'ELEMENTAIRE',1.0],
    [2022,'Un capital de 200 000 FC est placÃ© Ã  5% par an pendant 3 ans. Quel est l\'intÃ©rÃªt simple ?',[
        ['A','10 000 FC',false],['B','30 000 FC',true,'IntÃ©rÃªt simple = Capital Ã— taux Ã— durÃ©e = 200 000 Ã— 0,05 Ã— 3 = 30 000 FC.'],['C','50 000 FC',false],['D','60 000 FC',false]],'INTERMEDIAIRE',1.0],
    [2022,'RÃ©soudre le systÃ¨me : x + y = 7 et x âˆ’ y = 3',[
        ['A','x=3, y=4',false],['B','x=5, y=2',true,'Addition : 2x=10 â†’ x=5. Puis y=7âˆ’5=2.'],['C','x=4, y=3',false],['D','x=6, y=1',false]],'INTERMEDIAIRE',1.5],
];

foreach ($tenasospMaths as [$an, $enonce, $opts, $diff, $pts]) {
    insertQ($pdo, $M['maths'], 'TENASOSP', $an, $enonce, $opts, $diff, $pts, 'MathÃ©matiques TENASOSP');
}

// â”€â”€ TENASOSP Chimie â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$tenasospChimie = [
    [2024,'Quelle est la masse molaire de l\'eau (Hâ‚‚O) ?',[
        ['A','10 g/mol',false],['B','18 g/mol',true,'M(Hâ‚‚O) = 2Ã—M(H) + M(O) = 2Ã—1 + 16 = 18 g/mol.'],['C','20 g/mol',false],['D','16 g/mol',false]],'ELEMENTAIRE',1.0],
    [2024,'Quel type de liaison chimique implique le partage d\'Ã©lectrons ?',[
        ['A','Liaison ionique',false],['B','Liaison covalente',true,'La liaison covalente = partage d\'Ã©lectrons entre deux atomes non mÃ©talliques.'],['C','Liaison mÃ©tallique',false],['D','Liaison hydrogÃ¨ne',false]],'ELEMENTAIRE',1.0],
    [2024,'Ã‰quilibrer : Hâ‚‚ + Oâ‚‚ â†’ Hâ‚‚O. Quelle est l\'Ã©quation Ã©quilibrÃ©e ?',[
        ['A','Hâ‚‚ + Oâ‚‚ â†’ Hâ‚‚O',false],['B','2Hâ‚‚ + Oâ‚‚ â†’ 2Hâ‚‚O',true,'Pour Ã©quilibrer : 4H Ã  gauche (2Ã—Hâ‚‚), 4H Ã  droite (2Ã—Hâ‚‚O), 2O Ã  gauche (Oâ‚‚), 2O Ã  droite.'],['C','Hâ‚‚ + 2Oâ‚‚ â†’ 2Hâ‚‚O',false],['D','Hâ‚‚ + O â†’ Hâ‚‚O',false]],'INTERMEDIAIRE',1.0],
    [2024,'Quel est le numÃ©ro atomique du carbone (C) ?',[
        ['A','6',true,'Le carbone a 6 protons, donc son numÃ©ro atomique Z=6.'],['B','12',false],['C','8',false],['D','14',false]],'ELEMENTAIRE',1.0],
    [2024,'Quel ion est formÃ© lorsque le sodium perd un Ã©lectron ?',[
        ['A','NaÂ²âº',false],['B','Naâ»',false],['C','Naâº',true,'Le sodium (Na) a 11 Ã©lectrons. En perdant 1, il devient Naâº (charge +1).'],['D','NaÂ²â»',false]],'ELEMENTAIRE',1.0],
    [2024,'Quelle est la concentration molaire si on dissout 2 mol de NaCl dans 4 L d\'eau ?',[
        ['A','0,25 mol/L',false],['B','0,5 mol/L',true,'C = n/V = 2 mol Ã· 4 L = 0,5 mol/L.'],['C','2 mol/L',false],['D','8 mol/L',false]],'INTERMEDIAIRE',1.0],
    [2023,'Qu\'est-ce qu\'une rÃ©action d\'oxydorÃ©duction ?',[
        ['A','RÃ©action entre un acide et une base',false],['B','RÃ©action avec transfert d\'Ã©lectrons entre rÃ©actifs',true,'L\'oxydorÃ©duction implique un transfert d\'Ã©lectrons : un rÃ©ducteur cÃ¨de des Ã©lectrons Ã  un oxydant.'],['C','RÃ©action de prÃ©cipitation',false],['D','RÃ©action de neutralisation',false]],'INTERMEDIAIRE',1.5],
    [2023,'Quel est le pH d\'une solution neutre Ã  25Â°C ?',[
        ['A','0',false],['B','7',true,'Le pH 7 correspond Ã  la neutralitÃ©. pH < 7 = acide, pH > 7 = basique.'],['C','14',false],['D','10',false]],'ELEMENTAIRE',1.0],
    [2022,'Quelle est la formule du chlorure de sodium (sel de table) ?',[
        ['A','NaCl',true,'Le sel de table est le chlorure de sodium : Naâº et Clâ» s\'associent pour former NaCl.'],['B','KCl',false],['C','Naâ‚‚O',false],['D','NaOH',false]],'ELEMENTAIRE',1.0],
    [2022,'Dans une rÃ©action endothermique, l\'Ã©nergie est...',[
        ['A','libÃ©rÃ©e',false],['B','absorbÃ©e',true,'Endothermique = la rÃ©action absorbe de l\'Ã©nergie (chaleur). Exothermique = elle libÃ¨re de l\'Ã©nergie.'],['C','nulle',false],['D','nÃ©gative',false]],'INTERMEDIAIRE',1.0],
];

foreach ($tenasospChimie as [$an, $enonce, $opts, $diff, $pts]) {
    insertQ($pdo, $M['chimie'], 'TENASOSP', $an, $enonce, $opts, $diff, $pts, 'Chimie TENASOSP');
}

// â”€â”€ TENASOSP Physique â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$tenasospPhysique = [
    [2024,'Quelle est l\'unitÃ© SI de la force ?',[
        ['A','Joule',false],['B','Pascal',false],['C','Newton',true,'La force se mesure en Newton (N). 1 N = 1 kgÂ·m/sÂ².'],['D','Watt',false]],'ELEMENTAIRE',1.0],
    [2024,'Ã‰noncer la deuxiÃ¨me loi de Newton.',[
        ['A','F = mv',false],['B','F = ma',true,'La 2Ã¨me loi de Newton : la force = masse Ã— accÃ©lÃ©ration (F = ma).'],['C','F = m/a',false],['D','F = mvÂ²',false]],'ELEMENTAIRE',1.0],
    [2024,'Un objet de masse 5 kg est soumis Ã  une accÃ©lÃ©ration de 3 m/sÂ². Quelle est la force appliquÃ©e ?',[
        ['A','1,67 N',false],['B','8 N',false],['C','15 N',true,'F = ma = 5 Ã— 3 = 15 N.'],['D','20 N',false]],'ELEMENTAIRE',1.0],
    [2024,'Quelle est la vitesse de la lumiÃ¨re dans le vide ?',[
        ['A','3 Ã— 10â¶ m/s',false],['B','3 Ã— 10â¸ m/s',true,'La vitesse de la lumiÃ¨re dans le vide est c = 3Ã—10â¸ m/s (environ 300 000 km/s).'],['C','3 Ã— 10Â¹â° m/s',false],['D','3 Ã— 10â´ m/s',false]],'ELEMENTAIRE',1.0],
    [2024,'Dans un circuit en sÃ©rie, la rÃ©sistance totale de deux rÃ©sistances Râ‚=10Î© et Râ‚‚=20Î© est :',[
        ['A','10 Î©',false],['B','30 Î©',true,'En sÃ©rie : R_total = Râ‚ + Râ‚‚ = 10 + 20 = 30 Î©.'],['C','6,7 Î©',false],['D','200 Î©',false]],'ELEMENTAIRE',1.0],
    [2024,'Quelle est la loi d\'Ohm ?',[
        ['A','U = R/I',false],['B','U = IR',true,'Loi d\'Ohm : U = RI (tension = rÃ©sistance Ã— intensitÃ©).'],['C','I = UR',false],['D','R = U + I',false]],'ELEMENTAIRE',1.0],
    [2023,'Une voiture passe de 0 Ã  90 km/h en 10 secondes. Quelle est son accÃ©lÃ©ration en m/sÂ² ?',[
        ['A','2,5 m/sÂ²',true,'90 km/h = 25 m/s. a = Î”v/Î”t = 25Ã·10 = 2,5 m/sÂ².'],['B','9 m/sÂ²',false],['C','90 m/sÂ²',false],['D','1 m/sÂ²',false]],'INTERMEDIAIRE',1.5],
    [2023,'Quelle est l\'Ã©nergie cinÃ©tique d\'un objet de masse 2 kg se dÃ©plaÃ§ant Ã  10 m/s ?',[
        ['A','20 J',false],['B','100 J',true,'Ec = Â½mvÂ² = Â½Ã—2Ã—10Â² = Â½Ã—2Ã—100 = 100 J.'],['C','200 J',false],['D','50 J',false]],'INTERMEDIAIRE',1.5],
    [2022,'Quel phÃ©nomÃ¨ne explique la formation d\'un arc-en-ciel ?',[
        ['A','RÃ©flexion totale',false],['B','Dispersion de la lumiÃ¨re',true,'L\'arc-en-ciel est dÃ» Ã  la dispersion de la lumiÃ¨re blanche en ses composantes (rouge Ã  violet) par les gouttes d\'eau.'],['C','Absorption lumineuse',false],['D','Diffraction',false]],'INTERMEDIAIRE',1.0],
    [2022,'L\'unitÃ© de mesure de la pression est :',[
        ['A','Newton',false],['B','Joule',false],['C','Pascal',true,'La pression se mesure en Pascal (Pa). 1 Pa = 1 N/mÂ².'],['D','Watt',false]],'ELEMENTAIRE',1.0],
];

foreach ($tenasospPhysique as [$an, $enonce, $opts, $diff, $pts]) {
    insertQ($pdo, $M['physique'], 'TENASOSP', $an, $enonce, $opts, $diff, $pts, 'Physique TENASOSP');
}

// â”€â”€ TENASOSP Biologie â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$tenasospBio = [
    [2024,'Quel est le rÃ´le de l\'ADN dans la cellule ?',[
        ['A','Produire l\'Ã©nergie',false],['B','Porter l\'information gÃ©nÃ©tique',true,'L\'ADN (acide dÃ©soxyribonuclÃ©ique) contient le code gÃ©nÃ©tique qui contrÃ´le le dÃ©veloppement et le fonctionnement des organismes.'],['C','Transporter l\'oxygÃ¨ne',false],['D','SynthÃ©tiser les lipides',false]],'ELEMENTAIRE',1.0],
    [2024,'Combien de chromosomes possÃ¨de une cellule humaine normale ?',[
        ['A','23',false],['B','46',true,'Les cellules humaines somatiques contiennent 46 chromosomes (23 paires). Les gamÃ¨tes en ont 23.'],['C','48',false],['D','92',false]],'ELEMENTAIRE',1.0],
    [2024,'Quel organite cellulaire est responsable de la synthÃ¨se des protÃ©ines ?',[
        ['A','Mitochondrie',false],['B','Ribosome',true,'Les ribosomes sont les sites de la synthÃ¨se des protÃ©ines. Ils lisent l\'ARNm pour assembler les acides aminÃ©s.'],['C','Noyau',false],['D','Vacuole',false]],'INTERMEDIAIRE',1.0],
    [2024,'Comment appelle-t-on la division cellulaire produisant deux cellules identiques ?',[
        ['A','MÃ©iose',false],['B','Mitose',true,'La mitose produit deux cellules filles identiques Ã  la cellule mÃ¨re (mÃªme nombre de chromosomes). La mÃ©iose produit des gamÃ¨tes.'],['C','CytokinÃ¨se',false],['D','Transcription',false]],'ELEMENTAIRE',1.0],
    [2023,'Quel hormone rÃ©gule la glycÃ©mie (taux de sucre dans le sang) ?',[
        ['A','AdrÃ©naline',false],['B','Insuline',true,'L\'insuline, produite par le pancrÃ©as, fait baisser la glycÃ©mie en facilitant l\'entrÃ©e du glucose dans les cellules.'],['C','Thyroxine',false],['D','Cortisol',false]],'ELEMENTAIRE',1.0],
    [2023,'Comment s\'appelle la structure qui protÃ¨ge et nourrit l\'embryon chez les mammifÃ¨res ?',[
        ['A','Amnios',false],['B','Placenta',true,'Le placenta relie l\'embryon Ã  l\'utÃ©rus maternel et assure les Ã©changes nutritifs et gazeux.'],['C','Chorion',false],['D','VÃ©sicule vitelline',false]],'ELEMENTAIRE',1.0],
    [2022,'Quelle est la fonction des globules rouges ?',[
        ['A','DÃ©fense immunitaire',false],['B','Transport de l\'oxygÃ¨ne',true,'Les Ã©rythrocytes (globules rouges) contiennent l\'hÃ©moglobine qui transporte l\'Oâ‚‚ des poumons vers les tissus.'],['C','Coagulation',false],['D','Production d\'anticorps',false]],'ELEMENTAIRE',1.0],
    [2022,'Qu\'est-ce que la photosynthÃ¨se produit en plus du glucose ?',[
        ['A','COâ‚‚',false],['B','Azote',false],['C','OxygÃ¨ne (Oâ‚‚)',true,'La photosynthÃ¨se : COâ‚‚ + Hâ‚‚O + lumiÃ¨re â†’ glucose + Oâ‚‚. L\'Oâ‚‚ est un sous-produit essentiel Ã  la vie.'],['D','Eau',false]],'ELEMENTAIRE',1.0],
    [2021,'Le VIH attaque principalement quel type de cellule ?',[
        ['A','Globules rouges',false],['B','Lymphocytes T CD4âº',true,'Le VIH infecte et dÃ©truit les lymphocytes T CD4âº, affaiblissant le systÃ¨me immunitaire et menant au SIDA.'],['C','Plaquettes',false],['D','Neurones',false]],'INTERMEDIAIRE',1.5],
];

foreach ($tenasospBio as [$an, $enonce, $opts, $diff, $pts]) {
    insertQ($pdo, $M['biologie'], 'TENASOSP', $an, $enonce, $opts, $diff, $pts, 'Biologie TENASOSP');
}

// â”€â”€ TENASOSP FranÃ§ais â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$tenasospFr = [
    [2024,'Qu\'est-ce qu\'une mÃ©taphore ?',[
        ['A','Une comparaison avec Â« comme Â»',false],['B','Une figure de style sans outil de comparaison',true,'La mÃ©taphore affirme directement qu\'un objet est un autre : Â« La vie est un long fleuve tranquille Â». La comparaison utilise Â« comme Â».'],['C','Une rÃ©pÃ©tition de sons',false],['D','Une exagÃ©ration',false]],'INTERMEDIAIRE',1.0],
    [2024,'Conjuguer Â« finir Â» Ã  la 1Ã¨re personne du pluriel du subjonctif prÃ©sent.',[
        ['A','nous finissons',false],['B','nous finissions',false],['C','que nous finissions',true,'Subjonctif prÃ©sent de Â« finir Â» : que je finisse, que tu finisses, qu\'il finisse, que nous finissions...'],['D','que nous finirons',false]],'INTERMEDIAIRE',1.5],
    [2024,'Quelle est la fonction de Â« rapidement Â» dans : Â« Il court rapidement Â» ?',[
        ['A','ComplÃ©ment d\'objet direct',false],['B','ComplÃ©ment circonstanciel de maniÃ¨re',true,'Â« Rapidement Â» indique la faÃ§on dont il court â€” c\'est un CCM (complÃ©ment circonstanciel de maniÃ¨re).'],['C','Attribut du sujet',false],['D','ComplÃ©ment du nom',false]],'INTERMEDIAIRE',1.0],
    [2023,'Quel est le mode verbal de : Â« Prends ton livre ! Â»',[
        ['A','Indicatif',false],['B','Subjonctif',false],['C','ImpÃ©ratif',true,'L\'impÃ©ratif exprime un ordre, un conseil ou une interdiction. Ici : ordre direct.'],['D','Conditionnel',false]],'ELEMENTAIRE',1.0],
    [2023,'DÃ©finir le terme Â« hyperbole Â».',[
        ['A','Figure d\'insistance par la rÃ©pÃ©tition',false],['B','Figure d\'exagÃ©ration pour renforcer l\'effet',true,'L\'hyperbole est une exagÃ©ration stylistique : Â« J\'ai attendu une Ã©ternitÃ©. Â»'],['C','Comparaison sans outil',false],['D','Personnification d\'un objet',false]],'INTERMEDIAIRE',1.0],
];

foreach ($tenasospFr as [$an, $enonce, $opts, $diff, $pts]) {
    insertQ($pdo, $M['francais'], 'TENASOSP', $an, $enonce, $opts, $diff, $pts, 'FranÃ§ais TENASOSP');
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// â–ˆâ–ˆ  EXAMEN D'Ã‰TAT â€” Fin secondaire (toutes sections)
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

// â”€â”€ EXAMEN D'Ã‰TAT MathÃ©matiques â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$etatMaths = [
    [2024,'RÃ©soudre : 2xÂ² âˆ’ 8 = 0',[
        ['A','x = Â±4',false],['B','x = Â±2',true,'2xÂ² = 8, xÂ² = 4, x = Â±2.'],['C','x = Â±8',false],['D','x = 2 seulement',false]],'ELEMENTAIRE',1.0],
    [2024,'Quelle est la limite de (xÂ²âˆ’1)/(xâˆ’1) quand xâ†’1 ?',[
        ['A','0',false],['B','1',false],['C','2',true,'(xÂ²âˆ’1)/(xâˆ’1) = (x+1)(xâˆ’1)/(xâˆ’1) = x+1. Quand xâ†’1 : 1+1=2.'],['D','âˆž',false]],'INTERMEDIAIRE',2.0],
    [2024,'Calculer C(5,2) (combinaisons de 5 Ã©lÃ©ments pris 2 Ã  2).',[
        ['A','10',true,'C(5,2) = 5!/(2!Ã—3!) = (5Ã—4)/(2Ã—1) = 10.'],['B','20',false],['C','15',false],['D','25',false]],'INTERMEDIAIRE',1.5],
    [2024,'Dans un repÃ¨re orthonormÃ©, quelle est la distance entre A(1,2) et B(4,6) ?',[
        ['A','3',false],['B','4',false],['C','5',true,'d = âˆš((4âˆ’1)Â²+(6âˆ’2)Â²) = âˆš(9+16) = âˆš25 = 5.'],['D','7',false]],'INTERMEDIAIRE',2.0],
    [2024,'RÃ©soudre l\'inÃ©quation : 2x âˆ’ 3 > 7',[
        ['A','x > 2',false],['B','x > 5',true,'2x > 7+3 = 10, x > 10/2 = 5.'],['C','x > 3',false],['D','x > 4',false]],'ELEMENTAIRE',1.0],
    [2023,'Quel est le domaine de dÃ©finition de f(x) = âˆš(xâˆ’3) ?',[
        ['A','x < 3',false],['B','x = 3',false],['C','x â‰¥ 3',true,'La racine carrÃ©e est dÃ©finie pour xâˆ’3 â‰¥ 0, soit x â‰¥ 3.'],['D','x > 0',false]],'INTERMEDIAIRE',1.5],
    [2023,'Calculer la somme des 10 premiers entiers naturels.',[
        ['A','45',false],['B','50',false],['C','55',true,'Sn = n(n+1)/2 = 10Ã—11/2 = 55.'],['D','60',false]],'ELEMENTAIRE',1.0],
    [2023,'Quelle est la valeur de cos(Ï€/3) ?',[
        ['A','âˆš3/2',false],['B','1/2',true,'cos(60Â°) = cos(Ï€/3) = 1/2. Ã€ mÃ©moriser avec sin(60Â°)=âˆš3/2.'],['C','âˆš2/2',false],['D','1',false]],'INTERMEDIAIRE',1.5],
    [2022,'Calculer la dÃ©rivÃ©e de g(x) = sin(2x)',[
        ['A','cos(2x)',false],['B','2cos(2x)',true,'DÃ©rivÃ©e de sin(u) = u\'Â·cos(u). Ici u=2x, u\'=2, donc g\'(x)=2cos(2x).'],['C','âˆ’2cos(2x)',false],['D','2sin(2x)',false]],'INTERMEDIAIRE',2.0],
    [2022,'Quel est le nombre complexe conjuguÃ© de z = 3 + 4i ?',[
        ['A','3 âˆ’ 4i',true,'Le conjuguÃ© de z = a + bi est zÌ„ = a âˆ’ bi. Donc conjuguÃ© de 3+4i est 3âˆ’4i.'],['B','âˆ’3 + 4i',false],['C','4 + 3i',false],['D','3 + 4i',false]],'ELEMENTAIRE',1.0],
    [2021,'Quelle est la valeur de tan(45Â°) ?',[
        ['A','0',false],['B','âˆš3/2',false],['C','1',true,'tan(45Â°) = sin(45Â°)/cos(45Â°) = (âˆš2/2)/(âˆš2/2) = 1.'],['D','âˆš3',false]],'ELEMENTAIRE',1.0],
];

foreach ($etatMaths as [$an, $enonce, $opts, $diff, $pts]) {
    insertQ($pdo, $M['maths'], 'EXAMEN_ETAT', $an, $enonce, $opts, $diff, $pts, 'MathÃ©matiques Examen d\'Ã‰tat');
}

// â”€â”€ EXAMEN D'Ã‰TAT FranÃ§ais â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$etatFr = [
    [2024,'Quelle figure de style apparaÃ®t dans : Â« Le vent hurlait dans la nuit Â» ?',[
        ['A','MÃ©taphore',false],['B','Personnification',true,'Attribuer des actions humaines (hurler) Ã  une chose inanimÃ©e (le vent) est une personnification.'],['C','AllitÃ©ration',false],['D','Hyperbole',false]],'INTERMEDIAIRE',1.0],
    [2024,'Quel est le mode de Â« il faut que tu viennes Â» ?',[
        ['A','Indicatif',false],['B','Conditionnel',false],['C','Subjonctif',true,'AprÃ¨s Â« il faut que Â», on utilise le subjonctif.'],['D','Infinitif',false]],'ELEMENTAIRE',1.0],
    [2024,'Expliquer la diffÃ©rence entre Â« davantage Â» et Â« d\'avantage Â».',[
        ['A','Aucune diffÃ©rence',false],['B','Davantage = plus ; d\'avantage = de bÃ©nÃ©fice',true,'Â« Davantage Â» (adverbe) = plus. Â« D\'avantage Â» = de [l\']avantage (nom). Ex : Â« Je n\'ai d\'avantage sur personne. Â»'],['C','D\'avantage = plus ; davantage = bÃ©nÃ©fice',false],['D','L\'un est masculin, l\'autre fÃ©minin',false]],'AVANCE',2.0],
    [2023,'Conjuguer Â« vouloir Â» Ã  la 2Ã¨me personne du pluriel du conditionnel prÃ©sent.',[
        ['A','vous voulez',false],['B','vous voudrez',false],['C','vous voudriez',true,'Conditionnel prÃ©sent de Â« vouloir Â» : je voudrais, tu voudrais, il voudrait, nous voudrions, vous voudriez, ils voudraient.'],['D','vous voudriez',true]],'INTERMEDIAIRE',1.5],
    [2023,'Quelle est la proposition subordonnÃ©e dans : Â« Je pense qu\'il viendra. Â» ?',[
        ['A','Je pense',false],['B','qu\'il viendra',true,'Â« qu\'il viendra Â» est une proposition subordonnÃ©e conjonctive complÃ©tive (COD du verbe penser).'],['C','il viendra',false],['D','Je pense qu\'il',false]],'INTERMEDIAIRE',1.0],
    [2022,'Qu\'est-ce qu\'un oxymore ?',[
        ['A','RÃ©pÃ©tition d\'un mÃªme mot',false],['B','Association de termes contradictoires',true,'L\'oxymore rapproche deux mots de sens opposÃ©s : Â« une obscure clartÃ© Â», Â« un silence Ã©loquent Â».'],['C','Comparaison explicite',false],['D','Inversion du sujet et du verbe',false]],'INTERMEDIAIRE',1.0],
    [2021,'Distinguer les homophones Â« leur Â» et Â« leurs Â».',[
        ['A','Leur est toujours invariable',false],['B','Leurs prend un s quand il prÃ©cÃ¨de un nom pluriel',true,'Â« Leur Â» pronom personnel est invariable. Â« Leurs Â» dÃ©terminant possessif s\'accorde en nombre : leurs livres.'],['C','Ils s\'emploient toujours de la mÃªme faÃ§on',false],['D','Leur ne s\'emploie qu\'avec des animaux',false]],'INTERMEDIAIRE',1.5],
];

foreach ($etatFr as [$an, $enonce, $opts, $diff, $pts]) {
    insertQ($pdo, $M['francais'], 'EXAMEN_ETAT', $an, $enonce, $opts, $diff, $pts, 'FranÃ§ais Examen d\'Ã‰tat');
}

// â”€â”€ EXAMEN D'Ã‰TAT Chimie â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$etatChimie = [
    [2024,'Quel est le principe de la conservation de la masse dans une rÃ©action chimique ?',[
        ['A','La masse augmente pendant la rÃ©action',false],['B','La masse des rÃ©actifs = masse des produits',true,'Loi de Lavoisier : Â« Rien ne se perd, rien ne se crÃ©e, tout se transforme. Â» La masse totale est conservÃ©e.'],['C','La masse des produits est toujours infÃ©rieure',false],['D','Les rÃ©actifs disparaissent complÃ¨tement',false]],'ELEMENTAIRE',1.0],
    [2024,'Quel est le volume molaire d\'un gaz dans les conditions normales (0Â°C, 1 atm) ?',[
        ['A','18 L/mol',false],['B','22,4 L/mol',true,'Ã€ 0Â°C et 1 atm (CNTP), 1 mole de tout gaz idÃ©al occupe 22,4 litres.'],['C','24 L/mol',false],['D','11,2 L/mol',false]],'ELEMENTAIRE',1.0],
    [2024,'Quelle est la formule de l\'acide sulfurique ?',[
        ['A','HCl',false],['B','HNOâ‚ƒ',false],['C','Hâ‚‚SOâ‚„',true,'L\'acide sulfurique : Hâ‚‚SOâ‚„ (deux protons Hâº et un ion sulfate SOâ‚„Â²â»).'],['D','Hâ‚ƒPOâ‚„',false]],'ELEMENTAIRE',1.0],
    [2023,'Dans la rÃ©action : Zn + Hâ‚‚SOâ‚„ â†’ ZnSOâ‚„ + Hâ‚‚, quel Ã©lÃ©ment est oxydÃ© ?',[
        ['A','H',false],['B','S',false],['C','Zn',true,'Zn passe de l\'Ã©tat 0 (mÃ©tal) Ã  ZnÂ²âº (dans ZnSOâ‚„) â†’ il perd des Ã©lectrons â†’ il est oxydÃ©.'],['D','O',false]],'INTERMEDIAIRE',2.0],
    [2023,'Combien de moles de NaOH sont nÃ©cessaires pour neutraliser 2 moles de Hâ‚‚SOâ‚„ ?',[
        ['A','1 mol',false],['B','2 mol',false],['C','4 mol',true,'Hâ‚‚SOâ‚„ + 2NaOH â†’ Naâ‚‚SOâ‚„ + 2Hâ‚‚O. Le rapport est 1:2, donc 2 mol Hâ‚‚SOâ‚„ Ã— 2 = 4 mol NaOH.'],['D','3 mol',false]],'INTERMEDIAIRE',2.0],
    [2022,'Quel est le nom de CHâ‚ƒCOOH ?',[
        ['A','MÃ©thanol',false],['B','Acide acÃ©tique',true,'CHâ‚ƒCOOH est l\'acide acÃ©tique (ou acide Ã©thanoÃ¯que) â€” principal composant du vinaigre.'],['C','Ã‰thanol',false],['D','Acide formique',false]],'ELEMENTAIRE',1.0],
    [2021,'Comment calcule-t-on le nombre de moles d\'un gaz Ã  T et P donnÃ©es avec la loi des gaz parfaits ?',[
        ['A','n = PV/RT',true,'Loi des gaz parfaits : PV = nRT. Donc n = PV/RT oÃ¹ R = 8,314 J/(molÂ·K).'],['B','n = RT/PV',false],['C','n = P/VRT',false],['D','n = PVT/R',false]],'AVANCE',2.0],
];

foreach ($etatChimie as [$an, $enonce, $opts, $diff, $pts]) {
    insertQ($pdo, $M['chimie'], 'EXAMEN_ETAT', $an, $enonce, $opts, $diff, $pts, 'Chimie Examen d\'Ã‰tat');
}

// â”€â”€ EXAMEN D'Ã‰TAT Physique â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$etatPhysique = [
    [2024,'Ã‰noncer le principe de conservation de l\'Ã©nergie.',[
        ['A','L\'Ã©nergie peut Ãªtre crÃ©Ã©e lors d\'un choc',false],['B','L\'Ã©nergie totale d\'un systÃ¨me isolÃ© reste constante',true,'L\'Ã©nergie ne peut ni Ãªtre crÃ©Ã©e ni Ãªtre dÃ©truite â€” elle se transforme d\'une forme Ã  une autre.'],['C','L\'Ã©nergie cinÃ©tique est toujours maximale',false],['D','L\'Ã©nergie augmente avec la chaleur',false]],'ELEMENTAIRE',1.0],
    [2024,'Calculer la puissance d\'un appareil consommant 3 000 J en 60 secondes.',[
        ['A','20 W',false],['B','50 W',true,'P = E/t = 3 000 J Ã· 60 s = 50 W.'],['C','180 000 W',false],['D','3 060 W',false]],'ELEMENTAIRE',1.0],
    [2024,'Quel est l\'indice de rÃ©fraction si la vitesse de la lumiÃ¨re dans un milieu est 2Ã—10â¸ m/s ?',[
        ['A','1',false],['B','1,5',true,'n = c/v = (3Ã—10â¸)/(2Ã—10â¸) = 1,5.'],['C','2',false],['D','0,5',false]],'INTERMEDIAIRE',2.0],
    [2023,'Un fil conducteur de rÃ©sistance 10 Î© est parcouru par un courant de 3 A. Quelle est la tension ?',[
        ['A','3,3 V',false],['B','13 V',false],['C','30 V',true,'U = RI = 10 Ã— 3 = 30 V (loi d\'Ohm).'],['D','7 V',false]],'ELEMENTAIRE',1.0],
    [2023,'Qu\'est-ce que la frÃ©quence d\'une onde sonore exprime ?',[
        ['A','L\'amplitude du son',false],['B','Le nombre de vibrations par seconde',true,'La frÃ©quence (en Hz) est le nombre d\'oscillations complÃ¨tes par seconde. Elle dÃ©termine la hauteur (grave ou aigu) du son.'],['C','La vitesse de propagation',false],['D','L\'intensitÃ© du son',false]],'ELEMENTAIRE',1.0],
    [2022,'La force gravitationnelle entre deux masses mâ‚ et mâ‚‚ sÃ©parÃ©es par une distance d est :',[
        ['A','F = Gmâ‚mâ‚‚/d',false],['B','F = Gmâ‚mâ‚‚/dÂ²',true,'Loi de Newton : F = GÂ·mâ‚Â·mâ‚‚/dÂ² oÃ¹ G = 6,67Ã—10â»Â¹Â¹ NÂ·mÂ²/kgÂ².'],['C','F = G(mâ‚+mâ‚‚)/dÂ²',false],['D','F = mâ‚mâ‚‚d/G',false]],'INTERMEDIAIRE',2.0],
    [2021,'Quel est le type de miroir utilisÃ© dans les phares de voiture ?',[
        ['A','Miroir plan',false],['B','Miroir concave',true,'Les phares utilisent un miroir concave parabolique qui concentre la lumiÃ¨re en un faisceau parallÃ¨le.'],['C','Miroir convexe',false],['D','Lentille convergente',false]],'ELEMENTAIRE',1.0],
];

foreach ($etatPhysique as [$an, $enonce, $opts, $diff, $pts]) {
    insertQ($pdo, $M['physique'], 'EXAMEN_ETAT', $an, $enonce, $opts, $diff, $pts, 'Physique Examen d\'Ã‰tat');
}

// â”€â”€ EXAMEN D'Ã‰TAT Biologie â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$etatBio = [
    [2024,'Qu\'est-ce que la mÃ©iose produit ?',[
        ['A','Deux cellules identiques Ã  la cellule mÃ¨re',false],['B','Quatre cellules haploÃ¯des (gamÃ¨tes)',true,'La mÃ©iose produit 4 cellules avec la moitiÃ© des chromosomes (n=23 chez l\'humain) â€” ce sont les gamÃ¨tes (spermatozoÃ¯des, ovules).'],['C','Une seule cellule diploÃ¯de',false],['D','Deux cellules diploÃ¯des diffÃ©rentes',false]],'ELEMENTAIRE',1.0],
    [2024,'Quel est le rÃ´le de la mitochondrie ?',[
        ['A','SynthÃ¨se des protÃ©ines',false],['B','Production d\'Ã©nergie (ATP) par respiration cellulaire',true,'La mitochondrie est le Â« gÃ©nÃ©rateur Â» de la cellule â€” elle produit l\'ATP par la respiration cellulaire aÃ©robie.'],['C','Stockage de l\'ADN',false],['D','Transport des lipides',false]],'ELEMENTAIRE',1.0],
    [2024,'Comment s\'appelle la mutation oÃ¹ un chromosome surnumÃ©raire apparaÃ®t ?',[
        ['A','DÃ©lÃ©tion',false],['B','Translocation',false],['C','Trisomie',true,'La trisomie est la prÃ©sence d\'un chromosome en 3 exemplaires au lieu de 2. La trisomie 21 cause le syndrome de Down.'],['D','Inversion',false]],'INTERMEDIAIRE',1.5],
    [2023,'Quelle est la diffÃ©rence entre une cellule procaryote et eucaryote ?',[
        ['A','Les procaryotes ont un noyau, les eucaryotes non',false],['B','Les procaryotes n\'ont pas de noyau dÃ©limitÃ© par une membrane',true,'Procaryotes (bactÃ©ries) = pas de vrai noyau. Eucaryotes (animaux, plantes, champignons) = noyau avec membrane nuclÃ©aire.'],['C','Les deux types ont un noyau',false],['D','Les eucaryotes sont toujours plus petits',false]],'INTERMEDIAIRE',1.0],
    [2023,'Quel processus permet Ã  l\'organisme de lutter contre les agents pathogÃ¨nes ?',[
        ['A','Phagocytose uniquement',false],['B','RÃ©ponse immunitaire (immunitÃ©)',true,'L\'immunitÃ© comprend l\'immunitÃ© innÃ©e (phagocytose, inflammation) et adaptative (lymphocytes T et B, anticorps).'],['C','Respiration cellulaire',false],['D','Division cellulaire',false]],'ELEMENTAIRE',1.0],
    [2022,'Quel gÃ¨ne est portÃ© sur le chromosome Y ?',[
        ['A','GÃ¨ne de l\'hÃ©mophilie',false],['B','GÃ¨ne SRY (dÃ©termine le sexe masculin)',true,'Le gÃ¨ne SRY sur le chromosome Y dÃ©clenche le dÃ©veloppement masculin chez les mammifÃ¨res.'],['C','GÃ¨ne de la drÃ©panocytose',false],['D','GÃ¨ne BRCA1',false]],'AVANCE',2.0],
    [2021,'Qu\'est-ce que la sÃ©lection naturelle selon Darwin ?',[
        ['A','Les organismes les plus forts survivent toujours',false],['B','Les individus les mieux adaptÃ©s survivent et se reproduisent davantage',true,'Darwin : les individus avec des traits avantageux survivent et transmettent leurs gÃ¨nes â€” c\'est la sÃ©lection naturelle.'],['C','L\'environnement change les gÃ¨nes directement',false],['D','Les organismes choisissent leurs mutations',false]],'ELEMENTAIRE',1.0],
];

foreach ($etatBio as [$an, $enonce, $opts, $diff, $pts]) {
    insertQ($pdo, $M['biologie'], 'EXAMEN_ETAT', $an, $enonce, $opts, $diff, $pts, 'Biologie Examen d\'Ã‰tat');
}

// â”€â”€ EXAMEN D'Ã‰TAT Histoire-GÃ©o â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$etatHG = [
    [2024,'Quand et oÃ¹ a Ã©tÃ© fondÃ©e l\'Organisation des Nations Unies (ONU) ?',[
        ['A','1919, Paris',false],['B','1945, San Francisco',true,'L\'ONU a Ã©tÃ© fondÃ©e le 24 octobre 1945 Ã  San Francisco aprÃ¨s la Seconde Guerre mondiale.'],['C','1948, GenÃ¨ve',false],['D','1950, New York',false]],'ELEMENTAIRE',1.0],
    [2024,'Quel Ã©vÃ©nement a dÃ©clenchÃ© la PremiÃ¨re Guerre mondiale en 1914 ?',[
        ['A','Invasion de la Pologne',false],['B','Assassinat de l\'archiduc FranÃ§ois-Ferdinand Ã  Sarajevo',true,'L\'attentat contre l\'archiduc austro-hongrois Ã  Sarajevo le 28 juin 1914 a dÃ©clenchÃ© la PremiÃ¨re Guerre mondiale.'],['C','RÃ©volution russe',false],['D','TraitÃ© de Versailles',false]],'ELEMENTAIRE',1.0],
    [2024,'Quel pays a colonisÃ© le Congo (actuelle RDC) jusqu\'en 1960 ?',[
        ['A','France',false],['B','Portugal',false],['C','Belgique',true,'Le Congo a Ã©tÃ© colonisÃ© par la Belgique depuis 1908 (aprÃ¨s le Congo de LÃ©opold II) jusqu\'Ã  l\'indÃ©pendance du 30 juin 1960.'],['D','Angleterre',false]],'ELEMENTAIRE',1.0],
    [2023,'Quel est le premier pays africain Ã  avoir obtenu son indÃ©pendance ?',[
        ['A','Ghana',false],['B','Ã‰thiopie',false],['C','HaÃ¯ti (CaraÃ¯bes â€” premier Ã‰tat noir libre, 1804)',false],['D','Liberia (1847) â€” 1er pays africain indÃ©pendant reconnu',true,'Le Liberia (1847) est gÃ©nÃ©ralement citÃ© comme le premier pays africain indÃ©pendant. L\'Ã‰thiopie n\'a jamais Ã©tÃ© rÃ©ellement colonisÃ©e.'],],'AVANCE',2.0],
    [2023,'OÃ¹ se situe le mont Kilimandjaro ?',[
        ['A','Kenya',false],['B','Tanzanie',true,'Le Kilimandjaro (5 895 m), point culminant de l\'Afrique, se trouve en Tanzanie.'],['C','Ã‰thiopie',false],['D','RDC',false]],'ELEMENTAIRE',1.0],
    [2022,'Quel traitÃ© a mis fin Ã  la PremiÃ¨re Guerre mondiale ?',[
        ['A','TraitÃ© de Paris',false],['B','TraitÃ© de Versailles',true,'Le TraitÃ© de Versailles (28 juin 1919) a officiellement mis fin Ã  la PremiÃ¨re Guerre mondiale.'],['C','Accord de Munich',false],['D','Pacte de GenÃ¨ve',false]],'ELEMENTAIRE',1.0],
    [2021,'Quel est le dÃ©sert le plus grand du monde ?',[
        ['A','Sahara',false],['B','Gobi',false],['C','Antarctique',true,'L\'Antarctique est le plus grand dÃ©sert du monde (14 millions de kmÂ²). Le Sahara est le plus grand dÃ©sert chaud.'],['D','Arabian Desert',false]],'INTERMEDIAIRE',1.0],
];

foreach ($etatHG as [$an, $enonce, $opts, $diff, $pts]) {
    insertQ($pdo, $M['histgeo'], 'EXAMEN_ETAT', $an, $enonce, $opts, $diff, $pts, 'Histoire-GÃ©o Examen d\'Ã‰tat');
}

// â”€â”€ EXAMEN D'Ã‰TAT Anglais â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$etatAnglais = [
    [2024,'Rewrite in passive voice: "The teacher corrects the tests."',[
        ['A','The tests corrected by the teacher.',false],['B','The tests are corrected by the teacher.',true,'Passive voice: subject + to be + past participle + by + agent. Present: are corrected.'],['C','The tests were corrected by the teacher.',false],['D','The teacher is corrected the tests.',false]],'INTERMEDIAIRE',1.0],
    [2024,'Choose the correct form: "If I ___ you, I would study harder."',[
        ['A','am',false],['B','was/were',true,'Second conditional (unreal present/future): "If I were you..." â€” "were" is the subjunctive form used here.'],['C','will be',false],['D','have been',false]],'INTERMEDIAIRE',1.5],
    [2024,'What does "nevertheless" mean?',[
        ['A','As a result',false],['B','However / in spite of that',true,'"Nevertheless" is a conjunction meaning "however" or "in spite of that" â€” used to contrast two ideas.'],['C','Moreover',false],['D','Therefore',false]],'INTERMEDIAIRE',1.0],
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
    insertQ($pdo, $M['anglais'], 'EXAMEN_ETAT', $an, $enonce, $opts, $diff, $pts, 'Anglais Examen d\'Ã‰tat');
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// RÃ©sumÃ©
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
echo "âœ… TerminÃ© !\n";
echo "   Questions insÃ©rÃ©es : $inserted\n";
echo "   Questions ignorÃ©es (doublons) : $skipped\n";

// RÃ©sumÃ© par type
$stats = $pdo->query(
    "SELECT exam_type, COUNT(*) as nb FROM question_bank WHERE status='PUBLIE' GROUP BY exam_type"
)->fetchAll(PDO::FETCH_ASSOC);
echo "\nDistribution totale :\n";
foreach ($stats as $s) {
    echo "  {$s['exam_type']} : {$s['nb']} questions\n";
}

