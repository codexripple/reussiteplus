<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Notifications';
$pageActive = 'notifications';
$user = require_login();

// Marquer tout comme lu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_all_read' && csrf_verify()) {
    dbQuery("UPDATE notifications SET lu = 1 WHERE user_id = ?", [$user['id']]);
    redirect('/reussiteplus/notifications.php', 'success', 'Toutes les notifications ont été lues.');
}

// Marquer une seule comme lue
if (isset($_GET['lu']) && $_GET['lu']) {
    dbQuery("UPDATE notifications SET lu = 1 WHERE id = ? AND user_id = ?", [$_GET['lu'], $user['id']]);
}

$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 20;
$total  = dbRow("SELECT COUNT(*) as n FROM notifications WHERE user_id=?", [$user['id']])['n'];
$notifications = dbAll(
    "SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT $limit OFFSET " . (($page - 1) * $limit),
    [$user['id']]
);
$pagination = paginate($total, $page, $limit);
$nonLues = dbRow("SELECT COUNT(*) as n FROM notifications WHERE user_id=? AND lu=0", [$user['id']])['n'];

include __DIR__ . '/includes/header_app.php';
?>

<?= show_flash() ?>

<div style="max-width:700px;margin:0 auto">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
    <div>
      <div style="font-family:var(--font-display);font-size:22px;font-weight:800">Notifications</div>
      <?php if ($nonLues > 0): ?>
      <div style="font-size:13px;color:var(--gris-500)"><?= $nonLues ?> non lue(s)</div>
      <?php endif; ?>
    </div>
    <?php if ($nonLues > 0): ?>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="mark_all_read">
      <button type="submit" class="btn btn-ghost btn-sm">✓ Tout marquer comme lu</button>
    </form>
    <?php endif; ?>
  </div>

  <?php if ($notifications): ?>
  <div style="display:flex;flex-direction:column;gap:8px">
    <?php
    $icons = [
      'PAIEMENT'    => '💳',
      'ABONNEMENT'  => '⭐',
      'EXAMEN'      => '✏️',
      'PROGRESSION' => '📈',
      'SYSTEME'     => 'ℹ️',
      'PROMOTION'   => '🎁',
    ];
    foreach ($notifications as $n):
      $icon = $icons[$n['type']] ?? '🔔';
      $bgColor = $n['lu'] ? 'white' : 'var(--primary-subtle)';
      $borderColor = $n['lu'] ? 'var(--gris-200)' : 'var(--primary)';
    ?>
    <div style="background:<?= $bgColor ?>;border:1.5px solid <?= $borderColor ?>;border-radius:var(--radius-lg);padding:14px 16px;display:flex;gap:12px;align-items:flex-start;transition:.2s">
      <span style="font-size:22px;flex-shrink:0;margin-top:2px"><?= $icon ?></span>
      <div style="flex:1">
        <div style="font-size:14px;font-weight:<?= $n['lu'] ? '400' : '600' ?>;color:var(--gris-900);margin-bottom:3px">
          <?= e($n['titre']) ?>
        </div>
        <?php if ($n['message']): ?>
        <div style="font-size:13px;color:var(--gris-600);line-height:1.5"><?= e($n['message']) ?></div>
        <?php endif; ?>
        <div style="margin-top:8px;display:flex;justify-content:space-between;align-items:center">
          <span style="font-size:11px;color:var(--gris-400)"><?= temps_relatif($n['created_at']) ?></span>
          <div style="display:flex;gap:8px">
            <?php if ($n['lien']): ?>
            <a href="<?= e($n['lien']) ?><?= !$n['lu'] ? '?lu='.$n['id'] : '' ?>" class="btn btn-ghost btn-sm">Voir →</a>
            <?php endif; ?>
            <?php if (!$n['lu']): ?>
            <a href="?lu=<?= e($n['id']) ?>" class="btn btn-ghost btn-sm" style="font-size:11px;color:var(--gris-400)">Marquer lu</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Pagination -->
  <?php if ($pagination['pages'] > 1): ?>
  <div style="display:flex;justify-content:center;gap:8px;padding:24px 0">
    <?php for ($i = 1; $i <= $pagination['pages']; $i++): ?>
    <a href="?page=<?= $i ?>" class="btn <?= $i == $page ? 'btn-primary' : 'btn-ghost' ?> btn-sm"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>

  <?php else: ?>
  <div class="card" style="text-align:center;padding:48px">
    <div style="font-size:48px;margin-bottom:12px">🔔</div>
    <div style="font-size:15px;font-weight:600;margin-bottom:6px">Aucune notification</div>
    <div style="font-size:13px;color:var(--gris-500)">Vous serez notifié des confirmations de paiement, examens et nouveautés.</div>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
