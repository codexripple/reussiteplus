<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_admin(); // Script restreint aux admins
/**
 * Script d'ajout des questions manquantes pour atteindre 1000
 * Accessible localhost uniquement
 */
if (php_sapi_name() !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'])) {
    http_response_code(403); die('Accès refusé.');
}
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
header('Content-Type: text/plain; charset=utf-8');

$pdo = db();
$pdo->exec("SET FOREIGN_KEY_CHECKS=0");

// Récupérer les IDs des matières
$matMap = [];
foreach ($pdo->query("SELECT id,code FROM matieres")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $matMap[$r['code']] = $r['id'];
}

// Vérifier compte actuel
$current = (int)$pdo->query("SELECT COUNT(*) FROM question_bank")->fetchColumn();
echo "Questions actuelles: $current\n";
echo "Cible: 1000\n";
echo "�? ajouter: " . max(0, 1000 - $current) . "\n\n";

$stQ = $pdo->prepare("INSERT INTO question_bank (id,matiere_id,enonce,difficulte,exam_type,type_question,status) VALUES (UUID(),?,?,?,?,'QCM','PUBLIE')");
$stO = $pdo->prepare("INSERT INTO question_options (id,question_id,lettre,texte,est_correcte) VALUES (UUID(),?,?,?,?)");

function addQ(PDO $pdo, PDOStatement $stQ, PDOStatement $stO, string $matId, string $enonce, string $diff, string $src, array $opts): void {
    $stQ->execute([$matId, $enonce, $diff, $src]);
    $qid = $pdo->lastInsertId();
    if (!$qid) {
        $r = $pdo->prepare("SELECT id FROM question_bank WHERE enonce=? ORDER BY created_at DESC LIMIT 1");
        $r->execute([$enonce]);
        $qid = $r->fetchColumn();
    }
    if (!$qid) {
        $qid = bin2hex(random_bytes(16)); // Generate UUID as a fallback
    }
    foreach ($opts as [$l,$t,$ok]) $stO->execute([$qid,$l,$t,$ok]);
}

$added = 0;

/* �.��.� MATH�?MATIQUES (besoin ~8 pour atteindre 125) �.��.��.��.��.��.��.��.��.��.��.��.��.��.� */
$m = 'maths';
$questions_maths = [
    ["Quelle est la limite de sin(x)/x quand x�?'0 ?", 'AVANCE', 'EXAMEN_ETAT', [['A','1',1],['B','0',0],['C','�^z',0],['D','indéterminée',0]]],
    ["Si log�,,(x) = 5, quelle est la valeur de x ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','32',1],['B','10',0],['C','25',0],['D','16',0]]],
    ["Quel est le PGCD de 84 et 56 ?", 'ELEMENTAIRE', 'ENAFEP', [['A','28',1],['B','14',0],['C','7',0],['D','42',0]]],
    ["Développer (2x+3)² :", 'ELEMENTAIRE', 'ENAFEP', [['A','4x²+12x+9',1],['B','4x²+6x+9',0],['C','2x²+12x+9',0],['D','4x²+9',0]]],
    ["Résoudre x²-5x+6=0 : les racines sont :", 'INTERMEDIAIRE', 'TENASOSP', [['A','x=2 et x=3',1],['B','x=1 et x=6',0],['C','x=-2 et x=-3',0],['D','x=2 et x=-3',0]]],
    ["Volume d'un cube de côté 4 cm :", 'DEBUTANT', 'ENAFEP', [['A','64 cm³',1],['B','48 cm³',0],['C','16 cm³',0],['D','96 cm³',0]]],
    ["Si une suite arithmétique a�,�=3 et r=5, quel est a�,? ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','28',1],['B','30',0],['C','25',0],['D','18',0]]],
    ["Calculer : 5!/3! =", 'ELEMENTAIRE', 'TENASOSP', [['A','20',1],['B','10',0],['C','60',0],['D','15',0]]],
    ["L'intégrale de 2x dx est :", 'AVANCE', 'EXAMEN_ETAT', [['A','x²+C',1],['B','2x²+C',0],['C','x²',0],['D','2+C',0]]],
    ["Quelle est la médiane de {2, 5, 7, 9, 11} ?", 'ELEMENTAIRE', 'ENAFEP', [['A','7',1],['B','5',0],['C','9',0],['D','6,8',0]]],
    ["cos(60°) = ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','0,5',1],['B','0,866',0],['C','1',0],['D','0,707',0]]],
    ["Quelle est la pente de y = 3x - 7 ?", 'DEBUTANT', 'ENAFEP', [['A','3',1],['B','-7',0],['C','7',0],['D','-3',0]]],
];
foreach ($questions_maths as $q) {
    addQ($pdo, $stQ, $stO, $matMap[$m], ...$q);
    $added++;
}
echo "Mathématiques: +$added questions\n";

/* �.��.� FRAN�?AIS (besoin ~31 pour atteindre 125) �.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.� */
$prevAdded = $added;
$m = 'francais';
$questions_fr = [
    ["Quel est le sujet dans 'Les enfants jouent dans la cour' ?", 'DEBUTANT', 'ENAFEP', [['A','Les enfants',1],['B','jouent',0],['C','la cour',0],['D','dans',0]]],
    ["Quel temps est 'il aurait chanté' ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','Conditionnel passé',1],['B','Conditionnel présent',0],['C','Futur antérieur',0],['D','Plus-que-parfait',0]]],
    ["Quel est le synonyme de 'laborieux' ?", 'INTERMEDIAIRE', 'ENAFEP', [['A','travailleur',1],['B','paresseux',0],['C','rapide',0],['D','négligent',0]]],
    ["'Il a les dents du bonheur' est une expression qui signifie :", 'AVANCE', 'EXAMEN_ETAT', [['A','Avoir un écart entre les dents de devant',1],['B','�Stre toujours heureux',0],['C','Aimer sourire',0],['D','Avoir de belles dents',0]]],
    ["Identifier la proposition subordonnée relative : 'Le livre que tu lis est passionnant'", 'AVANCE', 'TENASOSP', [['A','que tu lis',1],['B','Le livre',0],['C','est passionnant',0],['D','tu lis est passionnant',0]]],
    ["Quel est le féminin de 'héros' ?", 'DEBUTANT', 'ENAFEP', [['A','héroïne',1],['B','hérose',0],['C','héroïsse',0],['D','héroïne',0]]],
    ["Dans 'Le soleil se lève à l'est', le verbe est :", 'DEBUTANT', 'ENAFEP', [['A','se lève',1],['B','soleil',0],['C','à l\'est',0],['D','le',0]]],
    ["'Chaque élève a rendu son devoir' �?" 'son' est :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Adjectif possessif',1],['B','Pronom possessif',0],['C','Adjectif démonstratif',0],['D','Pronom relatif',0]]],
    ["Quelle est la nature de 'rapidement' dans 'il court rapidement' ?", 'INTERMEDIAIRE', 'ENAFEP', [['A','Adverbe de manière',1],['B','Adjectif qualificatif',0],['C','Nom commun',0],['D','Verbe',0]]],
    ["Le préfixe 'anti-' signifie :", 'ELEMENTAIRE', 'ENAFEP', [['A','contre',1],['B','avant',0],['C','après',0],['D','avec',0]]],
    ["'Blanc comme neige' est :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Une comparaison',1],['B','Une métaphore',0],['C','Une hyperbole',0],['D','Une personnification',0]]],
    ["Conjuguer 'aller' au futur simple, 1ère personne du singulier :", 'DEBUTANT', 'ENAFEP', [['A','j\'irai',1],['B','je vais',0],['C','j\'allais',0],['D','je suis allé',0]]],
    ["Quel est le pluriel de 'bal' ?", 'ELEMENTAIRE', 'ENAFEP', [['A','bals',1],['B','baux',0],['C','bales',0],['D','balles',0]]],
    ["Identifier le COD : 'Marie mange une pomme'", 'ELEMENTAIRE', 'ENAFEP', [['A','une pomme',1],['B','Marie',0],['C','mange',0],['D','une',0]]],
    ["'L'homme est un loup pour l'homme' est :", 'AVANCE', 'EXAMEN_ETAT', [['A','Une métaphore',1],['B','Une comparaison',0],['C','Une hyperbole',0],['D','Une ironie',0]]],
    ["Quel est l'antonyme de 'avare' ?", 'ELEMENTAIRE', 'ENAFEP', [['A','généreux',1],['B','riche',0],['C','pauvre',0],['D','rapide',0]]],
    ["Le discours direct est introduit par :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Des guillemets et deux-points',1],['B','Des parenthèses',0],['C','Des tirets uniquement',0],['D','Des points de suspension',0]]],
    ["'Malgré la pluie, il est sorti' �?" 'Malgré' exprime :", 'AVANCE', 'TENASOSP', [['A','L\'opposition / la concession',1],['B','La cause',0],['C','La conséquence',0],['D','Le but',0]]],
    ["Quel est le genre de 'amour' au pluriel ?", 'AVANCE', 'EXAMEN_ETAT', [['A','Masculin au singulier, féminin au pluriel',1],['B','Toujours masculin',0],['C','Toujours féminin',0],['D','Neutre',0]]],
    ["'Je viendrai quand tu m'appelleras' �?" 'quand' introduit :", 'AVANCE', 'TENASOSP', [['A','Une proposition subordonnée de temps',1],['B','Une relative',0],['C','Une complétive',0],['D','Une circonstancielle de cause',0]]],
    ["Quel est le participe passé de 'résoudre' ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','résolu',1],['B','résoudé',0],['C','résolu\'',0],['D','résolvé',0]]],
    ["Identifier l'homonyme de 'ver' :", 'ELEMENTAIRE', 'ENAFEP', [['A','verre, vers, vert',1],['B','vair uniquement',0],['C','vers uniquement',0],['D','verbe',0]]],
    ["Que signifie l'expression 'avoir le cafard' ?", 'INTERMEDIAIRE', 'ENAFEP', [['A','�Stre triste, déprimé',1],['B','Avoir peur des insectes',0],['C','�Stre courageuse',0],['D','Avoir de la chance',0]]],
    ["Quel est le sujet dans 'C'est lui qui a gagné' ?", 'AVANCE', 'TENASOSP', [['A','lui',1],['B','C\'',0],['C','qui',0],['D','a gagné',0]]],
    ["Accorder : 'Les fleurs que j'ai _____ (cueillir)'", 'INTERMEDIAIRE', 'ENAFEP', [['A','cueillies',1],['B','cueillis',0],['C','cueilli',0],['D','cueillir',0]]],
    ["'Il est parti à l'aube.' �?" 'à l'aube' est :", 'ELEMENTAIRE', 'ENAFEP', [['A','Complément circonstanciel de temps',1],['B','COD',0],['C','Attribut du sujet',0],['D','Sujet',0]]],
    ["Quel registre de langue emploie 'ils bossent dur' ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','Familier',1],['B','Soutenu',0],['C','Courant',0],['D','Scientifique',0]]],
    ["Que désigne 'la Francophonie' ?", 'ELEMENTAIRE', 'ENAFEP', [['A','L\'ensemble des pays et communautés utilisant le français',1],['B','La langue française uniquement',0],['C','La France et ses colonies',0],['D','L\'Académie française',0]]],
    ["Quel est le type de la phrase : 'Ferme la porte !' ?", 'DEBUTANT', 'ENAFEP', [['A','Impérative',1],['B','Interrogative',0],['C','Déclarative',0],['D','Exclamative',0]]],
    ["'Quoiqu'il pleuve, je sortirai.' 'Quoique' exprime :", 'AVANCE', 'EXAMEN_ETAT', [['A','La concession',1],['B','La cause',0],['C','Le but',0],['D','La condition',0]]],
    ["Quel est le sens de 'épistémologie' ?", 'EXPERT', 'EXAMEN_ETAT', [['A','�?tude critique des sciences et de la connaissance',1],['B','�?tude des épidémies',0],['C','�?tude de la lettre',0],['D','Science du sol',0]]],
];
foreach ($questions_fr as $q) { addQ($pdo, $stQ, $stO, $matMap[$m], ...$q); $added++; }
echo "Français: +" . ($added-$prevAdded) . " questions\n";

/* �.��.� SCIENCES (besoin ~46 pour atteindre 125) �.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.� */
$prevAdded = $added;
$m = 'sciences';
$questions_sc = [
    ["Quelle est la formule de la photosynthèse (simplifiée) ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','CO�,, + H�,,O + lumière �?' glucose + O�,,',1],['B','O�,, + glucose �?' CO�,, + H�,,O',0],['C','H�,, + O�,, �?' H�,,O',0],['D','N�,, + H�,, �?' NH�,f',0]]],
    ["Quel est l'organe de l'olfaction (l'odorat) ?", 'DEBUTANT', 'ENAFEP', [['A','Le nez',1],['B','La langue',0],['C','L\'oreille',0],['D','L\'�"il',0]]],
    ["Quelle est la température normale du corps humain ?", 'DEBUTANT', 'ENAFEP', [['A','37°C',1],['B','36°C',0],['C','38°C',0],['D','39°C',0]]],
    ["Quel est le rôle des globules blancs ?", 'ELEMENTAIRE', 'ENAFEP', [['A','Défense immunitaire',1],['B','Transport de l\'oxygène',0],['C','Coagulation du sang',0],['D','Nutrition des cellules',0]]],
    ["Quel est le groupe sanguin universel donneur ?", 'ELEMENTAIRE', 'TENASOSP', [['A','O négatif',1],['B','AB positif',0],['C','A positif',0],['D','B négatif',0]]],
    ["La cellule animale se distingue de la cellule végétale par l'absence de :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Paroi cellulaire et chloroplastes',1],['B','Noyau',0],['C','Membrane cellulaire',0],['D','Mitochondries',0]]],
    ["Quelle vitamine est produite par exposition au soleil ?", 'ELEMENTAIRE', 'ENAFEP', [['A','Vitamine D',1],['B','Vitamine C',0],['C','Vitamine A',0],['D','Vitamine B12',0]]],
    ["Quel est l'organe qui filtre le sang dans l'organisme ?", 'ELEMENTAIRE', 'ENAFEP', [['A','Le rein',1],['B','Le foie',0],['C','Le c�"ur',0],['D','Le poumon',0]]],
    ["Combien de dents permanentes a l'adulte ?", 'DEBUTANT', 'ENAFEP', [['A','32',1],['B','28',0],['C','24',0],['D','36',0]]],
    ["Quelle est la substance qui donne sa couleur verte aux plantes ?", 'DEBUTANT', 'ENAFEP', [['A','La chlorophylle',1],['B','L\'anthocyane',0],['C','Le carotène',0],['D','La mélanine',0]]],
    ["Le paludisme est transmis par :", 'ELEMENTAIRE', 'ENAFEP', [['A','La piqûre de moustique anophèle femelle',1],['B','L\'eau contaminée',0],['C','Les aliments avariés',0],['D','Contact direct',0]]],
    ["Quel est l'agent pathogène du choléra ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','La bactérie Vibrio cholerae',1],['B','Un virus',0],['C','Un parasite',0],['D','Un champignon',0]]],
    ["La digestion des graisses est facilitée par :", 'INTERMEDIAIRE', 'TENASOSP', [['A','La bile et les lipases',1],['B','L\'amylase salivaire',0],['C','La pepsine',0],['D','L\'insuline',0]]],
    ["Dans quel organe se produit la fécondation chez la femme ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','La trompe de Fallope',1],['B','L\'utérus',0],['C','L\'ovaire',0],['D','Le vagin',0]]],
    ["Quelle molécule transporte l'oxygène dans le sang ?", 'ELEMENTAIRE', 'ENAFEP', [['A','L\'hémoglobine',1],['B','L\'albumine',0],['C','Le fibrinogène',0],['D','Le plasma',0]]],
    ["Le HIV détruit principalement les cellules :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Lymphocytes T CD4',1],['B','Globules rouges',0],['C','Plaquettes',0],['D','Neurones',0]]],
    ["Quelle est la durée normale d'une grossesse humaine ?", 'DEBUTANT', 'ENAFEP', [['A','9 mois (40 semaines environ)',1],['B','10 mois',0],['C','8 mois',0],['D','12 mois',0]]],
    ["Quel est le rôle de l'insuline ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','Réguler la glycémie (taux de sucre dans le sang)',1],['B','Digérer les protéines',0],['C','Transporter l\'oxygène',0],['D','Filtrer le sang',0]]],
    ["Le squsquelette humain comprend combien d'os à la naissance ?", 'AVANCE', 'EXAMEN_ETAT', [['A','270 environ',1],['B','206',0],['C','300',0],['D','350',0]]],
    ["Quelle glande sécrète le suc gastrique ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','L\'estomac',1],['B','Le pancréas',0],['C','L\'intestin grêle',0],['D','Le foie',0]]],
    ["La respiration cellulaire produit :", 'INTERMEDIAIRE', 'TENASOSP', [['A','ATP, CO�,, et H�,,O',1],['B','O�,, et glucose',0],['C','Glucose et CO�,,',0],['D','ATP et O�,,',0]]],
    ["Quelle est la principale cause de la malaria en RDC ?", 'DEBUTANT', 'ENAFEP', [['A','Plasmodium falciparum via les moustiques',1],['B','Un virus',0],['C','Les bactéries de l\'eau',0],['D','Les tiques',0]]],
    ["Quel sens utilise les récepteurs de la rétine ?", 'ELEMENTAIRE', 'ENAFEP', [['A','La vue',1],['B','L\'odorat',0],['C','Le toucher',0],['D','Le go�.t',0]]],
    ["La division cellulaire par mitose produit :", 'AVANCE', 'EXAMEN_ETAT', [['A','Deux cellules filles génétiquement identiques',1],['B','Quatre cellules haploïdes',0],['C','Deux cellules différentes',0],['D','Une seule cellule',0]]],
    ["Quel est le pH du sang humain normal ?", 'AVANCE', 'TENASOSP', [['A','7,35 �?" 7,45',1],['B','6,8 �?" 7,0',0],['C','7,8 �?" 8,2',0],['D','5,5 �?" 6,5',0]]],
    ["La chlorophylle absorbe principalement la lumière :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Rouge et bleue',1],['B','Verte',0],['C','Blanche',0],['D','Ultraviolette',0]]],
    ["Quel est l'organe central du système nerveux ?", 'DEBUTANT', 'ENAFEP', [['A','L\'encéphale (cerveau)',1],['B','La moelle épinière',0],['C','Le c�"ur',0],['D','La peau',0]]],
    ["Les vaccins agissent en :", 'ELEMENTAIRE', 'ENAFEP', [['A','Stimulant la mémoire immunitaire',1],['B','Tuant directement les microbes',0],['C','Détruisant les toxines',0],['D','Augmentant la température',0]]],
    ["La digestion de l'amidon commence dans :", 'ELEMENTAIRE', 'ENAFEP', [['A','La bouche (par l\'amylase salivaire)',1],['B','L\'estomac',0],['C','Le duodénum',0],['D','Le côlon',0]]],
    ["Quelle hormone déclenche la puberté chez le garçon ?", 'AVANCE', 'TENASOSP', [['A','La testostérone',1],['B','L\'adrénaline',0],['C','L\'insuline',0],['D','La progestérone',0]]],
    ["Quel type d'os forme le crâne ?", 'ELEMENTAIRE', 'ENAFEP', [['A','Os plats',1],['B','Os longs',0],['C','Os courts',0],['D','Os irréguliers',0]]],
    ["Le nerf optique relie l'�"il à :", 'ELEMENTAIRE', 'ENAFEP', [['A','Le cerveau',1],['B','Le tronc cérébral',0],['C','La moelle épinière',0],['D','L\'oreille',0]]],
    ["Quel organe produit la testostérone chez l'homme ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','Les testicules',1],['B','Les surrénales uniquement',0],['C','L\'hypophyse',0],['D','Le foie',0]]],
    ["La couche d'ozone protège la Terre contre :", 'ELEMENTAIRE', 'ENAFEP', [['A','Les rayons UV',1],['B','La pluie acide',0],['C','Les rayons infrarouges',0],['D','La pollution',0]]],
    ["Quelles sont les 4 étapes de la digestion ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','Ingestion, digestion, absorption, élimination',1],['B','Mastication, déglutition, absorption, excrétion',0],['C','Ingestion, fermentation, assimilation, évacuation',0],['D','Dégestion, absorption, filtration, élimination',0]]],
    ["Quel est le rôle des plaquettes sanguines ?", 'ELEMENTAIRE', 'ENAFEP', [['A','Coagulation du sang',1],['B','Transport de l\'oxygène',0],['C','Défense immunitaire',0],['D','Production d\'hormones',0]]],
    ["L'eau représente environ quelle proportion du corps humain ?", 'ELEMENTAIRE', 'ENAFEP', [['A','60 �?" 70 %',1],['B','30 �?" 40 %',0],['C','80 �?" 90 %',0],['D','20 �?" 30 %',0]]],
    ["Quel est le principal gaz de l'atmosphère terrestre ?", 'DEBUTANT', 'ENAFEP', [['A','Azote (N�,, : 78%)',1],['B','Oxygène (O�,,)',0],['C','CO�,,',0],['D','Argon',0]]],
    ["La multiplication végétative (reproduction asexuée des plantes) peut se faire par :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Bouture, marcottage, greffe',1],['B','Pollinisation uniquement',0],['C','Fécondation croisée',0],['D','Sporulation uniquement',0]]],
    ["Quelle partie du cerveau contrôle l'équilibre ?", 'AVANCE', 'TENASOSP', [['A','Le cervelet',1],['B','Le cortex frontal',0],['C','L\'hippocampe',0],['D','Le thalamus',0]]],
    ["Qu'est-ce que l'osmose ?", 'AVANCE', 'EXAMEN_ETAT', [['A','Passage de l\'eau à travers une membrane semi-perméable du milieu dilué vers le milieu concentré',1],['B','Dissolution d\'un solide dans l\'eau',0],['C','Filtration mécanique',0],['D','Diffusion des ions',0]]],
    ["Quel est l'effet de serre naturel ?", 'ELEMENTAIRE', 'ENAFEP', [['A','Maintien de la chaleur sur Terre grâce à CO�,,, H�,,O et CH�,"',1],['B','Destruction de la couche d\'ozone',0],['C','Réchauffement uniquement artificiel',0],['D','Absorption des UV',0]]],
    ["Les bactéries sont des organismes :", 'ELEMENTAIRE', 'ENAFEP', [['A','Procaryotes unicellulaires',1],['B','Eucaryotes multicellulaires',0],['C','Virus',0],['D','Champignons',0]]],
    ["La meiose se déroule dans les organes :", 'AVANCE', 'EXAMEN_ETAT', [['A','Reproducteurs (gonades)',1],['B','Musculaires',0],['C','Nerveux',0],['D','Digestifs',0]]],
    ["Quel est le minéral essentiel pour la solidité des os ?", 'DEBUTANT', 'ENAFEP', [['A','Calcium',1],['B','Fer',0],['C','Sodium',0],['D','Magnésium',0]]],
    ["La photosynthèse libère de l'oxygène grâce à la décomposition de :", 'INTERMEDIAIRE', 'TENASOSP', [['A','L\'eau (H�,,O)',1],['B','CO�,,',0],['C','Glucose',0],['D','L\'air',0]]],
];
foreach ($questions_sc as $q) { addQ($pdo, $stQ, $stO, $matMap[$m], ...$q); $added++; }
echo "Sciences: +" . ($added-$prevAdded) . " questions\n";

/* �.��.� HISTOIRE-G�?O (besoin ~42 pour atteindre 125) �.��.��.��.��.��.��.��.��.��.��.� */
$prevAdded = $added;
$m = 'histgeo';
$questions_hg = [
    ["Quelle est la capitale de la RDC ?", 'DEBUTANT', 'ENAFEP', [['A','Kinshasa',1],['B','Lubumbashi',0],['C','Brazzaville',0],['D','Bukavu',0]]],
    ["Quel est le plus grand pays d'Afrique par superficie ?", 'ELEMENTAIRE', 'ENAFEP', [['A','Algérie',1],['B','RDC',0],['C','Soudan',0],['D','Libye',0]]],
    ["En quelle année les Nations Unies ont-elles été fondées ?", 'ELEMENTAIRE', 'ENAFEP', [['A','1945',1],['B','1939',0],['C','1948',0],['D','1955',0]]],
    ["Quel pays a colonisé la RDC avant l'indépendance de 1960 ?", 'DEBUTANT', 'ENAFEP', [['A','La Belgique',1],['B','La France',0],['C','Le Portugal',0],['D','L\'Angleterre',0]]],
    ["Quel est le fleuve qui traverse Kinshasa ?", 'DEBUTANT', 'ENAFEP', [['A','Le fleuve Congo',1],['B','Le Nil',0],['C','Le Niger',0],['D','Le Kasaï',0]]],
    ["Qui a été le premier président de la RDC après l'indépendance ?", 'ELEMENTAIRE', 'ENAFEP', [['A','Joseph Kasa-Vubu',1],['B','Mobutu Sese Seko',0],['C','Patrice Lumumba',0],['D','Moise Tshombe',0]]],
    ["La Première Guerre mondiale a commencé en :", 'ELEMENTAIRE', 'ENAFEP', [['A','1914',1],['B','1918',0],['C','1939',0],['D','1900',0]]],
    ["Quel est le continent le plus peuplé du monde ?", 'DEBUTANT', 'ENAFEP', [['A','Asie',1],['B','Afrique',0],['C','Europe',0],['D','Amérique',0]]],
    ["La Seconde Guerre mondiale s'est terminée en :", 'ELEMENTAIRE', 'ENAFEP', [['A','1945',1],['B','1944',0],['C','1946',0],['D','1943',0]]],
    ["Quel est le tropique qui passe par l'hémisphère nord ?", 'ELEMENTAIRE', 'TENASOSP', [['A','Tropique du Cancer',1],['B','Tropique du Capricorne',0],['C','�?quateur',0],['D','Cercle arctique',0]]],
    ["La décolonisation en Afrique a principalement eu lieu dans les années :", 'ELEMENTAIRE', 'TENASOSP', [['A','1950 �?" 1970',1],['B','1900 �?" 1920',0],['C','1930 �?" 1940',0],['D','1980 �?" 2000',0]]],
    ["Quel est le plus long fleuve du monde ?", 'ELEMENTAIRE', 'ENAFEP', [['A','Le Nil',1],['B','L\'Amazone',0],['C','Le Congo',0],['D','Le Yangtsé',0]]],
    ["La RDC partage une frontière avec combien de pays ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','9',1],['B','7',0],['C','5',0],['D','11',0]]],
    ["Quel est le point culminant de l'Afrique ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','Le Kilimandjaro (5 895 m)',1],['B','Le Mont Kenya',0],['C','L\'Atlas',0],['D','Le Mont Cameroun',0]]],
    ["L'apartheid en Afrique du Sud a pris fin officiellement en :", 'INTERMEDIAIRE', 'TENASOSP', [['A','1994',1],['B','1990',0],['C','1985',0],['D','2000',0]]],
    ["Patrice Lumumba a été le premier :", 'ELEMENTAIRE', 'ENAFEP', [['A','Premier Ministre de la RDC',1],['B','Président de la RDC',0],['C','Chef de l\'armée congolaise',0],['D','Gouverneur du Katanga',0]]],
    ["La conférence de Berlin (1884-1885) a abouti à :", 'AVANCE', 'EXAMEN_ETAT', [['A','Le partage de l\'Afrique entre puissances européennes',1],['B','La fin de la traite négrière',0],['C','L\'indépendance de l\'�?gypte',0],['D','La création de l\'Union africaine',0]]],
    ["Quel est l'océan à l'ouest de l'Afrique ?", 'DEBUTANT', 'ENAFEP', [['A','L\'Atlantique',1],['B','L\'Indien',0],['C','Le Pacifique',0],['D','L\'Arctique',0]]],
    ["La Révolution française a eu lieu en :", 'ELEMENTAIRE', 'ENAFEP', [['A','1789',1],['B','1804',0],['C','1815',0],['D','1776',0]]],
    ["Qui est l'auteur du discours 'I have a dream' ?", 'ELEMENTAIRE', 'TENASOSP', [['A','Martin Luther King Jr.',1],['B','Malcolm X',0],['C','Nelson Mandela',0],['D','Barack Obama',0]]],
    ["Quelle est la monnaie officielle de la RDC ?", 'DEBUTANT', 'ENAFEP', [['A','Le Franc congolais (CDF)',1],['B','Le Franc CFA',0],['C','Le Dollar congolais',0],['D','Le Shilling',0]]],
    ["Quelle organisation régionale regroupe les pays d'Afrique centrale ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','CEEAC (Communauté �?conomique des �?tats d\'Afrique Centrale)',1],['B','CEDEAO',0],['C','SADC',0],['D','UMA',0]]],
    ["Quel est le désert le plus grand du monde ?", 'ELEMENTAIRE', 'ENAFEP', [['A','Le Sahara',1],['B','Le Kalahari',0],['C','L\'Antarctique (glace)',0],['D','L\'Arabie',0]]],
    ["L'OUA (Organisation de l'Unité Africaine) a été fondée en :", 'INTERMEDIAIRE', 'TENASOSP', [['A','1963',1],['B','1945',0],['C','1960',0],['D','1975',0]]],
    ["Quel pays est à la fois en Afrique et en Asie ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','�?gypte',1],['B','Maroc',0],['C','Tunisie',0],['D','Libye',0]]],
    ["Mobutu Sese Seko a renommé le Congo en :", 'ELEMENTAIRE', 'ENAFEP', [['A','Zaïre',1],['B','Congo-Kinshasa',0],['C','République Centrale Africaine',0],['D','Congo-Belge',0]]],
    ["Quelle mer sépare l'Europe de l'Afrique du Nord ?", 'ELEMENTAIRE', 'ENAFEP', [['A','La mer Méditerranée',1],['B','La mer Rouge',0],['C','La mer Noire',0],['D','L\'Atlantique',0]]],
    ["Quel est le principal minerai extrait au Katanga ?", 'ELEMENTAIRE', 'ENAFEP', [['A','Le cuivre',1],['B','L\'or',0],['C','Le diamant',0],['D','Le pétrole',0]]],
    ["La Charte des Nations Unies a été signée à :", 'AVANCE', 'EXAMEN_ETAT', [['A','San Francisco',1],['B','New York',0],['C','Genève',0],['D','Londres',0]]],
    ["Quel événement a déclenché la Première Guerre mondiale ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','L\'assassinat de l\'archiduc François-Ferdinand à Sarajevo',1],['B','L\'invasion de la Pologne',0],['C','La révolution bolchévique',0],['D','La crise de Suez',0]]],
    ["La RDC est traversée par l'�?quateur, ce qui lui donne :", 'ELEMENTAIRE', 'ENAFEP', [['A','Un climat équatorial (chaud et humide)',1],['B','Un climat désertique',0],['C','Un climat tempéré',0],['D','Un climat polaire',0]]],
    ["Quelle ville est surnommée 'la ville minière' en RDC ?", 'ELEMENTAIRE', 'ENAFEP', [['A','Lubumbashi',1],['B','Kinshasa',0],['C','Goma',0],['D','Kisangani',0]]],
    ["La région des Grands Lacs africains comprend notamment :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Les lacs Victoria, Tanganyika, Malawi',1],['B','Les lacs Titicaca et Baikal',0],['C','La mer Caspienne',0],['D','Le lac Baïkal uniquement',0]]],
    ["Quelle est la superficie de la RDC (environ) ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','2 344 000 km²',1],['B','1 200 000 km²',0],['C','3 500 000 km²',0],['D','945 000 km²',0]]],
    ["L'UNICEF est l'agence des Nations Unies chargée de :", 'ELEMENTAIRE', 'ENAFEP', [['A','L\'enfance',1],['B','L\'alimentation',0],['C','La santé',0],['D','L\'éducation des adultes',0]]],
    ["Qui a dirigé la lutte contre l'apartheid en Afrique du Sud ?", 'ELEMENTAIRE', 'ENAFEP', [['A','Nelson Mandela',1],['B','Kofi Annan',0],['C','Kwame Nkrumah',0],['D','Julius Nyerere',0]]],
    ["En géographie, la 'latitude' mesure :", 'ELEMENTAIRE', 'ENAFEP', [['A','La distance angulaire par rapport à l\'�?quateur',1],['B','La distance par rapport au méridien de Greenwich',0],['C','L\'altitude d\'un lieu',0],['D','La superficie d\'un pays',0]]],
    ["Quel est le rôle du Conseil de sécurité de l'ONU ?", 'AVANCE', 'EXAMEN_ETAT', [['A','Maintenir la paix et la sécurité internationales',1],['B','Gérer les finances mondiales',0],['C','Juger les chefs d\'�?tat',0],['D','Distribuer l\'aide humanitaire',0]]],
    ["La guerre froide opposait principalement :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Les �?tats-Unis et l\'URSS',1],['B','L\'Allemagne et la France',0],['C','L\'Angleterre et l\'Espagne',0],['D','La Chine et le Japon',0]]],
    ["Quelle province de la RDC est connue pour la production de coltan ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','Nord-Kivu et Sud-Kivu',1],['B','Kinshasa',0],['C','Bas-Congo',0],['D','Kasaï',0]]],
    ["L'agriculture de subsistance est caractérisée par :", 'ELEMENTAIRE', 'ENAFEP', [['A','La production destinée à l\'autoconsommation',1],['B','La production pour l\'exportation',0],['C','L\'utilisation intensive de machines',0],['D','La monoculture',0]]],
];
foreach ($questions_hg as $q) { addQ($pdo, $stQ, $stO, $matMap[$m], ...$q); $added++; }
echo "Histoire-G�?O: +" . ($added-$prevAdded) . " questions\n";

/* �.��.� CHIMIE (besoin ~38 pour atteindre 125) �.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.� */
$prevAdded = $added;
$m = 'chimie';
$questions_ch = [
    ["Quel est le symbole chimique de l'or ?", 'DEBUTANT', 'ENAFEP', [['A','Au',1],['B','Or',0],['C','Go',0],['D','Ag',0]]],
    ["La formule du dioxyde de carbone est :", 'DEBUTANT', 'ENAFEP', [['A','CO�,,',1],['B','CO',0],['C','C�,,O',0],['D','HCO�,f',0]]],
    ["Un atome est électriquement neutre car :", 'ELEMENTAIRE', 'ENAFEP', [['A','Le nombre de protons = nombre d\'électrons',1],['B','Il n\'a pas de charge',0],['C','Les neutrons compensent les protons',0],['D','Les électrons annulent les neutrons',0]]],
    ["Quelle est la formule de l'acide sulfurique ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','H�,,SO�,"',1],['B','HCl',0],['C','HNO�,f',0],['D','H�,,SO�,f',0]]],
    ["La liaison covalente résulte de :", 'AVANCE', 'EXAMEN_ETAT', [['A','Mise en commun d\'électrons entre deux atomes',1],['B','Transfert d\'électrons',0],['C','Attraction entre ions de charges opposées',0],['D','Forces de Van der Waals',0]]],
    ["Quel est le numéro atomique de l\'hydrogène ?", 'DEBUTANT', 'ENAFEP', [['A','1',1],['B','2',0],['C','6',0],['D','8',0]]],
    ["La neutralisation acide-base produit :", 'ELEMENTAIRE', 'ENAFEP', [['A','Sel + eau',1],['B','Acide + base',0],['C','Oxyde + eau',0],['D','Hydrogène + oxygène',0]]],
    ["Quelle est la couleur du papier tournesol en présence d'un acide ?", 'DEBUTANT', 'ENAFEP', [['A','Rouge',1],['B','Bleu',0],['C','Vert',0],['D','Incolore',0]]],
    ["La masse molaire de l'eau (H�,,O) est :", 'ELEMENTAIRE', 'ENAFEP', [['A','18 g/mol',1],['B','16 g/mol',0],['C','20 g/mol',0],['D','12 g/mol',0]]],
    ["Dans la classification périodique, les halogènes sont dans la colonne :", 'INTERMEDIAIRE', 'TENASOSP', [['A','17 (VIIA)',1],['B','1 (IA)',0],['C','18 (VIII)',0],['D','16 (VIA)',0]]],
    ["La réaction : Zn + CuSO�," �?' ZnSO�," + Cu est une réaction :", 'AVANCE', 'TENASOSP', [['A','De déplacement / substitution',1],['B','De combinaison',0],['C','De décomposition',0],['D','De double déplacement',0]]],
    ["Quelle est la valence du carbone ?", 'ELEMENTAIRE', 'ENAFEP', [['A','4',1],['B','2',0],['C','6',0],['D','8',0]]],
    ["L'électrolyse de l'eau produit :", 'INTERMEDIAIRE', 'TENASOSP', [['A','H�,, et O�,,',1],['B','HO⁻ uniquement',0],['C','H�,,O�,,',0],['D','H�,, uniquement',0]]],
    ["Quel ion est responsable de l'acidité d'une solution ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','H⁺ (ou H�,fO⁺)',1],['B','OH⁻',0],['C','Na⁺',0],['D','Cl⁻',0]]],
    ["La formule de l'éthanol est :", 'INTERMEDIAIRE', 'TENASOSP', [['A','C�,,H�,.OH',1],['B','CH�,fOH',0],['C','C�,fH�,?OH',0],['D','C�,,H�,"',0]]],
    ["Quelle est la loi de conservation de la masse ?", 'ELEMENTAIRE', 'ENAFEP', [['A','La masse des réactifs = masse des produits',1],['B','La masse diminue pendant la réaction',0],['C','La masse augmente avec la chaleur',0],['D','La masse est variable',0]]],
    ["L'atome de fer a pour symbole :", 'DEBUTANT', 'ENAFEP', [['A','Fe',1],['B','Fr',0],['C','Fn',0],['D','Ir',0]]],
    ["Un oxyde est un composé de l'oxygène avec :", 'ELEMENTAIRE', 'ENAFEP', [['A','Un autre élément',1],['B','Uniquement un métal',0],['C','L\'hydrogène uniquement',0],['D','Un acide',0]]],
    ["Quelle est la charge d'un proton ?", 'DEBUTANT', 'ENAFEP', [['A','+1',1],['B','-1',0],['C','0',0],['D','+2',0]]],
    ["La combustion complète d'un hydrocarbure produit :", 'INTERMEDIAIRE', 'TENASOSP', [['A','CO�,, et H�,,O',1],['B','CO et H�,,',0],['C','C et H�,,O',0],['D','CO et H�,,O',0]]],
    ["Le nombre d'Avogadro est (environ) :", 'INTERMEDIAIRE', 'TENASOSP', [['A','6,022 �- 10²³',1],['B','6,022 �- 10¹⁸',0],['C','3,14 �- 10²³',0],['D','1,6 �- 10⁻¹⁹',0]]],
    ["Quelle est la propriété d'un sel ?", 'ELEMENTAIRE', 'ENAFEP', [['A','Produit par neutralisation acide-base',1],['B','Toujours soluble dans l\'eau',0],['C','Toujours basique',0],['D','Contient toujours du sodium',0]]],
    ["La formule du chlorure de sodium (sel de table) est :", 'DEBUTANT', 'ENAFEP', [['A','NaCl',1],['B','NaOH',0],['C','Na�,,Cl',0],['D','NCl',0]]],
    ["Dans une solution aqueuse, KOH est :", 'ELEMENTAIRE', 'ENAFEP', [['A','Une base forte',1],['B','Un acide fort',0],['C','Un sel neutre',0],['D','Un oxyde',0]]],
    ["Quelle est la couche électronique externe appelée ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','Couche de valence',1],['B','Couche K',0],['C','Couche noyau',0],['D','Couche sigma',0]]],
    ["Le méthane (CH�,") appartient à la famille des :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Alcanes',1],['B','Alcènes',0],['C','Alcynes',0],['D','Aromatiques',0]]],
    ["Quelle réaction libère de l'énergie (exothermique) ?", 'AVANCE', 'EXAMEN_ETAT', [['A','La combustion',1],['B','L\'électrolyse',0],['C','La photosynthèse',0],['D','La dissolution de NH�,"NO�,f',0]]],
    ["La loi de Dalton concerne :", 'AVANCE', 'EXAMEN_ETAT', [['A','Les pressions partielles des gaz',1],['B','La conservation de la masse',0],['C','Les proportions des éléments',0],['D','Le volume des gaz',0]]],
    ["La formule brute du glucose est :", 'INTERMEDIAIRE', 'TENASOSP', [['A','C�,?H�,��,,O�,?',1],['B','C�,��,,H�,,�,,O�,��,�',0],['C','C�,?H�,��,?O�,.',0],['D','CH�,,O',0]]],
    ["Quel type de réaction est : A + B �?' AB ?", 'ELEMENTAIRE', 'ENAFEP', [['A','Réaction de combinaison (synthèse)',1],['B','Réaction de décomposition',0],['C','Réaction de substitution',0],['D','Oxydoréduction',0]]],
    ["Quel est le pH d'une solution basique ?", 'ELEMENTAIRE', 'ENAFEP', [['A','Supérieur à 7',1],['B','Inférieur à 7',0],['C','�?gal à 7',0],['D','Variable',0]]],
    ["L'oxydation d'un métal correspond à :", 'AVANCE', 'TENASOSP', [['A','La perte d\'électrons',1],['B','Le gain d\'électrons',0],['C','La perte de protons',0],['D','Le gain de neutrons',0]]],
    ["La rouille (oxyde de fer) a pour formule :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Fe�,,O�,f',1],['B','FeO',0],['C','Fe�,fO�,"',0],['D','FeCl�,,',0]]],
    ["Quel est le catalyseur dans la synthèse de l'ammoniac (procédé Haber) ?", 'AVANCE', 'EXAMEN_ETAT', [['A','Fer (Fe)',1],['B','Platine (Pt)',0],['C','Vanadium (V�,,O�,.)',0],['D','Nickel (Ni)',0]]],
    ["La concentration molaire s'exprime en :", 'INTERMEDIAIRE', 'TENASOSP', [['A','mol/L',1],['B','g/L',0],['C','kg/mol',0],['D','mol/m²',0]]],
    ["Qu'est-ce qu'un isotope ?", 'AVANCE', 'EXAMEN_ETAT', [['A','Atomes d\'un même élément avec des nombres de neutrons différents',1],['B','Atomes de même masse',0],['C','Ions de même charge',0],['D','Molécules isomères',0]]],
    ["Le brome est un élément halogène de symbole :", 'ELEMENTAIRE', 'ENAFEP', [['A','Br',1],['B','B',0],['C','Bn',0],['D','Bm',0]]],
];
foreach ($questions_ch as $q) { addQ($pdo, $stQ, $stO, $matMap[$m], ...$q); $added++; }
echo "Chimie: +" . ($added-$prevAdded) . " questions\n";

/* �.��.� PHYSIQUE (besoin ~37 pour atteindre 125) �.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.� */
$prevAdded = $added;
$m = 'physique';
$questions_ph = [
    ["Quelle est la formule de la vitesse ?", 'DEBUTANT', 'ENAFEP', [['A','v = d/t',1],['B','v = d�-t',0],['C','v = t/d',0],['D','v = m�-a',0]]],
    ["L'unité du travail dans le SI est :", 'ELEMENTAIRE', 'ENAFEP', [['A','Joule (J)',1],['B','Watt (W)',0],['C','Newton (N)',0],['D','Pascal (Pa)',0]]],
    ["La loi d'Ohm s'écrit :", 'ELEMENTAIRE', 'ENAFEP', [['A','U = R �- I',1],['B','U = I / R',0],['C','R = U �- I',0],['D','I = U �- R',0]]],
    ["Quelle est la fréquence du courant alternatif en RDC ?", 'ELEMENTAIRE', 'ENAFEP', [['A','50 Hz',1],['B','60 Hz',0],['C','100 Hz',0],['D','25 Hz',0]]],
    ["L'énergie cinétique d'un objet est donnée par :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Ec = ½mv²',1],['B','Ec = mgh',0],['C','Ec = mv',0],['D','Ec = Fd',0]]],
    ["Quelle est la résistance équivalente de deux résistances R en série ?", 'ELEMENTAIRE', 'ENAFEP', [['A','2R',1],['B','R/2',0],['C','R²',0],['D','R',0]]],
    ["Un corps de 2 kg tombe en chute libre. L'accélération gravitationnelle est (g �?^ 10 m/s²). La force exercée est :", 'ELEMENTAIRE', 'ENAFEP', [['A','20 N',1],['B','2 N',0],['C','10 N',0],['D','5 N',0]]],
    ["La loi de la réflexion de la lumière stipule que :", 'ELEMENTAIRE', 'ENAFEP', [['A','Angle d\'incidence = angle de réflexion',1],['B','La lumière est absorbée',0],['C','L\'angle de réfraction est nul',0],['D','La lumière change de vitesse',0]]],
    ["Quelle est l'unité de la pression ?", 'DEBUTANT', 'ENAFEP', [['A','Pascal (Pa)',1],['B','Newton (N)',0],['C','Joule (J)',0],['D','Watt (W)',0]]],
    ["La tension d'une batterie standard AA est :", 'DEBUTANT', 'ENAFEP', [['A','1,5 V',1],['B','3 V',0],['C','9 V',0],['D','12 V',0]]],
    ["Quelle est la formule de la puissance électrique ?", 'ELEMENTAIRE', 'ENAFEP', [['A','P = U �- I',1],['B','P = U / I',0],['C','P = I²',0],['D','P = U + I',0]]],
    ["La densité de l'eau est :", 'DEBUTANT', 'ENAFEP', [['A','1 g/cm³',1],['B','0,5 g/cm³',0],['C','2 g/cm³',0],['D','1,5 g/cm³',0]]],
    ["La réfraction de la lumière se produit quand :", 'INTERMEDIAIRE', 'TENASOSP', [['A','La lumière passe d\'un milieu à un autre de densité différente',1],['B','La lumière est réfléchie',0],['C','La lumière est absorbée',0],['D','La lumière se disperse',0]]],
    ["Quelle est la quantité de chaleur Q pour chauffer 1 kg d'eau de 20°C à 100°C ? (c = 4200 J/kg°C)", 'AVANCE', 'TENASOSP', [['A','336 000 J',1],['B','84 000 J',0],['C','420 000 J',0],['D','168 000 J',0]]],
    ["La deuxième loi de Newton s'énonce :", 'INTERMEDIAIRE', 'TENASOSP', [['A','F = m �- a',1],['B','F = m / a',0],['C','F = m + a',0],['D','a = F + m',0]]],
    ["Un circuit en parallèle se caractérise par :", 'ELEMENTAIRE', 'ENAFEP', [['A','La même tension aux bornes de chaque composant',1],['B','Le même courant dans chaque composant',0],['C','La somme des résistances',0],['D','L\'intensité nulle',0]]],
    ["Quelle est la longueur d'onde de la lumière visible (environ) ?", 'AVANCE', 'TENASOSP', [['A','400 �?" 700 nm',1],['B','10 �?" 100 nm',0],['C','1 mm �?" 1 m',0],['D','0,1 �?" 1 nm',0]]],
    ["Le principe d'Archimède stipule qu'un corps immergé subit :", 'ELEMENTAIRE', 'ENAFEP', [['A','Une poussée verticale vers le haut égale au poids du fluide déplacé',1],['B','Une pression latérale',0],['C','Une force vers le bas',0],['D','Aucune force',0]]],
    ["La chaleur se propage par conduction, convection et :", 'ELEMENTAIRE', 'ENAFEP', [['A','Rayonnement',1],['B','�?vaporation',0],['C','Fusion',0],['D','Condensation',0]]],
    ["Le générateur d'un circuit électrique est source de :", 'ELEMENTAIRE', 'ENAFEP', [['A','Tension (force électromotrice)',1],['B','Résistance',0],['C','Intensité nulle',0],['D','Fréquence',0]]],
    ["La vitesse du son dans l'air à 20°C est environ :", 'INTERMEDIAIRE', 'TENASOSP', [['A','340 m/s',1],['B','300 000 km/s',0],['C','1500 m/s',0],['D','150 m/s',0]]],
    ["Quel phénomène explique l'arc-en-ciel ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','Dispersion de la lumière par réfraction dans les gouttes d\'eau',1],['B','Réflexion totale dans l\'air',0],['C','Diffraction de la lumière',0],['D','Polarisation',0]]],
    ["La résistance d'un conducteur dépend de :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Sa longueur, section et résistivité du matériau',1],['B','La tension appliquée uniquement',0],['C','Le courant uniquement',0],['D','La fréquence',0]]],
    ["L'énergie potentielle gravitationnelle est donnée par :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Ep = mgh',1],['B','Ep = mv²/2',0],['C','Ep = Fd',0],['D','Ep = P/t',0]]],
    ["Quelle est la fréquence d'un son de période T = 0,02 s ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','50 Hz',1],['B','200 Hz',0],['C','0,02 Hz',0],['D','20 Hz',0]]],
    ["Le magnétisme est lié à :", 'ELEMENTAIRE', 'ENAFEP', [['A','Les charges électriques en mouvement',1],['B','Les charges au repos',0],['C','La température',0],['D','La masse',0]]],
    ["La dilatation thermique se produit quand :", 'ELEMENTAIRE', 'ENAFEP', [['A','Un corps se dilate en se réchauffant',1],['B','Un corps se contracte sous pression',0],['C','Un corps change d\'état',0],['D','Un corps refroidit',0]]],
    ["Quelle est la première loi de Kepler ?", 'AVANCE', 'EXAMEN_ETAT', [['A','Les planètes décrivent des ellipses autour du Soleil',1],['B','Les planètes se déplacent en ligne droite',0],['C','La période est proportionnelle à la distance',0],['D','Toutes les orbites sont circulaires',0]]],
    ["Le rendement d'une machine est :", 'INTERMEDIAIRE', 'TENASOSP', [['A','η = énergie utile / énergie fournie �- 100%',1],['B','η = énergie perdue / énergie fournie',0],['C','η = puissance �- temps',0],['D','η = force �- distance',0]]],
    ["Dans un fil conducteur, les porteurs de charges sont :", 'ELEMENTAIRE', 'ENAFEP', [['A','Les électrons libres',1],['B','Les protons',0],['C','Les neutrons',0],['D','Les ions positifs',0]]],
    ["Qu'est-ce que l'effet photoélectrique ?", 'AVANCE', 'EXAMEN_ETAT', [['A','�?mission d\'électrons par un métal éclairé',1],['B','�?mission de photons',0],['C','Réflexion de la lumière',0],['D','Réfraction de la lumière',0]]],
    ["La loi de gravitation universelle de Newton : F = G �- m�,�m�,,/r² �?" G représente :", 'AVANCE', 'EXAMEN_ETAT', [['A','La constante gravitationnelle universelle',1],['B','L\'accélération',0],['C','Le poids spécifique',0],['D','La force centripète',0]]],
    ["Un transformateur abaisseur a un rapport de transformation n = N�,�/N�,, :", 'AVANCE', 'EXAMEN_ETAT', [['A','Supérieur à 1',1],['B','Inférieur à 1',0],['C','�?gal à 1',0],['D','Négatif',0]]],
    ["Quelle est l'unité de la capacité électrique ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','Farad (F)',1],['B','Ohm (Ω)',0],['C','Henry (H)',0],['D','Coulomb (C)',0]]],
    ["Le principe de conservation de l'énergie stipule que :", 'INTERMEDIAIRE', 'TENASOSP', [['A','L\'énergie totale d\'un système isolé reste constante',1],['B','L\'énergie peut être créée ou détruite',0],['C','L\'énergie cinétique est toujours maximale',0],['D','La chaleur est toujours perdue',0]]],
    ["La période d'un pendule simple dépend principalement de :", 'AVANCE', 'TENASOSP', [['A','La longueur du fil et g',1],['B','La masse du pendule',0],['C','L\'amplitude d\'oscillation',0],['D','La matière du pendule',0]]],
];
foreach ($questions_ph as $q) { addQ($pdo, $stQ, $stO, $matMap[$m], ...$q); $added++; }
echo "Physique: +" . ($added-$prevAdded) . " questions\n";

/* �.��.� BIOLOGIE (besoin ~37 pour atteindre 125) �.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.� */
$prevAdded = $added;
$m = 'biologie';
$questions_bio = [
    ["L'ADN se trouve principalement dans :", 'ELEMENTAIRE', 'ENAFEP', [['A','Le noyau de la cellule',1],['B','Les mitochondries',0],['C','Le cytoplasme',0],['D','La membrane',0]]],
    ["Quelle est la base azotée complémentaire de l'adénine dans l'ADN ?", 'AVANCE', 'EXAMEN_ETAT', [['A','Thymine',1],['B','Guanine',0],['C','Cytosine',0],['D','Uracile',0]]],
    ["Les organismes autotrophes produisent leur matière organique grâce à :", 'INTERMEDIAIRE', 'TENASOSP', [['A','La photosynthèse (énergie lumineuse)',1],['B','La consommation d\'autres organismes',0],['C','La fermentation',0],['D','La décomposition',0]]],
    ["La respiration cellulaire a�?robie utilise :", 'INTERMEDIAIRE', 'TENASOSP', [['A','O�,, et glucose',1],['B','CO�,, et eau uniquement',0],['C','Lumière et chlorophylle',0],['D','ATP uniquement',0]]],
    ["Quelle est la fonction des ribosomes ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','Synthèse des protéines',1],['B','Production d\'ATP',0],['C','Digestion cellulaire',0],['D','Division cellulaire',0]]],
    ["La génétique est la science qui étudie :", 'DEBUTANT', 'ENAFEP', [['A','L\'hérédité et la variation génétique',1],['B','Les maladies infectieuses',0],['C','Le développement des embryons',0],['D','La structure cellulaire',0]]],
    ["Le gène dominant s'exprime :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Même en présence d\'un seul allèle',1],['B','Uniquement en homozygotie',0],['C','Jamais en présence d\'un allèle récessif',0],['D','Seulement chez les femmes',0]]],
    ["Les chloroplastes sont présents dans :", 'ELEMENTAIRE', 'ENAFEP', [['A','Les cellules végétales',1],['B','Les cellules animales',0],['C','Les cellules bactériennes',0],['D','Les champignons',0]]],
    ["La classification de Linné repose sur :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Des critères morphologiques et génétiques',1],['B','La couleur et la taille',0],['C','Uniquement le milieu de vie',0],['D','Le mode de reproduction',0]]],
    ["Quel est le rôle de l'enzyme dans une réaction biochimique ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','Catalyser (accélérer) la réaction sans être consommé',1],['B','Fournir l\'énergie',0],['C','Bloquer la réaction',0],['D','Fixer le pH',0]]],
    ["La glycolyse se déroule dans :", 'AVANCE', 'EXAMEN_ETAT', [['A','Le cytoplasme',1],['B','Les mitochondries',0],['C','Le noyau',0],['D','Le réticulum',0]]],
    ["La fécondation est la fusion de :", 'ELEMENTAIRE', 'ENAFEP', [['A','Un gamète m�le et un gamète femelle',1],['B','Deux cellules somatiques',0],['C','Deux noyaux quelconques',0],['D','Deux cellules haploïdes identiques',0]]],
    ["Quelle est la taille approximative d'une cellule eucaryote typique ?", 'AVANCE', 'TENASOSP', [['A','10 �?" 100 μm',1],['B','1 �?" 5 nm',0],['C','1 �?" 5 mm',0],['D','100 μm �?" 1 cm',0]]],
    ["Le chromosome Y chez l'homme détermine :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Le sexe masculin',1],['B','Le groupe sanguin',0],['C','La couleur des yeux',0],['D','La taille',0]]],
    ["Les antibiotiques agissent sur :", 'ELEMENTAIRE', 'ENAFEP', [['A','Les bactéries',1],['B','Les virus',0],['C','Les champignons',0],['D','Les parasites',0]]],
    ["Le cycle de Krebs se déroule dans :", 'AVANCE', 'EXAMEN_ETAT', [['A','La matrice mitochondriale',1],['B','Le cytoplasme',0],['C','Le réticulum endoplasmique',0],['D','L\'appareil de Golgi',0]]],
    ["L'évolution des espèces est principalement expliquée par :", 'INTERMEDIAIRE', 'TENASOSP', [['A','La sélection naturelle (Darwin)',1],['B','La génération spontanée',0],['C','La volonté des organismes',0],['D','Les mutations uniquement',0]]],
    ["Quelle partie de la fleur est femelle ?", 'ELEMENTAIRE', 'ENAFEP', [['A','Le pistil (carpelle)',1],['B','L\'étamine',0],['C','Le sépale',0],['D','Le pétale',0]]],
    ["La pollinisation est :", 'ELEMENTAIRE', 'ENAFEP', [['A','Le transfert du pollen des étamines au pistil',1],['B','La fusion des gamètes',0],['C','La formation des graines',0],['D','Le développement des fruits',0]]],
    ["Qu'est-ce qu'une mutation ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','Une modification permanente de l\'ADN',1],['B','Un changement de comportement',0],['C','Une variation saisonnière',0],['D','Un changement de couleur',0]]],
    ["Les virus se répliquent uniquement :", 'ELEMENTAIRE', 'ENAFEP', [['A','�? l\'intérieur de cellules hôtes vivantes',1],['B','Dans un milieu liquide',0],['C','En dehors d\'un organisme',0],['D','Dans les laboratoires uniquement',0]]],
    ["La membrane plasmique est composée principalement de :", 'AVANCE', 'EXAMEN_ETAT', [['A','Une bicouche lipidique avec des protéines',1],['B','De cellulose',0],['C','De chitine',0],['D','De glucose',0]]],
    ["Quelle est la principale différence entre cellule procaryote et eucaryote ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','La présence d\'un noyau délimité',1],['B','La présence d\'une membrane',0],['C','La présence d\'ADN',0],['D','La présence de ribosomes',0]]],
    ["Les mycètes (champignons) sont :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Des eucaryotes hétérotrophes',1],['B','Des plantes sans feuilles',0],['C','Des bactéries',0],['D','Des virus',0]]],
    ["Le cholestérol est :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Un lipide essentiel à la membrane cellulaire',1],['B','Un glucide',0],['C','Une protéine',0],['D','Un acide nucléique',0]]],
    ["Le SIDA est causé par :", 'ELEMENTAIRE', 'ENAFEP', [['A','Le virus VIH (rétrovirus)',1],['B','Une bactérie',0],['C','Un parasite',0],['D','Un champignon',0]]],
    ["La transpiration chez les plantes s'appelle :", 'INTERMEDIAIRE', 'TENASOSP', [['A','La transpiration ou évapotranspiration stomatique',1],['B','La photosynthèse',0],['C','L\'absorption racinaire',0],['D','La guttation',0]]],
    ["Quelle est la fonction du foie dans le métabolisme ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','Détoxification, synthèse protéique, stockage glycogène',1],['B','Produire des globules rouges',0],['C','Filtrer le sang',0],['D','Produire l\'adrénaline',0]]],
    ["Les cellules souches ont la capacité de :", 'AVANCE', 'EXAMEN_ETAT', [['A','Se différencier en différents types cellulaires',1],['B','Résister à tous les médicaments',0],['C','Produire des anticorps',0],['D','Uniquement se diviser',0]]],
    ["Le complément alimentaire dont le déficit cause l'anémie est :", 'ELEMENTAIRE', 'ENAFEP', [['A','Le fer',1],['B','Le calcium',0],['C','Le potassium',0],['D','Le zinc',0]]],
    ["L'hybridation en biologie est :", 'AVANCE', 'EXAMEN_ETAT', [['A','Le croisement entre deux espèces différentes',1],['B','La division cellulaire',0],['C','La mutation génique',0],['D','La reproduction asexuée',0]]],
    ["Les plantes carnivores obtiennent l'azote manquant en :", 'AVANCE', 'TENASOSP', [['A','Digérant des insectes et petits animaux',1],['B','Absorbant l\'azote de l\'air',0],['C','Réalisant la photosynthèse',0],['D','Parasitant d\'autres plantes',0]]],
    ["La notochorde est présente chez les :", 'AVANCE', 'EXAMEN_ETAT', [['A','Chordés (au moins à un stade de développement)',1],['B','Insectes uniquement',0],['C','Bactéries',0],['D','Champignons',0]]],
    ["Quelle hormone régule le cycle menstruel féminin ?", 'AVANCE', 'TENASOSP', [['A','LH et FSH (�"strogènes et progestérone)',1],['B','Testostérone uniquement',0],['C','Adrénaline',0],['D','Insuline',0]]],
    ["Les organismes décomposeurs (bactéries, champignons) ont un rôle écologique de :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Recycler la matière organique morte',1],['B','Produire l\'oxygène',0],['C','Fixer l\'azote atmosphérique',0],['D','Prédation',0]]],
    ["Le génotype est :", 'INTERMEDIAIRE', 'TENASOSP', [['A','L\'ensemble des allèles portés par un individu',1],['B','Les caractères visibles',0],['C','L\'environnement de l\'individu',0],['D','Le nombre de chromosomes',0]]],
];
foreach ($questions_bio as $q) { addQ($pdo, $stQ, $stO, $matMap[$m], ...$q); $added++; }
echo "Biologie: +" . ($added-$prevAdded) . " questions\n";

/* �.��.� ANGLAIS (besoin ~42 pour atteindre 125) �.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.� */
$prevAdded = $added;
$m = 'anglais';
$questions_en = [
    ["What is the plural of 'child'?", 'DEBUTANT', 'ENAFEP', [['A','children',1],['B','childs',0],['C','childrens',0],['D','childes',0]]],
    ["Choose the correct verb: 'She ___ to school every day.'", 'DEBUTANT', 'ENAFEP', [['A','goes',1],['B','go',0],['C','going',0],['D','gone',0]]],
    ["What is the comparative of 'good'?", 'ELEMENTAIRE', 'ENAFEP', [['A','better',1],['B','more good',0],['C','gooder',0],['D','best',0]]],
    ["Complete: 'If I had money, I ___ buy a car.'", 'INTERMEDIAIRE', 'TENASOSP', [['A','would',1],['B','will',0],['C','shall',0],['D','can',0]]],
    ["What is the past tense of 'write'?", 'ELEMENTAIRE', 'ENAFEP', [['A','wrote',1],['B','written',0],['C','writed',0],['D','writ',0]]],
    ["'Despite the rain, they continued.' �?" 'Despite' expresses:", 'AVANCE', 'EXAMEN_ETAT', [['A','Contrast/concession',1],['B','Cause',0],['C','Result',0],['D','Condition',0]]],
    ["What does 'benevolent' mean?", 'AVANCE', 'EXAMEN_ETAT', [['A','Kind and generous',1],['B','Aggressive',0],['C','Lazy',0],['D','Intelligent',0]]],
    ["Choose the correct form: 'The book ___ by Shakespeare.'", 'INTERMEDIAIRE', 'TENASOSP', [['A','was written',1],['B','wrote',0],['C','is write',0],['D','writes',0]]],
    ["What is the antonym of 'ancient'?", 'ELEMENTAIRE', 'ENAFEP', [['A','modern',1],['B','old',0],['C','past',0],['D','historic',0]]],
    ["'He has been studying for three hours.' The tense is:", 'AVANCE', 'TENASOSP', [['A','Present perfect continuous',1],['B','Past continuous',0],['C','Past perfect',0],['D','Simple present',0]]],
    ["Choose the correct article: '___ university'", 'INTERMEDIAIRE', 'TENASOSP', [['A','a',1],['B','an',0],['C','the',0],['D','no article',0]]],
    ["What is the superlative of 'far'?", 'INTERMEDIAIRE', 'TENASOSP', [['A','farthest / furthest',1],['B','more far',0],['C','farer',0],['D','the most far',0]]],
    ["Complete: 'By the time she arrived, he ___ already left.'", 'AVANCE', 'EXAMEN_ETAT', [['A','had',1],['B','has',0],['C','was',0],['D','did',0]]],
    ["What does the prefix 'un-' mean?", 'ELEMENTAIRE', 'ENAFEP', [['A','not / reverse',1],['B','again',0],['C','before',0],['D','after',0]]],
    ["'Can you lend me your pen?' is a sentence expressing:", 'ELEMENTAIRE', 'ENAFEP', [['A','A request',1],['B','A command',0],['C','A fact',0],['D','A question about ability',0]]],
    ["Choose the correct question tag: 'She is a doctor, ___?'", 'INTERMEDIAIRE', 'TENASOSP', [['A','isn\'t she',1],['B','is she',0],['C','does she',0],['D','doesn\'t she',0]]],
    ["Which sentence is correct?", 'INTERMEDIAIRE', 'TENASOSP', [['A','Neither John nor his brothers are coming.',1],['B','Neither John nor his brothers is coming.',0],['C','Neither John nor his brothers were come.',0],['D','John nor his brothers are coming.',0]]],
    ["'The bigger the challenge, ___ the reward.' Complete:", 'AVANCE', 'EXAMEN_ETAT', [['A','the greater',1],['B','greatest',0],['C','greater',0],['D','the great',0]]],
    ["What is the noun form of 'achieve'?", 'ELEMENTAIRE', 'ENAFEP', [['A','achievement',1],['B','achieval',0],['C','achieveness',0],['D','achievment',0]]],
    ["'I wish I knew the answer.' This expresses:", 'AVANCE', 'TENASOSP', [['A','A regret/wish about the present',1],['B','A real condition',0],['C','A future plan',0],['D','A past event',0]]],
    ["What type of word is 'quickly' in 'She ran quickly'?", 'DEBUTANT', 'ENAFEP', [['A','Adverb',1],['B','Adjective',0],['C','Noun',0],['D','Verb',0]]],
    ["Choose the correct preposition: 'She is afraid ___ dogs.'", 'ELEMENTAIRE', 'ENAFEP', [['A','of',1],['B','from',0],['C','about',0],['D','with',0]]],
    ["'He suggested going to the cinema.' The gerund is:", 'INTERMEDIAIRE', 'TENASOSP', [['A','going',1],['B','suggested',0],['C','cinema',0],['D','He',0]]],
    ["What is the passive voice of 'The teacher corrects the tests'?", 'AVANCE', 'TENASOSP', [['A','The tests are corrected by the teacher.',1],['B','The tests corrected by the teacher.',0],['C','The teacher is corrected by the tests.',0],['D','The tests were corrected.',0]]],
    ["'Although he was tired, he finished the work.' The clause 'Although he was tired' is:", 'AVANCE', 'EXAMEN_ETAT', [['A','Adverbial clause of concession',1],['B','Relative clause',0],['C','Noun clause',0],['D','Adverbial clause of condition',0]]],
    ["Which word means 'to improve skills'?", 'INTERMEDIAIRE', 'TENASOSP', [['A','enhance',1],['B','decrease',0],['C','ignore',0],['D','weaken',0]]],
    ["'I have lived in Kinshasa since 2010.' This is:", 'INTERMEDIAIRE', 'TENASOSP', [['A','Present perfect simple',1],['B','Past simple',0],['C','Past continuous',0],['D','Future perfect',0]]],
    ["The word 'democracy' means:", 'ELEMENTAIRE', 'ENAFEP', [['A','Government by the people',1],['B','Government by one person',0],['C','Rule by military',0],['D','Rule by religion',0]]],
    ["Choose: 'There ___ a lot of people at the event.'", 'ELEMENTAIRE', 'ENAFEP', [['A','were',1],['B','was',0],['C','is',0],['D','be',0]]],
    ["What does 'eloquent' describe?", 'AVANCE', 'EXAMEN_ETAT', [['A','A skilled, expressive speaker',1],['B','A silent person',0],['C','A confused writer',0],['D','A slow learner',0]]],
    ["'Had I known, I would have helped.' is an example of:", 'EXPERT', 'EXAMEN_ETAT', [['A','Third conditional (inverted)',1],['B','Second conditional',0],['C','First conditional',0],['D','Zero conditional',0]]],
    ["The synonym of 'commence' is:", 'ELEMENTAIRE', 'ENAFEP', [['A','begin',1],['B','finish',0],['C','avoid',0],['D','continue',0]]],
    ["Choose the correct relative pronoun: 'The woman ___ called is my mother.'", 'INTERMEDIAIRE', 'TENASOSP', [['A','who',1],['B','which',0],['C','whose',0],['D','whom',0]]],
    ["'She asked me whether I was ready.' This is:", 'AVANCE', 'TENASOSP', [['A','Indirect speech (reported question)',1],['B','Direct speech',0],['C','Passive voice',0],['D','Conditional clause',0]]],
    ["What part of speech is 'beautiful'?", 'DEBUTANT', 'ENAFEP', [['A','Adjective',1],['B','Adverb',0],['C','Noun',0],['D','Verb',0]]],
    ["'The more you read, the more you learn.' This uses:", 'AVANCE', 'EXAMEN_ETAT', [['A','Double comparative structure',1],['B','Superlative',0],['C','Conditional',0],['D','Passive',0]]],
    ["Complete: 'She has been waiting ___ two hours.'", 'ELEMENTAIRE', 'ENAFEP', [['A','for',1],['B','since',0],['C','during',0],['D','while',0]]],
    ["What is the correct form? 'The news ___ surprising.'", 'INTERMEDIAIRE', 'TENASOSP', [['A','is',1],['B','are',0],['C','were',0],['D','am',0]]],
    ["'To kill two birds with one stone' means:", 'AVANCE', 'TENASOSP', [['A','To achieve two things with one action',1],['B','To hunt efficiently',0],['C','To make a mistake',0],['D','To be violent',0]]],
    ["Choose the modal: 'You ___ wear a seatbelt. It\'s the law.'", 'ELEMENTAIRE', 'ENAFEP', [['A','must',1],['B','might',0],['C','could',0],['D','would',0]]],
    ["What is the correct punctuation? 'He said ___ I will come tomorrow ___'", 'ELEMENTAIRE', 'ENAFEP', [['A','"I will come tomorrow."',1],['B','(I will come tomorrow)',0],['C','[I will come tomorrow]',0],['D',';I will come tomorrow;',0]]],
    ["Identify the figure of speech in: 'Time is money'", 'AVANCE', 'EXAMEN_ETAT', [['A','Metaphor',1],['B','Simile',0],['C','Personification',0],['D','Hyperbole',0]]],
];
foreach ($questions_en as $q) { addQ($pdo, $stQ, $stO, $matMap[$m], ...$q); $added++; }
echo "Anglais: +" . ($added-$prevAdded) . " questions\n";

$pdo->exec("SET FOREIGN_KEY_CHECKS=1");

// Résultat final
$newTotal = (int)$pdo->query("SELECT COUNT(*) FROM question_bank")->fetchColumn();
echo "\n�"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"�\n";
echo "Questions ajoutées: $added\n";
echo "NOUVEAU TOTAL: $newTotal\n";
echo "�"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"�\n";
?>

