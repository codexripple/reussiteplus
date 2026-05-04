<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Mes résultats';
$pageActive = 'resultat';
$user = require_login();

$sessionId = $_GET['session'] ?? '';

// ── Mode liste : affiche l'historique de tous les examens ──
if (!$sessionId) {
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 15;
    $offset  = ($page - 1) * $perPage;

    $total = (int)(dbRow(
        "SELECT COUNT(*) as c FROM exam_sessions WHERE user_id=? AND statut='TERMINE'",
        [$user['id']]
    )['c'] ?? 0);

    $sessions = dbAll(
        "SELECT es.*, m.nom as matiere_nom, m.couleur as matiere_couleur, m.icone as matiere_icone
         FROM exam_sessions es
         LEFT JOIN matieres m ON es.matiere_id = m.id
         WHERE es.user_id=? AND es.statut='TERMINE'
         ORDER BY es.finished_at DESC
         LIMIT ? OFFSET ?",
        [$user['id'], $perPage, $offset]
    );

    include __DIR__ . '/includes/header_app.php';
    ?>
    <div style="margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
      <div>
        <h2 style="font-family:var(--font-display);font-size:20px;font-weight:800;margin:0">Mes résultats</h2>
        <p style="color:var(--gris-500);font-size:13px;margin:4px 0 0"><?= number_format($total) ?> examen<?= $total > 1 ? 's' : '' ?> terminé<?= $total > 1 ? 's' : '' ?></p>
      </div>
      <a href="/reussiteplus/examen.php" class="btn btn-primary"><i class="bi bi-pencil-square"></i> Passer un examen</a>
    </div>

    <?php if ($sessions): ?>
    <div style="display:flex;flex-direction:column;gap:12px">
      <?php foreach ($sessions as $s):
        $pct   = (float)($s['pourcentage'] ?? 0);
        $mins  = floor(($s['temps_passe'] ?? 0) / 60);
        $secs  = ($s['temps_passe'] ?? 0) % 60;
        $date  = date('d/m/Y à H:i', strtotime($s['finished_at'] ?? $s['started_at']));
        $color = score_couleur($pct);
      ?>
      <a href="/reussiteplus/resultat.php?session=<?= e($s['id']) ?>" style="text-decoration:none">
        <div class="card" style="display:flex;align-items:center;gap:16px;padding:14px 18px;transition:box-shadow .15s;cursor:pointer"
             onmouseenter="this.style.boxShadow='0 4px 16px rgba(0,0,0,.08)'"
             onmouseleave="this.style.boxShadow=''">
          <!-- Icône matière -->
          <div style="width:44px;height:44px;border-radius:12px;background:<?= e($s['matiere_couleur'] ?? 'var(--primary)') ?>20;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0">
            <?= e($s['matiere_icone'] ?? '📚') ?>
          </div>
          <!-- Info -->
          <div style="flex:1;min-width:0">
            <div style="font-weight:700;color:var(--gris-900);font-size:14px;overflow:hidden;text-overflow:ellipsis;display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical"><?= e($s['titre']) ?></div>
            <div style="font-size:12px;color:var(--gris-500);margin-top:2px">
              <?= e($s['matiere_nom'] ?? '—') ?> • <?= $date ?>
            </div>
          </div>
          <!-- Stats -->
          <div style="display:flex;align-items:center;gap:16px;flex-shrink:0">
            <div style="text-align:center">
              <div style="font-family:var(--font-display);font-size:20px;font-weight:900;color:<?= $color ?>;line-height:1"><?= number_format($pct, 0) ?>%</div>
              <div style="font-size:10px;color:var(--gris-500);text-transform:uppercase;margin-top:2px"><?= score_label($pct) ?></div>
            </div>
            <div style="text-align:center;display:none" class="d-md-block">
              <div style="font-size:13px;font-weight:700;color:var(--gris-700)"><?= (int)$s['nb_questions'] ?>Q</div>
              <div style="font-size:10px;color:var(--gris-500)"><?= $mins ?>:<?= str_pad($secs,2,'0',STR_PAD_LEFT) ?></div>
            </div>
            <div style="color:var(--gris-300);font-size:16px"><i class="bi bi-chevron-right"></i></div>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>

    <?php if ($total > $perPage): ?>
    <div style="display:flex;justify-content:center;gap:8px;margin-top:24px">
      <?php for ($p = 1; $p <= ceil($total/$perPage); $p++): ?>
      <a href="?page=<?= $p ?>" class="btn <?= $p === $page ? 'btn-primary' : 'btn-ghost' ?> btn-sm"><?= $p ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="card" style="text-align:center;padding:60px 20px">
      <div style="font-size:48px;margin-bottom:16px;opacity:.3"><i class="bi bi-clipboard-x"></i></div>
      <div style="font-size:18px;font-weight:700;color:var(--gris-700);margin-bottom:8px">Aucun résultat pour l'instant</div>
      <p style="color:var(--gris-500);margin-bottom:20px">Passez votre premier examen pour voir vos résultats ici.</p>
      <a href="/reussiteplus/examen.php" class="btn btn-primary"><i class="bi bi-pencil-square"></i> Commencer un examen</a>
    </div>
    <?php endif; ?>

    <?php include __DIR__ . '/includes/footer_app.php'; ?>
    <?php exit; ?>
<?php } // fin mode liste

// ── Mode détail : affiche le résultat d'un examen précis ──
$session = dbRow(
    "SELECT es.*, m.nom as matiere_nom, m.couleur
     FROM exam_sessions es
     LEFT JOIN matieres m ON es.matiere_id = m.id
     WHERE es.id=? AND es.user_id=?",
    [$sessionId, $user['id']]
);
if (!$session) { redirect('/reussiteplus/resultat.php', 'error', 'Session introuvable.'); }

// Réponses avec détails
$answers = dbAll(
    "SELECT ea.*, qb.enonce, qb.points, qb.difficulte,
            qo.texte as option_choisie_texte, qo.lettre as option_choisie_lettre,
            correct_opt.texte as bonne_reponse_texte, correct_opt.lettre as bonne_reponse_lettre,
            correct_opt.explication
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
    <div style="font-size:56px;margin-bottom:16px;color:<?= score_couleur($pct) ?>">
      <?php if ($pct >= 80): ?><i class="bi bi-trophy-fill"></i><?php elseif ($pct >= 60): ?><i class="bi bi-bullseye"></i><?php elseif ($pct >= 40): ?><i class="bi bi-graph-up-arrow"></i><?php else: ?><i class="bi bi-emoji-smile"></i><?php endif; ?>
    </div>
    <div style="font-family:var(--font-display);font-size:56px;font-weight:900;color:<?= score_couleur($pct) ?>">
      <?= number_format($pct, 1) ?>%
    </div>
    <div style="font-family:var(--font-display);font-size:22px;font-weight:700;color:var(--gris-900);margin-top:8px">
      <?= score_label($pct) ?>
    </div>
    <div style="font-size:14px;color:var(--gris-600);margin-top:6px">
      <?= e($session['matiere_nom'] ?? $session['titre']) ?> • <?= date('d/m/Y à H:i', strtotime($session['finished_at'] ?? $session['started_at'])) ?>
    </div>
  </div>

  <!-- Stats du résultat -->
  <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px">
    <div class="stat-card green">
      <div class="stat-label"><i class="bi bi-check-circle-fill"></i> Bonnes réponses</div>
      <div class="stat-value"><?= $bonnes ?></div>
      <div class="stat-sub">sur <?= $total ?> questions</div>
    </div>
    <div class="stat-card rouge">
      <div class="stat-label"><i class="bi bi-x-circle-fill"></i> Mauvaises</div>
      <div class="stat-value"><?= $mauvaises ?></div>
      <div class="stat-sub">à revoir</div>
    </div>
    <div class="stat-card gold">
      <div class="stat-label"><i class="bi bi-star-fill"></i> Score total</div>
      <div class="stat-value"><?= number_format((float)$session['score'], 1) ?></div>
      <div class="stat-sub">/ <?= number_format((float)$session['score_max'], 1) ?> pts</div>
    </div>
    <div class="stat-card bleu">
      <div class="stat-label"><i class="bi bi-stopwatch"></i> Temps passé</div>
      <div class="stat-value"><?= $mins ?>:<?= str_pad($secs, 2, '0', STR_PAD_LEFT) ?></div>
      <div class="stat-sub">minutes</div>
    </div>
  </div>

  <!-- Actions -->
  <div style="display:flex;gap:12px;margin-bottom:24px;flex-wrap:wrap">
    <a href="/reussiteplus/resultat.php" class="btn btn-ghost"><i class="bi bi-list-ul"></i> Tous mes résultats</a>
    <a href="/reussiteplus/examen.php" class="btn btn-primary"><i class="bi bi-arrow-repeat"></i> Refaire un examen</a>
    <a href="/reussiteplus/progression.php" class="btn btn-ghost"><i class="bi bi-graph-up"></i> Ma progression</a>
  </div>

  <!-- Détail des réponses -->
  <?php if ($answers): ?>
  <div class="section-header">
    <div class="section-title"><i class="bi bi-list-check"></i> Détail des réponses</div>
  </div>
  <div style="display:flex;flex-direction:column;gap:12px">
    <?php foreach ($answers as $idx => $ans): ?>
    <div class="card" style="border-left:4px solid <?= $ans['est_correcte'] ? 'var(--primary)' : 'var(--rouge)' ?>">
      <div style="display:flex;gap:12px;align-items:flex-start">
        <div style="font-size:20px;flex-shrink:0;color:<?= $ans['est_correcte'] ? 'var(--primary)' : 'var(--rouge)' ?>"><?= $ans['est_correcte'] ? '<i class="bi bi-check-circle-fill"></i>' : '<i class="bi bi-x-circle-fill"></i>' ?></div>
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
            <i class="bi bi-lightbulb-fill"></i> <?= nl2br(e($ans['explication'])) ?>
          </div>
          <?php elseif (!$ans['est_correcte'] && $user['plan'] === 'GRATUIT'): ?>
          <div style="margin-top:10px;background:var(--gold-light);padding:8px 12px;border-radius:8px;font-size:12px;color:var(--gold-dark)">
            <i class="bi bi-star-fill"></i> <a href="/reussiteplus/tarifs.php" style="color:var(--gold-dark);font-weight:600">Passez à Premium</a> pour voir les explications détaillées.
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
