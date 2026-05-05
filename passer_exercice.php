<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Exercice';
$pageActive = 'mes_exercices';
$user = require_login();

$exoId = $_GET['id'] ?? '';
if (!$exoId) { redirect('/reussiteplus/mes_cours.php'); }
$exo = dbRow("SELECT * FROM exercices_ecole WHERE id=? AND actif=1", [$exoId]);
if (!$exo) { redirect('/reussiteplus/dashboard.php', 'error', 'Exercice introuvable ou non disponible.'); }

// Charger les questions et options
$questions = dbAll(
    "SELECT * FROM questions_exercice WHERE exercice_id=? ORDER BY ordre",
    [$exoId]
) ?? [];
foreach ($questions as &$q) {
    $q['options'] = dbAll("SELECT * FROM options_question WHERE question_id=? ORDER BY ordre", [$q['id']]) ?? [];
}
unset($q);

if (!$questions) { redirect('/reussiteplus/dashboard.php', 'error', 'Cet exercice n\'a pas encore de questions.'); }

// ── Soumission des réponses ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { http_response_code(403); exit; }
    $sessionId = $_POST['session_id'] ?? '';
    $session   = dbRow("SELECT * FROM sessions_exercice WHERE id=? AND eleve_id=? AND statut='EN_COURS'", [$sessionId, $user['id']]);
    if (!$session) { redirect('/reussiteplus/passer_exercice.php?id='.urlencode($exoId), 'error', 'Session invalide.'); }

    $duree   = max(1, (int)($_POST['duree_secondes'] ?? 0));
    $nbCorrect = 0;
    $totalPts  = 0;
    $scorePts  = 0;

    foreach ($questions as $q) {
        $qId = $q['id'];
        $totalPts += (float)$q['points'];
        $repTxt  = trim($_POST['rep_'.$qId] ?? '');
        $optChoisie = null;
        $estCorrect = null;
        $ptsObtenus = 0;

        if ($q['type'] === 'TEXTE_LIBRE') {
            $estCorrect = null; // Correction manuelle
            $ptsObtenus = 0;
        } elseif ($q['type'] === 'VRAI_FAUX' || $q['type'] === 'QCM') {
            $optChoisie = $repTxt ?: null;
            if ($optChoisie) {
                $opt = dbRow("SELECT * FROM options_question WHERE id=? AND question_id=?", [$optChoisie, $qId]);
                if ($opt && $opt['est_correcte']) {
                    $estCorrect = 1;
                    $ptsObtenus = (float)$q['points'];
                    $nbCorrect++;
                } else {
                    $estCorrect = 0;
                }
            }
        }
        $scorePts += $ptsObtenus;

        dbInsert('reponses_exercice', [
            'session_id'     => $sessionId,
            'question_id'    => $qId,
            'reponse_texte'  => $repTxt ?: null,
            'option_choisie' => $optChoisie,
            'est_correct'    => $estCorrect,
            'points_obtenus' => $ptsObtenus,
        ]);
    }

    // Convertir score en note /note_max
    $noteMax = (float)$exo['note_max'];
    $score   = $totalPts > 0 ? round($scorePts / $totalPts * $noteMax, 2) : 0;

    dbQuery("UPDATE sessions_exercice SET statut='TERMINE', score=?, nb_correct=?, nb_total=?, duree_secondes=?, termine_le=NOW() WHERE id=?",
        [$score, $nbCorrect, count($questions), $duree, $sessionId]);

    header('Location: /reussiteplus/passer_exercice.php?id='.urlencode($exoId).'&resultat='.urlencode($sessionId));
    exit;
}

// ── Mode résultat ────────────────────────────────────────
$resultatId = $_GET['resultat'] ?? '';
$session    = null;
$reponses   = [];
if ($resultatId) {
    $session = dbRow("SELECT * FROM sessions_exercice WHERE id=? AND eleve_id=? AND statut='TERMINE'", [$resultatId, $user['id']]);
    if ($session) {
        $reponses = dbAll(
            "SELECT r.*, q.question, q.type, q.explication, q.points,
                    o.texte as option_txt, o.est_correcte as opt_correct
             FROM reponses_exercice r
             JOIN questions_exercice q ON q.id=r.question_id
             LEFT JOIN options_question o ON o.id=r.option_choisie
             WHERE r.session_id=? ORDER BY q.ordre",
            [$resultatId]
        ) ?? [];
    }
}

// Créer une nouvelle session si besoin
$sessionId = null;
if (!$resultatId) {
    // Vérifier si déjà une session en cours
    $existing = dbRow("SELECT id FROM sessions_exercice WHERE exercice_id=? AND eleve_id=? AND statut='EN_COURS'", [$exoId, $user['id']]);
    if ($existing) {
        $sessionId = $existing['id'];
    } else {
        $sessionId = dbInsert('sessions_exercice', ['exercice_id'=>$exoId, 'eleve_id'=>$user['id']]);
    }
}

include __DIR__ . '/includes/header_app.php';
?>

<style>
@keyframes timer-pulse { 0%,100%{opacity:1} 50%{opacity:.5} }
.timer-urgent { animation: timer-pulse 1s infinite; color: #DC2626 !important; }
.q-block { background:var(--blanc); border:1.5px solid var(--gris-200); border-radius:var(--radius-lg); padding:20px; margin-bottom:14px; transition:border-color .2s; }
.q-block:focus-within { border-color:#1E5FAD; }
.option-label { display:flex; align-items:center; gap:10px; padding:12px 14px; border:1.5px solid var(--gris-200); border-radius:10px; cursor:pointer; transition:.15s; margin-bottom:6px; font-size:14px; }
.option-label:hover { border-color:#1E5FAD; background:#EFF6FF; }
.option-label input:checked ~ * { color:#1E5FAD; }
.option-label:has(input:checked) { border-color:#1E5FAD; background:#EFF6FF; }

/* Résultats */
.res-correct { border-left:4px solid #059669; background:#F0FDF4; }
.res-wrong { border-left:4px solid #DC2626; background:#FFF5F5; }
.res-libre { border-left:4px solid #F59E0B; background:#FFFBEB; }
</style>

<?php if ($session && $resultatId): ?>
<!-- ══ PAGE RÉSULTATS ═══════════════════════════════════ -->
<?php
$pct = $exo['note_max'] > 0 ? round($session['score'] / $exo['note_max'] * 100) : 0;
$mention = match(true) { $pct>=90=>'Excellent !', $pct>=70=>'Bien !', $pct>=50=>'Passable', default=>'À améliorer' };
$mentionColor = match(true) { $pct>=90=>'#059669', $pct>=70=>'#1E5FAD', $pct>=50=>'#B45309', default=>'#DC2626' };
?>

<div style="max-width:720px;margin:0 auto">
  <!-- Score card -->
  <div style="background:linear-gradient(135deg,#0f172a,<?= $mentionColor ?>88,#0f172a);border-radius:var(--radius-xl);padding:36px;margin-bottom:24px;text-align:center">
    <div style="font-size:60px;margin-bottom:12px"><?= $pct>=90?'🏆':($pct>=70?'🎯':($pct>=50?'📚':'💪')) ?></div>
    <div style="font-family:var(--font-display);font-size:52px;font-weight:900;color:#fff;line-height:1"><?= number_format($session['score'],1) ?></div>
    <div style="font-size:18px;color:rgba(255,255,255,.6);margin-bottom:12px">/ <?= $exo['note_max'] ?> points</div>
    <div style="font-family:var(--font-display);font-size:22px;font-weight:700;color:<?= $mentionColor ?>"><?= $mention ?></div>
    <div style="margin-top:16px;display:flex;justify-content:center;gap:16px;flex-wrap:wrap">
      <div style="background:rgba(255,255,255,.1);border-radius:10px;padding:10px 18px;text-align:center">
        <div style="font-family:var(--font-display);font-size:20px;font-weight:900;color:#fff"><?= $pct ?>%</div>
        <div style="font-size:10px;color:rgba(255,255,255,.4);text-transform:uppercase">Score</div>
      </div>
      <div style="background:rgba(255,255,255,.1);border-radius:10px;padding:10px 18px;text-align:center">
        <div style="font-family:var(--font-display);font-size:20px;font-weight:900;color:#34d399"><?= $session['nb_correct'] ?>/<?= $session['nb_total'] ?></div>
        <div style="font-size:10px;color:rgba(255,255,255,.4);text-transform:uppercase">Correctes</div>
      </div>
      <?php if ($session['duree_secondes']): ?>
      <div style="background:rgba(255,255,255,.1);border-radius:10px;padding:10px 18px;text-align:center">
        <div style="font-family:var(--font-display);font-size:20px;font-weight:900;color:#fff"><?= floor($session['duree_secondes']/60) ?>:<?= str_pad($session['duree_secondes']%60,2,'0',STR_PAD_LEFT) ?></div>
        <div style="font-size:10px;color:rgba(255,255,255,.4);text-transform:uppercase">Durée</div>
      </div>
      <?php endif; ?>
    </div>
    <!-- Barre de progression -->
    <div style="margin-top:20px;background:rgba(255,255,255,.1);border-radius:30px;height:10px;overflow:hidden">
      <div style="width:<?= $pct ?>%;height:100%;background:<?= $mentionColor ?>;border-radius:30px;transition:width 1s ease"></div>
    </div>
  </div>

  <!-- Corrigé détaillé -->
  <div style="font-family:var(--font-display);font-size:16px;font-weight:800;margin-bottom:14px;display:flex;align-items:center;gap:8px">
    <i data-lucide="check-square" style="width:18px;height:18px;stroke:#1E5FAD"></i> Corrigé détaillé
  </div>

  <?php foreach ($reponses as $i => $r): ?>
  <div class="q-block <?= $r['est_correct']===null ? 'res-libre' : ($r['est_correct'] ? 'res-correct' : 'res-wrong') ?>">
    <div style="display:flex;align-items:flex-start;gap:10px">
      <div style="width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;font-family:var(--font-display);font-weight:900;background:<?= $r['est_correct']===null ? '#FEF3C7' : ($r['est_correct'] ? '#D1FAE5' : '#FEE2E2') ?>;color:<?= $r['est_correct']===null ? '#B45309' : ($r['est_correct'] ? '#065F46' : '#991B1B') ?>">
        <?= $r['est_correct']===null ? '?' : ($r['est_correct'] ? '✓' : '✗') ?>
      </div>
      <div style="flex:1">
        <div style="font-size:13px;font-weight:700;color:var(--gris-700);margin-bottom:6px">Q<?= $i+1 ?>. <?= e($r['question']) ?></div>
        <?php if ($r['type']==='TEXTE_LIBRE'): ?>
          <div style="font-size:12px;background:rgba(0,0,0,.04);padding:8px 12px;border-radius:8px;color:var(--gris-700)">
            Votre réponse : <em><?= e($r['reponse_texte']??'(pas de réponse)') ?></em>
          </div>
          <div style="font-size:11px;color:#B45309;margin-top:6px">📝 Réponse libre — sera corrigée par l'enseignant</div>
        <?php else: ?>
          <div style="font-size:12px;color:var(--gris-600)">
            Votre réponse : <strong style="color:<?= $r['est_correct'] ? '#059669' : '#DC2626' ?>"><?= e($r['option_txt']??'(pas de réponse)') ?></strong>
            <?php if (!$r['est_correct'] && $r['option_choisie']): ?>
            — <?= $r['points_obtenus'] ?>/<?= $r['points'] ?> pts
            <?php endif; ?>
          </div>
        <?php endif; ?>
        <?php if ($r['explication']): ?>
        <div style="margin-top:8px;background:#FEF3C7;border-left:3px solid #F59E0B;border-radius:0 8px 8px 0;padding:7px 10px;font-size:11px;color:#92400E">
          💡 <?= e($r['explication']) ?>
        </div>
        <?php endif; ?>
      </div>
      <div style="font-size:12px;font-weight:700;color:<?= $r['est_correct'] ? '#059669' : '#DC2626' ?>"><?= number_format($r['points_obtenus'],1) ?>pt</div>
    </div>
  </div>
  <?php endforeach; ?>

  <div style="display:flex;gap:10px;margin-top:18px;flex-wrap:wrap">
    <a href="/reussiteplus/mes_exercices.php" class="btn btn-ghost" style="flex:1;justify-content:center">
      <i data-lucide="list" style="width:13px;height:13px;vertical-align:-2px"></i> Tous les exercices
    </a>
    <a href="/reussiteplus/passer_exercice.php?id=<?= urlencode($exoId) ?>" class="btn btn-primary" style="background:#1E5FAD;border-color:#1E5FAD;flex:1;justify-content:center">
      🔄 Recommencer
    </a>
  </div>
</div>

<?php else: ?>
<!-- ══ PASSER L'EXERCICE ══════════════════════════════════ -->
<div style="max-width:720px;margin:0 auto">

  <!-- Header exercice -->
  <div style="background:linear-gradient(135deg,#1e3a5f,#1E5FAD);border-radius:var(--radius-xl);padding:22px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
    <div>
      <div style="font-family:var(--font-display);font-size:18px;font-weight:900;color:#fff"><?= e($exo['titre']) ?></div>
      <?php if ($exo['description']): ?>
      <div style="font-size:12px;color:rgba(255,255,255,.55);margin-top:4px"><?= e($exo['description']) ?></div>
      <?php endif; ?>
      <div style="font-size:11px;color:rgba(255,255,255,.4);margin-top:6px"><?= count($questions) ?> questions · <?= $exo['note_max'] ?> points</div>
    </div>
    <div style="text-align:center;background:rgba(0,0,0,.3);border-radius:14px;padding:12px 20px;min-width:100px" id="timer-wrap">
      <div style="font-family:var(--font-display);font-size:28px;font-weight:900;color:#fff" id="timer">
        <?= str_pad($exo['duree_minutes'],2,'0',STR_PAD_LEFT) ?>:00
      </div>
      <div style="font-size:10px;color:rgba(255,255,255,.4);text-transform:uppercase">Temps restant</div>
    </div>
  </div>

  <form method="POST" id="quiz-form">
    <?= csrf_field() ?>
    <input type="hidden" name="session_id" value="<?= e($sessionId) ?>">
    <input type="hidden" name="duree_secondes" id="duree-input" value="0">

    <!-- Barre de progression questions -->
    <div style="display:flex;gap:5px;margin-bottom:20px;flex-wrap:wrap">
      <?php foreach ($questions as $i => $q): ?>
      <div class="q-dot" data-idx="<?= $i ?>" onclick="scrollToQ(<?= $i ?>)"
           style="width:30px;height:30px;border-radius:8px;border:1.5px solid var(--gris-300);background:var(--gris-100);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:var(--gris-500);transition:.15s"><?= $i+1 ?></div>
      <?php endforeach; ?>
    </div>

    <?php foreach ($questions as $i => $q): ?>
    <div class="q-block" id="q-block-<?= $i ?>" data-idx="<?= $i ?>">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px">
        <div style="background:linear-gradient(135deg,#1E5FAD,#2563EB);color:#fff;font-family:var(--font-display);font-weight:900;font-size:13px;width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0"><?= $i+1 ?></div>
        <div style="font-size:14px;font-weight:700;color:var(--gris-900)"><?= e($q['question']) ?></div>
        <div style="margin-left:auto;font-size:11px;color:var(--gris-400)"><?= $q['points'] ?> pt<?= $q['points']>1?'s':'' ?></div>
      </div>

      <?php if ($q['type'] === 'TEXTE_LIBRE'): ?>
      <textarea name="rep_<?= e($q['id']) ?>" class="form-control" rows="3"
                placeholder="Écrivez votre réponse ici…"
                oninput="markAnswered(<?= $i ?>)"></textarea>

      <?php elseif ($q['type'] === 'VRAI_FAUX'): ?>
      <div style="display:flex;gap:10px">
        <?php foreach ($q['options'] as $opt): ?>
        <label class="option-label" style="flex:1;justify-content:center;font-size:16px;font-weight:700">
          <input type="radio" name="rep_<?= e($q['id']) ?>" value="<?= e($opt['id']) ?>" required style="display:none" onchange="markAnswered(<?= $i ?>)">
          <?= $opt['texte'] === 'VRAI' ? '✓ VRAI' : '✗ FAUX' ?>
        </label>
        <?php endforeach; ?>
      </div>

      <?php else: // QCM ?>
      <?php foreach ($q['options'] as $opt): ?>
      <label class="option-label">
        <input type="radio" name="rep_<?= e($q['id']) ?>" value="<?= e($opt['id']) ?>" style="accent-color:#1E5FAD;width:16px;height:16px" onchange="markAnswered(<?= $i ?>)">
        <span><?= e($opt['texte']) ?></span>
      </label>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <div style="position:sticky;bottom:20px;background:var(--blanc);border:1.5px solid var(--gris-200);border-radius:16px;padding:14px 18px;box-shadow:0 4px 20px rgba(0,0,0,.12);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
      <div style="font-size:13px;color:var(--gris-600)" id="answered-count">0 / <?= count($questions) ?> répondues</div>
      <button type="submit" class="btn btn-primary" style="background:#059669;border-color:#059669;padding:12px 28px;font-size:15px;font-weight:800"
              onclick="document.getElementById('duree-input').value=totalSeconds-timeLeft">
        <i data-lucide="send" style="width:14px;height:14px;stroke:#fff;vertical-align:-2px"></i> Soumettre mes réponses
      </button>
    </div>
  </form>
</div>

<script>
// Timer
const totalSeconds = <?= $exo['duree_minutes'] * 60 ?>;
let timeLeft = totalSeconds;
let answeredSet = new Set();

const timerEl = document.getElementById('timer');
const countEl = document.getElementById('answered-count');

const countdown = setInterval(() => {
  timeLeft--;
  if (timeLeft <= 0) {
    clearInterval(countdown);
    document.getElementById('quiz-form').submit();
    return;
  }
  const m = String(Math.floor(timeLeft/60)).padStart(2,'0');
  const s = String(timeLeft%60).padStart(2,'0');
  timerEl.textContent = m+':'+s;
  if (timeLeft <= 120) timerEl.classList.add('timer-urgent');
}, 1000);

function markAnswered(idx) {
  answeredSet.add(idx);
  const dot = document.querySelector(`.q-dot[data-idx="${idx}"]`);
  if (dot) { dot.style.background='#1E5FAD'; dot.style.borderColor='#1E5FAD'; dot.style.color='#fff'; }
  countEl.textContent = answeredSet.size + ' / <?= count($questions) ?> répondues';
}

function scrollToQ(idx) {
  document.getElementById('q-block-'+idx)?.scrollIntoView({behavior:'smooth',block:'center'});
}
</script>

<?php endif; ?>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
