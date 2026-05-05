<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

if (is_logged()) { header('Location: /reussiteplus/dashboard.php'); exit; }

$token   = trim($_GET['token'] ?? $_POST['token'] ?? '');
$email   = strtolower(trim($_GET['email'] ?? $_POST['email'] ?? ''));
$errors  = [];
$success = false;
$invalid = false;

// Valider le token au chargement de la page
if (!$token || !$email) {
    $invalid = true;
} else {
    $check = dbRow(
        "SELECT id, prenom, token_reset, token_reset_expire FROM utilisateurs
         WHERE email = ? AND is_active = 1 AND token_reset IS NOT NULL",
        [$email]
    );
    if (!$check) {
        $invalid = true;
    } elseif (strtotime($check['token_reset_expire']) < time()) {
        $invalid = true;
    } elseif (!hash_equals($check['token_reset'], hash('sha256', $token))) {
        $invalid = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$invalid) {
    if (!csrf_verify()) {
        $errors[] = 'Token de sécurité invalide. Rechargez la page.';
    } else {
        $newPass     = $_POST['password'] ?? '';
        $newPassConf = $_POST['password_confirm'] ?? '';
        if ($newPass !== $newPassConf) {
            $errors[] = 'Les deux mots de passe ne correspondent pas.';
        } elseif (strlen($newPass) < 8) {
            $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
        } else {
            $result = auth_confirm_password_reset($email, $token, $newPass);
            if ($result['ok']) {
                $success = true;
            } else {
                $errors[] = $result['msg'];
                if (in_array($result['msg'], ['Lien invalide ou expiré.', 'Ce lien a expiré. Faites une nouvelle demande.', 'Lien invalide.'])) {
                    $invalid = true;
                }
            }
        }
    }
}

$prenom = $check['prenom'] ?? '';
$csrf   = csrf_token();
?>
<!DOCTYPE html>
<html lang="fr" id="htmlRoot">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nouveau mot de passe — RÉUSSITE+</title>
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
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;}
body{font-family:var(--font-body);display:flex;min-height:100vh;background:var(--gris-50);align-items:center;justify-content:center;}
.card-wrap{width:100%;max-width:460px;background:var(--blanc);border-radius:16px;box-shadow:0 8px 40px rgba(0,0,0,.12);padding:48px 44px;margin:20px;}
.form-eyebrow{font-size:11px;font-weight:700;color:var(--primary);text-transform:uppercase;letter-spacing:1.8px;margin-bottom:10px;display:block;}
.form-title{font-family:var(--font-display);font-size:24px;font-weight:800;color:var(--gris-900);line-height:1.2;margin-bottom:8px;}
.form-desc{font-size:13px;color:var(--gris-600);line-height:1.7;margin-bottom:28px;}
.form-group{margin-bottom:18px;}
.form-label{display:block;font-size:13px;font-weight:600;color:var(--gris-700);margin-bottom:7px;}
.input-wrap{position:relative;}
.input-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--gris-400);font-size:16px;pointer-events:none;}
.form-control{width:100%;padding:13px 44px 13px 42px;border:1.5px solid var(--gris-200);border-radius:var(--radius);font-family:var(--font-body);font-size:14px;color:var(--gris-900);background:var(--blanc);outline:none;transition:var(--transition);}
.form-control:focus{border-color:var(--primary);box-shadow:0 0 0 4px rgba(0,122,94,0.1);}
.password-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--gris-400);font-size:16px;padding:4px;transition:var(--transition);}
.password-toggle:hover{color:var(--gris-700);}
.strength-bar{height:4px;border-radius:2px;margin-top:6px;background:var(--gris-200);overflow:hidden;}
.strength-fill{height:100%;border-radius:2px;transition:width .4s,background .4s;}
.strength-label{font-size:11px;color:var(--gris-500);margin-top:4px;}
.btn-submit{width:100%;padding:14px;border-radius:var(--radius);background:var(--primary);color:white;font-family:var(--font-body);font-size:15px;font-weight:700;border:none;cursor:pointer;transition:var(--transition);display:flex;align-items:center;justify-content:center;gap:8px;margin-top:8px;}
.btn-submit:hover{background:var(--primary-dark);transform:translateY(-1px);box-shadow:0 6px 20px rgba(0,122,94,0.32);}
.btn-submit:disabled{opacity:.6;cursor:not-allowed;transform:none;}
.alert-error{background:#FEF0EF;border:1px solid rgba(201,52,42,0.2);border-left:4px solid var(--rouge);color:#7F1D1D;padding:12px 16px;border-radius:var(--radius);font-size:13px;margin-bottom:20px;display:flex;align-items:flex-start;gap:10px;}
.alert-error i{font-size:16px;color:var(--rouge);flex-shrink:0;margin-top:1px;}
.success-icon{width:72px;height:72px;border-radius:50%;background:rgba(0,122,94,0.12);display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:32px;color:var(--primary);}
.invalid-icon{width:72px;height:72px;border-radius:50%;background:rgba(201,52,42,0.1);display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:32px;color:var(--rouge);}
.logo-wrap{text-align:center;margin-bottom:28px;}
.req{font-size:11px;color:var(--gris-500);margin-top:4px;}
.req-item{display:inline-flex;align-items:center;gap:4px;margin-right:10px;}
[data-theme="dark"]{--blanc:#1E293B;--gris-50:#0F172A;--gris-100:#1E293B;--gris-200:#334155;--gris-400:#64748B;--gris-600:#CBD5E1;--gris-700:#E2E8F0;--gris-900:#F8FAFC;}
[data-theme="dark"] body{background:var(--gris-50);}
[data-theme="dark"] .card-wrap{background:var(--blanc);}
[data-theme="dark"] .form-control{background:var(--gris-100);border-color:var(--gris-200);color:var(--gris-900);}
</style>
<script>(function(){var t=localStorage.getItem('rp-theme');var p=window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches;document.getElementById('htmlRoot').setAttribute('data-theme',t||(p?'dark':'light'));})()</script>
</head>
<body>
<div class="card-wrap">
  <div class="logo-wrap">
    <img src="/reussiteplus/assets/img/logo-icon.svg" height="44" alt="RÉUSSITE+">
  </div>

  <?php if ($invalid): ?>
  <!-- ── Lien invalide ── -->
  <div style="text-align:center">
    <div class="invalid-icon"><i class="bi bi-link-45deg"></i></div>
    <div style="font-size:20px;font-weight:800;color:var(--gris-900);margin-bottom:8px">Lien invalide ou expiré</div>
    <p style="font-size:13px;color:var(--gris-600);line-height:1.7;margin-bottom:24px">
      Ce lien de réinitialisation est invalide ou a expiré (validité : 1 heure).<br>
      Faites une nouvelle demande.
    </p>
    <a href="/reussiteplus/mot_de_passe_oublie.php" style="display:inline-flex;align-items:center;gap:8px;background:var(--primary);color:#fff;padding:12px 24px;border-radius:var(--radius);font-weight:700;font-size:14px;text-decoration:none">
      <i class="bi bi-arrow-repeat"></i> Nouvelle demande
    </a>
    <div style="margin-top:16px"><a href="/reussiteplus/connexion.php" style="font-size:13px;color:var(--gris-500)"><i class="bi bi-arrow-left"></i> Retour à la connexion</a></div>
  </div>

  <?php elseif ($success): ?>
  <!-- ── Succès ── -->
  <div style="text-align:center">
    <div class="success-icon"><i class="bi bi-check-circle-fill"></i></div>
    <div style="font-size:20px;font-weight:800;color:var(--gris-900);margin-bottom:8px">
      <?= $prenom ? 'Parfait, ' . e($prenom) . ' !' : 'Mot de passe modifié !' ?>
    </div>
    <p style="font-size:13px;color:var(--gris-600);line-height:1.7;margin-bottom:24px">
      Votre mot de passe a été mis à jour avec succès.<br>
      Vous pouvez maintenant vous connecter avec votre nouveau mot de passe.
    </p>
    <a href="/reussiteplus/connexion.php" style="display:inline-flex;align-items:center;gap:8px;background:var(--primary);color:#fff;padding:12px 24px;border-radius:var(--radius);font-weight:700;font-size:14px;text-decoration:none">
      <i class="bi bi-box-arrow-in-right"></i> Se connecter
    </a>
  </div>

  <?php else: ?>
  <!-- ── Formulaire ── -->
  <span class="form-eyebrow"><i class="bi bi-key"></i> Nouveau mot de passe</span>
  <h2 class="form-title">Choisissez un nouveau mot de passe<?= $prenom ? ', ' . e($prenom) : '' ?></h2>
  <p class="form-desc">Créez un mot de passe fort et mémorable. Il doit contenir au moins 8 caractères.</p>

  <?php if ($errors): ?>
  <div class="alert-error">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <div><?= implode('<br>', array_map('e', $errors)) ?></div>
  </div>
  <?php endif; ?>

  <form method="POST" id="pwdForm" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <input type="hidden" name="email" value="<?= e($email) ?>">

    <div class="form-group">
      <label class="form-label" for="password">Nouveau mot de passe</label>
      <div class="input-wrap">
        <i class="bi bi-lock input-icon"></i>
        <input type="password" name="password" id="password" class="form-control"
          placeholder="Minimum 8 caractères" required autocomplete="new-password">
        <button type="button" class="password-toggle" onclick="togglePwd('password','eyeIcon1')">
          <i class="bi bi-eye" id="eyeIcon1"></i>
        </button>
      </div>
      <div class="strength-bar"><div id="strengthFill" class="strength-fill" style="width:0%"></div></div>
      <div id="strengthLabel" class="strength-label">Entrez votre mot de passe</div>
      <div class="req">
        <span class="req-item" id="req-len"><i class="bi bi-circle" style="font-size:9px"></i> 8 caractères min</span>
        <span class="req-item" id="req-num"><i class="bi bi-circle" style="font-size:9px"></i> Un chiffre</span>
        <span class="req-item" id="req-upper"><i class="bi bi-circle" style="font-size:9px"></i> Une majuscule</span>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label" for="password_confirm">Confirmer le mot de passe</label>
      <div class="input-wrap">
        <i class="bi bi-lock-fill input-icon"></i>
        <input type="password" name="password_confirm" id="password_confirm" class="form-control"
          placeholder="Répétez le mot de passe" required autocomplete="new-password">
        <button type="button" class="password-toggle" onclick="togglePwd('password_confirm','eyeIcon2')">
          <i class="bi bi-eye" id="eyeIcon2"></i>
        </button>
      </div>
      <div id="matchMsg" style="font-size:11px;margin-top:4px"></div>
    </div>

    <button type="submit" class="btn-submit" id="submitBtn">
      <i class="bi bi-shield-check"></i> Enregistrer le nouveau mot de passe
    </button>
  </form>

  <div style="margin-top:20px;text-align:center">
    <a href="/reussiteplus/connexion.php" style="font-size:13px;color:var(--gris-500)"><i class="bi bi-arrow-left"></i> Retour à la connexion</a>
  </div>
  <?php endif; ?>
</div>

<script>
function togglePwd(id, iconId) {
  const inp = document.getElementById(id);
  const ico = document.getElementById(iconId);
  inp.type = inp.type === 'password' ? 'text' : 'password';
  ico.className = inp.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}

// Force du mot de passe
const pwdInput = document.getElementById('password');
const matchInput = document.getElementById('password_confirm');
if (pwdInput) {
  pwdInput.addEventListener('input', function() {
    const v = this.value;
    const hasLen   = v.length >= 8;
    const hasNum   = /\d/.test(v);
    const hasUpper = /[A-Z]/.test(v);
    const hasSpec  = /[^a-zA-Z0-9]/.test(v);
    const score    = [hasLen, hasNum, hasUpper, hasSpec].filter(Boolean).length;
    const fill     = document.getElementById('strengthFill');
    const label    = document.getElementById('strengthLabel');
    const colors   = ['', '#ef4444', '#f59e0b', '#3b82f6', '#22c55e'];
    const labels   = ['', 'Très faible', 'Faible', 'Bon', 'Excellent'];
    fill.style.width     = (score * 25) + '%';
    fill.style.background = colors[score] || '#e2e8f0';
    label.textContent    = v.length ? labels[score] : 'Entrez votre mot de passe';
    label.style.color    = colors[score] || 'var(--gris-500)';
    // Indicateurs exigences
    function setReq(id, ok) {
      const el = document.getElementById(id);
      if (!el) return;
      el.style.color = ok ? '#22c55e' : 'var(--gris-500)';
      el.querySelector('i').className = ok ? 'bi bi-check-circle-fill' : 'bi bi-circle';
    }
    setReq('req-len',   hasLen);
    setReq('req-num',   hasNum);
    setReq('req-upper', hasUpper);
    checkMatch();
  });
}
if (matchInput) {
  matchInput.addEventListener('input', checkMatch);
}
function checkMatch() {
  const msg = document.getElementById('matchMsg');
  if (!matchInput || !pwdInput || !msg) return;
  if (!matchInput.value) { msg.textContent = ''; return; }
  if (matchInput.value === pwdInput.value) {
    msg.innerHTML = '<span style="color:#22c55e"><i class="bi bi-check-circle-fill"></i> Les mots de passe correspondent</span>';
  } else {
    msg.innerHTML = '<span style="color:#ef4444"><i class="bi bi-x-circle-fill"></i> Les mots de passe ne correspondent pas</span>';
  }
}

document.getElementById('pwdForm')?.addEventListener('submit', function(e) {
  const pw1 = document.getElementById('password')?.value;
  const pw2 = document.getElementById('password_confirm')?.value;
  if (pw1 !== pw2) { e.preventDefault(); document.getElementById('matchMsg').innerHTML = '<span style="color:#ef4444"><i class="bi bi-x-circle-fill"></i> Les mots de passe ne correspondent pas</span>'; return; }
  const btn = document.getElementById('submitBtn');
  btn.disabled = true;
  btn.innerHTML = '<span style="display:inline-block;width:16px;height:16px;border:2px solid #ffffff55;border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite"></span> Enregistrement…';
});
const s = document.createElement('style');
s.textContent = '@keyframes spin{to{transform:rotate(360deg)}}';
document.head.appendChild(s);
</script>
</body>
</html>
