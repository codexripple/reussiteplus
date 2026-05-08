<?php
/**
 * RÉUSSITE+ — Génération de feedback IA pour un devoir
 * Utilisé par les admins École lors de la correction
 */
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$user = current_user();
if (!$user || $user['plan'] !== 'ECOLE') {
    echo json_encode(['ok'=>false,'msg'=>'Accès réservé au plan École.']); exit;
}

$input      = json_decode(file_get_contents('php://input'), true) ?? [];
$titre      = trim($input['titre']       ?? '');
$matiere    = trim($input['matiere']     ?? '');
$type       = trim($input['type']        ?? 'DEVOIR');
$note       = (float)($input['note']     ?? 0);
$pointsMax  = (int)($input['points_max'] ?? 20);
$commentaire= trim($input['commentaire'] ?? '');

if (!$titre) { echo json_encode(['ok'=>false,'msg'=>'Titre manquant.']); exit; }

$system = "Tu es un enseignant expert pour le système éducatif de la RDC (programmes EPST). "
        . "Tu dois générer un feedback pédagogique bienveillant et constructif pour un élève. "
        . "Réponds uniquement en français. Sois concis (3-4 phrases max), encourageant et précis. "
        . "Propose une piste d'amélioration concrète adaptée au niveau secondaire congolais.";

$noteTexte = $note > 0 ? "Note attribuée : {$note}/{$pointsMax}." : '';
$commentaireTexte = $commentaire ? "Commentaire de l'enseignant : {$commentaire}." : '';

$prompt = "Génère un feedback pédagogique pour l'élève.\n"
        . "Type de travail : {$type}.\n"
        . "Matière : " . ($matiere ?: 'non précisée') . ".\n"
        . "Titre : {$titre}.\n"
        . "{$noteTexte}\n{$commentaireTexte}\n"
        . "Le feedback doit : féliciter l'effort, souligner un point fort, proposer une amélioration précise.";

$messages = [
    ['role' => 'system', 'content' => $system],
    ['role' => 'user',   'content' => $prompt],
];

// Gemini primaire
$geminiKey = $_ENV['GEMINI_API_KEY'] ?? '';
if ($geminiKey) {
    $payload = json_encode([
        'systemInstruction' => ['parts' => [['text' => $system]]],
        'contents'          => [['role'=>'user','parts'=>[['text'=>$prompt]]]],
        'generationConfig'  => ['maxOutputTokens' => 200, 'temperature' => 0.7],
    ]);
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$geminiKey}";
    $ch  = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
        CURLOPT_POSTFIELDS=>$payload, CURLOPT_TIMEOUT=>20]);
    $raw  = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); unset($ch);
    if ($raw && $code !== 429) {
        $d = json_decode($raw, true);
        $t = $d['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if ($t) { echo json_encode(['ok'=>true,'feedback'=>trim($t)]); exit; }
    }
}

// Fallback GitHub Models
$ghToken = $_ENV['GITHUB_TOKEN'] ?? '';
if ($ghToken && $ghToken !== 'COLLE_TON_PAT_ICI') {
    $ch2 = curl_init('https://models.inference.ai.azure.com/chat/completions');
    curl_setopt_array($ch2, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json', 'Authorization: Bearer '.$ghToken],
        CURLOPT_POSTFIELDS=>json_encode(['model'=>'gpt-4o-mini','messages'=>$messages,'max_tokens'=>200,'temperature'=>0.7]),
        CURLOPT_TIMEOUT=>20]);
    $raw2 = curl_exec($ch2); $code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE); unset($ch2);
    if ($raw2 && $code2 === 200) {
        $t2 = json_decode($raw2, true)['choices'][0]['message']['content'] ?? null;
        if ($t2) { echo json_encode(['ok'=>true,'feedback'=>trim($t2)]); exit; }
    }
}

echo json_encode(['ok'=>false,'msg'=>'IA temporairement indisponible. Réessayez.']);
