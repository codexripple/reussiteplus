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


<?php if ($user['plan'] === 'GRATUIT'): ?>
<div style="background:linear-gradient(135deg,#F5E6C0,#FFF7E6);border:1px solid rgba(201,151,42,0.3);border-radius:var(--radius-lg);padding:16px 20px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
  <div>
    <strong style="color:var(--gold-dark)"><i data-lucide="star" style="width:14px;height:14px;vertical-align:-2px;stroke:var(--gold-dark)"></i> Passez à Premium pour un accès illimité</strong>
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
    <div class="stat-label"><i data-lucide="bar-chart-2"></i> Score moyen</div>
    <div class="stat-value" style="color:<?= score_couleur((float)($user['score_moyen'] ?? 0)) ?>">
      <?= number_format((float)($user['score_moyen'] ?? 0), 1) ?>%
    </div>
    <div class="stat-sub"><?= score_label((float)($user['score_moyen'] ?? 0)) ?></div>
  </div>
  <div class="stat-card gold">
    <div class="stat-label"><i data-lucide="file-check"></i> Examens passés</div>
    <div class="stat-value"><?= number_format((int)($user['total_examens'] ?? 0)) ?></div>
    <div class="stat-sub">Total cumulé</div>
  </div>
  <div class="stat-card bleu">
    <div class="stat-label"><i data-lucide="lightbulb"></i> Questions répondues</div>
    <div class="stat-value"><?= number_format((int)($user['total_questions'] ?? 0)) ?></div>
    <div class="stat-sub">Total cumulé</div>
  </div>
  <div class="stat-card rouge">
    <div class="stat-label"><i data-lucide="flame"></i> Série actuelle</div>
    <div class="stat-value"><?= (int)($stats['streak_actuel'] ?? 0) ?></div>
    <div class="stat-sub">jours consécutifs</div>
  </div>
</div>

<!-- Activité 7 jours + Progression Matières -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">

  <!-- Activité -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i data-lucide="calendar-days"></i> Activité (7 derniers jours)</div>
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
      <div class="card-title"><i data-lucide="trending-up"></i> Progression par matière</div>
      <a href="/reussiteplus/progression.php" class="section-link">Tout voir →</a>
    </div>
    <?php if ($progressMatieres): ?>
      <?php foreach (array_slice($progressMatieres, 0, 4) as $pm): ?>
      <div style="margin-bottom:10px">
        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px">
          <span style="color:var(--gris-700);display:flex;align-items:center;gap:5px"><?= matiere_icon($pm['icone'] ?? 'book', 13) ?> <?= e($pm['nom']) ?></span>
          <span style="font-weight:600;color:<?= score_couleur((float)$pm['score_moyen']) ?>"><?= number_format((float)$pm['score_moyen'],1) ?>%</span>
        </div>
        <div class="progress-bar">
          <div class="progress-bar-fill" style="width:<?= min(100, (float)$pm['score_moyen']) ?>%;background:<?= $pm['couleur'] ?? 'var(--primary)' ?>"></div>
        </div>
      </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div style="text-align:center;padding:20px;color:var(--gris-500);font-size:13px">
        <i data-lucide="bar-chart" style="width:16px;height:16px;margin-right:6px;vertical-align:-3px"></i> Passez vos premiers examens pour voir votre progression
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Derniers examens -->
<?php if ($recentSessions): ?>
<div style="margin-bottom:24px">
  <div class="section-header">
    <div class="section-title"><i data-lucide="clock"></i> Derniers examens passés</div>
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
    <div class="section-title"><i data-lucide="folder-open"></i> Archives recommandées</div>
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
          <span class="exam-meta-item"><i data-lucide="book-open" style="width:12px;height:12px;vertical-align:-2px"></i> <?= e($arc['matiere_nom']) ?></span>
          <span class="exam-meta-item"><i data-lucide="eye" style="width:12px;height:12px;vertical-align:-2px"></i> <?= number_format($arc['vues']) ?> vues</span>
        </div>
        <?php if ($arc['premium_only'] && $user['plan'] === 'GRATUIT'): ?>
        <div style="margin-top:8px;font-size:11px;color:var(--gold-dark)"><i data-lucide="star" style="width:12px;height:12px;vertical-align:-2px;stroke:var(--gold-dark)"></i> Réservé aux membres Premium</div>
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
    <div style="margin-bottom:16px;display:flex;justify-content:center"><svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--gris-300)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg></div>
    <div style="font-size:15px;font-weight:600;margin-bottom:8px">Aucune archive disponible</div>
    <div style="font-size:13px;color:var(--gris-500)">Les archives seront ajoutées prochainement par l'équipe.</div>
  </div>
  <?php endif; ?>
</div>

<!-- Actions rapides -->
<div style="margin-top:24px;display:grid;grid-template-columns:repeat(3,1fr);gap:16px">
  <a href="/reussiteplus/examen.php" class="card" style="text-align:center;cursor:pointer;text-decoration:none;transition:all .2s" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform=''">
    <div style="margin-bottom:16px;display:flex;justify-content:center"><svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></div>
    <div style="font-family:var(--font-display);font-weight:700;margin-bottom:4px">Passer un examen</div>
    <div style="font-size:12px;color:var(--gris-500)">Simuler les conditions réelles</div>
  </a>
  <a href="/reussiteplus/questions.php" class="card" style="text-align:center;cursor:pointer;text-decoration:none;transition:all .2s" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform=''">
    <div style="margin-bottom:16px;display:flex;justify-content:center"><svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--bleu)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
    <div style="font-family:var(--font-display);font-weight:700;margin-bottom:4px">S'entraîner</div>
    <div style="font-size:12px;color:var(--gris-500)">Banque de 15 000+ questions</div>
  </a>
  <a href="/reussiteplus/archives.php" class="card" style="text-align:center;cursor:pointer;text-decoration:none;transition:all .2s" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform=''">
    <div style="margin-bottom:16px;display:flex;justify-content:center"><svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--gold)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></div>
    <div style="font-family:var(--font-display);font-weight:700;margin-bottom:4px">Archives</div>
    <div style="font-size:12px;color:var(--gris-500)">Sujets & corrigés officiels</div>
  </a>
</div>

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
.ob-nav{display:flex;gap:8px}
.ob-btn{height:38px;padding:0 20px;border-radius:10px;font-family:inherit;font-size:13px;font-weight:600;cursor:pointer;border:none;display:inline-flex;align-items:center;gap:6px;transition:all .2s}
.ob-btn-sec{background:#F1F5F9;color:#4A5568}.ob-btn-sec:hover{background:#E2E8F0}
.ob-btn-pri{background:#007A5E;color:#fff}.ob-btn-pri:hover{background:#005A45}
.ob-btn-gold{background:linear-gradient(135deg,#C9972A,#F59E0B);color:#fff}.ob-btn-gold:hover{filter:brightness(1.05)}
.ob-icon{width:60px;height:60px;border-radius:16px;display:flex;align-items:center;justify-content:center;margin:0 auto 18px}
.ob-tag{display:inline-flex;align-items:center;gap:5px;background:#F1F5F9;border-radius:8px;padding:6px 12px;font-size:12px;font-weight:600;color:#4A5568}
.ob-plan{border-radius:14px;padding:16px 14px;border:2px solid #E2E8F0;position:relative}
.ob-plan.ob-best{border-color:#007A5E;background:#E8F5F1}
.ob-plan-badge{position:absolute;top:-10px;left:50%;transform:translateX(-50%);background:#007A5E;color:#fff;font-size:10px;font-weight:700;padding:2px 10px;border-radius:10px;white-space:nowrap;font-family:'Poppins',sans-serif}
.ob-check{color:#007A5E;font-size:12px}.ob-cross{color:#CBD5E1;font-size:12px}
.ob-step-row{display:flex;gap:14px;align-items:flex-start}
.ob-step-num{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:15px;flex-shrink:0}
@media(max-width:480px){.ob-slide{padding:24px 20px}.ob-footer{padding:12px 16px}.ob-plan-grid{grid-template-columns:1fr!important}}
</style>

<div id="ob-bd">
  <div id="ob-card">
    <!-- Barre de progression -->
    <div class="ob-topbar"><div class="ob-fill" id="ob-fill" style="width:25%"></div></div>

    <!-- Slides -->
    <div class="ob-slides" id="ob-slides">

      <!-- Slide 0 — Bienvenue -->
      <div class="ob-slide ob-active">
        <div class="ob-icon" style="background:linear-gradient(135deg,#007A5E,#7c3aed)">
          <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
        </div>
        <h2 style="font-family:'Poppins',sans-serif;font-size:22px;font-weight:800;text-align:center;margin-bottom:8px">Bienvenue, <?= e($user['prenom']) ?> !</h2>
        <p style="text-align:center;color:#6B7280;font-size:13px;line-height:1.7;margin-bottom:24px">Votre plateforme de révision intelligente pour réussir l'<strong>ENAFEP</strong>, le <strong>TENASOSP</strong> et l'<strong>Examen d'État</strong> en RDC.</p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <div style="background:#F8FAFC;border-radius:12px;padding:14px 12px;display:flex;gap:10px">
            <div style="width:32px;height:32px;background:#E8F5F1;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#007A5E" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            </div>
            <div><div style="font-weight:700;font-size:12px;margin-bottom:2px">Examens blancs</div><div style="font-size:11px;color:#6B7280">Conditions réelles + corrigés</div></div>
          </div>
          <div style="background:#F8FAFC;border-radius:12px;padding:14px 12px;display:flex;gap:10px">
            <div style="width:32px;height:32px;background:#EEF4FD;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#1E5FAD" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            </div>
            <div><div style="font-weight:700;font-size:12px;margin-bottom:2px">Archives officielles</div><div style="font-size:11px;color:#6B7280">Sujets + corrigés PDF</div></div>
          </div>
          <div style="background:#F8FAFC;border-radius:12px;padding:14px 12px;display:flex;gap:10px">
            <div style="width:32px;height:32px;background:#F5E6C0;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#8C6A1A" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
            </div>
            <div><div style="font-weight:700;font-size:12px;margin-bottom:2px">IA Coach 24h/24</div><div style="font-size:11px;color:#6B7280">Plan de révision sur mesure</div></div>
          </div>
          <div style="background:#F8FAFC;border-radius:12px;padding:14px 12px;display:flex;gap:10px">
            <div style="width:32px;height:32px;background:#FEF0EF;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#C9342A" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
            </div>
            <div><div style="font-weight:700;font-size:12px;margin-bottom:2px">Suivi de progression</div><div style="font-size:11px;color:#6B7280">Points forts &amp; faiblesses</div></div>
          </div>
        </div>
      </div>

      <!-- Slide 1 — Comment ça marche -->
      <div class="ob-slide ob-right">
        <h3 style="font-family:'Poppins',sans-serif;font-size:19px;font-weight:800;text-align:center;margin-bottom:6px">Comment ça marche ?</h3>
        <p style="text-align:center;color:#6B7280;font-size:13px;margin-bottom:24px">3 étapes pour progresser rapidement</p>
        <div style="display:flex;flex-direction:column;gap:18px">
          <div class="ob-step-row">
            <div class="ob-step-num" style="background:linear-gradient(135deg,#007A5E,#00A97F)">1</div>
            <div style="padding-top:4px">
              <div style="font-weight:700;font-size:14px;margin-bottom:3px">Passez un examen blanc</div>
              <div style="font-size:13px;color:#6B7280;line-height:1.6">Choisissez une matière, une durée et répondez aux questions dans les conditions réelles.</div>
            </div>
          </div>
          <div style="width:2px;height:16px;background:#E2E8F0;margin-left:17px"></div>
          <div class="ob-step-row">
            <div class="ob-step-num" style="background:linear-gradient(135deg,#1E5FAD,#7c3aed)">2</div>
            <div style="padding-top:4px">
              <div style="font-weight:700;font-size:14px;margin-bottom:3px">Analysez vos résultats</div>
              <div style="font-size:13px;color:#6B7280;line-height:1.6">Chaque erreur est expliquée. Vous comprenez pourquoi vous avez raté et comment faire mieux.</div>
            </div>
          </div>
          <div style="width:2px;height:16px;background:#E2E8F0;margin-left:17px"></div>
          <div class="ob-step-row">
            <div class="ob-step-num" style="background:linear-gradient(135deg,#C9972A,#F59E0B)">3</div>
            <div style="padding-top:4px">
              <div style="font-weight:700;font-size:14px;margin-bottom:3px">Laissez l'IA vous guider</div>
              <div style="font-size:13px;color:#6B7280;line-height:1.6">L'assistant IA génère un plan de révision 7 jours adapté à vos points faibles.</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Slide 2 — Plans -->
      <div class="ob-slide ob-right">
        <h3 style="font-family:'Poppins',sans-serif;font-size:19px;font-weight:800;text-align:center;margin-bottom:6px">Choisissez votre plan</h3>
        <p style="text-align:center;color:#6B7280;font-size:13px;margin-bottom:20px">Commencez gratuitement, évoluez quand vous voulez</p>
        <div class="ob-plan-grid" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:16px">
          <div class="ob-plan">
            <div style="font-weight:800;font-size:13px;margin-bottom:3px;color:#4A5568">Gratuit</div>
            <div style="font-size:20px;font-weight:900;color:#4A5568;margin-bottom:10px">Gratuit</div>
            <div style="font-size:11px;display:flex;flex-direction:column;gap:5px;color:#4A5568">
              <div class="ob-check">✓ 5 examens/mois</div>
              <div class="ob-check">✓ Questions basiques</div>
              <div class="ob-cross">✗ Archives</div>
              <div class="ob-cross">✗ IA Coach</div>
            </div>
          </div>
          <div class="ob-plan ob-best">
            <div class="ob-plan-badge">POPULAIRE</div>
            <div style="font-weight:800;font-size:13px;margin-bottom:3px;color:#005A45">Premium</div>
            <div style="font-size:20px;font-weight:900;color:#007A5E;margin-bottom:10px">10 000 <span style="font-size:11px;font-weight:400">CDF/mois</span></div>
            <div style="font-size:11px;display:flex;flex-direction:column;gap:5px;color:#2E4A3A">
              <div class="ob-check">✓ Examens illimités</div>
              <div class="ob-check">✓ Toutes archives</div>
              <div class="ob-check">✓ Corrigés PDF</div>
              <div class="ob-check">✓ IA Coach</div>
            </div>
          </div>
          <div class="ob-plan" style="border-color:#C9972A;background:#FFFBF0">
            <div style="font-weight:800;font-size:13px;margin-bottom:3px;color:#8C6A1A">École</div>
            <div style="font-size:20px;font-weight:900;color:#C9972A;margin-bottom:10px">50 000 <span style="font-size:11px;font-weight:400">CDF/mois</span></div>
            <div style="font-size:11px;display:flex;flex-direction:column;gap:5px;color:#6B4E1A">
              <div style="color:#C9972A">✓ Classe entière</div>
              <div style="color:#C9972A">✓ Tableau prof</div>
              <div style="color:#C9972A">✓ Tout Premium</div>
              <div style="color:#C9972A">✓ Rapport mensuel</div>
            </div>
          </div>
        </div>
        <div style="background:#F0FDF4;border:1px solid #BBF7D0;border-radius:10px;padding:11px 14px;font-size:12px;color:#166534;display:flex;gap:8px;align-items:center">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16A34A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          <span>Paiement sécurisé via <strong>M-Pesa</strong>, <strong>Airtel Money</strong> ou <strong>Orange Money</strong>. Annulable à tout moment.</span>
        </div>
      </div>

      <!-- Slide 3 — C'est parti -->
      <div class="ob-slide ob-right" style="text-align:center">
        <div class="ob-icon" style="background:linear-gradient(135deg,#22C55E,#16A34A);width:70px;height:70px">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <h2 style="font-family:'Poppins',sans-serif;font-size:22px;font-weight:800;margin-bottom:10px">Vous êtes prêt·e, <?= e($user['prenom']) ?> !</h2>
        <p style="color:#6B7280;font-size:13px;line-height:1.7;max-width:380px;margin:0 auto 28px">Votre premier examen prend 5 minutes. C'est le meilleur moyen de connaître votre niveau de départ.</p>
        <div style="display:flex;flex-direction:column;gap:10px;max-width:320px;margin:0 auto">
          <a href="/reussiteplus/examen.php" onclick="obClose()" style="display:flex;align-items:center;justify-content:center;gap:8px;background:#007A5E;color:#fff;border-radius:12px;padding:14px;font-weight:700;font-size:14px;text-decoration:none;transition:background .2s" onmouseover="this.style.background='#005A45'" onmouseout="this.style.background='#007A5E'">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            Passer mon premier examen
          </a>
          <a href="/reussiteplus/tarifs.php" style="display:flex;align-items:center;justify-content:center;gap:8px;background:#F1F5F9;color:#007A5E;border-radius:12px;padding:12px;font-weight:600;font-size:13px;text-decoration:none;transition:background .2s" onmouseover="this.style.background='#E2E8F0'" onmouseout="this.style.background='#F1F5F9'">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            Voir les plans Premium
          </a>
          <button onclick="obClose()" style="background:none;border:none;color:#9CA3AF;font-size:12px;cursor:pointer;padding:6px">Continuer gratuitement</button>
        </div>
      </div>

    </div><!-- /ob-slides -->

    <!-- Footer navigation -->
    <div class="ob-footer">
      <div class="ob-dots">
        <button class="ob-dot on" data-i="0" onclick="obGoto(0)"></button>
        <button class="ob-dot" data-i="1" onclick="obGoto(1)"></button>
        <button class="ob-dot" data-i="2" onclick="obGoto(2)"></button>
        <button class="ob-dot" data-i="3" onclick="obGoto(3)"></button>
      </div>
      <div class="ob-nav">
        <button class="ob-btn ob-btn-sec" id="ob-back" onclick="obGoto(window._obCur-1)" style="display:none">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
          Retour
        </button>
        <button class="ob-btn ob-btn-pri" id="ob-next" onclick="obGoto(window._obCur+1)">
          Suivant
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
      </div>
    </div>
  </div>
</div>

<script>
(function() {
  var TOTAL = 4;
  window._obCur = 0;
  var slides = document.querySelectorAll('#ob-slides .ob-slide');
  var dots   = document.querySelectorAll('.ob-dot');

  window.obGoto = function(n) {
    if (n < 0 || n >= TOTAL) return;
    var prev = window._obCur;
    // Direction
    slides[prev].className = 'ob-slide ' + (n > prev ? 'ob-left' : 'ob-right');
    window._obCur = n;
    slides[n].className = 'ob-slide ob-active';
    // Dots
    dots[prev].classList.remove('on');
    dots[n].classList.add('on');
    // Progress bar
    document.getElementById('ob-fill').style.width = ((n + 1) / TOTAL * 100) + '%';
    // Boutons
    document.getElementById('ob-back').style.display = n > 0 ? '' : 'none';
    var nextBtn = document.getElementById('ob-next');
    if (n === TOTAL - 1) {
      nextBtn.style.display = 'none';
    } else {
      nextBtn.style.display = '';
      nextBtn.innerHTML = 'Suivant <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>';
    }
  };

  window.obClose = function() {
    var bd = document.getElementById('ob-bd');
    bd.style.transition = 'opacity .25s';
    bd.style.opacity = '0';
    setTimeout(function(){ bd.remove(); }, 270);
    if (history.replaceState) {
      var url = new URL(window.location.href);
      url.searchParams.delete('welcome');
      history.replaceState({}, '', url.toString());
    }
  };

  // Fermer sur clic backdrop
  document.getElementById('ob-bd').addEventListener('click', function(e) {
    if (e.target === this) window.obClose();
  });

  // Clavier
  document.addEventListener('keydown', function(e) {
    if (e.key === 'ArrowRight') window.obGoto(window._obCur + 1);
    if (e.key === 'ArrowLeft')  window.obGoto(window._obCur - 1);
    if (e.key === 'Escape')     window.obClose();
  });
})();
</script>
<?php endif; ?>


<?php include __DIR__ . '/includes/footer_app.php'; ?>
