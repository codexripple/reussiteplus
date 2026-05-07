<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Choisir un plan';
$pageActive = 'abonnement';
$user = require_login();

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

// SVG inline par plan — icônes distinctives et reconnaissables
$planSvgs = [
  'GRATUIT' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 12V22H4V12"/><path d="M22 7H2v5h20V7z"/><path d="M12 22V7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/></svg>',
  'BASIQUE' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>',
  'PREMIUM' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 4l4.5 14h11L22 4l-6.5 6.5L12 4l-3.5 6.5L2 4z"/><line x1="5.5" y1="18" x2="18.5" y2="18"/></svg>',
  'ECOLE'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>',
];

// SVG utilitaires réutilisables
$svgCheck   = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
$svgArrow   = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>';
$svgRefresh = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>';
$svgPhone   = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.41 2 2 0 0 1 3.6 1.21h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L7.91 8.81a16 16 0 0 0 6.29 6.29l.95-.95a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>';
$svgChevron = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>';

include __DIR__ . '/includes/header_app.php';
?>

<style>
.tarif-hero{text-align:center;padding:36px 20px 28px;background:linear-gradient(160deg,#0D1117 0%,#003D2E 100%);border-radius:var(--radius-xl);margin-bottom:28px;position:relative;overflow:hidden}
.tarif-hero::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse 70% 60% at 50% 0%,rgba(0,122,94,.45) 0%,transparent 65%);pointer-events:none}
.tarif-hero-inner{position:relative}
.tarif-hero h1{font-family:var(--font-display);font-size:clamp(22px,4vw,32px);font-weight:800;color:#fff;line-height:1.2;margin-bottom:8px}
.tarif-hero h1 span{color:#FBBF24}
.tarif-hero p{font-size:14px;color:rgba(255,255,255,.6);max-width:440px;margin:0 auto}
.current-plan-card{background:var(--blanc);border:1px solid var(--gris-200);border-radius:var(--radius-lg);padding:20px 24px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:28px}
.current-plan-left{display:flex;align-items:center;gap:14px}
.plan-icon-wrap{width:52px;height:52px;border-radius:14px;flex-shrink:0;display:flex;align-items:center;justify-content:center}
.current-plan-label{font-size:11px;color:var(--gris-500);text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px}
.current-plan-name{font-family:var(--font-display);font-size:20px;font-weight:800;color:var(--gris-900);line-height:1.1}
.current-plan-meta{font-size:13px;color:var(--gris-600);margin-top:2px}
.referral-box{display:flex;flex-direction:column;align-items:flex-end;gap:6px}
.referral-code{font-family:var(--font-mono);font-size:15px;font-weight:700;background:var(--gris-100);padding:7px 18px;border-radius:var(--radius);cursor:pointer;border:1px solid var(--gris-200);transition:var(--transition);display:flex;align-items:center;gap:8px}
.referral-code:hover{border-color:var(--primary);background:var(--primary-subtle)}
.referral-label{font-size:11px;color:var(--gris-500)}
.tarif-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-bottom:32px}
@media(max-width:800px){.tarif-grid{grid-template-columns:1fr}}
@media(min-width:600px) and (max-width:800px){.tarif-grid{grid-template-columns:repeat(2,1fr)}}
.tarif-card{background:var(--blanc);border:2px solid var(--gris-200);border-radius:20px;display:flex;flex-direction:column;transition:all .2s;position:relative;overflow:hidden}
.tarif-card:hover{box-shadow:var(--shadow-lg);transform:translateY(-4px)}
.tarif-card.is-active{border-color:var(--primary)}
.tarif-card.is-popular{border-color:var(--gold);box-shadow:0 4px 24px rgba(201,151,42,.15)}
.tarif-badge{position:absolute;top:-1px;left:50%;transform:translateX(-50%);padding:4px 16px;border-radius:0 0 14px 14px;font-size:11px;font-weight:700;white-space:nowrap;display:flex;align-items:center;gap:5px}
.tarif-badge.active{background:var(--primary);color:#fff}
.tarif-badge.popular{background:var(--gold);color:#fff}
.tarif-card-head{padding:28px 24px 20px;text-align:center}
.tarif-card-icon{width:60px;height:60px;border-radius:16px;display:flex;align-items:center;justify-content:center;margin:0 auto 14px}
.tarif-card-name{font-family:var(--font-display);font-size:20px;font-weight:800;color:var(--gris-900);margin-bottom:12px}
.tarif-card-price{font-family:var(--font-display);font-size:32px;font-weight:900;line-height:1}
.tarif-card-period{font-size:12px;color:var(--gris-400);margin-top:4px}
.tarif-card-features{padding:4px 24px 16px;flex:1}
.tarif-feat{display:flex;align-items:flex-start;gap:10px;font-size:13px;color:var(--gris-700);padding:8px 0;border-bottom:1px solid var(--gris-100)}
.tarif-feat:last-child{border-bottom:none}
.tarif-card-cta{padding:8px 24px 24px}
.tarif-compare{background:var(--blanc);border:1px solid var(--gris-200);border-radius:var(--radius-lg);overflow:hidden;margin-bottom:32px}
.tarif-compare-header{padding:20px 24px 0;font-family:var(--font-display);font-size:16px;font-weight:700;display:flex;align-items:center;gap:8px;margin-bottom:4px}
.table-wrap{overflow-x:auto;padding:0 0 4px}
.tarif-compare table{width:100%;border-collapse:collapse}
.tarif-compare th{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--gris-500);padding:10px 16px;text-align:center;border-bottom:2px solid var(--gris-200)}
.tarif-compare th:first-child{text-align:left}
.tarif-compare td{padding:11px 16px;font-size:13px;color:var(--gris-700);border-bottom:1px solid var(--gris-100);text-align:center}
.tarif-compare td:first-child{text-align:left;font-weight:500}
.tarif-compare tr:last-child td{border-bottom:none}
.pay-grid{display:flex;flex-wrap:wrap;justify-content:center;gap:14px}
.pay-chip{display:flex;align-items:center;gap:10px;background:var(--gris-50);border:1px solid var(--gris-200);padding:12px 20px;border-radius:var(--radius);transition:var(--transition)}
.pay-chip:hover{border-color:var(--primary);background:var(--primary-subtle)}
.pay-chip-dot{width:36px;height:36px;border-radius:10px;flex-shrink:0;display:flex;align-items:center;justify-content:center}

/* Layout 2 colonnes */
.tarif-layout{display:grid;grid-template-columns:1fr 300px;gap:28px;align-items:start;}
.tarif-main{min-width:0;}
.tarif-sidebar{position:sticky;top:84px;display:flex;flex-direction:column;gap:16px;}
.tsb-card{background:var(--blanc);border:1px solid var(--gris-200);border-radius:var(--radius-lg);padding:20px;overflow:hidden;}
.tsb-card-head{font-family:var(--font-display);font-size:13px;font-weight:700;color:var(--gris-500);text-transform:uppercase;letter-spacing:.5px;margin-bottom:14px;display:flex;align-items:center;gap:6px;}
.tsb-plan-badge{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:var(--radius);font-family:var(--font-display);font-size:14px;font-weight:800;width:100%;margin-bottom:10px;}
.tsb-trust{display:flex;flex-direction:column;gap:9px;}
.tsb-trust-item{display:flex;align-items:center;gap:10px;font-size:13px;color:var(--gris-700);}
.tsb-trust-item svg{width:16px;height:16px;flex-shrink:0;}
.tsb-wa{display:flex;align-items:center;gap:10px;background:#F0FDF4;border:1px solid #BBF7D0;border-radius:var(--radius);padding:12px 14px;text-decoration:none;transition:var(--transition);}
.tsb-wa:hover{background:#DCFCE7;border-color:#86EFAC;}
.tsb-wa-icon{width:36px;height:36px;background:#25D366;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.tsb-wa-icon svg{width:18px;height:18px;stroke:#fff;}
.tsb-wa-label{font-size:13px;font-weight:700;color:#166534;}
.tsb-wa-sub{font-size:11px;color:#4ade80;}
@media(max-width:900px){.tarif-layout{grid-template-columns:1fr;}.tarif-sidebar{position:static;}}

/* FAQ */
.faq-list{display:flex;flex-direction:column;gap:10px}
.faq-item{background:var(--blanc);border:1px solid var(--gris-200);border-radius:var(--radius-lg);overflow:hidden;cursor:pointer;transition:border-color var(--transition)}
.faq-item:hover{border-color:var(--primary)}
.faq-question{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 20px;font-size:14px;font-weight:600;color:var(--gris-900);user-select:none}
.faq-answer{display:none;padding:0 20px 16px;font-size:13px;color:var(--gris-600);line-height:1.7;border-top:1px solid var(--gris-100)}
.faq-item.open .faq-answer{display:block}
.faq-item.open .faq-chevron{transform:rotate(180deg)}
.faq-item.open{border-color:var(--primary)}
</style>

<div style="max-width:1200px;margin:0 auto">

  <!-- Hero -->
  <div class="tarif-hero">
    <div class="tarif-hero-inner">
      <h1>Choisissez votre <span>plan</span></h1>
      <p>Des formules adaptées à chaque étape de votre parcours scolaire. Changez ou annulez à tout moment.</p>
    </div>
  </div>

  <!-- Plan actuel -->
  <?php $planActif = PLANS[$user['plan']]; ?>
  <div class="current-plan-card">
    <div class="current-plan-left">
      <div class="plan-icon-wrap" style="background:<?= $planActif['couleur'] ?>18">
        <span style="width:24px;height:24px;display:flex;align-items:center;justify-content:center;color:<?= $planActif['couleur'] ?>">
          <?= str_replace('<svg ', '<svg width="24" height="24" ', $planSvgs[$user['plan']] ?? $planSvgs['GRATUIT']) ?>
        </span>
      </div>
      <div>
        <div class="current-plan-label">Votre abonnement actuel</div>
        <div class="current-plan-name"><?= e($planActif['nom']) ?></div>
        <?php if ($user['plan_expire_at'] && $user['plan'] !== 'GRATUIT'):
          $jours = max(0, (int)((strtotime($user['plan_expire_at']) - time()) / 86400));
        ?>
          <div class="current-plan-meta">Expire dans <?= $jours ?> jours · <?= date('d/m/Y', strtotime($user['plan_expire_at'])) ?></div>
        <?php elseif ($user['plan'] === 'GRATUIT'): ?>
          <div class="current-plan-meta"><?= $user['examens_mois']??0 ?>/<?= FREE_EXAMS_PER_MONTH ?> examens utilisés ce mois</div>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($user['plan'] !== 'GRATUIT'): ?>
    <div class="referral-box">
      <div class="referral-label">Code de parrainage · gagnez 1 mois</div>
      <div class="referral-code" onclick="copyRef(this)" title="Copier le lien de parrainage">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
        <?= e($user['referral_code'] ?? 'N/A') ?>
      </div>
      <a href="/reussiteplus/abonnement.php" class="btn btn-ghost btn-sm" style="margin-top:2px">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        Historique
      </a>
    </div>
    <?php endif; ?>
  </div>

  <!-- Layout 2 colonnes : contenu principal + sidebar -->
  <div class="tarif-layout">
  <div class="tarif-main">

  <!-- Grille des 3 plans -->
  <div class="tarif-grid">
    <?php foreach (['BASIQUE','PREMIUM','ECOLE'] as $planKey):
      $plan     = PLANS[$planKey];
      $isActive = $user['plan'] === $planKey && plan_actif($user);
      $isPopular = $plan['populaire'] ?? false;
      $couleur  = $plan['couleur'];
    ?>
    <div class="tarif-card <?= $isActive ? 'is-active' : ($isPopular ? 'is-popular' : '') ?>">

      <?php if ($isActive): ?>
        <div class="tarif-badge active">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          Plan actif
        </div>
      <?php elseif ($isPopular): ?>
        <div class="tarif-badge popular">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="#fff" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
          Recommandé
        </div>
      <?php endif; ?>

      <div class="tarif-card-head">
        <div class="tarif-card-icon" style="background:<?= $couleur ?>18;color:<?= $couleur ?>">
          <?= str_replace('<svg ', '<svg width="28" height="28" ', $planSvgs[$planKey] ?? $planSvgs['BASIQUE']) ?>
        </div>
        <div class="tarif-card-name"><?= e($plan['nom']) ?></div>
        <?php if ($plan['prix'] === 0): ?>
          <div class="tarif-card-price" style="color:var(--gris-600)">Gratuit</div>
        <?php else: ?>
          <div class="tarif-card-price" style="color:<?= $couleur ?>"><?= number_format($plan['prix'],0,',',' ') ?> <span style="font-size:15px;font-weight:600">CDF</span></div>
          <div class="tarif-card-period">par mois · sans engagement</div>
        <?php endif; ?>
      </div>

      <div class="tarif-card-features">
        <?php
        $feats = [
          [$plan['examens_mois'] < 0 ? 'Examens <strong>illimités</strong>' : "<strong>{$plan['examens_mois']}</strong> examens par mois", true],
          [$plan['questions'] < 0 ? 'Questions <strong>illimitées</strong>' : "<strong>{$plan['questions']}</strong> questions par examen", true],
          ['Banque de +1 000 questions officielles', true],
          ['Archives ENAFEP, TENASOSP, État', (bool)$plan['archives']],
          ['Corrigés et explications détaillées', (bool)$plan['corrige']],
          ['Suivi de progression et statistiques', true],
          ['Assistant IA de révision personnalisé', (bool)$plan['ia']],
        ];
        if (isset($plan['eleves_max'])) {
          $feats[] = ["Jusqu'à <strong>{$plan['eleves_max']} élèves</strong>", true];
        }
        foreach ($feats as [$label, $ok]): ?>
        <div class="tarif-feat">
          <?php if ($ok): ?>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px"><polyline points="20 6 9 17 4 12"/></svg>
          <?php else: ?>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--gris-300)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          <?php endif; ?>
          <span><?= $label ?></span>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="tarif-card-cta">
        <?php if ($isActive): ?>
          <a href="/reussiteplus/paiement.php?plan=<?= $planKey ?>" class="btn btn-ghost btn-full">
            <?= $svgRefresh ?> Renouveler
          </a>
        <?php elseif ($planKey === 'ECOLE'): ?>
          <a href="/reussiteplus/paiement.php?plan=ECOLE" class="btn btn-primary btn-full">
            <?= $svgArrow ?> Activer le plan École
          </a>
          <div style="display:flex;gap:8px;margin-top:8px">
            <a href="https://wa.me/<?= CONTACT_MPESA ?>" target="_blank" rel="noopener"
               style="flex:1;display:flex;align-items:center;justify-content:center;gap:5px;padding:7px;background:#F0FDF4;border:1px solid #BBF7D0;border-radius:8px;font-size:11px;font-weight:700;color:#059669;text-decoration:none;transition:.15s"
               onmouseover="this.style.background='#DCFCE7'" onmouseout="this.style.background='#F0FDF4'">
              <span style="color:#059669"><?= $svgPhone ?></span> +243 83 150 8853
            </a>
            <a href="https://wa.me/<?= CONTACT_ORANGE ?>" target="_blank" rel="noopener"
               style="flex:1;display:flex;align-items:center;justify-content:center;gap:5px;padding:7px;background:#FFF7ED;border:1px solid #FED7AA;border-radius:8px;font-size:11px;font-weight:700;color:#D97706;text-decoration:none;transition:.15s"
               onmouseover="this.style.background='#FFEDD5'" onmouseout="this.style.background='#FFF7ED'">
              <span style="color:#D97706"><?= $svgPhone ?></span> +243 84 020 4331
            </a>
          </div>
        <?php else: ?>
          <a href="/reussiteplus/paiement.php?plan=<?= $planKey ?>" class="btn btn-full" style="background:<?= $isPopular ? 'var(--gold)' : $couleur ?>;color:#fff;font-weight:700;border:none;padding:13px;border-radius:10px;display:flex;align-items:center;justify-content:center;gap:8px;cursor:pointer;transition:opacity .15s;text-decoration:none" onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'">
            <?= $svgArrow ?>
            <?= $isPopular ? 'Commencer avec Premium' : 'Choisir ' . e($plan['nom']) ?>
          </a>
          <!-- Code promo -->
          <div class="promo-toggle" onclick="togglePromo('<?= $planKey ?>')" style="margin-top:10px;text-align:center;font-size:12px;color:var(--primary);cursor:pointer;font-weight:600;display:flex;align-items:center;justify-content:center;gap:4px">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
            J'ai un code promo
          </div>
          <div id="promo-<?= $planKey ?>" style="display:none;margin-top:8px">
            <div style="display:flex;gap:6px">
              <input type="text" id="promo-input-<?= $planKey ?>" placeholder="Ex: ETUDE50" maxlength="20"
                style="flex:1;padding:8px 12px;border:1px solid var(--gris-200);border-radius:8px;font-size:13px;outline:none;font-family:var(--font-mono);text-transform:uppercase"
                oninput="this.value=this.value.toUpperCase()"
                onkeydown="if(event.key==='Enter') appliquerPromo('<?= $planKey ?>')">
              <button onclick="appliquerPromo('<?= $planKey ?>')"
                style="padding:8px 12px;background:var(--primary);color:#fff;border:none;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;white-space:nowrap">
                Appliquer
              </button>
            </div>
            <div id="promo-result-<?= $planKey ?>" style="margin-top:6px;font-size:12px;min-height:18px"></div>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Tableau comparatif -->
  <div class="tarif-compare">
    <div class="tarif-compare-header">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M3 15h18M9 3v18"/></svg>
      Comparaison détaillée
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Fonctionnalité</th>
            <th style="color:var(--gris-600)">Gratuit</th>
            <th style="color:#1E5FAD">Basique</th>
            <th style="color:var(--gold)"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var(--gold)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px"><path d="M2 4l4.5 14h11L22 4l-6.5 6.5L12 4l-3.5 6.5L2 4z"/></svg> Premium</th>
            <th style="color:var(--primary)">École</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $rows = [
            ['Examens par mois',              '5',       '30',         '∞',          '∞'],
            ['Questions par examen',           '20',      '200',        '∞',          '∞'],
            ['Banque de questions officielle', 'ok',      'ok',         'ok',         'ok'],
            ['Archives ENAFEP/TENASOSP/État',  'no',      'ok',         'ok',         'ok'],
            ['Corrigés et explications',       'no',      'ok',         'ok',         'ok'],
            ['Assistant IA personnalisé',      'no',      'no',         'ok',         'ok'],
            ['Suivi de progression',           'ok',      'ok',         'ok',         'ok'],
            ['Gestion multi-élèves',           'no',      'no',         'no',         '50 élèves'],
            ['Prix mensuel','Gratuit','5 000 CDF','10 000 CDF','50 000 CDF'],
          ];
          foreach ($rows as [$feat,$g,$b,$p,$e]): ?>
          <tr>
            <td><?= $feat ?></td>
            <?php foreach ([$g,$b,$p,$e] as $v): ?>
            <td>
              <?php if ($v==='ok'): ?><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
              <?php elseif($v==='no'): ?><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--gris-300)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              <?php else: ?><strong style="color:var(--gris-900)"><?= $v ?></strong><?php endif; ?>
            </td>
            <?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Garantie -->
  <div style="background:linear-gradient(135deg,var(--primary-subtle),#EEF4FD);border:1px solid var(--gris-200);border-radius:var(--radius-xl);padding:28px 32px;display:flex;align-items:center;gap:24px;flex-wrap:wrap;margin-bottom:24px">
    <div style="width:64px;height:64px;background:#fff;border-radius:16px;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:var(--shadow-sm)">
      <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
    </div>
    <div style="flex:1;min-width:200px">
      <div style="font-family:var(--font-display);font-size:17px;font-weight:800;color:var(--gris-900);margin-bottom:4px">Satisfait ou remboursé — 7 jours</div>
      <div style="font-size:13px;color:var(--gris-600);line-height:1.6">Si vous n'êtes pas satisfait dans les 7 premiers jours, nous vous remboursons intégralement. Aucune question posée. Votre confiance est notre priorité.</div>
    </div>
    <a href="/reussiteplus/contact.php" class="btn btn-ghost btn-sm" style="flex-shrink:0">Contacter le support</a>
  </div>

  <!-- FAQ -->
  <div style="margin-bottom:32px">
    <div style="font-family:var(--font-display);font-size:20px;font-weight:800;color:var(--gris-900);margin-bottom:20px;display:flex;align-items:center;gap:10px">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      Questions fréquentes
    </div>
    <div class="faq-list">
      <?php
      $faqs = [
        ['Comment fonctionne le paiement ?',
         'Le paiement se fait via mobile money (M-Pesa, Airtel Money, Orange Money). Après confirmation de votre paiement, votre compte est activé sous 24h maximum. Vous recevez une notification dès que c\'est fait.'],
        ['Puis-je annuler à tout moment ?',
         'Oui, sans engagement ni pénalité. Si vous annulez avant la prochaine échéance, vous gardez l\'accès jusqu\'à la fin de la période payée. Aucun renouvellement automatique sans votre accord.'],
        ['Quelle est la différence entre Basique et Premium ?',
         'Le plan Basique donne accès aux archives et corrigés mais limite les examens à 30/mois. Le plan Premium est illimité et inclut l\'assistant IA de révision personnalisé, idéal pour se préparer intensivement.'],
        ['Le plan École inclut combien d\'élèves ?',
         'Jusqu\'à 50 élèves par défaut. Pour des classes plus grandes, contactez-nous directement via WhatsApp pour un devis adapté.'],
        ['Mes données sont-elles sécurisées ?',
         'Oui. Vos données sont chiffrées, hébergées en Europe et ne sont jamais partagées avec des tiers. Consultez notre politique de confidentialité pour plus de détails.'],
      ];
      foreach ($faqs as $i => [$q, $r]):
      ?>
      <div class="faq-item" onclick="toggleFaq(<?= $i ?>)">
        <div class="faq-question">
          <span><?= e($q) ?></span>
          <span class="faq-chevron" style="width:18px;height:18px;display:inline-flex;transition:transform .25s;flex-shrink:0;color:var(--gris-400)"><?= $svgChevron ?></span>
        </div>
        <div class="faq-answer" id="faq-answer-<?= $i ?>"><?= e($r) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Méthodes de paiement -->
  <div class="card" style="text-align:center;padding:28px;margin-bottom:8px">
    <div style="font-family:var(--font-display);font-size:16px;font-weight:700;margin-bottom:6px">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px;margin-right:4px"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
      Moyens de paiement acceptés
    </div>
    <div style="font-size:13px;color:var(--gris-500);margin-bottom:20px">Paiements traités en CDF · Activation sous 24h · Support réactif</div>
    <div class="pay-grid">
      <?php
      $payMeta = [
        'MPESA'        => ['#00A651', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>'],
        'AIRTEL_MONEY' => ['#E40613', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.41 2 2 0 0 1 3.6 1.21h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.81a16 16 0 0 0 6.29 6.29l.95-.95a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>'],
        'ORANGE_MONEY' => ['#FF6600', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>'],
      ];
      foreach (METHODES_PAIEMENT as $key => $m):
        [$color, $svg] = $payMeta[$key] ?? ['#6B7280', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2"/></svg>'];
      ?>
      <div class="pay-chip">
        <div class="pay-chip-dot" style="background:<?= $color ?>18;color:<?= $color ?>">
          <?= str_replace('<svg ', '<svg width="18" height="18" ', $svg) ?>
        </div>
        <div style="text-align:left">
          <div style="font-size:13px;font-weight:600"><?= e($m['nom']) ?></div>
          <div style="font-size:11px;color:var(--gris-500)"><?= e($m['numero']) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="margin-top:20px;font-size:12px;color:var(--gris-400);display:flex;align-items:center;justify-content:center;gap:16px;flex-wrap:wrap">
      <a href="mailto:support@reussiteplus.cd" style="color:var(--primary);display:flex;align-items:center;gap:5px;text-decoration:none;font-weight:500">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        support@reussiteplus.cd
      </a>
      <span style="color:var(--gris-200)">·</span>
      <a href="https://wa.me/243977329184" target="_blank" rel="noopener" style="color:#25D366;display:flex;align-items:center;gap:5px;text-decoration:none;font-weight:500">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        WhatsApp
      </a>
    </div>
  </div>

  </div><!-- /tarif-main -->

  <!-- ═══ SIDEBAR DROITE ═══════════════════════════════════ -->
  <aside class="tarif-sidebar">

    <!-- Plan actuel -->
    <div class="tsb-card">
      <div class="tsb-card-head">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>
        Votre abonnement
      </div>
      <?php
        $pc = $planActif['couleur'];
        $pn = $planActif['nom'];
        $pi = $planSvgs[$user['plan']] ?? $planSvgs['GRATUIT'];
      ?>
      <div class="tsb-plan-badge" style="background:<?= $pc ?>18;color:<?= $pc ?>">
        <span style="width:28px;height:28px;display:flex;align-items:center;justify-content:center">
          <?= str_replace('<svg ', '<svg width="20" height="20" ', $pi) ?>
        </span>
        <?= e($pn) ?>
        <?php if (plan_actif($user) && $user['plan'] !== 'GRATUIT'): ?>
          <span style="margin-left:auto;font-size:10px;background:<?= $pc ?>;color:#fff;padding:2px 8px;border-radius:10px">Actif</span>
        <?php endif; ?>
      </div>
      <?php if ($user['plan'] === 'GRATUIT'): ?>
        <div style="font-size:12px;color:var(--gris-500);margin-bottom:14px;display:flex;align-items:center;gap:6px">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          <?= $user['examens_mois'] ?? 0 ?>/<?= FREE_EXAMS_PER_MONTH ?> examens ce mois
        </div>
        <a href="/reussiteplus/paiement.php?plan=PREMIUM" style="display:flex;align-items:center;justify-content:center;gap:8px;background:linear-gradient(135deg,var(--gold),#F59E0B);color:#fff;border-radius:10px;padding:12px 16px;font-family:var(--font-display);font-size:13px;font-weight:700;text-decoration:none;transition:all .2s;box-shadow:0 4px 14px rgba(201,151,42,.3)" onmouseover="this.style.transform='translateY(-1px)';this.style.boxShadow='0 6px 20px rgba(201,151,42,.4)'" onmouseout="this.style.transform='';this.style.boxShadow='0 4px 14px rgba(201,151,42,.3)'">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="#fff" stroke="#fff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
          Passer à Premium
        </a>
      <?php elseif ($user['plan_expire_at']): ?>
        <?php $j = max(0, (int)((strtotime($user['plan_expire_at']) - time()) / 86400)); ?>
        <div style="font-size:12px;color:<?= $j < 7 ? 'var(--rouge)' : 'var(--gris-500)' ?>;margin-bottom:12px;display:flex;align-items:center;gap:5px">
          <?php if ($j < 7): ?>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
          <?php endif; ?>
          Expire dans <strong><?= $j ?> jours</strong>
        </div>
        <a href="/reussiteplus/paiement.php?plan=<?= $user['plan'] ?>" class="btn btn-ghost btn-full btn-sm" style="justify-content:center">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
          Renouveler le plan
        </a>
      <?php endif; ?>
    </div>

    <!-- Plan recommandé -->
    <?php if ($user['plan'] !== 'PREMIUM' && $user['plan'] !== 'ECOLE'): ?>
    <div class="tsb-card" style="border-color:var(--gold);background:linear-gradient(135deg,#FFFBF0,#FFF7E0)">
      <div class="tsb-card-head" style="color:var(--gold-dark)">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--gold)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        Recommandé pour vous
      </div>
      <div style="font-family:var(--font-display);font-size:16px;font-weight:800;color:var(--gris-900);margin-bottom:4px">Plan Premium</div>
      <div style="font-size:22px;font-weight:900;color:var(--gold);font-family:var(--font-display);margin-bottom:10px">10 000 <span style="font-size:13px;font-weight:500;color:var(--gris-500)">CDF/mois</span></div>
      <div style="display:flex;flex-direction:column;gap:7px;margin-bottom:14px">
        <?php foreach(['Examens illimités','Toutes les archives','Corrigés PDF','Assistant IA'] as $f): ?>
        <div style="display:flex;align-items:center;gap:8px;font-size:12px;color:var(--gris-700)">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          <?= $f ?>
        </div>
        <?php endforeach; ?>
      </div>
      <a href="/reussiteplus/paiement.php?plan=PREMIUM" class="btn btn-full btn-sm" style="background:var(--gold);color:#fff;border:none;justify-content:center">
        Choisir Premium →
      </a>
    </div>
    <?php endif; ?>

    <!-- Confiance & sécurité -->
    <div class="tsb-card">
      <div class="tsb-card-head">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        Pourquoi nous faire confiance
      </div>
      <div class="tsb-trust">
        <div class="tsb-trust-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
          <span>Remboursé sous 7 jours</span>
        </div>
        <div class="tsb-trust-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="var(--bleu)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
          <span>Paiement mobile money sécurisé</span>
        </div>
        <div class="tsb-trust-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="var(--gold)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          <span>Activation sous 24h</span>
        </div>
        <div class="tsb-trust-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="var(--rouge)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78z"/></svg>
          <span>Sans engagement, annulable</span>
        </div>
      </div>
    </div>

    <!-- WhatsApp -->
    <a href="https://wa.me/243977329184" target="_blank" rel="noopener" class="tsb-wa">
      <div class="tsb-wa-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
        </svg>
      </div>
      <div>
        <div class="tsb-wa-label">Besoin d'aide ?</div>
        <div class="tsb-wa-sub">Répondons sur WhatsApp</div>
      </div>
      <svg style="margin-left:auto;width:14px;height:14px;stroke:#166534;opacity:.5" viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
    </a>

  </aside>

  </div><!-- /tarif-layout -->

</div>

<script>
// ── Code parrainage ───────────────────────────────────────
function copyRef(el) {
  const code = '<?= e($user['referral_code'] ?? '') ?>';
  const url = window.location.origin + '/reussiteplus/inscription.php?ref=' + code;
  if (navigator.clipboard) {
    navigator.clipboard.writeText(url).then(() => {
      const orig = el.innerHTML;
      el.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Copié !';
      setTimeout(() => { el.innerHTML = orig; }, 2000);
    });
  }
}

// ── Code promo ────────────────────────────────────────────
function togglePromo(plan) {
  const box = document.getElementById('promo-' + plan);
  box.style.display = box.style.display === 'none' ? 'block' : 'none';
  if (box.style.display === 'block') {
    document.getElementById('promo-input-' + plan)?.focus();
  }
}

async function appliquerPromo(plan) {
  const input  = document.getElementById('promo-input-' + plan);
  const result = document.getElementById('promo-result-' + plan);
  const code   = input?.value.trim();
  if (!code) { result.innerHTML = '<span style="color:var(--rouge)">Entrez un code promo.</span>'; return; }

  result.innerHTML = '<span style="color:var(--gris-400)">Vérification...</span>';

  const fd = new FormData();
  fd.append('action', 'verifier_promo');
  fd.append('code', code);
  fd.append('plan', plan);

  try {
    const r = await fetch(window.location.href, { method: 'POST', body: fd });
    const d = await r.json();
    if (d.ok) {
      result.innerHTML = `<span style="color:var(--primary);font-weight:600">✓ Code valide — remise de <strong>${d.remise}</strong> appliquée !</span>`;
      // Mettre à jour le bouton CTA avec le code
      const btn = document.querySelector('[href*="paiement.php?plan=' + plan + '"]');
      if (btn) btn.href = '/reussiteplus/paiement.php?plan=' + plan + '&promo=' + encodeURIComponent(code);
    } else {
      result.innerHTML = `<span style="color:var(--rouge)">✗ ${d.msg}</span>`;
    }
  } catch {
    result.innerHTML = '<span style="color:var(--rouge)">Erreur réseau. Réessayez.</span>';
  }
}

// ── FAQ accordion ─────────────────────────────────────────
function toggleFaq(i) {
  const item    = document.querySelectorAll('.faq-item')[i];
  const isOpen  = item.classList.contains('open');
  document.querySelectorAll('.faq-item').forEach(el => el.classList.remove('open'));
  if (!isOpen) item.classList.add('open');
}
</script>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
