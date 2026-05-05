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

// ── Appel Groq API ──────────────────────────────────────────
function call_groq(array $messages, ?int $maxTokens = null): array {
    if (!GROQ_API_KEY) {
        return ['error' => 'no_key', 'msg' => 'Clé API Groq non configurée. Ajoutez GROQ_API_KEY dans votre environnement ou config.php.'];
    }
    $payload = json_encode([
        'model'       => GROQ_MODEL,
        'messages'    => $messages,
        'max_tokens'  => $maxTokens ?? GROQ_MAX_TOKENS,
        'temperature' => 0.7,
    ]);
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\nAuthorization: Bearer " . GROQ_API_KEY . "\r\n",
            'content' => $payload,
            'timeout' => 30,
            'ignore_errors' => true,
        ]
    ]);
    $raw  = @file_get_contents(GROQ_API_URL, false, $ctx);
    if (!$raw) {
        return ['error' => 'network', 'msg' => 'Impossible de joindre l\'API. Vérifiez votre connexion.'];
    }
    $data = json_decode($raw, true);
    if (isset($data['error'])) {
        $detail = $data['error']['message'] ?? ($data['error']['code'] ?? json_encode($data['error']));
        return ['error' => 'api_error', 'msg' => $detail];
    }
    $content = $data['choices'][0]['message']['content'] ?? '';
    return ['ok' => true, 'content' => $content];
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
// ACTION : Chat assistant
// ════════════════════════════════════════════
if ($action === 'chat') {
    $userMsg    = trim($_POST['message'] ?? '');
    $historyRaw = $_POST['history'] ?? '[]';
    if (!$userMsg) {
        echo json_encode(['error' => 'Message vide.']);
        exit;
    }
    $history = json_decode($historyRaw, true);
    if (!is_array($history)) $history = [];
    // Limiter l'historique aux 8 derniers échanges pour économiser les tokens
    $history = array_slice($history, -8);
    $userCtx = build_user_context($user);
    $systemPrompt = "Tu es RÉUSSITE+IA, un assistant pédagogique expert pour les examens nationaux de la RDC (ENAFEP pour les élèves de 6ème primaire, TENASOSP pour les élèves de 3ème secondaire, EXAMEN D'ÉTAT pour les élèves de terminale). Tu aides les élèves à réviser les matières : Mathématiques, Français, Sciences, Histoire-Géographie, Chimie, Physique, Biologie, Anglais. Tu expliques les concepts de façon claire, tu poses des questions pour vérifier la compréhension, et tu donnes des exemples concrets adaptés au contexte congolais. Réponds toujours en français. Sois bienveillant, encourageant et pédagogique.\n\nContexte de l'élève :\n{$userCtx}";
    $messages = [['role' => 'system', 'content' => $systemPrompt]];
    foreach ($history as $h) {
        if (isset($h['role'], $h['content'])) {
            $messages[] = ['role' => $h['role'], 'content' => $h['content']];
        }
    }
    $messages[] = ['role' => 'user', 'content' => $userMsg];
    $result = call_groq($messages, 800);
    echo json_encode($result);
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
