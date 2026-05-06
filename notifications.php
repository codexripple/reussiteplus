<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Notifications';
$pageActive = 'notifications';
$user = require_login();

// ── Rediriger les admins vers leur page dédiée ──────────────
if (is_admin()) {
    header('Location: /reussiteplus/admin/notifications.php');
    exit;
}

// ── Marquer tout comme lu ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_all_read' && csrf_verify()) {
    dbQuery("UPDATE notifications SET lu = 1, lu_at = NOW() WHERE user_id = ?", [$user['id']]);
    redirect('/reussiteplus/notifications.php', 'success', 'Toutes les notifications ont été marquées comme lues.');
}

// ── Marquer une seule comme lue (GET + AJAX) ─────────────────
if (($_GET['action'] ?? '') === 'read' && !empty($_GET['id'])) {
    dbQuery(
        "UPDATE notifications SET lu = 1, lu_at = NOW() WHERE id = ? AND user_id = ?",
        [$_GET['id'], $user['id']]
    );
    $isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    if ($isAjax) { header('Content-Type: application/json'); echo '{"ok":true}'; exit; }
    header('Location: /reussiteplus/notifications.php');
    exit;
}

$page       = max(1, (int)($_GET['page'] ?? 1));
$limit      = 20;
$total      = (int)(dbRow("SELECT COUNT(*) as n FROM notifications WHERE user_id=?", [$user['id']])['n'] ?? 0);
$notifsList = dbAll(
    "SELECT * FROM notifications WHERE user_id=? ORDER BY lu ASC, created_at DESC LIMIT $limit OFFSET " . (($page - 1) * $limit),
    [$user['id']]
);
$pagination = paginate($total, $page, $limit);
$nonLues    = (int)(dbRow("SELECT COUNT(*) as n FROM notifications WHERE user_id=? AND lu=0", [$user['id']])['n'] ?? 0);

include __DIR__ . '/includes/header_app.php';
?>

<?= show_flash() ?>

<style>
.notif-page   { max-width:720px; margin:0 auto; }
.notif-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:24px; gap:16px; flex-wrap:wrap; }
.notif-title  { font-family:var(--font-display,sans-serif); font-size:24px; font-weight:800; color:var(--gris-900,#111); }
.notif-sub    { font-size:13px; color:var(--gris-500,#6B7280); margin-top:3px; }
.notif-list   { display:flex; flex-direction:column; gap:10px; }

.notif-card {
  border-radius:14px; overflow:hidden;
  border:1.5px solid var(--gris-200,#E5E7EB);
  background:#fff;
  transition:box-shadow .2s, border-color .2s;
}
[data-theme="dark"] .notif-card { background:#1E293B; border-color:#334155; }
.notif-card.unread { border-color:var(--primary,#007A5E); background:var(--primary-subtle,#E8F5F1); }
[data-theme="dark"] .notif-card.unread { background:rgba(0,122,94,.1); border-color:rgba(0,122,94,.4); }
.notif-card:hover { box-shadow:0 4px 20px rgba(0,0,0,.08); }

.notif-row {
  display:flex; align-items:center; gap:14px;
  padding:16px 18px; cursor:pointer; user-select:none;
}
.notif-icon-wrap {
  width:44px; height:44px; border-radius:12px; flex-shrink:0;
  display:flex; align-items:center; justify-content:center;
}
.notif-icon-wrap svg { width:20px; height:20px; }
.notif-body       { flex:1; min-width:0; }
.notif-card-title { font-size:14px; font-weight:600; color:var(--gris-900,#111); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.notif-card.unread .notif-card-title { font-weight:700; }
.notif-card-time  { font-size:11px; color:var(--gris-400,#9CA3AF); margin-top:3px; }
.notif-chevron    { width:18px; height:18px; flex-shrink:0; color:var(--gris-400,#9CA3AF); transition:transform .25s; stroke:currentColor; fill:none; }
.notif-card.expanded .notif-chevron { transform:rotate(180deg); }
.notif-dot-badge  { width:8px; height:8px; border-radius:50%; background:var(--primary,#007A5E); flex-shrink:0; }

.notif-detail {
  display:none; padding:0 18px 18px;
  border-top:1px solid var(--gris-200,#E5E7EB);
}
[data-theme="dark"] .notif-detail { border-color:#334155; }
.notif-card.expanded .notif-detail { display:block; }
.notif-message { font-size:14px; color:var(--gris-700,#374151); line-height:1.7; margin:14px 0 16px; }
[data-theme="dark"] .notif-message { color:#CBD5E1; }
.notif-actions { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }

.notif-btn-go {
  display:inline-flex; align-items:center; gap:7px;
  background:var(--primary,#007A5E); color:#fff;
  border:none; border-radius:8px; padding:9px 18px;
  font-size:13px; font-weight:700; cursor:pointer; text-decoration:none;
  transition:background .2s, transform .2s;
}
.notif-btn-go:hover { background:#005a45; transform:translateY(-1px); color:#fff; text-decoration:none; }

.notif-btn-read {
  display:inline-flex; align-items:center; gap:6px;
  background:none; border:1.5px solid var(--gris-200,#E5E7EB); border-radius:8px;
  padding:8px 14px; font-size:13px; font-weight:600; color:var(--gris-600,#4B5563);
  cursor:pointer; text-decoration:none; transition:border-color .2s, color .2s;
}
.notif-btn-read:hover { border-color:var(--primary,#007A5E); color:var(--primary,#007A5E); text-decoration:none; }
[data-theme="dark"] .notif-btn-read { border-color:#334155; color:#94A3B8; }
[data-theme="dark"] .notif-btn-read:hover { border-color:var(--primary); color:var(--primary); }

.ic-paiement    { background:#EFF6FF; color:#3B82F6; }
.ic-abonnement  { background:#FFFBEB; color:#D97706; }
.ic-examen      { background:#F0FDF4; color:#16A34A; }
.ic-progression { background:#F3F4F6; color:#6B7280; }
.ic-systeme     { background:#E8F5F1; color:#007A5E; }
.ic-promotion   { background:#FEF3C7; color:#D97706; }
.ic-default     { background:#F3F4F6; color:#6B7280; }
[data-theme="dark"] .ic-paiement    { background:rgba(59,130,246,.15); }
[data-theme="dark"] .ic-abonnement  { background:rgba(217,119,6,.15); }
[data-theme="dark"] .ic-examen      { background:rgba(22,163,74,.15); }
[data-theme="dark"] .ic-systeme     { background:rgba(0,122,94,.15); }
[data-theme="dark"] .ic-promotion   { background:rgba(217,119,6,.15); }

.notif-empty {
  text-align:center; padding:64px 40px;
  background:#fff; border-radius:16px; border:1.5px dashed var(--gris-200,#E5E7EB);
}
[data-theme="dark"] .notif-empty { background:#1E293B; border-color:#334155; }
.notif-empty-icon  { font-size:52px; margin-bottom:16px; }
.notif-empty-title { font-size:17px; font-weight:700; color:var(--gris-900,#111); margin-bottom:8px; }
.notif-empty-sub   { font-size:14px; color:var(--gris-500,#6B7280); line-height:1.6; max-width:320px; margin:0 auto; }
</style>

<?php
// Helper : URL absolue pour le lien de la notification
function notif_abs(string $lien): string {
    if (empty($lien)) return '';
    if (str_starts_with($lien, 'http')) return $lien;
    // Lien comme /dashboard.php sans prefixe APP
    if (str_starts_with($lien, '/') && !str_contains(ltrim($lien, '/'), '/')) {
        return APP_URL . $lien;
    }
    return $lien;
}

$typeMap = [
  'PAIEMENT'    => ['icon' => '<i data-lucide="credit-card"></i>', 'css' => 'ic-paiement'],
  'ABONNEMENT'  => ['icon' => '<i data-lucide="star"></i>', 'css' => 'ic-abonnement'],
  'EXAMEN'      => ['icon' => '<i data-lucide="pencil-line"></i>', 'css' => 'ic-examen'],
  'PROGRESSION' => ['icon' => '<i data-lucide="trending-up"></i>', 'css' => 'ic-progression'],
  'SYSTEME'     => ['icon' => '<i data-lucide="info"></i>',  'css' => 'ic-systeme'],
  'PROMOTION'   => ['icon' => '<i data-lucide="gift"></i>', 'css' => 'ic-promotion'],
];
?>

<div class="notif-page">

  <div class="notif-header">
    <div>
      <div class="notif-title">Notifications</div>
      <div class="notif-sub" id="notif-sub">
        <?= $nonLues > 0 ? $nonLues . ' non lue' . ($nonLues > 1 ? 's' : '') : 'Tout est à jour ✓' ?>
      </div>
    </div>
    <?php if ($nonLues > 0): ?>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="mark_all_read">
      <button type="submit" class="btn btn-ghost btn-sm"><i data-lucide="check-check" style="width:13px;height:13px;vertical-align:-2px"></i> Tout marquer comme lu</button>
    </form>
    <?php endif; ?>
  </div>

  <?php if ($notifsList): ?>
  <div class="notif-list">
  <?php foreach ($notifsList as $n):
    $ti    = $typeMap[$n['type']] ?? ['icon' => '<i data-lucide="bell"></i>', 'css' => 'ic-default'];
    $url   = notif_abs($n['lien'] ?? '');
    $isNew = !$n['lu'];
    $nid   = htmlspecialchars($n['id'], ENT_QUOTES);
  ?>
    <div class="notif-card <?= $isNew ? 'unread' : '' ?>" id="nc-<?= $nid ?>">
      <div class="notif-row"
           onclick="toggleNotif('<?= $nid ?>',<?= $isNew ? 'true' : 'false' ?>)"
           role="button" tabindex="0"
           onkeydown="if(event.key==='Enter'||event.key===' ')toggleNotif('<?= $nid ?>',<?= $isNew ? 'true' : 'false' ?>)">
        <div class="notif-icon-wrap <?= $ti['css'] ?>"><?= $ti['icon'] ?></div>
        <div class="notif-body">
          <div class="notif-card-title"><?= htmlspecialchars($n['titre'], ENT_QUOTES) ?></div>
          <div class="notif-card-time"><?= temps_relatif($n['created_at']) ?></div>
        </div>
        <?php if ($isNew): ?><span class="notif-dot-badge" id="dot-<?= $nid ?>"></span><?php endif; ?>
        <svg class="notif-chevron" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="6 9 12 15 18 9"/>
        </svg>
      </div>
      <div class="notif-detail" id="nd-<?= $nid ?>">
        <div class="notif-message">
          <?= !empty($n['message']) ? nl2br(htmlspecialchars($n['message'], ENT_QUOTES)) : '<em style="color:var(--gris-400)">Aucun détail supplémentaire.</em>' ?>
        </div>
        <div class="notif-actions">
          <?php if (!empty($url)): ?>
          <a href="<?= htmlspecialchars($url, ENT_QUOTES) ?>" class="notif-btn-go">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
            </svg>
            Accéder à la page
          </a>
          <?php endif; ?>
          <?php if ($isNew): ?>
          <a href="#" class="notif-btn-read" id="btn-read-<?= $nid ?>"
             onclick="markRead('<?= $nid ?>');return false;"><i data-lucide="check" style="width:13px;height:13px;vertical-align:-2px"></i> Marquer comme lu</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  </div>

  <?php if ($pagination['pages'] > 1): ?>
  <div style="display:flex;justify-content:center;gap:8px;padding:28px 0">
    <?php for ($i = 1; $i <= $pagination['pages']; $i++): ?>
    <a href="?page=<?= $i ?>" class="btn <?= $i == $page ? 'btn-primary' : 'btn-ghost' ?> btn-sm"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>

  <?php else: ?>
  <div class="notif-empty">
    <div class="notif-empty-icon"><svg xmlns="http://www.w3.org/2000/svg" width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="var(--gris-300)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg></div>
    <div class="notif-empty-title">Aucune notification pour l'instant</div>
    <div class="notif-empty-sub">Tu seras notifié des confirmations de paiement, résultats d'examens et nouveautés de la plateforme.</div>
    <a href="/reussiteplus/dashboard.php" class="btn btn-primary btn-sm" style="margin-top:20px">Retour au tableau de bord</a>
  </div>
  <?php endif; ?>

</div>

<script>
function toggleNotif(id, isNew) {
  const card   = document.getElementById('nc-' + id);
  const isOpen = card.classList.contains('expanded');
  document.querySelectorAll('.notif-card.expanded').forEach(c => c.classList.remove('expanded'));
  if (!isOpen) {
    card.classList.add('expanded');
    if (isNew) markRead(id);
  }
}

function markRead(id) {
  const card = document.getElementById('nc-' + id);
  if (!card || !card.classList.contains('unread')) return;
  fetch('/reussiteplus/notifications.php?action=read&id=' + encodeURIComponent(id), {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  }).then(r => r.json()).then(() => {
    card.classList.remove('unread');
    const dot = document.getElementById('dot-' + id);
    if (dot) dot.remove();
    const btn = document.getElementById('btn-read-' + id);
    if (btn) btn.remove();
    // Badge global
    const badge = document.querySelector('[data-notif-count], .notif-count');
    if (badge) {
      const n = Math.max(0, parseInt(badge.textContent || '0') - 1);
      if (n <= 0) badge.closest('a')?.querySelector('span.badge, span[class*="badge"]')?.remove() || badge.remove();
      else badge.textContent = n;
    }
    // Sous-titre
    const remaining = document.querySelectorAll('.notif-card.unread').length;
    const sub = document.getElementById('notif-sub');
    if (sub) sub.textContent = remaining > 0 ? remaining + ' non lue' + (remaining > 1 ? 's' : '') : 'Tout est à jour ✓';
  }).catch(() => {
    window.location.href = '/reussiteplus/notifications.php?action=read&id=' + encodeURIComponent(id);
  });
}

document.addEventListener('DOMContentLoaded', () => {
  if (typeof lucide !== 'undefined') lucide.createIcons();
});
</script>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
