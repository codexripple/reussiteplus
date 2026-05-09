<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/rate_limit.php';

// Rediriger si déjà connecté
if (is_logged()) { header('Location: /reussiteplus/dashboard.php'); exit; }

$errors     = [];
$html_error = '';
$redirect = safe_redirect($_GET['redirect'] ?? '/reussiteplus/dashboard.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!rate_limit_check('login', $ip, 5, 600)) {
        $errors[] = 'Trop de tentatives. Réessayez dans quelques minutes.';
    } elseif (!csrf_verify()) { $errors[] = 'Token de sécurité invalide. Rechargez la page.'; }
    else {
        $result = auth_login(
            trim($_POST['email'] ?? ''),
            $_POST['password'] ?? ''
        );
        if ($result['ok']) {
            $role = $result['user']['role'] ?? '';
            if ($role === 'ENSEIGNANT') {
                // Rediriger vers portail enseignant
                header('Location: /reussiteplus/enseignant/dashboard.php');
                exit;
            } elseif (in_array($role, ['SUPER_ADMIN', 'ADMIN', 'MODERATEUR'])) {
                session_unset();
                session_destroy();
                session_start();
                $html_error = 'Ce compte est réservé à l\'administration. Veuillez utiliser le <a href="/reussiteplus/admin/connexion.php" style="color:#4ade80;text-decoration:underline">portail administrateur</a>.';
                $errors[] = '';
            } else {
                $dest = $redirect;
                if ($dest === '/reussiteplus/dashboard.php') {
                    $examCount = dbRow("SELECT COUNT(*) as n FROM exam_sessions WHERE user_id=?", [$result['user']['id']])['n'] ?? 0;
                    if ((int)$examCount === 0) {
                        $dest = '/reussiteplus/dashboard.php?welcome=1';
                    }
                }
                header('Location: ' . $dest);
                exit;
            }
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
<title>Connexion — RÉUSSITE+</title>
<link rel="icon" type="image/svg+xml" href="/reussiteplus/assets/img/favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,700;0,9..144,800;0,9..144,900;1,9..144,400;1,9..144,700&family=Manrope:wght@300;400;500;600;700;800&family=Newsreader:ital,opsz,wght@1,6..72,400;1,6..72,500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;-webkit-font-smoothing:antialiased;}
body{font-family:'Manrope',sans-serif;display:flex;min-height:100vh;background:#0C0F0D;}
a{text-decoration:none;color:inherit;}

/* ── PAGE SPLIT ───────────────────────────────────────────── */
.page{display:flex;min-height:100vh;width:100%;}

/* ── PANNEAU GAUCHE ─────────────────────────────────────────── */
.left{
  flex:1;min-height:100vh;position:relative;
  display:flex;flex-direction:column;justify-content:space-between;
  padding:48px 56px;overflow:hidden;background:#0C0F0D;
}
.left-photo{
  position:absolute;inset:0;z-index:0;
  background:url('https://images.unsplash.com/photo-1522529599102-193c0d76b5b6?w=900&auto=format&q=80&fit=crop&crop=center') center/cover no-repeat;
  opacity:.28;
}
.left-gradient{
  position:absolute;inset:0;z-index:1;
  background:
    radial-gradient(ellipse 90% 60% at 0% 0%,rgba(0,122,94,.45) 0%,transparent 60%),
    radial-gradient(ellipse 60% 50% at 100% 100%,rgba(201,151,42,.18) 0%,transparent 60%),
    linear-gradient(180deg,rgba(12,15,13,.55) 0%,rgba(12,15,13,.85) 100%);
  pointer-events:none;
}

.left-inner{position:relative;z-index:2;display:flex;flex-direction:column;height:100%;}

/* Logo */
.left-logo{display:flex;align-items:center;gap:10px;margin-bottom:auto;padding-bottom:32px;}
.left-logo img{display:block;border-radius:9px;}
.left-logo-text{font-family:'Syne',sans-serif;font-size:18px;font-weight:800;color:#fff;letter-spacing:-.3px;}
.left-logo-text em{color:#C9972A;font-style:normal;}

/* Headline centrale */
.left-headline-wrap{flex:1;display:flex;flex-direction:column;justify-content:center;padding:20px 0 40px;}
.left-kicker{font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:rgba(0,169,127,.85);margin-bottom:20px;}
.left-headline{
  font-family:'Syne',sans-serif;
  font-size:clamp(38px,4.5vw,68px);
  font-weight:900;color:#fff;
  line-height:1.05;letter-spacing:-.03em;
  margin-bottom:24px;
}
.left-headline em{color:#C9972A;font-style:italic;}
.left-sub{
  font-size:15px;color:rgba(255,255,255,.45);
  line-height:1.75;max-width:320px;
  font-weight:400;
}

/* Quote témoignage */
.left-quote{
  margin-top:36px;padding:20px 0;
  border-top:1px solid rgba(255,255,255,.08);
}
.left-quote-text{
  font-family:'Syne',sans-serif;
  font-style:italic;font-size:15px;
  color:rgba(255,255,255,.65);line-height:1.7;
  margin-bottom:12px;
}
.left-quote-author{display:flex;align-items:center;gap:10px;}
.left-quote-avatar{
  width:32px;height:32px;border-radius:50%;overflow:hidden;flex-shrink:0;
  border:1.5px solid rgba(255,255,255,.2);
}
.left-quote-avatar img{width:100%;height:100%;object-fit:cover;}
.left-quote-name{font-size:12px;font-weight:700;color:rgba(255,255,255,.7);}
.left-quote-meta{font-size:11px;color:rgba(255,255,255,.35);margin-top:2px;}

/* Stats */
.left-stats{
  display:flex;gap:0;
  border-top:1px solid rgba(255,255,255,.07);
  padding-top:24px;
  position:relative;z-index:2;
}
.left-stat{flex:1;padding:0 20px;}
.left-stat:first-child{padding-left:0;}
.left-stat-num{
  font-family:'Syne',sans-serif;
  font-size:22px;font-weight:800;color:#fff;margin-bottom:4px;
}
.left-stat-label{font-size:11px;color:rgba(255,255,255,.35);font-weight:500;}
.left-stat-sep{width:1px;background:rgba(255,255,255,.07);align-self:stretch;}

/* ── PANNEAU DROIT ───────────────────────────────────────────── */
.right{
  width:480px;flex-shrink:0;
  background:#FAFAF8;
  display:flex;flex-direction:column;justify-content:center;
  padding:56px 52px;overflow-y:auto;min-height:100vh;
}

/* Logo mobile */
.right-logo-mobile{
  display:none;align-items:center;gap:10px;margin-bottom:36px;
}
.right-logo-mobile img{display:block;border-radius:8px;}
.right-logo-mobile span{font-family:'Syne',sans-serif;font-size:18px;font-weight:800;color:#0C0F0D;}
.right-logo-mobile em{color:#C9972A;font-style:normal;}

/* En-tête formulaire */
.form-eyebrow{
  display:inline-flex;align-items:center;gap:6px;
  font-size:11px;font-weight:700;letter-spacing:1.5px;
  text-transform:uppercase;color:#007A5E;
  margin-bottom:14px;
}
.form-eyebrow::before{content:'';display:block;width:16px;height:1.5px;background:#007A5E;border-radius:2px;}
.form-title{
  font-family:'Syne',sans-serif;
  font-size:clamp(28px,3vw,38px);font-weight:800;
  color:#0D1117;line-height:1.1;letter-spacing:-.02em;
  margin-bottom:8px;
}
.form-desc{
  font-size:14px;color:#6B7280;line-height:1.65;margin-bottom:36px;
}

/* Erreur */
.error-box{
  background:#FEF0EF;border:1px solid rgba(201,52,42,.2);
  border-left:3px solid #C9342A;border-radius:8px;
  padding:14px 16px;margin-bottom:28px;
  font-size:13px;color:#7F1D1D;display:flex;align-items:flex-start;gap:10px;line-height:1.5;
}
.error-box svg{width:16px;height:16px;stroke:#C9342A;flex-shrink:0;margin-top:1px;}

/* Champs underline style */
.field{margin-bottom:28px;}
.field-label{
  display:block;font-size:11px;font-weight:700;
  letter-spacing:1px;text-transform:uppercase;
  color:#9CA3AF;margin-bottom:10px;
}
.field-input{
  width:100%;background:transparent;border:none;
  border-bottom:1.5px solid #E2E8F0;
  padding:10px 0 12px;
  font-family:'Manrope',sans-serif;
  font-size:15px;font-weight:500;color:#0D1117;
  outline:none;transition:border-color 200ms;
  -webkit-appearance:none;
}
.field-input:focus{border-bottom-color:#007A5E;}
.field-input::placeholder{color:#C0C7D0;font-weight:400;}
.field-input.has-error{border-bottom-color:#C9342A;}

/* Mot de passe avec oeil */
.field-input-wrap{position:relative;}
.field-input-wrap .field-input{padding-right:40px;}
.eye-btn{
  position:absolute;right:0;top:50%;transform:translateY(-50%);
  background:none;border:none;cursor:pointer;padding:6px;
  color:#C0C7D0;transition:color 200ms;
}
.eye-btn:hover{color:#6B7280;}
.eye-btn svg{width:18px;height:18px;stroke:currentColor;display:block;}

/* Lien mot de passe oublié */
.forgot-link{
  display:block;text-align:right;font-size:12px;
  font-weight:600;color:#007A5E;margin-top:8px;transition:color 200ms;
}
.forgot-link:hover{color:#005A45;}

/* Bouton submit */
.btn-submit{
  width:100%;padding:16px 24px;
  background:#0D1117;color:#fff;
  border:none;border-radius:10px;
  font-family:'Manrope',sans-serif;
  font-size:15px;font-weight:700;letter-spacing:.2px;
  cursor:pointer;transition:all 200ms;
  display:flex;align-items:center;justify-content:center;gap:10px;
  margin-top:8px;
}
.btn-submit:hover:not(:disabled){background:#007A5E;transform:translateY(-1px);box-shadow:0 8px 24px rgba(0,122,94,.25);}
.btn-submit:disabled{opacity:.5;cursor:not-allowed;transform:none;box-shadow:none;}
.btn-submit svg{width:16px;height:16px;stroke:currentColor;transition:transform 200ms;}
.btn-submit:hover:not(:disabled) svg{transform:translateX(3px);}

/* Séparateur */
.divider{display:flex;align-items:center;gap:12px;margin:24px 0;}
.divider-line{flex:1;height:1px;background:#E2E8F0;}
.divider-text{font-size:12px;color:#C0C7D0;font-weight:500;}

/* Liens pied de page */
.form-foot{text-align:center;margin-top:28px;}
.form-foot p{font-size:13px;color:#9CA3AF;margin-bottom:12px;line-height:1.6;}
.form-foot a{color:#007A5E;font-weight:600;}
.form-foot a:hover{text-decoration:underline;}
.back-link{
  display:inline-flex;align-items:center;gap:5px;
  font-size:12px;color:#C0C7D0;font-weight:500;
  transition:color 200ms;
}
.back-link:hover{color:#6B7280;}
.back-link svg{width:13px;height:13px;stroke:currentColor;}

/* ── RESPONSIVE ─────────────────────────────────────────────── */
@media(max-width:960px){
  .left{display:none;}
  .right{width:100%;padding:48px 28px;background:#FAFAF8;min-height:100vh;justify-content:flex-start;padding-top:60px;}
  .right-logo-mobile{display:flex;}
}
@media(max-width:480px){
  .right{padding:40px 20px;padding-top:48px;}
  .form-title{font-size:28px;}
}

/* ── PREMIUM ANIMATIONS ── */
@keyframes authFadeUp    { from{opacity:0;transform:translateY(18px)} to{opacity:1;transform:translateY(0)} }
@keyframes authSlideIn   { from{opacity:0;transform:translateX(16px)} to{opacity:1;transform:translateX(0)} }
@keyframes authSpin      { to{transform:rotate(360deg)} }
@keyframes authShake     { 0%,100%{transform:translateX(0)} 20%,60%{transform:translateX(-6px)} 40%,80%{transform:translateX(6px)} }
@keyframes authPulse     { 0%,100%{opacity:1} 50%{opacity:.5} }

/* Entry animations */
.right { animation: authSlideIn .5s cubic-bezier(0.16,1,0.3,1); }
.form-eyebrow { animation: authFadeUp .5s cubic-bezier(0.16,1,0.3,1) .08s both; }
.form-title   { animation: authFadeUp .5s cubic-bezier(0.16,1,0.3,1) .14s both; }
.form-desc    { animation: authFadeUp .5s cubic-bezier(0.16,1,0.3,1) .2s both; }
form          { animation: authFadeUp .5s cubic-bezier(0.16,1,0.3,1) .25s both; }
.form-foot    { animation: authFadeUp .5s cubic-bezier(0.16,1,0.3,1) .32s both; }
.error-box    { animation: authShake .4s cubic-bezier(0.36,0.07,0.19,0.97); }

/* Right panel subtle pattern */
.right {
  background-color: #FAFAF8;
  background-image:
    radial-gradient(circle, rgba(0,122,94,.04) 1px, transparent 1px);
  background-size: 22px 22px;
}

/* ── ENHANCED INPUT FIELD ── */
.field-input {
  border-bottom-width: 2px !important;
  transition: border-color .2s, background .2s !important;
  border-radius: 2px 2px 0 0;
  padding-bottom: 10px !important;
}
.field-input:focus {
  background: rgba(0,122,94,.025) !important;
  border-bottom-color: #007A5E !important;
}
.field-input.has-error:focus { border-bottom-color: #C9342A !important; }

/* Animated underline effect */
.field-input-wrap::after, .field > .field-input::after {
  content: '';
  position: absolute;
  bottom: 0; left: 0;
  height: 2px; width: 0;
  background: #007A5E;
  transition: width .25s cubic-bezier(0.16,1,0.3,1);
  border-radius: 2px;
}

/* ── BUTTON PREMIUM ── */
.btn-submit {
  position: relative;
  overflow: hidden;
  letter-spacing: .3px !important;
}
.btn-submit::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, transparent 0%, rgba(255,255,255,.06) 100%);
  opacity: 0;
  transition: opacity .2s;
}
.btn-submit:hover:not(:disabled)::before { opacity: 1; }
.btn-submit .spinner {
  display: none;
  width: 16px; height: 16px;
  border: 2px solid rgba(255,255,255,.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: authSpin .7s linear infinite;
  flex-shrink: 0;
}
.btn-submit.loading .spinner { display: block; }
.btn-submit.loading svg:last-child { display: none; }
.btn-submit.loading::after {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,.05) 50%, transparent 100%);
  background-size: 200% 100%;
  animation: authPulse 1.5s ease infinite;
}

/* Success state */
.btn-submit.success {
  background: #007A5E !important;
  pointer-events: none;
}

/* ── FIELD VALIDATION ICONS ── */
.field-input:valid:not(:placeholder-shown):not(.has-error) {
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2300A97F' stroke-width='2.5' stroke-linecap='round'%3E%3Cpolyline points='20 6 9 17 4 12'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 0 center;
  background-size: 16px;
  padding-right: 24px;
}

/* ── INPUT FOCUS GROUP ── */
.field:focus-within .field-label {
  color: #007A5E;
  transition: color .2s;
}

/* ── BACK LINK ── */
.back-link {
  transition: color .2s, transform .2s !important;
}
.back-link:hover { transform: translateX(-3px) !important; }
</style>
</head>
<body>
<div class="page">

  <!-- ── PANNEAU GAUCHE ── -->
  <div class="left">
    <div class="left-photo"></div>
    <div class="left-gradient"></div>
    <div class="left-inner">

      <!-- Logo -->
      <div class="left-logo">
        <img src="/reussiteplus/assets/img/logo-icon.svg" alt="RÉUSSITE+" width="32" height="32">
        <span class="left-logo-text">RÉUSSITE<em>+</em></span>
      </div>

      <!-- Headline -->
      <div class="left-headline-wrap">
        <div class="left-kicker">Plateforme EdTech · RDC</div>
        <h1 class="left-headline">
          Reprends là<br>où tu t'es<br><em>arrêté.</em>
        </h1>
        <p class="left-sub">
          Tes scores, tes archives et tes révisions t'attendent exactement où tu les as laissés.
        </p>

        <!-- Quote -->
        <div class="left-quote">
          <div class="left-quote-text">
            "J'avais raté en 2023. J'ai commencé les QCM chaque soir depuis mon téléphone — 20 minutes. Quatre mois après : 74 %. C'est tout ce dont j'avais besoin."
          </div>
          <div class="left-quote-author">
            <div class="left-quote-avatar">
              <img src="https://images.unsplash.com/photo-1522529599102-193c0d76b5b6?w=64&auto=format&q=80&fit=crop&crop=center" alt="Kalombo">
            </div>
            <div>
              <div class="left-quote-name">Kalombo Mutombo</div>
              <div class="left-quote-meta">Lycée Roi Baudouin, Kinshasa · Exam. d'État 2024</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Stats -->
      <div class="left-stats">
        <div class="left-stat">
          <div class="left-stat-num">14 238</div>
          <div class="left-stat-label">Élèves inscrits</div>
        </div>
        <div class="left-stat-sep"></div>
        <div class="left-stat">
          <div class="left-stat-num">26</div>
          <div class="left-stat-label">Provinces RDC</div>
        </div>
        <div class="left-stat-sep"></div>
        <div class="left-stat">
          <div class="left-stat-num">2005</div>
          <div class="left-stat-label">Archives depuis</div>
        </div>
      </div>

    </div>
  </div>

  <!-- ── PANNEAU DROIT ── -->
  <div class="right">

    <!-- Logo mobile -->
    <div class="right-logo-mobile">
      <img src="/reussiteplus/assets/img/logo-icon.svg" alt="RÉUSSITE+" width="30" height="30">
      <span>RÉUSSITE<em style="color:#C9972A;font-style:normal;">+</em></span>
    </div>

    <div class="form-eyebrow">Connexion sécurisée</div>
    <h1 class="form-title">Bon retour.</h1>
    <p class="form-desc">Tes révisions et tes archives t'attendent.<br>Reprends là où tu t'es arrêté.</p>

    <?php if ($errors): ?>
    <div class="error-box">
      <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      <span><?= $html_error ? $html_error : e($errors[0]) ?></span>
    </div>
    <?php endif; ?>

    <form method="POST" action="" novalidate id="loginForm">
      <?= csrf_field() ?>
      <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

      <!-- Email -->
      <div class="field">
        <label class="field-label" for="email">Adresse e-mail</label>
        <input class="field-input<?= $errors ? ' has-error' : '' ?>"
               type="email" id="email" name="email"
               placeholder="vous@exemple.com"
               value="<?= e($_POST['email'] ?? '') ?>"
               required autocomplete="email">
      </div>

      <!-- Mot de passe -->
      <div class="field">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
          <label class="field-label" for="password" style="margin-bottom:0">Mot de passe</label>
          <a href="/reussiteplus/mot_de_passe_oublie.php" class="forgot-link" style="margin-top:0;text-align:left;display:inline;font-size:12px">Oublié ?</a>
        </div>
        <div class="field-input-wrap">
          <input class="field-input<?= $errors ? ' has-error' : '' ?>"
                 type="password" id="password" name="password"
                 placeholder="Votre mot de passe"
                 required autocomplete="current-password">
          <button type="button" class="eye-btn" id="eyeBtn" aria-label="Afficher le mot de passe" onclick="toggleEye()">
            <svg id="iconEyeOn" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            <svg id="iconEyeOff" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-submit" id="submitBtn">
        <span class="spinner"></span>
        <span id="btnLabel">Me connecter</span>
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
      </button>
    </form>

    <div class="form-foot">
      <p>Pas encore de compte ? <a href="/reussiteplus/inscription.php">Créer un compte gratuit</a></p>
      <a href="/reussiteplus/index.php" class="back-link">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Retour à l'accueil
      </a>
    </div>

  </div>
</div>

<script>
let passVisible = false;
function toggleEye() {
  const input = document.getElementById('password');
  passVisible = !passVisible;
  input.type = passVisible ? 'text' : 'password';
  document.getElementById('iconEyeOn').style.display  = passVisible ? 'none' : '';
  document.getElementById('iconEyeOff').style.display = passVisible ? '' : 'none';
}

// ── Password toggle ──────────────────────────────────────────
let passVisible = false;
function toggleEye() {
  const input = document.getElementById('password');
  passVisible = !passVisible;
  input.type = passVisible ? 'text' : 'password';
  document.getElementById('iconEyeOn').style.display  = passVisible ? 'none' : '';
  document.getElementById('iconEyeOff').style.display = passVisible ? '' : 'none';
}

// ── Form submit — loading state ───────────────────────────────
document.getElementById('loginForm').addEventListener('submit', function(e) {
  const btn = document.getElementById('submitBtn');
  const lbl = document.getElementById('btnLabel');
  const email = document.getElementById('email').value.trim();
  const pass  = document.getElementById('password').value;

  // Client-side quick validation
  if (!email || !pass) { e.preventDefault(); return; }

  btn.classList.add('loading');
  btn.disabled = true;
  lbl.textContent = 'Connexion en cours…';
});

// ── Auto-focus email ──────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  const email = document.getElementById('email');
  if (email && !email.value) email.focus();
});

// ── Enter key on email → jump to password ────────────────────
document.getElementById('email')?.addEventListener('keydown', e => {
  if (e.key === 'Enter') { e.preventDefault(); document.getElementById('password').focus(); }
});
</script>
</body>
</html>
