<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Tableau de bord';
$pageActive = 'dashboard';

$user    = require_login();
$stats   = get_user_stats($user['id']);
$welcome = isset($_GET['welcome']);

$recentSessions = dbAll(
    "SELECT es.*, m.nom as matiere_nom, m.couleur as matiere_couleur
     FROM exam_sessions es
     LEFT JOIN matieres m ON es.matiere_id = m.id
     WHERE es.user_id = ? AND es.statut = 'TERMINE'
     ORDER BY es.finished_at DESC LIMIT 5",
    [$user['id']]
);

$progressMatieres = dbAll(
    "SELECT up.*, m.nom, m.couleur, m.icone
     FROM user_progression up
     JOIN matieres m ON up.matiere_id = m.id
     WHERE up.user_id = ?
     ORDER BY up.score_moyen DESC LIMIT 6",
    [$user['id']]
);

$archivesRec = dbAll(
    "SELECT a.*, m.nom as matiere_nom, m.couleur
     FROM archives a
     JOIN matieres m ON a.matiere_id = m.id
     WHERE a.status = 'PUBLIE' AND (a.premium_only = 0 OR ? != 'GRATUIT')
     ORDER BY a.vues DESC LIMIT 6",
    [$user['plan']]
);

$notificationsRecentes = dbAll(
    "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5",
    [$user['id']]
);

$activite7j = dbAll(
    "SELECT DATE(finished_at) as date_act, COUNT(*) as examens
     FROM exam_sessions
     WHERE user_id = ? AND finished_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     GROUP BY DATE(finished_at)",
    [$user['id']]
);

$matiereActives = dbAll(
    "SELECT COUNT(DISTINCT matiere_id) as n FROM user_progression WHERE user_id = ?",
    [$user['id']]
);
$nbMatieres = (int)($matiereActives[0]['n'] ?? 0);

include __DIR__ . '/includes/header_app.php';
?>

<!-- ═══ BANNIÈRE D'ACCUEIL ══════════════════════════════════════ -->
<div class="db-welcome">
  <div class="db-welcome-left">
    <div class="db-welcome-greeting">
      Bonjour, <?= e($user['prenom']) ?>
    </div>
    <div class="db-welcome-sub">
      <?php
        $h = (int)date('H');
        $moment = $h < 12 ? 'Bonne matinée' : ($h < 18 ? 'Bon après-midi' : 'Bonne soirée');
        $jours = ['Sunday'=>'Dimanche','Monday'=>'Lundi','Tuesday'=>'Mardi','Wednesday'=>'Mercredi','Thursday'=>'Jeudi','Friday'=>'Vendredi','Saturday'=>'Samedi'];
        $mois  = ['January'=>'janvier','February'=>'février','March'=>'mars','April'=>'avril','May'=>'mai','June'=>'juin','July'=>'juillet','August'=>'août','September'=>'septembre','October'=>'octobre','November'=>'novembre','December'=>'décembre'];
        $dateStr = ($jours[date('l')] ?? date('l')) . ' ' . date('j') . ' ' . ($mois[date('F')] ?? date('F')) . ' ' . date('Y');
        echo "$moment — $dateStr";
      ?>
    </div>
    <div class="db-welcome-badges">
      <span class="db-welcome-badge">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        <?= number_format((int)($user['total_examens'] ?? $stats['total_examens'] ?? 0)) ?> examens
      </span>
      <?php $plan = $user['plan'] ?? 'GRATUIT'; ?>
      <span class="db-welcome-badge">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        Plan <?= e($plan) ?>
      </span>
      <?php if ($nbMatieres > 0): ?>
      <span class="db-welcome-badge">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
        <?= $nbMatieres ?> matière<?= $nbMatieres > 1 ? 's' : '' ?>
      </span>
      <?php endif; ?>
    </div>
  </div>
  <div class="db-welcome-score">
    <?php $scoreM = (float)($user['score_moyen'] ?? $stats['score_moyen'] ?? 0); ?>
    <div class="db-welcome-score-circle">
      <div class="db-welcome-score-num"><?= number_format($scoreM, 1) ?>%</div>
      <div class="db-welcome-score-label">Score moy.</div>
    </div>
    <div style="font-size:11px;opacity:.7;text-align:center">Votre moyenne globale</div>
  </div>
</div>

<!-- ═══ KPI CARDS ══════════════════════════════════════════════ -->
<div class="db-kpi-grid">

  <div class="db-kpi-card kpi-green">
    <div class="db-kpi-top">
      <span class="db-kpi-label">Score moyen</span>
      <div class="db-kpi-icon kpi-green">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      </div>
    </div>
    <div class="db-kpi-value" style="color:var(--primary)"><?= number_format($scoreM, 1) ?>%</div>
    <div class="db-kpi-sub" style="display:flex;align-items:center;gap:4px;color:<?= $scoreM >= 50 ? 'var(--primary)' : 'var(--rouge)' ?>">
      <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><?= $scoreM >= 50 ? '<polyline points="18 15 12 9 6 15"/>' : '<polyline points="6 9 12 15 18 9"/>' ?></svg>
      <?= $scoreM >= 50 ? 'Au-dessus de la moyenne' : 'En dessous de 50 %' ?>
    </div>
  </div>

  <div class="db-kpi-card kpi-bleu">
    <div class="db-kpi-top">
      <span class="db-kpi-label">Examens passés</span>
      <div class="db-kpi-icon kpi-bleu">
        <svg viewBox="0 0 24 24" fill="none" stroke="var(--bleu)" stroke-width="2" stroke-linecap="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
      </div>
    </div>
    <div class="db-kpi-value" style="color:var(--bleu)"><?= number_format((int)($user['total_examens'] ?? $stats['total_examens'] ?? 0)) ?></div>
    <div class="db-kpi-sub">Total depuis l'inscription</div>
  </div>

  <div class="db-kpi-card kpi-gold">
    <div class="db-kpi-top">
      <span class="db-kpi-label">Matières actives</span>
      <div class="db-kpi-icon kpi-gold">
        <svg viewBox="0 0 24 24" fill="none" stroke="var(--gold)" stroke-width="2" stroke-linecap="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
      </div>
    </div>
    <div class="db-kpi-value" style="color:var(--gold)"><?= $nbMatieres ?></div>
    <div class="db-kpi-sub">Matières avec progression tracée</div>
  </div>

  <div class="db-kpi-card kpi-rouge">
    <div class="db-kpi-top">
      <span class="db-kpi-label">Notifications</span>
      <div class="db-kpi-icon kpi-rouge">
        <svg viewBox="0 0 24 24" fill="none" stroke="var(--rouge)" stroke-width="2" stroke-linecap="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      </div>
    </div>
    <?php $nbNotifs = count($notificationsRecentes); ?>
    <div class="db-kpi-value" style="color:<?= $nbNotifs > 0 ? 'var(--rouge)' : 'var(--gris-400)' ?>"><?= $nbNotifs ?></div>
    <div class="db-kpi-sub"><?= $nbNotifs > 0 ? $nbNotifs . ' non lue(s)' : 'Aucune nouvelle alerte' ?></div>
  </div>

</div>

<!-- ═══ ACTIVITÉ + PROGRESSION ════════════════════════════════ -->
<div class="db-grid-2">

  <!-- Activité 7 jours -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px;margin-right:6px"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        Activité — 7 derniers jours
      </div>
      <?php $totalSemaine = array_sum(array_column($activite7j, 'examens')); ?>
      <span class="badge badge-green"><?= $totalSemaine ?> cette semaine</span>
    </div>
    <?php
    $actMap = [];
    foreach ($activite7j as $a) $actMap[$a['date_act']] = $a;
    $maxEx  = max(1, max(array_map(fn($a) => (int)$a['examens'], $activite7j ?: [['examens' => 0]])));
    $days   = ['Mon'=>'L','Tue'=>'M','Wed'=>'M','Thu'=>'J','Fri'=>'V','Sat'=>'S','Sun'=>'D'];
    ?>
    <div class="db-chart-bars">
      <?php for ($i = 6; $i >= 0; $i--):
        $d   = date('Y-m-d', strtotime("-{$i} days"));
        $ex  = (int)($actMap[$d]['examens'] ?? 0);
        $pct = $ex > 0 ? max(12, (int)(($ex / $maxEx) * 100)) : 4;
        $dl  = $days[date('D', strtotime($d))] ?? date('D', strtotime($d));
        $isToday = ($d === date('Y-m-d'));
      ?>
      <div class="db-chart-bar-wrap">
        <div class="db-chart-bar <?= $ex === 0 ? 'empty' : '' ?>"
             style="height:<?= $pct ?>%;<?= $isToday ? 'background:var(--gold)' : '' ?>"
             title="<?= $ex ?> examen<?= $ex > 1 ? 's' : '' ?> — <?= date('d/m', strtotime($d)) ?>"></div>
        <div class="db-chart-day" style="<?= $isToday ? 'color:var(--gold);font-weight:700' : '' ?>"><?= $dl ?></div>
      </div>
      <?php endfor; ?>
    </div>
    <?php if ($totalSemaine === 0): ?>
    <div style="text-align:center;padding:12px 0 4px;font-size:13px;color:var(--gris-400)">
      Aucun examen cette semaine — <a href="/reussiteplus/examen.php" style="color:var(--primary);font-weight:600">Commencer maintenant →</a>
    </div>
    <?php endif; ?>
  </div>

  <!-- Progression par matière -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px;margin-right:6px"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
        Progression par matière
      </div>
      <a href="/reussiteplus/progression.php" class="section-link">Tout voir →</a>
    </div>
    <?php if ($progressMatieres): ?>
      <?php foreach (array_slice($progressMatieres, 0, 5) as $pm):
        $sc = (float)$pm['score_moyen'];
        $col = $sc >= 70 ? 'var(--primary)' : ($sc >= 50 ? 'var(--gold)' : 'var(--rouge)');
      ?>
      <div style="margin-bottom:12px">
        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:5px">
          <span style="color:var(--gris-700);font-weight:500"><?= e($pm['nom']) ?></span>
          <span style="font-weight:700;color:<?= $col ?>"><?= number_format($sc, 1) ?>%</span>
        </div>
        <div class="progress-bar">
          <div class="progress-bar-fill" style="width:<?= min(100, $sc) ?>%;background:<?= $col ?>"></div>
        </div>
      </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div style="text-align:center;padding:28px 0;color:var(--gris-400);font-size:13px">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="display:block;margin:0 auto 10px;opacity:.4"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
        Passez vos premiers examens pour suivre votre progression
      </div>
    <?php endif; ?>
  </div>

</div>

<!-- ═══ DERNIERS EXAMENS ══════════════════════════════════════ -->
<?php if ($recentSessions): ?>
<div style="margin-bottom:24px">
  <div class="section-header">
    <div class="section-title">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px;margin-right:6px"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      Derniers examens passés
    </div>
    <a href="/reussiteplus/progression.php" class="section-link">Historique complet →</a>
  </div>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>Matière</th>
          <th>Type</th>
          <th>Score</th>
          <th>Durée</th>
          <th>Date</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentSessions as $s):
          $pct  = (float)($s['pourcentage'] ?? 0);
          $mins = floor(($s['temps_passe'] ?? 0) / 60);
          $secs = ($s['temps_passe'] ?? 0) % 60;
          $col  = $pct >= 70 ? 'var(--primary)' : ($pct >= 50 ? 'var(--gold)' : 'var(--rouge)');
        ?>
        <tr>
          <td>
            <span style="display:inline-flex;align-items:center;gap:8px">
              <span style="width:10px;height:10px;border-radius:50%;background:<?= e($s['matiere_couleur'] ?? '#007A5E') ?>;display:inline-block;flex-shrink:0"></span>
              <span style="font-weight:500"><?= e($s['matiere_nom'] ?? 'Examen') ?></span>
            </span>
          </td>
          <td><span class="badge badge-gray"><?= e($s['exam_type'] ?? '—') ?></span></td>
          <td>
            <span style="font-weight:700;color:<?= $col ?>;font-size:14px"><?= number_format($pct, 1) ?>%</span>
            <span style="font-size:11px;color:var(--gris-500);margin-left:4px"><?= score_label($pct) ?></span>
          </td>
          <td style="font-size:12px;color:var(--gris-500)"><?= $mins ?>m<?= $secs > 0 ? " {$secs}s" : '' ?></td>
          <td style="font-size:12px;color:var(--gris-400)"><?= date('d/m/Y', strtotime($s['finished_at'] ?? $s['started_at'])) ?></td>
          <td><a href="/reussiteplus/resultat.php?session=<?= e($s['id']) ?>" class="btn btn-ghost btn-sm">Résultats</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php else: ?>
<div style="margin-bottom:24px">
  <div class="section-header">
    <div class="section-title">Derniers examens passés</div>
  </div>
  <div class="card" style="text-align:center;padding:36px;color:var(--gris-400)">
    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="display:block;margin:0 auto 12px;opacity:.35"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
    <div style="font-size:14px;font-weight:600;margin-bottom:6px;color:var(--gris-600)">Aucun examen pour l'instant</div>
    <a href="/reussiteplus/examen.php" class="btn btn-primary btn-sm" style="margin-top:8px">Passer mon premier examen</a>
  </div>
</div>
<?php endif; ?>

<!-- ═══ ARCHIVES RECOMMANDÉES ══════════════════════════════════ -->
<div style="margin-bottom:24px">
  <div class="section-header">
    <div class="section-title">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px;margin-right:6px"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
      Archives recommandées
    </div>
    <a href="/reussiteplus/archives.php" class="section-link">Toutes les archives →</a>
  </div>
  <?php if ($archivesRec): ?>
  <div class="exams-grid">
    <?php foreach ($archivesRec as $arc): ?>
    <div class="exam-card" onclick="window.location='/reussiteplus/archives.php?id=<?= e($arc['id']) ?>'">
      <div class="exam-card-header">
        <span class="badge badge-green"><?= e($arc['exam_type']) ?></span>
        <span style="font-size:11px;color:var(--gris-500)"><?= e($arc['annee']) ?></span>
      </div>
      <div class="exam-card-body">
        <div class="exam-card-title"><?= e($arc['titre']) ?></div>
        <div class="exam-meta" style="margin-top:6px">
          <span class="exam-meta-item">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
            <?= e($arc['matiere_nom']) ?>
          </span>
          <span class="exam-meta-item">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            <?= number_format($arc['vues']) ?> vues
          </span>
        </div>
        <?php if ($arc['premium_only'] && ($user['plan'] ?? '') === 'GRATUIT'): ?>
        <div style="margin-top:8px;font-size:11px;color:var(--gold-dark);display:flex;align-items:center;gap:4px">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
          Réservé aux membres Premium
        </div>
        <?php endif; ?>
      </div>
      <div class="exam-card-footer">
        <a href="/reussiteplus/archives.php?id=<?= e($arc['id']) ?>" class="btn btn-primary btn-sm" style="flex:1;justify-content:center" onclick="event.stopPropagation()">Consulter</a>
        <?php if ($arc['corrige_url']): ?>
        <a href="<?= e($arc['corrige_url']) ?>" class="btn btn-ghost btn-sm" target="_blank" onclick="event.stopPropagation()">Corrigé</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="card" style="text-align:center;padding:40px;color:var(--gris-400)">
    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="display:block;margin:0 auto 12px;opacity:.35"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
    <div style="font-size:14px;font-weight:600;color:var(--gris-600);margin-bottom:4px">Aucune archive disponible</div>
    <div style="font-size:13px">Les archives seront ajoutées prochainement.</div>
  </div>
  <?php endif; ?>
</div>

<!-- ═══ ACTIONS RAPIDES ════════════════════════════════════════ -->
<div class="db-actions-grid">
  <a href="/reussiteplus/examen.php" class="db-action-card db-action-green">
    <div class="db-action-icon">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>
    </div>
    <div class="db-action-title">Passer un examen</div>
    <div class="db-action-sub">Simule les conditions réelles EXETAT</div>
  </a>
  <a href="/reussiteplus/questions.php" class="db-action-card db-action-bleu">
    <div class="db-action-icon">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    </div>
    <div class="db-action-title">S'entraîner</div>
    <div class="db-action-sub">15 000+ questions par matière</div>
  </a>
  <a href="/reussiteplus/archives.php" class="db-action-card db-action-gold">
    <div class="db-action-icon">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
    </div>
    <div class="db-action-title">Archives officielles</div>
    <div class="db-action-sub">Sujets & corrigés EXETAT</div>
  </a>
  <a href="/reussiteplus/cours/index.php" class="db-action-card db-action-violet">
    <div class="db-action-icon">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
    </div>
    <div class="db-action-title">Mes cours</div>
    <div class="db-action-sub">PDF, vidéos et ressources</div>
  </a>
</div>

<!-- ═══ MODAL ONBOARDING ══════════════════════════════════════ -->
<?php if ($welcome): ?>
<style>
#ob-bd{position:fixed;inset:0;background:rgba(15,23,42,.7);z-index:9999;display:flex;align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(6px);animation:ob-fadein .25s ease}
@keyframes ob-fadein{from{opacity:0}to{opacity:1}}
#ob-card{background:#fff;border-radius:20px;width:100%;max-width:560px;overflow:hidden;box-shadow:0 32px 80px rgba(0,0,0,.25);display:flex;flex-direction:column;max-height:92vh}
.ob-slides{flex:1;overflow:hidden;position:relative;min-height:0}
.ob-slide{position:absolute;inset:0;overflow-y:auto;padding:36px 40px;transition:transform .3s cubic-bezier(.4,0,.2,1),opacity .3s;will-change:transform}
.ob-slide.ob-active{transform:translateX(0);opacity:1;position:relative}
.ob-slide.ob-left{transform:translateX(-100%);opacity:0;pointer-events:none}
.ob-slide.ob-right{transform:translateX(100%);opacity:0;pointer-events:none}
.ob-topbar{height:4px;background:#E2E8F0;flex-shrink:0}
.ob-fill{height:100%;background:linear-gradient(90deg,#007A5E,#7c3aed);border-radius:4px;transition:width .35s ease}
.ob-footer{display:flex;align-items:center;justify-content:space-between;padding:16px 28px;border-top:1px solid #F1F5F9;flex-shrink:0}
.ob-dots{display:flex;gap:6px;align-items:center}
.ob-dot{width:7px;height:7px;border-radius:4px;background:#E2E8F0;border:none;cursor:pointer;padding:0;transition:all .25s}
.ob-dot.on{width:20px;background:#007A5E}
.ob-btn{height:38px;padding:0 20px;border-radius:10px;font-family:inherit;font-size:13px;font-weight:600;cursor:pointer;border:none;display:inline-flex;align-items:center;gap:6px;transition:all .2s}
.ob-btn-sec{background:#F1F5F9;color:#4A5568}.ob-btn-sec:hover{background:#E2E8F0}
.ob-btn-pri{background:#007A5E;color:#fff}.ob-btn-pri:hover{background:#005A45}
.ob-icon{width:70px;height:70px;border-radius:16px;display:flex;align-items:center;justify-content:center;margin:0 auto 18px}
.ob-step-row{display:flex;gap:14px;align-items:flex-start}
.ob-step-num{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:15px;flex-shrink:0}
.ob-plan{border-radius:14px;padding:16px 14px;border:2px solid #E2E8F0;position:relative}
.ob-plan.ob-best{border-color:#007A5E;background:#E8F5F1}
.ob-plan-badge{position:absolute;top:-10px;left:50%;transform:translateX(-50%);background:#007A5E;color:#fff;font-size:10px;font-weight:700;padding:2px 10px;border-radius:10px;white-space:nowrap}
.ob-check{color:#007A5E;font-size:12px}.ob-cross{color:#CBD5E1;font-size:12px}
@media(max-width:480px){.ob-slide{padding:24px 20px}.ob-footer{padding:12px 16px}.ob-plan-grid{grid-template-columns:1fr!important}}
</style>
<div id="ob-bd">
  <div id="ob-card">
    <div class="ob-topbar"><div id="ob-fill" class="ob-fill" style="width:33%"></div></div>
    <div id="ob-slides" class="ob-slides">
      <div class="ob-slide ob-active">
        <div class="ob-icon" style="background:linear-gradient(135deg,#007A5E,#22C55E)">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        </div>
        <h2 style="font-family:'Manrope',sans-serif;font-size:19px;font-weight:800;text-align:center;margin-bottom:6px">Comment ça marche ?</h2>
        <p style="text-align:center;color:#6B7280;font-size:13px;margin-bottom:20px">3 étapes pour booster vos résultats EXETAT</p>
        <div style="display:flex;flex-direction:column;gap:0">
          <div class="ob-step-row"><div class="ob-step-num" style="background:linear-gradient(135deg,#007A5E,#22C55E)">1</div><div style="padding-top:4px"><div style="font-weight:700;font-size:14px;margin-bottom:3px">Passez vos examens</div><div style="font-size:13px;color:#6B7280;line-height:1.6">Choisissez une matière et simulez les conditions réelles avec de vraies questions EXETAT.</div></div></div>
          <div style="width:2px;height:16px;background:#E2E8F0;margin-left:17px"></div>
          <div class="ob-step-row"><div class="ob-step-num" style="background:linear-gradient(135deg,#1E5FAD,#7c3aed)">2</div><div style="padding-top:4px"><div style="font-weight:700;font-size:14px;margin-bottom:3px">Analysez vos résultats</div><div style="font-size:13px;color:#6B7280;line-height:1.6">Chaque erreur est expliquée. Comprenez pourquoi vous avez raté et comment progresser.</div></div></div>
          <div style="width:2px;height:16px;background:#E2E8F0;margin-left:17px"></div>
          <div class="ob-step-row"><div class="ob-step-num" style="background:linear-gradient(135deg,#C9972A,#F59E0B)">3</div><div style="padding-top:4px"><div style="font-weight:700;font-size:14px;margin-bottom:3px">Laissez l'IA vous guider</div><div style="font-size:13px;color:#6B7280;line-height:1.6">Un plan de révision 7 jours adapté à vos points faibles, généré automatiquement.</div></div></div>
        </div>
      </div>
      <div class="ob-slide ob-right">
        <h3 style="font-family:'Manrope',sans-serif;font-size:19px;font-weight:800;text-align:center;margin-bottom:6px">Choisissez votre plan</h3>
        <p style="text-align:center;color:#6B7280;font-size:13px;margin-bottom:20px">Commencez gratuitement, évoluez quand vous voulez</p>
        <div class="ob-plan-grid" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:16px">
          <div class="ob-plan"><div style="font-weight:800;font-size:13px;margin-bottom:3px;color:#4A5568">Gratuit</div><div style="font-size:20px;font-weight:900;color:#4A5568;margin-bottom:10px">0 CDF</div><div style="font-size:11px;display:flex;flex-direction:column;gap:5px"><div class="ob-check">✓ 5 examens/mois</div><div class="ob-check">✓ Questions basiques</div><div class="ob-cross">✗ Archives</div><div class="ob-cross">✗ IA Coach</div></div></div>
          <div class="ob-plan ob-best"><div class="ob-plan-badge">POPULAIRE</div><div style="font-weight:800;font-size:13px;margin-bottom:3px;color:#005A45">Premium</div><div style="font-size:18px;font-weight:900;color:#007A5E;margin-bottom:10px">10 000 <span style="font-size:11px;font-weight:400">CDF/mois</span></div><div style="font-size:11px;display:flex;flex-direction:column;gap:5px"><div class="ob-check">✓ Examens illimités</div><div class="ob-check">✓ Toutes archives</div><div class="ob-check">✓ Corrigés PDF</div><div class="ob-check">✓ IA Coach</div></div></div>
          <div class="ob-plan" style="border-color:#C9972A;background:#FFFBF0"><div style="font-weight:800;font-size:13px;margin-bottom:3px;color:#8C6A1A">École</div><div style="font-size:18px;font-weight:900;color:#C9972A;margin-bottom:10px">50 000 <span style="font-size:11px;font-weight:400">CDF/mois</span></div><div style="font-size:11px;display:flex;flex-direction:column;gap:5px;color:#6B4E1A"><div>✓ Classe entière</div><div>✓ Tableau prof</div><div>✓ Tout Premium</div><div>✓ Rapport mensuel</div></div></div>
        </div>
        <div style="background:#F0FDF4;border:1px solid #BBF7D0;border-radius:10px;padding:11px 14px;font-size:12px;color:#166534;display:flex;gap:8px;align-items:center">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16A34A" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          Paiement via <strong>M-Pesa</strong>, <strong>Airtel Money</strong> ou <strong>Orange Money</strong>. Annulable à tout moment.
        </div>
      </div>
      <div class="ob-slide ob-right" style="text-align:center">
        <div class="ob-icon" style="background:linear-gradient(135deg,#22C55E,#16A34A)">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <h2 style="font-family:'Manrope',sans-serif;font-size:22px;font-weight:800;margin-bottom:10px">Vous êtes prêt·e, <?= e($user['prenom']) ?> !</h2>
        <p style="color:#6B7280;font-size:13px;line-height:1.7;max-width:380px;margin:0 auto 24px">Votre premier examen prend 5 minutes. C'est le meilleur moyen de connaître votre niveau de départ.</p>
        <div style="display:flex;flex-direction:column;gap:10px;max-width:300px;margin:0 auto">
          <a href="/reussiteplus/examen.php" onclick="obClose()" style="display:flex;align-items:center;justify-content:center;gap:8px;background:#007A5E;color:#fff;border-radius:12px;padding:14px;font-weight:700;font-size:14px;text-decoration:none">Passer mon premier examen</a>
          <a href="/reussiteplus/tarifs.php" style="display:flex;align-items:center;justify-content:center;gap:8px;background:#F1F5F9;color:#007A5E;border-radius:12px;padding:12px;font-weight:600;font-size:13px;text-decoration:none">Voir les plans Premium</a>
          <button onclick="obClose()" style="background:none;border:none;color:#9CA3AF;font-size:12px;cursor:pointer;padding:6px">Continuer gratuitement</button>
        </div>
      </div>
    </div>
    <div class="ob-footer">
      <div class="ob-dots">
        <button class="ob-dot on" onclick="obGoto(0)"></button>
        <button class="ob-dot" onclick="obGoto(1)"></button>
        <button class="ob-dot" onclick="obGoto(2)"></button>
      </div>
      <div style="display:flex;gap:8px">
        <button class="ob-btn ob-btn-sec" id="ob-back" onclick="obGoto(window._obCur-1)" style="display:none">← Retour</button>
        <button class="ob-btn ob-btn-pri" id="ob-next" onclick="obGoto(window._obCur+1)">Suivant →</button>
      </div>
    </div>
  </div>
</div>
<script>
(function(){
  var T=3,cur=0;
  window._obCur=0;
  var slides=document.querySelectorAll('#ob-slides .ob-slide');
  var dots=document.querySelectorAll('.ob-dot');
  window.obGoto=function(n){
    if(n<0||n>=T)return;
    var p=cur;
    slides[p].className='ob-slide '+(n>p?'ob-left':'ob-right');
    cur=n; window._obCur=n;
    slides[n].className='ob-slide ob-active';
    dots[p].classList.remove('on'); dots[n].classList.add('on');
    document.getElementById('ob-fill').style.width=((n+1)/T*100)+'%';
    document.getElementById('ob-back').style.display=n>0?'':'none';
    var nb=document.getElementById('ob-next');
    nb.style.display=n===T-1?'none':'';
  };
  window.obClose=function(){
    var bd=document.getElementById('ob-bd');
    bd.style.transition='opacity .25s'; bd.style.opacity='0';
    setTimeout(function(){bd.remove()},270);
    if(history.replaceState){var u=new URL(location.href);u.searchParams.delete('welcome');history.replaceState({},'',u);}
  };
  document.getElementById('ob-bd').addEventListener('click',function(e){if(e.target===this)obClose();});
  document.addEventListener('keydown',function(e){if(e.key==='ArrowRight')obGoto(cur+1);if(e.key==='ArrowLeft')obGoto(cur-1);if(e.key==='Escape')obClose();});
})();
</script>
<?php endif; ?>



<?php if (in_array($user['plan'] ?? 'GRATUIT', ['PREMIUM','ECOLE'])): ?>
<!-- IA FLOTANT MODERNE -->
<button id="ia-fab" class="ia-fab" title="Coach IA">
  <span class="ia-fab-avatar">
    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 15s1.5 2 4 2 4-2 4-2"/><path d="M9 9h.01"/><path d="M15 9h.01"/></svg>
  </span>
</button>

<div id="ia-modal" class="ia-modal" data-ia-managed="1">
  <div class="ia-modal-card">
    <div class="ia-modal-header">
      <span class="ia-avatar-lg">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 15s1.5 2 4 2 4-2 4-2"/><path d="M9 9h.01"/><path d="M15 9h.01"/></svg>
      </span>
      <div class="ia-header-info">
        <span class="ia-header-title">Coach IA</span>
        <span class="ia-header-badge">Premium</span>
      </div>
      <select id="ia-tone" class="ia-tone">
        <option value="motivant">Motivant</option>
        <option value="strict">Strict</option>
        <option value="humoristique">Humoristique</option>
      </select>
      <div class="ia-header-actions">
        <button id="ia-export" class="ia-action" title="Exporter PDF">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="13" x2="12" y2="19"/><polyline points="9 16 12 19 15 16"/></svg>
        </button>
        <button id="ia-clear" class="ia-action ia-action-warn" title="Effacer">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
        </button>
        <button id="ia-close" class="ia-action ia-action-close" title="Fermer">&times;</button>
      </div>
    </div>
    <div id="ia-stats" class="ia-stats"></div>
    <div id="ia-chat-body" class="ia-chat-body"></div>
    <div class="ia-suggestions-wrap">
      <div id="ia-suggestions" class="ia-suggestions"></div>
      <button id="ia-analyse" type="button" class="ia-analyse">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
        Générer un plan de révision personnalisé
      </button>
    </div>
    <form id="ia-chat-form" class="ia-chat-form">
      <input id="ia-chat-input" type="text" placeholder="Posez votre question…" autocomplete="off" class="ia-chat-input" />
      <button type="submit" class="ia-send">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
      </button>
    </form>
  </div>
</div>

<script>
// ── Coach IA — Dashboard ─────────────────────────────────────
var iaHistory  = [];
var iaTone     = 'motivant';
var iaLoading  = false;
var iaStartTime = null;
var iaModal    = document.getElementById('ia-modal');
var iaChatBody = document.getElementById('ia-chat-body');

var IA_SVG = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 15s1.5 2 4 2 4-2 4-2"/><path d="M9 9h.01"/><path d="M15 9h.01"/></svg>';

// ── Bulle image Wikipedia ────────────────────────────────────
function makeImageMsg(apiUrl, caption) {
  var wrap   = document.createElement('div');
  wrap.className = 'ia-msg ia-msg-bot';
  var avatar = document.createElement('div');
  avatar.className = 'ia-msg-avatar';
  avatar.innerHTML = IA_SVG;
  var bubble = document.createElement('div');
  bubble.className = 'ia-msg-bubble ia-msg-image-bubble';

  var cap = document.createElement('p');
  cap.className = 'ia-img-caption';
  cap.innerHTML = mdToHtml(caption);
  bubble.appendChild(cap);

  var loader = document.createElement('div');
  loader.className = 'ia-img-loader';
  loader.innerHTML = '<div class="ia-typing"><span></span><span></span><span></span></div><span>Recherche d\'illustration…</span>';
  bubble.appendChild(loader);

  wrap.appendChild(avatar);
  wrap.appendChild(bubble);

  fetch(apiUrl)
    .then(function(r) { return r.json(); })
    .then(function(data) {
      loader.remove();
      if (!data.success || !data.url) {
        bubble.innerHTML += '<p style="color:#C9342A;font-size:12px">⚠ Aucune illustration trouvée. Essaie un autre terme.</p>';
        return;
      }
      var img = document.createElement('img');
      img.className = 'ia-gen-img';
      img.alt = data.title;
      img.src = data.url;
      bubble.appendChild(img);

      if (data.desc) {
        var desc = document.createElement('p');
        desc.style.cssText = 'font-size:11.5px;color:#6B7280;margin:5px 0 2px;line-height:1.5';
        desc.textContent = data.desc + '…';
        bubble.appendChild(desc);
      }
      var footer = document.createElement('div');
      footer.style.cssText = 'display:flex;align-items:center;justify-content:space-between;margin-top:4px';
      footer.innerHTML = '<span style="font-size:10px;color:#9CA3AF;font-style:italic">Source : Wikipedia (libre de droits)</span>'
        + '<a href="' + data.wiki + '" target="_blank" class="ia-img-dl">Consulter l\'article</a>';
      bubble.appendChild(footer);
      iaChatBody.scrollTop = iaChatBody.scrollHeight;
    })
    .catch(function() {
      loader.innerHTML = '<span style="color:#C9342A;font-size:12px">⚠ Erreur de connexion.</span>';
    });

  return wrap;
}

// ── Markdown → HTML ──────────────────────────────────────────
function mdToHtml(t) {
  if (!t) return '';
  t = t.replace(/^### (.+)$/gm, '<h4 class="ia-md-h3">$1</h4>');
  t = t.replace(/^## (.+)$/gm,  '<h3 class="ia-md-h2">$1</h3>');
  t = t.replace(/^# (.+)$/gm,   '<h3 class="ia-md-h2">$1</h3>');
  t = t.replace(/\*\*\*(.+?)\*\*\*/g, '<strong><em>$1</em></strong>');
  t = t.replace(/\*\*(.+?)\*\*/g,     '<strong>$1</strong>');
  t = t.replace(/\*(.+?)\*/g,         '<em>$1</em>');
  t = t.replace(/^[-•] (.+)$/gm,      '<li>$1</li>');
  t = t.replace(/^(\d+)\. (.+)$/gm,   '<li>$2</li>');
  t = t.replace(/(<li>[\s\S]*?<\/li>\n?)+/g, '<ul class="ia-md-ul">$&</ul>');
  t = t.replace(/\n{2,}/g, '<br><br>');
  t = t.replace(/\n/g, '<br>');
  return t;
}

// ── Stats ────────────────────────────────────────────────────
function updateIaStats() {
  var el = document.getElementById('ia-stats');
  var n  = Math.floor(iaHistory.length / 2);
  el.textContent = n > 0 ? (n + ' échange' + (n > 1 ? 's' : '') + ' — session en cours') : '';
}

// ── Créer une bulle message ───────────────────────────────────
function makeMsg(role, htmlContent, animate) {
  var isUser = role === 'user';
  var wrap   = document.createElement('div');
  wrap.className = 'ia-msg ' + (isUser ? 'ia-msg-user' : 'ia-msg-bot');

  var avatar = document.createElement('div');
  avatar.className = 'ia-msg-avatar';
  if (isUser) {
    var ini = (window.userPrenom && window.userPrenom[0]) ? window.userPrenom[0].toUpperCase() : '?';
    avatar.textContent = ini;
    avatar.style.background = 'linear-gradient(135deg,var(--gold),var(--gold-dark))';
  } else {
    avatar.innerHTML = IA_SVG;
  }

  var bubble = document.createElement('div');
  bubble.className = 'ia-msg-bubble';

  if (!isUser && animate) {
    // Effet machine à écrire : texte d'abord, HTML Markdown à la fin
    var plain = htmlContent.replace(/<[^>]+>/g, '');
    var i = 0;
    bubble.textContent = '';
    (function tick() {
      if (i >= plain.length) {
        bubble.innerHTML = htmlContent;
        iaChatBody.scrollTop = iaChatBody.scrollHeight;
        return;
      }
      bubble.textContent += plain[i++];
      iaChatBody.scrollTop = iaChatBody.scrollHeight;
      setTimeout(tick, 10);
    })();
  } else {
    if (isUser) bubble.textContent = htmlContent;
    else bubble.innerHTML = htmlContent;
  }

  // Bouton lecture vocale (IA uniquement)
  if (!isUser) {
    var tts = document.createElement('button');
    tts.className = 'ia-tts-btn';
    tts.title = 'Écouter';
    tts.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>';
    var rawText = htmlContent.replace(/<[^>]+>/g, '');
    tts.onclick = function() { var u = new SpeechSynthesisUtterance(rawText); u.lang = 'fr-FR'; u.rate = 1.02; speechSynthesis.speak(u); };
    bubble.appendChild(tts);
  }

  wrap.appendChild(avatar);
  wrap.appendChild(bubble);
  return wrap;
}

// ── Indicateur de frappe ─────────────────────────────────────
function showTyping() {
  var wrap   = document.createElement('div');
  wrap.className = 'ia-msg ia-msg-bot';
  var avatar = document.createElement('div');
  avatar.className = 'ia-msg-avatar';
  avatar.innerHTML = IA_SVG;
  var dots   = document.createElement('div');
  dots.className = 'ia-typing';
  dots.innerHTML  = '<span></span><span></span><span></span>';
  wrap.appendChild(avatar);
  wrap.appendChild(dots);
  iaChatBody.appendChild(wrap);
  iaChatBody.scrollTop = iaChatBody.scrollHeight;
  return wrap;
}

// ── Écran d'accueil ───────────────────────────────────────────
function showWelcomeIa() {
  var prenom = window.userPrenom || '';
  iaChatBody.innerHTML =
    '<div class="ia-welcome">' +
    '<div class="ia-welcome-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a10 10 0 1 1 0 20A10 10 0 0 1 12 2z"/><path d="M12 8v4l3 3"/></svg></div>' +
    '<h3>' + (prenom ? 'Bienvenue, ' + prenom : 'Coach IA RÉUSSITE+') + '</h3>' +
    '<p>Posez votre question sur l\'EXETAT, vos cours ou exercices.<br>Je réponds en m\'appuyant sur le programme scolaire RDC.</p>' +
    '</div>';
}

// ── Rendu complet de l'historique ─────────────────────────────
function renderIaHistory() {
  iaChatBody.innerHTML = '';
  if (!iaHistory.length) { showWelcomeIa(); updateIaStats(); return; }

  iaHistory.forEach(function(msg, idx) {
    var isLast = (idx === iaHistory.length - 1);
    var html   = msg.role === 'user' ? msg.content : mdToHtml(msg.content);
    iaChatBody.appendChild(makeMsg(msg.role, html, isLast && msg.role === 'assistant'));
  });

  iaChatBody.scrollTop = iaChatBody.scrollHeight;
  updateIaStats();
}

// ── Suggestions ───────────────────────────────────────────────
function renderIaSuggestions() {
  var cont = document.getElementById('ia-suggestions');
  cont.style.display = '';
  cont.innerHTML = '';
  ['Méthodes de révision efficaces pour l\'EXETAT',
   'Expliquer la photosynthèse',
   'Résoudre : 2x + 5 = 13',
   'Différence entre mitose et méiose',
   'Règles d\'accord du participe passé',
   'Plan de révision sur 7 jours'].forEach(function(q) {
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'ia-suggestion';
    btn.textContent = q;
    btn.onclick = function() {
      document.getElementById('ia-chat-input').value = q;
      document.getElementById('ia-chat-input').focus();
      cont.style.display = 'none';
    };
    cont.appendChild(btn);
  });
}

// ── Ouvrir / fermer ───────────────────────────────────────────
document.getElementById('ia-fab').onclick = function(e) {
  e.stopPropagation();
  iaModal.classList.add('open');
  if (!iaStartTime) iaStartTime = Date.now();
  setTimeout(function() {
    if (!iaChatBody.querySelector('.ia-msg, .ia-welcome')) showWelcomeIa();
    renderIaSuggestions();
    document.getElementById('ia-chat-input').focus();
  }, 80);
};
document.getElementById('ia-close').onclick = function() { iaModal.classList.remove('open'); };
iaModal.onclick = function(e) { if (e.target === this) this.classList.remove('open'); };
document.addEventListener('click', function(e) {
  if (!iaModal.contains(e.target) && !document.getElementById('ia-fab').contains(e.target))
    iaModal.classList.remove('open');
});
document.getElementById('ia-tone').onchange = function() { iaTone = this.value; };

// ── Export PDF — Rapport premium via IAPdf ─────────────────
document.getElementById('ia-export').onclick = function() {
  if (!iaHistory.length) { alert('Aucune conversation à exporter.'); return; }
  if (typeof IAPdf === 'undefined') { alert('Générateur PDF non chargé.'); return; }
  IAPdf.open(iaHistory, 'Session Coach IA', window.userPrenom || 'Élève');
};

// ── Effacer historique ────────────────────────────────────────
document.getElementById('ia-clear').onclick = function() {
  if (!confirm('Effacer toute la conversation IA ?')) return;
  iaHistory = [];
  showWelcomeIa();
  renderIaSuggestions();
  updateIaStats();
  fetch('/reussiteplus/api/ia_chat.php', {
    method: 'POST', headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'clear_history'})
  }).catch(function(){});
};

// ── Analyse & plan de révision ────────────────────────────────
document.getElementById('ia-analyse').onclick = async function() {
  if (iaHistory.length < 2) {
    alert('Commence d\'abord une conversation pour générer une analyse !'); return;
  }
  var btn = this;
  var orig = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" style="animation:ia-spin .8s linear infinite"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Analyse en cours…';
  if (!document.getElementById('ia-spin-style')) {
    var st = document.createElement('style'); st.id = 'ia-spin-style';
    st.textContent = '@keyframes ia-spin{to{transform:rotate(360deg)}}';
    document.head.appendChild(st);
  }
  var typing = showTyping();
  try {
    var res  = await fetch('/reussiteplus/api/ia_chat.php', {
      method: 'POST', headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({action: 'analyse', history: iaHistory})
    });
    var data = await res.json();
    typing.remove();
    iaHistory.push({role: 'assistant', content: data.reply || 'Analyse indisponible.'});
    iaChatBody.appendChild(makeMsg('assistant', mdToHtml(data.reply || 'Analyse indisponible.'), false));
    iaChatBody.scrollTop = iaChatBody.scrollHeight;
    updateIaStats();
  } catch(_) {
    typing.remove();
    iaChatBody.innerHTML += '<div class="ia-msg ia-msg-bot"><div class="ia-msg-bubble" style="background:#FEF0EF;color:#C9342A;border-color:#FCA5A5">Erreur de connexion. Réessaie.</div></div>';
  }
  btn.disabled = false; btn.innerHTML = orig;
};

// ── Envoi d'un message ────────────────────────────────────────
document.getElementById('ia-chat-form').onsubmit = async function(e) {
  e.preventDefault();
  if (iaLoading) return;
  var input = document.getElementById('ia-chat-input');
  var msg   = input.value.trim();
  var forbidden = /sexe|porn|nude|violence|drogue|suicide|meurtre|terrorisme|racisme|bombe|attentat/i;
  if (forbidden.test(msg)) { alert('Cette question n\'est pas autorisée sur RÉUSSITE+.'); return; }
  if (!msg) return;

  iaChatBody.querySelector('.ia-welcome')?.remove();
  document.getElementById('ia-suggestions').style.display = 'none';

  iaHistory.push({role: 'user', content: msg});
  iaChatBody.appendChild(makeMsg('user', msg, false));
  input.value = '';
  iaChatBody.scrollTop = iaChatBody.scrollHeight;

  iaLoading = true;
  var typing = showTyping();
  var isExercice = /\b(calculer|résous|résoudre|équation|problème|exercice|simplifie|factorise|montrer que)\b|\d+\s*[+\-*\/=]/i.test(msg);
  try {
    var res  = await fetch('/reussiteplus/api/ia_chat.php', {
      method: 'POST', headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({message: msg, history: iaHistory.slice(0, -1), exercice: isExercice ? 1 : 0, tone: iaTone})
    });
    var data = await res.json();
    typing.remove();
    if (data.type === 'image' || data.type === 'image_search') {
      iaHistory.push({role: 'assistant', content: data.reply || ''});
      iaChatBody.appendChild(makeImageMsg(data.image_url, data.reply || ''));
    } else {
      var reply = data.reply || 'Erreur IA';
      iaHistory.push({role: 'assistant', content: reply});
      iaChatBody.appendChild(makeMsg('assistant', mdToHtml(reply), true));
    }
    iaChatBody.scrollTop = iaChatBody.scrollHeight;
    updateIaStats();
  } catch(_) {
    typing.remove();
    iaChatBody.innerHTML += '<div class="ia-msg ia-msg-bot"><div class="ia-msg-bubble" style="background:#FEF0EF;color:#C9342A;border-color:#FCA5A5">Erreur de connexion. Réessaie.</div></div>';
  }
  iaLoading = false;
};
</script>
<?php endif; // fin bloc IA Premium ?>

<?php
// Afficher une publicité pour les utilisateurs Gratuit
if ($user['plan'] === 'GRATUIT') {
    require_once __DIR__ . '/includes/ads.php';
    echo render_ad('FEED', 'dashboard');
}
?>
<?php include __DIR__ . '/includes/footer_app.php'; ?>

