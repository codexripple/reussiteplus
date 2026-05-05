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

// Icônes Lucide par plan
$planIcons = ['GRATUIT'=>'backpack','BASIQUE'=>'zap','PREMIUM'=>'crown','ECOLE'=>'school'];

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
</style>

<div style="max-width:960px;margin:0 auto">

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
        <i data-lucide="<?= $planIcons[$user['plan']] ?? 'backpack' ?>" style="width:24px;height:24px;stroke:<?= $planActif['couleur'] ?>"></i>
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
        <i data-lucide="link-2" style="width:14px;height:14px;stroke:var(--primary)"></i>
        <?= e($user['referral_code'] ?? 'N/A') ?>
      </div>
      <a href="/reussiteplus/abonnement.php" class="btn btn-ghost btn-sm" style="margin-top:2px">
        <i data-lucide="clock" style="width:12px;height:12px;vertical-align:-2px"></i> Historique
      </a>
    </div>
    <?php endif; ?>
  </div>

  <!-- Grille des 3 plans -->
  <div class="tarif-grid">
    <?php foreach (['BASIQUE','PREMIUM','ECOLE'] as $planKey):
      $plan     = PLANS[$planKey];
      $isActive = $user['plan'] === $planKey && plan_actif($user);
      $isPopular = $plan['populaire'] ?? false;
      $couleur  = $plan['couleur'];
      $icon     = $planIcons[$planKey] ?? 'zap';
    ?>
    <div class="tarif-card <?= $isActive ? 'is-active' : ($isPopular ? 'is-popular' : '') ?>">

      <?php if ($isActive): ?>
        <div class="tarif-badge active">
          <i data-lucide="check-circle" style="width:11px;height:11px;stroke:#fff"></i> Plan actif
        </div>
      <?php elseif ($isPopular): ?>
        <div class="tarif-badge popular">
          <i data-lucide="star" style="width:11px;height:11px;stroke:#fff;fill:#fff"></i> Recommandé
        </div>
      <?php endif; ?>

      <div class="tarif-card-head">
        <div class="tarif-card-icon" style="background:<?= $couleur ?>18">
          <i data-lucide="<?= $icon ?>" style="width:28px;height:28px;stroke:<?= $couleur ?>"></i>
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
          <i data-lucide="<?= $ok ? 'check-circle' : 'x-circle' ?>" style="width:16px;height:16px;stroke:<?= $ok ? 'var(--primary)' : 'var(--gris-300)' ?>;flex-shrink:0;margin-top:1px"></i>
          <span><?= $label ?></span>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="tarif-card-cta">
        <?php if ($isActive): ?>
          <a href="/reussiteplus/paiement.php?plan=<?= $planKey ?>" class="btn btn-ghost btn-full">
            <i data-lucide="refresh-cw" style="width:13px;height:13px;vertical-align:-2px"></i> Renouveler
          </a>
        <?php elseif ($planKey === 'ECOLE'): ?>
          <a href="/reussiteplus/paiement.php?plan=ECOLE" class="btn btn-primary btn-full">
            <i data-lucide="arrow-right-circle" style="width:13px;height:13px;vertical-align:-2px"></i> Activer le plan École
          </a>
          <div style="display:flex;gap:8px;margin-top:8px">
            <a href="https://wa.me/<?= CONTACT_MPESA ?>" target="_blank" rel="noopener"
               style="flex:1;display:flex;align-items:center;justify-content:center;gap:5px;padding:7px;background:#F0FDF4;border:1px solid #BBF7D0;border-radius:8px;font-size:11px;font-weight:700;color:#059669;text-decoration:none;transition:.15s"
               onmouseover="this.style.background='#DCFCE7'" onmouseout="this.style.background='#F0FDF4'">
              <i data-lucide="message-circle" style="width:11px;height:11px;stroke:#059669"></i> +243 83 150 8853
            </a>
            <a href="https://wa.me/<?= CONTACT_ORANGE ?>" target="_blank" rel="noopener"
               style="flex:1;display:flex;align-items:center;justify-content:center;gap:5px;padding:7px;background:#FFF7ED;border:1px solid #FED7AA;border-radius:8px;font-size:11px;font-weight:700;color:#D97706;text-decoration:none;transition:.15s"
               onmouseover="this.style.background='#FFEDD5'" onmouseout="this.style.background='#FFF7ED'">
              <i data-lucide="phone" style="width:11px;height:11px;stroke:#D97706"></i> +243 84 020 4331
            </a>
          </div>
        <?php else: ?>
          <a href="/reussiteplus/paiement.php?plan=<?= $planKey ?>" class="btn btn-full" style="background:<?= $isPopular ? 'var(--gold)' : $couleur ?>;color:#fff;font-weight:700;border:none;padding:13px;border-radius:10px;display:flex;align-items:center;justify-content:center;gap:8px;cursor:pointer;transition:opacity .15s;text-decoration:none" onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'">
            <i data-lucide="arrow-right-circle" style="width:16px;height:16px;stroke:#fff"></i>
            <?= $isPopular ? 'Commencer avec Premium' : 'Choisir ' . e($plan['nom']) ?>
          </a>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Tableau comparatif -->
  <div class="tarif-compare">
    <div class="tarif-compare-header">
      <i data-lucide="table-2" style="width:18px;height:18px;stroke:var(--primary)"></i>
      Comparaison détaillée
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Fonctionnalité</th>
            <th style="color:var(--gris-600)">Gratuit</th>
            <th style="color:#1E5FAD">Basique</th>
            <th style="color:var(--gold)"><i data-lucide="crown" style="width:12px;height:12px;vertical-align:-1px;stroke:var(--gold)"></i> Premium</th>
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
              <?php if ($v==='ok'): ?><i data-lucide="check-circle" style="width:16px;height:16px;stroke:var(--primary)"></i>
              <?php elseif($v==='no'): ?><i data-lucide="x-circle" style="width:16px;height:16px;stroke:var(--gris-300)"></i>
              <?php else: ?><strong style="color:var(--gris-900)"><?= $v ?></strong><?php endif; ?>
            </td>
            <?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Méthodes de paiement -->
  <div class="card" style="text-align:center;padding:28px;margin-bottom:8px">
    <div style="font-family:var(--font-display);font-size:16px;font-weight:700;margin-bottom:6px">
      <i data-lucide="shield-check" style="width:18px;height:18px;vertical-align:-3px;stroke:var(--primary)"></i>
      Moyens de paiement acceptés
    </div>
    <div style="font-size:13px;color:var(--gris-500);margin-bottom:20px">Paiements traités en CDF · Activation sous 24h · Support réactif</div>
    <div class="pay-grid">
      <?php
      $payMeta = ['MPESA'=>['#00A651','smartphone'],'AIRTEL_MONEY'=>['#E40613','smartphone'],'ORANGE_MONEY'=>['#FF6600','smartphone']];
      foreach (METHODES_PAIEMENT as $key => $m):
        [$color,$icon] = $payMeta[$key] ?? ['#6B7280','smartphone'];
      ?>
      <div class="pay-chip">
        <div class="pay-chip-dot" style="background:<?= $color ?>18">
          <i data-lucide="<?= $icon ?>" style="width:18px;height:18px;stroke:<?= $color ?>"></i>
        </div>
        <div style="text-align:left">
          <div style="font-size:13px;font-weight:600"><?= e($m['nom']) ?></div>
          <div style="font-size:11px;color:var(--gris-500)"><?= e($m['numero']) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="margin-top:20px;font-size:12px;color:var(--gris-400)">
      <i data-lucide="headphones" style="width:13px;height:13px;vertical-align:-2px;stroke:var(--primary)"></i>
      Support : <a href="mailto:support@reussiteplus.cd" style="color:var(--primary)">support@reussiteplus.cd</a>
      &nbsp;·&nbsp;
      <i data-lucide="message-circle" style="width:13px;height:13px;vertical-align:-2px;stroke:#25D366"></i>
      <a href="https://wa.me/243977329184" target="_blank" rel="noopener" style="color:#25D366">WhatsApp</a>
    </div>
  </div>

</div>

<script>
function copyRef(el) {
  const code = '<?= e($user['referral_code'] ?? '') ?>';
  const url = window.location.origin + '/reussiteplus/inscription.php?ref=' + code;
  if (navigator.clipboard) {
    navigator.clipboard.writeText(url).then(() => {
      const orig = el.innerHTML;
      el.innerHTML = '<i data-lucide="check" style="width:14px;height:14px;stroke:var(--primary)"></i> Copié !';
      if (typeof lucide !== 'undefined') lucide.createIcons();
      setTimeout(() => { el.innerHTML = orig; if (typeof lucide !== 'undefined') lucide.createIcons(); }, 2000);
    });
  }
}
</script>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
