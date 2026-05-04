<?php
// ============================================================
// RÉUSSITE+ | Seed Questions Batch 2 — 120 nouvelles questions
// Accès local uniquement
// ============================================================
if (php_uname('n') !== gethostname() && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'])) {
    http_response_code(403); exit('Accès refusé.');
}
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$inserted = 0;
$skipped  = 0;

// IDs matières
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
// ENAFEP — Fin Primaire (40 questions)
// ============================================================

// MATHS ENAFEP (15)
addQ($M,$inserted,$skipped,"La valeur de 3/4 + 1/2 est :",
    'maths','ENAFEP',2025,['5/6','5/4','4/6','2/3'],'B',
    '3/4 + 1/2 = 3/4 + 2/4 = 5/4 = 1,25');

addQ($M,$inserted,$skipped,"Un rectangle a une longueur de 8 cm et une largeur de 5 cm. Son aire est :",
    'maths','ENAFEP',2025,['26 cm²','13 cm²','40 cm²','30 cm²'],'C',
    'Aire = longueur × largeur = 8 × 5 = 40 cm²');

addQ($M,$inserted,$skipped,"45% de 200 est égal à :",
    'maths','ENAFEP',2025,['80','85','95','90'],'D',
    '45/100 × 200 = 90');

addQ($M,$inserted,$skipped,"Le plus grand nombre premier inférieur à 20 est :",
    'maths','ENAFEP',2025,['17','16','18','19'],'D',
    '19 est premier (divisible uniquement par 1 et 19)');

addQ($M,$inserted,$skipped,"2,5 × 4 = ?",
    'maths','ENAFEP',2025,['8','8,5','10','9'],'C',
    '2,5 × 4 = 10');

addQ($M,$inserted,$skipped,"Un train roule à 120 km/h. En 2h30min, il parcourt :",
    'maths','ENAFEP',2025,['240 km','260 km','280 km','300 km'],'D',
    '2h30min = 2,5h. Distance = 120 × 2,5 = 300 km');

addQ($M,$inserted,$skipped,"L'angle intérieur d'un carré vaut :",
    'maths','ENAFEP',2025,['45°','60°','90°','180°'],'C',
    'Un carré a 4 angles droits, chacun valant 90°');

addQ($M,$inserted,$skipped,"3² + 4² = ?",
    'maths','ENAFEP',2025,['14','25','49','7'],'B',
    '3² = 9, 4² = 16, 9 + 16 = 25');

addQ($M,$inserted,$skipped,"Un article coûte 3 600 CDF avec une remise de 25%. Le prix à payer est :",
    'maths','ENAFEP',2025,['2 800 CDF','3 000 CDF','2 700 CDF','2 400 CDF'],'C',
    'Remise = 25% × 3600 = 900 CDF. Prix = 3600 - 900 = 2 700 CDF');

addQ($M,$inserted,$skipped,"La moyenne des nombres 12, 16, 18 et 14 est :",
    'maths','ENAFEP',2025,['14','16','13','15'],'D',
    '(12+16+18+14) ÷ 4 = 60 ÷ 4 = 15');

addQ($M,$inserted,$skipped,"Combien de faces a un cube ?",
    'maths','ENAFEP',2025,['4','8','12','6'],'D',
    'Un cube a 6 faces carrées');

addQ($M,$inserted,$skipped,"1 000 millilitres équivalent à :",
    'maths','ENAFEP',2025,['10 L','100 L','0,1 L','1 L'],'D',
    '1 000 mL = 1 L');

addQ($M,$inserted,$skipped,"Le périmètre d'un cercle de rayon 7 cm est (π ≈ 22/7) :",
    'maths','ENAFEP',2025,['44 cm','22 cm','28 cm','154 cm²'],'A',
    'Périmètre = 2πr = 2 × (22/7) × 7 = 44 cm');

addQ($M,$inserted,$skipped,"72 ÷ 8 × 3 = ?",
    'maths','ENAFEP',2025,['3','21','24','27'],'D',
    'On calcule de gauche à droite : 72 ÷ 8 = 9, puis 9 × 3 = 27');

addQ($M,$inserted,$skipped,"Un élève obtient 48 sur 80. Sa note sur 20 est :",
    'maths','ENAFEP',2025,['10','11','12','13'],'C',
    '(48 ÷ 80) × 20 = 12');

// FRANÇAIS ENAFEP (15)
addQ($M,$inserted,$skipped,"Le pluriel de « cheval » est :",
    'francais','ENAFEP',2025,['chevals','chevales','chevaus','chevaux'],'D',
    'Cheval → chevaux (pluriel irrégulier en -aux)');

addQ($M,$inserted,$skipped,"Dans la phrase « Le chat mange la souris », « la souris » est :",
    'francais','ENAFEP',2025,['sujet','attribut','complément d\'objet indirect','complément d\'objet direct'],'D',
    'La souris répond à la question « mange quoi ? » → COD');

addQ($M,$inserted,$skipped,"Le contraire de « généreux » est :",
    'francais','ENAFEP',2025,['avare','courageux','aimable','riche'],'A',
    'Généreux ↔ avare');

addQ($M,$inserted,$skipped,"« Il faisait beau » est à quel temps verbal ?",
    'francais','ENAFEP',2025,['passé composé','présent','futur','imparfait'],'D',
    'Faisait = imparfait de l\'indicatif du verbe faire');

addQ($M,$inserted,$skipped,"Le synonyme le plus proche de « rapide » est :",
    'francais','ENAFEP',2025,['lent','hâtif','prompt','vif'],'C',
    'Prompt signifie qui agit ou se produit rapidement');

addQ($M,$inserted,$skipped,"Le féminin de « instituteur » est :",
    'francais','ENAFEP',2025,['instituteuse','institutrisse','instituteure','institutrice'],'D',
    'Instituteur → institutrice');

addQ($M,$inserted,$skipped,"Quelle phrase contient une faute grammaticale ?",
    'francais','ENAFEP',2025,['Il chante bien','Nous mangeons','Vous dormez','Elle sont parties'],'D',
    'Le sujet « Elle » est singulier, il faut écrire « Elle est partie »');

addQ($M,$inserted,$skipped,"« Il a mangé » est au :",
    'francais','ENAFEP',2025,['imparfait','plus-que-parfait','futur antérieur','passé composé'],'D',
    'A + participe passé = passé composé');

addQ($M,$inserted,$skipped,"Dans « La belle rose rouge », « belle » et « rouge » sont des :",
    'francais','ENAFEP',2025,['noms','verbes','adverbes','adjectifs'],'D',
    'Belle et rouge qualifient le nom rose → adjectifs qualificatifs');

addQ($M,$inserted,$skipped,"Quel signe de ponctuation termine une question ?",
    'francais','ENAFEP',2025,['.','!',';','?'],'D',
    'Une phrase interrogative se termine par un point d\'interrogation');

addQ($M,$inserted,$skipped,"Le pluriel de « voix » est :",
    'francais','ENAFEP',2025,['voixs','vois','voixt','voix'],'D',
    'Voix se termine déjà par x, son pluriel est identique : voix');

addQ($M,$inserted,$skipped,"Le mot « immobile » signifie :",
    'francais','ENAFEP',2025,['très mobile','qui bouge lentement','difficile à bouger','qui ne bouge pas'],'D',
    'Im- est un préfixe négatif. Immobile = qui ne bouge pas');

addQ($M,$inserted,$skipped,"« Vite » est un :",
    'francais','ENAFEP',2025,['nom','adjectif','verbe','adverbe'],'D',
    'Vite modifie un verbe ou un adjectif → c\'est un adverbe');

addQ($M,$inserted,$skipped,"Le mot « bibliothèque » vient du grec « biblion » qui signifie :",
    'francais','ENAFEP',2025,['lecture','savoir','papier','livre'],'D',
    'Biblion = livre en grec ; thèque = armoire/lieu de rangement');

addQ($M,$inserted,$skipped,"Dans la phrase « Les oiseaux chantent joyeusement », le sujet est :",
    'francais','ENAFEP',2025,['chantent','joyeusement','les oiseaux','oiseaux'],'C',
    'Le sujet répond à la question « qui est-ce qui chante ? » → les oiseaux');

// SCIENCES ENAFEP (10)
addQ($M,$inserted,$skipped,"La photosynthèse se produit principalement dans :",
    'sciences','ENAFEP',2025,['les racines','les fleurs','les graines','les feuilles'],'D',
    'Les feuilles contiennent la chlorophylle qui capte l\'énergie solaire');

addQ($M,$inserted,$skipped,"L'organe qui pompe le sang dans le corps est :",
    'sciences','ENAFEP',2025,['le poumon','le foie','le rein','le cœur'],'D',
    'Le cœur est la pompe du système circulatoire');

addQ($M,$inserted,$skipped,"L'eau entre en ébullition à :",
    'sciences','ENAFEP',2025,['0 °C','50 °C','200 °C','100 °C'],'D',
    'À pression normale, l\'eau bout à 100 °C');

addQ($M,$inserted,$skipped,"Parmi ces animaux, lequel est un mammifère ?",
    'sciences','ENAFEP',2025,['le crocodile','le perroquet','la grenouille','la baleine'],'D',
    'La baleine respire à l\'air, allaite ses petits → mammifère marin');

addQ($M,$inserted,$skipped,"Le Soleil est :",
    'sciences','ENAFEP',2025,['une planète','une lune','une comète','une étoile'],'D',
    'Le Soleil est une étoile de type naine jaune');

addQ($M,$inserted,$skipped,"L'organe principal de la respiration est :",
    'sciences','ENAFEP',2025,['l\'estomac','le cœur','le foie','le poumon'],'D',
    'Les poumons permettent les échanges gazeux O₂/CO₂');

addQ($M,$inserted,$skipped,"L'eau gèle à :",
    'sciences','ENAFEP',2025,['-10 °C','4 °C','100 °C','0 °C'],'D',
    'Le point de congélation de l\'eau est 0 °C à pression normale');

addQ($M,$inserted,$skipped,"Le squelette humain adulte compte environ :",
    'sciences','ENAFEP',2025,['106 os','306 os','406 os','206 os'],'D',
    'Le corps humain adulte possède en moyenne 206 os');

addQ($M,$inserted,$skipped,"La planète la plus proche du Soleil est :",
    'sciences','ENAFEP',2025,['Vénus','Terre','Mars','Mercure'],'D',
    'Mercure est la planète la plus proche du Soleil dans le système solaire');

addQ($M,$inserted,$skipped,"Les plantes produisent de l'oxygène grâce à :",
    'sciences','ENAFEP',2025,['la respiration','la transpiration','la digestion','la photosynthèse'],'D',
    'La photosynthèse utilise CO₂ + eau + lumière pour produire glucose + O₂');

// ============================================================
// TENASOSP — Fin Secondaire (40 questions)
// ============================================================

// MATHS TENASOSP (10)
addQ($M,$inserted,$skipped,"La dérivée de f(x) = x³ − 2x + 1 est :",
    'maths','TENASOSP',2025,['3x²','x² − 2','3x + 1','3x² − 2'],'D',
    'Règle de dérivation : d/dx(xⁿ) = nxⁿ⁻¹ et d/dx(cste) = 0 → 3x² − 2');

addQ($M,$inserted,$skipped,"log₁₀(1 000) = ?",
    'maths','TENASOSP',2025,['2','4','10','3'],'D',
    '10³ = 1 000 donc log₁₀(1 000) = 3');

addQ($M,$inserted,$skipped,"sin²(x) + cos²(x) = ?",
    'maths','TENASOSP',2025,['0','2','sin(2x)','1'],'D',
    'Identité fondamentale de la trigonométrie');

addQ($M,$inserted,$skipped,"L'équation x² − 5x + 6 = 0 a pour solutions :",
    'maths','TENASOSP',2025,['x=1 et x=6','x=−2 et x=−3','x=2 et x=−3','x=2 et x=3'],'D',
    'Discriminant Δ = 25 − 24 = 1. x = (5±1)/2 → x=3 ou x=2');

addQ($M,$inserted,$skipped,"∫ 2x dx = ?",
    'maths','TENASOSP',2025,['2','2x² + C','x + C','x² + C'],'D',
    'Règle d\'intégration : ∫xⁿ dx = xⁿ⁺¹/(n+1) → ∫2x dx = x² + C');

addQ($M,$inserted,$skipped,"La limite de (x² − 1)/(x − 1) quand x → 1 est :",
    'maths','TENASOSP',2025,['0','1','indéfinie','2'],'D',
    'x²−1 = (x−1)(x+1), donc la fraction = x+1 → limite = 1+1 = 2');

addQ($M,$inserted,$skipped,"Dans une série statistique, la valeur la plus fréquente est :",
    'maths','TENASOSP',2025,['la moyenne','la médiane','l\'étendue','le mode'],'D',
    'Le mode est la valeur qui apparaît le plus souvent dans la série');

addQ($M,$inserted,$skipped,"Le nombre complexe (2 + 3i)(2 − 3i) vaut :",
    'maths','TENASOSP',2025,['4 − 9i²','1','4 + 9','13'],'D',
    '(2+3i)(2−3i) = 4 − 9i² = 4 − 9(−1) = 4 + 9 = 13');

addQ($M,$inserted,$skipped,"Si P(A) = 0,3 et P(B) = 0,5 avec A et B indépendants, P(A ∩ B) = ?",
    'maths','TENASOSP',2025,['0,8','0,2','0,35','0,15'],'D',
    'P(A ∩ B) = P(A) × P(B) = 0,3 × 0,5 = 0,15');

addQ($M,$inserted,$skipped,"Un vecteur de module 5 faisant 60° avec l'axe x a pour composante horizontale :",
    'maths','TENASOSP',2025,['5','2,5','5√3/2','5√2/2'],'B',
    'Composante x = 5 × cos(60°) = 5 × 0,5 = 2,5');

// CHIMIE TENASOSP (10)
addQ($M,$inserted,$skipped,"Le numéro atomique de l'oxygène est :",
    'chimie','TENASOSP',2025,['6','7','16','8'],'D',
    'L\'oxygène (O) a 8 protons dans son noyau → Z = 8');

addQ($M,$inserted,$skipped,"Un acide de Brønsted est une substance qui :",
    'chimie','TENASOSP',2025,['libère des OH⁻','est neutre','réagit avec les métaux','libère des H⁺'],'D',
    'Selon Brønsted-Lowry, un acide est un donneur de protons H⁺');

addQ($M,$inserted,$skipped,"Le pH d'une solution neutre est :",
    'chimie','TENASOSP',2025,['0','5','14','7'],'D',
    'Une solution neutre a [H⁺] = [OH⁻] = 10⁻⁷ mol/L → pH = 7');

addQ($M,$inserted,$skipped,"La formule du dioxyde de carbone est :",
    'chimie','TENASOSP',2025,['CO','C₂O','C₂O₃','CO₂'],'D',
    'Un atome de carbone lié à deux atomes d\'oxygène → CO₂');

addQ($M,$inserted,$skipped,"En nomenclature organique, le suffixe « -ane » désigne :",
    'chimie','TENASOSP',2025,['les alcools','les alcènes','les aldéhydes','les alcanes'],'D',
    'Les alcanes sont des hydrocarbures saturés (ex: méthane, éthane, propane)');

addQ($M,$inserted,$skipped,"La mole contient environ :",
    'chimie','TENASOSP',2025,['6,02×10²²','6,02×10²⁴','6,02×10²⁰','6,02×10²³'],'D',
    'Le nombre d\'Avogadro NA = 6,02×10²³ entités par mole');

addQ($M,$inserted,$skipped,"L'élément avec l'électronégativité la plus élevée est :",
    'chimie','TENASOSP',2025,['l\'oxygène','l\'azote','le chlore','le fluor'],'D',
    'Le fluor (F) est l\'élément le plus électronégatif selon l\'échelle de Pauling');

addQ($M,$inserted,$skipped,"La réaction de saponification entre un corps gras et NaOH produit :",
    'chimie','TENASOSP',2025,['une amine','un ester','un acide','du savon et de la glycérine'],'D',
    'Corps gras + NaOH → savon (sel d\'acide gras) + glycérine');

addQ($M,$inserted,$skipped,"La formule chimique de l'acide sulfurique est :",
    'chimie','TENASOSP',2025,['HCl','H₂SO₃','HNO₃','H₂SO₄'],'D',
    'L\'acide sulfurique est H₂SO₄ (diprôtique, très corrosif)');

addQ($M,$inserted,$skipped,"Dans la réaction Zn + 2HCl → ZnCl₂ + H₂, le zinc est :",
    'chimie','TENASOSP',2025,['réduit','oxydé et réduit','ni oxydé ni réduit','oxydé'],'D',
    'Zn → Zn²⁺ : perd 2 électrons → il est oxydé');

// PHYSIQUE TENASOSP (10)
addQ($M,$inserted,$skipped,"Si m = 5 kg et g = 10 m/s², la force gravitationnelle F = mg vaut :",
    'physique','TENASOSP',2025,['2 N','15 N','5 N','50 N'],'D',
    'F = 5 × 10 = 50 N');

addQ($M,$inserted,$skipped,"La vitesse de la lumière dans le vide est approximativement :",
    'physique','TENASOSP',2025,['3×10⁶ m/s','3×10¹⁰ m/s','3×10⁴ m/s','3×10⁸ m/s'],'D',
    'c ≈ 3×10⁸ m/s = 300 000 km/s');

addQ($M,$inserted,$skipped,"La résistance équivalente de R₁ = 6 Ω et R₂ = 3 Ω en parallèle est :",
    'physique','TENASOSP',2025,['9 Ω','3 Ω','18 Ω','2 Ω'],'D',
    '1/Req = 1/6 + 1/3 = 1/6 + 2/6 = 3/6 = 1/2 → Req = 2 Ω');

addQ($M,$inserted,$skipped,"La loi d'Ohm s'écrit :",
    'physique','TENASOSP',2025,['P = UI','F = ma','E = mc²','U = RI'],'D',
    'U = tension (V), R = résistance (Ω), I = intensité (A)');

addQ($M,$inserted,$skipped,"Un miroir plan forme une image :",
    'physique','TENASOSP',2025,['réelle et droite','virtuelle et renversée','réelle et renversée','virtuelle et droite'],'D',
    'L\'image dans un miroir plan est virtuelle (derrière le miroir), droite et de même taille');

addQ($M,$inserted,$skipped,"L'unité de la fréquence est :",
    'physique','TENASOSP',2025,['mètre','watt','newton','hertz'],'D',
    '1 Hz = 1 cycle par seconde');

addQ($M,$inserted,$skipped,"L'énergie cinétique d'un objet de masse m et vitesse v est :",
    'physique','TENASOSP',2025,['mgh','RI²','QV','½mv²'],'D',
    'Ec = ½mv² (en joules si m en kg et v en m/s)');

addQ($M,$inserted,$skipped,"Un courant de 2 A traverse une résistance de 4 Ω. La tension est :",
    'physique','TENASOSP',2025,['2 V','6 V','0,5 V','8 V'],'D',
    'U = R × I = 4 × 2 = 8 V');

addQ($M,$inserted,$skipped,"La réfraction de la lumière obéit à la loi de :",
    'physique','TENASOSP',2025,['Ohm','Newton','Faraday','Snell-Descartes'],'D',
    'n₁·sin(θ₁) = n₂·sin(θ₂) est la loi de Snell-Descartes');

addQ($M,$inserted,$skipped,"La période d'un pendule simple dépend principalement de :",
    'physique','TENASOSP',2025,['sa masse','son amplitude','sa couleur','sa longueur'],'D',
    'T = 2π√(l/g) : la période ne dépend que de la longueur l et de g');

// BIOLOGIE TENASOSP (10)
addQ($M,$inserted,$skipped,"L'ADN se trouve principalement dans :",
    'biologie','TENASOSP',2025,['la mitochondrie','le cytoplasme','la membrane','le noyau'],'D',
    'Le noyau contient l\'ADN organisé en chromosomes');

addQ($M,$inserted,$skipped,"La respiration cellulaire aérobie produit :",
    'biologie','TENASOSP',2025,['O₂ et H₂O','O₂ et CO₂','H₂O et glucose','CO₂ et H₂O'],'D',
    'Glucose + O₂ → CO₂ + H₂O + énergie (ATP)');

addQ($M,$inserted,$skipped,"Le nombre de chromosomes dans une cellule humaine diploïde est :",
    'biologie','TENASOSP',2025,['23','44','48','46'],'D',
    '2n = 46 chromosomes (23 paires) dans les cellules somatiques humaines');

addQ($M,$inserted,$skipped,"La mitose produit :",
    'biologie','TENASOSP',2025,['4 cellules haploïdes','4 cellules diploïdes','2 cellules haploïdes','2 cellules diploïdes identiques'],'D',
    'La mitose est une division cellulaire produisant 2 cellules filles génétiquement identiques à la cellule mère');

addQ($M,$inserted,$skipped,"L'enzyme qui catalyse la digestion de l'amidon est :",
    'biologie','TENASOSP',2025,['la lipase','la pepsine','la protéase','l\'amylase'],'D',
    'L\'amylase (salivaire et pancréatique) hydrolyse l\'amidon en maltose');

addQ($M,$inserted,$skipped,"Le groupe sanguin O est donneur universel car ses globules rouges :",
    'biologie','TENASOSP',2025,['contiennent les deux antigènes A et B','contiennent l\'antigène O','ont plus d\'hémoglobine','ne portent ni antigène A ni antigène B'],'D',
    'L\'absence d\'antigènes A et B évite les réactions immunitaires chez le receveur');

addQ($M,$inserted,$skipped,"L'insuline est sécrétée par :",
    'biologie','TENASOSP',2025,['le foie','les reins','la thyroïde','le pancréas'],'D',
    'Les cellules bêta des îlots de Langerhans du pancréas sécrètent l\'insuline');

addQ($M,$inserted,$skipped,"La membrane plasmique contrôle :",
    'biologie','TENASOSP',2025,['la synthèse d\'ADN','la production d\'énergie','la division cellulaire','les échanges entre la cellule et son milieu'],'D',
    'La membrane est sélectivement perméable et régule les entrées/sorties');

addQ($M,$inserted,$skipped,"La méiose est une division qui produit :",
    'biologie','TENASOSP',2025,['2 cellules diploïdes','8 cellules haploïdes','2 cellules haploïdes','4 cellules haploïdes'],'D',
    'La méiose comporte 2 divisions successives et produit 4 cellules haploïdes (gamètes)');

addQ($M,$inserted,$skipped,"Les virus sont des entités :",
    'biologie','TENASOSP',2025,['procaryotes','eucaryotes','bactériennes','acellulaires'],'D',
    'Les virus ne possèdent pas de structure cellulaire propre, ils sont acellulaires');

// ============================================================
// EXAMEN D'ÉTAT (40 questions)
// ============================================================

// MATHS EXAMEN_ETAT (10)
addQ($M,$inserted,$skipped,"La dérivée de f(x) = e^(2x) est :",
    'maths','EXAMEN_ETAT',2025,['e^(2x)','e^(2x)/2','2xe^x','2e^(2x)'],'D',
    'Règle de dérivation des fonctions composées : f\'(x) = 2·e^(2x)');

addQ($M,$inserted,$skipped,"∫₀¹ x² dx = ?",
    'maths','EXAMEN_ETAT',2025,['1','1/2','2/3','1/3'],'D',
    '[x³/3]₀¹ = 1/3 − 0 = 1/3');

addQ($M,$inserted,$skipped,"ln(e³) = ?",
    'maths','EXAMEN_ETAT',2025,['3e','e³','1','3'],'D',
    'ln et exp sont des fonctions réciproques → ln(e³) = 3');

addQ($M,$inserted,$skipped,"La somme d'une progression géométrique de raison 2, premier terme 1, sur 5 termes est :",
    'maths','EXAMEN_ETAT',2025,['15','63','32','31'],'D',
    'S = 1+2+4+8+16 = 31. Formule : Sn = a(rⁿ−1)/(r−1) = 1×(32−1)/1 = 31');

addQ($M,$inserted,$skipped,"Le déterminant de la matrice [[1, 2], [3, 4]] est :",
    'maths','EXAMEN_ETAT',2025,['2','−4','10','−2'],'D',
    'det = 1×4 − 2×3 = 4 − 6 = −2');

addQ($M,$inserted,$skipped,"La tangente à la courbe y = x² au point (2, 4) a pour équation :",
    'maths','EXAMEN_ETAT',2025,['y = 2x','y = 4x','y = 4x + 4','y = 4x − 4'],'D',
    'f\'(2) = 2×2 = 4 (pente). Équation : y − 4 = 4(x − 2) → y = 4x − 4');

addQ($M,$inserted,$skipped,"P(X = k) = C(n,k)·pᵏ·(1−p)^(n−k) est la loi :",
    'maths','EXAMEN_ETAT',2025,['de Poisson','normale','géométrique','binomiale'],'D',
    'C\'est la formule de la loi binomiale B(n, p)');

addQ($M,$inserted,$skipped,"L'ensemble des solutions de |2x − 1| < 3 est :",
    'maths','EXAMEN_ETAT',2025,['[−1, 2]',']−∞, −1[ ∪ ]2, +∞[',']1, 3[',']−1, 2['],'D',
    '−3 < 2x−1 < 3 → −2 < 2x < 4 → −1 < x < 2');

addQ($M,$inserted,$skipped,"Si les vecteurs (1, 2) et (k, −3) sont perpendiculaires, alors k vaut :",
    'maths','EXAMEN_ETAT',2025,['−6','3','−3','6'],'D',
    'Produit scalaire nul : 1·k + 2·(−3) = 0 → k = 6');

addQ($M,$inserted,$skipped,"La solution générale de l'équation différentielle y' = 3y est :",
    'maths','EXAMEN_ETAT',2025,['y = Ce^(x/3)','y = 3Cx','y = C·ln(x)','y = Ce^(3x)'],'D',
    'y\'/y = 3 → ln|y| = 3x + K → y = Ce^(3x)');

// FRANÇAIS EXAMEN_ETAT (10)
addQ($M,$inserted,$skipped,"La figure de style dans « La vie est un long fleuve tranquille » est :",
    'francais','EXAMEN_ETAT',2025,['une métonymie','une comparaison','une hyperbole','une métaphore'],'D',
    'Assimilation sans outil comparatif de la vie à un fleuve → métaphore');

addQ($M,$inserted,$skipped,"L'auteur des « Misérables » est :",
    'francais','EXAMEN_ETAT',2025,['Émile Zola','Honoré de Balzac','Gustave Flaubert','Victor Hugo'],'D',
    'Les Misérables (1862) est l\'œuvre majeure de Victor Hugo');

addQ($M,$inserted,$skipped,"La proposition subordonnée relative dans « L'homme qui rit est heureux » est :",
    'francais','EXAMEN_ETAT',2025,['L\'homme','est heureux','L\'homme qui rit','qui rit'],'D',
    'La relative est introduite par le pronom relatif « qui » et qualifie « l\'homme »');

addQ($M,$inserted,$skipped,"Le mouvement littéraire du XIXe siècle qui valorise la nature et les sentiments est :",
    'francais','EXAMEN_ETAT',2025,['le classicisme','le surréalisme','le naturalisme','le romantisme'],'D',
    'Le romantisme (1800-1850) exalte la sensibilité, la nature et l\'imagination');

addQ($M,$inserted,$skipped,'Le discours indirect de « Il dit : "Je viendrai demain" » est :',
    'francais','EXAMEN_ETAT',2025,["Il dit qu'il vient demain","Il dit qu'il est venu","Il dit qu'il viendra demain","Il dit qu'il viendrait le lendemain"],'D',
    'Concordance des temps : venir au futur → conditionnel. Demain → le lendemain');

addQ($M,$inserted,$skipped,"Une anaphore est :",
    'francais','EXAMEN_ETAT',2025,['la répétition d\'un mot en fin de vers','l\'omission d\'un mot','l\'inversion syntaxique','la répétition d\'un mot ou groupe en début de phrase/vers'],'D',
    'Ex: « Je veux la paix, je veux la justice, je veux la liberté »');

addQ($M,$inserted,$skipped,"« Les sanglots longs des violons de l'automne » est extrait de :",
    'francais','EXAMEN_ETAT',2025,['Rimbaud, Le Bateau ivre','Baudelaire, Spleen','Hugo, Demain dès l\'aube','Verlaine, Chanson d\'automne'],'D',
    'Ce vers ouvre le poème « Chanson d\'automne » de Paul Verlaine (1866)');

addQ($M,$inserted,$skipped,"Le futur antérieur de « avoir » à la 1ère personne du singulier est :",
    'francais','EXAMEN_ETAT',2025,['j\'avais eu','j\'aurais eu','j\'ai eu','j\'aurai eu'],'D',
    'Futur antérieur = auxiliaire au futur simple + participe passé : j\'aurai eu');

addQ($M,$inserted,$skipped,"L'hyperbole est une figure de style qui consiste à :",
    'francais','EXAMEN_ETAT',2025,['atténuer la réalité','comparer deux éléments','exagérer une réalité pour insister','inverser l\'ordre des mots'],'C',
    'Ex: « Je t\'ai dit mille fois ! » → exagération volontaire pour renforcer l\'effet');

addQ($M,$inserted,$skipped,"Dans « Il pleuvait des cordes », le verbe « pleuvait des cordes » est :",
    'francais','EXAMEN_ETAT',2025,['une litote','une périphrase','une allégorie','une métaphore lexicalisée'],'D',
    'Expression figée décrivant une forte pluie → métaphore lexicalisée (figée dans la langue)');

// HISTOIRE-GÉOGRAPHIE EXAMEN_ETAT (10)
addQ($M,$inserted,$skipped,"La Conférence de Berlin (1884-1885) a principalement :",
    'histgeo','EXAMEN_ETAT',2025,['mis fin à l\'esclavage','créé la Société des Nations','établi les frontières européennes','partagé l\'Afrique entre puissances européennes'],'D',
    'Organisée par Bismarck, elle a fixé les règles du partage de l\'Afrique');

addQ($M,$inserted,$skipped,"L'indépendance du Congo-Kinshasa a eu lieu le :",
    'histgeo','EXAMEN_ETAT',2025,['1er juillet 1960','30 juin 1962','30 juin 1958','30 juin 1960'],'D',
    'Le 30 juin 1960, le Congo belge accède à l\'indépendance sous Joseph Kasavubu');

addQ($M,$inserted,$skipped,"Le fleuve Congo est le deuxième fleuve du monde par :",
    'histgeo','EXAMEN_ETAT',2025,['sa longueur','son bassin','sa largeur','son débit'],'D',
    'Le Congo a un débit moyen de ~41 000 m³/s, second après l\'Amazone');

addQ($M,$inserted,$skipped,"La Première Guerre mondiale a débuté en :",
    'histgeo','EXAMEN_ETAT',2025,['1910','1918','1920','1914'],'D',
    'L\'assassinat de l\'archiduc François-Ferdinand le 28 juin 1914 déclenche la guerre');

addQ($M,$inserted,$skipped,"L'ONU a été fondée en :",
    'histgeo','EXAMEN_ETAT',2025,['1919','1939','1950','1945'],'D',
    'La Charte des Nations Unies est signée à San Francisco le 26 juin 1945');

addQ($M,$inserted,$skipped,"La capitale administrative de l'Afrique du Sud est :",
    'histgeo','EXAMEN_ETAT',2025,['Johannesburg','Le Cap','Durban','Pretoria'],'D',
    'L\'Afrique du Sud a 3 capitales : Pretoria (administrative), Le Cap (législative), Bloemfontein (judiciaire)');

addQ($M,$inserted,$skipped,"La population mondiale est estimée à environ :",
    'histgeo','EXAMEN_ETAT',2025,['6 milliards','7 milliards','9 milliards','8 milliards'],'D',
    'En 2022-2024 la population mondiale a franchi le cap des 8 milliards');

addQ($M,$inserted,$skipped,"Kinshasa se trouve sur le fleuve :",
    'histgeo','EXAMEN_ETAT',2025,['Kasaï','Ubangi','Lualaba','Congo'],'D',
    'Kinshasa est bâtie sur la rive gauche du fleuve Congo, face à Brazzaville');

addQ($M,$inserted,$skipped,"La décolonisation africaine a majoritairement eu lieu dans :",
    'histgeo','EXAMEN_ETAT',2025,['les années 1940','les années 1930','les années 1970','les années 1950-1960'],'D',
    'La vague des indépendances africaines se concentre dans les années 1957-1965 (« Année de l\'Afrique » = 1960)');

addQ($M,$inserted,$skipped,"L'Union Africaine (UA) a remplacé l'Organisation de l'Unité Africaine (OUA) en :",
    'histgeo','EXAMEN_ETAT',2025,['1999','2000','2005','2002'],'D',
    'L\'UA a été officiellement créée lors du sommet de Durban le 9 juillet 2002');

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

echo "<h2 style='font-family:sans-serif;color:#007A5E'>✓ Terminé</h2>";
echo "<p style='font-family:sans-serif'><strong>$inserted</strong> questions insérées, <strong>$skipped</strong> doublons ignorés.</p>";
