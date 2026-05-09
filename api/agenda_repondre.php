<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$user = current_user();
if (!$user) { echo json_encode(['ok'=>false]); exit; }

$input     = json_decode(file_get_contents('php://input'), true) ?? [];
$agendaId  = trim($input['agenda_id']  ?? '');
$lettre    = trim($input['lettre']     ?? '');
$questionId= trim($input['question_id']?? '');

if (!$agendaId || !$lettre) { echo json_encode(['ok'=>false,'msg'=>'Données manquantes.']); exit; }

// Vérifier que l'item appartient à l'utilisateur
$item = dbRow("SELECT id FROM agenda_quotidien WHERE id=? AND user_id=?", [$agendaId, $user['id']]);
if (!$item) { echo json_encode(['ok'=>false,'msg'=>'Item introuvable.']); exit; }

// Récupérer la bonne réponse
$bonneReponse = null;
$estCorrecte  = null;
if ($questionId) {
    $correct = dbRow("SELECT lettre FROM question_options WHERE question_id=? AND est_correcte=1 LIMIT 1", [$questionId]);
    if ($correct) {
        $bonneReponse = $correct['lettre'];
        $estCorrecte  = $lettre === $bonneReponse ? 1 : 0;
    }
}

// Enregistrer la réponse
try {
    dbQuery(
        "INSERT INTO agenda_reponses (agenda_id, user_id, question_id, option_choisie, est_correcte)
         VALUES (?,?,?,?,?)
         ON DUPLICATE KEY UPDATE option_choisie=VALUES(option_choisie), est_correcte=VALUES(est_correcte), repondu_le=NOW()",
        [$agendaId, $user['id'], $questionId ?: null, $lettre, $estCorrecte]
    );
    // Marquer l'item comme fait si réponse donnée
    dbQuery("UPDATE agenda_quotidien SET statut='FAIT' WHERE id=?", [$agendaId]);
} catch (Exception $e) {
    echo json_encode(['ok'=>false,'msg'=>'Erreur enregistrement.']); exit;
}

echo json_encode([
    'ok'           => true,
    'correct'      => $estCorrecte === 1,
    'bonne_reponse'=> $bonneReponse,
]);
