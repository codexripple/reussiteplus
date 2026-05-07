/**
 * RÉUSSITE+ — Coach IA Pro
 * Full chatbot interface JS
 */

const IAPro = (() => {

  // ── State ─────────────────────────────────────────────────
  const S = {
    conversations: [],
    activeId:      null,
    loading:       false,
    theme:         localStorage.getItem('ia_theme') || 'dark',
    tone:          localStorage.getItem('ia_tone')  || 'motivant',
    attachments:   [],
    searchQuery:   '',
  };

  // DOM refs (filled in init)
  let D = {};

  // ── Storage ──────────────────────────────────────────────
  const store = {
    load() {
      try {
        const raw = localStorage.getItem('ia_conversations');
        S.conversations = raw ? JSON.parse(raw) : [];
        S.activeId = localStorage.getItem('ia_active') || null;
      } catch(_) { S.conversations = []; }
    },
    save() {
      try {
        localStorage.setItem('ia_conversations', JSON.stringify(S.conversations));
        if (S.activeId) localStorage.setItem('ia_active', S.activeId);
      } catch(_) {}
    },
    getActive() {
      return S.conversations.find(c => c.id === S.activeId) || null;
    },
    newConv() {
      const c = {
        id:        crypto.randomUUID?.() || Date.now().toString(36),
        title:     'Nouvelle conversation',
        createdAt: Date.now(),
        updatedAt: Date.now(),
        favorite:  false,
        messages:  [],
      };
      S.conversations.unshift(c);
      S.activeId = c.id;
      store.save();
      return c;
    },
    addMessage(convId, role, content, extra = {}) {
      const c = S.conversations.find(x => x.id === convId);
      if (!c) return null;
      const msg = { id: Date.now().toString(36) + Math.random().toString(36).slice(2), role, content, ts: Date.now(), ...extra };
      c.messages.push(msg);
      c.updatedAt = Date.now();
      // Auto-title from first user message
      if (role === 'user' && c.messages.filter(m => m.role === 'user').length === 1) {
        c.title = content.slice(0, 48) + (content.length > 48 ? '…' : '');
      }
      store.save();
      return msg;
    },
    deleteMessage(convId, msgId) {
      const c = S.conversations.find(x => x.id === convId);
      if (!c) return;
      c.messages = c.messages.filter(m => m.id !== msgId);
      store.save();
    },
    deleteConv(id) {
      S.conversations = S.conversations.filter(c => c.id !== id);
      if (S.activeId === id) S.activeId = S.conversations[0]?.id || null;
      store.save();
    },
    toggleFavorite(id) {
      const c = S.conversations.find(x => x.id === id);
      if (c) { c.favorite = !c.favorite; store.save(); }
    },
    renameConv(id, title) {
      const c = S.conversations.find(x => x.id === id);
      if (c) { c.title = title; store.save(); }
    },
  };

  // ── Helpers ───────────────────────────────────────────────
  function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }
  function mdToHtml(t) {
    if (!t) return '';
    t = esc(t);
    t = t.replace(/```([\w]*)\n?([\s\S]*?)```/g, '<pre><code>$2</code></pre>');
    t = t.replace(/`([^`]+)`/g, '<code>$1</code>');
    t = t.replace(/^### (.+)$/gm, '<div class="ia-md-h3">$1</div>');
    t = t.replace(/^## (.+)$/gm,  '<div class="ia-md-h2">$1</div>');
    t = t.replace(/^# (.+)$/gm,   '<div class="ia-md-h2">$1</div>');
    t = t.replace(/\*\*\*(.+?)\*\*\*/g, '<strong><em>$1</em></strong>');
    t = t.replace(/\*\*(.+?)\*\*/g,     '<strong>$1</strong>');
    t = t.replace(/\*(.+?)\*/g,         '<em>$1</em>');
    t = t.replace(/^[-•] (.+)$/gm,      '<li>$1</li>');
    t = t.replace(/^(\d+)\. (.+)$/gm,   '<li>$2</li>');
    t = t.replace(/(<li>[\s\S]*?<\/li>\n?)+/g, '<ul class="ia-md-ul">$&</ul>');
    t = t.replace(/\n\n+/g, '<br><br>');
    t = t.replace(/\n/g,    '<br>');
    return t;
  }
  function formatTime(ts) {
    return new Date(ts).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
  }
  function formatDate(ts) {
    const d = new Date(ts), now = new Date();
    const diffDays = Math.floor((now - d) / 86400000);
    if (diffDays === 0) return 'Aujourd\'hui';
    if (diffDays === 1) return 'Hier';
    if (diffDays < 7)  return d.toLocaleDateString('fr-FR', { weekday: 'long' });
    return d.toLocaleDateString('fr-FR', { day: '2-digit', month: 'long' });
  }
  function fileSize(bytes) {
    if (bytes < 1024) return bytes + ' o';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' Ko';
    return (bytes / 1048576).toFixed(1) + ' Mo';
  }
  function toast(msg, type = 'info', duration = 2500) {
    const el = document.createElement('div');
    el.className = `ia-toast ${type}`;
    const icon = type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ';
    el.innerHTML = `<span style="color:${type==='success'?'#4ade80':type==='error'?'#f87171':'#60a5fa'}">${icon}</span> ${esc(msg)}`;
    document.getElementById('iaToastStack').appendChild(el);
    setTimeout(() => { el.style.opacity = '0'; el.style.transform = 'translateX(16px)'; el.style.transition = '.3s'; setTimeout(() => el.remove(), 300); }, duration);
  }

  // ── Theme ─────────────────────────────────────────────────
  function applyTheme(theme) {
    S.theme = theme;
    document.documentElement.setAttribute('data-ia-theme', theme);
    localStorage.setItem('ia_theme', theme);
  }

  // ── Sidebar rendering ──────────────────────────────────────
  function renderSidebar() {
    const list = document.getElementById('iaConvList');
    const q = S.searchQuery.toLowerCase();
    const filtered = S.conversations.filter(c =>
      !q || c.title.toLowerCase().includes(q) ||
      c.messages.some(m => m.content.toLowerCase().includes(q))
    );

    // Group by date
    const groups = {};
    filtered.forEach(c => {
      const label = formatDate(c.updatedAt);
      if (!groups[label]) groups[label] = [];
      groups[label].push(c);
    });

    const favs = filtered.filter(c => c.favorite);
    let html = '';

    if (favs.length) {
      html += `<div class="ia-conv-group-title">Favoris</div>`;
      favs.forEach(c => { html += convItemHtml(c); });
    }

    Object.entries(groups).forEach(([label, convs]) => {
      html += `<div class="ia-conv-group-title">${esc(label)}</div>`;
      convs.forEach(c => { html += convItemHtml(c); });
    });

    if (!filtered.length) {
      html = `<div style="text-align:center;padding:32px 12px;color:var(--ia-text-3);font-size:13px">Aucune conversation</div>`;
    }
    list.innerHTML = html;
  }
  function convItemHtml(c) {
    const active = c.id === S.activeId ? ' active' : '';
    const fav    = c.favorite ? ' favorite' : '';
    return `<div class="ia-conv-item${active}${fav}" data-id="${esc(c.id)}" onclick="IAPro.selectConv('${esc(c.id)}')">
      <svg class="ia-conv-icon" width="14" height="14" viewBox="0 0 24 24" fill="${c.favorite ? 'var(--ia-gold)' : 'none'}" stroke="currentColor" stroke-width="2" stroke-linecap="round">
        ${c.favorite ? '<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>' : '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>'}
      </svg>
      <span class="ia-conv-title">${esc(c.title)}</span>
      <div class="ia-conv-actions">
        <button class="ia-conv-action" title="Favoris" onclick="event.stopPropagation();IAPro.toggleFav('${esc(c.id)}')">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="${c.favorite?'var(--ia-gold)':'none'}" stroke="${c.favorite?'var(--ia-gold)':'currentColor'}" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
        </button>
        <button class="ia-conv-action" title="Renommer" onclick="event.stopPropagation();IAPro.renameConv('${esc(c.id)}')">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        </button>
        <button class="ia-conv-action" title="Supprimer" onclick="event.stopPropagation();IAPro.deleteConv('${esc(c.id)}')">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
        </button>
      </div>
    </div>`;
  }

  // ── Chat rendering ─────────────────────────────────────────
  function renderChat(scrollToBottom = true) {
    const conv = store.getActive();
    const msgs = document.getElementById('iaMsgs');
    const welcome = document.getElementById('iaWelcome');
    const convTitle = document.getElementById('iaConvTitle');

    if (!conv || !conv.messages.length) {
      msgs.innerHTML = '';
      if (welcome) welcome.style.display = '';
      if (convTitle) convTitle.textContent = 'Coach IA';
      return;
    }
    if (welcome) welcome.style.display = 'none';
    if (convTitle) convTitle.textContent = conv.title;

    msgs.innerHTML = conv.messages.map(msg => renderMsg(msg)).join('');
    if (scrollToBottom) {
      requestAnimationFrame(() => {
        const area = document.getElementById('iaMsgsArea');
        if (area) area.scrollTop = area.scrollHeight;
      });
    }
  }
  function renderMsg(msg) {
    const isUser  = msg.role === 'user';
    const time    = formatTime(msg.ts);
    const content = isUser ? esc(msg.content) : mdToHtml(msg.content);
    const initials = (window.iaUserPrenom || 'U')[0].toUpperCase();

    const tools = isUser ? `
      <button class="ia-msg-tool" title="Modifier" onclick="IAPro.editMsg('${esc(msg.id)}')">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
      </button>
      <button class="ia-msg-tool" title="Supprimer" onclick="IAPro.deleteMsgUI('${esc(msg.id)}')">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
      </button>` : `
      <button class="ia-msg-tool" id="copy-${esc(msg.id)}" title="Copier" onclick="IAPro.copyMsg('${esc(msg.id)}','${encodeURIComponent(msg.content)}')">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
        Copier
      </button>
      <button class="ia-msg-tool" title="Régénérer" onclick="IAPro.regenerate('${esc(msg.id)}')">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
        Régénérer
      </button>
      <button class="ia-msg-tool" title="Lire" onclick="IAPro.tts('${encodeURIComponent(msg.content)}')">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/></svg>
      </button>
      <button class="ia-msg-tool" title="Supprimer" onclick="IAPro.deleteMsgUI('${esc(msg.id)}')">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
      </button>`;

    // Image bubble
    if (msg.imageData) {
      return `<div class="ia-msg-row bot ia-fade-in" data-msg-id="${esc(msg.id)}">
        <div class="ia-msg-av"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M8 15s1.5 2 4 2 4-2 4-2"/><path d="M9 9h.01"/><path d="M15 9h.01"/></svg></div>
        <div class="ia-msg-content">
          <div class="ia-img-card">
            <div class="ia-img-card-caption">${esc(msg.content)}</div>
            <img src="${esc(msg.imageData.url)}" alt="${esc(msg.imageData.title || '')}" loading="lazy">
            <div class="ia-img-card-footer">
              <span>Source : ${esc(msg.imageData.source || 'Wikipedia')}</span>
              ${msg.imageData.wiki ? `<a href="${esc(msg.imageData.wiki)}" target="_blank">Consulter l'article</a>` : ''}
            </div>
          </div>
          <div class="ia-msg-meta"><span class="ia-msg-time">${time}</span><div class="ia-msg-tools">${tools}</div></div>
        </div>
      </div>`;
    }

    const av = isUser
      ? `<div class="ia-msg-av" style="background:linear-gradient(135deg,var(--ia-gold),#8C6A1A)">${initials}</div>`
      : `<div class="ia-msg-av"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M8 15s1.5 2 4 2 4-2 4-2"/><path d="M9 9h.01"/><path d="M15 9h.01"/></svg></div>`;

    return `<div class="ia-msg-row ${isUser?'user':'bot'} ia-fade-in" data-msg-id="${esc(msg.id)}">
      ${av}
      <div class="ia-msg-content">
        <div class="ia-bubble">${content}</div>
        <div class="ia-msg-meta">
          <span class="ia-msg-time">${time}</span>
          <div class="ia-msg-tools">${tools}</div>
        </div>
      </div>
    </div>`;
  }

  function appendMsgToDOM(msg) {
    const msgs = document.getElementById('iaMsgs');
    const welcome = document.getElementById('iaWelcome');
    if (welcome) welcome.style.display = 'none';
    const el = document.createElement('div');
    el.innerHTML = renderMsg(msg);
    msgs.appendChild(el.firstElementChild);
    const area = document.getElementById('iaMsgsArea');
    if (area) area.scrollTop = area.scrollHeight;
  }

  function showTyping() {
    const msgs = document.getElementById('iaMsgs');
    const el = document.createElement('div');
    el.id = 'iaTyping';
    el.className = 'ia-typing-row';
    el.innerHTML = `<div class="ia-typing-av"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M8 15s1.5 2 4 2 4-2 4-2"/><path d="M9 9h.01"/><path d="M15 9h.01"/></svg></div>
    <div class="ia-typing-dots"><span></span><span></span><span></span></div>`;
    msgs.appendChild(el);
    const area = document.getElementById('iaMsgsArea');
    if (area) area.scrollTop = area.scrollHeight;
    return el;
  }
  function removeTyping() { document.getElementById('iaTyping')?.remove(); }

  // ── Send message ──────────────────────────────────────────
  async function send(text) {
    if (S.loading || !text.trim()) return;
    text = text.trim();
    S.loading = true;
    setSendDisabled(true);

    let conv = store.getActive();
    if (!conv) conv = store.newConv();

    // Attachments
    const hasAttachments = S.attachments.length > 0;
    const userMsg = store.addMessage(conv.id, 'user', text + (hasAttachments ? `\n[${S.attachments.map(a=>a.name).join(', ')}]` : ''));
    appendMsgToDOM(userMsg);
    clearAttachments();
    renderSidebar();

    const typingEl = showTyping();

    // Build history for API
    const history = conv.messages.slice(0, -1)
      .filter(m => m.role === 'user' || m.role === 'assistant')
      .map(m => ({ role: m.role, content: m.content }));

    // Detect image request
    const imgPattern = /(?:génère|dessine|crée|fais|montre|illustre|représente)\s+(?:moi\s+)?(?:une?\s+)?(?:image|illustration|dessin|schéma|photo)\s+(?:de\s+|du\s+|d'|des?\s+)?(.+)/iu;
    const imgMatch = text.match(imgPattern);

    try {
      if (imgMatch) {
        // Image search
        const q = imgMatch[1].trim();
        const imgResp = await fetch('/reussiteplus/api/ia_image.php?q=' + encodeURIComponent(q));
        const imgData = await imgResp.json();
        removeTyping();
        const caption = `Illustration — ${q}`;
        if (imgData.success && imgData.url) {
          const botMsg = store.addMessage(conv.id, 'assistant', caption, { imageData: imgData });
          appendMsgToDOM(botMsg);
        } else {
          const botMsg = store.addMessage(conv.id, 'assistant', `Aucune illustration trouvée pour "${q}". Reformule ta question.`);
          appendMsgToDOM(botMsg);
        }
      } else {
        // Normal chat
        const res = await fetch('/reussiteplus/api/ia_chat.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ message: text, history, tone: S.tone, exercice: isExercice(text) ? 1 : 0 }),
        });
        const data = await res.json();
        removeTyping();
        const reply = data.reply || "Désolé, je n'ai pas pu répondre.";
        const botMsg = store.addMessage(conv.id, 'assistant', reply);
        appendMsgToDOM(botMsg);
        renderSidebar();
      }
    } catch(e) {
      removeTyping();
      const botMsg = store.addMessage(conv.id, 'assistant', 'Erreur de connexion. Vérifie ta connexion et réessaie.');
      appendMsgToDOM(botMsg);
    }

    S.loading = false;
    setSendDisabled(false);
    document.getElementById('iaChatInput')?.focus();
  }

  function isExercice(msg) {
    return /\b(calcul|résous|résoudre|équation|problème|exercice|simplifie|factorise|montrer que)\b|\d+\s*[+\-*\/=]/i.test(msg);
  }

  function setSendDisabled(val) {
    const btn = document.getElementById('iaSendBtn');
    if (btn) btn.disabled = val;
  }

  // ── Message actions ───────────────────────────────────────
  function copyMsg(id, encoded) {
    const text = decodeURIComponent(encoded);
    navigator.clipboard?.writeText(text).then(() => {
      const btn = document.getElementById('copy-' + id);
      if (btn) { btn.classList.add('copied'); btn.innerHTML = `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Copié`; setTimeout(() => { btn.classList.remove('copied'); btn.innerHTML = `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> Copier`; }, 2000); }
    });
  }

  async function regenerate(msgId) {
    const conv = store.getActive();
    if (!conv || S.loading) return;
    const idx = conv.messages.findIndex(m => m.id === msgId);
    if (idx < 0) return;
    // Find the user message before this bot message
    const userMsg = conv.messages.slice(0, idx).reverse().find(m => m.role === 'user');
    if (!userMsg) return;
    // Remove the bot message
    conv.messages = conv.messages.filter(m => m.id !== msgId);
    store.save();
    renderChat();
    await send(userMsg.content);
  }

  function editMsg(msgId) {
    const conv = store.getActive();
    if (!conv) return;
    const msg = conv.messages.find(m => m.id === msgId);
    if (!msg) return;
    const input = document.getElementById('iaChatInput');
    if (input) { input.value = msg.content; input.focus(); autoResize(input); }
    // Remove from list (re-sent when user clicks Send)
    store.deleteMessage(conv.id, msgId);
    renderChat();
  }

  function deleteMsgUI(msgId) {
    const conv = store.getActive();
    if (!conv) return;
    store.deleteMessage(conv.id, msgId);
    document.querySelector(`[data-msg-id="${msgId}"]`)?.remove();
    toast('Message supprimé', 'success');
  }

  function tts(encoded) {
    const text = decodeURIComponent(encoded);
    speechSynthesis.cancel();
    const u = new SpeechSynthesisUtterance(text);
    u.lang = 'fr-FR'; u.rate = 1.02; speechSynthesis.speak(u);
  }

  // ── Conversation management ────────────────────────────────
  function selectConv(id) {
    S.activeId = id;
    localStorage.setItem('ia_active', id);
    renderSidebar();
    renderChat();
    closeSidebarMobile();
  }
  function newChat() {
    store.newConv();
    renderSidebar();
    renderChat();
  }
  function deleteConv(id) {
    if (!confirm('Supprimer cette conversation ?')) return;
    store.deleteConv(id);
    renderSidebar();
    renderChat();
  }
  function toggleFav(id) {
    store.toggleFavorite(id);
    renderSidebar();
  }
  function renameConv(id) {
    const c = S.conversations.find(x => x.id === id);
    if (!c) return;
    const name = prompt('Nouveau nom :', c.title);
    if (name?.trim()) { store.renameConv(id, name.trim()); renderSidebar(); }
  }
  function clearHistory() {
    if (!confirm('Effacer cette conversation ?')) return;
    const conv = store.getActive();
    if (!conv) return;
    conv.messages = [];
    store.save();
    renderChat();
    fetch('/reussiteplus/api/ia_chat.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'clear_history'}) }).catch(()=>{});
    toast('Conversation effacée', 'success');
  }

  // ── Analyse / Plan révision ───────────────────────────────
  async function analyse() {
    const conv = store.getActive();
    if (!conv || conv.messages.length < 2) { toast('Commencez d\'abord une conversation', 'error'); return; }
    S.loading = true; setSendDisabled(true);
    const typingEl = showTyping();
    const history  = conv.messages.map(m => ({ role: m.role, content: m.content }));
    try {
      const res  = await fetch('/reussiteplus/api/ia_chat.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'analyse', history}) });
      const data = await res.json();
      removeTyping();
      const botMsg = store.addMessage(conv.id, 'assistant', data.reply || 'Analyse indisponible.');
      appendMsgToDOM(botMsg);
    } catch(_) {
      removeTyping();
      toast('Erreur de connexion', 'error');
    }
    S.loading = false; setSendDisabled(false);
  }

  // ── Attachments ───────────────────────────────────────────
  function handleFileInput(e) {
    const files = Array.from(e.target.files || []);
    files.forEach(f => {
      if (f.size > 10 * 1024 * 1024) { toast('Fichier trop volumineux (max 10 Mo)', 'error'); return; }
      S.attachments.push({ name: f.name, size: f.size, type: f.type, file: f });
    });
    renderAttachments();
    e.target.value = '';
  }
  function renderAttachments() {
    const row = document.getElementById('iaAttachRow');
    if (!row) return;
    if (!S.attachments.length) { row.style.display = 'none'; row.innerHTML = ''; return; }
    row.style.display = 'flex';
    row.innerHTML = S.attachments.map((a, i) => `
      <div class="ia-attachment-chip">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        ${esc(a.name)} <span style="color:var(--ia-text-3)">${fileSize(a.size)}</span>
        <button class="ia-attachment-rm" onclick="IAPro.removeAttachment(${i})">&times;</button>
      </div>`).join('');
  }
  function removeAttachment(i) {
    S.attachments.splice(i, 1);
    renderAttachments();
  }
  function clearAttachments() { S.attachments = []; renderAttachments(); }

  // ── Export ────────────────────────────────────────────────
  function exportTxt() {
    const conv = store.getActive();
    if (!conv || !conv.messages.length) { toast('Aucune conversation à exporter', 'error'); return; }
    const lines = conv.messages.map(m => `[${m.role === 'user' ? window.iaUserPrenom || 'Vous' : 'Coach IA'}] ${m.content}`);
    const blob  = new Blob([lines.join('\n\n')], { type: 'text/plain; charset=utf-8' });
    const url   = URL.createObjectURL(blob);
    const a     = document.createElement('a'); a.href = url; a.download = `Coach-IA-${conv.title.slice(0,30)}.txt`; a.click();
    URL.revokeObjectURL(url);
    toast('Export TXT réussi', 'success');
  }
  function exportPdf() {
    const conv = store.getActive();
    if (!conv || !conv.messages.length) { toast('Aucune conversation à exporter', 'error'); return; }
    if (typeof IAPdf === 'undefined') { toast('Générateur PDF non chargé', 'error'); return; }
    IAPdf.open(conv.messages, conv.title, window.iaUserPrenom || 'Élève');
    toast('Rapport PDF en cours de génération…', 'success');
  }

  // ── Prompts library ───────────────────────────────────────
  const PROMPTS = {
    'Mathématiques': [
      { t: 'Résolution d\'équations', p: 'Résous l\'équation : 2x + 5 = 13. Explique chaque étape.' },
      { t: 'Calcul de limites', p: 'Explique comment calculer la limite de f(x) = sin(x)/x quand x→0.' },
      { t: 'Géométrie', p: 'Comment calculer l\'aire d\'un triangle avec les 3 côtés connus ?' },
      { t: 'Statistiques', p: 'Explique la différence entre moyenne, médiane et mode avec un exemple.' },
    ],
    'Biologie': [
      { t: 'Photosynthèse', p: 'Explique la photosynthèse pour l\'EXETAT avec l\'équation chimique.' },
      { t: 'Mitose vs Méiose', p: 'Quelle est la différence entre la mitose et la méiose ?' },
      { t: 'ADN & Génétique', p: 'Explique la structure de l\'ADN et son rôle dans l\'hérédité.' },
      { t: 'Classification', p: 'Comment classer les êtres vivants ? Donne les grands règnes.' },
    ],
    'Chimie': [
      { t: 'Équilibrage', p: 'Comment équilibrer une équation chimique ? Donne un exemple.' },
      { t: 'Tableau périodique', p: 'Explique comment lire le tableau périodique des éléments.' },
      { t: 'pH et acidité', p: 'C\'est quoi le pH ? Donne des exemples d\'acides et de bases.' },
      { t: 'Réactions', p: 'Explique les types de réactions chimiques (synthèse, décomposition…).' },
    ],
    'Français': [
      { t: 'Accord participe passé', p: 'Explique les règles d\'accord du participe passé avec exemples.' },
      { t: 'Analyse grammaticale', p: 'Comment faire une analyse grammaticale complète d\'une phrase ?' },
      { t: 'Plan de rédaction', p: 'Donne-moi un plan pour rédiger un texte argumentatif.' },
      { t: 'Conjugaison', p: 'Quels sont les temps littéraires et leurs emplois ?' },
    ],
    'Révision EXETAT': [
      { t: 'Plan 7 jours', p: 'Génère un plan de révision sur 7 jours pour l\'EXETAT.' },
      { t: 'Matières importantes', p: 'Quelles matières sont les plus importantes pour l\'EXETAT en option scientifique ?' },
      { t: 'Stratégie d\'examen', p: 'Comment gérer son temps pendant l\'EXETAT ?' },
      { t: 'Gestion du stress', p: 'Comment gérer le stress avant et pendant l\'examen ?' },
    ],
  };

  function renderPromptsModal(cat = Object.keys(PROMPTS)[0]) {
    const catsHtml = Object.keys(PROMPTS).map(c =>
      `<button class="ia-prompt-cat${c===cat?' active':''}" onclick="IAPro.switchPromptCat('${esc(c)}')">${esc(c)}</button>`
    ).join('');
    const itemsHtml = PROMPTS[cat].map(p =>
      `<div class="ia-prompt-item" onclick="IAPro.usePrompt('${encodeURIComponent(p.p)}')">
        ${esc(p.t)}
        <div class="ia-prompt-item-label">${esc(p.p.slice(0,60))}…</div>
      </div>`
    ).join('');
    document.getElementById('iaPromptCats').innerHTML  = catsHtml;
    document.getElementById('iaPromptItems').innerHTML = itemsHtml;
  }
  function switchPromptCat(cat) { renderPromptsModal(cat); }
  function usePrompt(encoded) {
    const text = decodeURIComponent(encoded);
    const input = document.getElementById('iaChatInput');
    if (input) { input.value = text; input.focus(); autoResize(input); }
    closeModal('iaPromptsModal');
  }

  // ── Modals ────────────────────────────────────────────────
  function openModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('open');
    if (id === 'iaPromptsModal') renderPromptsModal();
  }
  function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('open');
  }

  // ── Sidebar toggle ────────────────────────────────────────
  function toggleSidebar() {
    const sb = document.getElementById('iaSidebar');
    if (!sb) return;
    if (window.innerWidth <= 768) {
      sb.classList.toggle('open');
    } else {
      sb.classList.toggle('collapsed');
    }
  }
  function closeSidebarMobile() {
    if (window.innerWidth <= 768) {
      document.getElementById('iaSidebar')?.classList.remove('open');
    }
  }

  // ── Textarea auto-resize ──────────────────────────────────
  function autoResize(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 220) + 'px';
  }

  // ── Keyboard shortcuts ────────────────────────────────────
  function initShortcuts() {
    document.addEventListener('keydown', e => {
      if (e.ctrlKey || e.metaKey) {
        if (e.key === 'n') { e.preventDefault(); newChat(); }
        if (e.key === '/' ) { e.preventDefault(); document.getElementById('iaChatInput')?.focus(); }
        if (e.key === 'k' ) { e.preventDefault(); document.getElementById('iaSearchInput')?.focus(); }
        if (e.key === 'b' ) { e.preventDefault(); toggleSidebar(); }
      }
      if (e.key === 'Escape') {
        document.querySelectorAll('.ia-overlay.open').forEach(el => el.classList.remove('open'));
      }
    });
  }

  // ── Init ──────────────────────────────────────────────────
  function init() {
    store.load();
    applyTheme(S.theme);

    // If no conversations, create one
    if (!S.conversations.length) store.newConv();
    if (!S.activeId || !store.getActive()) S.activeId = S.conversations[0]?.id;

    renderSidebar();
    renderChat();

    // Textarea
    const input = document.getElementById('iaChatInput');
    if (input) {
      input.addEventListener('input', () => autoResize(input));
      input.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendForm(); }
      });
    }

    // Form
    document.getElementById('iaChatForm')?.addEventListener('submit', e => { e.preventDefault(); sendForm(); });

    // Search
    document.getElementById('iaSearchInput')?.addEventListener('input', e => {
      S.searchQuery = e.target.value;
      renderSidebar();
    });

    // Tone
    const tone = document.getElementById('iaToneSelect');
    if (tone) { tone.value = S.tone; tone.addEventListener('change', e => { S.tone = e.target.value; localStorage.setItem('ia_tone', S.tone); }); }

    // File inputs
    document.getElementById('iaFileInput')?.addEventListener('change', handleFileInput);
    document.getElementById('iaImageInput')?.addEventListener('change', handleFileInput);

    // Theme toggle
    document.getElementById('iaThemeToggle')?.addEventListener('click', () => {
      applyTheme(S.theme === 'dark' ? 'light' : 'dark');
      const toggle = document.getElementById('iaThemeToggle');
      if (toggle) toggle.setAttribute('data-theme', S.theme);
    });

    // Keyboard shortcuts
    initShortcuts();

    // Focus input
    setTimeout(() => input?.focus(), 100);
  }

  function sendForm() {
    const input = document.getElementById('iaChatInput');
    const text  = input?.value.trim();
    if (!text || S.loading) return;
    input.value = '';
    autoResize(input);
    send(text);
  }

  // Public API
  return { init, newChat, selectConv, deleteConv, toggleFav, renameConv, clearHistory, analyse, copyMsg, regenerate, editMsg, deleteMsgUI, tts, exportTxt, exportPdf, openModal, closeModal, toggleSidebar, switchPromptCat, usePrompt, removeAttachment };
})();

document.addEventListener('DOMContentLoaded', IAPro.init);
