<?php
/**
 * RÉUSSITE+ — Chat Professeur IA avec Persona
 * SSE streaming — chaque prof a son style pédagogique distinct
 */
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ia_teachers.php';

while (ob_get_level()) ob_end_clean();
ini_set('output_buffering', 'off');
set_time_limit(60);

$user = current_user();
if (!$user || !in_array($user['plan'], ['PREMIUM', 'ECOLE'])) {
    echo "data: " . json_encode(['err' => 'Accès Premium requis.']) . "\n\n";
    flush(); exit;
}

$input      = json_decode(file_get_contents('php://input'), true) ?? [];
$teacherKey = trim($input['teacher']  ?? '');
$message    = trim($input['message']  ?? '');
$history    = is_array($input['history'] ?? null) ? $input['history'] : [];

if (!$message) {
    echo "data: " . json_encode(['err' => 'Message vide.']) . "\n\n";
    flush(); exit;
}

// Récupérer le persona du professeur
$teacher = IA_TEACHERS[$teacherKey] ?? null;
if (!$teacher) {
    // Fallback : détecter la matière dans le message
    $teacher = get_teacher_by_matiere($message) ?? array_values(IA_TEACHERS)[0];
}

// Construire le contexte élève
$progression = dbAll(
    "SELECT m.nom as matiere, up.score_moyen
     FROM user_progression up JOIN matieres m ON m.id=up.matiere_id
     WHERE up.user_id=? ORDER BY up.score_moyen ASC LIMIT 3",
    [$user['id']]
) ?? [];
$weakMats = implode(', ', array_column($progression, 'matiere')) ?: 'non déterminées';

// System prompt du professeur + contexte élève
$systemPrompt = $teacher['system_prompt']
    . "\n\nContexte de l'élève : {$user['prenom']}, plan {$user['plan']}."
    . " Matières faibles : {$weakMats}."
    . " Adapte ton niveau de langue et tes exemples à cet élève.";

// Construire l'historique de conversation
$contents = [];
foreach (array_slice($history, -10) as $h) {
    if (!isset($h['role'], $h['content'])) continue;
    $contents[] = [
        'role'  => $h['role'] === 'assistant' ? 'model' : 'user',
        'parts' => [['text' => $h['content']]],
    ];
}
$contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];

// ── Streaming Gemini ──────────────────────────────────────────
$geminiKey = $_ENV['GEMINI_API_KEY'] ?? '';
$sent      = false;

if ($geminiKey) {
    $payload = json_encode([
        'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
        'contents'          => $contents,
        'generationConfig'  => ['maxOutputTokens' => 500, 'temperature' => 0.75],
    ]);
    $url    = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:streamGenerateContent?alt=sse&key={$geminiKey}";
    $buffer = '';
    $ch     = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST          => true,
        CURLOPT_HTTPHEADER    => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS    => $payload,
        CURLOPT_TIMEOUT       => 55,
        CURLOPT_WRITEFUNCTION => function($ch, $data) use (&$buffer, &$sent) {
            $buffer .= $data;
            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $event  = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);
                foreach (explode("\n", $event) as $line) {
                    if (strncmp($line, 'data: ', 6) !== 0) continue;
                    $json = substr($line, 6);
                    if ($json === '[DONE]') break;
                    $d = json_decode($json, true);
                    $t = $d['candidates'][0]['content']['parts'][0]['text'] ?? '';
                    if ($t !== '') {
                        $sent = true;
                        echo 'data: ' . json_encode(['t' => $t, 'teacher' => ['id'=>null]]) . "\n\n";
                        flush();
                    }
                }
            }
            return strlen($data);
        },
    ]);
    $err  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unset($ch);
    if ($code !== 429 && $sent) {
        // Enregistrer la session pour les stats
        try {
            dbQuery(
                "INSERT INTO ia_teacher_sessions (teacher_code, student_id, school_admin_id, created_at)
                 SELECT ?, ?, c.admin_id, NOW()
                 FROM classe_membres cm JOIN classes_ecole c ON c.id=cm.classe_id
                 WHERE cm.eleve_id=? AND cm.statut='ACTIF' LIMIT 1",
                [$teacher['id'], $user['id'], $user['id']]
            );
        } catch (Exception $e) {}
        echo "data: [DONE]\n\n"; flush(); exit;
    }
}

// ── Fallback GitHub Models (non-streaming) ────────────────────
$ghToken = $_ENV['GITHUB_TOKEN'] ?? '';
if ($ghToken && $ghToken !== 'COLLE_TON_PAT_ICI') {
    $msgs = [['role'=>'system','content'=>$systemPrompt]];
    foreach (array_slice($history, -10) as $h) {
        if (isset($h['role'],$h['content'])) $msgs[] = $h;
    }
    $msgs[] = ['role'=>'user','content'=>$message];
    $ch2 = curl_init('https://models.inference.ai.azure.com/chat/completions');
    curl_setopt_array($ch2, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json','Authorization: Bearer '.$ghToken],
        CURLOPT_POSTFIELDS     => json_encode(['model'=>'gpt-4o-mini','messages'=>$msgs,'max_tokens'=>500,'temperature'=>0.75]),
        CURLOPT_TIMEOUT => 30,
    ]);
    $raw2 = curl_exec($ch2); $code2 = curl_getinfo($ch2,CURLINFO_HTTP_CODE); unset($ch2);
    if ($raw2 && $code2 === 200) {
        $t2 = json_decode($raw2,true)['choices'][0]['message']['content'] ?? null;
        if ($t2) {
            echo 'data: ' . json_encode(['t' => $t2]) . "\n\n"; flush();
            echo "data: [DONE]\n\n"; flush(); exit;
        }
    }
}

echo "data: " . json_encode(['err' => 'IA temporairement indisponible.']) . "\n\n";
echo "data: [DONE]\n\n"; flush();
