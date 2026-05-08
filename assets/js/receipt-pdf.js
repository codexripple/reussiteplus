/**
 * RÉUSSITE+ — Générateur de reçus de paiement Premium
 * Même ADN visuel que ia-pdf.js et exam-pdf.js
 */

const ReceiptPdf = (() => {

  const esc = s => String(s ?? '')
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

  const PLANS_INFO = {
    GRATUIT: { nom:'Gratuit',   color:'#6B7280', features:[] },
    BASIQUE: { nom:'Basique',   color:'#1E5FAD', features:['30 examens/mois','200 questions/examen','Archives officielles','Corrigés détaillés','Suivi de progression'] },
    PREMIUM: { nom:'Premium',   color:'#C9972A', features:['Examens illimités','Questions illimitées','Archives officielles','Corrigés détaillés','Suivi de progression avancé','Assistant IA 24h/24','Plan de révision IA','Analyse des erreurs','Rapports PDF Premium'] },
    ECOLE:   { nom:'École',     color:'#007A5E', features:['Tout le plan Premium','Jusqu\'à 50 élèves','10 comptes enseignants','5 classes','Emploi du temps','Bulletins automatiques','IA pédagogique','Groupe WhatsApp dédié'] },
  };

  const METHODES = {
    MPESA:        'M-Pesa',
    AIRTEL_MONEY: 'Airtel Money',
    ORANGE_MONEY: 'Orange Money',
    CARTE:        'Carte bancaire',
    VIREMENT:     'Virement bancaire',
    ADMIN:        'Activation admin',
  };

  function formatDate(s) {
    if (!s) return '—';
    try {
      return new Date(s).toLocaleDateString('fr-FR', { day:'2-digit', month:'long', year:'numeric' });
    } catch(_) { return s; }
  }
  function formatMontant(m, devise) {
    return new Intl.NumberFormat('fr-FR').format(parseFloat(m || 0)) + ' ' + (devise || 'CDF');
  }

  // ── Numéro de reçu formaté ─────────────────────────────────
  function formatReceiptNum(ref) {
    // RP-XXXXXXXX → REC-XXXXXXXX-2026
    const year  = new Date().getFullYear();
    const clean = (ref || '').replace('RP-', '');
    return `REC-${clean}-${year}`;
  }

  // ── CSS du reçu ────────────────────────────────────────────
  function buildCSS() {
    return `<style>
@page { size: A4; margin: 0; }
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'Helvetica Neue', Arial, sans-serif;
  color: #1a2535; font-size: 10.5pt; line-height: 1.65;
  background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact;
}
.page { width: 210mm; min-height: 297mm; position: relative; overflow: hidden; display: flex; flex-direction: column; }

/* ── HEADER BRAND ── */
.receipt-header {
  background: linear-gradient(135deg, #007A5E 0%, #005A45 60%, #003D30 100%);
  padding: 32px 48px 28px;
  position: relative; overflow: hidden;
}
.rh-deco-1 { position:absolute;top:-50px;right:-50px;width:220px;height:220px;border-radius:50%;border:1px solid rgba(255,255,255,.06);background:radial-gradient(circle,rgba(255,255,255,.05) 0%,transparent 70%); }
.rh-deco-2 { position:absolute;bottom:-30px;left:30%;width:150px;height:150px;border-radius:50%;border:1px solid rgba(201,151,42,.1); }
.rh-inner  { position:relative;z-index:1;display:flex;justify-content:space-between;align-items:flex-start; }
.rh-brand  { display:flex;align-items:center;gap:10px; }
.rh-brand-icon {
  width:40px;height:40px;border-radius:10px;
  background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);
  display:flex;align-items:center;justify-content:center;
}
.rh-brand-name { font-size:18pt;font-weight:900;color:#fff;letter-spacing:-.5px; }
.rh-brand-name span { color:#C9972A; }
.rh-brand-sub  { font-size:8.5pt;color:rgba(255,255,255,.5);margin-top:2px;letter-spacing:.3px; }
.rh-doc-type   { text-align:right; }
.rh-doc-badge  { display:inline-flex;align-items:center;gap:6px;background:rgba(201,151,42,.18);border:1px solid rgba(201,151,42,.3);padding:4px 14px;border-radius:20px;font-size:8pt;color:#F5D78E;font-weight:600;letter-spacing:.5px;margin-bottom:8px; }
.rh-doc-title  { font-size:20pt;font-weight:900;color:#fff;line-height:1.1;letter-spacing:-.3px; }
.rh-doc-num    { font-size:9pt;color:rgba(255,255,255,.55);margin-top:4px;letter-spacing:.5px; }
.rh-doc-date   { font-size:8.5pt;color:rgba(255,255,255,.4);margin-top:2px; }

/* ── STATUS BANNER ── */
.status-banner {
  padding: 10px 48px;
  display: flex; align-items: center; justify-content: space-between;
  font-size: 9pt; font-weight: 700;
}
.status-banner.confirmed { background: #E8F5F1; border-bottom: 1px solid rgba(0,122,94,.15); color: #005A45; }
.status-banner.pending   { background: #FEF3C7; border-bottom: 1px solid rgba(201,151,42,.2); color: #92400E; }
.status-badge { display:inline-flex;align-items:center;gap:6px;padding:4px 14px;border-radius:20px;font-size:8.5pt;font-weight:700; }
.status-badge.confirmed { background:rgba(0,122,94,.12);color:#005A45;border:1px solid rgba(0,122,94,.2); }
.status-badge.pending   { background:rgba(245,158,11,.12);color:#92400E;border:1px solid rgba(245,158,11,.2); }

/* ── BODY ── */
.receipt-body { padding: 28px 48px 80px; flex: 1; }

/* Grille infos ── */
.info-grid { display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px; }
.info-card { border:1px solid #E5E7EB;border-radius:12px;overflow:hidden; }
.info-card-hd { padding:9px 16px;font-size:8.5pt;font-weight:700;text-transform:uppercase;letter-spacing:.7px;display:flex;align-items:center;gap:6px; }
.info-card-body { padding:12px 16px; }
.info-row { display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid #F1F5F9;font-size:9.5pt; }
.info-row:last-child { border:none; }
.info-row-lbl { color:#6B7280;font-weight:500; }
.info-row-val { font-weight:600;color:#1a2535;text-align:right;max-width:55%;word-break:break-all; }

/* Amount block ── */
.amount-block {
  background:linear-gradient(135deg,#007A5E,#005A45);
  border-radius:14px;padding:20px 24px;margin-bottom:24px;
  display:flex;align-items:center;justify-content:space-between;
  position:relative;overflow:hidden;
}
.amount-block::before { content:'';position:absolute;top:-30px;right:-30px;width:130px;height:130px;border-radius:50%;background:rgba(255,255,255,.06);pointer-events:none; }
.amount-block-label { font-size:10pt;font-weight:600;color:rgba(255,255,255,.7);margin-bottom:4px; }
.amount-block-value { font-size:26pt;font-weight:900;color:#fff;line-height:1;letter-spacing:-.5px; }
.amount-block-sub   { font-size:8.5pt;color:rgba(255,255,255,.5);margin-top:3px; }
.amount-block-plan  { text-align:right;position:relative;z-index:1; }
.amount-plan-badge  { display:inline-block;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);border-radius:8px;padding:6px 14px;font-size:10pt;font-weight:800;color:#fff;margin-bottom:4px; }
.amount-plan-dates  { font-size:8.5pt;color:rgba(255,255,255,.55);line-height:1.7; }

/* Features ── */
.features-section { margin-bottom:24px; }
.features-title { font-size:8.5pt;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:#9CA3AF;margin-bottom:12px;display:flex;align-items:center;gap:8px; }
.features-title::after { content:'';flex:1;height:1px;background:#E5E7EB; }
.features-grid { display:grid;grid-template-columns:1fr 1fr;gap:7px; }
.feature-item { display:flex;align-items:center;gap:8px;padding:6px 10px;background:#F8FAFC;border:1px solid #E5E7EB;border-radius:7px;font-size:9.5pt;color:#374151; }
.feature-check { width:16px;height:16px;border-radius:50%;background:#E8F5F1;display:flex;align-items:center;justify-content:center;flex-shrink:0; }

/* ── FOOTER ── */
.receipt-footer {
  position:absolute;bottom:0;left:0;right:0;
  background:#F8FAFC;border-top:1px solid #E5E7EB;
  padding:12px 48px;display:flex;justify-content:space-between;align-items:center;
  font-size:8.5pt;
}
.rf-brand  { font-weight:800;color:#007A5E; }
.rf-support{ color:#9CA3AF; }
.rf-legal  { color:#C5D0DB;font-style:italic; }

/* ── PRINT ── */
@media print {
  @page { size:A4;margin:0; }
  body  { -webkit-print-color-adjust:exact !important;print-color-adjust:exact !important; }
}
</style>`;
  }

  // ── Contenu principal ─────────────────────────────────────
  function buildReceipt(data) {
    const { abonnement: ab, user } = data;
    const planInfo   = PLANS_INFO[ab.plan] || { nom: ab.plan, color:'#6B7280', features:[] };
    const isConfirmed = ab.statut === 'CONFIRME';
    const receiptNum = formatReceiptNum(ab.reference_paiement);
    const emittedDate = ab.confirmed_at || ab.created_at;
    const montantBrut = parseFloat(ab.montant_brut || ab.montant || 0);
    const remise      = parseFloat(ab.remise || 0);
    const montantFinal= parseFloat(ab.montant || 0);

    return `
<div class="page">
  <!-- Header -->
  <div class="receipt-header">
    <div class="rh-deco-1"></div>
    <div class="rh-deco-2"></div>
    <div class="rh-inner">
      <div class="rh-brand">
        <div class="rh-brand-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
        </div>
        <div>
          <div class="rh-brand-name">RÉUSSITE<span>+</span></div>
          <div class="rh-brand-sub">PLATEFORME ÉDUCATIVE · RDC</div>
        </div>
      </div>
      <div class="rh-doc-type">
        <div class="rh-doc-badge">
          <svg width="9" height="9" viewBox="0 0 24 24" fill="#F5D78E" stroke="none"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8" stroke="#F5D78E" stroke-width="2" fill="none"/></svg>
          Reçu de paiement
        </div>
        <div class="rh-doc-title">Reçu</div>
        <div class="rh-doc-num">${esc(receiptNum)}</div>
        <div class="rh-doc-date">Émis le ${formatDate(emittedDate)}</div>
      </div>
    </div>
  </div>

  <!-- Status -->
  <div class="status-banner ${isConfirmed ? 'confirmed' : 'pending'}">
    <span>Statut du paiement</span>
    <span class="status-badge ${isConfirmed ? 'confirmed' : 'pending'}">
      ${isConfirmed
        ? '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> PAIEMENT CONFIRMÉ'
        : '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> EN ATTENTE DE CONFIRMATION'}
    </span>
  </div>

  <!-- Body -->
  <div class="receipt-body">

    <!-- Info grid : Client + Transaction -->
    <div class="info-grid">
      <div class="info-card">
        <div class="info-card-hd" style="background:#F0F7F4;color:#005A45">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          Informations client
        </div>
        <div class="info-card-body">
          <div class="info-row"><span class="info-row-lbl">Nom complet</span><span class="info-row-val">${esc(user.prenom + ' ' + user.nom)}</span></div>
          <div class="info-row"><span class="info-row-lbl">Adresse e-mail</span><span class="info-row-val">${esc(user.email || '—')}</span></div>
          <div class="info-row"><span class="info-row-lbl">Téléphone</span><span class="info-row-val">${esc(ab.telephone || '—')}</span></div>
          <div class="info-row"><span class="info-row-lbl">Pays</span><span class="info-row-val">République Démocratique du Congo</span></div>
        </div>
      </div>
      <div class="info-card">
        <div class="info-card-hd" style="background:#EEF4FD;color:#1E5FAD">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
          Détails de la transaction
        </div>
        <div class="info-card-body">
          <div class="info-row"><span class="info-row-lbl">Référence</span><span class="info-row-val" style="font-family:monospace">${esc(ab.reference_paiement)}</span></div>
          <div class="info-row"><span class="info-row-lbl">Reçu n°</span><span class="info-row-val" style="font-family:monospace">${esc(receiptNum)}</span></div>
          <div class="info-row"><span class="info-row-lbl">Date paiement</span><span class="info-row-val">${formatDate(ab.created_at)}</span></div>
          <div class="info-row"><span class="info-row-lbl">Mode de paiement</span><span class="info-row-val">${esc(METHODES[ab.methode_paiement] || ab.methode_paiement || '—')}</span></div>
        </div>
      </div>
    </div>

    <!-- Amount block -->
    <div class="amount-block">
      <div style="position:relative;z-index:1">
        <div class="amount-block-label">Montant total payé</div>
        <div class="amount-block-value">${esc(formatMontant(ab.montant, ab.devise))}</div>
        ${remise > 0 ? `<div class="amount-block-sub">Remise appliquée : ${remise}%${ab.code_promo ? ' (Code : ' + esc(ab.code_promo) + ')' : ''}</div>` : ''}
      </div>
      <div class="amount-plan-badge-wrap" style="text-align:right;position:relative;z-index:1">
        <div class="amount-plan-badge" style="color:${esc(planInfo.color)};background:rgba(255,255,255,.15)">
          ${esc(planInfo.nom)}
        </div>
        <div class="amount-plan-dates">
          <div>Début : ${formatDate(ab.date_debut)}</div>
          <div>Fin   : ${formatDate(ab.date_fin)}</div>
          <div>Durée : ${esc(ab.duree_mois)} mois</div>
        </div>
      </div>
    </div>

    <!-- Subscription details -->
    <div class="info-card" style="margin-bottom:20px">
      <div class="info-card-hd" style="background:#FEF3C7;color:#92400E">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
        Détails de l'abonnement
      </div>
      <div class="info-card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0">
          <div class="info-row"><span class="info-row-lbl">Plan souscrit</span><span class="info-row-val" style="color:${esc(planInfo.color)};font-weight:800">${esc(planInfo.nom)}</span></div>
          <div class="info-row"><span class="info-row-lbl">Durée</span><span class="info-row-val">${esc(ab.duree_mois)} mois</span></div>
          <div class="info-row"><span class="info-row-lbl">Date d'activation</span><span class="info-row-val">${formatDate(ab.date_debut)}</span></div>
          <div class="info-row"><span class="info-row-lbl">Date d'expiration</span><span class="info-row-val">${formatDate(ab.date_fin)}</span></div>
          <div class="info-row"><span class="info-row-lbl">Renouvellement</span><span class="info-row-val">Manuel — avant le ${formatDate(ab.date_fin)}</span></div>
          <div class="info-row"><span class="info-row-lbl">Devise</span><span class="info-row-val">${esc(ab.devise || 'CDF')}</span></div>
        </div>
      </div>
    </div>

    <!-- Fonctionnalités incluses -->
    ${planInfo.features.length > 0 ? `
    <div class="features-section">
      <div class="features-title">Fonctionnalités incluses dans le plan ${esc(planInfo.nom)}</div>
      <div class="features-grid">
        ${planInfo.features.map(f => `
        <div class="feature-item">
          <div class="feature-check">
            <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#007A5E" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
          </div>
          ${esc(f)}
        </div>`).join('')}
      </div>
    </div>` : ''}

  </div>

  <!-- Footer -->
  <div class="receipt-footer">
    <div class="rf-brand">RÉUSSITE+ · Plateforme EdTech RDC</div>
    <div class="rf-support">Support : paiement@reussiteplus.cd · +243 977 329 184</div>
    <div class="rf-legal">Document généré automatiquement · valeur comptable</div>
  </div>
</div>`;
  }

  // ── Générateur principal ──────────────────────────────────
  function generate(data) {
    return `<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reçu ${esc(data.abonnement?.reference_paiement || '')} — RÉUSSITE+</title>
${buildCSS()}
</head>
<body>
${buildReceipt(data)}
</body>
</html>`;
  }

  // ── Ouvrir + imprimer ─────────────────────────────────────
  function open(data) {
    if (!data?.abonnement) { alert('Données du reçu manquantes.'); return; }
    const html = generate(data);
    const win  = window.open('', '_blank');
    if (!win) { alert('Autorisez les pop-ups pour télécharger le reçu.'); return; }
    win.document.write(html);
    win.document.close();
    win.focus();
    setTimeout(() => win.print(), 700);
  }

  return { open, generate };
})();
