<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = current_user();
if (!$user || !in_array($user['plan'], ['PREMIUM','ECOLE'])) {
    redirect('/reussiteplus/tarifs.php', 'warning', 'Réservé aux abonnés Premium.');
}
if (!csrf_verify()) { redirect('/reussiteplus/progression.php', 'error', 'Token invalide.'); }

$teacherCode = trim($_POST['teacher_code'] ?? '');
$note        = (float)($_POST['note']        ?? 0);
$clarte      = $_POST['clarte']   !== '' ? (float)$_POST['clarte']   : null;
$aide        = $_POST['aide']     !== '' ? (float)$_POST['aide']     : null;
$commentaire = trim($_POST['commentaire'] ?? '');

if (!$teacherCode || $note < 1 || $note > 5) {
    redirect('/reussiteplus/progression.php', 'error', 'Données invalides.');
}

// Récupérer l'admin de l'école de l'élève
$schoolAdminId = dbScalar(
    "SELECT c.admin_id FROM classe_membres cm JOIN classes_ecole c ON c.id=cm.classe_id
     WHERE cm.eleve_id=? AND cm.statut='ACTIF' LIMIT 1",
    [$user['id']]
);

try {
    dbQuery(
        "INSERT INTO ia_teacher_ratings (teacher_code, student_id, school_admin_id, note, clarte, aide, commentaire)
         VALUES (?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE note=VALUES(note), clarte=VALUES(clarte), aide=VALUES(aide),
         commentaire=VALUES(commentaire), created_at=NOW()",
        [$teacherCode, $user['id'], $schoolAdminId ?: null, $note, $clarte, $aide, $commentaire ?: null]
    );
    redirect('/reussiteplus/progression.php', 'success', 'Merci pour ton évaluation !');
} catch (Exception $e) {
    redirect('/reussiteplus/progression.php', 'error', 'Erreur lors de l\'enregistrement.');
}
