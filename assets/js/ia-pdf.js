/**
 * RÉUSSITE+ — Coach IA · Générateur PDF Premium
 * Rapport d'apprentissage professionnel — niveau SaaS
 */

const IAPdf = (() => {

  // ── Utilitaires ───────────────────────────────────────────
  const esc = s => String(s)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

  function mdToHtml(t) {
    if (!t) return '';
    // Blocs code
    t = t.replace(/```[\w]*\n?([\s\S]*?)```/g, '<pre class="code-block"><code>$1</code></pre>');
    // Inline code
    t = t.replace(/`([^`\n]+)`/g, '<code class="code-inline">$1</code>');
    // Escape HTML (sauf tags déjà créés)
    const parts = t.split(/(<pre[\s\S]*?<\/pre>)/g);
    t = parts.map((p, i) => i % 2 === 0 ? esc(p) : p).join('');
    // Titres
    t = t.replace(/^### (.+)$/gm, '<h4 class="md-h3">$1</h4>');
    t = t.replace(/^## (.+)$/gm,  '<h3 class="md-h2">$1</h3>');
    t = t.replace(/^# (.+)$/gm,   '<h3 class="md-h2">$1</h3>');
    // Gras / italique
    t = t.replace(/\*\*\*(.+?)\*\*\*/g, '<strong><em>$1</em></strong>');
    t = t.replace(/\*\*(.+?)\*\*/g,     '<strong class="bold-concept">$1</strong>');
    t = t.replace(/\*(.+?)\*/g,         '<em>$1</em>');
    // Listes numérotées
    t = t.replace(/^(\d+)\. (.+)$/gm,   '<li class="li-num">$2</li>');
    t = t.replace(/(<li class="li-num">[\s\S]*?<\/li>\n?)+/g, '<ol class="md-ol">$&</ol>');
    // Listes à puces
    t = t.replace(/^[-•] (.+)$/gm, '<li>$1</li>');
    t = t.replace(/(<li>[\s\S]*?<\/li>\n?)+/g, '<ul class="md-ul">$&</ul>');
    // Sauts de ligne
    t = t.replace(/\n\n+/g, '</p><p class="para">');
    t = t.replace(/\n/g,    '<br>');
    return `<p class="para">${t}</p>`;
  }

  // ── Analyse intelligente du contenu ──────────────────────
  function detectMainTopic(messages) {
    const text = messages.filter(m => m.role === 'user').map(m => m.content).join(' ').toLowerCase();
    const subjects = {
      'Mathématiques':  /math|équation|calcul|algèbre|géométrie|limite|dérivée|intégrale|pgcd|vecteur/i,
      'Biologie':       /biolog|cellule|adn|photosynthèse|mitose|méiose|organisme|écosystème|enzyme/i,
      'Chimie':         /chimi|réaction|élément|atome|molécule|ph|acide|base|oxyde|sel|formule/i,
      'Physique':       /physique|force|énergie|vitesse|accélération|électricité|optique|onde/i,
      'Français':       /français|grammaire|conjugaison|orthographe|rédaction|participe|phrase|style/i,
      'Histoire-Géo':   /histoire|géograph|continent|province|fleuve|colonisation|guerre|nation/i,
      'Révision EXETAT':  /exetat|révision|plan|préparer|programme|examen|enafep|tenasosp/i,
    };
    for (const [subject, re] of Object.entries(subjects)) {
      if (re.test(text)) return subject;
    }
    return 'Préparation EXETAT';
  }

  function extractKeyConceptsPdf(messages) {
    const concepts = new Set();
    messages.filter(m => m.role === 'assistant').forEach(msg => {
      // Titres ## comme concepts
      (msg.content.match(/^#{1,3} (.+)$/gm) || []).forEach(h =>
        concepts.add(h.replace(/^#{1,3} /, '').trim())
      );
      // Textes en gras comme concepts clés
      (msg.content.match(/\*\*([^*]{3,40})\*\*/g) || []).forEach(b =>
        concepts.add(b.replace(/\*\*/g, '').trim())
      );
    });
    return [...concepts].slice(0, 10);
  }

  function assessLearning(messages) {
    const userMsgs = messages.filter(m => m.role === 'user');
    const aiMsgs   = messages.filter(m => m.role === 'assistant');
    const avgLen   = aiMsgs.length
      ? aiMsgs.reduce((s, m) => s + m.content.length, 0) / aiMsgs.length
      : 0;
    const nbQ      = userMsgs.length;
    const engagement = Math.min(nbQ * 14, 56);
    const depth      = Math.min(Math.round(avgLen / 80), 44);
    const score      = Math.min(engagement + depth, 100);
    const level = score >= 85 ? 'Excellent' : score >= 70 ? 'Très bien' : score >= 55 ? 'Bien' : score >= 40 ? 'Satisfaisant' : 'En progression';
    const color = score >= 70 ? '#007A5E' : score >= 50 ? '#C9972A' : '#6B7280';
    return { score, level, color };
  }

  function autoRecommendations(messages, topic) {
    const text  = messages.map(m => m.content).join(' ').toLowerCase();
    const recs  = [];
    if (/équation|algèbre|calcul/.test(text))
      recs.push('Pratiquer la résolution d\'équations — 10 exercices/jour');
    if (/photosynthèse|cellule|biolog/.test(text))
      recs.push('Revoir le schéma de la cellule végétale');
    if (/liste|étape|plan/.test(text))
      recs.push('Créer des fiches récapitulatives par chapitre');
    if (messages.filter(m=>m.role==='user').length <= 2)
      recs.push('Approfondir le sujet avec des questions supplémentaires');
    recs.push('Passer un examen blanc sur ' + topic + ' dans RÉUSSITE+');
    recs.push('Générer un plan de révision personnalisé sur 7 jours');
    return recs.slice(0, 4);
  }

  // ── CSS premium ───────────────────────────────────────────
  function buildCSS(nom) {
    return `
<style>
/* ── Print setup ── */
@page { size: A4; margin: 0; }
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'Helvetica Neue', Arial, sans-serif;
  color: #1a2535; font-size: 10.5pt; line-height: 1.65;
  background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact;
}
.page { width: 210mm; min-height: 297mm; position: relative; overflow: hidden; page-break-after: always; }
.page:last-child { page-break-after: auto; }

/* ── PAGE 1 : COVER ── */
.cover {
  background: linear-gradient(160deg, #007A5E 0%, #005A45 55%, #003D30 100%);
  display: flex; flex-direction: column; justify-content: space-between;
  padding: 0;
}
.cover-top { padding: 42px 48px 0; }
.cover-brand {
  display: flex; align-items: center; gap: 10px; margin-bottom: 56px;
}
.cover-brand-icon {
  width: 40px; height: 40px; border-radius: 10px;
  background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.3);
  display: flex; align-items: center; justify-content: center;
}
.cover-brand-name {
  font-size: 18pt; font-weight: 900; color: #fff;
  letter-spacing: -.5px; font-family: Georgia, serif;
}
.cover-brand-name span { color: #C9972A; }
.cover-brand-sub { font-size: 8pt; color: rgba(255,255,255,.5); margin-top: 2px; letter-spacing: .5px; }
.cover-badge {
  display: inline-flex; align-items: center; gap: 6px;
  background: rgba(201,151,42,.2); border: 1px solid rgba(201,151,42,.4);
  padding: 4px 14px; border-radius: 20px;
  font-size: 8pt; color: #F5D78E; font-weight: 600; letter-spacing: .5px;
  margin-bottom: 24px;
}
.cover-title {
  font-family: Georgia, serif; font-size: 24pt; font-weight: 700;
  color: #fff; line-height: 1.2; letter-spacing: -.3px; margin-bottom: 10px;
}
.cover-subtitle { font-size: 11pt; color: rgba(255,255,255,.6); margin-bottom: 44px; }
.cover-card {
  background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.18);
  border-radius: 14px; padding: 20px 24px;
  max-width: 360px; display: grid; grid-template-columns: 1fr 1fr; gap: 14px 24px;
}
.cover-field-label { font-size: 7.5pt; color: rgba(255,255,255,.45); text-transform: uppercase; letter-spacing: .8px; margin-bottom: 3px; }
.cover-field-value { font-size: 11pt; font-weight: 700; color: #fff; }
.cover-field-value.gold { color: #F5D78E; }
.cover-decorative {
  position: absolute; right: -40px; top: 60px;
  width: 280px; height: 280px; border-radius: 50%;
  border: 1px solid rgba(255,255,255,.06);
  background: radial-gradient(circle, rgba(255,255,255,.04) 0%, transparent 70%);
}
.cover-decorative-2 {
  position: absolute; right: 40px; top: 140px;
  width: 160px; height: 160px; border-radius: 50%;
  border: 1px solid rgba(201,151,42,.12);
}
.cover-bottom {
  padding: 24px 48px; border-top: 1px solid rgba(255,255,255,.1);
  display: flex; justify-content: space-between; align-items: center;
}
.cover-stat { text-align: center; }
.cover-stat-num { font-size: 18pt; font-weight: 900; color: #fff; font-family: Georgia, serif; }
.cover-stat-label { font-size: 7.5pt; color: rgba(255,255,255,.45); text-transform: uppercase; letter-spacing: .6px; }
.cover-stat-divider { width: 1px; height: 36px; background: rgba(255,255,255,.12); }
.cover-powered { font-size: 8pt; color: rgba(255,255,255,.3); text-align: right; line-height: 1.7; }

/* ── PAGE HEADER & FOOTER (non-cover pages) ── */
.doc-page { padding: 0; }
.doc-header {
  background: #fff; border-bottom: 2px solid #007A5E;
  padding: 12px 30px; display: flex; align-items: center; justify-content: space-between;
  position: sticky; top: 0;
}
.doc-header-brand { font-size: 11pt; font-weight: 900; color: #007A5E; font-family: Georgia, serif; }
.doc-header-brand span { color: #C9972A; }
.doc-header-meta { font-size: 7.5pt; color: #9CA3AF; }
.doc-footer {
  background: #F8FAFC; border-top: 1px solid #E5E7EB;
  padding: 8px 30px; display: flex; align-items: center; justify-content: space-between;
  position: absolute; bottom: 0; left: 0; right: 0;
}
.doc-footer-brand { font-size: 7.5pt; color: #9CA3AF; }
.doc-footer-conf { font-size: 7.5pt; color: #C5D0DB; font-style: italic; }
.doc-content { padding: 22px 30px 60px; }

/* ── SECTIONS HEADER ── */
.section-wrap { margin-bottom: 22px; }
.section-hd {
  display: flex; align-items: center; gap: 10px; margin-bottom: 14px;
}
.section-icon {
  width: 28px; height: 28px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  font-size: 13pt; flex-shrink: 0;
}
.section-title-text { font-size: 9pt; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; }
.section-line { flex: 1; height: 1px; }

/* ── SUMMARY PAGE ── */
.summary-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 20px; }
.summary-card {
  background: #F8FAFC; border: 1px solid #E5E7EB; border-radius: 10px;
  padding: 14px 16px;
}
.summary-card.highlight {
  background: linear-gradient(135deg, #E8F5F1, #F0FAF7);
  border-color: rgba(0,122,94,.2);
}
.summary-card-title { font-size: 7.5pt; font-weight: 700; text-transform: uppercase; letter-spacing: .7px; color: #9CA3AF; margin-bottom: 8px; }
.summary-card-content { font-size: 10pt; color: #1a2535; line-height: 1.6; }
.score-circle {
  display: flex; align-items: center; gap: 14px;
}
.score-num { font-size: 28pt; font-weight: 900; font-family: Georgia, serif; }
.score-label { font-size: 10pt; font-weight: 700; }
.score-sub { font-size: 8pt; color: #9CA3AF; margin-top: 2px; }
.concepts-list { list-style: none; }
.concepts-list li {
  font-size: 9.5pt; padding: 4px 0; border-bottom: 1px solid #F1F5F9;
  display: flex; align-items: center; gap: 7px; color: #374151;
}
.concepts-list li::before { content: '◆'; font-size: 6pt; color: #007A5E; flex-shrink: 0; }
.concepts-list li:last-child { border: none; }

/* ── CONVERSATION ── */
.exchange-block { margin-bottom: 20px; page-break-inside: avoid; }
.exchange-num {
  font-size: 7.5pt; font-weight: 700; text-transform: uppercase;
  letter-spacing: .8px; color: #9CA3AF; margin-bottom: 8px;
  display: flex; align-items: center; gap: 8px;
}
.exchange-num::before { content: ''; flex: 1; height: 1px; background: #E5E7EB; }
.exchange-num::after  { content: ''; flex: 1; height: 1px; background: #E5E7EB; }

/* User message */
.msg-user-wrap { display: flex; justify-content: flex-end; margin-bottom: 8px; }
.msg-user {
  max-width: 78%; background: linear-gradient(135deg, #007A5E, #005A45);
  color: #fff; border-radius: 14px 14px 4px 14px;
  padding: 10px 14px; font-size: 10.5pt; line-height: 1.6;
  box-shadow: 0 2px 8px rgba(0,122,94,.15);
}
.msg-user-meta {
  display: flex; justify-content: flex-end; align-items: center;
  gap: 7px; margin-bottom: 4px;
}
.msg-user-name { font-size: 8pt; font-weight: 700; color: #fff; opacity: .85; }
.msg-user-av {
  width: 20px; height: 20px; border-radius: 50%;
  background: rgba(255,255,255,.2); border: 1px solid rgba(255,255,255,.3);
  display: inline-flex; align-items: center; justify-content: center;
  font-size: 8pt; font-weight: 800; color: #fff;
}

/* AI message */
.msg-ai-wrap { display: flex; margin-bottom: 4px; gap: 10px; }
.msg-ai-av {
  width: 26px; height: 26px; border-radius: 50%;
  background: linear-gradient(135deg, #007A5E, #005A45);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; margin-top: 2px;
}
.msg-ai-inner { flex: 1; min-width: 0; }
.msg-ai-name { font-size: 8pt; font-weight: 700; color: #007A5E; margin-bottom: 5px; }
.msg-ai {
  background: #FFFFFF; border: 1px solid #E8ECF0;
  border-left: 3px solid #C9972A; border-radius: 4px 12px 12px 12px;
  padding: 11px 14px; font-size: 10.5pt; line-height: 1.68;
  box-shadow: 0 1px 4px rgba(0,0,0,.06);
}

/* Markdown dans les réponses */
.msg-ai .para { margin-bottom: 8px; }
.msg-ai .para:last-child { margin-bottom: 0; }
.msg-ai .md-h2 {
  font-size: 11pt; font-weight: 800; color: #007A5E;
  font-family: Georgia, serif; margin: 12px 0 5px;
  padding-bottom: 4px; border-bottom: 1px solid #E8F5F1;
}
.msg-ai .md-h3 { font-size: 10.5pt; font-weight: 700; color: #1a2535; margin: 9px 0 3px; }
.msg-ai .md-ul { margin: 5px 0 5px 16px; padding: 0; }
.msg-ai .md-ol { margin: 5px 0 5px 18px; padding: 0; }
.msg-ai .md-ul li, .msg-ai .md-ol li { margin: 3px 0; font-size: 10.5pt; }
.msg-ai .bold-concept { font-weight: 700; color: #1a2535; }
.msg-ai .code-block {
  background: #F4F6F9; border: 1px solid #E2E8F0; border-radius: 6px;
  padding: 10px 13px; margin: 8px 0; font-family: 'Courier New', monospace;
  font-size: 9pt; overflow-wrap: break-word; white-space: pre-wrap;
}
.msg-ai .code-inline {
  background: #F4F6F9; border: 1px solid #E2E8F0; border-radius: 3px;
  padding: 1px 5px; font-family: 'Courier New', monospace; font-size: 9pt;
}

/* ── PEDAGOGY PAGE ── */
.ped-section { margin-bottom: 20px; }
.ped-card {
  border: 1px solid #E5E7EB; border-radius: 10px; overflow: hidden;
}
.ped-card-hd {
  padding: 10px 16px; font-size: 8.5pt; font-weight: 700;
  text-transform: uppercase; letter-spacing: .7px; display: flex; align-items: center; gap: 7px;
}
.ped-card-body { padding: 14px 16px; }
.ped-card-body.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.ped-item { display: flex; align-items: flex-start; gap: 9px; padding: 7px 0; border-bottom: 1px solid #F1F5F9; font-size: 10pt; color: #374151; }
.ped-item:last-child { border: none; }
.ped-item-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; margin-top: 5px; }
.ped-rec {
  display: flex; align-items: flex-start; gap: 9px;
  padding: 8px 0; border-bottom: 1px solid #F1F5F9;
  font-size: 10pt; color: #374151;
}
.ped-rec:last-child { border: none; }
.ped-rec-num {
  width: 20px; height: 20px; border-radius: 50%; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  font-size: 8pt; font-weight: 800; color: #fff;
  background: linear-gradient(135deg, #007A5E, #005A45);
}
.timeline { display: flex; flex-direction: column; gap: 0; }
.timeline-item { display: flex; gap: 12px; padding: 8px 0; }
.timeline-dot-wrap { display: flex; flex-direction: column; align-items: center; width: 20px; flex-shrink: 0; }
.timeline-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.timeline-line { width: 2px; flex: 1; min-height: 12px; background: #E5E7EB; }
.timeline-item:last-child .timeline-line { display: none; }
.timeline-content { flex: 1; padding-bottom: 4px; }
.timeline-q { font-size: 9.5pt; font-weight: 600; color: #1a2535; margin-bottom: 2px; }
.timeline-a { font-size: 8.5pt; color: #6B7280; line-height: 1.5; }

/* ── PRINT UTILITIES ── */
@media print {
  body { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
  .page { page-break-after: always; }
  .page:last-child { page-break-after: auto; }
  .exchange-block { page-break-inside: avoid; }
  .ped-card { page-break-inside: avoid; }
}
</style>`;
  }

  // ── Page couverture ───────────────────────────────────────
  function buildCover(data) {
    const { prenom, topic, nbEchanges, nbConcepts, score, level, date, heure, titre } = data;
    const initials = (prenom || 'É')[0].toUpperCase();
    return `
<div class="page cover">
  <div class="cover-decorative"></div>
  <div class="cover-decorative-2"></div>

  <div class="cover-top">
    <div class="cover-brand">
      <div class="cover-brand-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M8 15s1.5 2 4 2 4-2 4-2"/><path d="M9 9h.01"/><path d="M15 9h.01"/></svg>
      </div>
      <div>
        <div class="cover-brand-name">RÉUSSITE<span>+</span></div>
        <div class="cover-brand-sub">PLATEFORME ÉDUCATIVE · RDC</div>
      </div>
    </div>

    <div class="cover-badge">
      <svg width="10" height="10" viewBox="0 0 24 24" fill="#F5D78E" stroke="none"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
      Rapport Premium · Coach IA
    </div>
    <div class="cover-title">Rapport<br>d'Apprentissage IA</div>
    <div class="cover-subtitle">Session d'étude personnalisée · ${esc(topic)}</div>

    <div class="cover-card">
      <div>
        <div class="cover-field-label">Préparé pour</div>
        <div class="cover-field-value gold">${esc(prenom)}</div>
      </div>
      <div>
        <div class="cover-field-label">Date</div>
        <div class="cover-field-value" style="font-size:9.5pt">${esc(date)}</div>
      </div>
      <div>
        <div class="cover-field-label">Sujet principal</div>
        <div class="cover-field-value" style="font-size:9.5pt">${esc(topic)}</div>
      </div>
      <div>
        <div class="cover-field-label">Heure</div>
        <div class="cover-field-value">${esc(heure)}</div>
      </div>
      <div>
        <div class="cover-field-label">Titre de session</div>
        <div class="cover-field-value" style="font-size:9pt;grid-column:1/-1">${esc(titre || 'Session Coach IA')}</div>
      </div>
    </div>
  </div>

  <div class="cover-bottom">
    <div class="cover-stat">
      <div class="cover-stat-num">${nbEchanges}</div>
      <div class="cover-stat-label">Échanges</div>
    </div>
    <div class="cover-stat-divider"></div>
    <div class="cover-stat">
      <div class="cover-stat-num">${nbConcepts}</div>
      <div class="cover-stat-label">Concepts clés</div>
    </div>
    <div class="cover-stat-divider"></div>
    <div class="cover-stat">
      <div class="cover-stat-num" style="color:${esc(score.color)}">${score.score}</div>
      <div class="cover-stat-label">Score /100</div>
    </div>
    <div class="cover-stat-divider"></div>
    <div class="cover-powered">
      Propulsé par<br>
      <strong style="color:rgba(255,255,255,.6)">Gemini 2.5 Flash</strong><br>
      <span style="color:rgba(255,255,255,.3)">+ Base EXETAT 1 051 Q.</span>
    </div>
  </div>
</div>`;
  }

  // ── Page résumé exécutif ──────────────────────────────────
  function buildSummary(data) {
    const { prenom, topic, concepts, score, nbEchanges, messages, titre } = data;
    const initials = (prenom || 'É')[0].toUpperCase();
    const concHtml = concepts.length
      ? `<ul class="concepts-list">${concepts.map(c => `<li>${esc(c)}</li>`).join('')}</ul>`
      : `<p style="font-size:9pt;color:#9CA3AF">Continuez la conversation pour extraire les concepts clés.</p>`;

    // Timeline des échanges
    const userMsgs = messages.filter(m => m.role === 'user').slice(0, 5);
    const aiMsgs   = messages.filter(m => m.role === 'assistant');
    const timelineHtml = userMsgs.map((msg, i) => {
      const aiReply = aiMsgs[i];
      const shortAi = aiReply ? aiReply.content.replace(/[#*`]/g,'').split('\n')[0].slice(0,80) : '';
      return `<div class="timeline-item">
        <div class="timeline-dot-wrap">
          <div class="timeline-dot" style="background:${i===0?'#007A5E':'#C9972A'}"></div>
          <div class="timeline-line"></div>
        </div>
        <div class="timeline-content">
          <div class="timeline-q">${esc(msg.content.slice(0,72))}${msg.content.length>72?'…':''}</div>
          ${shortAi ? `<div class="timeline-a">${esc(shortAi)}${aiReply&&aiReply.content.length>80?'…':''}</div>` : ''}
        </div>
      </div>`;
    }).join('');

    return `
<div class="page doc-page">
  <div class="doc-header">
    <div class="doc-header-brand">RÉUSSITE<span>+</span></div>
    <div class="doc-header-meta">Rapport d'apprentissage · ${esc(prenom)} · ${esc(topic)}</div>
  </div>

  <div class="doc-content">

    <div class="section-wrap">
      <div class="section-hd">
        <div class="section-icon" style="background:#E8F5F1">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#007A5E" stroke-width="2.5" stroke-linecap="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
        </div>
        <div class="section-title-text" style="color:#007A5E">Résumé Exécutif</div>
        <div class="section-line" style="background:#E8F5F1"></div>
      </div>

      <div class="summary-grid">
        <div class="summary-card highlight">
          <div class="summary-card-title">Score d'apprentissage</div>
          <div class="score-circle">
            <div class="score-num" style="color:${esc(score.color)}">${score.score}</div>
            <div>
              <div class="score-label" style="color:${esc(score.color)}">${esc(score.level)}</div>
              <div class="score-sub">sur 100 points</div>
            </div>
          </div>
        </div>
        <div class="summary-card">
          <div class="summary-card-title">Données de session</div>
          <div class="summary-card-content" style="font-size:9.5pt">
            <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #F1F5F9"><span style="color:#6B7280">Échanges actifs</span><strong>${nbEchanges}</strong></div>
            <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #F1F5F9"><span style="color:#6B7280">Sujet principal</span><strong>${esc(topic)}</strong></div>
            <div style="display:flex;justify-content:space-between;padding:4px 0"><span style="color:#6B7280">Concepts identifiés</span><strong>${concepts.length}</strong></div>
          </div>
        </div>
      </div>
    </div>

    <div class="section-wrap">
      <div class="section-hd">
        <div class="section-icon" style="background:#FEF3C7">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#C9972A" stroke-width="2.5" stroke-linecap="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        </div>
        <div class="section-title-text" style="color:#C9972A">Concepts Clés Identifiés</div>
        <div class="section-line" style="background:#FEF3C7"></div>
      </div>
      ${concHtml}
    </div>

    <div class="section-wrap">
      <div class="section-hd">
        <div class="section-icon" style="background:#EEF4FD">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#1E5FAD" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="section-title-text" style="color:#1E5FAD">Parcours d'Apprentissage</div>
        <div class="section-line" style="background:#EEF4FD"></div>
      </div>
      <div class="timeline">${timelineHtml || '<p style="font-size:9pt;color:#9CA3AF">Démarrez une conversation pour voir votre parcours.</p>'}</div>
    </div>

  </div>

  <div class="doc-footer">
    <div class="doc-footer-brand">RÉUSSITE+ · Coach IA · Session ${esc(prenom)}</div>
    <div class="doc-footer-conf">Document confidentiel — Usage pédagogique</div>
  </div>
</div>`;
  }

  // ── Pages conversation ────────────────────────────────────
  function buildConversation(data) {
    const { messages, prenom, topic } = data;
    const initials = (prenom || 'É')[0].toUpperCase();
    let exchangeNum = 0;
    const blocks = [];
    let i = 0;
    while (i < messages.length) {
      const msg = messages[i];
      if (msg.role === 'user') {
        exchangeNum++;
        const aiMsg = messages[i + 1]?.role === 'assistant' ? messages[i + 1] : null;
        blocks.push({ user: msg, ai: aiMsg, num: exchangeNum });
        i += aiMsg ? 2 : 1;
      } else {
        i++;
      }
    }

    // Split blocks into pages (~4 exchanges per page)
    const perPage  = 3;
    const pages    = [];
    for (let p = 0; p < blocks.length; p += perPage) {
      pages.push(blocks.slice(p, p + perPage));
    }
    if (!pages.length) pages.push([]);

    return pages.map((pageBlocks, pi) => {
      const blocksHtml = pageBlocks.map(({ user, ai, num }) => `
        <div class="exchange-block">
          <div class="exchange-num">Échange ${num}</div>
          <!-- Question élève -->
          <div class="msg-user-meta">
            <span class="msg-user-name">${esc(prenom)}</span>
            <div class="msg-user-av">${esc(initials)}</div>
          </div>
          <div class="msg-user-wrap">
            <div class="msg-user">${esc(user.content)}</div>
          </div>
          <!-- Réponse IA -->
          ${ai ? `
          <div class="msg-ai-wrap" style="margin-top:10px">
            <div class="msg-ai-av">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M8 15s1.5 2 4 2 4-2 4-2"/><path d="M9 9h.01"/><path d="M15 9h.01"/></svg>
            </div>
            <div class="msg-ai-inner">
              <div class="msg-ai-name">Coach IA — RÉUSSITE+</div>
              <div class="msg-ai">${mdToHtml(ai.content)}</div>
            </div>
          </div>` : ''}
        </div>`).join('') || '<p style="font-size:9pt;color:#9CA3AF;text-align:center;padding:32px 0">Aucun message dans cette session.</p>';

      return `
<div class="page doc-page">
  <div class="doc-header">
    <div class="doc-header-brand">RÉUSSITE<span>+</span></div>
    <div class="doc-header-meta">Transcription · ${esc(topic)} · Page ${pi + 3}</div>
  </div>
  <div class="doc-content">
    <div class="section-hd" style="margin-bottom:16px">
      <div class="section-icon" style="background:#F3F4F6">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6B7280" stroke-width="2.5" stroke-linecap="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      </div>
      <div class="section-title-text" style="color:#6B7280">Transcription de la Session ${pi > 0 ? `(suite ${pi+1})` : ''}</div>
      <div class="section-line" style="background:#F3F4F6"></div>
    </div>
    ${blocksHtml}
  </div>
  <div class="doc-footer">
    <div class="doc-footer-brand">RÉUSSITE+ · Coach IA · Session ${esc(prenom)}</div>
    <div class="doc-footer-conf">Document confidentiel — Usage pédagogique</div>
  </div>
</div>`;
    }).join('');
  }

  // ── Page pédagogique ──────────────────────────────────────
  function buildPedagogy(data) {
    const { prenom, topic, concepts, score, recs, messages } = data;
    const nbPages = Math.ceil(messages.filter(m=>m.role==='user').length / 3) + 2;
    const recsHtml = recs.map((r, i) => `
      <div class="ped-rec">
        <div class="ped-rec-num">${i+1}</div>
        <div>${esc(r)}</div>
      </div>`).join('');

    const keyPointsHtml = concepts.slice(0, 6).map(c => `
      <div class="ped-item">
        <div class="ped-item-dot" style="background:#007A5E"></div>
        <div>${esc(c)}</div>
      </div>`).join('') || `<div class="ped-item"><div class="ped-item-dot" style="background:#C9972A"></div><div>Poursuivez la conversation pour générer des points clés.</div></div>`;

    const nextSteps = [
      `Passer un examen blanc sur ${topic} dans RÉUSSITE+`,
      'Télécharger les archives officielles correspondantes',
      'Activer le suivi de progression par matière',
    ];

    return `
<div class="page doc-page">
  <div class="doc-header">
    <div class="doc-header-brand">RÉUSSITE<span>+</span></div>
    <div class="doc-header-meta">Synthèse pédagogique · ${esc(prenom)} · Page ${nbPages}</div>
  </div>
  <div class="doc-content">

    <div class="section-hd" style="margin-bottom:16px">
      <div class="section-icon" style="background:#E8F5F1">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#007A5E" stroke-width="2.5" stroke-linecap="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
      </div>
      <div class="section-title-text" style="color:#007A5E">Synthèse Pédagogique</div>
      <div class="section-line" style="background:#E8F5F1"></div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
      <!-- Points clés -->
      <div class="ped-card" style="border-color:#E8F5F1">
        <div class="ped-card-hd" style="background:#E8F5F1;color:#005A45">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
          Points clés retenus
        </div>
        <div class="ped-card-body">${keyPointsHtml}</div>
      </div>
      <!-- Score -->
      <div class="ped-card" style="border-color:#FEF3C7">
        <div class="ped-card-hd" style="background:#FEF3C7;color:#92400E">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="#C9972A" stroke="none"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
          Évaluation de la session
        </div>
        <div class="ped-card-body">
          <div style="text-align:center;padding:8px 0">
            <div style="font-size:32pt;font-weight:900;color:${esc(score.color)};font-family:Georgia,serif;line-height:1">${score.score}</div>
            <div style="font-size:10pt;font-weight:700;color:${esc(score.color)};margin:4px 0">${esc(score.level)}</div>
            <div style="font-size:8pt;color:#9CA3AF">Score d'engagement · /100</div>
          </div>
          <div style="margin-top:10px">
            <div style="display:flex;justify-content:space-between;font-size:9pt;padding:4px 0;border-bottom:1px solid #F1F5F9"><span style="color:#6B7280">Échanges actifs</span><strong>${Math.floor(messages.length/2)}</strong></div>
            <div style="display:flex;justify-content:space-between;font-size:9pt;padding:4px 0;border-bottom:1px solid #F1F5F9"><span style="color:#6B7280">Concepts identifiés</span><strong>${concepts.length}</strong></div>
            <div style="display:flex;justify-content:space-between;font-size:9pt;padding:4px 0"><span style="color:#6B7280">Sujet principal</span><strong>${esc(topic)}</strong></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Recommandations -->
    <div class="ped-card" style="margin-bottom:14px;border-color:#EEF4FD">
      <div class="ped-card-hd" style="background:#EEF4FD;color:#1E5FAD">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
        Recommandations personnalisées
      </div>
      <div class="ped-card-body">${recsHtml}</div>
    </div>

    <!-- Prochaines étapes -->
    <div class="ped-card" style="border-color:#F3F4F6">
      <div class="ped-card-hd" style="background:#F3F4F6;color:#374151">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        Prochaines étapes sur RÉUSSITE+
      </div>
      <div class="ped-card-body">
        ${nextSteps.map((s,i)=>`<div class="ped-item"><div class="ped-item-dot" style="background:#6B7280"></div><div>${esc(s)}</div></div>`).join('')}
      </div>
    </div>

  </div>
  <div class="doc-footer">
    <div class="doc-footer-brand">RÉUSSITE+ — Plateforme EdTech RDC · Coach IA</div>
    <div class="doc-footer-conf">Document généré automatiquement · confidentiel · usage pédagogique</div>
  </div>
</div>`;
  }

  // ── Générateur principal ──────────────────────────────────
  function generate(messages, titre, prenom) {
    const now      = new Date();
    const date     = now.toLocaleDateString('fr-FR', {weekday:'long', day:'2-digit', month:'long', year:'numeric'});
    const heure    = now.toLocaleTimeString('fr-FR', {hour:'2-digit', minute:'2-digit'});
    const topic    = detectMainTopic(messages);
    const concepts = extractKeyConceptsPdf(messages);
    const score    = assessLearning(messages);
    const recs     = autoRecommendations(messages, topic);
    const nbEch    = Math.floor(messages.length / 2);
    const docId    = 'RP-' + now.getFullYear() + String(now.getMonth()+1).padStart(2,'0') + String(now.getDate()).padStart(2,'0') + '-' + Math.random().toString(36).slice(2,7).toUpperCase();

    const data = { prenom, topic, concepts, score, recs, nbEchanges: nbEch, nbConcepts: concepts.length, messages, titre: titre || 'Session Coach IA', date, heure, docId };

    return `<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rapport Coach IA — ${esc(prenom)} — RÉUSSITE+</title>
${buildCSS(prenom)}
</head>
<body>
${buildCover(data)}
${nbEch > 0 ? buildSummary(data) : ''}
${buildConversation(data)}
${nbEch >= 2 ? buildPedagogy(data) : ''}
</body>
</html>`;
  }

  // ── Ouvrir + imprimer ─────────────────────────────────────
  function open(messages, titre, prenom) {
    if (!messages || !messages.length) {
      if (typeof toast === 'function') toast('Aucune conversation à exporter', 'error');
      return;
    }
    const html = generate(messages, titre, prenom);
    const win  = window.open('', '_blank');
    if (!win) { alert('Autorisez les pop-ups pour exporter le PDF.'); return; }
    win.document.write(html);
    win.document.close();
    win.focus();
    setTimeout(() => win.print(), 700);
  }

  return { open, generate };
})();
