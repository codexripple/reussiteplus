/**
 * RÉUSSITE+ — Relevé de devoirs PDF Premium
 * Même ADN visuel : ia-pdf / exam-pdf / receipt-pdf
 */
const DevoirsPdf = (() => {

  const esc = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

  const TYPE_COLORS = {
    DEVOIR:'#1E5FAD', CONTROLE:'#7C3AED', EXAM:'#C9342A', PROJET:'#059669', EXPOSE:'#C9972A'
  };
  const TYPE_LABELS = {
    DEVOIR:'Devoir', CONTROLE:'Contrôle', EXAM:'Examen', PROJET:'Projet', EXPOSE:'Exposé'
  };
  const STATUT_LABELS = {
    SOUMIS:'Soumis', EN_RETARD:'En retard', CORRIGE:'Corrigé'
  };

  function formatDate(s) {
    if (!s) return '—';
    try { return new Date(s).toLocaleDateString('fr-FR',{day:'2-digit',month:'long',year:'numeric'}); } catch { return s; }
  }

  function buildCSS() {
    return `<style>
@page { size: A4; margin: 0; }
*,*::before,*::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Helvetica Neue', Arial, sans-serif; color: #1a2535; font-size: 10.5pt; line-height: 1.65; background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.page { width: 210mm; min-height: 297mm; position: relative; overflow: hidden; page-break-after: always; }
.page:last-child { page-break-after: auto; }

/* Cover */
.cv { background: linear-gradient(160deg, #0f172a 0%, #1e3a5f 60%, #0a1628 100%); display: flex; flex-direction: column; justify-content: space-between; }
.cv-deco { position: absolute; top: -50px; right: -50px; width: 240px; height: 240px; border-radius: 50%; border: 1px solid rgba(255,255,255,.04); background: radial-gradient(circle, rgba(30,95,173,.12) 0%, transparent 70%); }
.cv-top { padding: 42px 48px 0; position: relative; z-index: 1; }
.cv-brand { display: flex; align-items: center; gap: 10px; margin-bottom: 52px; }
.cv-brand-icon { width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(135deg, #007A5E, #005A45); border: 1px solid rgba(255,255,255,.2); display: flex; align-items: center; justify-content: center; }
.cv-brand-name { font-size: 18pt; font-weight: 900; color: #fff; letter-spacing: -.5px; }
.cv-brand-name span { color: #C9972A; }
.cv-brand-sub { font-size: 8.5pt; color: rgba(255,255,255,.4); margin-top: 2px; }
.cv-badge { display: inline-flex; align-items: center; gap: 6px; background: rgba(30,95,173,.25); border: 1px solid rgba(30,95,173,.45); padding: 4px 14px; border-radius: 20px; font-size: 8pt; color: #93c5fd; font-weight: 600; margin-bottom: 22px; }
.cv-title { font-size: 26pt; font-weight: 900; color: #fff; line-height: 1.1; margin-bottom: 8px; letter-spacing: -.5px; }
.cv-sub { font-size: 11pt; color: rgba(255,255,255,.5); margin-bottom: 36px; }
.cv-card { background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.12); border-radius: 14px; padding: 18px 22px; max-width: 380px; display: grid; grid-template-columns: 1fr 1fr; gap: 14px 24px; }
.cv-lbl { font-size: 7.5pt; color: rgba(255,255,255,.35); text-transform: uppercase; letter-spacing: .8px; margin-bottom: 3px; }
.cv-val { font-size: 11pt; font-weight: 700; color: #fff; }
.cv-val.blue { color: #93c5fd; }
.cv-bottom { padding: 22px 48px; border-top: 1px solid rgba(255,255,255,.07); display: flex; justify-content: space-between; align-items: center; position: relative; z-index: 1; }
.cv-stat { text-align: center; }
.cv-stat-num { font-size: 18pt; font-weight: 900; color: #fff; }
.cv-stat-lbl { font-size: 7.5pt; color: rgba(255,255,255,.35); text-transform: uppercase; letter-spacing: .5px; }
.cv-div { width: 1px; height: 36px; background: rgba(255,255,255,.08); }

/* Doc pages */
.doc-hd { background: #fff; border-bottom: 2px solid #1E5FAD; padding: 11px 30px; display: flex; align-items: center; justify-content: space-between; }
.doc-hd-brand { font-size: 11pt; font-weight: 900; color: #1E5FAD; }
.doc-hd-brand span { color: #C9972A; }
.doc-hd-meta { font-size: 7.5pt; color: #9CA3AF; }
.doc-body { padding: 22px 30px 60px; }
.doc-ft { background: #F8FAFC; border-top: 1px solid #E5E7EB; padding: 8px 30px; display: flex; justify-content: space-between; position: absolute; bottom: 0; left: 0; right: 0; font-size: 7.5pt; color: #9CA3AF; }

/* Section headers */
.sec-hd { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
.sec-icon { width: 28px; height: 28px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.sec-title { font-size: 9pt; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; }
.sec-line { flex: 1; height: 1px; }

/* Stats */
.stats-row { display: grid; grid-template-columns: repeat(4,1fr); gap: 12px; margin-bottom: 18px; }
.stat-box { border: 1px solid #E5E7EB; border-radius: 10px; padding: 12px; text-align: center; }
.stat-num { font-size: 18pt; font-weight: 900; line-height: 1.1; }
.stat-lbl { font-size: 7.5pt; color: #6B7280; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; margin-top: 3px; }

/* Devoir items */
.dv-item { border: 1px solid #E5E7EB; border-radius: 10px; overflow: hidden; margin-bottom: 12px; page-break-inside: avoid; }
.dv-header { padding: 9px 14px; display: flex; align-items: center; gap: 10px; }
.dv-type-badge { font-size: 8pt; font-weight: 800; padding: 2px 9px; border-radius: 5px; text-transform: uppercase; letter-spacing: .4px; }
.dv-classe { font-size: 8pt; color: #6B7280; background: #F3F4F6; padding: 2px 8px; border-radius: 5px; }
.dv-statut { margin-left: auto; font-size: 8pt; font-weight: 700; padding: 2px 9px; border-radius: 5px; }
.dv-body { padding: 10px 14px; }
.dv-titre { font-size: 11pt; font-weight: 700; color: #1a2535; margin-bottom: 5px; }
.dv-meta { display: flex; gap: 16px; font-size: 9pt; color: #6B7280; flex-wrap: wrap; margin-bottom: 6px; }
.dv-note { background: linear-gradient(135deg, #E8F5F1, #F0FAF7); border: 1px solid rgba(0,122,94,.15); border-radius: 7px; padding: 8px 12px; margin-top: 8px; }
.dv-note-num { font-size: 14pt; font-weight: 900; color: #007A5E; }
.dv-note-lbl { font-size: 8pt; color: #6B7280; margin-top: 2px; }
.dv-feedback { margin-top: 8px; background: #EEF4FD; border-left: 3px solid #1E5FAD; padding: 8px 12px; font-size: 9.5pt; color: #1a2535; line-height: 1.6; border-radius: 0 7px 7px 0; }
.dv-feedback-lbl { font-size: 7.5pt; font-weight: 700; color: #1E5FAD; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 4px; }

@media print { @page{size:A4;margin:0} body{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important} .page{page-break-after:always} .page:last-child{page-break-after:auto} .dv-item{page-break-inside:avoid} }
</style>`;
  }

  function buildCover(d) {
    const { prenom, stats, now, periode } = d;
    return `
<div class="page cv">
  <div class="cv-deco"></div>
  <div class="cv-top">
    <div class="cv-brand">
      <div class="cv-brand-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg></div>
      <div><div class="cv-brand-name">RÉUSSITE<span>+</span></div><div class="cv-brand-sub">PLATEFORME ÉDUCATIVE · RDC</div></div>
    </div>
    <div class="cv-badge"><svg width="9" height="9" viewBox="0 0 24 24" fill="#93c5fd" stroke="none"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>Relevé de Devoirs Premium</div>
    <div class="cv-title">Relevé de Devoirs</div>
    <div class="cv-sub">Suivi pédagogique personnel · ${esc(periode)}</div>
    <div class="cv-card">
      <div><div class="cv-lbl">Élève</div><div class="cv-val blue">${esc(prenom)}</div></div>
      <div><div class="cv-lbl">Généré le</div><div class="cv-val" style="font-size:9.5pt">${esc(now)}</div></div>
      <div><div class="cv-lbl">Total devoirs</div><div class="cv-val">${stats.total}</div></div>
      <div><div class="cv-lbl">Taux soumission</div><div class="cv-val">${stats.total > 0 ? Math.round(stats.soumis/stats.total*100) : 0}%</div></div>
    </div>
  </div>
  <div class="cv-bottom">
    <div class="cv-stat"><div class="cv-stat-num">${stats.total}</div><div class="cv-stat-lbl">Devoirs</div></div>
    <div class="cv-div"></div>
    <div class="cv-stat"><div class="cv-stat-num" style="color:#4ade80">${stats.soumis}</div><div class="cv-stat-lbl">Soumis</div></div>
    <div class="cv-div"></div>
    <div class="cv-stat"><div class="cv-stat-num" style="color:#a78bfa">${stats.corriges}</div><div class="cv-stat-lbl">Corrigés</div></div>
    <div class="cv-div"></div>
    <div class="cv-stat"><div class="cv-stat-num" style="color:#fbbf24">${stats.en_attente}</div><div class="cv-stat-lbl">En attente</div></div>
  </div>
</div>`;
  }

  function buildDevoirs(d) {
    const { prenom, devoirs, stats, now } = d;
    const PER_PAGE = 5;
    const pages = [];
    for (let i = 0; i < devoirs.length; i += PER_PAGE) pages.push(devoirs.slice(i, i + PER_PAGE));
    if (!pages.length) pages.push([]);

    return pages.map((chunk, pi) => {
      const items = chunk.map(dv => {
        const color = TYPE_COLORS[dv.type] || '#6B7280';
        const type  = TYPE_LABELS[dv.type] || dv.type;
        const isLate= !dv.soumis && dv.date_remise && new Date(dv.date_remise) < new Date();
        const statutStyle = dv.soumis_statut === 'CORRIGE' ? 'background:#E8F5F1;color:#005A45' :
                            dv.soumis_statut === 'EN_RETARD' ? 'background:#FEE2E2;color:#991B1B' :
                            dv.soumission_id ? 'background:#DBEAFE;color:#1e40af' :
                            isLate ? 'background:#FEE2E2;color:#991B1B' : 'background:#F3F4F6;color:#6B7280';
        const statutLabel = dv.soumis_statut ? STATUT_LABELS[dv.soumis_statut] :
                            isLate ? 'Expiré' : (dv.date_remise ? 'En cours' : 'Pas de date');
        return `<div class="dv-item">
          <div class="dv-header" style="background:${color}10">
            <span class="dv-type-badge" style="background:${color}18;color:${color}">${esc(type)}</span>
            <span class="dv-classe">${esc(dv.classe_nom || '')}</span>
            <span class="dv-statut" style="${statutStyle}">${esc(statutLabel)}</span>
          </div>
          <div class="dv-body">
            <div class="dv-titre">${esc(dv.titre || '')}</div>
            <div class="dv-meta">
              ${dv.matiere ? `<span>Matière : ${esc(dv.matiere)}</span>` : ''}
              ${dv.date_remise ? `<span>Date limite : ${formatDate(dv.date_remise)}</span>` : ''}
              ${dv.soumis_le ? `<span>Soumis le : ${formatDate(dv.soumis_le)}</span>` : ''}
              ${dv.points_max ? `<span>Barème : /${dv.points_max} pts</span>` : ''}
            </div>
            ${dv.note !== null && dv.note !== undefined ? `
            <div class="dv-note">
              <div class="dv-note-num">${dv.note}/${dv.points_max || 20}</div>
              <div class="dv-note-lbl">Note obtenue</div>
            </div>` : ''}
            ${dv.feedback ? `
            <div class="dv-feedback">
              <div class="dv-feedback-lbl">Correction de l'enseignant</div>
              ${esc(dv.feedback)}
            </div>` : ''}
          </div>
        </div>`;
      }).join('');

      // Stats globales sur la 1ère page de devoirs
      const statsHtml = pi === 0 ? `
      <div class="stats-row">
        <div class="stat-box"><div class="stat-num" style="color:#1E5FAD">${stats.total}</div><div class="stat-lbl">Total</div></div>
        <div class="stat-box"><div class="stat-num" style="color:#4ade80">${stats.soumis}</div><div class="stat-lbl">Soumis</div></div>
        <div class="stat-box"><div class="stat-num" style="color:#a78bfa">${stats.corriges}</div><div class="stat-lbl">Corrigés</div></div>
        <div class="stat-box"><div class="stat-num" style="color:#fbbf24">${stats.en_attente}</div><div class="stat-lbl">En attente</div></div>
      </div>` : '';

      return `
<div class="page">
  <div class="doc-hd"><div class="doc-hd-brand">RÉUSSITE<span>+</span></div><div class="doc-hd-meta">Relevé de devoirs · ${esc(prenom)}${pi > 0 ? ` · suite ${pi+1}` : ''}</div></div>
  <div class="doc-body">
    ${pi === 0 ? `
    <div class="sec-hd">
      <div class="sec-icon" style="background:#EEF4FD"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#1E5FAD" stroke-width="2.5" stroke-linecap="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></div>
      <div class="sec-title" style="color:#1E5FAD">Liste des devoirs</div>
      <div class="sec-line" style="background:#EEF4FD"></div>
    </div>` : ''}
    ${statsHtml}
    ${items || '<p style="color:#9CA3AF;text-align:center;padding:24px 0;font-style:italic">Aucun devoir à afficher.</p>'}
  </div>
  <div class="doc-ft"><span>RÉUSSITE+ · Relevé de devoirs · ${esc(prenom)}</span><span>Document confidentiel · ${esc(now)}</span></div>
</div>`;
    }).join('');
  }

  function generate(data) {
    const now = new Date().toLocaleDateString('fr-FR', {day:'2-digit',month:'long',year:'numeric'});
    return `<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Relevé Devoirs — ${esc(data.prenom)} — RÉUSSITE+</title>${buildCSS()}</head><body>${buildCover({...data,now})}${buildDevoirs({...data,now})}</body></html>`;
  }

  function open(data) {
    if (!data?.devoirs) { alert('Aucun devoir à exporter.'); return; }
    const html = generate(data);
    const win = window.open('','_blank');
    if (!win) { alert('Autorisez les pop-ups pour exporter.'); return; }
    win.document.write(html); win.document.close(); win.focus();
    setTimeout(() => win.print(), 700);
  }

  return { open, generate };
})();
