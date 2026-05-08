<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$pageTitle  = 'Administration';
$pageActive = 'admin';

$user = require_admin();
// CSRF token pour actions critiques
if (empty($_SESSION['csrf_admin'])) {
  $_SESSION['csrf_admin'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_admin'];

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
/* ======================================================
   ADMIN DASHBOARD — Design propre et aligné
   ====================================================== */

/* KPI Grid — 4 colonnes auto-responsive */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 24px;
}
.kpi-card {
  background: var(--blanc);
  border: 1px solid var(--gris-200);
  border-radius: 14px;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 0;
  transition: box-shadow .2s, transform .2s;
  position: relative;
  overflow: hidden;
}
.kpi-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,.08); transform: translateY(-2px); }
.kpi-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
  background: var(--kpi-color, var(--primary));
  border-radius: 14px 14px 0 0;
}
.kpi-icon {
  width: 40px; height: 40px;
  border-radius: 10px;
  background: var(--kpi-bg, rgba(0,122,94,.1));
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 14px;
}
.kpi-icon i { width: 18px; height: 18px; stroke: var(--kpi-color, var(--primary)); }
.kpi-value {
  font-family: var(--font-display);
  font-size: 26px; font-weight: 900;
  color: var(--gris-900); line-height: 1;
  margin-bottom: 4px;
}
.kpi-label {
  font-size: 11px; font-weight: 600;
  color: var(--gris-500);
  text-transform: uppercase; letter-spacing: .5px;
}
.kpi-sub {
  margin-top: 10px;
  display: flex; align-items: center; gap: 6px;
  flex-wrap: wrap;
}
.kpi-chip {
  display: inline-flex; align-items: center; gap: 3px;
  padding: 2px 8px; border-radius: 20px;
  font-size: 10px; font-weight: 700;
  background: var(--kpi-bg, rgba(0,122,94,.1));
  color: var(--kpi-color, var(--primary));
}

/* Stats secondaires */
.kpi-grid-sm {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
  margin-bottom: 24px;
}
.kpi-sm {
  background: var(--blanc);
  border: 1px solid var(--gris-200);
  border-radius: 12px;
  padding: 14px 16px;
  display: flex;
  align-items: center;
  gap: 12px;
}
.kpi-sm-bar {
  width: 4px; height: 36px;
  border-radius: 4px;
  background: var(--kpi-color, var(--primary));
  flex-shrink: 0;
}
.kpi-sm-val {
  font-family: var(--font-display);
  font-size: 20px; font-weight: 900;
  color: var(--gris-900); line-height: 1;
}
.kpi-sm-lbl {
  font-size: 11px; color: var(--gris-500);
  font-weight: 600; margin-top: 2px;
}

/* Section 2 colonnes */
.grid-2col {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 18px;
  margin-bottom: 24px;
}

/* Card standard */
.dash-card {
  background: var(--blanc);
  border: 1px solid var(--gris-200);
  border-radius: 14px;
  overflow: hidden;
}
.dash-card-header {
  padding: 14px 20px;
  border-bottom: 1px solid var(--gris-100);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}
.dash-card-title {
  font-family: var(--font-display);
  font-size: 13px; font-weight: 800;
  color: var(--gris-900);
  display: flex; align-items: center; gap: 7px;
}
.dash-card-title i { width: 14px; height: 14px; stroke: var(--primary); }
.dash-card-body { padding: 16px 20px; }

/* Bar chart */
.bar-chart { display: flex; align-items: flex-end; gap: 3px; height: 90px; }
.bar-chart .bar { flex: 1; border-radius: 4px 4px 0 0; transition: .2s; cursor: pointer; min-height: 3px; }
.bar-chart .bar:hover { opacity: .7; }

/* Tables admin */
.adm-table { width: 100%; border-collapse: collapse; }
.adm-table th {
  padding: 9px 14px; font-size: 10px; font-weight: 800;
  text-transform: uppercase; letter-spacing: .6px;
  color: var(--gris-500); background: var(--gris-50);
  text-align: left; border-bottom: 1px solid var(--gris-200);
  white-space: nowrap;
}
.adm-table td {
  padding: 10px 14px; font-size: 13px;
  border-bottom: 1px solid var(--gris-100);
  vertical-align: middle; color: var(--gris-700);
}
.adm-table tr:last-child td { border-bottom: none; }
.adm-table tr:hover td { background: var(--gris-50); }

/* Plan badge */
.plan-pill {
  display: inline-flex; align-items: center; gap: 3px;
  padding: 2px 8px; border-radius: 20px;
  font-size: 10px; font-weight: 800; text-transform: uppercase;
}

/* Activity list */
.act-item {
  display: flex; align-items: flex-start; gap: 10px;
  padding: 9px 0; border-bottom: 1px solid var(--gris-100);
}
.act-item:last-child { border-bottom: none; }
.act-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; margin-top: 4px; }

/* Plan distribution bar */
.plan-bar-row { margin-bottom: 12px; }
.plan-bar-row:last-child { margin-bottom: 0; }
.plan-bar-label { display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; }
.plan-bar-track { height: 6px; background: var(--gris-100); border-radius: 4px; overflow: hidden; }
.plan-bar-fill { height: 100%; border-radius: 4px; }

/* IA Section */
.ia-card {
  background: var(--blanc);
  border: 1px solid var(--gris-200);
  border-radius: 14px;
  overflow: hidden;
  margin-bottom: 24px;
}
.ia-card-header {
  padding: 14px 20px;
  border-bottom: 1px solid var(--gris-100);
  display: flex; align-items: center; justify-content: space-between;
  background: linear-gradient(135deg, #F5F3FF, #EFF6FF);
}
.ia-dot { width: 7px; height: 7px; background: #7C3AED; border-radius: 50%; animation: pulse-ai 2s infinite; }
@keyframes pulse-ai { 0%,100%{opacity:1} 50%{opacity:.3} }
.ia-insights-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
  padding: 16px 20px;
}
.ia-insight-card {
  background: var(--gris-50);
  border: 1px solid var(--gris-200);
  border-radius: 10px;
  padding: 14px;
}
.ia-insight-tag {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 2px 8px; border-radius: 6px;
  font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .4px;
  margin-bottom: 8px;
}
.ia-insight-text { font-size: 12px; color: var(--gris-700); line-height: 1.6; }
.ia-result-box {
  margin: 0 20px 16px;
  background: var(--gris-50);
  border: 1px solid var(--gris-200);
  border-radius: 10px;
  padding: 16px;
}

/* Welcome banner */
.welcome-banner {
  background: linear-gradient(135deg, #007A5E 0%, #005A45 100%);
  border-radius: 16px;
  padding: 24px 28px;
  margin-bottom: 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  flex-wrap: wrap;
  position: relative;
  overflow: hidden;
}
.welcome-banner::after {
  content: '';
  position: absolute; right: -60px; top: -60px;
  width: 220px; height: 220px;
  background: rgba(255,255,255,.06);
  border-radius: 50%;
  pointer-events: none;
}
.welcome-text-main {
  font-family: var(--font-display);
  font-size: 20px; font-weight: 800;
  color: #fff; margin-bottom: 4px;
}
.welcome-text-sub { font-size: 13px; color: rgba(255,255,255,.7); line-height: 1.6; }
.welcome-kpis { display: flex; gap: 12px; flex-wrap: wrap; }
.welcome-kpi-box {
  background: rgba(255,255,255,.12);
  border: 1px solid rgba(255,255,255,.18);
  border-radius: 10px;
  padding: 12px 18px;
  text-align: center;
  min-width: 110px;
}
.welcome-kpi-val { font-family: var(--font-display); font-size: 22px; font-weight: 900; color: #fff; }
.welcome-kpi-lbl { font-size: 10px; color: rgba(255,255,255,.6); margin-top: 2px; }

/* Alerte paiements */
.alert-pay {
  display: flex; align-items: center; gap: 12px;
  background: #FFFBEB;
  border: 1px solid #FDE68A;
  border-radius: 12px;
  padding: 14px 18px;
  margin-bottom: 24px;
}
.alert-pay-dot { width: 8px; height: 8px; background: #F59E0B; border-radius: 50%; flex-shrink: 0; animation: pulse-ai 1.5s infinite; }

/* Quick actions */
.quick-actions {
  display: flex; gap: 8px; flex-wrap: wrap;
  padding: 16px 20px;
  border-top: 1px solid var(--gris-100);
  background: var(--gris-50);
}

@media(max-width: 1200px) {
  .kpi-grid { grid-template-columns: repeat(2, 1fr); }
  .kpi-grid-sm { grid-template-columns: repeat(2, 1fr); }
  .ia-insights-grid { grid-template-columns: 1fr 1fr; }
}
@media(max-width: 900px) {
  .grid-2col { grid-template-columns: 1fr; }
}
@media(max-width: 640px) {
  .kpi-grid { grid-template-columns: 1fr 1fr; }
  .kpi-grid-sm { grid-template-columns: 1fr 1fr; }
  .ia-insights-grid { grid-template-columns: 1fr; }
  .welcome-banner { flex-direction: column; align-items: flex-start; }
}
</style>

<?php
/* Calcul pour les sections dynamiques */
$paidUsers = 0;
foreach ($planStats as $ps) if ($ps['plan'] !== 'GRATUIT') $paidUsers += $ps['nb'];
$convRate = $adm['total_users'] > 0 ? round($paidUsers / $adm['total_users'] * 100, 1) : 0;
$planColorsMap = ['GRATUIT'=>'#9CA3AF','BASIQUE'=>'#007A5E','PREMIUM'=>'#7C3AED','ECOLE'=>'#1E5FAD'];
$planBgMap     = ['GRATUIT'=>'#F3F4F6','BASIQUE'=>'#E8F5F1','PREMIUM'=>'#EDE9FE','ECOLE'=>'#DBEAFE'];
?>

<?php if (isset($_GET['welcome'])): ?>
<!-- ══ BANNIÈRE BIENVENUE ═══════════════════════════════ -->
<div class="welcome-banner" id="welcome-band">
  <div style="flex:1;min-width:0">
    <div style="font-size:11px;font-weight:700;color:rgba(255,255,255,.6);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px">
      Espace Administration
    </div>
    <div class="welcome-text-main">
      Bon retour, <?= e($user['prenom'] ?? 'Admin') ?> &mdash;
      <span style="font-weight:400;font-size:16px;color:rgba(255,255,255,.75)">tout est sous contrôle.</span>
    </div>
    <div class="welcome-text-sub" style="margin-top:6px">
      <?= number_format($adm['total_users']) ?> utilisateurs actifs &middot;
      <?= number_format($adm['total_archives']) ?> archives &middot;
      <?= number_format($adm['total_questions']) ?> questions en banque
      <?php if ($adm['paiements_att'] > 0): ?>
        &mdash; <strong style="color:#FDE68A"><?= $adm['paiements_att'] ?> paiement<?= $adm['paiements_att']>1?'s':'' ?> en attente</strong>
      <?php endif; ?>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:16px">
      <a href="/reussiteplus/admin/users.php" class="btn btn-sm" style="background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.25)">Utilisateurs</a>
      <a href="/reussiteplus/admin/paiements.php" class="btn btn-sm" style="background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.25)">Paiements</a>
      <a href="/reussiteplus/admin/archives.php" class="btn btn-sm" style="background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.25)">Archives</a>
      <button onclick="loadAiInsights()" id="ai-btn" class="btn btn-sm" style="background:rgba(124,58,237,.4);color:#e9d5ff;border:1px solid rgba(124,58,237,.5)">
        <i data-lucide="zap" style="width:13px;height:13px"></i> Analyse IA
      </button>
      <button onclick="exportAdminPDF()" id="export-pdf-btn" class="btn btn-sm" style="background:rgba(0,122,94,.4);color:#6EE7B7;border:1px solid rgba(0,122,94,.5)">
        <i data-lucide="file-down" style="width:13px;height:13px"></i> Exporter PDF
      </button>
    </div>
  </div>
  <div class="welcome-kpis">
    <div class="welcome-kpi-box">
      <div class="welcome-kpi-val"><?= $adm['exams_today'] ?></div>
      <div class="welcome-kpi-lbl">Examens aujourd'hui</div>
    </div>
    <div class="welcome-kpi-box">
      <div class="welcome-kpi-val"><?= number_format($adm['revenus_mois'], 0, ',', ' ') ?></div>
      <div class="welcome-kpi-lbl">CDF ce mois</div>
    </div>
  </div>
  <button onclick="document.getElementById('welcome-band').style.display='none'"
    style="position:absolute;top:12px;right:12px;background:rgba(255,255,255,.15);border:none;color:rgba(255,255,255,.7);width:26px;height:26px;border-radius:6px;cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center">
    &#x2715;
  </button>
</div>
<?php endif; ?>

<!-- ══ EN-TÊTE PAGE ════════════════════════════════════ -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px">
  <div>
    <h2 style="font-family:var(--font-display);font-size:20px;font-weight:900;color:var(--gris-900);margin:0">Tableau de bord</h2>
    <p style="font-size:12px;color:var(--gris-500);margin:3px 0 0">
      <?= date('l d F Y') ?> &middot; Connecté en tant que <strong><?= e($user['prenom'] ?? '') ?></strong>
    </p>
  </div>
  <div style="display:flex;gap:8px;align-items:center">
    <?php if ($adm['paiements_att'] > 0): ?>
    <a href="/reussiteplus/admin/paiements.php" class="btn btn-sm" style="background:#C9972A;color:#fff;border:none;font-weight:700">
      <?= $adm['paiements_att'] ?> paiement<?= $adm['paiements_att']>1?'s':'' ?> en attente
    </a>
    <?php endif; ?>
    <button onclick="loadAiInsights()" id="ai-btn" class="btn btn-sm" style="background:#7C3AED;color:#fff;border:none;font-weight:700">
      <i data-lucide="zap" style="width:13px;height:13px"></i> Analyse IA
    </button>
  </div>
</div>

<!-- ══ KPI PRINCIPAUX ══════════════════════════════════ -->
<div class="kpi-grid">

  <!-- Utilisateurs -->
  <div class="kpi-card" style="--kpi-color:#007A5E;--kpi-bg:rgba(0,122,94,.1)">
    <div class="kpi-icon"><i data-lucide="users"></i></div>
    <div class="kpi-value"><?= number_format($adm['total_users']) ?></div>
    <div class="kpi-label">Utilisateurs actifs</div>
    <div class="kpi-sub">
      <span class="kpi-chip">+<?= $adm['users_7j'] ?> cette semaine</span>
      <span style="font-size:10px;color:var(--gris-400)">+<?= $adm['users_today'] ?> aujourd'hui</span>
    </div>
  </div>

  <!-- Revenus -->
  <div class="kpi-card" style="--kpi-color:#C9972A;--kpi-bg:rgba(201,151,42,.1)">
    <div class="kpi-icon"><i data-lucide="trending-up"></i></div>
    <div class="kpi-value"><?= number_format($adm['revenus_mois'], 0, ',', ' ') ?></div>
    <div class="kpi-label">Revenus ce mois (CDF)</div>
    <div class="kpi-sub">
      <?php $gcPos = $revGrowth >= 0; ?>
      <span class="kpi-chip" style="--kpi-color:<?= $gcPos ? '#007A5E' : '#DC2626' ?>;--kpi-bg:<?= $gcPos ? 'rgba(0,122,94,.1)' : 'rgba(220,38,38,.1)' ?>">
        <?= $gcPos ? '+' : '' ?><?= $revGrowth ?>% vs mois dernier
      </span>
    </div>
  </div>

  <!-- Examens -->
  <div class="kpi-card" style="--kpi-color:#1E5FAD;--kpi-bg:rgba(30,95,173,.1)">
    <div class="kpi-icon"><i data-lucide="file-text"></i></div>
    <div class="kpi-value"><?= $adm['exams_today'] ?></div>
    <div class="kpi-label">Examens aujourd'hui</div>
    <div class="kpi-sub">
      <span class="kpi-chip"><?= $adm['exams_7j'] ?> cette semaine</span>
    </div>
  </div>

  <!-- Paiements en attente -->
  <div class="kpi-card" style="--kpi-color:<?= $adm['paiements_att']>0?'#B45309':'#9CA3AF' ?>;--kpi-bg:<?= $adm['paiements_att']>0?'rgba(180,83,9,.1)':'rgba(156,163,175,.1)' ?>;<?= $adm['paiements_att']>0?'border-color:#FDE68A;background:#FFFBEB':'' ?>">
    <div class="kpi-icon"><i data-lucide="credit-card"></i></div>
    <div class="kpi-value" style="color:<?= $adm['paiements_att']>0?'#B45309':'var(--gris-900)' ?>"><?= $adm['paiements_att'] ?></div>
    <div class="kpi-label">Paiements en attente</div>
    <div class="kpi-sub">
      <?php if ($adm['paiements_att'] > 0): ?>
        <a href="/reussiteplus/admin/paiements.php" style="font-size:11px;color:#B45309;font-weight:700">Traiter &rarr;</a>
      <?php else: ?>
        <span style="font-size:11px;color:var(--gris-400)">Tout est à jour</span>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- ══ KPI SECONDAIRES ════════════════════════════════ -->
<div class="kpi-grid-sm">
  <?php
  $kpis2 = [
    ['val'=>$adm['total_archives'],           'lbl'=>'Archives',        'color'=>'#059669', 'link'=>'/reussiteplus/admin/archives.php'],
    ['val'=>number_format($adm['total_questions']), 'lbl'=>'Questions',  'color'=>'#7C3AED', 'link'=>''],
    ['val'=>$adm['classes_actives'],          'lbl'=>'Classes actives', 'color'=>'#1E5FAD', 'link'=>''],
    ['val'=>$adm['messages_contact'],         'lbl'=>'Messages (48h)', 'color'=>'#DC2626', 'link'=>''],
  ];
  foreach ($kpis2 as $k):
  ?>
  <div class="kpi-sm" style="--kpi-color:<?= $k['color'] ?>">
    <div class="kpi-sm-bar"></div>
    <div>
      <div class="kpi-sm-val"><?= $k['val'] ?></div>
      <div class="kpi-sm-lbl"><?= $k['lbl'] ?></div>
      <?php if ($k['link']): ?>
        <a href="<?= $k['link'] ?>" style="font-size:10px;color:<?= $k['color'] ?>;font-weight:700;margin-top:2px;display:inline-block">Voir &rarr;</a>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ══ ANALYSE IA ═════════════════════════════════════ -->
<div class="ia-card" id="ai-panel">
  <div class="ia-card-header">
    <div style="display:flex;align-items:center;gap:8px">
      <div class="ia-dot"></div>
      <span style="font-family:var(--font-display);font-size:13px;font-weight:800;color:var(--gris-900)">Analyse intelligente de la plateforme</span>
    </div>
    <span style="font-size:11px;color:var(--gris-400)">Llama 3.1 · Groq</span>
  </div>
  <div class="ia-insights-grid">
    <?php
    $iaInsights = [
      ['tag'=>'Conversion','tagColor'=>'#7C3AED','tagBg'=>'#EDE9FE',
       'text'=>"Taux de conversion payant : <strong style='color:#7C3AED'>{$convRate}%</strong>. " . ($convRate < 20 ? "Améliorer l'onboarding pour convertir plus." : "Excellent — maintenir la qualité du contenu.")],
      ['tag'=>'Revenus','tagColor'=>'#C9972A','tagBg'=>'#FEF3C7',
       'text'=>"Revenus ce mois : <strong style='color:#C9972A'>" . number_format($adm['revenus_mois'],0,',',' ') . " CDF</strong>. " . ($revGrowth >= 0 ? "Croissance +{$revGrowth}% vs mois dernier." : "Baisse de {$revGrowth}% — revoir la stratégie.")],
      ['tag'=>'Activité','tagColor'=>'#1E5FAD','tagBg'=>'#DBEAFE',
       'text'=>"<strong style='color:#1E5FAD'>{$adm['exams_today']}</strong> examens lancés aujourd'hui. " . ($adm['exams_today'] >= 5 ? "Bonne activité journalière." : "Activité faible — envisager des notifications.")],
    ];
    foreach ($iaInsights as $ins):
    ?>
    <div class="ia-insight-card">
      <span class="ia-insight-tag" style="background:<?= $ins['tagBg'] ?>;color:<?= $ins['tagColor'] ?>"><?= $ins['tag'] ?></span>
      <p class="ia-insight-text"><?= $ins['text'] ?></p>
    </div>
    <?php endforeach; ?>
  </div>
  <div id="ai-loading" style="display:none;text-align:center;padding:16px 20px;color:var(--gris-500);font-size:13px">
    <span style="display:inline-block;width:16px;height:16px;border:2px solid var(--gris-200);border-top-color:#7C3AED;border-radius:50%;animation:spin .7s linear infinite;vertical-align:-3px;margin-right:8px"></span>
    Analyse Groq AI en cours...
  </div>
  <div id="ai-groq-result" style="display:none" class="ia-result-box">
    <div style="font-size:10px;font-weight:700;color:var(--gris-400);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Analyse approfondie — Groq AI</div>
    <div id="ai-groq-text" style="font-size:13px;color:var(--gris-700);line-height:1.8"></div>
  </div>
</div>

<!-- ══ GRAPHIQUES ═════════════════════════════════════ -->
<div class="grid-2col">

  <!-- Inscriptions 30j -->
  <div class="dash-card">
    <div class="dash-card-header">
      <div class="dash-card-title">
        <i data-lucide="bar-chart-2"></i>
        Inscriptions — 30 derniers jours
      </div>
      <span style="font-size:11px;color:var(--gris-400)"><?= array_sum(array_values($inscMap)) ?> nouvelles</span>
    </div>
    <div class="dash-card-body">
      <?php $maxI = max(1, max(array_merge([1], array_values($inscMap)))); ?>
      <div class="bar-chart">
        <?php for ($d = 29; $d >= 0; $d--):
          $jour = date('Y-m-d', strtotime("-{$d} days"));
          $nb   = $inscMap[$jour] ?? 0;
          $h    = $nb > 0 ? max(6, (int)(($nb / $maxI) * 80)) : 3;
        ?>
        <div class="bar" style="height:<?= $h ?>px;background:<?= $nb>0?'var(--primary)':'var(--gris-200)' ?>"
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
  <div class="dash-card">
    <div class="dash-card-header">
      <div class="dash-card-title">
        <i data-lucide="trending-up"></i>
        Revenus — 6 derniers mois
      </div>
      <span style="font-size:11px;color:var(--gris-400)">CDF confirmés</span>
    </div>
    <div class="dash-card-body">
      <?php
      $revMap = array_column($rev6m, 'total', 'mois');
      $maxR   = max(1, max(array_merge([1], array_values($revMap))));
      $moisL  = [];
      for ($m = 5; $m >= 0; $m--) $moisL[] = date('Y-m', strtotime("-{$m} month"));
      $shortM = ['01'=>'Jan','02'=>'Fév','03'=>'Mar','04'=>'Avr','05'=>'Mai','06'=>'Jun','07'=>'Jul','08'=>'Août','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Déc'];
      ?>
      <div class="bar-chart">
        <?php foreach ($moisL as $mois):
          $v = (float)($revMap[$mois] ?? 0);
          $h = $v > 0 ? max(6, (int)(($v / $maxR) * 80)) : 3;
          $lbl = $shortM[substr($mois,5,2)] ?? substr($mois,5,2);
        ?>
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:3px">
          <div class="bar" style="width:100%;height:<?= $h ?>px;background:<?= $v>0?'#C9972A':'var(--gris-200)' ?>"
               onmouseover="showTip(this,'<?= $lbl ?> : <?= number_format($v,0,',',' ') ?> CDF')"
               onmouseout="hideTip()"></div>
          <div style="font-size:9px;color:var(--gris-400)"><?= $lbl ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

</div>

<!-- ══ PLANS + ACTIVITÉ ══════════════════════════════ -->
<div class="grid-2col">

  <!-- Plans -->
  <div class="dash-card">
    <div class="dash-card-header">
      <div class="dash-card-title">
        <i data-lucide="pie-chart"></i>
        Répartition des abonnements
      </div>
      <span style="font-size:11px;color:var(--gris-400)"><?= number_format($totalUsers) ?> utilisateurs</span>
    </div>
    <div class="dash-card-body">
      <?php foreach ($planStats as $ps):
        $pct = round(($ps['nb'] / $totalUsers) * 100, 1);
        $fc  = $planColorsMap[$ps['plan']] ?? '#9CA3AF';
        $nom = (PLANS[$ps['plan']] ?? ['nom'=>$ps['plan']])['nom'];
      ?>
      <div class="plan-bar-row">
        <div class="plan-bar-label">
          <div style="display:flex;align-items:center;gap:7px">
            <span style="width:8px;height:8px;background:<?= $fc ?>;border-radius:50%;display:inline-block"></span>
            <span style="font-size:13px;font-weight:600;color:var(--gris-800)"><?= e($nom) ?></span>
          </div>
          <div>
            <strong style="font-size:13px;color:var(--gris-900)"><?= $ps['nb'] ?></strong>
            <span style="font-size:11px;color:var(--gris-400);margin-left:4px"><?= $pct ?>%</span>
          </div>
        </div>
        <div class="plan-bar-track">
          <div class="plan-bar-fill" style="width:<?= $pct ?>%;background:<?= $fc ?>"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Examens du jour -->
  <div class="dash-card">
    <div class="dash-card-header">
      <div class="dash-card-title">
        <i data-lucide="activity"></i>
        Activité — Examens du jour
      </div>
      <span style="background:var(--primary);color:#fff;font-size:10px;font-weight:800;padding:2px 8px;border-radius:8px"><?= $adm['exams_today'] ?></span>
    </div>
    <div style="padding:8px 16px">
      <?php if ($examSessions): foreach ($examSessions as $es): ?>
      <div class="act-item">
        <div class="act-dot" style="background:<?= ($es['statut']??'')===  'TERMINE'?'#007A5E':'#F59E0B' ?>"></div>
        <div style="flex:1;min-width:0">
          <div style="font-size:13px;font-weight:600;color:var(--gris-800);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
            <?= e(($es['prenom']??'').' '.($es['nom']??'')) ?>
          </div>
          <div style="font-size:11px;color:var(--gris-500)">
            <?= e($es['titre_custom']??'Examen') ?>
            <?= $es['score'] !== null ? ' &middot; '.round((float)$es['score']).'%' : '' ?>
          </div>
        </div>
        <div style="font-size:10px;color:var(--gris-400);white-space:nowrap;flex-shrink:0">
          <?= $es['started_at'] ? date('H:i', strtotime($es['started_at'])) : '' ?>
        </div>
      </div>
      <?php endforeach; else: ?>
      <div style="text-align:center;padding:28px;color:var(--gris-400);font-size:13px">Aucune session aujourd'hui</div>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- ══ PAIEMENTS EN ATTENTE ══════════════════════════ -->
<?php if ($paiementsAtt): ?>
<div class="dash-card" style="margin-bottom:24px;border-color:#FDE68A">
  <div class="dash-card-header" style="background:#FFFBEB">
    <div class="dash-card-title" style="color:#92400E">
      <span class="ia-dot" style="background:#F59E0B;flex-shrink:0"></span>
      Paiements en attente de confirmation
    </div>
    <a href="/reussiteplus/admin/paiements.php" class="btn btn-sm" style="background:#C9972A;color:#fff;border:none">Tout voir</a>
  </div>
  <div class="adm-table-wrap">
    <table class="adm-table">
      <thead>
        <tr>
          <th>Référence</th><th>Utilisateur</th><th>Plan</th>
          <th>Montant</th><th>Méthode</th><th>Date</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($paiementsAtt as $p):
        $planInfo = PLANS[$p['plan']] ?? ['nom'=>$p['plan']];
        $pc = $planColorsMap[$p['plan']] ?? '#9CA3AF';
      ?>
      <tr>
        <td><code style="font-size:11px;background:var(--gris-100);padding:2px 6px;border-radius:4px"><?= e(substr($p['reference_paiement']??'',0,18)) ?></code></td>
        <td>
          <div style="font-weight:600"><?= e($p['prenom'].' '.$p['nom']) ?></div>
          <div style="font-size:11px;color:var(--gris-500)"><?= e($p['email']) ?></div>
        </td>
        <td><span class="plan-pill" style="background:<?= $pc ?>22;color:<?= $pc ?>"><?= e($planInfo['nom']) ?></span></td>
        <td style="font-weight:800;white-space:nowrap"><?= number_format((float)$p['montant'],0,',',' ') ?> <span style="font-size:10px;color:var(--gris-400)">CDF</span></td>
        <td style="font-size:12px"><?= e(METHODES_PAIEMENT[$p['methode_paiement']]['nom'] ?? $p['methode_paiement']) ?></td>
        <td style="font-size:11px;color:var(--gris-500)"><?= date('d/m H:i', strtotime($p['created_at'])) ?></td>
        <td style="white-space:nowrap">
          <a href="/reussiteplus/admin/paiements.php?action=confirmer&id=<?= e($p['id']) ?>&csrf=<?= $csrf_token ?>" class="btn btn-sm" style="background:#E8F5F1;color:#007A5E;border:none" onclick="return confirm('Confirmer ?')">✓</a>
          <a href="/reussiteplus/admin/paiements.php?action=refuser&id=<?= e($p['id']) ?>&csrf=<?= $csrf_token ?>" class="btn btn-sm" style="background:#FEE2E2;color:#DC2626;border:none;margin-left:4px" onclick="return confirm('Refuser ?')">✕</a>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- ══ DERNIERS INSCRITS + MESSAGES ══════════════════ -->
<div class="grid-2col">

  <!-- Inscrits -->
  <div class="dash-card">
    <div class="dash-card-header">
      <div class="dash-card-title">
        <i data-lucide="user-plus"></i>
        Derniers inscrits
      </div>
      <a href="/reussiteplus/admin/users.php" class="btn btn-ghost btn-sm">Voir tous</a>
    </div>
    <div class="adm-table-wrap">
      <table class="adm-table">
        <thead>
          <tr><th>Utilisateur</th><th>Plan</th><th>Inscrit</th></tr>
        </thead>
        <tbody>
        <?php foreach ($lastUsers as $u):
          $pc2 = $planColorsMap[$u['plan']] ?? '#9CA3AF';
          $pi2 = PLANS[$u['plan']] ?? ['nom'=>$u['plan']];
        ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:9px">
              <div style="width:30px;height:30px;border-radius:50%;background:<?= $pc2 ?>22;color:<?= $pc2 ?>;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px;flex-shrink:0">
                <?= strtoupper(mb_substr($u['prenom']??'?',0,1)) ?>
              </div>
              <div>
                <div style="font-weight:600;font-size:13px"><?= e($u['prenom'].' '.$u['nom']) ?></div>
                <div style="font-size:11px;color:var(--gris-500)"><?= e($u['email']) ?></div>
              </div>
            </div>
          </td>
          <td><span class="plan-pill" style="background:<?= $pc2 ?>22;color:<?= $pc2 ?>"><?= e($pi2['nom']) ?></span></td>
          <td style="font-size:11px;color:var(--gris-500);white-space:nowrap"><?= temps_relatif($u['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Messages -->
  <div class="dash-card">
    <div class="dash-card-header">
      <div class="dash-card-title">
        <i data-lucide="message-square"></i>
        Messages de contact récents
      </div>
      <?php if ($adm['messages_contact'] > 0): ?>
      <span style="background:#FEE2E2;color:#DC2626;font-size:10px;font-weight:800;padding:2px 8px;border-radius:8px"><?= $adm['messages_contact'] ?> nouveaux</span>
      <?php endif; ?>
    </div>
    <?php if ($lastMessages): foreach ($lastMessages as $msg):
      $sujetCol = ['PLAN'=>'#007A5E','TECHNIQUE'=>'#DC2626','PARTENARIAT'=>'#7C3AED','PRESSE'=>'#1E5FAD','AUTRE'=>'#9CA3AF'];
      $sc = $sujetCol[$msg['sujet']??'AUTRE'] ?? '#9CA3AF';
    ?>
    <div style="padding:11px 20px;border-bottom:1px solid var(--gris-100)">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
        <div style="display:flex;align-items:center;gap:7px;min-width:0">
          <span style="width:7px;height:7px;background:<?= $sc ?>;border-radius:50%;flex-shrink:0"></span>
          <span style="font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($msg['nom']??'') ?></span>
          <span style="background:<?= $sc ?>22;color:<?= $sc ?>;font-size:9px;font-weight:800;padding:1px 6px;border-radius:4px;flex-shrink:0"><?= e($msg['sujet']??'') ?></span>
        </div>
        <span style="font-size:10px;color:var(--gris-400);white-space:nowrap;flex-shrink:0"><?= temps_relatif($msg['created_at']) ?></span>
      </div>
      <p style="font-size:12px;color:var(--gris-600);margin:4px 0 0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e(mb_strimwidth($msg['message']??'',0,80,'...')) ?></p>
    </div>
    <?php endforeach; else: ?>
    <div style="text-align:center;padding:28px;color:var(--gris-400);font-size:13px">Aucun message récent</div>
    <?php endif; ?>
  </div>

</div>

<!-- ══ ACTIONS RAPIDES ════════════════════════════════ -->
<div class="dash-card" style="margin-bottom:24px">
  <div class="dash-card-header">
    <div class="dash-card-title">
      <i data-lucide="zap"></i>
      Actions rapides
    </div>
  </div>
  <div class="quick-actions">
    <a href="/reussiteplus/admin/users.php" class="btn btn-ghost btn-sm" aria-label="Voir les utilisateurs"><i data-lucide="users" style="width:13px;height:13px"></i> Utilisateurs</a>
    <a href="/reussiteplus/admin/paiements.php" class="btn btn-ghost btn-sm" aria-label="Voir les paiements"><i data-lucide="credit-card" style="width:13px;height:13px"></i> Paiements</a>
    <a href="/reussiteplus/admin/archives.php" class="btn btn-ghost btn-sm" aria-label="Voir les archives"><i data-lucide="folder-open" style="width:13px;height:13px"></i> Archives</a>
    <a href="/reussiteplus/tarifs.php" target="_blank" class="btn btn-ghost btn-sm" aria-label="Voir les tarifs"><i data-lucide="tag" style="width:13px;height:13px"></i> Tarifs</a>
    <button onclick="window.location='/reussiteplus/admin/users.php?export=csv&csrf=<?= $csrf_token ?>'" class="btn btn-ghost btn-sm" aria-label="Exporter les utilisateurs en CSV"><i data-lucide="download" style="width:13px;height:13px"></i> Exporter CSV</button>
    <button onclick="loadAiInsights()" class="btn btn-sm" style="background:#7C3AED;color:#fff;border:none" aria-label="Rapport IA"><i data-lucide="zap" style="width:13px;height:13px"></i> Rapport IA</button>
  </div>
</div>

<!-- Tooltip -->
<div id="tooltip" style="display:none;position:fixed;background:rgba(15,23,42,.9);color:#fff;font-size:11px;padding:5px 10px;border-radius:6px;pointer-events:none;z-index:9999"></div>

<script>
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

async function loadAiInsights() {
  const btn    = document.getElementById('ai-btn');
  const result = document.getElementById('ai-groq-result');
  const loader = document.getElementById('ai-loading');
  const text   = document.getElementById('ai-groq-text');
  btn.disabled = true;
  btn.innerHTML = '<i data-lucide="loader" style="width:13px;height:13px;animation:spin .7s linear infinite"></i> Analyse...';
  loader.style.display = 'block';
  result.style.display = 'none';
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
  } catch(e) {
    text.innerHTML = '<span style="color:#DC2626">Impossible de contacter l\'IA. Vérifiez votre connexion.</span>';
    result.style.display = 'block';
  }
  loader.style.display = 'none';
  btn.disabled = false;
  btn.innerHTML = '<i data-lucide="zap" style="width:13px;height:13px"></i> Analyse IA';
  if (typeof lucide !== 'undefined') lucide.createIcons();
  result.scrollIntoView({behavior:'smooth', block:'center'});
}

// ── Export PDF Admin ──────────────────────────────────────
function exportAdminPDF() {
  if (typeof AdminPdf === 'undefined') { alert('Générateur PDF non chargé.'); return; }
  const btn  = document.getElementById('export-pdf-btn');
  const orig = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<svg style="animation:spin .7s linear infinite;display:inline-block" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Génération…';

  // Récupère le texte de l'analyse IA si disponible
  const iaText = document.getElementById('ai-groq-text')?.innerText?.trim() || '';

  const reportData = JSON.parse(document.getElementById('adminReportData').textContent);
  reportData.iaText = iaText;

  try {
    AdminPdf.open(reportData);
  } catch(e) {
    alert('Erreur lors de la génération du PDF.');
  }
  setTimeout(() => { btn.disabled = false; btn.innerHTML = orig; if (typeof lucide !== 'undefined') lucide.createIcons(); }, 1500);
}
</script>
<style>@keyframes spin { to { transform: rotate(360deg); } }</style>

<!-- ═══ EXPORTS ════════════════════════════════════════════ -->
<div class="card" style="margin-top:24px">
  <div class="card-header">
    <div class="card-title">
      <i data-lucide="download" style="width:16px;height:16px;vertical-align:-3px;margin-right:6px"></i>
      Exports de données
    </div>
  </div>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">

    <a href="/reussiteplus/admin/users.php?export=csv" class="export-card">
      <div class="export-icon" style="background:#EEF4FD">
        <i data-lucide="users" style="width:20px;height:20px;stroke:#1E5FAD"></i>
      </div>
      <div>
        <div class="export-title">Utilisateurs</div>
        <div class="export-sub">ID, nom, email, plan, classe, province</div>
      </div>
      <i data-lucide="download" style="width:14px;height:14px;stroke:var(--gris-400);margin-left:auto"></i>
    </a>

    <a href="/reussiteplus/admin/paiements.php?export=csv" class="export-card">
      <div class="export-icon" style="background:#FFF8EC">
        <i data-lucide="credit-card" style="width:20px;height:20px;stroke:#C9972A"></i>
      </div>
      <div>
        <div class="export-title">Paiements & Abonnements</div>
        <div class="export-sub">Références, montants, statuts, opérateurs</div>
      </div>
      <i data-lucide="download" style="width:14px;height:14px;stroke:var(--gris-400);margin-left:auto"></i>
    </a>

    <a href="/reussiteplus/admin/stats_matieres.php?export=csv" class="export-card">
      <div class="export-icon" style="background:#E8F5F1">
        <i data-lucide="bar-chart-2" style="width:20px;height:20px;stroke:#007A5E"></i>
      </div>
      <div>
        <div class="export-title">Résultats d'examens</div>
        <div class="export-sub">Tous les examens blancs avec scores</div>
      </div>
      <i data-lucide="download" style="width:14px;height:14px;stroke:var(--gris-400);margin-left:auto"></i>
    </a>

    <a href="/reussiteplus/admin/messages.php?export=csv" class="export-card">
      <div class="export-icon" style="background:#FEF0EF">
        <i data-lucide="mail" style="width:20px;height:20px;stroke:#C9342A"></i>
      </div>
      <div>
        <div class="export-title">Messages de contact</div>
        <div class="export-sub">Tous les messages reçus du formulaire</div>
      </div>
      <i data-lucide="download" style="width:14px;height:14px;stroke:var(--gris-400);margin-left:auto"></i>
    </a>

  </div>
</div>

<style>
.export-card{
  display:flex;align-items:center;gap:12px;
  padding:14px 16px;border-radius:var(--radius-lg);
  border:1px solid var(--gris-200);background:var(--gris-50);
  text-decoration:none;transition:all .18s;cursor:pointer;
}
.export-card:hover{background:var(--blanc);border-color:var(--primary);box-shadow:var(--shadow-sm);}
.export-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.export-title{font-size:13px;font-weight:700;color:var(--gris-900);margin-bottom:2px;}
.export-sub{font-size:11px;color:var(--gris-500);}
</style>

<?php
// Données sérialisées pour le générateur PDF admin
$convRate = $totalUsers > 0 ? round(array_sum(array_filter(array_column($planStats ?? [], 'nb'), fn($nb, $plan) => $plan !== 'GRATUIT', ARRAY_FILTER_USE_BOTH)) / $totalUsers * 100, 1) : 0;
// recalcul simple : utilisateurs payants / total
$payants = 0;
foreach ($planStats ?? [] as $ps) { if ($ps['plan'] !== 'GRATUIT') $payants += (int)$ps['nb']; }
$convRate = $totalUsers > 0 ? round($payants / $totalUsers * 100, 1) : 0;

$adminReportData = json_encode([
    'adminName'    => e($user['prenom'] . ' ' . $user['nom']),
    'periodeLabel' => date('F Y'),
    'totalUsers'   => $adm['total_users'],
    'usersToday'   => $adm['users_today'],
    'users7j'      => $adm['users_7j'],
    'revenus'      => $adm['revenus_mois'],
    'revGrowth'    => $revGrowth,
    'examsToday'   => $adm['exams_today'],
    'exams7j'      => $adm['exams_7j'],
    'paiementsAtt' => $adm['paiements_att'],
    'convRate'     => $convRate,
    'planStats'    => array_column($planStats ?? [], 'nb', 'plan'),
    'rev6m'        => array_map(fn($r) => ['mois'=>$r['mois'],'label'=>$r['mois'],'total'=>$r['total']], $rev6m ?? []),
], JSON_UNESCAPED_UNICODE);
?>
<script id="adminReportData" type="application/json"><?= $adminReportData ?></script>
<script src="/reussiteplus/assets/js/admin-pdf.js?v=<?= filemtime(__DIR__ . '/../assets/js/admin-pdf.js') ?>"></script>

<?php include __DIR__ . '/../includes/footer_app.php'; ?>
