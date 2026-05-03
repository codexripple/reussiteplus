<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Tableau de bord';
$pageActive = 'dashboard';

$user  = require_login();
$stats = get_user_stats($user['id']);

// Message de bienvenue à la première connexion
$welcome = isset($_GET['welcome']);

// Derniers examens passés
$recentSessions = dbAll(
    "SELECT es.*, m.nom as matiere_nom, m.couleur as matiere_couleur
     FROM exam_sessions es
     LEFT JOIN matieres m ON es.matiere_id = m.id
     WHERE es.user_id = ? AND es.statut = 'TERMINE'
     ORDER BY es.finished_at DESC LIMIT 5",
    [$user['id']]
);

// Progression par matière
$progressMatieres = dbAll(
    "SELECT up.*, m.nom, m.couleur, m.icone
     FROM user_progression up
     JOIN matieres m ON up.matiere_id = m.id
     WHERE up.user_id = ?
     ORDER BY up.score_moyen DESC LIMIT 6",
    [$user['id']]
);

// Archives récentes (pour recommandations)
$archivesRec = dbAll(
    "SELECT a.*, m.nom as matiere_nom, m.couleur
     FROM archives a
     JOIN matieres m ON a.matiere_id = m.id
     WHERE a.status = 'PUBLIE' AND (a.premium_only = 0 OR ? != 'GRATUIT')
     ORDER BY a.vues DESC LIMIT 6",
    [$user['plan']]
);

// Activité des 7 derniers jours
$activite7j = dbAll(
    "SELECT date_act, examens, questions FROM activite_journaliere
     WHERE user_id = ? AND date_act >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     ORDER BY date_act ASC",
    [$user['id']]
);

// Notifications non lues
$notificationsRecentes = dbAll(
    "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5",
    [$user['id']]
);

// Vérifier expiration plan
$planExpire = $user['plan_expire_at'] ? strtotime($user['plan_expire_at']) : null;
$planJoursRestants = $planExpire ? max(0, (int)(($planExpire - time()) / 86400)) : null;

include __DIR__ . '/includes/header_app.php';
?>

<?php if ($welcome): ?>
<div class="alert alert-success" style="margin-bottom:24px">
  🎉 Bienvenue sur RÉUSSITE+, <?= e($user['prenom']) ?> ! Votre compte est prêt. Commencez dès maintenant.
  <a href="/reussiteplus/tarifs.php" style="font-weight:600;color:var(--primary-dark);margin-left:8px">Découvrir le Premium →</a>
</div>
<?php endif; ?>

<?php if ($user['plan'] === 'GRATUIT'): ?>
<div style="background:linear-gradient(135deg,#F5E6C0,#FFF7E6);border:1px solid rgba(201,151,42,0.3);border-radius:var(--radius-lg);padding:16px 20px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
  <div>
    <strong style="color:var(--gold-dark)">⭐ Passez à Premium pour un accès illimité</strong>
    <div style="font-size:13px;color:var(--gris-600);margin-top:3px">
      <?= $user['examens_mois'] ?? 0 ?>/<?= FREE_EXAMS_PER_MONTH ?> examens utilisés ce mois •
      Archives complètes • Corrigés détaillés • Plan de révision IA
    </div>
  </div>
  <a href="/reussiteplus/tarifs.php" class="btn btn-gold btn-sm">Voir les offres →</a>
</div>
<?php elseif ($planJoursRestants !== null && $planJoursRestants <= 7): ?>
<div class="alert alert-warning">
  ⏰ Votre plan <?= e($user['plan']) ?> expire dans <strong><?= $planJoursRestants ?> jour(s)</strong>.
  <a href="/reussiteplus/abonnement.php" style="font-weight:600;margin-left:8px">Renouveler →</a>
</div>
<?php endif; ?>

<!-- STATS -->
<div class="stats-grid">
  <div class="stat-card green">
    <div class="stat-label">📊 Score moyen</div>
    <div class="stat-value" style="color:<?= score_couleur((float)($user['score_moyen'] ?? 0)) ?>">
      <?= number_format((float)($user['score_moyen'] ?? 0), 1) ?>%
    </div>
    <div class="stat-sub"><?= score_label((float)($user['score_moyen'] ?? 0)) ?></div>
  </div>
  <div class="stat-card gold">
    <div class="stat-label">✏️ Examens passés</div>
    <div class="stat-value"><?= number_format((int)($user['total_examens'] ?? 0)) ?></div>
    <div class="stat-sub">Total cumulé</div>
  </div>
  <div class="stat-card bleu">
    <div class="stat-label">🧠 Questions répondues</div>
    <div class="stat-value"><?= number_format((int)($user['total_questions'] ?? 0)) ?></div>
    <div class="stat-sub">Total cumulé</div>
  </div>
  <div class="stat-card rouge">
    <div class="stat-label">🔥 Série actuelle</div>
    <div class="stat-value"><?= (int)($stats['streak_actuel'] ?? 0) ?></div>
    <div class="stat-sub">jours consécutifs</div>
  </div>
</div>

<!-- Activité 7 jours + Progression Matières -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">

  <!-- Activité -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">📅 Activité (7 derniers jours)</div>
    </div>
    <div id="activity-chart" style="display:flex;align-items:flex-end;gap:8px;height:80px">
      <?php
      $actMap = [];
      foreach ($activite7j as $a) $actMap[$a['date_act']] = $a;
      $maxEx  = max(1, max(array_map(fn($a) => $a['examens'], $activite7j ?: [['examens'=>0]])));
      for ($i = 6; $i >= 0; $i--):
          $d    = date('Y-m-d', strtotime("-{$i} days"));
          $ex   = $actMap[$d]['examens'] ?? 0;
          $pct  = $ex > 0 ? max(10, (int)(($ex / $maxEx) * 100)) : 4;
          $day  = date('D', strtotime($d));
          $days = ['Mon'=>'L','Tue'=>'M','Wed'=>'M','Thu'=>'J','Fri'=>'V','Sat'=>'S','Sun'=>'D'];
          $dl   = $days[$day] ?? $day;
      ?>
      <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px">
        <div style="width:100%;height:<?= $pct ?>%;background:<?= $ex > 0 ? 'var(--primary)' : 'var(--gris-200)' ?>;border-radius:4px;min-height:4px;transition:height .5s" title="<?= $ex ?> exam"></div>
        <div style="font-size:10px;color:var(--gris-500)"><?= $dl ?></div>
      </div>
      <?php endfor; ?>
    </div>
  </div>

  <!-- Progression par matière -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">📚 Progression par matière</div>
      <a href="/reussiteplus/progression.php" class="section-link">Tout voir →</a>
    </div>
    <?php if ($progressMatieres): ?>
      <?php foreach (array_slice($progressMatieres, 0, 4) as $pm): ?>
      <div style="margin-bottom:10px">
        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px">
          <span style="color:var(--gris-700)"><?= e($pm['icone'] ?? '📚') ?> <?= e($pm['nom']) ?></span>
          <span style="font-weight:600;color:<?= score_couleur((float)$pm['score_moyen']) ?>"><?= number_format((float)$pm['score_moyen'],1) ?>%</span>
        </div>
        <div class="progress-bar">
          <div class="progress-bar-fill" style="width:<?= min(100, (float)$pm['score_moyen']) ?>%;background:<?= $pm['couleur'] ?? 'var(--primary)' ?>"></div>
        </div>
      </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div style="text-align:center;padding:20px;color:var(--gris-500);font-size:13px">
        📊 Passez vos premiers examens pour voir votre progression
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Derniers examens -->
<?php if ($recentSessions): ?>
<div style="margin-bottom:24px">
  <div class="section-header">
    <div class="section-title">🕐 Derniers examens passés</div>
    <a href="/reussiteplus/progression.php" class="section-link">Historique complet →</a>
  </div>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>Matière</th><th>Type</th><th>Score</th><th>Temps</th><th>Date</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentSessions as $s):
          $pct = (float)($s['pourcentage'] ?? 0);
          $mins = floor(($s['temps_passe'] ?? 0) / 60);
          $secs = ($s['temps_passe'] ?? 0) % 60;
        ?>
        <tr>
          <td>
            <span style="display:inline-flex;align-items:center;gap:6px">
              <span style="width:10px;height:10px;border-radius:50%;background:<?= e($s['matiere_couleur'] ?? '#007A5E') ?>;display:inline-block"></span>
              <?= e($s['matiere_nom'] ?? $s['titre'] ?? 'Examen') ?>
            </span>
          </td>
          <td><span class="badge badge-gray"><?= e($s['exam_type'] ?? '—') ?></span></td>
          <td>
            <span style="font-weight:700;color:<?= score_couleur($pct) ?>"><?= number_format($pct,1) ?>%</span>
            <span style="font-size:11px;color:var(--gris-500)"> <?= score_label($pct) ?></span>
          </td>
          <td style="font-size:12px;color:var(--gris-600)"><?= $mins ?>m <?= $secs ?>s</td>
          <td style="font-size:12px;color:var(--gris-500)"><?= date('d/m/Y', strtotime($s['finished_at'] ?? $s['started_at'])) ?></td>
          <td><a href="/reussiteplus/resultat.php?session=<?= e($s['id']) ?>" class="btn btn-ghost btn-sm">Résultats</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Archives recommandées -->
<div>
  <div class="section-header">
    <div class="section-title">📁 Archives recommandées</div>
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
        <div class="exam-meta">
          <span class="exam-meta-item">📚 <?= e($arc['matiere_nom']) ?></span>
          <span class="exam-meta-item">👁️ <?= number_format($arc['vues']) ?> vues</span>
        </div>
        <?php if ($arc['premium_only'] && $user['plan'] === 'GRATUIT'): ?>
        <div style="margin-top:8px;font-size:11px;color:var(--gold-dark)">⭐ Réservé aux membres Premium</div>
        <?php endif; ?>
      </div>
      <div class="exam-card-footer">
        <a href="/reussiteplus/archives.php?id=<?= e($arc['id']) ?>" class="btn btn-primary btn-sm" style="flex:1;justify-content:center">
          Consulter
        </a>
        <?php if ($arc['corrige_url']): ?>
        <a href="<?= e($arc['corrige_url']) ?>" class="btn btn-ghost btn-sm" target="_blank">Corrigé</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="card" style="text-align:center;padding:40px">
    <div style="font-size:48px;margin-bottom:12px">📚</div>
    <div style="font-size:15px;font-weight:600;margin-bottom:8px">Aucune archive disponible</div>
    <div style="font-size:13px;color:var(--gris-500)">Les archives seront ajoutées prochainement par l'équipe.</div>
  </div>
  <?php endif; ?>
</div>

<!-- Actions rapides -->
<div style="margin-top:24px;display:grid;grid-template-columns:repeat(3,1fr);gap:16px">
  <a href="/reussiteplus/examen.php" class="card" style="text-align:center;cursor:pointer;text-decoration:none;transition:all .2s" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform=''">
    <div style="font-size:36px;margin-bottom:10px">✏️</div>
    <div style="font-family:var(--font-display);font-weight:700;margin-bottom:4px">Passer un examen</div>
    <div style="font-size:12px;color:var(--gris-500)">Simuler les conditions réelles</div>
  </a>
  <a href="/reussiteplus/questions.php" class="card" style="text-align:center;cursor:pointer;text-decoration:none;transition:all .2s" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform=''">
    <div style="font-size:36px;margin-bottom:10px">🧠</div>
    <div style="font-family:var(--font-display);font-weight:700;margin-bottom:4px">S'entraîner</div>
    <div style="font-size:12px;color:var(--gris-500)">Banque de 15 000+ questions</div>
  </a>
  <a href="/reussiteplus/archives.php" class="card" style="text-align:center;cursor:pointer;text-decoration:none;transition:all .2s" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform=''">
    <div style="font-size:36px;margin-bottom:10px">📁</div>
    <div style="font-family:var(--font-display);font-weight:700;margin-bottom:4px">Archives</div>
    <div style="font-size:12px;color:var(--gris-500)">Sujets & corrigés officiels</div>
  </a>
</div>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
