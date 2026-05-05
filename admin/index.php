<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$pageTitle  = 'Administration';
$pageActive = 'admin';
$user = require_admin();

// -- Statistiques principales ----------------------------
$adm = [
    'total_users'     => (int)dbRow("SELECT COUNT(*) as n FROM utilisateurs WHERE is_active=1")['n'],
    'users_today'     => (int)dbRow("SELECT COUNT(*) as n FROM utilisateurs WHERE DATE(created_at)=CURDATE()")['n'],
    'users_7j'        => (int)dbRow("SELECT COUNT(*) as n FROM utilisateurs WHERE created_at >= DATE_SUB(CURDATE(),INTERVAL 6 DAY)")['n'],
    'users_mois'      => (int)dbRow("SELECT COUNT(*) as n FROM utilisateurs WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())")['n'],
    'total_archives'  => (int)dbRow("SELECT COUNT(*) as n FROM archives")['n'],
    'exams_today'     => (int)dbRow("SELECT COUNT(*) as n FROM exam_sessions WHERE DATE(started_at)=CURDATE()")['n'],
    'exams_7j'        => (int)dbRow("SELECT COUNT(*) as n FROM exam_sessions WHERE started_at >= DATE_SUB(CURDATE(),INTERVAL 6 DAY)")['n'],
    'paiements_att'   => (int)dbRow("SELECT COUNT(*) as n FROM abonnements WHERE statut='EN_ATTENTE'")['n'],
    'revenus_mois'    => (float)dbRow("SELECT COALESCE(SUM(montant),0) as n FROM abonnements WHERE statut='CONFIRME' AND MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())")['n'],
    'revenus_hier'    => (float)dbRow("SELECT COALESCE(SUM(montant),0) as n FROM abonnements WHERE statut='CONFIRME' AND MONTH(created_at)=MONTH(DATE_SUB(CURDATE(),INTERVAL 1 MONTH)) AND YEAR(created_at)=YEAR(DATE_SUB(CURDATE(),INTERVAL 1 MONTH))")['n'],
    'total_questions' => (int)dbRow("SELECT COUNT(*) as n FROM question_bank")['n'],
    'classes_actives' => (int)(dbRow("SELECT COUNT(*) as n FROM classes_ecole WHERE actif=1") ?? ['n'=>0])['n'],
    'devoirs_att'     => (int)(dbRow("SELECT COUNT(*) as n FROM soumissions_devoirs WHERE statut='SOUMIS'") ?? ['n'=>0])['n'],
    'messages_contact'=> (int)(dbRow("SELECT COUNT(*) as n FROM contact_messages WHERE created_at >= DATE_SUB(NOW(),INTERVAL 48 HOUR)") ?? ['n'=>0])['n'],
];

// Taux de croissance
$revGrowth = $adm['revenus_hier'] > 0
    ? round(($adm['revenus_mois'] - $adm['revenus_hier']) / $adm['revenus_hier'] * 100, 1)
    : ($adm['revenus_mois'] > 0 ? 100 : 0);

// Plans distribution
$planStats = dbAll("SELECT plan, COUNT(*) as nb FROM utilisateurs WHERE is_active=1 GROUP BY plan ORDER BY FIELD(plan,'ECOLE','PREMIUM','BASIQUE','GRATUIT')") ?? [];
$totalUsers = max(1, array_sum(array_column($planStats ?? [], 'nb')));

// Inscriptions 30 derniers jours (regroupï¿½es par semaine pour le chart)
$insc30j = dbAll("SELECT DATE(created_at) as jour, COUNT(*) as nb FROM utilisateurs WHERE created_at >= DATE_SUB(CURDATE(),INTERVAL 29 DAY) GROUP BY DATE(created_at) ORDER BY jour ASC") ?? [];
$inscMap = [];
foreach ($insc30j as $r) $inscMap[$r['jour']] = (int)$r['nb'];

// Revenus 6 derniers mois
$rev6m = dbAll("SELECT DATE_FORMAT(created_at,'%Y-%m') as mois, SUM(montant) as total FROM abonnements WHERE statut='CONFIRME' AND created_at >= DATE_SUB(CURDATE(),INTERVAL 5 MONTH) GROUP BY mois ORDER BY mois ASC") ?? [];

// Paiements en attente
$paiementsAtt = dbAll("SELECT a.*, u.email, u.prenom, u.nom FROM abonnements a JOIN utilisateurs u ON a.user_id=u.id WHERE a.statut='EN_ATTENTE' ORDER BY a.created_at DESC LIMIT 8") ?? [];

// Derniers inscrits
$lastUsers = dbAll("SELECT id, prenom, nom, email, plan, role, ville, created_at FROM utilisateurs ORDER BY created_at DESC LIMIT 6") ?? [];

// Messages de contact non lus (48h)
$lastMessages = dbAll("SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 5") ?? [];

// Activitï¿½ exam sessions today
$examSessions = dbAll("SELECT COALESCE(s.titre, e.titre, 'Examen libre') AS titre_custom, u.prenom, u.nom, s.score, s.started_at, s.statut FROM exam_sessions s JOIN utilisateurs u ON u.id=s.user_id LEFT JOIN archives e ON e.id=s.archive_id WHERE DATE(s.started_at)=CURDATE() ORDER BY s.started_at DESC LIMIT 8") ?? [];

include __DIR__ . '/../includes/header_app.php';
?>

<style>
/* -- ADMIN RESET & BASE ---------------------------------- */
.adm-kpi-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:22px; }
.adm-kpi { background:var(--blanc); border:1.5px solid var(--gris-200); border-radius:16px; padding:20px 22px; position:relative; overflow:hidden; transition:.2s; }
.adm-kpi:hover { box-shadow:0 6px 24px rgba(0,0,0,.09); transform:translateY(-2px); }
.adm-kpi .accent-bar { position:absolute; left:0; top:0; bottom:0; width:4px; border-radius:16px 0 0 16px; }
.adm-kpi .icon-wrap { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:20px; margin-bottom:12px; }
.adm-kpi .val { font-family:var(--font-display); font-size:28px; font-weight:900; color:var(--gris-900); line-height:1; margin-bottom:4px; }
.adm-kpi .lbl { font-size:12px; color:var(--gris-500); font-weight:600; text-transform:uppercase; letter-spacing:.4px; }
.adm-kpi .sub { font-size:11px; margin-top:8px; }
.adm-kpi .badge-growth { display:inline-flex; align-items:center; gap:3px; padding:2px 8px; border-radius:20px; font-size:10px; font-weight:700; }

.adm-section { display:grid; grid-template-columns:1fr 1fr; gap:18px; margin-bottom:22px; }
.adm-card { background:var(--blanc); border:1.5px solid var(--gris-200); border-radius:16px; overflow:hidden; }
.adm-card-hd { padding:16px 20px 14px; border-bottom:1px solid var(--gris-100); display:flex; align-items:center; justify-content:space-between; gap:10px; }
.adm-card-title { font-family:var(--font-display); font-size:14px; font-weight:800; color:var(--gris-900); }
.adm-card-body { padding:18px 20px; }

/* Chart bars */
.bar-chart { display:flex; align-items:flex-end; gap:3px; height:90px; }
.bar-chart .bar { flex:1; border-radius:4px 4px 0 0; transition:.3s; cursor:pointer; min-height:3px; }
.bar-chart .bar:hover { opacity:.75; }

/* AI Panel */
.ai-panel { background:linear-gradient(135deg,#0f172a,#1e1b4b); border:1.5px solid rgba(124,58,237,.3); border-radius:16px; overflow:hidden; margin-bottom:22px; }
.ai-panel-hd { padding:16px 20px; border-bottom:1px solid rgba(255,255,255,.07); display:flex; align-items:center; justify-content:space-between; gap:10px; }
.ai-dot { width:8px; height:8px; background:#a78bfa; border-radius:50%; animation:pulse-ai 2s infinite; }
@keyframes pulse-ai { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(1.4)} }
.ai-insight-item { display:flex; align-items:flex-start; gap:12px; padding:12px 20px; border-bottom:1px solid rgba(255,255,255,.05); }
.ai-insight-item:last-child { border-bottom:none; }
.ai-badge { padding:3px 10px; border-radius:6px; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.5px; flex-shrink:0; margin-top:2px; }

/* Table styling */
.adm-table { width:100%; border-collapse:collapse; }
.adm-table th { padding:9px 14px; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.6px; color:var(--gris-500); background:var(--gris-50); text-align:left; border-bottom:1px solid var(--gris-200); }
.adm-table td { padding:10px 14px; font-size:13px; border-bottom:1px solid var(--gris-100); vertical-align:middle; }
.adm-table tr:last-child td { border-bottom:none; }
.adm-table tr:hover td { background:var(--gris-50); }

/* Plan pill */
.plan-pill { display:inline-flex; align-items:center; gap:4px; padding:3px 10px; border-radius:20px; font-size:10px; font-weight:800; text-transform:uppercase; }

/* Activity feed */
.act-item { display:flex; align-items:flex-start; gap:10px; padding:10px 0; border-bottom:1px solid var(--gris-100); }
.act-item:last-child { border-bottom:none; }
.act-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; margin-top:5px; }

@media(max-width:1100px) { .adm-kpi-grid { grid-template-columns:repeat(2,1fr); } .adm-section { grid-template-columns:1fr; } }
@media(max-width:640px)  { .adm-kpi-grid { grid-template-columns:1fr 1fr; } }
</style>

<!-- -- PAGE HEADER -------------------------------------- -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:22px">
  <div>
    <div style="font-family:var(--font-display);font-size:22px;font-weight:900;color:var(--gris-900)">Tableau de bord</div>
    <div style="font-size:12px;color:var(--gris-500);margin-top:2px">
      <?= date('l d F Y', time()) ?> &middot; Connect&eacute; en tant que <strong><?= e($user['prenom']??'') ?></strong>
    </div>
  </div>
  <div style="display:flex;gap:10px;align-items:center">
    <?php if ($adm['paiements_att'] > 0): ?>
    <a href="/reussiteplus/admin/paiements.php" class="btn btn-gold btn-sm" style="background:#C9972A;color:white;border:none">
      <?= $adm['paiements_att'] ?> paiement<?= $adm['paiements_att']>1?'s':'' ?> en attente
    </a>
    <?php endif; ?>
    <button onclick="loadAiInsights()" id="ai-btn" class="btn btn-sm" style="background:linear-gradient(135deg,#7C3AED,#6D28D9);color:white;border:none;font-weight:700">
      Analyser avec l'IA
    </button>
  </div>
</div>

<!-- -- KPI CARDS --------------------------------------- -->
<div class="adm-kpi-grid">

  <!-- Utilisateurs actifs -->
  <div class="adm-kpi">
    <div class="accent-bar" style="background:#007A5E"></div>
    <div class="icon-wrap" style="background:#E8F5F1;color:#007A5E">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    </div>
    <div class="val"><?= number_format($adm['total_users']) ?></div>
    <div class="lbl">Utilisateurs actifs</div>
    <div class="sub" style="color:var(--gris-500)">
      <span class="badge-growth" style="background:#E8F5F1;color:#007A5E">+<?= $adm['users_7j'] ?> cette semaine</span>
      &nbsp;<span style="color:var(--gris-400)">+<?= $adm['users_today'] ?> aujourd'hui</span>
    </div>
  </div>

  <!-- Revenus du mois -->
  <div class="adm-kpi">
    <div class="accent-bar" style="background:#C9972A"></div>
    <div class="icon-wrap" style="background:#FEF3D7;color:#C9972A">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
    </div>
    <div class="val"><?= number_format($adm['revenus_mois'], 0, ',', ' ') ?></div>
    <div class="lbl">Revenus ce mois (CDF)</div>
    <div class="sub">
      <?php $gc = $revGrowth; $gcPos = $gc >= 0; ?>
      <span class="badge-growth" style="background:<?= $gcPos?'#E8F5F1':'#FEE2E2' ?>;color:<?= $gcPos?'#007A5E':'#DC2626' ?>">
        <?= $gcPos?'+':'' ?><?= $gc ?>% vs mois dernier
      </span>
    </div>
  </div>

  <!-- Examens aujourd'hui -->
  <div class="adm-kpi">
    <div class="accent-bar" style="background:#1E5FAD"></div>
    <div class="icon-wrap" style="background:#DBEAFE;color:#1E5FAD">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10,9 9,9 8,9"/></svg>
    </div>
    <div class="val"><?= number_format($adm['exams_today']) ?></div>
    <div class="lbl">Examens aujourd'hui</div>
    <div class="sub" style="color:var(--gris-500)">
      <span class="badge-growth" style="background:#DBEAFE;color:#1E5FAD"><?= $adm['exams_7j'] ?> cette semaine</span>
    </div>
  </div>

  <!-- Paiements en attente -->
  <div class="adm-kpi" style="<?= $adm['paiements_att']>0?'border-color:#F59E0B;background:#FFFBEB':'' ?>">
    <div class="accent-bar" style="background:<?= $adm['paiements_att']>0?'#F59E0B':'#9CA3AF' ?>"></div>
    <div class="icon-wrap" style="background:<?= $adm['paiements_att']>0?'#FEF3C7':'var(--gris-100)' ?>;color:<?= $adm['paiements_att']>0?'#B45309':'var(--gris-500)' ?>">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
    </div>
    <div class="val" style="color:<?= $adm['paiements_att']>0?'#B45309':'var(--gris-900)' ?>"><?= $adm['paiements_att'] ?></div>
    <div class="lbl">Paiements en attente</div>
    <div class="sub">
      <?php if ($adm['paiements_att'] > 0): ?>
      <a href="/reussiteplus/admin/paiements.php" style="color:#B45309;font-size:11px;font-weight:700">Traiter maintenant &rarr;</a>
      <?php else: ?>
      <span style="font-size:11px;color:var(--gris-400)">Aucun paiement en attente</span>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- Ligne 2 KPIs secondaires -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px">
  <?php
  $kpis2 = [
    ['val'=>$adm['total_archives'], 'lbl'=>'Archives disponibles', 'link'=>'/reussiteplus/admin/archives.php', 'color'=>'#059669'],
    ['val'=>number_format($adm['total_questions']), 'lbl'=>'Questions en banque', 'link'=>'', 'color'=>'#7C3AED'],
    ['val'=>$adm['classes_actives'], 'lbl'=>'Classes actives', 'link'=>'', 'color'=>'#1E5FAD'],
    ['val'=>$adm['messages_contact'], 'lbl'=>'Messages (48h)', 'link'=>'', 'color'=>'#DC2626'],
  ];
  foreach ($kpis2 as $k):
  ?>
  <div style="background:var(--blanc);border:1.5px solid var(--gris-200);border-radius:14px;padding:14px 18px;display:flex;align-items:center;gap:12px">
    <div style="width:10px;height:40px;background:<?= $k['color'] ?>;border-radius:4px;flex-shrink:0;opacity:.7"></div>
    <div>
      <div style="font-family:var(--font-display);font-size:22px;font-weight:900;color:var(--gris-900);line-height:1"><?= $k['val'] ?></div>
      <div style="font-size:11px;color:var(--gris-500);margin-top:3px;font-weight:600"><?= $k['lbl'] ?></div>
      <?php if ($k['link']): ?><a href="<?= $k['link'] ?>" style="font-size:10px;color:<?= $k['color'] ?>;font-weight:700">Voir &rarr;</a><?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- -- IA ANALYSE --------------------------------------- -->
<div class="ai-panel" id="ai-panel">
  <div class="ai-panel-hd">
    <div style="display:flex;align-items:center;gap:10px">
      <div class="ai-dot"></div>
      <span style="font-family:var(--font-display);font-size:14px;font-weight:800;color:white">Intelligence Artificielle &mdash; Analyse de la plateforme</span>
    </div>
    <span style="font-size:11px;color:rgba(255,255,255,.3)">Llama 3.1 &middot; Groq</span>
  </div>
  <div id="ai-content" style="padding:20px">
    <!-- Insights automatiques basï¿½s sur les donnï¿½es -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px" id="ai-auto">
      <?php
      // Insight 1 : plan le plus populaire
      $topPlan = $planStats[0] ?? null;
      $paidUsers = 0;
      foreach ($planStats as $ps) if ($ps['plan'] !== 'GRATUIT') $paidUsers += $ps['nb'];
      $convRate = $adm['total_users'] > 0 ? round($paidUsers / $adm['total_users'] * 100, 1) : 0;

      $aiInsights = [
        [
          'type' => 'TENDANCE',
          'color' => '#a78bfa',
          'bg'    => 'rgba(124,58,237,.15)',
          'title' => 'Conversion payant',
          'text'  => "Taux de conversion : <strong style='color:#a78bfa'>{$convRate}%</strong> des utilisateurs sont sur un plan payant. " . ($convRate < 20 ? "Optimisation de l'onboarding recommand&eacute;e." : "Excellent taux &mdash; maintenir la qualit&eacute; du contenu.")
        ],
        [
          'type' => 'REVENU',
          'color' => '#34d399',
          'bg'    => 'rgba(52,211,153,.12)',
          'title' => 'Performance revenus',
          'text'  => "Revenus mensuels : <strong style='color:#34d399'>" . number_format($adm['revenus_mois'],0,',',' ') . " CDF</strong>. " . ($revGrowth >= 0 ? "Croissance de +{$revGrowth}% par rapport au mois pr&eacute;c&eacute;dent." : "Baisse de {$revGrowth}% &mdash; r&eacute;viser la strat&eacute;gie tarifaire.")
        ],
        [
          'type' => 'ACTIVITE',
          'color' => '#60a5fa',
          'bg'    => 'rgba(96,165,250,.12)',
          'title' => 'Engagement utilisateurs',
          'text'  => "<strong style='color:#60a5fa'>{$adm['exams_today']}</strong> examens lanc&eacute;s aujourd'hui. " . ($adm['exams_today'] >= 10 ? "Bonne activit&eacute; journali&egrave;re." : "Activit&eacute; faible &mdash; envisager des notifications push ou emails d'encouragement.")
        ],
      ];
      foreach ($aiInsights as $ins):
      ?>
      <div style="background:<?= $ins['bg'] ?>;border:1px solid rgba(255,255,255,.08);border-radius:12px;padding:14px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
          <span style="background:<?= $ins['color'] ?>22;color:<?= $ins['color'] ?>;font-size:9px;font-weight:800;padding:2px 8px;border-radius:4px;text-transform:uppercase;letter-spacing:.5px"><?= $ins['type'] ?></span>
        </div>
        <div style="font-size:11px;font-weight:700;color:rgba(255,255,255,.6);margin-bottom:5px;text-transform:uppercase;letter-spacing:.4px"><?= $ins['title'] ?></div>
        <div style="font-size:13px;color:rgba(255,255,255,.8);line-height:1.6"><?= $ins['text'] ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Analyse IA Groq (chargï¿½e ï¿½ la demande) -->
    <div id="ai-groq-result" style="display:none;margin-top:14px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:16px">
      <div style="font-size:11px;font-weight:700;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px">Analyse approfondie &mdash; IA Groq</div>
      <div id="ai-groq-text" style="font-size:14px;color:rgba(255,255,255,.8);line-height:1.8"></div>
    </div>
    <div id="ai-loading" style="display:none;text-align:center;padding:20px;color:rgba(255,255,255,.4);font-size:13px">
      <span style="display:inline-block;width:18px;height:18px;border:2px solid rgba(255,255,255,.2);border-top-color:#a78bfa;border-radius:50%;animation:spin .7s linear infinite;vertical-align:-4px;margin-right:8px"></span>
      Analyse en cours via Groq AI...
    </div>
  </div>
</div>

<!-- -- CHARTS ------------------------------------------- -->
<div class="adm-section">

  <!-- Inscriptions 30 jours -->
  <div class="adm-card">
    <div class="adm-card-hd">
      <div>
        <div class="adm-card-title">Inscriptions &mdash; 30 derniers jours</div>
        <div style="font-size:11px;color:var(--gris-400);margin-top:2px"><?= array_sum(array_values($inscMap)) ?> nouvelles inscriptions</div>
      </div>
    </div>
    <div class="adm-card-body">
      <?php
      $maxI = max(1, max(array_merge([1], array_values($inscMap))));
      ?>
      <div class="bar-chart" id="chart-insc">
        <?php for ($d = 29; $d >= 0; $d--):
          $jour = date('Y-m-d', strtotime("-{$d} days"));
          $nb = $inscMap[$jour] ?? 0;
          $h = $nb > 0 ? max(8, (int)(($nb / $maxI) * 80)) : 3;
        ?>
        <div class="bar" style="height:<?= $h ?>px;background:<?= $nb>0?'var(--primary)':'var(--gris-200)' ?>;opacity:<?= $nb>0?'1':'.5' ?>"
             title="<?= date('d/m', strtotime($jour)) ?> &mdash; <?= $nb ?> inscription<?= $nb>1?'s':'' ?>"
             onmouseover="showTip(this,'<?= date('d/m', strtotime($jour)) ?> : <?= $nb ?> inscription<?= $nb>1?'s':'' ?>')"
             onmouseout="hideTip()"></div>
        <?php endfor; ?>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:9px;color:var(--gris-400);margin-top:6px">
        <span>Il y a 30 j</span><span>Aujourd'hui</span>
      </div>
    </div>
  </div>

  <!-- Revenus 6 mois -->
  <div class="adm-card">
    <div class="adm-card-hd">
      <div>
        <div class="adm-card-title">Revenus &mdash; 6 derniers mois</div>
        <div style="font-size:11px;color:var(--gris-400);margin-top:2px">CDF confirm&eacute;s</div>
      </div>
    </div>
    <div class="adm-card-body">
      <?php
      $revMap = [];
      foreach ($rev6m as $r) $revMap[$r['mois']] = (float)$r['total'];
      $maxR = max(1, max(array_merge([1], array_values($revMap))));
      $moisLabels = [];
      for ($m = 5; $m >= 0; $m--) {
          $moisLabels[] = date('Y-m', strtotime("-{$m} month"));
      }
      ?>
      <div class="bar-chart">
        <?php foreach ($moisLabels as $mois):
          $v = $revMap[$mois] ?? 0;
          $h = $v > 0 ? max(8, (int)(($v / $maxR) * 80)) : 3;
          $shortM = ['01'=>'Jan','02'=>'F&eacute;v','03'=>'Mar','04'=>'Avr','05'=>'Mai','06'=>'Jun','07'=>'Jul','08'=>'Ao&ucirc;t','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'D&eacute;c'];
          $lbl = $shortM[substr($mois,5,2)] ?? substr($mois,5,2);
        ?>
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:3px">
          <div class="bar" style="width:100%;height:<?= $h ?>px;background:<?= $v>0?'#C9972A':'var(--gris-200)' ?>;opacity:<?= $v>0?'1':'.5' ?>"
               title="<?= $lbl ?> <?= substr($mois,0,4) ?> &mdash; <?= number_format($v,0,',',' ') ?> CDF"
               onmouseover="showTip(this,'<?= $lbl ?> : <?= number_format($v,0,',',' ') ?> CDF')"
               onmouseout="hideTip()"></div>
          <div style="font-size:9px;color:var(--gris-400)"><?= $lbl ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

</div>

<!-- -- PLANS + ACTIVITE --------------------------------- -->
<div class="adm-section">

  <!-- Rï¿½partition plans -->
  <div class="adm-card">
    <div class="adm-card-hd">
      <div class="adm-card-title">R&eacute;partition des abonnements</div>
      <div style="font-size:11px;color:var(--gris-500)"><?= number_format($totalUsers) ?> utilisateurs</div>
    </div>
    <div class="adm-card-body">
      <?php
      $planColors = ['GRATUIT'=>['#9CA3AF','#F3F4F6'],'BASIQUE'=>['#007A5E','#E8F5F1'],'PREMIUM'=>['#7C3AED','#EDE9FE'],'ECOLE'=>['#1E5FAD','#DBEAFE']];
      foreach ($planStats as $ps):
        $pct = round(($ps['nb'] / $totalUsers) * 100, 1);
        [$fc, $bg] = $planColors[$ps['plan']] ?? ['#9CA3AF','#F3F4F6'];
        $info = PLANS[$ps['plan']] ?? ['nom' => $ps['plan']];
      ?>
      <div style="margin-bottom:14px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px">
          <div style="display:flex;align-items:center;gap:8px">
            <div style="width:10px;height:10px;background:<?= $fc ?>;border-radius:50%"></div>
            <span style="font-size:13px;font-weight:700;color:var(--gris-800)"><?= e($info['nom']) ?></span>
          </div>
          <div style="text-align:right">
            <span style="font-family:var(--font-display);font-size:15px;font-weight:900;color:var(--gris-900)"><?= $ps['nb'] ?></span>
            <span style="font-size:11px;color:var(--gris-400);margin-left:4px"><?= $pct ?>%</span>
          </div>
        </div>
        <div style="height:7px;background:var(--gris-100);border-radius:4px;overflow:hidden">
          <div style="width:<?= $pct ?>%;height:100%;background:<?= $fc ?>;border-radius:4px;transition:width 1s ease"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Activitï¿½ rï¿½cente -->
  <div class="adm-card">
    <div class="adm-card-hd">
      <div class="adm-card-title">Activit&eacute; &mdash; Examens du jour</div>
      <span style="background:var(--primary);color:white;font-size:10px;font-weight:800;padding:2px 8px;border-radius:8px"><?= $adm['exams_today'] ?></span>
    </div>
    <div class="adm-card-body" style="padding:12px 20px">
      <?php if ($examSessions): ?>
      <?php foreach ($examSessions as $es): ?>
      <div class="act-item">
        <div class="act-dot" style="background:<?= $es['statut']==='TERMINE'?'#007A5E':'#F59E0B' ?>"></div>
        <div style="flex:1;min-width:0">
          <div style="font-size:13px;font-weight:700;color:var(--gris-800);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
            <?= e(($es['prenom']??'').' '.($es['nom']??'')) ?>
          </div>
          <div style="font-size:11px;color:var(--gris-500)">
            <?= e($es['titre_custom'] ?? 'Examen') ?>
            <?= $es['score'] !== null ? '&middot; ' . round((float)$es['score']) . '%' : '' ?>
          </div>
        </div>
        <div style="font-size:10px;color:var(--gris-400);white-space:nowrap"><?= $es['started_at'] ? date('H:i', strtotime($es['started_at'])) : '' ?></div>
      </div>
      <?php endforeach; ?>
      <?php else: ?>
      <div style="text-align:center;padding:28px;color:var(--gris-400);font-size:13px">Aucune session aujourd'hui</div>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- -- PAIEMENTS EN ATTENTE ----------------------------- -->
<?php if ($paiementsAtt): ?>
<div class="adm-card" style="margin-bottom:22px;border-color:#F59E0B">
  <div class="adm-card-hd" style="background:#FFFBEB">
    <div style="display:flex;align-items:center;gap:10px">
      <div style="width:8px;height:8px;background:#F59E0B;border-radius:50%;animation:pulse-ai 1.5s infinite"></div>
      <div class="adm-card-title" style="color:#92400E">Paiements en attente de confirmation</div>
    </div>
    <a href="/reussiteplus/admin/paiements.php" class="btn btn-sm" style="background:#C9972A;color:white;border:none;font-weight:700">Tout voir</a>
  </div>
  <div style="overflow-x:auto">
    <table class="adm-table">
      <thead>
        <tr>
          <th>R&eacute;f&eacute;rence</th>
          <th>Utilisateur</th>
          <th>Plan</th>
          <th>Montant</th>
          <th>M&eacute;thode</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($paiementsAtt as $p):
        $planInfo = PLANS[$p['plan']] ?? ['nom'=>$p['plan']];
        $planColors2 = ['GRATUIT'=>'#9CA3AF','BASIQUE'=>'#007A5E','PREMIUM'=>'#7C3AED','ECOLE'=>'#1E5FAD'];
        $pc = $planColors2[$p['plan']] ?? '#9CA3AF';
      ?>
      <tr>
        <td><code style="font-size:11px;background:var(--gris-100);padding:2px 6px;border-radius:4px"><?= e(substr($p['reference_paiement'],0,20)) ?></code></td>
        <td>
          <div style="font-weight:700"><?= e($p['prenom'].' '.$p['nom']) ?></div>
          <div style="font-size:11px;color:var(--gris-500)"><?= e($p['email']) ?></div>
        </td>
        <td><span class="plan-pill" style="background:<?= $pc ?>20;color:<?= $pc ?>"><?= e($planInfo['nom']) ?></span></td>
        <td style="font-family:var(--font-display);font-weight:900;color:var(--gris-900)"><?= number_format((float)$p['montant'],0,',',' ') ?> <span style="font-size:10px;font-weight:400;color:var(--gris-500)">CDF</span></td>
        <td style="font-size:12px"><?= e(METHODES_PAIEMENT[$p['methode_paiement']]['nom'] ?? $p['methode_paiement']) ?></td>
        <td style="font-size:11px;color:var(--gris-500)"><?= date('d/m H:i', strtotime($p['created_at'])) ?></td>
        <td style="white-space:nowrap">
          <a href="/reussiteplus/admin/paiements.php?action=confirmer&id=<?= e($p['id']) ?>" class="btn btn-sm" style="background:#E8F5F1;color:#007A5E;border:none;font-weight:700" onclick="return confirm('Confirmer ce paiement ?')">Confirmer</a>
          <a href="/reussiteplus/admin/paiements.php?action=refuser&id=<?= e($p['id']) ?>" class="btn btn-sm" style="background:#FEE2E2;color:#DC2626;border:none;font-weight:700;margin-left:4px" onclick="return confirm('Refuser ce paiement ?')">Refuser</a>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- -- DERNIERS INSCRITS + MESSAGES -------------------- -->
<div class="adm-section">

  <!-- Derniers inscrits -->
  <div class="adm-card">
    <div class="adm-card-hd">
      <div class="adm-card-title">Derniers inscrits</div>
      <a href="/reussiteplus/admin/users.php" class="btn btn-ghost btn-sm">Voir tous</a>
    </div>
    <div style="overflow-x:auto">
      <table class="adm-table">
        <thead>
          <tr><th>Utilisateur</th><th>Plan</th><th>Inscrit</th></tr>
        </thead>
        <tbody>
        <?php foreach ($lastUsers as $u):
          $pc = $planColors2[$u['plan']] ?? '#9CA3AF';
          $planInfo2 = PLANS[$u['plan']] ?? ['nom'=>$u['plan']];
        ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:9px">
              <div style="width:32px;height:32px;border-radius:50%;background:<?= $pc ?>20;color:<?= $pc ?>;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px;flex-shrink:0">
                <?= strtoupper(mb_substr($u['prenom']??'?',0,1)) ?>
              </div>
              <div>
                <div style="font-weight:700;font-size:13px"><?= e($u['prenom'].' '.$u['nom']) ?></div>
                <div style="font-size:11px;color:var(--gris-500)"><?= e($u['email']) ?></div>
              </div>
            </div>
          </td>
          <td><span class="plan-pill" style="background:<?= $pc ?>20;color:<?= $pc ?>"><?= e($planInfo2['nom']) ?></span></td>
          <td style="font-size:11px;color:var(--gris-500)"><?= temps_relatif($u['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Messages de contact -->
  <div class="adm-card">
    <div class="adm-card-hd">
      <div class="adm-card-title">Messages de contact r&eacute;cents</div>
      <?php if ($adm['messages_contact'] > 0): ?>
      <span style="background:#FEE2E2;color:#DC2626;font-size:10px;font-weight:800;padding:2px 8px;border-radius:8px"><?= $adm['messages_contact'] ?> nouveaux</span>
      <?php endif; ?>
    </div>
    <div class="adm-card-body" style="padding:0">
      <?php if ($lastMessages): ?>
      <?php foreach ($lastMessages as $msg):
        $sujetColor = ['PLAN'=>'#007A5E','TECHNIQUE'=>'#DC2626','PARTENARIAT'=>'#7C3AED','PRESSE'=>'#1E5FAD','AUTRE'=>'#9CA3AF'];
        $sc = $sujetColor[$msg['sujet']??'AUTRE'] ?? '#9CA3AF';
      ?>
      <div style="padding:12px 20px;border-bottom:1px solid var(--gris-100);cursor:pointer" onclick="this.nextElementSibling&&this.nextElementSibling.classList.toggle('hidden')">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
          <div style="display:flex;align-items:center;gap:8px;min-width:0">
            <div style="width:8px;height:8px;background:<?= $sc ?>;border-radius:50%;flex-shrink:0"></div>
            <div style="font-weight:700;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($msg['nom']) ?></div>
            <span style="background:<?= $sc ?>20;color:<?= $sc ?>;font-size:9px;font-weight:800;padding:1px 6px;border-radius:4px;flex-shrink:0"><?= e($msg['sujet']??'') ?></span>
          </div>
          <div style="font-size:10px;color:var(--gris-400);white-space:nowrap"><?= temps_relatif($msg['created_at']) ?></div>
        </div>
        <div style="font-size:12px;color:var(--gris-600);margin-top:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e(mb_strimwidth($msg['message']??'',0,80,'...')) ?></div>
      </div>
      <?php endforeach; ?>
      <?php else: ?>
      <div style="text-align:center;padding:28px;color:var(--gris-400);font-size:13px">Aucun message r&eacute;cent</div>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- -- ACTIONS RAPIDES ---------------------------------- -->
<div style="background:var(--gris-50);border:1.5px solid var(--gris-200);border-radius:16px;padding:20px;margin-bottom:22px">
  <div style="font-family:var(--font-display);font-size:13px;font-weight:800;color:var(--gris-700);margin-bottom:14px;text-transform:uppercase;letter-spacing:.5px">Actions rapides</div>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <a href="/reussiteplus/admin/users.php" class="btn btn-ghost btn-sm" style="font-weight:700">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
      G&eacute;rer les utilisateurs
    </a>
    <a href="/reussiteplus/admin/paiements.php" class="btn btn-ghost btn-sm" style="font-weight:700">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
      Paiements
    </a>
    <a href="/reussiteplus/admin/archives.php" class="btn btn-ghost btn-sm" style="font-weight:700">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/></svg>
      G&eacute;rer archives
    </a>
    <a href="/reussiteplus/tarifs.php" target="_blank" class="btn btn-ghost btn-sm" style="font-weight:700">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
      Page tarifs
    </a>
    <button onclick="exportCsv()" class="btn btn-ghost btn-sm" style="font-weight:700">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      Exporter CSV
    </button>
    <button onclick="loadAiInsights()" class="btn btn-sm" style="background:linear-gradient(135deg,#7C3AED,#6D28D9);color:white;border:none;font-weight:700">
      Rapport IA complet
    </button>
  </div>
</div>

<!-- Tooltip -->
<div id="tooltip" style="display:none;position:fixed;background:rgba(0,0,0,.85);color:white;font-size:11px;padding:5px 10px;border-radius:6px;pointer-events:none;z-index:1000"></div>

<script>
// Tooltip on chart bars
function showTip(el, text) {
  const t = document.getElementById('tooltip');
  t.textContent = text;
  t.style.display = 'block';
  document.addEventListener('mousemove', moveTip);
}
function moveTip(e) {
  const t = document.getElementById('tooltip');
  t.style.left = (e.clientX + 12) + 'px';
  t.style.top  = (e.clientY - 28) + 'px';
}
function hideTip() {
  document.getElementById('tooltip').style.display = 'none';
  document.removeEventListener('mousemove', moveTip);
}

// IA Groq Analysis
async function loadAiInsights() {
  const btn    = document.getElementById('ai-btn');
  const result = document.getElementById('ai-groq-result');
  const loader = document.getElementById('ai-loading');
  const text   = document.getElementById('ai-groq-text');

  btn.disabled  = true;
  btn.textContent = 'Analyse en cours...';
  loader.style.display  = 'block';
  result.style.display  = 'none';

  try {
    const resp = await fetch('/reussiteplus/admin/ai_insights.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({
        users:       <?= $adm['total_users'] ?>,
        users_today: <?= $adm['users_today'] ?>,
        users_7j:    <?= $adm['users_7j'] ?>,
        revenus:     <?= $adm['revenus_mois'] ?>,
        rev_growth:  <?= $revGrowth ?>,
        exams_today: <?= $adm['exams_today'] ?>,
        paiements:   <?= $adm['paiements_att'] ?>,
        conv_rate:   <?= $convRate ?>,
        plans:       <?= json_encode(array_column($planStats??[], 'nb', 'plan')) ?>
      })
    });
    const data = await resp.json();
    if (data.analyse) {
      text.innerHTML = data.analyse.replace(/\n/g,'<br>');
      result.style.display = 'block';
    }
  } catch (e) {
    text.innerHTML = '<span style="color:#f87171">Impossible de contacter l\'IA. V&eacute;rifiez la cl&eacute; GROQ_API_KEY.</span>';
    result.style.display = 'block';
  }

  loader.style.display = 'none';
  btn.disabled = false;
  btn.textContent = 'Analyser avec l\'IA';
  result.scrollIntoView({behavior:'smooth', block:'center'});
}

// Export CSV basique
function exportCsv() {
  window.location = '/reussiteplus/admin/users.php?export=csv';
}
</script>
<style>@keyframes spin { to { transform:rotate(360deg); } }</style>

<?php include __DIR__ . '/../includes/footer_app.php'; ?>
