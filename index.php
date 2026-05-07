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
    $totalUsers = 12400; $totalArchives = 3200; $totalQuestions = 15000; $totalExamens = 48000;
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
<meta name="description" content="Archives officielles depuis 2005, QCM tirés des vrais sujets, suivi de progression par matière. Plus de 14 000 élèves de Kinshasa, Lubumbashi, Goma et Mbuji-Mayi s'y préparent déjà.">
<link rel="stylesheet" href="/reussiteplus/assets/css/fonts.css">
<link rel="stylesheet" href="/reussiteplus/assets/css/bootstrap-icons.css">
<style>
:root {
  --primary: #007A5E; --primary-dark: #005A45; --primary-light: #00A97F; --primary-subtle: #E8F5F1;
  --gold: #C9972A; --gold-light: #F5E6C0; --rouge: #C9342A; --bleu: #1E5FAD; --bleu-light: #EEF4FD;
  --noir: #0D1117; --gris-900: #1C2433; --gris-800: #2E3A4A; --gris-700: #4A5568; --gris-600: #6B7280;
  --gris-200: #E2E8F0; --gris-100: #F1F5F9; --gris-50: #F8FAFC; --blanc: #FFFFFF;
  --font-display: 'Poppins', sans-serif; --font-body: 'Poppins', sans-serif; /* DRC landing */
  --radius: 10px; --radius-lg: 16px; --radius-xl: 24px;
  --shadow: 0 4px 16px rgba(0,0,0,0.08); --shadow-lg: 0 8px 32px rgba(0,0,0,0.12);
  --shadow-glow: 0 0 40px rgba(0,122,94,0.25); --transition: 200ms cubic-bezier(0.4,0,0.2,1);
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: var(--font-body); color: var(--gris-900); line-height: 1.6; overflow-x: hidden; }
a { text-decoration: none; color: inherit; }

/* NAV */
.nav {
  position: fixed; top: 0; left: 0; right: 0; z-index: 100;
  background: rgba(13,17,23,0.9); backdrop-filter: blur(20px);
  border-bottom: 1px solid rgba(255,255,255,0.06);
  padding: 0 48px; height: 70px;
  display: flex; align-items: center; gap: 24px;
  transition: background .3s, border-color .3s;
}
.nav.scrolled { background: rgba(13,17,23,0.98); border-color: rgba(255,255,255,0.1); }
.nav-logo-link { display: flex; align-items: center; gap: 10px; text-decoration: none; flex-shrink: 0; }
.nav-logo { font-family: var(--font-display); font-size: 19px; font-weight: 800; color: #fff; letter-spacing: -.3px; }
.nav-logo em { color: var(--gold); font-style: normal; }
.nav-links { display: flex; gap: 2px; flex: 1; justify-content: center; }
.nav-link { font-size: 14px; color: rgba(255,255,255,.6); padding: 7px 13px; border-radius: 8px; transition: all .18s; font-weight: 500; position: relative; }
.nav-link:hover { color: #fff; background: rgba(255,255,255,.06); }
.nav-actions { display: flex; gap: 10px; align-items: center; flex-shrink: 0; }
.nav-hamburger { display: none; flex-direction: column; gap: 4.5px; background: none; border: 1.5px solid rgba(255,255,255,.15); border-radius: 8px; cursor: pointer; padding: 8px 10px; }
.nav-hamburger span { display: block; width: 18px; height: 1.5px; background: rgba(255,255,255,.8); border-radius: 2px; transition: all .25s; }
.nav-mobile { display: none; position: fixed; top: 70px; left: 0; right: 0; background: rgba(10,14,20,.98); backdrop-filter: blur(20px); border-bottom: 1px solid rgba(255,255,255,.08); padding: 16px 24px 24px; flex-direction: column; gap: 4px; z-index: 99; }
.nav-mobile.open { display: flex; animation: navSlide .2s ease; }
@keyframes navSlide { from { opacity:0; transform:translateY(-8px) } to { opacity:1; transform:translateY(0) } }
.nav-mobile-link { font-size: 15px; color: rgba(255,255,255,.65); padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,.05); font-weight: 500; }
.nav-mobile-link:hover { color: #fff; }
.nav-mobile-divider { height: 1px; background: rgba(255,255,255,.07); margin: 10px 0; }
@media (max-width: 960px) {
  .nav { padding: 0 20px; }
  .nav-links { display: none; }
  .nav-hamburger { display: flex; }
  .nav-actions .btn-outline { display: none; }
}
.btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 20px; border-radius: var(--radius); font-size: 14px; font-weight: 600; cursor: pointer; border: none; transition: var(--transition); font-family: var(--font-body); }
.btn-outline { background: transparent; color: white; border: 1px solid rgba(255,255,255,0.25); }
.btn-outline:hover { background: rgba(255,255,255,0.08); }
.btn-primary { background: var(--primary); color: white; }
.btn-primary:hover { background: var(--primary-dark); box-shadow: var(--shadow-glow); }
.btn-gold { background: var(--gold); color: white; }
.btn-gold:hover { background: #a07820; }
.btn-lg { padding: 14px 32px; font-size: 16px; border-radius: var(--radius-lg); }

/* HERO */
.hero {
  min-height: 92vh; background: var(--noir);
  display: grid; grid-template-columns: 1fr 1fr; gap: 60px;
  align-items: center; padding: 100px 80px 80px;
  max-width: 1400px; margin: 0 auto; position: relative;
}
.hero::before {
  content: ''; position: absolute; inset: 0;
  background: radial-gradient(ellipse 80% 60% at 30% 0%, rgba(0,122,94,0.2) 0%, transparent 60%),
              radial-gradient(ellipse 60% 40% at 90% 80%, rgba(201,151,42,0.1) 0%, transparent 60%);
  pointer-events: none;
}
.hero-content { position: relative; z-index: 1; }
.hero-badge {
  display: inline-flex; align-items: center; gap: 8px;
  background: rgba(0,122,94,0.15); border: 1px solid rgba(0,122,94,0.4);
  padding: 6px 16px; border-radius: 50px; font-size: 13px; color: var(--primary-light);
  font-weight: 500; margin-bottom: 28px;
}
.hero-title {
  font-family: var(--font-display); font-size: clamp(34px, 5vw, 62px);
  font-weight: 900; color: white; line-height: 1.05; letter-spacing: -1px; margin-bottom: 20px;
}
.hero-title span { color: var(--gold); }
.hero-sub { font-size: 17px; color: rgba(255,255,255,0.6); max-width: 480px; margin-bottom: 36px; line-height: 1.7; }
.hero-cta { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 56px; }
.hero-stats { display: flex; gap: 32px; flex-wrap: wrap; }
.hero-stat-num { font-family: var(--font-display); font-size: 26px; font-weight: 800; color: white; }
.hero-stat-label { font-size: 11px; color: rgba(255,255,255,0.45); margin-top: 2px; }
.hero-divider { width: 1px; background: rgba(255,255,255,0.15); height: 36px; align-self: center; }
/* Hero visual side */
.hero-visual { position: relative; z-index: 1; display: flex; align-items: center; justify-content: center; }
.hero-mockup { width: 100%; max-width: 520px; filter: drop-shadow(0 24px 64px rgba(0,0,0,0.5)); border-radius: 16px; }
.hero-float-badge {
  position: absolute; display: flex; align-items: center; gap: 10px;
  background: white; border-radius: 14px; padding: 10px 16px;
  box-shadow: 0 8px 32px rgba(0,0,0,0.18); animation: floatBadge 4s ease-in-out infinite;
}
.hero-float-1 { bottom: 14%; left: -5%; animation-delay: 0s; }
.hero-float-2 { top: 12%; right: -2%; animation-delay: 2s; }
@keyframes floatBadge { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-8px)} }

/* LOGOS EXAMS */
.exams-strip {
  background: var(--gris-50); padding: 20px 40px; display: flex; align-items: center;
  justify-content: center; gap: 12px; flex-wrap: wrap; border-bottom: 1px solid var(--gris-200);
}
.exam-tag {
  padding: 8px 18px; border-radius: 50px; font-size: 13px; font-weight: 600;
  display: flex; align-items: center; gap: 6px;
}

/* POUR QUI — sections avec photos */
.who-section { padding: 100px 40px; background: white; }
.who-block {
  display: grid; grid-template-columns: 1fr 1fr; gap: 72px; align-items: center;
  margin-bottom: 100px;
}
.who-block:last-child { margin-bottom: 0; }
.who-block-reverse .who-img-wrap { order: -1; }
.who-img-wrap {
  border-radius: 20px; overflow: hidden;
  box-shadow: 0 20px 60px rgba(0,0,0,0.14);
}
.who-img { width: 100%; height: 380px; object-fit: cover; display: block; transition: transform 0.5s ease; }
.who-img-wrap:hover .who-img { transform: scale(1.03); }
.who-title {
  font-family: var(--font-display); font-size: clamp(22px, 3vw, 34px);
  font-weight: 800; color: var(--gris-900); line-height: 1.2; margin-bottom: 14px;
}
.who-desc { font-size: 16px; color: var(--gris-600); line-height: 1.75; margin-bottom: 20px; }
.who-list { list-style: none; margin-bottom: 28px; }
.who-list li { font-size: 14px; color: var(--gris-700); padding: 7px 0; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid var(--gris-100); }
.who-list li:last-child { border-bottom: none; }
.who-list .bi-check-circle-fill { color: var(--primary); font-size: 16px; flex-shrink: 0; }

/* FEATURES */
.features { padding: 100px 40px; background: white; }
.container { max-width: 1200px; margin: 0 auto; }
.section-label { font-size: 12px; font-weight: 700; color: var(--primary); text-transform: uppercase; letter-spacing: 2px; margin-bottom: 12px; }
.section-title { font-family: var(--font-display); font-size: clamp(28px, 4vw, 44px); font-weight: 800; color: var(--gris-900); margin-bottom: 16px; line-height: 1.15; }
.section-sub { font-size: 17px; color: var(--gris-600); max-width: 540px; line-height: 1.7; }
.features-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 24px; margin-top: 56px; }
.feature-card {
  background: var(--gris-50); border-radius: var(--radius-xl); padding: 32px;
  border: 1px solid var(--gris-200); transition: var(--transition);
}
.feature-card:hover { box-shadow: var(--shadow-lg); transform: translateY(-4px); border-color: var(--primary); }
.feature-icon { font-size: 36px; margin-bottom: 16px; }
.feature-title { font-family: var(--font-display); font-size: 18px; font-weight: 700; margin-bottom: 10px; }
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
.plan-icon { font-size: 32px; margin-bottom: 12px; }
.plan-name { font-family: var(--font-display); font-size: 20px; font-weight: 800; margin-bottom: 4px; }
.plan-price { font-family: var(--font-display); font-size: 28px; font-weight: 800; color: var(--gris-900); margin: 12px 0 4px; }
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
.cta-title { font-family: var(--font-display); font-size: clamp(32px,5vw,56px); font-weight: 900; color: white; margin-bottom: 16px; position: relative; }
.cta-sub { font-size: 18px; color: rgba(255,255,255,0.6); margin-bottom: 36px; position: relative; }

/* FOOTER */
.footer { background: #070c11; border-top: 1px solid rgba(255,255,255,.06); }
.footer-top { max-width: 1200px; margin: 0 auto; padding: 60px 48px 48px; display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 48px; }
.footer-brand-logo { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
.footer-logo { font-family: var(--font-display); font-size: 20px; font-weight: 800; color: #fff; letter-spacing: -.3px; }
.footer-logo em { color: var(--gold); font-style: normal; }
.footer-tagline { font-size: 13px; color: rgba(255,255,255,.35); line-height: 1.75; margin-bottom: 22px; max-width: 270px; }
.footer-payments { display: flex; gap: 7px; flex-wrap: wrap; }
.footer-pay { font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 6px; letter-spacing: .3px; }
.footer-pay-m { background: rgba(0,166,81,.12); color: #4ade80; border: 1px solid rgba(0,166,81,.18); }
.footer-pay-a { background: rgba(228,6,19,.10); color: #f87171; border: 1px solid rgba(228,6,19,.15); }
.footer-pay-o { background: rgba(255,102,0,.10); color: #fb923c; border: 1px solid rgba(255,102,0,.14); }
.footer-col-title { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.2px; color: rgba(255,255,255,.4); margin-bottom: 16px; }
.footer-col-link { display: block; font-size: 13px; color: rgba(255,255,255,.38); padding: 5px 0; transition: color .18s; line-height: 1.5; }
.footer-col-link:hover { color: rgba(255,255,255,.8); }
.footer-col-link-wa { color: #25D366 !important; display: flex; align-items: center; gap: 6px; }
.footer-col-link-wa:hover { color: #4ade80 !important; }
.footer-bottom { border-top: 1px solid rgba(255,255,255,.05); }
.footer-bottom-inner { max-width: 1200px; margin: 0 auto; padding: 18px 48px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.footer-copy { font-size: 12px; color: rgba(255,255,255,.2); }
.footer-legal { display: flex; gap: 18px; }
.footer-legal a { font-size: 12px; color: rgba(255,255,255,.2); transition: color .18s; }
.footer-legal a:hover { color: rgba(255,255,255,.5); }
@media (max-width: 960px) { .footer-top { grid-template-columns: 1fr 1fr; gap: 32px; padding: 40px 24px 32px; } .footer-bottom-inner { padding: 16px 24px; } }
@media (max-width: 600px) { .footer-top { grid-template-columns: 1fr; } }

@media (max-width: 1024px) {
  .pricing-grid { grid-template-columns: repeat(2,1fr); }
  .features-grid { grid-template-columns: repeat(2,1fr); }
  .testimonials-grid { grid-template-columns: 1fr 1fr; }
  .hero { grid-template-columns: 1fr; padding: 90px 40px 60px; }
  .hero-visual { display: none; }
  .who-block, .who-block-reverse { grid-template-columns: 1fr; gap: 40px; }
  .who-block-reverse .who-img-wrap { order: 0; }
  .who-img { height: 280px; }
}
@media (max-width: 768px) {
  .nav { padding: 0 20px; }
  .nav-links { display: none; }
  .hero { padding: 90px 20px 50px; }
  .features, .pricing, .testimonials, .cta-section, .who-section { padding: 60px 20px; }
  .pricing-grid, .features-grid, .testimonials-grid { grid-template-columns: 1fr; }
  .hero-stats { gap: 20px; }
  .who-block { margin-bottom: 60px; }
}

/* ── HERO PHOTO CAROUSEL ───────────────────────────────── */
.hero-carousel {
  position: relative; width: 100%; max-width: 540px;
  border-radius: 22px; overflow: hidden;
  box-shadow: 0 28px 72px rgba(0,0,0,0.55);
  aspect-ratio: 4/3;
}
.hero-carousel-slide {
  position: absolute; inset: 0;
  opacity: 0; transition: opacity 1s cubic-bezier(0.4,0,0.2,1);
}
.hero-carousel-slide.active { opacity: 1; }
.hero-carousel-slide img { width: 100%; height: 100%; object-fit: cover; display: block; }
.hero-carousel-slide::after {
  content: ''; position: absolute; inset: 0;
  background: linear-gradient(to top, rgba(0,0,0,0.5) 0%, rgba(0,0,0,0.1) 50%, transparent 100%);
}
.hero-carousel-label {
  position: absolute; top: 14px; left: 14px; z-index: 3;
  background: rgba(0,0,0,0.4); backdrop-filter: blur(10px);
  border: 1px solid rgba(255,255,255,0.2);
  color: white; font-size: 11px; font-weight: 600;
  padding: 4px 12px; border-radius: 50px; letter-spacing: .4px;
}
.hero-carousel-caption {
  position: absolute; bottom: 46px; left: 14px; right: 50px; z-index: 2;
  font-size: 11px; color: rgba(255,255,255,0.85); font-weight: 500;
  text-shadow: 0 1px 4px rgba(0,0,0,0.6);
}
.hero-carousel-dots {
  position: absolute; bottom: 14px; left: 14px; z-index: 3;
  display: flex; gap: 5px; align-items: center;
}
.hero-carousel-dot {
  width: 7px; height: 7px; border-radius: 50%;
  background: rgba(255,255,255,0.4); cursor: pointer; transition: all .35s;
  border: none; padding: 0;
}
.hero-carousel-dot.active { background: white; width: 24px; border-radius: 4px; }
.hero-carousel-counter {
  position: absolute; bottom: 10px; right: 14px; z-index: 3;
  font-size: 11px; color: rgba(255,255,255,0.55); font-weight: 600;
}

/* ── GALERIE PHOTOS (scroll infini) ─────────────────────── */
.gallery-section { padding: 72px 0; background: #08111a; overflow: hidden; }
.gallery-header { padding: 0 40px; text-align: center; margin-bottom: 36px; }
.gallery-row { overflow: hidden; margin-bottom: 14px; }
.gallery-row:last-child { margin-bottom: 0; }
.gallery-track {
  display: flex; gap: 14px;
  animation: galleryScroll 42s linear infinite;
  width: max-content;
}
.gallery-track.reverse { animation: galleryScrollReverse 38s linear infinite; }
.gallery-track:hover { animation-play-state: paused; }
.gallery-item {
  width: 290px; height: 200px; border-radius: 14px;
  overflow: hidden; flex-shrink: 0; cursor: pointer;
  position: relative;
}
.gallery-item img {
  width: 100%; height: 100%; object-fit: cover; display: block;
  transition: transform .5s ease;
}
.gallery-item:hover img { transform: scale(1.06); }
.gallery-item-overlay {
  position: absolute; inset: 0;
  background: linear-gradient(to top, rgba(0,0,0,0.4) 0%, transparent 60%);
  opacity: 0; transition: opacity .3s;
}
.gallery-item:hover .gallery-item-overlay { opacity: 1; }
@keyframes galleryScroll { from { transform: translateX(0); } to { transform: translateX(-50%); } }
@keyframes galleryScrollReverse { from { transform: translateX(-50%); } to { transform: translateX(0); } }

/* ── TICKER EN DIRECT ────────────────────────────────────── */
.ticker-wrap{display:flex;align-items:center;background:#0a1628;border-top:1px solid rgba(255,255,255,.06);border-bottom:1px solid rgba(255,255,255,.06);height:44px;overflow:hidden}
.ticker-live{display:flex;align-items:center;gap:7px;padding:0 18px;font-size:11px;font-weight:700;color:#4ade80;white-space:nowrap;flex-shrink:0;border-right:1px solid rgba(255,255,255,.08)}
.ticker-dot{width:7px;height:7px;background:#4ade80;border-radius:50%;animation:tdot 1.2s ease infinite}
@keyframes tdot{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.7)}}
.ticker-overflow{flex:1;overflow:hidden}
.ticker-track{display:flex;align-items:center;gap:0;animation:tickerScroll 38s linear infinite;width:max-content}
@keyframes tickerScroll{from{transform:translateX(0)}to{transform:translateX(-50%)}}
.ticker-track:hover{animation-play-state:paused}
.ticker-item{font-size:12.5px;color:rgba(255,255,255,.65);white-space:nowrap;padding:0 10px}
.ticker-sep{color:rgba(255,255,255,.15);font-size:13px;padding:0 2px;font-weight:300}
.ticker-dot-inline{display:inline-block;width:5px;height:5px;background:#4ade80;border-radius:50%;margin-right:5px;vertical-align:2px}

/* ── CARTE & RÉSULTATS ────────────────────────────────────── */
.result-card{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:20px;transition:all .2s}
.result-card:hover{background:rgba(255,255,255,.07);border-color:rgba(255,255,255,.15);transform:translateY(-3px)}
.result-header{display:flex;align-items:center;gap:12px;margin-bottom:16px}
.result-avatar{width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:800;flex-shrink:0}
.result-name{font-size:13px;font-weight:700;color:#fff}
.result-meta{font-size:11px;color:rgba(255,255,255,.4);margin-top:2px}
.result-gain{margin-left:auto;font-size:15px;font-weight:800;white-space:nowrap}
.result-bars{display:flex;flex-direction:column;gap:10px}
.result-bar-row{display:flex;align-items:center;gap:10px}
.result-bar-label{font-size:10px;color:rgba(255,255,255,.4);width:34px;flex-shrink:0}
.result-bar-track{flex:1;height:6px;background:rgba(255,255,255,.06);border-radius:10px;overflow:hidden}
.result-bar-fill{height:100%;border-radius:10px;width:0%;transition:width 1.2s cubic-bezier(.4,0,.2,1)}
.result-bar-val{font-size:11px;width:32px;text-align:right;flex-shrink:0}

/* Labels galerie */
.gallery-item-label{position:absolute;bottom:10px;left:10px;z-index:3;opacity:0;transition:opacity .3s;pointer-events:none}
.gallery-item:hover .gallery-item-label{opacity:1}

/* Responsive nouvelles sections */
@media(max-width:900px){
  section[style*="grid-template-columns:1fr 1fr"]{grid-template-columns:1fr!important;}
}

/* ── TÉMOIGNAGES ─────────────────────────────────────────── */
.testimonial-stars { color: var(--gold); font-size: 13px; margin-bottom: 14px; display: flex; gap: 2px; }
.testimonial-avatar-img {
  width: 44px; height: 44px; border-radius: 50%; object-fit: cover; flex-shrink: 0;
  border: 2px solid var(--primary);
}
</style>
</head>
<body>

<!-- NAVIGATION -->
<nav class="nav" id="mainNav">
  <a href="/reussiteplus/index.php" class="nav-logo-link">
    <img src="/reussiteplus/assets/img/logo-icon.svg" alt="RÉUSSITE+" width="30" height="30" style="display:block;border-radius:8px">
    <span class="nav-logo">RÉUSSITE<em>+</em></span>
  </a>

  <div class="nav-links">
    <a href="#fonctionnalites" class="nav-link">Fonctionnalités</a>
    <a href="#tarifs" class="nav-link">Tarifs</a>
    <a href="#temoignages" class="nav-link">Témoignages</a>
    <a href="/reussiteplus/archives.php" class="nav-link">Archives</a>
    <a href="/reussiteplus/contact.php" class="nav-link">Contact</a>
  </div>

  <div class="nav-actions">
    <?php if ($user): ?>
      <a href="/reussiteplus/dashboard.php" class="btn btn-primary">Tableau de bord
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
      </a>
    <?php else: ?>
      <a href="/reussiteplus/connexion.php" class="btn btn-outline">Connexion</a>
      <a href="/reussiteplus/inscription.php" class="btn btn-primary">Commencer gratuitement
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
      </a>
    <?php endif; ?>
  </div>

  <button class="nav-hamburger" id="navHamburger" aria-label="Menu" aria-expanded="false">
    <span></span><span></span><span></span>
  </button>
</nav>

<!-- Menu mobile -->
<div class="nav-mobile" id="navMobile">
  <a href="#fonctionnalites" class="nav-mobile-link" onclick="closeNav()">Fonctionnalités</a>
  <a href="#tarifs" class="nav-mobile-link" onclick="closeNav()">Tarifs</a>
  <a href="#temoignages" class="nav-mobile-link" onclick="closeNav()">Témoignages</a>
  <a href="/reussiteplus/archives.php" class="nav-mobile-link">Archives</a>
  <a href="/reussiteplus/contact.php" class="nav-mobile-link">Contact</a>
  <div class="nav-mobile-divider"></div>
  <?php if ($user): ?>
    <a href="/reussiteplus/dashboard.php" class="btn btn-primary" style="justify-content:center">Tableau de bord</a>
  <?php else: ?>
    <a href="/reussiteplus/connexion.php" class="btn btn-outline" style="justify-content:center;margin-bottom:8px">Connexion</a>
    <a href="/reussiteplus/inscription.php" class="btn btn-primary" style="justify-content:center">Commencer gratuitement</a>
  <?php endif; ?>
</div>

<!-- HERO -->
<div style="background:var(--noir)">
<section class="hero">
  <div class="hero-content">
    <div class="hero-badge"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg> 14 238 élèves en RDC s'y préparent déjà — Kinshasa · Goma · Lubumbashi · Bukavu</div>
    <h1 class="hero-title">
      Ce n'est pas<br>une question de talent.<br><span>C'est une question de méthode.</span>
    </h1>
    <p class="hero-sub">Les élèves qui réussissent l'Exam d'État ne travaillent pas plus. Ils s'entraînent avec les vrais sujets, comprennent leurs erreurs et savent exactement quoi réviser. RÉUSSITE+ leur donne ça — sur téléphone, même sans WiFi.</p>
    <div class="hero-cta">
      <a href="/reussiteplus/inscription.php" class="btn btn-primary btn-lg">Je commence — c'est gratuit →</a>
      <a href="/reussiteplus/tarifs.php" class="btn btn-gold btn-lg"><i class="bi bi-star-fill"></i> Découvrir Premium</a>
    </div>
    <div class="hero-stats">
      <div>
        <div class="hero-stat-num"><?= number_format($totalUsers, 0, ',', ' ') ?>+</div>
        <div class="hero-stat-label">Élèves inscrits</div>
      </div>
      <div class="hero-divider"></div>
      <div>
        <div class="hero-stat-num"><?= number_format($totalArchives, 0, ',', ' ') ?>+</div>
        <div class="hero-stat-label">Archives officielles</div>
      </div>
      <div class="hero-divider"></div>
      <div>
        <div class="hero-stat-num"><?= number_format($totalQuestions, 0, ',', ' ') ?>+</div>
        <div class="hero-stat-label">Questions en banque</div>
      </div>
      <div class="hero-divider"></div>
      <div>
        <div class="hero-stat-num"><?= number_format($totalExamens, 0, ',', ' ') ?>+</div>
        <div class="hero-stat-label">Examens passés</div>
      </div>
    </div>
  </div>
  <!-- Carousel de photos étudiants -->
  <div class="hero-visual">
    <div class="hero-carousel" id="heroCarousel">

      <div class="hero-carousel-slide active">
        <img src="https://images.unsplash.com/photo-1522529599102-193c0d76b5b6?w=800&auto=format&q=80&fit=crop&crop=center" alt="Élève congolais qui révise" loading="eager">
        <div class="hero-carousel-label"><i class="bi bi-geo-alt-fill"></i> Kinshasa · RDC</div>
        <div class="hero-carousel-caption">«&nbsp;74 % à l’Examen d’État après 4 mois de révision sur RÉUSSITE+&nbsp;»</div>
      </div>

      <div class="hero-carousel-slide">
        <img src="https://images.unsplash.com/photo-1580582932707-520aed937b7b?w=800&auto=format&q=80&fit=crop&crop=center" alt="Salle de classe en RDC" loading="lazy">
        <div class="hero-carousel-label"><i class="bi bi-mortarboard"></i> Examen d’État — 6ème Sec.</div>
        <div class="hero-carousel-caption">Prépare-toi dans les vraies conditions d’examen EPST</div>
      </div>

      <div class="hero-carousel-slide">
        <img src="https://images.unsplash.com/photo-1531123897727-8f129e1688ce?w=800&auto=format&q=80&fit=crop&crop=center" alt="Étudiante congolaise" loading="lazy">
        <div class="hero-carousel-label"><i class="bi bi-star-fill"></i> TENASOSP — 1er essai</div>
        <div class="hero-carousel-caption">«&nbsp;Tout classé, corrigé, détaillé. Réussi du premier coup.&nbsp;»</div>
      </div>

      <div class="hero-carousel-slide">
        <img src="https://images.unsplash.com/photo-1509062522246-3755977927d7?w=800&auto=format&q=80&fit=crop&crop=center" alt="Enseignant avec ses élèves" loading="lazy">
        <div class="hero-carousel-label"><i class="bi bi-pencil-square"></i> QCM officiels</div>
        <div class="hero-carousel-caption">Questions tirées des vrais sujets ENAFEP, TENASOSP, Exam d’État</div>
      </div>

      <div class="hero-carousel-slide">
        <img src="https://images.unsplash.com/photo-1507152927626-13d6a1ee8614?w=800&auto=format&q=80&fit=crop&crop=center" alt="Répétiteur de Goma" loading="lazy">
        <div class="hero-carousel-label"><i class="bi bi-phone"></i> Goma · 11/13 reçus</div>
        <div class="hero-carousel-caption">«&nbsp;Mes élèves passent les QCM depuis leurs téléphones, même sans WiFi.&nbsp;»</div>
      </div>

      <!-- Dots navigation -->
      <div class="hero-carousel-dots" id="heroDots">
        <button class="hero-carousel-dot active" onclick="goSlide(0)"></button>
        <button class="hero-carousel-dot" onclick="goSlide(1)"></button>
        <button class="hero-carousel-dot" onclick="goSlide(2)"></button>
        <button class="hero-carousel-dot" onclick="goSlide(3)"></button>
        <button class="hero-carousel-dot" onclick="goSlide(4)"></button>
      </div>
      <div class="hero-carousel-counter" id="heroCounter">1 / 5</div>
    </div>

    <!-- Badges flottants -->
    <div class="hero-float-badge hero-float-1">
      <i class="bi bi-trophy-fill" style="color:var(--gold);font-size:18px"></i>
      <div>
        <div style="font-size:12px;font-weight:700;color:var(--gris-900)">+24 pts ce mois</div>
        <div style="font-size:10px;color:var(--gris-500)">Meilleure série <i class="bi bi-fire" style="color:var(--gold)"></i> 7j</div>
      </div>
    </div>
    <div class="hero-float-badge hero-float-2">
      <i class="bi bi-patch-check-fill" style="color:var(--primary);font-size:18px"></i>
      <div>
        <div style="font-size:12px;font-weight:700;color:var(--gris-900)">Examen d’État</div>
        <div style="font-size:10px;color:var(--gris-500)">Score : <strong style="color:var(--primary)">87%</strong></div>
      </div>
    </div>
  </div>
</section>
</div><!-- /hero-wrapper -->

<!-- EXAM TYPES -->
<div class="exams-strip">
  <span style="font-size:12px;color:var(--gris-500);font-weight:600;margin-right:6px">Certifications préparées&nbsp;:</span>
  <span class="exam-tag" style="background:#E8F5F1;color:#005A45">✦ ENAFEP &mdash; 6ème Primaire</span>
  <span class="exam-tag" style="background:#EEF4FD;color:#1E5FAD">✦ TENASOSP &mdash; 3ème Secondaire</span>
  <span class="exam-tag" style="background:#F5E6C0;color:#8C6A1A">✦ Examen d'État &mdash; 6ème Secondaire</span>
  <span class="exam-tag" style="background:#FEF0EF;color:#C9342A">✦ Tests Diocésains</span>
  <span class="exam-tag" style="background:#F3F4F6;color:#374151">✦ Entraînement libre</span>
</div>

<!-- ═══ TICKER EN DIRECT ════════════════════════════════════════ -->
<div class="ticker-wrap">
  <div class="ticker-live"><span class="ticker-dot"></span> En direct</div>
  <div class="ticker-overflow">
    <div class="ticker-track" id="tickerTrack">
      <span class="ticker-item"><span class="ticker-dot-inline"></span> <strong>Amisi K.</strong> · Kinshasa — examen blanc terminé avec <strong style="color:#4ade80">87 %</strong> en Mathématiques</span>
      <span class="ticker-sep">—</span>
      <span class="ticker-item"><span class="ticker-dot-inline"></span> <strong>Grâce M.</strong> · Lubumbashi — TENASOSP validé au <strong style="color:#4ade80">1er essai</strong></span>
      <span class="ticker-sep">—</span>
      <span class="ticker-item"><span class="ticker-dot-inline"></span> <strong>Patient N.</strong> · Goma — score passé de 48 % à <strong style="color:#4ade80">79 %</strong> en 3 semaines</span>
      <span class="ticker-sep">—</span>
      <span class="ticker-item"><span class="ticker-dot-inline"></span> <strong>Déborah K.</strong> · Mbuji-Mayi — 94 % en Chimie, meilleur score de sa classe</span>
      <span class="ticker-sep">—</span>
      <span class="ticker-item"><span class="ticker-dot-inline"></span> <strong>Éloi M.</strong> · Bukavu — 12 corrigés ENAFEP téléchargés ce matin</span>
      <span class="ticker-sep">—</span>
      <span class="ticker-item"><span class="ticker-dot-inline"></span> <strong>Christelle B.</strong> · Kinshasa — plan de révision IA sur 7 jours généré pour l'Exam d'État</span>
      <span class="ticker-sep">—</span>
      <span class="ticker-item"><span class="ticker-dot-inline"></span> <strong>Joseph K.</strong> · Kisangani — 14 jours consécutifs de révision</span>
      <span class="ticker-sep">—</span>
      <span class="ticker-item"><span class="ticker-dot-inline"></span> <strong>Merveille T.</strong> · Kananga — vient de rejoindre <strong>14 238 élèves</strong> sur RÉUSSITE+</span>
      <span class="ticker-sep">—</span>
    </div>
  </div>
</div>

<!-- POUR QUI — Section avec photos (style Schoolap) -->
<section class="who-section" id="pour-qui">
  <div class="container">
    <div style="text-align:center;margin-bottom:12px">
      <div class="section-label" style="display:inline-block">Pour qui ?</div>
    </div>
    <h2 class="section-title" style="text-align:center">Quelle que soit ta situation,<br>RÉUSSITE+ s'adapte à toi</h2>

    <!-- Bloc 1 : Élèves -->
    <div class="who-block">
      <div class="who-img-wrap">
        <img src="https://images.unsplash.com/photo-1522529599102-193c0d76b5b6?w=900&auto=format&q=82&fit=crop&crop=top" alt="Élève congolais révisant" class="who-img" loading="lazy">
      </div>
      <div class="who-text">
        <div class="section-label" style="margin-bottom:12px">Élèves — 6ème primaire, 3ème & 6ème secondaire</div>
        <h3 class="who-title">Tu as les cours.<br>Maintenant, entraîne-toi vraiment.</h3>
        <p class="who-desc">Avoir les livres ne suffit pas — tout le monde les a. Ce qui fait la différence, c'est de s'entraîner avec les vrais sujets, de comprendre ses erreurs et de savoir exactement ce qui reste à réviser avant le jour J. C'est précisément ce que font les 14 238 élèves qui utilisent RÉUSSITE+.</p>
        <ul class="who-list">
          <li><i class="bi bi-check-circle-fill"></i> Sujets officiels ENAFEP, TENASOSP & Exam d'État depuis 2005</li>
          <li><i class="bi bi-check-circle-fill"></i> Chaque erreur expliquée question par question</li>
          <li><i class="bi bi-check-circle-fill"></i> Ton score par matière mis à jour après chaque session</li>
        </ul>
        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
          <a href="/reussiteplus/inscription.php" class="btn btn-primary">Je commence gratuitement →</a>
          <span style="font-size:13px;color:var(--gris-500)">Aucune carte bancaire requise</span>
        </div>
      </div>
    </div>

    <!-- Bloc 2 : Enseignants / Répétiteurs -->
    <div class="who-block who-block-reverse">
      <div class="who-img-wrap">
        <img src="https://images.unsplash.com/photo-1509062522246-3755977927d7?w=900&auto=format&q=82&fit=crop&crop=center" alt="Enseignant congolais avec ses élèves" class="who-img" loading="lazy">
      </div>
      <div class="who-text">
        <div class="section-label" style="margin-bottom:12px">Enseignants & Répétiteurs</div>
        <h3 class="who-title">Donnez à vos élèves les mêmes chances qu'un répétiteur privé.</h3>
        <p class="who-desc">Vous savez ce qui tombe aux examens. Maintenant vous avez l'outil pour le faire réviser question par question, en dehors de la classe — et repérer en un coup d'œil qui progresse et qui décroche avant qu'il soit trop tard.</p>
        <ul class="who-list">
          <li><i class="bi bi-check-circle-fill"></i> Archives officielles classées par province, matière et année</li>
          <li><i class="bi bi-check-circle-fill"></i> 15 000+ questions QCM prêtes à exploiter en cours ou à la maison</li>
          <li><i class="bi bi-check-circle-fill"></i> Résultats collectifs visibles — repérez les lacunes à temps</li>
        </ul>
        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
          <a href="/reussiteplus/inscription.php" class="btn btn-primary">Créer un compte →</a>
          <span style="font-size:13px;color:var(--gris-500)">Plan École disponible pour les classes entières</span>
        </div>
      </div>
    </div>

    <!-- Bloc 3 : Parents -->
    <div class="who-block">
      <div class="who-img-wrap">
        <img src="https://images.unsplash.com/photo-1531123897727-8f129e1688ce?w=900&auto=format&q=82&fit=crop&crop=top" alt="Étudiante congolaise qui étudie" class="who-img" loading="lazy">
      </div>
      <div class="who-text">
        <div class="section-label" style="margin-bottom:12px">Parents & Familles</div>
        <h3 class="who-title">Votre enfant mérite<br>mieux qu'une photocopie froissée.</h3>
        <p class="who-desc">En RDC, l'accès aux bons outils de révision coûte cher et n'est pas toujours garanti. RÉUSSITE+ change ça. Votre enfant peut réviser à n'importe quelle heure, avec les vrais sujets officiels — depuis son téléphone, même sans courant stable. Et vous pouvez voir s'il avance.</p>
        <ul class="who-list">
          <li><i class="bi bi-check-circle-fill"></i> Inscription gratuite en moins de 2 minutes — pas d'ordinateur nécessaire</li>
          <li><i class="bi bi-check-circle-fill"></i> Fonctionne hors connexion, sur les téléphones d'entrée de gamme</li>
          <li><i class="bi bi-check-circle-fill"></i> Tableau de progression visible chaque semaine</li>
        </ul>
        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
          <a href="/reussiteplus/inscription.php" class="btn btn-primary">Inscrire mon enfant →</a>
          <span style="font-size:13px;color:var(--gris-500)">Gratuit pour commencer, Premium à 10 000 CDF/mois</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- FONCTIONNALITÉS -->
<section class="features" id="fonctionnalites">
  <div class="container">
    <div style="text-align:center;margin-bottom:12px">
      <div class="section-label" style="display:inline-block">Ce qui change tout</div>
    </div>
    <h2 class="section-title" style="text-align:center">Arrête de réviser<br>au hasard.</h2>
    <p class="section-sub" style="text-align:center;margin:0 auto">RÉUSSITE+ n'est pas une appli générique traduite en français. Elle a été construite pour un seul système — le tien. Pour l'EPST, les programmes officiels de la RDC, et les réalités du terrain.</p>

    <div class="features-grid">
      <div class="feature-card">
        <div class="feature-icon"><i class="bi bi-folder2-open"></i></div>
        <div class="feature-title">Tout ce qui est tombé depuis 2005 — en un endroit</div>
        <div class="feature-desc">ENAFEP, TENASOSP, Exam d'État, Tests diocésains. Chaque sujet corrigé, annoté, classé par matière et par province. Ce n'est pas une sélection — c'est l'intégralité.</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon"><i class="bi bi-pencil-square"></i></div>
        <div class="feature-title">L'examen blanc qui te fait peur… moins</div>
        <div class="feature-desc">Minuteur réaliste, QCM en conditions d'examen, score immédiat. Recommence autant de fois que tu veux. La confiance le jour J ne vient pas du talent — elle vient de l'entraînement.</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon"><i class="bi bi-lightbulb"></i></div>
        <div class="feature-title">Tu sais exactement pourquoi tu t'es trompé</div>
        <div class="feature-desc">Pas juste la bonne réponse — l'explication complète, étape par étape. 15 000+ questions classées par chapitre et par niveau. Chaque erreur devient une leçon concrète.</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon"><i class="bi bi-graph-up-arrow"></i></div>
        <div class="feature-title">Ta progression, en chiffres, semaine après semaine</div>
        <div class="feature-desc">Score par matière, jours consécutifs, évolution sur 30 jours. Fini de réviser dans le vide sans savoir si ça avance. Les données ne mentent pas — et elles motivent.</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon"><i class="bi bi-calendar-check"></i></div>
        <div class="feature-title">Ton coach IA — un plan personnalisé, chaque semaine</div>
        <div class="feature-desc">Tu entres la date de ton examen et tes matières les plus faibles. L'IA génère ce qu'il faut réviser chaque jour jusqu'au jour J. Pas d'improvisation, pas de panique — juste une feuille de route.</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon"><i class="bi bi-wifi-off"></i></div>
        <div class="feature-title">Le réseau coupe ? Tu continues quand même.</div>
        <div class="feature-desc">En RDC, on ne choisit pas quand le réseau lâche. Télécharge tes archives et QCM une fois — révise en bus, en coupure de courant, partout. Sans rien perdre.</div>
      </div>
    </div>
  </div>
</section>

<!-- GALERIE PHOTOS — Scroll infini ————————————————————————————— -->
<section class="gallery-section">
  <div class="gallery-header">
    <div class="section-label" style="color:var(--primary-light)">La communauté RÉUSSITE+</div>
    <h2 style="font-family:var(--font-display);font-size:clamp(24px,3vw,38px);font-weight:800;color:white;margin-top:8px;line-height:1.2">
      14 238 élèves qui ne laissent<br>pas le destin décider.
    </h2>
    <p style="font-size:14px;color:rgba(255,255,255,0.4);margin-top:8px">Kinshasa &bull; Lubumbashi &bull; Goma &bull; Mbuji-Mayi &bull; Bukavu &bull; Kisangani &bull; et 20 autres provinces</p>
  </div>

  <?php
  /* Photos galerie — élèves et contextes africains */
  $galleryRow1 = [
    ['1522529599102-193c0d76b5b6', 'Kalombo · Kinshasa', 'Exam d\'État 74%'],
    ['1531123897727-8f129e1688ce', 'Bénédicte · Lubumbashi', 'TENASOSP réussi'],
    ['1580582932707-520aed937b7b', 'Classe · Goma', 'Révision collective'],
    ['1507152927626-13d6a1ee8614', 'Dieumerci · Goma', 'Répétiteur 11/13'],
    ['1509062522246-3755977927d7', 'Institut · Bukavu', 'ENAFEP 6ème primaire'],
    ['1434030216411-0b5816edd9fb', 'Examen blanc', 'QCM officiels'],
  ];
  $galleryRow2 = [
    ['1503676260728-1c00da094a0b', 'Bibliothèque · Kin', 'Archives 2008-2024'],
    ['1588072432836-e10032774350', 'Mobile-first', 'Révision sur téléphone'],
    ['1523050854058-8df90110c9f1', 'Remise des diplômes', 'Promotion 2025'],
    ['1571260899304-425eee4c7efc', 'Campus · Mbuji-Mayi', 'Étudiants RDC'],
    ['1531123897727-8f129e1688ce', 'Préparation intensive', 'Plan de révision IA'],
    ['1522529599102-193c0d76b5b6', 'Kinshasa · RDC', 'Score +27 pts en 4 sem.'],
  ];
  ?>

  <!-- Rangée 1 — gauche → droite -->
  <div class="gallery-row">
    <div class="gallery-track">
      <?php foreach (array_merge($galleryRow1, $galleryRow1) as [$id, $name, $caption]): ?>
      <div class="gallery-item">
        <img src="https://images.unsplash.com/photo-<?= $id ?>?w=580&auto=format&q=72&fit=crop&crop=center" alt="<?= htmlspecialchars($name) ?>" loading="lazy">
        <div class="gallery-item-overlay"></div>
        <div class="gallery-item-label">
          <div style="font-size:11px;font-weight:700;color:#fff"><?= htmlspecialchars($name) ?></div>
          <div style="font-size:10px;color:rgba(255,255,255,.6)"><?= htmlspecialchars($caption) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Rangée 2 — droite → gauche -->
  <div class="gallery-row">
    <div class="gallery-track reverse">
      <?php foreach (array_merge($galleryRow2, $galleryRow2) as [$id, $name, $caption]): ?>
      <div class="gallery-item">
        <img src="https://images.unsplash.com/photo-<?= $id ?>?w=580&auto=format&q=72&fit=crop&crop=center" alt="<?= htmlspecialchars($name) ?>" loading="lazy">
        <div class="gallery-item-overlay"></div>
        <div class="gallery-item-label">
          <div style="font-size:11px;font-weight:700;color:#fff"><?= htmlspecialchars($name) ?></div>
          <div style="font-size:10px;color:rgba(255,255,255,.6)"><?= htmlspecialchars($caption) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- TARIFS -->
<section class="pricing" id="tarifs">
  <div class="container">
    <div style="text-align:center;margin-bottom:12px">
      <div class="section-label" style="display:inline-block">Nos offres</div>
    </div>
    <h2 class="section-title" style="text-align:center">Commence gratuitement,<br>passe au niveau supérieur si tu veux</h2>
    <p class="section-sub" style="text-align:center;margin:0 auto">Paiement en CDF — M-Pesa, Airtel Money ou Orange Money.</p>

    <div class="pricing-grid" style="margin-top:56px">
      <?php foreach (PLANS as $planKey => $plan): ?>
      <div class="plan-card <?= ($plan['populaire'] ?? false) ? 'popular' : '' ?>">
        <?php if ($plan['populaire'] ?? false): ?>
          <div class="plan-popular-badge"><i class="bi bi-star-fill"></i> Le plus populaire</div>
        <?php endif; ?>
        <div class="plan-icon"><?= $plan['icone'] ?></div>
        <div class="plan-name"><?= e($plan['nom']) ?></div>
        <div class="plan-price"><?= $plan['prix'] === 0 ? 'Gratuit' : number_format($plan['prix'], 0, ',', ' ') . ' CDF' ?></div>
        <div class="plan-price-sub"><?= $plan['prix'] === 0 ? 'Pour toujours' : 'par mois' ?></div>
        <ul class="plan-features">
          <li>
            <?= $plan['examens_mois'] === -1 ? '<span class="check"><i class="bi bi-check-lg"></i></span> Examens illimités' : '<span class="check"><i class="bi bi-check-lg"></i></span> ' . $plan['examens_mois'] . ' examens/mois' ?>
          </li>
          <li>
            <?= $plan['archives'] ? '<span class="check"><i class="bi bi-check-lg"></i></span> Archives officielles' : '<span class="cross"><i class="bi bi-x-lg"></i></span> Archives officielles' ?>
          </li>
          <li>
            <?= $plan['corrige'] ? '<span class="check"><i class="bi bi-check-lg"></i></span> Corrigés détaillés' : '<span class="cross"><i class="bi bi-x-lg"></i></span> Corrigés détaillés' ?>
          </li>
          <li>
            <?= $plan['ia'] ? '<span class="check"><i class="bi bi-check-lg"></i></span> Plan de révision IA' : '<span class="cross"><i class="bi bi-x-lg"></i></span> Plan de révision IA' ?>
          </li>
          <li><span class="check"><i class="bi bi-check-lg"></i></span> Suivi de progression</li>
          <?php if (isset($plan['eleves_max'])): ?>
          <li><span class="check"><i class="bi bi-check-lg"></i></span> Jusqu'à <?= $plan['eleves_max'] ?> élèves</li>
          <?php endif; ?>
        </ul>
        <?php if ($planKey === 'GRATUIT'): ?>
          <a href="/reussiteplus/inscription.php" class="btn" style="width:100%;justify-content:center;background:var(--gris-100);color:var(--gris-700)">Commencer gratuitement</a>
        <?php elseif ($planKey === 'ECOLE'): ?>
          <a href="mailto:contact@reussiteplus.cd?subject=Abonnement École" class="btn btn-primary" style="width:100%;justify-content:center">Nous contacter</a>
        <?php else: ?>
          <a href="/reussiteplus/paiement.php?plan=<?= $planKey ?>" class="btn <?= ($plan['populaire'] ?? false) ? 'btn-gold' : 'btn-primary' ?>" style="width:100%;justify-content:center">
            <?= ($plan['populaire'] ?? false) ? '<i class="bi bi-star-fill"></i> ' : '' ?>Choisir ce plan
          </a>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- TÉMOIGNAGES -->
<section class="testimonials" id="temoignages">
  <div class="container">
    <div style="text-align:center;margin-bottom:12px">
      <div class="section-label" style="display:inline-block">Témoignages</div>
    </div>
    <h2 class="section-title" style="text-align:center">Ils ne font pas que<br>nous faire confiance — <em style="font-style:normal;color:var(--primary)">ils réussissent.</em></h2>
    <div class="testimonials-grid">

      <div class="testimonial-card">
        <div class="testimonial-stars" style="color:#F59E0B;font-size:15px;margin-bottom:12px">★★★★★</div>
        <p class="testimonial-text">"J'avais raté l'Exam d'État en 2023. Je ne savais pas par où recommencer. J'ai trouvé RÉUSSITE+ et j'ai commencé les QCM chaque soir depuis mon Tecno — 20 minutes, pas plus. 4 mois plus tard&nbsp;: 74&nbsp;%. Le résultat que je n'avais pas eu la première fois."</p>
        <div class="testimonial-author">
          <img src="https://images.unsplash.com/photo-1522529599102-193c0d76b5b6?w=96&h=96&fit=crop&crop=center&auto=format&q=80" alt="Kalombo Mutombo" width="48" height="48" style="border-radius:50%;object-fit:cover;flex-shrink:0">
          <div>
            <div class="testimonial-name">Kalombo Mutombo</div>
            <div class="testimonial-school">Lycée Roi Baudouin, Kinshasa &middot; Exam. d'État 2024 &middot; <strong style="color:var(--primary)">74 %</strong></div>
          </div>
        </div>
      </div>

      <div class="testimonial-card">
        <div class="testimonial-stars" style="color:#F59E0B;font-size:15px;margin-bottom:12px">★★★★★</div>
        <p class="testimonial-text">"Avant, je cherchais les anciens sujets dans des photocopies mal lisibles que je trouvais au marché. Là, tout est classé, net, corrigé, avec le détail de chaque étape. J'ai eu mon TENASOSP du premier coup. Je ne comprends pas pourquoi tout le monde ne l'utilise pas encore."</p>
        <div class="testimonial-author">
          <img src="https://images.unsplash.com/photo-1531123897727-8f129e1688ce?w=96&h=96&fit=crop&crop=center&auto=format&q=80" alt="Bénédicte Nzuzi" width="48" height="48" style="border-radius:50%;object-fit:cover;flex-shrink:0">
          <div>
            <div class="testimonial-name">Bénédicte Nzuzi</div>
            <div class="testimonial-school">Institut Kyondo, Lubumbashi &middot; TENASOSP 2025 &middot; <strong style="color:var(--primary)">1er essai</strong></div>
          </div>
        </div>
      </div>

      <div class="testimonial-card">
        <div class="testimonial-stars" style="color:#F59E0B;font-size:15px;margin-bottom:12px">★★★★★</div>
        <p class="testimonial-text">"Je répète à Goma depuis 5 ans. Cette année j'ai intégré RÉUSSITE+ dans mes séances du soir. Les élèves s'entraînent depuis leurs téléphones — sans WiFi, sans ordinateur. Résultat&nbsp;: 11 reçus sur 13. C'est mon meilleur taux depuis que j'enseigne."</p>
        <div class="testimonial-author">
          <img src="https://images.unsplash.com/photo-1507152927626-13d6a1ee8614?w=96&h=96&fit=crop&crop=center&auto=format&q=80" alt="Dieumerci Bauma" width="48" height="48" style="border-radius:50%;object-fit:cover;flex-shrink:0">
          <div>
            <div class="testimonial-name">Dieumerci Bauma</div>
            <div class="testimonial-school">Répétiteur, Goma &middot; 5 ans &middot; <strong style="color:var(--primary)">11/13 reçus en 2025</strong></div>
          </div>
        </div>
      </div>

    </div>

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
<!-- ═══ AVANT / APRÈS — SCORES RÉELS ════════════════════════════ -->
<section style="padding:80px 40px;background:#08111a;overflow:hidden;position:relative">
  <div style="position:absolute;inset:0;background:radial-gradient(ellipse 60% 50% at 50% 50%,rgba(0,122,94,.08) 0%,transparent 70%);pointer-events:none"></div>
  <div class="container" style="position:relative">
    <div style="text-align:center;margin-bottom:48px">
      <div class="section-label" style="color:var(--primary-light);display:inline-block">Résultats réels</div>
      <h2 style="font-family:var(--font-display);font-size:clamp(26px,3.5vw,42px);font-weight:900;color:#fff;margin-top:10px;line-height:1.15">
        Ce qui change après 30 jours<br>avec RÉUSSITE+
      </h2>
      <p style="font-size:15px;color:rgba(255,255,255,.45);margin-top:10px">Progressions mesurées entre le premier et le dernier examen blanc</p>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px">
      <?php
      $results = [
        ['Amisi K.','Kinshasa','Mathématiques',42,87,'#60A5FA'],
        ['Grâce M.','Lubumbashi','Biologie',51,88,'#4ade80'],
        ['Patient N.','Goma','Physique',39,76,'#FBBF24'],
        ['Déborah K.','Mbuji-Mayi','Chimie',55,94,'#A78BFA'],
        ['Christelle B.','Kinshasa','Français',48,82,'#F87171'],
        ['Éloi M.','Bukavu','Histoire-Géo',44,79,'#34D399'],
      ];
      foreach ($results as [$nom, $ville, $matiere, $avant, $apres, $color]):
        $gain = $apres - $avant;
      ?>
      <div class="result-card">
        <div class="result-header">
          <div class="result-avatar" style="background:<?= $color ?>22;color:<?= $color ?>"><?= substr($nom,0,1) ?></div>
          <div>
            <div class="result-name"><?= $nom ?></div>
            <div class="result-meta"><?= $ville ?> · <?= $matiere ?></div>
          </div>
          <div class="result-gain" style="color:<?= $color ?>">+<?= $gain ?> pts</div>
        </div>
        <div class="result-bars">
          <div class="result-bar-row">
            <span class="result-bar-label">Avant</span>
            <div class="result-bar-track"><div class="result-bar-fill" style="width:<?= $avant ?>%;background:rgba(255,255,255,.1)" data-target="<?= $avant ?>"></div></div>
            <span class="result-bar-val" style="color:rgba(255,255,255,.4)"><?= $avant ?>%</span>
          </div>
          <div class="result-bar-row">
            <span class="result-bar-label">Après</span>
            <div class="result-bar-track"><div class="result-bar-fill" style="width:0%;background:<?= $color ?>" data-target="<?= $apres ?>"></div></div>
            <span class="result-bar-val" style="color:<?= $color ?>;font-weight:700"><?= $apres ?>%</span>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══ CARTE 26 PROVINCES RDC ═══════════════════════════════════ -->
<section style="padding:80px 40px;background:var(--gris-50)">
  <div class="container">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:60px;align-items:center">
      <div>
        <div class="section-label" style="display:inline-block">Couverture nationale</div>
        <h2 style="font-family:var(--font-display);font-size:clamp(26px,3.5vw,40px);font-weight:900;color:var(--gris-900);margin:12px 0 16px;line-height:1.15">
          RÉUSSITE+ dans<br>les <span style="color:var(--primary)">26 provinces</span><br>de la RDC
        </h2>
        <p style="font-size:15px;color:var(--gris-600);line-height:1.75;margin-bottom:28px">Des élèves de Kinshasa à Mbuji-Mayi, de Goma à Lubumbashi — partout où il y a un téléphone, RÉUSSITE+ est là.</p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px 16px">
          <?php
          $provinces = ['Kinshasa','Kongo Central','Kwango','Kwilu','Mai-Ndombe','Kasaï','Kasaï Central','Kasaï Oriental','Lomami','Sankuru','Maniema','Sud-Kivu','Nord-Kivu','Ituri','Haut-Uélé','Tshopo','Bas-Uélé','Nord-Ubangi','Mongala','Sud-Ubangi','Équateur','Tshuapa','Tanganyika','Haut-Lomami','Lualaba','Haut-Katanga'];
          foreach ($provinces as $p):
          ?>
          <div style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--gris-700);padding:4px 0">
            <span style="width:6px;height:6px;background:var(--primary);border-radius:50%;flex-shrink:0"></span>
            <?= $p ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div style="position:relative">
        <!-- SVG simplifié contour RDC avec effet glow -->
        <svg viewBox="0 0 400 400" xmlns="http://www.w3.org/2000/svg" style="width:100%;max-width:420px;filter:drop-shadow(0 0 32px rgba(0,122,94,.3))">
          <defs>
            <radialGradient id="rdcGlow" cx="50%" cy="50%" r="50%">
              <stop offset="0%" stop-color="rgba(0,122,94,.15)"/>
              <stop offset="100%" stop-color="rgba(0,122,94,0)"/>
            </radialGradient>
          </defs>
          <ellipse cx="200" cy="200" rx="190" ry="185" fill="url(#rdcGlow)"/>
          <!-- Contour simplifié RDC -->
          <path d="M 95 80 L 120 60 L 155 55 L 195 50 L 235 52 L 270 62 L 295 80 L 310 100 L 320 125 L 325 155 L 318 185 L 305 210 L 310 235 L 300 260 L 280 280 L 255 295 L 225 305 L 195 310 L 165 305 L 135 292 L 110 275 L 90 255 L 75 230 L 68 200 L 70 170 L 78 145 L 88 118 Z"
            fill="rgba(0,122,94,.1)" stroke="#007A5E" stroke-width="2.5" stroke-linejoin="round"/>
          <!-- Points villes -->
          <?php
          $villes = [
            ['Kinshasa', 88, 220, '#FBBF24'],
            ['Lubumbashi', 255, 295, '#60A5FA'],
            ['Mbuji-Mayi', 210, 235, '#4ade80'],
            ['Goma', 290, 155, '#F87171'],
            ['Kisangani', 230, 130, '#A78BFA'],
            ['Bukavu', 285, 185, '#34D399'],
          ];
          foreach ($villes as [$name, $x, $y, $color]):
          ?>
          <circle cx="<?= $x ?>" cy="<?= $y ?>" r="6" fill="<?= $color ?>" opacity=".9"/>
          <circle cx="<?= $x ?>" cy="<?= $y ?>" r="12" fill="<?= $color ?>" opacity=".2"/>
          <text x="<?= $x + 10 ?>" y="<?= $y + 4 ?>" font-size="10" fill="rgba(255,255,255,.7)" font-family="Inter,sans-serif" font-weight="600"><?= $name ?></text>
          <?php endforeach; ?>
          <!-- Label centré -->
          <text x="200" y="200" text-anchor="middle" font-size="13" fill="rgba(0,122,94,.6)" font-family="Poppins,sans-serif" font-weight="800">RÉUSSITE+</text>
        </svg>
        <!-- Compteur flottant -->
        <div style="position:absolute;top:10%;right:-10px;background:#fff;border-radius:14px;padding:12px 16px;box-shadow:0 8px 32px rgba(0,0,0,.12);text-align:center;min-width:100px">
          <div style="font-family:var(--font-display);font-size:22px;font-weight:900;color:var(--primary)" class="count-up" data-target="14238">0</div>
          <div style="font-size:10px;color:var(--gris-500);margin-top:2px">élèves inscrits</div>
        </div>
        <div style="position:absolute;bottom:15%;left:-10px;background:#fff;border-radius:14px;padding:12px 16px;box-shadow:0 8px 32px rgba(0,0,0,.12);text-align:center;min-width:100px">
          <div style="font-family:var(--font-display);font-size:22px;font-weight:900;color:var(--gold)" class="count-up" data-target="26">0</div>
          <div style="font-size:10px;color:var(--gris-500);margin-top:2px">provinces couvertes</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- CTA FINAL -->
<section class="cta-section">
  <div style="position:relative">
    <h2 class="cta-title">Cette année, tu ne rates pas.<br>Tu arrives <span style="color:var(--gold)">préparé.</span></h2>
    <p class="cta-sub" style="max-width:600px;margin:0 auto 36px">14 238 élèves de toutes les provinces ont commencé ici, gratuitement, sans rien promettre. Certains depuis leur chambre à Kinshasa, d'autres sous la véranda à Goma, d'autres encore dans le bus vers Lubumbashi.<br><strong style="color:rgba(255,255,255,.8)">Aujourd'hui, c'est ton tour.</strong></p>
    <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap;margin-bottom:20px">
      <a href="/reussiteplus/inscription.php" class="btn btn-primary btn-lg" style="font-size:17px;padding:16px 36px">Créer mon compte — c'est gratuit →</a>
      <a href="/reussiteplus/tarifs.php" class="btn btn-gold btn-lg"><i class="bi bi-star-fill"></i> Voir ce que donne Premium</a>
    </div>
    <p style="font-size:13px;color:rgba(255,255,255,.3);position:relative">Aucune carte bancaire. Aucun engagement. Tu peux arrêter quand tu veux.</p>
  </div>
</section>

<!-- FOOTER -->
<footer class="footer">

  <!-- Colonnes -->
  <div class="footer-top">

    <!-- Marque -->
    <div class="footer-brand">
      <div class="footer-brand-logo">
        <img src="/reussiteplus/assets/img/logo-icon.svg" alt="RÉUSSITE+" width="36" height="36" style="display:block;border-radius:10px">
        <span class="footer-logo">RÉUSSITE<em>+</em></span>
      </div>
      <p class="footer-tagline">La plateforme de préparation aux examens officiels de la République Démocratique du Congo. Construite pour les élèves congolais — pas adaptée, construite.</p>
      <div class="footer-payments">
        <span class="footer-pay footer-pay-m">M-Pesa</span>
        <span class="footer-pay footer-pay-a">Airtel Money</span>
        <span class="footer-pay footer-pay-o">Orange Money</span>
      </div>
    </div>

    <!-- Plateforme -->
    <div>
      <div class="footer-col-title">Plateforme</div>
      <a href="/reussiteplus/archives.php"    class="footer-col-link">Archives officielles</a>
      <a href="/reussiteplus/examen.php"      class="footer-col-link">Passer un examen</a>
      <a href="/reussiteplus/questions.php"   class="footer-col-link">Banque de questions</a>
      <a href="/reussiteplus/cours/index.php" class="footer-col-link">Cours &amp; ressources</a>
      <a href="/reussiteplus/progression.php" class="footer-col-link">Ma progression</a>
      <a href="/reussiteplus/dashboard.php"   class="footer-col-link">Tableau de bord</a>
    </div>

    <!-- Abonnements -->
    <div>
      <div class="footer-col-title">Abonnements</div>
      <a href="/reussiteplus/inscription.php" class="footer-col-link">Plan Gratuit — 0 CDF</a>
      <a href="/reussiteplus/tarifs.php"      class="footer-col-link">Plan Basique — 5 000 CDF</a>
      <a href="/reussiteplus/tarifs.php"      class="footer-col-link">Plan Premium — 10 000 CDF</a>
      <a href="/reussiteplus/tarifs.php"      class="footer-col-link">Plan École — 50 000 CDF</a>
      <a href="/reussiteplus/paiement.php"    class="footer-col-link">Payer via mobile money</a>
      <a href="/reussiteplus/tarifs.php"      class="footer-col-link">Garantie 7 jours</a>
    </div>

    <!-- Support -->
    <div>
      <div class="footer-col-title">Support</div>
      <a href="/reussiteplus/contact.php"    class="footer-col-link">Nous contacter</a>
      <a href="https://wa.me/243977329184"   class="footer-col-link footer-col-link-wa" target="_blank" rel="noopener">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        WhatsApp — +243 97 732 9184
      </a>
      <a href="mailto:support@reussiteplus.cd" class="footer-col-link">support@reussiteplus.cd</a>
      <div class="footer-col-title" style="margin-top:20px">Légal</div>
      <a href="#" class="footer-col-link">Conditions d'utilisation</a>
      <a href="#" class="footer-col-link">Politique de confidentialité</a>
      <a href="#" class="footer-col-link">Gestion des cookies</a>
    </div>

  </div>

  <!-- Barre du bas -->
  <div class="footer-bottom">
    <div class="footer-bottom-inner">
      <div class="footer-copy">
        &copy; <?= date('Y') ?> RÉUSSITE+ &nbsp;&middot;&nbsp; Plateforme EdTech RDC &nbsp;&middot;&nbsp; Kinshasa, République Démocratique du Congo
      </div>
      <div class="footer-legal">
        <a href="#">Confidentialité</a>
        <a href="#">Conditions</a>
        <a href="#">Cookies</a>
      </div>
    </div>
  </div>

</footer>

<script>
// ── NAV SCROLL + HAMBURGER ────────────────────────────────────
(function() {
  const nav  = document.getElementById('mainNav');
  const btn  = document.getElementById('navHamburger');
  const menu = document.getElementById('navMobile');
  let open   = false;

  window.addEventListener('scroll', () => {
    nav.classList.toggle('scrolled', window.scrollY > 30);
  }, { passive: true });

  if (btn && menu) {
    btn.addEventListener('click', () => {
      open = !open;
      menu.classList.toggle('open', open);
      btn.setAttribute('aria-expanded', open);
    });
  }

  window.closeNav = function() {
    open = false;
    menu?.classList.remove('open');
    btn?.setAttribute('aria-expanded', 'false');
  };

  // Fermer si clic à l'extérieur
  document.addEventListener('click', e => {
    if (open && !nav.contains(e.target) && !menu.contains(e.target)) closeNav();
  });
})();

// ── TICKER — duplication automatique pour loop seamless ──────
(function() {
  const track = document.getElementById('tickerTrack');
  if (!track) return;
  const clone = track.cloneNode(true);
  track.parentNode.appendChild(clone);
})();

// ── COUNT-UP ANIMÉ (provinces + élèves) ──────────────────────
(function() {
  const els = document.querySelectorAll('.count-up');
  if (!els.length) return;
  const io = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      const el = entry.target;
      const target = +el.dataset.target;
      let start = 0;
      const dur = 1800;
      const step = timestamp => {
        if (!start) start = timestamp;
        const p = Math.min((timestamp - start) / dur, 1);
        el.textContent = Math.floor(p * target).toLocaleString('fr-FR');
        if (p < 1) requestAnimationFrame(step);
        else el.textContent = target.toLocaleString('fr-FR');
      };
      requestAnimationFrame(step);
      io.unobserve(el);
    });
  }, { threshold: 0.5 });
  els.forEach(el => io.observe(el));
})();

// ── BARRES AVANT/APRÈS ANIMÉES ────────────────────────────────
(function() {
  const bars = document.querySelectorAll('.result-bar-fill');
  if (!bars.length) return;
  const io = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      entry.target.querySelectorAll ? null : null;
    });
  });
  const section = document.querySelector('.result-card');
  if (!section) return;
  const sectionIo = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      bars.forEach(bar => {
        const target = bar.dataset.target;
        if (target) setTimeout(() => { bar.style.width = target + '%'; }, 200);
      });
      sectionIo.disconnect();
    });
  }, { threshold: 0.2 });
  sectionIo.observe(section);
})();

// ── HERO PHOTO CAROUSEL ────────────────────────────────────────
(function() {
  const slides  = document.querySelectorAll('.hero-carousel-slide');
  const dots    = document.querySelectorAll('.hero-carousel-dot');
  const counter = document.getElementById('heroCounter');
  if (!slides.length) return;
  let cur = 0, timer;

  function show(n) {
    slides[cur].classList.remove('active');
    dots[cur].classList.remove('active');
    cur = (n + slides.length) % slides.length;
    slides[cur].classList.add('active');
    dots[cur].classList.add('active');
    if (counter) counter.textContent = (cur + 1) + ' / ' + slides.length;
  }

  function next() { show(cur + 1); }

  // Autoplay 4.5s
  function start() { timer = setInterval(next, 4500); }
  function stop()  { clearInterval(timer); }

  start();

  // Pause on hover
  const carousel = document.getElementById('heroCarousel');
  if (carousel) {
    carousel.addEventListener('mouseenter', stop);
    carousel.addEventListener('mouseleave', start);
    // Swipe support
    let sx = 0;
    carousel.addEventListener('touchstart', e => { sx = e.touches[0].clientX; }, {passive:true});
    carousel.addEventListener('touchend',   e => {
      const dx = e.changedTouches[0].clientX - sx;
      if (Math.abs(dx) > 50) { stop(); show(cur + (dx < 0 ? 1 : -1)); start(); }
    }, {passive:true});
  }

  // Expose dot click globally (called from inline onclick)
  window.goSlide = function(n) { stop(); show(n); start(); };
})();
</script>

</body>
</html>

