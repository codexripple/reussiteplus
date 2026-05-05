<?php
/**
 * admin/ai_insights.php
 * Endpoint JSON — Analyse IA via Groq pour le tableau de bord admin.
 * Accepte un POST JSON avec les statistiques de la plateforme.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

// Sécurisation : admins uniquement
$user = current_user();
if (!$user || !in_array($user['role'], ['ADMIN', 'SUPER_ADMIN'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé.']);
    exit;
}

// Lecture du body JSON
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Données invalides.']);
    exit;
}

// Validation / nettoyage des valeurs reçues
$users       = (int)($data['users']       ?? 0);
$usersToday  = (int)($data['users_today'] ?? 0);
$users7j     = (int)($data['users_7j']    ?? 0);
$revenus     = (float)($data['revenus']   ?? 0);
$revGrowth   = (float)($data['rev_growth'] ?? 0);
$examsToday  = (int)($data['exams_today'] ?? 0);
$paiements   = (int)($data['paiements']   ?? 0);
$convRate    = (float)($data['conv_rate'] ?? 0);
$plans       = is_array($data['plans'] ?? null) ? $data['plans'] : [];

// Clé Groq — retour gracieux si absente
if (!defined('GROQ_API_KEY') || GROQ_API_KEY === '') {
    echo json_encode([
        'analyse' => "Configuration IA manquante. Veuillez définir la variable d'environnement GROQ_API_KEY dans votre fichier .env ou dans la configuration du serveur.\n\nAucune analyse automatique n'est disponible pour l'instant."
    ]);
    exit;
}

// Résumé des plans pour le prompt
$planSummary = '';
foreach ($plans as $nom => $nb) {
    $planSummary .= "  - {$nom} : {$nb} utilisateurs\n";
}
if (!$planSummary) $planSummary = '  - Données non disponibles';

// Prompt structuré, contextualisé RDC/Congo
$systemPrompt = <<<SYSTEM
Tu es un conseiller business expert en EdTech et en marchés émergents d'Afrique subsaharienne, spécialisé en République Démocratique du Congo (RDC).
Tu dois analyser les données de performance d'une plateforme d'éducation en ligne congolaise appelée "RéussitePlus" et produire des recommandations concrètes, directes, sans fioritures.
Réponds UNIQUEMENT en français. Pas de liste à puces inutiles. Pas de platitudes. Sois direct, précis, actionnable. Maximum 350 mots.
SYSTEM;

$userPrompt = <<<PROMPT
Voici les données actuelles de la plateforme RéussitePlus (Congo RDC) :

Utilisateurs actifs : {$users}
Nouveaux aujourd'hui : {$usersToday}
Nouveaux cette semaine : {$users7j}
Revenus ce mois (CDF) : {$revenus}
Croissance revenus vs mois dernier : {$revGrowth}%
Examens lancés aujourd'hui : {$examsToday}
Paiements en attente : {$paiements}
Taux de conversion (utilisateurs payants) : {$convRate}%

Répartition des plans d'abonnement :
{$planSummary}

Analyse les 3 points prioritaires suivants :
1. Le taux de conversion et comment l'améliorer dans le contexte congolais (Mobile Money, barrières économiques, confiance en ligne).
2. La santé des revenus et les opportunités de croissance à court terme (upsell, ECOLE, promotions saisonnières).
3. L'engagement des utilisateurs (examens, activité quotidienne) et ce qu'il faut faire pour améliorer la rétention.

Conclus par une action immédiate à prioriser cette semaine.
PROMPT;

$payload = [
    'model'       => GROQ_MODEL,
    'max_tokens'  => GROQ_MAX_TOKENS,
    'temperature' => 0.7,
    'messages'    => [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user',   'content' => $userPrompt],
    ],
];

// Appel API Groq
$ch = curl_init(GROQ_API_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . GROQ_API_KEY,
    ],
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    http_response_code(502);
    echo json_encode(['error' => 'Erreur réseau : ' . $curlErr]);
    exit;
}

$result = json_decode($response, true);

if ($httpCode !== 200 || empty($result['choices'][0]['message']['content'])) {
    $errMsg = $result['error']['message'] ?? "Réponse inattendue (HTTP {$httpCode}).";
    http_response_code(502);
    echo json_encode(['error' => $errMsg]);
    exit;
}

$analyse = trim($result['choices'][0]['message']['content']);

echo json_encode(['analyse' => $analyse], JSON_UNESCAPED_UNICODE);
