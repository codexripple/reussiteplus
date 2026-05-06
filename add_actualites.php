<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_admin(); // Script restreint aux admins
/**
 * Ajout de 50 questions ActualitÃ©s (Histoire-GÃ©o / AUTRE)
 * Usage CLI uniquement : php add_actualites.php
 */
if (php_sapi_name() !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'])) {
    http_response_code(403); die('AccÃ¨s refusÃ©.');
}
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
header('Content-Type: text/plain; charset=utf-8');

$pdo = db();
$pdo->exec("SET FOREIGN_KEY_CHECKS=0");

// MatiÃ¨re Histoire-GÃ©o
$row = $pdo->query("SELECT id FROM matieres WHERE code='histgeo' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$row) { echo "ERREUR : matiÃ¨re histgeo introuvable.\n"; exit(1); }
$matId = $row['id'];

$stQ = $pdo->prepare("INSERT INTO question_bank (id,matiere_id,enonce,difficulte,exam_type,type_question,objectif,status) VALUES (UUID(),?,?,?,'AUTRE','QCM',?,  'PUBLIE')");
$stO = $pdo->prepare("INSERT INTO question_options (id,question_id,lettre,texte,est_correcte) VALUES (UUID(),?,?,?,?)");

function addQ($pdo, $stQ, $stO, $matId, $enonce, $diff, $explication, $opts) {
    $stQ->execute([$matId, $enonce, $diff, $explication]);
    $stmt = $pdo->prepare("SELECT id FROM question_bank WHERE enonce=? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$enonce]);
    $qid = $stmt->fetchColumn();
    foreach ($opts as [$l,$t,$ok]) $stO->execute([$qid,$l,$t,$ok]);
    echo "  + $enonce\n";
}

echo "=== Ajout 50 questions ACTUALITÃ‰S ===\n\n";

$questions = [
    // â”€â”€ RDC & Afrique â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    ["Quelle est la monnaie officielle de la RÃ©publique DÃ©mocratique du Congo ?", 'ELEMENTAIRE',
     'La monnaie de la RDC est le Franc congolais (CDF), introduit en 1998.',
     [['A','Franc congolais',1],['B','Franc CFA',0],['C','Dollar congolais',0],['D','Kwacha',0]]],

    ["Quelle ville est la capitale de la RDC ?", 'ELEMENTAIRE',
     'Kinshasa est la capitale et la plus grande ville de la RDC.',
     [['A','Kinshasa',1],['B','Lubumbashi',0],['C','Goma',0],['D','Mbuji-Mayi',0]]],

    ["Quel fleuve traverse la RDC et est le deuxiÃ¨me plus long d'Afrique ?", 'ELEMENTAIRE',
     'Le fleuve Congo est le deuxiÃ¨me plus long fleuve d\'Afrique aprÃ¨s le Nil.',
     [['A','Le Congo',1],['B','Le Nil',0],['C','Le ZambÃ¨ze',0],['D','Le Niger',0]]],

    ["Quelle organisation rÃ©gionale regroupe les pays d'Afrique centrale ?", 'INTERMEDIAIRE',
     'La CEEAC (CommunautÃ© Ã‰conomique des Ã‰tats de l\'Afrique Centrale) regroupe 11 pays.',
     [['A','CEEAC',1],['B','CEDEAO',0],['C','SADC',0],['D','UA',0]]],

    ["Quelle est la plus grande province de la RDC en superficie ?", 'INTERMEDIAIRE',
     'Le KasaÃ¯ est l\'une des grandes provinces, mais le Mai-Ndombe est parmi les plus vastes aprÃ¨s le dÃ©coupage de 2015.',
     [['A','Maniema',0],['B','Kwango',0],['C','Mai-Ndombe',0],['D','Tanganyika',1]]],

    ["En quelle annÃ©e la RDC a-t-elle obtenu son indÃ©pendance ?", 'ELEMENTAIRE',
     'La RDC (alors Congo-LÃ©opoldville) a obtenu son indÃ©pendance de la Belgique le 30 juin 1960.',
     [['A','1960',1],['B','1962',0],['C','1965',0],['D','1958',0]]],

    ["Qui est le premier prÃ©sident de la RDC aprÃ¨s l'indÃ©pendance ?", 'INTERMEDIAIRE',
     'Joseph Kasa-Vubu fut le premier prÃ©sident de la RÃ©publique du Congo dÃ¨s le 30 juin 1960.',
     [['A','Joseph Kasa-Vubu',1],['B','Patrice Lumumba',0],['C','Mobutu Sese Seko',0],['D','Laurent-DÃ©sirÃ© Kabila',0]]],

    ["Quel minÃ©ral stratÃ©gique est principalement extrait au Katanga (Lualaba) ?", 'INTERMEDIAIRE',
     'Le cobalt et le cuivre sont les principaux minerais du Katanga. La RDC fournit plus de 70% du cobalt mondial.',
     [['A','Cobalt et cuivre',1],['B','Diamant et or',0],['C','Bauxite et fer',0],['D','Uranium et lithium',0]]],

    ["Quel est le nom de l'union africaine fondÃ©e en 2002 qui a remplacÃ© l'OUA ?", 'INTERMEDIAIRE',
     'L\'Union Africaine (UA) a Ã©tÃ© crÃ©Ã©e en 2002 Ã  Durban (Afrique du Sud), remplaÃ§ant l\'Organisation de l\'UnitÃ© Africaine.',
     [['A','Union Africaine (UA)',1],['B','UEMOA',0],['C','CEMAC',0],['D','NEPAD',0]]],

    ["Quel pays africain possÃ¨de la plus grande Ã©conomie du continent (PIB) ?", 'INTERMEDIAIRE',
     'Le Nigeria est la plus grande Ã©conomie d\'Afrique devant l\'Ã‰gypte et l\'Afrique du Sud.',
     [['A','Nigeria',1],['B','Afrique du Sud',0],['C','Ã‰gypte',0],['D','Ã‰thiopie',0]]],

    // â”€â”€ GÃ©ographie mondiale â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    ["Quel est le pays le plus peuplÃ© du monde ?", 'ELEMENTAIRE',
     'L\'Inde a dÃ©passÃ© la Chine en 2023 pour devenir le pays le plus peuplÃ© avec plus de 1,4 milliard d\'habitants.',
     [['A','Inde',1],['B','Chine',0],['C','Ã‰tats-Unis',0],['D','IndonÃ©sie',0]]],

    ["Quel est le plus grand pays du monde en superficie ?", 'ELEMENTAIRE',
     'La Russie est le plus grand pays du monde avec environ 17 millions de kmÂ².',
     [['A','Russie',1],['B','Canada',0],['C','Ã‰tats-Unis',0],['D','Chine',0]]],

    ["Quelle est la capitale du Kenya ?", 'ELEMENTAIRE',
     'Nairobi est la capitale et la plus grande ville du Kenya.',
     [['A','Nairobi',1],['B','Mombasa',0],['C','Kampala',0],['D','Dar es Salam',0]]],

    ["Quel ocÃ©an borde la cÃ´te ouest de l'Afrique ?", 'ELEMENTAIRE',
     'L\'ocÃ©an Atlantique borde la cÃ´te ouest de l\'Afrique, tandis que l\'ocÃ©an Indien borde la cÃ´te est.',
     [['A','OcÃ©an Atlantique',1],['B','OcÃ©an Indien',0],['C','OcÃ©an Pacifique',0],['D','Mer MÃ©diterranÃ©e',0]]],

    ["Combien de pays composent le continent africain ?", 'INTERMEDIAIRE',
     'L\'Afrique compte 54 Ã‰tats souverains reconnus par l\'Union Africaine.',
     [['A','54',1],['B','48',0],['C','52',0],['D','57',0]]],

    ["Quel est le plus long fleuve du monde ?", 'ELEMENTAIRE',
     'Le Nil, avec environ 6 650 km, est gÃ©nÃ©ralement considÃ©rÃ© comme le plus long fleuve du monde.',
     [['A','Le Nil',1],['B','L\'Amazone',0],['C','Le Congo',0],['D','Le Mississippi',0]]],

    ["Quelle chaÃ®ne de montagnes sÃ©pare l'Europe de l'Asie ?", 'INTERMEDIAIRE',
     'Les monts Oural (et la riviÃ¨re Oural) forment la frontiÃ¨re conventionnelle entre l\'Europe et l\'Asie.',
     [['A','Les Oural',1],['B','Les Alpes',0],['C','Le Caucase',0],['D','Les Carpates',0]]],

    ["Quelle est la plus haute montagne du monde ?", 'ELEMENTAIRE',
     'L\'Everest (8 849 m) est la plus haute montagne du monde, Ã  la frontiÃ¨re entre le NÃ©pal et la Chine.',
     [['A','L\'Everest',1],['B','Le K2',0],['C','Le Kilimandjaro',0],['D','Le Mont Blanc',0]]],

    // â”€â”€ Sciences & Technologies â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    ["Quel gaz est principalement responsable de l'effet de serre ?", 'INTERMEDIAIRE',
     'Le COâ‚‚ (dioxyde de carbone) est le principal gaz Ã  effet de serre d\'origine humaine.',
     [['A','COâ‚‚ (dioxyde de carbone)',1],['B','Oâ‚ƒ (ozone)',0],['C','Nâ‚‚ (azote)',0],['D','Hâ‚‚ (hydrogÃ¨ne)',0]]],

    ["Qu'est-ce que l'intelligence artificielle (IA) ?", 'ELEMENTAIRE',
     'L\'IA est la simulation de processus d\'intelligence humaine par des machines, notamment via l\'apprentissage automatique.',
     [['A','La simulation de l\'intelligence humaine par des machines',1],['B','Un type de robot physique',0],['C','Un systÃ¨me de communication satellite',0],['D','Un logiciel antivirus',0]]],

    ["Quelle entreprise a dÃ©veloppÃ© le modÃ¨le d'IA ChatGPT ?", 'INTERMEDIAIRE',
     'ChatGPT est dÃ©veloppÃ© par OpenAI, une sociÃ©tÃ© amÃ©ricaine fondÃ©e en 2015.',
     [['A','OpenAI',1],['B','Google',0],['C','Meta',0],['D','Microsoft',0]]],

    ["Qu'est-ce que la 5G ?", 'INTERMEDIAIRE',
     'La 5G est la 5Ã¨me gÃ©nÃ©ration de rÃ©seaux mobiles, offrant des vitesses bien supÃ©rieures Ã  la 4G.',
     [['A','La 5Ã¨me gÃ©nÃ©ration de rÃ©seaux mobiles',1],['B','Un nouveau type de satellite',0],['C','Un standard Wi-Fi',0],['D','Une technologie Bluetooth amÃ©liorÃ©e',0]]],

    ["Quel pays a envoyÃ© le premier homme sur la Lune ?", 'ELEMENTAIRE',
     'Les Ã‰tats-Unis ont envoyÃ© Neil Armstrong et Buzz Aldrin sur la Lune le 20 juillet 1969 (mission Apollo 11).',
     [['A','Ã‰tats-Unis',1],['B','URSS',0],['C','France',0],['D','Chine',0]]],

    ["Qu'est-ce qu'une Ã©nergie renouvelable ?", 'ELEMENTAIRE',
     'Une Ã©nergie renouvelable provient de sources naturelles qui se reconstituent rapidement (soleil, vent, eau).',
     [['A','Une Ã©nergie issue de sources naturelles inÃ©puisables',1],['B','Une Ã©nergie issue du pÃ©trole',0],['C','Une Ã©nergie nuclÃ©aire',0],['D','Une Ã©nergie issue du charbon',0]]],

    ["Quelle planÃ¨te est surnommÃ©e la 'planÃ¨te rouge' ?", 'ELEMENTAIRE',
     'Mars est surnommÃ©e la planÃ¨te rouge en raison de l\'oxyde de fer (rouille) prÃ©sent Ã  sa surface.',
     [['A','Mars',1],['B','Jupiter',0],['C','VÃ©nus',0],['D','Saturne',0]]],

    // â”€â”€ Ã‰conomie & DÃ©veloppement â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    ["Que signifie PIB ?", 'ELEMENTAIRE',
     'PIB signifie Produit IntÃ©rieur Brut. C\'est la valeur totale de tous les biens et services produits dans un pays.',
     [['A','Produit IntÃ©rieur Brut',1],['B','Profit Industriel Brut',0],['C','Production Interne de Base',0],['D','Plan d\'Investissement BudgÃ©taire',0]]],

    ["Quelle institution internationale accorde des prÃªts aux pays en dÃ©veloppement ?", 'INTERMEDIAIRE',
     'La Banque Mondiale et le FMI (Fonds MonÃ©taire International) sont les principales institutions de financement du dÃ©veloppement.',
     [['A','La Banque Mondiale',1],['B','La Banque Centrale EuropÃ©enne',0],['C','La RÃ©serve FÃ©dÃ©rale amÃ©ricaine',0],['D','La Banque d\'Afrique',0]]],

    ["Qu'est-ce que le commerce Ã©quitable ?", 'INTERMEDIAIRE',
     'Le commerce Ã©quitable garantit des prix justes et des conditions de travail dÃ©centes aux producteurs des pays en dÃ©veloppement.',
     [['A','Un systÃ¨me garantissant des prix justes aux petits producteurs',1],['B','L\'Ã©change de biens sans taxes',0],['C','Le commerce entre pays riches uniquement',0],['D','Le troc entre pays africains',0]]],

    ["Quel est l'objectif principal du dÃ©veloppement durable ?", 'INTERMEDIAIRE',
     'Le dÃ©veloppement durable vise Ã  rÃ©pondre aux besoins du prÃ©sent sans compromettre la capacitÃ© des gÃ©nÃ©rations futures.',
     [['A','RÃ©pondre aux besoins prÃ©sents sans compromettre les gÃ©nÃ©rations futures',1],['B','Maximiser la croissance Ã©conomique',0],['C','RÃ©duire la population mondiale',0],['D','Supprimer l\'industrie polluante',0]]],

    ["Combien d'Objectifs de DÃ©veloppement Durable (ODD) l'ONU a-t-elle dÃ©finis pour 2030 ?", 'INTERMEDIAIRE',
     'L\'Agenda 2030 de l\'ONU comprend 17 Objectifs de DÃ©veloppement Durable (ODD) adoptÃ©s en 2015.',
     [['A','17',1],['B','10',0],['C','8',0],['D','20',0]]],

    // â”€â”€ SantÃ© & Environnement â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    ["Quel vaccin a Ã©tÃ© dÃ©veloppÃ© en un temps record pour combattre la COVID-19 ?", 'INTERMEDIAIRE',
     'Plusieurs vaccins ont Ã©tÃ© dÃ©veloppÃ©s fin 2020, notamment Pfizer-BioNTech, Moderna et AstraZeneca.',
     [['A','Pfizer-BioNTech (ARNm)',1],['B','BCG',0],['C','Variole',0],['D','Polio oral',0]]],

    ["Quelle maladie est causÃ©e par le moustique anophÃ¨le en Afrique ?", 'ELEMENTAIRE',
     'Le paludisme (malaria) est transmis par la piqÃ»re du moustique anophÃ¨le femelle infectÃ© par le parasite Plasmodium.',
     [['A','Le paludisme (malaria)',1],['B','La dengue',0],['C','La typhoÃ¯de',0],['D','Le cholÃ©ra',0]]],

    ["Qu'est-ce que le rÃ©chauffement climatique ?", 'ELEMENTAIRE',
     'Le rÃ©chauffement climatique dÃ©signe l\'augmentation progressive de la tempÃ©rature moyenne de la Terre due aux Ã©missions de gaz Ã  effet de serre.',
     [['A','L\'augmentation de la tempÃ©rature moyenne de la Terre',1],['B','Une variation naturelle des saisons',0],['C','Le refroidissement des pÃ´les',0],['D','Une rÃ©action chimique dans l\'atmosphÃ¨re',0]]],

    ["Quel accord international vise Ã  limiter le rÃ©chauffement climatique Ã  1,5Â°C ?", 'INTERMEDIAIRE',
     'L\'Accord de Paris (COP21, 2015) engage les pays Ã  limiter le rÃ©chauffement Ã  bien en dessous de 2Â°C.',
     [['A','L\'Accord de Paris',1],['B','Le Protocole de Kyoto',0],['C','Le TraitÃ© de MontrÃ©al',0],['D','La Convention de Rio',0]]],

    ["Quelle est la principale cause de dÃ©forestation en Afrique centrale ?", 'INTERMEDIAIRE',
     'L\'agriculture sur brÃ»lis, l\'exploitation forestiÃ¨re et l\'extraction miniÃ¨re sont les principales causes de dÃ©forestation.',
     [['A','L\'agriculture sur brÃ»lis et l\'exploitation forestiÃ¨re',1],['B','Le tourisme de masse',0],['C','Les tremblements de terre',0],['D','La sÃ©cheresse',0]]],

    // â”€â”€ Institutions & Droit â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    ["Quelle organisation mondiale veille Ã  la paix et la sÃ©curitÃ© internationale ?", 'ELEMENTAIRE',
     'L\'ONU (Organisation des Nations Unies), fondÃ©e en 1945, est la principale organisation internationale pour la paix.',
     [['A','L\'ONU (Organisation des Nations Unies)',1],['B','L\'OTAN',0],['C','L\'Union EuropÃ©enne',0],['D','La Croix-Rouge',0]]],

    ["Combien de membres permanents siÃ¨gent au Conseil de SÃ©curitÃ© de l'ONU ?", 'INTERMEDIAIRE',
     'Le Conseil de SÃ©curitÃ© de l\'ONU compte 5 membres permanents : USA, Russie, Chine, France, Royaume-Uni.',
     [['A','5',1],['B','10',0],['C','15',0],['D','7',0]]],

    ["Qu'est-ce que la DÃ©claration Universelle des Droits de l'Homme ?", 'ELEMENTAIRE',
     'AdoptÃ©e par l\'ONU en 1948, la DUDH proclame les droits fondamentaux inaliÃ©nables de tout Ãªtre humain.',
     [['A','Un texte proclamant les droits fondamentaux de tout Ãªtre humain',1],['B','Un traitÃ© militaire entre nations',0],['C','La constitution de l\'ONU',0],['D','Un accord commercial international',0]]],

    ["En quelle annÃ©e la Constitution de la RDC actuellement en vigueur a-t-elle Ã©tÃ© adoptÃ©e ?", 'INTERMEDIAIRE',
     'La Constitution de la RDC a Ã©tÃ© adoptÃ©e par rÃ©fÃ©rendum et promulguÃ©e le 18 fÃ©vrier 2006.',
     [['A','2006',1],['B','1997',0],['C','2003',0],['D','2010',0]]],

    ["Quel est le rÃ´le de la Cour PÃ©nale Internationale (CPI) ?", 'AVANCE',
     'La CPI juge les individus accusÃ©s de gÃ©nocide, crimes de guerre et crimes contre l\'humanitÃ©.',
     [['A','Juger les individus pour crimes de guerre, gÃ©nocide et crimes contre l\'humanitÃ©',1],['B','RÃ©gler les conflits commerciaux entre Ã‰tats',0],['C','Superviser les Ã©lections mondiales',0],['D','ProtÃ©ger les droits d\'auteur internationaux',0]]],

    // â”€â”€ Culture & SociÃ©tÃ© â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    ["Quelle est la langue la plus parlÃ©e dans le monde ?", 'ELEMENTAIRE',
     'Le mandarin (chinois) est la langue maternelle la plus parlÃ©e. L\'anglais est la plus utilisÃ©e comme langue internationale.',
     [['A','Le mandarin (chinois)',1],['B','L\'anglais',0],['C','L\'espagnol',0],['D','Le franÃ§ais',0]]],

    ["Combien de langues nationales la RDC reconnaÃ®t-elle officiellement ?", 'INTERMEDIAIRE',
     'La RDC reconnaÃ®t 4 langues nationales : kikongo, lingala, swahili et tshiluba, en plus du franÃ§ais (officiel).',
     [['A','4',1],['B','2',0],['C','6',0],['D','1',0]]],

    ["Quelle est la religion la plus pratiquÃ©e en RDC ?", 'ELEMENTAIRE',
     'Le christianisme est pratiquÃ© par environ 95% de la population congolaise.',
     [['A','Le christianisme',1],['B','L\'islam',0],['C','L\'animisme',0],['D','Le bouddhisme',0]]],

    ["Quel sport est le plus populaire en Afrique subsaharienne ?", 'ELEMENTAIRE',
     'Le football (soccer) est de loin le sport le plus populaire en Afrique subsaharienne.',
     [['A','Le football',1],['B','L\'athlÃ©tisme',0],['C','Le basketball',0],['D','Le volleyball',0]]],

    ["En quelle annÃ©e a eu lieu la premiÃ¨re Coupe du Monde de football organisÃ©e en Afrique ?", 'INTERMEDIAIRE',
     'L\'Afrique du Sud a organisÃ© la premiÃ¨re Coupe du Monde de football sur le continent africain en 2010.',
     [['A','2010',1],['B','2006',0],['C','2014',0],['D','2018',0]]],

    // â”€â”€ Ã‰ducation & Jeunesse â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    ["Qu'est-ce que l'ENAFEP en RDC ?", 'ELEMENTAIRE',
     'L\'ENAFEP (Examen National de Fin d\'Ã‰tudes Primaires) est l\'examen de fin du cycle primaire en RDC.',
     [['A','L\'examen de fin d\'Ã©tudes primaires en RDC',1],['B','Un diplÃ´me universitaire',0],['C','Un examen d\'entrÃ©e Ã  l\'universitÃ©',0],['D','Un certificat de formation professionnelle',0]]],

    ["Qu'est-ce que le TENAFEP / TENASOSP en RDC ?", 'ELEMENTAIRE',
     'Le TENASOSP (Test National de SÃ©lection et d\'Orientation Scolaire du Post-Primaire) oriente les Ã©lÃ¨ves aprÃ¨s le primaire.',
     [['A','Un test national d\'orientation scolaire aprÃ¨s le primaire',1],['B','Un examen universitaire',0],['C','Un concours national de mathÃ©matiques',0],['D','Un brevet technique',0]]],

    ["Quel est l'Examen d'Ã‰tat en RDC ?", 'ELEMENTAIRE',
     'L\'Examen d\'Ã‰tat est l\'examen national de fin d\'humanitÃ©s (secondaire) qui sanctionne le diplÃ´me d\'Ã‰tat en RDC.',
     [['A','L\'examen de fin d\'humanitÃ©s (baccalaurÃ©at congolais)',1],['B','Un concours d\'entrÃ©e Ã  l\'Ã©cole primaire',0],['C','Un examen de sÃ©lection universitaire',0],['D','Une Ã©valuation de fin de primaire',0]]],

    ["Combien d'annÃ©es dure le cycle secondaire en RDC ?", 'ELEMENTAIRE',
     'Le cycle secondaire en RDC dure 6 ans (2 ans de cycle d\'orientation + 4 ans de cycle long).',
     [['A','6 ans',1],['B','4 ans',0],['C','5 ans',0],['D','7 ans',0]]],

    ["Quel organisme gÃ¨re le systÃ¨me Ã©ducatif en RDC ?", 'INTERMEDIAIRE',
     'Le MinistÃ¨re de l\'Enseignement Primaire, Secondaire et Technique (EPST) gÃ¨re l\'Ã©ducation de base en RDC.',
     [['A','Le MinistÃ¨re de l\'EPST',1],['B','Le MinistÃ¨re des Finances',0],['C','L\'UNESCO seule',0],['D','Le Gouvernorat Provincial',0]]],

    ["Qu'est-ce que l'UNESCO ?", 'INTERMEDIAIRE',
     'L\'UNESCO est l\'Organisation des Nations Unies pour l\'Ã‰ducation, la Science et la Culture, fondÃ©e en 1945.',
     [['A','L\'Organisation des Nations Unies pour l\'Ã‰ducation, la Science et la Culture',1],['B','Un fonds international pour les enfants',0],['C','Une banque de dÃ©veloppement africain',0],['D','Un programme alimentaire mondial',0]]],
];

$pdo->beginTransaction();
try {
    $added = 0;
    foreach ($questions as [$enonce, $diff, $expl, $opts]) {
        addQ($pdo, $stQ, $stO, $matId, $enonce, $diff, $expl, $opts);
        $added++;
    }
    $pdo->commit();
    echo "\nâœ“ $added questions ajoutÃ©es avec succÃ¨s !\n";
    $total = $pdo->query("SELECT COUNT(*) FROM question_bank")->fetchColumn();
    echo "Total questions en base : $total\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "ERREUR : " . $e->getMessage() . "\n";
}

