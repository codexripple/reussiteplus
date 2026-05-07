<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_admin(); // Script restreint aux admins
/**
 * R�?USSITE+ �?" Ajout d'explications aux questions QCM
 * Script non-destructif : met à jour les explications sans toucher aux données existantes.
 * Accessible uniquement depuis localhost.
 */
if (php_sapi_name() !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'])) {
    http_response_code(403); die('Accès refusé.');
}
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/plain; charset=utf-8');

$pdo = db();
$updated = 0;

function add_expl(PDO $pdo, string $enonce, string $lettre_correcte, string $explication): void {
    global $updated;
    // Trouver l'option correcte par enonce de la question et lettre
    $stmt = $pdo->prepare(
        "UPDATE question_options qo
         INNER JOIN question_bank qb ON qo.question_id = qb.id
         SET qo.explication = ?
         WHERE qb.enonce = ? AND qo.lettre = ? AND qo.est_correcte = 1"
    );
    $stmt->execute([$explication, $enonce, $lettre_correcte]);
    if ($stmt->rowCount() > 0) $updated++;
}

echo "[" . date('H:i:s') . "] �Y"� Ajout des explications aux questions...\n\n";

/* �.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.�
   MATH�?MATIQUES
�.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.� */
echo "[" . date('H:i:s') . "] �Y"� Mathématiques...\n";

add_expl($pdo, 'Quel est le résultat de 8 �- 7 ?', 'A',
    '8 �- 7 = 56. Astuce : 8�-7 = 8�-5 + 8�-2 = 40 + 16 = 56. �? retenir par c�"ur comme table de multiplication.');

add_expl($pdo, 'Quelle est la valeur de x dans : x + 12 = 20 ?', 'A',
    'Pour isoler x, on soustrait 12 des deux membres : x = 20 �^' 12 = 8. Vérification : 8 + 12 = 20 �o"');

add_expl($pdo, 'Combien vaut 15% de 200 ?', 'A',
    '15% = 15/100. Calcul : (15 �- 200) / 100 = 3 000 / 100 = 30. Ou encore : 10% de 200 = 20, plus 5% = 10, total 30.');

add_expl($pdo, 'Quel est le PGCD de 12 et 18 ?', 'A',
    'Diviseurs de 12 : 1, 2, 3, 4, 6, 12. Diviseurs de 18 : 1, 2, 3, 6, 9, 18. Le plus grand diviseur commun est 6.');

add_expl($pdo, 'Calculer : 3² + 4²', 'A',
    '3² = 9 et 4² = 16. Somme = 25 = 5². C\'est le célèbre triplet pythagoricien 3-4-5 : dans un triangle rectangle de côtés 3 et 4, l\'hypoténuse vaut 5.');

add_expl($pdo, 'Résoudre : 2x - 5 = 11', 'A',
    '2x = 11 + 5 = 16, donc x = 16/2 = 8. Vérification : 2�-8 �^' 5 = 16 �^' 5 = 11 �o"');

add_expl($pdo, 'Quel est le résultat de 15² - 10² ?', 'A',
    'Identité remarquable : a² �^' b² = (a�^'b)(a+b). Ici : (15�^'10)(15+10) = 5 �- 25 = 125. Plus rapide que calculer 225 �^' 100.');

add_expl($pdo, 'Si f(x) = 3x² + 2x - 1, calculer f(2).', 'A',
    'f(2) = 3�-(2²) + 2�-2 �^' 1 = 3�-4 + 4 �^' 1 = 12 + 4 �^' 1 = 15. On remplace x par 2 dans la formule.');

add_expl($pdo, 'Calculer la valeur de sin(30°).', 'A',
    'sin(30°) = 1/2 = 0,5. Valeur fondamentale à mémoriser. Dans un triangle équilatéral coupé en deux, l\'angle de 30° a le demi-côté opposé à l\'hypoténuse.');

add_expl($pdo, 'Résoudre l\'équation du second degré : x² - 5x + 6 = 0', 'A',
    'Discriminant �" = b²�^'4ac = 25�^'24 = 1. Racines : x = (5±1)/2. Donc x�,� = 3 et x�,, = 2. Vérification : (x�^'2)(x�^'3) = 0 �o"');

add_expl($pdo, 'Dans un triangle rectangle, si sin(A) = 3/5, quelle est la valeur de cos(A) ?', 'A',
    'Relation fondamentale : sin²(A) + cos²(A) = 1. Donc cos²(A) = 1 �^' (3/5)² = 1 �^' 9/25 = 16/25. cos(A) = 4/5 (positif car A est un angle aigu).');

add_expl($pdo, 'Quelle est la dérivée de f(x) = 2x³ - 4x + 1 ?', 'A',
    'On dérive terme par terme : (2x³)\' = 6x², (�^'4x)\' = �^'4, (1)\' = 0. Donc f\'(x) = 6x² �^' 4.');

add_expl($pdo, 'Calculer la dérivée de f(x) = x³ - 3x.', 'A',
    'f\'(x) = 3x² �^' 3. Règle : (xⁿ)\' = n·xⁿ⁻¹. Donc (x³)\' = 3x² et (�^'3x)\' = �^'3.');

add_expl($pdo, 'Calculer la limite de (x² - 4)/(x - 2) quand x �?' 2.', 'A',
    'Forme indéterminée 0/0. On factorise : x²�^'4 = (x�^'2)(x+2). Donc (x²�^'4)/(x�^'2) = x+2 �?' limite = 2+2 = 4.');

add_expl($pdo, '�^��,?¹ x² dx est égal à :', 'A',
    'Primitive de x² est x³/3. On évalue : [x³/3]�,?¹ = 1³/3 �^' 0³/3 = 1/3 �^' 0 = 1/3.');

add_expl($pdo, 'La somme de la série géométrique 1 + 1/2 + 1/4 + ... converge vers :', 'A',
    'Série géométrique de premier terme a=1 et raison r=1/2. Somme infinie = a/(1�^'r) = 1/(1�^'1/2) = 1/(1/2) = 2.');

add_expl($pdo, 'Résoudre dans �"� : |2x - 3| < 5', 'A',
    '|2x�^'3| < 5 �Y� �^'5 < 2x�^'3 < 5 �Y� �^'2 < 2x < 8 �Y� �^'1 < x < 4.');

add_expl($pdo, 'Quel est le résultat de �^s144 ?', 'A',
    '�^s144 = 12 car 12² = 144. �? connaître : �^s1=1, �^s4=2, �^s9=3, �^s16=4, �^s25=5, �^s36=6, �^s49=7, �^s64=8, �^s81=9, �^s100=10, �^s121=11, �^s144=12.');

add_expl($pdo, 'Quelle est l\'image de x = �^'2 par f(x) = x² �^' 3x + 1 ?', 'A',
    'f(�^'2) = (�^'2)² �^' 3�-(�^'2) + 1 = 4 + 6 + 1 = 11. Attention : (�^'2)² = 4 (positif !)');

add_expl($pdo, 'Exprimer 60° en radians.', 'A',
    '180° = �? radians. Donc 60° = 60�-(�?/180) = �?/3. Règle : multiplier les degrés par �?/180.');

add_expl($pdo, 'Calculer : 5! (5 factorielle)', 'A',
    '5! = 5 �- 4 �- 3 �- 2 �- 1 = 120. La factorielle multiplie tous les entiers de 1 à n.');

add_expl($pdo, 'La probabilité de tirer un as d\'un jeu de 52 cartes est :', 'A',
    'Il y a 4 as dans 52 cartes. P = 4/52 = 1/13 �?^ 0,077. On simplifie 4/52 par 4.');

add_expl($pdo, 'Résoudre : x² = 25', 'A',
    'x² = 25 a DEUX solutions : x = +5 et x = �^'5 car (�^'5)² = 25 aussi. On écrit : x = ±5.');

add_expl($pdo, 'Résoudre : x² - 5x + 6 = 0', 'A',
    '�" = 25 �^' 24 = 1. x = (5±1)/2 �?' x=3 ou x=2. Ou par factorisation : (x�^'2)(x�^'3)=0.');

add_expl($pdo, 'Quelle est la médiane d\'une série : 4, 7, 2, 9, 1, 5 ?', 'A',
    'On trie la série : 1, 2, 4, 5, 7, 9. Nombre pair d\'éléments (6), donc médiane = moyenne des deux centraux = (4+5)/2 = 4,5.');

/* �.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.�
   FRAN�?AIS
�.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.� */
echo "[" . date('H:i:s') . "] �Y"� Français...\n";

add_expl($pdo, 'Quel est le pluriel de "�"il" en français ?', 'A',
    '"yeux" est un pluriel irrégulier d\'origine latine. "�'ils" n\'est utilisé que dans des termes composés comme "�"ils-de-b�"uf" (fenêtres rondes).');

add_expl($pdo, 'Conjuguer "aller" au présent de l\'indicatif, 1ère personne du singulier :', 'A',
    '"Aller" est un verbe très irrégulier : je vais, tu vas, il va, nous allons, vous allez, ils vont. Sa conjugaison vient de trois radicaux latins différents.');

add_expl($pdo, 'Quel est l\'antonyme de "rapide" ?', 'A',
    'L\'antonyme (contraire) de "rapide" est "lent". "Vite" est un adverbe synonyme de "rapidement", pas un antonyme.');

add_expl($pdo, 'Identifier la nature du mot souligné dans : "Il court vite."', 'A',
    '"Vite" modifie le verbe "court" en indiquant la manière : c\'est donc un adverbe de manière. Il est invariable.');

add_expl($pdo, 'Quel est le synonyme de "perspicace" ?', 'A',
    '"Perspicace" (du latin perspicere = voir clairement) signifie qui voit et comprend rapidement. "Clairvoyant" en est le synonyme le plus proche.');

add_expl($pdo, '"Le c�"ur de Pierre est de pierre" �?" quelle figure de style ?', 'A',
    'L\'antanaclase répète un même mot avec deux sens différents : "c�"ur" (organe/siège des émotions) et "pierre" (matériau/dureté). C\'est un jeu sur la polysémie.');

add_expl($pdo, '"La lune était sereine et jouait sur les flots" (Hugo) �?" figure de style ?', 'A',
    'La personnification attribue des qualités humaines à un objet inanimé. Ici, la lune "jouait" comme le ferait une personne.');

add_expl($pdo, 'Quel est le mode verbal de "Bien que tu sois parti tôt" ?', 'A',
    '"Bien que" impose le subjonctif. "Bien que tu sois parti" : "sois parti" = subjonctif passé du verbe "être". Les conjonctions de concession imposent le subjonctif.');

add_expl($pdo, 'Identifier le COD dans : "Marie offre une fleur à son père."', 'A',
    'Le COD répond à la question "offre quoi ?". Réponse : "une fleur". "�? son père" est le COI (complément d\'objet indirect).');

add_expl($pdo, 'Quel est le féminin de "acteur" ?', 'A',
    '"Acteur" �?' "actrice". Les noms en -eur font généralement leur féminin en -rice (directeur/directrice, acteur/actrice) ou -euse (chanteur/chanteuse).');

add_expl($pdo, 'Dans quelle phrase le subjonctif est-il obligatoire ?', 'A',
    '"Il faut que" impose systématiquement le subjonctif. "Il faut que tu viennes" : "viennes" = subjonctif présent de "venir".');

add_expl($pdo, 'Quel est le mode verbal de "Bien que tu sois parti tôt" ?', 'A',
    '"Bien que" impose toujours le subjonctif : c\'est une conjonction de concession. "sois parti" = subjonctif passé.');

add_expl($pdo, '"Sa voix était douce comme du miel." La figure de style est :', 'A',
    'La comparaison utilise un outil comparatif ("comme", "tel que", "pareil à"...). Ici "comme" compare la douceur de la voix au miel. Si on disait "Sa voix était du miel", ce serait une métaphore.');

add_expl($pdo, '"Il a une mémoire d\'éléphant." La figure de style est :', 'A',
    'La métaphore compare sans outil de comparaison ("comme", "tel"). "Mémoire d\'éléphant" = grande mémoire, sans le mot "comme". Si c\'était "une mémoire comme un éléphant", ce serait une comparaison.');

add_expl($pdo, 'Quel est le passé simple de "venir" à la 3e pers. sing. ?', 'A',
    '"Venir" au passé simple : je vins, tu vins, il vint, nous vînmes, vous vîntes, ils vinrent. "Vint" est la forme correcte pour "il/elle".');

/* �.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.�
   CHIMIE
�.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.� */
echo "[" . date('H:i:s') . "] �s-️ Chimie...\n";

add_expl($pdo, 'Quelle est la masse molaire de l\'eau (H�,,O) ? (H=1, O=16)', 'A',
    'H�,,O contient 2 atomes d\'hydrogène (2�-1=2) et 1 atome d\'oxygène (1�-16=16). Masse molaire = 2 + 16 = 18 g/mol.');

add_expl($pdo, 'Quelle est la configuration électronique du Sodium (Z=11) ?', 'A',
    'Le sodium a 11 électrons répartis sur 3 couches : 2 sur la 1ère, 8 sur la 2ème, 1 sur la 3ème �?' 2,8,1. Cet unique électron de valence explique sa grande réactivité.');

add_expl($pdo, 'La réaction NaOH + HCl �?' NaCl + H�,,O est une réaction de :', 'A',
    'La neutralisation est la réaction entre un acide (HCl) et une base (NaOH) pour former un sel (NaCl) et de l\'eau. Le pH de la solution résultante est proche de 7.');

add_expl($pdo, 'Quel est le symbole de l\'argent ?', 'A',
    '"Ag" vient du latin "argentum". L\'argent est un métal précieux blanc brillant, conducteur d\'électricité, utilisé en bijouterie et en photographie.');

add_expl($pdo, 'La liaison covalente est formée par :', 'A',
    'La liaison covalente résulte du partage d\'une ou plusieurs paires d\'électrons entre deux atomes. Ex : dans H�,,, les deux hydrogènes partagent 2 électrons.');

add_expl($pdo, 'La formule moléculaire du glucose est :', 'A',
    'Le glucose (C�,?H�,��,,O�,?) est le principal sucre énergétique des cellules. Son nom vient du grec "glukus" (doux). Le saccharose (sucre de table) est C�,��,,H�,,�,,O�,��,�.');

add_expl($pdo, 'Quel est le produit de la combustion complète du méthane CH�," ?', 'A',
    'CH�," + 2O�,, �?' CO�,, + 2H�,,O. Combustion complète = tout le carbone devient CO�,, et tout l\'hydrogène devient H�,,O. Incomplète : CO (monoxyde de carbone) se forme aussi.');

add_expl($pdo, 'La concentration molaire C s\'exprime en :', 'A',
    'La concentration molaire C = n/V, où n = nombre de moles (mol) et V = volume en litres (L). Unité : mol/L ou mol·L⁻¹. Exemple : 1 mol/L = 1 M.');

add_expl($pdo, 'Quel est le symbole du fer ?', 'A',
    '"Fe" vient du latin "ferrum". Le fer est le métal le plus abondant sur Terre (noyau terrestre). Il est essentiel à la production d\'acier et dans l\'hémoglobine du sang.');

add_expl($pdo, 'Une solution de pH = 3 est :', 'A',
    'L\'échelle de pH va de 0 à 14. pH < 7 = acide, pH = 7 = neutre, pH > 7 = basique (alcalin). pH = 3 est très acide (ex: vinaigre pH�?^3, jus de citron pH�?^2).');

add_expl($pdo, 'La formule de l\'ammoniac est :', 'A',
    'L\'ammoniac NH�,f est une molécule composée d\'1 azote et de 3 hydrogènes. C\'est un gaz piquant soluble dans l\'eau, utilisé comme engrais et produit chimique industriel.');

add_expl($pdo, 'Quel est le symbole du sodium ?', 'A',
    '"Na" vient du latin "natrium". Le sodium est un métal alcalin mou, très réactif avec l\'eau (Na + H�,,O �?' NaOH + H�,,). Présent dans le sel de cuisine NaCl.');

add_expl($pdo, 'La formule de l\'acide nitrique est :', 'A',
    'L\'acide nitrique HNO�,f est un acide fort oxydant. Il est utilisé dans la fabrication d\'engrais (nitrate d\'ammonium) et d\'explosifs. �? distinguer de HNO�,, (acide nitreux, acide faible).');

add_expl($pdo, 'Quel est le symbole de l\'aluminium ?', 'A',
    '"Al" pour aluminium. C\'est le métal le plus abondant dans la croûte terrestre. Léger, résistant à la corrosion, il est utilisé dans l\'aéronautique, les emballages alimentaires et la construction.');

add_expl($pdo, 'Le cuivre a pour symbole chimique :', 'A',
    '"Cu" vient du latin "cuprum" (île de Chypre, ancienne source de cuivre). Le cuivre est excellent conducteur d\'électricité et de chaleur. Il est utilisé dans les fils électriques.');

add_expl($pdo, 'La loi de conservation de la matière (Lavoisier) dit que :', 'A',
    '"Rien ne se perd, rien ne se crée, tout se transforme" (Lavoisier, 1789). La masse totale des réactifs = masse totale des produits. Exemple : 2H�,, + O�,, �?' 2H�,,O (4g + 32g = 36g).');

/* �.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.�
   PHYSIQUE
�.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.� */
echo "[" . date('H:i:s') . "] �s� Physique...\n";

add_expl($pdo, 'Quelle est l\'unité de l\'énergie dans le SI ?', 'A',
    'Le Joule (J) est l\'unité SI d\'énergie. 1 J = 1 N·m = 1 kg·m²/s². Le watt est une unité de PUISSANCE (J/s), le newton est une unité de FORCE.');

add_expl($pdo, 'Un objet de masse 2 kg tombe librement. Son poids vaut (g = 10 m/s²) :', 'A',
    'Poids P = m �- g = 2 �- 10 = 20 N. Le poids est une FORCE (en Newtons), différent de la masse (en kg). Sur la Lune, g �?^ 1,6 m/s², donc le poids changerait mais pas la masse.');

add_expl($pdo, 'La puissance électrique s\'exprime par :', 'A',
    'P = U �- I (puissance = tension �- intensité). En Watts. On peut aussi écrire P = R�-I² ou P = U²/R. Exemple : une ampoule 60W alimentée en 220V consomme I = 60/220 �?^ 0,27 A.');

add_expl($pdo, 'L\'unité du courant électrique est :', 'A',
    'L\'Ampère (A) mesure l\'intensité du courant électrique. 1 Ampère = passage de 1 Coulomb de charge par seconde. Nommé d\'après André-Marie Ampère, physicien français.');

add_expl($pdo, 'Deux résistances R�,� = 4 Ω et R�,, = 6 Ω montées en série. La résistance totale est :', 'A',
    'En série : Rtotal = R�,� + R�,, = 4 + 6 = 10 Ω. En série, les résistances s\'additionnent. En parallèle, la résistance totale serait : 1/Rt = 1/4 + 1/6 �?' Rt = 2,4 Ω.');

add_expl($pdo, 'Deux résistances R�,� = 4 Ω et R�,, = 4 Ω montées en parallèle. La résistance totale est :', 'A',
    'En parallèle : 1/Rt = 1/R�,� + 1/R�,, = 1/4 + 1/4 = 2/4 = 1/2. Donc Rt = 2 Ω. La résistance totale en parallèle est toujours inférieure à la plus petite résistance.');

add_expl($pdo, 'Le travail d\'une force F = 10 N sur un déplacement d = 5 m (angle 0°) vaut :', 'A',
    'W = F �- d �- cos(θ) = 10 �- 5 �- cos(0°) = 10 �- 5 �- 1 = 50 J. Quand la force et le déplacement sont dans le même sens (θ=0°), cos(0°)=1.');

add_expl($pdo, 'Quelle propriété de la lumière explique l\'arc-en-ciel ?', 'A',
    'La dispersion : la lumière blanche se décompose en ses couleurs (rouge, orange, jaune, vert, bleu, indigo, violet) car chaque longueur d\'onde est réfractée différemment dans les gouttelettes d\'eau.');

add_expl($pdo, 'Quel est le phénomène qui permet aux fibres optiques de transporter la lumière ?', 'A',
    'La réflexion totale interne : quand la lumière passe d\'un milieu dense vers un milieu moins dense avec un angle supérieur à l\'angle critique, elle est totalement réfléchie sans sortir de la fibre.');

add_expl($pdo, 'Quelle est l\'unité de la fréquence ?', 'A',
    'Le Hertz (Hz) = nombre d\'oscillations par seconde. 1 Hz = 1 cycle/seconde. Nommé d\'après Heinrich Hertz. Exemples : courant électrique alternatif = 50 Hz, audition humaine 20 Hz�?"20 000 Hz.');

add_expl($pdo, 'La loi de Newton de gravitation universelle : F = G·M·m/r². G est :', 'A',
    'G est la constante gravitationnelle universelle : G �?^ 6,67 �- 10⁻¹¹ N·m²/kg². Elle est la même partout dans l\'univers. Mesurée par Henry Cavendish en 1798.');

add_expl($pdo, 'Un objet lancé à 20 m/s vers le haut : combien de secondes met-il à s\'arrêter (g=10)?', 'A',
    'Au sommet, v = 0. On utilise v = v�,? �^' g·t �?' 0 = 20 �^' 10t �?' t = 2 s. La gravité décélère l\'objet de 10 m/s chaque seconde.');

add_expl($pdo, 'Le principe d\'inertie (1ère loi de Newton) stipule :', 'A',
    'Tout corps persévère dans son état de repos ou de mouvement rectiligne uniforme tant qu\'aucune force ne s\'y oppose. En l\'absence de frottements, un objet en mouvement continue indéfiniment.');

/* �.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.�
   BIOLOGIE
�.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.� */
echo "[" . date('H:i:s') . "] �Y�� Biologie...\n";

add_expl($pdo, 'Quel est le rôle du pancréas dans la digestion ?', 'A',
    'Le pancréas a deux rôles : (1) exocrine : il sécrète des enzymes digestives (amylase, lipase, protéase) dans le duodénum ; (2) endocrine : il produit l\'insuline et le glucagon pour réguler la glycémie.');

add_expl($pdo, 'Quelle vitamine est synthétisée par la peau sous l\'effet du soleil ?', 'A',
    'La vitamine D est synthétisée dans la peau par les rayons UV du soleil à partir d\'un précurseur dérivé du cholestérol. Elle est essentielle pour l\'absorption du calcium et la santé osseuse.');

add_expl($pdo, 'Les vaisseaux sanguins qui transportent le sang vers le c�"ur sont :', 'A',
    'Les VEINES transportent le sang vers le c�"ur (V comme "vers"). Les ART�^RES transportent le sang du c�"ur vers les organes. Les capillaires font les échanges entre le sang et les tissus.');

add_expl($pdo, 'Le système nerveux central est composé de :', 'A',
    'Le SNC comprend le cerveau et la moelle épinière. Le système nerveux périphérique (SNP) comprend tous les nerfs qui relient le SNC aux organes et muscles du corps.');

add_expl($pdo, 'Quel type de reproduction ne nécessite qu\'un seul parent ?', 'A',
    'La reproduction asexuée (ou végétative) ne nécessite qu\'un seul individu : bouturage, bourgeonnement, fission binaire (bactéries). Elle produit des clones génétiquement identiques au parent.');

add_expl($pdo, 'La respiration cellulaire se résume par :', 'A',
    'Glucose + 6O�,, �?' 6CO�,, + 6H�,,O + ATP. La cellule "brûle" le glucose avec l\'oxygène pour produire de l\'énergie (ATP). C\'est l\'inverse de la photosynthèse.');

add_expl($pdo, 'Quelle est la fonction de la membrane cellulaire ?', 'A',
    'La membrane cellulaire (bicouche phospholipidique) contrôle ce qui entre et sort de la cellule. Elle est semiperméable : laisse passer certaines molécules et en bloque d\'autres.');

add_expl($pdo, 'Le groupe sanguin O est dit "donneur universel" car :', 'A',
    'Le groupe O n\'a pas d\'antigènes A ni B sur ses globules rouges �?' le receveur ne peut pas les rejeter. Attention : le plasma du groupe O contient des anticorps anti-A et anti-B, donc les porteurs O ne peuvent recevoir que du sang O.');

add_expl($pdo, 'Quel est le rôle de l\'ADN dans la cellule ?', 'A',
    'L\'ADN (Acide DésoxyriboNucléique) est la molécule qui stocke l\'information génétique sous forme de séquence de bases (A, T, G, C). Il est transmis lors de la division cellulaire et contrôle la synthèse des protéines.');

add_expl($pdo, 'Les anticorps sont produits par :', 'A',
    'Les lymphocytes B (B pour Bourse de Fabricius chez les oiseaux) produisent les anticorps (immunoglobulines). Les lymphocytes T, eux, coordonnent la réponse immunitaire cellulaire.');

add_expl($pdo, 'Le VIH attaque principalement quel type de cellules ?', 'A',
    'Le VIH (Virus de l\'Immunodéficience Humaine) cible les lymphocytes T CD4+ (aussi appelés T helper). En les détruisant progressivement, le virus affaiblit le système immunitaire jusqu\'au SIDA déclaré.');

add_expl($pdo, 'La vaccination crée une immunité en induisant la production de :', 'A',
    'Le vaccin introduit un antigène (virus atténué, tué ou fragment protéique) �?' le système immunitaire produit des anticorps spécifiques et crée des cellules mémoire �?' réponse rapide si le vrai pathogène arrive.');

add_expl($pdo, 'La sélection naturelle est le mécanisme central de la théorie de :', 'A',
    'Charles Darwin (1859, "De l\'Origine des espèces") a proposé que les individus les mieux adaptés à leur environnement survivent et se reproduisent davantage �?' leurs traits se transmettent aux générations suivantes.');

add_expl($pdo, 'Quelle est la durée moyenne d\'une grossesse humaine ?', 'A',
    'La grossesse humaine dure environ 9 mois calendaires, soit 38 semaines de développement embryonnaire (40 semaines d\'aménorrhée à partir des dernières règles). Elle se divise en 3 trimestres.');

/* �.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.�
   HISTOIRE-G�?OGRAPHIE
�.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.� */
echo "[" . date('H:i:s') . "] �YO� Histoire-Géo...\n";

add_expl($pdo, 'La conférence de Berlin (1884-1885) a abouti à :', 'A',
    'La conférence de Berlin (organisée par Bismarck) a organisé le "partage" de l\'Afrique entre les puissances européennes sans consulter les Africains. Elle a fixé les règles de la colonisation et créé l\'�?tat Indépendant du Congo pour Léopold II de Belgique.');

add_expl($pdo, 'Qui était Patrice Lumumba ?', 'A',
    'Patrice �?mery Lumumba fut le premier Premier ministre de la République du Congo indépendante (30 juin 1960). Nationaliste panafricain, il fut renversé et assassiné le 17 janvier 1961. Il est considéré comme un héros national.');

add_expl($pdo, 'Le Kilimanjaro, plus haute montagne d\'Afrique, se trouve en :', 'A',
    'Le Kilimanjaro (5 895 m) est un stratovolcan situé en Tanzanie, près de la frontière kenyane. Son sommet Uhuru ("Liberté" en swahili) est souvent recouvert de neige malgré son emplacement tropical.');

add_expl($pdo, 'La deuxième guerre mondiale s\'est terminée en :', 'A',
    'La Seconde Guerre mondiale s\'est terminée en 1945 : capitulation de l\'Allemagne le 8 mai 1945 (V-E Day) et du Japon le 2 septembre 1945 (après les bombes atomiques sur Hiroshima et Nagasaki).');

add_expl($pdo, 'Quel est le plus grand pays d\'Afrique par sa superficie ?', 'A',
    'L\'Algérie (2 381 741 km²) est le plus grand pays d\'Afrique et le 10ème mondial. Elle est devenue la plus grande après la division du Soudan en 2011 (Soudan du Sud créé).');

add_expl($pdo, 'Quelle est la monnaie officielle de la RDC ?', 'A',
    'Le Franc Congolais (CDF) est la monnaie officielle de la RDC depuis 1997, remplaçant le Zaïre. Avant l\'indépendance (1960), c\'était le Franc Congolais Belge.');

add_expl($pdo, 'En quelle année l\'ONU a-t-elle été fondée ?', 'A',
    'L\'ONU (Organisation des Nations Unies) a été fondée le 24 octobre 1945 à San Francisco, après la Seconde Guerre mondiale, pour maintenir la paix internationale. Elle remplace la Société des Nations (SDN) créée en 1919.');

add_expl($pdo, 'Quel est le plus grand lac d\'Afrique ?', 'A',
    'Le lac Victoria (68 100 km²) est le plus grand lac d\'Afrique et le 2ème plus grand lac d\'eau douce du monde. Il est partagé entre l\'Ouganda, le Kenya et la Tanzanie. C\'est la source principale du Nil.');

add_expl($pdo, 'La Première Guerre mondiale a débuté en :', 'A',
    'La Première Guerre mondiale a débuté le 28 juillet 1914 après l\'assassinat de l\'archiduc François-Ferdinand à Sarajevo (28 juin 1914). Elle s\'est terminée le 11 novembre 1918.');

add_expl($pdo, 'La colonisation de la RDC par la Belgique a officiellement pris fin en :', 'A',
    'Le Congo belge a accédé à l\'indépendance le 30 juin 1960, proclamée par Patrice Lumumba. Avant d\'être belge (1908), il était l\'�?tat Indépendant du Congo, propriété personnelle du roi Léopold II (1885-1908).');

add_expl($pdo, 'La ville de Lubumbashi est connue pour :', 'A',
    'Lubumbashi (ex-�?lisabethville) est la 2ème ville de la RDC et la capitale du Haut-Katanga. Elle est au c�"ur de la "Copperbelt" (ceinture de cuivre) et riche en cuivre, cobalt et uranium. Le cobalt katangais est essentiel aux batteries de véhicules électriques.');

add_expl($pdo, 'Quel est le fleuve le plus long d\'Afrique ?', 'A',
    'Le Nil (6 650 km) est le plus long fleuve d\'Afrique et du monde (disputé avec l\'Amazone). Il coule du lac Victoria au nord jusqu\'à la mer Méditerranée en �?gypte. Le Congo est le 2ème fleuve d\'Afrique par la longueur mais le premier par le débit.');

/* �.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.�
   ANGLAIS
�.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.� */
echo "[" . date('H:i:s') . "] �Y?��Y?� Anglais...\n";

add_expl($pdo, 'What is the plural of "child" ?', 'A',
    '"Children" is an irregular plural from Old English "cildru". Other irregular plurals: man/men, woman/women, tooth/teeth, foot/feet, mouse/mice, goose/geese.');

add_expl($pdo, 'Fill in the blank: "I ___ to school every day."', 'A',
    '"Go" is correct with "I" (1st person singular, simple present). We DON\'T add "s" with I/you/we/they. Only he/she/it takes "goes".');

add_expl($pdo, 'Which sentence uses the Present Perfect correctly ?', 'A',
    '"I have visited Paris twice" is correct: Present Perfect = have/has + past participle, used for experiences at an unspecified time. "Since" and "for" are used with Present Perfect Continuous for ongoing actions.');

add_expl($pdo, 'What does "ambitious" mean ?', 'A',
    '"Ambitious" describes someone with a strong desire to succeed, achieve, or reach a high position. From Latin "ambire" (to go around canvassing for votes). Synonyms: driven, aspiring, motivated.');

add_expl($pdo, 'Choose the correct passive form of "They built this house in 1990."', 'A',
    '"This house was built in 1990" is correct passive voice. Formula: subject + was/were + past participle + by + agent. Past tense �?' was/were + pp. "Is built" (present) and "has been built" (present perfect) are wrong tenses here.');

add_expl($pdo, 'Which word is a synonym of "benevolent" ?', 'A',
    '"Benevolent" means kind, charitable, wishing good to others (from Latin "bene" = well + "velle" = to wish). Synonym: kind, generous, charitable, philanthropic. Antonym: malevolent.');

add_expl($pdo, 'Identify the gerund in: "Swimming is my favourite hobby."', 'A',
    'A gerund is a verb form ending in -ing used as a NOUN. "Swimming" is the subject of the sentence (subject position = noun). Compare: "I am swimming" where "swimming" is part of a verb tense.');

add_expl($pdo, 'Which sentence contains a conditional type 2 ?', 'A',
    'Conditional type 2 expresses an UNREAL/UNLIKELY present situation: If + past simple, would + bare infinitive. "If I had money, I would travel" = I don\'t have money (unreal). Type 1: if + present, will + inf (real possibility).');

add_expl($pdo, 'What is the comparative form of "good" ?', 'A',
    '"Good" has an irregular comparative: good �?' better �?' best. We DON\'T say "gooder" or "more good". Other irregulars: bad/worse/worst, far/farther(further)/farthest(furthest).');

add_expl($pdo, 'Choose the correct question tag: "She is a teacher, ___" ?', 'A',
    'Question tags use the auxiliary verb of the main clause, reversed: positive statement �?' negative tag. "She IS a teacher" �?' "isn\'t she?". If negative statement �?' positive tag: "She isn\'t a teacher, is she?"');

add_expl($pdo, 'What does "perseverance" mean ?', 'A',
    '"Perseverance" means continued effort and determination despite difficulties or obstacles. From Latin "perseverare" (to persist). Related: "persevere" (verb), "perseverant" (adj). Synonyms: persistence, tenacity, determination.');

add_expl($pdo, 'What is an antonym of "courageous" ?', 'A',
    '"Courageous" means brave, fearless. Its antonym (opposite) is "cowardly" (showing lack of courage). "Brave" and "bold" are synonyms of courageous, not antonyms.');

/* �.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.�
   SCIENCES
�.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.� */
echo "[" . date('H:i:s') . "] �Y"� Sciences...\n";

add_expl($pdo, 'Quel est l\'organe qui filtre le sang et produit l\'urine ?', 'A',
    'Les reins filtrent environ 180 litres de sang par jour, ne laissant passer que 1,5 à 2L d\'urine. Ils régulent aussi la pression artérielle et l\'équilibre des électrolytes (sodium, potassium).');

add_expl($pdo, 'Quelle est la planète la plus proche du Soleil ?', 'A',
    'Mercure est la planète la plus proche du Soleil (distance moyenne : 58 millions de km). Malgré sa proximité, elle n\'est pas la plus chaude (Vénus l\'est, grâce à son effet de serre intense).');

add_expl($pdo, 'Le Soleil est une étoile de type :', 'A',
    'Le Soleil est une naine jaune de type spectral G2V. Sa surface est à ~5 500°C. Il est à mi-vie (4,6 milliards d\'années). Dans ~5 milliards d\'années, il deviendra une géante rouge puis une naine blanche.');

add_expl($pdo, 'Quel gaz représente environ 78% de l\'atmosphère terrestre ?', 'A',
    'L\'air est composé de : 78% azote (N�,,), 21% oxygène (O�,,), 1% argon (Ar) et des traces de CO�,, (0,04%), vapeur d\'eau... L\'azote est inerte, c\'est l\'oxygène qui sert à la respiration.');

add_expl($pdo, 'La force qui attire les objets vers le centre de la Terre s\'appelle :', 'A',
    'La gravité (ou pesanteur) est la force d\'attraction gravitationnelle exercée par la Terre sur tous les corps. Elle donne aux objets leur "poids". g �?^ 9,8 m/s² à la surface de la Terre.');

add_expl($pdo, 'Quelle transformation de l\'eau correspond au passage de l\'état liquide à gazeux ?', 'A',
    'L\'évaporation est le passage de l\'état LIQUIDE à l\'état GAZEUX. La fusion = solide �?' liquide. La condensation = gaz �?' liquide. La solidification = liquide �?' solide. La sublimation = solide �?' gaz directement.');

add_expl($pdo, 'Quel est l\'appareil utilisé pour mesurer la pression atmosphérique ?', 'A',
    'Le baromètre mesure la pression atmosphérique (en hPa ou mmHg). Inventé par Torricelli en 1643. La pression normale au niveau de la mer est de 1013 hPa (= 1 atmosphère = 760 mmHg).');

add_expl($pdo, 'Combien de planètes compte notre système solaire ?', 'A',
    'Depuis 2006, le système solaire compte officiellement 8 planètes : Mercure, Vénus, Terre, Mars, Jupiter, Saturne, Uranus, Neptune. Pluton a été reclassé en "planète naine". Mnémotechnique : Mon Vieux, Tu Mourras Jeudi, Samedi Nuit (MVTMJSN).');

add_expl($pdo, 'Qu\'est-ce que la biodiversité ?', 'A',
    'La biodiversité désigne la variété des formes de vie sur Terre : diversité des espèces, des gènes et des écosystèmes. Elle est menacée par la déforestation, la pollution et le changement climatique. La RDC est l\'un des pays les plus riches en biodiversité.');

add_expl($pdo, 'La couche d\'ozone protège la Terre des rayons :', 'A',
    'La couche d\'ozone (O�,f) dans la stratosphère absorbe 97 à 99% des rayons UV-B et UV-C du Soleil. Ces rayons peuvent causer des cancers de la peau et des cataractes. Elle est menacée par les CFC (chlorofluorocarbures).');

add_expl($pdo, 'La température d\'ébullition de l\'eau à pression normale :', 'A',
    'L\'eau bout à 100°C à la pression atmosphérique standard (1013 hPa). �? altitude élevée (pression plus basse), l\'eau bout à moins de 100°C. Dans un autocuiseur (pression élevée), elle bout au-dessus de 100°C.');

add_expl($pdo, 'Quel gaz est rejeté par les animaux lors de la respiration ?', 'A',
    'Les animaux rejettent du CO�,, (dioxyde de carbone) lors de la respiration. Ce CO�,, est produit par la dégradation du glucose dans les cellules. Les plantes utilisent ce CO�,, pour la photosynthèse (cycle du carbone).');

add_expl($pdo, 'L\'effet de serre naturel est essentiel car il :', 'A',
    'Sans l\'effet de serre naturel, la température moyenne de la Terre serait de �^'18°C au lieu de +15°C. Les gaz à effet de serre (H�,,O, CO�,,, CH�,") retiennent la chaleur. Le problème actuel est l\'effet de serre AMPLIFI�? par les activités humaines.');

/* �.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.�
   FIN �?" Résumé
�.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.��.� */
echo "\n[" . date('H:i:s') . "] �o. Explications ajoutées : $updated questions mises à jour.\n";
echo "[" . date('H:i:s') . "] �Y'� Les explications apparaissent maintenant lors de la révision des réponses.\n";

