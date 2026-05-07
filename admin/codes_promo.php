<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$pageTitle  = 'Codes promo';
$pageActive = 'admin_promos';
$user = require_admin();

// ── Actions POST ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';

    // Créer un code promo
    if ($action === 'create') {
        $code        = strtoupper(trim($_POST['code'] ?? ''));
        $description = trim($_POST['description'] ?? '');
        $type_remise = $_POST['type_remise'] ?? 'POURCENTAGE';
        $valeur      = (float)($_POST['valeur_remise'] ?? 0);
        $plan        = $_POST['plan_applicable'] ?? 'TOUS';
        $nb_max      = $_POST['nb_max'] !== '' ? (int)$_POST['nb_max'] : null;
        $expire      = $_POST['date_expiration'] !== '' ? $_POST['date_expiration'] : null;

        $validTypes  = ['POURCENTAGE', 'MONTANT_FIXE'];
        $validPlans  = ['TOUS', 'BASIQUE', 'PREMIUM', 'ECOLE'];
        $errors = [];
        if (!preg_match('/^[A-Z0-9\-]{3,30}$/', $code)) $errors[] = 'Code invalide (3-30 caractères alphanumériques).';
        if (!in_array($type_remise, $validTypes, true)) $errors[] = 'Type de remise invalide.';
        if ($valeur <= 0) $errors[] = 'La valeur de remise doit être positive.';
        if ($type_remise === 'POURCENTAGE' && $valeur > 100) $errors[] = 'Le pourcentage ne peut dépasser 100%.';
        if (!in_array($plan, $validPlans, true)) $errors[] = 'Plan invalide.';
        if (dbRow("SELECT id FROM codes_promo WHERE code=?", [$code])) $errors[] = 'Ce code existe déjà.';

        if (!$errors) {
            dbInsert('codes_promo', [
                'code'            => $code,
                'description'     => $description ?: null,
                'type_remise'     => $type_remise,
                'valeur_remise'   => $valeur,
                'plan_applicable' => $plan,
                'nb_max'          => $nb_max,
                'date_expiration' => $expire,
                'actif'           => 1,
            ]);
            dbInsert('admin_logs', ['user_id' => $user['id'], 'action' => 'CREATE_PROMO', 'details' => "code=$code"]);
            redirect('/reussiteplus/admin/codes_promo.php', 'success', "Code promo <strong>$code</strong> créé.");
        }
        // On passe les erreurs à la vue
        $createErrors = $errors;
        $formData     = $_POST;
    }

    // Activer / désactiver
    if ($action === 'toggle' && !empty($_POST['id'])) {
        $current = dbRow("SELECT actif FROM codes_promo WHERE id=?", [$_POST['id']]);
        if ($current) {
            dbQuery("UPDATE codes_promo SET actif=? WHERE id=?", [1 - (int)$current['actif'], $_POST['id']]);
            redirect('/reussiteplus/admin/codes_promo.php', 'success', 'Statut mis à jour.');
        }
    }

    // Supprimer
    if ($action === 'delete' && !empty($_POST['id'])) {
        dbQuery("DELETE FROM codes_promo WHERE id=?", [$_POST['id']]);
        dbInsert('admin_logs', ['user_id' => $user['id'], 'action' => 'DELETE_PROMO', 'details' => 'id=' . $_POST['id']]);
        redirect('/reussiteplus/admin/codes_promo.php', 'success', 'Code promo supprimé.');
    }
}

// ── Lecture ───────────────────────────────────────────────
$q      = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['p'] ?? 1));
$perPage = 20;
$where  = $q ? "WHERE code LIKE ? OR description LIKE ?" : '';
$params = $q ? ["%$q%", "%$q%"] : [];

$total   = (int)(dbRow("SELECT COUNT(*) as n FROM codes_promo $where", $params)['n'] ?? 0);
$pagi    = paginate($total, $page, $perPage);
$promos  = dbAll(
    "SELECT * FROM codes_promo $where ORDER BY created_at DESC LIMIT $perPage OFFSET {$pagi['offset']}",
    $params
) ?? [];

$stats = dbRow("SELECT
    COUNT(*) as total,
    SUM(actif=1) as actifs,
    SUM(actif=0) as inactifs,
    SUM(nb_utilisations) as utilisations_tot
FROM codes_promo") ?? [];

include __DIR__ . '/../includes/header_app.php';
?>
<?= show_flash() ?>

<style>
.promo-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:22px; }
.promo-stat { background:#fff; border:1.5px solid var(--gris-200); border-radius:14px; padding:18px 20px; }
.promo-stat .val { font-family:var(--font-display); font-size:26px; font-weight:900; color:var(--gris-900); }
.promo-stat .lbl { font-size:11px; font-weight:700; color:var(--gris-500); text-transform:uppercase; letter-spacing:.4px; margin-top:4px; }

.promo-card { background:#fff; border:1.5px solid var(--gris-200); border-radius:14px; padding:18px 20px; margin-bottom:10px; display:flex; align-items:center; gap:16px; flex-wrap:wrap; transition:.15s; }
.promo-card:hover { border-color:#94a3b8; box-shadow:0 3px 14px rgba(0,0,0,.07); }
.promo-card.inactive { opacity:.6; }
.promo-code { font-family:monospace; font-size:16px; font-weight:800; color:var(--gris-900); background:var(--gris-100); padding:4px 12px; border-radius:8px; letter-spacing:1px; min-width:130px; text-align:center; }
.promo-badge { padding:3px 10px; border-radius:20px; font-size:10px; font-weight:800; text-transform:uppercase; }
.promo-meta { font-size:12px; color:var(--gris-500); }

.create-form { background:#fff; border:1.5px solid var(--gris-200); border-radius:16px; padding:24px 28px; margin-bottom:22px; }
.create-form .form-grid { display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px; }
@media(max-width:800px) { .create-form .form-grid { grid-template-columns:1fr 1fr; } .promo-grid { grid-template-columns:repeat(2,1fr); } }
@media(max-width:560px) { .create-form .form-grid { grid-template-columns:1fr; } .promo-grid { grid-template-columns:1fr 1fr; } }
</style>

<!-- Header -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:22px">
  <div>
    <div style="font-family:var(--font-display);font-size:22px;font-weight:900;color:var(--gris-900)">Codes Promo</div>
    <div style="font-size:12px;color:var(--gris-500);margin-top:2px">Gérez les codes de réduction pour vos abonnements</div>
  </div>
</div>

<!-- Stats -->
<div class="promo-grid">
  <div class="promo-stat"><div class="val"><?= (int)($stats['total'] ?? 0) ?></div><div class="lbl">Codes créés</div></div>
  <div class="promo-stat"><div class="val" style="color:#007A5E"><?= (int)($stats['actifs'] ?? 0) ?></div><div class="lbl">Codes actifs</div></div>
  <div class="promo-stat"><div class="val" style="color:#6B7280"><?= (int)($stats['inactifs'] ?? 0) ?></div><div class="lbl">Désactivés</div></div>
  <div class="promo-stat"><div class="val" style="color:#C9972A"><?= (int)($stats['utilisations_tot'] ?? 0) ?></div><div class="lbl">Utilisations totales</div></div>
</div>

<!-- Formulaire création -->
<?php $showForm = isset($_GET['new']) || !empty($createErrors ?? []); ?>
<?php if (!$showForm): ?>
<div style="margin-bottom:16px">
  <a href="?new=1" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:7px">
    <i data-lucide="plus-circle" style="width:15px;height:15px"></i> Créer un code promo
  </a>
</div>
<?php endif; ?>

<?php if ($showForm): ?>
<div class="create-form">
  <div style="font-family:var(--font-display);font-size:16px;font-weight:800;margin-bottom:18px;display:flex;align-items:center;gap:8px">
    <i data-lucide="tag" style="width:18px;height:18px;stroke:var(--primary)"></i>
    Nouveau code promo
  </div>
  <?php if (!empty($createErrors ?? [])): ?>
  <div class="alert alert-danger" style="margin-bottom:16px">
    <?php foreach ($createErrors as $e): ?><div><?= e($e) ?></div><?php endforeach; ?>
  </div>
  <?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div class="form-grid">
      <div>
        <label class="form-label">Code <span style="color:red">*</span></label>
        <input type="text" name="code" class="form-control" value="<?= e($formData['code'] ?? '') ?>"
               placeholder="EX: RENTRÉE25" style="text-transform:uppercase;font-family:monospace;font-weight:700" required>
        <div style="font-size:11px;color:var(--gris-400);margin-top:4px">Majuscules, chiffres, tirets. Ex: NOEL2026</div>
      </div>
      <div>
        <label class="form-label">Type de remise <span style="color:red">*</span></label>
        <select name="type_remise" id="typeRemise" class="form-control" onchange="updateValLabel()">
          <option value="POURCENTAGE" <?= ($formData['type_remise'] ?? '') === 'POURCENTAGE' ? 'selected' : '' ?>>Pourcentage (%)</option>
          <option value="MONTANT_FIXE" <?= ($formData['type_remise'] ?? '') === 'MONTANT_FIXE' ? 'selected' : '' ?>>Montant fixe (CDF)</option>
        </select>
      </div>
      <div>
        <label class="form-label" id="valLabel">Valeur de remise <span style="color:red">*</span></label>
        <input type="number" name="valeur_remise" class="form-control" min="1" step="0.01"
               value="<?= e($formData['valeur_remise'] ?? '') ?>" placeholder="Ex: 20" required>
      </div>
      <div>
        <label class="form-label">Plan applicable</label>
        <select name="plan_applicable" class="form-control">
          <option value="TOUS" <?= ($formData['plan_applicable'] ?? 'TOUS') === 'TOUS' ? 'selected' : '' ?>>Tous les plans</option>
          <option value="BASIQUE" <?= ($formData['plan_applicable'] ?? '') === 'BASIQUE' ? 'selected' : '' ?>>Basique</option>
          <option value="PREMIUM" <?= ($formData['plan_applicable'] ?? '') === 'PREMIUM' ? 'selected' : '' ?>>Premium</option>
          <option value="ECOLE" <?= ($formData['plan_applicable'] ?? '') === 'ECOLE' ? 'selected' : '' ?>>École</option>
        </select>
      </div>
      <div>
        <label class="form-label">Nb max d'utilisations</label>
        <input type="number" name="nb_max" class="form-control" min="1"
               value="<?= e($formData['nb_max'] ?? '') ?>" placeholder="Illimité">
      </div>
      <div>
        <label class="form-label">Date d'expiration</label>
        <input type="date" name="date_expiration" class="form-control"
               value="<?= e($formData['date_expiration'] ?? '') ?>" min="<?= date('Y-m-d') ?>">
      </div>
    </div>
    <div style="margin-top:14px">
      <label class="form-label">Description (optionnel)</label>
      <input type="text" name="description" class="form-control" maxlength="200"
             value="<?= e($formData['description'] ?? '') ?>" placeholder="Ex: Remise de rentrée scolaire 2026">
    </div>
    <div style="margin-top:18px;display:flex;gap:10px">
      <button type="submit" class="btn btn-primary">
        <i data-lucide="check-circle" style="width:14px;height:14px"></i> Créer le code
      </button>
      <a href="/reussiteplus/admin/codes_promo.php" class="btn btn-secondary">Annuler</a>
    </div>
  </form>
</div>
<script>
function updateValLabel(){
  const t = document.getElementById('typeRemise').value;
  document.getElementById('valLabel').textContent = t === 'POURCENTAGE' ? 'Remise (%)' : 'Remise (CDF)';
}
updateValLabel();
</script>
<?php endif; ?>

<!-- Recherche -->
<form method="get" style="margin-bottom:16px;display:flex;gap:10px;align-items:center">
  <input type="text" name="q" class="form-control" placeholder="Rechercher un code..." value="<?= e($q) ?>" style="max-width:280px">
  <button class="btn btn-secondary btn-sm" type="submit">Rechercher</button>
  <?php if ($q): ?><a href="/reussiteplus/admin/codes_promo.php" class="btn btn-secondary btn-sm">✕ Effacer</a><?php endif; ?>
</form>

<!-- Liste -->
<?php if (empty($promos)): ?>
<div style="text-align:center;padding:48px 0;color:var(--gris-400)">
  <i data-lucide="tag" style="width:40px;height:40px;stroke:var(--gris-300);display:block;margin:0 auto 12px"></i>
  <div style="font-size:14px">Aucun code promo trouvé</div>
  <?php if (!$showForm): ?><a href="?new=1" class="btn btn-primary" style="margin-top:14px">Créer le premier</a><?php endif; ?>
</div>
<?php else: ?>
<div style="margin-bottom:10px;font-size:12px;color:var(--gris-500)"><?= $total ?> code<?= $total > 1 ? 's' : '' ?> trouvé<?= $total > 1 ? 's' : '' ?></div>
<?php foreach ($promos as $p):
    $isExpired = $p['date_expiration'] && strtotime($p['date_expiration']) < time();
    $isMaxed   = $p['nb_max'] && $p['nb_utilisations'] >= $p['nb_max'];
    $cardCls   = (!$p['actif'] || $isExpired || $isMaxed) ? 'promo-card inactive' : 'promo-card';
    $remiseLabel = $p['type_remise'] === 'POURCENTAGE'
        ? $p['valeur_remise'] . '%'
        : number_format((float)$p['valeur_remise'], 0, ',', ' ') . ' CDF';
    $planColors = ['TOUS'=>['#007A5E','#E8F5F1'],'PREMIUM'=>['#C9972A','#FEF3D7'],'BASIQUE'=>['#1E5FAD','#DBEAFE'],'ECOLE'=>['#7C3AED','#EDE9FE']];
    $pc = $planColors[$p['plan_applicable']] ?? ['#6B7280','#F3F4F6'];
?>
<div class="<?= $cardCls ?>">
  <div class="promo-code"><?= e($p['code']) ?></div>

  <div style="flex:1;min-width:0">
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px">
      <span style="font-size:16px;font-weight:800;color:var(--primary)"><?= $remiseLabel ?> de remise</span>
      <span class="promo-badge" style="background:<?= $pc[1] ?>;color:<?= $pc[0] ?>"><?= $p['plan_applicable'] ?></span>
      <?php if (!$p['actif']): ?>
        <span class="promo-badge" style="background:#F3F4F6;color:#6B7280">Désactivé</span>
      <?php elseif ($isExpired): ?>
        <span class="promo-badge" style="background:#FEE2E2;color:#DC2626">Expiré</span>
      <?php elseif ($isMaxed): ?>
        <span class="promo-badge" style="background:#FEF3C7;color:#B45309">Quota atteint</span>
      <?php else: ?>
        <span class="promo-badge" style="background:#D1FAE5;color:#065F46">Actif</span>
      <?php endif; ?>
    </div>
    <div class="promo-meta">
      <?php if ($p['description']): ?><span><?= e($p['description']) ?></span> &middot; <?php endif; ?>
      <span><strong><?= $p['nb_utilisations'] ?></strong> utilisation<?= $p['nb_utilisations'] != 1 ? 's' : '' ?><?= $p['nb_max'] ? ' / ' . $p['nb_max'] : '' ?></span>
      <?php if ($p['date_expiration']): ?>
        &middot; <span style="color:<?= $isExpired ? '#DC2626' : 'inherit' ?>">Expire le <?= date('d/m/Y', strtotime($p['date_expiration'])) ?></span>
      <?php endif; ?>
      &middot; Créé <?= temps_relatif($p['created_at']) ?>
    </div>
  </div>

  <div style="display:flex;gap:6px;flex-shrink:0">
    <form method="post" style="display:inline">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="toggle">
      <input type="hidden" name="id" value="<?= e($p['id']) ?>">
      <button type="submit" class="btn btn-sm <?= $p['actif'] ? 'btn-secondary' : 'btn-primary' ?>" title="<?= $p['actif'] ? 'Désactiver' : 'Activer' ?>">
        <i data-lucide="<?= $p['actif'] ? 'toggle-right' : 'toggle-left' ?>" style="width:14px;height:14px"></i>
        <?= $p['actif'] ? 'Désactiver' : 'Activer' ?>
      </button>
    </form>
    <form method="post" style="display:inline" onsubmit="return confirm('Supprimer le code <?= e(addslashes($p['code'])) ?> ?')">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="<?= e($p['id']) ?>">
      <button type="submit" class="btn btn-sm btn-danger" title="Supprimer">
        <i data-lucide="trash-2" style="width:13px;height:13px"></i>
      </button>
    </form>
  </div>
</div>
<?php endforeach; ?>

<!-- Pagination -->
<?php if ($pagi['pages'] > 1): ?>
<div style="display:flex;justify-content:center;gap:6px;margin-top:20px;flex-wrap:wrap">
  <?php for ($i = 1; $i <= $pagi['pages']; $i++): ?>
  <a href="?p=<?= $i ?><?= $q ? '&q=' . urlencode($q) : '' ?>"
     style="padding:6px 12px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;<?= $i === $page ? 'background:var(--primary);color:white' : 'background:var(--gris-100);color:var(--gris-700)' ?>">
    <?= $i ?>
  </a>
  <?php endfor; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer_app.php'; ?>
