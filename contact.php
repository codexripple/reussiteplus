<?php
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$user    = current_user();
$success = false;
$errors  = [];
$nomEnvoyeur = '';

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
            $nomEnvoyeur = $nom;
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
  <a href="/reussiteplus/index.php" style="display:flex;align-items:center;gap:10px">
    <div style="width:36px;height:36px;background:var(--primary);border-radius:10px;display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-weight:900;color:white;font-size:16px">R+</div>
    <span style="font-family:var(--font-display);font-weight:800;color:white;font-size:16px">RÉUSSITE<span style="color:var(--primary-light)">+</span></span>
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

<?php if ($success): ?>
<!-- ══════════════════════════════════════════════════════════ -->
<!--  PAGE SUCCÈS — MESSAGE ENVOYÉ + UPSELL ABONNEMENT        -->
<!-- ══════════════════════════════════════════════════════════ -->

<!-- Confetti animation -->
<div id="confetti-container" style="position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:999;overflow:hidden"></div>

<!-- Hero succès -->
<div style="background:linear-gradient(160deg,#0D1117 0%,#0d3320 50%,#0D1117 100%);padding:80px 40px 60px;text-align:center;position:relative;overflow:hidden">
  <div style="position:absolute;inset:0;background:url('https://images.unsplash.com/photo-1523050854058-8df90110c9f1?w=1400&q=60') center/cover;opacity:.06"></div>
  <!-- Orb glow -->
  <div style="position:absolute;top:-80px;left:50%;transform:translateX(-50%);width:500px;height:500px;border-radius:50%;background:radial-gradient(circle,rgba(0,169,127,.25) 0%,transparent 70%);pointer-events:none"></div>
  <div style="position:relative;max-width:640px;margin:0 auto">
    <!-- Check animé -->
    <div class="success-check">✅</div>
    <div style="font-family:var(--font-display);font-size:clamp(28px,5vw,46px);font-weight:900;color:white;line-height:1.1;margin-bottom:14px">
      Message reçu,<br><span style="color:var(--primary-light)"><?= e(explode(' ', $nomEnvoyeur)[0]) ?> !</span>
    </div>
    <p style="font-size:17px;color:rgba(255,255,255,.55);line-height:1.7;margin-bottom:30px">
      Votre message a bien été transmis à notre équipe.<br>
      Nous vous répondrons sous <strong style="color:white">24 heures</strong>.
    </p>
    <!-- Badge confirmation -->
    <div style="display:inline-flex;align-items:center;gap:10px;background:rgba(0,122,94,.2);border:1px solid rgba(0,122,94,.5);border-radius:50px;padding:10px 22px">
      <i class="bi bi-envelope-check-fill" style="color:var(--primary-light);font-size:18px"></i>
      <span style="color:rgba(255,255,255,.8);font-size:14px">Confirmation envoyée à <strong style="color:white"><?= e($_POST['email'] ?? '') ?></strong></span>
    </div>
  </div>
</div>

<!-- Message motivant -->
<div style="background:white;border-bottom:3px solid var(--primary);padding:28px 40px;text-align:center">
  <div style="max-width:700px;margin:0 auto">
    <p style="font-size:18px;color:var(--gris-800);line-height:1.7;font-style:italic">
      💡 <strong>En attendant notre réponse</strong>, saviez-vous que les élèves qui s'entraînent régulièrement sur RÉUSSITE+ améliorent leurs résultats de <strong style="color:var(--primary)">47% en moyenne</strong> avant les examens officiels ?
    </p>
  </div>
</div>

<!-- Section upsell : plans -->
<div style="background:var(--gris-50);padding:72px 40px">
  <div style="max-width:1100px;margin:0 auto">

    <!-- Titre -->
    <div style="text-align:center;margin-bottom:48px">
      <div style="display:inline-block;background:var(--primary-subtle);border:1px solid rgba(0,122,94,.3);color:var(--primary-dark);font-size:11px;font-weight:700;padding:5px 16px;border-radius:50px;text-transform:uppercase;letter-spacing:1px;margin-bottom:14px">
        🚀 Ne perdez pas de temps
      </div>
      <h2 style="font-family:var(--font-display);font-size:clamp(24px,4vw,38px);font-weight:900;color:var(--gris-900);margin-bottom:10px">
        Commencez à préparer <span style="color:var(--primary)">dès maintenant</span>
      </h2>
      <p style="font-size:16px;color:var(--gris-600);max-width:560px;margin:0 auto;line-height:1.7">
        Des milliers d'élèves congolais utilisent déjà RÉUSSITE+ pour préparer l'ENAFEP et l'Examen d'État. 
        L'examen approche — chaque jour compte.
      </p>
    </div>

    <!-- Témoignage fort -->
    <div style="background:linear-gradient(135deg,#0D1117,#1a3a2a);border-radius:20px;padding:36px;margin-bottom:48px;display:flex;gap:28px;align-items:center;flex-wrap:wrap;position:relative;overflow:hidden">
      <div style="position:absolute;right:-20px;top:-20px;width:200px;height:200px;background:radial-gradient(circle,rgba(0,169,127,.15),transparent 70%);pointer-events:none"></div>
      <div style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#007A5E,#00A97F);display:flex;align-items:center;justify-content:center;font-size:32px;flex-shrink:0">👩‍🎓</div>
      <div style="flex:1;min-width:240px">
        <div style="font-size:17px;color:white;line-height:1.7;font-style:italic;margin-bottom:12px">
          "J'ai eu <strong style="color:#4ade80">18/20 en mathématiques</strong> à l'Examen d'État. Je m'entraînais tous les soirs sur RÉUSSITE+. Les exercices ressemblent exactement aux vrais examens !"
        </div>
        <div style="font-size:13px;color:rgba(255,255,255,.5)">
          <strong style="color:var(--primary-light)">Priscille M.</strong> · Élève finaliste, Kinshasa · Plan Basique
        </div>
      </div>
      <div style="background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:14px;padding:18px 22px;text-align:center;flex-shrink:0">
        <div style="font-family:var(--font-display);font-size:36px;font-weight:900;color:#4ade80">18/20</div>
        <div style="font-size:11px;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.5px">Score final</div>
      </div>
    </div>

    <!-- Plans cards -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin-bottom:48px">

      <!-- Gratuit -->
      <div style="background:white;border:2px solid var(--gris-200);border-radius:20px;padding:28px;position:relative">
        <div style="font-size:32px;margin-bottom:10px">🎓</div>
        <div style="font-family:var(--font-display);font-size:18px;font-weight:900;color:var(--gris-900);margin-bottom:6px">Gratuit</div>
        <div style="font-family:var(--font-display);font-size:28px;font-weight:900;color:var(--gris-700);margin-bottom:16px">0 <span style="font-size:14px;font-weight:500;color:var(--gris-500)">CDF/mois</span></div>
        <ul style="list-style:none;margin-bottom:24px;font-size:13px;color:var(--gris-600);line-height:2">
          <li>✅ 5 examens d'entraînement/mois</li>
          <li>✅ Statistiques de base</li>
          <li>❌ Archives officielles</li>
          <li>❌ Examens illimités</li>
        </ul>
        <a href="/reussiteplus/inscription.php" class="plan-cta" style="display:block;text-align:center;padding:12px;border:2px solid var(--gris-200);border-radius:10px;font-weight:700;color:var(--gris-700);font-size:14px;text-decoration:none;transition:.2s">Commencer gratuitement</a>
      </div>

      <!-- Basique — recommandé -->
      <div style="background:linear-gradient(160deg,#003d2b,#007A5E);border:2px solid var(--primary);border-radius:20px;padding:28px;position:relative;transform:scale(1.03);box-shadow:0 20px 60px rgba(0,122,94,.3)">
        <div style="position:absolute;top:-12px;left:50%;transform:translateX(-50%);background:var(--gold);color:white;font-size:10px;font-weight:800;padding:4px 14px;border-radius:50px;text-transform:uppercase;letter-spacing:1px;white-space:nowrap">⭐ Le plus populaire</div>
        <div style="font-size:32px;margin-bottom:10px">🚀</div>
        <div style="font-family:var(--font-display);font-size:18px;font-weight:900;color:white;margin-bottom:6px">Basique</div>
        <div style="font-family:var(--font-display);font-size:28px;font-weight:900;color:white;margin-bottom:4px">5 000 <span style="font-size:14px;font-weight:500;color:rgba(255,255,255,.6)">CDF/mois</span></div>
        <div style="font-size:12px;color:rgba(255,255,255,.5);margin-bottom:16px">soit ~1,8 USD/mois</div>
        <ul style="list-style:none;margin-bottom:24px;font-size:13px;color:rgba(255,255,255,.8);line-height:2">
          <li>✅ Examens illimités</li>
          <li>✅ Toutes les archives officielles</li>
          <li>✅ Statistiques avancées</li>
          <li>✅ Résultats détaillés</li>
        </ul>
        <a href="/reussiteplus/inscription.php?plan=BASIQUE" style="display:block;text-align:center;padding:13px;background:white;color:var(--primary-dark);border-radius:10px;font-weight:800;font-size:14px;text-decoration:none;transition:.2s">S'abonner maintenant →</a>
      </div>

      <!-- Premium -->
      <div style="background:white;border:2px solid #7C3AED;border-radius:20px;padding:28px;position:relative">
        <div style="font-size:32px;margin-bottom:10px">💎</div>
        <div style="font-family:var(--font-display);font-size:18px;font-weight:900;color:var(--gris-900);margin-bottom:6px">Premium</div>
        <div style="font-family:var(--font-display);font-size:28px;font-weight:900;color:#7C3AED;margin-bottom:16px">10 000 <span style="font-size:14px;font-weight:500;color:var(--gris-500)">CDF/mois</span></div>
        <ul style="list-style:none;margin-bottom:24px;font-size:13px;color:var(--gris-600);line-height:2">
          <li>✅ Tout le plan Basique</li>
          <li>✅ IA pédagogique personnalisée</li>
          <li>✅ Coaching & suivi détaillé</li>
          <li>✅ Certificats de réussite</li>
        </ul>
        <a href="/reussiteplus/inscription.php?plan=PREMIUM" style="display:block;text-align:center;padding:12px;background:linear-gradient(135deg,#7C3AED,#6D28D9);color:white;border-radius:10px;font-weight:700;font-size:14px;text-decoration:none;transition:.2s">Découvrir Premium →</a>
      </div>

    </div>

    <!-- Modes de paiement -->
    <div style="background:white;border-radius:16px;padding:24px 28px;display:flex;align-items:center;gap:20px;flex-wrap:wrap;border:1px solid var(--gris-200)">
      <div style="font-size:14px;font-weight:700;color:var(--gris-700)">💳 Paiement facile avec :</div>
      <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
        <div style="background:#FEF9EC;border:1px solid #F59E0B;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:700;color:#92400E;display:flex;align-items:center;gap:6px">📱 M-Pesa</div>
        <div style="background:#FFF5EC;border:1px solid #F97316;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:700;color:#9A3412;display:flex;align-items:center;gap:6px">📱 Orange Money</div>
        <div style="background:#EFF6FF;border:1px solid #3B82F6;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:700;color:#1E40AF;display:flex;align-items:center;gap:6px">📱 Airtel Money</div>
      </div>
      <div style="margin-left:auto;font-size:12px;color:var(--gris-500)">Pas de carte bancaire requise</div>
    </div>

    <!-- CTAs finaux -->
    <div style="text-align:center;margin-top:36px;display:flex;gap:14px;justify-content:center;flex-wrap:wrap">
      <a href="/reussiteplus/inscription.php" class="btn btn-primary" style="font-size:15px;padding:14px 32px;background:var(--primary)">
        <i class="bi bi-rocket-takeoff-fill"></i> Créer mon compte gratuitement
      </a>
      <a href="/reussiteplus/tarifs.php" class="btn btn-ghost" style="font-size:15px;padding:14px 32px">
        Voir tous les tarifs →
      </a>
    </div>
    <div style="text-align:center;margin-top:14px;font-size:12px;color:var(--gris-500)">✅ Sans engagement · Annulable à tout moment · 100% sécurisé</div>

  </div>
</div>

<!-- Stats sociales -->
<div style="background:var(--noir);padding:48px 40px">
  <div style="max-width:900px;margin:0 auto;display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:2px">
    <?php foreach ([['12 000+','Élèves inscrits'],['98%','Taux de satisfaction'],['47%','Amélioration moyenne'],['24h','Délai de réponse']] as [$num,$lbl]): ?>
    <div style="text-align:center;padding:28px 20px">
      <div style="font-family:var(--font-display);font-size:34px;font-weight:900;color:var(--primary-light)"><?= $num ?></div>
      <div style="font-size:13px;color:rgba(255,255,255,.45);margin-top:4px"><?= $lbl ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php else: ?>
<!-- ══════════════════════════════════════════════════════════ -->
<!--  PAGE FORMULAIRE CONTACT                                  -->
<!-- ══════════════════════════════════════════════════════════ -->

<!-- HERO -->
<div class="hero-contact" style="position:relative;overflow:hidden">
  <!-- Image de fond -->
  <div style="position:absolute;inset:0;background:url('https://images.unsplash.com/photo-1523050854058-8df90110c9f1?w=1400&q=60') center/cover;opacity:.12;pointer-events:none"></div>
  <div style="position:absolute;inset:0;background:radial-gradient(ellipse 70% 80% at 50% 0%,rgba(0,122,94,0.22) 0%,transparent 65%);pointer-events:none"></div>
  <div class="hero-contact-inner" style="position:relative">
    <div class="hero-label"><i class="bi bi-envelope-heart-fill"></i> Contactez-nous</div>
    <h1>On est là pour vous<br><span style="color:var(--primary-light)">aider à réussir.</span></h1>
    <p>Une question sur votre abonnement, un problème technique ou une idée de partenariat ?<br>Notre équipe répond sous <strong style="color:white">24 heures</strong>.</p>
    <!-- Stats mini -->
    <div style="display:flex;justify-content:center;gap:24px;margin-top:32px;flex-wrap:wrap">
      <?php foreach (['⚡ Réponse en < 24h','✅ Support en français','🇨🇩 Équipe basée à Kinshasa'] as $badge): ?>
      <div style="background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.14);padding:8px 16px;border-radius:50px;font-size:13px;color:rgba(255,255,255,.7)"><?= $badge ?></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Bande de confiance -->
<div style="background:var(--primary);padding:16px 40px">
  <div style="max-width:1100px;margin:0 auto;display:flex;justify-content:center;gap:40px;flex-wrap:wrap">
    <?php foreach ([['bi-people-fill','12 000+ élèves actifs'],['bi-star-fill','4.9/5 satisfaction'],['bi-shield-check-fill','Données sécurisées']] as [$icon,$txt]): ?>
    <div style="display:flex;align-items:center;gap:8px;color:rgba(255,255,255,.9);font-size:13px;font-weight:600">
      <i class="bi <?= $icon ?>" style="opacity:.8"></i> <?= $txt ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- MAIN GRID -->
<div class="contact-main">
  <div class="contact-grid">

    <!-- COLONNE GAUCHE -->
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
            <div class="info-value">
              <a href="https://wa.me/243977329184" target="_blank" rel="noopener">+243 977 329 184</a><br>
              <span style="font-size:12px;color:var(--gris-500)">Lun–Ven, 8h–18h (heure de Kinshasa)</span>
            </div>
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
          <a href="https://wa.me/243977329184" target="_blank" rel="noopener" class="social-btn wa"><i class="bi bi-whatsapp"></i></a>
          <a href="#" class="social-btn yt" title="YouTube"><i class="bi bi-youtube"></i></a>
        </div>
      </div>

      <!-- Carte garantie -->
      <div style="background:linear-gradient(135deg,var(--primary),var(--primary-dark));border-radius:var(--radius-xl);padding:28px;margin-top:16px;color:white;position:relative;overflow:hidden">
        <div style="position:absolute;right:-20px;bottom:-20px;width:120px;height:120px;background:rgba(255,255,255,.06);border-radius:50%"></div>
        <div style="font-size:32px;margin-bottom:10px">🛡️</div>
        <div style="font-family:var(--font-display);font-size:16px;font-weight:800;margin-bottom:8px">Réponse garantie sous 24h</div>
        <div style="font-size:13px;opacity:.8;line-height:1.6;margin-bottom:16px">Tous les messages reçus avant 16h sont traités le jour même.</div>
        <div style="background:rgba(255,255,255,.15);border-radius:10px;padding:10px 14px;font-size:12px;display:flex;align-items:center;gap:8px">
          <i class="bi bi-check-circle-fill" style="color:#4ade80"></i>
          Satisfait ou remboursé sous 7 jours
        </div>
      </div>

      <!-- Image témoignage -->
      <div style="margin-top:16px;border-radius:var(--radius-xl);overflow:hidden;position:relative;height:180px">
        <img src="https://images.unsplash.com/photo-1577896851231-70ef18881754?w=700&q=70" alt="Élèves RÉUSSITE+" style="width:100%;height:100%;object-fit:cover">
        <div style="position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,.8),transparent)"></div>
        <div style="position:absolute;bottom:16px;left:16px;right:16px">
          <div style="font-size:13px;color:white;font-style:italic;line-height:1.5">"RÉUSSITE+ m'a aidé à avoir 16/20 à l'Examen d'État !"</div>
          <div style="font-size:11px;color:rgba(255,255,255,.6);margin-top:4px">— Kevin T., Lubumbashi</div>
        </div>
      </div>
    </div>

    <!-- FORMULAIRE -->
    <div>
      <div class="form-card">
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:6px">
          <div style="width:44px;height:44px;background:var(--primary-subtle);border-radius:12px;display:flex;align-items:center;justify-content:center;color:var(--primary);font-size:20px">
            <i class="bi bi-chat-heart-fill"></i>
          </div>
          <div>
            <div class="form-title">Envoyez-nous un message</div>
            <div class="form-sub" style="margin-bottom:0">Notre équipe vous répond personnellement sous 24h.</div>
          </div>
        </div>
        <hr style="border:none;border-top:1.5px solid var(--gris-100);margin:20px 0">

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

        <form method="POST" id="contactForm" novalidate>
          <?= csrf_field() ?>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="nom">Nom complet <span style="color:var(--rouge)">*</span></label>
              <input class="form-control" type="text" id="nom" name="nom"
                     value="<?= e($_POST['nom'] ?? (($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''))) ?>"
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
                $checked = (($_POST['sujet'] ?? 'PLAN') === $val) ? 'checked' : '';
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
                      maxlength="2000"
                      placeholder="Décrivez votre demande en détail. Plus vous donnez de contexte, plus nous pouvons vous aider efficacement…"
                      oninput="document.getElementById('charCount').textContent = this.value.length"
                      required><?= e($_POST['message'] ?? '') ?></textarea>
            <div class="char-count"><span id="charCount"><?= strlen($_POST['message'] ?? '') ?></span> / 2000</div>
          </div>

          <button type="submit" class="btn-submit" id="submitBtn">
            <i class="bi bi-send-fill"></i> Envoyer le message
          </button>
          <div style="text-align:center;margin-top:12px;font-size:12px;color:var(--gris-400)">
            <i class="bi bi-lock-fill"></i> Vos données sont protégées. Nous ne les partageons jamais.
          </div>
        </form>
      </div>

      <!-- Mini upsell sous le formulaire -->
      <div style="margin-top:16px;background:linear-gradient(135deg,#0D1117,#1a2f50);border-radius:var(--radius-xl);padding:24px 28px;position:relative;overflow:hidden">
        <div style="position:absolute;right:0;top:0;bottom:0;width:160px;overflow:hidden;opacity:.15">
          <img src="https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?w=300&q=60" alt="" style="width:100%;height:100%;object-fit:cover">
        </div>
        <div style="position:relative">
          <div style="font-size:11px;font-weight:700;color:var(--gold);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px">💡 Conseil de l'équipe</div>
          <div style="font-family:var(--font-display);font-size:15px;font-weight:800;color:white;margin-bottom:8px">En attente de notre réponse ?</div>
          <div style="font-size:13px;color:rgba(255,255,255,.6);line-height:1.6;margin-bottom:16px">
            Commencez dès maintenant avec notre plan gratuit — 5 examens d'entraînement vous attendent, sans carte bancaire.
          </div>
          <a href="/reussiteplus/inscription.php" style="display:inline-flex;align-items:center;gap:7px;background:var(--primary);color:white;padding:9px 20px;border-radius:9px;font-size:13px;font-weight:700;text-decoration:none">
            <i class="bi bi-rocket-takeoff-fill"></i> Essayer gratuitement →
          </a>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- FAQ -->
<section class="faq-section">
  <div style="font-size:11px;font-weight:700;color:var(--primary);text-transform:uppercase;letter-spacing:2px;margin-bottom:10px">Questions fréquentes</div>
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

<!-- CTA BOTTOM -->
<section class="cta-bottom" style="position:relative;overflow:hidden">
  <div style="position:absolute;inset:0;background:url('https://images.unsplash.com/photo-1427504494785-3a9ca7044f45?w=1400&q=50') center/cover;opacity:.06;pointer-events:none"></div>
  <div class="cta-bottom-inner" style="position:relative">
    <div style="font-size:52px;margin-bottom:16px">🏆</div>
    <h2>Chaque jour sans s'entraîner<br>est un avantage donné aux autres.</h2>
    <p>Plus de 12 000 élèves se préparent déjà sur RÉUSSITE+. L'examen approche — rejoignez-les.</p>
    <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap">
      <a href="/reussiteplus/inscription.php" class="btn btn-primary" style="font-size:15px;padding:14px 32px;background:var(--primary)">
        Créer mon compte gratuitement →
      </a>
      <a href="/reussiteplus/tarifs.php" class="btn btn-outline" style="font-size:15px;padding:14px 32px">
        Voir les tarifs
      </a>
    </div>
    <div style="margin-top:14px;font-size:12px;color:rgba(255,255,255,.3)">✅ Inscription en 30 secondes · Pas de carte bancaire</div>
  </div>
</section>

<?php endif; ?>

<!-- FOOTER -->
<footer class="footer">
  <div class="footer-inner">
    <div style="display:flex;align-items:center;gap:8px">
      <div style="width:30px;height:30px;background:var(--primary);border-radius:8px;display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-weight:900;color:white;font-size:13px">R+</div>
      <span style="font-family:var(--font-display);font-weight:800;color:rgba(255,255,255,.6);font-size:14px">RÉUSSITE+</span>
    </div>
    <div class="footer-links">
      <a href="/reussiteplus/index.php" class="footer-link">Accueil</a>
      <a href="/reussiteplus/tarifs.php" class="footer-link">Tarifs</a>
      <a href="/reussiteplus/archives.php" class="footer-link">Archives</a>
      <a href="/reussiteplus/contact.php" class="footer-link" style="color:rgba(255,255,255,.7)">Contact</a>
    </div>
    <div style="font-size:12px;color:rgba(255,255,255,0.3)">© <?= date('Y') ?> RÉUSSITE+ · Tous droits réservés</div>
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

// Confetti sur page succès
<?php if ($success): ?>
(function() {
  const colors = ['#007A5E','#00A97F','#C9972A','#1E5FAD','#7C3AED','#EC4899'];
  const container = document.getElementById('confetti-container');
  if (!container) return;
  for (let i = 0; i < 80; i++) {
    const c = document.createElement('div');
    const color = colors[Math.floor(Math.random() * colors.length)];
    const size = 6 + Math.random() * 8;
    c.style.cssText = `
      position:absolute;width:${size}px;height:${size}px;
      background:${color};border-radius:${Math.random()>.5?'50%':'2px'};
      left:${Math.random()*100}%;top:-20px;
      animation:fall ${2+Math.random()*3}s ease-in ${Math.random()*2}s forwards;
      opacity:${0.7+Math.random()*.3};
    `;
    container.appendChild(c);
  }
  setTimeout(() => container.remove(), 6000);
})();
<?php endif; ?>
</script>
<style>
@keyframes spin { to { transform: rotate(360deg); } }
@keyframes fall {
  to { transform: translateY(105vh) rotate(720deg); opacity:0; }
}
@keyframes pop-in {
  0%{transform:scale(0) rotate(-20deg);opacity:0}
  70%{transform:scale(1.2) rotate(5deg)}
  100%{transform:scale(1) rotate(0deg);opacity:1}
}
.success-check {
  font-size:72px;display:block;margin-bottom:20px;
  animation: pop-in .6s cubic-bezier(0.34,1.56,0.64,1) both;
}
</style>
</body>
</html>

