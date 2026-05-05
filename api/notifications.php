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

// Pour les admins : compter les alertes admin (paiements en attente + messages non lus)
if (is_admin()) {
    $payCount = (int)(dbRow("SELECT COUNT(*) as n FROM abonnements WHERE statut='EN_ATTENTE'")['n'] ?? 0);
    $msgCount = (int)(dbRow("SELECT COUNT(*) as n FROM contact_messages WHERE statut='NOUVEAU'")['n'] ?? 0);
    echo json_encode(['count' => $payCount + $msgCount]);
    exit;
}

// Pour les élèves : notifications personnelles non lues
$count = dbRow("SELECT COUNT(*) as n FROM notifications WHERE user_id=? AND lu=0", [$user['id']]);
echo json_encode(['count' => (int)($count['n'] ?? 0)]);

