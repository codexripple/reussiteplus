<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/rate_limit.php';

// Déjà connecté enseignant → dashboard
if (is_logged()) {
    $u = current_user();
    if ($u['role'] === 'ENSEIGNANT') {
        redirect('/reussiteplus/enseignant/dashboard.php');
    }
}

$errors    = [];
$html_error= '';
$email_val = '';

// Compteur de tentatives non-enseignant en session
if (!isset($_SESSION['ens_portail_tentatives'])) {
    $_SESSION['ens_portail_tentatives'] = 0;
}
$MAX_TENTATIVES = 3;

// Si déjà trop de tentatives → rediriger immédiatement
if ($_SESSION['ens_portail_tentatives'] >= $MAX_TENTATIVES) {
    $_SESSION['ens_portail_tentatives'] = 0;
    session_unset(); session_destroy(); session_start();
    header('Location: /reussiteplus/index.php?alerte=portail_enseignant');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!rate_limit_check('ens_login', $ip, 5, 600)) {
        $errors[] = 'Trop de tentatives. Réessayez dans quelques minutes.';
    } elseif (!csrf_verify()) {
        $errors[] = 'Token de sécurité invalide.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';
        $result = auth_login($email, $pass);

        if ($result['ok']) {
            $role = $result['user']['role'] ?? '';
            if ($role === 'ENSEIGNANT') {
                $_SESSION['ens_portail_tentatives'] = 0; // reset
                $ens = dbRow("SELECT statut_compte FROM enseignants_ecole WHERE user_id=?", [$result['user']['id']]);
                if ($ens && ($ens['statut_compte'] ?? 'EN_ATTENTE') !== 'VALIDE') {
                    session_unset(); session_destroy(); session_start();
                    $html_error = 'Votre compte est en attente de validation par la direction. Contactez votre directeur d\'école.';
                    $errors[] = '';
                } else {
                    redirect('/reussiteplus/enseignant/dashboard.php');
                }
            } elseif (in_array($role, ['SUPER_ADMIN','ADMIN','MODERATEUR'])) {
                $_SESSION['ens_portail_tentatives'] = 0;
                redirect('/reussiteplus/admin/index.php');
            } else {
                // Élève — incrémenter le compteur
                session_unset(); session_destroy(); session_start();
                $_SESSION['ens_portail_tentatives'] = ($_SESSION['ens_portail_tentatives'] ?? 0) + 1;
                $restantes = $MAX_TENTATIVES - $_SESSION['ens_portail_tentatives'];

                if ($_SESSION['ens_portail_tentatives'] >= $MAX_TENTATIVES) {
                    // Dernière tentative — rediriger à la prochaine requête
                    $html_error = 'Accès définitivement refusé. Vous serez redirigé automatiquement.';
                    header('Refresh: 2; url=/reussiteplus/index.php?alerte=portail_enseignant');
                } else {
                    $html_error = 'Accès refusé. Ce portail est <strong>exclusivement réservé aux enseignants</strong>. '
                        . '<br>Tentatives restantes : <strong style="color:#C9342A">' . $restantes . '</strong>. '
                        . 'Au-delà, votre compte sera signalé et pourra être <strong>supprimé</strong>. '
                        . '<br><a href="/reussiteplus/connexion.php" style="color:#1E5FAD;font-weight:700">Aller au portail élève</a>';
                }
                $errors[] = '';
            }
        } else {
            $errors[] = $result['msg'];
        }
    }
    $email_val = htmlspecialchars($_POST['email'] ?? '');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Espace Enseignant — RÉUSSITE+</title>
<link rel="icon" type="image/svg+xml" href="/reussiteplus/assets/img/favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;-webkit-font-smoothing:antialiased;}
body{font-family:'Manrope',system-ui,sans-serif;display:flex;min-height:100vh;background:#0C0F0D;}
a{text-decoration:none;color:inherit;}

.page{display:flex;min-height:100vh;width:100%;}

/* Panneau gauche */
.left{
  flex:1;min-height:100vh;position:relative;
  display:flex;flex-direction:column;justify-content:space-between;
  padding:48px 56px;overflow:hidden;background:#0C0F0D;
}
.left-photo{
  position:absolute;inset:0;z-index:0;
  background:url('https://images.unsplash.com/photo-1580582932707-520aed937b7b?w=900&auto=format&q=80&fit=crop') center/cover no-repeat;
  opacity:.2;
}
.left-gradient{
  position:absolute;inset:0;z-index:1;
  background:
    radial-gradient(ellipse 90% 60% at 0% 0%,rgba(30,95,173,.45) 0%,transparent 60%),
    radial-gradient(ellipse 60% 50% at 100% 100%,rgba(0,122,94,.18) 0%,transparent 60%),
    linear-gradient(180deg,rgba(12,15,13,.55) 0%,rgba(12,15,13,.88) 100%);
  pointer-events:none;
}
.left-inner{position:relative;z-index:2;display:flex;flex-direction:column;height:100%;}
.left-logo{display:flex;align-items:center;gap:10px;margin-bottom:auto;padding-bottom:32px;}
.left-logo-text{font-size:19px;font-weight:800;color:#fff;letter-spacing:-.3px;}
.left-logo-text em{color:#C9972A;font-style:normal;}
.left-headline-wrap{flex:1;display:flex;flex-direction:column;justify-content:center;padding:20px 0 40px;}
.left-kicker{font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:rgba(30,95,173,.85);margin-bottom:20px;}
.left-headline{font-size:clamp(34px,4.5vw,60px);font-weight:900;color:#fff;line-height:1.05;letter-spacing:-.03em;margin-bottom:20px;}
.left-headline em{color:#C9972A;font-style:italic;}
.left-sub{font-size:15px;color:rgba(255,255,255,.45);line-height:1.75;max-width:340px;}
.left-features{margin-top:28px;display:flex;flex-direction:column;gap:12px;}
.left-feat{display:flex;align-items:center;gap:12px;font-size:13px;color:rgba(255,255,255,.7);}
.left-feat-icon{width:32px;height:32px;border-radius:8px;background:rgba(30,95,173,.2);border:1px solid rgba(30,95,173,.35);display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.left-feat-icon svg{stroke:#60A5FA;}

/* Panneau droit */
.right{
  width:480px;flex-shrink:0;background:#FAFAF8;
  display:flex;flex-direction:column;justify-content:center;
  padding:56px 52px;overflow-y:auto;min-height:100vh;
  background-image:radial-gradient(circle,rgba(30,95,173,.03) 1px,transparent 1px);
  background-size:22px 22px;
}
.form-eyebrow{display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:#1E5FAD;margin-bottom:14px;}
.form-eyebrow::before{content:'';display:block;width:16px;height:1.5px;background:#1E5FAD;border-radius:2px;}
.form-title{font-size:clamp(26px,3vw,36px);font-weight:800;color:#0D1117;line-height:1.1;letter-spacing:-.02em;margin-bottom:8px;}
.form-desc{font-size:14px;color:#6B7280;line-height:1.65;margin-bottom:32px;}
.error-box{background:#FEF0EF;border:1px solid rgba(201,52,42,.2);border-left:3px solid #C9342A;border-radius:8px;padding:14px 16px;margin-bottom:24px;font-size:13px;color:#7F1D1D;display:flex;align-items:flex-start;gap:10px;line-height:1.5;}
.error-box svg{width:16px;height:16px;stroke:#C9342A;flex-shrink:0;margin-top:1px;}
.field{margin-bottom:24px;}
.field-label{display:block;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#9CA3AF;margin-bottom:10px;}
.field-input{width:100%;background:transparent;border:none;border-bottom:2px solid #E2E8F0;padding:10px 0 12px;font-family:'Manrope',sans-serif;font-size:15px;font-weight:500;color:#0D1117;outline:none;transition:border-color .2s,background .2s;-webkit-appearance:none;border-radius:2px 2px 0 0;}
.field-input:focus{border-bottom-color:#1E5FAD;background:rgba(30,95,173,.025);}
.field-input.has-error{border-bottom-color:#C9342A;}
.field-input-wrap{position:relative;}
.field-input-wrap .field-input{padding-right:40px;}
.eye-btn{position:absolute;right:0;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;padding:6px;color:#C0C7D0;transition:color .2s;}
.eye-btn:hover{color:#6B7280;}
.eye-btn svg{width:18px;height:18px;stroke:currentColor;display:block;}
.btn-submit{width:100%;padding:16px 24px;background:#1E5FAD;color:#fff;border:none;border-radius:10px;font-family:'Manrope',sans-serif;font-size:15px;font-weight:700;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:10px;margin-top:8px;position:relative;overflow:hidden;}
.btn-submit:hover:not(:disabled){background:#1D4ED8;transform:translateY(-1px);box-shadow:0 8px 24px rgba(30,95,173,.3);}
.btn-submit:disabled{opacity:.5;cursor:not-allowed;transform:none;}
.btn-submit .spinner{display:none;width:16px;height:16px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;flex-shrink:0;}
.btn-submit.loading .spinner{display:block;}
.form-foot{text-align:center;margin-top:24px;font-size:13px;color:#9CA3AF;}
.form-foot a{color:#1E5FAD;font-weight:600;}
.form-foot a:hover{text-decoration:underline;}
.divider{display:flex;align-items:center;gap:12px;margin:20px 0;}
.divider-line{flex:1;height:1px;background:#E2E8F0;}
.divider-text{font-size:12px;color:#C0C7D0;}
@keyframes spin{to{transform:rotate(360deg)}}
@keyframes authShake{0%,100%{transform:translateX(0)}20%,60%{transform:translateX(-6px)}40%,80%{transform:translateX(6px)}}
.error-box{animation:authShake .4s cubic-bezier(.36,.07,.19,.97);}
@media(max-width:960px){.left{display:none;}.right{width:100%;padding:48px 28px;}}
@media(max-width:480px){.right{padding:40px 20px;}.form-title{font-size:26px;}}
</style>
</head>
<body>
<div class="page">

  <!-- Gauche -->
  <div class="left">
    <div class="left-photo"></div>
    <div class="left-gradient"></div>
    <div class="left-inner">
      <div class="left-logo">
        <img src="/reussiteplus/assets/img/logo-icon.svg" alt="RÉUSSITE+" width="30" height="30" style="border-radius:8px">
        <span class="left-logo-text">RÉUSSITE<em>+</em></span>
      </div>
      <div class="left-headline-wrap">
        <div class="left-kicker">Portail Enseignant · RDC</div>
        <h1 class="left-headline">Votre espace<br>pédagogique<br><em>dédié.</em></h1>
        <p class="left-sub">Accédez à vos classes, corrigez les devoirs, suivez vos élèves et consultez vos performances.</p>
        <div class="left-features">
          <?php foreach ([
            ['Mes classes &amp; élèves','<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
            ['Correction des devoirs','<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>'],
            ['Tableau de bord analytique','<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/>'],
            ['Espace salaires &amp; performance','<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>'],
          ] as [$label, $icon]): ?>
          <div class="left-feat">
            <div class="left-feat-icon">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><?= $icon ?></svg>
            </div>
            <?= $label ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Droite -->
  <div class="right">
    <div class="form-eyebrow">Portail enseignant</div>
    <h1 class="form-title">Connexion Enseignant</h1>
    <p class="form-desc">Accès réservé aux enseignants. Vos identifiants vous ont été fournis par votre directeur d'école.</p>

    <!-- Badge accès restreint -->
    <div style="display:flex;align-items:center;gap:8px;background:#EEF4FD;border:1px solid rgba(30,95,173,.2);border-radius:9px;padding:10px 13px;margin-bottom:20px">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#1E5FAD" stroke-width="2.5" stroke-linecap="round" style="flex-shrink:0"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
      <span style="font-size:12.5px;color:#1E3A5F;font-weight:600">Espace enseignants uniquement — les élèves ne peuvent pas se connecter ici</span>
    </div>

    <?php if ($errors): ?>
    <div class="error-box">
      <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      <span><?= $html_error ? $html_error : e($errors[0]) ?></span>
    </div>
    <?php endif; ?>

    <form method="POST" id="loginForm" novalidate>
      <?= csrf_field() ?>
      <div class="field">
        <label class="field-label" for="email">Adresse e-mail</label>
        <input class="field-input<?= $errors?' has-error':'' ?>" type="email" id="email" name="email"
               placeholder="vous@ecole.cd" value="<?= e($email_val) ?>"
               required autocomplete="username">
      </div>
      <div class="field">
        <label class="field-label" for="password">Mot de passe</label>
        <div class="field-input-wrap">
          <input class="field-input<?= $errors?' has-error':'' ?>" type="password" id="password" name="password"
                 placeholder="Votre mot de passe" required autocomplete="current-password">
          <button type="button" class="eye-btn" onclick="togglePwd()">
            <svg id="eye-on" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            <svg id="eye-off" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-submit" id="submitBtn">
        <span class="spinner"></span>
        <span id="btnLabel">Se connecter</span>
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
      </button>
    </form>

    <div class="divider"><div class="divider-line"></div><div class="divider-text">ou</div><div class="divider-line"></div></div>

    <div class="form-foot">
      <p style="margin-bottom:8px">Pas encore de compte ? Contactez votre directeur d'école pour obtenir vos identifiants.</p>
      <p><a href="/reussiteplus/connexion.php" style="color:#6B7280;display:inline-flex;align-items:center;gap:4px">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Portail élève
      </a></p>
    </div>
  </div>
</div>

<script>
document.getElementById('loginForm').addEventListener('submit', function() {
  const btn = document.getElementById('submitBtn');
  btn.classList.add('loading'); btn.disabled = true;
  document.getElementById('btnLabel').textContent = 'Connexion…';
});
function togglePwd() {
  const p = document.getElementById('password');
  p.type = p.type === 'password' ? 'text' : 'password';
  document.getElementById('eye-on').style.display  = p.type === 'password' ? '' : 'none';
  document.getElementById('eye-off').style.display = p.type === 'text'     ? '' : 'none';
}
document.addEventListener('DOMContentLoaded', () => document.getElementById('email')?.focus());
document.getElementById('email')?.addEventListener('keydown', e => {
  if (e.key === 'Enter') { e.preventDefault(); document.getElementById('password').focus(); }
});
</script>
</body>
</html>
