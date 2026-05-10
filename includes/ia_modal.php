<?php
// Composant Coach IA — réutilisable (inline ou flottant)
// Passer $iaInline = true pour affichage en page dédiée
?>
<?php
$_iaUser = current_user();
$_iaHasAccess = $_iaUser && in_array($_iaUser['plan'] ?? 'GRATUIT', ['PREMIUM','ECOLE']);
?>
<?php if (empty($iaInline) && $_iaHasAccess): ?>
<button id="ia-fab" class="ia-fab" title="Coach IA">
  <span class="ia-fab-avatar">
    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 15s1.5 2 4 2 4-2 4-2"/><path d="M9 9h.01"/><path d="M15 9h.01"/></svg>
  </span>
</button>
<?php endif; ?>
<?php if (!$_iaHasAccess && empty($iaInline)) return; // Masquer toute la modale pour Gratuit/Basique ?>
<div id="ia-modal" class="ia-modal<?= !empty($iaInline) ? ' ia-inline' : '' ?>">
  <div class="ia-modal-card">
    <div class="ia-modal-header">
      <span class="ia-avatar-lg">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 15s1.5 2 4 2 4-2 4-2"/><path d="M9 9h.01"/><path d="M15 9h.01"/></svg>
      </span>
      <div class="ia-header-info">
        <span class="ia-header-title">Coach IA</span>
        <span class="ia-header-badge">Premium</span>
      </div>
      <select id="ia-tone" class="ia-tone">
        <option value="motivant">Motivant</option>
        <option value="strict">Strict</option>
        <option value="humoristique">Humoristique</option>
      </select>
      <div class="ia-header-actions">
        <button id="ia-export" class="ia-action" title="Exporter PDF">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="13" x2="12" y2="19"/><polyline points="9 16 12 19 15 16"/></svg>
        </button>
        <button id="ia-clear" class="ia-action ia-action-warn" title="Effacer">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
        </button>
        <button id="ia-close" class="ia-action ia-action-close" title="Fermer">&times;</button>
      </div>
    </div>
    <div id="ia-stats" class="ia-stats"></div>
    <div id="ia-chat-body" class="ia-chat-body"></div>
    <div class="ia-suggestions-wrap">
      <div id="ia-suggestions" class="ia-suggestions"></div>
      <button id="ia-analyse" type="button" class="ia-analyse">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
        Générer un plan de révision personnalisé
      </button>
    </div>
    <form id="ia-chat-form" class="ia-chat-form">
      <input id="ia-chat-input" type="text" placeholder="Posez votre question…" autocomplete="off" class="ia-chat-input" />
      <button type="submit" class="ia-send">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
      </button>
    </form>
  </div>
</div>
<script>window.userPrenom = <?= json_encode($user['prenom'] ?? '') ?>;</script>