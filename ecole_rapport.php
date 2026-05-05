<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$user = require_login();
if ($user['plan'] !== 'ECOLE') redirect('/reussiteplus/tarifs.php');

$classeId = $_GET['classe'] ?? '';
$classe = dbRow("SELECT * FROM classes_ecole WHERE id=? AND admin_id=?", [$classeId, $user['id']]);
if (!$classe) redirect('/reussiteplus/ecole.php', 'error', 'Classe introuvable.');

$eleves = dbAll(
    "SELECT u.nom, u.prenom, u.email, u.classe, u.total_examens, u.score_moyen, u.streak_jours, u.derniere_activite, u.created_at
     FROM classe_membres cm
     JOIN utilisateurs u ON u.id = cm.eleve_id
     WHERE cm.classe_id = ? AND cm.statut='ACTIF'
     ORDER BY u.score_moyen DESC",
    [$classeId]
);

$mois = date('F Y');
$scores = array_column($eleves, 'score_moyen');
$scoreMoyen = count($scores) ? round(array_sum($scores) / count($scores), 1) : 0;
$actifs = count(array_filter($eleves, fn($e) => $e['derniere_activite'] && strtotime($e['derniere_activite']) > strtotime('-30 days')));

$pageTitle = 'Rapport mensuel — ' . e($classe['nom']);
include __DIR__ . '/includes/header_app.php';
?>

<style>
@media print {
  .sidebar, .top-bar, .no-print { display: none !important; }
  .main-content { margin: 0 !important; padding: 0 !important; }
  body { font-size: 12px; }
}
.rapport-paper { background: #fff; border: 1px solid var(--gris-200); border-radius: var(--radius-lg); padding: 40px; max-width: 860px; margin: 0 auto; }
.rapport-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 32px; padding-bottom: 20px; border-bottom: 2px solid var(--gris-200); }
.rapport-logo { font-family: var(--font-display); font-size: 22px; font-weight: 900; color: var(--primary); }
.rapport-title { font-family: var(--font-display); font-size: 18px; font-weight: 800; margin-bottom: 4px; }
.rapport-meta { font-size: 13px; color: var(--gris-600); }
.rapport-stat-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 12px; margin-bottom: 28px; }
.rapport-stat { background: var(--gris-50); border-radius: var(--radius); padding: 14px; text-align: center; border: 1px solid var(--gris-200); }
.rapport-stat-val { font-family: var(--font-display); font-size: 24px; font-weight: 900; line-height: 1; }
.rapport-table th { background: var(--gris-100); font-size: 11px; text-transform: uppercase; letter-spacing: .4px; color: var(--gris-600); padding: 8px 12px; text-align: left; }
.rapport-table td { padding: 10px 12px; font-size: 13px; border-bottom: 1px solid var(--gris-100); }
.rapport-table tr:last-child td { border-bottom: none; }
.rang-badge { width: 24px; height: 24px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; }
</style>

<div class="no-print" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
  <a href="/reussiteplus/ecole.php?classe=<?= e($classeId) ?>" class="btn btn-ghost">
    <i data-lucide="arrow-left" style="width:14px;height:14px;vertical-align:-2px"></i> Retour
  </a>
  <button onclick="window.print()" class="btn btn-primary">
    <i data-lucide="printer" style="width:14px;height:14px;vertical-align:-2px"></i> Imprimer / PDF
  </button>
</div>

<div class="rapport-paper">
  <!-- En-tête -->
  <div class="rapport-header">
    <div>
      <div class="rapport-logo">RÉUSSITE+</div>
      <div style="font-size:11px;color:var(--gris-500)">Plateforme EdTech RDC</div>
    </div>
    <div style="text-align:right">
      <div class="rapport-title">Rapport mensuel de classe</div>
      <div class="rapport-meta">
        <?= e($classe['nom']) ?> <?= $classe['niveau'] ? '· '.e($classe['niveau']) : '' ?><br>
        <?= $classe['annee_scolaire'] ? e($classe['annee_scolaire']).' · ' : '' ?>
        Généré le <?= date('d/m/Y à H:i') ?>
      </div>
    </div>
  </div>

  <!-- Stats globales -->
  <div class="rapport-stat-grid">
    <div class="rapport-stat">
      <div class="rapport-stat-val" style="color:var(--primary)"><?= count($eleves) ?></div>
      <div style="font-size:11px;color:var(--gris-500);margin-top:3px;text-transform:uppercase">Élèves</div>
    </div>
    <div class="rapport-stat">
      <div class="rapport-stat-val" style="color:var(--gold)"><?= $scoreMoyen ?>%</div>
      <div style="font-size:11px;color:var(--gris-500);margin-top:3px;text-transform:uppercase">Score moyen</div>
    </div>
    <div class="rapport-stat">
      <div class="rapport-stat-val" style="color:#059669"><?= $actifs ?></div>
      <div style="font-size:11px;color:var(--gris-500);margin-top:3px;text-transform:uppercase">Actifs (30j)</div>
    </div>
    <div class="rapport-stat">
      <div class="rapport-stat-val" style="color:#1E5FAD"><?= array_sum(array_column($eleves, 'total_examens')) ?></div>
      <div style="font-size:11px;color:var(--gris-500);margin-top:3px;text-transform:uppercase">Examens passés</div>
    </div>
  </div>

  <!-- Tableau des élèves -->
  <div style="font-family:var(--font-display);font-size:15px;font-weight:700;margin-bottom:12px;display:flex;align-items:center;gap:8px">
    <i data-lucide="users" style="width:16px;height:16px;stroke:var(--primary)"></i>
    Classement des élèves
  </div>

  <table style="width:100%;border-collapse:collapse" class="rapport-table">
    <thead>
      <tr>
        <th style="width:40px">Rang</th>
        <th>Élève</th>
        <th>Classe</th>
        <th style="text-align:center">Examens</th>
        <th style="text-align:center">Score moyen</th>
        <th style="text-align:center">Série</th>
        <th>Dernière activité</th>
        <th>Appréciation</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($eleves as $idx => $e): ?>
    <?php
      $rang = $idx + 1;
      $score = round($e['score_moyen']);
      $mention = $score >= 80 ? ['Excellent','#059669'] : ($score >= 60 ? ['Bien','#1E5FAD'] : ($score >= 50 ? ['Passable','#D97706'] : ['À améliorer','#DC2626']));
    ?>
    <tr>
      <td style="text-align:center">
        <?php if ($rang <= 3): ?>
        <span class="rang-badge" style="background:<?= ['#FFD700','#C0C0C0','#CD7F32'][$rang-1] ?>;color:#fff"><?= $rang ?></span>
        <?php else: ?>
        <span style="font-size:12px;color:var(--gris-500)"><?= $rang ?></span>
        <?php endif; ?>
      </td>
      <td>
        <div style="font-weight:600"><?= e($e['prenom']) ?> <?= e($e['nom']) ?></div>
        <div style="font-size:11px;color:var(--gris-500)"><?= e($e['email']) ?></div>
      </td>
      <td style="font-size:12px;color:var(--gris-600)"><?= $e['classe'] ? e($e['classe']) : '—' ?></td>
      <td style="text-align:center;font-weight:700"><?= $e['total_examens'] ?></td>
      <td style="text-align:center">
        <span style="font-weight:700;color:<?= $mention[1] ?>"><?= $score ?>%</span>
        <div style="height:4px;background:var(--gris-200);border-radius:2px;margin-top:3px;width:60px;margin-left:auto;margin-right:auto">
          <div style="height:100%;width:<?= $score ?>%;background:<?= $mention[1] ?>;border-radius:2px"></div>
        </div>
      </td>
      <td style="text-align:center">
        <?php if ($e['streak_jours'] > 0): ?>
        <span style="color:#F97316;font-weight:700"><?= $e['streak_jours'] ?>j <i data-lucide="flame" style="width:11px;height:11px;vertical-align:-1px;color:#F97316"></i></span>
        <?php else: ?>—<?php endif; ?>
      </td>
      <td style="font-size:12px;color:var(--gris-600)"><?= $e['derniere_activite'] ? date('d/m/Y', strtotime($e['derniere_activite'])) : 'Jamais' ?></td>
      <td>
        <span style="font-size:11px;font-weight:700;color:<?= $mention[1] ?>;background:<?= $mention[1] ?>18;padding:2px 8px;border-radius:10px"><?= $mention[0] ?></span>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$eleves): ?>
    <tr><td colspan="8" style="text-align:center;padding:20px;color:var(--gris-400)">Aucun élève dans cette classe</td></tr>
    <?php endif; ?>
    </tbody>
  </table>

  <!-- Pied de page -->
  <div style="margin-top:32px;padding-top:16px;border-top:1px solid var(--gris-200);display:flex;justify-content:space-between;align-items:center;font-size:11px;color:var(--gris-400)">
    <span>RÉUSSITE+ · Plateforme éducative RDC · reussiteplus.cd</span>
    <span>Plan École · <?= e($user['prenom']) ?> <?= e($user['nom']) ?></span>
  </div>
</div>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
