<?php
ob_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$pageTitle  = 'Gestion des utilisateurs';
$pageActive = 'admin_users';
$user = require_admin();

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $allUsers = dbAll("SELECT id, prenom, nom, email, plan, role, ville, ecole, classe, is_active, created_at FROM utilisateurs ORDER BY created_at DESC") ?? [];

    // Construire le CSV en mémoire (stream temporaire)
    $tmpStream = fopen('php://temp', 'r+');
    fwrite($tmpStream, "\xEF\xBB\xBF"); // BOM UTF-8
    fputcsv($tmpStream, ['ID', 'Prénom', 'Nom', 'Email', 'Plan', 'Rôle', 'Ville', 'École', 'Classe', 'Actif', 'Inscrit le'], ';');
    foreach ($allUsers as $u) {
        fputcsv($tmpStream, [
            $u['id'],
            $u['prenom'],
            $u['nom'],
            $u['email'],
            $u['plan'],
            $u['role'],
            $u['ville'] ?? '',
            $u['ecole'] ?? '',
            $u['classe'] ?? '',
            $u['is_active'] ? 'Oui' : 'Non',
            $u['created_at'],
        ], ';');
    }
    rewind($tmpStream);
    $csvContent = stream_get_contents($tmpStream);
    fclose($tmpStream);

    // Vider tous les buffers puis envoyer
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="utilisateurs_' . date('Y-m-d') . '.csv"');
    header('Content-Length: ' . strlen($csvContent));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    echo $csvContent;
    exit;
}

// Actions (changer plan, activer/désactiver)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action  = $_POST['action'] ?? '';
    $uid     = $_POST['uid'] ?? '';
    if ($action === 'change_plan' && $uid) {
        $newPlan = $_POST['plan'] ?? '';
        if (array_key_exists($newPlan, PLANS)) {
            dbQuery("UPDATE utilisateurs SET plan=? WHERE id=?", [$newPlan, $uid]);
            dbInsert('admin_logs', ['user_id' => $user['id'], 'action' => 'CHANGE_PLAN', 'details' => "uid=$uid plan=$newPlan"]);
            redirect('/reussiteplus/admin/users.php', 'success', 'Plan mis à jour.');
        }
    } elseif ($action === 'toggle_active' && $uid) {
        $current = dbRow("SELECT is_active FROM utilisateurs WHERE id=?", [$uid]);
        if ($current) {
            $newState = $current['is_active'] ? 0 : 1;
            dbQuery("UPDATE utilisateurs SET is_active=? WHERE id=?", [$newState, $uid]);
            dbInsert('admin_logs', ['user_id' => $user['id'], 'action' => $newState ? 'ACTIVER_USER' : 'DESACTIVER_USER', 'details' => "uid=$uid"]);
            redirect('/reussiteplus/admin/users.php', 'success', 'Statut utilisateur mis à jour.');
        }
    } elseif ($action === 'change_role' && $uid) {
        $validRoles = ['ELEVE', 'ENSEIGNANT', 'ADMIN_ECOLE', 'MODERATEUR', 'SUPER_ADMIN'];
        $newRole = $_POST['role'] ?? '';
        if (in_array($newRole, $validRoles, true)) {
            dbQuery("UPDATE utilisateurs SET role=? WHERE id=?", [$newRole, $uid]);
            dbInsert('admin_logs', ['user_id' => $user['id'], 'action' => 'CHANGE_ROLE', 'details' => "uid=$uid role=$newRole"]);
            redirect('/reussiteplus/admin/users.php', 'success', 'Rôle mis à jour.');
        }
    } elseif ($action === 'delete_user' && $uid) {
        // Seul SUPER_ADMIN peut supprimer, ne peut pas se supprimer lui-même
        if ($user['role'] === 'SUPER_ADMIN' && $uid !== $user['id']) {
            $target = dbRow("SELECT email, prenom, nom FROM utilisateurs WHERE id=?", [$uid]);
            if ($target) {
                // Cascade : on supprime d'abord les données liées pour éviter les FK errors
                dbQuery("DELETE FROM notifications   WHERE user_id=?", [$uid]);
                dbQuery("DELETE FROM abonnements     WHERE user_id=?", [$uid]);
                dbQuery("DELETE FROM exam_sessions   WHERE user_id=?", [$uid]);
                dbQuery("DELETE FROM utilisateurs    WHERE id=?",      [$uid]);
                dbInsert('admin_logs', ['user_id' => $user['id'], 'action' => 'DELETE_USER', 'details' => "email={$target['email']}"]);
                redirect('/reussiteplus/admin/users.php', 'success', "Utilisateur {$target['prenom']} {$target['nom']} supprimé.");
            }
        } else {
            redirect('/reussiteplus/admin/users.php', 'error', 'Action non autorisée.');
        }
    }
}

// Filtres
$search    = trim($_GET['q'] ?? '');
$filPlan   = $_GET['plan'] ?? '';
$filRole   = $_GET['role'] ?? '';
$filActif  = $_GET['actif'] ?? '';
$page      = max(1, (int)($_GET['page'] ?? 1));
$limit     = 25;

$conditions = ['1=1'];
$params     = [];
if ($search) {
    $conditions[] = "(u.email LIKE ? OR u.prenom LIKE ? OR u.nom LIKE ?)";
    $s = "%$search%";
    array_push($params, $s, $s, $s);
}
if ($filPlan && array_key_exists($filPlan, PLANS)) {
    $conditions[] = "u.plan=?";
    $params[] = $filPlan;
}
if ($filRole) {
    $conditions[] = "u.role=?";
    $params[] = $filRole;
}
if ($filActif !== '') {
    $conditions[] = "u.is_active=?";
    $params[] = (int)$filActif;
}
$where = implode(' AND ', $conditions);

$total = dbRow("SELECT COUNT(*) as n FROM utilisateurs u WHERE $where", $params)['n'];
$offset = ($page - 1) * $limit;
$users = dbAll(
    "SELECT u.id, u.prenom, u.nom, u.email, u.plan, u.role, u.is_active, u.score_moyen, u.total_examens, u.created_at, u.plan_expire_at
     FROM utilisateurs u WHERE $where ORDER BY u.created_at DESC LIMIT $limit OFFSET $offset",
    $params
);
$pagination = paginate($total, $page, $limit);

include __DIR__ . '/../includes/header_app.php';
?>

<?= show_flash() ?>

<!-- Filtres -->
<form method="GET" style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;align-items:flex-end">
  <div style="flex:1;min-width:200px">
    <label class="form-label">Rechercher</label>
    <input class="form-control" name="q" value="<?= e($search) ?>" placeholder="Nom, email...">
  </div>
  <div>
    <label class="form-label">Plan</label>
    <select class="form-control" name="plan">
      <option value="">Tous les plans</option>
      <?php foreach (PLANS as $key => $p): ?>
      <option value="<?= $key ?>" <?= $filPlan === $key ? 'selected' : '' ?>><?= e($p['nom']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="form-label">Rôle</label>
    <select class="form-control" name="role">
      <option value="">Tous les rôles</option>
      <?php foreach (['ELEVE','ENSEIGNANT','ADMIN_ECOLE','MODERATEUR','SUPER_ADMIN'] as $r): ?>
      <option value="<?= $r ?>" <?= $filRole === $r ? 'selected' : '' ?>><?= $r ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="form-label">Statut</label>
    <select class="form-control" name="actif">
      <option value="">Tous</option>
      <option value="1" <?= $filActif === '1' ? 'selected' : '' ?>>Actifs</option>
      <option value="0" <?= $filActif === '0' ? 'selected' : '' ?>>Désactivés</option>
    </select>
  </div>
  <button type="submit" class="btn btn-primary">Filtrer</button>
  <?php if ($search || $filPlan || $filRole || $filActif !== ''): ?>
  <a href="/reussiteplus/admin/users.php" class="btn btn-ghost">Réinitialiser</a>
  <?php endif; ?>
</form>

<div class="card">
  <div class="card-header">
    <div class="card-title">Utilisateurs (<?= $total ?>)</div>
    <div style="font-size:12px;color:var(--gris-500)">Page <?= $page ?>/<?= $pagination['pages'] ?></div>
  </div>

  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr><th>Utilisateur</th><th>Plan</th><th>Rôle</th><th>Score</th><th>Examens</th><th>Statut</th><th>Inscrit</th><th>Actions</th></tr>
      </thead>
      <tbody>
      <?php foreach ($users as $u): ?>
      <tr style="<?= !$u['is_active'] ? 'opacity:.5' : '' ?>">
        <td>
          <div style="font-weight:600;font-size:13px"><?= e($u['prenom'] . ' ' . $u['nom']) ?></div>
          <div style="font-size:11px;color:var(--gris-500)"><?= e($u['email']) ?></div>
        </td>
        <td><?= badge_plan($u['plan']) ?></td>
        <td><span style="font-size:11px;background:var(--gris-100);padding:2px 8px;border-radius:20px"><?= e($u['role']) ?></span></td>
        <td style="font-weight:700;color:<?= score_couleur((float)($u['score_moyen']??0)) ?>"><?= number_format((float)($u['score_moyen']??0), 1) ?>%</td>
        <td style="font-size:12px;color:var(--gris-600)"><?= (int)$u['total_examens'] ?></td>
        <td>
          <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?= $u['is_active'] ? 'var(--primary)' : 'var(--rouge)' ?>;margin-right:4px"></span>
          <?= $u['is_active'] ? 'Actif' : 'Inactif' ?>
        </td>
        <td style="font-size:11px;color:var(--gris-500)"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
        <td>
          <button class="btn btn-ghost btn-sm" onclick="openModal('<?= e($u['id']) ?>','<?= e($u['plan']) ?>','<?= e($u['role']) ?>','<?= (int)$u['is_active'] ?>')">Gérer</button>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if (($pagination['pages'] ?? 1) > 1): ?>
  <div style="display:flex;justify-content:center;gap:6px;padding:16px;flex-wrap:wrap">
    <?php for ($i = 1; $i <= $pagination['pages']; $i++): ?>
    <a href="?q=<?= urlencode($search) ?>&plan=<?= urlencode($filPlan) ?>&role=<?= urlencode($filRole) ?>&actif=<?= urlencode($filActif) ?>&page=<?= $i ?>" class="btn <?= $i == $page ? 'btn-primary' : 'btn-ghost' ?> btn-sm"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Modal gestion utilisateur -->
<div id="user-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center">
  <div style="background:white;border-radius:var(--radius-xl);padding:28px;width:100%;max-width:420px;margin:16px">
    <div style="font-family:var(--font-display);font-size:18px;font-weight:700;margin-bottom:20px">Gérer l'utilisateur</div>

    <form method="POST" id="modal-form">
      <?= csrf_field() ?>
      <input type="hidden" name="uid" id="modal-uid">

      <div class="form-group">
        <label class="form-label">Plan</label>
        <select class="form-control" name="plan" id="modal-plan">
          <?php foreach (PLANS as $k => $p): ?>
          <option value="<?= $k ?>"><?= e($p['icone'] . ' ' . $p['nom']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Rôle</label>
        <select class="form-control" name="role" id="modal-role">
          <?php foreach (['ELEVE','ENSEIGNANT','ADMIN_ECOLE','MODERATEUR','SUPER_ADMIN'] as $r): ?>
          <option value="<?= $r ?>"><?= $r ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="display:flex;gap:10px;margin-top:20px">
        <button type="submit" name="action" value="change_plan" class="btn btn-primary btn-full" onclick="document.getElementById('modal-form').querySelector('[name=action]').value='change_plan'">Changer plan</button>
        <button type="submit" name="action" value="change_role" class="btn btn-ghost btn-full" onclick="document.getElementById('modal-form').querySelector('[name=action]').value='change_role'">Changer rôle</button>
      </div>
      <button type="submit" name="action" value="toggle_active" class="btn btn-danger btn-full" style="margin-top:8px" id="toggle-btn">Désactiver le compte</button>
      <?php if ($user['role'] === 'SUPER_ADMIN'): ?>
      <button type="submit" name="action" value="delete_user" class="btn btn-full" style="margin-top:6px;background:#7f1d1d;color:white;border:none" id="delete-btn" onclick="return confirm('⚠️ Supprimer définitivement cet utilisateur et toutes ses données ? Cette action est irréversible.')">
        <i data-lucide="trash-2" style="width:13px;height:13px"></i> Supprimer définitivement
      </button>
      <?php endif; ?>
    </form>
    <button onclick="closeModal()" class="btn btn-ghost btn-full" style="margin-top:8px">Annuler</button>
  </div>
</div>

<script>
let hiddenAction = document.createElement('input');
hiddenAction.type = 'hidden';
hiddenAction.name = 'action';
document.getElementById('modal-form').appendChild(hiddenAction);

function openModal(uid, plan, role, active) {
  document.getElementById('modal-uid').value = uid;
  document.getElementById('modal-plan').value = plan;
  document.getElementById('modal-role').value = role;
  document.getElementById('toggle-btn').textContent = active == 1 ? 'Désactiver le compte' : 'Activer le compte';
  document.getElementById('user-modal').style.display = 'flex';
}
function closeModal() {
  document.getElementById('user-modal').style.display = 'none';
}
document.getElementById('modal-form').addEventListener('submit', function(e) {
  const btn = document.activeElement;
  if (btn && btn.name === 'action') hiddenAction.value = btn.value;
});
</script>

<?php include __DIR__ . '/../includes/footer_app.php'; ?>
