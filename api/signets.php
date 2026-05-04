<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

if (!is_logged()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Non authentifié']);
    exit;
}

$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type  = $_POST['type'] ?? '';
    $refId = $_POST['ref_id'] ?? '';

    if (!in_array($type, ['ARCHIVE', 'QUESTION'], true) || !$refId) {
        echo json_encode(['ok' => false, 'msg' => 'Paramètres invalides']);
        exit;
    }

    // Toggle signet — map type to actual column name
    if ($type === 'ARCHIVE') {
        $existing = dbRow(
            "SELECT id FROM signets WHERE user_id=? AND archive_id=?",
            [$user['id'], $refId]
        );
        if ($existing) {
            dbQuery("DELETE FROM signets WHERE id=?", [$existing['id']]);
            echo json_encode(['ok' => true, 'added' => false]);
        } else {
            dbInsert('signets', ['user_id' => $user['id'], 'archive_id' => $refId]);
            echo json_encode(['ok' => true, 'added' => true]);
        }
    } else {
        $existing = dbRow(
            "SELECT id FROM signets WHERE user_id=? AND question_id=?",
            [$user['id'], $refId]
        );
        if ($existing) {
            dbQuery("DELETE FROM signets WHERE id=?", [$existing['id']]);
            echo json_encode(['ok' => true, 'added' => false]);
        } else {
            dbInsert('signets', ['user_id' => $user['id'], 'question_id' => $refId]);
            echo json_encode(['ok' => true, 'added' => true]);
        }
    }
    exit;
}

// GET : liste des signets
$type = $_GET['type'] ?? 'ARCHIVE';
if ($type === 'ARCHIVE') {
    $signets = dbAll(
        "SELECT archive_id as ref_id FROM signets WHERE user_id=? AND archive_id IS NOT NULL",
        [$user['id']]
    );
} else {
    $signets = dbAll(
        "SELECT question_id as ref_id FROM signets WHERE user_id=? AND question_id IS NOT NULL",
        [$user['id']]
    );
}
echo json_encode(['ok' => true, 'signets' => array_column($signets, 'ref_id')]);
