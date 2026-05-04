<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$pageTitle  = 'Administration';
$pageActive = 'admin';
$user = require_admin();

// Statistiques générales
$adminStats = [
    'total_users'    => dbRow("SELECT COUNT(*) as n FROM utilisateurs WHERE is_active=1")['n'],
    'users_today'    => dbRow("SELECT COUNT(*) as n FROM utilisateurs WHERE DATE(created_at)=CURDATE()")['n'],
    'total_archives' => dbRow("SELECT COUNT(*) as n FROM archives")['n'],
    'exams_today'    => dbRow("SELECT COUNT(*) as n FROM exam_sessions WHERE DATE(started_at)=CURDATE()")['n'],
    'paiements_att'  => dbRow("SELECT COUNT(*) as n FROM abonnements WHERE statut='EN_ATTENTE'")['n'],
    'revenus_mois'   => dbRow("SELECT COALESCE(SUM(montant),0) as n FROM abonnements WHERE statut='CONFIRME' AND MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())")['n'],
];

// Répartition par plan
$planStats = dbAll(
    "SELECT plan, COUNT(*) as nb FROM utilisateurs WHERE is_active=1 GROUP BY plan ORDER BY FIELD(plan,'ECOLE','PREMIUM','BASIQUE','GRATUIT')"
);

// Inscriptions 7 derniers jours
$inscriptions7j = dbAll(
    "SELECT DATE(created_at) as jour, COUNT(*) as nb FROM utilisateurs WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at) ORDER BY jour ASC"
);

// Derniers utilisateurs
$lastUsers = dbAll(
    "SELECT id, prenom, nom, email, plan, role, created_at FROM utilisateurs ORDER BY created_at DESC LIMIT 8"
);

// Paiements en attente
$paiementsAtt = dbAll(
    "SELECT a.*, u.email, u.prenom, u.nom FROM abonnements a JOIN utilisateurs u ON a.user_id=u.id WHERE a.statut='EN_ATTENTE' ORDER BY a.created_at DESC LIMIT 10"
);

include __DIR__ . '/../includes/header_app.php';
?>

<!-- Stats overview -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px">
  <div class="stat-card green">
    <div class="stat-label"><i class="bi bi-people"></i> Utilisateurs actifs</div>
    <div class="stat-value"><?= number_format((int)$adminStats['total_users']) ?></div>
    <div class="stat-sub">+<?= $adminStats['users_today'] ?> aujourd'hui</div>
  </div>
  <div class="stat-card gold">
    <div class="stat-label"><i class="bi bi-cash-coin"></i> Revenus ce mois</div>
    <div class="stat-value"><?= number_format((float)$adminStats['revenus_mois'], 0, ',', ' ') ?></div>
    <div class="stat-sub">CDF confirmés</div>
  </div>
  <div class="stat-card rouge">
    <div class="stat-label"><i class="bi bi-hourglass-split"></i> Paiements en attente</div>
    <div class="stat-value"><?= (int)$adminStats['paiements_att'] ?></div>
    <div class="stat-sub"><a href="/reussiteplus/admin/paiements.php" style="color:var(--rouge);font-weight:600">À confirmer →</a></div>
  </div>
  <div class="stat-card bleu">
    <div class="stat-label"><i class="bi bi-pencil-square"></i> Examens aujourd'hui</div>
    <div class="stat-value"><?= number_format((int)$adminStats['exams_today']) ?></div>
    <div class="stat-sub">Sessions lancées</div>
  </div>
  <div class="stat-card">
    <div class="stat-label"><i class="bi bi-archive"></i> Archives</div>
    <div class="stat-value"><?= number_format((int)$adminStats['total_archives']) ?></div>
    <div class="stat-sub"><a href="/reussiteplus/admin/archives.php" style="color:var(--primary);font-weight:600">Gérer →</a></div>
  </div>
  <div class="stat-card">
    <div class="stat-label"><i class="bi bi-list-check"></i> Sujets en banque</div>
    <div class="stat-value"><?= dbRow("SELECT COUNT(*) as n FROM question_bank")['n'] ?></div>
    <div class="stat-sub">Questions QCM</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px">
  <!-- Répartition plans -->
  <div class="card">
    <div class="card-title" style="margin-bottom:16px"><i class="bi bi-pie-chart"></i> Répartition des plans</div>
    <?php
    $total = max(1, array_sum(array_column($planStats, 'nb')));
    foreach ($planStats as $ps):
      $pct = ($ps['nb'] / $total) * 100;
      $info = PLANS[$ps['plan']] ?? ['nom' => $ps['plan'], 'icone' => 'bi bi-circle'];
    ?>
    <div style="margin-bottom:12px">
      <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
        <span><i class="<?= e($info['icone']) ?>"></i> <?= e($info['nom']) ?></span>
        <span style="font-weight:700"><?= $ps['nb'] ?> <span style="font-weight:400;color:var(--gris-500)">(<?= number_format($pct, 1) ?>%)</span></span>
      </div>
      <div class="progress-bar"><div class="progress-bar-fill" style="width:<?= $pct ?>%"></div></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Inscriptions 7j -->
  <div class="card">
    <div class="card-title" style="margin-bottom:16px"><i class="bi bi-bar-chart"></i> Inscriptions — 7 derniers jours</div>
    <?php
    $inscMap = [];
    foreach ($inscriptions7j as $i) $inscMap[$i['jour']] = (int)$i['nb'];
    $maxI = max(1, ...array_values($inscMap ?: [1]));
    ?>
    <div style="display:flex;align-items:flex-end;gap:6px;height:80px">
      <?php for ($d = 6; $d >= 0; $d--):
        $jour = date('Y-m-d', strtotime("-{$d} days"));
        $nb = $inscMap[$jour] ?? 0;
        $h = $nb > 0 ? max(12, (int)(($nb / $maxI) * 72)) : 4;
      ?>
      <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:3px">
        <div style="font-size:10px;color:var(--gris-600);font-weight:600"><?= $nb > 0 ? $nb : '' ?></div>
        <div title="<?= date('d/m', strtotime($jour)) ?> — <?= $nb ?> inscriptions" style="width:100%;height:<?= $h ?>px;background:<?= $nb > 0 ? 'var(--primary)' : 'var(--gris-200)' ?>;border-radius:4px;transition:.2s" onmouseover="this.style.opacity='.7'" onmouseout="this.style.opacity='1'"></div>
        <div style="font-size:9px;color:var(--gris-400)"><?= date('d/m', strtotime($jour)) ?></div>
      </div>
      <?php endfor; ?>
    </div>
  </div>
</div>

<!-- Paiements en attente -->
<?php if ($paiementsAtt): ?>
<div class="card" style="margin-bottom:24px;border:2px solid var(--gold)">
  <div class="card-header">
    <div class="card-title"><i class="bi bi-hourglass-split"></i> Paiements en attente de confirmation</div>
    <a href="/reussiteplus/admin/paiements.php" class="btn btn-gold btn-sm">Voir tout</a>
  </div>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Référence</th><th>Utilisateur</th><th>Plan</th><th>Montant</th><th>Méthode</th><th>Date</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach ($paiementsAtt as $p): ?>
      <tr>
        <td style="font-family:var(--font-mono);font-size:11px"><?= e($p['reference_paiement']) ?></td>
        <td style="font-size:13px"><?= e($p['prenom'] . ' ' . $p['nom']) ?><div style="font-size:11px;color:var(--gris-500)"><?= e($p['email']) ?></div></td>
        <td><?= e(PLANS[$p['plan']]['nom'] ?? $p['plan']) ?></td>
        <td style="font-weight:700"><?= number_format((float)$p['montant'], 0, ',', ' ') ?> CDF</td>
        <td style="font-size:12px"><?= e(METHODES_PAIEMENT[$p['methode_paiement']]['nom'] ?? $p['methode_paiement']) ?></td>
        <td style="font-size:12px;color:var(--gris-500)"><?= date('d/m H:i', strtotime($p['created_at'])) ?></td>
        <td>
          <a href="/reussiteplus/admin/paiements.php?action=confirmer&id=<?= e($p['id']) ?>" class="btn btn-primary btn-sm" onclick="return confirm('Confirmer ce paiement ?')"><i class="bi bi-check-lg"></i></a>
          <a href="/reussiteplus/admin/paiements.php?action=refuser&id=<?= e($p['id']) ?>" class="btn btn-danger btn-sm" onclick="return confirm('Refuser ce paiement ?')"><i class="bi bi-x-lg"></i></a>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Derniers inscrits -->
<div class="card">
  <div class="card-header">
    <div class="card-title">👥 Derniers inscrits</div>
    <a href="/reussiteplus/admin/users.php" class="btn btn-ghost btn-sm">Voir tous</a>
  </div>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Nom</th><th>Email</th><th>Plan</th><th>Rôle</th><th>Inscrit le</th></tr></thead>
      <tbody>
      <?php foreach ($lastUsers as $u): ?>
      <tr>
        <td><?= e($u['prenom'] . ' ' . $u['nom']) ?></td>
        <td style="font-size:12px;color:var(--gris-600)"><?= e($u['email']) ?></td>
        <td><?= badge_plan($u['plan']) ?></td>
        <td style="font-size:12px"><?= e($u['role']) ?></td>
        <td style="font-size:12px;color:var(--gris-500)"><?= temps_relatif($u['created_at']) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer_app.php'; ?>
