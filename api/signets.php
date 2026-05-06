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

    // Toggle signet
    $existing = dbRow(
        "SELECT id FROM signets WHERE user_id=? AND type=? AND ref_id=?",
        [$user['id'], $type, $refId]
    );

    if ($existing) {
        dbQuery("DELETE FROM signets WHERE id=?", [$existing['id']]);
        echo json_encode(['ok' => true, 'added' => false]);
    } else {
        dbInsert('signets', [
            'user_id' => $user['id'],
            'type'    => $type,
            'ref_id'  => $refId,
        ]);
        echo json_encode(['ok' => true, 'added' => true]);
    }
    exit;
}

// GET : liste des signets
$type = $_GET['type'] ?? 'ARCHIVE';
$signets = dbAll(
    "SELECT ref_id FROM signets WHERE user_id=? AND type=?",
    [$user['id'], $type]
);
echo json_encode(['ok' => true, 'signets' => array_column($signets, 'ref_id')]);
