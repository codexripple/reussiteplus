<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Finaliser le paiement';
$pageActive = 'abonnement';
$user = require_login();

$planKey = strtoupper($_GET['plan'] ?? 'PREMIUM');
if (!isset(PLANS[$planKey]) || $planKey === 'GRATUIT') {
    redirect('/reussiteplus/tarifs.php');
}
$plan = PLANS[$planKey];
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmer_paiement'])) {
    if (!csrf_verify()) {
        $errors[] = 'Token invalide. Rechargez.';
    } else {
        $methode   = $_POST['methode'] ?? '';
        $telephone = trim($_POST['telephone'] ?? '');
        $duree     = (int)($_POST['duree'] ?? 1);
        $codePromo = strtoupper(trim($_POST['code_promo'] ?? ''));

        if (!array_key_exists($methode, METHODES_PAIEMENT)) $errors[] = 'Méthode de paiement invalide.';
        if (empty($telephone)) $errors[] = 'Numéro de téléphone requis.';
        if ($duree < 1 || $duree > 12) $errors[] = 'Durée invalide.';

        if (!$errors) {
            $montant = $plan['prix'] * $duree;
            $remise  = 0;

            // Appliquer promo
            if ($codePromo) {
                $promo = dbRow(
                    "SELECT * FROM codes_promo WHERE code=? AND actif=1 AND (date_expiration IS NULL OR date_expiration > NOW()) AND (nb_max IS NULL OR nb_utilisations < nb_max)",
                    [$codePromo]
                );
                if ($promo && ($promo['plan_applicable'] === 'TOUS' || $promo['plan_applicable'] === $planKey)) {
                    if ($promo['type_remise'] === 'POURCENTAGE') {
                        $remise = $montant * ($promo['valeur_remise'] / 100);
                    } else {
                        $remise = min($montant, (float)$promo['valeur_remise']);
                    }
                    $montant -= $remise;
                    dbQuery("UPDATE codes_promo SET nb_utilisations = nb_utilisations + 1 WHERE id=?", [$promo['id']]);
                }
            }

            $dateDebut = date('Y-m-d');
            $dateFin   = date('Y-m-d', strtotime("+{$duree} month"));
            $ref       = 'RP-' . strtoupper(substr(md5(uniqid()), 0, 8));

            $abonId = dbInsert('abonnements', [
                'user_id'          => $user['id'],
                'plan'             => $planKey,
                'montant'          => $montant,
                'devise'           => 'CDF',
                'methode_paiement' => $methode,
                'reference_paiement' => $ref,
                'telephone'        => $telephone,
                'statut'           => 'EN_ATTENTE',
                'date_debut'       => $dateDebut,
                'date_fin'         => $dateFin,
                'duree_mois'       => $duree,
                'code_promo'       => $codePromo ?: null,
                'remise'           => $remise,
            ]);

            // Notifier l'utilisateur
            dbInsert('notifications', [
                'user_id' => $user['id'],
                'type'    => 'PAIEMENT',
                'titre'   => 'Paiement en attente de confirmation',
                'message' => "Votre demande d'abonnement {$plan['nom']} (Réf: {$ref}) a bien été reçue. Elle sera confirmée sous 24h après vérification du paiement.",
                'lien'    => '/reussiteplus/abonnement.php',
            ]);

            $success = true;
            $successRef = $ref;
            $successMontant = $montant;
        }
    }
}

include __DIR__ . '/includes/header_app.php';
?>

<div style="max-width:620px;margin:0 auto">
  <?php if ($success): ?>
  <!-- Confirmation -->
  <div class="card" style="text-align:center;padding:40px">
    <div style="font-size:56px;margin-bottom:16px">🎉</div>
    <div style="font-family:var(--font-display);font-size:24px;font-weight:800;margin-bottom:8px">Demande envoyée !</div>
    <p style="font-size:14px;color:var(--gris-600);line-height:1.7;max-width:440px;margin:0 auto 24px">
      Votre demande d'abonnement <strong><?= e($plan['nom']) ?></strong> a bien été reçue.<br>
      Référence : <strong style="font-family:var(--font-mono)"><?= e($successRef) ?></strong><br>
      Montant : <strong><?= format_prix((int)$successMontant) ?></strong>
    </p>
    <div class="alert alert-info" style="text-align:left;margin-bottom:24px">
      📱 <strong>Étape suivante :</strong> Effectuez le virement via la méthode choisie et envoyez une capture d'écran à
      <a href="mailto:paiement@reussiteplus.cd" style="color:var(--bleu);font-weight:600">paiement@reussiteplus.cd</a>
      en mentionnant votre référence <strong><?= e($successRef) ?></strong>. Confirmation sous 24h.
    </div>
    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
      <a href="/reussiteplus/dashboard.php" class="btn btn-primary">Retour au dashboard</a>
      <a href="/reussiteplus/notifications.php" class="btn btn-ghost">Voir mes notifications</a>
    </div>
  </div>

  <?php else: ?>
  <!-- Formulaire paiement -->
  <div style="margin-bottom:16px">
    <a href="/reussiteplus/tarifs.php" style="color:var(--primary);font-size:13px;font-weight:500">← Choisir un autre plan</a>
  </div>

  <!-- Récap plan -->
  <div class="card" style="background:linear-gradient(135deg,<?= $planKey === 'PREMIUM' ? '#F5E6C0,#FFF7E6' : 'var(--primary-subtle),var(--blanc)' ?>);border:2px solid <?= $planKey === 'PREMIUM' ? 'var(--gold)' : 'var(--primary)' ?>;margin-bottom:20px">
    <div style="display:flex;align-items:center;gap:16px">
      <span style="font-size:40px"><?= $plan['icone'] ?></span>
      <div>
        <div style="font-family:var(--font-display);font-size:20px;font-weight:800">Plan <?= e($plan['nom']) ?></div>
        <div style="font-size:14px;color:var(--gris-600)"><?= e($plan['prix_affiche']) ?></div>
      </div>
      <div style="margin-left:auto;text-align:right">
        <div style="font-family:var(--font-display);font-size:26px;font-weight:800"><?= number_format($plan['prix'], 0, ',', ' ') ?> CDF</div>
        <div style="font-size:12px;color:var(--gris-500)">par mois</div>
      </div>
    </div>
  </div>

  <?php if ($errors): ?>
  <div class="alert alert-error">⚠️ <?= e($errors[0]) ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="card-title" style="margin-bottom:20px">💳 Informations de paiement</div>

    <form method="POST" id="paymentForm">
      <?= csrf_field() ?>
      <input type="hidden" name="confirmer_paiement" value="1">

      <div class="form-group">
        <label class="form-label">📱 Méthode de paiement *</label>
        <div style="display:flex;flex-direction:column;gap:8px">
          <?php foreach (METHODES_PAIEMENT as $key => $m): ?>
          <label style="cursor:pointer;display:flex;align-items:center;gap:12px;padding:12px 16px;border:2px solid var(--gris-200);border-radius:var(--radius);transition:all .15s">
            <input type="radio" name="methode" value="<?= e($key) ?>" required
                   onchange="document.querySelectorAll('.payment-opt').forEach(el=>el.style.borderColor='var(--gris-200)');this.parentElement.style.borderColor='var(--primary)';updateTotal()">
            <span style="font-size:22px"><?= $m['icone'] ?></span>
            <div>
              <div style="font-weight:600;font-size:14px"><?= e($m['nom']) ?></div>
              <div style="font-size:12px;color:var(--gris-500)">Numéro RÉUSSITE+ : <?= e($m['numero']) ?></div>
            </div>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">📞 Votre numéro de téléphone *</label>
        <input class="form-control" type="tel" name="telephone"
               placeholder="+243 8X XXX XXXX" value="<?= e($_POST['telephone'] ?? '') ?>"
               pattern="[+0-9\s]{10,15}" required>
        <div style="font-size:11px;color:var(--gris-500);margin-top:4px">Numéro utilisé pour effectuer le virement</div>
      </div>

      <div class="form-group">
        <label class="form-label">⏱ Durée d'abonnement</label>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px">
          <?php
          $durations = [1 => '1 mois', 3 => '3 mois (-5%)', 6 => '6 mois (-10%)', 12 => '12 mois (-15%)'];
          foreach ($durations as $d => $label): ?>
          <label style="cursor:pointer;text-align:center">
            <input type="radio" name="duree" value="<?= $d ?>" <?= $d === 1 ? 'checked' : '' ?> style="display:none"
                   onchange="document.querySelectorAll('.dur-card').forEach(el=>el.style.borderColor='var(--gris-200)');this.nextElementSibling.style.borderColor='var(--primary)';updateTotal()">
            <div class="dur-card" style="border:2px solid <?= $d === 1 ? 'var(--primary)' : 'var(--gris-200)' ?>;border-radius:var(--radius);padding:8px;font-size:12px;font-weight:600;transition:all .15s"><?= $label ?></div>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">🎟 Code promo (optionnel)</label>
        <div style="display:flex;gap:8px">
          <input class="form-control" type="text" id="code_promo" name="code_promo"
                 placeholder="Ex: RENTRÉE2025" value="<?= e($_POST['code_promo'] ?? '') ?>"
                 style="text-transform:uppercase">
          <button type="button" class="btn btn-ghost" onclick="verifyPromo()">Vérifier</button>
        </div>
        <div id="promo-result" style="font-size:12px;margin-top:4px"></div>
      </div>

      <!-- Récapitulatif -->
      <div style="background:var(--gris-50);border-radius:var(--radius);padding:16px;margin:16px 0">
        <div style="font-weight:600;margin-bottom:10px;font-size:14px">Récapitulatif</div>
        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px">
          <span>Plan <?= e($plan['nom']) ?> × <span id="recap-duree">1</span> mois</span>
          <span id="recap-sous-total"><?= number_format($plan['prix']) ?> CDF</span>
        </div>
        <div id="recap-remise-line" style="display:none;justify-content:space-between;font-size:13px;color:var(--primary);margin-bottom:6px">
          <span>Code promo</span>
          <span id="recap-remise">-0 CDF</span>
        </div>
        <div style="border-top:1px solid var(--gris-200);padding-top:8px;margin-top:4px;display:flex;justify-content:space-between;font-weight:700;font-size:16px">
          <span>Total à payer</span>
          <span id="recap-total"><?= number_format($plan['prix']) ?> CDF</span>
        </div>
      </div>

      <div class="alert alert-info" style="font-size:13px">
        ℹ️ Après soumission, envoyez une capture d'écran de votre paiement à
        <strong>paiement@reussiteplus.cd</strong> avec votre référence. Activation sous 24h.
      </div>

      <button type="submit" class="btn btn-gold btn-full btn-lg">
        💰 Soumettre la demande de paiement
      </button>
    </form>
  </div>
  <?php endif; ?>
</div>

<script>
const PLAN_PRIX = <?= $plan['prix'] ?>;
let promoValeur = 0;
let promoType = '';

function updateTotal() {
  const duree = parseInt(document.querySelector('input[name="duree"]:checked')?.value || 1);
  let discounts = {1:0, 3:5, 6:10, 12:15};
  let subtotal = PLAN_PRIX * duree;
  let dureeDiscount = subtotal * (discounts[duree] || 0) / 100;
  let promoDiscount = promoType === 'POURCENTAGE'
    ? subtotal * promoValeur / 100
    : Math.min(subtotal, promoValeur);

  let total = subtotal - dureeDiscount - promoDiscount;
  document.getElementById('recap-duree').textContent = duree;
  document.getElementById('recap-sous-total').textContent = subtotal.toLocaleString('fr-FR') + ' CDF';
  if (dureeDiscount > 0 || promoDiscount > 0) {
    document.getElementById('recap-remise-line').style.display = 'flex';
    document.getElementById('recap-remise').textContent = '-' + (dureeDiscount + promoDiscount).toLocaleString('fr-FR') + ' CDF';
  }
  document.getElementById('recap-total').textContent = Math.max(0, total).toLocaleString('fr-FR') + ' CDF';
}

async function verifyPromo() {
  const code = document.getElementById('code_promo').value.trim().toUpperCase();
  const plan = '<?= $planKey ?>';
  const res  = document.getElementById('promo-result');
  if (!code) { res.textContent = '⚠️ Entrez un code.'; res.style.color='var(--rouge)'; return; }

  const fd = new FormData();
  fd.append('action', 'verifier_promo');
  fd.append('code', code);
  fd.append('plan', plan);
  fd.append('csrf_token', '<?= e(csrf_token()) ?>');

  const r = await fetch(window.location.href, {method:'POST', body:fd});
  const data = await r.json();
  if (data.ok) {
    res.textContent = '✅ Code valide ! Réduction : ' + data.remise;
    res.style.color = 'var(--primary)';
    promoValeur = parseFloat(data.valeur);
    promoType   = data.type;
    updateTotal();
  } else {
    res.textContent = '❌ ' + data.msg;
    res.style.color = 'var(--rouge)';
    promoValeur = 0;
  }
}

document.querySelector('form').addEventListener('submit', function(e) {
  const btn = this.querySelector('button[type="submit"]');
  btn.disabled = true;
  btn.textContent = '⏳ Envoi en cours...';
});
</script>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
