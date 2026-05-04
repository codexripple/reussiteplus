<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Choisir un plan';
$pageActive = 'abonnement';
$user = require_login();

$planChoisi = $_GET['plan'] ?? null;

// Valider le code promo (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verifier_promo') {
    header('Content-Type: application/json');
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $plan = $_POST['plan'] ?? '';
    if (!$code) { echo json_encode(['ok' => false, 'msg' => 'Code vide.']); exit; }
    $promo = dbRow(
        "SELECT * FROM codes_promo WHERE code=? AND actif=1 AND (date_expiration IS NULL OR date_expiration > NOW()) AND (nb_max IS NULL OR nb_utilisations < nb_max)",
        [$code]
    );
    if (!$promo) { echo json_encode(['ok' => false, 'msg' => 'Code invalide ou expiré.']); exit; }
    if ($promo['plan_applicable'] !== 'TOUS' && $promo['plan_applicable'] !== $plan) {
        echo json_encode(['ok' => false, 'msg' => 'Ce code n\'est pas valable pour ce plan.']); exit;
    }
    $remise = $promo['type_remise'] === 'POURCENTAGE'
        ? $promo['valeur_remise'] . '%'
        : number_format($promo['valeur_remise'], 0, ',', ' ') . ' CDF';
    echo json_encode(['ok' => true, 'remise' => $remise, 'valeur' => $promo['valeur_remise'], 'type' => $promo['type_remise']]);
    exit;
}

include __DIR__ . '/includes/header_app.php';
?>

<div style="max-width:900px;margin:0 auto">
  <!-- Abonnement actuel -->
  <div class="card" style="margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
    <div>
      <div style="font-size:12px;color:var(--gris-500);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Mon abonnement actuel</div>
      <div style="display:flex;align-items:center;gap:12px">
        <span style="font-size:28px"><i class="<?= PLANS[$user['plan']]['icone'] ?? 'bi bi-backpack' ?>"></i></span>
        <div>
          <div style="font-family:var(--font-display);font-size:20px;font-weight:800"><?= e(PLANS[$user['plan']]['nom']) ?></div>
          <?php if ($user['plan_expire_at'] && $user['plan'] !== 'GRATUIT'): ?>
          <div style="font-size:13px;color:var(--gris-600)">Expire le <?= date('d/m/Y', strtotime($user['plan_expire_at'])) ?></div>
          <?php elseif ($user['plan'] === 'GRATUIT'): ?>
          <div style="font-size:13px;color:var(--gris-600)"><?= $user['examens_mois']??0 ?>/<?= FREE_EXAMS_PER_MONTH ?> examens utilisés ce mois</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php if ($user['plan'] !== 'GRATUIT'): ?>
    <div style="text-align:right">
      <div style="font-size:12px;color:var(--gris-500);margin-bottom:4px">Code référral</div>
      <div style="font-family:var(--font-mono);font-size:16px;font-weight:700;background:var(--gris-100);padding:6px 16px;border-radius:8px;cursor:pointer" onclick="copyRef()" title="Copier">
        <?= e($user['referral_code'] ?? 'N/A') ?>
      </div>
      <div style="font-size:11px;color:var(--gris-500);margin-top:3px">Partagez et gagnez 1 mois offert</div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Grille des plans -->
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-bottom:32px">
    <?php foreach (['BASIQUE', 'PREMIUM', 'ECOLE'] as $planKey):
      $plan = PLANS[$planKey];
      $isActive = $user['plan'] === $planKey && plan_actif($user);
    ?>
    <div style="background:white;border-radius:20px;padding:28px 24px;border:2px solid <?= $isActive ? 'var(--primary)' : (($plan['populaire']??false) ? 'var(--gold)' : 'var(--gris-200)') ?>;position:relative;transition:all .2s" onmouseover="this.style.boxShadow='var(--shadow-lg)';this.style.transform='translateY(-3px)'" onmouseout="this.style.boxShadow='';this.style.transform=''">
      <?php if ($isActive): ?>
        <div style="position:absolute;top:-12px;left:50%;transform:translateX(-50%);background:var(--primary);color:white;padding:3px 14px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap"><i class="bi bi-check-circle-fill"></i> Plan actif</div>
      <?php elseif ($plan['populaire']??false): ?>
        <div style="position:absolute;top:-12px;left:50%;transform:translateX(-50%);background:var(--gold);color:white;padding:3px 14px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap"><i class="bi bi-star-fill"></i> Recommandé</div>
      <?php endif; ?>

      <div style="font-size:32px;margin-bottom:12px"><i class="<?= e($plan['icone']) ?>"></i></div>
      <div style="font-family:var(--font-display);font-size:20px;font-weight:800;margin-bottom:12px"><?= e($plan['nom']) ?></div>
      <div style="font-family:var(--font-display);font-size:30px;font-weight:900;color:var(--gris-900)">
        <?= number_format($plan['prix'], 0, ',', ' ') ?> CDF
      </div>
      <div style="font-size:12px;color:var(--gris-500);margin-bottom:20px">par mois</div>

      <ul style="list-style:none;margin-bottom:24px">
        <li style="font-size:13px;padding:6px 0;border-bottom:1px solid var(--gris-100);display:flex;gap:8px">
          <span style="color:var(--primary)"><i class="bi bi-check-lg"></i></span>
          <?= $plan['examens_mois'] === -1 ? 'Examens illimités' : $plan['examens_mois'] . ' examens/mois' ?>
        </li>
        <li style="font-size:13px;padding:6px 0;border-bottom:1px solid var(--gris-100);display:flex;gap:8px">
          <span style="color:var(--primary)"><i class="bi bi-check-lg"></i></span> Archives officielles
        </li>
        <li style="font-size:13px;padding:6px 0;border-bottom:1px solid var(--gris-100);display:flex;gap:8px">
          <span style="color:var(--primary)"><i class="bi bi-check-lg"></i></span> Corrigés détaillés
        </li>
        <?php if ($plan['ia']): ?>
        <li style="font-size:13px;padding:6px 0;border-bottom:1px solid var(--gris-100);display:flex;gap:8px">
          <span style="color:var(--primary)"><i class="bi bi-check-lg"></i></span> Plan de révision IA
        </li>
        <?php endif; ?>
        <?php if (isset($plan['eleves_max'])): ?>
        <li style="font-size:13px;padding:6px 0;display:flex;gap:8px">
          <span style="color:var(--primary)"><i class="bi bi-check-lg"></i></span> <?= $plan['eleves_max'] ?> élèves
        </li>
        <?php endif; ?>
      </ul>

      <?php if ($isActive): ?>
        <div style="text-align:center;font-size:13px;color:var(--primary);font-weight:600"><i class="bi bi-check-circle-fill"></i> Plan actif</div>
      <?php elseif ($planKey === 'ECOLE'): ?>
        <a href="mailto:contact@reussiteplus.cd?subject=Plan École" class="btn btn-primary btn-full">Nous contacter</a>
      <?php else: ?>
        <a href="/reussiteplus/paiement.php?plan=<?= $planKey ?>" class="btn <?= ($plan['populaire']??false) ? 'btn-gold' : 'btn-primary' ?> btn-full">
          <?= ($plan['populaire']??false) ? '<i class="bi bi-star-fill"></i> ' : '' ?>Choisir ce plan
        </a>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Méthodes de paiement -->
  <div class="card" style="text-align:center;padding:28px">
    <div style="font-size:14px;font-weight:600;color:var(--gris-700);margin-bottom:16px">Paiement accepté via</div>
    <div style="display:flex;justify-content:center;gap:20px;flex-wrap:wrap">
      <?php foreach (METHODES_PAIEMENT as $m): ?>
      <div style="display:flex;align-items:center;gap:8px;background:var(--gris-50);border:1px solid var(--gris-200);padding:10px 20px;border-radius:var(--radius)">
        <span style="font-size:20px"><i class="<?= e($m['icone']) ?>"></i></span>
        <div>
          <div style="font-size:13px;font-weight:600"><?= e($m['nom']) ?></div>
          <div style="font-size:11px;color:var(--gris-500)"><?= e($m['numero']) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="font-size:12px;color:var(--gris-500);margin-top:12px">
      Paiement en CDF (Franc Congolais) • Confirmation manuelle sous 24h • Support : support@reussiteplus.cd
    </div>
  </div>
</div>

<script>
function copyRef() {
  const code = '<?= e($user['referral_code'] ?? '') ?>';
  const url = window.location.origin + '/reussiteplus/inscription.php?ref=' + code;
  navigator.clipboard?.writeText(url) || (document.execCommand ? document.execCommand('copy') : null);
  alert('Lien de référral copié !\n' + url);
}
</script>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
