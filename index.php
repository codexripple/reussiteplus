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
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RÉUSSITE+ | La plateforme de référence pour réussir en RDC</title>
<link rel="icon" type="image/svg+xml" href="/reussiteplus/assets/img/favicon.svg">
<meta name="description" content="Préparez l'ENAFEP, TENASOSP, Tests diocésains et Examen d'État avec les archives officielles, QCM et plans de révision IA. Rejoignez +12 000 élèves en RDC.">
<link rel="stylesheet" href="/reussiteplus/assets/css/fonts.css">
<link rel="stylesheet" href="/reussiteplus/assets/css/bootstrap-icons.css">
<style>
:root {
  --primary: #007A5E; --primary-dark: #005A45; --primary-light: #00A97F; --primary-subtle: #E8F5F1;
  --gold: #C9972A; --gold-light: #F5E6C0; --rouge: #C9342A; --bleu: #1E5FAD; --bleu-light: #EEF4FD;
  --noir: #0D1117; --gris-900: #1C2433; --gris-800: #2E3A4A; --gris-700: #4A5568; --gris-600: #6B7280;
  --gris-200: #E2E8F0; --gris-100: #F1F5F9; --gris-50: #F8FAFC; --blanc: #FFFFFF;
  --font-display: 'Poppins', sans-serif; --font-body: 'Poppins', sans-serif;
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
  background: rgba(13,17,23,0.95); backdrop-filter: blur(12px);
  border-bottom: 1px solid rgba(255,255,255,0.07);
  padding: 0 40px; height: 68px; display: flex; align-items: center; gap: 32px;
}
.nav-logo { font-family: var(--font-display); font-size: 22px; font-weight: 800; color: white; }
.nav-logo span { color: var(--gold); }
.nav-links { display: flex; gap: 28px; flex: 1; margin-left: 32px; }
.nav-link { font-size: 14px; color: rgba(255,255,255,0.65); transition: var(--transition); }
.nav-link:hover { color: white; }
.nav-actions { display: flex; gap: 12px; align-items: center; }
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
.footer { background: var(--noir); padding: 40px; border-top: 1px solid rgba(255,255,255,0.07); }
.footer-inner { max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; gap: 24px; flex-wrap: wrap; }
.footer-logo { font-family: var(--font-display); font-size: 18px; font-weight: 800; color: white; }
.footer-logo span { color: var(--gold); }
.footer-links { display: flex; gap: 24px; flex-wrap: wrap; }
.footer-link { font-size: 13px; color: rgba(255,255,255,0.4); transition: var(--transition); }
.footer-link:hover { color: rgba(255,255,255,0.8); }
.footer-copy { font-size: 12px; color: rgba(255,255,255,0.3); }

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
</style>
</head>
<body>

<!-- NAVIGATION -->
<nav class="nav">
  <a href="/reussiteplus/index.php" style="display:flex;align-items:center;gap:8px;text-decoration:none">
    <img src="/reussiteplus/assets/img/logo-white.svg" alt="RÉUSSITE+" height="36" style="display:block">
  </a>
  <div class="nav-links">
    <a href="#fonctionnalites" class="nav-link">Fonctionnalités</a>
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
<div style="background:var(--noir)">
<section class="hero">
  <div class="hero-content">
    <div class="hero-badge">🇨🇩 Conçu pour les élèves de la RDC</div>
    <h1 class="hero-title">
      Vos examens approchent.<br>Soyez vraiment<br><span>prêt.</span>
    </h1>
    <p class="hero-sub">Sujets officiels, QCM par matière, corrections détaillées — le tout en un seul endroit, accessible depuis votre téléphone.</p>
    <div class="hero-cta">
      <a href="/reussiteplus/inscription.php" class="btn btn-primary btn-lg">Commencer gratuitement →</a>
      <a href="/reussiteplus/tarifs.php" class="btn btn-gold btn-lg"><i class="bi bi-star-fill"></i> Voir les offres Premium</a>
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
  <!-- Mockup dashboard -->
  <div class="hero-visual">
    <img src="/reussiteplus/assets/img/dashboard-mockup.svg" alt="Tableau de bord RÉUSSITE+" class="hero-mockup">
    <!-- Badge flottant -->
    <div class="hero-float-badge hero-float-1">
      <i class="bi bi-trophy-fill" style="color:var(--gold);font-size:18px"></i>
      <div>
        <div style="font-size:12px;font-weight:700;color:var(--gris-900)">+24 pts ce mois</div>
        <div style="font-size:10px;color:var(--gris-500)">Meilleure série 🔥 7j</div>
      </div>
    </div>
    <div class="hero-float-badge hero-float-2">
      <i class="bi bi-patch-check-fill" style="color:var(--primary);font-size:18px"></i>
      <div>
        <div style="font-size:12px;font-weight:700;color:var(--gris-900)">Examen d'État</div>
        <div style="font-size:10px;color:var(--gris-500)">Score : <strong style="color:var(--primary)">87%</strong></div>
      </div>
    </div>
  </div>
</section>
</div><!-- /hero-wrapper -->

<!-- EXAM TYPES -->
<div class="exams-strip">
  <span style="font-size:13px;color:var(--gris-600);font-weight:600;margin-right:8px">Préparation pour :</span>
  <span class="exam-tag" style="background:#E8F5F1;color:#005A45"><i class="bi bi-book"></i> ENAFEP</span>
  <span class="exam-tag" style="background:#EEF4FD;color:#1E5FAD"><i class="bi bi-mortarboard"></i> TENASOSP</span>
  <span class="exam-tag" style="background:#F5E6C0;color:#8C6A1A"><i class="bi bi-building-fill"></i> Examen d'État</span>
  <span class="exam-tag" style="background:#FEF0EF;color:#C9342A"><i class="bi bi-shield-check"></i> Tests Diocésains</span>
</div>

<!-- POUR QUI — Section avec photos (style Schoolap) -->
<section class="who-section" id="pour-qui">
  <div class="container">
    <div style="text-align:center;margin-bottom:12px">
      <div class="section-label" style="display:inline-block">Pour qui ?</div>
    </div>
    <h2 class="section-title" style="text-align:center">Une plateforme pour tous<br>ceux qui veulent réussir</h2>

    <!-- Bloc 1 : Élèves -->
    <div class="who-block">
      <div class="who-img-wrap">
        <img src="/reussiteplus/assets/img/hero-students.jpg" alt="Élèves révisant ensemble" class="who-img" loading="lazy">
      </div>
      <div class="who-text">
        <div class="section-label" style="margin-bottom:12px">Élèves & Étudiants</div>
        <h3 class="who-title">Révise efficacement, seul ou avec tes amis</h3>
        <p class="who-desc">Accède à des centaines de sujets officiels classés par matière et par année. Entraîne-toi avec les QCM, suis ta progression semaine par semaine et prépare-toi dans les vraies conditions d'examen.</p>
        <ul class="who-list">
          <li><i class="bi bi-check-circle-fill"></i> ENAFEP, TENASOSP, Examen d'État, Tests Diocésains</li>
          <li><i class="bi bi-check-circle-fill"></i> QCM avec corrections détaillées</li>
          <li><i class="bi bi-check-circle-fill"></i> Suivi de progression par matière</li>
        </ul>
        <a href="/reussiteplus/inscription.php" class="btn btn-primary">Commencer gratuitement →</a>
      </div>
    </div>

    <!-- Bloc 2 : Enseignants / Répétiteurs -->
    <div class="who-block who-block-reverse">
      <div class="who-img-wrap">
        <img src="/reussiteplus/assets/img/section-teacher.jpg" alt="Enseignant avec élèves" class="who-img" loading="lazy">
      </div>
      <div class="who-text">
        <div class="section-label" style="margin-bottom:12px">Enseignants & Répétiteurs</div>
        <h3 class="who-title">Préparez vos cours avec les vrais sujets d'examens</h3>
        <p class="who-desc">Retrouvez les archives officielles de toutes les provinces, créez des révisions ciblées et partagez les ressources avec vos élèves. La banque de questions couvre 8 matières avec 5 niveaux de difficulté.</p>
        <ul class="who-list">
          <li><i class="bi bi-check-circle-fill"></i> Archives par province et par année</li>
          <li><i class="bi bi-check-circle-fill"></i> 600+ questions QCM prêtes à l'emploi</li>
          <li><i class="bi bi-check-circle-fill"></i> Corrigés officiels téléchargeables</li>
        </ul>
        <a href="/reussiteplus/inscription.php" class="btn btn-primary">Créer un compte →</a>
      </div>
    </div>

    <!-- Bloc 3 : Parents -->
    <div class="who-block">
      <div class="who-img-wrap">
        <img src="/reussiteplus/assets/img/section-student.jpg" alt="Élève qui étudie" class="who-img" loading="lazy">
      </div>
      <div class="who-text">
        <div class="section-label" style="margin-bottom:12px">Parents & Familles</div>
        <h3 class="who-title">Suivez les progrès de votre enfant en temps réel</h3>
        <p class="who-desc">Votre enfant a un examen dans 3 mois ? Inscrivez-le gratuitement dès aujourd'hui. Le plan de révision s'adapte à son niveau, ses points faibles et la date de son examen.</p>
        <ul class="who-list">
          <li><i class="bi bi-check-circle-fill"></i> Inscription gratuite en 2 minutes</li>
          <li><i class="bi bi-check-circle-fill"></i> Tableau de bord de progression</li>
          <li><i class="bi bi-check-circle-fill"></i> Plan de révision sur-mesure (Premium)</li>
        </ul>
        <a href="/reussiteplus/inscription.php" class="btn btn-primary">Inscrire mon enfant →</a>
      </div>
    </div>
  </div>
</section>

<!-- FONCTIONNALITÉS -->
<section class="features" id="fonctionnalites">
  <div class="container">
    <div style="text-align:center;margin-bottom:12px">
      <div class="section-label" style="display:inline-block">Pourquoi RÉUSSITE+ ?</div>
    </div>
    <h2 class="section-title" style="text-align:center">Ce qu'on trouve ici,<br>nulle part ailleurs</h2>
    <p class="section-sub" style="text-align:center;margin:0 auto">Conçu autour des examens officiels de la RDC, pas copié sur une plateforme étrangère.</p>

    <div class="features-grid">
      <div class="feature-card">
        <div class="feature-icon"><i class="bi bi-folder2-open"></i></div>
        <div class="feature-title">Sujets & corrigés officiels</div>
        <div class="feature-desc">ENAFEP, TENASOSP, Examen d'État, Tests diocésains — des centaines de sujets classés par matière, année et province. Exactement ce qui tombe aux examens.</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon"><i class="bi bi-pencil-square"></i></div>
        <div class="feature-title">Entraîne-toi dans les vraies conditions</div>
        <div class="feature-desc">Minuteur, QCM, score immédiat. Tu peux refaire le même examen autant de fois que tu veux — jusqu'à ce que tu le maîtrises vraiment.</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon"><i class="bi bi-lightbulb"></i></div>
        <div class="feature-title">Des milliers de questions classées</div>
        <div class="feature-desc">Par matière, par chapitre, par niveau. Chaque question a son explication. Tu sais exactement pourquoi tu t'es trompé, pas juste quelle réponse était bonne.</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon"><i class="bi bi-graph-up-arrow"></i></div>
        <div class="feature-title">Tu vois où tu en es</div>
        <div class="feature-desc">Score par matière, jours consécutifs, classement provincial. Plus besoin de deviner si tu progresses — les chiffres te le disent.</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon"><i class="bi bi-calendar-check"></i></div>
        <div class="feature-title">Plan de révision sur-mesure (Premium)</div>
        <div class="feature-desc">Tu entres la date de ton examen. On calcule ce qu'il faut réviser chaque semaine en fonction de tes points faibles. Pas de panique, juste un plan clair.</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon"><i class="bi bi-wifi-off"></i></div>
        <div class="feature-title">Fonctionne sans connexion</div>
        <div class="feature-desc">Pas de wifi ? Pas de problème. Les archives et les QCM téléchargés restent disponibles sur ton téléphone même hors-ligne.</div>
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
    <h2 class="section-title" style="text-align:center">Commence gratuitement,<br>passe au niveau supérieur si tu veux</h2>
    <p class="section-sub" style="text-align:center;margin:0 auto">Paiement en CDF — M-Pesa, Airtel Money ou Orange Money.</p>

    <div class="pricing-grid" style="margin-top:56px">
      <?php foreach (PLANS as $planKey => $plan): ?>
      <div class="plan-card <?= ($plan['populaire'] ?? false) ? 'popular' : '' ?>">
        <?php if ($plan['populaire'] ?? false): ?>
          <div class="plan-popular-badge">⭐ Le plus populaire</div>
        <?php endif; ?>
        <div class="plan-icon"><?= $plan['icone'] ?></div>
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
            <?= ($plan['populaire'] ?? false) ? '⭐ ' : '' ?>Choisir ce plan
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
    <h2 class="section-title" style="text-align:center">Ils l'ont fait.<br>À ton tour.</h2>
    <div class="testimonials-grid">
      <div class="testimonial-card">
        <div class="testimonial-stars">★★★★★</div>
        <p class="testimonial-text">"J'ai tout trouvé ici — les sujets depuis des années, les corrigés, les QCM. 87% à l'Examen d'État. Mes parents n'en revenaient pas."</p>
        <div class="testimonial-author">
          <div class="testimonial-avatar">KM</div>
          <div><div class="testimonial-name">Kala Muamba</div><div class="testimonial-school">Lycée Bosangani, Kinshasa</div></div>
        </div>
      </div>
      <div class="testimonial-card">
        <div class="testimonial-stars">★★★★★</div>
        <p class="testimonial-text">"Avant je séchais sur les corrigés parce que je n'avais personne pour m'expliquer. Là j'ai tout compris toute seule. TENASOSP réussi du premier coup."</p>
        <div class="testimonial-author">
          <div class="testimonial-avatar">BN</div>
          <div><div class="testimonial-name">Bénédicte Nzuzi</div><div class="testimonial-school">Institut Kikesa, Lubumbashi</div></div>
        </div>
      </div>
      <div class="testimonial-card">
        <div class="testimonial-stars">★★★★☆</div>
        <p class="testimonial-text">"Je prépare mes interros avec la banque de questions. Mes élèves travaillent aussi tout seuls sur la plateforme entre les séances. Ils ont clairement progressé."</p>
        <div class="testimonial-author">
          <div class="testimonial-avatar">EM</div>
          <div><div class="testimonial-name">Emmanuel Mbuyi</div><div class="testimonial-school">Répétiteur, Mbuji-Mayi</div></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- CTA FINAL -->
<section class="cta-section">
  <div style="position:relative">
    <h2 class="cta-title">L'examen n'attend pas.</h2>
    <p class="cta-sub">Plus de 12 000 élèves en RDC sont déjà dessus. Commence maintenant, c'est gratuit.</p>
    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
      <a href="/reussiteplus/inscription.php" class="btn btn-primary btn-lg">Créer mon compte gratuitement →</a>
      <a href="/reussiteplus/tarifs.php" class="btn btn-gold btn-lg">⭐ Voir le Premium</a>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer class="footer">
  <div class="footer-inner">
    <div>
      <img src="/reussiteplus/assets/img/logo-white.svg" alt="RÉUSSITE+" height="36" style="display:block;margin-bottom:8px;opacity:.9">
      <div style="font-size:12px;color:rgba(255,255,255,0.3);margin-top:4px">© <?= date('Y') ?> — Plateforme EdTech RDC</div>
    </div>
    <div class="footer-links">
      <a href="/reussiteplus/tarifs.php" class="footer-link">Tarifs</a>
      <a href="/reussiteplus/archives.php" class="footer-link">Archives</a>
      <a href="/reussiteplus/inscription.php" class="footer-link">Inscription</a>
      <a href="/reussiteplus/contact.php" class="footer-link">Contact</a>
    </div>
    <div class="footer-copy">Paiement via 💚 M-Pesa · 🔴 Airtel Money · 🟠 Orange Money</div>
  </div>
</footer>

</body>
</html>
