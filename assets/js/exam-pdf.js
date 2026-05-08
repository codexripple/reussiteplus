/**
 * RÉUSSITE+ — Exam PDF Premium Generator
 * Rapport d'examen académique haute qualité
 */

const ExamPdf = (() => {

  const esc = s => String(s ?? '')
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

  // ── Niveau estimé selon le score ─────────────────────────
  function getNiveau(pct) {
    if (pct >= 85) return { label:'Excellent',      color:'#007A5E', bg:'#E8F5F1', icon:'🏆' };
    if (pct >= 70) return { label:'Bien',           color:'#1E5FAD', bg:'#EEF4FD', icon:'⭐' };
    if (pct >= 55) return { label:'Satisfaisant',   color:'#C9972A', bg:'#FFF8EC', icon:'📈' };
    if (pct >= 40) return { label:'Insuffisant',    color:'#C9342A', bg:'#FEF0EF', icon:'🎯' };
    return          { label:'À améliorer',          color:'#6B7280', bg:'#F3F4F6', icon:'💪' };
  }

  // ── Recommandations auto ──────────────────────────────────
  function getRecommendations(pct, bonnes, total, wrongTopics) {
    const recs = [];
    if (pct < 60) recs.push('Revoir les leçons de base de cette matière avant de repasser un examen.');
    if (pct >= 60 && pct < 80) recs.push('Concentre-toi sur les questions où tu as hésité pour consolider tes acquis.');
    if (pct >= 80) recs.push('Excellent travail ! Passe aux questions de niveau Avancé pour progresser davantage.');
    if (wrongTopics.length > 0) recs.push(`Révise particulièrement : ${wrongTopics.slice(0,3).join(', ')}.`);
    recs.push('Utilise la banque de 1 051 questions EXETAT sur RÉUSSITE+ pour t\'entraîner.');
    recs.push('Génère un plan de révision personnalisé avec le Coach IA.');
    return recs.slice(0, 4);
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
  background: linear-gradient(160deg, #007A5E 0%, #005A45 55%, #003D30 100%);
  display: flex; flex-direction: column; justify-content: space-between;
}
.cover-deco-1 {
  position: absolute; top: -50px; right: -50px;
  width: 250px; height: 250px; border-radius: 50%;
  border: 1px solid rgba(255,255,255,.06);
  background: radial-gradient(circle, rgba(255,255,255,.05) 0%, transparent 70%);
}
.cover-deco-2 {
  position: absolute; bottom: 60px; left: -40px;
  width: 180px; height: 180px; border-radius: 50%;
  border: 1px solid rgba(201,151,42,.1);
}
.cover-top { padding: 42px 48px 0; position: relative; z-index: 1; }
.cover-brand { display: flex; align-items: center; gap: 10px; margin-bottom: 52px; }
.cover-brand-icon {
  width: 40px; height: 40px; border-radius: 10px;
  background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.3);
  display: flex; align-items: center; justify-content: center;
}
.cover-brand-name { font-size: 18pt; font-weight: 900; color: #fff; letter-spacing: -.5px; }
.cover-brand-name span { color: #C9972A; }
.cover-brand-sub { font-size: 9pt; opacity: .55; margin-top: 2px; letter-spacing: .3px; color: #fff; }
.cover-badge {
  display: inline-flex; align-items: center; gap: 6px;
  background: rgba(201,151,42,.18); border: 1px solid rgba(201,151,42,.35);
  padding: 4px 14px; border-radius: 20px;
  font-size: 8pt; color: #F5D78E; font-weight: 600; letter-spacing: .5px;
  margin-bottom: 24px;
}
.cover-title { font-size: 24pt; font-weight: 800; color: #fff; line-height: 1.2; margin-bottom: 8px; letter-spacing: -.3px; }
.cover-subtitle { font-size: 11pt; color: rgba(255,255,255,.6); margin-bottom: 40px; }
.cover-card {
  background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.18);
  border-radius: 14px; padding: 20px 24px;
  max-width: 380px; display: grid; grid-template-columns: 1fr 1fr; gap: 14px 24px;
}
.cover-field-lbl { font-size: 7.5pt; color: rgba(255,255,255,.4); text-transform: uppercase; letter-spacing: .8px; margin-bottom: 3px; }
.cover-field-val { font-size: 11pt; font-weight: 700; color: #fff; }
.cover-field-val.gold { color: #F5D78E; }
.cover-score-circle {
  width: 90px; height: 90px; border-radius: 50%;
  background: rgba(255,255,255,.12); border: 2px solid rgba(255,255,255,.22);
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  position: absolute; right: 48px; top: 50%;
}
.cover-score-num { font-size: 20pt; font-weight: 900; color: #fff; line-height: 1; }
.cover-score-lbl { font-size: 8pt; color: rgba(255,255,255,.5); margin-top: 2px; text-transform: uppercase; }
.cover-bottom {
  padding: 22px 48px; border-top: 1px solid rgba(255,255,255,.1);
  display: flex; justify-content: space-between; align-items: center;
  position: relative; z-index: 1;
}
.cover-stat { text-align: center; }
.cover-stat-num { font-size: 18pt; font-weight: 900; color: #fff; }
.cover-stat-lbl { font-size: 7.5pt; color: rgba(255,255,255,.4); text-transform: uppercase; letter-spacing: .5px; }
.cover-stat-div { width: 1px; height: 36px; background: rgba(255,255,255,.12); }
.cover-powered { font-size: 8pt; color: rgba(255,255,255,.3); text-align: right; line-height: 1.8; }

/* ── DOC PAGES (header/footer) ── */
.doc-page { padding: 0; }
.doc-hd {
  background: #fff; border-bottom: 2px solid #007A5E;
  padding: 11px 30px; display: flex; align-items: center; justify-content: space-between;
}
.doc-hd-brand { font-size: 11pt; font-weight: 900; color: #007A5E; }
.doc-hd-brand span { color: #C9972A; }
.doc-hd-meta { font-size: 7.5pt; color: #9CA3AF; }
.doc-ft {
  background: #F8FAFC; border-top: 1px solid #E5E7EB;
  padding: 8px 30px; display: flex; justify-content: space-between;
  position: absolute; bottom: 0; left: 0; right: 0;
}
.doc-ft-text { font-size: 7.5pt; color: #9CA3AF; }
.doc-body { padding: 22px 30px 56px; }

/* ── SECTION HEADERS ── */
.sec-hd { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
.sec-icon { width: 28px; height: 28px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.sec-title { font-size: 9pt; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; }
.sec-line { flex: 1; height: 1px; }

/* ── SUMMARY CARDS ── */
.sum-grid { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 12px; margin-bottom: 18px; }
.sum-card { border: 1px solid #E5E7EB; border-radius: 10px; padding: 12px 14px; text-align: center; }
.sum-card-num { font-size: 20pt; font-weight: 900; line-height: 1.1; margin-bottom: 3px; }
.sum-card-lbl { font-size: 8pt; color: #6B7280; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; }
.sum-level-card {
  background: linear-gradient(135deg, #F0F7F4, #E8F5F1);
  border: 1px solid rgba(0,122,94,.15);
  border-radius: 10px; padding: 14px 18px;
  display: flex; align-items: center; gap: 14px; margin-bottom: 18px;
}
.sum-level-icon { font-size: 22pt; }
.sum-level-label { font-size: 14pt; font-weight: 800; }
.sum-level-sub { font-size: 9pt; color: #6B7280; margin-top: 2px; }
.sum-prog-track { height: 8px; background: #E5E7EB; border-radius: 99px; overflow: hidden; margin-top: 6px; }
.sum-prog-fill { height: 100%; border-radius: 99px; }

/* ── QUESTION ITEMS ── */
.q-item { margin-bottom: 16px; page-break-inside: avoid; border: 1px solid #E5E7EB; border-radius: 10px; overflow: hidden; }
.q-header { padding: 9px 14px; display: flex; align-items: center; gap: 10px; }
.q-header.correct { background: #E8F5F1; border-bottom: 1px solid rgba(0,122,94,.15); }
.q-header.wrong   { background: #FEF0EF; border-bottom: 1px solid rgba(201,52,42,.15); }
.q-num { font-size: 8pt; font-weight: 800; }
.q-num.correct { color: #005A45; }
.q-num.wrong   { color: #C9342A; }
.q-badge {
  font-size: 7.5pt; font-weight: 700; padding: 1px 8px; border-radius: 4px;
  text-transform: uppercase; letter-spacing: .4px;
}
.q-badge.correct { background: rgba(0,122,94,.12); color: #005A45; }
.q-badge.wrong   { background: rgba(201,52,42,.1); color: #C9342A; }
.q-diff { font-size: 7pt; color: #9CA3AF; margin-left: auto; }
.q-body { padding: 10px 14px; }
.q-enonce { font-size: 10.5pt; color: #1a2535; line-height: 1.6; margin-bottom: 10px; }
.q-answers { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 8px; }
.q-ans-chip { font-size: 9.5pt; padding: 4px 10px; border-radius: 6px; }
.q-ans-chip.user-correct { background: #E8F5F1; color: #005A45; border: 1px solid rgba(0,122,94,.2); }
.q-ans-chip.user-wrong   { background: #FEF0EF; color: #C9342A; border: 1px solid rgba(201,52,42,.2); }
.q-ans-chip.correct-ans  { background: #E8F5F1; color: #005A45; border: 1px solid rgba(0,122,94,.2); }
.q-expl { background: #EEF4FD; border-left: 3px solid #1E5FAD; padding: 8px 12px; border-radius: 0 6px 6px 0; font-size: 9.5pt; color: #1a2535; line-height: 1.6; margin-top: 6px; }
.q-expl-lbl { font-weight: 700; color: #1E5FAD; margin-bottom: 3px; font-size: 8pt; text-transform: uppercase; letter-spacing: .5px; }

/* ── PEDAGOGY ── */
.ped-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px; }
.ped-card { border: 1px solid #E5E7EB; border-radius: 10px; overflow: hidden; }
.ped-card-hd { padding: 9px 14px; font-size: 8.5pt; font-weight: 700; text-transform: uppercase; letter-spacing: .7px; display: flex; align-items: center; gap: 6px; }
.ped-card-body { padding: 12px 14px; }
.ped-item { display: flex; align-items: flex-start; gap: 8px; padding: 6px 0; border-bottom: 1px solid #F1F5F9; font-size: 10pt; color: #374151; }
.ped-item:last-child { border: none; }
.ped-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; margin-top: 5px; }
.rec-item { display: flex; align-items: flex-start; gap: 9px; padding: 7px 0; border-bottom: 1px solid #F1F5F9; font-size: 10pt; color: #374151; }
.rec-item:last-child { border: none; }
.rec-num { width: 20px; height: 20px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 8pt; font-weight: 800; color: #fff; background: linear-gradient(135deg, #007A5E, #005A45); }

/* ── PRINT ── */
@media print {
  @page { size: A4; margin: 0; }
  body { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
  .page { page-break-after: always; }
  .page:last-child { page-break-after: auto; }
  .q-item { page-break-inside: avoid; }
}
</style>`;
  }

  // ── PAGE COUVERTURE ───────────────────────────────────────
  function buildCover(data) {
    const { prenom, matiere, titre, date, heure, pct, bonnes, total, mins, secs, niveau, examType } = data;
    const scoreColor = pct >= 70 ? '#fff' : pct >= 50 ? '#F5D78E' : '#FCA5A5';
    return `
<div class="page cover">
  <div class="cover-deco-1"></div>
  <div class="cover-deco-2"></div>
  <div class="cover-top">
    <div class="cover-brand">
      <div class="cover-brand-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
      </div>
      <div>
        <div class="cover-brand-name">RÉUSSITE<span>+</span></div>
        <div class="cover-brand-sub">PLATEFORME ÉDUCATIVE · RDC</div>
      </div>
    </div>

    <div class="cover-badge">
      <svg width="10" height="10" viewBox="0 0 24 24" fill="#F5D78E" stroke="none"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
      Rapport d'Examen Premium
    </div>
    <div class="cover-title">${esc(titre || matiere || 'Examen')}</div>
    <div class="cover-subtitle">${esc(matiere)}${examType ? ' · ' + esc(examType) : ''}</div>

    <div style="position:relative">
      <div class="cover-card">
        <div>
          <div class="cover-field-lbl">Élève</div>
          <div class="cover-field-val gold">${esc(prenom)}</div>
        </div>
        <div>
          <div class="cover-field-lbl">Date</div>
          <div class="cover-field-val" style="font-size:9.5pt">${esc(date)}</div>
        </div>
        <div>
          <div class="cover-field-lbl">Matière</div>
          <div class="cover-field-val" style="font-size:9.5pt">${esc(matiere)}</div>
        </div>
        <div>
          <div class="cover-field-lbl">Heure</div>
          <div class="cover-field-val">${esc(heure)}</div>
        </div>
        <div>
          <div class="cover-field-lbl">Niveau</div>
          <div class="cover-field-val" style="font-size:9.5pt">${niveau.icon} ${niveau.label}</div>
        </div>
        <div>
          <div class="cover-field-lbl">Durée</div>
          <div class="cover-field-val">${mins}m ${String(secs).padStart(2,'0')}s</div>
        </div>
      </div>
      <div class="cover-score-circle">
        <div class="cover-score-num" style="color:${scoreColor}">${pct.toFixed(1)}%</div>
        <div class="cover-score-lbl">Score</div>
      </div>
    </div>
  </div>

  <div class="cover-bottom">
    <div class="cover-stat">
      <div class="cover-stat-num">${bonnes}</div>
      <div class="cover-stat-lbl">Bonnes rép.</div>
    </div>
    <div class="cover-stat-div"></div>
    <div class="cover-stat">
      <div class="cover-stat-num">${total - bonnes}</div>
      <div class="cover-stat-lbl">À revoir</div>
    </div>
    <div class="cover-stat-div"></div>
    <div class="cover-stat">
      <div class="cover-stat-num">${total}</div>
      <div class="cover-stat-lbl">Questions</div>
    </div>
    <div class="cover-stat-div"></div>
    <div class="cover-powered">
      Généré par<br>
      <strong style="color:rgba(255,255,255,.6)">RÉUSSITE+</strong><br>
      <span style="color:rgba(255,255,255,.3)">Rapport Premium</span>
    </div>
  </div>
</div>`;
  }

  // ── PAGE RÉSUMÉ ───────────────────────────────────────────
  function buildSummary(data) {
    const { prenom, matiere, pct, bonnes, total, score, scoreMax, mins, secs, niveau, pageNum } = data;
    const taux   = total > 0 ? Math.round((bonnes / total) * 100) : 0;
    const fillW  = Math.min(100, pct);
    const fillColor = pct >= 70 ? '#007A5E' : pct >= 50 ? '#C9972A' : '#C9342A';
    return `
<div class="page doc-page">
  <div class="doc-hd">
    <div class="doc-hd-brand">RÉUSSITE<span>+</span></div>
    <div class="doc-hd-meta">Rapport d'examen · ${esc(prenom)} · ${esc(matiere)}</div>
  </div>
  <div class="doc-body">

    <div class="sec-hd">
      <div class="sec-icon" style="background:#E8F5F1">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#007A5E" stroke-width="2.5" stroke-linecap="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      </div>
      <div class="sec-title" style="color:#007A5E">Résumé de la performance</div>
      <div class="sec-line" style="background:#E8F5F1"></div>
    </div>

    <div class="sum-grid">
      <div class="sum-card" style="border-color:rgba(0,122,94,.2)">
        <div class="sum-card-num" style="color:#007A5E">${pct.toFixed(1)}%</div>
        <div class="sum-card-lbl">Score global</div>
      </div>
      <div class="sum-card" style="border-color:rgba(0,122,94,.15)">
        <div class="sum-card-num" style="color:#007A5E">${bonnes}</div>
        <div class="sum-card-lbl">Bonnes réponses</div>
      </div>
      <div class="sum-card" style="border-color:rgba(201,52,42,.15)">
        <div class="sum-card-num" style="color:#C9342A">${total - bonnes}</div>
        <div class="sum-card-lbl">À revoir</div>
      </div>
      <div class="sum-card" style="border-color:rgba(201,151,42,.2)">
        <div class="sum-card-num" style="color:#C9972A">${score.toFixed(1)}</div>
        <div class="sum-card-lbl">Points / ${scoreMax.toFixed(1)}</div>
      </div>
    </div>

    <div class="sum-level-card">
      <div class="sum-level-icon">${niveau.icon}</div>
      <div style="flex:1">
        <div class="sum-level-label" style="color:${niveau.color}">${niveau.label}</div>
        <div class="sum-level-sub">Niveau estimé selon le score obtenu · ${mins}m ${String(secs).padStart(2,'0')}s de durée</div>
        <div class="sum-prog-track">
          <div class="sum-prog-fill" style="width:${fillW}%;background:${fillColor}"></div>
        </div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
      <div style="border:1px solid #E5E7EB;border-radius:10px;padding:14px 16px">
        <div style="font-size:8.5pt;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:#9CA3AF;margin-bottom:10px">Répartition des réponses</div>
        <div style="display:flex;flex-direction:column;gap:8px">
          <div style="display:flex;justify-content:space-between;font-size:10pt">
            <span style="color:#007A5E;display:flex;align-items:center;gap:5px"><span style="width:8px;height:8px;border-radius:50%;background:#007A5E;display:inline-block"></span> Correctes</span>
            <strong>${bonnes} (${taux}%)</strong>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:10pt">
            <span style="color:#C9342A;display:flex;align-items:center;gap:5px"><span style="width:8px;height:8px;border-radius:50%;background:#C9342A;display:inline-block"></span> Incorrectes</span>
            <strong>${total - bonnes} (${100 - taux}%)</strong>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:10pt">
            <span style="color:#6B7280">Total</span>
            <strong>${total} questions</strong>
          </div>
        </div>
      </div>
      <div style="border:1px solid #E5E7EB;border-radius:10px;padding:14px 16px">
        <div style="font-size:8.5pt;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:#9CA3AF;margin-bottom:10px">Barème de notation</div>
        <div style="display:flex;flex-direction:column;gap:7px;font-size:9.5pt;color:#374151">
          <div style="display:flex;justify-content:space-between"><span>🏆 Excellent</span><span style="color:#007A5E;font-weight:700">≥ 85%</span></div>
          <div style="display:flex;justify-content:space-between"><span>⭐ Bien</span><span style="color:#1E5FAD;font-weight:700">70-84%</span></div>
          <div style="display:flex;justify-content:space-between"><span>📈 Satisfaisant</span><span style="color:#C9972A;font-weight:700">55-69%</span></div>
          <div style="display:flex;justify-content:space-between"><span>🎯 Insuffisant</span><span style="color:#C9342A;font-weight:700">40-54%</span></div>
          <div style="display:flex;justify-content:space-between"><span>💪 À améliorer</span><span style="color:#6B7280;font-weight:700">< 40%</span></div>
        </div>
      </div>
    </div>

  </div>
  <div class="doc-ft">
    <div class="doc-ft-text">RÉUSSITE+ · Rapport d'examen · ${esc(prenom)}</div>
    <div class="doc-ft-text">Document confidentiel — usage pédagogique</div>
  </div>
</div>`;
  }

  // ── PAGES QUESTIONS ───────────────────────────────────────
  function buildQuestions(data, pageOffset) {
    const { prenom, matiere, answers } = data;
    const PER_PAGE = 4;
    const pages    = [];
    for (let i = 0; i < answers.length; i += PER_PAGE) {
      pages.push(answers.slice(i, i + PER_PAGE));
    }
    return pages.map((chunk, pi) => {
      const qHtml = chunk.map((a, ci) => {
        const gIdx    = pi * PER_PAGE + ci;
        const correct = !!a.est_correcte;
        const cls     = correct ? 'correct' : 'wrong';
        const userAns = a.option_choisie_lettre
          ? `${esc(a.option_choisie_lettre)}) ${esc(a.option_choisie_texte || '')}`
          : `${esc(a.option_choisie_texte || 'Pas de réponse')}`;
        const bonneAns = a.bonne_reponse_lettre
          ? `${esc(a.bonne_reponse_lettre)}) ${esc(a.bonne_reponse_texte || '')}`
          : esc(a.bonne_reponse_texte || '');
        const diffLabel = { 'DEBUTANT':'Débutant', 'ELEMENTAIRE':'Élémentaire', 'INTERMEDIAIRE':'Intermédiaire', 'AVANCE':'Avancé', 'EXPERT':'Expert' }[a.difficulte] || (a.difficulte || '');

        return `<div class="q-item">
          <div class="q-header ${cls}">
            <div class="q-num ${cls}">Question ${gIdx + 1}</div>
            <span class="q-badge ${cls}">${correct ? '✓ Correct' : '✕ Incorrect'}</span>
            ${diffLabel ? `<span class="q-diff">${esc(diffLabel)}</span>` : ''}
          </div>
          <div class="q-body">
            <div class="q-enonce">${esc(a.enonce || '').replace(/\n/g,'<br>')}</div>
            <div class="q-answers">
              <span class="q-ans-chip ${correct ? 'user-correct' : 'user-wrong'}">
                <strong>Votre réponse :</strong> ${userAns}
              </span>
              ${!correct && bonneAns ? `<span class="q-ans-chip correct-ans"><strong>Bonne réponse :</strong> ${bonneAns}</span>` : ''}
            </div>
            ${a.explication ? `<div class="q-expl"><div class="q-expl-lbl">Explication</div>${esc(a.explication).replace(/\n/g,'<br>')}</div>` : ''}
          </div>
        </div>`;
      }).join('');

      return `
<div class="page doc-page">
  <div class="doc-hd">
    <div class="doc-hd-brand">RÉUSSITE<span>+</span></div>
    <div class="doc-hd-meta">Questions · ${esc(matiere)} · Page ${pageOffset + pi + 1}</div>
  </div>
  <div class="doc-body">
    <div class="sec-hd">
      <div class="sec-icon" style="background:#F3F4F6">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6B7280" stroke-width="2.5" stroke-linecap="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
      </div>
      <div class="sec-title" style="color:#6B7280">Détail des réponses${pi > 0 ? ` (suite ${pi + 1})` : ''}</div>
      <div class="sec-line" style="background:#F3F4F6"></div>
    </div>
    ${qHtml}
  </div>
  <div class="doc-ft">
    <div class="doc-ft-text">RÉUSSITE+ · Rapport d'examen · ${esc(prenom)}</div>
    <div class="doc-ft-text">Document confidentiel — usage pédagogique</div>
  </div>
</div>`;
    }).join('');
  }

  // ── PAGE PÉDAGOGIQUE ──────────────────────────────────────
  function buildPedagogy(data, pageNum) {
    const { prenom, matiere, pct, bonnes, total, answers, niveau } = data;
    const wrongAnswers = answers.filter(a => !a.est_correcte);
    const rightAnswers = answers.filter(a => a.est_correcte);

    // Extraire les thèmes erronés depuis les énoncés
    const wrongTopics = wrongAnswers.slice(0, 4).map(a =>
      (a.enonce || '').substring(0, 50).trim()
    ).filter(Boolean);

    const recs = getRecommendations(pct, bonnes, total, wrongTopics);

    const forceItems = rightAnswers.slice(0, 4).map(a =>
      `<div class="ped-item"><div class="ped-dot" style="background:#007A5E"></div><div>${esc(a.enonce || '').substring(0,70)}…</div></div>`
    ).join('') || `<div class="ped-item" style="color:#9CA3AF;font-style:italic">Aucune bonne réponse enregistrée.</div>`;

    const weakItems = wrongAnswers.slice(0, 4).map(a =>
      `<div class="ped-item"><div class="ped-dot" style="background:#C9342A"></div><div>${esc(a.enonce || '').substring(0,70)}…</div></div>`
    ).join('') || `<div class="ped-item" style="color:#9CA3AF;font-style:italic">Aucune réponse incorrecte. Parfait !</div>`;

    const recItems = recs.map((r, i) =>
      `<div class="rec-item"><div class="rec-num">${i+1}</div><div>${esc(r)}</div></div>`
    ).join('');

    return `
<div class="page doc-page">
  <div class="doc-hd">
    <div class="doc-hd-brand">RÉUSSITE<span>+</span></div>
    <div class="doc-hd-meta">Analyse pédagogique · ${esc(prenom)} · Page ${pageNum}</div>
  </div>
  <div class="doc-body">

    <div class="sec-hd">
      <div class="sec-icon" style="background:#E8F5F1">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#007A5E" stroke-width="2.5" stroke-linecap="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
      </div>
      <div class="sec-title" style="color:#007A5E">Synthèse pédagogique</div>
      <div class="sec-line" style="background:#E8F5F1"></div>
    </div>

    <div class="ped-grid">
      <div class="ped-card" style="border-color:#E8F5F1">
        <div class="ped-card-hd" style="background:#E8F5F1;color:#005A45">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
          Points maîtrisés
        </div>
        <div class="ped-card-body">${forceItems}</div>
      </div>
      <div class="ped-card" style="border-color:#FEF0EF">
        <div class="ped-card-hd" style="background:#FEF0EF;color:#C9342A">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          Points à retravailler
        </div>
        <div class="ped-card-body">${weakItems}</div>
      </div>
    </div>

    <div class="ped-card" style="margin-bottom:14px;border-color:#EEF4FD">
      <div class="ped-card-hd" style="background:#EEF4FD;color:#1E5FAD">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
        Recommandations personnalisées
      </div>
      <div class="ped-card-body">${recItems}</div>
    </div>

    <div style="background:linear-gradient(135deg,#E8F5F1,#F0FAF7);border:1px solid rgba(0,122,94,.15);border-radius:10px;padding:14px 18px;display:flex;align-items:center;gap:16px">
      <div style="font-size:24pt">${niveau.icon}</div>
      <div>
        <div style="font-size:11pt;font-weight:800;color:#007A5E">Niveau estimé : ${niveau.label}</div>
        <div style="font-size:9.5pt;color:#6B7280;margin-top:3px">Score ${pct.toFixed(1)}% · ${bonnes}/${total} questions correctes · Objectif suivant : ${pct >= 80 ? 'Maintiens ce niveau et passe aux examens avancés' : pct >= 60 ? 'Atteindre 80% au prochain examen' : 'Atteindre 60% au prochain examen'}</div>
      </div>
    </div>

  </div>
  <div class="doc-ft">
    <div class="doc-ft-text">RÉUSSITE+ — Plateforme EdTech RDC · Rapport Premium</div>
    <div class="doc-ft-text">Généré automatiquement · usage pédagogique</div>
  </div>
</div>`;
  }

  // ── Générateur principal ──────────────────────────────────
  function generate(examData) {
    const { session, answers, prenom } = examData;
    const now     = new Date();
    const date    = now.toLocaleDateString('fr-FR', { weekday:'long', day:'2-digit', month:'long', year:'numeric' });
    const heure   = now.toLocaleTimeString('fr-FR', { hour:'2-digit', minute:'2-digit' });
    const pct     = parseFloat(session.pourcentage || 0);
    const bonnes  = answers.filter(a => a.est_correcte).length;
    const total   = answers.length;
    const score   = parseFloat(session.score || 0);
    const scoreMax= parseFloat(session.score_max || total);
    const secs    = (session.temps_passe || 0) % 60;
    const mins    = Math.floor((session.temps_passe || 0) / 60);
    const niveau  = getNiveau(pct);

    const data = {
      prenom, pct, bonnes, total, score, scoreMax, secs, mins, niveau, answers,
      matiere:  session.matiere_nom || session.titre || 'Examen',
      titre:    session.titre || session.matiere_nom || 'Rapport d\'examen',
      examType: session.exam_type || '',
      date, heure,
    };

    const qPageCount = Math.ceil(answers.length / 4);

    return `<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rapport d'examen — ${esc(prenom)} — RÉUSSITE+</title>
${buildCSS()}
</head>
<body>
${buildCover(data)}
${buildSummary({ ...data, pageNum: 2 })}
${buildQuestions({ ...data }, 2)}
${buildPedagogy({ ...data }, 3 + qPageCount)}
</body>
</html>`;
  }

  // ── Ouvrir + imprimer ─────────────────────────────────────
  function open(examData) {
    const html = generate(examData);
    const win  = window.open('', '_blank');
    if (!win) { alert('Autorisez les pop-ups pour exporter le PDF.'); return; }
    win.document.write(html);
    win.document.close();
    win.focus();
    setTimeout(() => win.print(), 700);
  }

  return { open, generate };
})();
