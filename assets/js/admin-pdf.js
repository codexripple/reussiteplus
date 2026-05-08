/**
 * RÉUSSITE+ — Rapport d'Analyse Admin Premium
 * Même ADN visuel : ia-pdf.js / exam-pdf.js / receipt-pdf.js
 */

const AdminPdf = (() => {

  const esc = s => String(s ?? '')
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

  const fmt = n => new Intl.NumberFormat('fr-FR').format(Math.round(parseFloat(n) || 0));
  const fmtCdf = n => fmt(n) + ' CDF';

  function formatDate(d) {
    return d ? new Date(d).toLocaleDateString('fr-FR', { day:'2-digit', month:'long', year:'numeric' }) : '—';
  }

  function getTrend(val, neutral = 0) {
    if (val > neutral) return { arrow: '↑', color: '#007A5E', label: `+${val}%` };
    if (val < neutral) return { arrow: '↓', color: '#C9342A', label: `${val}%` };
    return { arrow: '→', color: '#6B7280', label: `${val}%` };
  }

  // ── CSS premium ───────────────────────────────────────────
  function buildCSS() {
    return `<style>
@page { size: A4; margin: 0; }
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'Helvetica Neue', Arial, sans-serif;
  color: #1a2535; font-size: 10.5pt; line-height: 1.65;
  background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact;
}
.page { width: 210mm; min-height: 297mm; position: relative; overflow: hidden; page-break-after: always; }
.page:last-child { page-break-after: auto; }

/* ── COVER ── */
.cover {
  background: linear-gradient(160deg, #0e1520 0%, #111827 55%, #0a1018 100%);
  display: flex; flex-direction: column; justify-content: space-between;
}
.cv-deco-1 { position:absolute;top:-60px;right:-60px;width:280px;height:280px;border-radius:50%;border:1px solid rgba(255,255,255,.04);background:radial-gradient(circle,rgba(124,58,237,.08) 0%,transparent 70%); }
.cv-deco-2 { position:absolute;bottom:40px;left:-40px;width:200px;height:200px;border-radius:50%;border:1px solid rgba(0,122,94,.08); }
.cv-top { padding:42px 48px 0;position:relative;z-index:1; }
.cv-brand { display:flex;align-items:center;gap:10px;margin-bottom:56px; }
.cv-brand-icon { width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#007A5E,#005A45);border:1px solid rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;box-shadow:0 2px 10px rgba(0,122,94,.4); }
.cv-brand-name { font-size:18pt;font-weight:900;color:#fff;letter-spacing:-.5px; }
.cv-brand-name span { color:#C9972A; }
.cv-brand-sub { font-size:8.5pt;color:rgba(255,255,255,.4);margin-top:2px;letter-spacing:.3px; }
.cv-badge { display:inline-flex;align-items:center;gap:6px;background:rgba(124,58,237,.2);border:1px solid rgba(124,58,237,.35);padding:4px 14px;border-radius:20px;font-size:8pt;color:#C4B5FD;font-weight:600;letter-spacing:.5px;margin-bottom:22px; }
.cv-title { font-size:26pt;font-weight:900;color:#fff;line-height:1.1;margin-bottom:8px;letter-spacing:-.5px; }
.cv-subtitle { font-size:11pt;color:rgba(255,255,255,.5);margin-bottom:40px; }
.cv-card { background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);border-radius:14px;padding:20px 24px;max-width:400px;display:grid;grid-template-columns:1fr 1fr;gap:14px 24px; }
.cv-field-lbl { font-size:7.5pt;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.8px;margin-bottom:3px; }
.cv-field-val { font-size:11pt;font-weight:700;color:#fff; }
.cv-field-val.violet { color:#C4B5FD; }
.cv-bottom { padding:22px 48px;border-top:1px solid rgba(255,255,255,.07);display:flex;justify-content:space-between;align-items:center;position:relative;z-index:1; }
.cv-stat { text-align:center; }
.cv-stat-num { font-size:18pt;font-weight:900;color:#fff; }
.cv-stat-lbl { font-size:7.5pt;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.5px; }
.cv-stat-div { width:1px;height:36px;background:rgba(255,255,255,.08); }
.cv-powered { font-size:8pt;color:rgba(255,255,255,.25);text-align:right;line-height:1.8; }

/* ── DOC PAGES ── */
.doc-page { }
.doc-hd { background:#fff;border-bottom:2px solid #007A5E;padding:11px 30px;display:flex;align-items:center;justify-content:space-between; }
.doc-hd-brand { font-size:11pt;font-weight:900;color:#007A5E; }
.doc-hd-brand span { color:#C9972A; }
.doc-hd-meta { font-size:7.5pt;color:#9CA3AF; }
.doc-body { padding:22px 30px 60px; }
.doc-ft { background:#F8FAFC;border-top:1px solid #E5E7EB;padding:8px 30px;display:flex;justify-content:space-between;position:absolute;bottom:0;left:0;right:0; }
.doc-ft-txt { font-size:7.5pt;color:#9CA3AF; }

/* ── SECTIONS ── */
.sec-hd { display:flex;align-items:center;gap:10px;margin-bottom:14px; }
.sec-icon { width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
.sec-title { font-size:9pt;font-weight:800;text-transform:uppercase;letter-spacing:1px; }
.sec-line { flex:1;height:1px; }

/* ── STATS GRID ── */
.stats-grid { display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:18px; }
.stat-box { border:1px solid #E5E7EB;border-radius:10px;padding:13px 15px;position:relative;overflow:hidden; }
.stat-box::after { content:'';position:absolute;top:0;left:0;right:0;height:2px;border-radius:10px 10px 0 0; }
.stat-box.green::after { background:#007A5E; } .stat-box.blue::after { background:#1E5FAD; }
.stat-box.gold::after  { background:#C9972A; } .stat-box.purple::after{ background:#7C3AED; }
.stat-box.red::after   { background:#C9342A; }
.stat-num { font-size:20pt;font-weight:900;line-height:1.1;margin-bottom:3px; }
.stat-lbl { font-size:7.5pt;color:#6B7280;font-weight:600;text-transform:uppercase;letter-spacing:.5px; }
.stat-trend { font-size:8.5pt;font-weight:700;margin-top:5px;display:flex;align-items:center;gap:3px; }

/* ── PLANS DONUT SIMULÉ ── */
.plans-grid { display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px; }
.plan-box { border:1px solid;border-radius:9px;padding:12px 14px;text-align:center; }
.plan-box-num { font-size:16pt;font-weight:900; }
.plan-box-name { font-size:8pt;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-top:2px; }
.plan-box-pct { font-size:9pt;color:#6B7280;margin-top:2px; }

/* ── REVENUS BARS ── */
.rev-chart { display:flex;align-items:flex-end;gap:8px;height:80px;margin-bottom:8px; }
.rev-bar-wrap { flex:1;display:flex;flex-direction:column;align-items:center;gap:4px; }
.rev-bar { width:100%;border-radius:4px 4px 0 0;min-height:4px;transition:height .3s; }
.rev-bar-lbl { font-size:7.5pt;color:#9CA3AF;white-space:nowrap; }
.rev-bar-val { font-size:7.5pt;font-weight:700;color:#1a2535; }

/* ── IA ANALYSIS ── */
.ia-box { background:linear-gradient(160deg,#0d1120,#111827);border:1px solid rgba(124,58,237,.25);border-radius:12px;padding:18px 20px;margin-bottom:16px; }
.ia-box-hd { display:flex;align-items:center;gap:8px;margin-bottom:12px; }
.ia-box-dot { width:8px;height:8px;border-radius:50%;background:#a78bfa;flex-shrink:0; }
.ia-box-title { font-size:9pt;font-weight:700;color:#C4B5FD;text-transform:uppercase;letter-spacing:.7px; }
.ia-box-badge { margin-left:auto;font-size:7.5pt;background:rgba(124,58,237,.2);border:1px solid rgba(124,58,237,.3);color:#C4B5FD;padding:2px 9px;border-radius:20px; }
.ia-box-text { font-size:10.5pt;color:rgba(255,255,255,.8);line-height:1.75;white-space:pre-wrap; }

/* ── RECOMMANDATIONS ── */
.rec-item { display:flex;align-items:flex-start;gap:10px;padding:9px 0;border-bottom:1px solid #F1F5F9;font-size:10pt;color:#374151; }
.rec-item:last-child { border:none; }
.rec-num { width:22px;height:22px;border-radius:50%;flex-shrink:0;background:linear-gradient(135deg,#007A5E,#005A45);display:flex;align-items:center;justify-content:center;font-size:8pt;font-weight:800;color:#fff; }
.rec-arrow { color:#007A5E;font-weight:800; }

/* ── ACTIVITÉ TABLE ── */
.act-table { width:100%;border-collapse:collapse; }
.act-table th { font-size:8pt;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#9CA3AF;padding:8px 12px;background:#F8FAFC;border-bottom:1px solid #E5E7EB; }
.act-table td { font-size:10pt;padding:8px 12px;border-bottom:1px solid #F1F5F9;color:#374151; }
.act-table tr:last-child td { border:none; }
.plan-chip { display:inline-block;padding:2px 9px;border-radius:5px;font-size:8pt;font-weight:700; }

/* ── PRINT ── */
@media print {
  @page { size:A4;margin:0; }
  body { -webkit-print-color-adjust:exact !important;print-color-adjust:exact !important; }
  .page { page-break-after:always; } .page:last-child { page-break-after:auto; }
}
</style>`;
  }

  // ── Page couverture ───────────────────────────────────────
  function buildCover(data) {
    const { adminName, totalUsers, revenus, examsToday, periodeLabel, now } = data;
    return `
<div class="page cover">
  <div class="cv-deco-1"></div>
  <div class="cv-deco-2"></div>
  <div class="cv-top">
    <div class="cv-brand">
      <div class="cv-brand-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
      </div>
      <div>
        <div class="cv-brand-name">RÉUSSITE<span>+</span></div>
        <div class="cv-brand-sub">PLATEFORME ÉDUCATIVE · RDC</div>
      </div>
    </div>
    <div class="cv-badge">
      <svg width="10" height="10" viewBox="0 0 24 24" fill="#C4B5FD" stroke="none"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
      Rapport d'Analyse IA — Administration
    </div>
    <div class="cv-title">Rapport d'Analyse<br>Intelligence Artificielle</div>
    <div class="cv-subtitle">Tableau de bord administrateur · ${esc(periodeLabel)}</div>
    <div style="position:relative">
      <div class="cv-card">
        <div>
          <div class="cv-field-lbl">Généré par</div>
          <div class="cv-field-val violet">${esc(adminName)}</div>
        </div>
        <div>
          <div class="cv-field-lbl">Date</div>
          <div class="cv-field-val" style="font-size:9.5pt">${esc(now)}</div>
        </div>
        <div>
          <div class="cv-field-lbl">Utilisateurs actifs</div>
          <div class="cv-field-val">${fmt(totalUsers)}</div>
        </div>
        <div>
          <div class="cv-field-lbl">Revenus ce mois</div>
          <div class="cv-field-val" style="font-size:9.5pt">${fmtCdf(revenus)}</div>
        </div>
      </div>
    </div>
  </div>
  <div class="cv-bottom">
    <div class="cv-stat">
      <div class="cv-stat-num">${fmt(totalUsers)}</div>
      <div class="cv-stat-lbl">Utilisateurs</div>
    </div>
    <div class="cv-stat-div"></div>
    <div class="cv-stat">
      <div class="cv-stat-num">${fmtCdf(revenus)}</div>
      <div class="cv-stat-lbl">Revenus mois</div>
    </div>
    <div class="cv-stat-div"></div>
    <div class="cv-stat">
      <div class="cv-stat-num">${fmt(examsToday)}</div>
      <div class="cv-stat-lbl">Examens /jour</div>
    </div>
    <div class="cv-stat-div"></div>
    <div class="cv-powered">
      Généré par<br>
      <strong style="color:rgba(255,255,255,.5)">RÉUSSITE+ Admin</strong><br>
      <span style="color:rgba(255,255,255,.25)">Propulsé par Gemini IA</span>
    </div>
  </div>
</div>`;
  }

  // ── Page résumé exécutif ──────────────────────────────────
  function buildSummary(data) {
    const { totalUsers, usersToday, users7j, revenus, revGrowth, examsToday, exams7j, paiementsAtt, convRate, adminName, periodeLabel } = data;
    const trend = getTrend(revGrowth);
    return `
<div class="page doc-page">
  <div class="doc-hd">
    <div class="doc-hd-brand">RÉUSSITE<span>+</span></div>
    <div class="doc-hd-meta">Rapport Admin IA · ${esc(adminName)} · ${esc(periodeLabel)}</div>
  </div>
  <div class="doc-body">

    <div class="sec-hd">
      <div class="sec-icon" style="background:#E8F5F1"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#007A5E" stroke-width="2.5" stroke-linecap="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg></div>
      <div class="sec-title" style="color:#007A5E">Résumé Exécutif</div>
      <div class="sec-line" style="background:#E8F5F1"></div>
    </div>

    <div class="stats-grid">
      <div class="stat-box green">
        <div class="stat-num" style="color:#007A5E">${fmt(totalUsers)}</div>
        <div class="stat-lbl">Utilisateurs actifs</div>
        <div class="stat-trend" style="color:#007A5E">↑ +${fmt(users7j)} cette semaine</div>
      </div>
      <div class="stat-box gold">
        <div class="stat-num" style="color:#C9972A">${fmtCdf(revenus)}</div>
        <div class="stat-lbl">Revenus ce mois</div>
        <div class="stat-trend" style="color:${trend.color}">${trend.arrow} ${esc(trend.label)} vs M-1</div>
      </div>
      <div class="stat-box blue">
        <div class="stat-num" style="color:#1E5FAD">${fmt(examsToday)}</div>
        <div class="stat-lbl">Examens aujourd'hui</div>
        <div class="stat-trend" style="color:#1E5FAD">↑ ${fmt(exams7j)} cette semaine</div>
      </div>
      <div class="stat-box purple">
        <div class="stat-num" style="color:#7C3AED">${parseFloat(convRate).toFixed(1)}%</div>
        <div class="stat-lbl">Taux conversion</div>
        <div class="stat-trend" style="color:${parseFloat(convRate)>=20?'#007A5E':'#C9342A'}">${parseFloat(convRate)>=20?'✓ Bon niveau':'⚠ À améliorer'}</div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px">
      <div style="border:1px solid #E5E7EB;border-radius:10px;padding:14px 16px">
        <div style="font-size:8.5pt;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:#9CA3AF;margin-bottom:10px">Indicateurs clés</div>
        <div style="display:flex;flex-direction:column;gap:7px;font-size:10pt">
          <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #F1F5F9"><span style="color:#6B7280">Inscrits aujourd'hui</span><strong>+${fmt(usersToday)}</strong></div>
          <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #F1F5F9"><span style="color:#6B7280">Inscrits 7 jours</span><strong>+${fmt(users7j)}</strong></div>
          <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #F1F5F9"><span style="color:#6B7280">Paiements en attente</span><strong style="color:${parseInt(paiementsAtt)>0?'#C9342A':'#007A5E'}">${fmt(paiementsAtt)}</strong></div>
          <div style="display:flex;justify-content:space-between;padding:4px 0"><span style="color:#6B7280">Examens / semaine</span><strong>${fmt(exams7j)}</strong></div>
        </div>
      </div>
      <div style="border:1px solid #E5E7EB;border-radius:10px;padding:14px 16px">
        <div style="font-size:8.5pt;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:#9CA3AF;margin-bottom:10px">Performance financière</div>
        <div style="text-align:center;padding:8px 0">
          <div style="font-size:22pt;font-weight:900;color:${trend.color};line-height:1">${trend.arrow} ${Math.abs(revGrowth)}%</div>
          <div style="font-size:9pt;color:#6B7280;margin-top:4px">${revGrowth >= 0 ? 'Croissance' : 'Baisse'} vs mois précédent</div>
          <div style="font-size:8pt;color:#9CA3AF;margin-top:8px">Revenus totaux : ${fmtCdf(revenus)}</div>
        </div>
      </div>
    </div>

  </div>
  <div class="doc-ft">
    <div class="doc-ft-txt">RÉUSSITE+ · Rapport Admin IA · ${esc(adminName)}</div>
    <div class="doc-ft-txt">Confidentiel — usage interne</div>
  </div>
</div>`;
  }

  // ── Page distribution utilisateurs ───────────────────────
  function buildUsersPage(data) {
    const { planStats, totalUsers, rev6m, adminName, periodeLabel } = data;
    const PLAN_COLORS = { PREMIUM:'#C9972A', ECOLE:'#007A5E', BASIQUE:'#1E5FAD', GRATUIT:'#6B7280' };
    const PLAN_BG    = { PREMIUM:'#FEF3C7', ECOLE:'#E8F5F1', BASIQUE:'#EEF4FD', GRATUIT:'#F3F4F6' };

    const plansHtml = Object.entries(planStats || {}).map(([plan, nb]) => {
      const pct = totalUsers > 0 ? ((nb / totalUsers) * 100).toFixed(1) : 0;
      const color = PLAN_COLORS[plan] || '#6B7280';
      const bg    = PLAN_BG[plan]    || '#F3F4F6';
      return `<div class="plan-box" style="border-color:${color}30;background:${bg}">
        <div class="plan-box-num" style="color:${color}">${fmt(nb)}</div>
        <div class="plan-box-name" style="color:${color}">${esc(plan)}</div>
        <div class="plan-box-pct">${pct}%</div>
      </div>`;
    }).join('');

    // Revenus 6 mois — barres simples
    const maxRev = Math.max(...(rev6m || []).map(r => parseFloat(r.total) || 0), 1);
    const revBarsHtml = (rev6m || []).map(r => {
      const h = Math.max(4, Math.round((parseFloat(r.total) / maxRev) * 72));
      return `<div class="rev-bar-wrap">
        <div class="rev-bar-val">${fmt(r.total)}</div>
        <div class="rev-bar" style="height:${h}px;background:#007A5E"></div>
        <div class="rev-bar-lbl">${esc(r.label || r.mois)}</div>
      </div>`;
    }).join('');

    return `
<div class="page doc-page">
  <div class="doc-hd">
    <div class="doc-hd-brand">RÉUSSITE<span>+</span></div>
    <div class="doc-hd-meta">Utilisateurs & Revenus · ${esc(periodeLabel)}</div>
  </div>
  <div class="doc-body">

    <div class="sec-hd">
      <div class="sec-icon" style="background:#EEF4FD"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#1E5FAD" stroke-width="2.5" stroke-linecap="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
      <div class="sec-title" style="color:#1E5FAD">Distribution des abonnements</div>
      <div class="sec-line" style="background:#EEF4FD"></div>
    </div>
    <div class="plans-grid">${plansHtml || '<p style="color:#9CA3AF;font-style:italic">Aucune donnée</p>'}</div>

    ${rev6m && rev6m.length > 0 ? `
    <div class="sec-hd" style="margin-top:16px">
      <div class="sec-icon" style="background:#E8F5F1"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#007A5E" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
      <div class="sec-title" style="color:#007A5E">Revenus — 6 derniers mois (CDF)</div>
      <div class="sec-line" style="background:#E8F5F1"></div>
    </div>
    <div style="border:1px solid #E5E7EB;border-radius:10px;padding:16px 20px">
      <div class="rev-chart">${revBarsHtml}</div>
    </div>` : ''}

  </div>
  <div class="doc-ft">
    <div class="doc-ft-txt">RÉUSSITE+ · Rapport Admin IA · ${esc(adminName)}</div>
    <div class="doc-ft-txt">Confidentiel — usage interne</div>
  </div>
</div>`;
  }

  // ── Page analyse IA ───────────────────────────────────────
  function buildIaPage(data) {
    const { iaText, adminName, periodeLabel, convRate, revGrowth, totalUsers } = data;
    const hasIa = iaText && iaText.trim().length > 20;

    // Recommandations auto si pas d'analyse IA
    const recs = hasIa ? [] : [
      { text: `Taux de conversion ${parseFloat(convRate).toFixed(1)}% — ${parseFloat(convRate) < 20 ? 'Améliorer l\'onboarding et les notifications push pour convertir plus d\'utilisateurs gratuits.' : 'Maintenir la qualité du contenu qui soutient ce bon taux de conversion.'}` },
      { text: `Revenus : ${revGrowth >= 0 ? `Croissance +${revGrowth}% — maintenir la dynamique via des campagnes de renouvellement.` : `Baisse de ${Math.abs(revGrowth)}% — revoir la stratégie tarifaire et les offres promotionnelles.`}` },
      { text: `Base utilisateurs : ${fmt(totalUsers)} inscrits. Analyser la cohorte des utilisateurs inactifs (>30j sans connexion) et lancer une campagne de réactivation.` },
      { text: `Optimiser le tunnel de paiement Mobile Money pour réduire l\'abandon de panier lors de la souscription Premium.` },
    ];

    const recsHtml = recs.map((r, i) => `
      <div class="rec-item">
        <div class="rec-num">${i+1}</div>
        <div>${esc(r.text)}</div>
      </div>`).join('');

    return `
<div class="page doc-page">
  <div class="doc-hd">
    <div class="doc-hd-brand">RÉUSSITE<span>+</span></div>
    <div class="doc-hd-meta">Analyse IA · ${esc(periodeLabel)}</div>
  </div>
  <div class="doc-body">

    <div class="sec-hd">
      <div class="sec-icon" style="background:#EDE9FE"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#7C3AED" stroke-width="2.5" stroke-linecap="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/><path d="M19 3v4"/><path d="M21 5h-4"/></svg></div>
      <div class="sec-title" style="color:#7C3AED">Analyse Intelligence Artificielle</div>
      <div class="sec-line" style="background:#EDE9FE"></div>
    </div>

    ${hasIa ? `
    <div class="ia-box">
      <div class="ia-box-hd">
        <div class="ia-box-dot"></div>
        <div class="ia-box-title">Analyse approfondie — Gemini IA</div>
        <div class="ia-box-badge">Gemini 2.0 Flash</div>
      </div>
      <div class="ia-box-text">${esc(iaText)}</div>
    </div>` : `
    <div style="border:1px solid #EDE9FE;border-radius:10px;padding:16px;margin-bottom:16px;background:#FAFAFA">
      <div style="font-size:10pt;color:#6B7280;font-style:italic;text-align:center">Lancez l'analyse IA depuis le tableau de bord pour inclure l'analyse approfondie dans ce rapport.</div>
    </div>`}

    <div class="sec-hd" style="margin-top:${hasIa?'16':'0'}px">
      <div class="sec-icon" style="background:#E8F5F1"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#007A5E" stroke-width="2.5" stroke-linecap="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg></div>
      <div class="sec-title" style="color:#007A5E">Recommandations Stratégiques</div>
      <div class="sec-line" style="background:#E8F5F1"></div>
    </div>

    <div style="border:1px solid #E5E7EB;border-radius:10px;overflow:hidden">
      <div style="padding:10px 16px;background:#F0F7F4;border-bottom:1px solid #E5E7EB;font-size:8.5pt;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:#005A45">
        Actions prioritaires
      </div>
      <div style="padding:6px 16px">
        ${hasIa ? `<div style="font-size:10pt;color:#374151;line-height:1.7;padding:8px 0">
          Voir l'analyse IA ci-dessus pour les recommandations personnalisées.
        </div>` : recsHtml}
      </div>
    </div>

  </div>
  <div class="doc-ft">
    <div class="doc-ft-txt">RÉUSSITE+ — Plateforme EdTech RDC · Rapport Admin</div>
    <div class="doc-ft-txt">Confidentiel — usage interne · ${new Date().toLocaleDateString('fr-FR')}</div>
  </div>
</div>`;
  }

  // ── Générateur principal ──────────────────────────────────
  function generate(reportData) {
    const now = new Date().toLocaleDateString('fr-FR', { weekday:'long', day:'2-digit', month:'long', year:'numeric' });
    const data = { ...reportData, now };

    return `<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rapport Admin IA — RÉUSSITE+ — ${esc(now)}</title>
${buildCSS()}
</head>
<body>
${buildCover(data)}
${buildSummary(data)}
${buildUsersPage(data)}
${buildIaPage(data)}
</body>
</html>`;
  }

  // ── Ouvrir + imprimer ─────────────────────────────────────
  function open(reportData) {
    const html = generate(reportData);
    const win  = window.open('', '_blank');
    if (!win) { alert('Autorisez les pop-ups pour exporter le rapport.'); return; }
    win.document.write(html);
    win.document.close();
    win.focus();
    setTimeout(() => win.print(), 700);
  }

  return { open, generate };
})();
