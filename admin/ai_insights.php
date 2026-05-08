<?php
/**
 * admin/ai_insights.php
 * Endpoint JSON — Analyse IA via Gemini pour le tableau de bord admin.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

$user = current_user();
if (!$user || !in_array($user['role'], ['ADMIN', 'SUPER_ADMIN'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé.']);
    exit;
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Données invalides.']);
    exit;
}

$users      = (int)($data['users']       ?? 0);
$usersToday = (int)($data['users_today'] ?? 0);
$users7j    = (int)($data['users_7j']    ?? 0);
$revenus    = (float)($data['revenus']   ?? 0);
$revGrowth  = (float)($data['rev_growth'] ?? 0);
$examsToday = (int)($data['exams_today'] ?? 0);
$paiements  = (int)($data['paiements']   ?? 0);
$convRate   = (float)($data['conv_rate'] ?? 0);
$plans      = is_array($data['plans'] ?? null) ? $data['plans'] : [];

$planSummary = '';
foreach ($plans as $nom => $nb) {
    $planSummary .= "  - {$nom} : {$nb} utilisateurs\n";
}
if (!$planSummary) $planSummary = '  - Données non disponibles';

$systemPrompt = "Tu es un conseiller business expert en EdTech et en marchés émergents d'Afrique subsaharienne, spécialisé en République Démocratique du Congo (RDC). Analyse les données de performance de RéussitePlus et produis des recommandations concrètes, directes, sans fioritures. Réponds UNIQUEMENT en français. Sois direct, précis, actionnable. Maximum 350 mots.";

$userPrompt = "Voici les données actuelles de RéussitePlus (Congo RDC) :\n\n"
    . "Utilisateurs actifs : {$users}\n"
    . "Nouveaux aujourd'hui : {$usersToday}\n"
    . "Nouveaux cette semaine : {$users7j}\n"
    . "Revenus ce mois (CDF) : {$revenus}\n"
    . "Croissance revenus vs mois dernier : {$revGrowth}%\n"
    . "Examens lancés aujourd'hui : {$examsToday}\n"
    . "Paiements en attente : {$paiements}\n"
    . "Taux de conversion : {$convRate}%\n\n"
    . "Répartition des plans :\n{$planSummary}\n\n"
    . "Analyse 3 points prioritaires :\n"
    . "1. Taux de conversion et comment l'améliorer (Mobile Money, contexte congolais).\n"
    . "2. Santé des revenus et opportunités à court terme.\n"
    . "3. Engagement utilisateurs et comment améliorer la rétention.\n\n"
    . "Conclus par UNE action immédiate à prioriser cette semaine.";

$messages = [
    ['role' => 'system', 'content' => $systemPrompt],
    ['role' => 'user',   'content' => $userPrompt],
];

// ── Tentative Gemini ─────────────────────────────────────────
$geminiKey = $_ENV['GEMINI_API_KEY'] ?? '';
if ($geminiKey) {
    $contents = [['role' => 'user', 'parts' => [['text' => $userPrompt]]]];
    $payload  = json_encode([
        'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
        'contents'          => $contents,
        'generationConfig'  => ['maxOutputTokens' => 600, 'temperature' => 0.7],
    ]);
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$geminiKey}";
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => $payload, CURLOPT_TIMEOUT => 30,
    ]);
    $raw2 = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); unset($ch);
    if ($raw2 && $code !== 429) {
        $d = json_decode($raw2, true);
        $t = $d['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if ($t) { echo json_encode(['analyse' => trim($t)], JSON_UNESCAPED_UNICODE); exit; }
    }
}

// ── Fallback GitHub Models ────────────────────────────────────
$ghToken = $_ENV['GITHUB_TOKEN'] ?? '';
if ($ghToken && $ghToken !== 'COLLE_TON_PAT_ICI') {
    $ch2 = curl_init('https://models.inference.ai.azure.com/chat/completions');
    curl_setopt_array($ch2, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $ghToken],
        CURLOPT_POSTFIELDS     => json_encode(['model'=>'gpt-4o-mini','messages'=>$messages,'max_tokens'=>600,'temperature'=>0.7]),
        CURLOPT_TIMEOUT => 30,
    ]);
    $raw3 = curl_exec($ch2); $code3 = curl_getinfo($ch2, CURLINFO_HTTP_CODE); unset($ch2);
    if ($raw3 && $code3 === 200) {
        $d3 = json_decode($raw3, true);
        $t3 = $d3['choices'][0]['message']['content'] ?? null;
        if ($t3) { echo json_encode(['analyse' => trim($t3)], JSON_UNESCAPED_UNICODE); exit; }
    }
}

// Aucun moteur disponible
echo json_encode(['analyse' => "Les moteurs IA sont temporairement indisponibles (quota atteint). Réessaie dans quelques minutes."], JSON_UNESCAPED_UNICODE);
