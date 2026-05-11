<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$user  = current_user();
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$pubId = trim($input['pub_id'] ?? '');
$type  = $input['type'] ?? 'CLICK';

if (!$pubId || !in_array($type, ['IMPRESSION','CLICK'])) {
    echo json_encode(['ok'=>false]); exit;
}

try {
    dbQuery(
        "INSERT INTO ad_impressions (pub_id, user_id, type, page, ip_address) VALUES (?,?,?,?,?)",
        [$pubId, $user['id'] ?? null, $type, $_SERVER['HTTP_REFERER'] ?? '', $_SERVER['REMOTE_ADDR'] ?? '']
    );
    echo json_encode(['ok'=>true]);
} catch (Exception $e) {
    echo json_encode(['ok'=>false]);
}
