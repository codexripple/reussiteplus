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

    if ($filtreType) { $sql .= " AND b.type=?"; $params[] = $filtreType; }
    if ($filtreClasse) { $sql .= " AND b.classe_id=?"; $params[] = $filtreClasse; }
    if ($q) { $sql .= " AND (b.titre LIKE ? OR b.matiere LIKE ? OR b.description LIKE ?)"; $params = array_merge($params, ["%$q%","%$q%","%$q%"]); }
    $sql .= " ORDER BY b.created_at DESC";

    $cours = dbAll($sql, $params) ?? [];
}

// Exercices disponibles pour mes classes
$exercices = [];
if ($classeIds) {
    $inPlaceholders = implode(',', array_fill(0, count($classeIds), '?'));
    $params2 = array_merge([$user['id']], $classeIds, [$user['id']]);
    $exercices = dbAll(
        "SELECT e.*,
                (SELECT COUNT(*) FROM questions_exercice WHERE exercice_id=e.id) as nb_questions,
                (SELECT s.score FROM sessions_exercice s WHERE s.exercice_id=e.id AND s.eleve_id=? AND s.statut='TERMINE' ORDER BY s.termine_le DESC LIMIT 1) as mon_score,
                c.nom as classe_nom
         FROM exercices_ecole e
         LEFT JOIN classes_ecole c ON c.id=e.classe_id
         WHERE e.actif=1 AND (e.classe_id IN ($inPlaceholders) OR e.classe_id IS NULL)
         AND (SELECT COUNT(*) FROM questions_exercice WHERE exercice_id=e.id) > 0
         ORDER BY e.created_at DESC",
        $params2
    ) ?? [];
}

$typesDispos = ['PDF'=>'📄 PDF','VIDEO'=>'🎬 Vidéo','AUDIO'=>'🎵 Audio','IMAGE'=>'🖼️ Image','LIEN'=>'🔗 Lien','AUTRE'=>'📁 Autre'];
$stats = [
    'cours_total'  => count($cours),
    'pdf'          => count(array_filter($cours, fn($c) => $c['type']==='PDF')),
    'exercices'    => count($exercices),
    'faits'        => count(array_filter($exercices, fn($e) => $e['mon_score']!==null)),
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
        📚 Mes Cours & Exercices
      </div>
      <div style="font-size:12px;color:rgba(255,255,255,.45);margin-top:3px">Accédez à vos cours, téléchargez les PDF et passez vos exercices</div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <?php foreach ([['cours_total','Ressources','#fff'],['pdf','PDFs','#60a5fa'],['exercices','Quiz','#a78bfa'],['faits','Faits','#34d399']] as [$k,$l,$c]): ?>
      <div style="text-align:center;padding:8px 14px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:12px">
        <div style="font-family:var(--font-display);font-size:20px;font-weight:900;color:<?= $c ?>"><?= $stats[$k] ?></div>
        <div style="font-size:9px;color:rgba(255,255,255,.4);text-transform:uppercase"><?= $l ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php if (!$mesClasses): ?>
<div class="card" style="text-align:center;padding:60px 30px">
  <div style="font-size:56px;margin-bottom:16px">🏫</div>
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

<!-- Tabs -->
<div style="display:flex;gap:4px;margin-bottom:20px;background:var(--gris-100);padding:4px;border-radius:12px;width:fit-content">
  <a href="#section-cours" class="tab-btn" style="padding:7px 18px;border-radius:9px;font-size:13px;font-weight:700;text-decoration:none;background:var(--blanc);color:var(--primary);box-shadow:0 1px 4px rgba(0,0,0,.08)">📚 Cours (<?= count($cours) ?>)</a>
  <a href="#section-exercices" class="tab-btn" style="padding:7px 18px;border-radius:9px;font-size:13px;font-weight:700;text-decoration:none;color:var(--gris-600)">🧠 Exercices (<?= count($exercices) ?>)</a>
</div>

<!-- ══ COURS / RESSOURCES ══════════════════════════════ -->
<div id="section-cours" style="margin-bottom:32px">
  <div style="font-family:var(--font-display);font-size:16px;font-weight:900;margin-bottom:14px">📚 Ressources de cours</div>

  <?php if ($cours): ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px">
  <?php foreach ($cours as $c):
    $typeColors = ['PDF'=>['#DC2626','#FEE2E2','📄'],'VIDEO'=>['#7C3AED','#EDE9FE','🎬'],
                   'AUDIO'=>['#059669','#D1FAE5','🎵'],'IMAGE'=>['#B45309','#FEF3C7','🖼️'],
                   'LIEN'=>['#1E5FAD','#DBEAFE','🔗'],'AUTRE'=>['#6B7280','#F3F4F6','📁']];
    [$tColor,$tBg,$tIcon] = $typeColors[$c['type']??'AUTRE'] ?? $typeColors['AUTRE'];
  ?>
  <div class="cours-card">
    <div style="display:flex;align-items:flex-start;gap:12px">
      <div style="width:44px;height:44px;background:<?= $tBg ?>;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0"><?= $tIcon ?></div>
      <div style="flex:1;min-width:0">
        <div style="font-size:10px;color:var(--gris-400);text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px"><?= e($c['classe_nom']??'') ?> <?= $c['matiere'] ? '· '.e($c['matiere']) : '' ?></div>
        <div style="font-family:var(--font-display);font-size:14px;font-weight:800;color:var(--gris-900);margin-bottom:4px;line-height:1.2"><?= e($c['titre']??'') ?></div>
        <?php if ($c['description']): ?>
        <div style="font-size:11px;color:var(--gris-500);line-height:1.4"><?= e(mb_strimwidth($c['description'],0,80,'…')) ?></div>
        <?php endif; ?>
      </div>
    </div>
    <div style="display:flex;align-items:center;justify-content:space-between;padding-top:8px;border-top:1px solid var(--gris-100)">
      <span style="background:<?= $tBg ?>;color:<?= $tColor ?>;font-size:10px;font-weight:700;padding:3px 10px;border-radius:8px"><?= $tIcon ?> <?= e($c['type']??'') ?></span>
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
    <div style="font-size:44px;margin-bottom:12px">📚</div>
    <div style="font-size:14px;font-weight:700;color:var(--gris-600)">Aucune ressource disponible</div>
    <div style="font-size:12px;color:var(--gris-400);margin-top:4px">Vos enseignants n'ont pas encore publié de cours.</div>
  </div>
  <?php endif; ?>
</div>

<!-- ══ EXERCICES ══════════════════════════════════════════ -->
<div id="section-exercices">
  <div style="font-family:var(--font-display);font-size:16px;font-weight:900;margin-bottom:14px">🧠 Exercices & Questionnaires</div>

  <?php if ($exercices): ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px">
  <?php foreach ($exercices as $exo):
    $typeColors = ['QCM'=>['#1E5FAD','#DBEAFE'],'VRAI_FAUX'=>['#059669','#D1FAE5'],'MIXTE'=>['#7C3AED','#EDE9FE']];
    [$tColor,$tBg] = $typeColors[$exo['type']??'QCM'] ?? $typeColors['QCM'];
    $monScore = $exo['mon_score'];
    $pct = $monScore!==null && $exo['note_max']>0 ? round($monScore/$exo['note_max']*100) : null;
    $scoreColor = $pct!==null ? ($pct>=70?'#059669':($pct>=50?'#B45309':'#DC2626')) : null;
  ?>
  <div class="cours-card" style="border-top:3px solid <?= $tColor ?>">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px">
      <div style="flex:1">
        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:6px">
          <span class="exo-badge" style="background:<?= $tBg ?>;color:<?= $tColor ?>"><?= $exo['type'] ?></span>
          <?php if ($exo['classe_nom']): ?>
          <span style="background:var(--gris-100);color:var(--gris-600);font-size:10px;font-weight:700;padding:2px 8px;border-radius:6px"><?= e($exo['classe_nom']) ?></span>
          <?php endif; ?>
        </div>
        <div style="font-family:var(--font-display);font-size:14px;font-weight:800;color:var(--gris-900);margin-bottom:5px"><?= e($exo['titre']) ?></div>
        <div style="display:flex;gap:10px;font-size:11px;color:var(--gris-400)">
          <span>❓ <?= $exo['nb_questions'] ?> questions</span>
          <span>⏱ <?= $exo['duree_minutes'] ?> min</span>
        </div>
      </div>
      <?php if ($pct !== null): ?>
      <div style="text-align:center;background:<?= $tBg ?>;border-radius:12px;padding:8px 12px;flex-shrink:0">
        <div style="font-family:var(--font-display);font-size:18px;font-weight:900;color:<?= $scoreColor ?>"><?= $pct ?>%</div>
        <div style="font-size:9px;color:var(--gris-500);text-transform:uppercase">Score</div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Score bar -->
    <?php if ($pct !== null): ?>
    <div style="height:5px;background:var(--gris-200);border-radius:3px;overflow:hidden">
      <div style="width:<?= $pct ?>%;height:100%;background:<?= $scoreColor ?>;border-radius:3px"></div>
    </div>
    <?php endif; ?>

    <a href="/reussiteplus/passer_exercice.php?id=<?= urlencode($exo['id']) ?>"
       class="btn" style="width:100%;justify-content:center;background:<?= $tColor ?>;color:#fff;border:none;font-weight:800;text-decoration:none;display:flex;align-items:center;gap:7px;padding:10px">
      <i data-lucide="<?= $pct!==null?'refresh-cw':'play' ?>" style="width:14px;height:14px;stroke:#fff"></i>
      <?= $pct !== null ? 'Recommencer' : 'Commencer l\'exercice' ?>
    </a>
  </div>
  <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div style="text-align:center;padding:40px 20px;background:var(--gris-50);border:2px dashed var(--gris-200);border-radius:14px">
    <div style="font-size:44px;margin-bottom:12px">🧠</div>
    <div style="font-size:14px;font-weight:700;color:var(--gris-600)">Aucun exercice disponible</div>
    <div style="font-size:12px;color:var(--gris-400);margin-top:4px">Vos enseignants n'ont pas encore publié d'exercices.</div>
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

// Scroll tabs
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', e => {
    e.preventDefault();
    const target = document.querySelector(btn.getAttribute('href'));
    if (target) target.scrollIntoView({behavior:'smooth'});
    document.querySelectorAll('.tab-btn').forEach(b => {
      b.style.background = '';
      b.style.color = 'var(--gris-600)';
      b.style.boxShadow = '';
    });
    btn.style.background = 'var(--blanc)';
    btn.style.color = 'var(--primary)';
    btn.style.boxShadow = '0 1px 4px rgba(0,0,0,.08)';
  });
});
</script>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
