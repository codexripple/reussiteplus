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

// Réponses avec détails
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
$mins = floor(($session['temps_passe'] ?? 0) / 60);
$secs = ($session['temps_passe'] ?? 0) % 60;

include __DIR__ . '/includes/header_app.php';
?>

<div style="max-width:800px;margin:0 auto">
  <!-- Score principal -->
  <div class="card" style="text-align:center;margin-bottom:24px;padding:40px">
    <div style="font-size:48px;margin-bottom:16px">
      <?php if ($pct >= 80): ?><i data-lucide="trophy" style="width:56px;height:56px;stroke:#C9972A"></i><?php elseif ($pct >= 60): ?><i data-lucide="target" style="width:56px;height:56px;stroke:#007A5E"></i><?php elseif ($pct >= 40): ?><i data-lucide="trending-up" style="width:56px;height:56px;stroke:#1E5FAD"></i><?php else: ?><i data-lucide="dumbbell" style="width:56px;height:56px;stroke:#C9342A"></i><?php endif; ?>
    </div>
    <div style="font-family:var(--font-body);font-size:56px;font-weight:900;color:<?= score_couleur($pct) ?>">
      <?= number_format($pct, 1) ?>%
    </div>
    <div style="font-family:var(--font-body);font-size:22px;font-weight:700;color:var(--gris-900);margin-top:8px">
      <?= score_label($pct) ?>
    </div>
    <div style="font-size:14px;color:var(--gris-600);margin-top:6px">
      <?= e($session['matiere_nom'] ?? $session['titre']) ?> • <?= date('d/m/Y à H:i', strtotime($session['finished_at'] ?? $session['started_at'])) ?>
    </div>
  </div>

  <!-- Stats du résultat -->
  <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px">
    <div class="stat-card green">
      <div class="stat-label"><i data-lucide="check-circle" style="width:13px;height:13px;vertical-align:-2px;margin-right:4px"></i> Bonnes réponses</div>
      <div class="stat-value"><?= $bonnes ?></div>
      <div class="stat-sub">sur <?= $total ?> questions</div>
    </div>
    <div class="stat-card rouge">
      <div class="stat-label"><i data-lucide="x-circle" style="width:13px;height:13px;vertical-align:-2px;margin-right:4px"></i> Mauvaises</div>
      <div class="stat-value"><?= $mauvaises ?></div>
      <div class="stat-sub">à revoir</div>
    </div>
    <div class="stat-card gold">
      <div class="stat-label"><i data-lucide="star" style="width:13px;height:13px;vertical-align:-2px;margin-right:4px"></i> Score total</div>
      <div class="stat-value"><?= number_format((float)$session['score'], 1) ?></div>
      <div class="stat-sub">/ <?= number_format((float)$session['score_max'], 1) ?> pts</div>
    </div>
    <div class="stat-card bleu">
      <div class="stat-label"><i data-lucide="timer" style="width:13px;height:13px;vertical-align:-2px;margin-right:4px"></i> Temps passé</div>
      <div class="stat-value"><?= $mins ?>:<?= str_pad($secs, 2, '0', STR_PAD_LEFT) ?></div>
      <div class="stat-sub">minutes</div>
    </div>
  </div>

  <!-- Actions -->
  <div style="display:flex;gap:12px;margin-bottom:24px;flex-wrap:wrap">
    <a href="/reussiteplus/examen.php" class="btn btn-primary"><i data-lucide="refresh-cw" style="width:14px;height:14px;vertical-align:-2px;margin-right:6px"></i> Refaire un examen</a>
    <a href="/reussiteplus/progression.php" class="btn btn-ghost"><i data-lucide="trending-up" style="width:14px;height:14px;vertical-align:-2px;margin-right:6px"></i> Voir ma progression</a>
    <a href="/reussiteplus/dashboard.php" class="btn btn-ghost"><i data-lucide="home" style="width:14px;height:14px;vertical-align:-2px;margin-right:6px"></i> Tableau de bord</a>
  </div>

  <!-- Détail des réponses -->
  <?php if ($answers): ?>
  <div class="section-header">
    <div class="section-title"><i data-lucide="list" style="width:15px;height:15px;vertical-align:-2px;margin-right:6px"></i> Détail des réponses</div>
  </div>
  <div style="display:flex;flex-direction:column;gap:12px">
    <?php foreach ($answers as $idx => $ans): ?>
    <div class="card" style="border-left:4px solid <?= $ans['est_correcte'] ? 'var(--primary)' : 'var(--rouge)' ?>">
      <div style="display:flex;gap:12px;align-items:flex-start">
        <div style="width:28px;height:28px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:<?= $ans['est_correcte'] ? 'var(--primary-subtle)' : 'var(--rouge-light)' ?>">
          <i data-lucide="<?= $ans['est_correcte'] ? 'check' : 'x' ?>" style="width:14px;height:14px;stroke:<?= $ans['est_correcte'] ? 'var(--primary)' : 'var(--rouge)' ?>"></i>
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
            <i data-lucide="lightbulb" style="width:13px;height:13px;vertical-align:-2px;margin-right:5px"></i><?= nl2br(e($ans['explication'])) ?>
          </div>
          <?php elseif (!$ans['est_correcte'] && $user['plan'] === 'GRATUIT'): ?>
          <div style="margin-top:10px;background:var(--gold-light);padding:8px 12px;border-radius:8px;font-size:12px;color:var(--gold-dark)">
            <i data-lucide="crown" style="width:12px;height:12px;vertical-align:-2px;margin-right:4px"></i><a href="/reussiteplus/tarifs.php" style="color:var(--gold-dark);font-weight:600">Passez à Premium</a> pour voir les explications détaillées.
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
