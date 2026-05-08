<?php
/**
 * RÉUSSITE+ — API Révision IA
 * Endpoint AJAX pour le chat IA et la génération du plan de révision
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');

// Auth
if (!is_logged()) {
    echo json_encode(['error' => 'Non connecté.']);
    exit;
}
$user = current_user();

// CSRF
if (!csrf_verify()) {
    echo json_encode(['error' => 'Token invalide.']);
    exit;
}

// Vérifier plan IA
$planData = PLANS[$user['plan']] ?? [];
if (!($planData['ia'] ?? false)) {
    echo json_encode(['error' => 'plan_required', 'msg' => 'La révision IA est disponible avec le plan Excellence ou Institution.']);
    exit;
}

$action = $_POST['action'] ?? '';

// ── Construire le contexte utilisateur ─────────────────────
function build_user_context(array $user): string {
    $progression = dbAll(
        "SELECT m.nom as matiere, up.score_moyen, up.questions_vues, up.bonnes_reponses
         FROM user_progression up
         JOIN matieres m ON up.matiere_id = m.id
         WHERE up.user_id = ?
         ORDER BY up.score_moyen ASC",
        [$user['id']]
    );
    $recentSessions = dbAll(
        "SELECT es.titre, es.pourcentage, es.exam_type, m.nom as matiere, es.finished_at
         FROM exam_sessions es
         LEFT JOIN matieres m ON es.matiere_id = m.id
         WHERE es.user_id = ? AND es.statut = 'TERMINE'
         ORDER BY es.finished_at DESC LIMIT 10",
        [$user['id']]
    );
    $context  = "Profil de l'élève : {$user['prenom']} {$user['nom']}, plan {$user['plan']}.\n";
    $context .= "Score moyen global : " . number_format((float)($user['score_moyen'] ?? 0), 1) . "%.\n";
    $context .= "Total examens passés : " . (int)($user['total_examens'] ?? 0) . ".\n\n";
    if ($progression) {
        $context .= "Progression par matière :\n";
        foreach ($progression as $p) {
            $context .= "- {$p['matiere']} : " . number_format((float)$p['score_moyen'], 1) . "% ({$p['questions_vues']} questions vues)\n";
        }
        $context .= "\n";
    }
    if ($recentSessions) {
        $context .= "5 derniers examens :\n";
        foreach (array_slice($recentSessions, 0, 5) as $s) {
            $context .= "- {$s['matiere']} ({$s['exam_type']}) : " . number_format((float)$s['pourcentage'], 0) . "% — " . date('d/m/Y', strtotime($s['finished_at'])) . "\n";
        }
    }
    return $context;
}

// ── Modèle rapide (pas de thinking tokens) ───────────────────
const IA_MODEL = 'gemini-2.0-flash';

// ── Appel Gemini non-streaming ────────────────────────────────
function call_ia(array $messages, ?int $maxTokens = null): array {
    $max       = $maxTokens ?? 600;
    $geminiKey = $_ENV['GEMINI_API_KEY'] ?? '';
    $system    = '';
    $contents  = [];

    foreach ($messages as $m) {
        if ($m['role'] === 'system') $system = $m['content'];
        else $contents[] = [
            'role'  => $m['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $m['content']]],
        ];
    }

    if ($geminiKey) {
        $payload = json_encode([
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents'          => $contents,
            'generationConfig'  => ['maxOutputTokens' => $max, 'temperature' => 0.7],
        ]);
        $url = "https://generativelanguage.googleapis.com/v1beta/models/" . IA_MODEL . ":generateContent?key={$geminiKey}";
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => $payload, CURLOPT_TIMEOUT => 30,
        ]);
        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch);
        if ($raw && $code !== 429) {
            $d = json_decode($raw, true);
            $t = $d['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if ($t) return ['ok' => true, 'content' => $t];
        }
    }

    // Fallback GitHub Models
    $ghToken = $_ENV['GITHUB_TOKEN'] ?? '';
    if ($ghToken && $ghToken !== 'COLLE_TON_PAT_ICI') {
        $ch2 = curl_init('https://models.inference.ai.azure.com/chat/completions');
        curl_setopt_array($ch2, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $ghToken],
            CURLOPT_POSTFIELDS     => json_encode(['model'=>'gpt-4o-mini','messages'=>$messages,'max_tokens'=>$max,'temperature'=>0.7]),
            CURLOPT_TIMEOUT => 30,
        ]);
        $raw2 = curl_exec($ch2); $code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE); unset($ch2);
        if ($raw2 && $code2 === 200) {
            $t = json_decode($raw2,true)['choices'][0]['message']['content'] ?? null;
            if ($t) return ['ok' => true, 'content' => $t];
        }
    }
    return ['error' => 'network', 'msg' => 'Moteur IA indisponible. Réessaie dans quelques instants.'];
}

// ── Streaming SSE pour le chat (tokens en temps réel) ─────────
function stream_chat(array $messages, int $maxTokens = 500): void {
    // Désactiver tout buffering
    while (ob_get_level()) ob_end_clean();
    ini_set('output_buffering', 'off');
    ini_set('zlib.output_compression', 'off');
    set_time_limit(60);

    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    header('Connection: keep-alive');

    $geminiKey = $_ENV['GEMINI_API_KEY'] ?? '';
    $system    = '';
    $contents  = [];
    foreach ($messages as $m) {
        if ($m['role'] === 'system') $system = $m['content'];
        else $contents[] = [
            'role'  => $m['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $m['content']]],
        ];
    }

    if ($geminiKey) {
        $payload = json_encode([
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents'          => $contents,
            'generationConfig'  => ['maxOutputTokens' => $maxTokens, 'temperature' => 0.7],
        ]);
        $url    = "https://generativelanguage.googleapis.com/v1beta/models/" . IA_MODEL . ":streamGenerateContent?alt=sse&key={$geminiKey}";
        $buffer = '';
        $ch     = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 55,
            CURLOPT_WRITEFUNCTION  => function($ch, $data) use (&$buffer) {
                $buffer .= $data;
                while (($pos = strpos($buffer, "\n\n")) !== false) {
                    $event  = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 2);
                    foreach (explode("\n", $event) as $line) {
                        if (strncmp($line, 'data: ', 6) === 0) {
                            $json = substr($line, 6);
                            if ($json === '[DONE]') break;
                            $d = json_decode($json, true);
                            $t = $d['candidates'][0]['content']['parts'][0]['text'] ?? '';
                            if ($t !== '') {
                                echo 'data: ' . json_encode(['t' => $t]) . "\n\n";
                                flush();
                            }
                        }
                    }
                }
                return strlen($data);
            },
        ]);
        $err = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch);

        if ($code !== 429) {
            echo "data: [DONE]\n\n"; flush(); return;
        }
    }

    // Fallback non-streaming via GitHub Models
    $result = call_ia($messages, $maxTokens);
    if ($result['ok'] ?? false) {
        // Envoyer le texte en un seul event
        echo 'data: ' . json_encode(['t' => $result['content']]) . "\n\n";
        flush();
    } else {
        echo 'data: ' . json_encode(['err' => $result['msg']]) . "\n\n";
        flush();
    }
    echo "data: [DONE]\n\n"; flush();
}

function call_groq(array $messages, ?int $maxTokens = null): array {
    return call_ia($messages, $maxTokens);
}

// ════════════════════════════════════════════
// ACTION : Générer le plan de révision
// ════════════════════════════════════════════
if ($action === 'plan_revision') {
    $userCtx = build_user_context($user);
    $messages = [
        [
            'role'    => 'system',
            'content' => "Tu es un tuteur pédagogique expert pour les examens nationaux de la République Démocratique du Congo (ENAFEP, TENASOSP, EXAMEN D'ÉTAT). Tu dois créer des plans de révision personnalisés, précis et motivants. Réponds toujours en français. Sois concis mais complet. Utilise des emojis pour la lisibilité. Structure ta réponse en sections claires."
        ],
        [
            'role'    => 'user',
            'content' => "Voici le profil de mon élève :\n\n{$userCtx}\n\nGénère un plan de révision hebdomadaire personnalisé sur 7 jours (lundi à dimanche). Pour chaque jour : indique la matière prioritaire, les thèmes à réviser, la durée recommandée, et un conseil. Mets l'accent sur les matières faibles. Termine par 3 conseils stratégiques personnalisés pour améliorer le score global."
        ]
    ];
    $result = call_groq($messages, 1400);
    echo json_encode($result);
    exit;
}

// ════════════════════════════════════════════
// ACTION : Chat assistant (streaming SSE)
// ════════════════════════════════════════════
if ($action === 'chat') {
    $userMsg    = trim($_POST['message'] ?? '');
    $historyRaw = $_POST['history'] ?? '[]';
    if (!$userMsg) { echo json_encode(['error' => 'Message vide.']); exit; }

    $history = json_decode($historyRaw, true);
    if (!is_array($history)) $history = [];
    $history = array_slice($history, -6); // 6 échanges max = moins de tokens = plus rapide

    // Contexte utilisateur minimal (score + matières faibles seulement)
    $scoreM   = number_format((float)($user['score_moyen'] ?? 0), 1);
    $weakMats = dbAll(
        "SELECT m.nom FROM user_progression up JOIN matieres m ON up.matiere_id=m.id
         WHERE up.user_id=? ORDER BY up.score_moyen ASC LIMIT 3",
        [$user['id']]
    );
    $weakList = implode(', ', array_column($weakMats, 'nom')) ?: 'non déterminées';

    $systemPrompt = "Tu es RÉUSSITE+IA, tuteur expert pour les examens nationaux RDC (ENAFEP, TENASOSP, EXAMEN D'ÉTAT). "
        . "Élève : {$user['prenom']}, score moyen {$scoreM}%, matières faibles : {$weakList}. "
        . "Réponds en français, sois concis (3-5 phrases max sauf si demande de détail), bienveillant et pédagogique. "
        . "Adapte tes exemples au contexte congolais.";

    $messages = [['role' => 'system', 'content' => $systemPrompt]];
    foreach ($history as $h) {
        if (isset($h['role'], $h['content'])) $messages[] = $h;
    }
    $messages[] = ['role' => 'user', 'content' => $userMsg];

    stream_chat($messages, 450);
    exit;
}

// ════════════════════════════════════════════
// ACTION : Analyse des erreurs (flashcards)
// ════════════════════════════════════════════
if ($action === 'analyse_erreurs') {
    // Récupérer les 10 questions les plus ratées
    $erreurs = dbAll(
        "SELECT qb.enonce, qb.difficulte, m.nom as matiere,
                correct_opt.texte as bonne_reponse, correct_opt.explication,
                COUNT(ea.id) as nb_erreurs
         FROM exam_answers ea
         JOIN question_bank qb ON ea.question_id = qb.id
         JOIN matieres m ON qb.matiere_id = m.id
         JOIN exam_sessions es ON ea.session_id = es.id
         JOIN question_options correct_opt ON correct_opt.question_id = qb.id AND correct_opt.est_correcte = 1
         WHERE es.user_id = ? AND ea.est_correcte = 0
         GROUP BY qb.id
         ORDER BY nb_erreurs DESC
         LIMIT 5",
        [$user['id']]
    );
    if (!$erreurs) {
        echo json_encode(['ok' => true, 'content' => "Bravo ! Pas encore suffisamment de données pour analyser vos erreurs. Passez quelques examens et revenez ici pour une analyse personnalisée. 🎯"]);
        exit;
    }
    $erreursTxt = "Questions les plus souvent ratées :\n";
    foreach ($erreurs as $e) {
        $erreursTxt .= "- [{$e['matiere']}] {$e['enonce']} → Bonne réponse : {$e['bonne_reponse']} ({$e['nb_erreurs']} erreur(s))\n";
    }
    $messages = [
        ['role' => 'system', 'content' => "Tu es un tuteur expert pour les examens nationaux de RDC. Analyse les erreurs récurrentes d'un élève et fournis des explications pédagogiques claires pour chaque point faible. Utilise des exemples simples. Réponds en français."],
        ['role' => 'user', 'content' => "Voici les questions que je rate souvent :\n\n{$erreursTxt}\n\nPour chacune, explique le concept clé à maîtriser et donne un moyen mnémotechnique ou une astuce pour ne plus faire cette erreur."]
    ];
    $result = call_groq($messages, 1200);
    echo json_encode($result);
    exit;
}

echo json_encode(['error' => 'Action inconnue.']);
