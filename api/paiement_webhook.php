<?php
// RÉUSSITE+ | Webhook de validation paiement opérateur
// Sécurisé par clé secrète (env)
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

$secret = $_ENV['WEBHOOK_SECRET'] ?? '';
if (!$secret || ($_GET['secret'] ?? '') !== $secret) {
    http_response_code(403); echo 'Forbidden'; exit;
}

// Format attendu : POST JSON { reference, statut, montant, operateur }
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || empty($input['reference']) || empty($input['statut'])) {
    http_response_code(400); echo 'Bad Request'; exit;
}

$ref = $input['reference'];
$statut = strtoupper($input['statut']); // CONFIRME ou ECHEC
$operateur = $input['operateur'] ?? 'API';
$montant = (int)($input['montant'] ?? 0);

$abon = dbRow("SELECT * FROM abonnements WHERE reference_paiement=?", [$ref]);
if (!$abon) { http_response_code(404); echo 'Not found'; exit; }
if ($abon['statut'] !== 'EN_ATTENTE') { echo 'Déjà traité'; exit; }

if ($statut === 'CONFIRME') {
    dbQuery("UPDATE abonnements SET statut='CONFIRME', confirmed_at=NOW(), confirmed_by=NULL, operateur=? WHERE id=?", [$operateur, $abon['id']]);
    dbQuery("UPDATE utilisateurs SET plan=?, plan_expire_at=? WHERE id=?", [$abon['plan'], $abon['date_fin'], $abon['user_id']]);
    dbInsert('notifications', [
        'user_id' => $abon['user_id'],
        'type'    => 'PAIEMENT',
        'titre'   => 'Abonnement ' . $abon['plan'] . ' activé !',
        'message' => 'Votre paiement a été confirmé automatiquement.',
        'lien'    => '/reussiteplus/abonnement.php',
    ]);
    echo 'OK';
} elseif ($statut === 'ECHEC') {
    dbQuery("UPDATE abonnements SET statut='ECHEC', operateur=? WHERE id=?", [$operateur, $abon['id']]);
    dbInsert('notifications', [
        'user_id' => $abon['user_id'],
        'type'    => 'PAIEMENT',
        'titre'   => 'Paiement non vérifié',
        'message' => 'Votre paiement n\'a pas pu être vérifié. Contactez support@reussiteplus.cd.',
        'lien'    => '/reussiteplus/abonnement.php',
    ]);
    echo 'ECHEC';
} else {
    http_response_code(400); echo 'Statut inconnu';
}
