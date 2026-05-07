<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$user = require_login();
$isPremium = in_array($user['plan'] ?? 'GRATUIT', ['PREMIUM', 'ECOLE']);
$prenom    = e($user['prenom'] ?? '');
$initials  = strtoupper(substr($user['prenom'] ?? 'U', 0, 1));
?>
<!DOCTYPE html>
<html lang="fr" id="iaHtmlRoot">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Coach IA — RÉUSSITE+</title>
<link rel="icon" type="image/svg+xml" href="/reussiteplus/assets/img/favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/reussiteplus/assets/css/ia-pro.css?v=<?= filemtime(__DIR__.'/assets/css/ia-pro.css') ?>">
</head>
<body>

<script>
  window.iaUserPrenom  = <?= json_encode($user['prenom'] ?? '') ?>;
  window.iaIsPremium   = <?= $isPremium ? 'true' : 'false' ?>;
  window.iaUserInitials = <?= json_encode($initials) ?>;
  // Apply saved theme before render to prevent flash
  (function(){ const t = localStorage.getItem('ia_theme')||'dark'; document.documentElement.setAttribute('data-ia-theme', t); })();
</script>

<div class="ia-pro-root">

  <!-- ═══ SIDEBAR ═══════════════════════════════════════════ -->
  <aside class="ia-sidebar" id="iaSidebar">

    <!-- Brand + New chat -->
    <div class="ia-sidebar-top">
      <div class="ia-sidebar-brand">
        <div class="ia-brand-logo">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M8 15s1.5 2 4 2 4-2 4-2"/><path d="M9 9h.01"/><path d="M15 9h.01"/></svg>
        </div>
        <div class="ia-brand-name">RÉUSSITE<span>+</span></div>
      </div>
      <button class="ia-new-chat" onclick="IAPro.newChat()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nouvelle conversation
      </button>
    </div>

    <!-- Search -->
    <div class="ia-search-wrap">
      <div class="ia-search-box">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="flex-shrink:0;opacity:.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" id="iaSearchInput" class="ia-search-input" placeholder="Rechercher…" autocomplete="off">
      </div>
    </div>

    <!-- Conversations list -->
    <div class="ia-conv-list" id="iaConvList"></div>

    <!-- Bottom actions -->
    <div class="ia-sidebar-bottom">
      <button class="ia-sidebar-btn" onclick="IAPro.openModal('iaPromptsModal')">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        Bibliothèque de prompts
      </button>
      <button class="ia-sidebar-btn" onclick="IAPro.openModal('iaSettingsModal')">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
        Paramètres
      </button>
      <a href="/reussiteplus/dashboard.php" class="ia-sidebar-btn" style="text-decoration:none">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
        Tableau de bord
      </a>
      <!-- User info -->
      <div class="ia-user-info" style="margin-top:4px;border-top:1px solid var(--ia-border);padding-top:10px">
        <div class="ia-user-av"><?= $initials ?></div>
        <div style="flex:1;min-width:0">
          <div class="ia-user-name" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= $prenom ?></div>
          <div style="font-size:10px;color:var(--ia-text-3)"><?= e($user['plan'] ?? 'GRATUIT') ?></div>
        </div>
        <?php if ($isPremium): ?>
        <div class="ia-user-plan">Premium</div>
        <?php else: ?>
        <a href="/reussiteplus/tarifs.php" style="font-size:10px;color:var(--ia-gold);background:var(--ia-gold-sub);padding:2px 8px;border-radius:20px;text-decoration:none;flex-shrink:0;white-space:nowrap">Upgrader</a>
        <?php endif; ?>
      </div>
    </div>
  </aside>

  <!-- Mobile sidebar overlay -->
  <div class="ia-mobile-overlay" onclick="IAPro.toggleSidebar()"></div>

  <!-- ═══ MAIN ═══════════════════════════════════════════════ -->
  <main class="ia-main">

    <!-- Top bar -->
    <div class="ia-topbar">
      <button class="ia-topbar-toggle" onclick="IAPro.toggleSidebar()" title="Ctrl+B">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <span class="ia-topbar-title" id="iaConvTitle">Coach IA</span>

      <div class="ia-model-badge">
        <span class="ia-model-dot"></span>
        gemini-2.5-flash
      </div>
      <?php if ($isPremium): ?>
      <div class="ia-premium-badge">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
        Premium
      </div>
      <?php endif; ?>

      <div class="ia-topbar-actions">
        <button class="ia-top-btn" id="iaThemeToggle" title="Changer de thème">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
        </button>
        <button class="ia-top-btn" title="Analyser & Plan de révision" onclick="IAPro.analyse()">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
        </button>
        <button class="ia-top-btn" title="Exporter TXT" onclick="IAPro.exportTxt()">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
        </button>
        <button class="ia-top-btn" title="Exporter PDF" onclick="IAPro.exportPdf()">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="13" x2="12" y2="19"/><polyline points="9 16 12 19 15 16"/></svg>
        </button>
        <button class="ia-top-btn" title="Effacer la conversation" onclick="IAPro.clearHistory()">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
        </button>
      </div>
    </div>

    <!-- Messages -->
    <div class="ia-messages" id="iaMsgsArea">
      <div class="ia-messages-inner">

        <!-- Welcome screen -->
        <div class="ia-welcome-screen" id="iaWelcome">
          <div class="ia-welcome-logo">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M8 15s1.5 2 4 2 4-2 4-2"/><path d="M9 9h.01"/><path d="M15 9h.01"/></svg>
          </div>
          <h1 class="ia-welcome-title">Bonjour<?= $prenom ? ', '.$prenom : '' ?></h1>
          <p class="ia-welcome-sub">Posez votre question sur l'EXETAT, vos cours ou exercices. Je réponds en m'appuyant sur 1 051 questions EXETAT réelles.</p>

          <div class="ia-prompt-grid">
            <div class="ia-prompt-card" onclick="IAPro.usePrompt(encodeURIComponent('Explique-moi la photosynthèse pour l\'EXETAT'))">
              <div class="ia-prompt-card-icon">🔬</div>
              <div class="ia-prompt-card-title">Biologie EXETAT</div>
              <div class="ia-prompt-card-sub">Photosynthèse, mitose, ADN…</div>
            </div>
            <div class="ia-prompt-card" onclick="IAPro.usePrompt(encodeURIComponent('Aide-moi à résoudre une équation du second degré'))">
              <div class="ia-prompt-card-icon">📐</div>
              <div class="ia-prompt-card-title">Mathématiques</div>
              <div class="ia-prompt-card-sub">Équations, limites, géométrie…</div>
            </div>
            <div class="ia-prompt-card" onclick="IAPro.usePrompt(encodeURIComponent('Génère mon plan de révision sur 7 jours pour l\'EXETAT'))">
              <div class="ia-prompt-card-icon">📅</div>
              <div class="ia-prompt-card-title">Plan de révision</div>
              <div class="ia-prompt-card-sub">Planning personnalisé 7 jours</div>
            </div>
            <div class="ia-prompt-card" onclick="IAPro.openModal('iaPromptsModal')">
              <div class="ia-prompt-card-icon">✨</div>
              <div class="ia-prompt-card-title">Plus de prompts</div>
              <div class="ia-prompt-card-sub">Bibliothèque complète par matière</div>
            </div>
          </div>
        </div>

        <!-- Dynamic messages -->
        <div id="iaMsgs"></div>

      </div>
    </div>

    <!-- ═══ INPUT ZONE ═══════════════════════════════════════ -->
    <div class="ia-input-zone">
      <div class="ia-input-inner">

        <!-- Attachments preview -->
        <div class="ia-attachments-row" id="iaAttachRow" style="display:none"></div>

        <!-- Input box -->
        <form id="iaChatForm">
          <div class="ia-input-box">
            <textarea
              id="iaChatInput"
              class="ia-textarea"
              placeholder="Posez votre question… (Entrée pour envoyer, Maj+Entrée pour sauter une ligne)"
              rows="1"
              autocomplete="off"
            ></textarea>
            <div class="ia-input-toolbar">
              <!-- File attach -->
              <label class="ia-input-btn" title="Joindre un fichier">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                <input type="file" id="iaFileInput" style="display:none" multiple accept=".pdf,.doc,.docx,.txt,.jpg,.png,.jpeg,.webp">
              </label>
              <!-- Image attach -->
              <label class="ia-input-btn" title="Joindre une image">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                <input type="file" id="iaImageInput" style="display:none" accept="image/*">
              </label>
              <!-- Prompts -->
              <button type="button" class="ia-input-btn" title="Bibliothèque de prompts" onclick="IAPro.openModal('iaPromptsModal')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
              </button>
              <!-- Tone select -->
              <select id="iaToneSelect" class="ia-tone-select" title="Ton de réponse">
                <option value="motivant">Motivant</option>
                <option value="strict">Strict</option>
                <option value="humoristique">Humoristique</option>
              </select>
              <div class="ia-spacer"></div>
              <!-- Analyse btn -->
              <button type="button" class="ia-input-btn" title="Générer un plan de révision" onclick="IAPro.analyse()" style="color:var(--ia-gold)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
              </button>
              <!-- Send -->
              <button type="submit" id="iaSendBtn" class="ia-send-btn" title="Envoyer (Entrée)">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
              </button>
            </div>
          </div>
        </form>

        <div class="ia-input-note">
          Ctrl+N — Nouvelle conv &nbsp;·&nbsp; Ctrl+/ — Focus &nbsp;·&nbsp; Ctrl+B — Sidebar &nbsp;·&nbsp; Ctrl+K — Recherche
        </div>
      </div>
    </div>

  </main><!-- /ia-main -->
</div><!-- /ia-pro-root -->

<!-- Toast stack -->
<div class="ia-toast-stack" id="iaToastStack"></div>

<!-- ═══ MODAL : Bibliothèque de prompts ═══════════════════════ -->
<div class="ia-overlay" id="iaPromptsModal" onclick="if(event.target===this)IAPro.closeModal('iaPromptsModal')">
  <div class="ia-modal-panel">
    <div class="ia-modal-hd">
      <div class="ia-modal-title">Bibliothèque de prompts</div>
      <button class="ia-modal-close" onclick="IAPro.closeModal('iaPromptsModal')">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="ia-prompt-cats" id="iaPromptCats"></div>
    <div class="ia-prompt-list" id="iaPromptItems"></div>
  </div>
</div>

<!-- ═══ MODAL : Paramètres ════════════════════════════════════ -->
<div class="ia-overlay" id="iaSettingsModal" onclick="if(event.target===this)IAPro.closeModal('iaSettingsModal')">
  <div class="ia-modal-panel">
    <div class="ia-modal-hd">
      <div class="ia-modal-title">Paramètres</div>
      <button class="ia-modal-close" onclick="IAPro.closeModal('iaSettingsModal')">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <!-- Theme -->
    <div class="ia-setting-row">
      <div class="ia-setting-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg></div>
      <div class="ia-setting-label">
        <div class="ia-setting-title">Thème clair</div>
        <div class="ia-setting-desc">Basculer entre mode sombre et clair</div>
      </div>
      <div class="ia-toggle" id="iaThemeToggle2" onclick="document.getElementById('iaThemeToggle').click();this.classList.toggle('on')">
        <div class="ia-toggle-thumb"></div>
      </div>
    </div>

    <!-- Model info -->
    <div class="ia-setting-row">
      <div class="ia-setting-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg></div>
      <div class="ia-setting-label">
        <div class="ia-setting-title">Modèle IA actif</div>
        <div class="ia-setting-desc">gemini-2.5-flash (primaire) · gpt-4o-mini (fallback)</div>
      </div>
      <span style="font-size:11px;color:#4ade80;background:rgba(74,222,128,.1);padding:3px 9px;border-radius:20px">Actif</span>
    </div>

    <!-- RAG info -->
    <div class="ia-setting-row">
      <div class="ia-setting-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
      <div class="ia-setting-label">
        <div class="ia-setting-title">Base de connaissances EXETAT</div>
        <div class="ia-setting-desc">1 051 questions réelles injectées dans chaque réponse</div>
      </div>
      <span style="font-size:11px;color:#60a5fa;background:rgba(96,165,250,.1);padding:3px 9px;border-radius:20px">1 051 Q</span>
    </div>

    <!-- Plan -->
    <div class="ia-setting-row">
      <div class="ia-setting-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="<?= $isPremium ? 'var(--ia-gold)' : 'none' ?>" stroke="<?= $isPremium ? 'var(--ia-gold)' : 'currentColor' ?>" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></div>
      <div class="ia-setting-label">
        <div class="ia-setting-title">Mon plan</div>
        <div class="ia-setting-desc"><?= e($user['plan'] ?? 'GRATUIT') ?> — <?= $isPremium ? 'Accès illimité au Coach IA' : 'Passez à Premium pour toutes les fonctionnalités' ?></div>
      </div>
      <?php if (!$isPremium): ?>
      <a href="/reussiteplus/tarifs.php" style="font-size:11px;color:var(--ia-gold);background:var(--ia-gold-sub);padding:3px 9px;border-radius:20px;text-decoration:none;white-space:nowrap">Upgrader</a>
      <?php else: ?>
      <span style="font-size:11px;color:var(--ia-gold);background:var(--ia-gold-sub);padding:3px 9px;border-radius:20px">Premium</span>
      <?php endif; ?>
    </div>

    <!-- Shortcuts -->
    <div style="margin-top:16px">
      <div style="font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--ia-text-3);margin-bottom:12px">Raccourcis clavier</div>
      <div class="ia-shortcut-list">
        <?php foreach ([['Ctrl','N','Nouvelle conversation'],['Ctrl','/','Focus sur l\'input'],['Ctrl','B','Afficher/masquer sidebar'],['Ctrl','K','Recherche dans l\'historique'],['Entrée','','Envoyer le message'],['Maj','Entrée','Saut de ligne'],['Échap','','Fermer les fenêtres']] as [$k1,$k2,$desc]): ?>
        <div class="ia-shortcut">
          <span><?= e($desc) ?></span>
          <div class="ia-kbd">
            <kbd><?= e($k1) ?></kbd>
            <?php if ($k2): ?><kbd><?= e($k2) ?></kbd><?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<script src="/reussiteplus/assets/js/ia-pdf.js?v=<?= filemtime(__DIR__.'/assets/js/ia-pdf.js') ?>"></script>
<script src="/reussiteplus/assets/js/ia-pro.js?v=<?= filemtime(__DIR__.'/assets/js/ia-pro.js') ?>"></script>
</body>
</html>
