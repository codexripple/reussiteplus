<?php
// RÉUSSITE+ | Rate Limiting simple (table SQL)
// Utilisation : rate_limit_check($action, $key, $max, $window)

function rate_limit_check(string $action, string $key, int $max, int $window): bool {
    $pdo = db();
    $now = time();
    $windowStart = $now - $window;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM rate_limits WHERE action=? AND rate_key=? AND ts > ?");
    $stmt->execute([$action, $key, $windowStart]);
    $count = (int)$stmt->fetchColumn();
    if ($count >= $max) return false;
    $pdo->prepare("INSERT INTO rate_limits (action, rate_key, ts) VALUES (?, ?, ?)")->execute([$action, $key, $now]);
    return true;
}
