/**
 * RÉUSSITE+ — app.js
 */

document.addEventListener('DOMContentLoaded', () => {

  // ─── Lucide icons ─────────────────────────────────────────
  if (typeof lucide !== 'undefined') lucide.createIcons();

  // ─── Flash messages auto-hide ─────────────────────────────
  document.querySelectorAll('.alert').forEach(el => {
    if (el.dataset.autohide === 'false') return;
    setTimeout(() => {
      el.style.transition = 'opacity .5s';
      el.style.opacity = '0';
      setTimeout(() => el.remove(), 500);
    }, 5000);
  });

  // ─── Mobile sidebar ───────────────────────────────────────
  const sidebar = document.querySelector('.sidebar');
  const menuBtn = document.getElementById('menuToggle');
  const overlay = document.getElementById('sidebarOverlay');

  function openSidebar() {
    sidebar?.classList.add('open');
    overlay?.classList.add('active');
    document.body.style.overflow = 'hidden';
    menuBtn?.setAttribute('aria-expanded', 'true');
  }
  function closeSidebar() {
    sidebar?.classList.remove('open');
    overlay?.classList.remove('active');
    document.body.style.overflow = '';
    menuBtn?.setAttribute('aria-expanded', 'false');
  }

  menuBtn?.addEventListener('click', () => {
    sidebar?.classList.contains('open') ? closeSidebar() : openSidebar();
  });
  overlay?.addEventListener('click', closeSidebar);

  document.querySelectorAll('.nav-item').forEach(link => {
    link.addEventListener('click', () => {
      if (window.innerWidth <= 768) closeSidebar();
    });
  });

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeSidebar();
  });

  // ─── Sidebar collapse desktop ─────────────────────────────
  const collapseBtn = document.getElementById('sidebarCollapseBtn');
  const COLLAPSE_KEY = 'rp_sidebar_collapsed';

  // Génère les tooltips depuis les labels
  document.querySelectorAll('.nav-item').forEach(item => {
    const label = item.querySelector('.nav-label');
    if (label && !item.dataset.tooltip) {
      item.dataset.tooltip = label.textContent.trim();
    }
  });

  function applySidebarCollapse(animate) {
    if (!sidebar || window.innerWidth <= 768) return;
    const isCollapsed = localStorage.getItem(COLLAPSE_KEY) === '1';
    if (!animate) sidebar.classList.add('no-transition');
    sidebar.classList.toggle('collapsed', isCollapsed);
    if (!animate) {
      sidebar.offsetHeight; // force reflow
      sidebar.classList.remove('no-transition');
    }
  }

  collapseBtn?.addEventListener('click', () => {
    if (window.innerWidth <= 768) return;
    const willCollapse = !sidebar.classList.contains('collapsed');
    localStorage.setItem(COLLAPSE_KEY, willCollapse ? '1' : '0');
    applySidebarCollapse(true);
  });

  applySidebarCollapse(false);
  window.addEventListener('resize', () => applySidebarCollapse(false));

  // ─── Dark mode ────────────────────────────────────────────
  function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    const btn = document.getElementById('themeToggle');
    if (btn) {
      btn.innerHTML = theme === 'dark'
        ? '<i data-lucide="sun"></i>'
        : '<i data-lucide="moon"></i>';
      if (typeof lucide !== 'undefined') lucide.createIcons({ nodes: [btn] });
    }
  }

  const savedTheme = localStorage.getItem('theme') || 'light';
  applyTheme(savedTheme);

  document.getElementById('themeToggle')?.addEventListener('click', () => {
    const current = document.documentElement.getAttribute('data-theme') || 'light';
    const next = current === 'dark' ? 'light' : 'dark';
    localStorage.setItem('theme', next);
    applyTheme(next);
  });

  // ─── Notification badge polling ───────────────────────────
  async function refreshNotifBadge() {
    try {
      const r = await fetch('/reussiteplus/api/notifications.php?count=1');
      const d = await r.json();
      const badge = document.querySelector('.notif-badge');
      if (badge) {
        badge.textContent = d.count > 0 ? d.count : '';
        badge.style.display = d.count > 0 ? 'inline-flex' : 'none';
      }
    } catch (_) {}
  }
  if (document.querySelector('.notif-badge')) {
    refreshNotifBadge();
    setInterval(refreshNotifBadge, 60_000);
  }

  // ─── Confirm actions dangereuses ──────────────────────────
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
      if (!confirm(el.dataset.confirm || 'Confirmer cette action ?')) {
        e.preventDefault();
      }
    });
  });

  // ─── Copy to clipboard ────────────────────────────────────
  window.copyText = (text, btn) => {
    navigator.clipboard?.writeText(text).then(() => {
      const orig = btn?.textContent;
      if (btn) { btn.textContent = '✓ Copié !'; setTimeout(() => btn.textContent = orig, 2000); }
    });
  };

  // ─── Password visibility toggle ───────────────────────────
  document.querySelectorAll('.pwd-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = document.querySelector(btn.dataset.target);
      if (!input) return;
      input.type = input.type === 'password' ? 'text' : 'password';
      btn.textContent = input.type === 'password' ? '👁' : '🙈';
    });
  });

  // ─── Auto-submit sur changement de select ─────────────────
  document.querySelectorAll('[data-autosubmit]').forEach(el => {
    el.addEventListener('change', () => el.closest('form')?.submit());
  });

});

// ─── CSRF token helper (global) ───────────────────────────
function getCsrfToken() {
  return document.querySelector('[name="csrf_token"]')?.value || '';
}

// ─── Coach IA ─────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function initCoachIA() {
  const fab      = document.getElementById('ia-fab');
  const modal    = document.getElementById('ia-modal');
  if (!modal) return;
  // Page qui gère son propre IA (ex: dashboard)
  if (modal.dataset.iaManaged) return;

  const closeBtn    = document.getElementById('ia-close');
  const chatBody    = document.getElementById('ia-chat-body');
  const chatForm    = document.getElementById('ia-chat-form');
  const chatInput   = document.getElementById('ia-chat-input');
  const sendBtn     = document.querySelector('#ia-chat-form .ia-send');
  const toneSelect  = document.getElementById('ia-tone');
  const clearBtn    = document.getElementById('ia-clear');
  const exportBtn   = document.getElementById('ia-export');
  const analyseBtn  = document.getElementById('ia-analyse');
  const suggestWrap = document.getElementById('ia-suggestions');
  const statsEl     = document.getElementById('ia-stats');

  let history = [];
  let loading  = false;
  const prenom   = (window.userPrenom || '').trim();
  const initials = prenom ? prenom.charAt(0).toUpperCase() : '?';
  const isInline = modal.classList.contains('ia-inline');

  const SUGGESTIONS = [
    "Comment réussir l'EXETAT ?",
    'Explique la photosynthèse',
    'Aide-moi en mathématiques',
    'Conseils de révision rapide',
  ];

  function esc(s) {
    return String(s).replace(/[&<>"']/g, c =>
      ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
  }

  function scrollBottom() {
    if (chatBody) chatBody.scrollTop = chatBody.scrollHeight;
  }

  function updateStats() {
    if (!statsEl) return;
    const n = Math.floor(history.length / 2);
    statsEl.textContent = n > 0 ? `${n} échange${n > 1 ? 's' : ''} — session en cours` : '';
  }

  // ── Suggestions ──────────────────────────────────────────
  function renderSuggestions() {
    if (!suggestWrap) return;
    suggestWrap.style.display = '';
    suggestWrap.innerHTML = '';
    SUGGESTIONS.forEach(s => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'ia-suggestion';
      btn.textContent = s;
      btn.addEventListener('click', () => {
        chatInput.value = s;
        chatInput.focus();
        suggestWrap.style.display = 'none';
      });
      suggestWrap.appendChild(btn);
    });
  }

  // ── Message de bienvenue ──────────────────────────────────
  function showWelcome() {
    chatBody.innerHTML = `
      <div class="ia-welcome">
        <div class="ia-welcome-icon">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 15s1.5 2 4 2 4-2 4-2"/><path d="M9 9h.01"/><path d="M15 9h.01"/></svg>
        </div>
        <h3>${prenom ? 'Bienvenue, ' + esc(prenom) : 'Coach IA RÉUSSITE+'}</h3>
        <p>Posez votre question sur l'EXETAT, vos cours ou exercices.<br>Je réponds en m'appuyant sur le programme scolaire RDC.</p>
      </div>`;
  }

  // ── Bulle image Wikipedia ─────────────────────────────────
  function appendImageMsg(apiUrl, caption) {
    const AV = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 15s1.5 2 4 2 4-2 4-2"/><path d="M9 9h.01"/><path d="M15 9h.01"/></svg>';
    const wrap = document.createElement('div'); wrap.className = 'ia-msg ia-msg-bot';
    const avatar = document.createElement('div'); avatar.className = 'ia-msg-avatar'; avatar.innerHTML = AV;
    const bubble = document.createElement('div'); bubble.className = 'ia-msg-bubble ia-msg-image-bubble';
    const cap = document.createElement('p'); cap.className = 'ia-img-caption';
    cap.textContent = caption.replace(/\*\*/g, '');
    const loader = document.createElement('div'); loader.className = 'ia-img-loader';
    loader.innerHTML = '<div class="ia-typing"><span></span><span></span><span></span></div><span>Recherche d\'illustration…</span>';
    bubble.appendChild(cap); bubble.appendChild(loader);
    wrap.appendChild(avatar); wrap.appendChild(bubble);
    chatBody.appendChild(wrap); scrollBottom();

    fetch(apiUrl).then(r => r.json()).then(data => {
      loader.remove();
      if (!data.success || !data.url) {
        bubble.innerHTML += '<p style="color:#C9342A;font-size:12px">⚠ Aucune illustration trouvée.</p>'; return;
      }
      const img = document.createElement('img');
      img.className = 'ia-gen-img'; img.alt = data.title; img.src = data.url;
      bubble.appendChild(img);
      if (data.desc) {
        const d = document.createElement('p');
        d.style.cssText = 'font-size:11.5px;color:#6B7280;margin:5px 0 2px;line-height:1.5';
        d.textContent = data.desc + '…'; bubble.appendChild(d);
      }
      const footer = document.createElement('div');
      footer.style.cssText = 'display:flex;align-items:center;justify-content:space-between;margin-top:4px';
      footer.innerHTML = '<span style="font-size:10px;color:#9CA3AF;font-style:italic">Source : Wikipedia (libre de droits)</span>'
        + `<a href="${data.wiki}" target="_blank" class="ia-img-dl">Consulter l'article</a>`;
      bubble.appendChild(footer); scrollBottom();
    }).catch(() => { loader.innerHTML = '<span style="color:#C9342A;font-size:12px">⚠ Erreur réseau.</span>'; });
  }

  // ── Ajouter une bulle ─────────────────────────────────────
  function appendMsg(role, text) {
    const wrap = document.createElement('div');
    wrap.className = `ia-msg ia-msg-${role}`;

    const avatar = document.createElement('div');
    avatar.className = 'ia-msg-avatar';
    if (role === 'user') {
      avatar.textContent = initials;
      avatar.style.background = 'linear-gradient(135deg, var(--gold), var(--gold-dark))';
    } else {
      avatar.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 15s1.5 2 4 2 4-2 4-2"/><path d="M9 9h.01"/><path d="M15 9h.01"/></svg>';
    }

    const bubble = document.createElement('div');
    bubble.className = 'ia-msg-bubble';
    bubble.textContent = text;

    wrap.appendChild(avatar);
    wrap.appendChild(bubble);
    chatBody.appendChild(wrap);
    scrollBottom();
    return bubble;
  }

  // ── Indicateur de frappe ──────────────────────────────────
  function showTyping() {
    const wrap = document.createElement('div');
    wrap.className = 'ia-msg ia-msg-bot';
    const avatar = document.createElement('div');
    avatar.className = 'ia-msg-avatar';
    avatar.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 15s1.5 2 4 2 4-2 4-2"/><path d="M9 9h.01"/><path d="M15 9h.01"/></svg>';
    const dots = document.createElement('div');
    dots.className = 'ia-typing';
    dots.innerHTML = '<span></span><span></span><span></span>';
    wrap.appendChild(avatar);
    wrap.appendChild(dots);
    chatBody.appendChild(wrap);
    scrollBottom();
    return wrap;
  }

  // ── Effet machine à écrire ────────────────────────────────
  function typeText(bubble, text, done) {
    let i = 0;
    bubble.textContent = '';
    function tick() {
      if (i >= text.length) { if (done) done(); return; }
      bubble.textContent += text[i++];
      scrollBottom();
      setTimeout(tick, 10);
    }
    tick();
  }

  function isExerciceMsg(msg) {
    return /exercice|calcul|résoudre|démontrer|prouver|calculez?|trouvez?|résolvez?/i.test(msg);
  }

  function setLoading(state) {
    loading = state;
    if (sendBtn)   sendBtn.disabled   = state;
    if (analyseBtn) analyseBtn.disabled = state;
  }

  // ── Envoi d'un message ────────────────────────────────────
  async function sendMessage(msg) {
    if (loading || !msg.trim()) return;
    setLoading(true);
    if (suggestWrap) suggestWrap.style.display = 'none';
    chatBody.querySelector('.ia-welcome')?.remove();

    appendMsg('user', msg);
    const typingEl = showTyping();

    try {
      const res = await fetch('/reussiteplus/api/ia_chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          message: msg,
          history,
          tone: toneSelect?.value || 'motivant',
          exercice: isExerciceMsg(msg) ? 1 : 0,
        }),
      });
      const data = await res.json();
      typingEl.remove();

      if (data.type === 'image' || data.type === 'image_search') {
        appendImageMsg(data.image_url, data.reply || '');
        history.push({ role: 'user', content: msg });
        history.push({ role: 'assistant', content: data.reply || '' });
        setLoading(false);
        updateStats();
      } else {
        const reply = data.reply || "Désolé, je n'ai pas pu répondre.";
        const bubble = appendMsg('bot', '');
        typeText(bubble, reply, () => setLoading(false));
        history.push({ role: 'user', content: msg });
        history.push({ role: 'assistant', content: reply });
        updateStats();
      }
    } catch (_) {
      typingEl.remove();
      appendMsg('bot', 'Erreur de connexion. Réessaie dans quelques instants.');
      setLoading(false);
    }
  }

  // ── Soumission du formulaire ──────────────────────────────
  chatForm?.addEventListener('submit', e => {
    e.preventDefault();
    const msg = chatInput.value.trim();
    if (!msg || loading) return;
    chatInput.value = '';
    sendMessage(msg);
  });

  // ── Mode flottant vs mode inline ──────────────────────────
  if (!isInline) {
    fab?.addEventListener('click', e => {
      e.stopPropagation();
      const opening = !modal.classList.contains('open');
      modal.classList.toggle('open');
      if (opening) {
        chatInput?.focus();
        if (!chatBody.querySelector('.ia-msg, .ia-welcome')) {
          showWelcome();
          renderSuggestions();
        }
      }
    });
    closeBtn?.addEventListener('click', () => modal.classList.remove('open'));
    document.addEventListener('click', e => {
      if (!modal.contains(e.target) && !fab?.contains(e.target)) {
        modal.classList.remove('open');
      }
    });
  } else {
    if (fab) fab.style.display = 'none';
    showWelcome();
    renderSuggestions();
    chatInput?.focus();
  }

  // ── Effacer l'historique ──────────────────────────────────
  clearBtn?.addEventListener('click', async () => {
    if (!confirm("Effacer tout l'historique de conversation ?")) return;
    history = [];
    showWelcome();
    updateStats();
    renderSuggestions();
    await fetch('/reussiteplus/api/ia_chat.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'clear_history' }),
    }).catch(() => {});
  });

  // ── Export PDF ────────────────────────────────────────────
  exportBtn?.addEventListener('click', () => {
    if (!history.length) return;
    const now      = new Date();
    const date     = now.toLocaleDateString('fr-FR', {weekday:'long', day:'2-digit', month:'long', year:'numeric'});
    const heure    = now.toLocaleTimeString('fr-FR', {hour:'2-digit', minute:'2-digit'});
    const nom      = (window.userPrenom || 'Élève').replace(/[<>&"]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;'}[c]));
    const nbEch    = Math.floor(history.length / 2);
    function esc(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
    function mdHtml(s){ return esc(s).replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>').replace(/^## (.+)$/gm,'<div class="md-h2">$1</div>').replace(/^### (.+)$/gm,'<div class="md-h3">$1</div>').replace(/^[-•] (.+)$/gm,'<li>$1</li>').replace(/(<li>[\s\S]*?<\/li>\n?)+/g,'<ul>$&</ul>').replace(/\n\n/g,'</p><p>').replace(/\n/g,'<br>'); }
    const rows = history.map((msg, idx) => {
      const isUser = msg.role === 'user';
      const num    = isUser ? `Question ${Math.ceil((idx+1)/2)}` : '';
      return `<div class="msg ${isUser?'user':'ia'}"><div class="msg-header"><span class="role-tag ${isUser?'user':'ia'}">${isUser ? nom : 'Coach IA'}</span>${num ? `<span class="msg-num">${num}</span>` : ''}</div><div class="msg-body"><p>${mdHtml(msg.content)}</p></div></div>`;
    }).join('');
    const html = `<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Rapport Coach IA — ${nom} — RÉUSSITE+</title><style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:Arial,Helvetica,sans-serif;color:#1a1a2e;font-size:11.5px;line-height:1.65}.header{background:#007A5E;color:#fff;padding:22px 32px 18px}.header-row{display:flex;justify-content:space-between;align-items:flex-start}.brand{font-size:20px;font-weight:900;letter-spacing:.5px}.brand span{color:#C9972A}.brand-sub{font-size:9.5px;opacity:.75;margin-top:2px}.doc-date{text-align:right;font-size:9.5px;opacity:.8;line-height:1.8}.doc-date strong{font-size:11px;display:block;opacity:1}.doc-title{margin-top:14px;padding-top:12px;border-top:1px solid rgba(255,255,255,.25);font-size:13px;font-weight:700}.meta-bar{background:#F0F7F4;border-bottom:2px solid #007A5E;padding:8px 32px;display:flex;gap:28px;font-size:10px;color:#4A5568}.meta-bar strong{color:#007A5E;font-weight:700}.content{padding:24px 32px}.section-title{font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#9CA3AF;margin-bottom:14px;padding-bottom:6px;border-bottom:1px solid #E5E7EB}.msg{margin-bottom:14px;page-break-inside:avoid}.msg-header{display:flex;align-items:center;gap:8px;margin-bottom:5px}.role-tag{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;padding:2px 9px;border-radius:2px}.role-tag.user{background:#E8F5F1;color:#005A45}.role-tag.ia{background:#FEF3C7;color:#92400E}.msg-num{font-size:9px;color:#9CA3AF}.msg-body{padding:9px 13px;font-size:11.5px;line-height:1.65}.msg.user .msg-body{background:#F0F7F4;border-left:3px solid #007A5E}.msg.ia .msg-body{background:#FAFAFA;border:1px solid #F0F0F0;border-left:3px solid #C9972A}.msg-body p{margin-bottom:6px}.msg-body p:last-child{margin-bottom:0}.msg-body ul{margin:4px 0 4px 18px;padding:0}.msg-body li{margin:2px 0}.md-h2{font-weight:700;font-size:12px;color:#007A5E;margin:8px 0 4px;padding-bottom:2px;border-bottom:1px solid #E5E7EB}.md-h3{font-weight:600;font-size:11.5px;color:#1a1a2e;margin:6px 0 3px}.footer{margin-top:32px;padding:12px 32px;border-top:1px solid #E5E7EB;display:flex;justify-content:space-between;font-size:9.5px;color:#9CA3AF}@media print{@page{size:A4;margin:1.2cm}.header,.meta-bar,.msg-body{-webkit-print-color-adjust:exact;print-color-adjust:exact}}</style></head><body><div class="header"><div class="header-row"><div><div class="brand">RÉUSSITE<span>+</span></div><div class="brand-sub">Plateforme éducative — République Démocratique du Congo</div></div><div class="doc-date"><strong>${date}</strong>${heure}</div></div><div class="doc-title">Compte-rendu de session — Coach IA</div></div><div class="meta-bar"><span><strong>Participant :</strong> ${nom}</span><span><strong>Échanges :</strong> ${nbEch}</span><span><strong>Document :</strong> Usage pédagogique — confidentiel</span></div><div class="content"><div class="section-title">Transcription de la session</div>${rows}</div><div class="footer"><span>RÉUSSITE+ — Plateforme EdTech RDC</span><span>Généré automatiquement par Coach IA</span></div></body></html>`;
    const win = window.open('', '_blank');
    win.document.write(html);
    win.document.close();
    win.focus();
    setTimeout(() => win.print(), 500);
  });

  // ── Analyse & plan de révision ────────────────────────────
  analyseBtn?.addEventListener('click', async () => {
    if (history.length < 2) {
      alert("Commence d'abord une conversation pour que je puisse l'analyser !");
      return;
    }
    const origHTML = analyseBtn.innerHTML;
    analyseBtn.disabled = true;
    analyseBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg> Analyse en cours…';
    const typingEl = showTyping();
    try {
      const res = await fetch('/reussiteplus/api/ia_chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'analyse', history }),
      });
      const data = await res.json();
      typingEl.remove();
      const bubble = appendMsg('bot', '');
      typeText(bubble, data.reply || 'Analyse indisponible pour le moment.', () => {
        analyseBtn.disabled = false;
        analyseBtn.innerHTML = origHTML;
      });
    } catch (_) {
      typingEl.remove();
      appendMsg('bot', "Impossible de générer l'analyse pour le moment.");
      analyseBtn.disabled = false;
      analyseBtn.innerHTML = origHTML;
    }
  });
});
