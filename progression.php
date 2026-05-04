<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Ma Progression';
$pageActive = 'progression';
$user = require_login();

// Stats globales
$stats = get_user_stats($user['id']);

// Progression par matière (complète)
$progressMatieres = dbAll(
    "SELECT up.*, m.nom, m.couleur, m.icone, m.code
     FROM user_progression up
     JOIN matieres m ON up.matiere_id = m.id
     WHERE up.user_id = ?
     ORDER BY up.score_moyen DESC",
    [$user['id']]
);

// Historique des 20 dernières sessions
$historique = dbAll(
    "SELECT es.*, m.nom as matiere_nom, m.couleur
     FROM exam_sessions es
     LEFT JOIN matieres m ON es.matiere_id = m.id
     WHERE es.user_id = ? AND es.statut = 'TERMINE'
     ORDER BY es.finished_at DESC LIMIT 20",
    [$user['id']]
);

// Activité des 30 derniers jours
$activite30j = dbAll(
    "SELECT date_act, examens, questions
     FROM activite_journaliere
     WHERE user_id = ? AND date_act >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
     ORDER BY date_act ASC",
    [$user['id']]
);

// Construire la map activité pour les 30 jours
$actMap = [];
foreach ($activite30j as $a) $actMap[$a['date_act']] = $a;

// Classement provincial
$classement = null;
if ($user['province_id']) {
    $classement = dbRow(
        "SELECT COUNT(*) + 1 as rang FROM utilisateurs
         WHERE province_id = ? AND score_moyen > ? AND is_active = 1",
        [$user['province_id'], $user['score_moyen'] ?? 0]
    );
}

include __DIR__ . '/includes/header_app.php';
?>

<!-- Stats globales -->
<div class="stats-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:24px">
  <div class="stat-card green">
    <div class="stat-label"><i class="bi bi-bar-chart"></i> Score moyen</div>
    <div class="stat-value" style="color:<?= score_couleur((float)($user['score_moyen']??0)) ?>"><?= number_format((float)($user['score_moyen']??0),1) ?>%</div>
    <div class="stat-sub"><?= score_label((float)($user['score_moyen']??0)) ?></div>
  </div>
  <div class="stat-card gold">
    <div class="stat-label"><i class="bi bi-pencil-square"></i> Examens</div>
    <div class="stat-value"><?= number_format((int)($user['total_examens']??0)) ?></div>
    <div class="stat-sub">Au total</div>
  </div>
  <div class="stat-card bleu">
    <div class="stat-label"><i class="bi bi-lightbulb"></i> Questions</div>
    <div class="stat-value"><?= number_format((int)($user['total_questions']??0)) ?></div>
    <div class="stat-sub">Répondues</div>
  </div>
  <div class="stat-card rouge">
    <div class="stat-label"><i class="bi bi-fire"></i> Série</div>
    <div class="stat-value"><?= (int)($stats['streak_actuel']??0) ?></div>
    <div class="stat-sub">jours consécutifs</div>
  </div>
  <?php if ($classement): ?>
  <div class="stat-card" style="--accent-color:var(--gold)">
    <div class="stat-label"><i class="bi bi-trophy"></i> Classement</div>
    <div class="stat-value">#<?= (int)$classement['rang'] ?></div>
    <div class="stat-sub">Dans ma province</div>
  </div>
  <?php else: ?>
  <div class="stat-card">
    <div class="stat-label"><i class="bi bi-calendar3"></i> Membre depuis</div>
    <div class="stat-value" style="font-size:18px"><?= date('m/Y', strtotime($user['created_at'])) ?></div>
    <div class="stat-sub"><?= floor((time()-strtotime($user['created_at']))/86400) ?> jours</div>
  </div>
  <?php endif; ?>
</div>

<!-- Calendrier activité 30j -->
<div class="card" style="margin-bottom:24px">
  <div class="card-header">
      <div class="card-title"><i class="bi bi-calendar3"></i> Activité — 30 derniers jours</div>
    <div style="font-size:12px;color:var(--gris-500)"><?= count($activite30j) ?> jours actifs</div>
  </div>
  <div style="display:flex;gap:4px;align-items:flex-end;height:60px;overflow-x:auto;padding-bottom:4px">
    <?php
    $maxEx = max(1, max(array_map(fn($a) => $a['examens'], $activite30j ?: [['examens'=>0]])));
    for ($i = 29; $i >= 0; $i--):
        $d   = date('Y-m-d', strtotime("-{$i} days"));
        $act = $actMap[$d] ?? null;
        $ex  = $act ? $act['examens'] : 0;
        $h   = $ex > 0 ? max(12, (int)(($ex / $maxEx) * 56)) : 4;
        $color = $ex > 0 ? 'var(--primary)' : 'var(--gris-200)';
        $title = date('d/m', strtotime($d)) . ' — ' . $ex . ' examen(s)';
    ?>
    <div title="<?= e($title) ?>" style="flex:1;min-width:6px;height:<?= $h ?>px;background:<?= $color ?>;border-radius:3px;cursor:default;transition:opacity .2s" onmouseover="this.style.opacity='.7'" onmouseout="this.style.opacity='1'"></div>
    <?php endfor; ?>
  </div>
  <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--gris-400);margin-top:6px">
    <span><?= date('d/m', strtotime('-29 days')) ?></span>
    <span>Aujourd'hui</span>
  </div>
</div>

<!-- Progression par matière -->
<?php if ($progressMatieres): ?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px">
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="bi bi-book"></i> Progression par matière</div>
    </div>
    <?php foreach ($progressMatieres as $pm):
      $pct2 = (float)$pm['score_moyen'];
    ?>
    <div style="margin-bottom:14px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px">
        <span style="font-size:13px;color:var(--gris-800);font-weight:500">
          <?= e($pm['icone']??'bi bi-book') ?> <?= e($pm['nom']) ?>
        </span>
        <div style="display:flex;gap:8px;align-items:center">
          <span style="font-size:11px;color:var(--gris-500)"><?= number_format($pm['questions_vues']) ?> q.</span>
          <span style="font-weight:700;font-size:13px;color:<?= score_couleur($pct2) ?>"><?= number_format($pct2, 1) ?>%</span>
        </div>
      </div>
      <div class="progress-bar">
        <div class="progress-bar-fill" style="width:<?= min(100,$pct2) ?>%;background:<?= e($pm['couleur']??'var(--primary)') ?>"></div>
      </div>
    </div>
    <?php endforeach; ?>
    <a href="/reussiteplus/questions.php" class="btn btn-primary btn-full" style="margin-top:8px"><i class="bi bi-brain"></i> S'entraîner maintenant</a>
  </div>

  <!-- Radar chart simulé par tableau -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="bi bi-bar-chart-line"></i> Analyse des forces</div>
    </div>
    <?php
    $top = array_slice($progressMatieres, 0, 3);
    $low = array_slice(array_reverse($progressMatieres), 0, 3);
    ?>
    <div style="margin-bottom:16px">
      <div style="font-size:12px;font-weight:600;color:var(--primary);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px"><i class="bi bi-trophy"></i> Points forts</div>
      <?php foreach ($top as $t): ?>
      <div style="display:flex;justify-content:space-between;font-size:13px;padding:4px 0;border-bottom:1px solid var(--gris-100)">
        <span><?= e($t['icone']??'bi bi-book') ?> <?= e($t['nom']) ?></span>
        <span style="color:var(--primary);font-weight:600"><?= number_format((float)$t['score_moyen'],1) ?>%</span>
      </div>
      <?php endforeach; ?>
    </div>
    <div>
      <div style="font-size:12px;font-weight:600;color:var(--rouge);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px"><i class="bi bi-graph-down"></i> À renforcer</div>
      <?php foreach ($low as $l): ?>
      <div style="display:flex;justify-content:space-between;font-size:13px;padding:4px 0;border-bottom:1px solid var(--gris-100)">
        <span><?= e($l['icone']??'bi bi-book') ?> <?= e($l['nom']) ?></span>
        <span style="color:var(--rouge);font-weight:600"><?= number_format((float)$l['score_moyen'],1) ?>%</span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php if ($user['plan'] === 'GRATUIT'): ?>
    <div style="margin-top:12px;background:var(--gold-light);border-radius:8px;padding:10px;font-size:12px;color:var(--gold-dark);text-align:center">
      <i class="bi bi-star-fill"></i> <a href="/reussiteplus/tarifs.php" style="color:var(--gold-dark);font-weight:600">Premium</a> : Plan de révision IA personnalisé
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- Historique des examens -->
<div class="section-header">
  <div class="section-title"><i class="bi bi-clock-history"></i> Historique des examens</div>
  <div style="font-size:13px;color:var(--gris-500)"><?= count($historique) ?> examens passés</div>
</div>

<?php if ($historique): ?>
<div class="table-wrap">
  <table class="table">
    <thead>
      <tr><th>Matière</th><th>Type</th><th>Score</th><th>Questions</th><th>Temps</th><th>Date</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($historique as $s):
      $pct3 = (float)($s['pourcentage']??0);
      $m = floor(($s['temps_passe']??0)/60);
      $sec = ($s['temps_passe']??0)%60;
    ?>
    <tr>
      <td>
        <span style="display:flex;align-items:center;gap:6px">
          <span style="width:8px;height:8px;border-radius:50%;background:<?= e($s['couleur']??'var(--primary)') ?>;display:inline-block;flex-shrink:0"></span>
          <?= e($s['matiere_nom'] ?? $s['titre'] ?? 'Examen') ?>
        </span>
      </td>
      <td><span class="badge badge-gray" style="font-size:10px"><?= e($s['exam_type']??'—') ?></span></td>
      <td>
        <span style="font-weight:700;color:<?= score_couleur($pct3) ?>"><?= number_format($pct3,1) ?>%</span>
        <div style="font-size:10px;color:var(--gris-500)"><?= score_label($pct3) ?></div>
      </td>
      <td style="font-size:12px;color:var(--gris-600)"><?= (int)$s['nb_questions'] ?> q.</td>
      <td style="font-size:12px;color:var(--gris-600)"><?= $m ?>m <?= str_pad($sec,2,'0',STR_PAD_LEFT) ?>s</td>
      <td style="font-size:12px;color:var(--gris-500)"><?= date('d/m/Y', strtotime($s['finished_at']??$s['started_at'])) ?></td>
      <td><a href="/reussiteplus/resultat.php?session=<?= e($s['id']) ?>" class="btn btn-ghost btn-sm">Voir</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php else: ?>
<div class="card" style="text-align:center;padding:40px">
  <div style="font-size:48px;margin-bottom:12px;color:var(--gris-300)"><i class="bi bi-bar-chart"></i></div>
  <div style="font-size:15px;font-weight:600;margin-bottom:8px">Aucun examen passé</div>
  <div style="font-size:13px;color:var(--gris-500);margin-bottom:20px">Commencez par passer votre premier examen !</div>
  <a href="/reussiteplus/examen.php" class="btn btn-primary"><i class="bi bi-pencil-square"></i> Passer un examen maintenant</a>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
