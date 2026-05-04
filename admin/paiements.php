<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$pageTitle  = 'Gestion des paiements';
$pageActive = 'admin';
$user = require_admin();

// Confirmer ou refuser un paiement
if (isset($_GET['action'], $_GET['id']) && !isset($_GET['confirm'])) {
    $action = $_GET['action'];
    $id     = $_GET['id'];
    $abon = dbRow("SELECT a.*, u.id as uid, u.plan as user_plan FROM abonnements a JOIN utilisateurs u ON a.user_id=u.id WHERE a.id=?", [$id]);
    if (!$abon) {
        redirect('/reussiteplus/admin/paiements.php', 'error', 'Paiement introuvable.');
    }
    if ($action === 'confirmer' && $abon['statut'] === 'EN_ATTENTE') {
        dbQuery("UPDATE abonnements SET statut='CONFIRME', confirmed_at=NOW() WHERE id=?", [$id]);
        dbQuery("UPDATE utilisateurs SET plan=?, plan_expire_at=? WHERE id=?",
            [$abon['plan'], $abon['date_fin'], $abon['uid']]);
        dbInsert('notifications', [
            'user_id' => $abon['uid'],
            'type'    => 'PAIEMENT',
            'titre'   => '✅ Paiement confirmé — ' . (PLANS[$abon['plan']]['nom'] ?? $abon['plan']),
            'message' => "Votre abonnement {$abon['plan']} a été activé jusqu'au " . date('d/m/Y', strtotime($abon['date_fin'])) . ". Bon apprentissage !",
            'lien'    => '/reussiteplus/abonnement.php',
        ]);
        dbInsert('admin_logs', ['user_id' => $user['id'], 'action' => 'CONFIRMER_PAIEMENT', 'details' => "ID=$id ref={$abon['reference_paiement']}"]);
        redirect('/reussiteplus/admin/paiements.php', 'success', 'Paiement confirmé et plan activé.');
    } elseif ($action === 'refuser' && $abon['statut'] === 'EN_ATTENTE') {
        dbQuery("UPDATE abonnements SET statut='ECHEC' WHERE id=?", [$id]);
        dbInsert('notifications', [
            'user_id' => $abon['uid'],
            'type'    => 'PAIEMENT',
            'titre'   => '❌ Paiement refusé — réf. ' . $abon['reference_paiement'],
            'message' => "Votre paiement n'a pas pu être vérifié. Contactez support@reussiteplus.cd.",
            'lien'    => '/reussiteplus/abonnement.php',
        ]);
        dbInsert('admin_logs', ['user_id' => $user['id'], 'action' => 'REFUSER_PAIEMENT', 'details' => "ID=$id"]);
        redirect('/reussiteplus/admin/paiements.php', 'success', 'Paiement refusé.');
    }
}

// Filtres
$statut = $_GET['statut'] ?? 'EN_ATTENTE';
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 20;

$validStatuts = ['EN_ATTENTE', 'CONFIRME', 'ECHEC', 'REMBOURSE', 'TOUS'];
if (!in_array($statut, $validStatuts, true)) $statut = 'EN_ATTENTE';

$where  = $statut !== 'TOUS' ? "WHERE a.statut=?" : "";
$params = $statut !== 'TOUS' ? [$statut] : [];

$total   = dbRow("SELECT COUNT(*) as n FROM abonnements a $where", $params)['n'];
$offset  = ($page - 1) * $limit;
$paiements = dbAll(
    "SELECT a.*, u.email, u.prenom, u.nom FROM abonnements a
     JOIN utilisateurs u ON a.user_id=u.id
     $where ORDER BY a.created_at DESC LIMIT $limit OFFSET $offset",
    $params
);
$pagination = paginate($total, $page, $limit);

include __DIR__ . '/../includes/header_app.php';
?>

<?= show_flash() ?>

<!-- Onglets statuts -->
<div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
  <?php foreach ($validStatuts as $s):
    $cnt = $s === 'TOUS' ? dbRow("SELECT COUNT(*) as n FROM abonnements")['n'] : dbRow("SELECT COUNT(*) as n FROM abonnements WHERE statut=?", [$s])['n'];
    $active = $statut === $s;
  ?>
  <a href="?statut=<?= $s ?>" class="btn <?= $active ? 'btn-primary' : 'btn-ghost' ?> btn-sm">
    <?= $s === 'EN_ATTENTE' ? '⏳' : ($s === 'CONFIRME' ? '✅' : ($s === 'ECHEC' ? '❌' : ($s === 'REMBOURSE' ? '↩️' : '📋'))) ?>
    <?= $s ?> (<?= $cnt ?>)
  </a>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="card-header">
    <div class="card-title">💳 Paiements — <?= $statut ?></div>
    <div style="font-size:12px;color:var(--gris-500)"><?= $total ?> résultat(s)</div>
  </div>

  <?php if ($paiements): ?>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr><th>Référence</th><th>Utilisateur</th><th>Plan</th><th>Montant</th><th>Méthode / Tél</th><th>Période</th><th>Statut</th><th>Action</th></tr>
      </thead>
      <tbody>
      <?php foreach ($paiements as $p):
        $sc = ['EN_ATTENTE'=>['#FEF3C7','#92400E'],'CONFIRME'=>['#D1FAE5','#064E3B'],'ECHEC'=>['#FEE2E2','#7F1D1D'],'REMBOURSE'=>['#F3F4F6','#6B7280']];
        $c = $sc[$p['statut']] ?? $sc['EN_ATTENTE'];
      ?>
      <tr>
        <td style="font-family:var(--font-mono);font-size:11px"><?= e($p['reference_paiement']) ?></td>
        <td style="font-size:13px">
          <?= e($p['prenom'] . ' ' . $p['nom']) ?>
          <div style="font-size:10px;color:var(--gris-500)"><?= e($p['email']) ?></div>
        </td>
        <td><?= badge_plan($p['plan']) ?></td>
        <td style="font-weight:700;white-space:nowrap">
          <?= number_format((float)$p['montant'], 0, ',', ' ') ?> <?= e($p['devise']) ?>
          <?php if ($p['remise'] > 0): ?>
          <div style="font-size:10px;color:var(--primary)">-<?= number_format((float)$p['remise'], 0) ?> promo</div>
          <?php endif; ?>
        </td>
        <td style="font-size:12px">
          <?= e(METHODES_PAIEMENT[$p['methode_paiement']]['nom'] ?? $p['methode_paiement']) ?>
          <div style="font-size:10px;color:var(--gris-500)"><?= e($p['telephone']) ?></div>
        </td>
        <td style="font-size:11px;color:var(--gris-500)">
          <?= date('d/m/Y', strtotime($p['date_debut'])) ?><br>→<?= date('d/m/Y', strtotime($p['date_fin'])) ?>
        </td>
        <td>
          <span style="background:<?= $c[0] ?>;color:<?= $c[1] ?>;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700">
            <?= e($p['statut']) ?>
          </span>
        </td>
        <td>
          <?php if ($p['statut'] === 'EN_ATTENTE'): ?>
          <div style="display:flex;gap:4px">
            <a href="?action=confirmer&id=<?= e($p['id']) ?>&statut=<?= $statut ?>" class="btn btn-primary btn-sm" onclick="return confirm('Confirmer ce paiement de <?= e($p['prenom']) ?> ?')">✓</a>
            <a href="?action=refuser&id=<?= e($p['id']) ?>&statut=<?= $statut ?>" class="btn btn-danger btn-sm" onclick="return confirm('Refuser ?')">&#10007;</a>
          </div>
          <?php else: ?>
          <span style="font-size:11px;color:var(--gris-400)">—</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($pagination['total_pages'] > 1): ?>
  <div style="display:flex;justify-content:center;gap:6px;padding:16px">
    <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
    <a href="?statut=<?= $statut ?>&page=<?= $i ?>" class="btn <?= $i == $page ? 'btn-primary' : 'btn-ghost' ?> btn-sm"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>

  <?php else: ?>
  <div style="text-align:center;padding:40px;color:var(--gris-500)">
    <div style="font-size:40px;margin-bottom:8px">💳</div>
    <div>Aucun paiement avec ce statut.</div>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer_app.php'; ?>
