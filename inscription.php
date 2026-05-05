<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

if (is_logged()) { header('Location: /reussiteplus/dashboard.php'); exit; }

$errors = [];
$provinces = [];
try { $provinces = dbAll("SELECT id, nom FROM provinces ORDER BY nom"); } catch (Exception $e) {}

// Code de parrainage depuis URL
$refCode = trim($_GET['ref'] ?? '');
$referralUser = null;
if ($refCode) {
    try {
        $referralUser = dbRow(
            "SELECT id, prenom, nom FROM utilisateurs WHERE referral_code = ?",
            [$refCode]
        );
    } catch (Exception $e) {}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Token de securite invalide. Rechargez la page.';
    } else {
        $nom    = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email  = trim($_POST['email'] ?? '');
        $pass   = $_POST['password'] ?? '';
        $classe = trim($_POST['classe'] ?? '');
        $provId = $_POST['province_id'] ?? null;

        if (empty($nom) || empty($prenom))
            $errors[] = 'Le nom et le pr&eacute;nom sont requis.';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))
            $errors[] = 'Adresse e-mail invalide.';
        if (strlen($pass) < 8)
            $errors[] = 'Mot de passe : minimum 8 caract&egrave;res.';
        if ($_POST['password_confirm'] !== $pass)
            $errors[] = 'Les mots de passe ne correspondent pas.';
        if (empty($_POST['cgv']))
            $errors[] = 'Veuillez accepter les conditions d\'utilisation.';

        if (!$errors) {
            $refParId = $referralUser ? $referralUser['id'] : null;
            $result = auth_register([
                'nom'          => $nom,
                'prenom'       => $prenom,
                'email'        => $email,
                'password'     => $pass,
                'classe'       => $classe,
                'province_id'  => $provId ?: null,
                'referral_par' => $refParId,
            ]);
            if ($result['ok']) {
                header('Location: /reussiteplus/dashboard.php?welcome=1');
                exit;
            } else {
                $errors[] = $result['msg'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr" id="htmlRoot">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inscription gratuite &ndash; R&Eacute;USSITE+</title>
<link rel="icon" type="image/svg+xml" href="/reussiteplus/assets/img/favicon.svg">
<link rel="stylesheet" href="/reussiteplus/assets/css/fonts.css">
<link rel="stylesheet" href="/reussiteplus/assets/css/bootstrap-icons.css">
<style>
:root{
  --primary:#007A5E; --primary-dark:#005A45; --primary-light:#00A97F;
  --primary-subtle:#E8F5F1; --gold:#C9972A; --rouge:#C9342A;
  --noir:#0D1117; --gris-900:#1C2433; --gris-700:#4A5568;
  --gris-600:#6B7280; --gris-500:#8A92A0; --gris-400:#A0AEC0;
  --gris-200:#E2E8F0; --gris-100:#F1F5F9; --blanc:#FFFFFF;
  --font-display:'Poppins',sans-serif; --font-body:'Poppins',sans-serif;
  --radius:10px; --radius-lg:16px;
  --transition:200ms cubic-bezier(0.4,0,0.2,1);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;}
body{font-family:var(--font-body);display:flex;min-height:100vh;}

/* PANNEAU GAUCHE */
.left-panel{
  flex:1;background:var(--noir);display:flex;flex-direction:column;
  justify-content:space-between;padding:48px;position:relative;overflow:hidden;
}
.left-panel::before{
  content:'';position:absolute;inset:0;
  background:
    radial-gradient(ellipse 80% 60% at 15% 10%,rgba(0,122,94,0.35) 0%,transparent 55%),
    radial-gradient(ellipse 55% 50% at 85% 85%,rgba(201,151,42,0.15) 0%,transparent 55%);
  pointer-events:none;
}
.left-bg-img{
  position:absolute;inset:0;z-index:0;
  background:url('https://images.unsplash.com/photo-1523050854058-8df90110c9f1?w=900&auto=format&q=55') center/cover no-repeat;
  opacity:.09;
}
.left-content{position:relative;z-index:1;}
.left-logo{display:flex;align-items:center;gap:12px;margin-bottom:48px;}
.left-logo img{height:40px;}
.left-badge{
  display:inline-flex;align-items:center;gap:8px;
  background:rgba(0,122,94,0.25);border:1px solid rgba(0,169,127,0.3);
  border-radius:50px;padding:7px 14px;font-size:12px;font-weight:700;
  color:#4DDFB3;margin-bottom:20px;
}
.left-badge i{font-size:14px;}
.left-headline{
  font-family:var(--font-display);font-size:clamp(24px,2.8vw,40px);
  font-weight:900;color:white;line-height:1.13;margin-bottom:16px;
}
.left-headline span{color:var(--gold);}
.left-sub{font-size:14.5px;color:rgba(255,255,255,0.5);line-height:1.75;max-width:340px;}
.left-features{margin-top:36px;display:flex;flex-direction:column;gap:13px;}
.left-feature{
  display:flex;align-items:center;gap:13px;
  background:rgba(255,255,255,0.052);border:1px solid rgba(255,255,255,0.09);
  border-radius:var(--radius);padding:13px 16px;
}
.left-feature-icon{
  width:37px;height:37px;border-radius:9px;display:flex;align-items:center;
  justify-content:center;font-size:16px;flex-shrink:0;
}
.left-feature-text{font-size:13px;color:rgba(255,255,255,0.68);line-height:1.45;}
.left-feature-title{font-weight:700;color:white;display:block;margin-bottom:2px;}
.left-bottom{position:relative;z-index:1;}
.left-stats{display:flex;gap:28px;flex-wrap:wrap;}
.left-stat-num{font-family:var(--font-display);font-size:21px;font-weight:800;color:white;}
.left-stat-label{font-size:11px;color:rgba(255,255,255,0.36);margin-top:2px;}
.left-stat-divider{width:1px;background:rgba(255,255,255,0.1);align-self:stretch;}

/* PANNEAU DROIT */
.right-panel{
  width:520px;flex-shrink:0;background:white;
  display:flex;flex-direction:column;justify-content:center;
  padding:48px 44px;overflow-y:auto;
}
.form-header{margin-bottom:24px;}
.form-eyebrow{
  font-size:11px;font-weight:700;color:var(--primary);
  text-transform:uppercase;letter-spacing:1.8px;margin-bottom:10px;display:block;
}
.form-title{
  font-family:var(--font-display);font-size:26px;font-weight:800;
  color:var(--gris-900);line-height:1.15;margin-bottom:8px;
}
.form-desc{font-size:13.5px;color:var(--gris-600);line-height:1.65;}

/* Referral banner */
.referral-banner{
  display:flex;align-items:center;gap:10px;
  background:#FFF8EC;border:1px solid rgba(201,151,42,0.3);
  border-radius:var(--radius);padding:12px 14px;
  font-size:13px;color:var(--gris-700);margin-bottom:16px;
}
.referral-banner i{color:var(--gold);font-size:16px;flex-shrink:0;}
.referral-banner strong{color:var(--gris-900);}

/* Free badge */
.free-badge{
  display:inline-flex;align-items:center;gap:8px;
  background:var(--primary-subtle);border:1px solid rgba(0,122,94,0.2);
  border-radius:var(--radius);padding:9px 13px;
  font-size:12.5px;color:var(--primary-dark);font-weight:600;
  margin-bottom:18px;
}
.free-badge i{font-size:14px;}

/* Alerte erreur */
.alert-error{
  background:#FEF0EF;border:1px solid rgba(201,52,42,0.2);
  border-left:4px solid var(--rouge);
  color:#7F1D1D;padding:12px 16px;border-radius:var(--radius);
  font-size:13px;margin-bottom:20px;display:flex;align-items:flex-start;gap:10px;
}
.alert-error i{font-size:16px;color:var(--rouge);flex-shrink:0;margin-top:1px;}
.alert-error ul{margin:6px 0 0 16px;}

/* Formulaire */
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.form-group{margin-bottom:16px;}
.form-label{display:block;font-size:13px;font-weight:600;color:var(--gris-700);margin-bottom:6px;}
.form-label-hint{font-size:11px;color:var(--gris-400);font-weight:400;margin-left:4px;}
.form-control{
  width:100%;padding:12px 14px;
  border:1.5px solid var(--gris-200);border-radius:var(--radius);
  font-family:var(--font-body);font-size:14px;color:var(--gris-900);
  background:var(--blanc);outline:none;transition:var(--transition);
}
.form-control:focus{border-color:var(--primary);box-shadow:0 0 0 4px rgba(0,122,94,0.1);}
.form-control::placeholder{color:var(--gris-400);}

.password-wrap{position:relative;}
.password-toggle{
  position:absolute;right:12px;top:50%;transform:translateY(-50%);
  background:none;border:none;cursor:pointer;color:var(--gris-400);
  font-size:16px;padding:4px;line-height:1;transition:var(--transition);
}
.password-toggle:hover{color:var(--gris-700);}
.password-wrap .form-control{padding-right:44px;}

/* Barre de force */
.strength-bar-track{
  height:4px;border-radius:4px;background:var(--gris-200);
  margin-top:8px;overflow:hidden;
}
.strength-bar-fill{
  height:100%;width:0;border-radius:4px;
  transition:width .35s ease, background .35s ease;
}
.strength-label{font-size:11px;color:var(--gris-500);margin-top:4px;}

/* Checkbox CGV */
.checkbox-wrap{display:flex;align-items:flex-start;gap:10px;cursor:pointer;}
.checkbox-wrap input[type=checkbox]{
  width:17px;height:17px;flex-shrink:0;margin-top:2px;
  accent-color:var(--primary);cursor:pointer;
}
.checkbox-wrap span{font-size:13px;color:var(--gris-600);line-height:1.55;}
.checkbox-wrap a{color:var(--primary);font-weight:600;}
.checkbox-wrap a:hover{text-decoration:underline;}

/* Bouton */
.btn-submit{
  width:100%;padding:14px;border-radius:var(--radius);
  background:var(--primary);color:white;
  font-family:var(--font-body);font-size:14.5px;font-weight:700;
  border:none;cursor:pointer;transition:var(--transition);
  display:flex;align-items:center;justify-content:center;gap:8px;
  margin-top:10px;
}
.btn-submit:hover{background:var(--primary-dark);transform:translateY(-1px);box-shadow:0 6px 20px rgba(0,122,94,0.32);}
.btn-submit:active{transform:none;}
.btn-submit:disabled{opacity:.6;cursor:not-allowed;transform:none;box-shadow:none;}

.form-footer{margin-top:20px;text-align:center;font-size:13px;color:var(--gris-600);}
.form-footer a{color:var(--primary);font-weight:600;}
.form-footer a:hover{text-decoration:underline;}
.back-link{
  display:inline-flex;align-items:center;gap:6px;
  font-size:12px;color:var(--gris-500);margin-top:8px;
  transition:var(--transition);
}
.back-link:hover{color:var(--gris-700);}

@media(max-width:960px){
  .left-panel{display:none;}
  .right-panel{width:100%;padding:36px 24px;}
  .mobile-logo{display:block !important;text-align:center;margin-bottom:24px;}
  .form-row{grid-template-columns:1fr;}
}
</style>
</head>
<body>

<!-- PANNEAU GAUCHE (masqu&eacute; sur mobile) -->
<div class="left-panel">
  <div class="left-bg-img"></div>

  <div class="left-content">
    <div class="left-logo">
      <img src="/reussiteplus/assets/img/logo-white.svg" alt="REUSSITE+">
    </div>

    <div class="left-badge">
      <i class="bi bi-gift"></i>
      Gratuit &mdash; sans carte bancaire
    </div>

    <h1 class="left-headline">
      Commence &agrave;<br>te pr&eacute;parer<br><span>d&egrave;s aujourd&rsquo;hui.</span>
    </h1>
    <p class="left-sub">
      Rejoins plus de 12&thinsp;000 &eacute;l&egrave;ves qui pr&eacute;parent
      leurs examens officiels avec R&Eacute;USSITE+.
    </p>

    <div class="left-features">
      <div class="left-feature">
        <div class="left-feature-icon" style="background:rgba(0,122,94,0.18)">
          <i class="bi bi-folder2-open" style="color:#00A97F"></i>
        </div>
        <div class="left-feature-text">
          <span class="left-feature-title">Archives officielles</span>
          ENAFEP, TENASOSP, Examen d&rsquo;&Eacute;tat &amp; Dioc&eacute;sains
        </div>
      </div>
      <div class="left-feature">
        <div class="left-feature-icon" style="background:rgba(201,151,42,0.18)">
          <i class="bi bi-pencil-square" style="color:var(--gold)"></i>
        </div>
        <div class="left-feature-text">
          <span class="left-feature-title">QCM chronom&eacute;tr&eacute;s</span>
          Simulations r&eacute;elles avec corrections d&eacute;taill&eacute;es
        </div>
      </div>
      <div class="left-feature">
        <div class="left-feature-icon" style="background:rgba(30,95,173,0.18)">
          <i class="bi bi-graph-up-arrow" style="color:#5B9BD5"></i>
        </div>
        <div class="left-feature-text">
          <span class="left-feature-title">Progression suivie</span>
          Score par mati&egrave;re, historique &amp; statistiques
        </div>
      </div>
    </div>
  </div>

  <div class="left-bottom">
    <div class="left-stats">
      <div>
        <div class="left-stat-num">12&thinsp;000+</div>
        <div class="left-stat-label">&Eacute;l&egrave;ves inscrits</div>
      </div>
      <div class="left-stat-divider"></div>
      <div>
        <div class="left-stat-num">5</div>
        <div class="left-stat-label">Examens/mois offerts</div>
      </div>
      <div class="left-stat-divider"></div>
      <div>
        <div class="left-stat-num">100%</div>
        <div class="left-stat-label">Gratuit pour d&eacute;buter</div>
      </div>
    </div>
  </div>
</div>

<!-- PANNEAU DROIT -->
<div class="right-panel">

  <div style="display:none" class="mobile-logo">
    <img src="/reussiteplus/assets/img/logo.svg" alt="REUSSITE+" height="42">
  </div>

  <div class="form-header">
    <span class="form-eyebrow">Inscription gratuite</span>
    <h2 class="form-title">Cr&eacute;er mon compte</h2>
    <p class="form-desc">
      Plus de 12&thinsp;000 &eacute;l&egrave;ves pr&eacute;parent leurs examens ici.
      Rejoins-les gratuitement.
    </p>
  </div>

  <?php if ($referralUser): ?>
  <div class="referral-banner">
    <i class="bi bi-person-check-fill"></i>
    <span>
      Tu es invit&eacute;(e) par
      <strong><?= e($referralUser['prenom'] . ' ' . $referralUser['nom']) ?></strong>.
      Cr&eacute;e ton compte et d&eacute;marre avec un bonus&nbsp;!
    </span>
  </div>
  <?php endif; ?>

  <div class="free-badge">
    <i class="bi bi-check-circle-fill"></i>
    Gratuit &mdash; 5 examens par mois, sans carte bancaire
  </div>

  <?php if ($errors): ?>
  <div class="alert-error">
    <i class="bi bi-exclamation-circle-fill"></i>
    <div>
      <?php if (count($errors) === 1): ?>
        <?= e($errors[0]) ?>
      <?php else: ?>
        Veuillez corriger les erreurs suivantes&nbsp;:
        <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <form method="POST" action="" id="regForm">
    <?= csrf_field() ?>
    <?php if ($refCode): ?>
      <input type="hidden" name="ref" value="<?= e($refCode) ?>">
    <?php endif; ?>

    <div class="form-row">
      <div class="form-group">
        <label class="form-label" for="prenom">Pr&eacute;nom *</label>
        <input class="form-control" type="text" id="prenom" name="prenom"
               placeholder="Jean"
               value="<?= e($_POST['prenom'] ?? '') ?>"
               required autocomplete="given-name">
      </div>
      <div class="form-group">
        <label class="form-label" for="nom">Nom *</label>
        <input class="form-control" type="text" id="nom" name="nom"
               placeholder="Mukeba"
               value="<?= e($_POST['nom'] ?? '') ?>"
               required autocomplete="family-name">
      </div>
    </div>

    <div class="form-group">
      <label class="form-label" for="email">Adresse e-mail *</label>
      <input class="form-control" type="email" id="email" name="email"
             placeholder="vous@exemple.com"
             value="<?= e($_POST['email'] ?? '') ?>"
             required autocomplete="email">
    </div>

    <div class="form-row">
      <div class="form-group">
        <label class="form-label" for="province_id">Province</label>
        <select class="form-control" id="province_id" name="province_id">
          <option value="">&mdash; S&eacute;lectionner &mdash;</option>
          <?php foreach ($provinces as $p): ?>
          <option value="<?= e($p['id']) ?>"
                  <?= (($_POST['province_id'] ?? '') == $p['id']) ? 'selected' : '' ?>>
            <?= e($p['nom']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="classe">Classe</label>
        <select class="form-control" id="classe" name="classe">
          <option value="">&mdash; S&eacute;lectionner &mdash;</option>
          <optgroup label="Primaire">
            <option value="5eme primaire"<?= (($_POST['classe'] ?? '') === '5eme primaire') ? ' selected' : '' ?>>5&egrave;me primaire</option>
            <option value="6eme primaire"<?= (($_POST['classe'] ?? '') === '6eme primaire') ? ' selected' : '' ?>>6&egrave;me primaire</option>
          </optgroup>
          <optgroup label="Secondaire">
            <option value="1ere secondaire"<?= (($_POST['classe'] ?? '') === '1ere secondaire') ? ' selected' : '' ?>>1&egrave;re secondaire</option>
            <option value="2eme secondaire"<?= (($_POST['classe'] ?? '') === '2eme secondaire') ? ' selected' : '' ?>>2&egrave;me secondaire</option>
            <option value="3eme secondaire"<?= (($_POST['classe'] ?? '') === '3eme secondaire') ? ' selected' : '' ?>>3&egrave;me secondaire</option>
            <option value="4eme secondaire"<?= (($_POST['classe'] ?? '') === '4eme secondaire') ? ' selected' : '' ?>>4&egrave;me secondaire</option>
            <option value="5eme secondaire"<?= (($_POST['classe'] ?? '') === '5eme secondaire') ? ' selected' : '' ?>>5&egrave;me secondaire</option>
            <option value="6eme secondaire"<?= (($_POST['classe'] ?? '') === '6eme secondaire') ? ' selected' : '' ?>>6&egrave;me secondaire</option>
          </optgroup>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label" for="password">
        Mot de passe *
        <span class="form-label-hint">(min. 8 caract&egrave;res)</span>
      </label>
      <div class="password-wrap">
        <input class="form-control" type="password" id="password" name="password"
               placeholder="Choisissez un mot de passe fort"
               required autocomplete="new-password"
               oninput="checkStrength(this.value)">
        <button type="button" class="password-toggle"
                onclick="togglePwd('password','icon1')" title="Afficher/masquer">
          <i class="bi bi-eye" id="icon1"></i>
        </button>
      </div>
      <div class="strength-bar-track">
        <div class="strength-bar-fill" id="strengthBar"></div>
      </div>
      <div class="strength-label" id="strengthLabel"></div>
    </div>

    <div class="form-group">
      <label class="form-label" for="password_confirm">Confirmer le mot de passe *</label>
      <div class="password-wrap">
        <input class="form-control" type="password" id="password_confirm" name="password_confirm"
               placeholder="R&eacute;p&eacute;tez votre mot de passe"
               required autocomplete="new-password">
        <button type="button" class="password-toggle"
                onclick="togglePwd('password_confirm','icon2')" title="Afficher/masquer">
          <i class="bi bi-eye" id="icon2"></i>
        </button>
      </div>
    </div>

    <div class="form-group">
      <label class="checkbox-wrap">
        <input type="checkbox" name="cgv" <?= isset($_POST['cgv']) ? 'checked' : '' ?> required>
        <span>
          J&rsquo;accepte les
          <a href="/reussiteplus/cgv.php" target="_blank">conditions d&rsquo;utilisation</a>
          et la
          <a href="/reussiteplus/confidentialite.php" target="_blank">politique de confidentialit&eacute;</a>.
        </span>
      </label>
    </div>

    <button type="submit" class="btn-submit" id="submitBtn">
      <i class="bi bi-person-plus"></i>
      Cr&eacute;er mon compte gratuitement &rarr;
    </button>
  </form>

  <div class="form-footer">
    D&eacute;j&agrave; un compte&nbsp;?
    <a href="/reussiteplus/connexion.php">Se connecter</a>
  </div>
  <div class="form-footer" style="margin-top:6px">
    <a href="/reussiteplus/index.php" class="back-link">
      <i class="bi bi-arrow-left"></i>&nbsp;Retour &agrave; l&rsquo;accueil
    </a>
  </div>
</div>

<script>
function togglePwd(inputId, iconId) {
  const el = document.getElementById(inputId);
  const ic = document.getElementById(iconId);
  el.type = el.type === 'password' ? 'text' : 'password';
  ic.className = el.type === 'text' ? 'bi bi-eye-slash' : 'bi bi-eye';
}

function checkStrength(val) {
  let score = 0;
  if (val.length >= 8)            score++;
  if (/[A-Z]/.test(val))          score++;
  if (/[0-9]/.test(val))          score++;
  if (/[^A-Za-z0-9]/.test(val))   score++;

  const bar = document.getElementById('strengthBar');
  const lbl = document.getElementById('strengthLabel');

  const colors = ['#C9342A', '#C9972A', '#1E5FAD', '#007A5E'];
  const labels = ['Tr\u00e8s faible', 'Moyen', 'Fort', 'Tr\u00e8s fort'];

  if (!val.length) {
    bar.style.width = '0';
    lbl.textContent = '';
    return;
  }
  const idx = Math.max(0, score - 1);
  bar.style.background = colors[idx];
  bar.style.width = (score * 25) + '%';
  lbl.style.color = colors[idx];
  lbl.textContent = labels[idx];
}

document.getElementById('regForm').addEventListener('submit', function () {
  const btn = document.getElementById('submitBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="bi bi-hourglass-split"></i>&nbsp;Cr\u00e9ation en cours\u2026';
});
</script>
</body>
</html>

