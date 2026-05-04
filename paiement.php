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
        $methode    = $_POST['methode']    ?? '';
        $telephone  = trim($_POST['telephone']  ?? '');
        $duree      = (int)($_POST['duree'] ?? 1);
        $codePromo  = strtoupper(trim($_POST['code_promo'] ?? ''));
        $isCard     = (METHODES_PAIEMENT[$methode]['type'] ?? '') === 'carte';

        // Champs carte
        $cardNum    = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
        $cardExpiry = trim($_POST['card_expiry'] ?? '');
        $cardCvc    = trim($_POST['card_cvc']    ?? '');

        if (!array_key_exists($methode, METHODES_PAIEMENT)) $errors[] = 'M&eacute;thode de paiement invalide.';
        if (!$isCard && empty($telephone))                   $errors[] = 'Num&eacute;ro de t&eacute;l&eacute;phone requis.';
        if ($isCard && strlen($cardNum) < 13)                $errors[] = 'Num&eacute;ro de carte invalide.';
        if ($isCard && !preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $cardExpiry)) $errors[] = 'Date d&rsquo;expiration invalide (MM/AA).';
        if ($isCard && (strlen($cardCvc) < 3))               $errors[] = 'Code CVC invalide.';
        if ($duree < 1 || $duree > 12)                       $errors[] = 'Dur&eacute;e invalide.';
        // Pour carte, le téléphone stocke les 4 derniers chiffres
        if ($isCard && !$errors) $telephone = 'CARTE-****' . substr($cardNum, -4);

        if (!$errors) {
            $montant = $plan['prix'] * $duree;
            $remise  = 0;
            $remiseDureeLabel = '';
            $remisePromoLabel = '';

            /* Remise durée */
            $remisesDuree = [3 => 5, 6 => 10, 12 => 15];
            if (isset($remisesDuree[$duree])) {
                $taux = $remisesDuree[$duree];
                $r    = $montant * $taux / 100;
                $remise += $r;
                $remiseDureeLabel = "Remise fidélité {$taux}% ({$duree} mois)";
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
                    $promoRemise = $promoRow['type_remise'] === 'POURCENTAGE'
                        ? $montant * ($promoRow['valeur_remise'] / 100)
                        : min($montant, (float)$promoRow['valeur_remise']);
                    $remise += $promoRemise;
                    $remisePromoLabel = 'Code promo ' . $codePromo;
                    dbQuery("UPDATE codes_promo SET nb_utilisations = nb_utilisations + 1 WHERE id = ?", [$promoRow['id']]);
                }
            }

            $sousTotal = $plan['prix'] * $duree;
            $montant   = max(0, $sousTotal - $remise);
            $ref       = 'RP-' . strtoupper(substr(md5(uniqid()), 0, 8));
            $numFacture = 'FAC-' . date('Ymd') . '-' . strtoupper(substr($ref, 3, 6));

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
                'message' => "Votre demande d'abonnement {$plan['nom']} (Réf: {$ref}) a bien été reçue. Elle sera confirmée sous 24h après vérification du paiement.",
                'lien'    => '/reussiteplus/abonnement.php',
            ]);

            $success            = true;
            $successRef         = $ref;
            $successMontant     = $montant;
            $successSousTotal   = $sousTotal;
            $successRemise      = $remise;
            $successNumFacture  = $numFacture;
            $successDuree       = $duree;
            $successMethode     = $methode;
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

<?php if ($success): /* ════════ PAGE SUCCÈS + FACTURE ════════ */
  $moisFr = ['','janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
  $dateEmission = date('j') . ' ' . $moisFr[(int)date('n')] . ' ' . date('Y');
  $dateEcheance = date('j') . ' ' . $moisFr[(int)date('n', strtotime('+30 days'))] . ' ' . date('Y', strtotime('+30 days'));
  $methodeName  = METHODES_PAIEMENT[$successMethode]['nom'] ?? $successMethode;
  $dateFin      = date('d/m/Y', strtotime("+{$successDuree} month"));
?>

<!-- Bannière succès -->
<div style="text-align:center;margin-bottom:32px">
  <div style="width:72px;height:72px;background:linear-gradient(135deg,var(--primary),var(--primary-light));border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;box-shadow:0 8px 24px rgba(5,150,105,0.35)">
    <i class="bi bi-check-lg" style="font-size:32px;color:white"></i>
  </div>
  <h2 style="font-family:var(--font-display);font-size:24px;font-weight:800;color:var(--gris-900);margin-bottom:6px">Demande envoyée avec succès !</h2>
  <p style="color:var(--gris-600);font-size:14px;max-width:480px;margin:0 auto">
    Votre demande d'abonnement <strong><?= e($plan['nom']) ?></strong> a bien été reçue.
    Conservez votre facture ci-dessous.
  </p>
</div>

<!-- Actions imprimables -->
<div style="display:flex;justify-content:center;gap:12px;flex-wrap:wrap;margin-bottom:28px" class="no-print">
  <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer"></i> Imprimer la facture</button>
  <button onclick="downloadInvoice()" class="btn btn-ghost"><i class="bi bi-download"></i> Télécharger PDF</button>
  <a href="https://wa.me/243977329184?text=<?= rawurlencode('Bonjour, voici ma référence de paiement RÉUSSITE+ : ' . $successRef . ' — Plan ' . $plan['nom'] . ' — ' . number_format((int)$successMontant, 0, ',', ' ') . ' CDF') ?>" target="_blank" class="btn btn-ghost" style="background:#25D366;color:white;border-color:#25D366">
    <i class="bi bi-whatsapp"></i> Envoyer par WhatsApp
  </a>
</div>

<!-- ══════════ FACTURE PROFESSIONNELLE ══════════ -->
<div id="invoice" style="background:white;max-width:760px;margin:0 auto 32px;border-radius:16px;overflow:hidden;box-shadow:var(--shadow-md);border:1px solid var(--gris-200)">

  <!-- En-tête facture -->
  <div style="background:linear-gradient(135deg,#0F172A 0%,#1E3A2F 100%);padding:32px 40px;display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:20px">
    <div>
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
        <div style="width:44px;height:44px;background:linear-gradient(135deg,var(--primary),#34D399);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <i class="bi bi-mortarboard-fill" style="color:white;font-size:20px"></i>
        </div>
        <div>
          <div style="font-family:var(--font-display);font-size:22px;font-weight:900;color:white;letter-spacing:-0.3px">RÉUSSITE<span style="color:#FBBF24">+</span></div>
          <div style="font-size:10px;color:rgba(255,255,255,0.45);letter-spacing:2px;text-transform:uppercase">Plateforme EdTech RDC</div>
        </div>
      </div>
      <div style="font-size:11px;color:rgba(255,255,255,0.5);line-height:1.8">
        Kinshasa, République Démocratique du Congo<br>
        contact@reussiteplus.cd · reussiteplus.cd<br>
        WhatsApp : +243 977 329 184
      </div>
    </div>
    <div style="text-align:right">
      <div style="font-size:11px;color:rgba(255,255,255,0.4);text-transform:uppercase;letter-spacing:2px;margin-bottom:6px">Facture Pro Forma</div>
      <div style="font-family:var(--font-display);font-size:28px;font-weight:900;color:#FBBF24;letter-spacing:1px"><?= e($successNumFacture) ?></div>
      <div style="margin-top:10px;font-size:12px;color:rgba(255,255,255,0.55)">
        <div>Émise le : <strong style="color:rgba(255,255,255,0.85)"><?= $dateEmission ?></strong></div>
        <div style="margin-top:2px">Réf. paiement : <strong style="color:rgba(255,255,255,0.85)"><?= e($successRef) ?></strong></div>
      </div>
      <!-- Statut -->
      <div style="margin-top:14px;background:rgba(251,191,36,0.18);border:1px solid rgba(251,191,36,0.4);border-radius:20px;padding:5px 16px;display:inline-block;font-size:11px;font-weight:700;color:#FBBF24;text-transform:uppercase;letter-spacing:1px">
        ⏳ En attente de confirmation
      </div>
    </div>
  </div>

  <!-- Infos client / émetteur -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:0;border-bottom:1px solid #E5E7EB">
    <div style="padding:24px 32px;border-right:1px solid #E5E7EB">
      <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:#9CA3AF;margin-bottom:10px">Facturé à</div>
      <div style="font-family:var(--font-display);font-size:16px;font-weight:800;color:#111827"><?= e($user['prenom'] . ' ' . strtoupper($user['nom'])) ?></div>
      <div style="font-size:13px;color:#6B7280;margin-top:4px;line-height:1.8">
        <?= e($user['email']) ?><br>
        Kinshasa, RDC
      </div>
    </div>
    <div style="padding:24px 32px">
      <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:#9CA3AF;margin-bottom:10px">Détails de la commande</div>
      <div style="font-size:13px;color:#374151;line-height:2">
        <div style="display:flex;justify-content:space-between"><span style="color:#9CA3AF">Durée :</span> <strong><?= $successDuree ?> mois</strong></div>
        <div style="display:flex;justify-content:space-between"><span style="color:#9CA3AF">Méthode :</span> <strong><?= e($methodeName) ?></strong></div>
        <div style="display:flex;justify-content:space-between"><span style="color:#9CA3AF">Début :</span> <strong><?= date('d/m/Y') ?></strong></div>
        <div style="display:flex;justify-content:space-between"><span style="color:#9CA3AF">Fin :</span> <strong><?= $dateFin ?></strong></div>
      </div>
    </div>
  </div>

  <!-- Tableau des articles -->
  <div style="padding:28px 32px">
    <table style="width:100%;border-collapse:collapse">
      <thead>
        <tr style="background:#F9FAFB;border-radius:8px">
          <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:#9CA3AF;border-bottom:2px solid #E5E7EB">Description</th>
          <th style="padding:10px 14px;text-align:center;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:#9CA3AF;border-bottom:2px solid #E5E7EB;white-space:nowrap">Qté</th>
          <th style="padding:10px 14px;text-align:right;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:#9CA3AF;border-bottom:2px solid #E5E7EB;white-space:nowrap">P.U. (CDF)</th>
          <th style="padding:10px 14px;text-align:right;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:#9CA3AF;border-bottom:2px solid #E5E7EB;white-space:nowrap">Total (CDF)</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td style="padding:16px 14px;border-bottom:1px solid #F3F4F6;vertical-align:top">
            <div style="font-weight:700;color:#111827;font-size:14px">
              <i class="<?= e($plan['icone'] ?? 'bi bi-mortarboard') ?>" style="color:<?= e($plan['couleur'] ?? '#059669') ?>;margin-right:6px"></i>
              Abonnement <?= e($plan['nom']) ?>
            </div>
            <div style="font-size:12px;color:#6B7280;margin-top:4px;line-height:1.6">
              <?= e($plan['tagline'] ?? '') ?><br>
              <?= $plan['examens_mois'] < 0 ? 'Examens illimités' : $plan['examens_mois'] . ' examens/mois' ?> •
              <?= $plan['archives'] ? 'Archives complètes' : 'Archives de base' ?> •
              <?= $plan['corrige'] ? 'Corrigés inclus' : 'Sans corrigés' ?> •
              <?= ($plan['ia'] ?? false) ? 'IA incluse' : 'Sans IA' ?>
            </div>
          </td>
          <td style="padding:16px 14px;text-align:center;border-bottom:1px solid #F3F4F6;color:#374151;font-weight:600"><?= $successDuree ?> mois</td>
          <td style="padding:16px 14px;text-align:right;border-bottom:1px solid #F3F4F6;color:#374151;white-space:nowrap"><?= number_format((int)$plan['prix'], 0, ',', ' ') ?></td>
          <td style="padding:16px 14px;text-align:right;border-bottom:1px solid #F3F4F6;font-weight:700;color:#111827;white-space:nowrap"><?= number_format((int)$successSousTotal, 0, ',', ' ') ?></td>
        </tr>
      </tbody>
    </table>

    <!-- Totaux -->
    <div style="display:flex;justify-content:flex-end;margin-top:16px">
      <div style="min-width:280px">
        <div style="display:flex;justify-content:space-between;padding:8px 0;font-size:13px;color:#6B7280;border-bottom:1px solid #F3F4F6">
          <span>Sous-total</span>
          <span style="font-weight:600;color:#374151"><?= number_format((int)$successSousTotal, 0, ',', ' ') ?> CDF</span>
        </div>
        <?php if (!empty($remiseDureeLabel) && $successRemise > 0): ?>
        <div style="display:flex;justify-content:space-between;padding:8px 0;font-size:13px;color:#059669;border-bottom:1px solid #F3F4F6">
          <span><i class="bi bi-tag-fill" style="font-size:10px;margin-right:4px"></i><?= e($remiseDureeLabel) ?></span>
          <span style="font-weight:600">−<?= number_format((int)($successRemise), 0, ',', ' ') ?> CDF</span>
        </div>
        <?php endif; ?>
        <?php if (!empty($remisePromoLabel) && isset($promoRemise) && $promoRemise > 0): ?>
        <div style="display:flex;justify-content:space-between;padding:8px 0;font-size:13px;color:#059669;border-bottom:1px solid #F3F4F6">
          <span><i class="bi bi-gift-fill" style="font-size:10px;margin-right:4px"></i><?= e($remisePromoLabel) ?></span>
          <span style="font-weight:600">−<?= number_format((int)$promoRemise, 0, ',', ' ') ?> CDF</span>
        </div>
        <?php endif; ?>
        <div style="display:flex;justify-content:space-between;padding:14px 16px;background:linear-gradient(135deg,#0F172A,#1E3A2F);border-radius:10px;margin-top:8px">
          <span style="font-family:var(--font-display);font-size:15px;font-weight:800;color:white">TOTAL À PAYER</span>
          <span style="font-family:var(--font-display);font-size:20px;font-weight:900;color:#FBBF24"><?= number_format((int)$successMontant, 0, ',', ' ') ?> CDF</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Instructions de paiement -->
  <div style="background:#F0FDF4;border-top:2px solid #BBF7D0;padding:24px 32px">
    <div style="font-size:13px;font-weight:700;color:#065F46;margin-bottom:14px;text-transform:uppercase;letter-spacing:.5px">
      <i class="bi bi-info-circle-fill" style="margin-right:6px"></i>Instructions de paiement
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px">
      <?php if ($successMethode === 'CARTE'): ?>
      <div style="background:white;border-radius:8px;padding:14px;border:1px solid #BBF7D0;display:flex;gap:10px">
        <div style="width:28px;height:28px;background:#059669;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:white;font-weight:700;font-size:12px">1</div>
        <div style="font-size:12px;color:#374151;line-height:1.5">Demande enregistrée. Notre équipe vous contactera sous <strong>24h</strong>.</div>
      </div>
      <div style="background:white;border-radius:8px;padding:14px;border:1px solid #BBF7D0;display:flex;gap:10px">
        <div style="width:28px;height:28px;background:#059669;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:white;font-weight:700;font-size:12px">2</div>
        <div style="font-size:12px;color:#374151;line-height:1.5">Paiement sécurisé finalisé. Activation immédiate de votre plan.</div>
      </div>
      <?php else: ?>
      <div style="background:white;border-radius:8px;padding:14px;border:1px solid #BBF7D0;display:flex;gap:10px">
        <div style="width:28px;height:28px;background:#059669;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:white;font-weight:700;font-size:12px">1</div>
        <div style="font-size:12px;color:#374151;line-height:1.5">Effectuez le virement <strong><?= e($methodeName) ?></strong> de <strong><?= number_format((int)$successMontant, 0, ',', ' ') ?> CDF</strong>.</div>
      </div>
      <div style="background:white;border-radius:8px;padding:14px;border:1px solid #BBF7D0;display:flex;gap:10px">
        <div style="width:28px;height:28px;background:#059669;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:white;font-weight:700;font-size:12px">2</div>
        <div style="font-size:12px;color:#374151;line-height:1.5">Envoyez la capture à <a href="mailto:paiement@reussiteplus.cd" style="color:#059669;font-weight:600">paiement@reussiteplus.cd</a> avec la réf. <strong><?= e($successRef) ?></strong>.</div>
      </div>
      <div style="background:white;border-radius:8px;padding:14px;border:1px solid #BBF7D0;display:flex;gap:10px">
        <div style="width:28px;height:28px;background:#059669;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:white;font-weight:700;font-size:12px">3</div>
        <div style="font-size:12px;color:#374151;line-height:1.5">Activation sous <strong>24h</strong> après vérification. Notification par email.</div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Pied de facture -->
  <div style="background:#F9FAFB;border-top:1px solid #E5E7EB;padding:18px 32px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
    <div style="font-size:11px;color:#9CA3AF;line-height:1.7">
      <strong style="color:#374151">RÉUSSITE+</strong> — Plateforme d'éducation nationale RDC<br>
      Ce document est une facture pro forma. Le service sera activé après confirmation du paiement.
    </div>
    <div style="text-align:right;font-size:11px;color:#9CA3AF">
      <div><?= e($successNumFacture) ?> · <?= $dateEmission ?></div>
      <div style="margin-top:2px">reussiteplus.cd</div>
    </div>
  </div>

</div><!-- /invoice -->

<!-- Boutons navigation -->
<div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-bottom:32px" class="no-print">
  <a href="/reussiteplus/dashboard.php" class="btn btn-primary">
    <i class="bi bi-house"></i> Tableau de bord
  </a>
  <a href="/reussiteplus/notifications.php" class="btn btn-ghost">
    <i class="bi bi-bell"></i> Mes notifications
  </a>
  <a href="/reussiteplus/abonnement.php" class="btn btn-ghost">
    <i class="bi bi-credit-card"></i> Mon abonnement
  </a>
</div>

<script>
function downloadInvoice() {
  const inv = document.getElementById('invoice').outerHTML;
  const win = window.open('', '_blank', 'width=900,height=750');
  win.document.write(`<!DOCTYPE html><html lang="fr"><head>
    <meta charset="UTF-8">
    <title>Facture <?= e($successNumFacture) ?> — RÉUSSITE+</title>
    <link rel="stylesheet" href="/reussiteplus/assets/css/bootstrap-icons.css?v=2">
    <link rel="stylesheet" href="/reussiteplus/assets/css/fonts.css">
    <style>
      *{box-sizing:border-box;margin:0;padding:0}
      body{font-family:'Poppins','Arial',sans-serif;background:#F8FAFC;padding:20px}
      @media print{body{padding:0;background:white}button{display:none!important}@page{size:A4;margin:8mm}}
      :root{--primary:#059669;--primary-light:#34D399;--font-display:'Poppins','Arial',sans-serif}
    </style>
  </head><body>${inv}</body></html>`);
  win.document.close();
  win.focus();
  setTimeout(() => win.print(), 700);
}
</script>

<style>
@media print {
  .no-print { display: none !important; }
  .sidebar, .topbar, .main-content > *:not(main), header, nav { display: none !important; }
  .page-content { padding: 0 !important; }
  #invoice { box-shadow: none !important; border-radius: 0 !important; }
  body, .main-content { background: white !important; }
}
</style>

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
          <i class="bi bi-wallet2" style="color:var(--primary)"></i>
          M&eacute;thode de paiement <span style="color:var(--rouge)">*</span>
        </label>
        <div class="pay-method-grid">
          <?php foreach (METHODES_PAIEMENT as $mKey => $m): ?>
          <label class="pay-method-label">
            <input type="radio" name="methode" value="<?= e($mKey) ?>"
                   onchange="togglePayFields(this.value)" required
                   onchange="recalc()">
            <div class="pay-method-icon">
              <i class="<?= e($m['icone']) ?>"></i>
            </div>
            <div>
              <div class="pay-method-nom"><?= e($m['nom']) ?></div>
              <div class="pay-method-num"><?= ($m['type'] ?? 'mobile') === 'carte' ? e($m['numero']) : 'N&deg; RÉUSSITE+&nbsp;: ' . e($m['numero']) ?></div>
            </div>
            <div class="pay-method-check"></div>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- T&eacute;l&eacute;phone (Mobile Money) -->
      <div class="form-group" id="field-telephone">
        <label class="form-label">
          <i class="bi bi-telephone" style="color:var(--primary)"></i>
          Votre num&eacute;ro Mobile Money <span style="color:var(--rouge)">*</span>
        </label>
        <input class="form-control" type="tel" name="telephone"
               placeholder="+243 8X XXX XXXX"
               value="<?= e($_POST['telephone'] ?? '') ?>"
               pattern="[+0-9\s]{10,15}">
        <div style="font-size:11px;color:var(--gris-500);margin-top:4px">
          Num&eacute;ro utilis&eacute; pour effectuer le virement Mobile Money
        </div>
      </div>

      <!-- Champs carte bancaire -->
      <div id="field-carte" style="display:none">
        <div class="form-group">
          <label class="form-label"><i class="bi bi-credit-card-2-front" style="color:var(--primary)"></i> Num&eacute;ro de carte <span style="color:var(--rouge)">*</span></label>
          <input class="form-control" type="text" name="card_number" id="card_number"
                 placeholder="1234 5678 9012 3456"
                 maxlength="19"
                 value="<?= e($_POST['card_number'] ?? '') ?>"
                 oninput="formatCardNum(this)">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label"><i class="bi bi-calendar3" style="color:var(--primary)"></i> Expiration</label>
            <input class="form-control" type="text" name="card_expiry" id="card_expiry"
                   placeholder="MM/AA" maxlength="5"
                   value="<?= e($_POST['card_expiry'] ?? '') ?>"
                   oninput="formatExpiry(this)">
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label"><i class="bi bi-lock" style="color:var(--primary)"></i> CVC</label>
            <input class="form-control" type="text" name="card_cvc" id="card_cvc"
                   placeholder="123" maxlength="4"
                   value="<?= e($_POST['card_cvc'] ?? '') ?>">
          </div>
        </div>
        <div style="background:#FFFBEB;border:1px solid #FDE68A;border-radius:8px;padding:10px 14px;font-size:12px;color:#92400E;margin-top:12px">
          <i class="bi bi-info-circle"></i> Paiement par carte trait&eacute; manuellement dans les 24h.
          Vos donn&eacute;es de carte ne sont pas stock&eacute;es sur nos serveurs.
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

function togglePayFields(methode) {
    const isCarte = methode === 'CARTE';
    const telField  = document.getElementById('field-telephone');
    const carteField = document.getElementById('field-carte');
    telField.style.display  = isCarte ? 'none' : '';
    carteField.style.display = isCarte ? '' : 'none';
    // Gérer le required
    const telInput = telField.querySelector('input');
    if (telInput) telInput.required = !isCarte;
}

function formatCardNum(input) {
    let v = input.value.replace(/\D/g, '').slice(0, 16);
    input.value = v.replace(/(.{4})/g, '$1 ').trim();
}

function formatExpiry(input) {
    let v = input.value.replace(/\D/g, '').slice(0, 4);
    if (v.length >= 3) v = v.slice(0, 2) + '/' + v.slice(2);
    input.value = v;
}

// Sélection initiale si retour formulaire
(function() {
    const checked = document.querySelector('input[name="methode"]:checked');
    if (checked) togglePayFields(checked.value);
})();
</script>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
