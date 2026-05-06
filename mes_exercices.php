<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Mes Exercices';
$pageActive = 'mes_exercices';
$user = require_login();

// Mes classes actives
$mesClasses = dbAll(
    "SELECT c.id, c.nom FROM classe_membres cm JOIN classes_ecole c ON c.id=cm.classe_id
     WHERE cm.eleve_id=? AND cm.statut='ACTIF' ORDER BY c.nom",
    [$user['id']]
) ?? [];

$classeIds = array_column($mesClasses, 'id');

// Filtres
$filtreClasse = $_GET['classe'] ?? '';
$filtreType   = $_GET['type']   ?? '';
$filtreStatut = $_GET['statut'] ?? '';
$q            = trim($_GET['q'] ?? '');

// Exercices disponibles
$exercices = [];
if ($classeIds) {
    $inPlaceholders = implode(',', array_fill(0, count($classeIds), '?'));
    $params = [$user['id']]; $extra = '';

    if ($filtreClasse) { $extra .= ' AND (e.classe_id=?)';  $params[] = $filtreClasse; }
    if ($filtreType)   { $extra .= ' AND e.type=?';         $params[] = $filtreType; }
    if ($q)            { $extra .= ' AND e.titre LIKE ?';   $params[] = "%$q%"; }
    $params = array_merge([$user['id']], $classeIds, [$user['id']], ($filtreClasse ? [$filtreClasse] : []), ($filtreType ? [$filtreType] : []), ($q ? ["%$q%"] : []));

    $exercices = dbAll(
        "SELECT e.*,
                (SELECT COUNT(*) FROM questions_exercice WHERE exercice_id=e.id) as nb_questions,
                (SELECT s.score FROM sessions_exercice s WHERE s.exercice_id=e.id AND s.eleve_id=? AND s.statut='TERMINE' ORDER BY s.termine_le DESC LIMIT 1) as mon_score,
                (SELECT s.id FROM sessions_exercice s WHERE s.exercice_id=e.id AND s.eleve_id=? AND s.statut='EN_COURS' LIMIT 1) as session_en_cours,
                c.nom as classe_nom
         FROM exercices_ecole e
         LEFT JOIN classes_ecole c ON c.id=e.classe_id
         WHERE e.actif=1
           AND (e.classe_id IN ($inPlaceholders) OR e.classe_id IS NULL)
           AND (SELECT COUNT(*) FROM questions_exercice WHERE exercice_id=e.id) > 0
         ORDER BY e.created_at DESC",
        array_merge([$user['id'], $user['id']], $classeIds)
    ) ?? [];
}

// Appliquer filtres PHP
if ($filtreClasse) $exercices = array_filter($exercices, fn($e) => $e['classe_id'] == $filtreClasse);
if ($filtreType)   $exercices = array_filter($exercices, fn($e) => $e['type'] == $filtreType);
if ($q)            $exercices = array_filter($exercices, fn($e) => stripos($e['titre'], $q) !== false);
if ($filtreStatut === 'fait')      $exercices = array_filter($exercices, fn($e) => $e['mon_score'] !== null);
if ($filtreStatut === 'a_faire')   $exercices = array_filter($exercices, fn($e) => $e['mon_score'] === null);
$exercices = array_values($exercices);

$total    = count($exercices);
$faits    = count(array_filter($exercices, fn($e) => $e['mon_score'] !== null));
$aFaire   = $total - $faits;
$scores   = array_filter(array_column($exercices, 'mon_score'), fn($s) => $s !== null);
$scoreMoy = $scores ? round(array_sum($scores) / count($scores), 1) : 0;

include __DIR__ . '/includes/header_app.php';
?>

<style>
.exo-card { background:var(--blanc); border:1.5px solid var(--gris-200); border-radius:var(--radius-lg); overflow:hidden; transition:all .2s; }
.exo-card:hover { box-shadow:0 6px 24px rgba(0,0,0,.09); transform:translateY(-2px); }
.exo-top-bar { height:4px; width:100%; }
.score-ring { position:relative; display:inline-flex; align-items:center; justify-content:center; }
</style>

<!-- Hero -->
<div style="background:linear-gradient(135deg,#0f172a,#3b0764 55%,#0f172a);border-radius:var(--radius-xl);padding:26px;margin-bottom:20px">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px">
    <div>
      <div style="font-size:11px;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Mes Cours</div>
      <div style="font-family:var(--font-display);font-size:20px;font-weight:900;color:#fff;display:flex;align-items:center;gap:10px">
        <i data-lucide="brain" style="width:22px;height:22px;stroke:#a78bfa"></i>
        Mes Exercices
      </div>
      <div style="font-size:12px;color:rgba(255,255,255,.45);margin-top:3px">Entra&icirc;nez-vous avec les exercices publi&eacute;s par vos enseignants</div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <?php foreach ([[$total,'Total','#fff'],[$faits,'Termin&eacute;s','#34d399'],[$aFaire,'&Agrave; faire','#fbbf24']] as [$v,$l,$c]): ?>
      <div style="text-align:center;padding:8px 14px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:12px">
        <div style="font-family:var(--font-display);font-size:20px;font-weight:900;color:<?= $c ?>"><?= $v ?></div>
        <div style="font-size:9px;color:rgba(255,255,255,.4);text-transform:uppercase"><?= $l ?></div>
      </div>
      <?php endforeach; ?>
      <?php if ($scoreMoy > 0): ?>
      <div style="text-align:center;padding:8px 14px;background:rgba(167,139,250,.15);border:1px solid rgba(167,139,250,.3);border-radius:12px">
        <div style="font-family:var(--font-display);font-size:20px;font-weight:900;color:#a78bfa"><?= number_format($scoreMoy, 1) ?></div>
        <div style="font-size:9px;color:rgba(255,255,255,.4);text-transform:uppercase">Moy. score</div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if (!$mesClasses): ?>
<div class="card" style="text-align:center;padding:60px 30px">
  <div style="display:flex;justify-content:center;margin-bottom:16px">
    <i data-lucide="school" style="width:48px;height:48px;stroke:var(--gris-300);stroke-width:1.5"></i>
  </div>
  <div style="font-family:var(--font-display);font-size:20px;font-weight:800;margin-bottom:8px">Vous n&apos;&ecirc;tes dans aucune classe</div>
  <p style="color:var(--gris-500);font-size:13px;max-width:380px;margin:0 auto 24px">Rejoignez une classe pour acc&eacute;der aux exercices.</p>
  <a href="/reussiteplus/rejoindre.php" class="btn btn-primary" style="background:#7C3AED;border-color:#7C3AED">Rejoindre une classe</a>
</div>
<?php else: ?>

<!-- Filtres -->
<div style="background:var(--blanc);border:1.5px solid var(--gris-200);border-radius:var(--radius-lg);padding:16px 20px;margin-bottom:20px">
  <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <div style="position:relative;flex:1;min-width:180px">
      <i data-lucide="search" style="width:14px;height:14px;position:absolute;left:12px;top:50%;transform:translateY(-50%);stroke:var(--gris-400)"></i>
      <input type="text" name="q" value="<?= e($q) ?>" placeholder="Rechercher un exercice&hellip;" class="form-control" style="padding-left:36px;margin-bottom:0">
    </div>
    <select name="type" class="form-control" style="width:130px;margin-bottom:0">
      <option value="">Tous types</option>
      <option value="QCM" <?= $filtreType==='QCM'?'selected':'' ?>>QCM</option>
      <option value="VRAI_FAUX" <?= $filtreType==='VRAI_FAUX'?'selected':'' ?>>Vrai / Faux</option>
      <option value="MIXTE" <?= $filtreType==='MIXTE'?'selected':'' ?>>Mixte</option>
    </select>
    <select name="classe" class="form-control" style="width:150px;margin-bottom:0">
      <option value="">Toutes classes</option>
      <?php foreach ($mesClasses as $cl): ?>
      <option value="<?= e($cl['id']) ?>" <?= $filtreClasse===$cl['id']?'selected':'' ?>><?= e($cl['nom']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="statut" class="form-control" style="width:140px;margin-bottom:0">
      <option value="">Tous statuts</option>
      <option value="a_faire" <?= $filtreStatut==='a_faire'?'selected':'' ?>>&Agrave; faire</option>
      <option value="fait" <?= $filtreStatut==='fait'?'selected':'' ?>>Termin&eacute;s</option>
    </select>
    <button type="submit" class="btn btn-primary" style="background:#7C3AED;border-color:#7C3AED">
      <i data-lucide="filter" style="width:13px;height:13px;stroke:#fff;vertical-align:-2px"></i> Filtrer
    </button>
    <?php if ($q || $filtreType || $filtreClasse || $filtreStatut): ?>
    <a href="/reussiteplus/mes_exercices.php" class="btn btn-ghost">Effacer</a>
    <?php endif; ?>
  </form>
</div>

<?php if ($exercices): ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:16px">
<?php foreach ($exercices as $exo):
  $typeColors = ['QCM'=>['#1E5FAD','#DBEAFE'],'VRAI_FAUX'=>['#059669','#D1FAE5'],'MIXTE'=>['#7C3AED','#EDE9FE']];
  $typeIcons  = ['QCM'=>'list','VRAI_FAUX'=>'check-square','MIXTE'=>'layers'];
  [$tColor,$tBg] = $typeColors[$exo['type']??'QCM'] ?? $typeColors['QCM'];
  $tIcon = $typeIcons[$exo['type']??'QCM'] ?? 'help-circle';
  $monScore = $exo['mon_score'];
  $noteMax  = (float)($exo['note_max'] ?? 10);
  $pct = ($monScore !== null && $noteMax > 0) ? round($monScore / $noteMax * 100) : null;
  $scoreColor = $pct !== null ? ($pct>=70?'#059669':($pct>=50?'#B45309':'#DC2626')) : null;
  $fait = $pct !== null;
?>
<div class="exo-card">
  <div class="exo-top-bar" style="background:<?= $tColor ?>"></div>
  <div style="padding:16px">
    <!-- Header -->
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:12px">
      <div style="flex:1">
        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:6px">
          <span style="background:<?= $tBg ?>;color:<?= $tColor ?>;font-size:10px;font-weight:800;padding:2px 8px;border-radius:6px;display:inline-flex;align-items:center;gap:4px">
            <i data-lucide="<?= $tIcon ?>" style="width:10px;height:10px"></i> <?= e($exo['type']) ?>
          </span>
          <?php if ($exo['classe_nom']): ?>
          <span style="background:var(--gris-100);color:var(--gris-600);font-size:10px;font-weight:700;padding:2px 8px;border-radius:6px"><?= e($exo['classe_nom']) ?></span>
          <?php endif; ?>
          <?php if ($fait): ?>
          <span style="background:#D1FAE5;color:#065F46;font-size:10px;font-weight:800;padding:2px 8px;border-radius:6px;display:inline-flex;align-items:center;gap:3px">
            <i data-lucide="check" style="width:9px;height:9px"></i> Fait
          </span>
          <?php endif; ?>
        </div>
        <div style="font-family:var(--font-display);font-size:15px;font-weight:900;color:var(--gris-900);margin-bottom:4px;line-height:1.2"><?= e($exo['titre']) ?></div>
        <?php if ($exo['description']): ?>
        <div style="font-size:11px;color:var(--gris-500);line-height:1.4;margin-bottom:8px"><?= e(mb_strimwidth($exo['description'], 0, 70, '&hellip;')) ?></div>
        <?php endif; ?>
        <div style="display:flex;gap:12px;font-size:11px;color:var(--gris-400)">
          <span style="display:inline-flex;align-items:center;gap:4px">
            <i data-lucide="help-circle" style="width:11px;height:11px"></i> <?= $exo['nb_questions'] ?> questions
          </span>
          <span style="display:inline-flex;align-items:center;gap:4px">
            <i data-lucide="clock" style="width:11px;height:11px"></i> <?= $exo['duree_minutes'] ?> min
          </span>
          <span style="display:inline-flex;align-items:center;gap:4px">
            <i data-lucide="star" style="width:11px;height:11px"></i> <?= $exo['note_max'] ?> pts
          </span>
        </div>
      </div>
      <?php if ($pct !== null): ?>
      <div style="text-align:center;background:<?= $tBg ?>;border-radius:14px;padding:10px 14px;flex-shrink:0;min-width:64px">
        <div style="font-family:var(--font-display);font-size:22px;font-weight:900;color:<?= $scoreColor ?>"><?= $pct ?>%</div>
        <div style="font-size:9px;color:var(--gris-500);text-transform:uppercase;margin-top:2px">Score</div>
        <div style="font-size:10px;color:var(--gris-600);font-weight:700"><?= number_format($monScore,1) ?>/<?= $noteMax ?></div>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($pct !== null): ?>
    <div style="height:5px;background:var(--gris-200);border-radius:3px;overflow:hidden;margin-bottom:12px">
      <div style="width:<?= $pct ?>%;height:100%;background:<?= $scoreColor ?>;border-radius:3px;transition:width 1s ease"></div>
    </div>
    <?php endif; ?>

    <a href="/reussiteplus/passer_exercice.php?id=<?= urlencode($exo['id']) ?>"
       style="display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:11px;background:<?= $tColor ?>;color:#fff;border-radius:10px;font-size:13px;font-weight:800;text-decoration:none;transition:opacity .15s"
       onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
      <i data-lucide="<?= $fait ? 'refresh-cw' : 'play' ?>" style="width:14px;height:14px;stroke:#fff"></i>
      <?= $fait ? 'Recommencer' : 'Commencer l\'exercice' ?>
    </a>
  </div>
</div>
<?php endforeach; ?>
</div>

<?php else: ?>
<div class="card" style="text-align:center;padding:60px 30px">
  <div style="display:flex;justify-content:center;margin-bottom:16px">
    <i data-lucide="brain" style="width:56px;height:56px;stroke:var(--gris-300);stroke-width:1.5"></i>
  </div>
  <div style="font-family:var(--font-display);font-size:20px;font-weight:800;margin-bottom:8px">Aucun exercice trouv&eacute;</div>
  <p style="color:var(--gris-500);font-size:13px;max-width:380px;margin:0 auto 24px">
    <?= ($q || $filtreType || $filtreStatut || $filtreClasse) ? 'Aucun exercice ne correspond &agrave; vos filtres.' : 'Vos enseignants n&apos;ont pas encore publi&eacute; d&apos;exercices.' ?>
  </p>
  <?php if ($q || $filtreType || $filtreStatut || $filtreClasse): ?>
  <a href="/reussiteplus/mes_exercices.php" class="btn btn-ghost">Effacer les filtres</a>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
