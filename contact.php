<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$user = get_user(); // peut être null — page publique

$success = false;
$errors  = [];

// ── Traitement du formulaire ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Token de sécurité invalide. Rechargez la page.';
    } else {
        $nom      = trim($_POST['nom']      ?? '');
        $email    = trim($_POST['email']    ?? '');
        $tel      = trim($_POST['telephone'] ?? '');
        $sujet    = $_POST['sujet']         ?? 'AUTRE';
        $message  = trim($_POST['message']  ?? '');

        // Validation
        if (!$nom || mb_strlen($nom) < 2)        $errors[] = 'Votre nom est requis (minimum 2 caractères).';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Adresse e-mail invalide.';
        if (!$message || mb_strlen($message) < 10) $errors[] = 'Le message est trop court (minimum 10 caractères).';
        if (mb_strlen($message) > 2000)            $errors[] = 'Le message est trop long (maximum 2000 caractères).';
        $allowed = ['PLAN','TECHNIQUE','PARTENARIAT','PRESSE','AUTRE'];
        if (!in_array($sujet, $allowed, true)) $sujet = 'AUTRE';

        // Anti-spam simple : pas plus de 3 messages par IP / heure
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
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
            $success = true;
        }
    }
}

$sujets = [
    'PLAN'         => 'Question sur un abonnement / tarif',
    'TECHNIQUE'    => 'Problème technique',
    'PARTENARIAT'  => 'Partenariat ou collaboration école',
    'PRESSE'       => 'Médias & presse',
    'AUTRE'        => 'Autre',
];

$faq = [
    ['q' => 'Comment accéder aux archives officielles ?',
     'r' => 'Les archives sont accessibles dès le plan Basique. Créez un compte gratuit, puis abonnez-vous à partir de 5 000 CDF/mois.'],
    ['q' => 'Le plan Gratuit a-t-il vraiment 0 CDF ?',
     'r' => 'Oui, totalement gratuit et sans carte bancaire. Vous avez accès à 5 examens d\'entraînement par mois.'],
    ['q' => 'Comment payer mon abonnement en RDC ?',
     'r' => 'Nous acceptons M-Pesa, Airtel Money et Orange Money. Le paiement se fait directement dans l\'application.'],
    ['q' => 'Puis-je utiliser RÉUSSITE+ hors-ligne ?',
     'r' => 'Pour l\'instant, une connexion est requise. Une version hors-ligne est prévue pour fin 2025.'],
    ['q' => 'Comment préparer l\'Examen d\'État avec la plateforme ?',
     'r' => 'Choisissez le type "Examen d\'État" dans la configuration, sélectionnez votre matière et lancez une simulation chronométrée.'],
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
<link rel="stylesheet" href="/reussiteplus/assets/css/fonts.css">
<link rel="stylesheet" href="/reussiteplus/assets/css/bootstrap-icons.css">
<style>
:root {
  --primary:#007A5E;--primary-dark:#005A45;--primary-light:#00A97F;--primary-subtle:#E8F5F1;
  --gold:#C9972A;--gold-light:#FEF3D7;--gold-dark:#8C6A1A;
  --rouge:#C9342A;--noir:#0D1117;--gris-900:#1C2433;--gris-800:#2D3748;
  --gris-700:#4A5568;--gris-600:#6B7280;--gris-500:#9CA3AF;--gris-400:#A0AEC0;
  --gris-200:#E2E8F0;--gris-100:#F1F5F9;--gris-50:#F8FAFC;--blanc:#FFFFFF;
  --bleu:#1E5FAD;--bleu-light:#EEF4FD;
  --font-display:'Poppins',sans-serif;--font-body:'Poppins',sans-serif;
  --radius:10px;--radius-lg:16px;--radius-xl:24px;
  --shadow:0 2px 8px rgba(0,0,0,.08);--shadow-lg:0 8px 32px rgba(0,0,0,.12);
  --transition:200ms cubic-bezier(0.4,0,0.2,1);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html{scroll-behavior:smooth;}
body{font-family:var(--font-body);background:var(--gris-50);color:var(--gris-800);}
a{text-decoration:none;color:inherit;}

/* ── NAV ─────────────────────────────────────────────── */
.nav {
  position:sticky;top:0;z-index:100;
  background:rgba(13,17,23,0.96);backdrop-filter:blur(12px);
  display:flex;align-items:center;justify-content:space-between;
  padding:0 40px;height:64px;border-bottom:1px solid rgba(255,255,255,0.07);
}
.nav-links{display:flex;gap:28px;}
.nav-link{font-size:14px;font-weight:500;color:rgba(255,255,255,0.6);transition:var(--transition);}
.nav-link:hover,.nav-link.active{color:white;}
.nav-actions{display:flex;gap:10px;align-items:center;}
.btn{display:inline-flex;align-items:center;gap:7px;padding:9px 20px;border-radius:var(--radius);font-size:14px;font-weight:600;font-family:var(--font-body);cursor:pointer;transition:var(--transition);border:none;text-decoration:none;}
.btn-primary{background:var(--primary);color:white;}
.btn-primary:hover{background:var(--primary-dark);}
.btn-outline{background:transparent;color:rgba(255,255,255,0.8);border:1.5px solid rgba(255,255,255,0.2);}
.btn-outline:hover{background:rgba(255,255,255,0.07);}
.btn-ghost{background:transparent;color:var(--gris-700);border:1.5px solid var(--gris-200);}
.btn-ghost:hover{background:var(--gris-100);}

/* ── HERO ─────────────────────────────────────────────── */
.hero-contact {
  background:var(--noir);padding:80px 40px 60px;text-align:center;
  position:relative;overflow:hidden;
}
.hero-contact::before {
  content:'';position:absolute;inset:0;
  background:radial-gradient(ellipse 70% 80% at 50% 0%,rgba(0,122,94,0.22) 0%,transparent 65%);
  pointer-events:none;
}
.hero-contact-inner{position:relative;max-width:640px;margin:0 auto;}
.hero-label{display:inline-flex;align-items:center;gap:8px;background:rgba(0,122,94,0.15);border:1px solid rgba(0,122,94,0.4);padding:5px 14px;border-radius:50px;font-size:12px;color:var(--primary-light);font-weight:600;letter-spacing:1px;text-transform:uppercase;margin-bottom:20px;}
.hero-contact h1{font-family:var(--font-display);font-size:clamp(30px,5vw,52px);font-weight:900;color:white;line-height:1.1;margin-bottom:14px;}
.hero-contact p{font-size:17px;color:rgba(255,255,255,0.58);line-height:1.7;}

/* ── MAIN LAYOUT ─────────────────────────────────────── */
.contact-main{max-width:1100px;margin:0 auto;padding:64px 40px;}
.contact-grid{display:grid;grid-template-columns:1fr 1.7fr;gap:48px;align-items:start;}

/* ── INFO CARD ───────────────────────────────────────── */
.info-card{background:white;border-radius:var(--radius-xl);padding:36px;border:1px solid var(--gris-200);box-shadow:var(--shadow);}
.info-title{font-family:var(--font-display);font-size:18px;font-weight:800;color:var(--gris-900);margin-bottom:6px;}
.info-sub{font-size:13px;color:var(--gris-600);margin-bottom:28px;line-height:1.6;}
.info-item{display:flex;align-items:flex-start;gap:14px;padding:14px 0;border-bottom:1px solid var(--gris-100);}
.info-item:last-child{border-bottom:none;padding-bottom:0;}
.info-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}
.info-icon.green{background:var(--primary-subtle);color:var(--primary);}
.info-icon.gold{background:var(--gold-light);color:var(--gold-dark);}
.info-icon.bleu{background:var(--bleu-light);color:var(--bleu);}
.info-label{font-size:11px;font-weight:700;color:var(--gris-500);text-transform:uppercase;letter-spacing:.8px;margin-bottom:3px;}
.info-value{font-size:14px;color:var(--gris-800);font-weight:500;line-height:1.5;}
.info-value a{color:var(--primary);font-weight:600;}
.info-value a:hover{color:var(--primary-dark);}

/* Social */
.social-row{display:flex;gap:10px;margin-top:24px;padding-top:20px;border-top:1px solid var(--gris-100);}
.social-btn{width:38px;height:38px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:17px;transition:var(--transition);}
.social-btn.fb{background:#EEF2FF;color:#4F63D2;}
.social-btn.fb:hover{background:#4F63D2;color:white;}
.social-btn.tw{background:#E8F5FE;color:#1DA1F2;}
.social-btn.tw:hover{background:#1DA1F2;color:white;}
.social-btn.wa{background:#E8F8EF;color:#25D366;}
.social-btn.wa:hover{background:#25D366;color:white;}
.social-btn.yt{background:#FEE8E8;color:#FF0000;}
.social-btn.yt:hover{background:#FF0000;color:white;}

/* ── FORM CARD ───────────────────────────────────────── */
.form-card{background:white;border-radius:var(--radius-xl);padding:40px;border:1px solid var(--gris-200);box-shadow:var(--shadow);}
.form-title{font-family:var(--font-display);font-size:22px;font-weight:800;color:var(--gris-900);margin-bottom:6px;}
.form-sub{font-size:14px;color:var(--gris-600);margin-bottom:28px;}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.form-group{margin-bottom:18px;}
.form-label{display:block;font-size:13px;font-weight:600;color:var(--gris-700);margin-bottom:6px;}
.form-control{
  width:100%;padding:11px 14px;border:1.5px solid var(--gris-200);border-radius:var(--radius);
  font-size:14px;font-family:var(--font-body);color:var(--gris-900);background:white;
  transition:var(--transition);outline:none;
}
.form-control:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(0,122,94,0.12);}
.form-control.error{border-color:var(--rouge);}
textarea.form-control{resize:vertical;min-height:130px;line-height:1.6;}
.char-count{font-size:11px;color:var(--gris-400);text-align:right;margin-top:4px;}
.sujet-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
.sujet-option input{display:none;}
.sujet-label{
  display:flex;align-items:center;gap:8px;padding:10px 14px;
  border:1.5px solid var(--gris-200);border-radius:var(--radius);
  font-size:13px;font-weight:500;color:var(--gris-700);cursor:pointer;
  transition:var(--transition);
}
.sujet-label:hover{border-color:var(--primary);background:var(--primary-subtle);}
.sujet-option input:checked + .sujet-label{
  border-color:var(--primary);background:var(--primary-subtle);color:var(--primary-dark);font-weight:700;
}
.btn-submit{
  width:100%;padding:14px;background:var(--primary);color:white;border:none;
  border-radius:var(--radius);font-size:15px;font-weight:700;font-family:var(--font-body);
  cursor:pointer;transition:var(--transition);display:flex;align-items:center;justify-content:center;gap:8px;
}
.btn-submit:hover{background:var(--primary-dark);}
.btn-submit:disabled{opacity:.6;cursor:not-allowed;}

/* Alerts */
.alert{padding:14px 18px;border-radius:var(--radius);font-size:14px;margin-bottom:20px;display:flex;align-items:flex-start;gap:10px;}
.alert-success{background:#E8F5F1;border:1px solid rgba(0,122,94,0.3);color:var(--primary-dark);}
.alert-error{background:#FEF0EF;border:1px solid rgba(201,52,42,0.3);color:var(--rouge);}
.errors-list{list-style:none;}
.errors-list li{padding:3px 0;}

/* ── FAQ ──────────────────────────────────────────────── */
.faq-section{max-width:1100px;margin:0 auto;padding:0 40px 80px;}
.faq-title{font-family:var(--font-display);font-size:clamp(22px,3vw,34px);font-weight:800;color:var(--gris-900);margin-bottom:8px;}
.faq-sub{font-size:15px;color:var(--gris-600);margin-bottom:36px;}
.faq-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.faq-item{background:white;border-radius:var(--radius-lg);border:1px solid var(--gris-200);overflow:hidden;transition:var(--transition);}
.faq-item:hover{border-color:var(--primary);box-shadow:var(--shadow);}
.faq-q{
  display:flex;align-items:center;justify-content:space-between;gap:12px;
  padding:18px 20px;cursor:pointer;font-size:14px;font-weight:600;color:var(--gris-900);
  list-style:none;
}
.faq-q::after{content:'\f282';font-family:'bootstrap-icons';font-size:12px;color:var(--gris-400);flex-shrink:0;transition:transform .2s;}
.faq-item.open .faq-q::after{transform:rotate(180deg);color:var(--primary);}
.faq-a{padding:0 20px;max-height:0;overflow:hidden;transition:max-height .3s ease, padding .3s ease;font-size:14px;color:var(--gris-600);line-height:1.7;}
.faq-item.open .faq-a{max-height:200px;padding-bottom:18px;}

/* ── CTA BOTTOM ──────────────────────────────────────── */
.cta-bottom{background:var(--noir);padding:72px 40px;text-align:center;position:relative;overflow:hidden;}
.cta-bottom::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse 70% 60% at 50% 50%,rgba(0,122,94,0.18) 0%,transparent 70%);pointer-events:none;}
.cta-bottom-inner{position:relative;max-width:560px;margin:0 auto;}
.cta-bottom h2{font-family:var(--font-display);font-size:clamp(26px,4vw,42px);font-weight:900;color:white;margin-bottom:12px;line-height:1.15;}
.cta-bottom p{font-size:16px;color:rgba(255,255,255,0.55);margin-bottom:28px;}

/* ── FOOTER ──────────────────────────────────────────── */
.footer{background:var(--noir);padding:36px 40px;border-top:1px solid rgba(255,255,255,0.07);}
.footer-inner{max-width:1100px;margin:0 auto;display:flex;justify-content:space-between;align-items:center;gap:20px;flex-wrap:wrap;}
.footer-links{display:flex;gap:20px;flex-wrap:wrap;}
.footer-link{font-size:13px;color:rgba(255,255,255,0.4);transition:var(--transition);}
.footer-link:hover{color:rgba(255,255,255,0.8);}

/* ── RESPONSIVE ──────────────────────────────────────── */
@media(max-width:900px){
  .contact-grid{grid-template-columns:1fr;}
  .faq-grid{grid-template-columns:1fr;}
  .form-row{grid-template-columns:1fr;}
  .sujet-grid{grid-template-columns:1fr;}
}
@media(max-width:640px){
  .nav{padding:0 20px;} .nav-links{display:none;}
  .hero-contact,.contact-main,.faq-section,.cta-bottom{padding-left:20px;padding-right:20px;}
  .form-card,.info-card{padding:24px 20px;}
}
</style>
</head>
<body>

<!-- NAVIGATION -->
<nav class="nav">
  <a href="/reussiteplus/index.php" style="display:flex;align-items:center;gap:8px">
    <img src="/reussiteplus/assets/img/logo-white.svg" alt="RÉUSSITE+" height="34" style="display:block">
  </a>
  <div class="nav-links">
    <a href="/reussiteplus/index.php#fonctionnalites" class="nav-link">Fonctionnalités</a>
    <a href="/reussiteplus/tarifs.php" class="nav-link">Tarifs</a>
    <a href="/reussiteplus/archives.php" class="nav-link">Archives</a>
    <a href="/reussiteplus/contact.php" class="nav-link active">Contact</a>
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
<div class="hero-contact">
  <div class="hero-contact-inner">
    <div class="hero-label"><i class="bi bi-envelope-heart"></i> Contactez-nous</div>
    <h1>On est là pour vous<br><span style="color:var(--primary-light)">aider à réussir.</span></h1>
    <p>Une question sur votre abonnement, un problème technique ou une idée de partenariat ? Notre équipe répond sous 24h.</p>
  </div>
</div>

<!-- MAIN GRID -->
<div class="contact-main">
  <div class="contact-grid">

    <!-- INFOS CONTACT -->
    <div>
      <div class="info-card">
        <div class="info-title">Restons en contact</div>
        <div class="info-sub">L'équipe RÉUSSITE+ est basée à Kinshasa et répond tous les jours ouvrables.</div>

        <div class="info-item">
          <div class="info-icon green"><i class="bi bi-envelope-fill"></i></div>
          <div>
            <div class="info-label">Email</div>
            <div class="info-value"><a href="mailto:contact@reussiteplus.cd">contact@reussiteplus.cd</a></div>
          </div>
        </div>

        <div class="info-item">
          <div class="info-icon green"><i class="bi bi-whatsapp"></i></div>
          <div>
            <div class="info-label">WhatsApp</div>
            <div class="info-value"><a href="https://wa.me/243977329184" target="_blank">+243 977 329 184</a><br><span style="font-size:12px;color:var(--gris-500)">Lun–Ven, 8h–18h (heure de Kinshasa)</span></div>
          </div>
        </div>

        <div class="info-item">
          <div class="info-icon gold"><i class="bi bi-geo-alt-fill"></i></div>
          <div>
            <div class="info-label">Adresse</div>
            <div class="info-value">Kinshasa, République Démocratique du Congo</div>
          </div>
        </div>

        <div class="info-item">
          <div class="info-icon bleu"><i class="bi bi-clock-fill"></i></div>
          <div>
            <div class="info-label">Temps de réponse</div>
            <div class="info-value">Moins de <strong>24 heures</strong> en semaine</div>
          </div>
        </div>

        <div class="social-row">
          <a href="#" class="social-btn fb" title="Facebook"><i class="bi bi-facebook"></i></a>
          <a href="#" class="social-btn tw" title="Twitter / X"><i class="bi bi-twitter-x"></i></a>
          <a href="https://wa.me/243977329184" target="_blank" class="social-btn wa" title="WhatsApp"><i class="bi bi-whatsapp"></i></a>
          <a href="#" class="social-btn yt" title="YouTube"><i class="bi bi-youtube"></i></a>
        </div>
      </div>

      <!-- Carte bonus : délai garanti -->
      <div style="background:var(--primary);border-radius:var(--radius-xl);padding:24px 28px;margin-top:16px;color:white">
        <div style="font-size:28px;margin-bottom:10px"><i class="bi bi-shield-check"></i></div>
        <div style="font-family:var(--font-display);font-size:16px;font-weight:800;margin-bottom:6px">Réponse garantie sous 24h</div>
        <div style="font-size:13px;opacity:.8;line-height:1.6">Tous les messages reçus avant 16h (heure RDC) sont traités le jour même.</div>
      </div>
    </div>

    <!-- FORMULAIRE -->
    <div>
      <div class="form-card">
        <div class="form-title">Envoyez-nous un message</div>
        <div class="form-sub">Tous les champs marqués <span style="color:var(--rouge)">*</span> sont obligatoires.</div>

        <?php if ($success): ?>
        <div class="alert alert-success">
          <i class="bi bi-check-circle-fill" style="font-size:20px;flex-shrink:0"></i>
          <div>
            <strong>Message envoyé avec succès !</strong><br>
            Merci <?= e(htmlspecialchars_decode(e($_POST['nom'] ?? ''))) ?>, notre équipe vous répondra sous 24h à <strong><?= e($_POST['email'] ?? '') ?></strong>.
          </div>
        </div>
        <?php endif; ?>

        <?php if ($errors): ?>
        <div class="alert alert-error">
          <i class="bi bi-exclamation-triangle-fill" style="font-size:18px;flex-shrink:0;margin-top:2px"></i>
          <ul class="errors-list">
            <?php foreach ($errors as $err): ?>
            <li><?= e($err) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST" action="" id="contactForm" novalidate>
          <?= csrf_field() ?>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="nom">Nom complet <span style="color:var(--rouge)">*</span></label>
              <input class="form-control <?= in_array('Votre nom', array_map(fn($e)=>substr($e,0,10), $errors)) ? 'error' : '' ?>"
                     type="text" id="nom" name="nom" value="<?= e($_POST['nom'] ?? ($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')) ?>"
                     placeholder="Jean Mukeba" required autocomplete="name">
            </div>
            <div class="form-group">
              <label class="form-label" for="email">Adresse e-mail <span style="color:var(--rouge)">*</span></label>
              <input class="form-control" type="email" id="email" name="email"
                     value="<?= e($_POST['email'] ?? ($user['email'] ?? '')) ?>"
                     placeholder="jean@exemple.cd" required autocomplete="email">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="telephone">Téléphone <span style="font-size:11px;color:var(--gris-400)">(optionnel)</span></label>
              <input class="form-control" type="tel" id="telephone" name="telephone"
                     value="<?= e($_POST['telephone'] ?? '') ?>"
                     placeholder="+243 81 XXX XXXX" autocomplete="tel">
            </div>
            <div class="form-group" style="display:flex;flex-direction:column;justify-content:flex-end">
              <!-- spacer -->
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Sujet <span style="color:var(--rouge)">*</span></label>
            <div class="sujet-grid">
              <?php
              $sujetIcons = ['PLAN'=>'bi-credit-card','TECHNIQUE'=>'bi-bug','PARTENARIAT'=>'bi-building','PRESSE'=>'bi-newspaper','AUTRE'=>'bi-chat-dots'];
              foreach ($sujets as $val => $label):
                $checked = (($_POST['sujet'] ?? 'AUTRE') === $val) ? 'checked' : '';
              ?>
              <label class="sujet-option">
                <input type="radio" name="sujet" value="<?= $val ?>" <?= $checked ?>>
                <span class="sujet-label"><i class="bi <?= $sujetIcons[$val] ?>"></i> <?= $label ?></span>
              </label>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="message">Message <span style="color:var(--rouge)">*</span></label>
            <textarea class="form-control" id="message" name="message" rows="5"
                      maxlength="2000" placeholder="Décrivez votre demande en détail…"
                      oninput="document.getElementById('charCount').textContent = this.value.length"
                      required><?= e($_POST['message'] ?? '') ?></textarea>
            <div class="char-count"><span id="charCount"><?= strlen($_POST['message'] ?? '') ?></span> / 2000</div>
          </div>

          <button type="submit" class="btn-submit" id="submitBtn">
            <i class="bi bi-send-fill"></i> Envoyer le message
          </button>
        </form>
        <?php else: ?>
        <div style="text-align:center;padding:20px 0">
          <a href="/reussiteplus/contact.php" class="btn btn-ghost" style="margin-right:10px"><i class="bi bi-arrow-left"></i> Nouveau message</a>
          <a href="/reussiteplus/index.php" class="btn btn-primary"><i class="bi bi-house"></i> Retour à l'accueil</a>
        </div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<!-- FAQ -->
<section class="faq-section">
  <div class="section-label-small" style="font-size:11px;font-weight:700;color:var(--primary);text-transform:uppercase;letter-spacing:2px;margin-bottom:10px">Questions fréquentes</div>
  <div class="faq-title">Tout ce que vous voulez savoir</div>
  <div class="faq-sub">Avant de nous écrire, vérifiez si la réponse se trouve ici.</div>
  <div class="faq-grid">
    <?php foreach ($faq as $i => $f): ?>
    <div class="faq-item" id="faq-<?= $i ?>">
      <div class="faq-q" onclick="toggleFaq(<?= $i ?>)"><?= e($f['q']) ?></div>
      <div class="faq-a"><?= e($f['r']) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- CTA -->
<section class="cta-bottom">
  <div class="cta-bottom-inner">
    <h2>Pas encore inscrit ?<br>C'est <span style="color:var(--primary-light)">gratuit</span>.</h2>
    <p>Rejoignez des milliers d'élèves qui préparent leurs examens sur RÉUSSITE+.</p>
    <a href="/reussiteplus/inscription.php" class="btn btn-primary" style="font-size:15px;padding:12px 28px">Créer mon compte gratuitement →</a>
  </div>
</section>

<!-- FOOTER -->
<footer class="footer">
  <div class="footer-inner">
    <img src="/reussiteplus/assets/img/logo-white.svg" alt="RÉUSSITE+" height="30">
    <div class="footer-links">
      <a href="/reussiteplus/index.php" class="footer-link">Accueil</a>
      <a href="/reussiteplus/tarifs.php" class="footer-link">Tarifs</a>
      <a href="/reussiteplus/archives.php" class="footer-link">Archives</a>
      <a href="/reussiteplus/contact.php" class="footer-link" style="color:rgba(255,255,255,.7)">Contact</a>
    </div>
    <div style="font-size:12px;color:rgba(255,255,255,0.3)">© <?= date('Y') ?> RÉUSSITE+ — Tous droits réservés</div>
  </div>
</footer>

<script>
// FAQ accordion
function toggleFaq(i) {
  const item = document.getElementById('faq-' + i);
  const isOpen = item.classList.contains('open');
  document.querySelectorAll('.faq-item').forEach(el => el.classList.remove('open'));
  if (!isOpen) item.classList.add('open');
}

// Empêcher double-soumission
const form = document.getElementById('contactForm');
if (form) {
  form.addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span style="display:inline-block;width:16px;height:16px;border:2px solid rgba(255,255,255,.4);border-top-color:white;border-radius:50%;animation:spin .7s linear infinite;margin-right:8px"></span> Envoi en cours…';
  });
}
</script>
<style>
@keyframes spin { to { transform: rotate(360deg); } }
</style>
</body>
</html>
