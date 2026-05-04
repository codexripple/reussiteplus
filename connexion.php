<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

// Rediriger si dÃ©jÃ  connectÃ©
if (is_logged()) { header('Location: /reussiteplus/dashboard.php'); exit; }

$errors = [];
$redirect = $_GET['redirect'] ?? '/reussiteplus/dashboard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { $errors[] = 'Token de sÃ©curitÃ© invalide. Rechargez la page.'; }
    else {
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
<title>Connexion â€” RÃ‰USSITE+</title>
<link rel="icon" type="image/svg+xml" href="/reussiteplus/assets/img/favicon.svg">
<link rel="stylesheet" href="/reussiteplus/assets/css/fonts.css">
<link rel="stylesheet" href="/reussiteplus/assets/css/bootstrap-icons.css">
<link rel="stylesheet" href="/reussiteplus/assets/css/bootstrap-icons.css">
<style>
:root {
  --primary:#007A5E;--primary-dark:#005A45;--primary-light:#00A97F;--primary-subtle:#E8F5F1;
  --gold:#C9972A;--rouge:#C9342A;--noir:#0D1117;--gris-900:#1C2433;--gris-700:#4A5568;
  --gris-600:#6B7280;--gris-400:#A0AEC0;--gris-200:#E2E8F0;--gris-100:#F1F5F9;--blanc:#FFFFFF;
  --font-display:'Poppins',sans-serif;--font-body:'Poppins',sans-serif;
  --radius:10px;--radius-lg:16px;--shadow-lg:0 8px 32px rgba(0,0,0,.12);
  --transition:200ms cubic-bezier(0.4,0,0.2,1);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:var(--font-body);background:var(--gris-100);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
.auth-wrap{width:100%;max-width:440px;}
.auth-logo{text-align:center;margin-bottom:32px;}
.auth-logo-icon{width:56px;height:56px;background:linear-gradient(135deg,var(--primary),var(--primary-light));border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:28px;margin:0 auto 12px;box-shadow:0 0 24px rgba(0,122,94,.25);}
.auth-logo-text{font-family:var(--font-display);font-size:26px;font-weight:800;color:var(--gris-900);}
.auth-logo-text span{color:var(--gold);}
.auth-sub{font-size:14px;color:var(--gris-600);margin-top:4px;}
.auth-card{background:var(--blanc);border-radius:var(--radius-lg);padding:36px;box-shadow:var(--shadow-lg);border:1px solid var(--gris-200);}
.auth-title{font-family:var(--font-display);font-size:22px;font-weight:800;color:var(--gris-900);margin-bottom:4px;}
.auth-desc{font-size:14px;color:var(--gris-600);margin-bottom:24px;}
.form-group{margin-bottom:16px;}
.form-label{display:block;font-size:13px;font-weight:600;color:var(--gris-700);margin-bottom:6px;}
.form-control{width:100%;padding:11px 14px;border:1px solid var(--gris-200);border-radius:var(--radius);font-family:var(--font-body);font-size:14px;color:var(--gris-900);background:var(--blanc);transition:var(--transition);outline:none;}
.form-control:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(0,122,94,.1);}
.form-control::placeholder{color:var(--gris-400);}
.password-wrap{position:relative;}
.password-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:16px;color:var(--gris-400);}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:12px 20px;border-radius:var(--radius);font-size:14px;font-weight:600;cursor:pointer;border:none;transition:var(--transition);font-family:var(--font-body);width:100%;}
.btn-primary{background:var(--primary);color:var(--blanc);}
.btn-primary:hover{background:var(--primary-dark);}
.btn-primary:disabled{opacity:.6;cursor:not-allowed;}
.error-box{background:#FEF0EF;border-left:4px solid var(--rouge);color:var(--rouge);padding:12px 14px;border-radius:8px;font-size:13px;margin-bottom:16px;}
.auth-footer{text-align:center;margin-top:20px;font-size:13px;color:var(--gris-600);}
.auth-footer a{color:var(--primary);font-weight:600;}
.divider{display:flex;align-items:center;gap:12px;margin:20px 0;}
.divider-line{flex:1;height:1px;background:var(--gris-200);}
.divider-text{font-size:12px;color:var(--gris-400);}
.social-google{display:flex;align-items:center;justify-content:center;gap:10px;padding:11px;border:1px solid var(--gris-200);border-radius:var(--radius);background:var(--blanc);font-size:14px;font-weight:500;color:var(--gris-700);cursor:pointer;transition:var(--transition);width:100%;text-decoration:none;}
.social-google:hover{background:var(--gris-100);}
</style>
</head>
<body>
<div class="auth-wrap">
  <div class="auth-logo">
    <img src="/reussiteplus/assets/img/logo.svg" alt="RÃ‰USSITE+" height="52" style="display:block;margin:0 auto 8px">
    <div class="auth-sub">Bienvenue sur RÃ‰USSITE+ â€” RDC</div>
  </div>

  <div class="auth-card">
    <div class="auth-title">Content de te revoir</div>
    <div class="auth-desc">Entre tes identifiants pour reprendre lÃ  oÃ¹ tu t'es arrÃªtÃ©.</div>

    <?php if ($errors): ?>
    <div class="error-box">âš ï¸ <?= e($errors[0]) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <?= csrf_field() ?>
      <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

      <div class="form-group">
        <label class="form-label" for="email">Adresse email</label>
        <input class="form-control" type="email" id="email" name="email" placeholder="vous@exemple.com"
               value="<?= e($_POST['email'] ?? '') ?>" required autocomplete="email">
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Mot de passe</label>
        <div class="password-wrap">
          <input class="form-control" type="password" id="password" name="password"
                 placeholder="Votre mot de passe" required autocomplete="current-password">
          <button type="button" class="password-toggle" onclick="togglePassword('password')">ðŸ‘ï¸</button>
        </div>
      </div>

      <div style="text-align:right;margin-bottom:20px">
        <a href="/reussiteplus/mot-de-passe.php" style="font-size:13px;color:var(--primary);font-weight:500">Mot de passe oubliÃ© ?</a>
      </div>

      <button type="submit" class="btn btn-primary" id="submitBtn">
        Se connecter â†’
      </button>
    </form>

    <div class="divider">
      <div class="divider-line"></div>
      <div class="divider-text">ou</div>
      <div class="divider-line"></div>
    </div>

    <div style="text-align:center;font-size:13px;color:var(--gris-600)">
      Compte de dÃ©mo : <strong>demo@reussiteplus.cd</strong> / <strong>Demo1234!</strong>
    </div>
  </div>

  <div class="auth-footer">
    Pas encore de compte ? <a href="/reussiteplus/inscription.php">CrÃ©er un compte gratuit</a>
  </div>
  <div class="auth-footer" style="margin-top:8px">
    <a href="/reussiteplus/index.php" style="color:var(--gris-500)">â† Retour Ã  l'accueil</a>
  </div>
</div>

<script>
function togglePassword(id) {
  const el = document.getElementById(id);
  el.type = el.type === 'password' ? 'text' : 'password';
}
document.querySelector('form').addEventListener('submit', function() {
  document.getElementById('submitBtn').disabled = true;
  document.getElementById('submitBtn').textContent = 'Connexion en cours...';
});
</script>
</body>
</html>

