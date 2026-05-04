<?php
// Template : En-tête de page (avec sidebar)
if (!isset($pageTitle))  $pageTitle  = 'Dashboard';
if (!isset($pageActive)) $pageActive = 'dashboard';
$user   = require_login();
$stats  = get_user_stats($user['id']);
$notifs = (int)($stats['notifs_non_lues'] ?? 0);
require_once __DIR__ . '/icons.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> — RÉUSSITE+</title>
<link rel="icon" type="image/svg+xml" href="/reussiteplus/assets/img/favicon.svg">
<link rel="stylesheet" href="/reussiteplus/assets/css/fonts.css">
<link rel="stylesheet" href="/reussiteplus/assets/css/bootstrap-icons.css?v=2">
<link rel="stylesheet" href="/reussiteplus/assets/css/app.css">
<?= isset($extraHead) ? $extraHead : '' ?>
</head>
<body>
<div class="app-wrapper">

<!-- MOBILE overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <a href="/reussiteplus/dashboard.php" style="display:flex;align-items:center;gap:10px;text-decoration:none">
      <img src="/reussiteplus/assets/img/logo-icon.svg" alt="RÉUSSITE+" width="36" height="36" style="flex-shrink:0">
      <div>
        <div class="logo-text">RÉUSSITE<span>+</span></div>
        <div class="logo-sub">Plateforme EdTech RDC</div>
      </div>
    </a>
  </div>

  <div class="sidebar-user">
    <div class="user-avatar"><?= strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1)) ?></div>
    <div style="flex:1;min-width:0">
      <div class="user-info-name truncate"><?= e($user['prenom'] . ' ' . $user['nom']) ?></div>
      <?php $plan = $user['plan']; $plans = PLANS; ?>
      <div><span class="user-info-plan"><i class="bi bi-star-fill" style="font-size:10px"></i> <?= e($plans[$plan]['nom'] ?? $plan) ?></span></div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-title">Principal</div>

    <a href="/reussiteplus/dashboard.php" class="nav-item <?= $pageActive === 'dashboard' ? 'active' : '' ?>">
      <div class="nav-icon"><i class="bi bi-house-door"></i></div>
      <span class="nav-label">Tableau de bord</span>
    </a>

    <a href="/reussiteplus/archives.php" class="nav-item <?= $pageActive === 'archives' ? 'active' : '' ?>">
      <div class="nav-icon"><i class="bi bi-archive"></i></div>
      <span class="nav-label">Archives</span>
    </a>

    <a href="/reussiteplus/examen.php" class="nav-item <?= $pageActive === 'examen' ? 'active' : '' ?>">
      <div class="nav-icon"><i class="bi bi-pencil-square"></i></div>
      <span class="nav-label">Passer un examen</span>
    </a>

    <a href="/reussiteplus/questions.php" class="nav-item <?= $pageActive === 'questions' ? 'active' : '' ?>">
      <div class="nav-icon"><i class="bi bi-book"></i></div>
      <span class="nav-label">Banque de questions</span>
    </a>

    <a href="/reussiteplus/recherche.php" class="nav-item <?= $pageActive === 'recherche' ? 'active' : '' ?>">
      <div class="nav-icon"><i class="bi bi-search"></i></div>
      <span class="nav-label">Recherche</span>
    </a>

    <div class="nav-section-title" style="margin-top:12px">Suivi</div>

    <a href="/reussiteplus/progression.php" class="nav-item <?= $pageActive === 'progression' ? 'active' : '' ?>">
      <div class="nav-icon"><i class="bi bi-graph-up"></i></div>
      <span class="nav-label">Ma progression</span>
    </a>

    <a href="/reussiteplus/resultat.php" class="nav-item <?= $pageActive === 'resultat' ? 'active' : '' ?>">
      <div class="nav-icon"><i class="bi bi-trophy"></i></div>
      <span class="nav-label">Mes résultats</span>
    </a>

    <a href="/reussiteplus/notifications.php" class="nav-item <?= $pageActive === 'notifications' ? 'active' : '' ?>">
      <div class="nav-icon"><i class="bi bi-bell"></i></div>
      <span class="nav-label">Notifications</span>
      <?php if ($notifs > 0): ?><span class="nav-badge"><?= $notifs ?></span><?php endif; ?>
    </a>

    <?php if (is_admin()): ?>
    <div class="nav-section-title" style="margin-top:12px">Administration</div>
    <a href="/reussiteplus/admin/index.php" class="nav-item <?= $pageActive === 'admin' ? 'active' : '' ?>">
      <div class="nav-icon"><i class="bi bi-gear"></i></div>
      <span class="nav-label">Administration</span>
    </a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-bottom">
    <?php if ($user['plan'] === 'GRATUIT'): ?>
    <a href="/reussiteplus/tarifs.php" class="sidebar-upgrade">
      <div class="sidebar-upgrade-title">
        <i class="bi bi-star-fill" style="font-size:11px"></i> Passer à Premium
      </div>
      <div class="sidebar-upgrade-sub">Accès illimité dès 10 000 CDF/mois</div>
    </a>
    <?php endif; ?>
    <a href="/reussiteplus/deconnexion.php" class="nav-item" style="margin-top:4px">
      <div class="nav-icon"><i class="bi bi-box-arrow-right"></i></div>
      <span class="nav-label">Déconnexion</span>
    </a>
  </div>
</aside>

<!-- MAIN CONTENT -->
<div class="main-content">
  <!-- TOP BAR -->
  <header class="topbar">
    <button class="topbar-btn" id="menuToggle" onclick="toggleSidebar()"
            style="display:none;border:none;background:transparent;color:var(--gris-700)" title="Menu">
      <i class="bi bi-list" style="font-size:22px"></i>
    </button>
    <h1 class="topbar-title"><?= e($pageTitle) ?></h1>
    <form class="search-bar" action="/reussiteplus/recherche.php" method="GET">
      <i class="bi bi-search"></i>
      <input type="search" name="q" placeholder="Rechercher archives, questions…"
             value="<?= e($_GET['q'] ?? '') ?>">
    </form>
    <a href="/reussiteplus/abonnement.php" class="topbar-btn" title="Mon abonnement — Plan <?= e($plan) ?>">
      <i class="bi bi-credit-card"></i>
    </a>
    <a href="/reussiteplus/notifications.php" class="topbar-btn" title="Notifications">
      <i class="bi bi-bell"></i>
      <?php if ($notifs > 0): ?><span class="notif-dot"></span><?php endif; ?>
    </a>
  </header>

  <!-- PAGE CONTENT -->
  <main class="page-content">
    <?= show_flash() ?>
