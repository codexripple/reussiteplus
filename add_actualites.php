<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_admin(); // Script restreint aux admins
/**
 * Ajout de 50 questions Actualités (Histoire-Géo / AUTRE)
 * Usage CLI uniquement : php add_actualites.php
 */
if (php_sapi_name() !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'])) {
    http_response_code(403); die('Accès refusé.');
}
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
header('Content-Type: text/plain; charset=utf-8');

$pdo = db();
$pdo->exec("SET FOREIGN_KEY_CHECKS=0");

// Matière Histoire-Géo
$row = $pdo->query("SELECT id FROM matieres WHERE code='histgeo' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$row) { echo "ERREUR : matière histgeo introuvable.\n"; exit(1); }
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

echo "=== Ajout 50 questions ACTUALIT�?S ===\n\n";

$questions = [
    // �"?�"? RDC & Afrique �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
    ["Quelle est la monnaie officielle de la République Démocratique du Congo ?", 'ELEMENTAIRE',
     'La monnaie de la RDC est le Franc congolais (CDF), introduit en 1998.',
     [['A','Franc congolais',1],['B','Franc CFA',0],['C','Dollar congolais',0],['D','Kwacha',0]]],

    ["Quelle ville est la capitale de la RDC ?", 'ELEMENTAIRE',
     'Kinshasa est la capitale et la plus grande ville de la RDC.',
     [['A','Kinshasa',1],['B','Lubumbashi',0],['C','Goma',0],['D','Mbuji-Mayi',0]]],

    ["Quel fleuve traverse la RDC et est le deuxième plus long d'Afrique ?", 'ELEMENTAIRE',
     'Le fleuve Congo est le deuxième plus long fleuve d\'Afrique après le Nil.',
     [['A','Le Congo',1],['B','Le Nil',0],['C','Le Zambèze',0],['D','Le Niger',0]]],

    ["Quelle organisation régionale regroupe les pays d'Afrique centrale ?", 'INTERMEDIAIRE',
     'La CEEAC (Communauté �?conomique des �?tats de l\'Afrique Centrale) regroupe 11 pays.',
     [['A','CEEAC',1],['B','CEDEAO',0],['C','SADC',0],['D','UA',0]]],

    ["Quelle est la plus grande province de la RDC en superficie ?", 'INTERMEDIAIRE',
     'Le Kasaï est l\'une des grandes provinces, mais le Mai-Ndombe est parmi les plus vastes après le découpage de 2015.',
     [['A','Maniema',0],['B','Kwango',0],['C','Mai-Ndombe',0],['D','Tanganyika',1]]],

    ["En quelle année la RDC a-t-elle obtenu son indépendance ?", 'ELEMENTAIRE',
     'La RDC (alors Congo-Léopoldville) a obtenu son indépendance de la Belgique le 30 juin 1960.',
     [['A','1960',1],['B','1962',0],['C','1965',0],['D','1958',0]]],

    ["Qui est le premier président de la RDC après l'indépendance ?", 'INTERMEDIAIRE',
     'Joseph Kasa-Vubu fut le premier président de la République du Congo dès le 30 juin 1960.',
     [['A','Joseph Kasa-Vubu',1],['B','Patrice Lumumba',0],['C','Mobutu Sese Seko',0],['D','Laurent-Désiré Kabila',0]]],

    ["Quel minéral stratégique est principalement extrait au Katanga (Lualaba) ?", 'INTERMEDIAIRE',
     'Le cobalt et le cuivre sont les principaux minerais du Katanga. La RDC fournit plus de 70% du cobalt mondial.',
     [['A','Cobalt et cuivre',1],['B','Diamant et or',0],['C','Bauxite et fer',0],['D','Uranium et lithium',0]]],

    ["Quel est le nom de l'union africaine fondée en 2002 qui a remplacé l'OUA ?", 'INTERMEDIAIRE',
     'L\'Union Africaine (UA) a été créée en 2002 à Durban (Afrique du Sud), remplaçant l\'Organisation de l\'Unité Africaine.',
     [['A','Union Africaine (UA)',1],['B','UEMOA',0],['C','CEMAC',0],['D','NEPAD',0]]],

    ["Quel pays africain possède la plus grande économie du continent (PIB) ?", 'INTERMEDIAIRE',
     'Le Nigeria est la plus grande économie d\'Afrique devant l\'�?gypte et l\'Afrique du Sud.',
     [['A','Nigeria',1],['B','Afrique du Sud',0],['C','�?gypte',0],['D','�?thiopie',0]]],

    // �"?�"? Géographie mondiale �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
    ["Quel est le pays le plus peuplé du monde ?", 'ELEMENTAIRE',
     'L\'Inde a dépassé la Chine en 2023 pour devenir le pays le plus peuplé avec plus de 1,4 milliard d\'habitants.',
     [['A','Inde',1],['B','Chine',0],['C','�?tats-Unis',0],['D','Indonésie',0]]],

    ["Quel est le plus grand pays du monde en superficie ?", 'ELEMENTAIRE',
     'La Russie est le plus grand pays du monde avec environ 17 millions de km².',
     [['A','Russie',1],['B','Canada',0],['C','�?tats-Unis',0],['D','Chine',0]]],

    ["Quelle est la capitale du Kenya ?", 'ELEMENTAIRE',
     'Nairobi est la capitale et la plus grande ville du Kenya.',
     [['A','Nairobi',1],['B','Mombasa',0],['C','Kampala',0],['D','Dar es Salam',0]]],

    ["Quel océan borde la côte ouest de l'Afrique ?", 'ELEMENTAIRE',
     'L\'océan Atlantique borde la côte ouest de l\'Afrique, tandis que l\'océan Indien borde la côte est.',
     [['A','Océan Atlantique',1],['B','Océan Indien',0],['C','Océan Pacifique',0],['D','Mer Méditerranée',0]]],

    ["Combien de pays composent le continent africain ?", 'INTERMEDIAIRE',
     'L\'Afrique compte 54 �?tats souverains reconnus par l\'Union Africaine.',
     [['A','54',1],['B','48',0],['C','52',0],['D','57',0]]],

    ["Quel est le plus long fleuve du monde ?", 'ELEMENTAIRE',
     'Le Nil, avec environ 6 650 km, est généralement considéré comme le plus long fleuve du monde.',
     [['A','Le Nil',1],['B','L\'Amazone',0],['C','Le Congo',0],['D','Le Mississippi',0]]],

    ["Quelle chaîne de montagnes sépare l'Europe de l'Asie ?", 'INTERMEDIAIRE',
     'Les monts Oural (et la rivière Oural) forment la frontière conventionnelle entre l\'Europe et l\'Asie.',
     [['A','Les Oural',1],['B','Les Alpes',0],['C','Le Caucase',0],['D','Les Carpates',0]]],

    ["Quelle est la plus haute montagne du monde ?", 'ELEMENTAIRE',
     'L\'Everest (8 849 m) est la plus haute montagne du monde, à la frontière entre le Népal et la Chine.',
     [['A','L\'Everest',1],['B','Le K2',0],['C','Le Kilimandjaro',0],['D','Le Mont Blanc',0]]],

    // �"?�"? Sciences & Technologies �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
    ["Quel gaz est principalement responsable de l'effet de serre ?", 'INTERMEDIAIRE',
     'Le CO�,, (dioxyde de carbone) est le principal gaz à effet de serre d\'origine humaine.',
     [['A','CO�,, (dioxyde de carbone)',1],['B','O�,f (ozone)',0],['C','N�,, (azote)',0],['D','H�,, (hydrogène)',0]]],

    ["Qu'est-ce que l'intelligence artificielle (IA) ?", 'ELEMENTAIRE',
     'L\'IA est la simulation de processus d\'intelligence humaine par des machines, notamment via l\'apprentissage automatique.',
     [['A','La simulation de l\'intelligence humaine par des machines',1],['B','Un type de robot physique',0],['C','Un système de communication satellite',0],['D','Un logiciel antivirus',0]]],

    ["Quelle entreprise a développé le modèle d'IA ChatGPT ?", 'INTERMEDIAIRE',
     'ChatGPT est développé par OpenAI, une société américaine fondée en 2015.',
     [['A','OpenAI',1],['B','Google',0],['C','Meta',0],['D','Microsoft',0]]],

    ["Qu'est-ce que la 5G ?", 'INTERMEDIAIRE',
     'La 5G est la 5ème génération de réseaux mobiles, offrant des vitesses bien supérieures à la 4G.',
     [['A','La 5ème génération de réseaux mobiles',1],['B','Un nouveau type de satellite',0],['C','Un standard Wi-Fi',0],['D','Une technologie Bluetooth améliorée',0]]],

    ["Quel pays a envoyé le premier homme sur la Lune ?", 'ELEMENTAIRE',
     'Les �?tats-Unis ont envoyé Neil Armstrong et Buzz Aldrin sur la Lune le 20 juillet 1969 (mission Apollo 11).',
     [['A','�?tats-Unis',1],['B','URSS',0],['C','France',0],['D','Chine',0]]],

    ["Qu'est-ce qu'une énergie renouvelable ?", 'ELEMENTAIRE',
     'Une énergie renouvelable provient de sources naturelles qui se reconstituent rapidement (soleil, vent, eau).',
     [['A','Une énergie issue de sources naturelles inépuisables',1],['B','Une énergie issue du pétrole',0],['C','Une énergie nucléaire',0],['D','Une énergie issue du charbon',0]]],

    ["Quelle planète est surnommée la 'planète rouge' ?", 'ELEMENTAIRE',
     'Mars est surnommée la planète rouge en raison de l\'oxyde de fer (rouille) présent à sa surface.',
     [['A','Mars',1],['B','Jupiter',0],['C','Vénus',0],['D','Saturne',0]]],

    // �"?�"? �?conomie & Développement �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
    ["Que signifie PIB ?", 'ELEMENTAIRE',
     'PIB signifie Produit Intérieur Brut. C\'est la valeur totale de tous les biens et services produits dans un pays.',
     [['A','Produit Intérieur Brut',1],['B','Profit Industriel Brut',0],['C','Production Interne de Base',0],['D','Plan d\'Investissement Budgétaire',0]]],

    ["Quelle institution internationale accorde des prêts aux pays en développement ?", 'INTERMEDIAIRE',
     'La Banque Mondiale et le FMI (Fonds Monétaire International) sont les principales institutions de financement du développement.',
     [['A','La Banque Mondiale',1],['B','La Banque Centrale Européenne',0],['C','La Réserve Fédérale américaine',0],['D','La Banque d\'Afrique',0]]],

    ["Qu'est-ce que le commerce équitable ?", 'INTERMEDIAIRE',
     'Le commerce équitable garantit des prix justes et des conditions de travail décentes aux producteurs des pays en développement.',
     [['A','Un système garantissant des prix justes aux petits producteurs',1],['B','L\'échange de biens sans taxes',0],['C','Le commerce entre pays riches uniquement',0],['D','Le troc entre pays africains',0]]],

    ["Quel est l'objectif principal du développement durable ?", 'INTERMEDIAIRE',
     'Le développement durable vise à répondre aux besoins du présent sans compromettre la capacité des générations futures.',
     [['A','Répondre aux besoins présents sans compromettre les générations futures',1],['B','Maximiser la croissance économique',0],['C','Réduire la population mondiale',0],['D','Supprimer l\'industrie polluante',0]]],

    ["Combien d'Objectifs de Développement Durable (ODD) l'ONU a-t-elle définis pour 2030 ?", 'INTERMEDIAIRE',
     'L\'Agenda 2030 de l\'ONU comprend 17 Objectifs de Développement Durable (ODD) adoptés en 2015.',
     [['A','17',1],['B','10',0],['C','8',0],['D','20',0]]],

    // �"?�"? Santé & Environnement �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
    ["Quel vaccin a été développé en un temps record pour combattre la COVID-19 ?", 'INTERMEDIAIRE',
     'Plusieurs vaccins ont été développés fin 2020, notamment Pfizer-BioNTech, Moderna et AstraZeneca.',
     [['A','Pfizer-BioNTech (ARNm)',1],['B','BCG',0],['C','Variole',0],['D','Polio oral',0]]],

    ["Quelle maladie est causée par le moustique anophèle en Afrique ?", 'ELEMENTAIRE',
     'Le paludisme (malaria) est transmis par la piqûre du moustique anophèle femelle infecté par le parasite Plasmodium.',
     [['A','Le paludisme (malaria)',1],['B','La dengue',0],['C','La typhoïde',0],['D','Le choléra',0]]],

    ["Qu'est-ce que le réchauffement climatique ?", 'ELEMENTAIRE',
     'Le réchauffement climatique désigne l\'augmentation progressive de la température moyenne de la Terre due aux émissions de gaz à effet de serre.',
     [['A','L\'augmentation de la température moyenne de la Terre',1],['B','Une variation naturelle des saisons',0],['C','Le refroidissement des pôles',0],['D','Une réaction chimique dans l\'atmosphère',0]]],

    ["Quel accord international vise à limiter le réchauffement climatique à 1,5°C ?", 'INTERMEDIAIRE',
     'L\'Accord de Paris (COP21, 2015) engage les pays à limiter le réchauffement à bien en dessous de 2°C.',
     [['A','L\'Accord de Paris',1],['B','Le Protocole de Kyoto',0],['C','Le Traité de Montréal',0],['D','La Convention de Rio',0]]],

    ["Quelle est la principale cause de déforestation en Afrique centrale ?", 'INTERMEDIAIRE',
     'L\'agriculture sur brûlis, l\'exploitation forestière et l\'extraction minière sont les principales causes de déforestation.',
     [['A','L\'agriculture sur brûlis et l\'exploitation forestière',1],['B','Le tourisme de masse',0],['C','Les tremblements de terre',0],['D','La sécheresse',0]]],

    // �"?�"? Institutions & Droit �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
    ["Quelle organisation mondiale veille à la paix et la sécurité internationale ?", 'ELEMENTAIRE',
     'L\'ONU (Organisation des Nations Unies), fondée en 1945, est la principale organisation internationale pour la paix.',
     [['A','L\'ONU (Organisation des Nations Unies)',1],['B','L\'OTAN',0],['C','L\'Union Européenne',0],['D','La Croix-Rouge',0]]],

    ["Combien de membres permanents siègent au Conseil de Sécurité de l'ONU ?", 'INTERMEDIAIRE',
     'Le Conseil de Sécurité de l\'ONU compte 5 membres permanents : USA, Russie, Chine, France, Royaume-Uni.',
     [['A','5',1],['B','10',0],['C','15',0],['D','7',0]]],

    ["Qu'est-ce que la Déclaration Universelle des Droits de l'Homme ?", 'ELEMENTAIRE',
     'Adoptée par l\'ONU en 1948, la DUDH proclame les droits fondamentaux inaliénables de tout être humain.',
     [['A','Un texte proclamant les droits fondamentaux de tout être humain',1],['B','Un traité militaire entre nations',0],['C','La constitution de l\'ONU',0],['D','Un accord commercial international',0]]],

    ["En quelle année la Constitution de la RDC actuellement en vigueur a-t-elle été adoptée ?", 'INTERMEDIAIRE',
     'La Constitution de la RDC a été adoptée par référendum et promulguée le 18 février 2006.',
     [['A','2006',1],['B','1997',0],['C','2003',0],['D','2010',0]]],

    ["Quel est le rôle de la Cour Pénale Internationale (CPI) ?", 'AVANCE',
     'La CPI juge les individus accusés de génocide, crimes de guerre et crimes contre l\'humanité.',
     [['A','Juger les individus pour crimes de guerre, génocide et crimes contre l\'humanité',1],['B','Régler les conflits commerciaux entre �?tats',0],['C','Superviser les élections mondiales',0],['D','Protéger les droits d\'auteur internationaux',0]]],

    // �"?�"? Culture & Société �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
    ["Quelle est la langue la plus parlée dans le monde ?", 'ELEMENTAIRE',
     'Le mandarin (chinois) est la langue maternelle la plus parlée. L\'anglais est la plus utilisée comme langue internationale.',
     [['A','Le mandarin (chinois)',1],['B','L\'anglais',0],['C','L\'espagnol',0],['D','Le français',0]]],

    ["Combien de langues nationales la RDC reconnaît-elle officiellement ?", 'INTERMEDIAIRE',
     'La RDC reconnaît 4 langues nationales : kikongo, lingala, swahili et tshiluba, en plus du français (officiel).',
     [['A','4',1],['B','2',0],['C','6',0],['D','1',0]]],

    ["Quelle est la religion la plus pratiquée en RDC ?", 'ELEMENTAIRE',
     'Le christianisme est pratiqué par environ 95% de la population congolaise.',
     [['A','Le christianisme',1],['B','L\'islam',0],['C','L\'animisme',0],['D','Le bouddhisme',0]]],

    ["Quel sport est le plus populaire en Afrique subsaharienne ?", 'ELEMENTAIRE',
     'Le football (soccer) est de loin le sport le plus populaire en Afrique subsaharienne.',
     [['A','Le football',1],['B','L\'athlétisme',0],['C','Le basketball',0],['D','Le volleyball',0]]],

    ["En quelle année a eu lieu la première Coupe du Monde de football organisée en Afrique ?", 'INTERMEDIAIRE',
     'L\'Afrique du Sud a organisé la première Coupe du Monde de football sur le continent africain en 2010.',
     [['A','2010',1],['B','2006',0],['C','2014',0],['D','2018',0]]],

    // �"?�"? �?ducation & Jeunesse �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
    ["Qu'est-ce que l'ENAFEP en RDC ?", 'ELEMENTAIRE',
     'L\'ENAFEP (Examen National de Fin d\'�?tudes Primaires) est l\'examen de fin du cycle primaire en RDC.',
     [['A','L\'examen de fin d\'études primaires en RDC',1],['B','Un diplôme universitaire',0],['C','Un examen d\'entrée à l\'université',0],['D','Un certificat de formation professionnelle',0]]],

    ["Qu'est-ce que le TENAFEP / TENASOSP en RDC ?", 'ELEMENTAIRE',
     'Le TENASOSP (Test National de Sélection et d\'Orientation Scolaire du Post-Primaire) oriente les élèves après le primaire.',
     [['A','Un test national d\'orientation scolaire après le primaire',1],['B','Un examen universitaire',0],['C','Un concours national de mathématiques',0],['D','Un brevet technique',0]]],

    ["Quel est l'Examen d'�?tat en RDC ?", 'ELEMENTAIRE',
     'L\'Examen d\'�?tat est l\'examen national de fin d\'humanités (secondaire) qui sanctionne le diplôme d\'�?tat en RDC.',
     [['A','L\'examen de fin d\'humanités (baccalauréat congolais)',1],['B','Un concours d\'entrée à l\'école primaire',0],['C','Un examen de sélection universitaire',0],['D','Une évaluation de fin de primaire',0]]],

    ["Combien d'années dure le cycle secondaire en RDC ?", 'ELEMENTAIRE',
     'Le cycle secondaire en RDC dure 6 ans (2 ans de cycle d\'orientation + 4 ans de cycle long).',
     [['A','6 ans',1],['B','4 ans',0],['C','5 ans',0],['D','7 ans',0]]],

    ["Quel organisme gère le système éducatif en RDC ?", 'INTERMEDIAIRE',
     'Le Ministère de l\'Enseignement Primaire, Secondaire et Technique (EPST) gère l\'éducation de base en RDC.',
     [['A','Le Ministère de l\'EPST',1],['B','Le Ministère des Finances',0],['C','L\'UNESCO seule',0],['D','Le Gouvernorat Provincial',0]]],

    ["Qu'est-ce que l'UNESCO ?", 'INTERMEDIAIRE',
     'L\'UNESCO est l\'Organisation des Nations Unies pour l\'�?ducation, la Science et la Culture, fondée en 1945.',
     [['A','L\'Organisation des Nations Unies pour l\'�?ducation, la Science et la Culture',1],['B','Un fonds international pour les enfants',0],['C','Une banque de développement africain',0],['D','Un programme alimentaire mondial',0]]],
];

$pdo->beginTransaction();
try {
    $added = 0;
    foreach ($questions as [$enonce, $diff, $expl, $opts]) {
        addQ($pdo, $stQ, $stO, $matId, $enonce, $diff, $expl, $opts);
        $added++;
    }
    $pdo->commit();
    echo "\n�o" $added questions ajoutées avec succès !\n";
    $total = $pdo->query("SELECT COUNT(*) FROM question_bank")->fetchColumn();
    echo "Total questions en base : $total\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "ERREUR : " . $e->getMessage() . "\n";
}

