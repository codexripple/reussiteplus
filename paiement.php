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
$plan     = PLANS[$planKey];
$hasIA    = (bool)($plan['ia'] ?? false);
$errors   = [];
$success  = false;
$successRef     = '';
$successMontant = 0;

// Vérification code promo (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verifier_promo') {
    header('Content-Type: application/json');
    $code = strtoupper(trim($_POST['code'] ?? ''));
    if (!$code) { echo json_encode(['ok' => false, 'msg' => 'Code vide.']); exit; }
    $promo = dbRow(
        "SELECT * FROM codes_promo WHERE code=? AND actif=1
         AND (date_expiration IS NULL OR date_expiration > NOW())
         AND (nb_max IS NULL OR nb_utilisations < nb_max)",
        [$code]
    );
    if (!$promo) { echo json_encode(['ok' => false, 'msg' => 'Code invalide ou expiré.']); exit; }
    if ($promo['plan_applicable'] !== 'TOUS' && $promo['plan_applicable'] !== $planKey) {
        echo json_encode(['ok' => false, 'msg' => 'Ce code n\'est pas valable pour ce plan.']); exit;
    }
    $remise = $promo['type_remise'] === 'POURCENTAGE'
        ? $promo['valeur_remise'] . '%'
        : number_format($promo['valeur_remise'], 0, ',', ' ') . ' CDF';
    echo json_encode(['ok' => true, 'remise' => $remise, 'valeur' => $promo['valeur_remise'], 'type' => $promo['type_remise']]);
    exit;
}

// Soumission du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmer_paiement'])) {
    if (!csrf_verify()) {
        $errors[] = 'Token invalide. Rechargez la page.';
    } else {
        $methode   = $_POST['methode'] ?? '';
        $telephone = trim($_POST['telephone'] ?? '');
        $duree     = max(1, min(12, (int)($_POST['duree'] ?? 1)));
        $codePromo = strtoupper(trim($_POST['code_promo'] ?? ''));

        if (!array_key_exists($methode, METHODES_PAIEMENT)) $errors[] = 'Sélectionnez une méthode de paiement.';
        if (empty($telephone))                              $errors[] = 'Le numéro de téléphone est requis.';
        if (!preg_match('/^[+0-9\s]{9,15}$/', $telephone)) $errors[] = 'Numéro de téléphone invalide.';

        if (!$errors) {
            $remise = 0;
            // Réduction durée
            $dureeDiscounts = [1 => 0, 3 => 5, 6 => 10, 12 => 15];
            $montant = $plan['prix'] * $duree;
            $dureeDiscount = $montant * (($dureeDiscounts[$duree] ?? 0) / 100);
            $montant -= $dureeDiscount;

            // Code promo
            if ($codePromo) {
                $promo = dbRow(
                    "SELECT * FROM codes_promo WHERE code=? AND actif=1
                     AND (date_expiration IS NULL OR date_expiration > NOW())
                     AND (nb_max IS NULL OR nb_utilisations < nb_max)",
                    [$codePromo]
                );
                if ($promo && ($promo['plan_applicable'] === 'TOUS' || $promo['plan_applicable'] === $planKey)) {
                    if ($promo['type_remise'] === 'POURCENTAGE') {
                        $remise = $montant * ($promo['valeur_remise'] / 100);
                    } else {
                        $remise = min($montant, (float)$promo['valeur_remise']);
                    }
                    $montant = max(0, $montant - $remise);
                    dbQuery("UPDATE codes_promo SET nb_utilisations = nb_utilisations + 1 WHERE id=?", [$promo['id']]);
                }
            }

            $ref     = 'RP-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
            $dateDebut = date('Y-m-d');
            $dateFin   = date('Y-m-d', strtotime("+{$duree} month"));

            dbInsert('abonnements', [
                'user_id'            => $user['id'],
                'plan'               => $planKey,
                'montant'            => $montant,
                'devise'             => 'CDF',
                'methode_paiement'   => $methode,
                'reference_paiement' => $ref,
                'telephone'          => $telephone,
                'statut'             => 'EN_ATTENTE',
                'date_debut'         => $dateDebut,
                'date_fin'           => $dateFin,
                'duree_mois'         => $duree,
                'code_promo'         => $codePromo ?: null,
                'remise'             => $remise,
            ]);

            dbInsert('notifications', [
                'user_id' => $user['id'],
                'type'    => 'PAIEMENT',
                'titre'   => 'Demande d\'abonnement reçue',
                'message' => "Votre demande d'abonnement {$plan['nom']} (Réf: {$ref}) a été reçue. Confirmation sous 24h après vérification.",
                'lien'    => '/reussiteplus/abonnement.php',
            ]);

            $success        = true;
            $successRef     = $ref;
            $successMontant = $montant;
        }
    }
}

$planIcons = ['GRATUIT' => 'backpack', 'BASIQUE' => 'zap', 'PREMIUM' => 'crown', 'ECOLE' => 'school'];
$payMeta   = [
    'MPESA'        => ['#00A651', 'smartphone', 'Via USSD *150*00# ou application M-Pesa'],
    'AIRTEL_MONEY' => ['#E40613', 'smartphone', 'Via USSD *185# ou application Airtel Money'],
    'ORANGE_MONEY' => ['#FF6600', 'smartphone', 'Via USSD *144# ou application Orange Money'],
];

include __DIR__ . '/includes/header_app.php';
?>

<style>
/* ── Paiement page ─────────────────────────────────────── */
.pay-layout { display:grid; grid-template-columns:1fr 380px; gap:24px; max-width:960px; margin:0 auto; align-items:start; }
@media(max-width: 800px) { .pay-layout { grid-template-columns:1fr; } }

/* Plan recap sidebar */
.plan-recap {
  background: linear-gradient(160deg, #0D1117 0%, #1a1a2e 100%);
  border-radius: var(--radius-xl); padding: 24px; color: #fff; position: sticky; top: 20px;
}
.plan-recap-icon {
  width: 60px; height: 60px; border-radius: 16px;
  display: flex; align-items: center; justify-content: center; margin-bottom: 16px;
}
.plan-recap-name { font-family: var(--font-display); font-size: 22px; font-weight: 900; margin-bottom: 4px; }
.plan-recap-price { font-size: 28px; font-weight: 900; line-height: 1.1; }
.plan-recap-period { font-size: 12px; color: rgba(255,255,255,.45); margin-bottom: 20px; }
.plan-recap-feats { display:flex; flex-direction:column; gap:8px; border-top:1px solid rgba(255,255,255,.1); padding-top:16px; }
.plan-recap-feat { display:flex; align-items:center; gap:10px; font-size:13px; color:rgba(255,255,255,.75); }
.plan-recap-feat svg { flex-shrink:0; }

/* IA highlight inside recap */
.ia-pill {
  background: rgba(124,58,237,.3); border:1px solid rgba(124,58,237,.5);
  border-radius: var(--radius); padding: 12px 14px; margin: 16px 0;
  display: flex; align-items: flex-start; gap: 10px;
}
.ia-pill-text { font-size: 12px; color: #C4B5FD; line-height: 1.5; }
.ia-pill-title { font-weight: 700; color: #DDD6FE; margin-bottom: 2px; font-size: 13px; }

/* Totaux sidebar */
.recap-line { display:flex; justify-content:space-between; font-size:13px; padding:4px 0; color:rgba(255,255,255,.65); }
.recap-total { display:flex; justify-content:space-between; font-family:var(--font-display); font-size:20px; font-weight:800; border-top:1px solid rgba(255,255,255,.15); margin-top:8px; padding-top:10px; color:#fff; }

/* Formulaire */
.pay-form-card { background:var(--blanc); border:1px solid var(--gris-200); border-radius:var(--radius-xl); overflow:hidden; }
.pay-form-card-head { padding:20px 24px; border-bottom:1px solid var(--gris-100); background:var(--gris-50); }
.pay-form-section { padding:20px 24px; border-bottom:1px solid var(--gris-100); }
.pay-form-section:last-child { border-bottom:none; }
.pay-form-title { font-size:13px; font-weight:700; color:var(--gris-800); margin-bottom:12px; display:flex; align-items:center; gap:8px; }

/* Méthodes radio */
.pay-method-opt {
  display:flex; align-items:center; gap:14px;
  border:2px solid var(--gris-200); border-radius:var(--radius);
  padding:13px 16px; cursor:pointer; transition:all .15s; margin-bottom:8px;
}
.pay-method-opt:last-child { margin-bottom:0; }
.pay-method-opt.selected { border-color:var(--primary); background:var(--primary-subtle); }
.pay-method-dot { width:38px; height:38px; border-radius:10px; flex-shrink:0; display:flex; align-items:center; justify-content:center; }
.pay-method-name { font-size:14px; font-weight:600; color:var(--gris-800); }
.pay-method-desc { font-size:11px; color:var(--gris-500); }
.pay-method-radio { margin-left:auto; width:18px; height:18px; accent-color:var(--primary); cursor:pointer; }

/* Durées */
.dur-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:8px; }
@media(max-width:480px) { .dur-grid { grid-template-columns:repeat(2,1fr); } }
.dur-opt { text-align:center; cursor:pointer; }
.dur-card { border:2px solid var(--gris-200); border-radius:var(--radius); padding:10px 6px; transition:all .15s; }
.dur-opt input:checked + .dur-card { border-color:var(--primary); background:var(--primary-subtle); color:var(--primary); }
.dur-label { font-size:13px; font-weight:700; }
.dur-badge { font-size:10px; color:var(--primary); font-weight:600; margin-top:2px; }
</style>

<?php if ($success): ?>
<!-- ══ CONFIRMATION ══════════════════════════════════════════ -->
<div style="max-width:580px;margin:0 auto">
  <div class="card" style="text-align:center;padding:48px 32px">
    <div style="width:72px;height:72px;border-radius:20px;background:#D1FAE5;display:flex;align-items:center;justify-content:center;margin:0 auto 20px">
      <i data-lucide="check-circle" style="width:36px;height:36px;stroke:#065F46"></i>
    </div>
    <div style="font-family:var(--font-display);font-size:26px;font-weight:900;margin-bottom:8px">Demande envoyée !</div>
    <p style="font-size:14px;color:var(--gris-600);line-height:1.8;max-width:420px;margin:0 auto 24px">
      Votre demande d'abonnement <strong><?= e($plan['nom']) ?></strong> a bien été enregistrée.<br>
      Référence : <code style="background:var(--gris-100);padding:2px 8px;border-radius:4px;font-size:13px"><?= e($successRef) ?></code><br>
      Total : <strong><?= number_format((int)$successMontant, 0, ',', ' ') ?> CDF</strong>
    </p>

    <!-- Étapes à suivre -->
    <div style="background:var(--gris-50);border:1px solid var(--gris-200);border-radius:var(--radius-lg);padding:20px;text-align:left;margin-bottom:24px">
      <div style="font-size:13px;font-weight:700;margin-bottom:12px;color:var(--gris-800)">
        <i data-lucide="list-checks" style="width:15px;height:15px;vertical-align:-2px;stroke:var(--primary)"></i>
        Étapes suivantes
      </div>
      <?php foreach ([
        ['smartphone','Effectuez le virement via la méthode de paiement choisie au numéro indiqué'],
        ['camera','Prenez une capture d\'écran de la confirmation de votre virement'],
        ['mail','Envoyez-la à <a href="mailto:paiement@reussiteplus.cd" style="color:var(--primary);font-weight:600">paiement@reussiteplus.cd</a> avec la référence <strong>'.$successRef.'</strong>'],
        ['clock','Votre compte sera activé sous <strong>24h</strong> après vérification'],
      ] as [$ico, $txt]): ?>
      <div style="display:flex;gap:10px;font-size:13px;color:var(--gris-700);margin-bottom:8px;align-items:flex-start">
        <i data-lucide="<?= $ico ?>" style="width:16px;height:16px;stroke:var(--primary);flex-shrink:0;margin-top:1px"></i>
        <span><?= $txt ?></span>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if ($hasIA): ?>
    <div style="background:rgba(124,58,237,.08);border:1px solid rgba(124,58,237,.25);border-radius:var(--radius);padding:14px 18px;text-align:left;margin-bottom:24px">
      <div style="font-size:13px;font-weight:700;color:#6D28D9;margin-bottom:4px">
        <i data-lucide="sparkles" style="width:14px;height:14px;vertical-align:-2px;stroke:#7C3AED"></i>
        L'Assistant IA sera activé avec votre plan
      </div>
      <div style="font-size:12px;color:#7C3AED;line-height:1.6">
        Dès que votre paiement est confirmé, votre tuteur IA personnalisé sera disponible directement depuis votre tableau de bord.
      </div>
    </div>
    <?php endif; ?>

    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
      <a href="/reussiteplus/dashboard.php" class="btn btn-primary">
        <i data-lucide="layout-dashboard" style="width:13px;height:13px;vertical-align:-2px"></i> Mon tableau de bord
      </a>
      <a href="/reussiteplus/abonnement.php" class="btn btn-ghost">
        <i data-lucide="receipt" style="width:13px;height:13px;vertical-align:-2px"></i> Voir mon abonnement
      </a>
    </div>
  </div>
</div>

<?php else: ?>
<!-- ══ FORMULAIRE DE PAIEMENT ════════════════════════════════ -->

<div style="max-width:960px;margin:0 auto">
  <a href="/reussiteplus/tarifs.php" style="color:var(--primary);font-size:13px;font-weight:500;display:inline-flex;align-items:center;gap:5px;margin-bottom:16px">
    <i data-lucide="arrow-left" style="width:14px;height:14px;vertical-align:-2px"></i> Choisir un autre plan
  </a>

  <?php if ($errors): ?>
  <div style="background:#FEE2E2;border:1px solid #FCA5A5;color:#7F1D1D;border-radius:var(--radius);padding:12px 16px;margin-bottom:16px;font-size:13px;display:flex;align-items:center;gap:8px">
    <i data-lucide="alert-circle" style="width:16px;height:16px;stroke:#DC2626;flex-shrink:0"></i>
    <?= e($errors[0]) ?>
  </div>
  <?php endif; ?>
</div>

<div class="pay-layout">
  <!-- ── COLONNE GAUCHE: Formulaire ────────────────────────── -->
  <div>

    <!-- Méthodes de paiement -->
    <div class="pay-form-card" style="margin-bottom:16px">
      <div class="pay-form-card-head">
        <div style="font-family:var(--font-display);font-size:17px;font-weight:800;color:var(--gris-900)">
          <i data-lucide="credit-card" style="width:18px;height:18px;vertical-align:-3px;stroke:var(--primary)"></i>
          Méthode de paiement
        </div>
        <div style="font-size:12px;color:var(--gris-500);margin-top:4px">Mobile Money — sans frais supplémentaires</div>
      </div>
      <div class="pay-form-section">
        <form method="POST" id="paymentForm">
          <?= csrf_field() ?>
          <input type="hidden" name="confirmer_paiement" value="1">

          <?php foreach (METHODES_PAIEMENT as $key => $m):
            [$color, $ico, $ussd] = $payMeta[$key] ?? ['#6B7280','smartphone',''];
            $checked = ($_POST['methode'] ?? '') === $key;
          ?>
          <label class="pay-method-opt <?= $checked ? 'selected' : '' ?>" onclick="selectMethod(this)">
            <div class="pay-method-dot" style="background:<?= $color ?>18">
              <i data-lucide="<?= $ico ?>" style="width:18px;height:18px;stroke:<?= $color ?>"></i>
            </div>
            <div style="flex:1">
              <div class="pay-method-name"><?= e($m['nom']) ?></div>
              <div class="pay-method-desc"><?= e($ussd) ?> · Numéro : <strong><?= e($m['numero']) ?></strong></div>
            </div>
            <input type="radio" name="methode" value="<?= e($key) ?>" class="pay-method-radio" <?= $checked ? 'checked' : '' ?> required onchange="updateTotal()">
          </label>
          <?php endforeach; ?>
      </div>

      <!-- Numéro de téléphone -->
      <div class="pay-form-section">
        <div class="pay-form-title">
          <i data-lucide="phone" style="width:15px;height:15px;stroke:var(--primary)"></i>
          Votre numéro de téléphone
        </div>
        <input class="form-control" type="tel" name="telephone"
               placeholder="+243 8X XXX XXXX"
               value="<?= e($_POST['telephone'] ?? '') ?>"
               required>
        <div style="font-size:11px;color:var(--gris-500);margin-top:6px">
          <i data-lucide="info" style="width:11px;height:11px;vertical-align:-1px;stroke:var(--gris-400)"></i>
          Numéro avec lequel vous allez effectuer le virement
        </div>
      </div>

      <!-- Durée -->
      <div class="pay-form-section">
        <div class="pay-form-title">
          <i data-lucide="calendar" style="width:15px;height:15px;stroke:var(--primary)"></i>
          Durée d'abonnement
        </div>
        <div class="dur-grid">
          <?php foreach ([
            1  => ['label' => '1 mois',  'badge' => ''],
            3  => ['label' => '3 mois',  'badge' => '-5%'],
            6  => ['label' => '6 mois',  'badge' => '-10%'],
            12 => ['label' => '1 an',    'badge' => '-15%'],
          ] as $d => $info):
            $checked = ((int)($_POST['duree'] ?? 1)) === $d;
          ?>
          <label class="dur-opt">
            <input type="radio" name="duree" value="<?= $d ?>" <?= $checked || ($d === 1 && empty($_POST['duree'])) ? 'checked' : '' ?>
                   style="display:none" onchange="updateTotal()">
            <div class="dur-card">
              <div class="dur-label"><?= $info['label'] ?></div>
              <?php if ($info['badge']): ?>
              <div class="dur-badge"><?= $info['badge'] ?></div>
              <?php endif; ?>
            </div>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Code promo -->
      <div class="pay-form-section">
        <div class="pay-form-title">
          <i data-lucide="tag" style="width:15px;height:15px;stroke:var(--primary)"></i>
          Code promo <span style="font-weight:400;color:var(--gris-400)">(optionnel)</span>
        </div>
        <div style="display:flex;gap:8px">
          <input class="form-control" type="text" id="code_promo" name="code_promo"
                 placeholder="Ex: RENTRÉE2025"
                 value="<?= e($_POST['code_promo'] ?? '') ?>"
                 style="text-transform:uppercase;flex:1">
          <button type="button" class="btn btn-ghost" onclick="verifyPromo()">Vérifier</button>
        </div>
        <div id="promo-result" style="font-size:12px;margin-top:6px"></div>
      </div>

      <!-- Récap + CTA -->
      <div class="pay-form-section" style="background:var(--gris-50)">
        <div id="recap-box" style="margin-bottom:16px">
          <div class="recap-line">
            <span>Plan <?= e($plan['nom']) ?> × <span id="r-duree">1</span> mois</span>
            <span id="r-subtotal"><?= number_format($plan['prix']) ?> CDF</span>
          </div>
          <div id="r-dur-discount-line" style="display:none" class="recap-line">
            <span>Réduction durée</span>
            <span id="r-dur-discount" style="color:var(--primary)"></span>
          </div>
          <div id="r-promo-line" style="display:none" class="recap-line">
            <span>Code promo</span>
            <span id="r-promo-val" style="color:var(--primary)"></span>
          </div>
          <div style="display:flex;justify-content:space-between;border-top:1px solid var(--gris-200);margin-top:8px;padding-top:10px;font-weight:800;font-size:17px;color:var(--gris-900)">
            <span>Total à payer</span>
            <span id="r-total"><?= number_format($plan['prix']) ?> CDF</span>
          </div>
        </div>

        <div style="background:#EEF2FF;border:1px solid #C7D2FE;border-radius:var(--radius);padding:12px 14px;font-size:12px;color:#4338CA;margin-bottom:16px;display:flex;align-items:flex-start;gap:8px">
          <i data-lucide="info" style="width:14px;height:14px;stroke:#4338CA;flex-shrink:0;margin-top:1px"></i>
          <span>Après soumission, envoyez votre capture d'écran de paiement à
          <strong>paiement@reussiteplus.cd</strong> en mentionnant votre référence.
          Activation sous <strong>24h</strong>.</span>
        </div>

        <button type="submit" class="btn btn-full btn-lg" id="submitBtn"
                style="background:<?= $plan['couleur'] ?>;color:#fff;font-weight:800;border:none;font-size:16px;padding:15px;border-radius:12px;cursor:pointer;transition:opacity .15s;display:flex;align-items:center;justify-content:center;gap:10px"
                onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'">
          <i data-lucide="send" style="width:18px;height:18px;stroke:#fff"></i>
          Confirmer ma demande d'abonnement
        </button>
      </div>
      </form>
    </div>
  </div>

  <!-- ── COLONNE DROITE: Récap Plan ────────────────────────── -->
  <div>
    <div class="plan-recap">
      <!-- Icône + nom plan -->
      <div class="plan-recap-icon" style="background:<?= $plan['couleur'] ?>">
        <i data-lucide="<?= $planIcons[$planKey] ?? 'zap' ?>" style="width:28px;height:28px;stroke:#fff"></i>
      </div>
      <div class="plan-recap-name"><?= e($plan['nom']) ?></div>
      <div style="margin-bottom:4px">
        <span class="plan-recap-price" style="color:<?= $plan['couleur'] === '#C9972A' ? '#FBBF24' : '#fff' ?>"><?= number_format($plan['prix'], 0, ',', ' ') ?></span>
        <span style="font-size:14px;color:rgba(255,255,255,.5)"> CDF</span>
      </div>
      <div class="plan-recap-period">par mois · sans engagement</div>

      <!-- Bloc IA si applicable -->
      <?php if ($hasIA): ?>
      <div class="ia-pill">
        <i data-lucide="sparkles" style="width:18px;height:18px;stroke:#C4B5FD;flex-shrink:0"></i>
        <div>
          <div class="ia-pill-title">Assistant IA inclus</div>
          <div class="ia-pill-text">Tuteur intelligent LLaMA 3 · Explications personnalisées · Plan de révision adaptatif</div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Fonctionnalités incluses -->
      <div class="plan-recap-feats">
        <?php
        $recapFeats = [
          [$plan['examens_mois'] < 0 ? 'Examens illimités' : "{$plan['examens_mois']} examens/mois", 'pencil-line', true],
          [$plan['questions'] < 0 ? 'Questions illimitées' : "{$plan['questions']} questions/examen", 'help-circle', true],
          ['Archives ENAFEP / TENASOSP / État', 'archive', (bool)$plan['archives']],
          ['Corrigés et explications', 'file-check', (bool)$plan['corrige']],
          ['Assistant IA personnalisé', 'sparkles', (bool)$plan['ia']],
          ['Suivi de progression', 'trending-up', true],
        ];
        if (isset($plan['eleves_max'])) {
          $recapFeats[] = ["Jusqu'à {$plan['eleves_max']} élèves", 'users', true];
        }
        foreach ($recapFeats as [$label, $ico, $ok]):
        ?>
        <div class="plan-recap-feat" style="<?= !$ok ? 'opacity:.35' : '' ?>">
          <i data-lucide="<?= $ok ? $ico : 'x' ?>" style="width:14px;height:14px;stroke:<?= $ok ? ($ico === 'sparkles' ? '#C4B5FD' : 'var(--primary)') : '#9CA3AF' ?>"></i>
          <span style="color:<?= $ok ? 'rgba(255,255,255,.75)' : 'rgba(255,255,255,.3)' ?>"><?= e($label) ?></span>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Mini-total -->
      <div style="margin-top:20px;border-top:1px solid rgba(255,255,255,.1);padding-top:16px">
        <div class="recap-line">
          <span>× <span id="sidebar-duree">1</span> mois</span>
          <span id="sidebar-subtotal"><?= number_format($plan['prix']) ?> CDF</span>
        </div>
        <div id="sidebar-discount-line" style="display:none" class="recap-line">
          <span>Réduction</span>
          <span id="sidebar-discount" style="color:#6EE7B7"></span>
        </div>
        <div class="recap-total">
          <span>Total</span>
          <span id="sidebar-total"><?= number_format($plan['prix']) ?> CDF</span>
        </div>
      </div>

      <!-- Garanties -->
      <div style="margin-top:16px;display:flex;flex-direction:column;gap:6px">
        <div style="display:flex;gap:8px;font-size:11px;color:rgba(255,255,255,.45);align-items:center">
          <i data-lucide="shield-check" style="width:13px;height:13px;stroke:rgba(255,255,255,.4)"></i>
          Paiement sécurisé Mobile Money
        </div>
        <div style="display:flex;gap:8px;font-size:11px;color:rgba(255,255,255,.45);align-items:center">
          <i data-lucide="clock" style="width:13px;height:13px;stroke:rgba(255,255,255,.4)"></i>
          Activation sous 24h après confirmation
        </div>
        <div style="display:flex;gap:8px;font-size:11px;color:rgba(255,255,255,.45);align-items:center">
          <i data-lucide="headphones" style="width:13px;height:13px;stroke:rgba(255,255,255,.4)"></i>
          Support WhatsApp disponible
        </div>
      </div>
    </div>
  </div>
</div>

<?php endif; ?>

<script>
const PLAN_PRIX = <?= (int)$plan['prix'] ?>;
const DUR_DISCOUNTS = {1: 0, 3: 5, 6: 10, 12: 15};
let promoValeur = 0;
let promoType   = '';

function fmt(n) { return Math.round(n).toLocaleString('fr-FR') + ' CDF'; }

function updateTotal() {
  const dureeEl = document.querySelector('input[name="duree"]:checked');
  const duree   = dureeEl ? parseInt(dureeEl.value) : 1;
  const subtotal = PLAN_PRIX * duree;
  const durDisc  = subtotal * (DUR_DISCOUNTS[duree] || 0) / 100;

  const promoDisc = promoType === 'POURCENTAGE'
    ? (subtotal - durDisc) * promoValeur / 100
    : Math.min(subtotal - durDisc, promoValeur);

  const total = Math.max(0, subtotal - durDisc - promoDisc);

  // Main form recap
  const set = (id, val) => { const el=document.getElementById(id); if(el) el.textContent=val; };
  const show = (id, visible) => { const el=document.getElementById(id); if(el) el.style.display=visible?'flex':'none'; };

  set('r-duree', duree);
  set('r-subtotal', fmt(subtotal));
  set('r-total', fmt(total));
  if (durDisc > 0) { show('r-dur-discount-line', true); set('r-dur-discount', '-' + fmt(durDisc)); }
  if (promoDisc > 0) { show('r-promo-line', true); set('r-promo-val', '-' + fmt(promoDisc)); }

  // Sidebar
  set('sidebar-duree', duree);
  set('sidebar-subtotal', fmt(subtotal));
  set('sidebar-total', fmt(total));
  if (durDisc + promoDisc > 0) {
    const sdd = document.getElementById('sidebar-discount-line');
    if (sdd) sdd.style.display = 'flex';
    set('sidebar-discount', '-' + fmt(durDisc + promoDisc));
  }

  // Active dur-card styles
  document.querySelectorAll('.dur-opt input').forEach(inp => {
    const card = inp.nextElementSibling;
    if (!card) return;
    if (inp.checked) {
      card.style.borderColor = 'var(--primary)';
      card.style.background  = 'var(--primary-subtle)';
      card.style.color       = 'var(--primary)';
    } else {
      card.style.borderColor = 'var(--gris-200)';
      card.style.background  = '';
      card.style.color       = '';
    }
  });
}

function selectMethod(label) {
  document.querySelectorAll('.pay-method-opt').forEach(l => l.classList.remove('selected'));
  label.classList.add('selected');
  const radio = label.querySelector('input[type=radio]');
  if (radio) { radio.checked = true; updateTotal(); }
}

async function verifyPromo() {
  const code = document.getElementById('code_promo')?.value?.trim()?.toUpperCase();
  const res  = document.getElementById('promo-result');
  if (!code) { if(res){res.textContent='⚠️ Entrez un code.';res.style.color='var(--rouge)';} return; }
  const fd = new FormData();
  fd.append('action', 'verifier_promo');
  fd.append('code', code);
  fd.append('plan', '<?= e($planKey) ?>');
  fd.append('csrf_token', '<?= e(csrf_token()) ?>');
  try {
    const r = await fetch(window.location.href, {method:'POST', body:fd});
    const d = await r.json();
    if (d.ok) {
      if(res){res.textContent='✅ Code valide ! Réduction : '+d.remise;res.style.color='var(--primary)';}
      promoValeur = parseFloat(d.valeur); promoType = d.type; updateTotal();
    } else {
      if(res){res.textContent='❌ '+d.msg;res.style.color='var(--rouge)';}
      promoValeur = 0; promoType = '';
    }
  } catch(e) { console.error(e); }
}

// Init
document.addEventListener('DOMContentLoaded', function() {
  updateTotal();
  // Soumission
  const form = document.getElementById('paymentForm');
  if (form) {
    form.addEventListener('submit', function() {
      const btn = document.getElementById('submitBtn');
      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="loader-2" style="width:18px;height:18px;stroke:#fff;animation:spin 1s linear infinite"></i> Envoi en cours...';
        if (typeof lucide !== 'undefined') lucide.createIcons({nodes:[btn]});
      }
    });
  }
});
</script>
<style>@keyframes spin{to{transform:rotate(360deg)}}</style>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
