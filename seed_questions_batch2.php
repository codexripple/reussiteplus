<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_admin(); // Script restreint aux admins
// ============================================================
// RÃ‰USSITE+ | Seed Questions Batch 2 â€” 120 nouvelles questions
// AccÃ¨s local uniquement
// ============================================================
if (php_uname('n') !== gethostname() && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'])) {
    http_response_code(403); exit('AccÃ¨s refusÃ©.');
}
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$inserted = 0;
$skipped  = 0;

// IDs matiÃ¨res
$M = [
    'maths'   => '0d8077c4-47bd-11f1-84f5-f0d5bfbb3a4f',
    'francais'=> '0d80fad3-47bd-11f1-84f5-f0d5bfbb3a4f',
    'sciences'=> '0d818604-47bd-11f1-84f5-f0d5bfbb3a4f',
    'histgeo' => '0d81f29b-47bd-11f1-84f5-f0d5bfbb3a4f',
    'chimie'  => '0d82633d-47bd-11f1-84f5-f0d5bfbb3a4f',
    'physique'=> '0d829f20-47bd-11f1-84f5-f0d5bfbb3a4f',
    'biologie'=> '0d82d977-47bd-11f1-84f5-f0d5bfbb3a4f',
    'anglais' => '0d8313f0-47bd-11f1-84f5-f0d5bfbb3a4f',
];

function addQ(array $M, int &$inserted, int &$skipped,
              string $enonce, string $mat, string $examType, int $annee,
              array $opts, string $correctLettre, string $explication = ''): void
{
    $exists = dbRow(
        "SELECT id FROM question_bank WHERE enonce=? AND exam_type=? AND annee_source=?",
        [$enonce, $examType, $annee]
    );
    if ($exists) { $skipped++; return; }

    $qid = dbInsert('question_bank', [
        'matiere_id'    => $M[$mat],
        'enonce'        => $enonce,
        'type_question' => 'QCM',
        'exam_type'     => $examType,
        'annee_source'  => $annee,
        'status'        => 'PUBLIE',
    ]);

    $lettres = ['A','B','C','D'];
    foreach ($opts as $i => $texte) {
        $lettre = $lettres[$i];
        dbInsert('question_options', [
            'question_id'  => $qid,
            'lettre'       => $lettre,
            'texte'        => $texte,
            'est_correcte' => $lettre === $correctLettre ? 1 : 0,
            'explication'  => $lettre === $correctLettre ? $explication : '',
        ]);
    }
    $inserted++;
}

// ============================================================
// ENAFEP â€” Fin Primaire (40 questions)
// ============================================================

// MATHS ENAFEP (15)
addQ($M,$inserted,$skipped,"La valeur de 3/4 + 1/2 est :",
    'maths','ENAFEP',2025,['5/6','5/4','4/6','2/3'],'B',
    '3/4 + 1/2 = 3/4 + 2/4 = 5/4 = 1,25');

addQ($M,$inserted,$skipped,"Un rectangle a une longueur de 8 cm et une largeur de 5 cm. Son aire est :",
    'maths','ENAFEP',2025,['26 cmÂ²','13 cmÂ²','40 cmÂ²','30 cmÂ²'],'C',
    'Aire = longueur Ã— largeur = 8 Ã— 5 = 40 cmÂ²');

addQ($M,$inserted,$skipped,"45% de 200 est Ã©gal Ã  :",
    'maths','ENAFEP',2025,['80','85','95','90'],'D',
    '45/100 Ã— 200 = 90');

addQ($M,$inserted,$skipped,"Le plus grand nombre premier infÃ©rieur Ã  20 est :",
    'maths','ENAFEP',2025,['17','16','18','19'],'D',
    '19 est premier (divisible uniquement par 1 et 19)');

addQ($M,$inserted,$skipped,"2,5 Ã— 4 = ?",
    'maths','ENAFEP',2025,['8','8,5','10','9'],'C',
    '2,5 Ã— 4 = 10');

addQ($M,$inserted,$skipped,"Un train roule Ã  120 km/h. En 2h30min, il parcourt :",
    'maths','ENAFEP',2025,['240 km','260 km','280 km','300 km'],'D',
    '2h30min = 2,5h. Distance = 120 Ã— 2,5 = 300 km');

addQ($M,$inserted,$skipped,"L'angle intÃ©rieur d'un carrÃ© vaut :",
    'maths','ENAFEP',2025,['45Â°','60Â°','90Â°','180Â°'],'C',
    'Un carrÃ© a 4 angles droits, chacun valant 90Â°');

addQ($M,$inserted,$skipped,"3Â² + 4Â² = ?",
    'maths','ENAFEP',2025,['14','25','49','7'],'B',
    '3Â² = 9, 4Â² = 16, 9 + 16 = 25');

addQ($M,$inserted,$skipped,"Un article coÃ»te 3 600 CDF avec une remise de 25%. Le prix Ã  payer est :",
    'maths','ENAFEP',2025,['2 800 CDF','3 000 CDF','2 700 CDF','2 400 CDF'],'C',
    'Remise = 25% Ã— 3600 = 900 CDF. Prix = 3600 - 900 = 2 700 CDF');

addQ($M,$inserted,$skipped,"La moyenne des nombres 12, 16, 18 et 14 est :",
    'maths','ENAFEP',2025,['14','16','13','15'],'D',
    '(12+16+18+14) Ã· 4 = 60 Ã· 4 = 15');

addQ($M,$inserted,$skipped,"Combien de faces a un cube ?",
    'maths','ENAFEP',2025,['4','8','12','6'],'D',
    'Un cube a 6 faces carrÃ©es');

addQ($M,$inserted,$skipped,"1 000 millilitres Ã©quivalent Ã  :",
    'maths','ENAFEP',2025,['10 L','100 L','0,1 L','1 L'],'D',
    '1 000 mL = 1 L');

addQ($M,$inserted,$skipped,"Le pÃ©rimÃ¨tre d'un cercle de rayon 7 cm est (Ï€ â‰ˆ 22/7) :",
    'maths','ENAFEP',2025,['44 cm','22 cm','28 cm','154 cmÂ²'],'A',
    'PÃ©rimÃ¨tre = 2Ï€r = 2 Ã— (22/7) Ã— 7 = 44 cm');

addQ($M,$inserted,$skipped,"72 Ã· 8 Ã— 3 = ?",
    'maths','ENAFEP',2025,['3','21','24','27'],'D',
    'On calcule de gauche Ã  droite : 72 Ã· 8 = 9, puis 9 Ã— 3 = 27');

addQ($M,$inserted,$skipped,"Un Ã©lÃ¨ve obtient 48 sur 80. Sa note sur 20 est :",
    'maths','ENAFEP',2025,['10','11','12','13'],'C',
    '(48 Ã· 80) Ã— 20 = 12');

// FRANÃ‡AIS ENAFEP (15)
addQ($M,$inserted,$skipped,"Le pluriel de Â« cheval Â» est :",
    'francais','ENAFEP',2025,['chevals','chevales','chevaus','chevaux'],'D',
    'Cheval â†’ chevaux (pluriel irrÃ©gulier en -aux)');

addQ($M,$inserted,$skipped,"Dans la phrase Â« Le chat mange la souris Â», Â« la souris Â» est :",
    'francais','ENAFEP',2025,['sujet','attribut','complÃ©ment d\'objet indirect','complÃ©ment d\'objet direct'],'D',
    'La souris rÃ©pond Ã  la question Â« mange quoi ? Â» â†’ COD');

addQ($M,$inserted,$skipped,"Le contraire de Â« gÃ©nÃ©reux Â» est :",
    'francais','ENAFEP',2025,['avare','courageux','aimable','riche'],'A',
    'GÃ©nÃ©reux â†” avare');

addQ($M,$inserted,$skipped,"Â« Il faisait beau Â» est Ã  quel temps verbal ?",
    'francais','ENAFEP',2025,['passÃ© composÃ©','prÃ©sent','futur','imparfait'],'D',
    'Faisait = imparfait de l\'indicatif du verbe faire');

addQ($M,$inserted,$skipped,"Le synonyme le plus proche de Â« rapide Â» est :",
    'francais','ENAFEP',2025,['lent','hÃ¢tif','prompt','vif'],'C',
    'Prompt signifie qui agit ou se produit rapidement');

addQ($M,$inserted,$skipped,"Le fÃ©minin de Â« instituteur Â» est :",
    'francais','ENAFEP',2025,['instituteuse','institutrisse','instituteure','institutrice'],'D',
    'Instituteur â†’ institutrice');

addQ($M,$inserted,$skipped,"Quelle phrase contient une faute grammaticale ?",
    'francais','ENAFEP',2025,['Il chante bien','Nous mangeons','Vous dormez','Elle sont parties'],'D',
    'Le sujet Â« Elle Â» est singulier, il faut Ã©crire Â« Elle est partie Â»');

addQ($M,$inserted,$skipped,"Â« Il a mangÃ© Â» est au :",
    'francais','ENAFEP',2025,['imparfait','plus-que-parfait','futur antÃ©rieur','passÃ© composÃ©'],'D',
    'A + participe passÃ© = passÃ© composÃ©');

addQ($M,$inserted,$skipped,"Dans Â« La belle rose rouge Â», Â« belle Â» et Â« rouge Â» sont des :",
    'francais','ENAFEP',2025,['noms','verbes','adverbes','adjectifs'],'D',
    'Belle et rouge qualifient le nom rose â†’ adjectifs qualificatifs');

addQ($M,$inserted,$skipped,"Quel signe de ponctuation termine une question ?",
    'francais','ENAFEP',2025,['.','!',';','?'],'D',
    'Une phrase interrogative se termine par un point d\'interrogation');

addQ($M,$inserted,$skipped,"Le pluriel de Â« voix Â» est :",
    'francais','ENAFEP',2025,['voixs','vois','voixt','voix'],'D',
    'Voix se termine dÃ©jÃ  par x, son pluriel est identique : voix');

addQ($M,$inserted,$skipped,"Le mot Â« immobile Â» signifie :",
    'francais','ENAFEP',2025,['trÃ¨s mobile','qui bouge lentement','difficile Ã  bouger','qui ne bouge pas'],'D',
    'Im- est un prÃ©fixe nÃ©gatif. Immobile = qui ne bouge pas');

addQ($M,$inserted,$skipped,"Â« Vite Â» est un :",
    'francais','ENAFEP',2025,['nom','adjectif','verbe','adverbe'],'D',
    'Vite modifie un verbe ou un adjectif â†’ c\'est un adverbe');

addQ($M,$inserted,$skipped,"Le mot Â« bibliothÃ¨que Â» vient du grec Â« biblion Â» qui signifie :",
    'francais','ENAFEP',2025,['lecture','savoir','papier','livre'],'D',
    'Biblion = livre en grec ; thÃ¨que = armoire/lieu de rangement');

addQ($M,$inserted,$skipped,"Dans la phrase Â« Les oiseaux chantent joyeusement Â», le sujet est :",
    'francais','ENAFEP',2025,['chantent','joyeusement','les oiseaux','oiseaux'],'C',
    'Le sujet rÃ©pond Ã  la question Â« qui est-ce qui chante ? Â» â†’ les oiseaux');

// SCIENCES ENAFEP (10)
addQ($M,$inserted,$skipped,"La photosynthÃ¨se se produit principalement dans :",
    'sciences','ENAFEP',2025,['les racines','les fleurs','les graines','les feuilles'],'D',
    'Les feuilles contiennent la chlorophylle qui capte l\'Ã©nergie solaire');

addQ($M,$inserted,$skipped,"L'organe qui pompe le sang dans le corps est :",
    'sciences','ENAFEP',2025,['le poumon','le foie','le rein','le cÅ“ur'],'D',
    'Le cÅ“ur est la pompe du systÃ¨me circulatoire');

addQ($M,$inserted,$skipped,"L'eau entre en Ã©bullition Ã  :",
    'sciences','ENAFEP',2025,['0 Â°C','50 Â°C','200 Â°C','100 Â°C'],'D',
    'Ã€ pression normale, l\'eau bout Ã  100 Â°C');

addQ($M,$inserted,$skipped,"Parmi ces animaux, lequel est un mammifÃ¨re ?",
    'sciences','ENAFEP',2025,['le crocodile','le perroquet','la grenouille','la baleine'],'D',
    'La baleine respire Ã  l\'air, allaite ses petits â†’ mammifÃ¨re marin');

addQ($M,$inserted,$skipped,"Le Soleil est :",
    'sciences','ENAFEP',2025,['une planÃ¨te','une lune','une comÃ¨te','une Ã©toile'],'D',
    'Le Soleil est une Ã©toile de type naine jaune');

addQ($M,$inserted,$skipped,"L'organe principal de la respiration est :",
    'sciences','ENAFEP',2025,['l\'estomac','le cÅ“ur','le foie','le poumon'],'D',
    'Les poumons permettent les Ã©changes gazeux Oâ‚‚/COâ‚‚');

addQ($M,$inserted,$skipped,"L'eau gÃ¨le Ã  :",
    'sciences','ENAFEP',2025,['-10 Â°C','4 Â°C','100 Â°C','0 Â°C'],'D',
    'Le point de congÃ©lation de l\'eau est 0 Â°C Ã  pression normale');

addQ($M,$inserted,$skipped,"Le squelette humain adulte compte environ :",
    'sciences','ENAFEP',2025,['106 os','306 os','406 os','206 os'],'D',
    'Le corps humain adulte possÃ¨de en moyenne 206 os');

addQ($M,$inserted,$skipped,"La planÃ¨te la plus proche du Soleil est :",
    'sciences','ENAFEP',2025,['VÃ©nus','Terre','Mars','Mercure'],'D',
    'Mercure est la planÃ¨te la plus proche du Soleil dans le systÃ¨me solaire');

addQ($M,$inserted,$skipped,"Les plantes produisent de l'oxygÃ¨ne grÃ¢ce Ã  :",
    'sciences','ENAFEP',2025,['la respiration','la transpiration','la digestion','la photosynthÃ¨se'],'D',
    'La photosynthÃ¨se utilise COâ‚‚ + eau + lumiÃ¨re pour produire glucose + Oâ‚‚');

// ============================================================
// TENASOSP â€” Fin Secondaire (40 questions)
// ============================================================

// MATHS TENASOSP (10)
addQ($M,$inserted,$skipped,"La dÃ©rivÃ©e de f(x) = xÂ³ âˆ’ 2x + 1 est :",
    'maths','TENASOSP',2025,['3xÂ²','xÂ² âˆ’ 2','3x + 1','3xÂ² âˆ’ 2'],'D',
    'RÃ¨gle de dÃ©rivation : d/dx(xâ¿) = nxâ¿â»Â¹ et d/dx(cste) = 0 â†’ 3xÂ² âˆ’ 2');

addQ($M,$inserted,$skipped,"logâ‚â‚€(1 000) = ?",
    'maths','TENASOSP',2025,['2','4','10','3'],'D',
    '10Â³ = 1 000 donc logâ‚â‚€(1 000) = 3');

addQ($M,$inserted,$skipped,"sinÂ²(x) + cosÂ²(x) = ?",
    'maths','TENASOSP',2025,['0','2','sin(2x)','1'],'D',
    'IdentitÃ© fondamentale de la trigonomÃ©trie');

addQ($M,$inserted,$skipped,"L'Ã©quation xÂ² âˆ’ 5x + 6 = 0 a pour solutions :",
    'maths','TENASOSP',2025,['x=1 et x=6','x=âˆ’2 et x=âˆ’3','x=2 et x=âˆ’3','x=2 et x=3'],'D',
    'Discriminant Î” = 25 âˆ’ 24 = 1. x = (5Â±1)/2 â†’ x=3 ou x=2');

addQ($M,$inserted,$skipped,"âˆ« 2x dx = ?",
    'maths','TENASOSP',2025,['2','2xÂ² + C','x + C','xÂ² + C'],'D',
    'RÃ¨gle d\'intÃ©gration : âˆ«xâ¿ dx = xâ¿âºÂ¹/(n+1) â†’ âˆ«2x dx = xÂ² + C');

addQ($M,$inserted,$skipped,"La limite de (xÂ² âˆ’ 1)/(x âˆ’ 1) quand x â†’ 1 est :",
    'maths','TENASOSP',2025,['0','1','indÃ©finie','2'],'D',
    'xÂ²âˆ’1 = (xâˆ’1)(x+1), donc la fraction = x+1 â†’ limite = 1+1 = 2');

addQ($M,$inserted,$skipped,"Dans une sÃ©rie statistique, la valeur la plus frÃ©quente est :",
    'maths','TENASOSP',2025,['la moyenne','la mÃ©diane','l\'Ã©tendue','le mode'],'D',
    'Le mode est la valeur qui apparaÃ®t le plus souvent dans la sÃ©rie');

addQ($M,$inserted,$skipped,"Le nombre complexe (2 + 3i)(2 âˆ’ 3i) vaut :",
    'maths','TENASOSP',2025,['4 âˆ’ 9iÂ²','1','4 + 9','13'],'D',
    '(2+3i)(2âˆ’3i) = 4 âˆ’ 9iÂ² = 4 âˆ’ 9(âˆ’1) = 4 + 9 = 13');

addQ($M,$inserted,$skipped,"Si P(A) = 0,3 et P(B) = 0,5 avec A et B indÃ©pendants, P(A âˆ© B) = ?",
    'maths','TENASOSP',2025,['0,8','0,2','0,35','0,15'],'D',
    'P(A âˆ© B) = P(A) Ã— P(B) = 0,3 Ã— 0,5 = 0,15');

addQ($M,$inserted,$skipped,"Un vecteur de module 5 faisant 60Â° avec l'axe x a pour composante horizontale :",
    'maths','TENASOSP',2025,['5','2,5','5âˆš3/2','5âˆš2/2'],'B',
    'Composante x = 5 Ã— cos(60Â°) = 5 Ã— 0,5 = 2,5');

// CHIMIE TENASOSP (10)
addQ($M,$inserted,$skipped,"Le numÃ©ro atomique de l'oxygÃ¨ne est :",
    'chimie','TENASOSP',2025,['6','7','16','8'],'D',
    'L\'oxygÃ¨ne (O) a 8 protons dans son noyau â†’ Z = 8');

addQ($M,$inserted,$skipped,"Un acide de BrÃ¸nsted est une substance qui :",
    'chimie','TENASOSP',2025,['libÃ¨re des OHâ»','est neutre','rÃ©agit avec les mÃ©taux','libÃ¨re des Hâº'],'D',
    'Selon BrÃ¸nsted-Lowry, un acide est un donneur de protons Hâº');

addQ($M,$inserted,$skipped,"Le pH d'une solution neutre est :",
    'chimie','TENASOSP',2025,['0','5','14','7'],'D',
    'Une solution neutre a [Hâº] = [OHâ»] = 10â»â· mol/L â†’ pH = 7');

addQ($M,$inserted,$skipped,"La formule du dioxyde de carbone est :",
    'chimie','TENASOSP',2025,['CO','Câ‚‚O','Câ‚‚Oâ‚ƒ','COâ‚‚'],'D',
    'Un atome de carbone liÃ© Ã  deux atomes d\'oxygÃ¨ne â†’ COâ‚‚');

addQ($M,$inserted,$skipped,"En nomenclature organique, le suffixe Â« -ane Â» dÃ©signe :",
    'chimie','TENASOSP',2025,['les alcools','les alcÃ¨nes','les aldÃ©hydes','les alcanes'],'D',
    'Les alcanes sont des hydrocarbures saturÃ©s (ex: mÃ©thane, Ã©thane, propane)');

addQ($M,$inserted,$skipped,"La mole contient environ :",
    'chimie','TENASOSP',2025,['6,02Ã—10Â²Â²','6,02Ã—10Â²â´','6,02Ã—10Â²â°','6,02Ã—10Â²Â³'],'D',
    'Le nombre d\'Avogadro NA = 6,02Ã—10Â²Â³ entitÃ©s par mole');

addQ($M,$inserted,$skipped,"L'Ã©lÃ©ment avec l'Ã©lectronÃ©gativitÃ© la plus Ã©levÃ©e est :",
    'chimie','TENASOSP',2025,['l\'oxygÃ¨ne','l\'azote','le chlore','le fluor'],'D',
    'Le fluor (F) est l\'Ã©lÃ©ment le plus Ã©lectronÃ©gatif selon l\'Ã©chelle de Pauling');

addQ($M,$inserted,$skipped,"La rÃ©action de saponification entre un corps gras et NaOH produit :",
    'chimie','TENASOSP',2025,['une amine','un ester','un acide','du savon et de la glycÃ©rine'],'D',
    'Corps gras + NaOH â†’ savon (sel d\'acide gras) + glycÃ©rine');

addQ($M,$inserted,$skipped,"La formule chimique de l'acide sulfurique est :",
    'chimie','TENASOSP',2025,['HCl','Hâ‚‚SOâ‚ƒ','HNOâ‚ƒ','Hâ‚‚SOâ‚„'],'D',
    'L\'acide sulfurique est Hâ‚‚SOâ‚„ (diprÃ´tique, trÃ¨s corrosif)');

addQ($M,$inserted,$skipped,"Dans la rÃ©action Zn + 2HCl â†’ ZnClâ‚‚ + Hâ‚‚, le zinc est :",
    'chimie','TENASOSP',2025,['rÃ©duit','oxydÃ© et rÃ©duit','ni oxydÃ© ni rÃ©duit','oxydÃ©'],'D',
    'Zn â†’ ZnÂ²âº : perd 2 Ã©lectrons â†’ il est oxydÃ©');

// PHYSIQUE TENASOSP (10)
addQ($M,$inserted,$skipped,"Si m = 5 kg et g = 10 m/sÂ², la force gravitationnelle F = mg vaut :",
    'physique','TENASOSP',2025,['2 N','15 N','5 N','50 N'],'D',
    'F = 5 Ã— 10 = 50 N');

addQ($M,$inserted,$skipped,"La vitesse de la lumiÃ¨re dans le vide est approximativement :",
    'physique','TENASOSP',2025,['3Ã—10â¶ m/s','3Ã—10Â¹â° m/s','3Ã—10â´ m/s','3Ã—10â¸ m/s'],'D',
    'c â‰ˆ 3Ã—10â¸ m/s = 300 000 km/s');

addQ($M,$inserted,$skipped,"La rÃ©sistance Ã©quivalente de Râ‚ = 6 Î© et Râ‚‚ = 3 Î© en parallÃ¨le est :",
    'physique','TENASOSP',2025,['9 Î©','3 Î©','18 Î©','2 Î©'],'D',
    '1/Req = 1/6 + 1/3 = 1/6 + 2/6 = 3/6 = 1/2 â†’ Req = 2 Î©');

addQ($M,$inserted,$skipped,"La loi d'Ohm s'Ã©crit :",
    'physique','TENASOSP',2025,['P = UI','F = ma','E = mcÂ²','U = RI'],'D',
    'U = tension (V), R = rÃ©sistance (Î©), I = intensitÃ© (A)');

addQ($M,$inserted,$skipped,"Un miroir plan forme une image :",
    'physique','TENASOSP',2025,['rÃ©elle et droite','virtuelle et renversÃ©e','rÃ©elle et renversÃ©e','virtuelle et droite'],'D',
    'L\'image dans un miroir plan est virtuelle (derriÃ¨re le miroir), droite et de mÃªme taille');

addQ($M,$inserted,$skipped,"L'unitÃ© de la frÃ©quence est :",
    'physique','TENASOSP',2025,['mÃ¨tre','watt','newton','hertz'],'D',
    '1 Hz = 1 cycle par seconde');

addQ($M,$inserted,$skipped,"L'Ã©nergie cinÃ©tique d'un objet de masse m et vitesse v est :",
    'physique','TENASOSP',2025,['mgh','RIÂ²','QV','Â½mvÂ²'],'D',
    'Ec = Â½mvÂ² (en joules si m en kg et v en m/s)');

addQ($M,$inserted,$skipped,"Un courant de 2 A traverse une rÃ©sistance de 4 Î©. La tension est :",
    'physique','TENASOSP',2025,['2 V','6 V','0,5 V','8 V'],'D',
    'U = R Ã— I = 4 Ã— 2 = 8 V');

addQ($M,$inserted,$skipped,"La rÃ©fraction de la lumiÃ¨re obÃ©it Ã  la loi de :",
    'physique','TENASOSP',2025,['Ohm','Newton','Faraday','Snell-Descartes'],'D',
    'nâ‚Â·sin(Î¸â‚) = nâ‚‚Â·sin(Î¸â‚‚) est la loi de Snell-Descartes');

addQ($M,$inserted,$skipped,"La pÃ©riode d'un pendule simple dÃ©pend principalement de :",
    'physique','TENASOSP',2025,['sa masse','son amplitude','sa couleur','sa longueur'],'D',
    'T = 2Ï€âˆš(l/g) : la pÃ©riode ne dÃ©pend que de la longueur l et de g');

// BIOLOGIE TENASOSP (10)
addQ($M,$inserted,$skipped,"L'ADN se trouve principalement dans :",
    'biologie','TENASOSP',2025,['la mitochondrie','le cytoplasme','la membrane','le noyau'],'D',
    'Le noyau contient l\'ADN organisÃ© en chromosomes');

addQ($M,$inserted,$skipped,"La respiration cellulaire aÃ©robie produit :",
    'biologie','TENASOSP',2025,['Oâ‚‚ et Hâ‚‚O','Oâ‚‚ et COâ‚‚','Hâ‚‚O et glucose','COâ‚‚ et Hâ‚‚O'],'D',
    'Glucose + Oâ‚‚ â†’ COâ‚‚ + Hâ‚‚O + Ã©nergie (ATP)');

addQ($M,$inserted,$skipped,"Le nombre de chromosomes dans une cellule humaine diploÃ¯de est :",
    'biologie','TENASOSP',2025,['23','44','48','46'],'D',
    '2n = 46 chromosomes (23 paires) dans les cellules somatiques humaines');

addQ($M,$inserted,$skipped,"La mitose produit :",
    'biologie','TENASOSP',2025,['4 cellules haploÃ¯des','4 cellules diploÃ¯des','2 cellules haploÃ¯des','2 cellules diploÃ¯des identiques'],'D',
    'La mitose est une division cellulaire produisant 2 cellules filles gÃ©nÃ©tiquement identiques Ã  la cellule mÃ¨re');

addQ($M,$inserted,$skipped,"L'enzyme qui catalyse la digestion de l'amidon est :",
    'biologie','TENASOSP',2025,['la lipase','la pepsine','la protÃ©ase','l\'amylase'],'D',
    'L\'amylase (salivaire et pancrÃ©atique) hydrolyse l\'amidon en maltose');

addQ($M,$inserted,$skipped,"Le groupe sanguin O est donneur universel car ses globules rouges :",
    'biologie','TENASOSP',2025,['contiennent les deux antigÃ¨nes A et B','contiennent l\'antigÃ¨ne O','ont plus d\'hÃ©moglobine','ne portent ni antigÃ¨ne A ni antigÃ¨ne B'],'D',
    'L\'absence d\'antigÃ¨nes A et B Ã©vite les rÃ©actions immunitaires chez le receveur');

addQ($M,$inserted,$skipped,"L'insuline est sÃ©crÃ©tÃ©e par :",
    'biologie','TENASOSP',2025,['le foie','les reins','la thyroÃ¯de','le pancrÃ©as'],'D',
    'Les cellules bÃªta des Ã®lots de Langerhans du pancrÃ©as sÃ©crÃ¨tent l\'insuline');

addQ($M,$inserted,$skipped,"La membrane plasmique contrÃ´le :",
    'biologie','TENASOSP',2025,['la synthÃ¨se d\'ADN','la production d\'Ã©nergie','la division cellulaire','les Ã©changes entre la cellule et son milieu'],'D',
    'La membrane est sÃ©lectivement permÃ©able et rÃ©gule les entrÃ©es/sorties');

addQ($M,$inserted,$skipped,"La mÃ©iose est une division qui produit :",
    'biologie','TENASOSP',2025,['2 cellules diploÃ¯des','8 cellules haploÃ¯des','2 cellules haploÃ¯des','4 cellules haploÃ¯des'],'D',
    'La mÃ©iose comporte 2 divisions successives et produit 4 cellules haploÃ¯des (gamÃ¨tes)');

addQ($M,$inserted,$skipped,"Les virus sont des entitÃ©s :",
    'biologie','TENASOSP',2025,['procaryotes','eucaryotes','bactÃ©riennes','acellulaires'],'D',
    'Les virus ne possÃ¨dent pas de structure cellulaire propre, ils sont acellulaires');

// ============================================================
// EXAMEN D'Ã‰TAT (40 questions)
// ============================================================

// MATHS EXAMEN_ETAT (10)
addQ($M,$inserted,$skipped,"La dÃ©rivÃ©e de f(x) = e^(2x) est :",
    'maths','EXAMEN_ETAT',2025,['e^(2x)','e^(2x)/2','2xe^x','2e^(2x)'],'D',
    'RÃ¨gle de dÃ©rivation des fonctions composÃ©es : f\'(x) = 2Â·e^(2x)');

addQ($M,$inserted,$skipped,"âˆ«â‚€Â¹ xÂ² dx = ?",
    'maths','EXAMEN_ETAT',2025,['1','1/2','2/3','1/3'],'D',
    '[xÂ³/3]â‚€Â¹ = 1/3 âˆ’ 0 = 1/3');

addQ($M,$inserted,$skipped,"ln(eÂ³) = ?",
    'maths','EXAMEN_ETAT',2025,['3e','eÂ³','1','3'],'D',
    'ln et exp sont des fonctions rÃ©ciproques â†’ ln(eÂ³) = 3');

addQ($M,$inserted,$skipped,"La somme d'une progression gÃ©omÃ©trique de raison 2, premier terme 1, sur 5 termes est :",
    'maths','EXAMEN_ETAT',2025,['15','63','32','31'],'D',
    'S = 1+2+4+8+16 = 31. Formule : Sn = a(râ¿âˆ’1)/(râˆ’1) = 1Ã—(32âˆ’1)/1 = 31');

addQ($M,$inserted,$skipped,"Le dÃ©terminant de la matrice [[1, 2], [3, 4]] est :",
    'maths','EXAMEN_ETAT',2025,['2','âˆ’4','10','âˆ’2'],'D',
    'det = 1Ã—4 âˆ’ 2Ã—3 = 4 âˆ’ 6 = âˆ’2');

addQ($M,$inserted,$skipped,"La tangente Ã  la courbe y = xÂ² au point (2, 4) a pour Ã©quation :",
    'maths','EXAMEN_ETAT',2025,['y = 2x','y = 4x','y = 4x + 4','y = 4x âˆ’ 4'],'D',
    'f\'(2) = 2Ã—2 = 4 (pente). Ã‰quation : y âˆ’ 4 = 4(x âˆ’ 2) â†’ y = 4x âˆ’ 4');

addQ($M,$inserted,$skipped,"P(X = k) = C(n,k)Â·páµÂ·(1âˆ’p)^(nâˆ’k) est la loi :",
    'maths','EXAMEN_ETAT',2025,['de Poisson','normale','gÃ©omÃ©trique','binomiale'],'D',
    'C\'est la formule de la loi binomiale B(n, p)');

addQ($M,$inserted,$skipped,"L'ensemble des solutions de |2x âˆ’ 1| < 3 est :",
    'maths','EXAMEN_ETAT',2025,['[âˆ’1, 2]',']âˆ’âˆž, âˆ’1[ âˆª ]2, +âˆž[',']1, 3[',']âˆ’1, 2['],'D',
    'âˆ’3 < 2xâˆ’1 < 3 â†’ âˆ’2 < 2x < 4 â†’ âˆ’1 < x < 2');

addQ($M,$inserted,$skipped,"Si les vecteurs (1, 2) et (k, âˆ’3) sont perpendiculaires, alors k vaut :",
    'maths','EXAMEN_ETAT',2025,['âˆ’6','3','âˆ’3','6'],'D',
    'Produit scalaire nul : 1Â·k + 2Â·(âˆ’3) = 0 â†’ k = 6');

addQ($M,$inserted,$skipped,"La solution gÃ©nÃ©rale de l'Ã©quation diffÃ©rentielle y' = 3y est :",
    'maths','EXAMEN_ETAT',2025,['y = Ce^(x/3)','y = 3Cx','y = CÂ·ln(x)','y = Ce^(3x)'],'D',
    'y\'/y = 3 â†’ ln|y| = 3x + K â†’ y = Ce^(3x)');

// FRANÃ‡AIS EXAMEN_ETAT (10)
addQ($M,$inserted,$skipped,"La figure de style dans Â« La vie est un long fleuve tranquille Â» est :",
    'francais','EXAMEN_ETAT',2025,['une mÃ©tonymie','une comparaison','une hyperbole','une mÃ©taphore'],'D',
    'Assimilation sans outil comparatif de la vie Ã  un fleuve â†’ mÃ©taphore');

addQ($M,$inserted,$skipped,"L'auteur des Â« MisÃ©rables Â» est :",
    'francais','EXAMEN_ETAT',2025,['Ã‰mile Zola','HonorÃ© de Balzac','Gustave Flaubert','Victor Hugo'],'D',
    'Les MisÃ©rables (1862) est l\'Å“uvre majeure de Victor Hugo');

addQ($M,$inserted,$skipped,"La proposition subordonnÃ©e relative dans Â« L'homme qui rit est heureux Â» est :",
    'francais','EXAMEN_ETAT',2025,['L\'homme','est heureux','L\'homme qui rit','qui rit'],'D',
    'La relative est introduite par le pronom relatif Â« qui Â» et qualifie Â« l\'homme Â»');

addQ($M,$inserted,$skipped,"Le mouvement littÃ©raire du XIXe siÃ¨cle qui valorise la nature et les sentiments est :",
    'francais','EXAMEN_ETAT',2025,['le classicisme','le surrÃ©alisme','le naturalisme','le romantisme'],'D',
    'Le romantisme (1800-1850) exalte la sensibilitÃ©, la nature et l\'imagination');

addQ($M,$inserted,$skipped,'Le discours indirect de Â« Il dit : "Je viendrai demain" Â» est :',
    'francais','EXAMEN_ETAT',2025,["Il dit qu'il vient demain","Il dit qu'il est venu","Il dit qu'il viendra demain","Il dit qu'il viendrait le lendemain"],'D',
    'Concordance des temps : venir au futur â†’ conditionnel. Demain â†’ le lendemain');

addQ($M,$inserted,$skipped,"Une anaphore est :",
    'francais','EXAMEN_ETAT',2025,['la rÃ©pÃ©tition d\'un mot en fin de vers','l\'omission d\'un mot','l\'inversion syntaxique','la rÃ©pÃ©tition d\'un mot ou groupe en dÃ©but de phrase/vers'],'D',
    'Ex: Â« Je veux la paix, je veux la justice, je veux la libertÃ© Â»');

addQ($M,$inserted,$skipped,"Â« Les sanglots longs des violons de l'automne Â» est extrait de :",
    'francais','EXAMEN_ETAT',2025,['Rimbaud, Le Bateau ivre','Baudelaire, Spleen','Hugo, Demain dÃ¨s l\'aube','Verlaine, Chanson d\'automne'],'D',
    'Ce vers ouvre le poÃ¨me Â« Chanson d\'automne Â» de Paul Verlaine (1866)');

addQ($M,$inserted,$skipped,"Le futur antÃ©rieur de Â« avoir Â» Ã  la 1Ã¨re personne du singulier est :",
    'francais','EXAMEN_ETAT',2025,['j\'avais eu','j\'aurais eu','j\'ai eu','j\'aurai eu'],'D',
    'Futur antÃ©rieur = auxiliaire au futur simple + participe passÃ© : j\'aurai eu');

addQ($M,$inserted,$skipped,"L'hyperbole est une figure de style qui consiste Ã  :",
    'francais','EXAMEN_ETAT',2025,['attÃ©nuer la rÃ©alitÃ©','comparer deux Ã©lÃ©ments','exagÃ©rer une rÃ©alitÃ© pour insister','inverser l\'ordre des mots'],'C',
    'Ex: Â« Je t\'ai dit mille fois ! Â» â†’ exagÃ©ration volontaire pour renforcer l\'effet');

addQ($M,$inserted,$skipped,"Dans Â« Il pleuvait des cordes Â», le verbe Â« pleuvait des cordes Â» est :",
    'francais','EXAMEN_ETAT',2025,['une litote','une pÃ©riphrase','une allÃ©gorie','une mÃ©taphore lexicalisÃ©e'],'D',
    'Expression figÃ©e dÃ©crivant une forte pluie â†’ mÃ©taphore lexicalisÃ©e (figÃ©e dans la langue)');

// HISTOIRE-GÃ‰OGRAPHIE EXAMEN_ETAT (10)
addQ($M,$inserted,$skipped,"La ConfÃ©rence de Berlin (1884-1885) a principalement :",
    'histgeo','EXAMEN_ETAT',2025,['mis fin Ã  l\'esclavage','crÃ©Ã© la SociÃ©tÃ© des Nations','Ã©tabli les frontiÃ¨res europÃ©ennes','partagÃ© l\'Afrique entre puissances europÃ©ennes'],'D',
    'OrganisÃ©e par Bismarck, elle a fixÃ© les rÃ¨gles du partage de l\'Afrique');

addQ($M,$inserted,$skipped,"L'indÃ©pendance du Congo-Kinshasa a eu lieu le :",
    'histgeo','EXAMEN_ETAT',2025,['1er juillet 1960','30 juin 1962','30 juin 1958','30 juin 1960'],'D',
    'Le 30 juin 1960, le Congo belge accÃ¨de Ã  l\'indÃ©pendance sous Joseph Kasavubu');

addQ($M,$inserted,$skipped,"Le fleuve Congo est le deuxiÃ¨me fleuve du monde par :",
    'histgeo','EXAMEN_ETAT',2025,['sa longueur','son bassin','sa largeur','son dÃ©bit'],'D',
    'Le Congo a un dÃ©bit moyen de ~41 000 mÂ³/s, second aprÃ¨s l\'Amazone');

addQ($M,$inserted,$skipped,"La PremiÃ¨re Guerre mondiale a dÃ©butÃ© en :",
    'histgeo','EXAMEN_ETAT',2025,['1910','1918','1920','1914'],'D',
    'L\'assassinat de l\'archiduc FranÃ§ois-Ferdinand le 28 juin 1914 dÃ©clenche la guerre');

addQ($M,$inserted,$skipped,"L'ONU a Ã©tÃ© fondÃ©e en :",
    'histgeo','EXAMEN_ETAT',2025,['1919','1939','1950','1945'],'D',
    'La Charte des Nations Unies est signÃ©e Ã  San Francisco le 26 juin 1945');

addQ($M,$inserted,$skipped,"La capitale administrative de l'Afrique du Sud est :",
    'histgeo','EXAMEN_ETAT',2025,['Johannesburg','Le Cap','Durban','Pretoria'],'D',
    'L\'Afrique du Sud a 3 capitales : Pretoria (administrative), Le Cap (lÃ©gislative), Bloemfontein (judiciaire)');

addQ($M,$inserted,$skipped,"La population mondiale est estimÃ©e Ã  environ :",
    'histgeo','EXAMEN_ETAT',2025,['6 milliards','7 milliards','9 milliards','8 milliards'],'D',
    'En 2022-2024 la population mondiale a franchi le cap des 8 milliards');

addQ($M,$inserted,$skipped,"Kinshasa se trouve sur le fleuve :",
    'histgeo','EXAMEN_ETAT',2025,['KasaÃ¯','Ubangi','Lualaba','Congo'],'D',
    'Kinshasa est bÃ¢tie sur la rive gauche du fleuve Congo, face Ã  Brazzaville');

addQ($M,$inserted,$skipped,"La dÃ©colonisation africaine a majoritairement eu lieu dans :",
    'histgeo','EXAMEN_ETAT',2025,['les annÃ©es 1940','les annÃ©es 1930','les annÃ©es 1970','les annÃ©es 1950-1960'],'D',
    'La vague des indÃ©pendances africaines se concentre dans les annÃ©es 1957-1965 (Â« AnnÃ©e de l\'Afrique Â» = 1960)');

addQ($M,$inserted,$skipped,"L'Union Africaine (UA) a remplacÃ© l'Organisation de l'UnitÃ© Africaine (OUA) en :",
    'histgeo','EXAMEN_ETAT',2025,['1999','2000','2005','2002'],'D',
    'L\'UA a Ã©tÃ© officiellement crÃ©Ã©e lors du sommet de Durban le 9 juillet 2002');

// ANGLAIS EXAMEN_ETAT (10)
addQ($M,$inserted,$skipped,"The passive voice of 'She writes a letter' is:",
    'anglais','EXAMEN_ETAT',2025,['A letter was written by her','A letter has been written','She is written','A letter is written by her'],'D',
    'Present simple passive: subject + is/am/are + past participle + by + agent');

addQ($M,$inserted,$skipped,"Complete: 'I wish I _____ taller'",
    'anglais','EXAMEN_ETAT',2025,['am','will be','have been','were'],'D',
    'After "wish" we use past subjunctive (were) for present unreal situations');

addQ($M,$inserted,$skipped,"The synonym of 'enormous' is:",
    'anglais','EXAMEN_ETAT',2025,['tiny','average','normal','huge'],'D',
    'Enormous and huge both mean very large in size');

addQ($M,$inserted,$skipped,"'He has been working here _____ 2010'",
    'anglais','EXAMEN_ETAT',2025,['for','during','ago','since'],'D',
    'Since is used with a specific point in time (2010); for is used with a period of time');

addQ($M,$inserted,$skipped,"The plural of 'criterion' is:",
    'anglais','EXAMEN_ETAT',2025,['criterions','criterias','criteriones','criteria'],'D',
    'Criterion is a Latin/Greek borrowing; its plural follows the original form: criteria');

addQ($M,$inserted,$skipped,"'Despite _____ tired, she kept working'",
    'anglais','EXAMEN_ETAT',2025,['to be','she is','been','being'],'D',
    'After prepositions (despite, before, after, etc.) we use the gerund (-ing form)');

addQ($M,$inserted,$skipped,"Which sentence is grammatically correct?",
    'anglais','EXAMEN_ETAT',2025,['She don\'t like coffee','She doesn\'t likes coffee','She not like coffee','She doesn\'t like coffee'],'D',
    '3rd person singular uses does/doesn\'t + base verb (no -s after doesn\'t)');

addQ($M,$inserted,$skipped,"The antonym of 'expand' is:",
    'anglais','EXAMEN_ETAT',2025,['extend','spread','grow','contract'],'D',
    'Expand means to grow larger; contract means to become smaller');

addQ($M,$inserted,$skipped,"'If I _____ you, I would study harder' (conditional II)",
    'anglais','EXAMEN_ETAT',2025,['am','was','have been','were'],'D',
    'In conditional type 2 (unreal present), we use "were" for all persons');

addQ($M,$inserted,$skipped,"PHOTOSYNTHESIS broken into Greek roots means:",
    'anglais','EXAMEN_ETAT',2025,['light + action','dark + growth','sun + process','light + putting together'],'D',
    'Photo = light (Greek: phos/photos), synthesis = putting together (Greek: synthesis)');

echo "<h2 style='font-family:sans-serif;color:#007A5E'>âœ“ TerminÃ©</h2>";
echo "<p style='font-family:sans-serif'><strong>$inserted</strong> questions insÃ©rÃ©es, <strong>$skipped</strong> doublons ignorÃ©s.</p>";

