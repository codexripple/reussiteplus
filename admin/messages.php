<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$pageTitle  = 'Messages de contact';
$pageActive = 'admin_messages';
$user = require_admin();

// ── Export CSV messages ───────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $rows = dbAll("SELECT nom, email, sujet, message, statut, created_at FROM contact_messages ORDER BY created_at DESC") ?? [];
    $tmp = fopen('php://temp', 'r+');
    fwrite($tmp, "\xEF\xBB\xBF");
    fputcsv($tmp, ['Nom', 'Email', 'Sujet', 'Message', 'Statut', 'Date'], ';');
    foreach ($rows as $r) fputcsv($tmp, array_values($r), ';');
    rewind($tmp); $csv = stream_get_contents($tmp); fclose($tmp);
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="messages_contact_' . date('Y-m-d') . '.csv"');
    header('Content-Length: ' . strlen($csv));
    echo $csv; exit;
}

// ── Actions POST ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';
    $id     = $_POST['id']     ?? '';

    if ($action === 'mark_read' && $id) {
        dbRun("UPDATE contact_messages SET statut='LU' WHERE id=?", [$id]);
        redirect('/reussiteplus/admin/messages.php', 'success', 'Message marqu&eacute; comme lu.');
    }
    if ($action === 'mark_unread' && $id) {
        dbRun("UPDATE contact_messages SET statut='NOUVEAU' WHERE id=?", [$id]);
        redirect('/reussiteplus/admin/messages.php', 'success', 'Message marqu&eacute; comme non lu.');
    }
    if ($action === 'delete' && $id) {
        dbRun("DELETE FROM contact_messages WHERE id=?", [$id]);
        redirect('/reussiteplus/admin/messages.php', 'success', 'Message supprim&eacute;.');
    }
    if ($action === 'mark_all_read') {
        dbRun("UPDATE contact_messages SET statut='LU' WHERE statut='NOUVEAU'");
        redirect('/reussiteplus/admin/messages.php', 'success', 'Tous les messages marqu&eacute;s comme lus.');
    }
    exit;
}

// ── Filtres ───────────────────────────────────────────────
$filtreStatut = $_GET['statut'] ?? '';
$filtreSujet  = $_GET['sujet']  ?? '';
$q            = trim($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['p'] ?? 1));
$perPage      = 20;

$where = '1=1'; $params = [];
if ($filtreStatut) { $where .= ' AND statut=?'; $params[] = $filtreStatut; }
if ($filtreSujet)  { $where .= ' AND sujet=?';  $params[] = $filtreSujet; }
if ($q)            { $where .= ' AND (nom LIKE ? OR email LIKE ? OR message LIKE ?)'; $params = array_merge($params, ["%$q%","%$q%","%$q%"]); }

$total   = (int)(dbRow("SELECT COUNT(*) as n FROM contact_messages WHERE $where", $params)['n'] ?? 0);
$offset  = ($page - 1) * $perPage;
$pagi    = paginate($total, $page, $perPage);

$messages = dbAll(
    "SELECT * FROM contact_messages WHERE $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset",
    $params
) ?? [];

// Stats
$totalNouv  = (int)(dbRow("SELECT COUNT(*) as n FROM contact_messages WHERE statut='NOUVEAU'")['n'] ?? 0);
$totalLus   = (int)(dbRow("SELECT COUNT(*) as n FROM contact_messages WHERE statut='LU'")['n'] ?? 0);
$totalAll   = $totalNouv + $totalLus;

$sujetsMap = [
    'PLAN'        => ['label'=>'Abonnement / Tarif',      'color'=>'#1E5FAD','bg'=>'#DBEAFE','icon'=>'credit-card'],
    'TECHNIQUE'   => ['label'=>'Probl&egrave;me technique','color'=>'#DC2626','bg'=>'#FEE2E2','icon'=>'tool'],
    'PARTENARIAT' => ['label'=>'Partenariat',              'color'=>'#059669','bg'=>'#D1FAE5','icon'=>'handshake'],
    'PRESSE'      => ['label'=>'Presse',                   'color'=>'#7C3AED','bg'=>'#EDE9FE','icon'=>'newspaper'],
    'AUTRE'       => ['label'=>'Autre',                    'color'=>'#6B7280','bg'=>'#F3F4F6','icon'=>'message-circle'],
];

include __DIR__ . '/../includes/header_app.php';
?>
<?= show_flash() ?>

<style>
.msg-row { background:#fff; border:1.5px solid var(--gris-200); border-radius:14px; padding:18px 20px; margin-bottom:10px; transition:.15s; }
.msg-row:hover { border-color:#94a3b8; box-shadow:0 3px 14px rgba(0,0,0,.07); }
.msg-row.nouveau { border-left:4px solid #DC2626; }
.msg-avatar { width:40px; height:40px; border-radius:50%; background:linear-gradient(135deg,var(--primary),var(--gold)); display:flex; align-items:center; justify-content:center; font-size:14px; font-weight:800; color:#fff; flex-shrink:0; }
.msg-body-preview { font-size:13px; color:var(--gris-700); line-height:1.55; margin:8px 0; }
.msg-body-full { display:none; font-size:13px; color:var(--gris-700); line-height:1.6; margin:10px 0; padding:12px 14px; background:var(--gris-50); border-radius:10px; border-left:3px solid var(--gris-300); white-space:pre-wrap; }
.msg-row.expanded .msg-body-preview { display:none; }
.msg-row.expanded .msg-body-full { display:block; }
.btn-act { display:inline-flex; align-items:center; gap:5px; padding:5px 11px; border-radius:8px; font-size:11px; font-weight:700; border:none; cursor:pointer; font-family:'Manrope',sans-serif; transition:.15s; text-decoration:none; }
</style>

<!-- Hero -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;margin-bottom:24px">
  <div>
    <h1 style="font-family:var(--font-display);font-size:22px;font-weight:900;color:var(--gris-900);margin:0">
      Messages de contact
    </h1>
    <p style="color:var(--gris-500);font-size:13px;margin:4px 0 0">Formulaires remplis par vos visiteurs et utilisateurs</p>
  </div>
  <div style="display:flex;gap:8px;align-items:center">
    <?php if ($totalNouv > 0): ?>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="mark_all_read">
      <button type="submit" class="btn btn-ghost btn-sm">
        <i data-lucide="check-check" style="width:13px;height:13px"></i> Tout marquer lu
      </button>
    </form>
    <?php endif; ?>
    <a href="?export=csv" class="btn btn-ghost btn-sm">
      <i data-lucide="download" style="width:13px;height:13px"></i> Exporter CSV
    </a>
    <a href="/reussiteplus/admin/index.php" class="btn btn-ghost btn-sm">
      <i data-lucide="arrow-left" style="width:13px;height:13px"></i> Tableau de bord
    </a>
  </div>
</div>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:24px">
  <div style="background:#fff;border:1.5px solid var(--gris-200);border-radius:14px;padding:16px 18px;display:flex;align-items:center;gap:14px">
    <div style="width:42px;height:42px;background:#FEE2E2;border-radius:10px;display:flex;align-items:center;justify-content:center">
      <i data-lucide="mail" style="width:20px;height:20px;stroke:#DC2626"></i>
    </div>
    <div>
      <div style="font-family:var(--font-display);font-size:24px;font-weight:900;color:<?= $totalNouv>0?'#DC2626':'var(--gris-900)' ?>"><?= $totalNouv ?></div>
      <div style="font-size:11px;color:var(--gris-500)">Non lus</div>
    </div>
  </div>
  <div style="background:#fff;border:1.5px solid var(--gris-200);border-radius:14px;padding:16px 18px;display:flex;align-items:center;gap:14px">
    <div style="width:42px;height:42px;background:#D1FAE5;border-radius:10px;display:flex;align-items:center;justify-content:center">
      <i data-lucide="mail-open" style="width:20px;height:20px;stroke:#059669"></i>
    </div>
    <div>
      <div style="font-family:var(--font-display);font-size:24px;font-weight:900"><?= $totalLus ?></div>
      <div style="font-size:11px;color:var(--gris-500)">Lus</div>
    </div>
  </div>
  <div style="background:#fff;border:1.5px solid var(--gris-200);border-radius:14px;padding:16px 18px;display:flex;align-items:center;gap:14px">
    <div style="width:42px;height:42px;background:#EDE9FE;border-radius:10px;display:flex;align-items:center;justify-content:center">
      <i data-lucide="inbox" style="width:20px;height:20px;stroke:#7C3AED"></i>
    </div>
    <div>
      <div style="font-family:var(--font-display);font-size:24px;font-weight:900"><?= $totalAll ?></div>
      <div style="font-size:11px;color:var(--gris-500)">Total messages</div>
    </div>
  </div>
</div>

<!-- Filtres -->
<div style="background:#fff;border:1.5px solid var(--gris-200);border-radius:14px;padding:14px 18px;margin-bottom:20px">
  <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <div style="position:relative;flex:1;min-width:200px">
      <i data-lucide="search" style="width:13px;height:13px;position:absolute;left:12px;top:50%;transform:translateY(-50%);stroke:var(--gris-400)"></i>
      <input type="text" name="q" value="<?= e($q) ?>" placeholder="Nom, e-mail, message&hellip;" class="form-control" style="padding-left:34px;margin-bottom:0">
    </div>
    <select name="statut" class="form-control" style="width:140px;margin-bottom:0">
      <option value="">Tous statuts</option>
      <option value="NOUVEAU" <?= $filtreStatut==='NOUVEAU'?'selected':'' ?>>Non lus</option>
      <option value="LU"      <?= $filtreStatut==='LU'     ?'selected':'' ?>>Lus</option>
    </select>
    <select name="sujet" class="form-control" style="width:180px;margin-bottom:0">
      <option value="">Tous sujets</option>
      <?php foreach ($sujetsMap as $k => $s): ?>
      <option value="<?= $k ?>" <?= $filtreSujet===$k?'selected':'' ?>><?= $s['label'] ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">
      <i data-lucide="filter" style="width:12px;height:12px;stroke:#fff"></i> Filtrer
    </button>
    <?php if ($q || $filtreStatut || $filtreSujet): ?>
    <a href="/reussiteplus/admin/messages.php" class="btn btn-ghost btn-sm">Effacer</a>
    <?php endif; ?>
  </form>
</div>

<!-- Résultats -->
<div style="font-size:12px;color:var(--gris-400);margin-bottom:12px">
  <?= $total ?> message<?= $total>1?'s':'' ?> trouvé<?= $total>1?'s':'' ?>
  <?= ($q||$filtreStatut||$filtreSujet) ? ' (filtr&eacute;s)' : '' ?>
</div>

<?php if (!$messages): ?>
<div style="text-align:center;padding:60px 20px;background:var(--gris-50);border:2px dashed var(--gris-200);border-radius:14px">
  <div style="display:flex;justify-content:center;margin-bottom:14px">
    <i data-lucide="inbox" style="width:48px;height:48px;stroke:var(--gris-300);stroke-width:1.5"></i>
  </div>
  <div style="font-size:15px;font-weight:700;color:var(--gris-600)">Aucun message</div>
  <div style="font-size:12px;color:var(--gris-400);margin-top:4px">
    <?= ($q||$filtreStatut||$filtreSujet) ? 'Aucun message ne correspond &agrave; ces filtres.' : 'Personne n\'a encore envoy&eacute; de message.' ?>
  </div>
</div>
<?php else: ?>

<?php foreach ($messages as $m):
  $sc   = $sujetsMap[$m['sujet']] ?? $sujetsMap['AUTRE'];
  $init = strtoupper(substr($m['nom'],0,1));
  $isNew = ($m['statut'] === 'NOUVEAU');
  $shortMsg = mb_strimwidth($m['message'], 0, 160, '…');
  $needsExpand = mb_strlen($m['message']) > 160;
?>
<div class="msg-row <?= $isNew ? 'nouveau' : '' ?>" id="msg-<?= $m['id'] ?>">
  <div style="display:flex;align-items:flex-start;gap:14px">
    <!-- Avatar -->
    <div class="msg-avatar" style="background:linear-gradient(135deg,<?= $sc['color'] ?>,<?= $isNew?'#DC2626':'#64748b' ?>)"><?= $init ?></div>

    <!-- Contenu -->
    <div style="flex:1;min-width:0">
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:4px">
        <span style="font-size:14px;font-weight:800;color:var(--gris-900)"><?= e($m['nom']) ?></span>
        <?php if ($isNew): ?>
        <span style="background:#FEE2E2;color:#DC2626;font-size:9px;font-weight:900;padding:2px 7px;border-radius:6px;text-transform:uppercase">Nouveau</span>
        <?php endif; ?>
        <span style="background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;font-size:10px;font-weight:700;padding:2px 9px;border-radius:8px;display:inline-flex;align-items:center;gap:4px">
          <i data-lucide="<?= $sc['icon'] ?>" style="width:10px;height:10px"></i> <?= $sc['label'] ?>
        </span>
        <span style="font-size:11px;color:var(--gris-400);margin-left:auto"><?= temps_relatif($m['created_at']) ?> &bull; <?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></span>
      </div>

      <div style="font-size:12px;color:var(--gris-500);margin-bottom:8px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <a href="mailto:<?= e($m['email']) ?>" style="color:var(--primary);text-decoration:none;display:inline-flex;align-items:center;gap:4px">
          <i data-lucide="mail" style="width:11px;height:11px"></i> <?= e($m['email']) ?>
        </a>
        <?php if ($m['telephone']): ?>
        <span style="display:inline-flex;align-items:center;gap:4px">
          <i data-lucide="phone" style="width:11px;height:11px;stroke:var(--gris-400)"></i> <?= e($m['telephone']) ?>
        </span>
        <?php endif; ?>
        <span style="font-size:10px;color:var(--gris-300)">IP: <?= e($m['ip'] ?? '—') ?></span>
      </div>

      <!-- Message -->
      <div class="msg-body-preview"><?= e($shortMsg) ?>
        <?php if ($needsExpand): ?>
        <button onclick="toggleMsg(<?= $m['id'] ?>)" style="background:none;border:none;color:var(--primary);font-size:11px;font-weight:700;cursor:pointer;padding:0;margin-left:4px">Voir tout</button>
        <?php endif; ?>
      </div>
      <div class="msg-body-full"><?= e($m['message']) ?>
        <button onclick="toggleMsg(<?= $m['id'] ?>)" style="display:block;margin-top:8px;background:none;border:none;color:var(--gris-500);font-size:11px;cursor:pointer;padding:0">R&eacute;duire</button>
      </div>

      <!-- Actions -->
      <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
        <a href="mailto:<?= e($m['email']) ?>?subject=Re:+<?= urlencode(html_entity_decode($sc['label'])) ?>"
           class="btn-act" style="background:#EFF6FF;color:#1D4ED8">
          <i data-lucide="reply" style="width:11px;height:11px"></i> R&eacute;pondre
        </a>
        <?php if ($isNew): ?>
        <form method="POST" style="display:inline">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="mark_read">
          <input type="hidden" name="id" value="<?= e($m['id']) ?>">
          <button type="submit" class="btn-act" style="background:#F0FDF4;color:#15803D">
            <i data-lucide="check" style="width:11px;height:11px"></i> Marquer lu
          </button>
        </form>
        <?php else: ?>
        <form method="POST" style="display:inline">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="mark_unread">
          <input type="hidden" name="id" value="<?= e($m['id']) ?>">
          <button type="submit" class="btn-act" style="background:var(--gris-100);color:var(--gris-600)">
            <i data-lucide="mail" style="width:11px;height:11px"></i> Marquer non lu
          </button>
        </form>
        <?php endif; ?>
        <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ce message d\u0027un visiteur ?')">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= e($m['id']) ?>">
          <button type="submit" class="btn-act" style="background:#FEF2F2;color:#B91C1C">
            <i data-lucide="trash-2" style="width:11px;height:11px"></i> Supprimer
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>

<!-- Pagination -->
<?php if ($pagi['total_pages'] > 1): ?>
<div style="display:flex;justify-content:center;gap:6px;margin-top:24px;flex-wrap:wrap">
  <?php for ($i = 1; $i <= $pagi['total_pages']; $i++):
    $isActive = $i === $page;
    $qs = http_build_query(['q'=>$q,'statut'=>$filtreStatut,'sujet'=>$filtreSujet,'p'=>$i]);
  ?>
  <a href="?<?= $qs ?>" style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:8px;font-size:13px;font-weight:700;text-decoration:none;<?= $isActive ? 'background:var(--primary);color:#fff' : 'background:var(--gris-100);color:var(--gris-700)' ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php endif; ?>

<script>
function toggleMsg(id) {
  const row = document.getElementById('msg-' + id);
  row.classList.toggle('expanded');
}
</script>

<?php include __DIR__ . '/../includes/footer_app.php'; ?>
