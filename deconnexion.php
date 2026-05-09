<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

// Récupérer le prénom AVANT de détruire la session
$prenom = $_SESSION['user']['prenom'] ?? 'vous';

// Détruire la session proprement
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Déconnexion — RÉUSSITE+</title>
<link rel="icon" type="image/svg+xml" href="/reussiteplus/assets/img/favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700;800&family=Inter:wght@400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Inter', sans-serif;
    background: linear-gradient(135deg, #0D1117 0%, #003D2E 60%, #001A2C 100%);
    min-height: 100vh;
    display: flex; align-items: center; justify-content: center;
    padding: 20px;
    color: #fff;
    overflow: hidden;
  }

  /* Cercles décoratifs */
  .bg-orb {
    position: fixed; border-radius: 50%;
    pointer-events: none; opacity: 0.12;
    filter: blur(60px);
  }
  .bg-orb-1 { width: 400px; height: 400px; background: #007A5E; top: -100px; left: -100px; }
  .bg-orb-2 { width: 350px; height: 350px; background: #1E5FAD; bottom: -80px; right: -80px; }
  .bg-orb-3 { width: 200px; height: 200px; background: #C9972A; top: 50%; left: 50%; transform: translate(-50%,-50%); }

  .card {
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 24px;
    padding: 48px 40px;
    max-width: 420px; width: 100%;
    text-align: center;
    backdrop-filter: blur(20px);
    box-shadow: 0 32px 80px rgba(0,0,0,0.4);
    position: relative;
    animation: fadeIn .4s ease;
  }
  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
  }

  .logo {
    display: inline-flex; align-items: center; gap: 10px;
    margin-bottom: 32px;
  }
  .logo-text {
    font-family: 'Poppins', sans-serif;
    font-size: 22px; font-weight: 800;
    color: #fff; letter-spacing: -0.5px;
  }
  .logo-text span { color: #FBBF24; }

  .icon-wrap {
    width: 80px; height: 80px; border-radius: 50%;
    background: rgba(0,122,94,0.2);
    border: 2px solid rgba(0,122,94,0.4);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 24px;
    animation: pulse 2s ease infinite;
  }
  @keyframes pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(0,122,94,0.3); }
    50%       { box-shadow: 0 0 0 12px rgba(0,122,94,0); }
  }
  .icon-wrap svg { width: 36px; height: 36px; stroke: #4ade80; }

  .title {
    font-family: 'Poppins', sans-serif;
    font-size: 26px; font-weight: 800;
    margin-bottom: 10px; line-height: 1.2;
  }
  .subtitle {
    font-size: 15px; color: rgba(255,255,255,0.6);
    line-height: 1.6; margin-bottom: 32px;
  }
  .subtitle strong { color: rgba(255,255,255,0.9); }

  /* Barre de progression */
  .progress-track {
    height: 4px; background: rgba(255,255,255,0.1);
    border-radius: 10px; overflow: hidden;
    margin-bottom: 20px;
  }
  .progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #007A5E, #4ade80);
    border-radius: 10px;
    width: 0%;
    animation: fillBar 3s linear forwards;
  }
  @keyframes fillBar { from { width: 0% } to { width: 100% } }

  .redirect-text {
    font-size: 13px; color: rgba(255,255,255,0.4);
    margin-bottom: 24px;
  }

  .btn-login {
    display: inline-flex; align-items: center; gap: 8px;
    background: #007A5E; color: #fff;
    padding: 14px 28px; border-radius: 12px;
    font-family: 'Poppins', sans-serif;
    font-size: 14px; font-weight: 700;
    text-decoration: none; transition: all .2s;
    width: 100%; justify-content: center;
  }
  .btn-login:hover { background: #005A45; transform: translateY(-1px); }

  .links {
    display: flex; gap: 16px; justify-content: center;
    margin-top: 20px;
  }
  .links a {
    font-size: 13px; color: rgba(255,255,255,0.4);
    text-decoration: none; transition: color .2s;
  }
  .links a:hover { color: rgba(255,255,255,0.8); }

  @media (max-width: 480px) {
    .card { padding: 36px 24px; }
    .title { font-size: 22px; }
  }
</style>
</head>
<body>

<div class="bg-orb bg-orb-1"></div>
<div class="bg-orb bg-orb-2"></div>
<div class="bg-orb bg-orb-3"></div>

<div class="card">

  <div class="logo">
    <img src="/reussiteplus/assets/img/logo-icon.svg" alt="RÉUSSITE+" width="36" height="36" style="border-radius:10px;display:block">
    <div class="logo-text">RÉUSSITE<span>+</span></div>
  </div>

  <div class="icon-wrap">
    <!-- Icône "porte de sortie" avec check -->
    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#4ade80" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
      <polyline points="16 17 21 12 16 7"/>
      <line x1="21" y1="12" x2="9" y2="12"/>
    </svg>
  </div>

  <div class="title">À bientôt, <?= htmlspecialchars($prenom, ENT_QUOTES, 'UTF-8') ?> ! </div>
  <div class="subtitle">
    Vous êtes bien déconnecté(e).<br>
    Votre session a été fermée en toute sécurité.<br>
    <strong>Continuez à réviser, le succès vous attend !</strong>
  </div>

  <div class="progress-track">
    <div class="progress-bar"></div>
  </div>
  <div class="redirect-text">Redirection vers la page de connexion...</div>

  <a href="/reussiteplus/connexion.php" class="btn-login">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
    Se reconnecter
  </a>

  <div class="links">
    <a href="/reussiteplus/index.php">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px;margin-right:3px"><polyline points="15 18 9 12 15 6"/></svg>
      Accueil
    </a>
    <a href="/reussiteplus/inscription.php">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px;margin-right:3px"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
      Créer un compte
    </a>
  </div>

</div>

<script>
  setTimeout(function() {
    window.location.href = '/reussiteplus/connexion.php';
  }, 3000);
</script>
</body>
</html>
