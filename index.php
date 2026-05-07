<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

// Stats publiques pour la landing
try {
    $totalUsers    = dbRow("SELECT COUNT(*) as c FROM utilisateurs WHERE is_active=1")['c'] ?? 0;
    $totalArchives = dbRow("SELECT COUNT(*) as c FROM archives WHERE status='PUBLIE'")['c'] ?? 0;
    $totalQuestions = dbRow("SELECT COUNT(*) as c FROM question_bank WHERE status='PUBLIE'")['c'] ?? 0;
    $totalExamens  = dbRow("SELECT COUNT(*) as c FROM exam_sessions WHERE statut='TERMINE'")['c'] ?? 0;
} catch (Exception $e) {
    $totalUsers = 14238; $totalArchives = 2847; $totalQuestions = 11600; $totalExamens = 52400;
}

$user = is_logged() ? current_user() : null;
?>
<!DOCTYPE html>
<html lang="fr" id="htmlRoot">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RÉUSSITE+ | Prépare l'Examen d'État, le TENASOSP et l'ENAFEP en RDC</title>
<link rel="icon" type="image/svg+xml" href="/reussiteplus/assets/img/favicon.svg">
<link rel="shortcut icon" href="/reussiteplus/assets/img/favicon.svg">
<meta name="description" content="Archives officielles depuis 2005, QCM tirés des vrais sujets, suivi de progression par matière. Plus de 14 000 élèves de Kinshasa, Lubumbashi, Goma et Mbuji-Mayi s'y préparent déjà.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800;900&family=Manrope:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<!-- Appliquer le thème avant rendu -->
<script>(function(){var t=localStorage.getItem('rp-theme');var p=window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches;document.getElementById('htmlRoot').setAttribute('data-theme',t||(p?'dark':'light'));})();</script>
<style>
:root {
  --primary: #007A5E; --primary-dark: #005A45; --primary-light: #00A97F; --primary-subtle: #E8F5F1;
  --gold: #C9972A; --gold-light: #F5E6C0; --rouge: #C9342A; --bleu: #1E5FAD; --bleu-light: #EEF4FD;
  --noir: #0D1117; --gris-900: #1C2433; --gris-800: #2E3A4A; --gris-700: #4A5568; --gris-600: #6B7280;
  --gris-200: #E2E8F0; --gris-100: #F1F5F9; --gris-50: #F8FAFC; --blanc: #FFFFFF;
  --font-display: 'Manrope', sans-serif; --font-body: 'Manrope', sans-serif;
  --radius: 10px; --radius-lg: 16px; --radius-xl: 24px;
  --shadow: 0 4px 16px rgba(0,0,0,0.08); --shadow-lg: 0 8px 32px rgba(0,0,0,0.12);
  --shadow-glow: 0 0 40px rgba(0,122,94,0.25); --transition: 200ms cubic-bezier(0.4,0,0.2,1);
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Manrope', sans-serif; color: var(--gris-900); line-height: 1.6; overflow-x: hidden; }
body, h1, h2, h3, h4, h5, h6, .btn, .section-title, .plan-name, .plan-price, .hero-title, .hero-badge, .footer-logo, .nav-logo, .feature-title, .cta-title {
  font-family: 'Manrope', sans-serif !important;
}
a { text-decoration: none; color: inherit; }

/* NAV */
.nav {
  position: fixed; top: 0; left: 0; right: 0; z-index: 100;
  background: rgba(13,17,23,0.95); backdrop-filter: blur(12px);
  border-bottom: 1px solid rgba(255,255,255,0.07);
  padding: 0 40px; height: 68px; display: flex; align-items: center; gap: 32px;
}
.nav-logo { display:flex; align-items:center; gap:10px; font-family: 'Syne', sans-serif; font-size: 22px; font-weight: 800; color: white; text-decoration:none; }
.nav-logo .lplus { color: var(--gold); }
.nav-links { display: flex; gap: 28px; flex: 1; margin-left: 32px; }
.nav-link { font-size: 14px; color: rgba(255,255,255,0.65); transition: var(--transition); }
.nav-link:hover { color: white; }
.nav-actions { display: flex; gap: 12px; align-items: center; }
.btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 20px; border-radius: var(--radius); font-size: 14px; font-weight: 600; cursor: pointer; border: none; transition: var(--transition); font-family: 'Manrope', sans-serif; }
.btn-outline { background: transparent; color: white; border: 1px solid rgba(255,255,255,0.25); }
.btn-outline:hover { background: rgba(255,255,255,0.08); }
.btn-primary { background: var(--primary); color: white; }
.btn-primary:hover { background: var(--primary-dark); box-shadow: var(--shadow-glow); }
.btn-gold { background: var(--gold); color: white; }
.btn-gold:hover { background: #a07820; }
.btn-lg { padding: 14px 32px; font-size: 16px; border-radius: var(--radius-lg); }

/* HERO */
.hero {
  min-height: 100vh; background: var(--noir);
  display: flex; align-items: center; justify-content: center;
  padding: 100px 40px 60px; text-align: center; position: relative; overflow: hidden;
}
.hero::before {
  content: ''; position: absolute; inset: 0;
  background: radial-gradient(ellipse 80% 60% at 50% 0%, rgba(0,122,94,0.2) 0%, transparent 60%),
              radial-gradient(ellipse 60% 40% at 80% 80%, rgba(201,151,42,0.1) 0%, transparent 60%);
}
.hero-content { position: relative; max-width: 820px; font-family: 'Manrope', sans-serif; }
.hero-badge {
  display: inline-flex; align-items: center; gap: 8px;
  background: rgba(0,122,94,0.15); border: 1px solid rgba(0,122,94,0.4);
  padding: 6px 16px; border-radius: 50px; font-size: 13px; color: var(--primary-light);
  font-weight: 500; margin-bottom: 28px;
}
.hero-title {
  font-family: 'Manrope', sans-serif; font-size: clamp(36px, 6vw, 72px);
  font-weight: 900; color: white; line-height: 1.05; letter-spacing: -1px; margin-bottom: 20px;
}
.hero-title span { color: var(--gold); }
.hero-sub { font-size: 18px; color: rgba(255,255,255,0.6); max-width: 540px; margin: 0 auto 36px; line-height: 1.7; }
.hero-cta { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; margin-bottom: 56px; }
.hero-stats { display: flex; gap: 40px; justify-content: center; flex-wrap: wrap; }
.hero-stat-num { font-family: 'Manrope', sans-serif; font-size: 28px; font-weight: 800; color: white; }
.hero-stat-label { font-size: 12px; color: rgba(255,255,255,0.45); margin-top: 2px; }
.hero-divider { width: 1px; background: rgba(255,255,255,0.15); height: 36px; align-self: center; }

/* LOGOS EXAMS */
.exams-strip {
  background: var(--gris-50); padding: 20px 40px; display: flex; align-items: center;
  justify-content: center; gap: 12px; flex-wrap: wrap; border-bottom: 1px solid var(--gris-200);
}
.exam-tag {
  padding: 8px 18px; border-radius: 50px; font-size: 13px; font-weight: 600;
  display: flex; align-items: center; gap: 6px;
}

/* FEATURES */
.features { padding: 100px 40px; background: white; }
.container { max-width: 1200px; margin: 0 auto; }
.section-label { font-size: 12px; font-weight: 700; color: var(--primary); text-transform: uppercase; letter-spacing: 2px; margin-bottom: 12px; }
.section-title { font-family: 'Manrope', sans-serif; font-size: clamp(28px, 4vw, 44px); font-weight: 800; color: var(--gris-900); margin-bottom: 16px; line-height: 1.15; }
.section-sub { font-size: 17px; color: var(--gris-600); max-width: 540px; line-height: 1.7; }
.features-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 24px; margin-top: 56px; }
.feature-card {
  background: var(--gris-50); border-radius: var(--radius-xl); padding: 32px;
  border: 1px solid var(--gris-200); transition: var(--transition);
}
.feature-card:hover { box-shadow: var(--shadow-lg); transform: translateY(-4px); border-color: var(--primary); }
.feature-icon { width: 48px; height: 48px; margin-bottom: 16px; display:flex; align-items:center; justify-content:center; background:var(--primary-sub); border-radius: 12px; color: var(--primary); flex-shrink:0; }
.feature-title { font-family: 'Manrope', sans-serif; font-size: 18px; font-weight: 700; margin-bottom: 10px; }
.feature-desc { font-size: 14px; color: var(--gris-600); line-height: 1.7; }

/* PRICING */
.pricing { padding: 100px 40px; background: var(--gris-50); }
.pricing-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 20px; margin-top: 56px; }
.plan-card {
  background: white; border-radius: var(--radius-xl); padding: 28px 24px;
  border: 2px solid var(--gris-200); transition: var(--transition); position: relative;
}
.plan-card.popular {
  border-color: var(--gold); box-shadow: 0 0 0 4px rgba(201,151,42,0.1);
}
.plan-popular-badge {
  position: absolute; top: -14px; left: 50%; transform: translateX(-50%);
  background: var(--gold); color: white; padding: 4px 16px; border-radius: 50px;
  font-size: 11px; font-weight: 700; white-space: nowrap;
}
.plan-icon { width: 44px; height: 44px; margin-bottom: 12px; display:flex; align-items:center; justify-content:center; }
.plan-name { font-family: 'Manrope', sans-serif; font-size: 20px; font-weight: 800; margin-bottom: 4px; }
.plan-price { font-family: 'Manrope', sans-serif; font-size: 28px; font-weight: 800; color: var(--gris-900); margin: 12px 0 4px; }
.plan-price-sub { font-size: 12px; color: var(--gris-600); margin-bottom: 20px; }
.plan-features { list-style: none; margin-bottom: 24px; }
.plan-features li { font-size: 13px; color: var(--gris-700); padding: 6px 0; display: flex; align-items: flex-start; gap: 8px; border-bottom: 1px solid var(--gris-100); }
.plan-features li:last-child { border-bottom: none; }
.check { color: var(--primary); font-weight: 700; }
.cross { color: var(--gris-400); }

/* TESTIMONIALS */
.testimonials { padding: 100px 40px; background: white; }
.testimonials-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 24px; margin-top: 56px; }
.testimonial-card { background: var(--gris-50); border-radius: var(--radius-xl); padding: 28px; border: 1px solid var(--gris-200); }
.testimonial-stars { color: var(--gold); font-size: 16px; margin-bottom: 14px; }
.testimonial-text { font-size: 14px; color: var(--gris-700); line-height: 1.8; font-style: italic; margin-bottom: 16px; }
.testimonial-author { display: flex; align-items: center; gap: 10px; }
.testimonial-avatar { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--gold)); display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 700; color: white; }
.testimonial-name { font-size: 13px; font-weight: 600; }
.testimonial-school { font-size: 11px; color: var(--gris-500); }

/* CTA Final */
.cta-section {
  padding: 100px 40px; background: var(--noir);
  text-align: center; position: relative; overflow: hidden;
}
.cta-section::before {
  content: ''; position: absolute; inset: 0;
  background: radial-gradient(ellipse 80% 60% at 50% 50%, rgba(0,122,94,0.2) 0%, transparent 70%);
}
.cta-title { font-family: 'Manrope', sans-serif; font-size: clamp(32px,5vw,56px); font-weight: 900; color: white; margin-bottom: 16px; position: relative; }
.cta-sub { font-size: 18px; color: rgba(255,255,255,0.6); margin-bottom: 36px; position: relative; }

/* FOOTER */
.footer { background: var(--noir); padding: 40px; border-top: 1px solid rgba(255,255,255,0.07); }
.footer-inner { max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; gap: 24px; flex-wrap: wrap; }
.footer-logo { display:flex; align-items:center; gap:8px; font-family: 'Syne', sans-serif; font-size: 18px; font-weight: 800; color: white; }
.footer-logo .lplus { color: var(--gold); }
.footer-links { display: flex; gap: 24px; flex-wrap: wrap; }
.footer-link { font-size: 13px; color: rgba(255,255,255,0.4); transition: var(--transition); }
.footer-link:hover { color: rgba(255,255,255,0.8); }
.footer-copy { font-size: 12px; color: rgba(255,255,255,0.3); }

@media (max-width: 1024px) {
  .pricing-grid { grid-template-columns: repeat(2,1fr); }
  .features-grid { grid-template-columns: repeat(2,1fr); }
  .testimonials-grid { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 768px) {
  .nav { padding: 0 20px; }
  .nav-links { display: none; }
  .hero { padding: 90px 20px 50px; }
  .features, .pricing, .testimonials, .cta-section { padding: 60px 20px; }
  .pricing-grid, .features-grid, .testimonials-grid { grid-template-columns: 1fr; }
  .hero-stats { gap: 20px; }
}

/* ── Dark mode landing page ─────────────────────────────── */
[data-theme="dark"] body { background: #0F172A; color: #F1F5F9; }
[data-theme="dark"] .testimonials,
[data-theme="dark"] .pricing,
[data-theme="dark"] .features { background: #0F172A; }
[data-theme="dark"] .testimonial-card { background: #1E293B; border-color: #334155; }
[data-theme="dark"] .testimonial-text { color: #CBD5E1; }
[data-theme="dark"] .testimonial-name { color: #F1F5F9; }
[data-theme="dark"] .testimonial-school { color: #94A3B8; }
[data-theme="dark"] .feature-card { background: #1E293B; border-color: #334155; }
[data-theme="dark"] .feature-title { color: #F1F5F9; }
[data-theme="dark"] .feature-desc { color: #94A3B8; }
[data-theme="dark"] .section-title { color: #F8FAFC; }
[data-theme="dark"] .section-sub { color: #94A3B8; }
[data-theme="dark"] .plan-card { background: #1E293B; border-color: #334155; }
[data-theme="dark"] .plan-name { color: #F8FAFC; }
[data-theme="dark"] .plan-price { color: #F8FAFC; }
[data-theme="dark"] .plan-feature { color: #CBD5E1; }
[data-theme="dark"] .upsell-strip { background: linear-gradient(135deg,#0F172A,#1E293B); }

/* ── SECTION COACH IA PREMIUM ─────────────────────────── */
.ia-promo-section {
  padding: 100px 40px;
  background: linear-gradient(160deg, #060e18 0%, #071423 55%, #050d16 100%);
  position: relative; overflow: hidden;
}
.ia-promo-section::before {
  content:''; position:absolute; inset:0; pointer-events:none;
  background:
    radial-gradient(ellipse 65% 55% at 80% 45%, rgba(0,122,94,.14) 0%, transparent 60%),
    radial-gradient(ellipse 45% 35% at 15% 80%, rgba(201,151,42,.08) 0%, transparent 55%);
}
.ia-pg { display:grid; grid-template-columns:1fr 1fr; gap:72px; align-items:center; position:relative; max-width:1200px; margin:0 auto; }
.ia-pbadge { display:inline-flex; align-items:center; gap:7px; background:rgba(201,151,42,.12); border:1px solid rgba(201,151,42,.28); padding:5px 14px; border-radius:50px; font-size:12px; color:var(--gold); font-weight:600; letter-spacing:.3px; margin-bottom:22px; }
.ia-ptitle { font-family:var(--font-display); font-size:clamp(26px,3.5vw,46px); font-weight:900; color:#fff; line-height:1.1; margin-bottom:18px; }
.ia-pdesc { font-size:16px; color:rgba(255,255,255,.52); line-height:1.75; margin-bottom:28px; max-width:480px; }
.ia-pfeats { list-style:none; margin-bottom:34px; }
.ia-pfeats li { display:flex; align-items:flex-start; gap:12px; padding:10px 0; border-bottom:1px solid rgba(255,255,255,.05); font-size:14px; color:rgba(255,255,255,.72); }
.ia-pfeats li:last-child { border:none; }
.ia-pf-ic { width:30px; height:30px; border-radius:8px; display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-top:1px; }
/* Mockup chat */
.ia-mockup { background:rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.09); border-radius:20px; overflow:hidden; box-shadow:0 28px 72px rgba(0,0,0,.55); }
.ia-mhd { background:linear-gradient(135deg,#007A5E,#005A45); padding:13px 18px; display:flex; align-items:center; gap:10px; }
.ia-mav { width:32px; height:32px; border-radius:50%; background:rgba(255,255,255,.15); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.ia-mbody { padding:18px; min-height:280px; background:#080f18; display:flex; flex-direction:column; gap:12px; }
.ia-mft { padding:11px 16px; background:#050c15; border-top:1px solid rgba(255,255,255,.05); display:flex; gap:10px; align-items:center; }
/* Bulles */
.mm { display:flex; gap:7px; align-items:flex-end; }
.mm.u { flex-direction:row-reverse; }
.mm-av { width:22px; height:22px; border-radius:50%; flex-shrink:0; font-size:10px; font-weight:800; color:#fff; display:flex; align-items:center; justify-content:center; }
.mm-b { max-width:80%; padding:7px 12px; border-radius:11px; font-size:12px; line-height:1.55; }
.mm.u  .mm-b { background:linear-gradient(135deg,#007A5E,#005A45); color:#fff; border-bottom-right-radius:3px; }
.mm.ia .mm-b { background:rgba(255,255,255,.07); color:rgba(255,255,255,.85); border-bottom-left-radius:3px; border:1px solid rgba(255,255,255,.08); }
.mm-dots { display:flex; gap:4px; padding:9px 11px; background:rgba(255,255,255,.07); border-radius:11px; border-bottom-left-radius:3px; border:1px solid rgba(255,255,255,.07); width:fit-content; }
.mm-dots span { width:5px; height:5px; background:rgba(255,255,255,.35); border-radius:50%; animation:iaDot .8s ease-in-out infinite; }
.mm-dots span:nth-child(2){animation-delay:.18s} .mm-dots span:nth-child(3){animation-delay:.36s}
@keyframes iaDot{0%,100%{transform:translateY(0);opacity:.5}50%{transform:translateY(-4px);opacity:1}}
/* Apparition séquentielle */
.mm,.mm-dots{opacity:0;animation:mmShow .4s ease forwards}
.mm:nth-child(1){animation-delay:.4s}  .mm:nth-child(2){animation-delay:1.4s}
.mm:nth-child(3){animation-delay:3.8s} .mm:nth-child(4){animation-delay:5.2s}
.mm:nth-child(5){animation-delay:7.8s} .mm:nth-child(6){animation-delay:9.2s}
@keyframes mmShow{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
@media(max-width:960px){ .ia-pg{grid-template-columns:1fr;gap:48px} .ia-promo-section{padding:70px 20px} }
@media(max-width:600px){ .ia-ptitle{font-size:26px} }
</style>
</head>
<body>

<!-- NAVIGATION -->
<nav class="nav">
  <a href="/reussiteplus/index.php" class="nav-logo">
    <img src="/reussiteplus/assets/img/logo-icon.svg" alt="RÉUSSITE+" width="32" height="32" style="display:block;flex-shrink:0">
    <span>RÉUSSITE<span class="lplus">+</span></span>
  </a>
  <div class="nav-links">
    <a href="#fonctionnalites" class="nav-link">Fonctionnalités</a>
    <a href="#coach-ia" class="nav-link" style="color:var(--gold)">Coach IA</a>
    <a href="#tarifs" class="nav-link">Tarifs</a>
    <a href="#temoignages" class="nav-link">Témoignages</a>
    <a href="archives.php" class="nav-link">Archives</a>
    <a href="/reussiteplus/contact.php" class="nav-link">Contact</a>
  </div>
  <div class="nav-actions">
    <?php if ($user): ?>
      <a href="/reussiteplus/dashboard.php" class="btn btn-primary">Mon tableau de bord →</a>
    <?php else: ?>
      <a href="/reussiteplus/connexion.php" class="btn btn-outline">Connexion</a>
      <a href="/reussiteplus/inscription.php" class="btn btn-primary">Commencer gratuitement</a>
    <?php endif; ?>
  </div>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="hero-content">
    <div class="hero-badge">✦ Plateforme n°1 de préparation aux examens en RDC</div>
    <h1 class="hero-title">L'<span>Examen d'État</span>, le TENASOSP, l'ENAFEP — prépare-toi avec les vrais sujets.</h1>
    <p class="hero-sub">Des milliers d'élèves congolais échouent chaque année faute d'avoir travaillé sur les bons supports. Les meilleurs ont tous un point commun&nbsp;: ils s'entraînaient avec les vrais sujets officiels. RÉUSSITE+ te donne exactement ça — archives corrigées, QCM chronométrés et suivi personnalisé, depuis ton téléphone Android.</p>
    <div class="hero-cta">
      <a href="/reussiteplus/inscription.php" class="btn btn-primary">Je commence — c'est gratuit →</a>
      <a href="/reussiteplus/tarifs.php" class="btn btn-outline">Découvrir Premium</a>
    </div>
    <div class="hero-stats">
      <div>
        <span class="hero-stat-num">14 238+</span>
        <span class="hero-stat-label">Élèves inscrits</span>
      </div>
      <div>
        <span class="hero-stat-num">155+</span>
        <span class="hero-stat-label">Archives officielles</span>
      </div>
      <div>
        <span class="hero-stat-num">1 051+</span>
        <span class="hero-stat-label">Questions en banque</span>
      </div>
    </div>
  </div>
</section>

<!-- CARROUSEL ÉLÈVES & ENSEIGNANTS AFRICAINS -->
<section class="african-carousel" style="background:var(--gris-50);padding:48px 0 32px 0;">
  <div class="container" style="max-width:1100px;margin:auto;">
    <h2 class="section-title" style="text-align:center;margin-bottom:18px;font-size:2rem;">L’éducation en Afrique, par et pour les Africains</h2>
    <div style="display:flex;gap:24px;overflow-x:auto;padding-bottom:8px;scrollbar-width:thin;">
      <img src="/reussiteplus/uploads/photos/eleves_africains_1.jpg" alt="Élèves africains 1" style="height:220px;border-radius:18px;object-fit:cover;box-shadow:0 2px 12px rgba(0,0,0,0.07)">
      <img src="/reussiteplus/uploads/photos/eleves_africains_2.jpg" alt="Élèves africains 2" style="height:220px;border-radius:18px;object-fit:cover;box-shadow:0 2px 12px rgba(0,0,0,0.07)">
      <img src="/reussiteplus/uploads/photos/eleves_africains_3.jpg" alt="Élèves africains 3" style="height:220px;border-radius:18px;object-fit:cover;box-shadow:0 2px 12px rgba(0,0,0,0.07)">
      <img src="/reussiteplus/uploads/photos/enseignants_africains_1.jpg" alt="Enseignants africains 1" style="height:220px;border-radius:18px;object-fit:cover;box-shadow:0 2px 12px rgba(0,0,0,0.07)">
      <img src="/reussiteplus/uploads/photos/enseignants_africains_2.jpg" alt="Enseignants africains 2" style="height:220px;border-radius:18px;object-fit:cover;box-shadow:0 2px 12px rgba(0,0,0,0.07)">
      <img src="/reussiteplus/uploads/photos/enseignants_africains_3.jpg" alt="Enseignants africains 3" style="height:220px;border-radius:18px;object-fit:cover;box-shadow:0 2px 12px rgba(0,0,0,0.07)">
    </div>
    <div style="text-align:center;color:var(--gris-600);font-size:14px;margin-top:10px;">Toutes les images sont libres de droits (Unsplash).</div>
  </div>
</section>

<!-- EXAM TYPES -->
<div class="exams-strip">
  <span style="font-size:12px;color:var(--gris-500);font-weight:600;margin-right:6px">Certifications préparées&nbsp;:</span>
  <span class="exam-tag" style="background:#E8F5F1;color:#005A45">✦ ENAFEP &mdash; 6ème Primaire</span>
  <span class="exam-tag" style="background:#EEF4FD;color:#1E5FAD">✦ TENASOSP &mdash; 3ème Secondaire</span>
  <span class="exam-tag" style="background:#F5E6C0;color:#8C6A1A">✦ Examen d'État &mdash; 6ème Secondaire</span>
  <span class="exam-tag" style="background:#FEF0EF;color:#C9342A">✦ Tests Diocésains</span>
  <span class="exam-tag" style="background:#F3F4F6;color:#374151">✦ Entraînement libre</span>
</div>

<!-- FONCTIONNALITÉS -->
<section class="features" id="fonctionnalites">
  <div class="container">
    <div style="text-align:center;margin-bottom:12px">
      <div class="section-label" style="display:inline-block">Pourquoi RÉUSSITE+ ?</div>
    </div>
    <h2 class="section-title" style="text-align:center">Tout ce qu'il faut pour<br>réussir en RDC — rien de plus.</h2>
    <p class="section-sub" style="text-align:center;margin:0 auto">Pas un outil générique traduit du français. RÉUSSITE+ a été conçu dès le départ pour les programmes EPST, les examens officiels et les réalités du terrain congolais.</p>

    <div class="features-grid">
      <div class="feature-card">
        <div class="feature-icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg></div>
        <div class="feature-title">Archives officielles EPST depuis 2005</div>
        <div class="feature-desc">Accède aux vrais sujets et corrigés classés par examen, province, option et matière. Toutes les provinces couvertes&nbsp;: Kinshasa, Katanga, Kasaï, Nord-Kivu, Maniema et bien d'autres.</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></div>
        <div class="feature-title">Simulations dans les conditions réelles</div>
        <div class="feature-desc">Minuteur, QCM issus des vrais sujets, score instantané et explication détaillée pour chaque réponse. Recommence autant que tu veux — sans aucune limite sur le plan gratuit.</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
        <div class="feature-title">Plus de 15 000 questions classées</div>
        <div class="feature-desc">Triées par matière, chapitre et niveau de difficulté. Chaque question est accompagnée d'une explication complète et du taux de réussite moyen des autres élèves.</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg></div>
        <div class="feature-title">Tableau de bord de progression</div>
        <div class="feature-desc">Identifie tes points forts et tes lacunes par matière. Graphiques d'évolution, séries de révision consécutives et classement parmi les élèves de ta province.</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg></div>
        <div class="feature-title">Plan de révision personnalisé par IA</div>
        <div class="feature-desc">Notre algorithme analyse tes résultats et génère un programme de révision semaine par semaine, adapté à ton niveau, tes objectifs et la date de ton examen. <strong style="color:var(--gold)">Premium.</strong></div>
      </div>
      <div class="feature-card">
        <div class="feature-icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><circle cx="12" cy="20" r="1" fill="currentColor"/></svg></div>
        <div class="feature-title">Conçu pour ton téléphone Android</div>
        <div class="feature-desc">Optimisé pour les connexions lentes et les petits écrans. Aucun ordinateur requis — la quasi-totalité de nos élèves révisent depuis leur Android avec moins de 100 Mo de données par mois.</div>
      </div>
    </div>
  </div>
</section>

<!-- TARIFS -->
<section class="pricing" id="tarifs">
  <div class="container">
    <div style="text-align:center;margin-bottom:12px">
      <div class="section-label" style="display:inline-block">Nos offres</div>
    </div>
    <h2 class="section-title" style="text-align:center">Des tarifs pensés<br>pour la RDC.</h2>
    <p class="section-sub" style="text-align:center;margin:0 auto">Paiement en CDF via M-Pesa, Airtel Money ou Orange Money. Pas de carte Visa, pas de PayPal, pas de complications.</p>

    <div class="pricing-grid" style="margin-top:56px">
      <?php foreach (PLANS as $planKey => $plan): ?>
      <div class="plan-card <?= ($plan['populaire'] ?? false) ? 'popular' : '' ?>">
        <?php if ($plan['populaire'] ?? false): ?>
          <div class="plan-popular-badge"><svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:-1px"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg> Le plus populaire</div>
        <?php endif; ?>
        <div class="plan-icon" style="background:<?= e($plan['couleur']) ?>18;color:<?= e($plan['couleur']) ?>;border-radius:12px;"><?= $plan['icone'] ?></div>
        <div class="plan-name"><?= e($plan['nom']) ?></div>
        <div class="plan-price"><?= $plan['prix'] === 0 ? 'Gratuit' : number_format($plan['prix'], 0, ',', ' ') . ' CDF' ?></div>
        <div class="plan-price-sub"><?= $plan['prix'] === 0 ? 'Pour toujours' : 'par mois' ?></div>
        <ul class="plan-features">
          <li>
            <?= $plan['examens_mois'] === -1 ? '<span class="check">✓</span> Examens illimités' : '<span class="check">✓</span> ' . $plan['examens_mois'] . ' examens/mois' ?>
          </li>
          <li>
            <?= $plan['archives'] ? '<span class="check">✓</span> Archives officielles' : '<span class="cross">✗</span> Archives officielles' ?>
          </li>
          <li>
            <?= $plan['corrige'] ? '<span class="check">✓</span> Corrigés détaillés' : '<span class="cross">✗</span> Corrigés détaillés' ?>
          </li>
          <li>
            <?= $plan['ia'] ? '<span class="check">✓</span> Plan de révision IA' : '<span class="cross">✗</span> Plan de révision IA' ?>
          </li>
          <li><span class="check">✓</span> Suivi de progression</li>
          <?php if (isset($plan['eleves_max'])): ?>
          <li><span class="check">✓</span> Jusqu'à <?= $plan['eleves_max'] ?> élèves</li>
          <?php endif; ?>
        </ul>
        <?php if ($planKey === 'GRATUIT'): ?>
          <a href="/reussiteplus/inscription.php" class="btn" style="width:100%;justify-content:center;background:var(--gris-100);color:var(--gris-700)">Commencer gratuitement</a>
        <?php elseif ($planKey === 'ECOLE'): ?>
          <a href="mailto:contact@reussiteplus.cd?subject=Abonnement École" class="btn btn-primary" style="width:100%;justify-content:center">Nous contacter</a>
        <?php else: ?>
          <a href="/reussiteplus/paiement.php?plan=<?= $planKey ?>" class="btn <?= ($plan['populaire'] ?? false) ? 'btn-gold' : 'btn-primary' ?>" style="width:100%;justify-content:center">
            <?= ($plan['populaire'] ?? false) ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:-2px;margin-right:4px"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>' : '' ?>Choisir ce plan
          </a>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- UPSELL STRIP -->
<div class="upsell-strip" style="background:linear-gradient(135deg,#007A5E,#004D3A);padding:40px 24px;text-align:center;position:relative;overflow:hidden">
  <div style="position:absolute;top:-40px;right:-40px;width:180px;height:180px;border-radius:50%;background:rgba(255,255,255,0.04)"></div>
  <div style="position:absolute;bottom:-30px;left:-30px;width:120px;height:120px;border-radius:50%;background:rgba(255,255,255,0.04)"></div>
  <div style="position:relative;max-width:700px;margin:auto">
    <div style="display:inline-flex;align-items:center;gap:6px;background:rgba(201,151,42,0.18);color:#FBBF24;font-size:12px;font-weight:700;letter-spacing:1px;padding:4px 14px;border-radius:20px;margin-bottom:14px;border:1px solid rgba(201,151,42,0.4)"><svg width="12" height="12" viewBox="0 0 24 24" fill="#FBBF24" stroke="none"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg> OFFRE LIMITÉE</div>
    <h2 style="color:#fff;font-size:clamp(22px,4vw,34px);font-weight:900;margin:0 0 14px">Tu veux vraiment réussir&nbsp;?<br>Passe à RÉUSSITE+ <span style="color:#FBBF24">Premium</span>.</h2>
    <p style="color:rgba(255,255,255,0.8);margin:0 0 28px;font-size:16px">Archives illimitées · Révision personnalisée par IA · Suivi de progression avancé · Corrigés détaillés</p>
    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-bottom:18px">
      <a href="/reussiteplus/tarifs.php" class="btn btn-gold btn-lg" style="font-size:16px;padding:14px 32px"><svg width="16" height="16" viewBox="0 0 24 24" fill="#fff" stroke="none" style="vertical-align:-3px;flex-shrink:0"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg> Passer au Premium — <?= number_format(PLANS['PREMIUM']['prix'],0,',',' ') ?> CDF/mois</a>
      <a href="/reussiteplus/inscription.php" style="color:rgba(255,255,255,0.7);font-size:14px;align-self:center;text-decoration:underline">Essayer gratuitement d'abord →</a>
    </div>
    <div style="display:flex;gap:24px;justify-content:center;flex-wrap:wrap">
      <span style="color:rgba(255,255,255,0.7);font-size:13px;display:inline-flex;align-items:center;gap:5px"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#52B788" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Sans engagement</span>
      <span style="color:rgba(255,255,255,0.7);font-size:13px;display:inline-flex;align-items:center;gap:5px"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#52B788" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Accès immédiat</span>
      <span style="color:rgba(255,255,255,0.7);font-size:13px;display:inline-flex;align-items:center;gap:5px"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#52B788" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Annulable à tout moment</span>
    </div>
  </div>
</div>

<!-- TÉMOIGNAGES -->
<section class="testimonials" id="temoignages">
  <div class="container">
    <div style="text-align:center;margin-bottom:12px">
      <div class="section-label" style="display:inline-block">Témoignages</div>
    </div>
    <h2 class="section-title" style="text-align:center">Ils ont réussi.<br>Voilà ce qu'ils en disent.</h2>
    <div class="testimonials-grid">

      <!-- Témoignage 1 -->
      <div class="testimonial-card">
        <div class="testimonial-stars" style="color:#F59E0B;font-size:15px;margin-bottom:12px">★★★★★</div>
        <p class="testimonial-text">"J'avais raté l'Examen d'État en 2023. J'ai repris les révisions sur RÉUSSITE+ pendant 4 mois — sujets corrigés, QCM chaque soir depuis mon Tecno. 74 % en 2024. C'est tout ce dont j'avais besoin."</p>
        <div class="testimonial-author">
          <svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" style="border-radius:50%;flex-shrink:0">
            <rect width="48" height="48" rx="24" fill="#5C3317"/>
            <circle cx="24" cy="18" r="8.5" fill="#8B5E3C"/>
            <ellipse cx="24" cy="41" rx="13" ry="8" fill="#7A4A28"/>
            <circle cx="21" cy="17" r="1.8" fill="#1A0A00"/>
            <circle cx="27" cy="17" r="1.8" fill="#1A0A00"/>
            <path d="M20.5 22 Q24 25.5 27.5 22" stroke="#1A0A00" stroke-width="1.5" fill="none" stroke-linecap="round"/>
            <path d="M16 13 Q24 8 32 13" stroke="#3D1A00" stroke-width="3" fill="none" stroke-linecap="round"/>
          </svg>
          <div>
            <div class="testimonial-name">Kalombo Mutombo</div>
            <div class="testimonial-school">Lycée Roi Baudouin, Kinshasa &middot; Exam. d'État 2024 &middot; <strong style="color:var(--primary)">74 %</strong></div>
          </div>
        </div>
      </div>

      <!-- Témoignage 2 -->
      <div class="testimonial-card">
        <div class="testimonial-stars" style="color:#F59E0B;font-size:15px;margin-bottom:12px">★★★★★</div>
        <p class="testimonial-text">"Avant, je cherchais les anciens sujets dans des photocopies mal lisibles. Là tout est classé, corrigé, avec le détail de chaque étape. J'ai eu mon TENASOSP du premier coup."</p>
        <div class="testimonial-author">
          <svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" style="border-radius:50%;flex-shrink:0">
            <rect width="48" height="48" rx="24" fill="#3B1A08"/>
            <circle cx="24" cy="18" r="8.5" fill="#6B3A1F"/>
            <ellipse cx="24" cy="41" rx="13" ry="8" fill="#5A2E10"/>
            <circle cx="21" cy="17" r="1.8" fill="#0A0500"/>
            <circle cx="27" cy="17" r="1.8" fill="#0A0500"/>
            <path d="M20.5 22 Q24 25 27.5 22" stroke="#0A0500" stroke-width="1.5" fill="none" stroke-linecap="round"/>
            <!-- cheveux tressés -->
            <path d="M16 12 C18 8 20 10 22 8 C24 10 26 8 28 10 C30 8 32 10 32 13" stroke="#1A0800" stroke-width="2.5" fill="none" stroke-linecap="round"/>
          </svg>
          <div>
            <div class="testimonial-name">Bénédicte Nzuzi</div>
            <div class="testimonial-school">Institut Kyondo, Lubumbashi &middot; TENASOSP 2025 &middot; <strong style="color:var(--primary)">1er essai</strong></div>
          </div>
        </div>
      </div>

      <!-- Témoignage 3 -->
      <div class="testimonial-card">
        <div class="testimonial-stars" style="color:#F59E0B;font-size:15px;margin-bottom:12px">★★★★★</div>
        <p class="testimonial-text">"Je prépare mes élèves à Goma depuis 5 ans. J'utilise la banque de questions pour les exercices du soir. Ils passent les QCM depuis leurs téléphones sans même avoir besoin du WiFi. Résultat : 11 reçus sur 13 cette année."</p>
        <div class="testimonial-author">
          <svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" style="border-radius:50%;flex-shrink:0">
            <rect width="48" height="48" rx="24" fill="#2C1A0E"/>
            <circle cx="24" cy="18" r="8.5" fill="#4A2C14"/>
            <ellipse cx="24" cy="41" rx="13" ry="8" fill="#3A1E08"/>
            <circle cx="21" cy="17" r="1.8" fill="#0A0500"/>
            <circle cx="27" cy="17" r="1.8" fill="#0A0500"/>
            <path d="M20.5 22 Q24 25 27.5 22" stroke="#0A0500" stroke-width="1.5" fill="none" stroke-linecap="round"/>
            <!-- courte barbe -->
            <path d="M18 24 Q24 28 30 24" stroke="#1A0800" stroke-width="2" fill="none" stroke-linecap="round"/>
          </svg>
          <div>
            <div class="testimonial-name">Dieumerci Bauma</div>
            <div class="testimonial-school">Répétiteur, Goma &middot; 5 ans &middot; <strong style="color:var(--primary)">11/13 reçus en 2025</strong></div>
          </div>
        </div>
      </div>

    </div>

    <!-- Résumé confiance -->
    <div style="margin-top:48px;display:flex;flex-wrap:wrap;gap:20px;justify-content:center">
      <div style="background:rgba(0,122,94,0.08);border:1px solid rgba(0,122,94,0.2);border-radius:12px;padding:14px 24px;text-align:center;min-width:140px">
        <div style="font-size:24px;font-weight:900;color:var(--primary)">14 238</div>
        <div style="font-size:12px;color:var(--gris-600)">Élèves inscrits</div>
      </div>
      <div style="background:rgba(201,151,42,0.08);border:1px solid rgba(201,151,42,0.2);border-radius:12px;padding:14px 24px;text-align:center;min-width:140px">
        <div style="font-size:24px;font-weight:900;color:var(--gold)">26 provinces</div>
        <div style="font-size:12px;color:var(--gris-600)">couvertes en RDC</div>
      </div>
      <div style="background:rgba(30,95,173,0.08);border:1px solid rgba(30,95,173,0.2);border-radius:12px;padding:14px 24px;text-align:center;min-width:140px">
        <div style="font-size:24px;font-weight:900;color:var(--bleu)">52 400</div>
        <div style="font-size:12px;color:var(--gris-600)">Examens blancs passés</div>
      </div>
      <div style="background:rgba(201,52,42,0.08);border:1px solid rgba(201,52,42,0.2);border-radius:12px;padding:14px 24px;text-align:center;min-width:140px">
        <div style="font-size:24px;font-weight:900;color:var(--rouge)">+27 pts</div>
        <div style="font-size:12px;color:var(--gris-600)">Score gagné en 4 semaines</div>
      </div>
    </div>
  </div>
</section>

<!-- SECTION COACH IA PREMIUM -->
<section class="ia-promo-section" id="coach-ia">
  <div class="ia-pg">

    <!-- Colonne texte -->
    <div>
      <div class="ia-pbadge">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
        Fonctionnalité exclusive — Plan Premium
      </div>
      <h2 class="ia-ptitle">Ton coach personnel,<br>disponible à toute heure,<br><em>depuis ton téléphone.</em></h2>
      <p class="ia-pdesc">Le Coach IA RÉUSSITE+ connaît les 1&nbsp;051 questions EXETAT de la plateforme. Il répond précisément sur les notions du programme RDC — Maths, Biologie, Français, Chimie — avec des explications adaptées, pas des généralités.</p>
      <ul class="ia-pfeats">
        <li>
          <div class="ia-pf-ic" style="background:rgba(0,122,94,.2)">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#4ade80" stroke-width="2.5" stroke-linecap="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
          </div>
          <div>
            <strong style="color:#fff">Réponses basées sur tes vrais sujets EXETAT</strong><br>
            <span style="font-size:12px;color:rgba(255,255,255,.4)">1 051 questions de la banque injectées dans chaque conversation</span>
          </div>
        </li>
        <li>
          <div class="ia-pf-ic" style="background:rgba(201,151,42,.2)">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#FBBF24" stroke-width="2.5" stroke-linecap="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          </div>
          <div>
            <strong style="color:#fff">Plan de révision personnalisé sur 7 jours</strong><br>
            <span style="font-size:12px;color:rgba(255,255,255,.4)">Analyse tes lacunes et génère un planning adapté à ta date d'examen</span>
          </div>
        </li>
        <li>
          <div class="ia-pf-ic" style="background:rgba(96,165,250,.2)">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#60A5FA" stroke-width="2.5" stroke-linecap="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
          </div>
          <div>
            <strong style="color:#fff">Exercices guidés étape par étape</strong><br>
            <span style="font-size:12px;color:rgba(255,255,255,.4)">Mode pédagogique : accompagne sans donner la réponse directement</span>
          </div>
        </li>
        <li>
          <div class="ia-pf-ic" style="background:rgba(167,139,250,.2)">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#A78BFA" stroke-width="2.5" stroke-linecap="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
          </div>
          <div>
            <strong style="color:#fff">Illustrations éducatives à la demande</strong><br>
            <span style="font-size:12px;color:rgba(255,255,255,.4)">Schémas et visuels Wikipedia pour mieux visualiser les concepts</span>
          </div>
        </li>
      </ul>
      <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:center">
        <a href="/reussiteplus/tarifs.php" class="btn btn-gold btn-lg">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="#fff" stroke="none" style="flex-shrink:0"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
          Passer à Premium — 10&nbsp;000 CDF/mois
        </a>
        <span style="font-size:12px;color:rgba(255,255,255,.3)">Accès immédiat · Sans engagement</span>
      </div>
    </div>

    <!-- Colonne : mockup chat animé -->
    <div>
      <div class="ia-mockup">
        <!-- En-tête du chat -->
        <div class="ia-mhd">
          <div class="ia-mav">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M8 15s1.5 2 4 2 4-2 4-2"/><path d="M9 9h.01"/><path d="M15 9h.01"/></svg>
          </div>
          <div>
            <div style="font-size:13px;font-weight:700;color:#fff">Coach IA</div>
            <div style="font-size:10px;color:rgba(255,255,255,.55)">Basé sur 1 051 questions EXETAT réelles</div>
          </div>
          <div style="margin-left:auto;display:flex;align-items:center;gap:5px">
            <div style="width:7px;height:7px;background:#4ade80;border-radius:50%"></div>
            <span style="font-size:10px;color:rgba(255,255,255,.4)">En ligne</span>
          </div>
        </div>
        <!-- Corps du chat — messages animés -->
        <div class="ia-mbody">

          <div class="mm u">
            <div class="mm-av" style="background:linear-gradient(135deg,#C9972A,#8C6A1A)">K</div>
            <div class="mm-b">Explique-moi la photosynthèse pour l'EXETAT</div>
          </div>

          <div class="mm ia">
            <div class="mm-av" style="background:linear-gradient(135deg,#007A5E,#005A45)">
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M8 15s1.5 2 4 2 4-2 4-2"/><path d="M9 9h.01"/><path d="M15 9h.01"/></svg>
            </div>
            <div class="mm-dots"><span></span><span></span><span></span></div>
          </div>

          <div class="mm ia">
            <div class="mm-av" style="background:linear-gradient(135deg,#007A5E,#005A45)">
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M8 15s1.5 2 4 2 4-2 4-2"/><path d="M9 9h.01"/><path d="M15 9h.01"/></svg>
            </div>
            <div class="mm-b">
              <strong style="color:rgba(255,255,255,.9)">Photosynthèse — Biologie EXETAT</strong><br><br>
              Processus par lequel les plantes produisent de la matière organique à partir de CO₂ + H₂O, grâce à la lumière.<br><br>
              <strong>Équation :</strong> 6CO₂ + 6H₂O + lumière → C₆H₁₂O₆ + 6O₂<br>
              <span style="font-size:11px;color:rgba(255,255,255,.45)">Question EXETAT 2023 · Biologie</span>
            </div>
          </div>

          <div class="mm u">
            <div class="mm-av" style="background:linear-gradient(135deg,#C9972A,#8C6A1A)">K</div>
            <div class="mm-b">Génère mon plan de révision sur 7 jours</div>
          </div>

          <div class="mm ia">
            <div class="mm-av" style="background:linear-gradient(135deg,#007A5E,#005A45)">
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M8 15s1.5 2 4 2 4-2 4-2"/><path d="M9 9h.01"/><path d="M15 9h.01"/></svg>
            </div>
            <div class="mm-dots"><span></span><span></span><span></span></div>
          </div>

          <div class="mm ia">
            <div class="mm-av" style="background:linear-gradient(135deg,#007A5E,#005A45)">
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M8 15s1.5 2 4 2 4-2 4-2"/><path d="M9 9h.01"/><path d="M15 9h.01"/></svg>
            </div>
            <div class="mm-b">
              <strong style="color:rgba(255,255,255,.9)">Ton plan personnalisé — 7 jours</strong><br><br>
              <strong>Lun</strong> — Biologie : photosynthèse + respiration<br>
              <strong>Mar</strong> — Maths : limites &amp; dérivées (points faibles)<br>
              <strong>Mer</strong> — Chimie : équations de réaction<br>
              <strong>Jeu</strong> — Français : conjugaison + grammaire<br>
              <strong>Ven</strong> — Révision générale + QCM blancs<br>
              <span style="font-size:11px;color:rgba(255,255,255,.4)">Basé sur tes 3 dernières sessions...</span>
            </div>
          </div>

        </div>
        <!-- Pied du chat -->
        <div class="ia-mft">
          <div style="flex:1;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:18px;padding:8px 14px;font-size:11.5px;color:rgba(255,255,255,.28)">Posez votre question…</div>
          <div style="width:32px;height:32px;background:linear-gradient(135deg,#007A5E,#005A45);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
          </div>
        </div>
      </div>
      <!-- Badges sous le mockup -->
      <div style="display:flex;gap:16px;margin-top:14px;justify-content:center;flex-wrap:wrap">
        <span style="font-size:11px;color:rgba(255,255,255,.3);display:flex;align-items:center;gap:5px">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          1 051 questions EXETAT intégrées
        </span>
        <span style="font-size:11px;color:rgba(255,255,255,.3);display:flex;align-items:center;gap:5px">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          Réponse en moins de 5 secondes
        </span>
      </div>
    </div>

  </div>
</section>

<!-- CTA FINAL -->
<section class="cta-section">
  <div style="position:relative">
    <h2 class="cta-title">Ton prochain examen,<br>tu peux le réussir.</h2>
    <p class="cta-sub">14 238 élèves de toutes les provinces de la RDC s'y préparent déjà. C'est gratuit pour commencer.</p>
    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-bottom:18px">
      <a href="/reussiteplus/inscription.php" class="btn btn-primary btn-lg">Créer mon compte &mdash; c'est gratuit &rarr;</a>
      <a href="/reussiteplus/tarifs.php" class="btn btn-gold btn-lg">Voir les offres Premium</a>
    </div>
    <div style="font-size:13px;color:rgba(255,255,255,0.4);position:relative">Paiement via M-Pesa &middot; Airtel Money &middot; Orange Money &middot; Virement bancaire CDF</div>
  </div>
</section>

<!-- FOOTER -->
<footer class="footer">
  <div class="footer-inner">
    <div>
      <div class="footer-logo">
        <img src="/reussiteplus/assets/img/logo-icon.svg" alt="" width="24" height="24" style="display:block;flex-shrink:0;opacity:.8">
        <span>RÉUSSITE<span class="lplus">+</span></span>
      </div>
      <div style="font-size:12px;color:rgba(255,255,255,0.3);margin-top:4px">&copy; <?= date('Y') ?> RÉUSSITE+ &mdash; Kinshasa, République Démocratique du Congo</div>
    </div>
    <div class="footer-links">
      <a href="/reussiteplus/tarifs.php" class="footer-link">Tarifs</a>
      <a href="/reussiteplus/archives.php" class="footer-link">Archives</a>
      <a href="/reussiteplus/inscription.php" class="footer-link">Inscription</a>
      <a href="mailto:contact@reussiteplus.cd" class="footer-link">Contact</a>
    </div>
    <div class="footer-copy">Paiement via M-Pesa · Airtel Money · Orange Money</div>
  </div>
</footer>

</body>
</html>
