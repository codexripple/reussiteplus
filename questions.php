<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Banque de questions';
$pageActive = 'questions';
$user = require_login();

// Filtres
$search   = trim($_GET['q'] ?? '');
$matiereF = $_GET['matiere'] ?? '';
$diffF    = $_GET['diff'] ?? '';
$page     = max(1, (int)($_GET['page'] ?? 1));
$limit    = 15;

$conditions = ["qb.status = 'PUBLIE'"];
$params     = [];
if ($search) {
    $conditions[] = "qb.enonce LIKE ?";
    $params[] = "%$search%";
}
if ($matiereF) {
    $conditions[] = "qb.matiere_id = ?";
    $params[] = $matiereF;
}
if ($diffF) {
    $conditions[] = "qb.difficulte = ?";
    $params[] = $diffF;
}
$where = implode(' AND ', $conditions);

$total = dbRow("SELECT COUNT(*) as n FROM question_bank qb WHERE $where", $params)['n'];
$offset = ($page - 1) * $limit;
$questions = dbAll(
    "SELECT qb.*, m.nom as matiere_nom, m.couleur, m.icone
     FROM question_bank qb
     LEFT JOIN matieres m ON qb.matiere_id = m.id
     WHERE $where
     ORDER BY qb.created_at DESC
     LIMIT $limit OFFSET $offset",
    $params
);
$pagination = paginate($total, $page, $limit);

$matieres = dbAll("SELECT id, nom, icone FROM matieres WHERE actif=1 ORDER BY nom");

// Signets de l'utilisateur
$signets = dbAll("SELECT question_id FROM signets WHERE user_id=? AND question_id IS NOT NULL", [$user['id']]);
$signetIds = array_column($signets, 'question_id');

include __DIR__ . '/includes/header_app.php';
?>

<!-- Filtres -->
<form method="GET" style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;align-items:flex-end">
  <div style="flex:1;min-width:200px">
    <input class="form-control" name="q" value="<?= e($search) ?>" placeholder="Chercher une question...">
  </div>
  <div>
    <select class="form-control" name="matiere">
      <option value="">Toutes les matières</option>
      <?php foreach ($matieres as $m): ?>
      <option value="<?= e($m['id']) ?>" <?= $matiereF === $m['id'] ? 'selected' : '' ?>><?= e($m['icone']??'📚') . ' ' . e($m['nom']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <select class="form-control" name="diff">
      <option value="">Toute difficulté</option>
      <option value="DEBUTANT" <?= $diffF==='DEBUTANT'?'selected':'' ?>>Débutant</option>
      <option value="ELEMENTAIRE" <?= $diffF==='ELEMENTAIRE'?'selected':'' ?>>Élémentaire</option>
      <option value="INTERMEDIAIRE" <?= $diffF==='INTERMEDIAIRE'?'selected':'' ?>>Intermédiaire</option>
      <option value="AVANCE" <?= $diffF==='AVANCE'?'selected':'' ?>>Avancé</option>
      <option value="EXPERT" <?= $diffF==='EXPERT'?'selected':'' ?>>Expert</option>
    </select>
  </div>
  <button type="submit" class="btn btn-primary">Filtrer</button>
  <?php if ($search || $matiereF || $diffF): ?>
  <a href="/reussiteplus/questions.php" class="btn btn-ghost"><i class="bi bi-x-lg"></i> Reset</a>
  <?php endif; ?>
</form>

<div class="section-header">
  <div class="section-title"><i class="bi bi-lightbulb"></i> <?= number_format($total) ?> questions disponibles</div>
  <a href="/reussiteplus/examen.php" class="btn btn-primary btn-sm"><i class="bi bi-pencil-square"></i> Passer un examen</a>
</div>

<?php if ($questions): ?>
<div style="display:flex;flex-direction:column;gap:12px">
  <?php foreach ($questions as $q):
    $opts = dbAll("SELECT * FROM question_options WHERE question_id=? ORDER BY lettre ASC", [$q['id']]);
    $inSignet = in_array($q['id'], $signetIds, true);
    // Préparer les données JSON pour le JS (sans révéler la bonne réponse côté HTML)
    $optsData = array_map(fn($o) => [
        'id'      => $o['id'],
        'lettre'  => $o['lettre'],
        'texte'   => $o['texte'],
        'correct' => (bool)$o['est_correcte'],
        'expl'    => ($user['plan'] !== 'GRATUIT') ? ($o['explication'] ?? '') : '',
    ], $opts);
    $correctExpl = '';
    foreach ($opts as $o) { if ($o['est_correcte'] && $o['explication']) { $correctExpl = $user['plan'] !== 'GRATUIT' ? $o['explication'] : ''; break; } }
  ?>
  <div class="card q-card" id="qcard-<?= e($q['id']) ?>" style="border-left:4px solid <?= e($q['couleur']??'var(--primary)') ?>">
    <!-- En-tête -->
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:10px">
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <span style="font-size:11px;background:var(--gris-100);padding:2px 10px;border-radius:20px;color:var(--gris-600)"><?= e($q['icone']??'📚') ?> <?= e($q['matiere_nom']??'—') ?></span>
        <?= badge_difficulte($q['difficulte']??'MOYEN') ?>
        <?php if ($q['source']): ?><span style="font-size:10px;color:var(--gris-400)"><?= e($q['source']) ?></span><?php endif; ?>
      </div>
      <button class="btn btn-ghost btn-sm signet-btn" onclick="toggleSignet(this,'<?= e($q['id']) ?>')"
              style="flex-shrink:0;color:<?= $inSignet ? 'var(--gold)' : 'var(--gris-400)' ?>"
              title="<?= $inSignet ? 'Retirer des signets' : 'Ajouter aux signets' ?>">
        <i class="bi <?= $inSignet ? 'bi-bookmark-fill' : 'bi-bookmark' ?>"></i>
      </button>
    </div>

    <!-- Énoncé -->
    <div style="font-size:14px;font-weight:500;line-height:1.6;margin-bottom:14px"><?= e($q['enonce']) ?></div>

    <!-- Options — toutes neutres, la bonne est cachée dans data-* -->
    <div class="q-options" data-qid="<?= e($q['id']) ?>" data-opts='<?= htmlspecialchars(json_encode($optsData), ENT_QUOTES) ?>'>
      <?php foreach ($opts as $o): ?>
      <button type="button" class="q-opt-btn" data-id="<?= e($o['id']) ?>"
              onclick="selectOption(this)"
              style="display:flex;align-items:center;gap:10px;width:100%;padding:9px 12px;border-radius:8px;font-size:13px;
                     border:1.5px solid var(--gris-200);background:var(--gris-50);color:var(--gris-700);
                     cursor:pointer;text-align:left;transition:all .15s;margin-bottom:6px;font-family:var(--font-body)">
        <span class="q-opt-letter" style="width:26px;height:26px;border-radius:50%;background:var(--gris-200);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;flex-shrink:0"><?= e($o['lettre']) ?></span>
        <span><?= e($o['texte']) ?></span>
        <span class="q-opt-icon" style="margin-left:auto;font-size:14px;display:none"></span>
      </button>
      <?php endforeach; ?>
    </div>

    <!-- Bouton "Vérifier" — masqué jusqu'à sélection -->
    <div style="display:flex;gap:10px;align-items:center;margin-top:4px">
      <button class="btn btn-primary btn-sm q-verify-btn" onclick="verifyAnswer(this)"
              style="display:none" data-qid="<?= e($q['id']) ?>">
        <i class="bi bi-check2-circle"></i> Vérifier ma réponse
      </button>
      <button class="btn btn-ghost btn-sm q-reveal-btn" onclick="revealAnswer(this)"
              data-qid="<?= e($q['id']) ?>">
        <i class="bi bi-eye"></i> Voir la réponse
      </button>
      <button class="btn btn-ghost btn-sm q-reset-btn" onclick="resetQuestion(this)"
              style="display:none" data-qid="<?= e($q['id']) ?>">
        <i class="bi bi-arrow-counterclockwise"></i> Réessayer
      </button>
    </div>

    <!-- Feedback (caché au départ) -->
    <div class="q-feedback" data-qid="<?= e($q['id']) ?>" style="display:none;margin-top:10px"></div>

    <?php if (!$correctExpl && $user['plan'] === 'GRATUIT'): ?>
    <!-- Tease explication premium -->
    <div class="q-premium-hint" data-qid="<?= e($q['id']) ?>" style="display:none;margin-top:8px;padding:8px 12px;background:var(--gold-light);border-radius:8px;font-size:12px;color:var(--gold-dark)">
      <i class="bi bi-star-fill"></i> <a href="/reussiteplus/tarifs.php" style="color:var(--gold-dark);font-weight:600">Passez à Premium</a> pour accéder aux explications détaillées.
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if ($pagination['pages'] > 1): ?>
<div style="display:flex;justify-content:center;gap:8px;padding:24px 0;flex-wrap:wrap">
  <?php for ($i = 1; $i <= $pagination['pages']; $i++): ?>
  <a href="?q=<?= urlencode($search) ?>&matiere=<?= urlencode($matiereF) ?>&diff=<?= urlencode($diffF) ?>&page=<?= $i ?>" class="btn <?= $i == $page ? 'btn-primary' : 'btn-ghost' ?> btn-sm"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php else: ?>
<div class="card" style="text-align:center;padding:48px">
  <div style="font-size:48px;margin-bottom:12px;color:var(--gris-300)"><i class="bi bi-lightbulb"></i></div>
  <div style="font-size:16px;font-weight:600;margin-bottom:8px">Aucune question trouvée</div>
  <div style="color:var(--gris-500);margin-bottom:20px">Modifiez vos critères ou revenez plus tard.</div>
  <a href="/reussiteplus/questions.php" class="btn btn-ghost">Voir toutes les questions</a>
</div>
<?php endif; ?>

<style>
.q-opt-btn:hover { border-color: var(--primary) !important; background: var(--primary-subtle) !important; color: var(--primary-dark) !important; }
.q-opt-btn:hover .q-opt-letter { background: var(--primary) !important; color: white !important; }
.q-opt-btn.selected { border-color: var(--primary) !important; background: var(--primary-subtle) !important; color: var(--primary-dark) !important; }
.q-opt-btn.selected .q-opt-letter { background: var(--primary) !important; color: white !important; }
.q-opt-btn.correct { border-color: var(--primary) !important; background: var(--primary-subtle) !important; color: var(--primary-dark) !important; pointer-events:none; }
.q-opt-btn.correct .q-opt-letter { background: var(--primary) !important; color: white !important; }
.q-opt-btn.wrong { border-color: var(--rouge) !important; background: #FEF0EF !important; color: var(--rouge) !important; pointer-events:none; }
.q-opt-btn.wrong .q-opt-letter { background: var(--rouge) !important; color: white !important; }
.q-opt-btn.missed { border-color: var(--primary) !important; background: var(--primary-subtle) !important; pointer-events:none; opacity:.7; }
.q-opt-btn.locked { pointer-events: none; cursor: default; }
</style>

<script>
// ── Sélection d'une option ────────────────────────────────
function selectOption(btn) {
  const wrap = btn.closest('.q-options');
  if (wrap.dataset.locked) return;
  const qid = wrap.dataset.qid;

  // Désélectionner les autres
  wrap.querySelectorAll('.q-opt-btn').forEach(b => {
    b.classList.remove('selected');
    b.querySelector('.q-opt-icon').style.display = 'none';
  });

  btn.classList.add('selected');
  // Afficher le bouton Vérifier
  const card = document.getElementById('qcard-' + qid);
  const verifyBtn = card.querySelector('.q-verify-btn');
  verifyBtn.dataset.selected = btn.dataset.id;
  verifyBtn.style.display = '';
}

// ── Vérifier la réponse choisie ───────────────────────────
function verifyAnswer(verifyBtn) {
  const qid = verifyBtn.dataset.qid;
  const selectedId = verifyBtn.dataset.selected;
  const card = document.getElementById('qcard-' + qid);
  const wrap = card.querySelector('.q-options');
  const opts = JSON.parse(wrap.dataset.opts);
  const correctOpt = opts.find(o => o.correct);

  revealWithChoice(card, wrap, opts, selectedId);
}

// ── Révéler directement sans choisir ─────────────────────
function revealAnswer(revealBtn) {
  const qid = revealBtn.dataset.qid;
  const card = document.getElementById('qcard-' + qid);
  const wrap = card.querySelector('.q-options');
  const opts = JSON.parse(wrap.dataset.opts);

  revealWithChoice(card, wrap, opts, null);
}

// ── Logique commune de révélation ────────────────────────
function revealWithChoice(card, wrap, opts, selectedId) {
  wrap.dataset.locked = '1';
  const correctOpt = opts.find(o => o.correct);

  wrap.querySelectorAll('.q-opt-btn').forEach(btn => {
    const oId = btn.dataset.id;
    btn.classList.remove('selected');
    btn.classList.add('locked');
    const icon = btn.querySelector('.q-opt-icon');
    icon.style.display = '';

    if (oId === correctOpt.id) {
      btn.classList.add('correct');
      icon.innerHTML = '<i class="bi bi-check-circle-fill" style="color:var(--primary)"></i>';
    } else if (selectedId && oId === selectedId) {
      btn.classList.add('wrong');
      icon.innerHTML = '<i class="bi bi-x-circle-fill" style="color:var(--rouge)"></i>';
    }
  });

  // Feedback
  const feedback = card.querySelector('.q-feedback');
  if (selectedId) {
    const isCorrect = selectedId === correctOpt.id;
    feedback.style.display = '';
    feedback.innerHTML = isCorrect
      ? `<div style="display:flex;gap:10px;align-items:center;padding:10px 14px;background:var(--primary-subtle);border-radius:10px;border:1px solid rgba(0,122,94,.2)">
           <i class="bi bi-trophy-fill" style="color:var(--primary);font-size:18px;flex-shrink:0"></i>
           <div><strong style="color:var(--primary-dark)">Bonne réponse !</strong> <span style="font-size:13px;color:var(--gris-600)">Vous avez bien trouvé.</span></div>
         </div>`
      : `<div style="display:flex;gap:10px;align-items:center;padding:10px 14px;background:#FEF0EF;border-radius:10px;border:1px solid rgba(201,52,42,.15)">
           <i class="bi bi-x-circle-fill" style="color:var(--rouge);font-size:18px;flex-shrink:0"></i>
           <div><strong style="color:var(--rouge)">Mauvaise réponse.</strong> <span style="font-size:13px;color:var(--gris-700)">La bonne réponse était <strong>${correctOpt.lettre})</strong>.</span></div>
         </div>`;

    // Explication si dispo
    if (correctOpt.expl) {
      feedback.innerHTML += `<div style="margin-top:8px;padding:8px 12px;background:var(--gris-50);border-radius:8px;font-size:12px;color:var(--gris-600);border-left:3px solid var(--primary)">
        <i class="bi bi-lightbulb-fill" style="color:var(--primary)"></i> ${escHtml(correctOpt.expl)}
      </div>`;
    }
  } else {
    // Juste révéler sans feedback de score
    feedback.style.display = '';
    feedback.innerHTML = `<div style="padding:8px 12px;background:var(--primary-subtle);border-radius:8px;font-size:12px;color:var(--primary-dark)">
      <i class="bi bi-info-circle"></i> La réponse correcte est <strong>${correctOpt.lettre})</strong> ${escHtml(correctOpt.texte)}.
      ${correctOpt.expl ? '<br><i class="bi bi-lightbulb-fill"></i> ' + escHtml(correctOpt.expl) : ''}
    </div>`;
  }

  // Masquer / réorganiser les boutons
  card.querySelector('.q-verify-btn').style.display = 'none';
  card.querySelector('.q-reveal-btn').style.display = 'none';
  card.querySelector('.q-reset-btn').style.display = '';
  // Afficher tease premium si pas d'explication
  const hint = card.querySelector('.q-premium-hint');
  if (hint) hint.style.display = '';
}

// ── Réinitialiser une question ────────────────────────────
function resetQuestion(resetBtn) {
  const qid = resetBtn.dataset.qid;
  const card = document.getElementById('qcard-' + qid);
  const wrap = card.querySelector('.q-options');

  delete wrap.dataset.locked;
  wrap.querySelectorAll('.q-opt-btn').forEach(btn => {
    btn.classList.remove('selected','correct','wrong','missed','locked');
    const icon = btn.querySelector('.q-opt-icon');
    icon.style.display = 'none';
    icon.innerHTML = '';
    // Restaurer styles neutres
    btn.style.borderColor = '';
    btn.style.background = '';
    btn.style.color = '';
  });

  card.querySelector('.q-feedback').style.display = 'none';
  card.querySelector('.q-verify-btn').style.display = 'none';
  card.querySelector('.q-reveal-btn').style.display = '';
  card.querySelector('.q-reset-btn').style.display = 'none';
  const hint = card.querySelector('.q-premium-hint');
  if (hint) hint.style.display = 'none';
}

// ── Signets ───────────────────────────────────────────────
async function toggleSignet(btn, questionId) {
  const fd = new FormData();
  fd.append('type', 'QUESTION');
  fd.append('ref_id', questionId);
  fd.append('csrf_token', document.querySelector('[name=csrf_token]')?.value || '');
  const r = await fetch('/reussiteplus/api/signets.php', {method:'POST', body:fd});
  const d = await r.json();
  if (d.ok) {
    btn.innerHTML = d.added
      ? '<i class="bi bi-bookmark-fill"></i>'
      : '<i class="bi bi-bookmark"></i>';
    btn.style.color = d.added ? 'var(--gold)' : 'var(--gris-400)';
  }
}

function escHtml(str) {
  return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
<?= csrf_field() ?>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
