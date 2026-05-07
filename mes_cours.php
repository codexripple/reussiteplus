<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Mes Cours';
$pageActive = 'mes_cours';
$user = require_login();

// Mes classes actives
$mesClasses = dbAll(
    "SELECT c.id, c.nom FROM classe_membres cm JOIN classes_ecole c ON c.id=cm.classe_id
     WHERE cm.eleve_id=? AND cm.statut='ACTIF' ORDER BY c.nom",
    [$user['id']]
) ?? [];

$classeIds = array_column($mesClasses, 'id');

// Filtres
$filtreType    = $_GET['type'] ?? '';
$filtreClasse  = $_GET['classe'] ?? '';
$q             = trim($_GET['q'] ?? '');

// Cours de la bibliothèque de mes classes
$cours = [];
if ($classeIds) {
    $inPlaceholders = implode(',', array_fill(0, count($classeIds), '?'));
    $params = $classeIds;

    $sql = "SELECT b.*, c.nom as classe_nom FROM bibliotheque_ecole b
            JOIN classes_ecole c ON c.id=b.classe_id
            WHERE b.classe_id IN ($inPlaceholders)";

    if ($filtreType) { $sql .= " AND b.type_ressource=?"; $params[] = $filtreType; }
    if ($filtreClasse) { $sql .= " AND b.classe_id=?"; $params[] = $filtreClasse; }
    if ($q) { $sql .= " AND (b.titre LIKE ? OR b.description LIKE ?)"; $params = array_merge($params, ["%$q%","%$q%"]); }
    $sql .= " ORDER BY b.created_at DESC";

    $cours = dbAll($sql, $params) ?? [];
}

$typesDispos = ['PDF'=>'PDF','VIDEO'=>'Vid&eacute;o','AUDIO'=>'Audio','IMAGE'=>'Image','LIEN'=>'Lien','AUTRE'=>'Autre'];
$coursStats = [
    'cours_total' => count($cours),
    'pdf'         => count(array_filter($cours, fn($c) => ($c['type_ressource'] ?? '')==='PDF')),
];

include __DIR__ . '/includes/header_app.php';
?>

<style>
.cours-card { background:var(--blanc); border:1.5px solid var(--gris-200); border-radius:var(--radius-lg); padding:16px 18px; transition:all .2s; display:flex; flex-direction:column; gap:10px; }
.cours-card:hover { box-shadow:0 6px 24px rgba(0,0,0,.09); transform:translateY(-2px); }
.exo-badge { display:inline-flex; align-items:center; gap:6px; padding:4px 12px; border-radius:20px; font-size:11px; font-weight:800; }
</style>

<!-- Hero -->
<div style="background:linear-gradient(135deg,#0f172a,#1e3a5f 50%,#0f172a);border-radius:var(--radius-xl);padding:26px;margin-bottom:20px">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px">
    <div>
      <div style="font-family:var(--font-display);font-size:20px;font-weight:900;color:#fff;display:flex;align-items:center;gap:10px">
        <i data-lucide="book-open" style="width:22px;height:22px;stroke:#93c5fd"></i> Mes Cours
      </div>
      <div style="font-size:12px;color:rgba(255,255,255,.45);margin-top:3px">Acc&eacute;dez &agrave; vos ressources et t&eacute;l&eacute;chargez les PDF</div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <?php foreach ([['cours_total','Ressources','#fff'],['pdf','PDFs','#60a5fa']] as [$k,$l,$c]): ?>
      <div style="text-align:center;padding:8px 14px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:12px">
        <div style="font-family:var(--font-display);font-size:20px;font-weight:900;color:<?= $c ?>"><?= $coursStats[$k] ?></div>
        <div style="font-size:9px;color:rgba(255,255,255,.4);text-transform:uppercase"><?= $l ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php if (!$mesClasses): ?>
<div class="card" style="text-align:center;padding:60px 30px">
  <div style="display:flex;justify-content:center;margin-bottom:16px"><i data-lucide="layout" style="width:48px;height:48px;stroke:var(--gris-300);stroke-width:1.5"></i></div>
  <div style="font-family:var(--font-display);font-size:20px;font-weight:800;margin-bottom:8px">Vous n'êtes dans aucune classe</div>
  <p style="color:var(--gris-500);font-size:13px;max-width:380px;margin:0 auto 24px">Rejoignez une classe pour accéder aux cours et exercices.</p>
  <a href="/reussiteplus/rejoindre.php" class="btn btn-primary" style="background:#1E5FAD;border-color:#1E5FAD">Rejoindre une classe</a>
</div>
<?php else: ?>

<!-- Barre de filtres -->
<div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;align-items:center">
  <form method="GET" style="display:flex;gap:8px;flex:1;flex-wrap:wrap">
    <div style="position:relative;flex:1;min-width:200px">
      <i data-lucide="search" style="width:14px;height:14px;position:absolute;left:12px;top:50%;transform:translateY(-50%);stroke:var(--gris-400)"></i>
      <input type="text" name="q" value="<?= e($q) ?>" placeholder="Rechercher un cours…" class="form-control" style="padding-left:36px;margin-bottom:0">
    </div>
    <select name="type" class="form-control" style="width:140px;margin-bottom:0">
      <option value="">Tous types</option>
      <?php foreach ($typesDispos as $k => $l): ?>
      <option value="<?= $k ?>" <?= $filtreType===$k?'selected':'' ?>><?= $l ?></option>
      <?php endforeach; ?>
    </select>
    <select name="classe" class="form-control" style="width:160px;margin-bottom:0">
      <option value="">Toutes classes</option>
      <?php foreach ($mesClasses as $cl): ?>
      <option value="<?= e($cl['id']) ?>" <?= $filtreClasse===$cl['id']?'selected':'' ?>><?= e($cl['nom']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary" style="background:#1E5FAD;border-color:#1E5FAD">Filtrer</button>
    <?php if ($q || $filtreType || $filtreClasse): ?>
    <a href="/reussiteplus/mes_cours.php" class="btn btn-ghost">✕ Reset</a>
    <?php endif; ?>
  </form>
</div>

<!-- CTA exercices -->
<a href="/reussiteplus/mes_exercices.php" style="display:flex;align-items:center;gap:12px;background:linear-gradient(90deg,#4c1d95,#7C3AED);border-radius:12px;padding:12px 18px;margin-bottom:18px;text-decoration:none">
  <i data-lucide="brain" style="width:20px;height:20px;stroke:#c4b5fd;flex-shrink:0"></i>
  <div style="flex:1">
    <div style="font-weight:800;color:#fff;font-size:13px">Acc&eacute;der &agrave; mes exercices</div>
    <div style="font-size:11px;color:rgba(255,255,255,.55)">QCM, Vrai/Faux et exercices interactifs de vos classes</div>
  </div>
  <i data-lucide="arrow-right" style="width:16px;height:16px;stroke:#c4b5fd"></i>
</a>

<!-- ══ COURS / RESSOURCES ══════════════════════════════ -->
<div id="section-cours" style="margin-bottom:32px">
  <div style="font-family:var(--font-display);font-size:16px;font-weight:900;margin-bottom:14px;display:flex;align-items:center;gap:8px"><i data-lucide="book-open" style="width:16px;height:16px;stroke:#1E5FAD"></i> Ressources de cours</div>

  <?php if ($cours): ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px">
  <?php foreach ($cours as $c):
    $typeIcons = ['PDF'=>'file-text','VIDEO'=>'video','AUDIO'=>'music','IMAGE'=>'image','LIEN'=>'link','AUTRE'=>'folder'];
    $typeColors = ['PDF'=>['#DC2626','#FEE2E2'],'VIDEO'=>['#7C3AED','#EDE9FE'],
                   'AUDIO'=>['#059669','#D1FAE5'],'IMAGE'=>['#B45309','#FEF3C7'],
                   'LIEN'=>['#1E5FAD','#DBEAFE'],'AUTRE'=>['#6B7280','#F3F4F6']];
    $tIcon = $typeIcons[$c['type_ressource']??'AUTRE'] ?? 'folder';
    [$tColor,$tBg] = $typeColors[$c['type_ressource']??'AUTRE'] ?? $typeColors['AUTRE'];
  ?>
  <div class="cours-card">
    <div style="display:flex;align-items:flex-start;gap:12px">
      <div style="width:44px;height:44px;background:<?= $tBg ?>;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <i data-lucide="<?= $tIcon ?>" style="width:20px;height:20px;stroke:<?= $tColor ?>"></i>
      </div>
      <div style="flex:1;min-width:0">
        <div style="font-size:10px;color:var(--gris-400);text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px"><?= e($c['classe_nom']??'') ?> <?= $c['matiere'] ? '· '.e($c['matiere']) : '' ?></div>
        <div style="font-family:var(--font-display);font-size:14px;font-weight:800;color:var(--gris-900);margin-bottom:4px;line-height:1.2"><?= e($c['titre']??'') ?></div>
        <?php if ($c['description']): ?>
        <div style="font-size:11px;color:var(--gris-500);line-height:1.4"><?= e(mb_strimwidth($c['description'],0,80,'…')) ?></div>
        <?php endif; ?>
      </div>
    </div>
    <div style="display:flex;align-items:center;justify-content:space-between;padding-top:8px;border-top:1px solid var(--gris-100)">
      <span style="background:<?= $tBg ?>;color:<?= $tColor ?>;font-size:10px;font-weight:700;padding:3px 10px;border-radius:8px;display:inline-flex;align-items:center;gap:4px"><i data-lucide="<?= $tIcon ?>" style="width:10px;height:10px"></i> <?= e($c['type_ressource']??'') ?></span>
      <?php if ($c['fichier_url']): ?>
      <a href="<?= e($c['fichier_url']) ?>" target="_blank" download
         onclick="incrementDl('<?= e($c['id']) ?>')"
         class="btn btn-sm" style="background:<?= $tColor ?>;color:#fff;border:none;font-weight:700;text-decoration:none;display:flex;align-items:center;gap:5px">
        <i data-lucide="download" style="width:12px;height:12px;stroke:#fff"></i>
        Télécharger <?= $c['nb_telechargements'] ? '('.$c['nb_telechargements'].')' : '' ?>
      </a>
      <?php elseif ($c['lien_externe']): ?>
      <a href="<?= e($c['lien_externe']) ?>" target="_blank" rel="noopener"
         class="btn btn-sm" style="background:<?= $tColor ?>;color:#fff;border:none;font-weight:700;text-decoration:none;display:flex;align-items:center;gap:5px">
        <i data-lucide="external-link" style="width:12px;height:12px;stroke:#fff"></i> Ouvrir
      </a>
      <?php else: ?>
      <span style="font-size:11px;color:var(--gris-400)">Pas de fichier</span>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div style="text-align:center;padding:40px 20px;background:var(--gris-50);border:2px dashed var(--gris-200);border-radius:14px">
    <div style="display:flex;justify-content:center;margin-bottom:12px"><i data-lucide="book-open" style="width:40px;height:40px;stroke:var(--gris-300);stroke-width:1.5"></i></div>
    <div style="font-size:14px;font-weight:700;color:var(--gris-600)">Aucune ressource disponible</div>
    <div style="font-size:12px;color:var(--gris-400);margin-top:4px">Vos enseignants n'ont pas encore publi&eacute; de cours.</div>
  </div>
  <?php endif; ?>
</div>



<?php endif; ?>

<script>
function incrementDl(id) {
  fetch('/reussiteplus/api/archives.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({action:'increment_dl', id})
  });
}
</script>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
