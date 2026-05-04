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

<style>
.tarif-wrap { max-width: 1060px; margin: 0 auto; padding: 0 16px 60px; }

/* Bannière plan actuel */
.tarif-current {
  background: linear-gradient(135deg, #EAF5F1 0%, #F0F9FF 100%);
  border: 1.5px solid var(--gris-200);
  border-radius: 16px;
  padding: 20px 28px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
  margin-bottom: 36px;
}

/* En-tête section */
.tarif-header { text-align: center; margin-bottom: 36px; }
.tarif-header h2 {
  font-family: var(--font-display);
  font-size: 28px;
  font-weight: 900;
  color: var(--noir);
  margin-bottom: 8px;
}
.tarif-header p { font-size: 15px; color: var(--gris-600); max-width: 520px; margin: 0 auto; }

/* Grille plans */
.tarif-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
  margin-bottom: 40px;
  align-items: stretch;
}
@media (max-width: 860px) { .tarif-grid { grid-template-columns: 1fr; max-width: 420px; margin-left: auto; margin-right: auto; } }

/* Carte plan */
.tarif-card {
  background: #fff;
  border: 2px solid var(--gris-200);
  border-radius: 20px;
  padding: 0;
  overflow: hidden;
  position: relative;
  display: flex;
  flex-direction: column;
  transition: transform .2s, box-shadow .2s;
}
.tarif-card:hover { transform: translateY(-4px); box-shadow: 0 12px 40px rgba(0,0,0,.12); }
.tarif-card.is-popular { border-color: var(--gold); box-shadow: 0 4px 20px rgba(201,151,42,.2); }
.tarif-card.is-active  { border-color: var(--primary); }

/* Badge */
.tarif-badge {
  position: absolute;
  top: -1px;
  left: 50%;
  transform: translateX(-50%);
  padding: 4px 16px;
  border-radius: 0 0 12px 12px;
  font-size: 11px;
  font-weight: 700;
  white-space: nowrap;
  display: flex;
  align-items: center;
  gap: 5px;
}
.tarif-badge.popular { background: var(--gold); color: #fff; }
.tarif-badge.active  { background: var(--primary); color: #fff; }

/* En-tête carte */
.tarif-card-head {
  padding: 32px 24px 20px;
  border-bottom: 1px solid var(--gris-100);
  text-align: center;
}
.tarif-card-icon {
  width: 56px; height: 56px;
  border-radius: 14px;
  display: flex; align-items: center; justify-content: center;
  font-size: 26px;
  margin: 0 auto 14px;
}
.tarif-card-name {
  font-family: var(--font-display);
  font-size: 22px;
  font-weight: 800;
  margin-bottom: 4px;
}
.tarif-card-tagline { font-size: 12px; color: var(--gris-500); margin-bottom: 16px; }
.tarif-card-price {
  font-family: var(--font-display);
  font-size: 36px;
  font-weight: 900;
  line-height: 1;
  margin-bottom: 4px;
}
.tarif-card-period { font-size: 12px; color: var(--gris-400); }

/* Features */
.tarif-card-features { padding: 20px 24px; flex: 1; }
.tarif-feat {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  font-size: 13px;
  color: var(--gris-700);
  padding: 7px 0;
  border-bottom: 1px solid var(--gris-50);
}
.tarif-feat:last-child { border-bottom: none; }
.tarif-feat .feat-icon { font-size: 14px; flex-shrink: 0; margin-top: 1px; }
.tarif-feat .feat-icon.ok  { color: var(--primary); }
.tarif-feat .feat-icon.no  { color: var(--gris-300); }
.tarif-feat .feat-label    { line-height: 1.4; }
.tarif-feat .feat-label span { display: block; font-size: 11px; color: var(--gris-400); }

/* CTA */
.tarif-card-cta { padding: 16px 24px 24px; }

/* Tableau comparatif */
.tarif-compare { margin-bottom: 40px; }
.tarif-compare table { width: 100%; border-collapse: collapse; }
.tarif-compare th {
  font-size: 12px; font-weight: 700; text-transform: uppercase;
  letter-spacing: .5px; color: var(--gris-500); padding: 10px 16px;
  text-align: center; border-bottom: 2px solid var(--gris-200);
}
.tarif-compare th:first-child { text-align: left; }
.tarif-compare td {
  padding: 11px 16px; font-size: 13px; color: var(--gris-700);
  border-bottom: 1px solid var(--gris-100); text-align: center;
}
.tarif-compare td:first-child { text-align: left; font-weight: 500; color: var(--noir); }
.tarif-compare tr:hover td { background: var(--gris-50); }
.tarif-compare .ok  { color: var(--primary); font-size: 17px; }
.tarif-compare .no  { color: var(--gris-300); font-size: 17px; }
.tarif-compare .val { font-weight: 700; color: var(--noir); }

/* Méthodes paiement */
.pay-methods-grid {
  display: flex; justify-content: center; gap: 16px; flex-wrap: wrap;
  margin-top: 16px;
}
.pay-method-chip {
  display: flex; align-items: center; gap: 10px;
  background: var(--gris-50); border: 1.5px solid var(--gris-200);
  padding: 10px 18px; border-radius: 12px;
}
.pay-method-chip i { font-size: 22px; color: var(--primary); }
</style>

<div class="tarif-wrap">

  <!-- Bannière plan actuel -->
  <div class="tarif-current">
    <div style="display:flex;align-items:center;gap:14px">
      <div style="width:48px;height:48px;border-radius:12px;background:<?= PLANS[$user['plan']]['couleur'] ?>20;display:flex;align-items:center;justify-content:center;font-size:22px;color:<?= PLANS[$user['plan']]['couleur'] ?>">
        <i class="<?= e(PLANS[$user['plan']]['icone']) ?>"></i>
      </div>
      <div>
        <div style="font-size:11px;color:var(--gris-500);text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px">Votre abonnement actuel</div>
        <div style="font-family:var(--font-display);font-size:18px;font-weight:800"><?= e(PLANS[$user['plan']]['nom']) ?></div>
        <?php if ($user['plan_expire_at'] && $user['plan'] !== 'GRATUIT'): ?>
          <?php $j = (int)floor((strtotime($user['plan_expire_at']) - time()) / 86400); ?>
          <div style="font-size:12px;color:<?= $j <= 7 ? 'var(--rouge)' : 'var(--gris-500)' ?>">
            <?= $j > 0 ? "Expire dans {$j} jour" . ($j>1?'s':'') : 'Expiré' ?> · <?= date('d/m/Y', strtotime($user['plan_expire_at'])) ?>
          </div>
        <?php elseif ($user['plan'] === 'GRATUIT'): ?>
          <div style="font-size:12px;color:var(--gris-500)"><?= (int)($user['examens_mois']??0) ?>/<?= FREE_EXAMS_PER_MONTH ?> examens utilisés ce mois</div>
        <?php endif; ?>
      </div>
    </div>
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
      <?php if ($user['plan'] !== 'GRATUIT'): ?>
      <div>
        <div style="font-size:11px;color:var(--gris-500);margin-bottom:3px">Votre code de parrainage</div>
        <div style="font-family:var(--font-mono);font-size:15px;font-weight:700;background:#fff;border:1px solid var(--gris-200);padding:5px 14px;border-radius:8px;cursor:pointer;display:flex;align-items:center;gap:8px" onclick="copyRef()" title="Copier le lien">
          <?= e($user['referral_code'] ?? 'N/A') ?> <i class="bi bi-copy" style="font-size:12px;color:var(--gris-400)"></i>
        </div>
        <div style="font-size:11px;color:var(--gris-400);margin-top:2px">Partagez · gagnez 1 mois</div>
      </div>
      <?php endif; ?>
      <a href="/reussiteplus/abonnement.php" class="btn btn-ghost btn-sm"><i class="bi bi-clock-history"></i> Historique</a>
    </div>
  </div>

  <!-- En-tête section plans -->
  <div class="tarif-header">
    <h2>Choisissez votre plan</h2>
    <p>Des formules adaptées à chaque étape de votre parcours scolaire. Changez ou annulez à tout moment.</p>
  </div>

  <!-- Grille des 3 plans -->
  <div class="tarif-grid">
    <?php foreach (['BASIQUE', 'PREMIUM', 'ECOLE'] as $planKey):
      $plan    = PLANS[$planKey];
      $isActive = $user['plan'] === $planKey && plan_actif($user);
      $isPopular = $plan['populaire'] ?? false;
      $couleur = $plan['couleur'];
    ?>
    <div class="tarif-card <?= $isActive ? 'is-active' : ($isPopular ? 'is-popular' : '') ?>">

      <?php if ($isActive): ?>
        <div class="tarif-badge active"><i class="bi bi-check-circle-fill"></i> Plan actif</div>
      <?php elseif ($isPopular): ?>
        <div class="tarif-badge popular"><i class="bi bi-lightning-charge-fill"></i> Recommandé</div>
      <?php endif; ?>

      <div class="tarif-card-head">
        <div class="tarif-card-icon" style="background:<?= $couleur ?>18;color:<?= $couleur ?>">
          <i class="<?= e($plan['icone']) ?>"></i>
        </div>
        <div class="tarif-card-name"><?= e($plan['nom']) ?></div>
        <div class="tarif-card-tagline"><?= e($plan['tagline']) ?></div>
        <?php if ($plan['prix'] === 0): ?>
          <div class="tarif-card-price" style="color:var(--gris-600)">Gratuit</div>
        <?php else: ?>
          <div class="tarif-card-price" style="color:<?= $couleur ?>"><?= number_format($plan['prix'], 0, ',', ' ') ?> <span style="font-size:16px;font-weight:600">CDF</span></div>
          <div class="tarif-card-period">par mois · sans engagement</div>
        <?php endif; ?>
      </div>

      <div class="tarif-card-features">
        <?php
        $features = [
            [$plan['examens_mois'] < 0 ? 'Examens <strong>illimités</strong>' : "<strong>{$plan['examens_mois']}</strong> examens par mois", 'ok', 'Passez autant d\'épreuves que vous voulez'],
            [$plan['questions'] < 0 ? 'Questions <strong>illimitées</strong>' : "<strong>{$plan['questions']}</strong> questions par examen", 'ok'],
            ['Banque de +800 questions officielles', 'ok'],
            ['Archives ENAFEP, TENASOSP, État', $plan['archives'] ? 'ok' : 'no'],
            ['Corrigés et explications détaillées', $plan['corrige'] ? 'ok' : 'no'],
            ['Suivi de progression et statistiques', 'ok'],
            ['Assistant IA de révision personnalisé', $plan['ia'] ? 'ok' : 'no'],
            [isset($plan['eleves_max']) ? "Jusqu'à <strong>{$plan['eleves_max']} élèves</strong>" : null, isset($plan['eleves_max']) ? 'ok' : null],
        ];
        foreach ($features as [$label, $state]):
            if ($label === null) continue;
        ?>
        <div class="tarif-feat">
          <i class="bi <?= $state==='ok' ? 'bi-check-circle-fill feat-icon ok' : 'bi-x-circle feat-icon no' ?>"></i>
          <span class="feat-label"><?= $label ?></span>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="tarif-card-cta">
        <?php if ($isActive): ?>
          <a href="/reussiteplus/paiement.php?plan=<?= $planKey ?>" class="btn btn-ghost btn-full"><i class="bi bi-arrow-repeat"></i> Renouveler</a>
        <?php elseif ($planKey === 'ECOLE'): ?>
          <a href="mailto:contact@reussiteplus.cd?subject=Plan%20Institution%20-%20RÉUSSITE%2B" class="btn btn-primary btn-full"><i class="bi bi-envelope"></i> Nous contacter</a>
        <?php else: ?>
          <a href="/reussiteplus/paiement.php?plan=<?= $planKey ?>" class="btn btn-full" style="background:<?= $isPopular ? 'var(--gold)' : $couleur ?>;color:#fff;font-weight:700;border:none;padding:13px;border-radius:10px;display:flex;align-items:center;justify-content:center;gap:8px;cursor:pointer;transition:opacity .15s;text-decoration:none" onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'">
            <i class="bi bi-arrow-right-circle-fill"></i>
            <?= $isPopular ? 'Commencer avec Excellence' : 'Choisir ' . e($plan['nom']) ?>
          </a>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Tableau comparatif -->
  <div class="card tarif-compare" style="margin-bottom:32px">
    <div style="padding:20px 24px 0;font-family:var(--font-display);font-size:16px;font-weight:700"><i class="bi bi-table" style="color:var(--primary)"></i> Comparaison détaillée</div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Fonctionnalité</th>
            <th style="color:var(--gris-600)">Découverte</th>
            <th style="color:#1E5FAD">Essentiel</th>
            <th style="color:var(--gold)"><i class="bi bi-lightning-charge-fill"></i> Excellence</th>
            <th style="color:var(--primary)">Institution</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Examens par mois</td>
            <td><span class="val">5</span></td>
            <td><span class="val">30</span></td>
            <td><span class="val">∞</span></td>
            <td><span class="val">∞</span></td>
          </tr>
          <tr>
            <td>Questions par examen</td>
            <td><span class="val">20</span></td>
            <td><span class="val">200</span></td>
            <td><span class="val">∞</span></td>
            <td><span class="val">∞</span></td>
          </tr>
          <tr>
            <td>Accès archives officielles</td>
            <td><i class="bi bi-x-circle no"></i></td>
            <td><i class="bi bi-check-circle-fill ok"></i></td>
            <td><i class="bi bi-check-circle-fill ok"></i></td>
            <td><i class="bi bi-check-circle-fill ok"></i></td>
          </tr>
          <tr>
            <td>Corrigés et explications</td>
            <td><i class="bi bi-x-circle no"></i></td>
            <td><i class="bi bi-check-circle-fill ok"></i></td>
            <td><i class="bi bi-check-circle-fill ok"></i></td>
            <td><i class="bi bi-check-circle-fill ok"></i></td>
          </tr>
          <tr>
            <td>Assistant IA personnalisé</td>
            <td><i class="bi bi-x-circle no"></i></td>
            <td><i class="bi bi-x-circle no"></i></td>
            <td><i class="bi bi-check-circle-fill ok"></i></td>
            <td><i class="bi bi-check-circle-fill ok"></i></td>
          </tr>
          <tr>
            <td>Suivi de progression</td>
            <td><i class="bi bi-check-circle-fill ok"></i></td>
            <td><i class="bi bi-check-circle-fill ok"></i></td>
            <td><i class="bi bi-check-circle-fill ok"></i></td>
            <td><i class="bi bi-check-circle-fill ok"></i></td>
          </tr>
          <tr>
            <td>Gestion multi-élèves</td>
            <td><i class="bi bi-x-circle no"></i></td>
            <td><i class="bi bi-x-circle no"></i></td>
            <td><i class="bi bi-x-circle no"></i></td>
            <td><span class="val">50 élèves</span></td>
          </tr>
          <tr>
            <td><strong>Prix mensuel</strong></td>
            <td><span style="font-weight:700;color:var(--gris-600)">Gratuit</span></td>
            <td><span class="val" style="color:#1E5FAD">5 000 CDF</span></td>
            <td><span class="val" style="color:var(--gold)">10 000 CDF</span></td>
            <td><span class="val" style="color:var(--primary)">50 000 CDF</span></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Méthodes de paiement -->
  <div class="card" style="text-align:center;padding:28px">
    <div style="font-family:var(--font-display);font-size:16px;font-weight:700;margin-bottom:6px">Moyens de paiement acceptés</div>
    <div style="font-size:13px;color:var(--gris-500);margin-bottom:20px">Paiements traités en CDF · Activation sous 24h · Support réactif</div>
    <div class="pay-methods-grid">
      <?php foreach (METHODES_PAIEMENT as $m): ?>
      <div class="pay-method-chip">
        <i class="<?= e($m['icone']) ?>"></i>
        <div style="text-align:left">
          <div style="font-size:13px;font-weight:600"><?= e($m['nom']) ?></div>
          <div style="font-size:11px;color:var(--gris-500)"><?= e($m['numero']) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="margin-top:20px;font-size:12px;color:var(--gris-400)">
      <i class="bi bi-shield-check" style="color:var(--primary)"></i> Paiements sécurisés &nbsp;·&nbsp;
      <i class="bi bi-headset" style="color:var(--primary)"></i> Support : <a href="mailto:support@reussiteplus.cd" style="color:var(--primary)">support@reussiteplus.cd</a> &nbsp;·&nbsp;
      <i class="bi bi-whatsapp" style="color:#25D366"></i> WhatsApp : +243 8XX XXX XXX
    </div>
  </div>

</div>

<script>
function copyRef() {
  const code = '<?= e($user['referral_code'] ?? '') ?>';
  const url = window.location.origin + '/reussiteplus/inscription.php?ref=' + code;
  if (navigator.clipboard) {
    navigator.clipboard.writeText(url).then(() => {
      const el = event.currentTarget;
      const orig = el.innerHTML;
      el.innerHTML = '<i class="bi bi-check" style="color:var(--primary)"></i> Copié !';
      setTimeout(() => { el.innerHTML = orig; }, 1800);
    });
  }
}
</script>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
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
