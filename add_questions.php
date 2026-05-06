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
    http_response_code(403); die('AccÃ¨s refusÃ©.');
}
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
header('Content-Type: text/plain; charset=utf-8');

$pdo = db();
$pdo->exec("SET FOREIGN_KEY_CHECKS=0");

// RÃ©cupÃ©rer les IDs des matiÃ¨res
$matMap = [];
foreach ($pdo->query("SELECT id,code FROM matieres")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $matMap[$r['code']] = $r['id'];
}

// VÃ©rifier compte actuel
$current = (int)$pdo->query("SELECT COUNT(*) FROM question_bank")->fetchColumn();
echo "Questions actuelles: $current\n";
echo "Cible: 1000\n";
echo "Ã€ ajouter: " . max(0, 1000 - $current) . "\n\n";

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
    foreach ($opts as [$l,$t,$ok]) $stO->execute([$qid,$l,$t,$ok]);
}

$added = 0;

/* â•â• MATHÃ‰MATIQUES (besoin ~8 pour atteindre 125) â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
$m = 'maths';
$questions_maths = [
    ["Quelle est la limite de sin(x)/x quand xâ†’0 ?", 'AVANCE', 'EXAMEN_ETAT', [['A','1',1],['B','0',0],['C','âˆž',0],['D','indÃ©terminÃ©e',0]]],
    ["Si logâ‚‚(x) = 5, quelle est la valeur de x ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','32',1],['B','10',0],['C','25',0],['D','16',0]]],
    ["Quel est le PGCD de 84 et 56 ?", 'ELEMENTAIRE', 'ENAFEP', [['A','28',1],['B','14',0],['C','7',0],['D','42',0]]],
    ["DÃ©velopper (2x+3)Â² :", 'ELEMENTAIRE', 'ENAFEP', [['A','4xÂ²+12x+9',1],['B','4xÂ²+6x+9',0],['C','2xÂ²+12x+9',0],['D','4xÂ²+9',0]]],
    ["RÃ©soudre xÂ²-5x+6=0 : les racines sont :", 'INTERMEDIAIRE', 'TENASOSP', [['A','x=2 et x=3',1],['B','x=1 et x=6',0],['C','x=-2 et x=-3',0],['D','x=2 et x=-3',0]]],
    ["Volume d'un cube de cÃ´tÃ© 4 cm :", 'DEBUTANT', 'ENAFEP', [['A','64 cmÂ³',1],['B','48 cmÂ³',0],['C','16 cmÂ³',0],['D','96 cmÂ³',0]]],
    ["Si une suite arithmÃ©tique aâ‚=3 et r=5, quel est aâ‚† ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','28',1],['B','30',0],['C','25',0],['D','18',0]]],
    ["Calculer : 5!/3! =", 'ELEMENTAIRE', 'TENASOSP', [['A','20',1],['B','10',0],['C','60',0],['D','15',0]]],
    ["L'intÃ©grale de 2x dx est :", 'AVANCE', 'EXAMEN_ETAT', [['A','xÂ²+C',1],['B','2xÂ²+C',0],['C','xÂ²',0],['D','2+C',0]]],
    ["Quelle est la mÃ©diane de {2, 5, 7, 9, 11} ?", 'ELEMENTAIRE', 'ENAFEP', [['A','7',1],['B','5',0],['C','9',0],['D','6,8',0]]],
    ["cos(60Â°) = ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','0,5',1],['B','0,866',0],['C','1',0],['D','0,707',0]]],
    ["Quelle est la pente de y = 3x - 7 ?", 'DEBUTANT', 'ENAFEP', [['A','3',1],['B','-7',0],['C','7',0],['D','-3',0]]],
];
foreach ($questions_maths as $q) {
    addQ($pdo, $stQ, $stO, $matMap[$m], ...$q);
    $added++;
}
echo "MathÃ©matiques: +$added questions\n";

/* â•â• FRANÃ‡AIS (besoin ~31 pour atteindre 125) â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
$prevAdded = $added;
$m = 'francais';
$questions_fr = [
    ["Quel est le sujet dans 'Les enfants jouent dans la cour' ?", 'DEBUTANT', 'ENAFEP', [['A','Les enfants',1],['B','jouent',0],['C','la cour',0],['D','dans',0]]],
    ["Quel temps est 'il aurait chantÃ©' ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','Conditionnel passÃ©',1],['B','Conditionnel prÃ©sent',0],['C','Futur antÃ©rieur',0],['D','Plus-que-parfait',0]]],
    ["Quel est le synonyme de 'laborieux' ?", 'INTERMEDIAIRE', 'ENAFEP', [['A','travailleur',1],['B','paresseux',0],['C','rapide',0],['D','nÃ©gligent',0]]],
    ["'Il a les dents du bonheur' est une expression qui signifie :", 'AVANCE', 'EXAMEN_ETAT', [['A','Avoir un Ã©cart entre les dents de devant',1],['B','ÃŠtre toujours heureux',0],['C','Aimer sourire',0],['D','Avoir de belles dents',0]]],
    ["Identifier la proposition subordonnÃ©e relative : 'Le livre que tu lis est passionnant'", 'AVANCE', 'TENASOSP', [['A','que tu lis',1],['B','Le livre',0],['C','est passionnant',0],['D','tu lis est passionnant',0]]],
    ["Quel est le fÃ©minin de 'hÃ©ros' ?", 'DEBUTANT', 'ENAFEP', [['A','hÃ©roÃ¯ne',1],['B','hÃ©rose',0],['C','hÃ©roÃ¯sse',0],['D','hÃ©roÃ¯ne',0]]],
    ["Dans 'Le soleil se lÃ¨ve Ã  l'est', le verbe est :", 'DEBUTANT', 'ENAFEP', [['A','se lÃ¨ve',1],['B','soleil',0],['C','Ã  l\'est',0],['D','le',0]]],
    ["'Chaque Ã©lÃ¨ve a rendu son devoir' â€” 'son' est :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Adjectif possessif',1],['B','Pronom possessif',0],['C','Adjectif dÃ©monstratif',0],['D','Pronom relatif',0]]],
    ["Quelle est la nature de 'rapidement' dans 'il court rapidement' ?", 'INTERMEDIAIRE', 'ENAFEP', [['A','Adverbe de maniÃ¨re',1],['B','Adjectif qualificatif',0],['C','Nom commun',0],['D','Verbe',0]]],
    ["Le prÃ©fixe 'anti-' signifie :", 'ELEMENTAIRE', 'ENAFEP', [['A','contre',1],['B','avant',0],['C','aprÃ¨s',0],['D','avec',0]]],
    ["'Blanc comme neige' est :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Une comparaison',1],['B','Une mÃ©taphore',0],['C','Une hyperbole',0],['D','Une personnification',0]]],
    ["Conjuguer 'aller' au futur simple, 1Ã¨re personne du singulier :", 'DEBUTANT', 'ENAFEP', [['A','j\'irai',1],['B','je vais',0],['C','j\'allais',0],['D','je suis allÃ©',0]]],
    ["Quel est le pluriel de 'bal' ?", 'ELEMENTAIRE', 'ENAFEP', [['A','bals',1],['B','baux',0],['C','bales',0],['D','balles',0]]],
    ["Identifier le COD : 'Marie mange une pomme'", 'ELEMENTAIRE', 'ENAFEP', [['A','une pomme',1],['B','Marie',0],['C','mange',0],['D','une',0]]],
    ["'L'homme est un loup pour l'homme' est :", 'AVANCE', 'EXAMEN_ETAT', [['A','Une mÃ©taphore',1],['B','Une comparaison',0],['C','Une hyperbole',0],['D','Une ironie',0]]],
    ["Quel est l'antonyme de 'avare' ?", 'ELEMENTAIRE', 'ENAFEP', [['A','gÃ©nÃ©reux',1],['B','riche',0],['C','pauvre',0],['D','rapide',0]]],
    ["Le discours direct est introduit par :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Des guillemets et deux-points',1],['B','Des parenthÃ¨ses',0],['C','Des tirets uniquement',0],['D','Des points de suspension',0]]],
    ["'MalgrÃ© la pluie, il est sorti' â€” 'MalgrÃ©' exprime :", 'AVANCE', 'TENASOSP', [['A','L\'opposition / la concession',1],['B','La cause',0],['C','La consÃ©quence',0],['D','Le but',0]]],
    ["Quel est le genre de 'amour' au pluriel ?", 'AVANCE', 'EXAMEN_ETAT', [['A','Masculin au singulier, fÃ©minin au pluriel',1],['B','Toujours masculin',0],['C','Toujours fÃ©minin',0],['D','Neutre',0]]],
    ["'Je viendrai quand tu m'appelleras' â€” 'quand' introduit :", 'AVANCE', 'TENASOSP', [['A','Une proposition subordonnÃ©e de temps',1],['B','Une relative',0],['C','Une complÃ©tive',0],['D','Une circonstancielle de cause',0]]],
    ["Quel est le participe passÃ© de 'rÃ©soudre' ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','rÃ©solu',1],['B','rÃ©soudÃ©',0],['C','rÃ©solu\'',0],['D','rÃ©solvÃ©',0]]],
    ["Identifier l'homonyme de 'ver' :", 'ELEMENTAIRE', 'ENAFEP', [['A','verre, vers, vert',1],['B','vair uniquement',0],['C','vers uniquement',0],['D','verbe',0]]],
    ["Que signifie l'expression 'avoir le cafard' ?", 'INTERMEDIAIRE', 'ENAFEP', [['A','ÃŠtre triste, dÃ©primÃ©',1],['B','Avoir peur des insectes',0],['C','ÃŠtre courageuse',0],['D','Avoir de la chance',0]]],
    ["Quel est le sujet dans 'C'est lui qui a gagnÃ©' ?", 'AVANCE', 'TENASOSP', [['A','lui',1],['B','C\'',0],['C','qui',0],['D','a gagnÃ©',0]]],
    ["Accorder : 'Les fleurs que j'ai _____ (cueillir)'", 'INTERMEDIAIRE', 'ENAFEP', [['A','cueillies',1],['B','cueillis',0],['C','cueilli',0],['D','cueillir',0]]],
    ["'Il est parti Ã  l'aube.' â€” 'Ã  l'aube' est :", 'ELEMENTAIRE', 'ENAFEP', [['A','ComplÃ©ment circonstanciel de temps',1],['B','COD',0],['C','Attribut du sujet',0],['D','Sujet',0]]],
    ["Quel registre de langue emploie 'ils bossent dur' ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','Familier',1],['B','Soutenu',0],['C','Courant',0],['D','Scientifique',0]]],
    ["Que dÃ©signe 'la Francophonie' ?", 'ELEMENTAIRE', 'ENAFEP', [['A','L\'ensemble des pays et communautÃ©s utilisant le franÃ§ais',1],['B','La langue franÃ§aise uniquement',0],['C','La France et ses colonies',0],['D','L\'AcadÃ©mie franÃ§aise',0]]],
    ["Quel est le type de la phrase : 'Ferme la porte !' ?", 'DEBUTANT', 'ENAFEP', [['A','ImpÃ©rative',1],['B','Interrogative',0],['C','DÃ©clarative',0],['D','Exclamative',0]]],
    ["'Quoiqu'il pleuve, je sortirai.' 'Quoique' exprime :", 'AVANCE', 'EXAMEN_ETAT', [['A','La concession',1],['B','La cause',0],['C','Le but',0],['D','La condition',0]]],
    ["Quel est le sens de 'Ã©pistÃ©mologie' ?", 'EXPERT', 'EXAMEN_ETAT', [['A','Ã‰tude critique des sciences et de la connaissance',1],['B','Ã‰tude des Ã©pidÃ©mies',0],['C','Ã‰tude de la lettre',0],['D','Science du sol',0]]],
];
foreach ($questions_fr as $q) { addQ($pdo, $stQ, $stO, $matMap[$m], ...$q); $added++; }
echo "FranÃ§ais: +" . ($added-$prevAdded) . " questions\n";

/* â•â• SCIENCES (besoin ~46 pour atteindre 125) â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
$prevAdded = $added;
$m = 'sciences';
$questions_sc = [
    ["Quelle est la formule de la photosynthÃ¨se (simplifiÃ©e) ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','COâ‚‚ + Hâ‚‚O + lumiÃ¨re â†’ glucose + Oâ‚‚',1],['B','Oâ‚‚ + glucose â†’ COâ‚‚ + Hâ‚‚O',0],['C','Hâ‚‚ + Oâ‚‚ â†’ Hâ‚‚O',0],['D','Nâ‚‚ + Hâ‚‚ â†’ NHâ‚ƒ',0]]],
    ["Quel est l'organe de l'olfaction (l'odorat) ?", 'DEBUTANT', 'ENAFEP', [['A','Le nez',1],['B','La langue',0],['C','L\'oreille',0],['D','L\'Å“il',0]]],
    ["Quelle est la tempÃ©rature normale du corps humain ?", 'DEBUTANT', 'ENAFEP', [['A','37Â°C',1],['B','36Â°C',0],['C','38Â°C',0],['D','39Â°C',0]]],
    ["Quel est le rÃ´le des globules blancs ?", 'ELEMENTAIRE', 'ENAFEP', [['A','DÃ©fense immunitaire',1],['B','Transport de l\'oxygÃ¨ne',0],['C','Coagulation du sang',0],['D','Nutrition des cellules',0]]],
    ["Quel est le groupe sanguin universel donneur ?", 'ELEMENTAIRE', 'TENASOSP', [['A','O nÃ©gatif',1],['B','AB positif',0],['C','A positif',0],['D','B nÃ©gatif',0]]],
    ["La cellule animale se distingue de la cellule vÃ©gÃ©tale par l'absence de :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Paroi cellulaire et chloroplastes',1],['B','Noyau',0],['C','Membrane cellulaire',0],['D','Mitochondries',0]]],
    ["Quelle vitamine est produite par exposition au soleil ?", 'ELEMENTAIRE', 'ENAFEP', [['A','Vitamine D',1],['B','Vitamine C',0],['C','Vitamine A',0],['D','Vitamine B12',0]]],
    ["Quel est l'organe qui filtre le sang dans l'organisme ?", 'ELEMENTAIRE', 'ENAFEP', [['A','Le rein',1],['B','Le foie',0],['C','Le cÅ“ur',0],['D','Le poumon',0]]],
    ["Combien de dents permanentes a l'adulte ?", 'DEBUTANT', 'ENAFEP', [['A','32',1],['B','28',0],['C','24',0],['D','36',0]]],
    ["Quelle est la substance qui donne sa couleur verte aux plantes ?", 'DEBUTANT', 'ENAFEP', [['A','La chlorophylle',1],['B','L\'anthocyane',0],['C','Le carotÃ¨ne',0],['D','La mÃ©lanine',0]]],
    ["Le paludisme est transmis par :", 'ELEMENTAIRE', 'ENAFEP', [['A','La piqÃ»re de moustique anophÃ¨le femelle',1],['B','L\'eau contaminÃ©e',0],['C','Les aliments avariÃ©s',0],['D','Contact direct',0]]],
    ["Quel est l'agent pathogÃ¨ne du cholÃ©ra ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','La bactÃ©rie Vibrio cholerae',1],['B','Un virus',0],['C','Un parasite',0],['D','Un champignon',0]]],
    ["La digestion des graisses est facilitÃ©e par :", 'INTERMEDIAIRE', 'TENASOSP', [['A','La bile et les lipases',1],['B','L\'amylase salivaire',0],['C','La pepsine',0],['D','L\'insuline',0]]],
    ["Dans quel organe se produit la fÃ©condation chez la femme ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','La trompe de Fallope',1],['B','L\'utÃ©rus',0],['C','L\'ovaire',0],['D','Le vagin',0]]],
    ["Quelle molÃ©cule transporte l'oxygÃ¨ne dans le sang ?", 'ELEMENTAIRE', 'ENAFEP', [['A','L\'hÃ©moglobine',1],['B','L\'albumine',0],['C','Le fibrinogÃ¨ne',0],['D','Le plasma',0]]],
    ["Le HIV dÃ©truit principalement les cellules :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Lymphocytes T CD4',1],['B','Globules rouges',0],['C','Plaquettes',0],['D','Neurones',0]]],
    ["Quelle est la durÃ©e normale d'une grossesse humaine ?", 'DEBUTANT', 'ENAFEP', [['A','9 mois (40 semaines environ)',1],['B','10 mois',0],['C','8 mois',0],['D','12 mois',0]]],
    ["Quel est le rÃ´le de l'insuline ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','RÃ©guler la glycÃ©mie (taux de sucre dans le sang)',1],['B','DigÃ©rer les protÃ©ines',0],['C','Transporter l\'oxygÃ¨ne',0],['D','Filtrer le sang',0]]],
    ["Le squsquelette humain comprend combien d'os Ã  la naissance ?", 'AVANCE', 'EXAMEN_ETAT', [['A','270 environ',1],['B','206',0],['C','300',0],['D','350',0]]],
    ["Quelle glande sÃ©crÃ¨te le suc gastrique ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','L\'estomac',1],['B','Le pancrÃ©as',0],['C','L\'intestin grÃªle',0],['D','Le foie',0]]],
    ["La respiration cellulaire produit :", 'INTERMEDIAIRE', 'TENASOSP', [['A','ATP, COâ‚‚ et Hâ‚‚O',1],['B','Oâ‚‚ et glucose',0],['C','Glucose et COâ‚‚',0],['D','ATP et Oâ‚‚',0]]],
    ["Quelle est la principale cause de la malaria en RDC ?", 'DEBUTANT', 'ENAFEP', [['A','Plasmodium falciparum via les moustiques',1],['B','Un virus',0],['C','Les bactÃ©ries de l\'eau',0],['D','Les tiques',0]]],
    ["Quel sens utilise les rÃ©cepteurs de la rÃ©tine ?", 'ELEMENTAIRE', 'ENAFEP', [['A','La vue',1],['B','L\'odorat',0],['C','Le toucher',0],['D','Le goÃ»t',0]]],
    ["La division cellulaire par mitose produit :", 'AVANCE', 'EXAMEN_ETAT', [['A','Deux cellules filles gÃ©nÃ©tiquement identiques',1],['B','Quatre cellules haploÃ¯des',0],['C','Deux cellules diffÃ©rentes',0],['D','Une seule cellule',0]]],
    ["Quel est le pH du sang humain normal ?", 'AVANCE', 'TENASOSP', [['A','7,35 â€“ 7,45',1],['B','6,8 â€“ 7,0',0],['C','7,8 â€“ 8,2',0],['D','5,5 â€“ 6,5',0]]],
    ["La chlorophylle absorbe principalement la lumiÃ¨re :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Rouge et bleue',1],['B','Verte',0],['C','Blanche',0],['D','Ultraviolette',0]]],
    ["Quel est l'organe central du systÃ¨me nerveux ?", 'DEBUTANT', 'ENAFEP', [['A','L\'encÃ©phale (cerveau)',1],['B','La moelle Ã©piniÃ¨re',0],['C','Le cÅ“ur',0],['D','La peau',0]]],
    ["Les vaccins agissent en :", 'ELEMENTAIRE', 'ENAFEP', [['A','Stimulant la mÃ©moire immunitaire',1],['B','Tuant directement les microbes',0],['C','DÃ©truisant les toxines',0],['D','Augmentant la tempÃ©rature',0]]],
    ["La digestion de l'amidon commence dans :", 'ELEMENTAIRE', 'ENAFEP', [['A','La bouche (par l\'amylase salivaire)',1],['B','L\'estomac',0],['C','Le duodÃ©num',0],['D','Le cÃ´lon',0]]],
    ["Quelle hormone dÃ©clenche la pubertÃ© chez le garÃ§on ?", 'AVANCE', 'TENASOSP', [['A','La testostÃ©rone',1],['B','L\'adrÃ©naline',0],['C','L\'insuline',0],['D','La progestÃ©rone',0]]],
    ["Quel type d'os forme le crÃ¢ne ?", 'ELEMENTAIRE', 'ENAFEP', [['A','Os plats',1],['B','Os longs',0],['C','Os courts',0],['D','Os irrÃ©guliers',0]]],
    ["Le nerf optique relie l'Å“il Ã  :", 'ELEMENTAIRE', 'ENAFEP', [['A','Le cerveau',1],['B','Le tronc cÃ©rÃ©bral',0],['C','La moelle Ã©piniÃ¨re',0],['D','L\'oreille',0]]],
    ["Quel organe produit la testostÃ©rone chez l'homme ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','Les testicules',1],['B','Les surrÃ©nales uniquement',0],['C','L\'hypophyse',0],['D','Le foie',0]]],
    ["La couche d'ozone protÃ¨ge la Terre contre :", 'ELEMENTAIRE', 'ENAFEP', [['A','Les rayons UV',1],['B','La pluie acide',0],['C','Les rayons infrarouges',0],['D','La pollution',0]]],
    ["Quelles sont les 4 Ã©tapes de la digestion ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','Ingestion, digestion, absorption, Ã©limination',1],['B','Mastication, dÃ©glutition, absorption, excrÃ©tion',0],['C','Ingestion, fermentation, assimilation, Ã©vacuation',0],['D','DÃ©gestion, absorption, filtration, Ã©limination',0]]],
    ["Quel est le rÃ´le des plaquettes sanguines ?", 'ELEMENTAIRE', 'ENAFEP', [['A','Coagulation du sang',1],['B','Transport de l\'oxygÃ¨ne',0],['C','DÃ©fense immunitaire',0],['D','Production d\'hormones',0]]],
    ["L'eau reprÃ©sente environ quelle proportion du corps humain ?", 'ELEMENTAIRE', 'ENAFEP', [['A','60 â€“ 70 %',1],['B','30 â€“ 40 %',0],['C','80 â€“ 90 %',0],['D','20 â€“ 30 %',0]]],
    ["Quel est le principal gaz de l'atmosphÃ¨re terrestre ?", 'DEBUTANT', 'ENAFEP', [['A','Azote (Nâ‚‚ : 78%)',1],['B','OxygÃ¨ne (Oâ‚‚)',0],['C','COâ‚‚',0],['D','Argon',0]]],
    ["La multiplication vÃ©gÃ©tative (reproduction asexuÃ©e des plantes) peut se faire par :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Bouture, marcottage, greffe',1],['B','Pollinisation uniquement',0],['C','FÃ©condation croisÃ©e',0],['D','Sporulation uniquement',0]]],
    ["Quelle partie du cerveau contrÃ´le l'Ã©quilibre ?", 'AVANCE', 'TENASOSP', [['A','Le cervelet',1],['B','Le cortex frontal',0],['C','L\'hippocampe',0],['D','Le thalamus',0]]],
    ["Qu'est-ce que l'osmose ?", 'AVANCE', 'EXAMEN_ETAT', [['A','Passage de l\'eau Ã  travers une membrane semi-permÃ©able du milieu diluÃ© vers le milieu concentrÃ©',1],['B','Dissolution d\'un solide dans l\'eau',0],['C','Filtration mÃ©canique',0],['D','Diffusion des ions',0]]],
    ["Quel est l'effet de serre naturel ?", 'ELEMENTAIRE', 'ENAFEP', [['A','Maintien de la chaleur sur Terre grÃ¢ce Ã  COâ‚‚, Hâ‚‚O et CHâ‚„',1],['B','Destruction de la couche d\'ozone',0],['C','RÃ©chauffement uniquement artificiel',0],['D','Absorption des UV',0]]],
    ["Les bactÃ©ries sont des organismes :", 'ELEMENTAIRE', 'ENAFEP', [['A','Procaryotes unicellulaires',1],['B','Eucaryotes multicellulaires',0],['C','Virus',0],['D','Champignons',0]]],
    ["La meiose se dÃ©roule dans les organes :", 'AVANCE', 'EXAMEN_ETAT', [['A','Reproducteurs (gonades)',1],['B','Musculaires',0],['C','Nerveux',0],['D','Digestifs',0]]],
    ["Quel est le minÃ©ral essentiel pour la soliditÃ© des os ?", 'DEBUTANT', 'ENAFEP', [['A','Calcium',1],['B','Fer',0],['C','Sodium',0],['D','MagnÃ©sium',0]]],
    ["La photosynthÃ¨se libÃ¨re de l'oxygÃ¨ne grÃ¢ce Ã  la dÃ©composition de :", 'INTERMEDIAIRE', 'TENASOSP', [['A','L\'eau (Hâ‚‚O)',1],['B','COâ‚‚',0],['C','Glucose',0],['D','L\'air',0]]],
];
foreach ($questions_sc as $q) { addQ($pdo, $stQ, $stO, $matMap[$m], ...$q); $added++; }
echo "Sciences: +" . ($added-$prevAdded) . " questions\n";

/* â•â• HISTOIRE-GÃ‰O (besoin ~42 pour atteindre 125) â•â•â•â•â•â•â•â•â•â•â• */
$prevAdded = $added;
$m = 'histgeo';
$questions_hg = [
    ["Quelle est la capitale de la RDC ?", 'DEBUTANT', 'ENAFEP', [['A','Kinshasa',1],['B','Lubumbashi',0],['C','Brazzaville',0],['D','Bukavu',0]]],
    ["Quel est le plus grand pays d'Afrique par superficie ?", 'ELEMENTAIRE', 'ENAFEP', [['A','AlgÃ©rie',1],['B','RDC',0],['C','Soudan',0],['D','Libye',0]]],
    ["En quelle annÃ©e les Nations Unies ont-elles Ã©tÃ© fondÃ©es ?", 'ELEMENTAIRE', 'ENAFEP', [['A','1945',1],['B','1939',0],['C','1948',0],['D','1955',0]]],
    ["Quel pays a colonisÃ© la RDC avant l'indÃ©pendance de 1960 ?", 'DEBUTANT', 'ENAFEP', [['A','La Belgique',1],['B','La France',0],['C','Le Portugal',0],['D','L\'Angleterre',0]]],
    ["Quel est le fleuve qui traverse Kinshasa ?", 'DEBUTANT', 'ENAFEP', [['A','Le fleuve Congo',1],['B','Le Nil',0],['C','Le Niger',0],['D','Le KasaÃ¯',0]]],
    ["Qui a Ã©tÃ© le premier prÃ©sident de la RDC aprÃ¨s l'indÃ©pendance ?", 'ELEMENTAIRE', 'ENAFEP', [['A','Joseph Kasa-Vubu',1],['B','Mobutu Sese Seko',0],['C','Patrice Lumumba',0],['D','Moise Tshombe',0]]],
    ["La PremiÃ¨re Guerre mondiale a commencÃ© en :", 'ELEMENTAIRE', 'ENAFEP', [['A','1914',1],['B','1918',0],['C','1939',0],['D','1900',0]]],
    ["Quel est le continent le plus peuplÃ© du monde ?", 'DEBUTANT', 'ENAFEP', [['A','Asie',1],['B','Afrique',0],['C','Europe',0],['D','AmÃ©rique',0]]],
    ["La Seconde Guerre mondiale s'est terminÃ©e en :", 'ELEMENTAIRE', 'ENAFEP', [['A','1945',1],['B','1944',0],['C','1946',0],['D','1943',0]]],
    ["Quel est le tropique qui passe par l'hÃ©misphÃ¨re nord ?", 'ELEMENTAIRE', 'TENASOSP', [['A','Tropique du Cancer',1],['B','Tropique du Capricorne',0],['C','Ã‰quateur',0],['D','Cercle arctique',0]]],
    ["La dÃ©colonisation en Afrique a principalement eu lieu dans les annÃ©es :", 'ELEMENTAIRE', 'TENASOSP', [['A','1950 â€“ 1970',1],['B','1900 â€“ 1920',0],['C','1930 â€“ 1940',0],['D','1980 â€“ 2000',0]]],
    ["Quel est le plus long fleuve du monde ?", 'ELEMENTAIRE', 'ENAFEP', [['A','Le Nil',1],['B','L\'Amazone',0],['C','Le Congo',0],['D','Le YangtsÃ©',0]]],
    ["La RDC partage une frontiÃ¨re avec combien de pays ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','9',1],['B','7',0],['C','5',0],['D','11',0]]],
    ["Quel est le point culminant de l'Afrique ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','Le Kilimandjaro (5 895 m)',1],['B','Le Mont Kenya',0],['C','L\'Atlas',0],['D','Le Mont Cameroun',0]]],
    ["L'apartheid en Afrique du Sud a pris fin officiellement en :", 'INTERMEDIAIRE', 'TENASOSP', [['A','1994',1],['B','1990',0],['C','1985',0],['D','2000',0]]],
    ["Patrice Lumumba a Ã©tÃ© le premier :", 'ELEMENTAIRE', 'ENAFEP', [['A','Premier Ministre de la RDC',1],['B','PrÃ©sident de la RDC',0],['C','Chef de l\'armÃ©e congolaise',0],['D','Gouverneur du Katanga',0]]],
    ["La confÃ©rence de Berlin (1884-1885) a abouti Ã  :", 'AVANCE', 'EXAMEN_ETAT', [['A','Le partage de l\'Afrique entre puissances europÃ©ennes',1],['B','La fin de la traite nÃ©griÃ¨re',0],['C','L\'indÃ©pendance de l\'Ã‰gypte',0],['D','La crÃ©ation de l\'Union africaine',0]]],
    ["Quel est l'ocÃ©an Ã  l'ouest de l'Afrique ?", 'DEBUTANT', 'ENAFEP', [['A','L\'Atlantique',1],['B','L\'Indien',0],['C','Le Pacifique',0],['D','L\'Arctique',0]]],
    ["La RÃ©volution franÃ§aise a eu lieu en :", 'ELEMENTAIRE', 'ENAFEP', [['A','1789',1],['B','1804',0],['C','1815',0],['D','1776',0]]],
    ["Qui est l'auteur du discours 'I have a dream' ?", 'ELEMENTAIRE', 'TENASOSP', [['A','Martin Luther King Jr.',1],['B','Malcolm X',0],['C','Nelson Mandela',0],['D','Barack Obama',0]]],
    ["Quelle est la monnaie officielle de la RDC ?", 'DEBUTANT', 'ENAFEP', [['A','Le Franc congolais (CDF)',1],['B','Le Franc CFA',0],['C','Le Dollar congolais',0],['D','Le Shilling',0]]],
    ["Quelle organisation rÃ©gionale regroupe les pays d'Afrique centrale ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','CEEAC (CommunautÃ© Ã‰conomique des Ã‰tats d\'Afrique Centrale)',1],['B','CEDEAO',0],['C','SADC',0],['D','UMA',0]]],
    ["Quel est le dÃ©sert le plus grand du monde ?", 'ELEMENTAIRE', 'ENAFEP', [['A','Le Sahara',1],['B','Le Kalahari',0],['C','L\'Antarctique (glace)',0],['D','L\'Arabie',0]]],
    ["L'OUA (Organisation de l'UnitÃ© Africaine) a Ã©tÃ© fondÃ©e en :", 'INTERMEDIAIRE', 'TENASOSP', [['A','1963',1],['B','1945',0],['C','1960',0],['D','1975',0]]],
    ["Quel pays est Ã  la fois en Afrique et en Asie ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','Ã‰gypte',1],['B','Maroc',0],['C','Tunisie',0],['D','Libye',0]]],
    ["Mobutu Sese Seko a renommÃ© le Congo en :", 'ELEMENTAIRE', 'ENAFEP', [['A','ZaÃ¯re',1],['B','Congo-Kinshasa',0],['C','RÃ©publique Centrale Africaine',0],['D','Congo-Belge',0]]],
    ["Quelle mer sÃ©pare l'Europe de l'Afrique du Nord ?", 'ELEMENTAIRE', 'ENAFEP', [['A','La mer MÃ©diterranÃ©e',1],['B','La mer Rouge',0],['C','La mer Noire',0],['D','L\'Atlantique',0]]],
    ["Quel est le principal minerai extrait au Katanga ?", 'ELEMENTAIRE', 'ENAFEP', [['A','Le cuivre',1],['B','L\'or',0],['C','Le diamant',0],['D','Le pÃ©trole',0]]],
    ["La Charte des Nations Unies a Ã©tÃ© signÃ©e Ã  :", 'AVANCE', 'EXAMEN_ETAT', [['A','San Francisco',1],['B','New York',0],['C','GenÃ¨ve',0],['D','Londres',0]]],
    ["Quel Ã©vÃ©nement a dÃ©clenchÃ© la PremiÃ¨re Guerre mondiale ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','L\'assassinat de l\'archiduc FranÃ§ois-Ferdinand Ã  Sarajevo',1],['B','L\'invasion de la Pologne',0],['C','La rÃ©volution bolchÃ©vique',0],['D','La crise de Suez',0]]],
    ["La RDC est traversÃ©e par l'Ã‰quateur, ce qui lui donne :", 'ELEMENTAIRE', 'ENAFEP', [['A','Un climat Ã©quatorial (chaud et humide)',1],['B','Un climat dÃ©sertique',0],['C','Un climat tempÃ©rÃ©',0],['D','Un climat polaire',0]]],
    ["Quelle ville est surnommÃ©e 'la ville miniÃ¨re' en RDC ?", 'ELEMENTAIRE', 'ENAFEP', [['A','Lubumbashi',1],['B','Kinshasa',0],['C','Goma',0],['D','Kisangani',0]]],
    ["La rÃ©gion des Grands Lacs africains comprend notamment :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Les lacs Victoria, Tanganyika, Malawi',1],['B','Les lacs Titicaca et Baikal',0],['C','La mer Caspienne',0],['D','Le lac BaÃ¯kal uniquement',0]]],
    ["Quelle est la superficie de la RDC (environ) ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','2 344 000 kmÂ²',1],['B','1 200 000 kmÂ²',0],['C','3 500 000 kmÂ²',0],['D','945 000 kmÂ²',0]]],
    ["L'UNICEF est l'agence des Nations Unies chargÃ©e de :", 'ELEMENTAIRE', 'ENAFEP', [['A','L\'enfance',1],['B','L\'alimentation',0],['C','La santÃ©',0],['D','L\'Ã©ducation des adultes',0]]],
    ["Qui a dirigÃ© la lutte contre l'apartheid en Afrique du Sud ?", 'ELEMENTAIRE', 'ENAFEP', [['A','Nelson Mandela',1],['B','Kofi Annan',0],['C','Kwame Nkrumah',0],['D','Julius Nyerere',0]]],
    ["En gÃ©ographie, la 'latitude' mesure :", 'ELEMENTAIRE', 'ENAFEP', [['A','La distance angulaire par rapport Ã  l\'Ã‰quateur',1],['B','La distance par rapport au mÃ©ridien de Greenwich',0],['C','L\'altitude d\'un lieu',0],['D','La superficie d\'un pays',0]]],
    ["Quel est le rÃ´le du Conseil de sÃ©curitÃ© de l'ONU ?", 'AVANCE', 'EXAMEN_ETAT', [['A','Maintenir la paix et la sÃ©curitÃ© internationales',1],['B','GÃ©rer les finances mondiales',0],['C','Juger les chefs d\'Ã‰tat',0],['D','Distribuer l\'aide humanitaire',0]]],
    ["La guerre froide opposait principalement :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Les Ã‰tats-Unis et l\'URSS',1],['B','L\'Allemagne et la France',0],['C','L\'Angleterre et l\'Espagne',0],['D','La Chine et le Japon',0]]],
    ["Quelle province de la RDC est connue pour la production de coltan ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','Nord-Kivu et Sud-Kivu',1],['B','Kinshasa',0],['C','Bas-Congo',0],['D','KasaÃ¯',0]]],
    ["L'agriculture de subsistance est caractÃ©risÃ©e par :", 'ELEMENTAIRE', 'ENAFEP', [['A','La production destinÃ©e Ã  l\'autoconsommation',1],['B','La production pour l\'exportation',0],['C','L\'utilisation intensive de machines',0],['D','La monoculture',0]]],
];
foreach ($questions_hg as $q) { addQ($pdo, $stQ, $stO, $matMap[$m], ...$q); $added++; }
echo "Histoire-GÃ©o: +" . ($added-$prevAdded) . " questions\n";

/* â•â• CHIMIE (besoin ~38 pour atteindre 125) â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
$prevAdded = $added;
$m = 'chimie';
$questions_ch = [
    ["Quel est le symbole chimique de l'or ?", 'DEBUTANT', 'ENAFEP', [['A','Au',1],['B','Or',0],['C','Go',0],['D','Ag',0]]],
    ["La formule du dioxyde de carbone est :", 'DEBUTANT', 'ENAFEP', [['A','COâ‚‚',1],['B','CO',0],['C','Câ‚‚O',0],['D','HCOâ‚ƒ',0]]],
    ["Un atome est Ã©lectriquement neutre car :", 'ELEMENTAIRE', 'ENAFEP', [['A','Le nombre de protons = nombre d\'Ã©lectrons',1],['B','Il n\'a pas de charge',0],['C','Les neutrons compensent les protons',0],['D','Les Ã©lectrons annulent les neutrons',0]]],
    ["Quelle est la formule de l'acide sulfurique ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','Hâ‚‚SOâ‚„',1],['B','HCl',0],['C','HNOâ‚ƒ',0],['D','Hâ‚‚SOâ‚ƒ',0]]],
    ["La liaison covalente rÃ©sulte de :", 'AVANCE', 'EXAMEN_ETAT', [['A','Mise en commun d\'Ã©lectrons entre deux atomes',1],['B','Transfert d\'Ã©lectrons',0],['C','Attraction entre ions de charges opposÃ©es',0],['D','Forces de Van der Waals',0]]],
    ["Quel est le numÃ©ro atomique de l\'hydrogÃ¨ne ?", 'DEBUTANT', 'ENAFEP', [['A','1',1],['B','2',0],['C','6',0],['D','8',0]]],
    ["La neutralisation acide-base produit :", 'ELEMENTAIRE', 'ENAFEP', [['A','Sel + eau',1],['B','Acide + base',0],['C','Oxyde + eau',0],['D','HydrogÃ¨ne + oxygÃ¨ne',0]]],
    ["Quelle est la couleur du papier tournesol en prÃ©sence d'un acide ?", 'DEBUTANT', 'ENAFEP', [['A','Rouge',1],['B','Bleu',0],['C','Vert',0],['D','Incolore',0]]],
    ["La masse molaire de l'eau (Hâ‚‚O) est :", 'ELEMENTAIRE', 'ENAFEP', [['A','18 g/mol',1],['B','16 g/mol',0],['C','20 g/mol',0],['D','12 g/mol',0]]],
    ["Dans la classification pÃ©riodique, les halogÃ¨nes sont dans la colonne :", 'INTERMEDIAIRE', 'TENASOSP', [['A','17 (VIIA)',1],['B','1 (IA)',0],['C','18 (VIII)',0],['D','16 (VIA)',0]]],
    ["La rÃ©action : Zn + CuSOâ‚„ â†’ ZnSOâ‚„ + Cu est une rÃ©action :", 'AVANCE', 'TENASOSP', [['A','De dÃ©placement / substitution',1],['B','De combinaison',0],['C','De dÃ©composition',0],['D','De double dÃ©placement',0]]],
    ["Quelle est la valence du carbone ?", 'ELEMENTAIRE', 'ENAFEP', [['A','4',1],['B','2',0],['C','6',0],['D','8',0]]],
    ["L'Ã©lectrolyse de l'eau produit :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Hâ‚‚ et Oâ‚‚',1],['B','HOâ» uniquement',0],['C','Hâ‚‚Oâ‚‚',0],['D','Hâ‚‚ uniquement',0]]],
    ["Quel ion est responsable de l'aciditÃ© d'une solution ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','Hâº (ou Hâ‚ƒOâº)',1],['B','OHâ»',0],['C','Naâº',0],['D','Clâ»',0]]],
    ["La formule de l'Ã©thanol est :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Câ‚‚Hâ‚…OH',1],['B','CHâ‚ƒOH',0],['C','Câ‚ƒHâ‚‡OH',0],['D','Câ‚‚Hâ‚„',0]]],
    ["Quelle est la loi de conservation de la masse ?", 'ELEMENTAIRE', 'ENAFEP', [['A','La masse des rÃ©actifs = masse des produits',1],['B','La masse diminue pendant la rÃ©action',0],['C','La masse augmente avec la chaleur',0],['D','La masse est variable',0]]],
    ["L'atome de fer a pour symbole :", 'DEBUTANT', 'ENAFEP', [['A','Fe',1],['B','Fr',0],['C','Fn',0],['D','Ir',0]]],
    ["Un oxyde est un composÃ© de l'oxygÃ¨ne avec :", 'ELEMENTAIRE', 'ENAFEP', [['A','Un autre Ã©lÃ©ment',1],['B','Uniquement un mÃ©tal',0],['C','L\'hydrogÃ¨ne uniquement',0],['D','Un acide',0]]],
    ["Quelle est la charge d'un proton ?", 'DEBUTANT', 'ENAFEP', [['A','+1',1],['B','-1',0],['C','0',0],['D','+2',0]]],
    ["La combustion complÃ¨te d'un hydrocarbure produit :", 'INTERMEDIAIRE', 'TENASOSP', [['A','COâ‚‚ et Hâ‚‚O',1],['B','CO et Hâ‚‚',0],['C','C et Hâ‚‚O',0],['D','CO et Hâ‚‚O',0]]],
    ["Le nombre d'Avogadro est (environ) :", 'INTERMEDIAIRE', 'TENASOSP', [['A','6,022 Ã— 10Â²Â³',1],['B','6,022 Ã— 10Â¹â¸',0],['C','3,14 Ã— 10Â²Â³',0],['D','1,6 Ã— 10â»Â¹â¹',0]]],
    ["Quelle est la propriÃ©tÃ© d'un sel ?", 'ELEMENTAIRE', 'ENAFEP', [['A','Produit par neutralisation acide-base',1],['B','Toujours soluble dans l\'eau',0],['C','Toujours basique',0],['D','Contient toujours du sodium',0]]],
    ["La formule du chlorure de sodium (sel de table) est :", 'DEBUTANT', 'ENAFEP', [['A','NaCl',1],['B','NaOH',0],['C','Naâ‚‚Cl',0],['D','NCl',0]]],
    ["Dans une solution aqueuse, KOH est :", 'ELEMENTAIRE', 'ENAFEP', [['A','Une base forte',1],['B','Un acide fort',0],['C','Un sel neutre',0],['D','Un oxyde',0]]],
    ["Quelle est la couche Ã©lectronique externe appelÃ©e ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','Couche de valence',1],['B','Couche K',0],['C','Couche noyau',0],['D','Couche sigma',0]]],
    ["Le mÃ©thane (CHâ‚„) appartient Ã  la famille des :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Alcanes',1],['B','AlcÃ¨nes',0],['C','Alcynes',0],['D','Aromatiques',0]]],
    ["Quelle rÃ©action libÃ¨re de l'Ã©nergie (exothermique) ?", 'AVANCE', 'EXAMEN_ETAT', [['A','La combustion',1],['B','L\'Ã©lectrolyse',0],['C','La photosynthÃ¨se',0],['D','La dissolution de NHâ‚„NOâ‚ƒ',0]]],
    ["La loi de Dalton concerne :", 'AVANCE', 'EXAMEN_ETAT', [['A','Les pressions partielles des gaz',1],['B','La conservation de la masse',0],['C','Les proportions des Ã©lÃ©ments',0],['D','Le volume des gaz',0]]],
    ["La formule brute du glucose est :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Câ‚†Hâ‚â‚‚Oâ‚†',1],['B','Câ‚â‚‚Hâ‚‚â‚‚Oâ‚â‚',0],['C','Câ‚†Hâ‚â‚€Oâ‚…',0],['D','CHâ‚‚O',0]]],
    ["Quel type de rÃ©action est : A + B â†’ AB ?", 'ELEMENTAIRE', 'ENAFEP', [['A','RÃ©action de combinaison (synthÃ¨se)',1],['B','RÃ©action de dÃ©composition',0],['C','RÃ©action de substitution',0],['D','OxydorÃ©duction',0]]],
    ["Quel est le pH d'une solution basique ?", 'ELEMENTAIRE', 'ENAFEP', [['A','SupÃ©rieur Ã  7',1],['B','InfÃ©rieur Ã  7',0],['C','Ã‰gal Ã  7',0],['D','Variable',0]]],
    ["L'oxydation d'un mÃ©tal correspond Ã  :", 'AVANCE', 'TENASOSP', [['A','La perte d\'Ã©lectrons',1],['B','Le gain d\'Ã©lectrons',0],['C','La perte de protons',0],['D','Le gain de neutrons',0]]],
    ["La rouille (oxyde de fer) a pour formule :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Feâ‚‚Oâ‚ƒ',1],['B','FeO',0],['C','Feâ‚ƒOâ‚„',0],['D','FeClâ‚‚',0]]],
    ["Quel est le catalyseur dans la synthÃ¨se de l'ammoniac (procÃ©dÃ© Haber) ?", 'AVANCE', 'EXAMEN_ETAT', [['A','Fer (Fe)',1],['B','Platine (Pt)',0],['C','Vanadium (Vâ‚‚Oâ‚…)',0],['D','Nickel (Ni)',0]]],
    ["La concentration molaire s'exprime en :", 'INTERMEDIAIRE', 'TENASOSP', [['A','mol/L',1],['B','g/L',0],['C','kg/mol',0],['D','mol/mÂ²',0]]],
    ["Qu'est-ce qu'un isotope ?", 'AVANCE', 'EXAMEN_ETAT', [['A','Atomes d\'un mÃªme Ã©lÃ©ment avec des nombres de neutrons diffÃ©rents',1],['B','Atomes de mÃªme masse',0],['C','Ions de mÃªme charge',0],['D','MolÃ©cules isomÃ¨res',0]]],
    ["Le brome est un Ã©lÃ©ment halogÃ¨ne de symbole :", 'ELEMENTAIRE', 'ENAFEP', [['A','Br',1],['B','B',0],['C','Bn',0],['D','Bm',0]]],
];
foreach ($questions_ch as $q) { addQ($pdo, $stQ, $stO, $matMap[$m], ...$q); $added++; }
echo "Chimie: +" . ($added-$prevAdded) . " questions\n";

/* â•â• PHYSIQUE (besoin ~37 pour atteindre 125) â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
$prevAdded = $added;
$m = 'physique';
$questions_ph = [
    ["Quelle est la formule de la vitesse ?", 'DEBUTANT', 'ENAFEP', [['A','v = d/t',1],['B','v = dÃ—t',0],['C','v = t/d',0],['D','v = mÃ—a',0]]],
    ["L'unitÃ© du travail dans le SI est :", 'ELEMENTAIRE', 'ENAFEP', [['A','Joule (J)',1],['B','Watt (W)',0],['C','Newton (N)',0],['D','Pascal (Pa)',0]]],
    ["La loi d'Ohm s'Ã©crit :", 'ELEMENTAIRE', 'ENAFEP', [['A','U = R Ã— I',1],['B','U = I / R',0],['C','R = U Ã— I',0],['D','I = U Ã— R',0]]],
    ["Quelle est la frÃ©quence du courant alternatif en RDC ?", 'ELEMENTAIRE', 'ENAFEP', [['A','50 Hz',1],['B','60 Hz',0],['C','100 Hz',0],['D','25 Hz',0]]],
    ["L'Ã©nergie cinÃ©tique d'un objet est donnÃ©e par :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Ec = Â½mvÂ²',1],['B','Ec = mgh',0],['C','Ec = mv',0],['D','Ec = Fd',0]]],
    ["Quelle est la rÃ©sistance Ã©quivalente de deux rÃ©sistances R en sÃ©rie ?", 'ELEMENTAIRE', 'ENAFEP', [['A','2R',1],['B','R/2',0],['C','RÂ²',0],['D','R',0]]],
    ["Un corps de 2 kg tombe en chute libre. L'accÃ©lÃ©ration gravitationnelle est (g â‰ˆ 10 m/sÂ²). La force exercÃ©e est :", 'ELEMENTAIRE', 'ENAFEP', [['A','20 N',1],['B','2 N',0],['C','10 N',0],['D','5 N',0]]],
    ["La loi de la rÃ©flexion de la lumiÃ¨re stipule que :", 'ELEMENTAIRE', 'ENAFEP', [['A','Angle d\'incidence = angle de rÃ©flexion',1],['B','La lumiÃ¨re est absorbÃ©e',0],['C','L\'angle de rÃ©fraction est nul',0],['D','La lumiÃ¨re change de vitesse',0]]],
    ["Quelle est l'unitÃ© de la pression ?", 'DEBUTANT', 'ENAFEP', [['A','Pascal (Pa)',1],['B','Newton (N)',0],['C','Joule (J)',0],['D','Watt (W)',0]]],
    ["La tension d'une batterie standard AA est :", 'DEBUTANT', 'ENAFEP', [['A','1,5 V',1],['B','3 V',0],['C','9 V',0],['D','12 V',0]]],
    ["Quelle est la formule de la puissance Ã©lectrique ?", 'ELEMENTAIRE', 'ENAFEP', [['A','P = U Ã— I',1],['B','P = U / I',0],['C','P = IÂ²',0],['D','P = U + I',0]]],
    ["La densitÃ© de l'eau est :", 'DEBUTANT', 'ENAFEP', [['A','1 g/cmÂ³',1],['B','0,5 g/cmÂ³',0],['C','2 g/cmÂ³',0],['D','1,5 g/cmÂ³',0]]],
    ["La rÃ©fraction de la lumiÃ¨re se produit quand :", 'INTERMEDIAIRE', 'TENASOSP', [['A','La lumiÃ¨re passe d\'un milieu Ã  un autre de densitÃ© diffÃ©rente',1],['B','La lumiÃ¨re est rÃ©flÃ©chie',0],['C','La lumiÃ¨re est absorbÃ©e',0],['D','La lumiÃ¨re se disperse',0]]],
    ["Quelle est la quantitÃ© de chaleur Q pour chauffer 1 kg d'eau de 20Â°C Ã  100Â°C ? (c = 4200 J/kgÂ°C)", 'AVANCE', 'TENASOSP', [['A','336 000 J',1],['B','84 000 J',0],['C','420 000 J',0],['D','168 000 J',0]]],
    ["La deuxiÃ¨me loi de Newton s'Ã©nonce :", 'INTERMEDIAIRE', 'TENASOSP', [['A','F = m Ã— a',1],['B','F = m / a',0],['C','F = m + a',0],['D','a = F + m',0]]],
    ["Un circuit en parallÃ¨le se caractÃ©rise par :", 'ELEMENTAIRE', 'ENAFEP', [['A','La mÃªme tension aux bornes de chaque composant',1],['B','Le mÃªme courant dans chaque composant',0],['C','La somme des rÃ©sistances',0],['D','L\'intensitÃ© nulle',0]]],
    ["Quelle est la longueur d'onde de la lumiÃ¨re visible (environ) ?", 'AVANCE', 'TENASOSP', [['A','400 â€“ 700 nm',1],['B','10 â€“ 100 nm',0],['C','1 mm â€“ 1 m',0],['D','0,1 â€“ 1 nm',0]]],
    ["Le principe d'ArchimÃ¨de stipule qu'un corps immergÃ© subit :", 'ELEMENTAIRE', 'ENAFEP', [['A','Une poussÃ©e verticale vers le haut Ã©gale au poids du fluide dÃ©placÃ©',1],['B','Une pression latÃ©rale',0],['C','Une force vers le bas',0],['D','Aucune force',0]]],
    ["La chaleur se propage par conduction, convection et :", 'ELEMENTAIRE', 'ENAFEP', [['A','Rayonnement',1],['B','Ã‰vaporation',0],['C','Fusion',0],['D','Condensation',0]]],
    ["Le gÃ©nÃ©rateur d'un circuit Ã©lectrique est source de :", 'ELEMENTAIRE', 'ENAFEP', [['A','Tension (force Ã©lectromotrice)',1],['B','RÃ©sistance',0],['C','IntensitÃ© nulle',0],['D','FrÃ©quence',0]]],
    ["La vitesse du son dans l'air Ã  20Â°C est environ :", 'INTERMEDIAIRE', 'TENASOSP', [['A','340 m/s',1],['B','300 000 km/s',0],['C','1500 m/s',0],['D','150 m/s',0]]],
    ["Quel phÃ©nomÃ¨ne explique l'arc-en-ciel ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','Dispersion de la lumiÃ¨re par rÃ©fraction dans les gouttes d\'eau',1],['B','RÃ©flexion totale dans l\'air',0],['C','Diffraction de la lumiÃ¨re',0],['D','Polarisation',0]]],
    ["La rÃ©sistance d'un conducteur dÃ©pend de :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Sa longueur, section et rÃ©sistivitÃ© du matÃ©riau',1],['B','La tension appliquÃ©e uniquement',0],['C','Le courant uniquement',0],['D','La frÃ©quence',0]]],
    ["L'Ã©nergie potentielle gravitationnelle est donnÃ©e par :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Ep = mgh',1],['B','Ep = mvÂ²/2',0],['C','Ep = Fd',0],['D','Ep = P/t',0]]],
    ["Quelle est la frÃ©quence d'un son de pÃ©riode T = 0,02 s ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','50 Hz',1],['B','200 Hz',0],['C','0,02 Hz',0],['D','20 Hz',0]]],
    ["Le magnÃ©tisme est liÃ© Ã  :", 'ELEMENTAIRE', 'ENAFEP', [['A','Les charges Ã©lectriques en mouvement',1],['B','Les charges au repos',0],['C','La tempÃ©rature',0],['D','La masse',0]]],
    ["La dilatation thermique se produit quand :", 'ELEMENTAIRE', 'ENAFEP', [['A','Un corps se dilate en se rÃ©chauffant',1],['B','Un corps se contracte sous pression',0],['C','Un corps change d\'Ã©tat',0],['D','Un corps refroidit',0]]],
    ["Quelle est la premiÃ¨re loi de Kepler ?", 'AVANCE', 'EXAMEN_ETAT', [['A','Les planÃ¨tes dÃ©crivent des ellipses autour du Soleil',1],['B','Les planÃ¨tes se dÃ©placent en ligne droite',0],['C','La pÃ©riode est proportionnelle Ã  la distance',0],['D','Toutes les orbites sont circulaires',0]]],
    ["Le rendement d'une machine est :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Î· = Ã©nergie utile / Ã©nergie fournie Ã— 100%',1],['B','Î· = Ã©nergie perdue / Ã©nergie fournie',0],['C','Î· = puissance Ã— temps',0],['D','Î· = force Ã— distance',0]]],
    ["Dans un fil conducteur, les porteurs de charges sont :", 'ELEMENTAIRE', 'ENAFEP', [['A','Les Ã©lectrons libres',1],['B','Les protons',0],['C','Les neutrons',0],['D','Les ions positifs',0]]],
    ["Qu'est-ce que l'effet photoÃ©lectrique ?", 'AVANCE', 'EXAMEN_ETAT', [['A','Ã‰mission d\'Ã©lectrons par un mÃ©tal Ã©clairÃ©',1],['B','Ã‰mission de photons',0],['C','RÃ©flexion de la lumiÃ¨re',0],['D','RÃ©fraction de la lumiÃ¨re',0]]],
    ["La loi de gravitation universelle de Newton : F = G Ã— mâ‚mâ‚‚/rÂ² â€” G reprÃ©sente :", 'AVANCE', 'EXAMEN_ETAT', [['A','La constante gravitationnelle universelle',1],['B','L\'accÃ©lÃ©ration',0],['C','Le poids spÃ©cifique',0],['D','La force centripÃ¨te',0]]],
    ["Un transformateur abaisseur a un rapport de transformation n = Nâ‚/Nâ‚‚ :", 'AVANCE', 'EXAMEN_ETAT', [['A','SupÃ©rieur Ã  1',1],['B','InfÃ©rieur Ã  1',0],['C','Ã‰gal Ã  1',0],['D','NÃ©gatif',0]]],
    ["Quelle est l'unitÃ© de la capacitÃ© Ã©lectrique ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','Farad (F)',1],['B','Ohm (Î©)',0],['C','Henry (H)',0],['D','Coulomb (C)',0]]],
    ["Le principe de conservation de l'Ã©nergie stipule que :", 'INTERMEDIAIRE', 'TENASOSP', [['A','L\'Ã©nergie totale d\'un systÃ¨me isolÃ© reste constante',1],['B','L\'Ã©nergie peut Ãªtre crÃ©Ã©e ou dÃ©truite',0],['C','L\'Ã©nergie cinÃ©tique est toujours maximale',0],['D','La chaleur est toujours perdue',0]]],
    ["La pÃ©riode d'un pendule simple dÃ©pend principalement de :", 'AVANCE', 'TENASOSP', [['A','La longueur du fil et g',1],['B','La masse du pendule',0],['C','L\'amplitude d\'oscillation',0],['D','La matiÃ¨re du pendule',0]]],
];
foreach ($questions_ph as $q) { addQ($pdo, $stQ, $stO, $matMap[$m], ...$q); $added++; }
echo "Physique: +" . ($added-$prevAdded) . " questions\n";

/* â•â• BIOLOGIE (besoin ~37 pour atteindre 125) â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
$prevAdded = $added;
$m = 'biologie';
$questions_bio = [
    ["L'ADN se trouve principalement dans :", 'ELEMENTAIRE', 'ENAFEP', [['A','Le noyau de la cellule',1],['B','Les mitochondries',0],['C','Le cytoplasme',0],['D','La membrane',0]]],
    ["Quelle est la base azotÃ©e complÃ©mentaire de l'adÃ©nine dans l'ADN ?", 'AVANCE', 'EXAMEN_ETAT', [['A','Thymine',1],['B','Guanine',0],['C','Cytosine',0],['D','Uracile',0]]],
    ["Les organismes autotrophes produisent leur matiÃ¨re organique grÃ¢ce Ã  :", 'INTERMEDIAIRE', 'TENASOSP', [['A','La photosynthÃ¨se (Ã©nergie lumineuse)',1],['B','La consommation d\'autres organismes',0],['C','La fermentation',0],['D','La dÃ©composition',0]]],
    ["La respiration cellulaire aÃ©robie utilise :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Oâ‚‚ et glucose',1],['B','COâ‚‚ et eau uniquement',0],['C','LumiÃ¨re et chlorophylle',0],['D','ATP uniquement',0]]],
    ["Quelle est la fonction des ribosomes ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','SynthÃ¨se des protÃ©ines',1],['B','Production d\'ATP',0],['C','Digestion cellulaire',0],['D','Division cellulaire',0]]],
    ["La gÃ©nÃ©tique est la science qui Ã©tudie :", 'DEBUTANT', 'ENAFEP', [['A','L\'hÃ©rÃ©ditÃ© et la variation gÃ©nÃ©tique',1],['B','Les maladies infectieuses',0],['C','Le dÃ©veloppement des embryons',0],['D','La structure cellulaire',0]]],
    ["Le gÃ¨ne dominant s'exprime :", 'INTERMEDIAIRE', 'TENASOSP', [['A','MÃªme en prÃ©sence d\'un seul allÃ¨le',1],['B','Uniquement en homozygotie',0],['C','Jamais en prÃ©sence d\'un allÃ¨le rÃ©cessif',0],['D','Seulement chez les femmes',0]]],
    ["Les chloroplastes sont prÃ©sents dans :", 'ELEMENTAIRE', 'ENAFEP', [['A','Les cellules vÃ©gÃ©tales',1],['B','Les cellules animales',0],['C','Les cellules bactÃ©riennes',0],['D','Les champignons',0]]],
    ["La classification de LinnÃ© repose sur :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Des critÃ¨res morphologiques et gÃ©nÃ©tiques',1],['B','La couleur et la taille',0],['C','Uniquement le milieu de vie',0],['D','Le mode de reproduction',0]]],
    ["Quel est le rÃ´le de l'enzyme dans une rÃ©action biochimique ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','Catalyser (accÃ©lÃ©rer) la rÃ©action sans Ãªtre consommÃ©',1],['B','Fournir l\'Ã©nergie',0],['C','Bloquer la rÃ©action',0],['D','Fixer le pH',0]]],
    ["La glycolyse se dÃ©roule dans :", 'AVANCE', 'EXAMEN_ETAT', [['A','Le cytoplasme',1],['B','Les mitochondries',0],['C','Le noyau',0],['D','Le rÃ©ticulum',0]]],
    ["La fÃ©condation est la fusion de :", 'ELEMENTAIRE', 'ENAFEP', [['A','Un gamÃ¨te mÃ¢le et un gamÃ¨te femelle',1],['B','Deux cellules somatiques',0],['C','Deux noyaux quelconques',0],['D','Deux cellules haploÃ¯des identiques',0]]],
    ["Quelle est la taille approximative d'une cellule eucaryote typique ?", 'AVANCE', 'TENASOSP', [['A','10 â€“ 100 Î¼m',1],['B','1 â€“ 5 nm',0],['C','1 â€“ 5 mm',0],['D','100 Î¼m â€“ 1 cm',0]]],
    ["Le chromosome Y chez l'homme dÃ©termine :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Le sexe masculin',1],['B','Le groupe sanguin',0],['C','La couleur des yeux',0],['D','La taille',0]]],
    ["Les antibiotiques agissent sur :", 'ELEMENTAIRE', 'ENAFEP', [['A','Les bactÃ©ries',1],['B','Les virus',0],['C','Les champignons',0],['D','Les parasites',0]]],
    ["Le cycle de Krebs se dÃ©roule dans :", 'AVANCE', 'EXAMEN_ETAT', [['A','La matrice mitochondriale',1],['B','Le cytoplasme',0],['C','Le rÃ©ticulum endoplasmique',0],['D','L\'appareil de Golgi',0]]],
    ["L'Ã©volution des espÃ¨ces est principalement expliquÃ©e par :", 'INTERMEDIAIRE', 'TENASOSP', [['A','La sÃ©lection naturelle (Darwin)',1],['B','La gÃ©nÃ©ration spontanÃ©e',0],['C','La volontÃ© des organismes',0],['D','Les mutations uniquement',0]]],
    ["Quelle partie de la fleur est femelle ?", 'ELEMENTAIRE', 'ENAFEP', [['A','Le pistil (carpelle)',1],['B','L\'Ã©tamine',0],['C','Le sÃ©pale',0],['D','Le pÃ©tale',0]]],
    ["La pollinisation est :", 'ELEMENTAIRE', 'ENAFEP', [['A','Le transfert du pollen des Ã©tamines au pistil',1],['B','La fusion des gamÃ¨tes',0],['C','La formation des graines',0],['D','Le dÃ©veloppement des fruits',0]]],
    ["Qu'est-ce qu'une mutation ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','Une modification permanente de l\'ADN',1],['B','Un changement de comportement',0],['C','Une variation saisonniÃ¨re',0],['D','Un changement de couleur',0]]],
    ["Les virus se rÃ©pliquent uniquement :", 'ELEMENTAIRE', 'ENAFEP', [['A','Ã€ l\'intÃ©rieur de cellules hÃ´tes vivantes',1],['B','Dans un milieu liquide',0],['C','En dehors d\'un organisme',0],['D','Dans les laboratoires uniquement',0]]],
    ["La membrane plasmique est composÃ©e principalement de :", 'AVANCE', 'EXAMEN_ETAT', [['A','Une bicouche lipidique avec des protÃ©ines',1],['B','De cellulose',0],['C','De chitine',0],['D','De glucose',0]]],
    ["Quelle est la principale diffÃ©rence entre cellule procaryote et eucaryote ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','La prÃ©sence d\'un noyau dÃ©limitÃ©',1],['B','La prÃ©sence d\'une membrane',0],['C','La prÃ©sence d\'ADN',0],['D','La prÃ©sence de ribosomes',0]]],
    ["Les mycÃ¨tes (champignons) sont :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Des eucaryotes hÃ©tÃ©rotrophes',1],['B','Des plantes sans feuilles',0],['C','Des bactÃ©ries',0],['D','Des virus',0]]],
    ["Le cholestÃ©rol est :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Un lipide essentiel Ã  la membrane cellulaire',1],['B','Un glucide',0],['C','Une protÃ©ine',0],['D','Un acide nuclÃ©ique',0]]],
    ["Le SIDA est causÃ© par :", 'ELEMENTAIRE', 'ENAFEP', [['A','Le virus VIH (rÃ©trovirus)',1],['B','Une bactÃ©rie',0],['C','Un parasite',0],['D','Un champignon',0]]],
    ["La transpiration chez les plantes s'appelle :", 'INTERMEDIAIRE', 'TENASOSP', [['A','La transpiration ou Ã©vapotranspiration stomatique',1],['B','La photosynthÃ¨se',0],['C','L\'absorption racinaire',0],['D','La guttation',0]]],
    ["Quelle est la fonction du foie dans le mÃ©tabolisme ?", 'INTERMEDIAIRE', 'TENASOSP', [['A','DÃ©toxification, synthÃ¨se protÃ©ique, stockage glycogÃ¨ne',1],['B','Produire des globules rouges',0],['C','Filtrer le sang',0],['D','Produire l\'adrÃ©naline',0]]],
    ["Les cellules souches ont la capacitÃ© de :", 'AVANCE', 'EXAMEN_ETAT', [['A','Se diffÃ©rencier en diffÃ©rents types cellulaires',1],['B','RÃ©sister Ã  tous les mÃ©dicaments',0],['C','Produire des anticorps',0],['D','Uniquement se diviser',0]]],
    ["Le complÃ©ment alimentaire dont le dÃ©ficit cause l'anÃ©mie est :", 'ELEMENTAIRE', 'ENAFEP', [['A','Le fer',1],['B','Le calcium',0],['C','Le potassium',0],['D','Le zinc',0]]],
    ["L'hybridation en biologie est :", 'AVANCE', 'EXAMEN_ETAT', [['A','Le croisement entre deux espÃ¨ces diffÃ©rentes',1],['B','La division cellulaire',0],['C','La mutation gÃ©nique',0],['D','La reproduction asexuÃ©e',0]]],
    ["Les plantes carnivores obtiennent l'azote manquant en :", 'AVANCE', 'TENASOSP', [['A','DigÃ©rant des insectes et petits animaux',1],['B','Absorbant l\'azote de l\'air',0],['C','RÃ©alisant la photosynthÃ¨se',0],['D','Parasitant d\'autres plantes',0]]],
    ["La notochorde est prÃ©sente chez les :", 'AVANCE', 'EXAMEN_ETAT', [['A','ChordÃ©s (au moins Ã  un stade de dÃ©veloppement)',1],['B','Insectes uniquement',0],['C','BactÃ©ries',0],['D','Champignons',0]]],
    ["Quelle hormone rÃ©gule le cycle menstruel fÃ©minin ?", 'AVANCE', 'TENASOSP', [['A','LH et FSH (Å“strogÃ¨nes et progestÃ©rone)',1],['B','TestostÃ©rone uniquement',0],['C','AdrÃ©naline',0],['D','Insuline',0]]],
    ["Les organismes dÃ©composeurs (bactÃ©ries, champignons) ont un rÃ´le Ã©cologique de :", 'INTERMEDIAIRE', 'TENASOSP', [['A','Recycler la matiÃ¨re organique morte',1],['B','Produire l\'oxygÃ¨ne',0],['C','Fixer l\'azote atmosphÃ©rique',0],['D','PrÃ©dation',0]]],
    ["Le gÃ©notype est :", 'INTERMEDIAIRE', 'TENASOSP', [['A','L\'ensemble des allÃ¨les portÃ©s par un individu',1],['B','Les caractÃ¨res visibles',0],['C','L\'environnement de l\'individu',0],['D','Le nombre de chromosomes',0]]],
];
foreach ($questions_bio as $q) { addQ($pdo, $stQ, $stO, $matMap[$m], ...$q); $added++; }
echo "Biologie: +" . ($added-$prevAdded) . " questions\n";

/* â•â• ANGLAIS (besoin ~42 pour atteindre 125) â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
$prevAdded = $added;
$m = 'anglais';
$questions_en = [
    ["What is the plural of 'child'?", 'DEBUTANT', 'ENAFEP', [['A','children',1],['B','childs',0],['C','childrens',0],['D','childes',0]]],
    ["Choose the correct verb: 'She ___ to school every day.'", 'DEBUTANT', 'ENAFEP', [['A','goes',1],['B','go',0],['C','going',0],['D','gone',0]]],
    ["What is the comparative of 'good'?", 'ELEMENTAIRE', 'ENAFEP', [['A','better',1],['B','more good',0],['C','gooder',0],['D','best',0]]],
    ["Complete: 'If I had money, I ___ buy a car.'", 'INTERMEDIAIRE', 'TENASOSP', [['A','would',1],['B','will',0],['C','shall',0],['D','can',0]]],
    ["What is the past tense of 'write'?", 'ELEMENTAIRE', 'ENAFEP', [['A','wrote',1],['B','written',0],['C','writed',0],['D','writ',0]]],
    ["'Despite the rain, they continued.' â€” 'Despite' expresses:", 'AVANCE', 'EXAMEN_ETAT', [['A','Contrast/concession',1],['B','Cause',0],['C','Result',0],['D','Condition',0]]],
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

// RÃ©sultat final
$newTotal = (int)$pdo->query("SELECT COUNT(*) FROM question_bank")->fetchColumn();
echo "\nâ”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n";
echo "Questions ajoutÃ©es: $added\n";
echo "NOUVEAU TOTAL: $newTotal\n";
echo "â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”â”\n";
?>

