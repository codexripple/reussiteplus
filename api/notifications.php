<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!is_logged()) {
    echo json_encode(['count' => 0]);
    exit;
}
$user = current_user();
$count = dbRow("SELECT COUNT(*) as n FROM notifications WHERE user_id=? AND lu=0", [$user['id']]);
echo json_encode(['count' => (int)($count['n'] ?? 0)]);
