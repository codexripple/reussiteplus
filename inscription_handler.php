<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/rate_limit.php';

if (is_logged()) { header('Location: /reussiteplus/dashboard.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /reussiteplus/inscription.php'); exit; }

if (!csrf_verify()) {
    redirect('/reussiteplus/inscription.php', 'error', 'Token de sécurité invalide. Rechargez la page.');
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!rate_limit_check('inscription', $ip, 5, 3600)) {
    redirect('/reussiteplus/inscription.php', 'error', 'Trop de tentatives. Réessayez dans une heure.');
}

$referralUser = null;
$refCode = trim($_POST['ref'] ?? '');
if ($refCode) {
    try { $referralUser = dbRow("SELECT id FROM utilisateurs WHERE referral_code = ?", [$refCode]); }
    catch (Exception $e) {}
}

$result = auth_register([
    'nom'          => trim($_POST['nom'] ?? ''),
    'prenom'       => trim($_POST['prenom'] ?? ''),
    'email'        => trim($_POST['email'] ?? ''),
    'password'     => $_POST['password'] ?? '',
    'classe'       => trim($_POST['classe'] ?? ''),
    'province_id'  => $_POST['province_id'] ?? null,
    'referral_par' => $referralUser ? $referralUser['id'] : null,
]);

if ($result['ok']) {
    redirect('/reussiteplus/dashboard.php?welcome=1', 'success', 'Bienvenue sur RÉUSSITE+ !');
} else {
    redirect('/reussiteplus/inscription.php', 'error', $result['msg']);
}
