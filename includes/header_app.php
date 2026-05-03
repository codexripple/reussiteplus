<?php
// Template : En-tête de page (avec sidebar)
// Usage: include 'includes/header_app.php'; après avoir défini $pageTitle et $pageActive
if (!isset($pageTitle)) $pageTitle = 'Dashboard';
if (!isset($pageActive)) $pageActive = 'dashboard';
$user  = require_login();
$stats = get_user_stats($user['id']);
$notifs = (int)($stats['notifs_non_lues'] ?? 0);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> — RÉUSSITE+</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/reussiteplus/assets/css/app.css">
<?= isset($extraHead) ? $extraHead : '' ?>
</head>
<body>
<div class="app-wrapper">

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-icon">🎓</div>
    <div>
      <div class="logo-text">RÉUSSITE<span>+</span></div>
      <div class="logo-sub">Plateforme EdTech RDC</div>
    </div>
  </div>

  <div class="sidebar-user">
    <div class="user-avatar"><?= strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1)) ?></div>
    <div>
      <div class="user-info-name"><?= e($user['prenom'] . ' ' . $user['nom']) ?></div>
      <div>
        <?php $plan = $user['plan']; $plans = PLANS; ?>
        <span class="user-info-plan"><?= $plans[$plan]['icone'] ?? '🎒' ?> <?= e($plans[$plan]['nom'] ?? $plan) ?></span>
      </div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-title">Principal</div>

    <a href="/reussiteplus/dashboard.php" class="nav-item <?= $pageActive === 'dashboard' ? 'active' : '' ?>">
      <div class="nav-icon">🏠</div>
      <span class="nav-label">Tableau de bord</span>
    </a>
    <a href="/reussiteplus/archives.php" class="nav-item <?= $pageActive === 'archives' ? 'active' : '' ?>">
      <div class="nav-icon">📁</div>
      <span class="nav-label">Archives</span>
    </a>
    <a href="/reussiteplus/examen.php" class="nav-item <?= $pageActive === 'examen' ? 'active' : '' ?>">
      <div class="nav-icon">✏️</div>
      <span class="nav-label">Passer un examen</span>
    </a>
    <a href="/reussiteplus/questions.php" class="nav-item <?= $pageActive === 'questions' ? 'active' : '' ?>">
      <div class="nav-icon">🧠</div>
      <span class="nav-label">Banque de questions</span>
    </a>

    <div class="nav-section-title" style="margin-top:12px">Progression</div>
    <a href="/reussiteplus/progression.php" class="nav-item <?= $pageActive === 'progression' ? 'active' : '' ?>">
      <div class="nav-icon">📈</div>
      <span class="nav-label">Ma progression</span>
    </a>
    <a href="/reussiteplus/notifications.php" class="nav-item <?= $pageActive === 'notifications' ? 'active' : '' ?>">
      <div class="nav-icon">🔔</div>
      <span class="nav-label">Notifications</span>
      <?php if ($notifs > 0): ?>
        <span class="nav-badge"><?= $notifs ?></span>
      <?php endif; ?>
    </a>

    <?php if (is_admin()): ?>
    <div class="nav-section-title" style="margin-top:12px">Administration</div>
    <a href="/reussiteplus/admin/index.php" class="nav-item <?= $pageActive === 'admin' ? 'active' : '' ?>">
      <div class="nav-icon">⚙️</div>
      <span class="nav-label">Admin</span>
    </a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-bottom">
    <?php if ($user['plan'] === 'GRATUIT'): ?>
    <a href="/reussiteplus/tarifs.php" class="sidebar-upgrade">
      <div class="sidebar-upgrade-title">⭐ Passer à Premium</div>
      <div class="sidebar-upgrade-sub">Accès illimité dès 10 000 CDF/mois</div>
    </a>
    <?php endif; ?>
    <a href="/reussiteplus/deconnexion.php" class="nav-item" style="margin-top:8px">
      <div class="nav-icon">🚪</div>
      <span class="nav-label">Déconnexion</span>
    </a>
  </div>
</aside>

<!-- MAIN CONTENT -->
<div class="main-content">
  <!-- TOP BAR -->
  <header class="topbar">
    <h1 class="topbar-title"><?= e($pageTitle) ?></h1>
    <form class="search-bar" action="/reussiteplus/recherche.php" method="GET">
      <span>🔍</span>
      <input type="search" name="q" placeholder="Rechercher archives, questions..." value="<?= e($_GET['q'] ?? '') ?>">
    </form>
    <a href="/reussiteplus/abonnement.php" class="topbar-btn" title="Mon abonnement">
      <?= PLANS[$user['plan']]['icone'] ?? '🎒' ?>
    </a>
    <a href="/reussiteplus/notifications.php" class="topbar-btn" title="Notifications">
      🔔
      <?php if ($notifs > 0): ?><span class="notif-dot"></span><?php endif; ?>
    </a>
  </header>

  <!-- PAGE CONTENT -->
  <main class="page-content">
    <?= show_flash() ?>
