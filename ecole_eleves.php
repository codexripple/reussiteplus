<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Élèves';
$pageActive = 'ecole_eleves';
$user = require_login();
if ($user['plan'] !== 'ECOLE') redirect('/reussiteplus/tarifs.php');

$filtreClasse = $_GET['classe'] ?? '';
$q = trim($_GET['q'] ?? '');

// ── Actions ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { http_response_code(403); exit; }
    $action = $_POST['action'] ?? '';
    if ($action === 'retirer_eleve') {
        $classeId = $_POST['classe_id'] ?? '';
        $eleveId  = $_POST['eleve_id']  ?? '';
        $c = dbRow("SELECT id FROM classes_ecole WHERE id=? AND admin_id=?", [$classeId, $user['id']]);
        if ($c) dbRun("DELETE FROM classe_membres WHERE classe_id=? AND eleve_id=?", [$classeId, $eleveId]);
        redirect('/reussiteplus/ecole_eleves.php?classe='.urlencode($filtreClasse).'&q='.urlencode($q), 'success', 'Élève retiré.');
    }
    exit;
}

// ── Données ───────────────────────────────────────────────────
$classes = dbAll(
    "SELECT c.id, c.nom, COUNT(DISTINCT cm.eleve_id) as nb_eleves
     FROM classes_ecole c
     LEFT JOIN classe_membres cm ON cm.classe_id=c.id
     WHERE c.admin_id=? AND c.actif=1
     GROUP BY c.id ORDER BY c.nom",
    [$user['id']]
) ?? [];

$whereClass = '';
$params     = [$user['id']];
if ($filtreClasse) { $whereClass = ' AND c.id=? '; $params[] = $filtreClasse; }

$whereQ = '';
if ($q) { $whereQ = ' AND (u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ?) '; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }

$eleves = dbAll(
    "SELECT u.id, u.nom, u.prenom, u.email,
            c.id as classe_id, c.nom as classe_nom,
            cm.joined_at as rejoint_le,
            COUNT(DISTINCT es.id) as nb_examens,
            COALESCE(ROUND(AVG(es.pourcentage),1), 0) as score_moyen,
            MAX(es.finished_at) as dernier_examen
     FROM classes_ecole c
     JOIN classe_membres cm ON cm.classe_id=c.id
     JOIN utilisateurs u ON u.id=cm.eleve_id
     LEFT JOIN exam_sessions es ON es.user_id=u.id AND es.statut='TERMINE'
     WHERE c.admin_id=? $whereClass $whereQ
     GROUP BY u.id, c.id
     ORDER BY c.nom, score_moyen DESC",
    $params
) ?? [];

$totalEleves       = count($eleves);
$scoreMoyenGlobal  = $totalEleves ? round(array_sum(array_column($eleves,'score_moyen'))/$totalEleves,1) : 0;
$elevesEnDifficulte = array_filter($eleves, fn($e) => $e['nb_examens'] > 0 && $e['score_moyen'] < 50);
$elevesActifs      = array_filter($eleves, fn($e) => $e['nb_examens'] > 0);

include __DIR__ . '/includes/header_app.php';
?>

<style>
.eleves-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:14px; }
.eleve-card { background:var(--blanc); border:1.5px solid var(--gris-200); border-radius:var(--radius-lg); padding:16px; transition:all .2s; }
.eleve-card:hover { box-shadow:0 4px 16px rgba(0,0,0,.08); border-color:var(--gris-300); transform:translateY(-1px); }
.eleve-avatar { width:48px; height:48px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-family:var(--font-display); font-size:16px; font-weight:900; flex-shrink:0; }
.score-ring-wrap { position:relative; width:52px; height:52px; flex-shrink:0; }
.score-ring-wrap svg { transform:rotate(-90deg); }
.score-ring-val { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-family:var(--font-display); font-size:11px; font-weight:900; }
</style>

<!-- Hero -->
<div style="background:linear-gradient(135deg,#134e4a,#0d9488 50%,#1e3a5f);border-radius:var(--radius-xl);padding:24px;margin-bottom:20px">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
    <div>
      <div style="font-size:11px;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px">
        <a href="/reussiteplus/ecole.php" style="color:rgba(255,255,255,.4);text-decoration:none">Mon École</a> / Élèves
      </div>
      <div style="font-family:var(--font-display);font-size:20px;font-weight:900;color:#fff"><?= $totalEleves ?> élève<?= $totalEleves!=1?'s':'' ?> inscrits</div>
      <div style="font-size:12px;color:rgba(255,255,255,.5);margin-top:3px">Score moyen global : <strong style="color:#fff"><?= $scoreMoyenGlobal ?>%</strong></div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <?php foreach ($classes as $cl): ?>
      <a href="/reussiteplus/ecole_eleves.php?classe=<?= urlencode($cl['id']) ?>"
         style="padding:6px 12px;border-radius:20px;font-size:11px;font-weight:700;text-decoration:none;transition:.15s;<?= $filtreClasse===$cl['id']?'background:rgba(255,255,255,.9);color:#0d9488':'background:rgba(255,255,255,.15);color:rgba(255,255,255,.8);border:1px solid rgba(255,255,255,.3)' ?>"
         onmouseover="this.style.background='rgba(255,255,255,.25)'" onmouseout="<?= $filtreClasse===$cl['id']?'this.style.background=\'rgba(255,255,255,.9)\'':'this.style.background=\'rgba(255,255,255,.15)\'' ?>">
        <?= e($cl['nom']) ?> <span style="opacity:.6">(<?= $cl['nb_eleves'] ?>)</span>
      </a>
      <?php endforeach; ?>
      <?php if ($filtreClasse): ?>
      <a href="/reussiteplus/ecole_eleves.php" style="padding:6px 12px;border-radius:20px;font-size:11px;font-weight:700;text-decoration:none;background:rgba(255,255,255,.1);color:rgba(255,255,255,.6);border:1px solid rgba(255,255,255,.2)">
        × Tout voir
      </a>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if (count($elevesEnDifficulte) > 0): ?>
<!-- Alerte élèves en difficulté -->
<div style="background:#FEF0EF;border:1.5px solid #FECACA;border-radius:var(--radius-lg);padding:14px 18px;margin-bottom:16px;display:flex;align-items:flex-start;gap:12px">
  <div style="width:34px;height:34px;border-radius:9px;background:#FEE2E2;display:flex;align-items:center;justify-content:center;flex-shrink:0">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#C9342A" stroke-width="2.5" stroke-linecap="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
  </div>
  <div style="flex:1">
    <div style="font-size:13px;font-weight:700;color:#7F1D1D;margin-bottom:4px"><?= count($elevesEnDifficulte) ?> élève<?= count($elevesEnDifficulte)>1?'s':'' ?> en difficulté (score &lt; 50%)</div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px">
      <?php foreach (array_slice($elevesEnDifficulte, 0, 5) as $ed): ?>
      <span style="background:#FEE2E2;color:#7F1D1D;font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px">
        <?= e($ed['prenom'] . ' ' . $ed['nom']) ?> — <?= $ed['score_moyen'] ?>%
      </span>
      <?php endforeach; ?>
      <?php if (count($elevesEnDifficulte) > 5): ?>
      <span style="background:#F3F4F6;color:#6B7280;font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px">+<?= count($elevesEnDifficulte)-5 ?> autres</span>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- KPI rapides -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px">
  <?php foreach ([
    ['label'=>'Inscrits', 'val'=>$totalEleves, 'color'=>'#007A5E'],
    ['label'=>'Actifs', 'val'=>count($elevesActifs), 'color'=>'#1E5FAD'],
    ['label'=>'Score moyen', 'val'=>$scoreMoyenGlobal.'%', 'color'=>($scoreMoyenGlobal>=50?'#007A5E':'#C9342A')],
    ['label'=>'En difficulté', 'val'=>count($elevesEnDifficulte), 'color'=>'#C9342A'],
  ] as $kpi): ?>
  <div style="background:var(--blanc);border:1px solid var(--gris-200);border-radius:12px;padding:14px 16px;text-align:center">
    <div style="font-size:22px;font-weight:900;color:<?= $kpi['color'] ?>"><?= $kpi['val'] ?></div>
    <div style="font-size:10.5px;color:var(--gris-500);margin-top:2px;text-transform:uppercase;letter-spacing:.4px"><?= $kpi['label'] ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Barre de recherche -->
<form method="GET" style="margin-bottom:16px;display:flex;gap:8px">
  <?php if ($filtreClasse): ?><input type="hidden" name="classe" value="<?= e($filtreClasse) ?>"><?php endif; ?>
  <input type="text" name="q" value="<?= e($q) ?>" class="form-control" placeholder="Rechercher un élève par nom ou email…" style="flex:1;max-width:400px">
  <button type="submit" class="btn btn-primary">
    <i data-lucide="search" style="width:13px;height:13px;vertical-align:-2px"></i> Rechercher
  </button>
  <?php if ($q): ?>
  <a href="/reussiteplus/ecole_eleves.php<?= $filtreClasse?'?classe='.urlencode($filtreClasse):'' ?>" class="btn btn-ghost">✕</a>
  <?php endif; ?>
</form>

<?php if ($eleves): ?>

<!-- Grille élèves -->
<div class="eleves-grid">
  <?php foreach ($eleves as $el): ?>
  <?php
    $sc = (float)$el['score_moyen'];
    $scoreColor = $sc >= 70 ? '#059669' : ($sc >= 50 ? '#D97706' : '#DC2626');
    $scoreBg    = $sc >= 70 ? '#D1FAE5' : ($sc >= 50 ? '#FEF3C7' : '#FEE2E2');
    $mention    = $sc >= 80 ? 'Excellent' : ($sc >= 70 ? 'Bien' : ($sc >= 50 ? 'Passable' : 'À améliorer'));
    $initiales  = mb_strtoupper(mb_substr($el['prenom']??'?',0,1) . mb_substr($el['nom']??'',0,1));
    $avatarColors = ['#2563EB','#7C3AED','#059669','#D97706','#DC2626','#0891B2'];
    $avatarColor  = $avatarColors[crc32($el['id']) % count($avatarColors)];
    $circonference = 2 * M_PI * 20;
    $dash = ($sc / 100) * $circonference;
  ?>
  <div class="eleve-card">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">
      <div class="eleve-avatar" style="background:<?= $avatarColor ?>22;color:<?= $avatarColor ?>"><?= $initiales ?></div>
      <div style="flex:1;min-width:0">
        <div style="font-size:14px;font-weight:700;color:var(--gris-900);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
          <?= e(($el['prenom']??'').' '.($el['nom']??'')) ?>
        </div>
        <div style="font-size:11px;color:var(--gris-400);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($el['email']??'') ?></div>
        <span style="display:inline-block;background:#EDE9FE;color:#7C3AED;font-size:10px;font-weight:700;padding:2px 8px;border-radius:8px;margin-top:3px"><?= e($el['classe_nom']) ?></span>
      </div>
      <!-- Score ring -->
      <div class="score-ring-wrap">
        <svg width="52" height="52" viewBox="0 0 52 52">
          <circle cx="26" cy="26" r="20" fill="none" stroke="<?= $scoreBg ?>" stroke-width="5"/>
          <circle cx="26" cy="26" r="20" fill="none" stroke="<?= $scoreColor ?>" stroke-width="5"
                  stroke-dasharray="<?= round($dash,1) ?> <?= round($circonference,1) ?>"
                  stroke-linecap="round"/>
        </svg>
        <div class="score-ring-val" style="color:<?= $scoreColor ?>"><?= $sc ?>%</div>
      </div>
    </div>

    <!-- Stats -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px">
      <div style="background:var(--gris-50);border-radius:8px;padding:8px;text-align:center">
        <div style="font-family:var(--font-display);font-size:16px;font-weight:900;color:var(--gris-900)"><?= $el['nb_examens'] ?></div>
        <div style="font-size:10px;color:var(--gris-500)">Examens</div>
      </div>
      <div style="background:<?= $scoreBg ?>;border-radius:8px;padding:8px;text-align:center">
        <div style="font-family:var(--font-display);font-size:12px;font-weight:900;color:<?= $scoreColor ?>"><?= $mention ?></div>
        <div style="font-size:10px;color:var(--gris-500)">Niveau</div>
      </div>
    </div>

    <?php if ($el['dernier_examen']): ?>
    <div style="font-size:11px;color:var(--gris-400);margin-bottom:10px">
      <i data-lucide="clock" style="width:10px;height:10px;vertical-align:-1px"></i>
      Dernier examen : <?= date('d/m/Y', strtotime($el['dernier_examen'])) ?>
    </div>
    <?php endif; ?>

    <div style="display:flex;gap:6px">
      <a href="/reussiteplus/progression.php?user=<?= urlencode($el['id']) ?>" class="btn btn-ghost btn-sm" style="flex:1;justify-content:center;text-decoration:none;display:flex;align-items:center;gap:4px">
        <i data-lucide="trending-up" style="width:11px;height:11px"></i> Progression
      </a>
      <form method="POST" onsubmit="return confirm('Retirer <?= e(($el['prenom']??'').' '.($el['nom']??'')) ?> de la classe ?')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="retirer_eleve">
        <input type="hidden" name="classe_id" value="<?= e($el['classe_id']) ?>">
        <input type="hidden" name="eleve_id"  value="<?= e($el['id']) ?>">
        <button type="submit" class="btn btn-ghost btn-sm" style="color:#DC2626;border-color:#FECACA" title="Retirer de la classe">
          <i data-lucide="user-minus" style="width:12px;height:12px"></i>
        </button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php else: ?>
<div class="card" style="text-align:center;padding:60px 30px">
  <div style="width:72px;height:72px;background:#CCFBF1;border-radius:20px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
    <i data-lucide="users" style="width:32px;height:32px;stroke:#0d9488"></i>
  </div>
  <div style="font-family:var(--font-display);font-size:20px;font-weight:800;margin-bottom:8px">
    <?= $q ? 'Aucun résultat pour « '.e($q).' »' : 'Aucun élève inscrit' ?>
  </div>
  <p style="color:var(--gris-500);max-width:380px;margin:0 auto 20px;font-size:14px">
    <?= $q ? 'Essayez un autre terme de recherche.' : 'Partagez les codes d\'invitation de vos classes pour que les élèves puissent les rejoindre.' ?>
  </p>
  <a href="/reussiteplus/ecole_classes.php" class="btn btn-primary" style="background:#0d9488;border-color:#0d9488">
    <i data-lucide="layout-list" style="width:14px;height:14px;vertical-align:-2px"></i> Gérer les classes
  </a>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
