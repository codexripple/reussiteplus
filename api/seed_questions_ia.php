<?php
/**
 * RÉUSSITE+ — Générateur IA de questions
 * Admin seulement — génère des QCM via Gemini pour chaque matière
 * Accès : /api/seed_questions_ia.php (POST JSON)
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

$user = current_user();
if (!$user || !in_array($user['role'], ['SUPER_ADMIN', 'ADMIN', 'MODERATEUR'])) {
    echo json_encode(['ok'=>false,'msg'=>'Accès réservé aux administrateurs.']); exit;
}

$input      = json_decode(file_get_contents('php://input'), true) ?? [];
$matiereId  = trim($input['matiere_id'] ?? '');
$difficulte = $input['difficulte'] ?? 'INTERMEDIAIRE';
$nbQ        = min(20, max(3, (int)($input['nb'] ?? 5)));

$validDiffs = ['DEBUTANT','ELEMENTAIRE','INTERMEDIAIRE','AVANCE','EXPERT'];
if (!in_array($difficulte, $validDiffs)) $difficulte = 'INTERMEDIAIRE';

if (!$matiereId) { echo json_encode(['ok'=>false,'msg'=>'matiere_id requis.']); exit; }

$matiere = dbRow("SELECT * FROM matieres WHERE id=?", [$matiereId]);
if (!$matiere) { echo json_encode(['ok'=>false,'msg'=>'Matière introuvable.']); exit; }

$geminiKey = $_ENV['GEMINI_API_KEY'] ?? '';
if (!$geminiKey) { echo json_encode(['ok'=>false,'msg'=>'Clé Gemini manquante.']); exit; }

$diffLabel = ['DEBUTANT'=>'débutant (niveau 6ème primaire)','ELEMENTAIRE'=>'élémentaire (niveau 3ème secondaire)','INTERMEDIAIRE'=>'intermédiaire (niveau terminale)','AVANCE'=>'avancé (niveau EXETAT)','EXPERT'=>'expert (niveau supérieur)'][$difficulte];

$prompt = "Tu es un concepteur de questionnaires scolaires pour le programme EPST en République Démocratique du Congo.
Génère exactement {$nbQ} questions QCM de niveau {$diffLabel} en {$matiere['nom']}.

Règles strictes :
1. Chaque question doit avoir 4 options (A, B, C, D)
2. Une seule réponse correcte par question
3. Questions adaptées au programme EPST RDC (ENAFEP, TENASOSP, EXETAT)
4. Contexte congolais quand pertinent
5. Explication courte (1-2 phrases) de la bonne réponse
6. Pas de numérotation dans le texte des questions

Retourne UNIQUEMENT un tableau JSON valide, sans texte avant ou après :
[
  {
    \"enonce\": \"Texte de la question ?\",
    \"options\": [
      {\"lettre\": \"A\", \"texte\": \"Option A\", \"correct\": false},
      {\"lettre\": \"B\", \"texte\": \"Option B\", \"correct\": true},
      {\"lettre\": \"C\", \"texte\": \"Option C\", \"correct\": false},
      {\"lettre\": \"D\", \"texte\": \"Option D\", \"correct\": false}
    ],
    \"explication\": \"Explication de la bonne réponse.\",
    \"difficulte\": \"{$difficulte}\"
  }
]";

$payload = json_encode([
    'contents' => [['role'=>'user','parts'=>[['text'=>$prompt]]]],
    'generationConfig' => ['maxOutputTokens'=>4096, 'temperature'=>0.8, 'responseMimeType'=>'application/json'],
]);

$ch  = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$geminiKey}");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => $payload, CURLOPT_TIMEOUT => 60,
]);
$raw  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
unset($ch);

if ($err || !$raw || $code !== 200) {
    echo json_encode(['ok'=>false,'msg'=>"Erreur Gemini (HTTP $code): $err"]); exit;
}

$resp = json_decode($raw, true);
$text = $resp['candidates'][0]['content']['parts'][0]['text'] ?? '';

// Nettoyer le JSON
$text = preg_replace('/^```json\s*/i', '', trim($text));
$text = preg_replace('/```\s*$/i', '', $text);
$text = trim($text);

$questions = json_decode($text, true);
if (!is_array($questions)) {
    echo json_encode(['ok'=>false,'msg'=>'Réponse Gemini invalide.','raw'=>substr($text,0,300)]); exit;
}

$inserted = 0;
$errors   = [];

foreach ($questions as $q) {
    if (!isset($q['enonce'], $q['options']) || !is_array($q['options'])) continue;

    $enonce     = trim($q['enonce']);
    $explication= trim($q['explication'] ?? '');
    $diff       = in_array($q['difficulte']??'', $validDiffs) ? $q['difficulte'] : $difficulte;

    // Vérifier si la question n'existe pas déjà
    $exists = dbRow("SELECT id FROM question_bank WHERE enonce=? AND matiere_id=?", [$enonce, $matiereId]);
    if ($exists) { $errors[] = "Doublon: " . substr($enonce, 0, 50); continue; }

    try {
        $qid = bin2hex(random_bytes(18));
        $qid = sprintf('%s-%s-%s-%s-%s',
            substr($qid,0,8), substr($qid,8,4), substr($qid,12,4), substr($qid,16,4), substr($qid,20,12)
        );

        dbQuery(
            "INSERT INTO question_bank (id, matiere_id, enonce, type_question, difficulte, objectif, points, status, created_by)
             VALUES (?,?,?,?,?,?,?,?,?)",
            [$qid, $matiereId, $enonce, 'QCM', $diff, $explication ?: null, 1.0, 'PUBLIE', $user['id']]
        );

        $ordre = 0;
        foreach ($q['options'] as $opt) {
            if (!isset($opt['lettre'], $opt['texte'])) continue;
            $est_correcte = (bool)($opt['correct'] ?? false);
            dbQuery(
                "INSERT INTO question_options (id, question_id, lettre, texte, est_correcte, ordre)
                 VALUES (UUID(),?,?,?,?,?)",
                [$qid, strtoupper($opt['lettre']), trim($opt['texte']), $est_correcte ? 1 : 0, $ordre++]
            );
        }
        $inserted++;
    } catch (Exception $e) {
        $errors[] = "Erreur insert: " . $e->getMessage();
    }
}

// Total questions maintenant
$total = (int)(dbRow("SELECT COUNT(*) as n FROM question_bank WHERE matiere_id=? AND status='PUBLIE'", [$matiereId])['n'] ?? 0);

echo json_encode([
    'ok'       => true,
    'inserted' => $inserted,
    'errors'   => $errors,
    'total_matiere' => $total,
    'matiere'  => $matiere['nom'],
    'difficulte'=> $difficulte,
]);
