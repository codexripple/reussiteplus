<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

if (is_logged()) { header('Location: /reussiteplus/dashboard.php'); exit; }

$errors = [];
$redirect = $_GET['redirect'] ?? '/reussiteplus/dashboard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Token de securite invalide. Rechargez la page.';
    } else {
        $result = auth_login(
            trim($_POST['email'] ?? ''),
            $_POST['password'] ?? ''
        );
        if ($result['ok']) {
            header('Location: ' . $redirect);
            exit;
        } else {
            $errors[] = $result['msg'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Connexion &ndash; R&Eacute;USSITE+</title>
<link rel="icon" type="image/svg+xml" href="/reussiteplus/assets/img/favicon.svg">
<link rel="stylesheet" href="/reussiteplus/assets/css/fonts.css">
<link rel="stylesheet" href="/reussiteplus/assets/css/bootstrap-icons.css">
<style>
:root {
  --primary:#007A5E; --primary-dark:#005A45; --primary-light:#00A97F;
  --primary-subtle:#E8F5F1; --gold:#C9972A; --rouge:#C9342A;
  --noir:#0D1117; --gris-900:#1C2433; --gris-700:#4A5568;
  --gris-600:#6B7280; --gris-400:#A0AEC0; --gris-200:#E2E8F0;
  --gris-100:#F1F5F9; --blanc:#FFFFFF;
  --font-display:'Poppins',sans-serif; --font-body:'Poppins',sans-serif;
  --radius:10px; --radius-lg:16px;
  --shadow-lg:0 8px 32px rgba(0,0,0,.12);
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
    radial-gradient(ellipse 80% 60% at 20% 10%,rgba(0,122,94,0.35) 0%,transparent 55%),
    radial-gradient(ellipse 60% 50% at 80% 90%,rgba(201,151,42,0.15) 0%,transparent 55%);
  pointer-events:none;
}
.left-bg-img{
  position:absolute;inset:0;z-index:0;
  background:url('https://images.unsplash.com/photo-1427504494785-3a9ca7044f45?w=900&auto=format&q=55') center/cover no-repeat;
  opacity:.1;
}
.left-content{position:relative;z-index:1;}
.left-logo{display:flex;align-items:center;gap:12px;margin-bottom:56px;}
.left-logo img{height:40px;}
.left-headline{
  font-family:var(--font-display);font-size:clamp(26px,3vw,42px);
  font-weight:900;color:white;line-height:1.12;margin-bottom:18px;
}
.left-headline span{color:var(--gold);}
.left-sub{font-size:15px;color:rgba(255,255,255,0.52);line-height:1.72;max-width:340px;}
.left-features{margin-top:40px;display:flex;flex-direction:column;gap:14px;}
.left-feature{
  display:flex;align-items:center;gap:14px;
  background:rgba(255,255,255,0.055);border:1px solid rgba(255,255,255,0.09);
  border-radius:var(--radius);padding:14px 16px;
}
.left-feature-icon{
  width:38px;height:38px;border-radius:9px;display:flex;align-items:center;
  justify-content:center;font-size:17px;flex-shrink:0;
}
.left-feature-text{font-size:13px;color:rgba(255,255,255,0.7);line-height:1.45;}
.left-feature-title{font-weight:700;color:white;display:block;margin-bottom:2px;}
.left-bottom{position:relative;z-index:1;}
.left-stats{display:flex;gap:28px;flex-wrap:wrap;}
.left-stat-num{font-family:var(--font-display);font-size:22px;font-weight:800;color:white;}
.left-stat-label{font-size:11px;color:rgba(255,255,255,0.38);margin-top:2px;}
.left-stat-divider{width:1px;background:rgba(255,255,255,0.1);align-self:stretch;}

/* PANNEAU DROIT */
.right-panel{
  width:480px;flex-shrink:0;background:white;
  display:flex;flex-direction:column;justify-content:center;
  padding:56px 48px;overflow-y:auto;
}
.form-header{margin-bottom:32px;}
.form-eyebrow{
  font-size:11px;font-weight:700;color:var(--primary);
  text-transform:uppercase;letter-spacing:1.8px;margin-bottom:10px;display:block;
}
.form-title{
  font-family:var(--font-display);font-size:28px;font-weight:800;
  color:var(--gris-900);line-height:1.15;margin-bottom:8px;
}
.form-desc{font-size:14px;color:var(--gris-600);line-height:1.65;}

.form-group{margin-bottom:18px;}
.form-label{display:block;font-size:13px;font-weight:600;color:var(--gris-700);margin-bottom:7px;}
.input-wrap{position:relative;}
.input-icon{
  position:absolute;left:14px;top:50%;transform:translateY(-50%);
  color:var(--gris-400);font-size:16px;pointer-events:none;
}
.form-control{
  width:100%;padding:13px 16px 13px 42px;
  border:1.5px solid var(--gris-200);border-radius:var(--radius);
  font-family:var(--font-body);font-size:14px;color:var(--gris-900);
  background:var(--blanc);outline:none;transition:var(--transition);
}
.form-control:focus{border-color:var(--primary);box-shadow:0 0 0 4px rgba(0,122,94,0.1);}
.form-control::placeholder{color:var(--gris-400);}
.password-toggle{
  position:absolute;right:12px;top:50%;transform:translateY(-50%);
  background:none;border:none;cursor:pointer;color:var(--gris-400);
  font-size:16px;padding:4px;line-height:1;transition:var(--transition);
}
.password-toggle:hover{color:var(--gris-700);}
.forgot-link{text-align:right;margin-top:7px;}
.forgot-link a{font-size:12px;color:var(--primary);font-weight:600;}
.forgot-link a:hover{text-decoration:underline;}

.btn-submit{
  width:100%;padding:14px;border-radius:var(--radius);
  background:var(--primary);color:white;
  font-family:var(--font-body);font-size:15px;font-weight:700;
  border:none;cursor:pointer;transition:var(--transition);
  display:flex;align-items:center;justify-content:center;gap:8px;
  margin-top:8px;
}
.btn-submit:hover{background:var(--primary-dark);transform:translateY(-1px);box-shadow:0 6px 20px rgba(0,122,94,0.32);}
.btn-submit:active{transform:none;}
.btn-submit:disabled{opacity:.6;cursor:not-allowed;transform:none;box-shadow:none;}

.alert-error{
  background:#FEF0EF;border:1px solid rgba(201,52,42,0.2);
  border-left:4px solid var(--rouge);
  color:#7F1D1D;padding:12px 16px;border-radius:var(--radius);
  font-size:13px;margin-bottom:20px;display:flex;align-items:flex-start;gap:10px;
}
.alert-error i{font-size:16px;color:var(--rouge);flex-shrink:0;margin-top:1px;}

.demo-box{
  background:var(--gris-100);border-radius:var(--radius);padding:12px 14px;
  font-size:12px;color:var(--gris-600);margin-top:16px;text-align:center;
  border:1px solid var(--gris-200);
}
.demo-box strong{color:var(--gris-900);}

.form-footer{margin-top:24px;text-align:center;font-size:13px;color:var(--gris-600);}
.form-footer a{color:var(--primary);font-weight:600;}
.form-footer a:hover{text-decoration:underline;}
.back-link{
  display:inline-flex;align-items:center;gap:6px;
  font-size:12px;color:var(--gris-500);margin-top:10px;
  transition:var(--transition);
}
.back-link:hover{color:var(--gris-700);}

@media(max-width:900px){
  .left-panel{display:none;}
  .right-panel{width:100%;padding:40px 24px;}
  .mobile-logo{display:block !important;text-align:center;margin-bottom:28px;}
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
    <h1 class="left-headline">Ton examen,<br>notre <span>mission.</span></h1>
    <p class="left-sub">
      La plateforme de pr&eacute;paration aux examens officiels
      de la R&eacute;publique D&eacute;mocratique du Congo.
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
          <i class="bi bi-lightbulb" style="color:var(--gold)"></i>
        </div>
        <div class="left-feature-text">
          <span class="left-feature-title">Banque de questions</span>
          +15&thinsp;000 QCM avec corrections d&eacute;taill&eacute;es
        </div>
      </div>
      <div class="left-feature">
        <div class="left-feature-icon" style="background:rgba(30,95,173,0.18)">
          <i class="bi bi-graph-up-arrow" style="color:#5B9BD5"></i>
        </div>
        <div class="left-feature-text">
          <span class="left-feature-title">Suivi de progression</span>
          Score par mati&egrave;re, historique &amp; points forts
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
        <div class="left-stat-num">3&thinsp;200+</div>
        <div class="left-stat-label">Archives</div>
      </div>
      <div class="left-stat-divider"></div>
      <div>
        <div class="left-stat-num">48&thinsp;000+</div>
        <div class="left-stat-label">Examens pass&eacute;s</div>
      </div>
    </div>
  </div>
</div>

<!-- PANNEAU DROIT -->
<div class="right-panel">

  <!-- Logo visible uniquement sur mobile -->
  <div style="display:none" class="mobile-logo">
    <img src="/reussiteplus/assets/img/logo.svg" alt="REUSSITE+" height="42">
  </div>

  <div class="form-header">
    <span class="form-eyebrow">Connexion</span>
    <h2 class="form-title">Content de te revoir&nbsp;!</h2>
    <p class="form-desc">
      Entre tes identifiants pour reprendre l&agrave; o&ugrave; tu t&rsquo;es arr&ecirc;t&eacute;.
    </p>
  </div>

  <?php if ($errors): ?>
  <div class="alert-error">
    <i class="bi bi-exclamation-circle-fill"></i>
    <span><?= e($errors[0]) ?></span>
  </div>
  <?php endif; ?>

  <form method="POST" action="" id="loginForm">
    <?= csrf_field() ?>
    <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

    <div class="form-group">
      <label class="form-label" for="email">Adresse e-mail</label>
      <div class="input-wrap">
        <i class="bi bi-envelope input-icon"></i>
        <input class="form-control" type="email" id="email" name="email"
               placeholder="vous@exemple.com"
               value="<?= e($_POST['email'] ?? '') ?>"
               required autocomplete="email">
      </div>
    </div>

    <div class="form-group">
      <label class="form-label" for="password">Mot de passe</label>
      <div class="input-wrap">
        <i class="bi bi-lock input-icon"></i>
        <input class="form-control" type="password" id="password" name="password"
               placeholder="Votre mot de passe"
               required autocomplete="current-password"
               style="padding-right:44px">
        <button type="button" class="password-toggle"
                onclick="togglePwd('password','icon1')" title="Afficher/masquer">
          <i class="bi bi-eye" id="icon1"></i>
        </button>
      </div>
      <div class="forgot-link">
        <a href="/reussiteplus/mot-de-passe.php">Mot de passe oubli&eacute;&nbsp;?</a>
      </div>
    </div>

    <button type="submit" class="btn-submit" id="submitBtn">
      <i class="bi bi-box-arrow-in-right"></i>
      Se connecter
    </button>
  </form>

  <div class="demo-box">
    Compte d&eacute;mo&nbsp;: <strong>demo@reussiteplus.cd</strong>
    &nbsp;&bull;&nbsp; <strong>Demo1234!</strong>
  </div>

  <div class="form-footer">
    Pas encore de compte&nbsp;?
    <a href="/reussiteplus/inscription.php">Cr&eacute;er un compte gratuit</a>
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
document.getElementById('loginForm').addEventListener('submit', function() {
  const btn = document.getElementById('submitBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="bi bi-hourglass-split"></i>&nbsp;Connexion en cours&hellip;';
});
</script>
</body>
</html>
