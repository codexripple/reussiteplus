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
    $action = $_POST['action'] ?? '';

    // Incrémenter compteur de téléchargement
    if ($action === 'download') {
        $archiveId = $_POST['archive_id'] ?? '';
        if (!$archiveId) { echo json_encode(['ok' => false]); exit; }
        dbQuery("UPDATE archives SET nb_telechargements = nb_telechargements + 1 WHERE id=?", [$archiveId]);
        echo json_encode(['ok' => true]);
        exit;
    }
}

http_response_code(400);
echo json_encode(['ok' => false, 'msg' => 'Action inconnue']);
