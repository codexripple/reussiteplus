<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/rate_limit.php';

if (is_logged()) { header('Location: /reussiteplus/dashboard.php'); exit; }

$errors = [];
$provinces = [];
try { $provinces = dbAll("SELECT id, nom FROM provinces ORDER BY nom"); } catch (Exception $e) {}

// Récupérer code referral depuis URL
$refCode = trim($_GET['ref'] ?? '');
$referralUser = null;
if ($refCode) {
    try {
        $referralUser = dbRow("SELECT id, prenom, nom FROM utilisateurs WHERE referral_code = ?", [$refCode]);
    } catch (Exception $e) {}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!rate_limit_check('register', $ip, 3, 1800)) {
        $errors[] = 'Trop de tentatives d’inscription. Réessayez plus tard.';
    } elseif (!csrf_verify()) {
        $errors[] https://www.ethnocia.c= 'Token de sécurité invalide.';
    } else {
        $nom    = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email  = trim($_POST['email'] ?? '');
        $pass   = $_POST['password'] ?? '';
        $classe = trim($_POST['classe'] ?? '');
        $provId = $_POST['province_id'] ?? null;

        if (empty($nom) || empty($prenom)) $errors[] = 'Le nom et prénom sont requis.';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide.';
        if (strlen($pass) < 8) $errors[] = 'Mot de passe : minimum 8 caractères.';
        if ($_POST['password_confirm'] !== $pass) $errors[] = 'Les mots de passe ne correspondent pas.';
        if (empty($_POST['cgv'])) $errors[] = 'Veuillez accepter les conditions d\'utilisation.';

        if (!$errors) {
            $refParId = $referralUser ? $referralUser['id'] : null;
            $result = auth_register([
                'nom'         => $nom,
                'prenom'      => $prenom,
                'email'       => $email,
                'password'    => $pass,
                'classe'      => $classe,
                'province_id' => $provId ?: null,
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
<title>Inscription gratuite — RÉUSSITE+</title>
<link rel="icon" type="image/svg+xml" href="/reussiteplus/assets/img/favicon.svg">
<link rel="shortcut icon" href="/reussiteplus/assets/img/favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<script>(function(){try{var t=localStorage.getItem('rp-theme');if(t==='dark')document.getElementById('htmlRoot').setAttribute('data-theme','dark');}catch(e){}}());</script>
<style>
/* ── Tokens ─────────────────────────────────────────────── */
:root{
  --pr:#007A5E;--pr-dk:#005A45;--pr-lt:#00A97F;--pr-sub:#E8F5F1;
  --gold:#C9972A;--rouge:#C9342A;--rouge-sub:#FEF0EF;
  --n900:#1C2433;--n800:#2E3A4A;--n700:#4A5568;--n600:#6B7280;
  --n500:#6B7280;--n400:#A0AEC0;--n300:#CBD5E1;--n200:#E2E8F0;--n100:#F1F5F9;--white:#FFFFFF;
  --ff-display:'Poppins',sans-serif;--ff-body:'Inter',sans-serif;
  --r:10px;--r-lg:18px;
  --shadow:0 4px 16px rgba(0,0,0,.08);--shadow-lg:0 12px 40px rgba(0,0,0,.12);
  --ease:200ms cubic-bezier(.4,0,.2,1);
}
[data-theme="dark"]{
  --n900:#F1F5F9;--n800:#E2E8F0;--n700:#CBD5E1;--n600:#94A3B8;
  --n500:#64748B;--n400:#475569;--n300:#334155;--n200:#1E293B;--n100:#0F172A;--white:#1E293B;
  --pr-sub:rgba(0,122,94,.15);--rouge-sub:rgba(201,52,42,.1);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;}
body{font-family:'Manrope',sans-serif;background:var(--n100);color:var(--n900);line-height:1.6;}

/* ── Layout ─────────────────────────────────────────────── */
.page{display:grid;grid-template-columns:1fr 1.35fr;min-height:100vh;}

/* ── Panneau gauche ─────────────────────────────────────── */
.left{
  background:#0D1117;
  padding:48px 44px;display:flex;flex-direction:column;
  position:sticky;top:0;height:100vh;overflow:hidden;
}
.left-photo{
  position:absolute;inset:0;
  background:url('/reussiteplus/assets/img/hero-students.jpg') center/cover no-repeat;
  opacity:.18;
}
.left::before{
  content:'';position:absolute;inset:0;
  background:radial-gradient(ellipse 80% 60% at 20% 10%,rgba(0,122,94,0.45) 0%,transparent 55%),
             radial-gradient(ellipse 60% 50% at 80% 90%,rgba(201,151,42,0.15) 0%,transparent 55%);
  pointer-events:none;
}
.brand{position:relative;display:flex;align-items:center;gap:12px;margin-bottom:auto;}
.brand-mark{
  width:44px;height:44px;border-radius:12px;
  background:linear-gradient(135deg,rgba(255,255,255,.25),rgba(255,255,255,.1));
  border:1px solid rgba(255,255,255,.2);
  display:flex;align-items:center;justify-content:center;
}
.brand-name{font-family:'Syne',sans-serif;font-size:20px;font-weight:800;color:#fff;letter-spacing:-.3px;}
.brand-name span{color:#FBBF24;}

.left-content{position:relative;flex:1;display:flex;flex-direction:column;justify-content:center;padding:32px 0;}
.left-headline{font-family:'Manrope',sans-serif;font-size:clamp(26px,3vw,38px);font-weight:900;color:white;line-height:1.15;margin-bottom:16px;}
.left-headline span{color:#FBBF24;}
.left-sub{font-size:14px;color:rgba(255,255,255,.55);line-height:1.7;max-width:340px;margin-bottom:32px;}
/* Steps */
.steps{display:flex;flex-direction:column;gap:16px;}
.step{display:flex;align-items:flex-start;gap:14px;}
.step-num{width:32px;height:32px;border-radius:50%;background:rgba(0,122,94,0.3);border:1px solid rgba(0,122,94,0.5);color:#00C896;font-weight:800;font-size:13px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.step-text{font-size:13px;color:rgba(255,255,255,.65);line-height:1.5;padding-top:6px;}
.step-title{font-weight:700;color:white;display:block;margin-bottom:2px;}

.left-footer{position:relative;margin-top:auto;border-top:1px solid rgba(255,255,255,.1);padding-top:18px;}
.avatar-row{display:flex;align-items:center;gap:12px;}
.avatar-stack{display:flex;}
.avatar-stack div{
  width:28px;height:28px;border-radius:50%;border:2px solid rgba(0,61,46,1);
  margin-left:-7px;font-size:9px;font-weight:700;display:flex;align-items:center;justify-content:center;color:#fff;
}
.avatar-stack div:first-child{margin-left:0;}
.av1{background:#52B788;} .av2{background:#FBBF24;color:#7B2D00;} .av3{background:#60A5FA;color:#1E3A5F;} .av4{background:#F87171;color:#7F1D1D;}
.avatar-label{font-size:12px;color:rgba(255,255,255,.65);}
.avatar-label strong{color:#fff;}

/* ── Panneau droit ──────────────────────────────────────── */
.right{
  background:var(--white);overflow-y:auto;
  display:flex;flex-direction:column;align-items:center;
  padding:48px 52px;
}
[data-theme="dark"] .right{background:#0F172A;}

.right-inner{width:100%;max-width:480px;}

/* En-tête formulaire */
.form-header{margin-bottom:28px;}
.form-eyebrow{
  display:inline-flex;align-items:center;gap:6px;
  background:var(--pr-sub);color:var(--pr);
  font-size:11px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;
  padding:5px 12px;border-radius:20px;margin-bottom:14px;
}
.form-eyebrow svg{width:13px;height:13px;stroke:currentColor;}
.form-h1{font-family:'Manrope',sans-serif;font-size:28px;font-weight:900;color:var(--n900);line-height:1.15;margin-bottom:6px;letter-spacing:-.4px;}
.form-desc{font-size:15px;color:var(--n600);line-height:1.55;}

/* Alertes */
.error-alert{
  display:flex;align-items:flex-start;gap:10px;
  background:var(--rouge-sub);border:1px solid rgba(201,52,42,.2);
  border-radius:var(--r);padding:12px 14px;margin-bottom:20px;
}
.error-alert svg{width:16px;height:16px;stroke:var(--rouge);flex-shrink:0;margin-top:1px;}
.error-alert div{font-size:13px;color:var(--rouge);line-height:1.5;}
.error-alert ul{margin:4px 0 0 16px;}

.referral-alert{
  display:flex;align-items:center;gap:10px;
  background:var(--pr-sub);border:1px solid rgba(0,122,94,.2);
  border-radius:var(--r);padding:12px 14px;margin-bottom:16px;
}
.referral-alert svg{width:16px;height:16px;stroke:var(--pr);flex-shrink:0;}
.referral-alert p{font-size:13px;color:var(--pr);}

.free-pill{
  display:inline-flex;align-items:center;gap:7px;
  background:var(--pr-sub);border:1px solid rgba(0,122,94,.2);
  border-radius:20px;padding:7px 14px;margin-bottom:22px;
  font-size:13px;color:var(--pr);font-weight:500;
}
.free-pill svg{width:15px;height:15px;stroke:var(--pr);}

/* Champs */
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.field{margin-bottom:16px;}
.field-label{display:flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:var(--n700);margin-bottom:7px;letter-spacing:.1px;}
.field-label svg{width:13px;height:13px;stroke:currentColor;color:var(--n400);}
.field-label .req{color:var(--rouge);margin-left:1px;}
.input-wrap{position:relative;}
.input-icon{position:absolute;left:13px;top:50%;transform:translateY(-50%);width:15px;height:15px;stroke:var(--n400);pointer-events:none;}
.fc{
  width:100%;padding:11px 40px 11px 38px;
  border:1.5px solid var(--n200);border-radius:var(--r);
  font-family:'Manrope',sans-serif;font-size:14px;font-weight:400;color:var(--n900);background:var(--white);
  transition:var(--ease);outline:none;line-height:1.5;
}
.fc.no-icon{padding-left:13px;}
.fc.has-eye{padding-right:40px;}
[data-theme="dark"] .fc{background:#1E293B;border-color:#334155;color:#F1F5F9;}
.fc:focus{border-color:var(--pr);box-shadow:0 0 0 3px rgba(0,122,94,.12);}
.fc::placeholder{color:var(--n400);}
.fc.has-error{border-color:var(--rouge);box-shadow:0 0 0 3px rgba(201,52,42,.1);}
select.fc{cursor:pointer;padding-left:13px;}
.eye-btn{
  position:absolute;right:10px;top:50%;transform:translateY(-50%);
  width:30px;height:30px;border:none;background:none;cursor:pointer;
  display:flex;align-items:center;justify-content:center;border-radius:6px;
  color:var(--n400);transition:var(--ease);
}
.eye-btn:hover{background:var(--n200);color:var(--n600);}
[data-theme="dark"] .eye-btn:hover{background:#334155;}
.eye-btn svg{width:16px;height:16px;stroke:currentColor;}

/* Strength bar */
.strength-bar-wrap{height:3px;border-radius:3px;background:var(--n200);margin-top:7px;overflow:hidden;}
.strength-bar-fill{height:100%;border-radius:3px;width:0;transition:all .3s;}
.strength-label{font-size:11px;margin-top:4px;color:var(--n400);}

/* Bouton submit */
.btn-submit{
  width:100%;padding:13px 20px;
  background:var(--pr);color:#fff;
  border:none;border-radius:var(--r);
  font-family:'Manrope',sans-serif;font-size:15px;font-weight:700;letter-spacing:.2px;
  cursor:pointer;transition:var(--ease);
  display:flex;align-items:center;justify-content:center;gap:8px;margin-top:4px;
}
.btn-submit:hover:not(:disabled){background:var(--pr-dk);transform:translateY(-1px);box-shadow:0 6px 20px rgba(0,122,94,.3);}
.btn-submit:disabled{opacity:.55;cursor:not-allowed;transform:none;box-shadow:none;}
.btn-submit svg{width:17px;height:17px;stroke:currentColor;transition:transform var(--ease);}
.btn-submit:hover:not(:disabled) svg{transform:translateX(3px);}

/* Checkbox */
.checkbox-field{display:flex;align-items:flex-start;gap:10px;margin-bottom:20px;margin-top:4px;}
.checkbox-field input{margin-top:2px;accent-color:var(--pr);width:16px;height:16px;flex-shrink:0;cursor:pointer;}
.checkbox-field label{font-size:13px;color:var(--n600);line-height:1.55;cursor:pointer;}
.checkbox-field a{color:var(--pr);font-weight:600;text-decoration:none;}
.checkbox-field a:hover{text-decoration:underline;}

/* Divider & footer */
.divider{display:flex;align-items:center;gap:10px;margin:22px 0 16px;}
.divider-line{flex:1;height:1px;background:var(--n200);}
[data-theme="dark"] .divider-line{background:#334155;}
.divider-text{font-size:12px;color:var(--n400);}
.form-footer{text-align:center;}
.form-footer p{font-size:14px;color:var(--n500);margin-bottom:8px;line-height:1.5;}
.form-footer a{color:var(--pr);font-weight:600;text-decoration:none;}
.form-footer a:hover{text-decoration:underline;}
.back-link{display:inline-flex;align-items:center;gap:6px;font-size:13px;color:var(--n400);text-decoration:none;transition:var(--ease);font-weight:500;}
.back-link:hover{color:var(--pr);}
.back-link svg{width:14px;height:14px;stroke:currentColor;}

/* Responsive */
@media(max-width:960px){
  .page{grid-template-columns:1fr;}
  .left{display:none;}
  .right{padding:40px 28px;justify-content:flex-start;padding-top:60px;}
}
@media(max-width:480px){
  .form-row{grid-template-columns:1fr;}
  .right{padding:28px 18px;padding-top:48px;}
  .form-h1{font-size:22px;}
  .fc{font-size:14px;}
  .btn-submit{font-size:14px;}
}
@media(max-width:360px){
  .right{padding:20px 14px;padding-top:40px;}
  .form-h1{font-size:20px;}
}
/* Logo mobile */
@media(max-width:960px){
  .mobile-brand{
    display:flex;align-items:center;gap:10px;
    margin-bottom:24px;
  }
  .mobile-brand-mark{
    width:38px;height:38px;border-radius:10px;
    background:linear-gradient(135deg,var(--pr),#00A97F);
    display:flex;align-items:center;justify-content:center;flex-shrink:0;
  }
  .mobile-brand-mark svg{stroke:#fff;}
  .mobile-brand-name{font-family:'Syne',sans-serif;font-size:18px;font-weight:800;color:var(--n900);}
  .mobile-brand-name span{color:var(--pr);}
  [data-theme="dark"] .mobile-brand-name{color:#F1F5F9;}
}
@media(min-width:961px){
  .mobile-brand{display:none;}
}

/* ── PREMIUM ANIMATIONS ── */
@keyframes authFadeUp  { from{opacity:0;transform:translateY(18px)} to{opacity:1;transform:translateY(0)} }
@keyframes authSlideIn { from{opacity:0;transform:translateX(16px)} to{opacity:1;transform:translateX(0)} }
@keyframes authSpin    { to{transform:rotate(360deg)} }
@keyframes authShake   { 0%,100%{transform:translateX(0)} 20%,60%{transform:translateX(-6px)} 40%,80%{transform:translateX(6px)} }

.right { animation: authSlideIn .5s cubic-bezier(0.16,1,0.3,1); }
.right-inner > *:nth-child(1) { animation: authFadeUp .5s cubic-bezier(0.16,1,0.3,1) .08s both; }
.right-inner > *:nth-child(2) { animation: authFadeUp .5s cubic-bezier(0.16,1,0.3,1) .14s both; }
.right-inner > *:nth-child(3) { animation: authFadeUp .5s cubic-bezier(0.16,1,0.3,1) .20s both; }
.right-inner > *:nth-child(4) { animation: authFadeUp .5s cubic-bezier(0.16,1,0.3,1) .26s both; }
.right-inner > form           { animation: authFadeUp .5s cubic-bezier(0.16,1,0.3,1) .30s both; }

/* Right panel subtle pattern */
.right {
  background-image: radial-gradient(circle, rgba(0,122,94,.035) 1px, transparent 1px) !important;
  background-size: 22px 22px !important;
}

/* Enhanced inputs */
.fc:focus {
  background: rgba(0,122,94,.025) !important;
}

/* Password strength — premium */
#pwStrengthBar {
  height: 4px !important;
  border-radius: 4px !important;
  transition: width .4s cubic-bezier(0.16,1,0.3,1), background .3s !important;
}

/* Button with spinner */
.btn-submit { position: relative; overflow: hidden; }
.btn-submit .spinner {
  display: none; width: 16px; height: 16px; flex-shrink: 0;
  border: 2px solid rgba(255,255,255,.3); border-top-color: #fff;
  border-radius: 50%; animation: authSpin .7s linear infinite;
}
.btn-submit.loading .spinner { display: block; }
.btn-submit.loading .btn-arrow { display: none; }
</style>
</head>
<body>
<div class="page">

  <!-- ── Panneau gauche ── -->
  <div class="left">
    <div class="left-photo"></div>

    <div class="brand">
      <div class="brand-mark">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
          <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
        </svg>
      </div>
      <div class="brand-name">RÉUSSITE<span>+</span></div>
    </div>

    <div class="left-content">
      <div class="left-title">Ton diplôme,<br>tu peux l'avoir<br><em>cette année.</em></div>
      <div class="left-sub">Des milliers d'élèves de Kinshasa, Lubumbashi et Goma s'y préparent déjà. Rejoins-les.</div>

      <!-- Cartes stats visuelles -->
      <div class="stats-cards">
        <div class="stat-card">
          <div class="stat-card-icon" style="background:rgba(0,169,127,.2)">
            <svg viewBox="0 0 24 24" fill="none" stroke="#52B788" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
              <circle cx="9" cy="7" r="4"/>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
              <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
          </div>
          <div class="stat-card-body">
            <strong>14 238</strong>
            <span>Élèves inscrits sur la plateforme</span>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-card-icon" style="background:rgba(201,151,42,.2)">
            <svg viewBox="0 0 24 24" fill="none" stroke="#FBBF24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
              <polyline points="14 2 14 8 20 8"/>
              <line x1="16" y1="13" x2="8" y2="13"/>
              <line x1="16" y1="17" x2="8" y2="17"/>
              <polyline points="10 9 9 9 8 9"/>
            </svg>
          </div>
          <div class="stat-card-body">
            <strong>2 847</strong>
            <span>Archives officielles corrigées</span>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-card-icon" style="background:rgba(96,165,250,.2)">
            <svg viewBox="0 0 24 24" fill="none" stroke="#60A5FA" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
            </svg>
          </div>
          <div class="stat-card-body">
            <strong>+27 pts</strong>
            <span>Score moyen gagné après 4 semaines</span>
          </div>
        </div>
      </div>

      <!-- Badge plan gratuit -->
      <div class="plan-highlight">
        <div class="plan-highlight-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="20 6 9 17 4 12"/>
          </svg>
        </div>
        <div class="plan-highlight-text">
          <strong>Gratuit dès l'inscription — aucune carte bancaire</strong>
          <span>5 examens/mois &middot; Accès archives de base &middot; QCM entraînement</span>
        </div>
      </div>
    </div>

    <div class="left-footer">
      <div class="avatar-row">
        <div class="avatar-stack">
          <div class="av1">KM</div>
          <div class="av2">BN</div>
          <div class="av3">EM</div>
          <div class="av4">PK</div>
        </div>
        <div class="avatar-label"><strong>14 238 élèves</strong> de toutes les provinces de la RDC</div>
      </div>
    </div>
  </div>

  <!-- ── Panneau droit ── -->
  <div class="right">
    <div class="right-inner">

      <!-- Logo visible sur mobile uniquement -->
      <div class="mobile-brand">
        <div class="mobile-brand-mark">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
            <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
          </svg>
        </div>
        <div class="mobile-brand-name">RÉUSSITE<span>+</span></div>
      </div>

      <div class="form-header">
        <div class="form-eyebrow">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
            <circle cx="8.5" cy="7" r="4"/>
            <polyline points="17 11 19 13 23 9"/>
          </svg>
          Inscription gratuite
        </div>
        <h1 class="form-h1">Créer<br>mon compte</h1>
        <p class="form-desc">C'est gratuit, sans engagement. Tes premières révisions commencent dans 2 minutes.</p>
      </div>

      <!-- Badge gratuit -->
      <div class="free-pill">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="20 6 9 17 4 12"/>
        </svg>
        Compte Gratuit — 5 examens blancs/mois, sans carte bancaire
      </div>

      <?php if ($referralUser): ?>
      <div class="referral-alert">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/>
          <line x1="12" y1="8" x2="12" y2="12"/>
          <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <p><strong><?= e($referralUser['prenom']) ?></strong> vous a invité ! Vous recevrez 1 mois de Basique offert.</p>
      </div>
      <?php endif; ?>

      <?php if ($errors): ?>
      <div class="error-alert">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
          <line x1="12" y1="9" x2="12" y2="13"/>
          <line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
        <div>
          <?php if (count($errors) === 1): ?>
            <?= e($errors[0]) ?>
          <?php else: ?>
            Veuillez corriger les erreurs suivantes :
            <ul><?php foreach ($errors as $e2): ?><li><?= e($e2) ?></li><?php endforeach; ?></ul>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <form method="POST" action="" novalidate id="regForm">
        <?= csrf_field() ?>
        <?php if ($refCode): ?><input type="hidden" name="ref" value="<?= e($refCode) ?>"><?php endif; ?>

        <!-- Prénom / Nom -->
        <div class="form-row">
          <div class="field">
            <div class="field-label">
              <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
              </svg>
              Prénom <span class="req">*</span>
            </div>
            <input class="fc no-icon" type="text" name="prenom" placeholder="Jean"
                   value="<?= e($_POST['prenom'] ?? '') ?>" required autocomplete="given-name">
          </div>
          <div class="field">
            <div class="field-label">
              <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
              </svg>
              Nom <span class="req">*</span>
            </div>
            <input class="fc no-icon" type="text" name="nom" placeholder="Mukeba"
                   value="<?= e($_POST['nom'] ?? '') ?>" required autocomplete="family-name">
          </div>
        </div>

        <!-- Email -->
        <div class="field">
          <div class="field-label">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
              <polyline points="22,6 12,13 2,6"/>
            </svg>
            Adresse email <span class="req">*</span>
          </div>
          <div class="input-wrap">
            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
              <polyline points="22,6 12,13 2,6"/>
            </svg>
            <input class="fc" type="email" name="email" placeholder="vous@exemple.com"
                   value="<?= e($_POST['email'] ?? '') ?>" required autocomplete="email">
          </div>
        </div>

        <!-- Province / Classe -->
        <div class="form-row">
          <div class="field">
            <div class="field-label">
              <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
              </svg>
              Province
            </div>
            <select class="fc no-icon" name="province_id">
              <option value="">— Sélectionner —</option>
              <?php foreach ($provinces as $p): ?>
              <option value="<?= e($p['id']) ?>" <?= ($_POST['province_id'] ?? '') === $p['id'] ? 'selected' : '' ?>>
                <?= e($p['nom']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <div class="field-label">
              <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/>
              </svg>
              Classe
            </div>
            <select class="fc no-icon" name="classe">
              <option value="">— Sélectionner —</option>
              <optgroup label="Primaire">
                <option value="5ème primaire">5ème primaire</option>
                <option value="6ème primaire">6ème primaire</option>
              </optgroup>
              <optgroup label="Secondaire">
                <option value="1ère secondaire">1ère secondaire</option>
                <option value="2ème secondaire">2ème secondaire</option>
                <option value="3ème secondaire">3ème secondaire</option>
                <option value="4ème secondaire">4ème secondaire</option>
                <option value="5ème secondaire">5ème secondaire</option>
                <option value="6ème secondaire">6ème secondaire</option>
              </optgroup>
            </select>
          </div>
        </div>

        <!-- Mot de passe -->
        <div class="field">
          <div class="field-label">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            Mot de passe <span class="req">*</span>
            <span style="font-size:11px;color:var(--n400);font-weight:400;margin-left:4px">(min. 8 caract.)</span>
          </div>
          <div class="input-wrap">
            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            <input class="fc has-eye" type="password" id="password" name="password"
                   placeholder="Choisissez un mot de passe fort" required autocomplete="new-password"
                   oninput="checkStrength(this.value)">
            <button type="button" class="eye-btn" onclick="toggleEye('password','eye1on','eye1off')" aria-label="Afficher">
              <svg id="eye1on" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
              </svg>
              <svg id="eye1off" style="display:none" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                <line x1="1" y1="1" x2="23" y2="23"/>
              </svg>
            </button>
          </div>
          <div class="strength-bar-wrap"><div class="strength-bar-fill" id="strengthFill"></div></div>
          <div class="strength-label" id="strengthLabel"></div>
        </div>

        <!-- Confirmer -->
        <div class="field">
          <div class="field-label">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
            Confirmer le mot de passe <span class="req">*</span>
          </div>
          <div class="input-wrap">
            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
            <input class="fc has-eye" type="password" id="password_confirm" name="password_confirm"
                   placeholder="Répétez votre mot de passe" required autocomplete="new-password">
            <button type="button" class="eye-btn" onclick="toggleEye('password_confirm','eye2on','eye2off')" aria-label="Afficher">
              <svg id="eye2on" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
              </svg>
              <svg id="eye2off" style="display:none" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                <line x1="1" y1="1" x2="23" y2="23"/>
              </svg>
            </button>
          </div>
        </div>

        <!-- CGV -->
        <div class="checkbox-field">
          <input type="checkbox" id="cgv" name="cgv" <?= isset($_POST['cgv']) ? 'checked' : '' ?> required>
          <label for="cgv">J'accepte les <a href="/reussiteplus/cgv.php" target="_blank">conditions d'utilisation</a> et la <a href="/reussiteplus/confidentialite.php" target="_blank">politique de confidentialité</a>.</label>
        </div>

        <button type="submit" class="btn-submit" id="submitBtn">
          <span id="btnLabel">Créer mon compte gratuitement</span>
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
          </svg>
        </button>
      </form>

      <div class="divider">
        <div class="divider-line"></div>
        <div class="divider-text">déjà inscrit ?</div>
        <div class="divider-line"></div>
      </div>

      <div class="form-footer">
        <p>Déjà un compte ? <a href="/reussiteplus/connexion.php">Se connecter</a></p>
        <a href="/reussiteplus/index.php" class="back-link">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
          </svg>
          Retour à l'accueil
        </a>
      </div>

    </div>
  </div>

</div>

<script>
// Toggle oeil
function toggleEye(inputId, onId, offId) {
  const inp = document.getElementById(inputId);
  const on  = document.getElementById(onId);
  const off = document.getElementById(offId);
  const show = inp.type === 'password';
  inp.type = show ? 'text' : 'password';
  on.style.display  = show ? 'none' : '';
  off.style.display = show ? '' : 'none';
}

// Force du mot de passe
const colors = ['#C9342A','#C9342A','#C9972A','#1E5FAD','#007A5E'];
const labels = ['','Très faible','Moyen','Fort','Très fort'];
function checkStrength(val) {
  let s = 0;
  if (val.length >= 8) s++;
  if (/[A-Z]/.test(val)) s++;
  if (/[0-9]/.test(val)) s++;
  if (/[^A-Za-z0-9]/.test(val)) s++;
  const fill  = document.getElementById('strengthFill');
  const label = document.getElementById('strengthLabel');
  if (!val) { fill.style.width='0'; label.textContent=''; return; }
  fill.style.width = (s * 25) + '%';
  fill.style.background = colors[s];
  label.style.color = colors[s];
  label.textContent = labels[s];
}

// Spinner submit
document.getElementById('regForm').addEventListener('submit', function() {
  const btn = document.getElementById('submitBtn');
  btn.disabled = true;
  document.getElementById('btnLabel').textContent = 'Création en cours…';
});
</script>
</body>
</html>
