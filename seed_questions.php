<?php
/**
 * seed_questions.php — Seeder dédié aux questions QCM
 * RÉUSSITE+ | Localhost only
 *
 * Usage : http://localhost/reussiteplus/seed_questions.php
 *         ou : & "C:\xampp\php\php.exe" seed_questions.php
 *
 * • Insère uniquement des questions (pas d'utilisateurs, pas d'archives)
 * • Vérifie les doublons par énoncé avant insertion
 * • Affiche un rapport détaillé par matière
 */

if ($_SERVER['SERVER_NAME'] !== 'localhost' && php_uname('n') !== gethostname()) {
    http_response_code(403); exit('Forbidden');
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$pdo = db();
header('Content-Type: text/plain; charset=utf-8');

/* ── Helpers ─────────────────────────────────────────────────────────────── */
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

/* ── Chargement des matières ─────────────────────────────────────────────── */
log_msg("═══════════════════════════════════════════════════");
log_msg("  RÉUSSITE+ — seed_questions.php");
log_msg("  Démarrage : " . date('Y-m-d H:i:s'));
log_msg("═══════════════════════════════════════════════════\n");

$matMapping = [
    'maths'    => 'Mathématiques',
    'francais' => 'Français',
    'chimie'   => 'Chimie',
    'physique' => 'Physique',
    'biologie' => 'Biologie',
    'histgeo'  => 'Histoire-Géographie',
    'anglais'  => 'Anglais',
    'sciences' => 'Sciences',
];

$matIds = [];
foreach ($matMapping as $key => $nom) {
    $id = get_matiere_id($pdo, $nom);
    if (!$id) {
        log_msg("  ✗ Matière introuvable : $nom — crée-la d'abord via seed.php");
        exit(1);
    }
    $matIds[$key] = $id;
}
log_msg("  ✓ " . count($matIds) . " matières chargées\n");

/* ── Dataset : 75 questions thématiques (10 niveaux ENAFEP / TENASOSP 2023-2025) */
$dataset = [

  /* ════════════ MATHÉMATIQUES (10) ════════════ */
  [$matIds['maths'], 'Quel est le PGCD de 84 et 56 ?',                                               'ELEMENTAIRE',   'ENAFEP',     [['A','28',1], ['B','14',0], ['C','42',0], ['D','7',0]]],
  [$matIds['maths'], 'Un article coûte 1 200 CDF. Après une remise de 15 %, il vaut :',              'ELEMENTAIRE',   'ENAFEP',     [['A','1 020 CDF',1], ['B','1 020 CDF',0], ['C','1 080 CDF',0], ['D','1 008 CDF',0]]],
  [$matIds['maths'], 'Quelle est l\'équation du cercle de centre O(2;-1) et de rayon 3 ?',          'AVANCE',        'EXAMEN_ETAT',[['A','(x-2)²+(y+1)²=9',1], ['B','(x+2)²+(y-1)²=9',0], ['C','(x-2)²+(y+1)²=3',0], ['D','x²+y²=9',0]]],
  [$matIds['maths'], 'Calculer la limite de (x²-4)/(x-2) quand x→2.',                                'AVANCE',        'EXAMEN_ETAT',[['A','4',1], ['B','0',0], ['C','∞',0], ['D','2',0]]],
  [$matIds['maths'], 'Un triangle rectangle a des cathètes 6 et 8. Son hypoténuse vaut :',           'ELEMENTAIRE',   'ENAFEP',     [['A','10',1], ['B','14',0], ['C','7',0], ['D','√100',0]]],
  [$matIds['maths'], 'Calculer : 7 × (3 + 2²) − 4',                                                 'ELEMENTAIRE',   'ENAFEP',     [['A','45',1], ['B','49',0], ['C','51',0], ['D','39',0]]],
  [$matIds['maths'], 'La dérivée de f(x) = x³ − 5x + 2 est :',                                      'AVANCE',        'EXAMEN_ETAT',[['A','3x² − 5',1], ['B','3x² + 2',0], ['C','x³ − 5',0], ['D','3x − 5',0]]],
  [$matIds['maths'], 'Résoudre : |2x − 3| = 7',                                                      'INTERMEDIAIRE', 'TENASOSP',   [['A','x = 5 ou x = −2',1], ['B','x = 5',0], ['C','x = 2',0], ['D','x = −2',0]]],
  [$matIds['maths'], 'Dans un sac : 4 rouges, 3 vertes, 2 bleues. P(rouge) = ?',                    'ELEMENTAIRE',   'ENAFEP',     [['A','4/9',1], ['B','1/4',0], ['C','3/9',0], ['D','4/3',0]]],
  [$matIds['maths'], 'Quel est le volume d\'un cylindre de r=5 cm et h=10 cm ?',                    'INTERMEDIAIRE', 'TENASOSP',   [['A','250π cm³',1], ['B','500π cm³',0], ['C','100π cm³',0], ['D','50π cm³',0]]],

  /* ════════════ FRANÇAIS (10) ════════════ */
  [$matIds['francais'], 'Identifier le complément de nom dans : "Le livre de mon frère est neuf."',  'ELEMENTAIRE',   'ENAFEP',     [['A','de mon frère',1], ['B','Le livre',0], ['C','est neuf',0], ['D','mon frère',0]]],
  [$matIds['francais'], 'Conjuguer "se souvenir" au passé composé, 1ère pers. sing. :',             'ELEMENTAIRE',   'ENAFEP',     [['A','je me suis souvenu(e)',1], ['B','je me souvins',0], ['C','j\'ai souvenu',0], ['D','je me souviendrais',0]]],
  [$matIds['francais'], '"Il ne cesse de parler." — La négation "ne...de" est-elle standard ?',     'AVANCE',        'EXAMEN_ETAT',[['A','Oui, forme standard : ne...de + infinitif',1], ['B','Non, incorrecte',0], ['C','Registre familier uniquement',0], ['D','Appartient au vieux français',0]]],
  [$matIds['francais'], 'Quel est le sens du préfixe "mal-" dans "malchance" ?',                    'DEBUTANT',      'ENAFEP',     [['A','Mauvais / contraire positif',1], ['B','Sous',0], ['C','Très',0], ['D','Avec',0]]],
  [$matIds['francais'], 'La phrase "Il fait chaud." est de type :',                                  'ELEMENTAIRE',   'ENAFEP',     [['A','Déclarative',1], ['B','Interrogative',0], ['C','Exclamative',0], ['D','Impérative',0]]],
  [$matIds['francais'], 'Dans "Vouloir, c\'est pouvoir.", le sujet du verbe "est" est :',            'AVANCE',        'EXAMEN_ETAT',[['A','Vouloir (proposition infinitive)',1], ['B','c\'',0], ['C','pouvoir',0], ['D','Il (absent)',0]]],
  [$matIds['francais'], 'Identifier la métonymie dans : "Il a lu tout Voltaire."',                   'AVANCE',        'EXAMEN_ETAT',[['A','Voltaire = l\'œuvre de Voltaire',1], ['B','Voltaire = la ville de Voltaire',0], ['C','C\'est une hyperbole',0], ['D','C\'est une synecdoque',0]]],
  [$matIds['francais'], 'Quel est le pluriel de "bail" ?',                                           'INTERMEDIAIRE', 'ENAFEP',     [['A','baux',1], ['B','bails',0], ['C','bailes',0], ['D','baulx',0]]],
  [$matIds['francais'], 'Comment appelle-t-on le résumé d\'un texte en ses propres mots ?',         'ELEMENTAIRE',   'ENAFEP',     [['A','Paraphrase',1], ['B','Paraphrase / reformulation',0], ['C','Synopsis',0], ['D','Épitomé',0]]],
  [$matIds['francais'], 'Quelle est la forme négative correcte de "Il faut partir." ?',             'ELEMENTAIRE',   'ENAFEP',     [['A','Il ne faut pas partir.',1], ['B','Il faut ne pas partir.',0], ['C','Il faut partir non.',0], ['D','Ne faut-il pas partir ?',0]]],

  /* ════════════ CHIMIE (10) ════════════ */
  [$matIds['chimie'], 'Quel gaz est produit lors de la réaction de Zn avec HCl ?',                  'ELEMENTAIRE',   'TENASOSP',   [['A','H₂',1], ['B','Cl₂',0], ['C','ZnO',0], ['D','HZn',0]]],
  [$matIds['chimie'], 'Le soufre a pour numéro atomique 16. Sa configuration électronique est :',    'AVANCE',        'EXAMEN_ETAT',[['A','1s² 2s² 2p⁶ 3s² 3p⁴',1], ['B','1s² 2s² 2p⁴ 3s² 3p⁴',0], ['C','1s² 2s² 2p⁶ 3s⁴',0], ['D','1s² 2s⁶ 2p⁶ 3s²',0]]],
  [$matIds['chimie'], 'La formule de l\'hydroxyde de magnésium est :',                               'ELEMENTAIRE',   'TENASOSP',   [['A','Mg(OH)₂',1], ['B','MgOH',0], ['C','Mg₂OH',0], ['D','MgO',0]]],
  [$matIds['chimie'], 'Quelle est la masse molaire du sulfate de calcium CaSO₄ ? (Ca=40, S=32, O=16)', 'INTERMEDIAIRE', 'TENASOSP', [['A','136 g/mol',1], ['B','104 g/mol',0], ['C','96 g/mol',0], ['D','120 g/mol',0]]],
  [$matIds['chimie'], 'La réaction de neutralisation produit toujours :',                            'ELEMENTAIRE',   'TENASOSP',   [['A','Un sel et de l\'eau',1], ['B','Un acide et une base',0], ['C','De l\'oxygène',0], ['D','Un oxyde',0]]],
  [$matIds['chimie'], 'Quelle technique sépare un solide dissous d\'un liquide par évaporation ?',  'ELEMENTAIRE',   'ENAFEP',     [['A','Cristallisation / évaporation',1], ['B','Filtration',0], ['C','Distillation',0], ['D','Décantation',0]]],
  [$matIds['chimie'], 'Dans l\'électrolyse de l\'eau, où se dégage l\'oxygène ?',                   'INTERMEDIAIRE', 'TENASOSP',   [['A','À l\'anode (+)',1], ['B','À la cathode (-)',0], ['C','Aux deux électrodes',0], ['D','Dans la solution',0]]],
  [$matIds['chimie'], 'Quel est le nom du composé Fe₂O₃ ?',                                          'ELEMENTAIRE',   'TENASOSP',   [['A','Trioxyde de difer / oxyde de fer (III)',1], ['B','Oxyde ferreux',0], ['C','Sulfate de fer',0], ['D','Chlorure de fer',0]]],
  [$matIds['chimie'], 'La densité d\'un gaz parfait est proportionnelle à sa :',                    'AVANCE',        'EXAMEN_ETAT',[['A','Masse molaire (à T et P constants)',1], ['B','Température',0], ['C','Pression',0], ['D','Volume',0]]],
  [$matIds['chimie'], 'Quelle est la concentration d\'une solution : 2 mol dans 500 mL ?',          'INTERMEDIAIRE', 'TENASOSP',   [['A','4 mol/L',1], ['B','1 mol/L',0], ['C','0,25 mol/L',0], ['D','2,5 mol/L',0]]],

  /* ════════════ PHYSIQUE (10) ════════════ */
  [$matIds['physique'], 'Un objet de 5 kg est à 10 m de hauteur. Ep = ? (g = 10)',                  'ELEMENTAIRE',   'ENAFEP',     [['A','500 J',1], ['B','50 J',0], ['C','5000 J',0], ['D','250 J',0]]],
  [$matIds['physique'], 'La résistance équivalente de 4 Ω et 6 Ω en série vaut :',                  'ELEMENTAIRE',   'ENAFEP',     [['A','10 Ω',1], ['B','2.4 Ω',0], ['C','24 Ω',0], ['D','5 Ω',0]]],
  [$matIds['physique'], 'La puissance d\'un appareil sous 220 V absorbant 2 A est :',               'ELEMENTAIRE',   'TENASOSP',   [['A','440 W',1], ['B','110 W',0], ['C','222 W',0], ['D','880 W',0]]],
  [$matIds['physique'], 'Le son ne se propage pas dans le vide car :',                               'ELEMENTAIRE',   'ENAFEP',     [['A','Il nécessite un milieu matériel (ondes mécaniques)',1], ['B','Sa vitesse est trop grande',0], ['C','Il est absorbé par les photons',0], ['D','Les atomes bloquent sa propagation',0]]],
  [$matIds['physique'], 'La vitesse du son dans l\'air (20°C) est environ :',                       'ELEMENTAIRE',   'ENAFEP',     [['A','340 m/s',1], ['B','300 m/s',0], ['C','3 × 10⁸ m/s',0], ['D','1500 m/s',0]]],
  [$matIds['physique'], 'Un corps de 3 kg à v = 4 m/s. Son énergie cinétique vaut :',               'INTERMEDIAIRE', 'TENASOSP',   [['A','24 J',1], ['B','12 J',0], ['C','48 J',0], ['D','6 J',0]]],
  [$matIds['physique'], 'La loi d\'Ohm généralisée pour un générateur est ε = U + r·I. Ici r est :','AVANCE',        'EXAMEN_ETAT',[['A','La résistance interne du générateur',1], ['B','La résistance externe',0], ['C','La force électromotrice',0], ['D','La tension aux bornes',0]]],
  [$matIds['physique'], 'La première loi de Newton (principe d\'inertie) dit :',                    'ELEMENTAIRE',   'ENAFEP',     [['A','Tout corps en l\'absence de force nette reste au repos ou en MRU',1], ['B','F = ma',0], ['C','Action = réaction',0], ['D','L\'énergie se conserve',0]]],
  [$matIds['physique'], 'Le phénomène de réfraction se produit quand la lumière :',                 'INTERMEDIAIRE', 'TENASOSP',   [['A','Change de milieu (vitesse différente)',1], ['B','Rebondit sur une surface',0], ['C','Est absorbée',0], ['D','Se diffracte',0]]],
  [$matIds['physique'], 'Quelle est l\'unité du travail en physique ?',                              'DEBUTANT',      'ENAFEP',     [['A','Joule (J)',1], ['B','Watt',0], ['C','Newton',0], ['D','Pascal',0]]],

  /* ════════════ BIOLOGIE (10) ════════════ */
  [$matIds['biologie'], 'La mitose produit :',                                                       'INTERMEDIAIRE', 'TENASOSP',   [['A','2 cellules filles génétiquement identiques à la mère',1], ['B','4 cellules haploïdes',0], ['C','Des gamètes',0], ['D','Des cellules souches',0]]],
  [$matIds['biologie'], 'L\'hémoglobine est une protéine qui contient du :',                         'ELEMENTAIRE',   'TENASOSP',   [['A','Fer (Fe)',1], ['B','Calcium',0], ['C','Zinc',0], ['D','Magnésium',0]]],
  [$matIds['biologie'], 'Quel organe filtre le sang et produit l\'urine ?',                          'DEBUTANT',      'ENAFEP',     [['A','Les reins',1], ['B','Le foie',0], ['C','La rate',0], ['D','Le pancréas',0]]],
  [$matIds['biologie'], 'La méiose se déroule dans :',                                               'AVANCE',        'EXAMEN_ETAT',[['A','Les gonades (ovaires/testicules)',1], ['B','Le foie',0], ['C','La moelle osseuse',0], ['D','Le thymus',0]]],
  [$matIds['biologie'], 'Combien de paires de chromosomes possède l\'être humain ?',                 'ELEMENTAIRE',   'ENAFEP',     [['A','23 paires (46 chromosomes)',1], ['B','24 paires',0], ['C','22 paires',0], ['D','46 paires',0]]],
  [$matIds['biologie'], 'Qu\'est-ce qu\'un vecteur en épidémiologie ?',                              'INTERMEDIAIRE', 'TENASOSP',   [['A','Un organisme transmettant un agent pathogène sans être malade',1], ['B','Le virus lui-même',0], ['C','Un antibiotique',0], ['D','L\'hôte final',0]]],
  [$matIds['biologie'], 'La photosynthèse libère de l\'O₂ à partir de :',                           'ELEMENTAIRE',   'ENAFEP',     [['A','La photolyse de l\'eau (H₂O)',1], ['B','Le CO₂',0], ['C','La chlorophylle',0], ['D','Le glucose',0]]],
  [$matIds['biologie'], 'Quel est le rôle de l\'ATP dans la cellule ?',                              'AVANCE',        'EXAMEN_ETAT',[['A','Molécule de transfert d\'énergie universelle',1], ['B','Support de l\'information génétique',0], ['C','Protéine de structure',0], ['D','Enzyme digestif',0]]],
  [$matIds['biologie'], 'La vitamine D est synthétisée par l\'organisme grâce à :',                  'INTERMEDIAIRE', 'TENASOSP',   [['A','L\'exposition au soleil (UV)',1], ['B','L\'alimentation seule',0], ['C','Le foie uniquement',0], ['D','Les intestins',0]]],
  [$matIds['biologie'], 'Quelle maladie est causée par une carence en iode ?',                       'INTERMEDIAIRE', 'ENAFEP',     [['A','Goitre (hypothyroïdie)',1], ['B','Anémie',0], ['C','Rachitisme',0], ['D','Scorbut',0]]],

  /* ════════════ HISTOIRE-GÉO (10) ════════════ */
  [$matIds['histgeo'], 'En quelle année la RDC a-t-elle accédé à l\'indépendance ?',                'DEBUTANT',      'ENAFEP',     [['A','1960',1], ['B','1958',0], ['C','1962',0], ['D','1965',0]]],
  [$matIds['histgeo'], 'La révolution française a débuté en :',                                      'ELEMENTAIRE',   'ENAFEP',     [['A','1789',1], ['B','1776',0], ['C','1799',0], ['D','1815',0]]],
  [$matIds['histgeo'], 'Quel est le plus grand pays d\'Afrique par superficie ?',                    'ELEMENTAIRE',   'ENAFEP',     [['A','Algérie',1], ['B','RDC',0], ['C','Soudan',0], ['D','Libye',0]]],
  [$matIds['histgeo'], 'La ligne de l\'équateur passe par la RDC. Cela lui confère un climat :',    'ELEMENTAIRE',   'ENAFEP',     [['A','Équatorial (chaud et humide) dans le bassin central',1], ['B','Désertique',0], ['C','Méditerranéen',0], ['D','Tempéré',0]]],
  [$matIds['histgeo'], 'Quelle est la population estimée de la RDC (2025) ?',                        'INTERMEDIAIRE', 'ENAFEP',     [['A','~110-115 millions hab.',1], ['B','~50 millions',0], ['C','~200 millions',0], ['D','~80 millions',0]]],
  [$matIds['histgeo'], 'Quel est l\'organe principal de l\'ONU chargé de la paix et sécurité ?',    'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','Le Conseil de sécurité',1], ['B','L\'Assemblée générale',0], ['C','La Cour internationale de justice',0], ['D','Le Secrétariat général',0]]],
  [$matIds['histgeo'], 'La conférence de Berlin (1884-1885) a :',                                    'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','Partagé l\'Afrique entre puissances européennes',1], ['B','Libéré l\'Afrique',0], ['C','Fondé l\'OUA',0], ['D','Mis fin à la traite négrière',0]]],
  [$matIds['histgeo'], 'Le vol SP500 du mont Nyiragongo (RDC) de 2021 a provoqué :',                'ELEMENTAIRE',   'ENAFEP',     [['A','Une éruption volcanique menaçant Goma',1], ['B','Un tremblement de terre à Kinshasa',0], ['C','Une inondation du lac Kivu',0], ['D','Une sécheresse au Katanga',0]]],
  [$matIds['histgeo'], 'Quelle est la différence entre latitude et longitude ?',                     'ELEMENTAIRE',   'ENAFEP',     [['A','Latitude = N/S (parallèles) ; Longitude = E/O (méridiens)',1], ['B','Latitude = E/O ; Longitude = N/S',0], ['C','Elles sont identiques',0], ['D','Latitude = altitude',0]]],
  [$matIds['histgeo'], 'La SADC est une organisation régionale d\'Afrique :',                        'INTERMEDIAIRE', 'ENAFEP',     [['A','Australe',1], ['B','Occidentale',0], ['C','Centrale uniquement',0], ['D','Orientale',0]]],

  /* ════════════ ANGLAIS (7) ════════════ */
  [$matIds['anglais'], 'Choose the correct form: "She has been working here ___ 2018."',             'ELEMENTAIRE',   'ENAFEP',     [['A','since',1], ['B','for',0], ['C','from',0], ['D','during',0]]],
  [$matIds['anglais'], 'What is the past participle of "forbid"?',                                   'AVANCE',        'EXAMEN_ETAT',[['A','forbidden',1], ['B','forbade',0], ['C','forbid',0], ['D','forbit',0]]],
  [$matIds['anglais'], 'Identify the conditional type: "If I were rich, I would travel."',           'INTERMEDIAIRE', 'EXAMEN_ETAT',[['A','Second conditional (unreal present)',1], ['B','First conditional',0], ['C','Third conditional',0], ['D','Zero conditional',0]]],
  [$matIds['anglais'], 'The prefix "dis-" in "disagree" means:',                                     'ELEMENTAIRE',   'ENAFEP',     [['A','Not / opposite of',1], ['B','Again',0], ['C','Before',0], ['D','Under',0]]],
  [$matIds['anglais'], '"I wish I had studied harder." This expresses:',                              'AVANCE',        'EXAMEN_ETAT',[['A','Regret about the past',1], ['B','A future wish',0], ['C','A polite request',0], ['D','A general truth',0]]],
  [$matIds['anglais'], 'What does "prodigious" mean?',                                               'EXPERT',        'EXAMEN_ETAT',[['A','Remarkably great in extent or ability',1], ['B','Very small',0], ['C','Dishonest',0], ['D','Lazy',0]]],
  [$matIds['anglais'], 'Which is a correct relative clause? "The man ___ called you is my uncle."', 'INTERMEDIAIRE', 'ENAFEP',     [['A','who',1], ['B','which',0], ['C','whose',0], ['D','whom',0]]],

  /* ════════════ SCIENCES (8) ════════════ */
  [$matIds['sciences'], 'Quelle est l\'échelle de température utilisée en sciences (absolue) ?',    'ELEMENTAIRE',   'TENASOSP',   [['A','Kelvin (K)',1], ['B','Celsius',0], ['C','Fahrenheit',0], ['D','Rankine',0]]],
  [$matIds['sciences'], 'Un atome neutre a autant de protons que de :',                              'DEBUTANT',      'ENAFEP',     [['A','Électrons',1], ['B','Neutrons',0], ['C','Noyaux',0], ['D','Quarks',0]]],
  [$matIds['sciences'], 'La photovoltaïque convertit :',                                             'ELEMENTAIRE',   'ENAFEP',     [['A','L\'énergie solaire en énergie électrique',1], ['B','La chaleur en lumière',0], ['C','L\'eau en hydrogène',0], ['D','La biomasse en gaz',0]]],
  [$matIds['sciences'], 'Quelle est la différence entre une étoile et une planète ?',               'ELEMENTAIRE',   'ENAFEP',     [['A','Une étoile produit sa propre lumière par fusion nucléaire ; une planète non',1], ['B','Une planète est plus grande',0], ['C','Une étoile tourne autour d\'une planète',0], ['D','Elles sont identiques',0]]],
  [$matIds['sciences'], 'Le béton est un mélange de :',                                              'ELEMENTAIRE',   'ENAFEP',     [['A','Ciment, sable, gravier et eau',1], ['B','Plâtre et sable',0], ['C','Calcaire et argile',0], ['D','Ciment et pétrole',0]]],
  [$matIds['sciences'], 'Quelle est la principale cause des pluies acides ?',                        'INTERMEDIAIRE', 'TENASOSP',   [['A','Émissions de SO₂ et NOₓ (combustion fossile)',1], ['B','L\'ozone',0], ['C','Le CO₂ seul',0], ['D','L\'érosion des sols',0]]],
  [$matIds['sciences'], 'L\'ADN recombinant est utilisé pour :',                                     'AVANCE',        'EXAMEN_ETAT',[['A','Insérer un gène d\'un organisme dans un autre (OGM, thérapie génique)',1], ['B','Amplifier l\'ARN',0], ['C','Séquencer des protéines',0], ['D','Fabriquer des lipides',0]]],
  [$matIds['sciences'], 'Quelle est la différence entre un vaccin et un antibiotique ?',             'ELEMENTAIRE',   'ENAFEP',     [['A','Vaccin = prévention (immunisation) ; antibiotique = traitement bactérien',1], ['B','Les deux traitent les virus',0], ['C','Antibiotique = prévention',0], ['D','Ils sont synonymes',0]]],
];

/* ── Insertion ────────────────────────────────────────────────────────────── */
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

/* ── Rapport ─────────────────────────────────────────────────────────────── */
log_msg("\n  RAPPORT PAR MATIÈRE");
log_msg("  " . str_repeat("─", 42));
foreach ($matMapping as $key => $nom) {
    $ins  = $bySubject[$key]['ins'];
    $skip = $bySubject[$key]['skip'];
    log_msg(sprintf("  %-28s  +%d insérées  (%d existantes)", $nom, $ins, $skip));
}
log_msg("  " . str_repeat("─", 42));
log_msg(sprintf("  TOTAL : %d insérées | %d ignorées (doublons)", $inserted, $skipped));
log_msg("\n  ✓ Terminé : " . date('Y-m-d H:i:s'));
