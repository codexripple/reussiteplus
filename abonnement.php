<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Mon abonnement';
$pageActive = 'abonnement';
$user = require_login();

// Historique des abonnements
$abonnements = dbAll(
    "SELECT * FROM abonnements WHERE user_id = ? ORDER BY created_at DESC LIMIT 20",
    [$user['id']]
);

include __DIR__ . '/includes/header_app.php';
?>

<div style="max-width:780px;margin:0 auto">
  <!-- État actuel -->
  <div class="card" style="margin-bottom:24px;padding:28px">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px">
      <div style="display:flex;align-items:center;gap:16px">
        <span style="font-size:48px"><i class="<?= PLANS[$user['plan']]['icone'] ?? 'bi bi-backpack' ?>"></i></span>
        <div>
          <div style="font-size:12px;color:var(--gris-500);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Mon plan actuel</div>
          <div style="font-family:var(--font-display);font-size:26px;font-weight:800"><?= e(PLANS[$user['plan']]['nom']) ?></div>
          <?php if ($user['plan'] !== 'GRATUIT'): ?>
            <?php if ($user['plan_expire_at']): ?>
              <?php $joursRestants = (int)floor((strtotime($user['plan_expire_at']) - time()) / 86400); ?>
              <div style="font-size:13px;color:<?= $joursRestants <= 7 ? 'var(--rouge)' : 'var(--gris-600)' ?>">
                <?= $joursRestants > 0
                  ? 'Expire dans ' . $joursRestants . ' jour' . ($joursRestants > 1 ? 's' : '') . ' (' . date('d/m/Y', strtotime($user['plan_expire_at'])) . ')'
                    : 'Plan expiré le ' . date('d/m/Y', strtotime($user['plan_expire_at'])) ?>
              </div>
            <?php endif; ?>
          <?php else: ?>
            <div style="font-size:13px;color:var(--gris-600)">
              <?= ($user['examens_mois']??0) ?>/<?= FREE_EXAMS_PER_MONTH ?> examens utilisés ce mois
            </div>
          <?php endif; ?>
        </div>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <?php if ($user['plan'] === 'GRATUIT'): ?>
          <a href="/reussiteplus/tarifs.php" class="btn btn-gold"><i class="bi bi-star-fill"></i> Passer à Premium</a>
        <?php else: ?>
          <a href="/reussiteplus/paiement.php?plan=<?= e($user['plan']) ?>" class="btn btn-primary"><i class="bi bi-arrow-repeat"></i> Renouveler</a>
          <a href="/reussiteplus/tarifs.php" class="btn btn-ghost">Changer de plan</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Avantages du plan actif -->
  <div class="card" style="margin-bottom:24px">
    <div class="card-title" style="margin-bottom:16px"><i class="bi bi-gift"></i> Inclus dans votre plan</div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px">
      <?php $p = PLANS[$user['plan']]; ?>
      <div style="text-align:center;padding:14px;background:var(--gris-50);border-radius:var(--radius)">
        <div style="font-size:22px;margin-bottom:4px;color:var(--primary)"><i class="bi bi-pencil-square"></i></div>
        <div style="font-size:20px;font-weight:800"><?= $p['examens_mois'] === -1 ? '∞' : $p['examens_mois'] ?></div>
        <div style="font-size:11px;color:var(--gris-500)">examens/mois</div>
      </div>
      <div style="text-align:center;padding:14px;background:var(--gris-50);border-radius:var(--radius)">
        <div style="font-size:22px;margin-bottom:4px;color:var(--primary)"><?= ($p['ia'] ?? false) ? '<i class="bi bi-cpu"></i>' : '<i class="bi bi-book"></i>' ?></div>
        <div style="font-size:14px;font-weight:700"><?= ($p['ia'] ?? false) ? 'IA active' : 'Standard' ?></div>
        <div style="font-size:11px;color:var(--gris-500)"><?= ($p['ia'] ?? false) ? 'Plan personnalisé' : 'Révision guidée' ?></div>
      </div>
      <div style="text-align:center;padding:14px;background:var(--gris-50);border-radius:var(--radius)">
        <div style="font-size:22px;margin-bottom:4px;color:var(--primary)"><i class="bi bi-file-earmark-text"></i></div>
        <div style="font-size:14px;font-weight:700"><?= $user['plan'] !== 'GRATUIT' ? 'Corrigés inclus' : 'Corrigés verrouillés' ?></div>
        <div style="font-size:11px;color:var(--gris-500)"><?= $user['plan'] !== 'GRATUIT' ? 'Accès complet' : 'Passez à Premium' ?></div>
      </div>
    </div>
  </div>

  <!-- Historique des paiements -->
  <div class="card">
    <div class="card-header">
    <div class="card-title"><i class="bi bi-receipt"></i> Historique des paiements</div>
      <a href="/reussiteplus/tarifs.php" class="btn btn-primary btn-sm">+ Nouveau paiement</a>
    </div>

    <?php if ($abonnements): ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr><th>Référence</th><th>Plan</th><th>Montant</th><th>Méthode</th><th>Période</th><th>Statut</th></tr>
        </thead>
        <tbody>
        <?php foreach ($abonnements as $ab):
          $statusColors = [
            'EN_ATTENTE' => ['bg'=>'#FEF3C7','c'=>'#92400E'],
            'CONFIRME'   => ['bg'=>'#D1FAE5','c'=>'#064E3B'],
            'REFUSE'     => ['bg'=>'#FEE2E2','c'=>'#7F1D1D'],
            'EXPIRE'     => ['bg'=>'#F3F4F6','c'=>'#6B7280'],
          ];
          $sc = $statusColors[$ab['statut']] ?? $statusColors['EN_ATTENTE'];
          $methodNom = METHODES_PAIEMENT[$ab['methode_paiement']]['nom'] ?? $ab['methode_paiement'];
        ?>
        <tr>
          <td style="font-family:var(--font-mono);font-size:12px;color:var(--gris-700)"><?= e($ab['reference_paiement']) ?></td>
          <td><?= e(PLANS[$ab['plan']]['nom'] ?? $ab['plan']) ?></td>
          <td style="font-weight:700"><?= number_format((float)$ab['montant'], 0, ',', ' ') ?> <?= e($ab['devise']) ?></td>
          <td style="font-size:12px;color:var(--gris-600)"><?= e($methodNom) ?></td>
          <td style="font-size:12px;color:var(--gris-500)">
            <?= date('d/m/Y', strtotime($ab['date_debut'])) ?> → <?= date('d/m/Y', strtotime($ab['date_fin'])) ?>
          </td>
          <td>
            <span style="background:<?= $sc['bg'] ?>;color:<?= $sc['c'] ?>;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:600">
              <?= e($ab['statut']) ?>
            </span>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:32px;color:var(--gris-500)">
      <div style="font-size:36px;margin-bottom:8px;color:var(--gris-300)"><i class="bi bi-receipt"></i></div>
      <div style="font-size:14px">Aucun historique de paiement</div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Support -->
  <div style="text-align:center;margin-top:24px;font-size:13px;color:var(--gris-500)">
    Un problème avec votre paiement ?
    <a href="mailto:paiement@reussiteplus.cd" style="color:var(--primary);font-weight:600">Contactez notre support</a>
    ou écrivez-nous sur WhatsApp au +243 8XX XXX XXX
  </div>
</div>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
