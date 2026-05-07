<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$pageTitle  = 'Statistiques par matière';
$pageActive = 'admin_stats_matieres';
$user = require_admin();

// ── Export CSV examens ────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $rows = dbAll("
        SELECT u.prenom, u.nom, u.email, u.plan, u.classe, u.ville,
               m.nom as matiere, es.statut, es.pourcentage, es.nb_questions,
               es.nb_correctes, es.temps_passe, es.started_at, es.finished_at
        FROM exam_sessions es
        JOIN utilisateurs u ON es.user_id = u.id
        LEFT JOIN matieres m ON es.matiere_id = m.id
        ORDER BY es.started_at DESC
    ") ?? [];
    $tmp = fopen('php://temp', 'r+');
    fwrite($tmp, "\xEF\xBB\xBF");
    fputcsv($tmp, ['Prénom','Nom','Email','Plan','Classe','Ville','Matière','Statut','Score%','Questions','Correctes','Durée(s)','Démarré','Terminé'], ';');
    foreach ($rows as $r) fputcsv($tmp, array_values($r), ';');
    rewind($tmp); $csv = stream_get_contents($tmp); fclose($tmp);
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="examens_' . date('Y-m-d') . '.csv"');
    header('Content-Length: ' . strlen($csv));
    echo $csv; exit;
}

// Période filtre
$periode = $_GET['periode'] ?? '30'; // 7, 30, 90, 365, all
$whereDate = match($periode) {
    '7'   => "AND s.started_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)",
    '30'  => "AND s.started_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)",
    '90'  => "AND s.started_at >= DATE_SUB(CURDATE(), INTERVAL 89 DAY)",
    '365' => "AND s.started_at >= DATE_SUB(CURDATE(), INTERVAL 364 DAY)",
    default => "",
};
$whereDateArch = match($periode) {
    '7'   => "AND a.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)",
    '30'  => "AND a.created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)",
    '90'  => "AND a.created_at >= DATE_SUB(CURDATE(), INTERVAL 89 DAY)",
    '365' => "AND a.created_at >= DATE_SUB(CURDATE(), INTERVAL 364 DAY)",
    default => "",
};

// ── Stats examens par matière ──────────────────────────────
$examStats = dbAll("
    SELECT
        m.id,
        m.nom,
        m.icone,
        m.couleur,
        COUNT(s.id)                              AS nb_sessions,
        COUNT(DISTINCT s.user_id)                AS nb_eleves,
        ROUND(AVG(s.score), 1)                   AS score_moyen,
        SUM(CASE WHEN s.score >= 50 THEN 1 ELSE 0 END) AS nb_reussi,
        COUNT(CASE WHEN s.statut = 'TERMINE' THEN 1 END) AS nb_termines
    FROM matieres m
    LEFT JOIN question_bank qb ON qb.matiere_id = m.id
    LEFT JOIN exam_sessions s ON s.matiere_id = m.id $whereDate
    GROUP BY m.id, m.nom, m.icone, m.couleur
    ORDER BY nb_sessions DESC
") ?? [];

// ── Stats archives par matière (vues + téléchargements) ───
$archStats = dbAll("
    SELECT
        m.id,
        m.nom,
        COUNT(a.id)           AS nb_archives,
        SUM(a.vues)           AS total_vues,
        SUM(a.telechargements) AS total_dl,
        MAX(a.annee)          AS derniere_annee
    FROM matieres m
    LEFT JOIN archives a ON a.matiere_id = m.id AND a.status = 'PUBLIE' $whereDateArch
    GROUP BY m.id, m.nom
    ORDER BY total_vues DESC
") ?? [];

// Index archStats par matière id
$archMap = [];
foreach ($archStats as $r) $archMap[$r['id']] = $r;

// ── Questions par matière ─────────────────────────────────
$qStats = dbAll("
    SELECT
        m.id,
        COUNT(qb.id)   AS nb_questions,
        ROUND(AVG(CASE WHEN qb.difficulte='DEBUTANT' THEN 1 WHEN qb.difficulte='ELEMENTAIRE' THEN 2 WHEN qb.difficulte='INTERMEDIAIRE' THEN 3 WHEN qb.difficulte='AVANCE' THEN 4 ELSE 5 END), 2) AS difficulte_moy
    FROM matieres m
    LEFT JOIN question_bank qb ON qb.matiere_id = m.id AND qb.status = 'PUBLIE'
    GROUP BY m.id
") ?? [];
$qMap = [];
foreach ($qStats as $r) $qMap[$r['id']] = $r;

// ── Top questions difficiles par matière ──────────────────
$topDifficiles = dbAll("
    SELECT qb.id, qb.enonce, qb.difficulte, m.nom AS matiere_nom, m.icone,
           qb.success_rate AS taux_reussite_pct
    FROM question_bank qb
    JOIN matieres m ON m.id = qb.matiere_id
    WHERE qb.status = 'PUBLIE' AND qb.success_rate IS NOT NULL AND qb.success_rate < 40
    ORDER BY qb.success_rate ASC
    LIMIT 10
") ?? [];

// ── Évolution inscriptions par matière préférée ───────────
// (via question_bank comme proxy — nb de sessions par matière dans le temps)
$evol = dbAll("
    SELECT
        DATE_FORMAT(s.started_at, '%Y-%m') AS mois,
        m.nom AS matiere,
        COUNT(s.id)                        AS nb
    FROM exam_sessions s
    JOIN matieres m ON m.id = s.matiere_id
    WHERE s.started_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
      AND s.matiere_id IS NOT NULL
    GROUP BY mois, m.id
    ORDER BY mois ASC
") ?? [];

// Global totals
$totalSessions = array_sum(array_column($examStats, 'nb_sessions'));
$totalEleves   = (int)dbRow("SELECT COUNT(DISTINCT user_id) as n FROM exam_sessions")['n'];
$totalQuestions = array_sum(array_column($qStats, 'nb_questions'));

// ── Export CSV ────────────────────────────────────────────
if (($_GET['export'] ?? '') === 'csv') {
    $periodeLabel = ['7'=>'7 jours','30'=>'30 jours','90'=>'3 mois','365'=>'1 an','all'=>'Tout'][$periode] ?? $periode;
    $filename = 'stats_matieres_' . $periode . '_' . date('Ymd') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
    fputcsv($out, ['Rapport statistiques RÉUSSITE+ — Période : ' . $periodeLabel . ' — Généré le ' . date('d/m/Y H:i')]);
    fputcsv($out, []);
    // Tableau principal
    fputcsv($out, ['Matière','Sessions','Élèves distincts','Score moyen (%)','Taux réussite (%)','Questions en banque','Archives publiées','Téléchargements archives']);
    foreach ($examStats as $m) {
        $arc  = $archMap[$m['id']] ?? ['nb_archives'=>0,'total_vues'=>0,'total_dl'=>0];
        $q    = $qMap[$m['id']]   ?? ['nb_questions'=>0];
        $taux = $m['nb_termines'] > 0 ? round($m['nb_reussi'] / $m['nb_termines'] * 100) : '';
        fputcsv($out, [
            $m['nom'],
            $m['nb_sessions'],
            $m['nb_eleves'],
            $m['score_moyen'] ?? '',
            $taux,
            $q['nb_questions'],
            $arc['nb_archives'],
            $arc['total_dl'],
        ]);
    }
    fputcsv($out, []);
    // Totaux
    fputcsv($out, ['TOTAUX','Total sessions','Total élèves distincts','—','—','Total questions','—','—']);
    fputcsv($out, ['', $totalSessions, $totalEleves, '', '', $totalQuestions, '', '']);
    fputcsv($out, []);
    // Questions difficiles
    fputcsv($out, ['Questions les plus échouées (taux réussite < 40%)']);
    fputcsv($out, ['Matière','Énoncé (extrait)','Taux réussite (%)']);
    foreach ($topDifficiles as $q) {
        fputcsv($out, [$q['matiere_nom'], mb_strimwidth($q['enonce'], 0, 120, '…'), $q['taux_reussite_pct']]);
    }
    fclose($out);
    exit;
}

include __DIR__ . '/../includes/header_app.php';
?>

<style>
.stats-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:24px; }
.stat-card { background:var(--blanc); border:1.5px solid var(--gris-200); border-radius:14px; padding:18px 20px; }
.stat-card .val { font-family:'Manrope',sans-serif; font-size:26px; font-weight:900; color:var(--gris-900); }
.stat-card .lbl { font-size:11px; color:var(--gris-500); text-transform:uppercase; letter-spacing:.5px; margin-top:4px; }

.mat-row { display:grid; grid-template-columns:180px 1fr 90px 90px 90px 90px 100px 80px; gap:0; align-items:center; border-bottom:1px solid var(--gris-100); padding:12px 0; }
.mat-row:last-child { border-bottom:none; }
.mat-row .mat-name { display:flex; align-items:center; gap:8px; font-size:13px; font-weight:600; color:var(--gris-900); }

.bar-wrap { background:var(--gris-100); border-radius:4px; height:8px; overflow:hidden; flex:1; margin:0 8px; }
.bar-fill { height:8px; border-radius:4px; transition:width .5s; }

.badge-diff { display:inline-block; padding:2px 8px; border-radius:20px; font-size:10px; font-weight:700; }

@media(max-width:900px){
  .stats-grid { grid-template-columns:1fr 1fr; }
  .mat-row { grid-template-columns:1fr 1fr 1fr; }
}
</style>

<!-- Header page -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px">
  <div>
    <h1 style="font-family:'Manrope',sans-serif;font-size:22px;font-weight:900;color:var(--gris-900);margin:0 0 4px">Statistiques par matière</h1>
    <p style="font-size:13px;color:var(--gris-500);margin:0">Performance, engagement et couverture du contenu par discipline.</p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php foreach (['7'=>'7 jours','30'=>'30 jours','90'=>'3 mois','365'=>'1 an','all'=>'Tout'] as $v=>$l): ?>
    <a href="?periode=<?= $v ?>" class="btn <?= $periode===$v ? 'btn-primary' : 'btn-ghost' ?> btn-sm"><?= $l ?></a>
    <?php endforeach; ?>
    <a href="?periode=<?= $periode ?>&export=csv"
       class="btn btn-ghost btn-sm"
       style="display:inline-flex;align-items:center;gap:5px;border:1px solid var(--gris-300);margin-left:8px">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
        <polyline points="7 10 12 15 17 10"/>
        <line x1="12" y1="15" x2="12" y2="3"/>
      </svg>
      Exporter CSV
    </a>
  </div>
</div>

<!-- KPI globaux -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="val"><?= count($examStats) ?></div>
    <div class="lbl">Matières actives</div>
  </div>
  <div class="stat-card">
    <div class="val"><?= number_format($totalQuestions) ?></div>
    <div class="lbl">Questions en banque</div>
  </div>
  <div class="stat-card">
    <div class="val"><?= number_format($totalSessions) ?></div>
    <div class="lbl">Sessions d'examen</div>
  </div>
  <div class="stat-card">
    <div class="val"><?= number_format($totalEleves) ?></div>
    <div class="lbl">Élèves actifs</div>
  </div>
</div>

<!-- Tableau principal matières -->
<div class="card" style="margin-bottom:24px">
  <div class="card-header">
    <div class="card-title">Performance par matière</div>
    <span style="font-size:12px;color:var(--gris-500);margin:0">Période : <?= ['7'=>'7 derniers jours','30'=>'30 derniers jours','90'=>'3 derniers mois','365'=>'Dernière année','all'=>'Tout le temps'][$periode] ?></span>
  </div>
  <div style="overflow-x:auto;padding:0 20px 20px">

    <!-- En-têtes -->
    <div class="mat-row" style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--gris-400);padding-bottom:8px;border-bottom:2px solid var(--gris-200)">
      <div>Matière</div>
      <div>Score moyen</div>
      <div>Sessions</div>
      <div>Élèves</div>
      <div>Taux réussite</div>
      <div>Questions</div>
      <div>Archives</div>
      <div>Télécharg.</div>
    </div>

    <?php foreach ($examStats as $m):
        $arc  = $archMap[$m['id']] ?? ['nb_archives'=>0,'total_vues'=>0,'total_dl'=>0];
        $q    = $qMap[$m['id']]   ?? ['nb_questions'=>0];
        $taux = $m['nb_termines'] > 0 ? round($m['nb_reussi'] / $m['nb_termines'] * 100) : null;
        $score = (float)$m['score_moyen'];
        $color = $m['couleur'] ?? 'var(--primary)';
    ?>
    <div class="mat-row">
      <!-- Nom + icône -->
      <div class="mat-name">
        <span style="width:30px;height:30px;border-radius:8px;background:<?= e($color) ?>15;display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <?= matiere_icon($m['icone'] ?? 'book', 14) ?>
        </span>
        <?= e($m['nom']) ?>
      </div>

      <!-- Barre score -->
      <div style="display:flex;align-items:center;gap:8px;padding-right:12px">
        <div class="bar-wrap">
          <div class="bar-fill" style="width:<?= min(100, $score) ?>%;background:<?= score_couleur($score) ?>"></div>
        </div>
        <span style="font-size:13px;font-weight:700;color:<?= score_couleur($score) ?>;white-space:nowrap;min-width:38px">
          <?= $score > 0 ? $score . '%' : '—' ?>
        </span>
      </div>

      <div style="font-size:13px;font-weight:600;color:var(--gris-700)"><?= number_format($m['nb_sessions']) ?></div>
      <div style="font-size:13px;color:var(--gris-600)"><?= number_format($m['nb_eleves']) ?></div>

      <!-- Taux réussite -->
      <div>
        <?php if ($taux !== null): ?>
        <span style="font-size:12px;font-weight:700;color:<?= $taux>=50?'#007A5E':'#C9342A' ?>">
          <?= $taux ?>%
        </span>
        <?php else: ?>
        <span style="font-size:12px;color:var(--gris-400)">—</span>
        <?php endif; ?>
      </div>

      <div style="font-size:13px;color:var(--gris-700)"><?= number_format($q['nb_questions']) ?></div>
      <div style="font-size:13px;color:var(--gris-700)"><?= number_format($arc['nb_archives']) ?></div>
      <div style="font-size:13px;color:var(--gris-500)"><?= number_format($arc['total_dl']) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Section basse : deux colonnes -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:24px">

  <!-- Archives par matière -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i data-lucide="archive" style="width:15px;height:15px;vertical-align:-3px;margin-right:5px"></i> Archives par matière</div>
    </div>
    <div style="padding:20px">
      <?php
      $maxVues = max(1, max(array_column($archStats, 'total_vues') ?: [0]));
      foreach ($archStats as $arc):
          $pct = round($arc['total_vues'] / $maxVues * 100);
          $mat = null;
          foreach ($examStats as $m) { if ($m['id'] === $arc['id']) { $mat = $m; break; } }
          $color = $mat['couleur'] ?? 'var(--primary)';
      ?>
      <div style="margin-bottom:14px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px">
          <span style="font-size:13px;font-weight:600;color:var(--gris-800)"><?= e($arc['nom']) ?></span>
          <div style="display:flex;gap:12px">
            <span style="font-size:11px;color:var(--gris-500)"><?= $arc['nb_archives'] ?> fichiers</span>
            <span style="font-size:11px;font-weight:700;color:var(--gris-700)"><?= number_format($arc['total_vues']) ?> vues</span>
          </div>
        </div>
        <div style="background:var(--gris-100);border-radius:6px;height:7px;overflow:hidden">
          <div style="height:7px;border-radius:6px;background:<?= e($color) ?>;width:<?= $pct ?>%;transition:width .5s"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Questions difficiles -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i data-lucide="alert-triangle" style="width:15px;height:15px;vertical-align:-3px;margin-right:5px;stroke:#C9342A"></i> Questions les plus échouées</div>
      <span style="font-size:11px;color:var(--gris-400)">Taux de réussite &lt; 40%</span>
    </div>
    <?php if ($topDifficiles): ?>
    <div style="overflow-x:auto">
      <table class="table">
        <thead>
          <tr>
            <th>Matière</th>
            <th>Question</th>
            <th>Taux</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($topDifficiles as $q): ?>
          <tr>
            <td>
              <span style="display:inline-flex;align-items:center;gap:5px;font-size:12px">
                <?= matiere_icon($q['icone'] ?? 'book', 12) ?> <?= e($q['matiere_nom']) ?>
              </span>
            </td>
            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;color:var(--gris-700)" title="<?= e($q['enonce']) ?>">
              <?= e(mb_strimwidth($q['enonce'], 0, 50, '…')) ?>
            </td>
            <td>
              <span style="font-size:12px;font-weight:700;color:#C9342A"><?= $q['taux_reussite_pct'] ?>%</span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div style="padding:24px;text-align:center;color:var(--gris-400);font-size:13px">
      <i data-lucide="check-circle" style="width:32px;height:32px;margin-bottom:8px;stroke:var(--primary)"></i><br>
      Aucune question avec taux de réussite critique pour l'instant.
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Répartition questions par difficulté -->
<div class="card" style="margin-bottom:24px">
  <div class="card-header">
    <div class="card-title"><i data-lucide="bar-chart-2" style="width:15px;height:15px;vertical-align:-3px;margin-right:5px"></i> Répartition des questions par difficulté</div>
  </div>
  <div style="padding:20px;display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px">
    <?php foreach ($examStats as $m):
        $q = $qMap[$m['id']] ?? ['nb_questions'=>0];
        if (!$q['nb_questions']) continue;
        $color = $m['couleur'] ?? 'var(--primary)';
    ?>
    <div style="border:1.5px solid var(--gris-200);border-radius:12px;padding:14px">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
        <span style="width:28px;height:28px;border-radius:7px;background:<?= e($color) ?>15;display:flex;align-items:center;justify-content:center">
          <?= matiere_icon($m['icone'] ?? 'book', 13) ?>
        </span>
        <span style="font-size:13px;font-weight:700;color:var(--gris-900)"><?= e($m['nom']) ?></span>
      </div>
      <div style="font-size:22px;font-weight:900;color:var(--gris-900);margin-bottom:2px"><?= number_format($q['nb_questions']) ?></div>
      <div style="font-size:11px;color:var(--gris-400)">questions actives</div>
      <?php
        $diff = round($q['difficulte_moy'] ?? 1, 1);
        $diffLabel = $diff < 1.5 ? ['Facile','#007A5E'] : ($diff < 2.5 ? ['Moyen','#C9972A'] : ['Difficile','#C9342A']);
      ?>
      <div style="margin-top:8px">
        <span style="font-size:11px;background:<?= $diffLabel[1] ?>15;color:<?= $diffLabel[1] ?>;padding:2px 8px;border-radius:20px;font-weight:600"><?= $diffLabel[0] ?></span>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer_app.php'; ?>
