<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/rate_limit.php';

// Rediriger si déjà connecté
if (is_logged()) { header('Location: /reussiteplus/dashboard.php'); exit; }

$errors   = [];
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
            // Admin → rediriger vers le panel admin
            $role = $result['user']['role'] ?? '';
            if (in_array($role, ['SUPER_ADMIN', 'ADMIN', 'MODERATEUR'])) {
                header('Location: /reussiteplus/admin/index.php?welcome=1');
                exit;
            }
            // Montrer l'onboarding si c'est un nouvel utilisateur (aucun examen)
            $dest = $redirect;
            if ($dest === '/reussiteplus/dashboard.php') {
                $examCount = dbRow("SELECT COUNT(*) as n FROM exam_sessions WHERE user_id=?", [$result['user']['id']])['n'] ?? 0;
                if ((int)$examCount === 0) {
                    $dest = '/reussiteplus/dashboard.php?welcome=1';
                }
            }
            header('Location: ' . $dest);
            exit;
        } else {
            $errors[] = $result['msg'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr" id="htmlRoot">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Connexion — RÉUSSITE+</title>
<link rel="icon" type="image/svg+xml" href="/reussiteplus/assets/img/favicon.svg">
<link rel="shortcut icon" href="/reussiteplus/assets/img/favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<script>(function(){try{var t=localStorage.getItem('rp-theme');if(t==='dark')document.getElementById('htmlRoot').setAttribute('data-theme','dark');}catch(e){}}());</script>
<style>
/* ── Tokens ────────────────────────────────────────────── */
:root{
  --pr:#007A5E;--pr-dk:#005A45;--pr-lt:#00A97F;--pr-sub:#E8F5F1;
  --gold:#C9972A;--rouge:#C9342A;--rouge-sub:#FEF0EF;
  --n900:#1C2433;--n800:#2E3A4A;--n700:#4A5568;--n600:#6B7280;
  --n500:#6B7280;--n400:#A0AEC0;--n300:#CBD5E1;--n200:#E2E8F0;--n100:#F1F5F9;--white:#FFFFFF;
  --ff-display:'Poppins',sans-serif;--ff-body:'Inter',sans-serif;
  --font-display:'Poppins',sans-serif;--font-body:'Inter',sans-serif;
  --r:10px;--r-lg:18px;--r-xl:24px;
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
body{font-family:var(--ff-body);background:var(--n100);color:var(--n900);line-height:1.6;}

/* ── Layout split-screen ────────────────────────────────── */
.page{display:grid;grid-template-columns:1fr 1fr;min-height:100vh;}

/* ── Panneau gauche ─────────────────────────────────────── */
.left{
  background:#0D1117;
  padding:48px 52px;display:flex;flex-direction:column;position:relative;overflow:hidden;
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
.brand-name{font-family:var(--ff-display);font-size:20px;font-weight:800;color:#fff;letter-spacing:-.3px;}
.brand-name span{color:#FBBF24;}

.left-content{position:relative;flex:1;display:flex;flex-direction:column;justify-content:center;padding:40px 0;}
.left-title{font-family:var(--ff-display);font-size:clamp(26px,3vw,36px);font-weight:900;color:#fff;line-height:1.2;margin-bottom:16px;}
.left-title em{font-style:normal;color:#FBBF24;}
.left-sub{font-size:15px;color:rgba(255,255,255,.7);line-height:1.6;margin-bottom:40px;max-width:320px;}

/* ── Carousel ───────────────────────────────────────────── */
.carousel{position:relative;overflow:hidden;border-radius:16px;margin-bottom:24px;}
.carousel-track{display:flex;transition:transform .5s cubic-bezier(.4,0,.2,1);will-change:transform;}
.slide{
  min-width:100%;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);
  border-radius:16px;padding:20px;backdrop-filter:blur(8px);flex-shrink:0;
}
.slide-icon-row{display:flex;align-items:center;gap:10px;margin-bottom:14px;}
.slide-icon-wrap{
  width:40px;height:40px;border-radius:10px;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;
}
.slide-icon-wrap svg{width:20px;height:20px;stroke:currentColor;}
.slide-label{font-size:11px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;opacity:.6;color:#fff;}
.slide-title{font-family:var(--ff-display);font-size:17px;font-weight:800;color:#fff;margin-bottom:8px;line-height:1.25;}
.slide-body{font-size:13px;color:rgba(255,255,255,.65);line-height:1.55;margin-bottom:16px;}
/* Mock UI dans les slides */
.mock-bar{height:6px;border-radius:6px;margin-bottom:6px;background:rgba(255,255,255,.12);overflow:hidden;}
.mock-bar-fill{height:100%;border-radius:6px;}
.mock-pill{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);border-radius:20px;padding:5px 10px;font-size:11px;font-weight:600;color:#fff;margin:3px 3px 0 0;}
.mock-pill svg{width:11px;height:11px;stroke:currentColor;}
.mock-score{display:flex;align-items:baseline;gap:6px;margin-bottom:4px;}
.mock-score-num{font-family:var(--ff-display);font-size:32px;font-weight:800;color:#fff;line-height:1;}
.mock-score-label{font-size:12px;color:rgba(255,255,255,.5);}
/* Dots */
.carousel-dots{display:flex;gap:6px;justify-content:center;margin-top:12px;}
.dot{width:6px;height:6px;border-radius:50%;background:rgba(255,255,255,.25);cursor:pointer;transition:all .3s;border:none;padding:0;}
.dot.active{background:#FBBF24;width:18px;border-radius:6px;}

.left-footer{position:relative;margin-top:40px;border-top:1px solid rgba(255,255,255,.1);padding-top:20px;}
.avatar-row{display:flex;align-items:center;gap:12px;}
.avatar-stack{display:flex;}
.avatar-stack div{
  width:30px;height:30px;border-radius:50%;border:2px solid rgba(0,61,46,1);
  margin-left:-8px;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center;color:#fff;
}
.avatar-stack div:first-child{margin-left:0;}
.avatar-stack .av1{background:#52B788;}
.avatar-stack .av2{background:#FBBF24;color:#7B2D00;}
.avatar-stack .av3{background:#60A5FA;color:#1E3A5F;}
.avatar-stack .av4{background:#F87171;color:#7F1D1D;}
/* Correction border couleur avatar pour fond sombre */
.avatar-stack div{border-color:#0D1117;}
.avatar-label{font-size:12px;color:rgba(255,255,255,.7);}
.avatar-label strong{color:#fff;}

/* ── Panneau droit ──────────────────────────────────────── */
.right{
  background:var(--white);display:flex;flex-direction:column;align-items:center;
  justify-content:center;padding:48px 52px;
}
[data-theme="dark"] .right{background:#0F172A;}

.right-inner{width:100%;max-width:400px;}

.form-header{margin-bottom:32px;}
.form-eyebrow{
  display:inline-flex;align-items:center;gap:6px;
  background:var(--pr-sub);color:var(--pr);
  font-size:11px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;
  padding:5px 12px;border-radius:20px;margin-bottom:14px;
}
.form-eyebrow svg{width:13px;height:13px;stroke:currentColor;}
.form-h1{font-family:var(--ff-display);font-size:28px;font-weight:900;color:var(--n900);line-height:1.15;margin-bottom:6px;letter-spacing:-.4px;}
.form-desc{font-size:15px;color:var(--n600);line-height:1.55;}

/* ── Champ ──────────────────────────────────────────────── */
.field{margin-bottom:18px;}
.field-label{display:flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:var(--n700);margin-bottom:7px;letter-spacing:.1px;}
.field-label svg{width:14px;height:14px;stroke:currentColor;color:var(--n500);}
.input-wrap{position:relative;}
.input-wrap .input-icon{
  position:absolute;left:14px;top:50%;transform:translateY(-50%);
  width:16px;height:16px;stroke:var(--n400);pointer-events:none;
}
.fc{
  width:100%;padding:12px 42px 12px 42px;
  border:1.5px solid var(--n200);border-radius:var(--r);
  font-family:var(--ff-body);font-size:14px;font-weight:400;color:var(--n900);background:var(--white);
  transition:var(--ease);outline:none;line-height:1.5;
}
[data-theme="dark"] .fc{background:#1E293B;border-color:#334155;color:#F1F5F9;}
.fc:focus{border-color:var(--pr);box-shadow:0 0 0 3px rgba(0,122,94,.12);}
.fc::placeholder{color:var(--n400);}
.fc.has-error{border-color:var(--rouge);box-shadow:0 0 0 3px rgba(201,52,42,.1);}
.eye-btn{
  position:absolute;right:12px;top:50%;transform:translateY(-50%);
  width:32px;height:32px;border:none;background:none;cursor:pointer;
  display:flex;align-items:center;justify-content:center;border-radius:6px;
  color:var(--n400);transition:var(--ease);
}
.eye-btn:hover{background:var(--n200);color:var(--n600);}
[data-theme="dark"] .eye-btn:hover{background:#334155;}
.eye-btn svg{width:17px;height:17px;stroke:currentColor;}

/* ── Erreur ─────────────────────────────────────────────── */
.error-alert{
  display:flex;align-items:flex-start;gap:10px;
  background:var(--rouge-sub);border:1px solid rgba(201,52,42,.2);
  border-radius:var(--r);padding:12px 14px;margin-bottom:20px;
}
.error-alert svg{width:16px;height:16px;stroke:var(--rouge);flex-shrink:0;margin-top:1px;}
.error-alert p{font-size:13px;color:var(--rouge);line-height:1.5;}

/* ── Bouton submit ──────────────────────────────────────── */
.btn-submit{
  width:100%;padding:13px 20px;
  background:var(--pr);color:#fff;
  border:none;border-radius:var(--r);
  font-family:var(--ff-display);font-size:15px;font-weight:800;letter-spacing:.3px;
  cursor:pointer;transition:var(--ease);
  display:flex;align-items:center;justify-content:center;gap:8px;
}
.btn-submit:hover:not(:disabled){background:var(--pr-dk);transform:translateY(-1px);box-shadow:0 6px 20px rgba(0,122,94,.3);}
.btn-submit:disabled{opacity:.55;cursor:not-allowed;transform:none;box-shadow:none;}
.btn-submit svg{width:18px;height:18px;stroke:currentColor;transition:transform var(--ease);}
.btn-submit:hover:not(:disabled) svg{transform:translateX(3px);}

/* ── Divider ────────────────────────────────────────────── */
.divider{display:flex;align-items:center;gap:10px;margin:22px 0;}
.divider-line{flex:1;height:1px;background:var(--n200);}
[data-theme="dark"] .divider-line{background:#334155;}
.divider-text{font-size:12px;color:var(--n400);}

/* ── Footer links ───────────────────────────────────────── */
.form-footer{text-align:center;margin-top:24px;}
.form-footer p{font-size:14px;color:var(--n500);margin-bottom:8px;line-height:1.5;}
.form-footer a{color:var(--pr);font-weight:600;text-decoration:none;}
.form-footer a:hover{text-decoration:underline;}
.back-link{display:inline-flex;align-items:center;gap:6px;font-size:13px;color:var(--n400);text-decoration:none;transition:var(--ease);font-weight:500;}
.back-link:hover{color:var(--pr);}
.back-link svg{width:14px;height:14px;stroke:currentColor;}

/* ── Responsive ─────────────────────────────────────────── */
@media(max-width:900px){
  .page{grid-template-columns:1fr;}
  .left{display:none;}
  .right{padding:40px 28px;justify-content:flex-start;padding-top:60px;}
}
@media(max-width:480px){
  .right{padding:28px 18px;padding-top:48px;}
  .form-h1{font-size:24px;}
  .form-desc{font-size:14px;}
  .fc{padding:11px 40px 11px 40px;font-size:14px;}
  .btn-submit{padding:12px 20px;font-size:14px;}
}
@media(max-width:360px){
  .right{padding:20px 14px;padding-top:40px;}
  .form-h1{font-size:22px;}
}
/* Bandeau de marque en haut sur mobile (au lieu du panneau gauche) */
@media(max-width:900px){
  .mobile-brand{
    display:flex;align-items:center;gap:10px;
    margin-bottom:28px;
  }
  .mobile-brand-mark{
    width:38px;height:38px;border-radius:10px;
    background:linear-gradient(135deg,var(--pr),#00A97F);
    display:flex;align-items:center;justify-content:center;flex-shrink:0;
  }
  .mobile-brand-mark svg{stroke:#fff;}
  .mobile-brand-name{font-family:var(--ff-display);font-size:18px;font-weight:800;color:var(--n900);}
  .mobile-brand-name span{color:var(--pr);}
  [data-theme="dark"] .mobile-brand-name{color:#F1F5F9;}
}
@media(min-width:901px){
  .mobile-brand{display:none;}
}
</style>
</head>
<body>
<div class="page">

  <!-- ── Panneau gauche ── -->
  <div class="left">
    <div class="left-photo"></div>

    <div class="brand">
      <div class="brand-mark">
        <img src="/reussiteplus/assets/img/logo-icon.svg" alt="RÉUSSITE+" width="26" height="26" style="display:block">
      </div>
      <div class="brand-name">RÉUSSITE<span>+</span></div>
    </div>

    <div class="left-content">
      <div class="left-title">Prépare ton<br>examen avec<br><em>confiance.</em></div>
      <div class="left-sub">Archives officielles, QCM adaptatifs et révision IA — tout ce qu'il faut pour décrocher ton diplôme en RDC.</div>

      <!-- ── Carousel ── -->
      <div class="carousel" id="carousel">
        <div class="carousel-track" id="carouselTrack">

          <!-- Slide 1 — Archives -->
          <div class="slide">
            <div class="slide-icon-row">
              <div class="slide-icon-wrap" style="background:rgba(96,165,250,.2)">
                <svg viewBox="0 0 24 24" fill="none" stroke="#60A5FA" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                  <line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
                </svg>
              </div>
              <div class="slide-label">Archives officielles</div>
            </div>
            <div class="slide-title">Tous les sujets d'examen<br>depuis 2010</div>
            <div class="slide-body">Examen d'État, TENASOSP, ENAFEP — corrigés détaillés par matière.</div>
            <!-- Mock: liste de matières avec barres -->
            <div style="display:flex;flex-direction:column;gap:7px">
              <div style="display:flex;align-items:center;gap:8px">
                <span style="font-size:11px;color:rgba(255,255,255,.5);width:68px">Maths</span>
                <div class="mock-bar" style="flex:1"><div class="mock-bar-fill" style="width:92%;background:#60A5FA"></div></div>
                <span style="font-size:11px;color:rgba(255,255,255,.5);width:28px">92%</span>
              </div>
              <div style="display:flex;align-items:center;gap:8px">
                <span style="font-size:11px;color:rgba(255,255,255,.5);width:68px">Français</span>
                <div class="mock-bar" style="flex:1"><div class="mock-bar-fill" style="width:78%;background:#60A5FA"></div></div>
                <span style="font-size:11px;color:rgba(255,255,255,.5);width:28px">78%</span>
              </div>
              <div style="display:flex;align-items:center;gap:8px">
                <span style="font-size:11px;color:rgba(255,255,255,.5);width:68px">Chimie</span>
                <div class="mock-bar" style="flex:1"><div class="mock-bar-fill" style="width:85%;background:#60A5FA"></div></div>
                <span style="font-size:11px;color:rgba(255,255,255,.5);width:28px">85%</span>
              </div>
            </div>
          </div>

          <!-- Slide 2 — QCM -->
          <div class="slide">
            <div class="slide-icon-row">
              <div class="slide-icon-wrap" style="background:rgba(251,191,36,.2)">
                <svg viewBox="0 0 24 24" fill="none" stroke="#FBBF24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
              </div>
              <div class="slide-label">QCM interactifs</div>
            </div>
            <div class="slide-title">Entraîne-toi avec<br>des vrais QCM</div>
            <div class="slide-body">Chronomètre intégré, explication des bonnes réponses, suivi de score.</div>
            <!-- Mock: question QCM -->
            <div style="background:rgba(255,255,255,.06);border-radius:10px;padding:12px">
              <div style="font-size:12px;color:rgba(255,255,255,.8);margin-bottom:10px;font-weight:500">Quelle est la formule de l'eau ?</div>
              <div style="display:flex;flex-direction:column;gap:6px">
                <div style="display:flex;align-items:center;gap:8px;background:rgba(82,183,136,.2);border:1px solid rgba(82,183,136,.4);border-radius:7px;padding:7px 10px">
                  <span style="width:18px;height:18px;border-radius:50%;background:#52B788;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;flex-shrink:0">A</span>
                  <span style="font-size:12px;color:#fff">H₂O</span>
                  <svg style="margin-left:auto;width:13px;height:13px;stroke:#52B788" viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <div style="display:flex;align-items:center;gap:8px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:7px;padding:7px 10px">
                  <span style="width:18px;height:18px;border-radius:50%;background:rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:rgba(255,255,255,.6);flex-shrink:0">B</span>
                  <span style="font-size:12px;color:rgba(255,255,255,.5)">CO₂</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Slide 3 — Révision IA -->
          <div class="slide">
            <div class="slide-icon-row">
              <div class="slide-icon-wrap" style="background:rgba(167,139,250,.2)">
                <svg viewBox="0 0 24 24" fill="none" stroke="#A78BFA" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                </svg>
              </div>
              <div class="slide-label">Révision IA</div>
            </div>
            <div class="slide-title">Ton plan de révision<br>personnalisé par l'IA</div>
            <div class="slide-body">L'IA analyse tes résultats et génère un plan semaine par semaine.</div>
            <!-- Mock: score progression -->
            <div style="display:flex;align-items:flex-end;gap:6px;height:48px;margin-bottom:10px">
              <?php
              $bars = [30,45,38,55,62,70,87];
              $max  = max($bars);
              $cols = ['rgba(167,139,250,.3)','rgba(167,139,250,.3)','rgba(167,139,250,.4)','rgba(167,139,250,.5)','rgba(167,139,250,.6)','rgba(167,139,250,.8)','#A78BFA'];
              foreach ($bars as $i => $b): ?>
              <div style="flex:1;height:<?= round($b/$max*100) ?>%;background:<?= $cols[$i] ?>;border-radius:4px 4px 0 0"></div>
              <?php endforeach; ?>
            </div>
            <div class="mock-score">
              <div class="mock-score-num">87<span style="font-size:18px">%</span></div>
              <div class="mock-score-label">score moyen<br>après 30 jours</div>
            </div>
          </div>

          <!-- Slide 4 — Progression -->
          <div class="slide">
            <div class="slide-icon-row">
              <div class="slide-icon-wrap" style="background:rgba(82,183,136,.2)">
                <svg viewBox="0 0 24 24" fill="none" stroke="#52B788" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                </svg>
              </div>
              <div class="slide-label">Suivi de progression</div>
            </div>
            <div class="slide-title">Vois ta progression<br>en temps réel</div>
            <div class="slide-body">Tableau de bord avec historique, classement et badges de réussite.</div>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:4px">
              <div class="mock-pill">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/></svg>
                15 examens réussis
              </div>
              <div class="mock-pill">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                +23 pts ce mois
              </div>
              <div class="mock-pill">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                Plan IA actif
              </div>
            </div>
          </div>

        </div><!-- /track -->
      </div><!-- /carousel -->

      <!-- Dots -->
      <div class="carousel-dots" id="carouselDots">
        <button class="dot active" onclick="goSlide(0)"></button>
        <button class="dot" onclick="goSlide(1)"></button>
        <button class="dot" onclick="goSlide(2)"></button>
        <button class="dot" onclick="goSlide(3)"></button>
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
        <div class="avatar-label">Rejoint par <strong>+12 000 élèves</strong> de la RDC</div>
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
            <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
          </svg>
        </div>
        <div class="mobile-brand-name">RÉUSSITE<span>+</span></div>
      </div>

      <div class="form-header">
        <div class="form-eyebrow">
          <!-- Icône shield/sécurité -->
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
          </svg>
          Connexion sécurisée
        </div>
        <h1 class="form-h1">Bon retour<br>parmi nous</h1>
        <p class="form-desc">Continuez votre préparation là où vous l'avez laissée.</p>
      </div>

      <?php if ($errors): ?>
      <div class="error-alert">
        <!-- Icône alerte triangle -->
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
          <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
        <p><?= e($errors[0]) ?></p>
      </div>
      <?php endif; ?>

      <form method="POST" action="" novalidate id="loginForm">
        <?= csrf_field() ?>
        <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

        <!-- Email -->
        <div class="field">
          <label class="field-label" for="email">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
              <polyline points="22,6 12,13 2,6"/>
            </svg>
            Adresse email
          </label>
          <div class="input-wrap">
            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
              <polyline points="22,6 12,13 2,6"/>
            </svg>
            <input class="fc<?= $errors ? ' has-error' : '' ?>" type="email" id="email" name="email"
                   placeholder="vous@exemple.com"
                   value="<?= e($_POST['email'] ?? '') ?>" required autocomplete="email">
          </div>
        </div>

        <!-- Mot de passe -->
        <div class="field">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:7px">
            <label class="field-label" for="password" style="margin-bottom:0">
              <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
              </svg>
              Mot de passe
            </label>
            <a href="/reussiteplus/mot_de_passe_oublie.php" style="font-size:12px;color:var(--pr);font-weight:500;text-decoration:none">Oublié ?</a>
          </div>
          <div class="input-wrap">
            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            <input class="fc<?= $errors ? ' has-error' : '' ?>" type="password" id="password" name="password"
                   placeholder="Votre mot de passe" required autocomplete="current-password">
            <button type="button" class="eye-btn" id="eyeBtn" aria-label="Afficher le mot de passe" onclick="toggleEye()">
              <!-- Icône oeil ouvert (état initial) -->
              <svg id="iconEyeOn" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
              </svg>
              <!-- Icône oeil barré (état masqué) -->
              <svg id="iconEyeOff" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                <line x1="1" y1="1" x2="23" y2="23"/>
              </svg>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-submit" id="submitBtn">
          <span id="btnLabel">Se connecter</span>
          <!-- Icône flèche -->
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
          </svg>
        </button>
      </form>

      <div class="form-footer">
        <p>Pas encore de compte ? <a href="/reussiteplus/inscription.php">Créer un compte gratuit</a></p>
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
// ── Carousel ──────────────────────────────────────────────
let current = 0;
const total = 4;
const track = document.getElementById('carouselTrack');
const dots  = document.querySelectorAll('.dot');
let timer;

function goSlide(n) {
  current = (n + total) % total;
  track.style.transform = `translateX(-${current * 100}%)`;
  dots.forEach((d, i) => d.classList.toggle('active', i === current));
  resetTimer();
}

function resetTimer() {
  clearInterval(timer);
  timer = setInterval(() => goSlide(current + 1), 4000);
}

// Démarrage
resetTimer();

// Swipe mobile
let startX = 0;
track.addEventListener('touchstart', e => { startX = e.touches[0].clientX; }, {passive:true});
track.addEventListener('touchend',   e => {
  const dx = e.changedTouches[0].clientX - startX;
  if (Math.abs(dx) > 40) goSlide(dx < 0 ? current + 1 : current - 1);
}, {passive:true});

// Toggle mot de passe
let passVisible = false;
function toggleEye() {
  const input = document.getElementById('password');
  const on    = document.getElementById('iconEyeOn');
  const off   = document.getElementById('iconEyeOff');
  passVisible = !passVisible;
  input.type  = passVisible ? 'text' : 'password';
  on.style.display  = passVisible ? 'none' : '';
  off.style.display = passVisible ? '' : 'none';
  document.getElementById('eyeBtn').setAttribute('aria-label', passVisible ? 'Masquer' : 'Afficher');
}

// Spinner submit
document.getElementById('loginForm').addEventListener('submit', function() {
  const btn   = document.getElementById('submitBtn');
  const label = document.getElementById('btnLabel');
  btn.disabled = true;
  label.textContent = 'Connexion en cours…';
});
</script>
</body>
</html>



