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
$errors  = [];
$success = false;

/* ── Handler AJAX : v&eacute;rification code promo ─────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verifier_promo') {
    header('Content-Type: application/json; charset=utf-8');
    if (!csrf_verify()) {
        echo json_encode(['ok' => false, 'msg' => 'Token invalide.']);
        exit;
    }
    $ajaxCode = strtoupper(trim($_POST['code'] ?? ''));
    $ajaxPlan = strtoupper(trim($_POST['plan'] ?? ''));
    $promo = dbRow(
        "SELECT * FROM codes_promo
         WHERE code = ? AND actif = 1
           AND (date_expiration IS NULL OR date_expiration > NOW())
           AND (nb_max IS NULL OR nb_utilisations < nb_max)",
        [$ajaxCode]
    );
    if (!$promo || ($promo['plan_applicable'] !== 'TOUS' && $promo['plan_applicable'] !== $ajaxPlan)) {
        echo json_encode(['ok' => false, 'msg' => 'Code invalide ou expir&eacute;.']);
        exit;
    }
    $remiseLabel = $promo['type_remise'] === 'POURCENTAGE'
        ? $promo['valeur_remise'] . '% de r&eacute;duction'
        : number_format((float)$promo['valeur_remise'], 0, ',', ' ') . ' CDF de r&eacute;duction';
    echo json_encode([
        'ok'     => true,
        'valeur' => (float)$promo['valeur_remise'],
        'type'   => $promo['type_remise'],
        'remise' => $remiseLabel,
    ]);
    exit;
}

/* ── Traitement du formulaire de paiement ───────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmer_paiement'])) {
    if (!csrf_verify()) {
        $errors[] = 'Token invalide. Rechargez la page.';
    } else {
        $methode   = $_POST['methode']   ?? '';
        $telephone = trim($_POST['telephone'] ?? '');
        $duree     = (int)($_POST['duree'] ?? 1);
        $codePromo = strtoupper(trim($_POST['code_promo'] ?? ''));

        if (!array_key_exists($methode, METHODES_PAIEMENT)) $errors[] = 'M&eacute;thode de paiement invalide.';
        if (empty($telephone))                               $errors[] = 'Num&eacute;ro de t&eacute;l&eacute;phone requis.';
        if ($duree < 1 || $duree > 12)                      $errors[] = 'Dur&eacute;e invalide.';

        if (!$errors) {
            $montant = $plan['prix'] * $duree;
            $remise  = 0;

            /* Remise dur&eacute;e */
            $remisesDuree = [3 => 5, 6 => 10, 12 => 15];
            if (isset($remisesDuree[$duree])) {
                $remise += $montant * $remisesDuree[$duree] / 100;
            }

            /* Code promo */
            if ($codePromo) {
                $promoRow = dbRow(
                    "SELECT * FROM codes_promo
                     WHERE code = ? AND actif = 1
                       AND (date_expiration IS NULL OR date_expiration > NOW())
                       AND (nb_max IS NULL OR nb_utilisations < nb_max)",
                    [$codePromo]
                );
                if ($promoRow && ($promoRow['plan_applicable'] === 'TOUS' || $promoRow['plan_applicable'] === $planKey)) {
                    $remise += $promoRow['type_remise'] === 'POURCENTAGE'
                        ? $montant * ($promoRow['valeur_remise'] / 100)
                        : min($montant, (float)$promoRow['valeur_remise']);
                    dbQuery("UPDATE codes_promo SET nb_utilisations = nb_utilisations + 1 WHERE id = ?", [$promoRow['id']]);
                }
            }

            $montant = max(0, $montant - $remise);
            $ref     = 'RP-' . strtoupper(substr(md5(uniqid()), 0, 8));

            dbInsert('abonnements', [
                'user_id'            => $user['id'],
                'plan'               => $planKey,
                'montant'            => $montant,
                'devise'             => 'CDF',
                'methode_paiement'   => $methode,
                'reference_paiement' => $ref,
                'telephone'          => $telephone,
                'statut'             => 'EN_ATTENTE',
                'date_debut'         => date('Y-m-d'),
                'date_fin'           => date('Y-m-d', strtotime("+{$duree} month")),
                'duree_mois'         => $duree,
                'code_promo'         => $codePromo ?: null,
                'remise'             => $remise,
            ]);

            dbInsert('notifications', [
                'user_id' => $user['id'],
                'type'    => 'PAIEMENT',
                'titre'   => 'Paiement en attente de confirmation',
                'message' => "Votre demande d'abonnement {$plan['nom']} (R&eacute;f: {$ref}) a bien &eacute;t&eacute; re&ccedil;ue. Elle sera confirm&eacute;e sous 24h apr&egrave;s v&eacute;rification du paiement.",
                'lien'    => '/reussiteplus/abonnement.php',
            ]);

            $success        = true;
            $successRef     = $ref;
            $successMontant = $montant;
        }
    }
}

include __DIR__ . '/includes/header_app.php';
?>

<style>
/* ── Page paiement Schoolap-style ─────────────────────── */
.pay-wrap {
    max-width: 1020px;
    margin: 0 auto;
    padding: 0 16px 60px;
}
.pay-breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: var(--gris-500);
    margin-bottom: 28px;
    padding-top: 8px;
}
.pay-breadcrumb a {
    color: var(--primary);
    font-weight: 600;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 4px;
}
.pay-breadcrumb a:hover { text-decoration: underline; }
.pay-breadcrumb .sep { color: var(--gris-300); }

/* Layout deux colonnes */
.pay-grid {
    display: grid;
    grid-template-columns: 340px 1fr;
    gap: 24px;
    align-items: start;
}
@media (max-width: 820px) {
    .pay-grid { grid-template-columns: 1fr; }
}

/* Colonne gauche — r&eacute;cap plan */
.pay-summary {
    background: var(--blanc);
    border: 1.5px solid var(--gris-200);
    border-radius: 14px;
    overflow: hidden;
    position: sticky;
    top: 80px;
}
.pay-summary-header {
    background: linear-gradient(135deg, var(--primary) 0%, #005C47 100%);
    padding: 24px 20px;
    color: #fff;
}
.pay-summary-header.premium {
    background: linear-gradient(135deg, #B8860B 0%, var(--gold) 100%);
}
.pay-plan-icon {
    width: 52px;
    height: 52px;
    border-radius: 12px;
    background: rgba(255,255,255,.18);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin-bottom: 12px;
}
.pay-plan-name {
    font-family: var(--font-display);
    font-size: 22px;
    font-weight: 800;
    margin-bottom: 2px;
}
.pay-plan-price {
    font-size: 14px;
    opacity: .85;
}
.pay-features {
    padding: 18px 20px;
    border-bottom: 1px solid var(--gris-100);
}
.pay-feature-item {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    color: var(--gris-700);
    margin-bottom: 9px;
}
.pay-feature-item:last-child { margin-bottom: 0; }
.pay-feature-item i { color: var(--primary); font-size: 15px; flex-shrink: 0; }
.pay-totals {
    padding: 18px 20px;
}
.pay-total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13px;
    color: var(--gris-600);
    margin-bottom: 8px;
}
.pay-total-row.final {
    font-size: 17px;
    font-weight: 700;
    color: var(--noir);
    border-top: 1.5px solid var(--gris-200);
    padding-top: 12px;
    margin-top: 4px;
    margin-bottom: 0;
}
.pay-total-row .green { color: var(--primary); font-weight: 600; }

/* Colonne droite — formulaire */
.pay-form-card {
    background: var(--blanc);
    border: 1.5px solid var(--gris-200);
    border-radius: 14px;
    padding: 28px 28px;
}
@media (max-width: 500px) {
    .pay-form-card { padding: 20px 16px; }
}
.pay-form-title {
    font-family: var(--font-display);
    font-size: 18px;
    font-weight: 700;
    color: var(--noir);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.pay-form-title i { color: var(--primary); }

/* Méthode de paiement */
.pay-method-grid {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 4px;
}
.pay-method-label {
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 13px 16px;
    border: 2px solid var(--gris-200);
    border-radius: 10px;
    transition: border-color .15s, background .15s;
    position: relative;
}
.pay-method-label:has(input:checked) {
    border-color: var(--primary);
    background: var(--primary-subtle, #EAF5F1);
}
.pay-method-label input[type="radio"] {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}
.pay-method-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    background: var(--gris-100);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}
.pay-method-nom { font-weight: 600; font-size: 14px; color: var(--noir); }
.pay-method-num { font-size: 12px; color: var(--gris-500); margin-top: 2px; }
.pay-method-check {
    margin-left: auto;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 2px solid var(--gris-300);
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: border-color .15s, background .15s;
}
.pay-method-label:has(input:checked) .pay-method-check {
    background: var(--primary);
    border-color: var(--primary);
}
.pay-method-label:has(input:checked) .pay-method-check::after {
    content: '';
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #fff;
}

/* Dur&eacute;e */
.dur-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
}
@media (max-width: 420px) {
    .dur-grid { grid-template-columns: repeat(2, 1fr); }
}
.dur-label { cursor: pointer; }
.dur-label input { display: none; }
.dur-card {
    border: 2px solid var(--gris-200);
    border-radius: 9px;
    padding: 9px 4px;
    text-align: center;
    font-size: 11px;
    font-weight: 600;
    color: var(--gris-600);
    transition: border-color .15s, color .15s, background .15s;
    line-height: 1.4;
}
.dur-label input:checked + .dur-card {
    border-color: var(--primary);
    color: var(--primary);
    background: var(--primary-subtle, #EAF5F1);
}
.dur-card .dur-badge {
    display: block;
    font-size: 10px;
    color: var(--primary);
    margin-top: 2px;
}

/* Promo */
.promo-row { display: flex; gap: 8px; }
.promo-row .form-control { flex: 1; }

/* Bouton soumettre */
.btn-pay {
    width: 100%;
    padding: 15px 24px;
    font-size: 15px;
    font-weight: 700;
    border-radius: 10px;
    border: none;
    background: var(--primary);
    color: #fff;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: background .15s, transform .1s;
    margin-top: 8px;
}
.btn-pay:hover:not(:disabled) { background: #005C47; transform: translateY(-1px); }
.btn-pay:disabled { opacity: .7; cursor: not-allowed; }
.btn-pay.premium-btn { background: var(--gold); color: #fff; }
.btn-pay.premium-btn:hover:not(:disabled) { background: #b8860b; }

/* Page succ&egrave;s */
.pay-success {
    max-width: 600px;
    margin: 0 auto;
    text-align: center;
    padding: 48px 24px;
}
.pay-success-icon {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    background: #DCFCE7;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    margin: 0 auto 20px;
    color: var(--primary);
}
.pay-success-title {
    font-family: var(--font-display);
    font-size: 26px;
    font-weight: 800;
    margin-bottom: 10px;
    color: var(--noir);
}
.pay-success-ref {
    display: inline-block;
    background: var(--gris-100);
    border: 1px solid var(--gris-200);
    border-radius: 8px;
    padding: 8px 18px;
    font-family: var(--font-mono, monospace);
    font-size: 18px;
    font-weight: 700;
    color: var(--primary);
    letter-spacing: .08em;
    margin: 8px 0 20px;
}
.pay-steps {
    background: #F0FDF4;
    border: 1.5px solid #BBF7D0;
    border-radius: 12px;
    padding: 20px 24px;
    text-align: left;
    margin: 20px 0;
}
.pay-step {
    display: flex;
    gap: 12px;
    font-size: 13px;
    color: var(--gris-700);
    margin-bottom: 10px;
    line-height: 1.5;
}
.pay-step:last-child { margin-bottom: 0; }
.pay-step-num {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: var(--primary);
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-top: 1px;
}
</style>

<div class="pay-wrap">

<?php if ($success): /* ════════ PAGE SUCCÈS ════════ */ ?>

<div class="pay-success">
  <div class="pay-success-icon"><i class="bi bi-check-lg"></i></div>
  <div class="pay-success-title">Demande envoy&eacute;e !</div>
  <p style="color:var(--gris-600);font-size:14px;margin-bottom:8px">
    Votre demande d&rsquo;abonnement <strong><?= e($plan['nom']) ?></strong> a bien &eacute;t&eacute; re&ccedil;ue.
  </p>
  <div class="pay-success-ref"><?= e($successRef) ?></div>
  <p style="font-size:13px;color:var(--gris-500);margin-bottom:20px">
    Montant &agrave; virer&nbsp;: <strong><?= number_format((int)$successMontant, 0, ',', ' ') ?> CDF</strong>
  </p>

  <div class="pay-steps">
    <div class="pay-step">
      <div class="pay-step-num">1</div>
      <div>Effectuez le virement Mobile Money du montant indiqu&eacute; via la m&eacute;thode choisie.</div>
    </div>
    <div class="pay-step">
      <div class="pay-step-num">2</div>
      <div>Prenez une capture d&rsquo;&eacute;cran de votre confirmation de paiement.</div>
    </div>
    <div class="pay-step">
      <div class="pay-step-num">3</div>
      <div>Envoyez la capture &agrave; <a href="mailto:paiement@reussiteplus.cd" style="color:var(--primary);font-weight:600">paiement@reussiteplus.cd</a> en mentionnant la r&eacute;f&eacute;rence <strong><?= e($successRef) ?></strong>.</div>
    </div>
    <div class="pay-step">
      <div class="pay-step-num">4</div>
      <div>Activation de votre abonnement sous 24h apr&egrave;s v&eacute;rification.</div>
    </div>
  </div>

  <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
    <a href="/reussiteplus/dashboard.php" class="btn btn-primary">
      <i class="bi bi-house"></i>&nbsp; Tableau de bord
    </a>
    <a href="/reussiteplus/notifications.php" class="btn btn-ghost">
      <i class="bi bi-bell"></i>&nbsp; Mes notifications
    </a>
  </div>
</div>

<?php else: /* ════════ FORMULAIRE PAIEMENT ════════ */ ?>

<!-- Fil d'Ariane -->
<div class="pay-breadcrumb">
  <a href="/reussiteplus/tarifs.php">
    <i class="bi bi-arrow-left"></i> Choisir un plan
  </a>
  <span class="sep">/</span>
  <span>Finaliser le paiement</span>
  <span class="sep">/</span>
  <strong style="color:var(--noir)">Plan <?= e($plan['nom']) ?></strong>
</div>

<?php if ($errors): ?>
<div class="alert alert-error" style="margin-bottom:20px">
  <i class="bi bi-exclamation-triangle"></i>&nbsp;
  <?= e($errors[0]) ?>
</div>
<?php endif; ?>

<div class="pay-grid">

  <!-- ═══════════ COLONNE GAUCHE — R&Eacute;CAP PLAN ═══════════ -->
  <div class="pay-summary">

    <div class="pay-summary-header <?= $planKey === 'PREMIUM' ? 'premium' : '' ?>">
      <div class="pay-plan-icon">
        <i class="<?= e($plan['icone']) ?>"></i>
      </div>
      <div class="pay-plan-name">Plan <?= e($plan['nom']) ?></div>
      <div class="pay-plan-price"><?= e($plan['prix_affiche']) ?></div>
    </div>

    <div class="pay-features">
      <?php
      $examTxt = $plan['examens_mois'] < 0 ? 'Examens illimit&eacute;s' : $plan['examens_mois'] . ' examens / mois';
      $qsTxt   = $plan['questions']    < 0 ? 'Questions illimit&eacute;es' : $plan['questions'] . ' questions / examen';
      $feats   = [
          ['bi bi-pencil-square', $examTxt],
          ['bi bi-question-circle', $qsTxt],
          ['bi bi-file-earmark-pdf', $plan['archives'] ? 'Archives PDF incluses' : 'Archives non incluses'],
          ['bi bi-journal-check',   $plan['corrige']  ? 'Corrig&eacute;s d&eacute;taill&eacute;s' : 'Sans corrig&eacute;s'],
          ['bi bi-robot',           $plan['ia']       ? 'Assistant IA inclus'      : 'Sans assistant IA'],
      ];
      foreach ($feats as [$ic, $lbl]):
      ?>
      <div class="pay-feature-item">
        <i class="<?= e($ic) ?>"></i>
        <span><?= $lbl ?></span>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="pay-totals">
      <div class="pay-total-row">
        <span>Plan <span id="s-plan"><?= e($plan['nom']) ?></span> &times; <span id="s-duree">1</span> mois</span>
        <span id="s-subtotal"><?= number_format($plan['prix']) ?> CDF</span>
      </div>
      <div class="pay-total-row" id="s-dur-row" style="display:none">
        <span class="green">R&eacute;duction dur&eacute;e</span>
        <span class="green" id="s-dur-val">&ndash;0 CDF</span>
      </div>
      <div class="pay-total-row" id="s-promo-row" style="display:none">
        <span class="green">Code promo</span>
        <span class="green" id="s-promo-val">&ndash;0 CDF</span>
      </div>
      <div class="pay-total-row final">
        <span>Total &agrave; payer</span>
        <span id="s-total"><?= number_format($plan['prix']) ?> CDF</span>
      </div>
    </div>
  </div>
  <!-- /colonne gauche -->

  <!-- ═══════════ COLONNE DROITE — FORMULAIRE ═══════════ -->
  <div class="pay-form-card">

    <div class="pay-form-title">
      <i class="bi bi-credit-card-2-front"></i>
      Informations de paiement
    </div>

    <form method="POST" id="payForm">
      <?= csrf_field() ?>
      <input type="hidden" name="confirmer_paiement" value="1">

      <!-- M&eacute;thode -->
      <div class="form-group">
        <label class="form-label">
          <i class="bi bi-phone" style="color:var(--primary)"></i>
          M&eacute;thode de paiement <span style="color:var(--rouge)">*</span>
        </label>
        <div class="pay-method-grid">
          <?php foreach (METHODES_PAIEMENT as $mKey => $m): ?>
          <label class="pay-method-label">
            <input type="radio" name="methode" value="<?= e($mKey) ?>" required
                   onchange="recalc()">
            <div class="pay-method-icon">
              <i class="<?= e($m['icone']) ?>"></i>
            </div>
            <div>
              <div class="pay-method-nom"><?= e($m['nom']) ?></div>
              <div class="pay-method-num">N&deg; RÉUSSITE+&nbsp;: <?= e($m['numero']) ?></div>
            </div>
            <div class="pay-method-check"></div>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- T&eacute;l&eacute;phone -->
      <div class="form-group">
        <label class="form-label">
          <i class="bi bi-telephone" style="color:var(--primary)"></i>
          Votre num&eacute;ro de t&eacute;l&eacute;phone <span style="color:var(--rouge)">*</span>
        </label>
        <input class="form-control" type="tel" name="telephone"
               placeholder="+243 8X XXX XXXX"
               value="<?= e($_POST['telephone'] ?? '') ?>"
               pattern="[+0-9\s]{10,15}" required>
        <div style="font-size:11px;color:var(--gris-500);margin-top:4px">
          Num&eacute;ro utilis&eacute; pour effectuer le virement Mobile Money
        </div>
      </div>

      <!-- Dur&eacute;e -->
      <div class="form-group">
        <label class="form-label">
          <i class="bi bi-calendar3" style="color:var(--primary)"></i>
          Dur&eacute;e d&rsquo;abonnement
        </label>
        <div class="dur-grid">
          <?php
          $durOpts = [
              1  => ['1 mois',    null],
              3  => ['3 mois',   '-5&nbsp;%'],
              6  => ['6 mois',   '-10&nbsp;%'],
              12 => ['12 mois',  '-15&nbsp;%'],
          ];
          foreach ($durOpts as $d => [$lbl, $badge]):
          ?>
          <label class="dur-label">
            <input type="radio" name="duree" value="<?= $d ?>" <?= $d === 1 ? 'checked' : '' ?> onchange="recalc()">
            <div class="dur-card">
              <?= $lbl ?>
              <?php if ($badge): ?><span class="dur-badge"><?= $badge ?></span><?php endif; ?>
            </div>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Code promo -->
      <div class="form-group">
        <label class="form-label">
          <i class="bi bi-ticket-perforated" style="color:var(--primary)"></i>
          Code promo <span style="font-weight:400;color:var(--gris-500)">(optionnel)</span>
        </label>
        <div class="promo-row">
          <input class="form-control" type="text" id="code_promo" name="code_promo"
                 placeholder="Ex&nbsp;: BIENVENUE2025"
                 value="<?= e($_POST['code_promo'] ?? '') ?>"
                 style="text-transform:uppercase">
          <button type="button" class="btn btn-ghost" onclick="verifyPromo()" id="btnPromo">
            V&eacute;rifier
          </button>
        </div>
        <div id="promo-result" style="font-size:12px;margin-top:6px"></div>
      </div>

      <!-- S&eacute;parateur -->
      <div style="border-top:1.5px solid var(--gris-100);margin:20px 0"></div>

      <!-- Info instruction -->
      <div style="background:#F0FDF4;border:1.5px solid #BBF7D0;border-radius:10px;padding:14px 16px;font-size:13px;color:var(--gris-700);margin-bottom:20px">
        <i class="bi bi-info-circle" style="color:var(--primary)"></i>
        Apr&egrave;s soumission, envoyez la capture de paiement &agrave;
        <strong>paiement@reussiteplus.cd</strong> avec votre r&eacute;f&eacute;rence.
        Activation sous <strong>24h</strong>.
      </div>

      <!-- Bouton -->
      <button type="submit" class="btn-pay <?= $planKey === 'PREMIUM' ? 'premium-btn' : '' ?>" id="btnPay">
        <i class="bi bi-send"></i>
        Soumettre la demande &mdash; <span id="btn-total"><?= number_format($plan['prix']) ?> CDF</span>
      </button>
    </form>
  </div>
  <!-- /colonne droite -->

</div><!-- /pay-grid -->

<?php endif; ?>

</div><!-- /pay-wrap -->

<script>
const PLAN_PRIX = <?= (int)$plan['prix'] ?>;
const PLAN_KEY  = '<?= e($planKey) ?>';
const CSRF      = '<?= e(csrf_token()) ?>';
const REMISES_DUREE = {1: 0, 3: 5, 6: 10, 12: 15};
let promoValeur = 0;
let promoType   = '';

function fmt(n) {
    return Math.round(n).toLocaleString('fr-FR') + ' CDF';
}

function recalc() {
    const duree  = parseInt(document.querySelector('input[name="duree"]:checked')?.value || 1);
    const subtot = PLAN_PRIX * duree;
    const pctDur = REMISES_DUREE[duree] || 0;
    const durDis = subtot * pctDur / 100;
    const promDis = promoType === 'POURCENTAGE'
        ? subtot * promoValeur / 100
        : Math.min(subtot, promoValeur);
    const total = Math.max(0, subtot - durDis - promDis);

    document.getElementById('s-duree').textContent   = duree;
    document.getElementById('s-subtotal').textContent = fmt(subtot);
    document.getElementById('s-total').textContent    = fmt(total);
    document.getElementById('btn-total').textContent  = fmt(total);

    const durRow = document.getElementById('s-dur-row');
    if (durDis > 0) {
        durRow.style.display = 'flex';
        document.getElementById('s-dur-val').textContent = '\u2013' + fmt(durDis);
    } else {
        durRow.style.display = 'none';
    }

    const promoRow = document.getElementById('s-promo-row');
    if (promDis > 0) {
        promoRow.style.display = 'flex';
        document.getElementById('s-promo-val').textContent = '\u2013' + fmt(promDis);
    } else {
        promoRow.style.display = 'none';
    }
}

async function verifyPromo() {
    const code = document.getElementById('code_promo').value.trim().toUpperCase();
    const res  = document.getElementById('promo-result');
    const btn  = document.getElementById('btnPromo');
    if (!code) {
        res.innerHTML = '<span style="color:var(--rouge)">&#9888; Entrez un code promo.</span>';
        return;
    }
    btn.disabled = true;
    btn.textContent = '...';
    const fd = new FormData();
    fd.append('action',     'verifier_promo');
    fd.append('code',       code);
    fd.append('plan',       PLAN_KEY);
    fd.append('csrf_token', CSRF);
    try {
        const r    = await fetch(window.location.href, {method: 'POST', body: fd});
        const data = await r.json();
        if (data.ok) {
            res.innerHTML = '<span style="color:var(--primary)">&#10003; Code valide&nbsp;&mdash;&nbsp;' + data.remise + '</span>';
            promoValeur = parseFloat(data.valeur);
            promoType   = data.type;
            recalc();
        } else {
            res.innerHTML = '<span style="color:var(--rouge)">&#10007;&nbsp;' + data.msg + '</span>';
            promoValeur = 0;
            promoType   = '';
            recalc();
        }
    } catch (e) {
        res.innerHTML = '<span style="color:var(--rouge)">Erreur r&eacute;seau.</span>';
    }
    btn.disabled = false;
    btn.textContent = 'V\u00e9rifier';
}

document.getElementById('payForm').addEventListener('submit', function() {
    const btn = document.getElementById('btnPay');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Envoi en cours\u2026';
});
</script>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
