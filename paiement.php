<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$user = require_login();

$planKey = strtoupper(trim($_GET['plan'] ?? 'PREMIUM'));
if (!array_key_exists($planKey, PLANS) || $planKey === 'GRATUIT') {
    redirect('/reussiteplus/tarifs.php');
}

/** @var array $planData */
$planData = PLANS[$planKey];
$hasIA    = (bool)($planData['ia'] ?? false);
$errors   = [];
$success  = false;
$successRef     = '';
$successMontant = 0;
$successMethode = '';

/* ── Vérif code promo (AJAX) ─────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verifier_promo') {
    header('Content-Type: application/json');
    $code = strtoupper(trim($_POST['code'] ?? ''));
    if (!$code) { echo json_encode(['ok' => false, 'msg' => 'Code vide.']); exit; }
    $promo = dbRow(
        "SELECT * FROM codes_promo
         WHERE code=? AND actif=1
           AND (date_expiration IS NULL OR date_expiration > NOW())
           AND (nb_max IS NULL OR nb_utilisations < nb_max)",
        [$code]
    );
    if (!$promo) { echo json_encode(['ok' => false, 'msg' => 'Code invalide ou expiré.']); exit; }
    if ($promo['plan_applicable'] !== 'TOUS' && $promo['plan_applicable'] !== $planKey) {
        echo json_encode(['ok' => false, 'msg' => "Ce code n'est pas valable pour ce plan."]); exit;
    }
    $remiseLabel = $promo['type_remise'] === 'POURCENTAGE'
        ? $promo['valeur_remise'] . '%'
        : number_format((float)$promo['valeur_remise'], 0, ',', ' ') . ' CDF';
    echo json_encode(['ok' => true, 'remise' => $remiseLabel, 'valeur' => $promo['valeur_remise'], 'type' => $promo['type_remise']]);
    exit;
}

/* ── Soumission paiement ─────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['confirmer_paiement'])) {
    if (!csrf_verify()) {
        $errors[] = 'Token de sécurité invalide. Rechargez la page.';
    } else {
        $methode   = trim($_POST['methode'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $duree     = max(1, min(12, (int)($_POST['duree'] ?? 1)));
        $codePromo = strtoupper(trim($_POST['code_promo'] ?? ''));

        if (!array_key_exists($methode, METHODES_PAIEMENT)) $errors[] = 'Sélectionnez une méthode de paiement.';
        if ($telephone === '')                              $errors[] = 'Le numéro de téléphone est requis.';
        elseif (!preg_match('/^[+0-9\s\-]{9,16}$/', $telephone)) $errors[] = 'Numéro de téléphone invalide.';

        if (!$errors) {
            $prixUnitaire  = (int)$planData['prix'];
            $dureeDiscPct  = [1 => 0, 3 => 5, 6 => 10, 12 => 15][$duree] ?? 0;
            $sousTotal     = $prixUnitaire * $duree;
            $remiseDuree   = (int)round($sousTotal * $dureeDiscPct / 100);
            $remisePromo   = 0;

            if ($codePromo !== '') {
                $promo = dbRow(
                    "SELECT * FROM codes_promo
                     WHERE code=? AND actif=1
                       AND (date_expiration IS NULL OR date_expiration > NOW())
                       AND (nb_max IS NULL OR nb_utilisations < nb_max)",
                    [$codePromo]
                );
                if ($promo && ($promo['plan_applicable'] === 'TOUS' || $promo['plan_applicable'] === $planKey)) {
                    $basePromo   = $sousTotal - $remiseDuree;
                    if ($promo['type_remise'] === 'POURCENTAGE') {
                        $remisePromo = (int)round($basePromo * (float)$promo['valeur_remise'] / 100);
                    } else {
                        $remisePromo = min($basePromo, (int)$promo['valeur_remise']);
                    }
                    dbQuery("UPDATE codes_promo SET nb_utilisations = nb_utilisations + 1 WHERE id=?", [$promo['id']]);
                }
            }

            $montantFinal = max(0, $sousTotal - $remiseDuree - $remisePromo);
            $ref          = 'RP-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 8));

            dbInsert('abonnements', [
                'user_id'            => $user['id'],
                'plan'               => $planKey,
                'montant'            => $montantFinal,
                'devise'             => 'CDF',
                'methode_paiement'   => $methode,
                'reference_paiement' => $ref,
                'telephone'          => $telephone,
                'statut'             => 'EN_ATTENTE',
                'date_debut'         => date('Y-m-d'),
                'date_fin'           => date('Y-m-d', strtotime("+{$duree} month")),
                'duree_mois'         => $duree,
                'code_promo'         => $codePromo ?: null,
                'remise'             => $remiseDuree + $remisePromo,
            ]);

            dbInsert('notifications', [
                'user_id' => $user['id'],
                'type'    => 'PAIEMENT',
                'titre'   => 'Demande d\'abonnement reçue',
                'message' => "Demande {$planData['nom']} (Réf: {$ref}) reçue. Activation sous 24h après vérification.",
                'lien'    => '/reussiteplus/abonnement.php',
            ]);

            $success        = true;
            $successRef     = $ref;
            $successMontant = $montantFinal;
            $successMethode = METHODES_PAIEMENT[$methode]['nom'] ?? $methode;
        }
    }
}

/* ── Données statiques ───────────────────────────────────── */
$planIcons = ['GRATUIT' => 'backpack', 'BASIQUE' => 'zap', 'PREMIUM' => 'crown', 'ECOLE' => 'school'];

$operateurs = [
    'MPESA'        => ['nom' => 'M-Pesa',       'couleur' => '#00A651', 'ussd' => 'USSD *150*00#'],
    'AIRTEL_MONEY' => ['nom' => 'Airtel Money',  'couleur' => '#E40613', 'ussd' => 'USSD *185#'],
    'ORANGE_MONEY' => ['nom' => 'Orange Money',  'couleur' => '#FF6600', 'ussd' => 'USSD *144#'],
];

/* ── Titre de page ───────────────────────────────────────── */
$pageTitle  = $success ? 'Confirmation de paiement' : "Passer au plan {$planData['nom']}";
$pageActive = 'abonnement';

include __DIR__ . '/includes/header_app.php';
?>

<style>
/* ════════════════════════════════════════════════════════════
   CHECKOUT — Style Stripe-like
════════════════════════════════════════════════════════════ */
.checkout-wrap {
  max-width: 980px; margin: 0 auto;
  display: grid; grid-template-columns: 1fr 400px; gap: 28px; align-items: start;
}
@media (max-width: 840px) { .checkout-wrap { grid-template-columns: 1fr; } }

/* ── Breadcrumb ──────────────────────────────────────────── */
.co-breadcrumb {
  display: flex; align-items: center; gap: 8px;
  font-size: 12px; color: var(--gris-400); margin-bottom: 24px;
  max-width: 980px; margin-left: auto; margin-right: auto;
}
.co-breadcrumb span { display: flex; align-items: center; gap: 6px; }
.co-breadcrumb span.active { color: var(--gris-800); font-weight: 600; }
.co-breadcrumb .sep { color: var(--gris-300); }

/* ── Formulaire ──────────────────────────────────────────── */
.co-form {
  background: var(--blanc); border: 1px solid var(--gris-200);
  border-radius: 20px; overflow: hidden;
}
.co-section { padding: 22px 24px; border-bottom: 1px solid var(--gris-100); }
.co-section:last-child { border-bottom: none; }
.co-section-title {
  font-size: 11px; font-weight: 700; text-transform: uppercase;
  letter-spacing: .6px; color: var(--gris-400); margin-bottom: 14px;
  display: flex; align-items: center; gap: 6px;
}
.co-section-title i { flex-shrink: 0; }

/* ── Options de paiement ─────────────────────────────────── */
.pay-opts { display: flex; flex-direction: column; gap: 8px; }
.pay-opt {
  display: flex; align-items: center; gap: 14px;
  border: 2px solid var(--gris-200); border-radius: 12px;
  padding: 14px 16px; cursor: pointer; transition: all .18s; position: relative;
}
.pay-opt:hover { border-color: #B0C4B1; background: var(--gris-50); }
.pay-opt.active { border-color: var(--primary); background: var(--primary-subtle); }
.pay-opt-logo {
  width: 42px; height: 42px; border-radius: 10px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
}
.pay-opt-info { flex: 1; }
.pay-opt-name { font-size: 14px; font-weight: 700; color: var(--gris-900); line-height: 1.2; }
.pay-opt-detail { font-size: 11px; color: var(--gris-500); margin-top: 2px; }
.pay-opt-number { font-family: var(--font-mono); font-size: 12px; font-weight: 700; color: var(--gris-700); margin-top: 3px; }
.pay-opt input[type=radio] { width: 18px; height: 18px; accent-color: var(--primary); flex-shrink: 0; cursor: pointer; }

/* ── Durées ──────────────────────────────────────────────── */
.dur-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
@media (max-width: 480px) { .dur-grid { grid-template-columns: repeat(2, 1fr); } }
.dur-item { position: relative; cursor: pointer; }
.dur-item input[type=radio] { position: absolute; opacity: 0; width: 0; height: 0; }
.dur-card {
  border: 2px solid var(--gris-200); border-radius: 10px; padding: 12px 8px;
  text-align: center; transition: all .15s; user-select: none;
}
.dur-item input:checked ~ .dur-card { border-color: var(--primary); background: var(--primary-subtle); }
.dur-name { font-size: 13px; font-weight: 700; color: var(--gris-800); }
.dur-item input:checked ~ .dur-card .dur-name { color: var(--primary); }
.dur-save { font-size: 10px; font-weight: 700; color: #059669; margin-top: 3px; }

/* ── Total bar ───────────────────────────────────────────── */
.total-bar {
  background: var(--gris-50); border-top: 1px solid var(--gris-200);
  padding: 20px 24px;
}
.total-row { display: flex; justify-content: space-between; font-size: 13px; color: var(--gris-600); margin-bottom: 6px; }
.total-row.discount { color: #059669; font-weight: 600; }
.total-final {
  display: flex; justify-content: space-between; align-items: baseline;
  border-top: 1px solid var(--gris-200); padding-top: 12px; margin-top: 6px;
  font-family: var(--font-display); font-weight: 900; font-size: 22px; color: var(--gris-900);
}
.total-final span:last-child { font-size: 14px; color: var(--gris-400); font-weight: 400; }

/* ── CTA ─────────────────────────────────────────────────── */
.co-cta {
  width: 100%; padding: 16px; border: none; border-radius: 12px;
  font-family: var(--font-display); font-size: 16px; font-weight: 800;
  cursor: pointer; transition: all .18s; display: flex; align-items: center; justify-content: center; gap: 10px;
  text-decoration: none;
}
.co-cta:hover { opacity: .9; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(0,0,0,.2); }
.co-cta:active { transform: translateY(0); }
.co-cta:disabled { opacity: .6; cursor: not-allowed; transform: none; }
@keyframes spin { to { transform: rotate(360deg); } }
.spin { animation: spin .8s linear infinite; display: inline-block; }

/* ── Trust badges ────────────────────────────────────────── */
.trust-row { display: flex; justify-content: center; gap: 20px; flex-wrap: wrap; margin-top: 16px; }
.trust-item { display: flex; align-items: center; gap: 5px; font-size: 11px; color: var(--gris-400); }

/* ══════════════════════════════════════════════════════════
   SIDEBAR — Récap commande
══════════════════════════════════════════════════════════ */
.co-sidebar { position: sticky; top: 20px; }
.co-order-card {
  background: #0D1117; border-radius: 20px;
  overflow: hidden; color: #fff;
}
.co-order-header { padding: 24px 24px 20px; border-bottom: 1px solid rgba(255,255,255,.08); }
.co-order-plan-icon {
  width: 52px; height: 52px; border-radius: 14px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center; margin-bottom: 14px;
}
.co-order-plan-name { font-family: var(--font-display); font-size: 20px; font-weight: 900; color: #fff; margin-bottom: 2px; }
.co-order-plan-sub { font-size: 12px; color: rgba(255,255,255,.45); }

/* IA pill */
.co-ia-pill {
  margin: 16px 24px 0; padding: 12px 16px;
  background: rgba(124,58,237,.2); border: 1px solid rgba(124,58,237,.4);
  border-radius: 12px;
}
.co-ia-pill-head { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 700; color: #C4B5FD; margin-bottom: 6px; }
.co-ia-pill-desc { font-size: 11px; color: rgba(196,181,253,.65); line-height: 1.6; }
.co-ia-features { display: flex; flex-direction: column; gap: 5px; margin-top: 8px; }
.co-ia-feat { display: flex; align-items: center; gap: 6px; font-size: 11px; color: rgba(196,181,253,.75); }

/* Features list */
.co-feats { padding: 20px 24px; display: flex; flex-direction: column; gap: 10px; border-bottom: 1px solid rgba(255,255,255,.08); }
.co-feat { display: flex; align-items: flex-start; gap: 10px; }
.co-feat-icon { width: 28px; height: 28px; border-radius: 7px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,.07); }
.co-feat-title { font-size: 13px; font-weight: 600; color: rgba(255,255,255,.85); }
.co-feat-sub { font-size: 11px; color: rgba(255,255,255,.4); margin-top: 1px; }

/* Prix sidebar */
.co-price-block { padding: 20px 24px; }
.co-price-row { display: flex; justify-content: space-between; font-size: 13px; color: rgba(255,255,255,.5); padding: 4px 0; }
.co-price-row.saving { color: #6EE7B7; font-weight: 600; }
.co-price-total {
  display: flex; justify-content: space-between; align-items: baseline;
  border-top: 1px solid rgba(255,255,255,.1); margin-top: 10px; padding-top: 12px;
  font-family: var(--font-display); font-weight: 900; font-size: 24px; color: #fff;
}

/* ══════════════════════════════════════════════════════════
   CONFIRMATION PAGE
══════════════════════════════════════════════════════════ */
.confirm-wrap { max-width: 620px; margin: 0 auto; }
.confirm-card { background: var(--blanc); border: 1px solid var(--gris-200); border-radius: 20px; overflow: hidden; }
.confirm-header { padding: 40px 32px 32px; text-align: center; background: linear-gradient(160deg, #0D1117, #003D2E); }
.confirm-icon { width: 80px; height: 80px; border-radius: 24px; background: #D1FAE5; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
.confirm-title { font-family: var(--font-display); font-size: 28px; font-weight: 900; color: #fff; margin-bottom: 8px; }
.confirm-sub { font-size: 14px; color: rgba(255,255,255,.55); }
.confirm-ref { display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.2); border-radius: var(--radius); padding: 8px 18px; font-family: var(--font-mono); font-size: 16px; font-weight: 700; color: #fff; margin-top: 16px; cursor: pointer; transition: background .15s; }
.confirm-ref:hover { background: rgba(255,255,255,.18); }
.confirm-body { padding: 28px 32px; }
.confirm-steps { display: flex; flex-direction: column; gap: 0; }
.confirm-step { display: flex; gap: 16px; padding: 16px 0; border-bottom: 1px solid var(--gris-100); position: relative; }
.confirm-step:last-child { border-bottom: none; padding-bottom: 0; }
.confirm-step-num { width: 32px; height: 32px; border-radius: 50%; background: var(--primary); color: #fff; font-weight: 800; font-size: 13px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; z-index: 1; }
.confirm-step-content { padding-top: 4px; flex: 1; }
.confirm-step-title { font-size: 14px; font-weight: 700; color: var(--gris-900); margin-bottom: 4px; }
.confirm-step-desc { font-size: 13px; color: var(--gris-600); line-height: 1.6; }
</style>

<?php if ($success): ?>
<!-- ══════════════════════════════════════════════════════════
     PAGE CONFIRMATION
══════════════════════════════════════════════════════════ -->
<div class="confirm-wrap">
  <div class="confirm-card">
    <!-- Header vert sombre -->
    <div class="confirm-header">
      <div class="confirm-icon">
        <i data-lucide="check" style="width:40px;height:40px;stroke:#065F46;stroke-width:3"></i>
      </div>
      <div class="confirm-title">Demande envoyée !</div>
      <div class="confirm-sub">Plan <?= e($planData['nom']) ?> · <?= number_format($successMontant, 0, ',', ' ') ?> CDF · <?= e($successMethode) ?></div>
      <div class="confirm-ref" onclick="copyRef(this)" title="Cliquez pour copier">
        <i data-lucide="copy" style="width:14px;height:14px;stroke:rgba(255,255,255,.6)"></i>
        <?= e($successRef) ?>
      </div>
    </div>

    <!-- Corps : étapes -->
    <div class="confirm-body">
      <?php if ($hasIA): ?>
      <div style="background:rgba(124,58,237,.07);border:1px solid rgba(124,58,237,.2);border-radius:12px;padding:14px 18px;margin-bottom:24px;display:flex;align-items:flex-start;gap:12px">
        <i data-lucide="sparkles" style="width:18px;height:18px;stroke:#7C3AED;flex-shrink:0;margin-top:2px"></i>
        <div>
          <div style="font-size:13px;font-weight:700;color:#5B21B6;margin-bottom:3px">L'Assistant IA sera activé automatiquement</div>
          <div style="font-size:12px;color:#7C3AED;line-height:1.6">Dès la confirmation de votre paiement, votre tuteur IA personnalisé sera disponible dans votre tableau de bord.</div>
        </div>
      </div>
      <?php endif; ?>

      <div style="font-size:13px;font-weight:700;color:var(--gris-700);margin-bottom:16px;text-transform:uppercase;letter-spacing:.5px">Que faire maintenant ?</div>

      <div class="confirm-steps">
        <?php
        $methodeChoisie = METHODES_PAIEMENT[array_search($successMethode, array_column(METHODES_PAIEMENT, 'nom', null)) ?: 'MPESA'] ?? null;
        $steps = [
          [
            'title' => 'Effectuez le virement Mobile Money',
            'desc'  => "Envoyez <strong>" . number_format($successMontant, 0, ',', ' ') . " CDF</strong> via <strong>{$successMethode}</strong> au numéro indiqué lors de votre sélection.",
          ],
          [
            'title' => 'Prenez une capture d\'écran',
            'desc'  => 'Faites une capture d\'écran de la confirmation de votre virement (numéro de transaction, montant, date).',
          ],
          [
            'title' => 'Envoyez la preuve par email',
            'desc'  => 'Envoyez la capture à <a href="mailto:paiement@reussiteplus.cd" style="color:var(--primary);font-weight:600">paiement@reussiteplus.cd</a> en mentionnant votre référence <strong>' . e($successRef) . '</strong>.',
          ],
          [
            'title' => 'Activation sous 24h',
            'desc'  => 'Votre compte sera mis à jour dès vérification. Vous recevrez une notification de confirmation.',
          ],
        ];
        foreach ($steps as $i => $step): ?>
        <div class="confirm-step">
          <div class="confirm-step-num"><?= $i + 1 ?></div>
          <div class="confirm-step-content">
            <div class="confirm-step-title"><?= $step['title'] ?></div>
            <div class="confirm-step-desc"><?= $step['desc'] ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Contact rapide -->
      <div style="margin-top:24px;background:var(--gris-50);border:1px solid var(--gris-200);border-radius:12px;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
        <div>
          <div style="font-size:13px;font-weight:700;color:var(--gris-800);margin-bottom:3px">
            <i data-lucide="headphones" style="width:13px;height:13px;vertical-align:-2px;stroke:var(--primary)"></i>
            Besoin d'aide ?
          </div>
          <div style="font-size:12px;color:var(--gris-500)">Notre équipe répond en moins d'une heure</div>
        </div>
        <div style="display:flex;gap:8px">
          <a href="mailto:paiement@reussiteplus.cd" class="btn btn-ghost btn-sm">
            <i data-lucide="mail" style="width:12px;height:12px;vertical-align:-2px"></i> Email
          </a>
          <a href="https://wa.me/243977329184?text=Ref+<?= urlencode($successRef) ?>" target="_blank" rel="noopener"
             class="btn btn-sm" style="background:#25D366;color:#fff;border:none;font-weight:700">
            <i data-lucide="message-circle" style="width:12px;height:12px;vertical-align:-2px;stroke:#fff"></i> WhatsApp
          </a>
        </div>
      </div>

      <!-- Boutons fin -->
      <div style="display:flex;gap:12px;margin-top:20px;flex-wrap:wrap">
        <a href="/reussiteplus/dashboard.php" class="btn btn-primary" style="flex:1;justify-content:center">
          <i data-lucide="layout-dashboard" style="width:14px;height:14px;vertical-align:-2px"></i> Mon tableau de bord
        </a>
        <a href="/reussiteplus/abonnement.php" class="btn btn-ghost" style="flex:1;justify-content:center">
          <i data-lucide="receipt" style="width:14px;height:14px;vertical-align:-2px"></i> Voir mon abonnement
        </a>
      </div>
    </div>
  </div>
</div>

<script>
function copyRef(el) {
  navigator.clipboard?.writeText('<?= e($successRef) ?>').then(() => {
    const orig = el.innerHTML;
    el.innerHTML = '<i data-lucide="check" style="width:14px;height:14px;stroke:#6EE7B7"></i> Copié !';
    el.style.background = 'rgba(110,231,183,.15)';
    if (typeof lucide !== 'undefined') lucide.createIcons({nodes:[el]});
    setTimeout(() => { el.innerHTML = orig; el.style.background = ''; if (typeof lucide !== 'undefined') lucide.createIcons({nodes:[el]}); }, 2000);
  });
}
</script>

<?php else: ?>
<!-- ══════════════════════════════════════════════════════════
     PAGE CHECKOUT
══════════════════════════════════════════════════════════ -->

<!-- Breadcrumb -->
<div class="co-breadcrumb">
  <span><a href="/reussiteplus/tarifs.php" style="color:var(--gris-400);text-decoration:none;display:flex;align-items:center;gap:4px"><i data-lucide="arrow-left" style="width:12px;height:12px"></i> Plans</a></span>
  <span class="sep">›</span>
  <span class="active"><i data-lucide="credit-card" style="width:12px;height:12px"></i> Paiement</span>
  <span class="sep">›</span>
  <span>Confirmation</span>
</div>

<?php if ($errors): ?>
<div style="max-width:980px;margin:0 auto 16px;background:#FEF2F2;border:1px solid #FCA5A5;border-radius:10px;padding:12px 16px;font-size:13px;color:#991B1B;display:flex;align-items:center;gap:8px">
  <i data-lucide="alert-circle" style="width:16px;height:16px;stroke:#DC2626;flex-shrink:0"></i>
  <span><?= e($errors[0]) ?></span>
</div>
<?php endif; ?>

<div class="checkout-wrap">

  <!-- ── GAUCHE: Formulaire ─────────────────────────────────── -->
  <form method="POST" id="payForm">
    <?= csrf_field() ?>
    <input type="hidden" name="confirmer_paiement" value="1">

    <!-- Section 1: Méthode de paiement -->
    <div class="co-form" style="margin-bottom:16px">
      <div class="co-section">
        <div class="co-section-title">
          <i data-lucide="smartphone" style="width:13px;height:13px;stroke:var(--gris-400)"></i>
          Choisissez votre opérateur Mobile Money
        </div>
        <div class="pay-opts">
          <?php foreach (METHODES_PAIEMENT as $key => $m):
            $op      = $operateurs[$key] ?? ['nom' => $m['nom'], 'couleur' => '#6B7280', 'ussd' => ''];
            $checked = ($_POST['methode'] ?? '') === $key;
          ?>
          <label class="pay-opt <?= $checked ? 'active' : '' ?>" data-key="<?= e($key) ?>">
            <div class="pay-opt-logo" style="background:<?= $op['couleur'] ?>18">
              <i data-lucide="smartphone" style="width:20px;height:20px;stroke:<?= $op['couleur'] ?>"></i>
            </div>
            <div class="pay-opt-info">
              <div class="pay-opt-name"><?= e($op['nom']) ?></div>
              <div class="pay-opt-detail"><?= e($op['ussd']) ?> · Paiement instantané</div>
              <div class="pay-opt-number"><?= e($m['numero']) ?></div>
            </div>
            <input type="radio" name="methode" value="<?= e($key) ?>" <?= $checked ? 'checked' : '' ?> required
                   onchange="onMethodChange(this.closest('label'))">
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Section 2: Téléphone -->
      <div class="co-section">
        <div class="co-section-title">
          <i data-lucide="phone" style="width:13px;height:13px;stroke:var(--gris-400)"></i>
          Votre numéro de téléphone
        </div>
        <input class="form-control" type="tel" name="telephone" id="tel-input"
               placeholder="+243 8X XXX XXXX"
               value="<?= e($_POST['telephone'] ?? '') ?>"
               style="font-size:15px;padding:12px 16px"
               required>
        <div style="margin-top:8px;font-size:11px;color:var(--gris-400);display:flex;align-items:center;gap:5px">
          <i data-lucide="info" style="width:11px;height:11px;stroke:var(--gris-400)"></i>
          Numéro depuis lequel vous allez effectuer le virement Mobile Money
        </div>
      </div>

      <!-- Section 3: Durée -->
      <div class="co-section">
        <div class="co-section-title">
          <i data-lucide="calendar" style="width:13px;height:13px;stroke:var(--gris-400)"></i>
          Durée de l'abonnement
        </div>
        <div class="dur-grid">
          <?php
          $durOptions = [
            1  => ['label' => '1 mois',  'sub' => 'Mensuel',  'save' => ''],
            3  => ['label' => '3 mois',  'sub' => 'Trimestr.','save' => '-5%'],
            6  => ['label' => '6 mois',  'sub' => 'Semestr.', 'save' => '-10%'],
            12 => ['label' => '1 an',    'sub' => 'Annuel',   'save' => '-15%'],
          ];
          foreach ($durOptions as $d => $info):
            $isChecked = ((int)($_POST['duree'] ?? 1)) === $d;
          ?>
          <label class="dur-item">
            <input type="radio" name="duree" value="<?= $d ?>" <?= $isChecked || ($d === 1 && empty($_POST['duree'])) ? 'checked' : '' ?>
                   onchange="updateCalc()">
            <div class="dur-card">
              <div class="dur-name"><?= $info['label'] ?></div>
              <div style="font-size:10px;color:var(--gris-400);margin-top:2px"><?= $info['sub'] ?></div>
              <?php if ($info['save']): ?>
              <div class="dur-save"><?= $info['save'] ?></div>
              <?php endif; ?>
            </div>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Section 4: Code promo -->
      <div class="co-section">
        <div class="co-section-title">
          <i data-lucide="tag" style="width:13px;height:13px;stroke:var(--gris-400)"></i>
          Code promo <span style="font-weight:400;color:var(--gris-300);text-transform:none;letter-spacing:0">&nbsp;— optionnel</span>
        </div>
        <div style="display:flex;gap:8px">
          <div style="flex:1;position:relative">
            <input class="form-control" type="text" id="promo-input" name="code_promo"
                   placeholder="Ex: RENTRÉE2025"
                   value="<?= e($_POST['code_promo'] ?? '') ?>"
                   style="text-transform:uppercase;padding-right:36px">
            <span id="promo-status" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);display:none"></span>
          </div>
          <button type="button" class="btn btn-ghost" onclick="applyPromo()" id="promo-btn">Appliquer</button>
        </div>
        <div id="promo-msg" style="font-size:12px;margin-top:6px;display:none"></div>
      </div>

      <!-- Total + CTA -->
      <div class="total-bar">
        <div class="total-row">
          <span><?= e($planData['nom']) ?> × <span id="recap-dur">1</span> mois</span>
          <span id="recap-base"><?= number_format((int)$planData['prix']) ?> CDF</span>
        </div>
        <div class="total-row discount" id="dur-disc-row" style="display:none">
          <span id="dur-disc-label">Réduction durée</span>
          <span id="dur-disc-val"></span>
        </div>
        <div class="total-row discount" id="promo-disc-row" style="display:none">
          <span>Code promo</span>
          <span id="promo-disc-val"></span>
        </div>
        <div class="total-final">
          <span>Total à payer</span>
          <span style="color:var(--primary)" id="recap-total"><?= number_format((int)$planData['prix']) ?> CDF</span>
          <span>· CDF</span>
        </div>
      </div>

      <div style="padding:0 24px 24px">
        <div style="background:#EEF2FF;border:1px solid #C7D2FE;border-radius:10px;padding:12px 16px;font-size:12px;color:#4338CA;margin-bottom:16px;display:flex;gap:8px;align-items:flex-start">
          <i data-lucide="info" style="width:14px;height:14px;stroke:#4338CA;flex-shrink:0;margin-top:1px"></i>
          <span>Après validation, envoyez votre capture d'écran de paiement à <strong>paiement@reussiteplus.cd</strong> avec votre référence. Activation sous <strong>24h</strong>.</span>
        </div>
        <button type="submit" id="cta-btn" class="co-cta" style="background:<?= e($planData['couleur']) ?>;color:#fff">
          <i data-lucide="lock" style="width:18px;height:18px;stroke:#fff"></i>
          Confirmer ma souscription au plan <?= e($planData['nom']) ?>
        </button>
        <div class="trust-row">
          <div class="trust-item"><i data-lucide="shield-check" style="width:13px;height:13px;stroke:var(--primary)"></i> Paiement sécurisé</div>
          <div class="trust-item"><i data-lucide="clock" style="width:13px;height:13px;stroke:var(--gris-400)"></i> Activation sous 24h</div>
          <div class="trust-item"><i data-lucide="headphones" style="width:13px;height:13px;stroke:var(--gris-400)"></i> Support réactif</div>
        </div>
      </div>
    </div>
  </form>

  <!-- ── DROITE: Récap commande (Sidebar) ───────────────────── -->
  <div class="co-sidebar">
    <div class="co-order-card">

      <!-- Plan -->
      <div class="co-order-header">
        <div class="co-order-plan-icon" style="background:<?= e($planData['couleur']) ?>">
          <i data-lucide="<?= $planIcons[$planKey] ?? 'zap' ?>" style="width:26px;height:26px;stroke:#fff"></i>
        </div>
        <div class="co-order-plan-name">Plan <?= e($planData['nom']) ?></div>
        <div class="co-order-plan-sub"><?= number_format((int)$planData['prix'], 0, ',', ' ') ?> CDF/mois · sans engagement</div>
      </div>

      <?php if ($hasIA): ?>
      <!-- IA pill -->
      <div class="co-ia-pill">
        <div class="co-ia-pill-head">
          <i data-lucide="sparkles" style="width:16px;height:16px;stroke:#C4B5FD"></i>
          Assistant IA inclus
        </div>
        <div class="co-ia-pill-desc">Tuteur intelligent LLaMA 3 — disponible 24h/24</div>
        <div class="co-ia-features">
          <?php foreach ([
            'Explications personnalisées',
            'Analyse de vos lacunes',
            'Plan de révision adaptatif',
            'Chat en français illimité',
          ] as $f): ?>
          <div class="co-ia-feat">
            <i data-lucide="check" style="width:11px;height:11px;stroke:#C4B5FD;flex-shrink:0"></i>
            <?= $f ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Features -->
      <div class="co-feats">
        <?php
        $sidebarFeats = [
          [
            'icon'  => 'pencil-line',
            'color' => '#6EE7B7',
            'title' => $planData['examens_mois'] < 0 ? 'Examens illimités' : "{$planData['examens_mois']} examens/mois",
            'sub'   => $planData['examens_mois'] < 0 ? 'Entraînez-vous sans restriction' : 'Renouvellement mensuel automatique',
          ],
          [
            'icon'  => 'database',
            'color' => '#93C5FD',
            'title' => '+1 000 questions officielles',
            'sub'   => 'ENAFEP, TENASOSP, Examen d\'État',
          ],
          [
            'icon'  => 'file-check',
            'color' => '#FCA5A5',
            'title' => (bool)$planData['corrige'] ? 'Corrigés et explications' : 'Corrigés non inclus',
            'sub'   => (bool)$planData['corrige'] ? 'Méthode de résolution détaillée' : 'Disponible à partir de Basique',
          ],
          [
            'icon'  => 'archive',
            'color' => '#D8B4FE',
            'title' => (bool)$planData['archives'] ? 'Archives officielles' : 'Archives non incluses',
            'sub'   => (bool)$planData['archives'] ? 'Tous les sujets depuis 2015' : 'Disponible à partir de Basique',
          ],
        ];
        if (isset($planData['eleves_max'])) {
          $sidebarFeats[] = [
            'icon'  => 'users',
            'color' => '#6EE7B7',
            'title' => "Jusqu'à {$planData['eleves_max']} élèves",
            'sub'   => 'Tableau de bord enseignant inclus',
          ];
        }
        foreach ($sidebarFeats as $feat): ?>
        <div class="co-feat">
          <div class="co-feat-icon">
            <i data-lucide="<?= $feat['icon'] ?>" style="width:14px;height:14px;stroke:<?= $feat['color'] ?>"></i>
          </div>
          <div>
            <div class="co-feat-title"><?= e($feat['title']) ?></div>
            <div class="co-feat-sub"><?= e($feat['sub']) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Prix sidebar (dynamique) -->
      <div class="co-price-block">
        <div class="co-price-row">
          <span><?= e($planData['nom']) ?> × <span id="sb-dur">1</span> mois</span>
          <span id="sb-base"><?= number_format((int)$planData['prix']) ?> CDF</span>
        </div>
        <div class="co-price-row saving" id="sb-disc-row" style="display:none">
          <span>Économie</span>
          <span id="sb-disc-val"></span>
        </div>
        <div class="co-price-total">
          <span>Total</span>
          <span id="sb-total"><?= number_format((int)$planData['prix']) ?></span>
          <span style="font-size:13px;color:rgba(255,255,255,.4);font-family:var(--font-body);font-weight:400">CDF</span>
        </div>
        <div style="margin-top:12px;font-size:11px;color:rgba(255,255,255,.3);display:flex;flex-direction:column;gap:5px">
          <span><i data-lucide="shield-check" style="width:11px;height:11px;stroke:rgba(255,255,255,.3)"></i> Activation sous 24h après confirmation</span>
          <span><i data-lucide="rotate-ccw" style="width:11px;height:11px;stroke:rgba(255,255,255,.3)"></i> Sans engagement, annulable à tout moment</span>
        </div>
      </div>
    </div>

    <!-- Contact support -->
    <div style="margin-top:12px;text-align:center;font-size:12px;color:var(--gris-400)">
      Une question ?
      <a href="https://wa.me/243977329184" target="_blank" rel="noopener" style="color:#25D366;font-weight:600;text-decoration:none">
        <i data-lucide="message-circle" style="width:12px;height:12px;vertical-align:-1px;stroke:#25D366"></i> WhatsApp
      </a>
      ·
      <a href="mailto:paiement@reussiteplus.cd" style="color:var(--primary);font-weight:600;text-decoration:none">Email</a>
    </div>
  </div>
</div><!-- .checkout-wrap -->

<script>
const PRIX_UNITAIRE = <?= (int)$planData['prix'] ?>;
const DUR_DISC = {1:0, 3:5, 6:10, 12:15};
let promoValeur = 0, promoType = '';

function fmt(n) {
  return Math.round(n).toLocaleString('fr-FR') + ' CDF';
}
function fmtNum(n) {
  return Math.round(n).toLocaleString('fr-FR');
}

function updateCalc() {
  const durEl  = document.querySelector('input[name="duree"]:checked');
  const duree  = durEl ? parseInt(durEl.value) : 1;
  const base   = PRIX_UNITAIRE * duree;
  const discPct = DUR_DISC[duree] || 0;
  const durDisc = Math.round(base * discPct / 100);
  const basePromo = base - durDisc;
  const promDisc  = promoType === 'POURCENTAGE'
    ? Math.round(basePromo * promoValeur / 100)
    : Math.min(basePromo, promoValeur);
  const total = Math.max(0, base - durDisc - promDisc);
  const totalSaving = durDisc + promDisc;

  // Form recap
  const s = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
  const disp = (id, v) => { const el = document.getElementById(id); if (el) el.style.display = v ? '' : 'none'; };

  s('recap-dur', duree);
  s('recap-base', fmt(base));
  s('recap-total', fmt(total));
  if (durDisc > 0) {
    disp('dur-disc-row', true);
    s('dur-disc-label', `Réduction ${discPct}% (${duree} mois)`);
    s('dur-disc-val', '-' + fmt(durDisc));
  } else { disp('dur-disc-row', false); }
  if (promDisc > 0) {
    disp('promo-disc-row', true);
    s('promo-disc-val', '-' + fmt(promDisc));
  } else { disp('promo-disc-row', false); }

  // Sidebar
  s('sb-dur', duree);
  s('sb-base', fmtNum(base) + ' CDF');
  s('sb-total', fmtNum(total));
  if (totalSaving > 0) {
    disp('sb-disc-row', true);
    s('sb-disc-val', '-' + fmt(totalSaving));
  } else { disp('sb-disc-row', false); }

  // Dur-cards style
  document.querySelectorAll('.dur-item').forEach(item => {
    const card = item.querySelector('.dur-card');
    const inp  = item.querySelector('input');
    if (!card || !inp) return;
    if (inp.checked) {
      card.style.borderColor = 'var(--primary)';
      card.style.background  = 'var(--primary-subtle)';
    } else {
      card.style.borderColor = 'var(--gris-200)';
      card.style.background  = '';
    }
    const nameEl = card.querySelector('.dur-name');
    if (nameEl) nameEl.style.color = inp.checked ? 'var(--primary)' : '';
  });
}

function onMethodChange(label) {
  document.querySelectorAll('.pay-opt').forEach(l => l.classList.remove('active'));
  label.classList.add('active');
}

async function applyPromo() {
  const input = document.getElementById('promo-input');
  const msgEl = document.getElementById('promo-msg');
  const btn   = document.getElementById('promo-btn');
  const code  = input?.value?.trim()?.toUpperCase() || '';
  if (!code) { showPromoMsg('Entrez un code promo.', false); return; }

  btn.textContent = '…';
  btn.disabled = true;

  const fd = new FormData();
  fd.append('action',     'verifier_promo');
  fd.append('code',       code);
  fd.append('plan',       '<?= e($planKey) ?>');
  fd.append('csrf_token', '<?= e(csrf_token()) ?>');

  try {
    const r = await fetch(window.location.href, {method:'POST', body:fd});
    const d = await r.json();
    if (d.ok) {
      promoValeur = parseFloat(d.valeur);
      promoType   = d.type;
      showPromoMsg('✓ Code appliqué · Réduction : ' + d.remise, true);
      updateCalc();
    } else {
      promoValeur = 0; promoType = '';
      showPromoMsg(d.msg, false);
      updateCalc();
    }
  } catch(e) {
    showPromoMsg('Erreur réseau.', false);
  } finally {
    btn.textContent = 'Appliquer';
    btn.disabled = false;
  }
}

function showPromoMsg(msg, ok) {
  const el = document.getElementById('promo-msg');
  if (!el) return;
  el.style.display = '';
  el.textContent   = msg;
  el.style.color   = ok ? 'var(--primary)' : '#DC2626';
}

document.addEventListener('DOMContentLoaded', function () {
  updateCalc();
  // Soumission
  const form = document.getElementById('payForm');
  if (form) {
    form.addEventListener('submit', function () {
      const btn = document.getElementById('cta-btn');
      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spin" style="display:inline-block"><i data-lucide="loader-2" style="width:18px;height:18px;stroke:#fff"></i></span>&nbsp; Envoi en cours…';
        if (typeof lucide !== 'undefined') lucide.createIcons({nodes:[btn]});
      }
    });
  }
  // Keyboard Enter sur code promo
  document.getElementById('promo-input')?.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); applyPromo(); }
  });
});
</script>
<style>@keyframes spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}</style>

<?php endif; /* success/form */ ?>

<?php
// Page title correcte selon l'état
$pageTitle = $success ? 'Confirmation — ' . $planData['nom'] : 'Abonnement ' . $planData['nom'] . ' — RÉUSSITE+';
include __DIR__ . '/includes/footer_app.php'; ?>
