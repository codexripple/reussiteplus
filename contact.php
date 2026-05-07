<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$user        = current_user();
$success     = false;
$errors      = [];
$nomEnvoyeur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Token de sécurité invalide. Rechargez la page.';
    } else {
        $nom     = trim($_POST['nom']       ?? '');
        $email   = trim($_POST['email']     ?? '');
        $tel     = trim($_POST['telephone'] ?? '');
        $sujet   = $_POST['sujet']          ?? 'AUTRE';
        $message = trim($_POST['message']   ?? '');

        if (!$nom || mb_strlen($nom) < 2)              $errors[] = 'Votre nom est requis (minimum 2 caractères).';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Adresse e-mail invalide.';
        if (!$message || mb_strlen($message) < 10)     $errors[] = 'Le message est trop court (minimum 10 caractères).';
        if (mb_strlen($message) > 2000)                $errors[] = 'Le message est trop long (maximum 2000 caractères).';
        $allowed = ['PLAN','TECHNIQUE','PARTENARIAT','PRESSE','AUTRE'];
        if (!in_array($sujet, $allowed, true)) $sujet = 'AUTRE';

        $ip     = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $recent = dbRow(
            "SELECT COUNT(*) as n FROM contact_messages WHERE ip=? AND created_at > NOW() - INTERVAL 1 HOUR",
            [$ip]
        );
        if (($recent['n'] ?? 0) >= 3) {
            $errors[] = 'Trop de messages envoyés. Réessayez dans une heure.';
        }

        if (!$errors) {
            dbInsert('contact_messages', [
                'nom'       => $nom,
                'email'     => $email,
                'telephone' => $tel ?: null,
                'sujet'     => $sujet,
                'message'   => $message,
                'ip'        => $ip,
            ]);
            $success     = true;
            $nomEnvoyeur = $nom;
        }
    }
}

$sujets = [
    'PLAN'        => 'Question sur un abonnement / tarif',
    'TECHNIQUE'   => 'Problème technique',
    'PARTENARIAT' => 'Partenariat ou collaboration école',
    'PRESSE'      => 'Médias & presse',
    'AUTRE'       => 'Autre',
];

$faq = [
    ['q' => 'Comment accéder aux archives officielles ?',
     'r' => 'Les archives sont accessibles dès le plan Basique. Créez un compte gratuit, puis abonnez-vous à partir de 5 000 CDF/mois.'],
    ['q' => 'Le plan Gratuit a-t-il vraiment 0 CDF ?',
     'r' => "Oui, totalement gratuit et sans carte bancaire. Vous avez accès à 5 examens d'entraînement par mois."],
    ['q' => 'Comment payer mon abonnement en RDC ?',
     'r' => 'Nous acceptons M-Pesa, Airtel Money et Orange Money. Le paiement se fait directement dans l\'application.'],
    ['q' => 'Puis-je utiliser RÉUSSITE+ hors-ligne ?',
     'r' => "Pour l'instant, une connexion est requise. Une version hors-ligne est prévue pour fin 2025."],
    ['q' => "Comment préparer l'Examen d'État avec la plateforme ?",
     'r' => "Choisissez le type \"Examen d'État\" dans la configuration, sélectionnez votre matière et lancez une simulation chronométrée."],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nous contacter — RÉUSSITE+</title>
<meta name="description" content="Contactez l'équipe RÉUSSITE+ pour toute question, partenariat ou support technique.">
<link rel="icon" type="image/svg+xml" href="/reussiteplus/assets/img/favicon.svg">
<link rel="stylesheet" href="/reussiteplus/assets/css/bootstrap-icons.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800;900&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root {
  --bg:#fbf9f4; --surface:#ffffff;
  --navy:#0d1b3e; --brand:#007A5E; --brand-l:#00A97F;
  --text:#1b1c19; --muted:#45464e; --subtle:#6b7280;
  --border:#e5e7eb; --rouge:#dc2626;
  --serif:ui-serif,Georgia,Cambria,"Times New Roman",Times,serif;
  --sans:'Manrope',system-ui,sans-serif;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html{scroll-behavior:smooth;}
a, body, p, span, li, label, input, textarea, select, button, div, td, th, h1, h2, h3, h4, h5, h6 {
  font-family: 'Manrope', sans-serif !important;
}
body{font-family:var(--font-body);background:var(--gris-50);color:var(--gris-800);}
a{text-decoration:none;color:inherit;}

*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html{scroll-behavior:smooth;}
body{font-family:var(--sans);background:var(--bg);color:var(--text);font-size:16px;line-height:1.5;}
a{text-decoration:none;color:inherit;}
img{display:block;max-width:100%;}
/* ─── NAV ─── */
.nav{
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 100;
  background: rgba(13, 17, 23, 0.97);
  backdrop-filter: blur(12px);
  border-bottom: 1px solid rgba(255, 255, 255, 0.07);
  display: flex;
  align-items: center;
  padding: 0 64px;
  height: 72px;
  gap: 48px;
}
.nav-links {
  display: flex;
  gap: 36px;
}
.nav-logo{
  display: flex;
  align-items: center;
  gap: 12px;
  font-family: 'Syne', sans-serif;
  font-size: 22px;
  font-weight: 800;
  color: white;
  letter-spacing: -0.5px;
  text-decoration: none;
  flex-shrink: 0;
}
.nav-logo span {
  color: white;
}
.nav-logo .lplus{
  color: #C9972A;
}
.nav-links a{
  color: rgba(255, 255, 255, 0.85);
  font-size: 14px;
  font-weight: 500;
  text-decoration: none;
  transition: color 200ms;
}
.nav-links a:hover{
  color: white;
}
.btn-nav{
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 24px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  border: none;
  text-decoration: none;
  transition: 200ms;
  border-radius: 10px;
}
.btn-nav-ghost{
  background: transparent;
  color: rgba(255, 255, 255, 0.85);
  border: 1px solid rgba(255, 255, 255, 0.25);
}
.btn-nav-ghost:hover{
  background: rgba(255, 255, 255, 0.08);
  color: white;
}
.btn-nav-solid{
  background: #007A5E;
  color: white;
}
.btn-nav-solid:hover{
  background: #005A45;
  box-shadow: 0 0 24px rgba(0, 122, 94, 0.35);
}
/* ─── HERO ─── */
.hero {
  padding: 96px 48px 64px;
  max-width: 1280px;
  margin: 0 auto;
  background: #f3f1ec;
  border-radius: 8px;
  color: #1b1c19;
  box-shadow: 0 4px 24px rgba(0,0,0,0.04);
}
.hero-eyebrow {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 3px;
  text-transform: uppercase;
  color: var(--brand);
  margin-bottom: 24px;
}
.hero h1 {
  font-family: var(--serif);
  font-size: clamp(44px,6vw,68px);
  font-weight: 800;
  line-height: 1.15;
  letter-spacing: -1px;
  color: #C9972A;
  margin-bottom: 18px;
}
.hero h1 em {
  font-style: italic;
  color: var(--brand);
}
.hero-sub {
  font-size: 18px;
  color: #45464e;
  line-height: 1.7;
  max-width: 560px;
}
/* ─── MAIN LAYOUT ─── */
.contact-wrap{max-width:1280px;margin:0 auto;padding:0 48px 112px;display:grid;grid-template-columns:7fr 5fr;gap:80px;align-items:start;}
  .contact-wrap {
    max-width: 1280px;
    margin: 0 auto;
    padding: 48px;
    display: grid;
    grid-template-columns: 7fr 5fr;
    gap: 64px;
    align-items: start;
    background: rgba(13, 17, 23, 0.95);
    border-radius: 6px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
  }
/* ─── FORM ─── */
.section-heading{font-family:var(--serif);font-size:30px;font-weight:400;letter-spacing:-1px;color:#00020e;margin-bottom:40px;line-height:1.15;}
.section-heading {
  font-family: 'Syne', sans-serif;
  font-size: 28px;
  font-weight: 700;
  color: #C9972A;
  margin-bottom: 24px;
}
.field-group{margin-bottom:24px;}
.field-row{display:grid;grid-template-columns:1fr 1fr;gap:24px;}
.field-label{display:block;font-size:14px;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:rgba(255, 255, 255, 0.85);margin-bottom:8px;}
.field-req{color:var(--rouge);margin-left:2px;}
  .field-input {
    width: 100%;
    padding: 14px;
    border: 1.5px solid #e5e7eb;
    outline: none;
    background: #f3f1ec;
    font-family: 'Manrope', sans-serif;
    font-size: 16px;
    color: #222;
    border-radius: 4px;
    transition: box-shadow 200ms, border-color 200ms;
    box-shadow: none;
  }
  .field-input:focus {
    border-color: #C9972A;
    box-shadow: 0 0 0 2px #C9972A22;
  }
  .field-input.is-error {
    border-color: var(--rouge);
    box-shadow: 0 0 0 2px #dc262622;
  }
textarea.field-input{resize:vertical;min-height:160px;line-height:1.6;}
select.field-input{appearance:none;cursor:pointer;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 16px center;background-color:var(--surface);}
.char-count{font-size:11px;color:var(--subtle);text-align:right;margin-top:6px;}
  .btn-submit {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: #e5e7eb;
    color: #222;
    border: none;
    cursor: pointer;
    padding: 16px 32px;
    font-family: 'Syne', sans-serif;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
    border-radius: 4px;
    box-shadow: none;
    transition: background 200ms, box-shadow 200ms;
  }
  .btn-submit:hover {
    background: #d1d5db;
    color: #111;
    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
  }
  .btn-submit:disabled {
    opacity: .6;
    cursor: not-allowed;
  }
.form-privacy{font-size:12px;color:var(--subtle);margin-top:16px;display:flex;align-items:center;gap:6px;}
/* ─── ALERTS ─── */
.alert{padding:16px 20px;margin-bottom:28px;font-size:14px;border-left:3px solid;display:flex;align-items:flex-start;gap:12px;}
.alert-error{background:#fef2f2;border-color:var(--rouge);color:#991b1b;}
.errors-list{list-style:none;}
.errors-list li::before{content:"— ";}
/* ─── ASIDE ─── */
.aside-cta-card{background:var(--navy);color:white;padding:36px;}
.aside-cta-eyebrow{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:3px;color:rgba(255,255,255,.4);margin-bottom:14px;}
.aside-cta-title{font-family:var(--serif);font-size:24px;font-weight:400;color:white;letter-spacing:-.5px;line-height:1.25;margin-bottom:12px;}
.aside-cta-title em{font-style:italic;color:var(--brand-l);}
.aside-cta-p{font-size:14px;color:rgba(255,255,255,.6);line-height:1.7;margin-bottom:24px;}
.aside-cta-btn{display:inline-flex;align-items:center;gap:8px;background:white;color:var(--navy);padding:14px 24px;font-size:13px;font-weight:700;letter-spacing:.8px;text-decoration:none;text-transform:uppercase;transition:180ms;}
.aside-cta-btn:hover{background:var(--bg);}
.contact-info{border-top:1px solid var(--border);}
.info-item{display:flex;gap:16px;padding:22px 0;border-bottom:1px solid var(--border);}
.info-ico{font-size:16px;color:var(--brand);flex-shrink:0;margin-top:2px;}
.info-lbl{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:3px;color:var(--muted);margin-bottom:4px;}
.info-val{font-size:14px;color:var(--text);line-height:1.6;}
.info-val a{color:var(--brand);}
.aside-loc{background:var(--navy);padding:24px 28px;}
.aside-loc-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:3px;color:rgba(255,255,255,.4);margin-bottom:8px;display:flex;align-items:center;gap:8px;}
.aside-loc-city{font-size:20px;font-weight:700;color:white;}
.aside-loc-sub{font-size:11px;text-transform:uppercase;letter-spacing:2px;color:rgba(255,255,255,.35);margin-top:4px;}
/* ─── FAQ ─── */
.faq-section{background:#f3f1ec;padding:96px 48px;}
.faq-wrap{max-width:1280px;margin:0 auto;}
.faq-hd{margin-bottom:56px;}
.faq-title{font-family:var(--serif);font-size:clamp(36px,5vw,56px);font-weight:400;letter-spacing:-2px;color:#00020e;line-height:1.1;margin-bottom:14px;}
.faq-title em{font-style:italic;color:var(--brand);}
.faq-sub{font-size:15px;color:var(--muted);}
.faq-grid{display:grid;grid-template-columns:1fr 1fr;}
.faq-item{border-bottom:1px solid var(--border);padding-right:32px;}
.faq-item:nth-child(even){padding-right:0;padding-left:32px;border-left:1px solid var(--border);}
.faq-q{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:24px 0;cursor:pointer;font-size:15px;font-weight:600;color:var(--text);list-style:none;}
.faq-q-icon{width:24px;height:24px;border-radius:50%;border:1.5px solid var(--border);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:14px;color:var(--muted);transition:200ms;line-height:1;}
.faq-item.open .faq-q-icon{background:var(--navy);border-color:var(--navy);color:white;transform:rotate(45deg);}
.faq-a{font-size:14px;color:var(--muted);line-height:1.8;max-height:0;overflow:hidden;transition:max-height .3s ease,padding .3s ease;}
.faq-item.open .faq-a{max-height:220px;padding-bottom:24px;}
/* ─── CTA ─── */
.cta-section{background:var(--navy);padding:96px 48px;text-align:center;}
.cta-wrap{max-width:680px;margin:0 auto;}
.cta-eyebrow{font-size:11px;font-weight:700;letter-spacing:3px;text-transform:uppercase;color:rgba(255,255,255,.35);margin-bottom:28px;}
.cta-title{font-family:var(--serif);font-size:clamp(36px,5vw,56px);font-weight:400;letter-spacing:-2px;color:white;line-height:1.1;margin-bottom:20px;}
.cta-title em{font-style:italic;color:var(--brand-l);}
.cta-sub{font-size:16px;color:rgba(255,255,255,.5);line-height:1.7;margin-bottom:40px;}
.cta-actions{display:flex;gap:16px;justify-content:center;flex-wrap:wrap;}
.btn-cta-white{background:white;color:var(--navy);padding:18px 36px;font-size:13px;font-weight:700;letter-spacing:1px;text-transform:uppercase;display:inline-flex;align-items:center;gap:8px;text-decoration:none;transition:180ms;}
.btn-cta-white:hover{background:var(--bg);}
.btn-cta-ghost{background:transparent;color:rgba(255,255,255,.6);border:1.5px solid rgba(255,255,255,.25);padding:18px 36px;font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:8px;text-decoration:none;transition:180ms;}
.btn-cta-ghost:hover{color:white;border-color:white;}
/* ─── SUCCESS ─── */
.success-hero{padding:128px 48px 96px;max-width:1280px;margin:0 auto;}
.success-check-circle{width:72px;height:72px;border-radius:50%;border:2px solid var(--brand);display:flex;align-items:center;justify-content:center;margin-bottom:32px;animation:pop-in .6s cubic-bezier(0.34,1.56,0.64,1) both;}
.success-hero h1{font-family:var(--serif);font-size:clamp(48px,7vw,80px);font-weight:400;letter-spacing:-3px;color:#00020e;line-height:1;margin-bottom:24px;}
.success-hero h1 em{font-style:italic;color:var(--brand);}
.success-hero p{font-size:18px;color:var(--muted);line-height:1.7;max-width:520px;}
.confirm-badge{display:inline-flex;align-items:center;gap:10px;background:#f0fdf4;border:1px solid #bbf7d0;padding:12px 24px;margin-top:28px;font-size:14px;color:#166534;}
.upsell-section{padding:96px 48px;max-width:1280px;margin:0 auto;}
.upsell-title{font-family:var(--serif);font-size:clamp(32px,4vw,48px);font-weight:400;letter-spacing:-2px;color:#00020e;margin-bottom:12px;line-height:1.1;}
.upsell-title em{font-style:italic;color:var(--brand);}
.upsell-sub{font-size:16px;color:var(--muted);margin-bottom:56px;max-width:560px;}
.plans-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:2px;background:var(--border);}
.plan-card{background:var(--surface);padding:36px 32px;position:relative;}
.plan-card.featured{background:var(--navy);}
.plan-badge{position:absolute;top:-12px;left:32px;background:var(--brand);color:white;font-size:10px;font-weight:700;padding:4px 14px;letter-spacing:1px;text-transform:uppercase;}
.plan-name{font-size:13px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-bottom:20px;}
.plan-name.featured{color:rgba(255,255,255,.5);}
.plan-price{font-family:var(--serif);font-size:48px;font-weight:400;letter-spacing:-2px;color:#00020e;line-height:1;margin-bottom:6px;}
.plan-price.featured{color:white;}
.plan-unit{font-size:14px;font-weight:400;color:var(--subtle);letter-spacing:0;font-family:var(--sans);}
.plan-unit.featured{color:rgba(255,255,255,.4);}
.plan-divider{height:1px;background:var(--border);margin:24px 0;}
.plan-divider.featured{background:rgba(255,255,255,.15);}
.plan-features{list-style:none;margin-bottom:32px;}
.plan-features li{font-size:14px;color:var(--muted);padding:8px 0;display:flex;align-items:center;gap:10px;border-bottom:1px solid var(--border);}
.plan-features li:last-child{border-bottom:none;}
.plan-features.featured li{color:rgba(255,255,255,.7);border-color:rgba(255,255,255,.1);}
.plan-features li.off{opacity:.4;}
.fc{color:var(--brand);font-size:13px;flex-shrink:0;}
.fc.featured{color:var(--brand-l);}
.fx{color:var(--subtle);font-size:13px;flex-shrink:0;}
.plan-btn{display:block;text-align:center;padding:14px;font-size:13px;font-weight:700;letter-spacing:1px;text-transform:uppercase;text-decoration:none;transition:180ms;}
.plan-btn-outline{background:transparent;color:var(--navy);border:1.5px solid var(--navy);}
.plan-btn-outline:hover{background:var(--navy);color:white;}
.plan-btn-white{background:white;color:var(--navy);}
.plan-btn-white:hover{background:var(--bg);}
.plan-btn-brand{background:var(--brand);color:white;}
.plan-btn-brand:hover{background:#005A45;}
/* ─── FOOTER ─── */
.footer{background:#07112a;padding:64px 48px 0;}
.footer-grid{max-width:1280px;margin:0 auto;display:grid;grid-template-columns:2fr 1fr 1fr;gap:64px;padding-bottom:48px;}
.footer-brand-name{font-size:20px;font-weight:800;color:white;letter-spacing:-.5px;}
.footer-brand-name span{color:#C9972A;}
.footer-tagline{font-size:13px;color:rgba(255,255,255,.3);margin-top:10px;line-height:1.6;max-width:260px;}
.footer-col-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:2px;color:rgba(255,255,255,.3);margin-bottom:20px;}
.footer-list{list-style:none;}
.footer-list li{margin-bottom:12px;}
.footer-list a{font-size:14px;color:rgba(255,255,255,.5);transition:color 180ms;}
.footer-list a:hover{color:white;}
.footer-bottom{max-width:1280px;margin:0 auto;padding:24px 0;border-top:1px solid rgba(255,255,255,.06);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;}
.footer-copyright{font-size:12px;color:rgba(255,255,255,.2);}
.footer-legal{display:flex;gap:20px;}
.footer-legal a{font-size:12px;color:rgba(255,255,255,.25);transition:color 180ms;}
.footer-legal a:hover{color:rgba(255,255,255,.6);}
/* ─── RESPONSIVE ─── */
@media(max-width:1024px){
  .contact-wrap{grid-template-columns:1fr;gap:48px;}
  .plans-grid{grid-template-columns:1fr;background:none;gap:16px;}
  .plan-card{border:1px solid var(--border);}
  .footer-grid{grid-template-columns:1fr 1fr;}
}
@media(max-width:768px){
  .nav{padding:0 24px;}.nav-links{display:none;}
  .hero{padding:80px 24px 64px;}
  .contact-wrap{padding:0 24px 64px;}
  .faq-section{padding:64px 24px;}
  .faq-grid{grid-template-columns:1fr;}
  .faq-item:nth-child(even){padding-left:0;border-left:none;}
  .cta-section{padding:64px 24px;}
  .footer{padding:48px 24px 0;}
  .footer-grid{grid-template-columns:1fr;gap:32px;}
  .field-row{grid-template-columns:1fr;}
  .success-hero{padding:80px 24px 64px;}
  .upsell-section{padding:64px 24px;}
}
@keyframes pop-in{0%{transform:scale(0) rotate(-20deg);opacity:0}70%{transform:scale(1.2) rotate(3deg)}100%{transform:scale(1) rotate(0);opacity:1}}
@keyframes spin{to{transform:rotate(360deg)}}
@keyframes fall{to{transform:translateY(105vh) rotate(720deg);opacity:0}}
</style>
</head>
<body>

<?php if ($success): ?>
<!-- ═══ PAGE SUCCÈS ═══ -->
<div id="confetti-container" style="position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:999;overflow:hidden"></div>
<div class="success-hero">
  <div class="success-check-circle">
    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--brand)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
  </div>
  <h1>Message reçu,<br><em><?= e(explode(' ', $nomEnvoyeur)[0]) ?> !</em></h1>
  <p>Votre message a bien été transmis à notre équipe.<br>Nous vous répondrons sous <strong>24 heures ouvrables</strong>.</p>
  <div class="confirm-badge">
    <i class="bi bi-envelope-check-fill" style="color:var(--brand)"></i>
    Confirmation envoyée à <strong><?= e($_POST['email'] ?? '') ?></strong>
  </div>
</div>
<!-- Plans upsell -->
<div class="upsell-section">
  <div class="upsell-title">Commencez à préparer<br><em>dès maintenant.</em></div>
  <p class="upsell-sub">Des milliers d'élèves congolais s'entraînent déjà sur RÉUSSITE+.<br>L'examen approche — chaque jour compte.</p>
  <div class="plans-grid">
    <div class="plan-card">
      <div class="plan-name">Gratuit</div>
      <div class="plan-price">0 <span class="plan-unit">CDF/mois</span></div>
      <div class="plan-divider"></div>
      <ul class="plan-features">
        <li><span class="fc"><i class="bi bi-check-lg"></i></span> 5 examens d'entraînement/mois</li>
        <li><span class="fc"><i class="bi bi-check-lg"></i></span> Statistiques de base</li>
        <li class="off"><span class="fx"><i class="bi bi-x-lg"></i></span> Archives officielles</li>
        <li class="off"><span class="fx"><i class="bi bi-x-lg"></i></span> Examens illimités</li>
      </ul>
      <a href="/reussiteplus/inscription.php" class="plan-btn plan-btn-outline">Commencer gratuitement</a>
    </div>
    <div class="plan-card featured" style="position:relative">
      <div class="plan-badge">Le plus populaire</div>
      <div class="plan-name featured">Basique</div>
      <div class="plan-price featured">5 000 <span class="plan-unit featured">CDF/mois</span></div>
      <div class="plan-divider featured"></div>
      <ul class="plan-features featured">
        <li><span class="fc featured"><i class="bi bi-check-lg"></i></span> Examens illimités</li>
        <li><span class="fc featured"><i class="bi bi-check-lg"></i></span> Toutes les archives EPST</li>
        <li><span class="fc featured"><i class="bi bi-check-lg"></i></span> Statistiques avancées</li>
        <li><span class="fc featured"><i class="bi bi-check-lg"></i></span> Résultats détaillés</li>
      </ul>
      <a href="/reussiteplus/inscription.php?plan=BASIQUE" class="plan-btn plan-btn-white">S'abonner →</a>
    </div>
    <div class="plan-card">
      <div class="plan-name">Premium</div>
      <div class="plan-price" style="color:#7C3AED">10 000 <span class="plan-unit">CDF/mois</span></div>
      <div class="plan-divider"></div>
      <ul class="plan-features">
        <li><span class="fc"><i class="bi bi-check-lg"></i></span> Tout le plan Basique</li>
        <li><span class="fc"><i class="bi bi-check-lg"></i></span> IA pédagogique</li>
        <li><span class="fc"><i class="bi bi-check-lg"></i></span> Coaching personnalisé</li>
        <li><span class="fc"><i class="bi bi-check-lg"></i></span> Certificats de réussite</li>
      </ul>
      <a href="/reussiteplus/inscription.php?plan=PREMIUM" class="plan-btn plan-btn-brand">Découvrir Premium →</a>
    </div>
  </div>
  <div style="text-align:center;margin-top:48px">
    <div style="font-size:12px;color:var(--subtle);display:flex;align-items:center;justify-content:center;gap:16px">
      <span>M-Pesa</span><span style="color:var(--border)">·</span><span>Orange Money</span><span style="color:var(--border)">·</span><span>Airtel Money</span><span style="color:var(--border)">·</span><span>Pas de carte bancaire requise</span>
    </div>
  </div>
</div>

<?php else: ?>
<!-- ═══ HERO ═══ -->
<nav class="nav">
  <a href="/reussiteplus/index.php" class="nav-logo"><img src="/reussiteplus/assets/img/logo-icon.svg" alt="RÉUSSITE+" width="32" height="32" style="display:block;flex-shrink:0">
    <span>RÉUSSITE<span class="lplus">+</span></span>
  </a>
  <div class="nav-links">
    <a href="/reussiteplus/index.php#fonctionnalites" class="nav-link">Fonctionnalités</a>
    <a href="/reussiteplus/tarifs.php" class="nav-link">Tarifs</a>
    <a href="/reussiteplus/index.php#temoignages" class="nav-link">Témoignages</a>
    <a href="/reussiteplus/archives.php" class="nav-link">Archives</a>
    <a href="/reussiteplus/contact.php" class="nav-link active">Contact</a>
  </div>
  <div class="nav-actions">
    <?php if ($user): ?>
      <a href="/reussiteplus/dashboard.php" class="btn-nav btn-nav-solid">Mon tableau de bord →</a>
    <?php else: ?>
      <a href="/reussiteplus/connexion.php" class="btn-nav btn-nav-ghost">Connexion</a>
      <a href="/reussiteplus/inscription.php" class="btn-nav btn-nav-solid">Commencer gratuitement →</a>
    <?php endif; ?>
  </div>
</nav>

<!-- ═══ HERO ═══ -->
<div class="hero">
  <p class="hero-eyebrow">Contact</p>
  <h1>Nous <em>contacter.</em></h1>
  <p class="hero-sub">Notre équipe répond sous 24 heures. Pas de robot, pas de formulaire perdu — une vraie réponse humaine.</p>
</div>

<!-- ═══ ERREURS ═══ -->
<?php if ($errors): ?>
<div style="max-width:1280px;margin:0 auto;padding:0 48px 24px">
  <div class="alert alert-error">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <ul class="errors-list">
      <?php foreach ($errors as $err): ?>
        <li><?= e($err) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>
<?php endif; ?>

<!-- ═══ FORMULAIRE + ASIDE ═══ -->
<div class="contact-wrap">
<div>
  <h2 class="section-heading">Envoyer un message.</h2>
  <form id="contactForm" method="post" action="contact.php" style="width:100%">
        <?= csrf_field() ?>
        <div class="field-row field-group">
          <div>
            <label class="field-label" for="nom">Nom complet <span class="field-req">*</span></label>
            <input class="field-input" type="text" id="nom" name="nom"
                   value="<?= e($_POST['nom'] ?? (($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''))) ?>"
                   placeholder="Jean Mukeba" required autocomplete="name">
          </div>
          <div>
            <label class="field-label" for="email">Adresse e-mail <span class="field-req">*</span></label>
            <input class="field-input" type="email" id="email" name="email"
                   value="<?= e($_POST['email'] ?? ($user['email'] ?? '')) ?>"
                   placeholder="jean@exemple.cd" required autocomplete="email">
          </div>
        </div>
        <div class="field-group">
          <label class="field-label" for="sujet">Sujet <span class="field-req">*</span></label>
          <select class="field-input" id="sujet" name="sujet">
            <?php foreach ($sujets as $val => $label): ?>
            <option value="<?= $val ?>" <?= (($_POST['sujet'] ?? 'PLAN') === $val) ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field-group">
          <label class="field-label" for="telephone">Téléphone <span style="font-size:11px;color:var(--subtle);text-transform:none;letter-spacing:0;font-weight:400">(optionnel)</span></label>
          <input class="field-input" type="tel" id="telephone" name="telephone"
                 value="<?= e($_POST['telephone'] ?? '') ?>"
                 placeholder="+243 81 XXX XXXX" autocomplete="tel">
        </div>
        <div class="field-group">
          <label class="field-label" for="message">Message <span class="field-req">*</span></label>
          <textarea class="field-input" id="message" name="message" rows="7"
                    maxlength="2000"
                    placeholder="Décrivez votre demande avec le plus de contexte possible."
                    oninput="document.getElementById('charCount').textContent = this.value.length"
                    required><?= e($_POST['message'] ?? '') ?></textarea>
          <div class="char-count"><span id="charCount"><?= strlen($_POST['message'] ?? '') ?></span> / 2000</div>
        </div>
        <button type="submit" class="btn-submit" id="submitBtn">
          Transmettre la demande
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </button>
        <p class="form-privacy">
          <i class="bi bi-lock-fill"></i>
          Vos données sont protégées et ne sont jamais partagées.
        </p>
      </form>
</div>

  <!-- ASIDE -->
  <div class="contact-aside">
    <div class="aside-cta-card">
      <div class="aside-cta-title">Commencez votre<br>préparation <em>dès aujourd'hui.</em></div>
      <p class="aside-cta-p">Créez un compte gratuit et accédez immédiatement à 5 examens d'entraînement — sans carte bancaire.</p>
      <a href="/reussiteplus/inscription.php" class="aside-cta-btn">
        Commencer gratuitement
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
      </a>
    </div>
    <div class="contact-info">
      <div class="info-item">
        <div class="info-ico"><i class="bi bi-envelope-fill"></i></div>
        <div>
          <div class="info-lbl">Courriel</div>
          <div class="info-val"><a href="mailto:contact@reussiteplus.cd">contact@reussiteplus.cd</a></div>
        </div>
      </div>
      <div class="info-item">
        <div class="info-ico"><i class="bi bi-whatsapp"></i></div>
        <div>
          <div class="info-lbl">WhatsApp</div>
          <div class="info-val">
            <a href="https://wa.me/243977329184" target="_blank" rel="noopener">+243 977 329 184</a><br>
            <span style="font-size:12px;color:var(--subtle)">Lun — Ven, 08:00 — 18:00</span>
          </div>
        </div>
      </div>
      <div class="info-item">
        <div class="info-ico"><i class="bi bi-clock-fill"></i></div>
        <div>
          <div class="info-lbl">Délai de réponse</div>
          <div class="info-val">Moins de <strong>24 heures</strong> en semaine</div>
        </div>
      </div>
    </div>
    <div class="aside-loc">
      <div class="aside-loc-label">
        <i class="bi bi-geo-alt-fill" style="color:rgba(255,255,255,.5)"></i> Localisation
      </div>
      <div class="aside-loc-city">Kinshasa, RDC</div>
      <div class="aside-loc-sub">Pôle Éducatif National</div>
    </div>
  </div>
</div>

<!-- ═══ FAQ ═══ -->
<section class="faq-section">
  <div class="faq-wrap">
    <div class="faq-hd">
      <div class="faq-title">Questions<br><em>fréquentes.</em></div>
      <p class="faq-sub">Vous ne trouvez pas ce que vous cherchez ? Écrivez-nous directement via le formulaire ci-dessus.</p>
    </div>
    <div class="faq-grid">
      <?php foreach ($faq as $i => $f): ?>
      <div class="faq-item" id="faq-<?= $i ?>">
        <div class="faq-q" onclick="toggleFaq(<?= $i ?>)">
          <?= e($f['q']) ?>
          <span class="faq-q-icon">+</span>
        </div>
        <div class="faq-a"><?= e($f['r']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══ CTA ═══ -->
<section class="cta-section">
  <div class="cta-wrap">
    <div class="cta-eyebrow">Prêt à commencer ?</div>
    <h2 class="cta-title">L'examen n'attend pas.<br><em>Vous non plus.</em></h2>
    <p class="cta-sub">Plus de 12 000 élèves congolais se préparent déjà sur RÉUSSITE+.<br>Inscription en 30 secondes, sans carte bancaire.</p>
    <div class="cta-actions">
      <a href="/reussiteplus/inscription.php" class="btn-cta-white">Créer mon compte →</a>
      <a href="/reussiteplus/tarifs.php" class="btn-cta-ghost">Voir les tarifs</a>
    </div>
  </div>
</section>

<?php endif; ?>
<!-- ═══ FOOTER ═══ -->
<footer class="footer">
  <div class="footer-grid">
    <div class="footer-brand">
      <div class="footer-brand-name">
        <img src="/reussiteplus/assets/img/logo-icon.svg" alt="RÉUSSITE+" width="24" height="24" style="display:inline-block;vertical-align:middle;margin-right:6px">
        RÉUSSITE<span class="lplus">+</span>
      </div>
      <p class="footer-tagline">La plateforme de préparation aux examens officiels en République Démocratique du Congo.</p>
    </div>
    <div>
      <div class="footer-col-title">Plateforme</div>
      <ul class="footer-list">
        <li><a href="/reussiteplus/index.php#fonctionnalites">Fonctionnalités</a></li>
        <li><a href="/reussiteplus/tarifs.php">Tarifs</a></li>
        <li><a href="/reussiteplus/archives.php">Archives</a></li>
        <li><a href="/reussiteplus/contact.php">Contact</a></li>
      </ul>
    </div>
    <div>
      <div class="footer-col-title">Compte</div>
      <ul class="footer-list">
        <li><a href="/reussiteplus/connexion.php">Connexion</a></li>
        <li><a href="/reussiteplus/inscription.php">Inscription gratuite</a></li>
        <li><a href="/reussiteplus/inscription.php?plan=BASIQUE">Abonnement Basique</a></li>
        <li><a href="/reussiteplus/inscription.php?plan=PREMIUM">Abonnement Premium</a></li>
      </ul>
    </div>
  </div>
  <div class="footer-bottom">
    <span class="footer-copyright">© <?= date('Y') ?> RÉUSSITE+ · Tous droits réservés</span>
    <div class="footer-legal">
      <a href="/reussiteplus/contact.php">Contact</a>
    </div>
  </div>
</footer>

<script>
function toggleFaq(i) {
  const item = document.getElementById('faq-' + i);
  const isOpen = item.classList.contains('open');
  document.querySelectorAll('.faq-item').forEach(el => el.classList.remove('open'));
  if (!isOpen) item.classList.add('open');
}
const form = document.getElementById('contactForm');
if (form) {
  form.addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span style="display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.4);border-top-color:white;border-radius:50%;animation:spin .7s linear infinite"></span> Envoi en cours...';
  });
}
<?php if ($success): ?>
(function() {
  const colors = ['#007A5E','#00A97F','#0d1b3e','#C9972A','#1E5FAD'];
  const container = document.getElementById('confetti-container');
  if (!container) return;
  for (let i = 0; i < 70; i++) {
    const c = document.createElement('div');
    const color = colors[Math.floor(Math.random() * colors.length)];
    const size = 6 + Math.random() * 8;
    c.style.cssText = `position:absolute;width:${size}px;height:${size}px;background:${color};border-radius:${Math.random()>.5?'50%':'2px'};left:${Math.random()*100}%;top:-20px;animation:fall ${2+Math.random()*3}s ease-in ${Math.random()*2}s forwards;opacity:${0.7+Math.random()*.3}`;
    container.appendChild(c);
  }
  setTimeout(() => container.remove(), 6000);
})();
<?php endif; ?>
</script>
</body>
</html>

