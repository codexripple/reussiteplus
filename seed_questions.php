<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_admin(); // Script restreint aux admins
/**
 * seed_questions.php â€” Seeder dÃ©diÃ© aux questions QCM
 * RÃ‰USSITE+ | Localhost only
 *
 * Usage : http://localhost/reussiteplus/seed_questions.php
 *         ou : & "C:\xampp\php\php.exe" seed_questions.php
 *
 * â€¢ InsÃ¨re uniquement des questions (pas d'utilisateurs, pas d'archives)
 * â€¢ VÃ©rifie les doublons par Ã©noncÃ© avant insertion
 * â€¢ Affiche un rapport dÃ©taillÃ© par matiÃ¨re
 */

if ($_SERVER['SERVER_NAME'] !== 'localhost' && php_uname('n') !== gethostname()) {
    http_response_code(403); exit('Forbidden');
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$pdo = db();
header('Content-Type: text/plain; charset=utf-8');

/* â”€â”€ Helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
function log_msg(string $msg): void { echo $msg . "\n"; flush(); }

function get_matiere_id(PDO $pdo, string $nom): ?int {
    static $cache = [];
    if (isset($cache[$nom])) return $cache[$nom];
    $row = $pdo->query("SELECT id FROM matieres WHERE nom = " . $pdo->quote($nom) . " LIMIT 1")->fetch();
    return $cache[$nom] = $row ? (int)$row['id'] : null;
}

function question_exists(PDO $pdo, string $enonce): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE enonce = ?");
    $stmt->execute([$enonce]);
    return (int)$stmt->fetchColumn() > 0;
}

function insert_q(PDO $pdo, int $matId, string $enonce, string $diff, string $src, array $opts): bool {
    if (question_exists($pdo, $enonce)) return false;

    $stQ = $pdo->prepare("INSERT INTO questions (matiere_id,enonce,difficulte,source,actif,created_at)
                          VALUES (?,?,?,?,1,NOW())");
    $stQ->execute([$matId, $enonce, $diff, $src]);
    $qId = (int)$pdo->lastInsertId();

    $stO = $pdo->prepare("INSERT INTO options_reponse (question_id,lettre,texte,est_correcte)
                          VALUES (?,?,?,?)");
    foreach ($opts as [$lettre, $texte, $correct]) {
        $stO->execute([$qId, $lettre, $texte, (int)$correct]);
    }
    return true;
}

/* â”€â”€ Chargement des matiÃ¨res â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
log_msg("â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•");
log_msg("  RÃ‰USSITE+ â€” seed_questions.php");
log_msg("  DÃ©marrage : " . date('Y-m-d H:i:s'));
log_msg("â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•\n");

$matMapping = [
    'maths'    => 'MathÃ©matiques',
    'francais' => 'FranÃ§ais',
    'chimie'   => 'Chimie',
    'physique' => 'Physique',
    'biologie' => 'Biologie',
    'histgeo'  => 'Histoire-GÃ©ographie',
    'anglais'  => 'Anglais',
    'sciences' => 'Sciences',
];

$matIds = [];
foreach ($matMapping as $key => $nom) {
    $id = get_matiere_id($pdo, $nom);
    if (!$id) {
        log_msg("  âœ— MatiÃ¨re introuvable : $nom â€” crÃ©e-la d'abord via seed.php");
        exit(1);
    }
    $matIds[$key] = $id;
}
log_msg("  âœ“ " . count($matIds) . " matiÃ¨res chargÃ©es\n");

/* â”€â”€ Dataset : 75 questions thÃ©matiques (10 niveaux ENAFEP / TENASOSP 2023-2025) */
$dataset = [

  /* â•â•â•â•â•â•â•â•â•â•â•â• MATHÃ‰MATIQUES (10) â•â•â•â•â•â•â•â•â•â•â•â• */
  [$matIds['maths'], 'Quel est le PGCD de 84 et 56 ?',                                               'ELEMENTAIRE',   'ENAFEP',     [['A','28',1], ['B','14',0], ['C','42',0], ['D','7',0]]],
  [$matIds['maths'], 'Un article coÃ»te 1 200 CDF. AprÃ¨s une remise de 15 %, il vaut :',              'ELEMENTAIRE',   'ENAFEP',     [['A','1 020 CDF',1], ['B','1 020 CDF',0], ['C','1 080 CDF',0], ['D','1 008 CDF',0]]],
  [$matIds['maths'], 'Quelle est l\'Ã©quation du cercle de centre O(2;-1) et de rayon 3 ?',          'AVANCE',        'EXAMEN_ETAT',[['A','(x-2)Â²+(y+1)Â²=9',1], ['B','(x+2)Â²+(y-1)Â²=9',0], ['C','(x-2)Â²+(y+1)Â²=3',0], ['D','xÂ²+yÂ²=9',0]]],
  [$matIds['maths'], 'Calculer la limite de (xÂ²-4)/(x-2) quand xâ†’2.',                                'AVANCE',        'EXAMEN_ETAT',[['A','4',1], ['B','0',0], ['C','âˆž',0], ['D','2',0]]],
  [$matIds['maths'], 'Un triangle rectangle a des cathÃ¨tes 6 et 8. Son hypotÃ©nuse vaut :',           'ELEMENTAIRE',   'ENAFEP',     [['A','10',1], ['B','14',0], ['C','7',0], ['D','âˆš100',0]]],
  [$matIds['maths'], 'Calculer : 7 Ã— (3 + 2Â²) âˆ’ 4',                                                 'ELEMENTAIRE',   'ENAFEP',     [['A','45',1], ['B','49',0], ['C','51',0], ['D','39',0]]],
  [$matIds['maths'], 'La dÃ©rivÃ©e de f(x) = xÂ³ âˆ’ 5x + 2 est :',                                      'AVANCE',        'EXAMEN_ETAT',[['A','3xÂ² âˆ’ 5',1], ['B','3xÂ² + 2',0], ['C','xÂ³ âˆ’ 5',0], ['D','3x âˆ’ 5',0]]],
  [$matIds['maths'], 'RÃ©soudre : |2x âˆ’ 3| = 7',                                                      'INTERMEDIAIRE', 'TENASOSP',   [['A','x = 5 ou x = âˆ’2',1], ['B','x = 5',0], ['C','x = 2',0], ['D','x = âˆ’2',0]]],
  [$matIds['maths'], 'Dans un sac : 4 rouges, 3 vertes, 2 bleues. P(rouge) = ?',                    'ELEMENTAIRE',   'ENAFEP',     [['A','4/9',1], ['B','1/4',0], ['C','3/9',0], ['D','4/3',0]]],
  [$matIds['maths'], 'Quel est le volume d\'un cylindre de r=5 cm et h=10 cm ?',                    'INTERMEDIAIRE', 'TENASOSP',   [['A','250Ï€ cmÂ³',1], ['B','500Ï€ cmÂ³',0], ['C','100Ï€ cmÂ³',0], ['D','50Ï€ cmÂ³',0]]],

  /* â•â•â•â•â•â•â•â•â•â•â•â• FRANÃ‡AIS (10) â•â•â•â•â•â•â•â•â•â•â•â• */
  [$matIds['francais'], 'Identifier le complÃ©ment de nom dans : "Le livre de mon frÃ¨re est neuf."',  'ELEMENTAIRE',   'ENAFEP',     [['A','de mon frÃ¨re',1], ['B','Le livre',0], ['C','est neuf',0], ['D','mon frÃ¨re',0]]],
  [$matIds['francais'], 'Conjuguer "se souvenir" au passÃ© composÃ©, 1Ã¨re pers. sing. :',             'ELEMENTAIRE',   'ENAFEP',     [['A','je me suis souvenu(e)',1], ['B','je me souvins',0], ['C','j\'ai souvenu',0], ['D','je me souviendrais',0]]],
  [$matIds['francais'], '"Il ne cesse de parler." â€” La nÃ©gation "ne...de" est-elle standard ?',     'AVANCE',        'EXAMEN_ETAT',[['A','Oui, forme standard : ne...de + infinitif',1], ['B','Non, incorrecte',0], ['C','Registre familier uniquement',0], ['D','Appartient au vieux franÃ§ais',0]]],
  [$matIds['francais'], 'Quel est le sens du prÃ©fixe "mal-" dans "malchance" ?',                    'DEBUTANT',      'ENAFEP',     [['A','Mauvais / contraire positif',1], ['B','Sous',0], ['C','TrÃ¨s',0], ['D','Avec',0]]],
  [$matIds['francais'], 'La phrase "Il fait chaud." est de type :',                                  'ELEMENTAIRE',   'ENAFEP',     [['A','DÃ©clarative',1], ['B','Interrogative',0], ['C','Exclamative',0], ['D','ImpÃ©rative',0]]],
  [$matIds['francais'], 'Dans "Vouloir, c\'est pouvoir.", le sujet du verbe "est" est :',            'AVANCE',        'EXAMEN_ETAT',[['A','Vouloir (proposition infinitive)',1], ['B','c\'',0], ['C','pouvoir',0], ['D','Il (absent)',0]]],
  [$matIds['francais'], 'Identifier la mÃ©tonymie dans : "Il a lu tout Voltaire."',                   'AVANCE',        'EXAMEN_ETAT',[['A','Voltaire = l\'Å“uvre de Voltaire',1], ['B','Voltaire = la ville de Voltaire',0], ['C','C\'est une hyperbole',0], ['D','C\'est une synecdoque',0]]],
  [$matIds['francais'], 'Quel est le pluriel de "bail" ?',                                           'INTERMEDIAIRE', 'ENAFEP',     [['A','baux',1], ['B','bails',0], ['C','bailes',0], ['D','baulx',0]]],
  [$matIds['francais'], 'Comment appelle-t-on le rÃ©sumÃ© d\'un texte en ses propres mots ?',         'ELEMENTAIRE',   'ENAFEP',     [['A','Paraphrase',1], ['B','Paraphrase / reformulation',0], ['C','Synopsis',0], ['D','Ã‰pitomÃ©',0]]],
  [$matIds['francais'], 'Quelle est la forme nÃ©gative correcte de "Il faut partir." ?',             'ELEMENTAIRE',   'ENAFEP',     [['A','Il ne faut pas partir.',1], ['B','Il faut ne pas partir.',0], ['C','Il faut partir non.',0], ['D','Ne faut-il pas partir ?',0]]],

  /* â•â•â•â•â•â•â•â•â•â•â•â• CHIMIE (10) â•â•â•â•â•â•â•â•â•â•â•â• */
  [$matIds['chimie'], 'Quel gaz est produit lors de la rÃ©action de Zn avec HCl ?',                  'ELEMENTAIRE',   'TENASOSP',   [['A','Hâ‚‚',1], ['B','Clâ‚‚',0], ['C','ZnO',0], ['D','HZn',0]]],
  [$matIds['chimie'], 'Le soufre a pour numÃ©ro atomique 16. Sa configuration Ã©lectronique est :',    'AVANCE',        'EXAMEN_ETAT',[['A','1sÂ² 2sÂ² 2pâ¶ 3sÂ² 3pâ´',1], ['B','1sÂ² 2sÂ² 2pâ´ 3sÂ² 3pâ´',0], ['C','1sÂ² 2sÂ² 2pâ¶ 3sâ´',0], ['D','1sÂ² 2sâ¶ 2pâ¶ 3sÂ²',0]]],
  [$matIds['chimie'], 'La formule de l\'hydroxyde de magnÃ©sium est :',                               'ELEMENTAIRE',   'TENASOSP',   [['A','Mg(OH)â‚‚',1], ['B','MgOH',0], ['C','Mgâ‚‚OH',0], ['D','MgO',0]]],
  [$matIds['chimie'], 'Quelle est la masse molaire du sulfate de calcium CaSOâ‚„ ? (Ca=40, S=32, O=16)', 'INTERMEDIAIRE', 'TENASOSP', [['A','136 g/mol',1], ['B','104 g/mol',0], ['C','96 g/mol',0], ['D','120 g/mol',0]]],
  [$matIds['chimie'], 'La rÃ©action de neutralisation produit toujours :',                            'ELEMENTAIRE',   'TENASOSP',   [['A','Un sel et de l\'eau',1], ['B','Un acide et une base',0], ['C','De l\'oxygÃ¨ne',0], ['D','Un oxyde',0]]],
  [$matIds['chimie'], 'Quelle technique sÃ©pare un solide dissous d\'un liquide par Ã©vaporation ?',  'ELEMENTAIRE',   'ENAFEP',     [['A','Cristallisation / Ã©vaporation',1], ['B','Filtration',0], ['C','Distillation',0], ['D','DÃ©cantation',0]]],
  [$matIds['chimie'], 'Dans l\'Ã©lectrolyse de l\'eau, oÃ¹ se dÃ©gage l\'oxygÃ¨ne ?',                   'INTERMEDIAIRE', 'TENASOSP',   [['A','Ã€ l\'anode (+)',1], ['B','Ã€ la cathode (-)',0], ['C','Aux deux Ã©lectrodes',0], ['D','Dans la solution',0]]],
  [$matIds['chimie'], 'Quel est le nom du composÃ© Feâ‚‚Oâ‚ƒ ?',                                          'ELEMENTAIRE',   'TENASOSP',   [['A','Trioxyde de difer / oxyde de fer (III)',1], ['B','Oxyde ferreux',0], ['C','Sulfate de fer',0], ['D','Chlorure de fer',0]]],
  [$matIds['chimie'], 'La densitÃ© d\'un gaz parfait est proportionnelle Ã  sa :',                    'AVANCE',        'EXAMEN_ETAT',[['A','Masse molaire (Ã  T et P constants)',1], ['B','TempÃ©rature',0], ['C','Pression',0], ['D','Volume',0]]],
  [$matIds['chimie'], 'Quelle est la concentration d\'une solution : 2 mol dans 500 mL ?',          'INTERMEDIAIRE', 'TENASOSP',   [['A','4 mol/L',1], ['B','1 mol/L',0], ['C','0,25 mol/L',0], ['D','2,5 mol/L',0]]],

  /* â•â•â•â•â•â•â•â•â•â•â•â• PHYSIQUE (10) â•â•â•â•â•â•â•â•â•â•â•â• */
  [$matIds['physique'], 'Un objet de 5 kg est Ã  10 m de hauteur. Ep = ? (g = 10)',                  'ELEMENTAIRE',   'ENAFEP',     [['A','500 J',1], ['B','50 J',0], ['C','5000 J',0], ['D','250 J',0]]],
  [$matIds['physique'], 'La rÃ©sistance Ã©quivalente de 4 Î© et 6 Î© en sÃ©rie vaut :',                  'ELEMENTAIRE',   'ENAFEP',     [['A','10 Î©',1], ['B','2.4 Î©',0], ['C','24 Î©',0], ['D','5 Î©',0]]],
  [$matIds['physique'], 'La puissance d\'un appareil sous 220 V absorbant 2 A est :',               'ELEMENTAIRE',   'TENASOSP',   [['A','440 W',1], ['B','110 W',0], ['C','222 W',0], ['D','880 W',0]]],
  [$matIds['physique'], 'Le son ne se propage pas dans le vide car :',                               'ELEMENTAIRE',   'ENAFEP',     [['A','Il nÃ©cessite un milieu matÃ©riel (ondes mÃ©caniques)',1], ['B','Sa vitesse est trop grande',0], ['C','Il est absorbÃ© par les photons',0], ['D','Les atomes bloquent sa propagation',0]]],
  [$matIds['physique'], 'La vitesse du son dans l\'air (20Â°C) est environ :',                       'ELEMENTAIRE',   'ENAFEP',     [['A','340 m/s',1], ['B','300 m/s',0], ['C','3 Ã— 10â¸ m/s',0], ['D','1500 m/s',0]]],
  [$matIds['physique'], 'Un corps de 3 kg Ã  v = 4 m/s. Son Ã©nergie cinÃ©tique vaut :',               'INTERMEDIAIRE', 'TENASOSP',   [['A','24 J',1], ['B','12 J',0], ['C','48 J',0], ['D','6 J',0]]],
  [$matIds['physique'], 'La loi d\'Ohm gÃ©nÃ©ralisÃ©e pour un gÃ©nÃ©rateur est Îµ = U + rÂ·I. Ici r est :','AVANCE',        'EXAMEN_ETAT',[['A','La rÃ©sistance interne du gÃ©nÃ©rateur',1], ['B','La rÃ©sistance externe',0], ['C','La force Ã©lectromotrice',0], ['D','La tension aux bornes',0]]],
  [$matIds['physique'], 'La premiÃ¨re loi de Newton (principe d\'inertie) dit :',                    'ELEMENTAIRE',   'ENAFEP',     [['A','Tout corps en l\'absence de force nette reste au repos ou en MRU',1], ['B','F = ma',0], ['C','Action = rÃ©action',0], ['D','L\'Ã©nergie se conserve',0]]],
  [$matIds['physique'], 'Le phÃ©nomÃ¨ne de rÃ©fraction se produit quand la lumiÃ¨re :',                 'INTERMEDIAIRE', 'TENASOSP',   [['A','Change de milieu (vitesse diffÃ©rente)',1], ['B','Rebondit sur une surface',0], ['C','Est absorbÃ©e',0], ['D','Se diffracte',0]]],
  [$matIds['physique'], 'Quelle est l\'unitÃ© du travail en physique ?',                              'DEBUTANT',      'ENAFEP',     [['A','Joule (J)',1], ['B','Watt',0], ['C','Newton',0], ['D','Pascal',0]]],

  /* â•â•â•â•â•â•â•â•â•â•â•â• BIOLOGIE (10) â•â•â•â•â•â•â•â•â•â•â•â• */
  [$matIds['biologie'], 'La mitose produit :',                                                       'INTERMEDIAIRE', 'TENASOSP',   [['A','2 cellules filles gÃ©nÃ©tiquement identiques Ã  la mÃ¨re',1], ['B','4 cellules haploÃ¯des',0], ['C','Des gamÃ¨tes',0], ['D','Des cellules souches',0]]],
  [$matIds['biologie'], 'L\'hÃ©moglobine est une protÃ©ine qui contient du :',                         'ELEMENTAIRE',   'TENASOSP',   [['A','Fer (Fe)',1], ['B','Calcium',0], ['C','Zinc',0], ['D','MagnÃ©sium',0]]],
  [$matIds['biologie'], 'Quel organe filtre le sang et produit l\'urine ?',                          'DEBUTANT',      'ENAFEP',     [['A','Les reins',1], ['B','Le foie',0], ['C','La rate',0], ['D','Le pancrÃ©as',0]]],
  [$matIds['biologie'], 'La mÃ©iose se dÃ©roule dans :',                                               'AVANCE',        'EXAMEN_ETAT',[['A','Les gonades (ovaires/testicules)',1], ['B','Le foie',0], ['C','La moelle osseuse',0], ['D','Le thymus',0]]],
  [$matIds['biologie'], 'Combien de paires de chromosomes possÃ¨de l\'Ãªtre humain ?',                 'ELEMENTAIRE',   'ENAFEP',     [['A','23 paires (46 chromosomes)',1], ['B','24 paires',0], ['C','22 paires',0], ['D','46 paires',0]]],
  [$matIds['biologie'], 'Qu\'est-ce qu\'un vecteur en Ã©pidÃ©miologie ?',                              'INTERMEDIAIRE', 'TENASOSP',   [['A','Un organisme transmettant un agent pathogÃ¨ne sans Ãªtre malade',1], ['B','Le virus lui-mÃªme',0], ['C','Un antibiotique',0], ['D','L\'hÃ´te final',0]]],
  [$matIds['biologie'], 'La photosynthÃ¨se libÃ¨re de l\'Oâ‚‚ Ã  partir de :',                           'ELEMENTAIRE',   'ENAFEP',     [['A','La photolyse de l\'eau (Hâ‚‚O)',1], ['B','Le COâ‚‚',0], ['C','La chlorophylle',0], ['D','Le glucose',0]]],
  [$matIds['biologie'], 'Quel est le rÃ´le de l\'ATP dans la cellule ?',                              'AVANCE',        'EXAMEN_ETAT',[['A','MolÃ©cule de transfert d\'Ã©nergie universelle',1], ['B','Support de l\'information gÃ©nÃ©tique',0], ['C','ProtÃ©ine de structure',0], ['D','Enzyme digestif',0]]],
  [$matIds['biologie'], 'La vitamine D est synthÃ©tisÃ©e par l\'organisme grÃ¢ce Ã  :',                  'INTERMEDIAIRE', 'TENASOSP',   [['A','L\'exposition au soleil (UV)',1], ['B','L\'alimentation seule',0], ['C','Le foie uniquement',0], ['D','Les intestins',0]]],
  [$matIds['biologie'], 'Quelle maladie est causÃ©e par une carence en iode ?',                       'INTERMEDIAIRE', 'ENAFEP',     [['A','Goitre (hypothyroÃ¯die)',1], ['B','AnÃ©mie',0], ['C','Rachitisme',0], ['D','Scorbut',0]]],

  /* â•â•â•â•â•â•â•â•â•â•â•â• HISTOIRE-GÃ‰O (10) â•â•â•â•â•â•â•â•â•â•â•â• */
  [$matIds['histgeo'], 'En quelle annÃ©e la RDC a-t-elle accÃ©dÃ© Ã  l\'indÃ©pendance ?',                'DEBUTANT',      'ENAFEP',     [['A','1960',1], ['B','1958',0], ['C','1962',0], ['D','1965',0]]],
  [$matIds['histgeo'], 'La rÃ©volution franÃ§aise a dÃ©butÃ© en :',                                      'ELEMENTAIRE',   'ENAFEP',     [['A','1789',1], ['B','1776',0], ['C','1799',0], ['D','1815',0]]],
  [$matIds['histgeo'], 'Quel est le plus grand pays d\'Afrique par superficie ?',                    'ELEMENTAIRE',   'ENAFEP',     [['A','AlgÃ©rie',1], ['B','RDC',0], ['C','Soudan',0], ['D','Libye',0]]],
  [$matIds['histgeo'], 'La ligne de l\'Ã©quateur passe par la RDC. Cela lui confÃ¨re un climat :',    'ELEMENTAIRE',   'ENAFEP',     [['A','Ã‰quatorial (chaud et humide) dans le bassin central',1], ['B','DÃ©sertique',0], ['C','MÃ©diterranÃ©en',0], ['D','TempÃ©rÃ©',0]]],
  [$matIds['histgeo'], 'Quelle est la population estimÃ©e de la RDC (2025) ?',                        'INTERMEDIAIRE', 'ENAFEP',     [['A','~110-115 millions hab.',1], ['B','~50 millions',0], ['C','~200 millions',0], ['D','~80 millions',0]]],
  [$matIds['histgeo'], 'Quel est l\'organe principal de l\'ONU chargÃ© de la paix et sÃ©curitÃ© ?',    'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','Le Conseil de sÃ©curitÃ©',1], ['B','L\'AssemblÃ©e gÃ©nÃ©rale',0], ['C','La Cour internationale de justice',0], ['D','Le SecrÃ©tariat gÃ©nÃ©ral',0]]],
  [$matIds['histgeo'], 'La confÃ©rence de Berlin (1884-1885) a :',                                    'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','PartagÃ© l\'Afrique entre puissances europÃ©ennes',1], ['B','LibÃ©rÃ© l\'Afrique',0], ['C','FondÃ© l\'OUA',0], ['D','Mis fin Ã  la traite nÃ©griÃ¨re',0]]],
  [$matIds['histgeo'], 'Le vol SP500 du mont Nyiragongo (RDC) de 2021 a provoquÃ© :',                'ELEMENTAIRE',   'ENAFEP',     [['A','Une Ã©ruption volcanique menaÃ§ant Goma',1], ['B','Un tremblement de terre Ã  Kinshasa',0], ['C','Une inondation du lac Kivu',0], ['D','Une sÃ©cheresse au Katanga',0]]],
  [$matIds['histgeo'], 'Quelle est la diffÃ©rence entre latitude et longitude ?',                     'ELEMENTAIRE',   'ENAFEP',     [['A','Latitude = N/S (parallÃ¨les) ; Longitude = E/O (mÃ©ridiens)',1], ['B','Latitude = E/O ; Longitude = N/S',0], ['C','Elles sont identiques',0], ['D','Latitude = altitude',0]]],
  [$matIds['histgeo'], 'La SADC est une organisation rÃ©gionale d\'Afrique :',                        'INTERMEDIAIRE', 'ENAFEP',     [['A','Australe',1], ['B','Occidentale',0], ['C','Centrale uniquement',0], ['D','Orientale',0]]],

  /* â•â•â•â•â•â•â•â•â•â•â•â• ANGLAIS (7) â•â•â•â•â•â•â•â•â•â•â•â• */
  [$matIds['anglais'], 'Choose the correct form: "She has been working here ___ 2018."',             'ELEMENTAIRE',   'ENAFEP',     [['A','since',1], ['B','for',0], ['C','from',0], ['D','during',0]]],
  [$matIds['anglais'], 'What is the past participle of "forbid"?',                                   'AVANCE',        'EXAMEN_ETAT',[['A','forbidden',1], ['B','forbade',0], ['C','forbid',0], ['D','forbit',0]]],
  [$matIds['anglais'], 'Identify the conditional type: "If I were rich, I would travel."',           'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','Second conditional (unreal present)',1], ['B','First conditional',0], ['C','Third conditional',0], ['D','Zero conditional',0]]],
  [$matIds['anglais'], 'The prefix "dis-" in "disagree" means:',                                     'ELEMENTAIRE',   'ENAFEP',     [['A','Not / opposite of',1], ['B','Again',0], ['C','Before',0], ['D','Under',0]]],
  [$matIds['anglais'], '"I wish I had studied harder." This expresses:',                              'AVANCE',        'EXAMEN_ETAT',[['A','Regret about the past',1], ['B','A future wish',0], ['C','A polite request',0], ['D','A general truth',0]]],
  [$matIds['anglais'], 'What does "prodigious" mean?',                                               'EXPERT',        'EXAMEN_ETAT',[['A','Remarkably great in extent or ability',1], ['B','Very small',0], ['C','Dishonest',0], ['D','Lazy',0]]],
  [$matIds['anglais'], 'Which is a correct relative clause? "The man ___ called you is my uncle."', 'INTERMEDIAIRE', 'ENAFEP',     [['A','who',1], ['B','which',0], ['C','whose',0], ['D','whom',0]]],

  /* â•â•â•â•â•â•â•â•â•â•â•â• SCIENCES (8) â•â•â•â•â•â•â•â•â•â•â•â• */
  [$matIds['sciences'], 'Quelle est l\'Ã©chelle de tempÃ©rature utilisÃ©e en sciences (absolue) ?',    'ELEMENTAIRE',   'TENASOSP',   [['A','Kelvin (K)',1], ['B','Celsius',0], ['C','Fahrenheit',0], ['D','Rankine',0]]],
  [$matIds['sciences'], 'Un atome neutre a autant de protons que de :',                              'DEBUTANT',      'ENAFEP',     [['A','Ã‰lectrons',1], ['B','Neutrons',0], ['C','Noyaux',0], ['D','Quarks',0]]],
  [$matIds['sciences'], 'La photovoltaÃ¯que convertit :',                                             'ELEMENTAIRE',   'ENAFEP',     [['A','L\'Ã©nergie solaire en Ã©nergie Ã©lectrique',1], ['B','La chaleur en lumiÃ¨re',0], ['C','L\'eau en hydrogÃ¨ne',0], ['D','La biomasse en gaz',0]]],
  [$matIds['sciences'], 'Quelle est la diffÃ©rence entre une Ã©toile et une planÃ¨te ?',               'ELEMENTAIRE',   'ENAFEP',     [['A','Une Ã©toile produit sa propre lumiÃ¨re par fusion nuclÃ©aire ; une planÃ¨te non',1], ['B','Une planÃ¨te est plus grande',0], ['C','Une Ã©toile tourne autour d\'une planÃ¨te',0], ['D','Elles sont identiques',0]]],
  [$matIds['sciences'], 'Le bÃ©ton est un mÃ©lange de :',                                              'ELEMENTAIRE',   'ENAFEP',     [['A','Ciment, sable, gravier et eau',1], ['B','PlÃ¢tre et sable',0], ['C','Calcaire et argile',0], ['D','Ciment et pÃ©trole',0]]],
  [$matIds['sciences'], 'Quelle est la principale cause des pluies acides ?',                        'INTERMEDIAIRE', 'TENASOSP',   [['A','Ã‰missions de SOâ‚‚ et NOâ‚“ (combustion fossile)',1], ['B','L\'ozone',0], ['C','Le COâ‚‚ seul',0], ['D','L\'Ã©rosion des sols',0]]],
  [$matIds['sciences'], 'L\'ADN recombinant est utilisÃ© pour :',                                     'AVANCE',        'EXAMEN_ETAT',[['A','InsÃ©rer un gÃ¨ne d\'un organisme dans un autre (OGM, thÃ©rapie gÃ©nique)',1], ['B','Amplifier l\'ARN',0], ['C','SÃ©quencer des protÃ©ines',0], ['D','Fabriquer des lipides',0]]],
  [$matIds['sciences'], 'Quelle est la diffÃ©rence entre un vaccin et un antibiotique ?',             'ELEMENTAIRE',   'ENAFEP',     [['A','Vaccin = prÃ©vention (immunisation) ; antibiotique = traitement bactÃ©rien',1], ['B','Les deux traitent les virus',0], ['C','Antibiotique = prÃ©vention',0], ['D','Ils sont synonymes',0]]],
];

/* â”€â”€ Insertion â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
$inserted  = 0;
$skipped   = 0;
$bySubject = array_fill_keys(array_keys($matIds), ['ins' => 0, 'skip' => 0]);

$subjectReverse = [];
foreach ($matIds as $key => $id) $subjectReverse[$id] = $key;

foreach ($dataset as [$matId, $enonce, $diff, $src, $opts]) {
    $key = $subjectReverse[$matId] ?? 'unknown';
    if (insert_q($pdo, $matId, $enonce, $diff, $src, $opts)) {
        $inserted++;
        $bySubject[$key]['ins']++;
    } else {
        $skipped++;
        $bySubject[$key]['skip']++;
    }
}

/* â”€â”€ Rapport â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
log_msg("\n  RAPPORT PAR MATIÃˆRE");
log_msg("  " . str_repeat("â”€", 42));
foreach ($matMapping as $key => $nom) {
    $ins  = $bySubject[$key]['ins'];
    $skip = $bySubject[$key]['skip'];
    log_msg(sprintf("  %-28s  +%d insÃ©rÃ©es  (%d existantes)", $nom, $ins, $skip));
}
log_msg("  " . str_repeat("â”€", 42));
log_msg(sprintf("  TOTAL : %d insÃ©rÃ©es | %d ignorÃ©es (doublons)", $inserted, $skipped));
log_msg("\n  âœ“ TerminÃ© : " . date('Y-m-d H:i:s'));

