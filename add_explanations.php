<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_admin(); // Script restreint aux admins
/**
 * RÃ‰USSITE+ â€” Ajout d'explications aux questions QCM
 * Script non-destructif : met Ã  jour les explications sans toucher aux donnÃ©es existantes.
 * Accessible uniquement depuis localhost.
 */
if (php_sapi_name() !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'])) {
    http_response_code(403); die('AccÃ¨s refusÃ©.');
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

echo "[" . date('H:i:s') . "] ðŸ“ Ajout des explications aux questions...\n\n";

/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   MATHÃ‰MATIQUES
â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
echo "[" . date('H:i:s') . "] ðŸ”¢ MathÃ©matiques...\n";

add_expl($pdo, 'Quel est le rÃ©sultat de 8 Ã— 7 ?', 'A',
    '8 Ã— 7 = 56. Astuce : 8Ã—7 = 8Ã—5 + 8Ã—2 = 40 + 16 = 56. Ã€ retenir par cÅ“ur comme table de multiplication.');

add_expl($pdo, 'Quelle est la valeur de x dans : x + 12 = 20 ?', 'A',
    'Pour isoler x, on soustrait 12 des deux membres : x = 20 âˆ’ 12 = 8. VÃ©rification : 8 + 12 = 20 âœ“');

add_expl($pdo, 'Combien vaut 15% de 200 ?', 'A',
    '15% = 15/100. Calcul : (15 Ã— 200) / 100 = 3 000 / 100 = 30. Ou encore : 10% de 200 = 20, plus 5% = 10, total 30.');

add_expl($pdo, 'Quel est le PGCD de 12 et 18 ?', 'A',
    'Diviseurs de 12 : 1, 2, 3, 4, 6, 12. Diviseurs de 18 : 1, 2, 3, 6, 9, 18. Le plus grand diviseur commun est 6.');

add_expl($pdo, 'Calculer : 3Â² + 4Â²', 'A',
    '3Â² = 9 et 4Â² = 16. Somme = 25 = 5Â². C\'est le cÃ©lÃ¨bre triplet pythagoricien 3-4-5 : dans un triangle rectangle de cÃ´tÃ©s 3 et 4, l\'hypotÃ©nuse vaut 5.');

add_expl($pdo, 'RÃ©soudre : 2x - 5 = 11', 'A',
    '2x = 11 + 5 = 16, donc x = 16/2 = 8. VÃ©rification : 2Ã—8 âˆ’ 5 = 16 âˆ’ 5 = 11 âœ“');

add_expl($pdo, 'Quel est le rÃ©sultat de 15Â² - 10Â² ?', 'A',
    'IdentitÃ© remarquable : aÂ² âˆ’ bÂ² = (aâˆ’b)(a+b). Ici : (15âˆ’10)(15+10) = 5 Ã— 25 = 125. Plus rapide que calculer 225 âˆ’ 100.');

add_expl($pdo, 'Si f(x) = 3xÂ² + 2x - 1, calculer f(2).', 'A',
    'f(2) = 3Ã—(2Â²) + 2Ã—2 âˆ’ 1 = 3Ã—4 + 4 âˆ’ 1 = 12 + 4 âˆ’ 1 = 15. On remplace x par 2 dans la formule.');

add_expl($pdo, 'Calculer la valeur de sin(30Â°).', 'A',
    'sin(30Â°) = 1/2 = 0,5. Valeur fondamentale Ã  mÃ©moriser. Dans un triangle Ã©quilatÃ©ral coupÃ© en deux, l\'angle de 30Â° a le demi-cÃ´tÃ© opposÃ© Ã  l\'hypotÃ©nuse.');

add_expl($pdo, 'RÃ©soudre l\'Ã©quation du second degrÃ© : xÂ² - 5x + 6 = 0', 'A',
    'Discriminant Î” = bÂ²âˆ’4ac = 25âˆ’24 = 1. Racines : x = (5Â±1)/2. Donc xâ‚ = 3 et xâ‚‚ = 2. VÃ©rification : (xâˆ’2)(xâˆ’3) = 0 âœ“');

add_expl($pdo, 'Dans un triangle rectangle, si sin(A) = 3/5, quelle est la valeur de cos(A) ?', 'A',
    'Relation fondamentale : sinÂ²(A) + cosÂ²(A) = 1. Donc cosÂ²(A) = 1 âˆ’ (3/5)Â² = 1 âˆ’ 9/25 = 16/25. cos(A) = 4/5 (positif car A est un angle aigu).');

add_expl($pdo, 'Quelle est la dÃ©rivÃ©e de f(x) = 2xÂ³ - 4x + 1 ?', 'A',
    'On dÃ©rive terme par terme : (2xÂ³)\' = 6xÂ², (âˆ’4x)\' = âˆ’4, (1)\' = 0. Donc f\'(x) = 6xÂ² âˆ’ 4.');

add_expl($pdo, 'Calculer la dÃ©rivÃ©e de f(x) = xÂ³ - 3x.', 'A',
    'f\'(x) = 3xÂ² âˆ’ 3. RÃ¨gle : (xâ¿)\' = nÂ·xâ¿â»Â¹. Donc (xÂ³)\' = 3xÂ² et (âˆ’3x)\' = âˆ’3.');

add_expl($pdo, 'Calculer la limite de (xÂ² - 4)/(x - 2) quand x â†’ 2.', 'A',
    'Forme indÃ©terminÃ©e 0/0. On factorise : xÂ²âˆ’4 = (xâˆ’2)(x+2). Donc (xÂ²âˆ’4)/(xâˆ’2) = x+2 â†’ limite = 2+2 = 4.');

add_expl($pdo, 'âˆ«â‚€Â¹ xÂ² dx est Ã©gal Ã  :', 'A',
    'Primitive de xÂ² est xÂ³/3. On Ã©value : [xÂ³/3]â‚€Â¹ = 1Â³/3 âˆ’ 0Â³/3 = 1/3 âˆ’ 0 = 1/3.');

add_expl($pdo, 'La somme de la sÃ©rie gÃ©omÃ©trique 1 + 1/2 + 1/4 + ... converge vers :', 'A',
    'SÃ©rie gÃ©omÃ©trique de premier terme a=1 et raison r=1/2. Somme infinie = a/(1âˆ’r) = 1/(1âˆ’1/2) = 1/(1/2) = 2.');

add_expl($pdo, 'RÃ©soudre dans â„ : |2x - 3| < 5', 'A',
    '|2xâˆ’3| < 5 âŸº âˆ’5 < 2xâˆ’3 < 5 âŸº âˆ’2 < 2x < 8 âŸº âˆ’1 < x < 4.');

add_expl($pdo, 'Quel est le rÃ©sultat de âˆš144 ?', 'A',
    'âˆš144 = 12 car 12Â² = 144. Ã€ connaÃ®tre : âˆš1=1, âˆš4=2, âˆš9=3, âˆš16=4, âˆš25=5, âˆš36=6, âˆš49=7, âˆš64=8, âˆš81=9, âˆš100=10, âˆš121=11, âˆš144=12.');

add_expl($pdo, 'Quelle est l\'image de x = âˆ’2 par f(x) = xÂ² âˆ’ 3x + 1 ?', 'A',
    'f(âˆ’2) = (âˆ’2)Â² âˆ’ 3Ã—(âˆ’2) + 1 = 4 + 6 + 1 = 11. Attention : (âˆ’2)Â² = 4 (positif !)');

add_expl($pdo, 'Exprimer 60Â° en radians.', 'A',
    '180Â° = Ï€ radians. Donc 60Â° = 60Ã—(Ï€/180) = Ï€/3. RÃ¨gle : multiplier les degrÃ©s par Ï€/180.');

add_expl($pdo, 'Calculer : 5! (5 factorielle)', 'A',
    '5! = 5 Ã— 4 Ã— 3 Ã— 2 Ã— 1 = 120. La factorielle multiplie tous les entiers de 1 Ã  n.');

add_expl($pdo, 'La probabilitÃ© de tirer un as d\'un jeu de 52 cartes est :', 'A',
    'Il y a 4 as dans 52 cartes. P = 4/52 = 1/13 â‰ˆ 0,077. On simplifie 4/52 par 4.');

add_expl($pdo, 'RÃ©soudre : xÂ² = 25', 'A',
    'xÂ² = 25 a DEUX solutions : x = +5 et x = âˆ’5 car (âˆ’5)Â² = 25 aussi. On Ã©crit : x = Â±5.');

add_expl($pdo, 'RÃ©soudre : xÂ² - 5x + 6 = 0', 'A',
    'Î” = 25 âˆ’ 24 = 1. x = (5Â±1)/2 â†’ x=3 ou x=2. Ou par factorisation : (xâˆ’2)(xâˆ’3)=0.');

add_expl($pdo, 'Quelle est la mÃ©diane d\'une sÃ©rie : 4, 7, 2, 9, 1, 5 ?', 'A',
    'On trie la sÃ©rie : 1, 2, 4, 5, 7, 9. Nombre pair d\'Ã©lÃ©ments (6), donc mÃ©diane = moyenne des deux centraux = (4+5)/2 = 4,5.');

/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   FRANÃ‡AIS
â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
echo "[" . date('H:i:s') . "] ðŸ“ FranÃ§ais...\n";

add_expl($pdo, 'Quel est le pluriel de "Å“il" en franÃ§ais ?', 'A',
    '"yeux" est un pluriel irrÃ©gulier d\'origine latine. "Å’ils" n\'est utilisÃ© que dans des termes composÃ©s comme "Å“ils-de-bÅ“uf" (fenÃªtres rondes).');

add_expl($pdo, 'Conjuguer "aller" au prÃ©sent de l\'indicatif, 1Ã¨re personne du singulier :', 'A',
    '"Aller" est un verbe trÃ¨s irrÃ©gulier : je vais, tu vas, il va, nous allons, vous allez, ils vont. Sa conjugaison vient de trois radicaux latins diffÃ©rents.');

add_expl($pdo, 'Quel est l\'antonyme de "rapide" ?', 'A',
    'L\'antonyme (contraire) de "rapide" est "lent". "Vite" est un adverbe synonyme de "rapidement", pas un antonyme.');

add_expl($pdo, 'Identifier la nature du mot soulignÃ© dans : "Il court vite."', 'A',
    '"Vite" modifie le verbe "court" en indiquant la maniÃ¨re : c\'est donc un adverbe de maniÃ¨re. Il est invariable.');

add_expl($pdo, 'Quel est le synonyme de "perspicace" ?', 'A',
    '"Perspicace" (du latin perspicere = voir clairement) signifie qui voit et comprend rapidement. "Clairvoyant" en est le synonyme le plus proche.');

add_expl($pdo, '"Le cÅ“ur de Pierre est de pierre" â€” quelle figure de style ?', 'A',
    'L\'antanaclase rÃ©pÃ¨te un mÃªme mot avec deux sens diffÃ©rents : "cÅ“ur" (organe/siÃ¨ge des Ã©motions) et "pierre" (matÃ©riau/duretÃ©). C\'est un jeu sur la polysÃ©mie.');

add_expl($pdo, '"La lune Ã©tait sereine et jouait sur les flots" (Hugo) â€” figure de style ?', 'A',
    'La personnification attribue des qualitÃ©s humaines Ã  un objet inanimÃ©. Ici, la lune "jouait" comme le ferait une personne.');

add_expl($pdo, 'Quel est le mode verbal de "Bien que tu sois parti tÃ´t" ?', 'A',
    '"Bien que" impose le subjonctif. "Bien que tu sois parti" : "sois parti" = subjonctif passÃ© du verbe "Ãªtre". Les conjonctions de concession imposent le subjonctif.');

add_expl($pdo, 'Identifier le COD dans : "Marie offre une fleur Ã  son pÃ¨re."', 'A',
    'Le COD rÃ©pond Ã  la question "offre quoi ?". RÃ©ponse : "une fleur". "Ã€ son pÃ¨re" est le COI (complÃ©ment d\'objet indirect).');

add_expl($pdo, 'Quel est le fÃ©minin de "acteur" ?', 'A',
    '"Acteur" â†’ "actrice". Les noms en -eur font gÃ©nÃ©ralement leur fÃ©minin en -rice (directeur/directrice, acteur/actrice) ou -euse (chanteur/chanteuse).');

add_expl($pdo, 'Dans quelle phrase le subjonctif est-il obligatoire ?', 'A',
    '"Il faut que" impose systÃ©matiquement le subjonctif. "Il faut que tu viennes" : "viennes" = subjonctif prÃ©sent de "venir".');

add_expl($pdo, 'Quel est le mode verbal de "Bien que tu sois parti tÃ´t" ?', 'A',
    '"Bien que" impose toujours le subjonctif : c\'est une conjonction de concession. "sois parti" = subjonctif passÃ©.');

add_expl($pdo, '"Sa voix Ã©tait douce comme du miel." La figure de style est :', 'A',
    'La comparaison utilise un outil comparatif ("comme", "tel que", "pareil Ã "...). Ici "comme" compare la douceur de la voix au miel. Si on disait "Sa voix Ã©tait du miel", ce serait une mÃ©taphore.');

add_expl($pdo, '"Il a une mÃ©moire d\'Ã©lÃ©phant." La figure de style est :', 'A',
    'La mÃ©taphore compare sans outil de comparaison ("comme", "tel"). "MÃ©moire d\'Ã©lÃ©phant" = grande mÃ©moire, sans le mot "comme". Si c\'Ã©tait "une mÃ©moire comme un Ã©lÃ©phant", ce serait une comparaison.');

add_expl($pdo, 'Quel est le passÃ© simple de "venir" Ã  la 3e pers. sing. ?', 'A',
    '"Venir" au passÃ© simple : je vins, tu vins, il vint, nous vÃ®nmes, vous vÃ®ntes, ils vinrent. "Vint" est la forme correcte pour "il/elle".');

/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   CHIMIE
â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
echo "[" . date('H:i:s') . "] âš—ï¸ Chimie...\n";

add_expl($pdo, 'Quelle est la masse molaire de l\'eau (Hâ‚‚O) ? (H=1, O=16)', 'A',
    'Hâ‚‚O contient 2 atomes d\'hydrogÃ¨ne (2Ã—1=2) et 1 atome d\'oxygÃ¨ne (1Ã—16=16). Masse molaire = 2 + 16 = 18 g/mol.');

add_expl($pdo, 'Quelle est la configuration Ã©lectronique du Sodium (Z=11) ?', 'A',
    'Le sodium a 11 Ã©lectrons rÃ©partis sur 3 couches : 2 sur la 1Ã¨re, 8 sur la 2Ã¨me, 1 sur la 3Ã¨me â†’ 2,8,1. Cet unique Ã©lectron de valence explique sa grande rÃ©activitÃ©.');

add_expl($pdo, 'La rÃ©action NaOH + HCl â†’ NaCl + Hâ‚‚O est une rÃ©action de :', 'A',
    'La neutralisation est la rÃ©action entre un acide (HCl) et une base (NaOH) pour former un sel (NaCl) et de l\'eau. Le pH de la solution rÃ©sultante est proche de 7.');

add_expl($pdo, 'Quel est le symbole de l\'argent ?', 'A',
    '"Ag" vient du latin "argentum". L\'argent est un mÃ©tal prÃ©cieux blanc brillant, conducteur d\'Ã©lectricitÃ©, utilisÃ© en bijouterie et en photographie.');

add_expl($pdo, 'La liaison covalente est formÃ©e par :', 'A',
    'La liaison covalente rÃ©sulte du partage d\'une ou plusieurs paires d\'Ã©lectrons entre deux atomes. Ex : dans Hâ‚‚, les deux hydrogÃ¨nes partagent 2 Ã©lectrons.');

add_expl($pdo, 'La formule molÃ©culaire du glucose est :', 'A',
    'Le glucose (Câ‚†Hâ‚â‚‚Oâ‚†) est le principal sucre Ã©nergÃ©tique des cellules. Son nom vient du grec "glukus" (doux). Le saccharose (sucre de table) est Câ‚â‚‚Hâ‚‚â‚‚Oâ‚â‚.');

add_expl($pdo, 'Quel est le produit de la combustion complÃ¨te du mÃ©thane CHâ‚„ ?', 'A',
    'CHâ‚„ + 2Oâ‚‚ â†’ COâ‚‚ + 2Hâ‚‚O. Combustion complÃ¨te = tout le carbone devient COâ‚‚ et tout l\'hydrogÃ¨ne devient Hâ‚‚O. IncomplÃ¨te : CO (monoxyde de carbone) se forme aussi.');

add_expl($pdo, 'La concentration molaire C s\'exprime en :', 'A',
    'La concentration molaire C = n/V, oÃ¹ n = nombre de moles (mol) et V = volume en litres (L). UnitÃ© : mol/L ou molÂ·Lâ»Â¹. Exemple : 1 mol/L = 1 M.');

add_expl($pdo, 'Quel est le symbole du fer ?', 'A',
    '"Fe" vient du latin "ferrum". Le fer est le mÃ©tal le plus abondant sur Terre (noyau terrestre). Il est essentiel Ã  la production d\'acier et dans l\'hÃ©moglobine du sang.');

add_expl($pdo, 'Une solution de pH = 3 est :', 'A',
    'L\'Ã©chelle de pH va de 0 Ã  14. pH < 7 = acide, pH = 7 = neutre, pH > 7 = basique (alcalin). pH = 3 est trÃ¨s acide (ex: vinaigre pHâ‰ˆ3, jus de citron pHâ‰ˆ2).');

add_expl($pdo, 'La formule de l\'ammoniac est :', 'A',
    'L\'ammoniac NHâ‚ƒ est une molÃ©cule composÃ©e d\'1 azote et de 3 hydrogÃ¨nes. C\'est un gaz piquant soluble dans l\'eau, utilisÃ© comme engrais et produit chimique industriel.');

add_expl($pdo, 'Quel est le symbole du sodium ?', 'A',
    '"Na" vient du latin "natrium". Le sodium est un mÃ©tal alcalin mou, trÃ¨s rÃ©actif avec l\'eau (Na + Hâ‚‚O â†’ NaOH + Hâ‚‚). PrÃ©sent dans le sel de cuisine NaCl.');

add_expl($pdo, 'La formule de l\'acide nitrique est :', 'A',
    'L\'acide nitrique HNOâ‚ƒ est un acide fort oxydant. Il est utilisÃ© dans la fabrication d\'engrais (nitrate d\'ammonium) et d\'explosifs. Ã€ distinguer de HNOâ‚‚ (acide nitreux, acide faible).');

add_expl($pdo, 'Quel est le symbole de l\'aluminium ?', 'A',
    '"Al" pour aluminium. C\'est le mÃ©tal le plus abondant dans la croÃ»te terrestre. LÃ©ger, rÃ©sistant Ã  la corrosion, il est utilisÃ© dans l\'aÃ©ronautique, les emballages alimentaires et la construction.');

add_expl($pdo, 'Le cuivre a pour symbole chimique :', 'A',
    '"Cu" vient du latin "cuprum" (Ã®le de Chypre, ancienne source de cuivre). Le cuivre est excellent conducteur d\'Ã©lectricitÃ© et de chaleur. Il est utilisÃ© dans les fils Ã©lectriques.');

add_expl($pdo, 'La loi de conservation de la matiÃ¨re (Lavoisier) dit que :', 'A',
    '"Rien ne se perd, rien ne se crÃ©e, tout se transforme" (Lavoisier, 1789). La masse totale des rÃ©actifs = masse totale des produits. Exemple : 2Hâ‚‚ + Oâ‚‚ â†’ 2Hâ‚‚O (4g + 32g = 36g).');

/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   PHYSIQUE
â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
echo "[" . date('H:i:s') . "] âš¡ Physique...\n";

add_expl($pdo, 'Quelle est l\'unitÃ© de l\'Ã©nergie dans le SI ?', 'A',
    'Le Joule (J) est l\'unitÃ© SI d\'Ã©nergie. 1 J = 1 NÂ·m = 1 kgÂ·mÂ²/sÂ². Le watt est une unitÃ© de PUISSANCE (J/s), le newton est une unitÃ© de FORCE.');

add_expl($pdo, 'Un objet de masse 2 kg tombe librement. Son poids vaut (g = 10 m/sÂ²) :', 'A',
    'Poids P = m Ã— g = 2 Ã— 10 = 20 N. Le poids est une FORCE (en Newtons), diffÃ©rent de la masse (en kg). Sur la Lune, g â‰ˆ 1,6 m/sÂ², donc le poids changerait mais pas la masse.');

add_expl($pdo, 'La puissance Ã©lectrique s\'exprime par :', 'A',
    'P = U Ã— I (puissance = tension Ã— intensitÃ©). En Watts. On peut aussi Ã©crire P = RÃ—IÂ² ou P = UÂ²/R. Exemple : une ampoule 60W alimentÃ©e en 220V consomme I = 60/220 â‰ˆ 0,27 A.');

add_expl($pdo, 'L\'unitÃ© du courant Ã©lectrique est :', 'A',
    'L\'AmpÃ¨re (A) mesure l\'intensitÃ© du courant Ã©lectrique. 1 AmpÃ¨re = passage de 1 Coulomb de charge par seconde. NommÃ© d\'aprÃ¨s AndrÃ©-Marie AmpÃ¨re, physicien franÃ§ais.');

add_expl($pdo, 'Deux rÃ©sistances Râ‚ = 4 Î© et Râ‚‚ = 6 Î© montÃ©es en sÃ©rie. La rÃ©sistance totale est :', 'A',
    'En sÃ©rie : Rtotal = Râ‚ + Râ‚‚ = 4 + 6 = 10 Î©. En sÃ©rie, les rÃ©sistances s\'additionnent. En parallÃ¨le, la rÃ©sistance totale serait : 1/Rt = 1/4 + 1/6 â†’ Rt = 2,4 Î©.');

add_expl($pdo, 'Deux rÃ©sistances Râ‚ = 4 Î© et Râ‚‚ = 4 Î© montÃ©es en parallÃ¨le. La rÃ©sistance totale est :', 'A',
    'En parallÃ¨le : 1/Rt = 1/Râ‚ + 1/Râ‚‚ = 1/4 + 1/4 = 2/4 = 1/2. Donc Rt = 2 Î©. La rÃ©sistance totale en parallÃ¨le est toujours infÃ©rieure Ã  la plus petite rÃ©sistance.');

add_expl($pdo, 'Le travail d\'une force F = 10 N sur un dÃ©placement d = 5 m (angle 0Â°) vaut :', 'A',
    'W = F Ã— d Ã— cos(Î¸) = 10 Ã— 5 Ã— cos(0Â°) = 10 Ã— 5 Ã— 1 = 50 J. Quand la force et le dÃ©placement sont dans le mÃªme sens (Î¸=0Â°), cos(0Â°)=1.');

add_expl($pdo, 'Quelle propriÃ©tÃ© de la lumiÃ¨re explique l\'arc-en-ciel ?', 'A',
    'La dispersion : la lumiÃ¨re blanche se dÃ©compose en ses couleurs (rouge, orange, jaune, vert, bleu, indigo, violet) car chaque longueur d\'onde est rÃ©fractÃ©e diffÃ©remment dans les gouttelettes d\'eau.');

add_expl($pdo, 'Quel est le phÃ©nomÃ¨ne qui permet aux fibres optiques de transporter la lumiÃ¨re ?', 'A',
    'La rÃ©flexion totale interne : quand la lumiÃ¨re passe d\'un milieu dense vers un milieu moins dense avec un angle supÃ©rieur Ã  l\'angle critique, elle est totalement rÃ©flÃ©chie sans sortir de la fibre.');

add_expl($pdo, 'Quelle est l\'unitÃ© de la frÃ©quence ?', 'A',
    'Le Hertz (Hz) = nombre d\'oscillations par seconde. 1 Hz = 1 cycle/seconde. NommÃ© d\'aprÃ¨s Heinrich Hertz. Exemples : courant Ã©lectrique alternatif = 50 Hz, audition humaine 20 Hzâ€“20 000 Hz.');

add_expl($pdo, 'La loi de Newton de gravitation universelle : F = GÂ·MÂ·m/rÂ². G est :', 'A',
    'G est la constante gravitationnelle universelle : G â‰ˆ 6,67 Ã— 10â»Â¹Â¹ NÂ·mÂ²/kgÂ². Elle est la mÃªme partout dans l\'univers. MesurÃ©e par Henry Cavendish en 1798.');

add_expl($pdo, 'Un objet lancÃ© Ã  20 m/s vers le haut : combien de secondes met-il Ã  s\'arrÃªter (g=10)?', 'A',
    'Au sommet, v = 0. On utilise v = vâ‚€ âˆ’ gÂ·t â†’ 0 = 20 âˆ’ 10t â†’ t = 2 s. La gravitÃ© dÃ©cÃ©lÃ¨re l\'objet de 10 m/s chaque seconde.');

add_expl($pdo, 'Le principe d\'inertie (1Ã¨re loi de Newton) stipule :', 'A',
    'Tout corps persÃ©vÃ¨re dans son Ã©tat de repos ou de mouvement rectiligne uniforme tant qu\'aucune force ne s\'y oppose. En l\'absence de frottements, un objet en mouvement continue indÃ©finiment.');

/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   BIOLOGIE
â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
echo "[" . date('H:i:s') . "] ðŸ§¬ Biologie...\n";

add_expl($pdo, 'Quel est le rÃ´le du pancrÃ©as dans la digestion ?', 'A',
    'Le pancrÃ©as a deux rÃ´les : (1) exocrine : il sÃ©crÃ¨te des enzymes digestives (amylase, lipase, protÃ©ase) dans le duodÃ©num ; (2) endocrine : il produit l\'insuline et le glucagon pour rÃ©guler la glycÃ©mie.');

add_expl($pdo, 'Quelle vitamine est synthÃ©tisÃ©e par la peau sous l\'effet du soleil ?', 'A',
    'La vitamine D est synthÃ©tisÃ©e dans la peau par les rayons UV du soleil Ã  partir d\'un prÃ©curseur dÃ©rivÃ© du cholestÃ©rol. Elle est essentielle pour l\'absorption du calcium et la santÃ© osseuse.');

add_expl($pdo, 'Les vaisseaux sanguins qui transportent le sang vers le cÅ“ur sont :', 'A',
    'Les VEINES transportent le sang vers le cÅ“ur (V comme "vers"). Les ARTÃˆRES transportent le sang du cÅ“ur vers les organes. Les capillaires font les Ã©changes entre le sang et les tissus.');

add_expl($pdo, 'Le systÃ¨me nerveux central est composÃ© de :', 'A',
    'Le SNC comprend le cerveau et la moelle Ã©piniÃ¨re. Le systÃ¨me nerveux pÃ©riphÃ©rique (SNP) comprend tous les nerfs qui relient le SNC aux organes et muscles du corps.');

add_expl($pdo, 'Quel type de reproduction ne nÃ©cessite qu\'un seul parent ?', 'A',
    'La reproduction asexuÃ©e (ou vÃ©gÃ©tative) ne nÃ©cessite qu\'un seul individu : bouturage, bourgeonnement, fission binaire (bactÃ©ries). Elle produit des clones gÃ©nÃ©tiquement identiques au parent.');

add_expl($pdo, 'La respiration cellulaire se rÃ©sume par :', 'A',
    'Glucose + 6Oâ‚‚ â†’ 6COâ‚‚ + 6Hâ‚‚O + ATP. La cellule "brÃ»le" le glucose avec l\'oxygÃ¨ne pour produire de l\'Ã©nergie (ATP). C\'est l\'inverse de la photosynthÃ¨se.');

add_expl($pdo, 'Quelle est la fonction de la membrane cellulaire ?', 'A',
    'La membrane cellulaire (bicouche phospholipidique) contrÃ´le ce qui entre et sort de la cellule. Elle est semipermÃ©able : laisse passer certaines molÃ©cules et en bloque d\'autres.');

add_expl($pdo, 'Le groupe sanguin O est dit "donneur universel" car :', 'A',
    'Le groupe O n\'a pas d\'antigÃ¨nes A ni B sur ses globules rouges â†’ le receveur ne peut pas les rejeter. Attention : le plasma du groupe O contient des anticorps anti-A et anti-B, donc les porteurs O ne peuvent recevoir que du sang O.');

add_expl($pdo, 'Quel est le rÃ´le de l\'ADN dans la cellule ?', 'A',
    'L\'ADN (Acide DÃ©soxyriboNuclÃ©ique) est la molÃ©cule qui stocke l\'information gÃ©nÃ©tique sous forme de sÃ©quence de bases (A, T, G, C). Il est transmis lors de la division cellulaire et contrÃ´le la synthÃ¨se des protÃ©ines.');

add_expl($pdo, 'Les anticorps sont produits par :', 'A',
    'Les lymphocytes B (B pour Bourse de Fabricius chez les oiseaux) produisent les anticorps (immunoglobulines). Les lymphocytes T, eux, coordonnent la rÃ©ponse immunitaire cellulaire.');

add_expl($pdo, 'Le VIH attaque principalement quel type de cellules ?', 'A',
    'Le VIH (Virus de l\'ImmunodÃ©ficience Humaine) cible les lymphocytes T CD4+ (aussi appelÃ©s T helper). En les dÃ©truisant progressivement, le virus affaiblit le systÃ¨me immunitaire jusqu\'au SIDA dÃ©clarÃ©.');

add_expl($pdo, 'La vaccination crÃ©e une immunitÃ© en induisant la production de :', 'A',
    'Le vaccin introduit un antigÃ¨ne (virus attÃ©nuÃ©, tuÃ© ou fragment protÃ©ique) â†’ le systÃ¨me immunitaire produit des anticorps spÃ©cifiques et crÃ©e des cellules mÃ©moire â†’ rÃ©ponse rapide si le vrai pathogÃ¨ne arrive.');

add_expl($pdo, 'La sÃ©lection naturelle est le mÃ©canisme central de la thÃ©orie de :', 'A',
    'Charles Darwin (1859, "De l\'Origine des espÃ¨ces") a proposÃ© que les individus les mieux adaptÃ©s Ã  leur environnement survivent et se reproduisent davantage â†’ leurs traits se transmettent aux gÃ©nÃ©rations suivantes.');

add_expl($pdo, 'Quelle est la durÃ©e moyenne d\'une grossesse humaine ?', 'A',
    'La grossesse humaine dure environ 9 mois calendaires, soit 38 semaines de dÃ©veloppement embryonnaire (40 semaines d\'amÃ©norrhÃ©e Ã  partir des derniÃ¨res rÃ¨gles). Elle se divise en 3 trimestres.');

/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   HISTOIRE-GÃ‰OGRAPHIE
â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
echo "[" . date('H:i:s') . "] ðŸŒ Histoire-GÃ©o...\n";

add_expl($pdo, 'La confÃ©rence de Berlin (1884-1885) a abouti Ã  :', 'A',
    'La confÃ©rence de Berlin (organisÃ©e par Bismarck) a organisÃ© le "partage" de l\'Afrique entre les puissances europÃ©ennes sans consulter les Africains. Elle a fixÃ© les rÃ¨gles de la colonisation et crÃ©Ã© l\'Ã‰tat IndÃ©pendant du Congo pour LÃ©opold II de Belgique.');

add_expl($pdo, 'Qui Ã©tait Patrice Lumumba ?', 'A',
    'Patrice Ã‰mery Lumumba fut le premier Premier ministre de la RÃ©publique du Congo indÃ©pendante (30 juin 1960). Nationaliste panafricain, il fut renversÃ© et assassinÃ© le 17 janvier 1961. Il est considÃ©rÃ© comme un hÃ©ros national.');

add_expl($pdo, 'Le Kilimanjaro, plus haute montagne d\'Afrique, se trouve en :', 'A',
    'Le Kilimanjaro (5 895 m) est un stratovolcan situÃ© en Tanzanie, prÃ¨s de la frontiÃ¨re kenyane. Son sommet Uhuru ("LibertÃ©" en swahili) est souvent recouvert de neige malgrÃ© son emplacement tropical.');

add_expl($pdo, 'La deuxiÃ¨me guerre mondiale s\'est terminÃ©e en :', 'A',
    'La Seconde Guerre mondiale s\'est terminÃ©e en 1945 : capitulation de l\'Allemagne le 8 mai 1945 (V-E Day) et du Japon le 2 septembre 1945 (aprÃ¨s les bombes atomiques sur Hiroshima et Nagasaki).');

add_expl($pdo, 'Quel est le plus grand pays d\'Afrique par sa superficie ?', 'A',
    'L\'AlgÃ©rie (2 381 741 kmÂ²) est le plus grand pays d\'Afrique et le 10Ã¨me mondial. Elle est devenue la plus grande aprÃ¨s la division du Soudan en 2011 (Soudan du Sud crÃ©Ã©).');

add_expl($pdo, 'Quelle est la monnaie officielle de la RDC ?', 'A',
    'Le Franc Congolais (CDF) est la monnaie officielle de la RDC depuis 1997, remplaÃ§ant le ZaÃ¯re. Avant l\'indÃ©pendance (1960), c\'Ã©tait le Franc Congolais Belge.');

add_expl($pdo, 'En quelle annÃ©e l\'ONU a-t-elle Ã©tÃ© fondÃ©e ?', 'A',
    'L\'ONU (Organisation des Nations Unies) a Ã©tÃ© fondÃ©e le 24 octobre 1945 Ã  San Francisco, aprÃ¨s la Seconde Guerre mondiale, pour maintenir la paix internationale. Elle remplace la SociÃ©tÃ© des Nations (SDN) crÃ©Ã©e en 1919.');

add_expl($pdo, 'Quel est le plus grand lac d\'Afrique ?', 'A',
    'Le lac Victoria (68 100 kmÂ²) est le plus grand lac d\'Afrique et le 2Ã¨me plus grand lac d\'eau douce du monde. Il est partagÃ© entre l\'Ouganda, le Kenya et la Tanzanie. C\'est la source principale du Nil.');

add_expl($pdo, 'La PremiÃ¨re Guerre mondiale a dÃ©butÃ© en :', 'A',
    'La PremiÃ¨re Guerre mondiale a dÃ©butÃ© le 28 juillet 1914 aprÃ¨s l\'assassinat de l\'archiduc FranÃ§ois-Ferdinand Ã  Sarajevo (28 juin 1914). Elle s\'est terminÃ©e le 11 novembre 1918.');

add_expl($pdo, 'La colonisation de la RDC par la Belgique a officiellement pris fin en :', 'A',
    'Le Congo belge a accÃ©dÃ© Ã  l\'indÃ©pendance le 30 juin 1960, proclamÃ©e par Patrice Lumumba. Avant d\'Ãªtre belge (1908), il Ã©tait l\'Ã‰tat IndÃ©pendant du Congo, propriÃ©tÃ© personnelle du roi LÃ©opold II (1885-1908).');

add_expl($pdo, 'La ville de Lubumbashi est connue pour :', 'A',
    'Lubumbashi (ex-Ã‰lisabethville) est la 2Ã¨me ville de la RDC et la capitale du Haut-Katanga. Elle est au cÅ“ur de la "Copperbelt" (ceinture de cuivre) et riche en cuivre, cobalt et uranium. Le cobalt katangais est essentiel aux batteries de vÃ©hicules Ã©lectriques.');

add_expl($pdo, 'Quel est le fleuve le plus long d\'Afrique ?', 'A',
    'Le Nil (6 650 km) est le plus long fleuve d\'Afrique et du monde (disputÃ© avec l\'Amazone). Il coule du lac Victoria au nord jusqu\'Ã  la mer MÃ©diterranÃ©e en Ã‰gypte. Le Congo est le 2Ã¨me fleuve d\'Afrique par la longueur mais le premier par le dÃ©bit.');

/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   ANGLAIS
â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
echo "[" . date('H:i:s') . "] ðŸ‡¬ðŸ‡§ Anglais...\n";

add_expl($pdo, 'What is the plural of "child" ?', 'A',
    '"Children" is an irregular plural from Old English "cildru". Other irregular plurals: man/men, woman/women, tooth/teeth, foot/feet, mouse/mice, goose/geese.');

add_expl($pdo, 'Fill in the blank: "I ___ to school every day."', 'A',
    '"Go" is correct with "I" (1st person singular, simple present). We DON\'T add "s" with I/you/we/they. Only he/she/it takes "goes".');

add_expl($pdo, 'Which sentence uses the Present Perfect correctly ?', 'A',
    '"I have visited Paris twice" is correct: Present Perfect = have/has + past participle, used for experiences at an unspecified time. "Since" and "for" are used with Present Perfect Continuous for ongoing actions.');

add_expl($pdo, 'What does "ambitious" mean ?', 'A',
    '"Ambitious" describes someone with a strong desire to succeed, achieve, or reach a high position. From Latin "ambire" (to go around canvassing for votes). Synonyms: driven, aspiring, motivated.');

add_expl($pdo, 'Choose the correct passive form of "They built this house in 1990."', 'A',
    '"This house was built in 1990" is correct passive voice. Formula: subject + was/were + past participle + by + agent. Past tense â†’ was/were + pp. "Is built" (present) and "has been built" (present perfect) are wrong tenses here.');

add_expl($pdo, 'Which word is a synonym of "benevolent" ?', 'A',
    '"Benevolent" means kind, charitable, wishing good to others (from Latin "bene" = well + "velle" = to wish). Synonym: kind, generous, charitable, philanthropic. Antonym: malevolent.');

add_expl($pdo, 'Identify the gerund in: "Swimming is my favourite hobby."', 'A',
    'A gerund is a verb form ending in -ing used as a NOUN. "Swimming" is the subject of the sentence (subject position = noun). Compare: "I am swimming" where "swimming" is part of a verb tense.');

add_expl($pdo, 'Which sentence contains a conditional type 2 ?', 'A',
    'Conditional type 2 expresses an UNREAL/UNLIKELY present situation: If + past simple, would + bare infinitive. "If I had money, I would travel" = I don\'t have money (unreal). Type 1: if + present, will + inf (real possibility).');

add_expl($pdo, 'What is the comparative form of "good" ?', 'A',
    '"Good" has an irregular comparative: good â†’ better â†’ best. We DON\'T say "gooder" or "more good". Other irregulars: bad/worse/worst, far/farther(further)/farthest(furthest).');

add_expl($pdo, 'Choose the correct question tag: "She is a teacher, ___" ?', 'A',
    'Question tags use the auxiliary verb of the main clause, reversed: positive statement â†’ negative tag. "She IS a teacher" â†’ "isn\'t she?". If negative statement â†’ positive tag: "She isn\'t a teacher, is she?"');

add_expl($pdo, 'What does "perseverance" mean ?', 'A',
    '"Perseverance" means continued effort and determination despite difficulties or obstacles. From Latin "perseverare" (to persist). Related: "persevere" (verb), "perseverant" (adj). Synonyms: persistence, tenacity, determination.');

add_expl($pdo, 'What is an antonym of "courageous" ?', 'A',
    '"Courageous" means brave, fearless. Its antonym (opposite) is "cowardly" (showing lack of courage). "Brave" and "bold" are synonyms of courageous, not antonyms.');

/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   SCIENCES
â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
echo "[" . date('H:i:s') . "] ðŸ”¬ Sciences...\n";

add_expl($pdo, 'Quel est l\'organe qui filtre le sang et produit l\'urine ?', 'A',
    'Les reins filtrent environ 180 litres de sang par jour, ne laissant passer que 1,5 Ã  2L d\'urine. Ils rÃ©gulent aussi la pression artÃ©rielle et l\'Ã©quilibre des Ã©lectrolytes (sodium, potassium).');

add_expl($pdo, 'Quelle est la planÃ¨te la plus proche du Soleil ?', 'A',
    'Mercure est la planÃ¨te la plus proche du Soleil (distance moyenne : 58 millions de km). MalgrÃ© sa proximitÃ©, elle n\'est pas la plus chaude (VÃ©nus l\'est, grÃ¢ce Ã  son effet de serre intense).');

add_expl($pdo, 'Le Soleil est une Ã©toile de type :', 'A',
    'Le Soleil est une naine jaune de type spectral G2V. Sa surface est Ã  ~5 500Â°C. Il est Ã  mi-vie (4,6 milliards d\'annÃ©es). Dans ~5 milliards d\'annÃ©es, il deviendra une gÃ©ante rouge puis une naine blanche.');

add_expl($pdo, 'Quel gaz reprÃ©sente environ 78% de l\'atmosphÃ¨re terrestre ?', 'A',
    'L\'air est composÃ© de : 78% azote (Nâ‚‚), 21% oxygÃ¨ne (Oâ‚‚), 1% argon (Ar) et des traces de COâ‚‚ (0,04%), vapeur d\'eau... L\'azote est inerte, c\'est l\'oxygÃ¨ne qui sert Ã  la respiration.');

add_expl($pdo, 'La force qui attire les objets vers le centre de la Terre s\'appelle :', 'A',
    'La gravitÃ© (ou pesanteur) est la force d\'attraction gravitationnelle exercÃ©e par la Terre sur tous les corps. Elle donne aux objets leur "poids". g â‰ˆ 9,8 m/sÂ² Ã  la surface de la Terre.');

add_expl($pdo, 'Quelle transformation de l\'eau correspond au passage de l\'Ã©tat liquide Ã  gazeux ?', 'A',
    'L\'Ã©vaporation est le passage de l\'Ã©tat LIQUIDE Ã  l\'Ã©tat GAZEUX. La fusion = solide â†’ liquide. La condensation = gaz â†’ liquide. La solidification = liquide â†’ solide. La sublimation = solide â†’ gaz directement.');

add_expl($pdo, 'Quel est l\'appareil utilisÃ© pour mesurer la pression atmosphÃ©rique ?', 'A',
    'Le baromÃ¨tre mesure la pression atmosphÃ©rique (en hPa ou mmHg). InventÃ© par Torricelli en 1643. La pression normale au niveau de la mer est de 1013 hPa (= 1 atmosphÃ¨re = 760 mmHg).');

add_expl($pdo, 'Combien de planÃ¨tes compte notre systÃ¨me solaire ?', 'A',
    'Depuis 2006, le systÃ¨me solaire compte officiellement 8 planÃ¨tes : Mercure, VÃ©nus, Terre, Mars, Jupiter, Saturne, Uranus, Neptune. Pluton a Ã©tÃ© reclassÃ© en "planÃ¨te naine". MnÃ©motechnique : Mon Vieux, Tu Mourras Jeudi, Samedi Nuit (MVTMJSN).');

add_expl($pdo, 'Qu\'est-ce que la biodiversitÃ© ?', 'A',
    'La biodiversitÃ© dÃ©signe la variÃ©tÃ© des formes de vie sur Terre : diversitÃ© des espÃ¨ces, des gÃ¨nes et des Ã©cosystÃ¨mes. Elle est menacÃ©e par la dÃ©forestation, la pollution et le changement climatique. La RDC est l\'un des pays les plus riches en biodiversitÃ©.');

add_expl($pdo, 'La couche d\'ozone protÃ¨ge la Terre des rayons :', 'A',
    'La couche d\'ozone (Oâ‚ƒ) dans la stratosphÃ¨re absorbe 97 Ã  99% des rayons UV-B et UV-C du Soleil. Ces rayons peuvent causer des cancers de la peau et des cataractes. Elle est menacÃ©e par les CFC (chlorofluorocarbures).');

add_expl($pdo, 'La tempÃ©rature d\'Ã©bullition de l\'eau Ã  pression normale :', 'A',
    'L\'eau bout Ã  100Â°C Ã  la pression atmosphÃ©rique standard (1013 hPa). Ã€ altitude Ã©levÃ©e (pression plus basse), l\'eau bout Ã  moins de 100Â°C. Dans un autocuiseur (pression Ã©levÃ©e), elle bout au-dessus de 100Â°C.');

add_expl($pdo, 'Quel gaz est rejetÃ© par les animaux lors de la respiration ?', 'A',
    'Les animaux rejettent du COâ‚‚ (dioxyde de carbone) lors de la respiration. Ce COâ‚‚ est produit par la dÃ©gradation du glucose dans les cellules. Les plantes utilisent ce COâ‚‚ pour la photosynthÃ¨se (cycle du carbone).');

add_expl($pdo, 'L\'effet de serre naturel est essentiel car il :', 'A',
    'Sans l\'effet de serre naturel, la tempÃ©rature moyenne de la Terre serait de âˆ’18Â°C au lieu de +15Â°C. Les gaz Ã  effet de serre (Hâ‚‚O, COâ‚‚, CHâ‚„) retiennent la chaleur. Le problÃ¨me actuel est l\'effet de serre AMPLIFIÃ‰ par les activitÃ©s humaines.');

/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   FIN â€” RÃ©sumÃ©
â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
echo "\n[" . date('H:i:s') . "] âœ… Explications ajoutÃ©es : $updated questions mises Ã  jour.\n";
echo "[" . date('H:i:s') . "] ðŸ’¡ Les explications apparaissent maintenant lors de la rÃ©vision des rÃ©ponses.\n";

