<?php
// Template : En-tête de page (avec sidebar)
// Usage: include 'includes/header_app.php'; après avoir défini $pageTitle et $pageActive
if (!isset($pageTitle)) $pageTitle = 'Dashboard';
if (!isset($pageActive)) $pageActive = 'dashboard';
$user  = require_login();
$stats = get_user_stats($user['id']);
$notifs = (int)($stats['notifs_non_lues'] ?? 0);
// Pour les admins : alertes spécifiques (paiements + messages)
$adminAlerts = 0;
if (is_admin()) {
    $adminAlerts  = (int)(dbRow("SELECT COUNT(*) as n FROM abonnements WHERE statut='EN_ATTENTE'") ?? ['n'=>0])['n'];
    $adminAlerts += (int)(dbRow("SELECT COUNT(*) as n FROM contact_messages WHERE statut='NOUVEAU'") ?? ['n'=>0])['n'];
}
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
    <button class="sidebar-collapse-btn" id="sidebarCollapseBtn" title="Réduire">
      <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 19l-7-7 7-7"/><path d="M21 19l-7-7 7-7"/></svg>
    </button>
  </div>

  <div class="sidebar-user">
    <div class="user-avatar" style="<?= is_admin() ? 'background:linear-gradient(135deg,#007A5E,#7C3AED)' : '' ?>"><?= strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1)) ?></div>
    <div>
      <div class="user-info-name"><?= e($user['prenom'] . ' ' . $user['nom']) ?></div>
      <div>
        <?php if (is_admin()): ?>
        <span class="user-info-plan" style="background:rgba(124,58,237,.2);color:#a78bfa">
          <i data-lucide="shield-check" style="width:10px;height:10px"></i>
          <?= $user['role'] === 'SUPER_ADMIN' ? 'Super Admin' : ($user['role'] === 'MODERATEUR' ? 'Modérateur' : 'Admin') ?>
        </span>
        <?php else: ?>
        <?php $plan = $user['plan']; $plans = PLANS; ?>
        <span class="user-info-plan"><i data-lucide="<?= $plan==='PREMIUM'?'crown':($plan==='BASIQUE'?'zap':'backpack') ?>"></i> <?= e($plans[$plan]['nom'] ?? $plan) ?></span>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <?php $isAdminPage = str_starts_with($pageActive ?? '', 'admin'); ?>
    <?php if (!$isAdminPage): ?>
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
    <a href="/reussiteplus/profil.php" class="nav-item <?= $pageActive === 'profil' ? 'active' : '' ?>">
      <div class="nav-icon"><i data-lucide="user-circle"></i></div>
      <span class="nav-label">Mon Profil</span>
    </a>
    <a href="/reussiteplus/mes_devoirs.php" class="nav-item <?= $pageActive === 'mes_devoirs' ? 'active' : '' ?>">
      <div class="nav-icon"><i data-lucide="clipboard"></i></div>
      <span class="nav-label">Mes Devoirs</span>
    </a>
    <a href="/reussiteplus/mes_cours.php" class="nav-item <?= $pageActive === 'mes_cours' ? 'active' : '' ?>">
      <div class="nav-icon"><i data-lucide="book-open"></i></div>
      <span class="nav-label">Mes Cours</span>
    </a>
    <a href="/reussiteplus/mes_exercices.php" class="nav-item <?= $pageActive === 'mes_exercices' ? 'active' : '' ?>">
      <div class="nav-icon"><i data-lucide="brain"></i></div>
      <span class="nav-label">Exercices</span>
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
      <div class="nav-icon"><i data-lucide="clipboard-list"></i></div>
      <span class="nav-label">Bulletins</span>
    </a>
    <a href="/reussiteplus/ecole_certificat.php" class="nav-item <?= $pageActive === 'ecole_certificat' ? 'active' : '' ?>">
      <div class="nav-icon"><i data-lucide="scroll"></i></div>
      <span class="nav-label">Certificats</span>
    </a>
    <a href="/reussiteplus/ecole_exercices.php" class="nav-item <?= $pageActive === 'ecole_exercices' ? 'active' : '' ?>">
      <div class="nav-icon"><i data-lucide="brain"></i></div>
      <span class="nav-label">Exercices</span>
    </a>
    <a href="/reussiteplus/ecole_questions.php" class="nav-item <?= $pageActive === 'ecole_questions' ? 'active' : '' ?>">
      <div class="nav-icon"><i data-lucide="help-circle"></i></div>
      <span class="nav-label">Questions</span>
    </a>
    <a href="/reussiteplus/ecole_messages.php" class="nav-item <?= $pageActive === 'ecole_messages' ? 'active' : '' ?>">
      <div class="nav-icon"><i data-lucide="message-square"></i></div>
      <span class="nav-label">Messages</span>
    </a>
    <a href="/reussiteplus/ecole_ia.php" class="nav-item <?= $pageActive === 'ecole_ia' ? 'active' : '' ?>">
      <div class="nav-icon"><i data-lucide="sparkles"></i></div>
      <span class="nav-label">IA Pédagogique</span>
    </a>
    <?php endif; ?>
    <?php endif; /* end !$isAdminPage */ ?>

    <?php if (is_admin()): ?>
    <?php $pendingPay = (int)(dbRow("SELECT COUNT(*) as n FROM abonnements WHERE statut='EN_ATTENTE'") ?? ['n'=>0])['n']; ?>
    <?php $pendingMsg = (int)(dbRow("SELECT COUNT(*) as n FROM contact_messages WHERE created_at >= DATE_SUB(NOW(),INTERVAL 48 HOUR)") ?? ['n'=>0])['n']; ?>
    <!-- ═══ ZONE ADMIN ═══ -->
    <div class="sidebar-adm-header-text" style="margin:12px -12px 0;padding:10px 12px 6px;background:linear-gradient(135deg,rgba(0,122,94,.12),rgba(124,58,237,.08));border-top:1px solid rgba(0,122,94,.2);border-bottom:1px solid rgba(124,58,237,.15)">
      <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px">
        <div style="width:6px;height:6px;background:#4ade80;border-radius:50%;animation:adm-blink 1.5s infinite"></div>
        <span style="font-size:9px;font-weight:800;color:#4ade80;text-transform:uppercase;letter-spacing:1.5px">Mode Administration</span>
      </div>
      <style>@keyframes adm-blink{0%,100%{opacity:1}50%{opacity:.3}}</style>
    </div>

    <div class="nav-section-title" style="margin-top:10px">Vue d'ensemble</div>
    <a href="/reussiteplus/admin/index.php" class="nav-item adm-nav <?= $pageActive === 'admin' ? 'active adm-active' : '' ?>">
      <div class="nav-icon adm-icon"><i data-lucide="layout-dashboard"></i></div>
      <span class="nav-label">Tableau de bord</span>
    </a>
    <a href="/reussiteplus/admin/notifications.php" class="nav-item adm-nav <?= $pageActive === 'admin_notifs' ? 'active adm-active' : '' ?>">
      <div class="nav-icon adm-icon"><i data-lucide="bell"></i></div>
      <span class="nav-label">Alertes</span>
      <?php if ($adminAlerts > 0): ?>
      <span style="background:#EF4444;color:white;font-size:9px;font-weight:800;padding:1px 6px;border-radius:10px;flex-shrink:0"><?= $adminAlerts ?></span>
      <?php endif; ?>
    </a>

    <div class="nav-section-title" style="margin-top:8px">Gestion</div>
    <a href="/reussiteplus/admin/users.php" class="nav-item adm-nav <?= $pageActive === 'admin_users' ? 'active adm-active' : '' ?>">
      <div class="nav-icon adm-icon"><i data-lucide="users"></i></div>
      <span class="nav-label">Utilisateurs</span>
    </a>
    <a href="/reussiteplus/admin/paiements.php" class="nav-item adm-nav <?= $pageActive === 'admin_paiements' ? 'active adm-active' : '' ?>">
      <div class="nav-icon adm-icon"><i data-lucide="credit-card"></i></div>
      <span class="nav-label">Paiements</span>
      <?php if ($pendingPay > 0): ?>
      <span style="background:#F59E0B;color:white;font-size:9px;font-weight:800;padding:1px 6px;border-radius:10px;flex-shrink:0"><?= $pendingPay ?></span>
      <?php endif; ?>
    </a>
    <a href="/reussiteplus/admin/messages.php" class="nav-item adm-nav <?= $pageActive === 'admin_messages' ? 'active adm-active' : '' ?>">
      <div class="nav-icon adm-icon"><i data-lucide="mail"></i></div>
      <span class="nav-label">Messages</span>
      <?php if ($pendingMsg > 0): ?>
      <span style="background:#EF4444;color:white;font-size:9px;font-weight:800;padding:1px 6px;border-radius:10px;flex-shrink:0"><?= $pendingMsg ?></span>
      <?php endif; ?>
    </a>

    <div class="nav-section-title" style="margin-top:8px">Contenu</div>
    <a href="/reussiteplus/admin/archives.php" class="nav-item adm-nav <?= $pageActive === 'admin_archives' ? 'active adm-active' : '' ?>">
      <div class="nav-icon adm-icon"><i data-lucide="folder-open"></i></div>
      <span class="nav-label">Archives</span>
    </a>
    <a href="/reussiteplus/questions.php" class="nav-item adm-nav <?= $pageActive === 'admin_questions' ? 'active adm-active' : '' ?>">
      <div class="nav-icon adm-icon"><i data-lucide="help-circle"></i></div>
      <span class="nav-label">Questions</span>
    </a>
    <a href="/reussiteplus/tarifs.php" target="_blank" class="nav-item adm-nav">
      <div class="nav-icon adm-icon"><i data-lucide="tag"></i></div>
      <span class="nav-label">Tarifs &amp; Plans</span>
      <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,.25)" stroke-width="2.5" style="flex-shrink:0"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
    </a>

    <div class="nav-section-title" style="margin-top:8px">Outils</div>
    <a href="/reussiteplus/admin/users.php?export=csv" class="nav-item adm-nav">
      <div class="nav-icon adm-icon"><i data-lucide="download"></i></div>
      <span class="nav-label">Export CSV</span>
    </a>
    <a href="/reussiteplus/admin/index.php#ai-panel" class="nav-item adm-nav">
      <div class="nav-icon" style="background:rgba(124,58,237,.2)"><i data-lucide="sparkles" style="width:16px;height:16px;stroke:#a78bfa"></i></div>
      <span class="nav-label" style="color:rgba(255,255,255,.7)">Analyse IA Groq</span>
    </a>

    <div style="margin:10px -12px 0;padding:10px 20px 8px;border-top:1px solid rgba(255,255,255,.06)">
      <a href="/reussiteplus/index.php" target="_blank" style="display:flex;align-items:center;gap:8px;font-size:11px;color:rgba(255,255,255,.3);text-decoration:none;font-weight:600;transition:.15s" onmouseover="this.style.color='rgba(255,255,255,.6)'" onmouseout="this.style.color='rgba(255,255,255,.3)'">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
        Voir le site public
      </a>
    </div>

    <style>
    .adm-nav:hover { background: rgba(0,122,94,.12) !important; }
    .adm-nav .adm-icon { background: rgba(255,255,255,.06) !important; }
    .adm-nav:hover .adm-icon { background: rgba(0,122,94,.2) !important; }
    .adm-nav:hover .nav-icon svg { stroke: #4ade80 !important; }
    .adm-active { background: rgba(0,122,94,.22) !important; }
    .adm-active::before { background: #4ade80 !important; }
    .adm-active .nav-icon svg { stroke: #4ade80 !important; }
    .adm-active .nav-label { color: #4ade80 !important; font-weight: 600 !important; }
    </style>
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
    <?php if (is_admin()): ?>
    <a href="/reussiteplus/admin/notifications.php" class="topbar-btn" title="Alertes admin">
      <i data-lucide="bell"></i>
      <?php if ($adminAlerts > 0): ?><span class="notif-dot"></span><?php endif; ?>
    </a>
    <?php else: ?>
    <a href="/reussiteplus/notifications.php" class="topbar-btn" title="Notifications">
      <i data-lucide="bell"></i>
      <?php if ($notifs > 0): ?><span class="notif-dot"></span><?php endif; ?>
    </a>
    <?php endif; ?>
    <!-- Bouton dark mode -->
    <button id="themeToggle" class="topbar-btn" title="Changer le thème" onclick="toggleTheme()"><i data-lucide="moon"></i></button>
  </header>

  <!-- PAGE CONTENT -->
  <main class="page-content">
    <?= show_flash() ?>
