<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_login();
if (!csrf_verify()) { redirect('/reussiteplus/agenda.php', 'error', 'Token invalide.'); }

$agendaId = $_POST['agenda_id'] ?? '';
$statut   = in_array($_POST['statut'] ?? '', ['FAIT','A_FAIRE','IGNORE']) ? $_POST['statut'] : 'FAIT';

if ($agendaId) {
    dbQuery("UPDATE agenda_quotidien SET statut=? WHERE id=? AND user_id=?", [$statut, $agendaId, $user['id']]);
}
redirect('/reussiteplus/agenda.php');
