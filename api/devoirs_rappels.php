<?php
/**
 * RÉUSSITE+ — Rappels deadline devoirs
 * Appelé côté élève au chargement de mes_devoirs.php
 * Crée des notifications pour les devoirs J-1 et J-3
 */
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged()) { echo json_encode(['ok'=>false]); exit; }
$userId = $_SESSION['user']['id'];

// Devoirs à venir pour cet élève (J-1 à J+3, non soumis)
$devoirs = dbAll(
    "SELECT d.id, d.titre, d.date_remise, d.type_devoir, c.nom as classe_nom
     FROM devoirs_ecole d
     JOIN classe_membres cm ON cm.classe_id=d.classe_id AND cm.eleve_id=? AND cm.statut='ACTIF'
     JOIN classes_ecole c ON c.id=d.classe_id
     LEFT JOIN soumissions_devoirs sd ON sd.devoir_id=d.id AND sd.eleve_id=?
     WHERE d.actif=1 AND sd.id IS NULL
       AND d.date_remise BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
     ORDER BY d.date_remise ASC",
    [$userId, $userId]
) ?? [];

$created = 0;
foreach ($devoirs as $dv) {
    $jours = (int)ceil((strtotime($dv['date_remise']) - time()) / 86400);
    $urgence = $jours <= 1 ? 'URGENT' : 'RAPPEL';
    $titreNotif = $urgence === 'URGENT'
        ? "Devoir à rendre aujourd'hui !"
        : "Devoir à rendre dans {$jours} jours";
    $msgNotif = "« {$dv['titre']} » ({$dv['classe_nom']}) — date limite : "
              . date('d/m/Y', strtotime($dv['date_remise']));

    // Vérifier qu'une notification similaire n'existe pas déjà dans les dernières 20h
    $existe = dbRow(
        "SELECT id FROM notifications WHERE user_id=? AND titre=? AND created_at >= DATE_SUB(NOW(), INTERVAL 20 HOUR)",
        [$userId, $titreNotif]
    );
    if ($existe) continue;

    dbQuery(
        "INSERT INTO notifications (user_id, type, titre, message, lien, created_at)
         VALUES (?,?,?,?,?, NOW())",
        [$userId, 'DEVOIR', $titreNotif, $msgNotif, '/reussiteplus/mes_devoirs.php']
    );
    $created++;
}

echo json_encode(['ok' => true, 'notifications_creees' => $created]);
