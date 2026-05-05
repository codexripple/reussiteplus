<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Bibliothèque pédagogique';
$pageActive = 'ecole';
$user = require_login();
if ($user['plan'] !== 'ECOLE') redirect('/reussiteplus/tarifs.php');

$matieres = dbAll("SELECT id, nom FROM matieres ORDER BY nom") ?? [];
$classes  = dbAll("SELECT id, nom FROM classes_ecole WHERE admin_id=? AND actif=1 ORDER BY nom", [$user['id']]) ?? [];
$filtre   = $_GET['type'] ?? '';
$filtreM  = $_GET['matiere'] ?? '';

// ── Actions ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { http_response_code(403); exit; }
    $action = $_POST['action'] ?? '';

    if ($action === 'ajouter_ressource') {
        $titre    = trim($_POST['titre']          ?? '');
        $desc     = trim($_POST['description']    ?? '');
        $type     = $_POST['type_ressource']      ?? 'DOCUMENT';
        $lien     = trim($_POST['lien_externe']   ?? '');
        $classeId = $_POST['classe_id']           ?: null;
        $matId    = $_POST['matiere_id']          ?: null;

        $validTypes = ['PDF','DOCUMENT','LIEN','VIDEO','IMAGE','AUTRE'];
        $type = in_array($type, $validTypes) ? $type : 'DOCUMENT';

        if ($titre) {
            dbRun("INSERT INTO bibliotheque_ecole (ecole_admin_id, classe_id, titre, description, type_ressource, lien_externe, matiere_id, created_by) VALUES (?,?,?,?,?,?,?,?)",
                [$user['id'], $classeId, $titre, $desc ?: null, $type, $lien ?: null, $matId, $user['id']]);
            redirect('/reussiteplus/ecole_bibliotheque.php', 'success', 'Ressource ajoutée.');
        }
    }

    if ($action === 'supprimer_ressource') {
        $id = $_POST['ressource_id'] ?? '';
        dbRun("DELETE FROM bibliotheque_ecole WHERE id=? AND ecole_admin_id=?", [$id, $user['id']]);
        redirect('/reussiteplus/ecole_bibliotheque.php', 'success', 'Ressource supprimée.');
    }

    if ($action === 'increment_dl') {
        $id = $_POST['ressource_id'] ?? '';
        dbRun("UPDATE bibliotheque_ecole SET nb_telechargements = nb_telechargements + 1 WHERE id=? AND ecole_admin_id=?", [$id, $user['id']]);
        http_response_code(204); exit;
    }
    exit;
}

// ── Données ───────────────────────────────────────────────────
$whereExtra = '';
$params = [$user['id']];
if ($filtre) { $whereExtra .= ' AND b.type_ressource=?'; $params[] = $filtre; }
if ($filtreM) { $whereExtra .= ' AND b.matiere_id=?'; $params[] = $filtreM; }

$ressources = dbAll(
    "SELECT b.*, m.nom as matiere_nom, c.nom as classe_nom
     FROM bibliotheque_ecole b
     LEFT JOIN matieres m ON m.id=b.matiere_id
     LEFT JOIN classes_ecole c ON c.id=b.classe_id
     WHERE b.ecole_admin_id=? $whereExtra
     ORDER BY b.created_at DESC",
    $params
) ?? [];

$typeStats = dbAll("SELECT type_ressource, COUNT(*) as nb FROM bibliotheque_ecole WHERE ecole_admin_id=? GROUP BY type_ressource", [$user['id']]) ?? [];
$statsMap = [];
foreach ($typeStats as $ts) $statsMap[$ts['type_ressource']] = $ts['nb'];

$typeConfig = [
    'PDF'      => ['icon'=>'file-type',    'color'=>'#DC2626','bg'=>'#FEE2E2','label'=>'PDF'],
    'DOCUMENT' => ['icon'=>'file-text',    'color'=>'#1E5FAD','bg'=>'#DBEAFE','label'=>'Document'],
    'LIEN'     => ['icon'=>'link',         'color'=>'#059669','bg'=>'#D1FAE5','label'=>'Lien web'],
    'VIDEO'    => ['icon'=>'video',        'color'=>'#7C3AED','bg'=>'#EDE9FE','label'=>'Vidéo'],
    'IMAGE'    => ['icon'=>'image',        'color'=>'#D97706','bg'=>'#FEF3C7','label'=>'Image'],
    'AUTRE'    => ['icon'=>'paperclip',    'color'=>'#6B7280','bg'=>'#F3F4F6','label'=>'Autre'],
];

include __DIR__ . '/includes/header_app.php';
?>

<style>
.bib-hero { background:linear-gradient(135deg,#3b0764,#7c3aed 50%,#1e1b4b); border-radius:var(--radius-xl); padding:28px; margin-bottom:24px; }
.bib-filter-bar { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:20px; align-items:center; }
.bib-filter { padding:7px 14px; border-radius:20px; border:1.5px solid var(--gris-200); background:var(--blanc); font-size:12px; font-weight:600; color:var(--gris-600); text-decoration:none; transition:.15s; cursor:pointer; display:inline-flex; align-items:center; gap:5px; }
.bib-filter:hover, .bib-filter.active { border-color:var(--primary); background:var(--primary-subtle); color:var(--primary); }
.bib-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:16px; }
.bib-card { background:var(--blanc); border:1.5px solid var(--gris-200); border-radius:var(--radius-lg); overflow:hidden; transition:all .2s; display:flex; flex-direction:column; }
.bib-card:hover { box-shadow:0 4px 20px rgba(0,0,0,.1); transform:translateY(-2px); border-color:var(--gris-300); }
.bib-card-top { padding:16px; flex:1; }
.bib-card-icon { width:42px; height:42px; border-radius:11px; display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-bottom:12px; }
.bib-card-title { font-family:var(--font-display); font-size:14px; font-weight:700; color:var(--gris-900); margin-bottom:5px; line-height:1.3; }
.bib-card-desc { font-size:12px; color:var(--gris-500); line-height:1.5; }
.bib-card-footer { padding:10px 16px; background:var(--gris-50); border-top:1px solid var(--gris-100); display:flex; align-items:center; justify-content:space-between; }
.modal-bd { position:fixed; inset:0; background:rgba(0,0,0,.5); display:flex; align-items:center; justify-content:center; z-index:1000; padding:20px; backdrop-filter:blur(4px); }
.modal-card { background:var(--blanc); border-radius:20px; width:100%; max-width:480px; max-height:90vh; overflow-y:auto; }
.modal-head { padding:20px 24px 16px; border-bottom:1px solid var(--gris-100); display:flex; align-items:center; justify-content:space-between; }
.modal-title { font-family:var(--font-display); font-size:16px; font-weight:800; display:flex; align-items:center; gap:8px; }
.modal-body { padding:20px 24px; }
</style>

<!-- Hero -->
<div class="bib-hero">
  <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
    <div>
      <div style="font-size:11px;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">
        <a href="/reussiteplus/ecole.php" style="color:rgba(255,255,255,.4);text-decoration:none">Mon École</a> / Bibliothèque
      </div>
      <div style="font-family:var(--font-display);font-size:22px;font-weight:900;color:#fff;margin-bottom:4px">Bibliothèque pédagogique</div>
      <div style="font-size:13px;color:rgba(255,255,255,.5)"><?= count($ressources) ?> ressource<?= count($ressources)!=1?'s':'' ?> disponible<?= count($ressources)!=1?'s':'' ?></div>
    </div>
    <div style="display:flex;gap:16px;flex-wrap:wrap">
      <?php foreach ($typeConfig as $k => $tc): ?>
      <div style="text-align:center">
        <div style="font-family:var(--font-display);font-size:20px;font-weight:900;color:#fff"><?= $statsMap[$k] ?? 0 ?></div>
        <div style="font-size:10px;color:rgba(255,255,255,.4);text-transform:uppercase"><?= $tc['label'] ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <button onclick="document.getElementById('modal-add').style.display='flex'"
            style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);color:#fff;padding:10px 18px;border-radius:var(--radius);font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:7px;transition:.15s;flex-shrink:0"
            onmouseover="this.style.background='rgba(255,255,255,.25)'" onmouseout="this.style.background='rgba(255,255,255,.15)'">
      <i data-lucide="plus" style="width:15px;height:15px;stroke:#fff"></i> Ajouter une ressource
    </button>
  </div>
</div>

<!-- Filtres -->
<div class="bib-filter-bar">
  <a href="/reussiteplus/ecole_bibliotheque.php" class="bib-filter <?= !$filtre?'active':'' ?>">
    <i data-lucide="grid" style="width:12px;height:12px"></i> Toutes
  </a>
  <?php foreach ($typeConfig as $k => $tc): ?>
  <?php if (($statsMap[$k] ?? 0) > 0): ?>
  <a href="/reussiteplus/ecole_bibliotheque.php?type=<?= $k ?>" class="bib-filter <?= $filtre===$k?'active':'' ?>">
    <i data-lucide="<?= $tc['icon'] ?>" style="width:12px;height:12px"></i> <?= $tc['label'] ?>
    <span style="background:var(--gris-200);border-radius:10px;padding:1px 6px;font-size:10px"><?= $statsMap[$k] ?></span>
  </a>
  <?php endif; ?>
  <?php endforeach; ?>
  <?php if ($matieres): ?>
  <select onchange="location.href='/reussiteplus/ecole_bibliotheque.php?matiere='+this.value" class="form-control" style="width:auto;padding:6px 12px;font-size:12px;height:36px">
    <option value="">Toutes les matières</option>
    <?php foreach ($matieres as $mat): ?>
    <option value="<?= $mat['id'] ?>" <?= $filtreM==$mat['id']?'selected':'' ?>><?= e($mat['nom']) ?></option>
    <?php endforeach; ?>
  </select>
  <?php endif; ?>
</div>

<!-- Grille ressources -->
<?php if ($ressources): ?>
<div class="bib-grid">
  <?php foreach ($ressources as $res): ?>
  <?php $tc = $typeConfig[$res['type_ressource']] ?? $typeConfig['AUTRE']; ?>
  <div class="bib-card">
    <div class="bib-card-top">
      <div class="bib-card-icon" style="background:<?= $tc['bg'] ?>">
        <i data-lucide="<?= $tc['icon'] ?>" style="width:20px;height:20px;stroke:<?= $tc['color'] ?>"></i>
      </div>
      <div class="bib-card-title"><?= e($res['titre']) ?></div>
      <?php if ($res['description']): ?>
      <div class="bib-card-desc"><?= e(mb_substr($res['description'],0,100)) ?><?= mb_strlen($res['description'])>100?'…':'' ?></div>
      <?php endif; ?>
      <div style="display:flex;flex-wrap:wrap;gap:5px;margin-top:10px">
        <span style="background:<?= $tc['bg'] ?>;color:<?= $tc['color'] ?>;font-size:10px;font-weight:700;padding:2px 8px;border-radius:8px"><?= $tc['label'] ?></span>
        <?php if ($res['matiere_nom']): ?>
        <span style="background:var(--primary-subtle);color:var(--primary);font-size:10px;font-weight:700;padding:2px 8px;border-radius:8px"><?= e($res['matiere_nom']) ?></span>
        <?php endif; ?>
        <?php if ($res['classe_nom']): ?>
        <span style="background:#EDE9FE;color:#7C3AED;font-size:10px;font-weight:700;padding:2px 8px;border-radius:8px"><?= e($res['classe_nom']) ?></span>
        <?php else: ?>
        <span style="background:#F3F4F6;color:#6B7280;font-size:10px;font-weight:700;padding:2px 8px;border-radius:8px">Toutes classes</span>
        <?php endif; ?>
      </div>
    </div>
    <div class="bib-card-footer">
      <div style="font-size:11px;color:var(--gris-400)">
        <i data-lucide="download" style="width:11px;height:11px;vertical-align:-1px"></i>
        <?= $res['nb_telechargements'] ?> accès
        · <?= date('d/m/Y', strtotime($res['created_at'])) ?>
      </div>
      <div style="display:flex;gap:6px">
        <?php if ($res['lien_externe']): ?>
        <a href="<?= e($res['lien_externe']) ?>" target="_blank" rel="noopener"
           onclick="fetch('/reussiteplus/ecole_bibliotheque.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=increment_dl&ressource_id=<?= e($res['id']) ?>&csrf_token=<?= csrf_token() ?>'})"
           style="padding:5px 10px;background:var(--primary);color:#fff;border-radius:var(--radius);font-size:11px;font-weight:700;text-decoration:none;display:flex;align-items:center;gap:4px;transition:.15s"
           onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
          <i data-lucide="external-link" style="width:11px;height:11px;stroke:#fff"></i> Ouvrir
        </a>
        <?php endif; ?>
        <form method="POST" onsubmit="return confirm('Supprimer cette ressource ?')">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="supprimer_ressource">
          <input type="hidden" name="ressource_id" value="<?= e($res['id']) ?>">
          <button type="submit" style="padding:5px 8px;background:none;border:1px solid #FECACA;border-radius:var(--radius);color:#DC2626;cursor:pointer;transition:.15s;display:flex;align-items:center"
                  onmouseover="this.style.background='#FEE2E2'" onmouseout="this.style.background='none'">
            <i data-lucide="trash-2" style="width:12px;height:12px"></i>
          </button>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php else: ?>
<div class="card" style="text-align:center;padding:60px 30px">
  <div style="width:72px;height:72px;background:#EDE9FE;border-radius:20px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
    <i data-lucide="book-open" style="width:32px;height:32px;stroke:#7C3AED"></i>
  </div>
  <div style="font-family:var(--font-display);font-size:20px;font-weight:800;color:var(--gris-800);margin-bottom:8px">Bibliothèque vide</div>
  <p style="color:var(--gris-500);max-width:380px;margin:0 auto 20px;font-size:14px">
    Partagez des cours, vidéos, liens et documents pédagogiques avec vos élèves et enseignants.
  </p>
  <button onclick="document.getElementById('modal-add').style.display='flex'" class="btn btn-primary" style="background:#7C3AED;border-color:#7C3AED">
    <i data-lucide="plus" style="width:14px;height:14px;vertical-align:-2px"></i> Ajouter la première ressource
  </button>
</div>
<?php endif; ?>

<!-- ══ MODAL Ajouter ressource ════════════════════════════════ -->
<div id="modal-add" class="modal-bd" style="display:none" onclick="if(event.target===this)this.style.display='none'">
  <div class="modal-card">
    <div class="modal-head">
      <span class="modal-title"><i data-lucide="book-plus" style="width:16px;height:16px;stroke:#7C3AED"></i> Nouvelle ressource</span>
      <button onclick="document.getElementById('modal-add').style.display='none'" style="background:none;border:none;cursor:pointer;color:var(--gris-500)"><i data-lucide="x" style="width:18px;height:18px"></i></button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="ajouter_ressource">
        <div class="form-group">
          <label class="form-label">Titre *</label>
          <input type="text" name="titre" class="form-control" required placeholder="Ex : Cours de mathématiques — 6ème">
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="2" placeholder="Résumé du contenu…"></textarea>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group">
            <label class="form-label">Type</label>
            <select name="type_ressource" class="form-control" id="type-sel" onchange="toggleLien(this.value)">
              <option value="PDF">PDF</option>
              <option value="DOCUMENT">Document</option>
              <option value="LIEN" selected>Lien web</option>
              <option value="VIDEO">Vidéo</option>
              <option value="IMAGE">Image</option>
              <option value="AUTRE">Autre</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Matière</label>
            <select name="matiere_id" class="form-control">
              <option value="">-- Toutes --</option>
              <?php foreach ($matieres as $mat): ?>
              <option value="<?= $mat['id'] ?>"><?= e($mat['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group" id="lien-group">
          <label class="form-label">URL / Lien *</label>
          <input type="url" name="lien_externe" class="form-control" placeholder="https://…">
        </div>
        <div class="form-group">
          <label class="form-label">Classe (laisser vide = toutes)</label>
          <select name="classe_id" class="form-control">
            <option value="">Toutes les classes</option>
            <?php foreach ($classes as $cl): ?>
            <option value="<?= e($cl['id']) ?>"><?= e($cl['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;background:#7C3AED;border-color:#7C3AED">
          <i data-lucide="check" style="width:14px;height:14px;vertical-align:-2px"></i> Ajouter
        </button>
      </form>
    </div>
  </div>
</div>

<script>
function toggleLien(type) {
  const g = document.getElementById('lien-group');
  g.style.display = ['LIEN','VIDEO'].includes(type) ? '' : 'none';
}
document.addEventListener('DOMContentLoaded', () => toggleLien(document.getElementById('type-sel')?.value));
</script>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
