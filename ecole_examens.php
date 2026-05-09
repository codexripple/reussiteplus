<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Bibliothèque d\'examens';
$pageActive = 'ecole_examens';
$user       = require_login();

$plan = $user['plan'] ?? 'GRATUIT';
if (!in_array($plan, ['BASIQUE','PREMIUM','ECOLE'])) {
    redirect('/reussiteplus/tarifs.php', 'warning', 'La bibliothèque d\'examens est accessible à partir du plan Basique.');
}
// Capacités par plan dans la bibliothèque
$examCanSeeAll  = in_array($plan, ['PREMIUM','ECOLE']);  // Basique voit seulement les matières standard
$examCanUseIA   = (bool)(PLANS[$plan]['ia'] ?? false);
$examIsEcole    = ($plan === 'ECOLE');

// ── Filtres ───────────────────────────────────────────────────
$filtreCateg  = $_GET['cat']   ?? '';
$filtreDiff   = $_GET['diff']  ?? '';
$filtreSearch = trim($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['p'] ?? 1));
$limit        = 20;

// ── Matières avec stats ───────────────────────────────────────
// Basique : seulement matières de base (8 premières)
$matiereWhere = $examCanSeeAll ? 'WHERE m.actif=1' : "WHERE m.actif=1 AND m.code IN ('maths','francais','sciences','histgeo','chimie','physique','biologie','anglais')";

$matieres = dbAll(
    "SELECT m.id, m.code, m.nom, m.nom_court, m.couleur, m.ordre,
            COUNT(DISTINCT qb.id) as nb_questions,
            COALESCE(ROUND(AVG(es.pourcentage),1),0) as score_moyen_ecole
     FROM matieres m
     LEFT JOIN question_bank qb ON qb.matiere_id=m.id AND qb.status='PUBLIE' AND qb.type_question='QCM'
       " . (!$examCanSeeAll ? "AND qb.premium_only=0" : "") . "
     LEFT JOIN exam_sessions es ON es.matiere_id=m.id AND es.statut='TERMINE'
     $matiereWhere
     GROUP BY m.id ORDER BY m.ordre ASC, m.nom ASC"
) ?? [];

// Catégories
$categories = [
    'exactes'    => ['label'=>'Sciences exactes',     'codes'=>['maths','physique','chimie','biologie','svt','sciences']],
    'langues'    => ['label'=>'Langues',               'codes'=>['francais','anglais','espagnol','allemand','latin','litterature']],
    'humaines'   => ['label'=>'Sciences humaines',    'codes'=>['histoire','geo','histgeo','philo','sociologie','psyco']],
    'citoyennete'=> ['label'=>'Citoyenneté',           'codes'=>['edcivique','edvie','religion']],
    'techno'     => ['label'=>'Technologie',           'codes'=>['info','progr','culture_num','techno','dessin']],
    'economie'   => ['label'=>'Sciences économiques',  'codes'=>['ecopol','gestion','compta','droit','socaf']],
    'sante'      => ['label'=>'Santé & Sciences appli.',  'codes'=>['sante','nutrition']],
];

$matieresByCode = array_column($matieres, null, 'code');

// ── Questions disponibles avec filtres ────────────────────────
$where  = "qb.status='PUBLIE' AND qb.type_question='QCM'";
$params = [];

if ($filtreSearch) { $where .= " AND (qb.enonce LIKE ? OR m.nom LIKE ?)"; $params[] = "%$filtreSearch%"; $params[] = "%$filtreSearch%"; }
if ($filtreDiff)   { $where .= " AND qb.difficulte=?";  $params[] = $filtreDiff; }
if ($filtreCateg && isset($categories[$filtreCateg])) {
    $codes = $categories[$filtreCateg]['codes'];
    $in    = implode(',', array_fill(0, count($codes), '?'));
    $where .= " AND m.code IN ($in)";
    $params = array_merge($params, $codes);
}

$totalQ  = (int)(dbScalar("SELECT COUNT(*) FROM question_bank qb LEFT JOIN matieres m ON m.id=qb.matiere_id WHERE $where", $params) ?? 0);
$totalPages = max(1, ceil($totalQ / $limit));
$offset     = ($page - 1) * $limit;

// Stats globales
$totalQuestions = (int)(dbScalar("SELECT COUNT(*) FROM question_bank WHERE status='PUBLIE' AND type_question='QCM'") ?? 0);
$totalMatieres  = count(array_filter($matieres, fn($m) => $m['nb_questions'] > 0));
$totalSessions  = (int)(dbScalar("SELECT COUNT(*) FROM exam_sessions WHERE statut='TERMINE'") ?? 0);

// Historique examens de l'utilisateur
$historiqueUser = dbAll(
    "SELECT es.*, m.nom as matiere_nom, m.couleur
     FROM exam_sessions es
     LEFT JOIN matieres m ON m.id=es.matiere_id
     WHERE es.user_id=? AND es.statut='TERMINE'
     ORDER BY es.finished_at DESC LIMIT 5",
    [$user['id']]
) ?? [];

include __DIR__ . '/includes/header_app.php';
?>

<style>
/* ── Bibliothèque examens ──────────────────────────────────── */
.exam-lib-hero {
  background: linear-gradient(135deg, #1e3a5f 0%, #0a1628 100%);
  border-radius: 18px; padding: 26px 28px; margin-bottom: 22px;
  position: relative; overflow: hidden;
}
.exam-lib-hero::before {
  content:'';position:absolute;top:-40px;right:-40px;width:200px;height:200px;
  border-radius:50%;background:radial-gradient(circle,rgba(30,95,173,.18) 0%,transparent 70%);
}
.exam-layout { display:grid;grid-template-columns:220px 1fr;gap:20px; }
@media(max-width:900px){ .exam-layout{grid-template-columns:1fr} }

/* Sidebar filtres */
.exam-sidebar { background:var(--blanc);border:1px solid var(--gris-200);border-radius:14px;overflow:hidden;height:fit-content;position:sticky;top:76px; }
.exam-sidebar-hd { padding:13px 16px;border-bottom:1px solid var(--gris-100);font-size:12px;font-weight:700;color:var(--gris-700);text-transform:uppercase;letter-spacing:.5px; }
.exam-sidebar-body { padding:10px 8px; }
.exam-filter-item {
  display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:8px;
  cursor:pointer;text-decoration:none;transition:.15s;font-size:13px;color:var(--gris-700);
}
.exam-filter-item:hover { background:var(--gris-50);color:var(--gris-900); }
.exam-filter-item.active { background:var(--primary-subtle);color:var(--primary-dark);font-weight:600; }
.exam-filter-dot { width:8px;height:8px;border-radius:50%;flex-shrink:0; }
.exam-filter-count { margin-left:auto;font-size:11px;background:var(--gris-100);color:var(--gris-500);padding:1px 7px;border-radius:20px; }

/* Cards matières */
.matiere-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px;margin-bottom:22px; }
.matiere-card {
  background:var(--blanc);border:1px solid var(--gris-200);border-radius:13px;
  padding:16px;text-decoration:none;color:inherit;transition:all .2s;
  display:flex;flex-direction:column;gap:8px;position:relative;overflow:hidden;
}
.matiere-card:hover { box-shadow:0 6px 20px rgba(0,0,0,.09);transform:translateY(-2px);border-color:transparent; }
.matiere-card::before { content:'';position:absolute;top:0;left:0;right:0;height:2px;border-radius:13px 13px 0 0; }
.matiere-card-icon { width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
.matiere-card-name { font-size:13.5px;font-weight:700;color:var(--gris-900);line-height:1.2; }
.matiere-card-stats { display:flex;gap:8px;flex-wrap:wrap; }
.matiere-stat-chip { font-size:10.5px;color:var(--gris-500);background:var(--gris-50);padding:2px 8px;border-radius:20px;border:1px solid var(--gris-200); }
.matiere-card-actions { display:flex;gap:6px;margin-top:auto;padding-top:8px;border-top:1px solid var(--gris-100); }
.matiere-action {
  flex:1;font-size:11.5px;font-weight:700;padding:6px 8px;border-radius:7px;
  border:none;cursor:pointer;font-family:inherit;text-align:center;text-decoration:none;
  display:flex;align-items:center;justify-content:center;gap:4px;transition:.15s;
}
.matiere-action.primary { background:var(--primary);color:#fff; }
.matiere-action.primary:hover { background:var(--primary-dark); }
.matiere-action.ghost { background:var(--gris-100);color:var(--gris-700); }
.matiere-action.ghost:hover { background:var(--gris-200); }

/* Diff badges */
.diff-badge { font-size:10px;font-weight:700;padding:2px 8px;border-radius:4px;text-transform:uppercase;letter-spacing:.4px; }
.diff-DEBUTANT     { background:#D1FAE5;color:#065F46; }
.diff-ELEMENTAIRE  { background:#DBEAFE;color:#1e40af; }
.diff-INTERMEDIAIRE{ background:#FEF3C7;color:#92400E; }
.diff-AVANCE       { background:#FEE2E2;color:#7F1D1D; }
.diff-EXPERT       { background:#EDE9FE;color:#5B21B6; }

/* Section header */
.cat-section-title {
  font-size:11.5px;font-weight:800;color:var(--gris-500);text-transform:uppercase;
  letter-spacing:.8px;padding:6px 10px;margin-bottom:8px;
  border-left:3px solid var(--gris-300);
}

/* Difficulté selector */
.diff-selector { display:flex;gap:7px;flex-wrap:wrap;margin-bottom:16px; }
.diff-btn {
  padding:6px 14px;border-radius:20px;font-size:12px;font-weight:600;
  border:1.5px solid var(--gris-200);background:var(--blanc);color:var(--gris-600);
  cursor:pointer;text-decoration:none;transition:.15s;
}
.diff-btn:hover,.diff-btn.active { border-color:var(--primary);background:var(--primary-subtle);color:var(--primary-dark); }

/* Historique */
.hist-row { display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--gris-100); }
.hist-row:last-child { border:none; }

/* Generateur IA */
.ia-gen-panel { background:linear-gradient(160deg,#0d1120,#111827);border:1px solid rgba(124,58,237,.2);border-radius:14px;padding:20px;margin-bottom:20px; }

/* Empty state */
.empty-state { text-align:center;padding:48px 24px; }
.empty-icon { width:56px;height:56px;border-radius:14px;background:var(--gris-100);margin:0 auto 16px;display:flex;align-items:center;justify-content:center; }
</style>

<!-- Hero -->
<div class="exam-lib-hero">
  <div style="position:relative;z-index:1;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px">
    <div>
      <div style="font-size:11px;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.6px;margin-bottom:5px">
        <?= ['BASIQUE'=>'Plan Basique','PREMIUM'=>'Plan Premium','ECOLE'=>'École Premium'][$plan] ?? '' ?>
      </div>
      <div style="font-size:22px;font-weight:900;color:#fff;margin-bottom:4px">Bibliothèque d'examens</div>
      <div style="font-size:13px;color:rgba(255,255,255,.5)"><?= $totalQuestions ?> questions · <?= $totalMatieres ?> matières · <?= $totalSessions ?> examens passés</div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <?php foreach (['DEBUTANT'=>'#6EE7B7','INTERMEDIAIRE'=>'#FCD34D','AVANCE'=>'#FCA5A5','EXPERT'=>'#C4B5FD'] as $d=>$c): ?>
      <div style="text-align:center;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);border-radius:10px;padding:8px 14px">
        <div style="font-size:16px;font-weight:900;color:<?= $c ?>"><?= dbScalar("SELECT COUNT(*) FROM question_bank WHERE status='PUBLIE' AND difficulte=?", [$d]) ?></div>
        <div style="font-size:9px;color:rgba(255,255,255,.4);text-transform:uppercase"><?= $d ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Bannière accès plan -->
<?php if ($plan === 'BASIQUE'): ?>
<div style="background:#EEF4FD;border:1px solid rgba(30,95,173,.2);border-radius:12px;padding:13px 18px;margin-bottom:18px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
  <div style="display:flex;align-items:center;gap:9px">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#1E5FAD" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <span style="font-size:13px;color:#1E3A5F"><strong>Plan Basique</strong> — Accès aux 8 matières principales · Questions standard uniquement · Passez à Premium pour toutes les matières et questions avancées.</span>
  </div>
  <a href="/reussiteplus/tarifs.php" style="font-size:12px;font-weight:700;color:#C9972A;background:#FEF3C7;border:1px solid rgba(201,151,42,.25);border-radius:8px;padding:6px 13px;text-decoration:none;white-space:nowrap">Passer à Premium</a>
</div>
<?php elseif ($plan === 'PREMIUM'): ?>
<div style="background:#F5F3FF;border:1px solid rgba(124,58,237,.15);border-radius:12px;padding:13px 18px;margin-bottom:18px;display:flex;align-items:center;gap:9px">
  <svg width="14" height="14" viewBox="0 0 24 24" fill="#7C3AED" stroke="none"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
  <span style="font-size:13px;color:#4B1D9B"><strong>Plan Premium</strong> — Toutes les matières · Toutes les questions · Analyse IA incluse.</span>
</div>
<?php endif; ?>

<!-- Générateur IA (admin) -->
<?php if (is_admin()): ?>
<div class="ia-gen-panel">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
    <div style="font-size:13px;font-weight:800;color:#C4B5FD;display:flex;align-items:center;gap:8px">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
      Générateur IA de questions — Admin
    </div>
    <span style="font-size:11px;color:rgba(255,255,255,.35)">Gemini 2.0-flash · EPST RDC</span>
  </div>
  <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <div>
      <label style="font-size:10.5px;color:rgba(255,255,255,.45);display:block;margin-bottom:5px">Matière</label>
      <select id="genMatiere" style="background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);color:#fff;border-radius:8px;padding:7px 12px;font-size:12px;font-family:inherit">
        <?php foreach ($matieres as $m): ?>
        <option value="<?= e($m['id']) ?>" style="background:#111827;color:#fff"><?= e($m['nom']) ?> (<?= $m['nb_questions'] ?> Q)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label style="font-size:10.5px;color:rgba(255,255,255,.45);display:block;margin-bottom:5px">Niveau</label>
      <select id="genDiff" style="background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);color:#fff;border-radius:8px;padding:7px 12px;font-size:12px;font-family:inherit">
        <?php foreach (['DEBUTANT','ELEMENTAIRE','INTERMEDIAIRE','AVANCE','EXPERT'] as $d): ?>
        <option value="<?= $d ?>" <?= $d==='INTERMEDIAIRE'?'selected':'' ?> style="background:#111827"><?= $d ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label style="font-size:10.5px;color:rgba(255,255,255,.45);display:block;margin-bottom:5px">Nombre</label>
      <input type="number" id="genNb" value="10" min="3" max="20" style="background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);color:#fff;border-radius:8px;padding:7px 12px;font-size:12px;width:70px">
    </div>
    <button onclick="genererQuestions()" id="genBtn" style="background:linear-gradient(135deg,#7C3AED,#4F46E5);color:#fff;border:none;border-radius:8px;padding:8px 18px;font-size:12px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px;font-family:inherit">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
      Générer
    </button>
    <div id="genStatus" style="font-size:12px;color:rgba(255,255,255,.55);padding:8px 0"></div>
  </div>
  <!-- Progress multi-matières -->
  <div style="margin-top:14px;padding-top:14px;border-top:1px solid rgba(255,255,255,.07)">
    <div style="font-size:10.5px;color:rgba(255,255,255,.4);margin-bottom:8px">Progression banque de questions</div>
    <div style="display:flex;gap:6px;flex-wrap:wrap">
      <?php foreach (array_slice($matieres, 0, 16) as $m): ?>
      <div style="display:flex;align-items:center;gap:4px;background:rgba(255,255,255,.06);border-radius:6px;padding:3px 8px">
        <div style="width:6px;height:6px;border-radius:50%;background:<?= $m['nb_questions']>=20?'#4ade80':($m['nb_questions']>=5?'#fbbf24':'#f87171') ?>"></div>
        <span style="font-size:10.5px;color:rgba(255,255,255,.55)"><?= e($m['nom_court'] ?? $m['nom']) ?></span>
        <span style="font-size:10.5px;color:rgba(255,255,255,.35)"><?= $m['nb_questions'] ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Filtres rapides -->
<div style="display:flex;gap:10px;margin-bottom:18px;flex-wrap:wrap;align-items:center">
  <form method="GET" style="flex:1;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <div style="position:relative;flex:1;max-width:320px">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--gris-400)" stroke-width="2.5" stroke-linecap="round" style="position:absolute;left:11px;top:50%;transform:translateY(-50%)"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" name="q" value="<?= e($filtreSearch) ?>" class="form-control" placeholder="Rechercher une matière…" style="padding-left:32px">
    </div>
    <input type="hidden" name="cat" value="<?= e($filtreCateg) ?>">
    <?php if ($filtreDiff): ?><input type="hidden" name="diff" value="<?= e($filtreDiff) ?>"><?php endif; ?>
    <button type="submit" class="btn btn-primary btn-sm">Rechercher</button>
    <?php if ($filtreSearch || $filtreCateg || $filtreDiff): ?>
    <a href="/reussiteplus/ecole_examens.php" class="btn btn-ghost btn-sm">Effacer</a>
    <?php endif; ?>
  </form>
</div>

<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
  <span style="font-size:12px;font-weight:600;color:var(--gris-500);align-self:center">Niveau :</span>
  <?php foreach ([''=> 'Tous', 'DEBUTANT'=>'Débutant','ELEMENTAIRE'=>'Élémentaire','INTERMEDIAIRE'=>'Intermédiaire','AVANCE'=>'Avancé','EXPERT'=>'Expert'] as $val=>$lab): ?>
  <a href="?<?= http_build_query(array_filter(['cat'=>$filtreCateg,'diff'=>$val,'q'=>$filtreSearch])) ?>" class="diff-btn <?= $filtreDiff===$val?'active':'' ?>"><?= $lab ?></a>
  <?php endforeach; ?>
</div>

<div class="exam-layout">
  <!-- Sidebar catégories -->
  <div class="exam-sidebar">
    <div class="exam-sidebar-hd">Catégories</div>
    <div class="exam-sidebar-body">
      <a href="/reussiteplus/ecole_examens.php" class="exam-filter-item <?= !$filtreCateg?'active':'' ?>">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        Toutes les matières
        <span class="exam-filter-count"><?= count($matieres) ?></span>
      </a>
      <?php foreach ($categories as $key => $cat): ?>
      <?php $nb = count(array_filter($cat['codes'], fn($c) => isset($matieresByCode[$c]))); ?>
      <?php if ($nb === 0) continue; ?>
      <a href="?cat=<?= $key ?><?= $filtreDiff?"&diff=$filtreDiff":'' ?>" class="exam-filter-item <?= $filtreCateg===$key?'active':'' ?>">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
        <?= e($cat['label']) ?>
        <span class="exam-filter-count"><?= $nb ?></span>
      </a>
      <?php endforeach; ?>
    </div>

    <?php if ($historiqueUser): ?>
    <div style="border-top:1px solid var(--gris-100);padding:12px 16px">
      <div style="font-size:11px;font-weight:700;color:var(--gris-500);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px">Derniers examens</div>
      <?php foreach ($historiqueUser as $h): ?>
      <div class="hist-row">
        <div style="width:8px;height:8px;border-radius:50%;flex-shrink:0;background:<?= $h['couleur']??'#007A5E' ?>"></div>
        <div style="flex:1;min-width:0">
          <div style="font-size:12px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--gris-800)"><?= e($h['matiere_nom'] ?? '—') ?></div>
        </div>
        <div style="font-size:11.5px;font-weight:700;color:<?= score_couleur((float)$h['pourcentage']) ?>"><?= round((float)$h['pourcentage']) ?>%</div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Contenu principal -->
  <div>
    <?php
    $catafiltree = $filtreCateg && isset($categories[$filtreCateg]) ? [$filtreCateg => $categories[$filtreCateg]] : $categories;
    $anyMat = false;
    foreach ($catafiltree as $catKey => $cat):
        $matsDeLaCat = array_filter($matieres, function($m) use ($cat, $filtreSearch) {
            if ($filtreSearch && stripos($m['nom'], $filtreSearch) === false) return false;
            return in_array($m['code'], $cat['codes']);
        });
        if (empty($matsDeLaCat)) continue;
        $anyMat = true;
    ?>
    <div style="margin-bottom:22px">
      <div class="cat-section-title"><?= e($cat['label']) ?></div>
      <div class="matiere-grid">
      <?php foreach ($matsDeLaCat as $m):
          $nbQ = (int)$m['nb_questions'];
          if ($filtreDiff) {
              $nbQ = (int)(dbScalar("SELECT COUNT(*) FROM question_bank WHERE matiere_id=? AND status='PUBLIE' AND type_question='QCM' AND difficulte=?", [$m['id'], $filtreDiff]) ?? 0);
          }
          $score = (float)$m['score_moyen_ecole'];
          $colorBg = $m['couleur'] . '14';
      ?>
      <div class="matiere-card" style="--mat-color:<?= $m['couleur'] ?>">
        <style>.matiere-card:hover { border-color: <?= $m['couleur'] ?>; }</style>
        <div style="position:absolute;top:0;left:0;right:0;height:2px;background:<?= $m['couleur'] ?>;border-radius:13px 13px 0 0"></div>
        <div style="display:flex;align-items:center;gap:9px">
          <div class="matiere-card-icon" style="background:<?= $colorBg ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="<?= $m['couleur'] ?>" stroke-width="2.5" stroke-linecap="round">
              <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
              <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
            </svg>
          </div>
          <div class="matiere-card-name"><?= e($m['nom']) ?></div>
        </div>
        <div class="matiere-card-stats">
          <span class="matiere-stat-chip">
            <strong><?= $nbQ ?></strong> question<?= $nbQ!=1?'s':'' ?>
          </span>
          <?php if ($score > 0): ?>
          <span class="matiere-stat-chip" style="color:<?= score_couleur($score) ?>;background:<?= score_couleur($score) ?>12"><?= $score ?>% moy.</span>
          <?php endif; ?>
        </div>
        <?php if ($filtreDiff): ?>
        <div><span class="diff-badge diff-<?= $filtreDiff ?>"><?= $filtreDiff ?></span></div>
        <?php endif; ?>
        <div class="matiere-card-actions">
          <?php if ($nbQ >= 5): ?>
          <a href="/reussiteplus/examen.php?matiere=<?= urlencode($m['id']) ?><?= $filtreDiff?"&diff=$filtreDiff":'' ?>"
             class="matiere-action primary">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="5 12 12 5 19 12"/></svg>
            Commencer
          </a>
          <?php else: ?>
          <span class="matiere-action ghost" style="cursor:default;opacity:.5">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <?= $nbQ ?>/5 min.
          </span>
          <?php endif; ?>
          <a href="/reussiteplus/progression.php" class="matiere-action ghost">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/></svg>
            Stats
          </a>
        </div>
      </div>
      <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (!$anyMat): ?>
    <div class="empty-state card">
      <div class="empty-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--gris-400)" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></div>
      <div style="font-size:15px;font-weight:700;margin-bottom:6px">Aucune matière trouvée</div>
      <p style="color:var(--gris-500);font-size:13px;max-width:300px;margin:0 auto">Essayez d'autres filtres ou <a href="/reussiteplus/ecole_examens.php" style="color:var(--primary)">réinitialisez la recherche</a>.</p>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if (is_admin()): ?>
<script>
async function genererQuestions() {
  const btn    = document.getElementById('genBtn');
  const status = document.getElementById('genStatus');
  const mat    = document.getElementById('genMatiere').value;
  const diff   = document.getElementById('genDiff').value;
  const nb     = parseInt(document.getElementById('genNb').value) || 5;

  btn.disabled = true;
  status.textContent = 'Génération en cours… (15-30s)';
  status.style.color = '#FCD34D';

  try {
    const resp = await fetch('/reussiteplus/api/seed_questions_ia.php', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ matiere_id: mat, difficulte: diff, nb }),
    });
    const data = await resp.json();
    if (data.ok) {
      status.textContent = `Succès : ${data.inserted} questions ajoutées pour ${data.matiere} — Total : ${data.total_matiere}`;
      status.style.color = '#6EE7B7';
      setTimeout(() => location.reload(), 2000);
    } else {
      status.textContent = 'Erreur : ' + (data.msg || 'Inconnue');
      status.style.color = '#FCA5A5';
    }
  } catch(e) {
    status.textContent = 'Erreur réseau : ' + e.message;
    status.style.color = '#FCA5A5';
  }
  btn.disabled = false;
}
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
