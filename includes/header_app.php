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
<link rel="icon" type="image/svg+xml" href="/reussiteplus/assets/img/favicon.svg">
<link rel="shortcut icon" href="/reussiteplus/assets/img/favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/reussiteplus/assets/css/app.css?v=<?= filemtime(__DIR__ . '/../assets/css/app.css') ?>">
<?= isset($extraHead) ? $extraHead : '' ?>
</head>
<body>
<div class="app-wrapper">

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-icon">
      <img src="/reussiteplus/assets/img/logo-icon.svg" alt="RÉUSSITE+" width="32" height="32" style="display:block">
    </div>
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
        <span class="user-info-plan"><i data-lucide="<?= $plan==='PREMIUM'?'crown':($plan==='BASIQUE'?'zap':'backpack') ?>"></i> <?= e($plans[$plan]['nom'] ?? $plan) ?></span>
      </div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-title">Principal</div>

    <a href="/reussiteplus/dashboard.php" class="nav-item <?= $pageActive === 'dashboard' ? 'active' : '' ?>">
      <div class="nav-icon"><i data-lucide="layout-dashboard"></i></div>
      <span class="nav-label">Tableau de bord</span>
    </a>
    <a href="/reussiteplus/archives.php" class="nav-item <?= $pageActive === 'archives' ? 'active' : '' ?>">
      <div class="nav-icon"><i data-lucide="folder-open"></i></div>
      <span class="nav-label">Archives</span>
    </a>
    <a href="/reussiteplus/examen.php" class="nav-item <?= $pageActive === 'examen' ? 'active' : '' ?>">
      <div class="nav-icon"><i data-lucide="pencil-line"></i></div>
      <span class="nav-label">Passer un examen</span>
    </a>
    <a href="/reussiteplus/questions.php" class="nav-item <?= $pageActive === 'questions' ? 'active' : '' ?>">
      <div class="nav-icon"><i data-lucide="brain"></i></div>
      <span class="nav-label">Banque de questions</span>
    </a>

    <div class="nav-section-title" style="margin-top:12px">Progression</div>
    <a href="/reussiteplus/progression.php" class="nav-item <?= $pageActive === 'progression' ? 'active' : '' ?>">
      <div class="nav-icon"><i data-lucide="trending-up"></i></div>
      <span class="nav-label">Ma progression</span>
    </a>
    <a href="/reussiteplus/notifications.php" class="nav-item <?= $pageActive === 'notifications' ? 'active' : '' ?>">
      <div class="nav-icon"><i data-lucide="bell"></i></div>
      <span class="nav-label">Notifications</span>
      <?php if ($notifs > 0): ?>
        <span class="nav-badge"><?= $notifs ?></span>
      <?php endif; ?>
    </a>

    <?php if (($user['plan'] ?? '') === 'ECOLE'): ?>
    <div class="nav-section-title" style="margin-top:12px">Mon École</div>
    <a href="/reussiteplus/ecole.php" class="nav-item <?= $pageActive === 'ecole' ? 'active' : '' ?>">
      <div class="nav-icon"><i data-lucide="layout-dashboard"></i></div>
      <span class="nav-label">Tableau de bord</span>
    </a>
    <a href="/reussiteplus/ecole_classes.php" class="nav-item <?= $pageActive === 'ecole_classes' ? 'active' : '' ?>">
      <div class="nav-icon"><i data-lucide="layout-list"></i></div>
      <span class="nav-label">Classes</span>
    </a>
    <a href="/reussiteplus/ecole_enseignants.php" class="nav-item <?= $pageActive === 'ecole_enseignants' ? 'active' : '' ?>">
      <div class="nav-icon"><i data-lucide="user-check"></i></div>
      <span class="nav-label">Enseignants</span>
    </a>
    <a href="/reussiteplus/ecole_eleves.php" class="nav-item <?= $pageActive === 'ecole_eleves' ? 'active' : '' ?>">
      <div class="nav-icon"><i data-lucide="users"></i></div>
      <span class="nav-label">Élèves</span>
    </a>
    <a href="/reussiteplus/ecole_emploi_temps.php" class="nav-item <?= $pageActive === 'ecole_emploi_temps' ? 'active' : '' ?>">
      <div class="nav-icon"><i data-lucide="calendar-days"></i></div>
      <span class="nav-label">Emploi du temps</span>
    </a>
    <a href="/reussiteplus/ecole_bibliotheque.php" class="nav-item <?= $pageActive === 'ecole_bibliotheque' ? 'active' : '' ?>">
      <div class="nav-icon"><i data-lucide="book-open"></i></div>
      <span class="nav-label">Bibliothèque</span>
    </a>
    <a href="/reussiteplus/ecole_devoirs.php" class="nav-item <?= $pageActive === 'ecole_devoirs' ? 'active' : '' ?>">
      <div class="nav-icon"><i data-lucide="file-text"></i></div>
      <span class="nav-label">Devoirs</span>
    </a>
    <a href="/reussiteplus/ecole_absences.php" class="nav-item <?= $pageActive === 'ecole_absences' ? 'active' : '' ?>">
      <div class="nav-icon"><i data-lucide="user-x"></i></div>
      <span class="nav-label">Absences</span>
    </a>
    <a href="/reussiteplus/ecole_bulletin.php" class="nav-item <?= $pageActive === 'ecole_bulletin' ? 'active' : '' ?>">
      <div class="nav-icon"><i data-lucide="award"></i></div>
      <span class="nav-label">Bulletins</span>
    </a>
    <a href="/reussiteplus/ecole_ia.php" class="nav-item <?= $pageActive === 'ecole_ia' ? 'active' : '' ?>">
      <div class="nav-icon"><i data-lucide="sparkles"></i></div>
      <span class="nav-label">IA Pédagogique</span>
    </a>
    <?php endif; ?>

    <?php if (is_admin()): ?>
    <div class="nav-section-title" style="margin-top:12px">Administration</div>
    <a href="/reussiteplus/admin/index.php" class="nav-item <?= $pageActive === 'admin' ? 'active' : '' ?>">
      <div class="nav-icon"><i data-lucide="settings"></i></div>
      <span class="nav-label">Admin</span>
    </a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-bottom">
    <?php if ($user['plan'] === 'GRATUIT'): ?>
    <a href="/reussiteplus/tarifs.php" class="sidebar-upgrade">
      <div class="sidebar-upgrade-title"><i data-lucide="star" style="width:14px;height:14px;vertical-align:-2px"></i> Passer à Premium</div>
      <div class="sidebar-upgrade-sub">Accès illimité dès 10 000 CDF/mois</div>
    </a>
    <?php endif; ?>
    <a href="/reussiteplus/deconnexion.php" class="nav-item" style="margin-top:8px">
      <div class="nav-icon"><i data-lucide="log-out"></i></div>
      <span class="nav-label">Déconnexion</span>
    </a>
  </div>
</aside>
<!-- Overlay sidebar mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- MAIN CONTENT -->
<div class="main-content">
  <!-- TOP BAR -->
  <header class="topbar">
    <!-- Hamburger mobile -->
    <button class="menu-toggle" id="menuToggle" aria-label="Menu" aria-expanded="false">
      <span class="menu-toggle-icon">
        <span></span><span></span><span></span>
      </span>
    </button>

    <h1 class="topbar-title"><?= e($pageTitle) ?></h1>
    <form class="search-bar" action="/reussiteplus/recherche.php" method="GET">
      <i data-lucide="search" style="width:15px;height:15px;flex-shrink:0;opacity:.5"></i>
      <input type="search" name="q" placeholder="Rechercher archives, questions..." value="<?= e($_GET['q'] ?? '') ?>">
    </form>
    <!-- Loupe mobile (remplace la barre de recherche) -->
    <a href="/reussiteplus/recherche.php" class="topbar-btn topbar-search-btn" title="Rechercher"><i data-lucide="search"></i></a>

    <a href="/reussiteplus/abonnement.php" class="topbar-btn" title="Mon abonnement">
      <i data-lucide="<?= $user['plan']==='PREMIUM'?'crown':($user['plan']==='BASIQUE'?'zap':'backpack') ?>"></i>
    </a>
    <a href="/reussiteplus/notifications.php" class="topbar-btn" title="Notifications">
      <i data-lucide="bell"></i>
      <?php if ($notifs > 0): ?><span class="notif-dot"></span><?php endif; ?>
    </a>
    <!-- Bouton dark mode -->
    <button id="themeToggle" class="topbar-btn" title="Changer le thème" onclick="toggleTheme()"><i data-lucide="moon"></i></button>
  </header>

  <!-- PAGE CONTENT -->
  <main class="page-content">
    <?= show_flash() ?>
