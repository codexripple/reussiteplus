<?php
// Template : En-tête de page (avec sidebar)
if (!isset($pageTitle))  $pageTitle  = 'Dashboard';
if (!isset($pageActive)) $pageActive = 'dashboard';
$user   = require_login();
$stats  = get_user_stats($user['id']);
$notifs = (int)($stats['notifs_non_lues'] ?? 0);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> — RÉUSSITE+</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800;900&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
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
    <div class="logo-icon">
      <svg viewBox="0 0 24 24" fill="white"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
    </div>
    <div>
      <div class="logo-text">RÉUSSITE<span>+</span></div>
      <div class="logo-sub">Plateforme EdTech RDC</div>
    </div>
  </div>

  <div class="sidebar-user">
    <div class="user-avatar"><?= strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1)) ?></div>
    <div style="flex:1;min-width:0">
      <div class="user-info-name truncate"><?= e($user['prenom'] . ' ' . $user['nom']) ?></div>
      <?php $plan = $user['plan']; $plans = PLANS; ?>
      <div><span class="user-info-plan"><?= e($plans[$plan]['nom'] ?? $plan) ?></span></div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-title">Principal</div>

    <a href="/reussiteplus/dashboard.php" class="nav-item <?= $pageActive === 'dashboard' ? 'active' : '' ?>">
      <div class="nav-icon">
        <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      </div>
      <span class="nav-label">Tableau de bord</span>
    </a>

    <a href="/reussiteplus/archives.php" class="nav-item <?= $pageActive === 'archives' ? 'active' : '' ?>">
      <div class="nav-icon">
        <svg viewBox="0 0 24 24"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg>
      </div>
      <span class="nav-label">Archives</span>
    </a>

    <a href="/reussiteplus/examen.php" class="nav-item <?= $pageActive === 'examen' ? 'active' : '' ?>">
      <div class="nav-icon">
        <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
      </div>
      <span class="nav-label">Passer un examen</span>
    </a>

    <a href="/reussiteplus/questions.php" class="nav-item <?= $pageActive === 'questions' ? 'active' : '' ?>">
      <div class="nav-icon">
        <svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg>
      </div>
      <span class="nav-label">Banque de questions</span>
    </a>

    <div class="nav-section-title" style="margin-top:12px">Suivi</div>

    <a href="/reussiteplus/progression.php" class="nav-item <?= $pageActive === 'progression' ? 'active' : '' ?>">
      <div class="nav-icon">
        <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      </div>
      <span class="nav-label">Ma progression</span>
    </a>

    <a href="/reussiteplus/notifications.php" class="nav-item <?= $pageActive === 'notifications' ? 'active' : '' ?>">
      <div class="nav-icon">
        <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
      </div>
      <span class="nav-label">Notifications</span>
      <?php if ($notifs > 0): ?><span class="nav-badge"><?= $notifs ?></span><?php endif; ?>
    </a>

    <?php if (is_admin()): ?>
    <div class="nav-section-title" style="margin-top:12px">Administration</div>
    <a href="/reussiteplus/admin/index.php" class="nav-item <?= $pageActive === 'admin' ? 'active' : '' ?>">
      <div class="nav-icon">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
      </div>
      <span class="nav-label">Administration</span>
    </a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-bottom">
    <?php if ($user['plan'] === 'GRATUIT'): ?>
    <a href="/reussiteplus/tarifs.php" class="sidebar-upgrade">
      <div class="sidebar-upgrade-title">
        <svg style="width:11px;height:11px;fill:currentColor;display:inline;margin-right:4px;vertical-align:middle" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        Passer à Premium
      </div>
      <div class="sidebar-upgrade-sub">Accès illimité dès 10 000 CDF/mois</div>
    </a>
    <?php endif; ?>
    <a href="/reussiteplus/deconnexion.php" class="nav-item" style="margin-top:4px">
      <div class="nav-icon">
        <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      </div>
      <span class="nav-label">Déconnexion</span>
    </a>
  </div>
</aside>

<!-- MAIN CONTENT -->
<div class="main-content">
  <!-- TOP BAR -->
  <header class="topbar">
    <!-- Mobile menu toggle -->
    <button class="topbar-btn" id="menuToggle" onclick="toggleSidebar()" style="display:none;border:none;background:transparent;color:var(--gris-700)" title="Menu">
      <svg viewBox="0 0 24 24" style="width:20px;height:20px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <h1 class="topbar-title"><?= e($pageTitle) ?></h1>
    <form class="search-bar" action="/reussiteplus/recherche.php" method="GET">
      <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="search" name="q" placeholder="Rechercher archives, questions..." value="<?= e($_GET['q'] ?? '') ?>">
    </form>
    <a href="/reussiteplus/abonnement.php" class="topbar-btn" title="Mon abonnement — Plan <?= e($plan) ?>">
      <svg viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
    </a>
    <a href="/reussiteplus/notifications.php" class="topbar-btn" title="Notifications">
      <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
      <?php if ($notifs > 0): ?><span class="notif-dot"></span><?php endif; ?>
    </a>
  </header>

  <!-- PAGE CONTENT -->
  <main class="page-content">
    <?= show_flash() ?>
