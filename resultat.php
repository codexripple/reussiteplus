<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Résultats de l\'examen';
$pageActive = 'examen';
$user = require_login();

$sessionId = $_GET['session'] ?? '';
if (!$sessionId) { redirect('/reussiteplus/examen.php'); }

$session = dbRow(
    "SELECT es.*, m.nom as matiere_nom, m.couleur
     FROM exam_sessions es
     LEFT JOIN matieres m ON es.matiere_id = m.id
     WHERE es.id=? AND es.user_id=?",
    [$sessionId, $user['id']]
);
if (!$session) { redirect('/reussiteplus/dashboard.php', 'error', 'Session introuvable.'); }

$answers = dbAll(
    "SELECT ea.*, qb.enonce, qb.points, qb.difficulte,
            qo.texte as option_choisie_texte, qo.lettre as option_choisie_lettre,
            correct_opt.texte as bonne_reponse_texte, correct_opt.lettre as bonne_reponse_lettre,
            qb.objectif as explication
     FROM exam_answers ea
     JOIN question_bank qb ON ea.question_id = qb.id
     LEFT JOIN question_options qo ON ea.option_id = qo.id
     LEFT JOIN question_options correct_opt ON correct_opt.question_id = ea.question_id AND correct_opt.est_correcte = 1
     WHERE ea.session_id = ?",
    [$sessionId]
);

$pct       = (float)($session['pourcentage'] ?? 0);
$bonnes    = count(array_filter($answers, fn($a) => $a['est_correcte']));
$total     = count($answers);
$mauvaises = $total - $bonnes;
$mins      = floor(($session['temps_passe'] ?? 0) / 60);
$secs      = ($session['temps_passe'] ?? 0) % 60;
$isPremium = in_array($user['plan'] ?? 'GRATUIT', ['PREMIUM', 'ECOLE']);

// Données JSON pour le PDF
$examDataJson = json_encode([
    'session' => [
        'titre'       => $session['titre'] ?? null,
        'matiere_nom' => $session['matiere_nom'] ?? null,
        'exam_type'   => $session['exam_type'] ?? null,
        'pourcentage' => $pct,
        'score'       => (float)($session['score'] ?? 0),
        'score_max'   => (float)($session['score_max'] ?? $total),
        'temps_passe' => (int)($session['temps_passe'] ?? 0),
        'finished_at' => $session['finished_at'] ?? $session['started_at'],
    ],
    'answers' => array_map(fn($a) => [
        'enonce'                => $a['enonce'],
        'est_correcte'          => (bool)$a['est_correcte'],
        'difficulte'            => $a['difficulte'],
        'option_choisie_lettre' => $a['option_choisie_lettre'],
        'option_choisie_texte'  => $a['option_choisie_texte'],
        'bonne_reponse_lettre'  => $a['bonne_reponse_lettre'],
        'bonne_reponse_texte'   => $a['bonne_reponse_texte'],
        'explication'           => $a['explication'],
    ], $answers),
    'prenom' => $user['prenom'],
], JSON_UNESCAPED_UNICODE);

include __DIR__ . '/includes/header_app.php';
?>

<style>
/* ── PDF Export styles ── */
.export-btn {
  display: inline-flex; align-items: center; gap: 8px;
  background: linear-gradient(135deg, #007A5E, #005A45);
  color: #fff; border: none; border-radius: 10px;
  padding: 10px 20px; font-size: 13.5px; font-weight: 700;
  cursor: pointer; font-family: inherit;
  transition: box-shadow .18s, transform .18s;
  box-shadow: 0 2px 10px rgba(0,122,94,.3);
  text-decoration: none;
}
.export-btn:hover { box-shadow: 0 6px 20px rgba(0,122,94,.4); transform: translateY(-1px); }
.export-btn:disabled { opacity: .5; cursor: not-allowed; transform: none; }
.export-btn .export-badge {
  background: rgba(255,255,255,.2); border-radius: 4px;
  padding: 1px 7px; font-size: 10px; font-weight: 800; letter-spacing: .5px;
}
@keyframes exportSpin { to { transform: rotate(360deg); } }
.export-spinner { animation: exportSpin .7s linear infinite; }

/* ── Premium upsell block ── */
.premium-upsell {
  background: linear-gradient(135deg, #0d1120, #111827);
  border: 1px solid rgba(201,151,42,.25); border-radius: 18px;
  padding: 28px; margin-bottom: 24px; position: relative; overflow: hidden;
}
.premium-upsell::before {
  content: ''; position: absolute; top: -40px; right: -40px;
  width: 180px; height: 180px; border-radius: 50%;
  background: radial-gradient(circle, rgba(201,151,42,.1) 0%, transparent 70%);
}
.upsell-badge {
  display: inline-flex; align-items: center; gap: 6px;
  background: rgba(201,151,42,.15); border: 1px solid rgba(201,151,42,.3);
  border-radius: 20px; padding: 4px 12px;
  font-size: 11px; color: #F5D78E; font-weight: 700; margin-bottom: 14px;
}
.upsell-title { font-size: 18px; font-weight: 800; color: #fff; margin-bottom: 6px; }
.upsell-desc  { font-size: 13px; color: rgba(255,255,255,.5); margin-bottom: 18px; line-height: 1.6; }
.upsell-features { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; }
.upsell-feature {
  display: flex; align-items: center; gap: 6px;
  background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.1);
  border-radius: 8px; padding: 6px 12px; font-size: 12px; color: rgba(255,255,255,.8);
}
.upsell-cta {
  display: inline-flex; align-items: center; gap: 8px;
  background: linear-gradient(135deg, #C9972A, #8C6A1A);
  color: #fff; padding: 12px 22px; border-radius: 12px;
  font-size: 14px; font-weight: 700; text-decoration: none;
  transition: box-shadow .18s, transform .18s;
  box-shadow: 0 4px 16px rgba(201,151,42,.35);
}
.upsell-cta:hover { box-shadow: 0 8px 28px rgba(201,151,42,.5); transform: translateY(-1px); }

/* PDF blurred preview */
.pdf-preview-blur {
  border: 1px solid rgba(201,151,42,.2); border-radius: 14px;
  overflow: hidden; position: relative; margin-top: 16px;
}
.pdf-preview-inner {
  filter: blur(5px); pointer-events: none; padding: 20px;
  background: #fff; opacity: .6;
}
.pdf-preview-overlay {
  position: absolute; inset: 0;
  background: linear-gradient(to bottom, rgba(13,17,32,.2) 0%, rgba(13,17,32,.85) 100%);
  display: flex; flex-direction: column; align-items: center; justify-content: flex-end;
  padding: 20px;
}
.pdf-preview-lock {
  display: flex; flex-direction: column; align-items: center; gap: 6px; color: #fff; text-align: center;
}
</style>

<div style="max-width:800px;margin:0 auto">

  <!-- ── Score principal ── -->
  <div class="card" style="text-align:center;margin-bottom:24px;padding:40px">
    <div style="margin-bottom:16px">
      <?php if ($pct >= 80): ?>
      <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#C9972A" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>
      <?php elseif ($pct >= 60): ?>
      <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#007A5E" stroke-width="1.8" stroke-linecap="round" style="display:inline-block"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
      <?php elseif ($pct >= 40): ?>
      <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#1E5FAD" stroke-width="1.8" stroke-linecap="round" style="display:inline-block"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
      <?php else: ?>
      <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#C9342A" stroke-width="1.8" stroke-linecap="round" style="display:inline-block"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
      <?php endif; ?>
    </div>
    <div style="font-size:56px;font-weight:900;color:<?= score_couleur($pct) ?>;line-height:1">
      <?= number_format($pct, 1) ?>%
    </div>
    <div style="font-size:22px;font-weight:700;color:var(--gris-900);margin-top:8px">
      <?= score_label($pct) ?>
    </div>
    <div style="font-size:14px;color:var(--gris-600);margin-top:6px">
      <?= e($session['matiere_nom'] ?? $session['titre']) ?> &bull; <?= date('d/m/Y à H:i', strtotime($session['finished_at'] ?? $session['started_at'])) ?>
    </div>
  </div>

  <!-- ── Stats ── -->
  <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px">
    <div class="stat-card green">
      <div class="stat-label">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="vertical-align:-2px;margin-right:4px"><polyline points="20 6 9 17 4 12"/></svg>
        Bonnes réponses
      </div>
      <div class="stat-value"><?= $bonnes ?></div>
      <div class="stat-sub">sur <?= $total ?> questions</div>
    </div>
    <div class="stat-card rouge">
      <div class="stat-label">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="vertical-align:-2px;margin-right:4px"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        Mauvaises
      </div>
      <div class="stat-value"><?= $mauvaises ?></div>
      <div class="stat-sub">à revoir</div>
    </div>
    <div class="stat-card gold">
      <div class="stat-label">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="vertical-align:-2px;margin-right:4px"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        Score total
      </div>
      <div class="stat-value"><?= number_format((float)$session['score'], 1) ?></div>
      <div class="stat-sub">/ <?= number_format((float)$session['score_max'], 1) ?> pts</div>
    </div>
    <div class="stat-card bleu">
      <div class="stat-label">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="vertical-align:-2px;margin-right:4px"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        Temps passé
      </div>
      <div class="stat-value"><?= $mins ?>:<?= str_pad($secs, 2, '0', STR_PAD_LEFT) ?></div>
      <div class="stat-sub">minutes</div>
    </div>
  </div>

  <!-- ── Actions rapides ── -->
  <div style="display:flex;gap:12px;margin-bottom:24px;flex-wrap:wrap;align-items:center">
    <a href="/reussiteplus/examen.php" class="btn btn-primary">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="vertical-align:-2px;margin-right:6px"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
      Refaire un examen
    </a>
    <a href="/reussiteplus/progression.php" class="btn btn-ghost">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="vertical-align:-2px;margin-right:6px"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/></svg>
      Ma progression
    </a>
    <?php if ($isPremium): ?>
    <button class="export-btn" id="exportBtn" onclick="exportPDF()">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="13" x2="12" y2="19"/><polyline points="9 16 12 19 15 16"/></svg>
      Exporter en PDF
      <span class="export-badge">PREMIUM</span>
    </button>
    <?php else: ?>
    <button class="btn btn-ghost" onclick="document.getElementById('premiumUpsell').scrollIntoView({behavior:'smooth'})" style="border-color:rgba(201,151,42,.4);color:#C9972A">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="vertical-align:-2px;margin-right:6px"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      Rapport PDF
      <span style="background:rgba(201,151,42,.15);color:#C9972A;padding:1px 7px;border-radius:4px;font-size:10px;font-weight:800">PREMIUM</span>
    </button>
    <?php endif; ?>
  </div>

  <!-- ── Bloc Premium upsell (non-Premium) ── -->
  <?php if (!$isPremium): ?>
  <div class="premium-upsell" id="premiumUpsell">
    <div class="upsell-badge">
      <svg width="11" height="11" viewBox="0 0 24 24" fill="#F5D78E" stroke="none"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
      Fonctionnalité Premium
    </div>
    <div class="upsell-title">Télécharge ton rapport d'examen complet</div>
    <div class="upsell-desc">Les abonnés Premium peuvent exporter un rapport PDF professionnel de chaque examen — avec corrections détaillées, explications et analyse pédagogique personnalisée.</div>
    <div class="upsell-features">
      <div class="upsell-feature">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
        Page de couverture officielle
      </div>
      <div class="upsell-feature">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
        Corrections détaillées
      </div>
      <div class="upsell-feature">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
        Explications pour chaque erreur
      </div>
      <div class="upsell-feature">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
        Analyse pédagogique personnalisée
      </div>
      <div class="upsell-feature">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
        Recommandations IA
      </div>
      <div class="upsell-feature">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
        Format A4 imprimable
      </div>
    </div>
    <a href="/reussiteplus/tarifs.php" class="upsell-cta">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="#fff" stroke="none"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
      Passer à Premium — 10 000 CDF/mois
    </a>

    <!-- Aperçu flouté du PDF -->
    <div class="pdf-preview-blur">
      <div class="pdf-preview-inner">
        <div style="background:#007A5E;height:60px;border-radius:8px;margin-bottom:10px;display:flex;align-items:center;padding:0 16px">
          <div style="width:100px;height:14px;background:rgba(255,255,255,.3);border-radius:4px"></div>
          <div style="width:60px;height:10px;background:rgba(255,255,255,.2);border-radius:4px;margin-left:auto"></div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:10px">
          <?php for($i=0;$i<4;$i++): ?>
          <div style="height:50px;background:#f3f4f6;border-radius:6px;border:1px solid #e5e7eb"></div>
          <?php endfor; ?>
        </div>
        <?php for($i=0;$i<3;$i++): ?>
        <div style="height:40px;border:1px solid #e5e7eb;border-radius:6px;margin-bottom:6px;background:#fff;display:flex;align-items:center;padding:0 10px;gap:8px">
          <div style="width:16px;height:16px;border-radius:50%;background:<?= $i===0?'#E8F5F1':($i===1?'#FEF0EF':'#E8F5F1') ?>"></div>
          <div style="flex:1;height:8px;background:#f3f4f6;border-radius:4px"></div>
          <div style="width:40px;height:8px;background:#f3f4f6;border-radius:4px"></div>
        </div>
        <?php endfor; ?>
      </div>
      <div class="pdf-preview-overlay">
        <div class="pdf-preview-lock">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#C9972A" stroke-width="2" stroke-linecap="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          <div style="font-size:12px;font-weight:700;color:#F5D78E">Rapport complet disponible en Premium</div>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Détail des réponses ── -->
  <?php if ($answers): ?>
  <div class="section-header">
    <div class="section-title">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="vertical-align:-2px;margin-right:6px"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
      Détail des réponses
    </div>
    <span style="font-size:12px;color:var(--gris-500)"><?= $total ?> questions</span>
  </div>
  <div style="display:flex;flex-direction:column;gap:12px">
    <?php foreach ($answers as $idx => $ans): ?>
    <div class="card" style="border-left:4px solid <?= $ans['est_correcte'] ? 'var(--primary)' : 'var(--rouge)' ?>">
      <div style="display:flex;gap:12px;align-items:flex-start">
        <div style="width:28px;height:28px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:<?= $ans['est_correcte'] ? 'var(--primary-subtle)' : 'var(--rouge-light)' ?>">
          <?php if ($ans['est_correcte']): ?>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
          <?php else: ?>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--rouge)" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          <?php endif; ?>
        </div>
        <div style="flex:1">
          <div style="font-size:13px;font-weight:600;color:var(--gris-700);margin-bottom:6px">
            Q<?= $idx + 1 ?> — <?= badge_difficulte($ans['difficulte']) ?>
          </div>
          <div style="font-size:14px;color:var(--gris-900);margin-bottom:10px;line-height:1.5"><?= nl2br(e($ans['enonce'])) ?></div>
          <div style="display:flex;gap:8px;flex-wrap:wrap;font-size:13px">
            <span style="background:<?= $ans['est_correcte'] ? 'var(--primary-subtle)' : 'var(--rouge-light)' ?>;color:<?= $ans['est_correcte'] ? 'var(--primary-dark)' : 'var(--rouge)' ?>;padding:4px 10px;border-radius:6px">
              Votre réponse : <?= e($ans['option_choisie_lettre'] ?? '—') ?>) <?= e($ans['option_choisie_texte'] ?? 'Pas de réponse') ?>
            </span>
            <?php if (!$ans['est_correcte'] && $ans['bonne_reponse_texte']): ?>
            <span style="background:var(--primary-subtle);color:var(--primary-dark);padding:4px 10px;border-radius:6px">
              Bonne réponse : <?= e($ans['bonne_reponse_lettre']) ?>) <?= e($ans['bonne_reponse_texte']) ?>
            </span>
            <?php endif; ?>
          </div>
          <?php if (!$ans['est_correcte'] && $ans['explication']): ?>
          <div style="margin-top:10px;background:var(--bleu-light);color:var(--bleu);padding:10px 12px;border-radius:8px;font-size:13px;line-height:1.6">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="vertical-align:-2px;margin-right:5px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?= nl2br(e($ans['explication'])) ?>
          </div>
          <?php elseif (!$ans['est_correcte'] && !$isPremium): ?>
          <div style="margin-top:10px;background:var(--gold-light);padding:8px 12px;border-radius:8px;font-size:12px;color:var(--gold-dark)">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:4px"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            <a href="/reussiteplus/tarifs.php" style="color:var(--gold-dark);font-weight:600">Passez à Premium</a> pour voir les explications détaillées.
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div>

<!-- Données JSON pour le PDF -->
<script id="examData" type="application/json"><?= $examDataJson ?></script>

<?php if ($isPremium): ?>
<script>
function exportPDF() {
  const btn = document.getElementById('exportBtn');
  if (!btn || typeof ExamPdf === 'undefined') {
    alert('Générateur PDF non chargé. Actualisez la page.');
    return;
  }
  const origHTML = btn.innerHTML;
  btn.disabled   = true;
  btn.innerHTML  = `<svg class="export-spinner" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Génération en cours…`;

  try {
    const examData = JSON.parse(document.getElementById('examData').textContent);
    ExamPdf.open(examData);
  } catch(e) {
    alert('Erreur lors de la génération du PDF. Réessayez.');
  }

  setTimeout(() => {
    btn.disabled  = false;
    btn.innerHTML = origHTML;
  }, 1800);
}
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
