<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

if (is_logged()) { header('Location: /reussiteplus/dashboard.php'); exit; }

$errors  = [];
$success = null;
$devUrl  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Token de sécurité invalide. Rechargez la page.';
    } else {
        $email  = trim($_POST['email'] ?? '');
        $result = auth_request_password_reset($email);
        if (!$result['ok']) {
            $errors[] = $result['msg'];
        } else {
            $success = $result['prenom'] ?? '';
            $devUrl  = $result['dev_url'] ?? null;
        }
    }
}
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="fr" id="htmlRoot">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mot de passe oublié — RÉUSSITE+</title>
<link rel="icon" type="image/svg+xml" href="/reussiteplus/assets/img/favicon.svg">
<link rel="stylesheet" href="/reussiteplus/assets/css/fonts.css">
<link rel="stylesheet" href="/reussiteplus/assets/css/bootstrap-icons.css">
<style>
:root {
  --primary:#007A5E; --primary-dark:#005A45; --primary-subtle:#E8F5F1;
  --gold:#C9972A; --rouge:#C9342A;
  --noir:#0D1117; --gris-900:#1C2433; --gris-700:#4A5568;
  --gris-600:#6B7280; --gris-400:#A0AEC0; --gris-200:#E2E8F0;
  --gris-100:#F1F5F9; --gris-50:#F8FAFC; --blanc:#FFFFFF;
  --font-display:'Poppins',sans-serif; --font-body:'Poppins',sans-serif;
  --radius:10px; --transition:200ms cubic-bezier(0.4,0,0.2,1);
  --shadow-lg:0 8px 32px rgba(0,0,0,.12);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;}
body{font-family:var(--font-body);display:flex;min-height:100vh;background:var(--gris-50);}
.left-panel{
  flex:1;background:var(--noir);display:flex;flex-direction:column;
  justify-content:space-between;padding:48px;position:relative;overflow:hidden;
}
.left-panel::before{
  content:'';position:absolute;inset:0;
  background:radial-gradient(ellipse 80% 60% at 20% 10%,rgba(0,122,94,0.35) 0%,transparent 55%),
             radial-gradient(ellipse 60% 50% at 80% 90%,rgba(201,151,42,0.15) 0%,transparent 55%);
  pointer-events:none;
}
.left-content{position:relative;z-index:1;}
.left-headline{font-family:var(--font-display);font-size:clamp(26px,3vw,40px);font-weight:900;color:white;line-height:1.15;margin-bottom:16px;}
.left-headline span{color:var(--gold);}
.left-sub{font-size:14px;color:rgba(255,255,255,0.55);line-height:1.7;max-width:340px;}
.steps{margin-top:40px;display:flex;flex-direction:column;gap:16px;}
.step{display:flex;align-items:flex-start;gap:14px;}
.step-num{width:32px;height:32px;border-radius:50%;background:rgba(0,122,94,0.3);border:1px solid rgba(0,122,94,0.5);color:var(--primary);font-weight:800;font-size:13px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.step-text{font-size:13px;color:rgba(255,255,255,0.65);line-height:1.5;padding-top:6px;}
.step-title{font-weight:700;color:white;display:block;margin-bottom:2px;}
.right-panel{
  width:480px;flex-shrink:0;background:var(--blanc);
  display:flex;flex-direction:column;justify-content:center;
  padding:56px 48px;overflow-y:auto;
}
.form-eyebrow{font-size:11px;font-weight:700;color:var(--primary);text-transform:uppercase;letter-spacing:1.8px;margin-bottom:10px;display:block;}
.form-title{font-family:var(--font-display);font-size:26px;font-weight:800;color:var(--gris-900);line-height:1.2;margin-bottom:8px;}
.form-desc{font-size:13px;color:var(--gris-600);line-height:1.7;margin-bottom:28px;}
.form-group{margin-bottom:18px;}
.form-label{display:block;font-size:13px;font-weight:600;color:var(--gris-700);margin-bottom:7px;}
.input-wrap{position:relative;}
.input-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--gris-400);font-size:16px;pointer-events:none;}
.form-control{width:100%;padding:13px 16px 13px 42px;border:1.5px solid var(--gris-200);border-radius:var(--radius);font-family:var(--font-body);font-size:14px;color:var(--gris-900);background:var(--blanc);outline:none;transition:var(--transition);}
.form-control:focus{border-color:var(--primary);box-shadow:0 0 0 4px rgba(0,122,94,0.1);}
.btn-submit{width:100%;padding:14px;border-radius:var(--radius);background:var(--primary);color:white;font-family:var(--font-body);font-size:15px;font-weight:700;border:none;cursor:pointer;transition:var(--transition);display:flex;align-items:center;justify-content:center;gap:8px;margin-top:8px;}
.btn-submit:hover{background:var(--primary-dark);transform:translateY(-1px);box-shadow:0 6px 20px rgba(0,122,94,0.32);}
.btn-submit:disabled{opacity:.6;cursor:not-allowed;transform:none;}
.alert-error{background:#FEF0EF;border:1px solid rgba(201,52,42,0.2);border-left:4px solid var(--rouge);color:#7F1D1D;padding:12px 16px;border-radius:var(--radius);font-size:13px;margin-bottom:20px;display:flex;align-items:flex-start;gap:10px;}
.alert-error i{font-size:16px;color:var(--rouge);flex-shrink:0;margin-top:1px;}
.success-card{background:var(--primary-subtle);border:1px solid rgba(0,122,94,0.2);border-radius:12px;padding:24px;text-align:center;}
.success-icon{width:64px;height:64px;border-radius:50%;background:rgba(0,122,94,0.12);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:28px;color:var(--primary);}
.dev-box{background:var(--gris-100);border:1px dashed var(--gris-400);border-radius:var(--radius);padding:14px;margin-top:16px;font-size:12px;}
.dev-box .dev-label{font-weight:700;color:var(--gold);font-size:11px;text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:6px;}
.dev-url{word-break:break-all;color:var(--primary);font-weight:600;}
.form-footer{margin-top:24px;text-align:center;font-size:13px;color:var(--gris-600);}
.form-footer a{color:var(--primary);font-weight:600;text-decoration:none;}
.form-footer a:hover{text-decoration:underline;}
@media(max-width:900px){.left-panel{display:none;}.right-panel{width:100%;padding:40px 24px;}}

/* ── Dark mode ─────────────────────────────────────── */
[data-theme="dark"]{--blanc:#1E293B;--gris-50:#0F172A;--gris-100:#1E293B;--gris-200:#334155;--gris-400:#64748B;--gris-600:#CBD5E1;--gris-700:#E2E8F0;--gris-900:#F8FAFC;--primary-subtle:rgba(0,122,94,0.14);}
[data-theme="dark"] body{background:var(--gris-50);}
[data-theme="dark"] .right-panel{background:var(--blanc);}
[data-theme="dark"] .form-control{background:var(--gris-100);border-color:var(--gris-200);color:var(--gris-900);}
[data-theme="dark"] .form-control:focus{background:var(--blanc);}
[data-theme="dark"] .success-card{background:rgba(0,122,94,0.15);border-color:rgba(0,122,94,0.3);}
[data-theme="dark"] .dev-box{background:var(--gris-100);border-color:var(--gris-200);}
</style>
<!-- Appliquer le thème avant le rendu -->
<script>(function(){var t=localStorage.getItem('rp-theme');var p=window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches;document.getElementById('htmlRoot').setAttribute('data-theme',t||(p?'dark':'light'));})()</script>
</head>
<body>

<div class="left-panel">
  <div class="left-content">
    <div style="margin-bottom:48px">
      <img src="/reussiteplus/assets/img/logo-white.svg" alt="RÉUSSITE+" height="36">
    </div>
    <h1 class="left-headline">Retrouvez<br>l'accès à votre<br><span>compte.</span></h1>
    <p class="left-sub">La réinitialisation prend moins de 2 minutes. Un lien sécurisé vous sera envoyé.</p>
    <div class="steps">
      <div class="step">
        <div class="step-num">1</div>
        <div class="step-text"><span class="step-title">Saisissez votre email</span>Celui utilisé lors de l'inscription</div>
      </div>
      <div class="step">
        <div class="step-num">2</div>
        <div class="step-text"><span class="step-title">Recevez le lien</span>Un email sécurisé valable 1 heure</div>
      </div>
      <div class="step">
        <div class="step-num">3</div>
        <div class="step-text"><span class="step-title">Choisissez un nouveau mot de passe</span>Minimum 8 caractères</div>
      </div>
    </div>
  </div>
  <div style="position:relative;z-index:1;font-size:12px;color:rgba(255,255,255,0.3)">© <?= date('Y') ?> RÉUSSITE+ — Sécurisé HTTPS</div>
</div>

<div class="right-panel">
  <div style="display:none" class="mobile-logo">
    <img src="/reussiteplus/assets/img/logo-icon.svg" height="40" alt="RÉUSSITE+">
  </div>

  <?php if ($success !== null): ?>
  <!-- ── Succès ── -->
  <div class="success-card">
    <div class="success-icon"><i class="bi bi-envelope-check-fill"></i></div>
    <div style="font-size:20px;font-weight:800;color:var(--gris-900);margin-bottom:8px">Email envoyé !</div>
    <p style="font-size:13px;color:var(--gris-600);line-height:1.7">
      <?php if ($success): ?>
      Bonjour <strong><?= e($success) ?></strong> !<br>
      <?php endif; ?>
      Un lien de réinitialisation a été envoyé à votre adresse email. <strong>Il est valable 1 heure.</strong><br><br>
      Vérifiez aussi votre dossier <strong>Spam / Indésirables</strong>.
    </p>
    <?php if ($devUrl && is_localhost()): ?>
    <!-- Mode développement : afficher le lien directement -->
    <div class="dev-box">
      <span class="dev-label">⚙ Mode développement (localhost)</span>
      <div style="margin-bottom:6px;color:var(--gris-600);font-size:11px">Email non envoyé — cliquez directement sur le lien :</div>
      <a href="<?= e($devUrl) ?>" class="dev-url"><i class="bi bi-link-45deg"></i> <?= e($devUrl) ?></a>
    </div>
    <?php endif; ?>
    <div style="margin-top:20px;display:flex;flex-direction:column;gap:10px">
      <a href="<?= e($_POST['email'] ? 'mot_de_passe_oublie.php' : 'connexion.php') ?>" style="font-size:13px;color:var(--primary);font-weight:600">
        <i class="bi bi-arrow-repeat"></i> Renvoyer un lien
      </a>
      <a href="/reussiteplus/connexion.php" style="font-size:13px;color:var(--gris-600)">
        <i class="bi bi-arrow-left"></i> Retour à la connexion
      </a>
    </div>
  </div>

  <?php else: ?>
  <!-- ── Formulaire ── -->
  <span class="form-eyebrow"><i class="bi bi-shield-lock"></i> Sécurité du compte</span>
  <h2 class="form-title">Mot de passe oublié ?</h2>
  <p class="form-desc">Saisissez l'adresse email de votre compte. Nous vous enverrons un lien pour créer un nouveau mot de passe.</p>

  <?php if ($errors): ?>
  <div class="alert-error">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <div><?= implode('<br>', array_map('e', $errors)) ?></div>
  </div>
  <?php endif; ?>

  <form method="POST" novalidate id="resetForm">
    <?= csrf_field() ?>
    <div class="form-group">
      <label class="form-label" for="email">Adresse email</label>
      <div class="input-wrap">
        <i class="bi bi-envelope input-icon"></i>
        <input type="email" name="email" id="email" class="form-control"
          placeholder="votre@email.com" value="<?= e($_POST['email'] ?? '') ?>"
          required autofocus autocomplete="email">
      </div>
    </div>
    <button type="submit" class="btn-submit" id="submitBtn">
      <i class="bi bi-send-fill"></i> Envoyer le lien
    </button>
  </form>

  <?php endif; ?>

  <div class="form-footer">
    <a href="/reussiteplus/connexion.php"><i class="bi bi-arrow-left"></i> Retour à la connexion</a>
    &nbsp;·&nbsp;
    <a href="/reussiteplus/inscription.php">Créer un compte</a>
  </div>
</div>

<script>
document.getElementById('resetForm')?.addEventListener('submit', function() {
  const btn = document.getElementById('submitBtn');
  btn.disabled = true;
  btn.innerHTML = '<span style="display:inline-block;width:16px;height:16px;border:2px solid #ffffff55;border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite"></span> Envoi en cours…';
});
const s = document.createElement('style');
s.textContent = '@keyframes spin{to{transform:rotate(360deg)}}';
document.head.appendChild(s);
</script>
</body>
</html>
