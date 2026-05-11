<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$pageTitle  = 'Gestionnaire de publicités';
$pageActive = 'admin_publicite';
$user       = require_admin();

if (empty($_SESSION['csrf_admin'])) $_SESSION['csrf_admin'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_admin'];

// ── Actions ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf_admin'], $_POST['csrf'])) {
        redirect('/reussiteplus/admin/publicite.php', 'error', 'Action non autorisée.');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'creer' || $action === 'modifier') {
        $data = [
            'titre'        => trim($_POST['titre']       ?? ''),
            'description'  => trim($_POST['description'] ?? '') ?: null,
            'image_url'    => trim($_POST['image_url']   ?? '') ?: null,
            'lien_url'     => trim($_POST['lien_url']    ?? '') ?: null,
            'cta_texte'    => trim($_POST['cta_texte']   ?? 'En savoir plus'),
            'position'     => $_POST['position']          ?? 'FEED',
            'plans_cibles' => json_encode($_POST['plans_cibles'] ?? ['GRATUIT']),
            'pages_cibles' => json_encode($_POST['pages_cibles'] ?? ['*']),
            'annonceur'    => trim($_POST['annonceur']   ?? '') ?: null,
            'budget_cdf'   => (float)($_POST['budget_cdf'] ?? 0),
            'date_debut'   => $_POST['date_debut'] ?: null,
            'date_fin'     => $_POST['date_fin']   ?: null,
            'actif'        => isset($_POST['actif']) ? 1 : 0,
        ];
        if (!$data['titre']) redirect('/reussiteplus/admin/publicite.php', 'error', 'Titre requis.');

        if ($action === 'creer') {
            $data['created_by'] = $user['id'];
            dbInsert('publicites', $data);
            redirect('/reussiteplus/admin/publicite.php', 'success', 'Publicité créée.');
        } else {
            $id = $_POST['pub_id'] ?? '';
            if ($id) {
                $sets = implode(', ', array_map(fn($k) => "`$k`=?", array_keys($data)));
                dbQuery("UPDATE publicites SET $sets WHERE id=?", [...array_values($data), $id]);
                redirect('/reussiteplus/admin/publicite.php', 'success', 'Publicité mise à jour.');
            }
        }
    }

    if ($action === 'toggle') {
        $id = $_POST['pub_id'] ?? '';
        $p  = dbRow("SELECT actif FROM publicites WHERE id=?", [$id]);
        if ($p) { dbQuery("UPDATE publicites SET actif=? WHERE id=?", [$p['actif'] ? 0 : 1, $id]); }
        redirect('/reussiteplus/admin/publicite.php', 'success', 'Statut mis à jour.');
    }

    if ($action === 'supprimer') {
        dbQuery("DELETE FROM publicites WHERE id=?", [$_POST['pub_id'] ?? '']);
        redirect('/reussiteplus/admin/publicite.php', 'success', 'Publicité supprimée.');
    }
}

// ── Données ───────────────────────────────────────────────────
$pubs = dbAll(
    "SELECT p.*,
            (SELECT COUNT(*) FROM ad_impressions WHERE pub_id=p.id AND type='IMPRESSION') as nb_impressions,
            (SELECT COUNT(*) FROM ad_impressions WHERE pub_id=p.id AND type='CLICK')      as nb_clicks
     FROM publicites p
     ORDER BY p.created_at DESC"
) ?? [];

// Stats globales
$totalImp    = (int)(dbScalar("SELECT COUNT(*) FROM ad_impressions WHERE type='IMPRESSION'") ?? 0);
$totalClicks = (int)(dbScalar("SELECT COUNT(*) FROM ad_impressions WHERE type='CLICK'")      ?? 0);
$ctr         = $totalImp > 0 ? round($totalClicks / $totalImp * 100, 2) : 0;
$revenu      = (float)(dbScalar("SELECT COALESCE(SUM(budget_cdf),0) FROM publicites WHERE actif=1") ?? 0);

// Edit
$editPub = null;
if (isset($_GET['edit'])) $editPub = dbRow("SELECT * FROM publicites WHERE id=?", [$_GET['edit']]);

include __DIR__ . '/../includes/header_app.php';
?>

<style>
.pub-grid { display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px; }
@media(max-width:768px){ .pub-grid{grid-template-columns:repeat(2,1fr)} }
.pub-card { background:var(--blanc);border:1px solid var(--gris-200);border-radius:14px;overflow:hidden;transition:box-shadow .2s; }
.pub-card:hover { box-shadow:0 4px 16px rgba(0,0,0,.07); }
.pub-card-head { padding:14px 16px;border-bottom:1px solid var(--gris-100); }
.pub-card-title { font-size:14px;font-weight:700;color:var(--gris-900);margin-bottom:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
.pub-card-body { padding:14px 16px; }
.pub-stat { display:flex;justify-content:space-between;font-size:12.5px;padding:5px 0;border-bottom:1px solid var(--gris-50); }
.pub-stat:last-child { border:none; }
.pub-stat-val { font-weight:700; }
.pos-badge { font-size:9.5px;font-weight:700;padding:2px 8px;border-radius:4px;text-transform:uppercase;letter-spacing:.4px; }
</style>

<!-- Header -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:22px">
  <div>
    <h1 style="font-size:20px;font-weight:800;color:var(--gris-900);margin-bottom:3px">Ad Manager</h1>
    <div style="font-size:12.5px;color:var(--gris-500)"><?= count($pubs) ?> publicité<?= count($pubs)!=1?'s':'' ?> · Monétisation par segmentation plan</div>
  </div>
  <button onclick="document.getElementById('modal-pub').style.display='flex'" class="btn btn-primary">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="vertical-align:-2px;margin-right:5px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Nouvelle publicité
  </button>
</div>

<!-- KPI -->
<div class="pub-grid">
  <?php foreach ([
    ['Impressions totales', number_format($totalImp),     '#1E5FAD', '<circle cx="12" cy="12" r="10"/><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>'],
    ['Clics totaux',        number_format($totalClicks),  '#007A5E', '<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/>'],
    ['CTR global',          $ctr . '%',                   '#C9972A', '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>'],
    ['Budget actif (CDF)',  number_format($revenu),       '#7C3AED', '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>'],
  ] as [$lbl,$val,$col,$ic]): ?>
  <div class="pub-card">
    <div style="padding:16px;display:flex;flex-direction:column;gap:6px">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <div style="font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--gris-500)"><?= $lbl ?></div>
        <div style="width:32px;height:32px;border-radius:8px;background:<?= $col ?>14;display:flex;align-items:center;justify-content:center">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="<?= $col ?>" stroke-width="2.5" stroke-linecap="round"><?= $ic ?></svg>
        </div>
      </div>
      <div style="font-size:24px;font-weight:900;color:<?= $col ?>;letter-spacing:-.5px"><?= $val ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Liste des publicités -->
<div style="background:var(--blanc);border:1px solid var(--card-border,rgba(0,0,0,.06));border-radius:14px;overflow:hidden">
  <div style="padding:14px 20px;border-bottom:1px solid var(--gris-100)">
    <div style="font-size:13.5px;font-weight:700;color:var(--gris-900)">Publicités actives et inactives</div>
  </div>
  <?php if ($pubs): ?>
  <div style="overflow-x:auto">
    <table class="table" style="margin:0">
      <thead>
        <tr>
          <th style="text-align:left">Publicité</th>
          <th>Position</th>
          <th>Plans ciblés</th>
          <th>Impressions</th>
          <th>Clics</th>
          <th>CTR</th>
          <th>Statut</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($pubs as $p):
        $plansCib = json_decode($p['plans_cibles'] ?? '[]', true) ?: [];
        $ctrP = $p['nb_impressions'] > 0 ? round($p['nb_clicks']/$p['nb_impressions']*100,1) : 0;
        $posColors = ['BANNER_TOP'=>['#C9342A','#FEE2E2'],'FEED'=>['#1E5FAD','#DBEAFE'],'SIDEBAR'=>['#C9972A','#FEF3C7'],'BOTTOM'=>['#6B7280','#F3F4F6']];
        [$pc,$pb] = $posColors[$p['position']] ?? ['#6B7280','#F3F4F6'];
      ?>
      <tr>
        <td>
          <div style="font-weight:700;font-size:13.5px;color:var(--gris-900)"><?= e($p['titre']) ?></div>
          <?php if ($p['annonceur']): ?>
          <div style="font-size:11px;color:var(--gris-400)"><?= e($p['annonceur']) ?></div>
          <?php endif; ?>
          <?php if ($p['lien_url']): ?>
          <div style="font-size:10.5px;color:var(--primary);margin-top:2px">→ <?= e(parse_url($p['lien_url'], PHP_URL_PATH)) ?></div>
          <?php endif; ?>
        </td>
        <td><span class="pos-badge" style="background:<?= $pb ?>;color:<?= $pc ?>"><?= $p['position'] ?></span></td>
        <td>
          <div style="display:flex;gap:4px;flex-wrap:wrap">
          <?php foreach ($plansCib as $plan): ?>
          <span style="font-size:10px;font-weight:600;padding:2px 7px;border-radius:4px;background:var(--gris-100);color:var(--gris-600)"><?= $plan ?></span>
          <?php endforeach; ?>
          </div>
        </td>
        <td style="font-size:13px;font-weight:600;text-align:center"><?= number_format($p['nb_impressions']) ?></td>
        <td style="font-size:13px;font-weight:600;text-align:center;color:var(--primary)"><?= number_format($p['nb_clicks']) ?></td>
        <td style="text-align:center">
          <span style="font-size:12px;font-weight:700;color:<?= $ctrP>=2?'#007A5E':($ctrP>=1?'#C9972A':'var(--gris-400)') ?>"><?= $ctrP ?>%</span>
        </td>
        <td style="text-align:center">
          <form method="POST" style="display:inline">
            <input type="hidden" name="action"  value="toggle">
            <input type="hidden" name="pub_id"  value="<?= e($p['id']) ?>">
            <input type="hidden" name="csrf"    value="<?= e($csrf) ?>">
            <button type="submit" style="background:<?= $p['actif']?'#D1FAE5':'#FEE2E2' ?>;border:1px solid <?= $p['actif']?'#A7F3D0':'#FECACA' ?>;border-radius:6px;padding:4px 10px;font-size:11px;font-weight:700;color:<?= $p['actif']?'#065F46':'#7F1D1D' ?>;cursor:pointer;font-family:inherit">
              <?= $p['actif'] ? 'Active' : 'Inactive' ?>
            </button>
          </form>
        </td>
        <td style="text-align:right">
          <div style="display:flex;gap:5px;justify-content:flex-end">
            <a href="?edit=<?= e($p['id']) ?>" class="btn btn-ghost btn-sm">Modifier</a>
            <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer cette publicité ?')">
              <input type="hidden" name="action" value="supprimer">
              <input type="hidden" name="pub_id" value="<?= e($p['id']) ?>">
              <input type="hidden" name="csrf"   value="<?= e($csrf) ?>">
              <button type="submit" class="btn btn-sm" style="background:#FEE2E2;color:#7F1D1D;border:1px solid #FECACA">Suppr.</button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <div style="text-align:center;padding:48px 24px">
    <div style="font-size:14px;font-weight:600;color:var(--gris-600);margin-bottom:8px">Aucune publicité</div>
    <p style="font-size:13px;color:var(--gris-400);margin-bottom:16px">Créez votre première publicité pour monétiser les comptes gratuits.</p>
    <button onclick="document.getElementById('modal-pub').style.display='flex'" class="btn btn-primary btn-sm">Créer une publicité</button>
  </div>
  <?php endif; ?>
</div>

<!-- ══ MODALE Créer/Modifier ══════════════════════════════════ -->
<div id="modal-pub" style="display:<?= $editPub?'flex':'none' ?>;position:fixed;inset:0;background:rgba(0,0,0,.55);backdrop-filter:blur(4px);z-index:200;align-items:center;justify-content:center;overflow-y:auto" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--blanc);border-radius:18px;padding:26px;width:100%;max-width:560px;margin:20px;box-shadow:0 24px 64px rgba(0,0,0,.2);max-height:90vh;overflow-y:auto">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
      <div style="font-size:16px;font-weight:800;color:var(--gris-900)"><?= $editPub?'Modifier':'Nouvelle' ?> publicité</div>
      <button onclick="document.getElementById('modal-pub').style.display='none'" style="background:none;border:none;cursor:pointer;font-size:22px;color:var(--gris-400)">×</button>
    </div>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="csrf"    value="<?= e($csrf) ?>">
      <input type="hidden" name="action"  value="<?= $editPub?'modifier':'creer' ?>">
      <?php if ($editPub): ?><input type="hidden" name="pub_id" value="<?= e($editPub['id']) ?>"><?php endif; ?>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
        <div style="grid-column:1/-1">
          <label class="form-label">Titre *</label>
          <input type="text" name="titre" class="form-control" value="<?= e($editPub['titre']??'') ?>" required>
        </div>
        <div>
          <label class="form-label">Annonceur</label>
          <input type="text" name="annonceur" class="form-control" value="<?= e($editPub['annonceur']??'') ?>" placeholder="Ex: RÉUSSITE+">
        </div>
        <div>
          <label class="form-label">Budget (CDF)</label>
          <input type="number" name="budget_cdf" class="form-control" value="<?= $editPub['budget_cdf']??0 ?>" min="0">
        </div>
        <div style="grid-column:1/-1">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="2"><?= e($editPub['description']??'') ?></textarea>
        </div>
        <div style="grid-column:1/-1">
          <label class="form-label">URL image (optionnel)</label>
          <input type="url" name="image_url" class="form-control" value="<?= e($editPub['image_url']??'') ?>" placeholder="https://...">
        </div>
        <div style="grid-column:1/-1">
          <label class="form-label">URL de destination</label>
          <input type="text" name="lien_url" class="form-control" value="<?= e($editPub['lien_url']??'') ?>" placeholder="/reussiteplus/tarifs.php">
        </div>
        <div>
          <label class="form-label">Texte du bouton</label>
          <input type="text" name="cta_texte" class="form-control" value="<?= e($editPub['cta_texte']??'En savoir plus') ?>">
        </div>
        <div>
          <label class="form-label">Position</label>
          <select name="position" class="form-control">
            <?php foreach (['BANNER_TOP'=>'Bandeau haut','FEED'=>'Dans le flux','SIDEBAR'=>'Sidebar','BOTTOM'=>'Bas de page'] as $v=>$l): ?>
            <option value="<?= $v ?>" <?= ($editPub['position']??'FEED')===$v?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">Date début</label>
          <input type="date" name="date_debut" class="form-control" value="<?= $editPub['date_debut']??'' ?>">
        </div>
        <div>
          <label class="form-label">Date fin</label>
          <input type="date" name="date_fin" class="form-control" value="<?= $editPub['date_fin']??'' ?>">
        </div>
        <div style="grid-column:1/-1">
          <label class="form-label">Plans ciblés (qui verront cette pub)</label>
          <div style="display:flex;gap:12px;flex-wrap:wrap;padding:10px;background:var(--gris-50);border-radius:8px;border:1px solid var(--gris-200)">
            <?php
            $plansCibEdit = json_decode($editPub['plans_cibles'] ?? '["GRATUIT"]', true) ?: ['GRATUIT'];
            foreach (['GRATUIT','BASIQUE','PREMIUM','ECOLE'] as $p): ?>
            <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
              <input type="checkbox" name="plans_cibles[]" value="<?= $p ?>" <?= in_array($p,$plansCibEdit)?'checked':'' ?>>
              <?= $p ?>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div style="grid-column:1/-1">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;font-weight:600">
            <input type="checkbox" name="actif" value="1" <?= ($editPub['actif']??1)?'checked':'' ?>> Publicité active
          </label>
        </div>
      </div>
      <div style="display:flex;gap:8px">
        <button type="submit" class="btn btn-primary" style="flex:1"><?= $editPub?'Mettre à jour':'Créer la publicité' ?></button>
        <button type="button" onclick="document.getElementById('modal-pub').style.display='none'" class="btn btn-ghost">Annuler</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer_app.php'; ?>
