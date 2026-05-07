<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

// Toute erreur non capturée reste en JSON
set_exception_handler(function (Throwable $e) {
    if (!headers_sent()) header('Content-Type: application/json');
    echo json_encode(['success' => false, 'reply' => "Erreur serveur : " . $e->getMessage()]);
    exit;
});

// ═══════════════════════════════════════════════════════════
// FONCTIONS IA  (déclarées en tête pour éviter les warnings)
// ═══════════════════════════════════════════════════════════

/**
 * RAG — Récupère des questions EXETAT réelles depuis la DB.
 * Détecte la matière dans le message, retourne un bloc de contexte
 * à injecter dans le system prompt.
 */
function getRagContext(string $message, int $limit = 4): string
{
    try {
        // 1. Détecter la matière mentionnée dans le message
        $matieres  = dbAll("SELECT id, nom, nom_court, code FROM matieres WHERE actif = 1");
        $matiereId = null;
        $msg       = mb_strtolower(strip_tags($message));

        foreach ($matieres as $mat) {
            $terms = array_filter([
                mb_strtolower($mat['nom']       ?? ''),
                mb_strtolower($mat['nom_court'] ?? ''),
                mb_strtolower($mat['code']      ?? ''),
            ]);
            foreach ($terms as $t) {
                if (mb_strlen($t) > 2 && mb_strpos($msg, $t) !== false) {
                    $matiereId = $mat['id'];
                    break 2;
                }
            }
        }

        // 2. Requête : questions publiées avec bonne réponse + explication
        $sql = "SELECT qb.enonce, qb.annee_source, qb.difficulte,
                       m.nom AS matiere_nom,
                       qo.texte AS bonne_reponse, qo.explication
                FROM question_bank qb
                JOIN matieres m ON qb.matiere_id = m.id
                LEFT JOIN question_options qo
                       ON qb.id = qo.question_id AND qo.est_correcte = 1
                WHERE qb.status = 'PUBLIE'";

        $params = [];
        if ($matiereId) {
            $sql     .= " AND qb.matiere_id = ?";
            $params[] = $matiereId;
        }
        $sql    .= " ORDER BY qb.usage_count DESC, qb.annee_source DESC LIMIT ?";
        $params[] = $limit;

        $questions = dbAll($sql, $params);
        if (empty($questions)) return '';

        // 3. Formater le contexte (compact pour économiser les tokens)
        $lines = ["CONTEXTE — Questions EXETAT réelles issues de la plateforme RÉUSSITE+ :"];
        foreach ($questions as $q) {
            $year  = $q['annee_source'] ? " {$q['annee_source']}" : '';
            $lines[] = "• [{$q['matiere_nom']}{$year}] {$q['enonce']}";
            if (!empty($q['bonne_reponse'])) {
                $expl    = !empty($q['explication'])
                    ? ' (' . mb_substr($q['explication'], 0, 90) . '…)'
                    : '';
                $lines[] = "  ✓ {$q['bonne_reponse']}{$expl}";
            }
        }
        $lines[] = "Appuie-toi sur ces exemples pour contextualiser ta réponse si pertinent.";

        return "\n\n" . implode("\n", $lines);

    } catch (Throwable $e) {
        return ''; // RAG non bloquant — ne pas planter si la table est vide
    }
}

/**
 * Appel GitHub Models (GPT-4o-mini) — format OpenAI-compatible.
 * Utilisé comme fallback quand le quota Gemini est épuisé.
 */
function callGithubModels(string $token, string $system, array $history, string $message, int $maxTokens): ?string
{
    $messages = [['role' => 'system', 'content' => $system]];
    foreach ($history as $h) {
        if (!isset($h['role'], $h['content'])) continue;
        $messages[] = [
            'role'    => $h['role'] === 'assistant' ? 'assistant' : 'user',
            'content' => $h['content'],
        ];
    }
    $messages[] = ['role' => 'user', 'content' => $message];

    $payload = json_encode([
        'model'       => 'gpt-4o-mini',
        'messages'    => $messages,
        'max_tokens'  => $maxTokens,
        'temperature' => 0.7,
    ]);

    $ch = curl_init('https://models.inference.ai.azure.com/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT    => 30,
    ]);
    $result   = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);
    unset($ch);

    if ($err || !$result) return null;

    $data = json_decode($result, true);

    if ($httpCode === 401) return null;          // token invalide
    if ($httpCode === 429) return '__QUOTA__';   // rate limit

    return $data['choices'][0]['message']['content'] ?? null;
}

/**
 * Appel Gemini (API native Google).
 * Retry automatique jusqu'à 3× si 429 avec délai ≤ 12 s.
 * Retourne '__QUOTA__' si quota journalier épuisé.
 */
function callGemini(string $key, string $model, string $system, array $history, string $message, int $maxTokens): ?string
{
    $contents = [];
    foreach ($history as $h) {
        if (!isset($h['role'], $h['content'])) continue;
        $contents[] = [
            'role'  => $h['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $h['content']]],
        ];
    }
    if ($message !== '') {
        $contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];
    }

    $payload = json_encode([
        'systemInstruction' => ['parts' => [['text' => $system]]],
        'contents'          => $contents,
        'generationConfig'  => ['maxOutputTokens' => $maxTokens, 'temperature' => 0.7],
    ]);

    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}";

    for ($attempt = 0; $attempt < 3; $attempt++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $result   = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        unset($ch);

        if ($err || !$result) return null;

        $data = json_decode($result, true);

        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if ($text !== null) return $text;

        if ($httpCode === 429) {
            preg_match('/dans (\d+(?:\.\d+)?) secondes/', $data['error']['message'] ?? '', $m);
            $wait = isset($m[1]) ? (float) $m[1] : 5;
            if ($wait <= 12 && $attempt < 2) {
                sleep((int) ceil($wait) + 1);
                continue;
            }
            return '__QUOTA__';
        }

        return null;
    }
    return null;
}

// ═══════════════════════════════════════════════════════════
// INITIALISATION
// ═══════════════════════════════════════════════════════════

$apiKey  = $_ENV['GEMINI_API_KEY'] ?? '';
$ghToken = $_ENV['GITHUB_TOKEN']   ?? '';

if (!$apiKey) {
    echo json_encode(['success' => false, 'reply' => "Clé API Gemini manquante."]);
    exit;
}

$model = 'gemini-2.5-flash';

// Créer la table si absente
try {
    db()->exec("CREATE TABLE IF NOT EXISTS ia_conversations (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        user_id    CHAR(36) NOT NULL,
        messages   JSON NOT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ia_conv_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) { /* silencieux */ }

$input = json_decode(file_get_contents('php://input'), true);

// ─── Action : effacer l'historique ───────────────────────
if (isset($input['action']) && $input['action'] === 'clear_history') {
    $userId = $_SESSION['user']['id'] ?? null;
    if ($userId) dbQuery("DELETE FROM ia_conversations WHERE user_id = ?", [$userId]);
    echo json_encode(['success' => true, 'cleared' => true]);
    exit;
}

// ─── Action : analyse & plan de révision ─────────────────
if (isset($input['action']) && $input['action'] === 'analyse') {
    $history      = is_array($input['history'] ?? null) ? $input['history'] : [];
    $systemPrompt = "En te basant sur l'historique de cette conversation entre un élève et le Coach IA, dresse un plan de révision personnalisé pour l'EXETAT. Identifie les points faibles, propose des conseils adaptés, et donne un planning sur 7 jours. Sois synthétique, bienveillant, et motivant. Format :\n- Points faibles détectés\n- Conseils personnalisés\n- Planning de révision 7 jours (jour par jour)";

    $reply = callGemini($apiKey, $model, $systemPrompt, $history, '', 800);

    // Fallback GitHub Models si Gemini indisponible
    if (($reply === '__QUOTA__' || $reply === null) && $ghToken && $ghToken !== 'COLLE_TON_PAT_ICI') {
        $reply = callGithubModels($ghToken, $systemPrompt, $history, '', 800);
    }

    echo json_encode($reply && $reply !== '__QUOTA__'
        ? ['success' => true,  'reply' => $reply]
        : ['success' => false, 'reply' => "L'IA n'a pas pu générer l'analyse."]);
    exit;
}

// ─── Message normal ───────────────────────────────────────
$message    = trim($input['message'] ?? '');
$history    = is_array($input['history'] ?? null) ? $input['history'] : [];
$isExercice = !empty($input['exercice']);
$tone       = $input['tone'] ?? 'motivant';

if ($message === '') {
    echo json_encode(['success' => false, 'reply' => "Merci d'écrire une question."]);
    exit;
}

// Détection image → Wikipedia
$imagePattern = '/(?:génère|générer|dessine|dessiner|crée|créer|fais|montre|illustre|représente|cherche|trouve)\s+(?:moi\s+)?(?:une?\s+)?(?:image|illustration|dessin|schéma|photo|figure|représentation)\s+(?:de\s+|du\s+|d\'|des?\s+|sur\s+|représentant\s+)?(.+)/iu';
$showPattern  = '/(?:montre[- ]moi|affiche|visualise)\s+(?:moi\s+)?(.+)/iu';
if (preg_match($imagePattern, $message, $m) || preg_match($showPattern, $message, $m)) {
    $sujet = trim($m[1]);
    echo json_encode([
        'success'   => true,
        'type'      => 'image_search',
        'query'     => $sujet,
        'image_url' => '/reussiteplus/api/ia_image.php?q=' . urlencode($sujet),
        'reply'     => "Recherche d'une illustration sur **{$sujet}**…",
    ]);
    exit;
}

// Charger l'historique DB si vide
$userId = $_SESSION['user']['id'] ?? null;
if ($userId && empty($history)) {
    try {
        $row = dbRow("SELECT messages FROM ia_conversations WHERE user_id = ?", [$userId]);
        if ($row && !empty($row['messages'])) {
            $history = json_decode($row['messages'], true) ?: [];
        }
    } catch (Throwable $e) { $history = []; }
}

// Construire le system prompt
$tonePrompt = [
    'motivant'     => "Sois toujours encourageant, positif, donne confiance à l'élève et félicite ses efforts.",
    'strict'       => "Sois exigeant, direct, corrige les erreurs sans détour, pousse l'élève à se dépasser.",
    'humoristique' => "Ajoute une touche d'humour et des encouragements amusants, tout en restant pédagogique.",
];
$toneText = $tonePrompt[$tone] ?? $tonePrompt['motivant'];

$basePrompt = $isExercice
    ? "Tu es Coach IA pour la plateforme RÉUSSITE+. Si la question est un exercice, explique la solution étape par étape en détaillant chaque raisonnement, sans donner la réponse finale tout de suite. Encourage l'élève à réfléchir à chaque étape. Sois bienveillant, pédagogique, adapté au niveau lycée RDC. $toneText"
    : "Tu es Coach IA pour la plateforme RÉUSSITE+. Réponds de façon claire, concise, bienveillante et pédagogique, en français, à des élèves préparant l'EXETAT ou des devoirs. Sois motivant, donne des conseils, explique simplement. Adapte-toi au niveau lycée RDC. $toneText";

// Enrichir avec le contexte RAG (questions EXETAT réelles)
$ragContext   = getRagContext($message);
$systemPrompt = $basePrompt . $ragContext;

// ─── Appel IA avec fallback automatique ──────────────────
$reply  = callGemini($apiKey, $model, $systemPrompt, $history, $message, 600);
$engine = 'gemini';

if ($reply === '__QUOTA__' || $reply === null) {
    if ($ghToken && $ghToken !== 'COLLE_TON_PAT_ICI') {
        $reply  = callGithubModels($ghToken, $systemPrompt, $history, $message, 600);
        $engine = 'github';
    }
}

if ($reply === null) {
    echo json_encode(['success' => false, 'reply' => "L'IA n'a pas pu répondre. Vérifie ta connexion et réessaie."]);
    exit;
}
if ($reply === '__QUOTA__') {
    echo json_encode(['success' => false, 'reply' => "⏳ Les deux moteurs IA sont temporairement indisponibles. Réessaie dans quelques minutes."]);
    exit;
}

// Sauvegarder l'historique
if ($userId) {
    $history[] = ['role' => 'user',      'content' => $message];
    $history[] = ['role' => 'assistant', 'content' => $reply];
    try {
        $row = dbRow("SELECT id FROM ia_conversations WHERE user_id = ?", [$userId]);
        if ($row) {
            dbQuery("UPDATE ia_conversations SET messages = ?, updated_at = NOW() WHERE user_id = ?",
                [json_encode($history, JSON_UNESCAPED_UNICODE), $userId]);
        } else {
            dbQuery("INSERT INTO ia_conversations (user_id, messages) VALUES (?, ?)",
                [$userId, json_encode($history, JSON_UNESCAPED_UNICODE)]);
        }
    } catch (Throwable $e) { /* non bloquant */ }
}

echo json_encode(['success' => true, 'reply' => $reply, 'history' => $history, 'engine' => $engine]);
