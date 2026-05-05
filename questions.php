<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Banque de questions';
$pageActive = 'questions';
$user = require_login();

$search   = trim($_GET['q'] ?? '');
$matiereF = trim($_GET['matiere'] ?? '');
$diffF    = trim($_GET['diff'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$limit    = 12;

$conditions = ["qb.status = 'PUBLIE'"];
$params     = [];

if ($search !== '') {
    $conditions[] = "qb.enonce LIKE ?";
    $params[]     = "%{$search}%";
}
if ($matiereF !== '') {
    $conditions[] = "qb.matiere_id = ?";
    $params[]     = $matiereF;
}
if ($diffF !== '') {
    $conditions[] = "qb.difficulte = ?";
    $params[]     = $diffF;
}
$where  = implode(' AND ', $conditions);
$total  = (int)dbRow("SELECT COUNT(*) AS n FROM question_bank qb WHERE {$where}", $params)['n'];
$offset = ($page - 1) * $limit;

$questions = dbAll(
    "SELECT qb.*, m.nom AS matiere_nom, m.couleur, m.icone
     FROM question_bank qb
     LEFT JOIN matieres m ON qb.matiere_id = m.id
     WHERE {$where}
     ORDER BY qb.created_at DESC
     LIMIT {$limit} OFFSET {$offset}",
    $params
);
$pages    = max(1, (int)ceil($total / $limit));
$matieres = dbAll("SELECT id, nom, icone FROM matieres WHERE actif=1 ORDER BY nom");

$signetIds = [];
foreach (dbAll("SELECT question_id FROM signets WHERE user_id=? AND question_id IS NOT NULL", [$user['id']]) as $r) {
    $signetIds[$r['question_id']] = true;
}

include __DIR__ . '/includes/header_app.php';
?>

<style>
.filter-bar{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;background:var(--blanc);border:1px solid var(--gris-200);border-radius:var(--radius-lg);padding:16px 20px;margin-bottom:20px}
.filter-bar .form-control{font-size:13px}
.q-card{background:var(--blanc);border:1px solid var(--gris-200);border-radius:var(--radius-lg);overflow:hidden;transition:box-shadow .2s}
.q-card:hover{box-shadow:var(--shadow-md)}
.q-card-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;padding:14px 16px 10px;border-bottom:1px solid var(--gris-100)}
.q-enonce{font-size:14px;font-weight:500;line-height:1.7;color:var(--gris-900);padding:14px 16px 10px}
.q-opts{padding:0 16px 12px;display:flex;flex-direction:column;gap:6px}
.q-opt{display:flex;align-items:flex-start;gap:10px;padding:10px 14px;border-radius:10px;font-size:13px;border:1.5px solid var(--gris-200);background:var(--gris-50);color:var(--gris-700);cursor:pointer;transition:all .2s;user-select:none;text-align:left;width:100%}
.q-opt:hover:not([disabled]){border-color:var(--primary);background:var(--primary-subtle)}
.q-opt .opt-letter{font-weight:700;font-size:12px;width:24px;height:24px;border-radius:6px;background:var(--gris-200);color:var(--gris-600);display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px}
.q-opt .opt-text{flex:1;line-height:1.5}
.q-opt .opt-icon{flex-shrink:0;margin-top:2px;opacity:0}
.q-opt.correct{border-color:#007A5E;background:#E6F4F0;color:#004D3B;font-weight:600}
.q-opt.correct .opt-letter{background:#007A5E;color:#fff}
.q-opt.correct .opt-icon{opacity:1}
.q-opt.wrong{border-color:#DC2626;background:#FEF2F2;color:#991B1B}
.q-opt.wrong .opt-letter{background:#DC2626;color:#fff}
.q-opt.wrong .opt-icon{opacity:1}
.q-opt[disabled]{cursor:default}
.q-expl{margin:0 16px 14px;padding:10px 14px;background:var(--gris-50);border-left:3px solid var(--primary);border-radius:0 8px 8px 0;font-size:13px;line-height:1.6;color:var(--gris-700);display:none}
.q-expl-lock{margin:0 16px 14px;padding:10px 14px;background:#FEF9EC;border-left:3px solid #F59E0B;border-radius:0 8px 8px 0;font-size:13px;display:none}
.q-footer{display:flex;align-items:center;justify-content:space-between;padding:10px 16px 14px;gap:8px;flex-wrap:wrap}
.pagination{display:flex;justify-content:center;gap:6px;flex-wrap:wrap;padding:28px 0 8px}
</style>

<form method="GET" class="filter-bar">
  <div style="flex:2;min-width:180px">
    <label style="font-size:11px;font-weight:600;color:var(--gris-500);display:block;margin-bottom:4px;text-transform:uppercase;letter-spacing:.4px">Recherche</label>
    <input class="form-control" name="q" value="<?= e($search) ?>" placeholder="Mot-clé dans la question...">
  </div>
  <div style="flex:1;min-width:150px">
    <label style="font-size:11px;font-weight:600;color:var(--gris-500);display:block;margin-bottom:4px;text-transform:uppercase;letter-spacing:.4px">Matière</label>
    <select class="form-control" name="matiere">
      <option value="">Toutes</option>
      <?php foreach ($matieres as $mat): ?>
      <option value="<?= e($mat['id']) ?>" <?= ($matiereF !== '' && (string)$mat['id'] === $matiereF) ? 'selected' : '' ?>>
        <?= matiere_icon($mat['icone'] ?? 'book', 13) ?> <?= e($mat['nom']) ?>
      </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div style="flex:1;min-width:140px">
    <label style="font-size:11px;font-weight:600;color:var(--gris-500);display:block;margin-bottom:4px;text-transform:uppercase;letter-spacing:.4px">Difficulté</label>
    <select class="form-control" name="diff">
      <option value="">Toutes</option>
      <?php foreach (['DEBUTANT'=>'Débutant','ELEMENTAIRE'=>'Élémentaire','INTERMEDIAIRE'=>'Intermédiaire','AVANCE'=>'Avancé','EXPERT'=>'Expert'] as $val => $label): ?>
      <option value="<?= $val ?>" <?= $diffF === $val ? 'selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div style="display:flex;gap:8px;align-self:flex-end">
    <button type="submit" class="btn btn-primary">
      <i data-lucide="search" style="width:14px;height:14px;vertical-align:-2px"></i> Filtrer
    </button>
    <?php if ($search !== '' || $matiereF !== '' || $diffF !== ''): ?>
    <a href="/reussiteplus/questions.php" class="btn btn-ghost" title="Réinitialiser">
      <i data-lucide="x" style="width:14px;height:14px;vertical-align:-2px"></i>
    </a>
    <?php endif; ?>
  </div>
</form>

<div class="section-header" style="margin-bottom:16px">
  <div class="section-title">
    <i data-lucide="brain-circuit"></i>
    <?php if ($total === 0): ?>Aucune question trouvée
    <?php elseif ($search !== '' || $matiereF !== '' || $diffF !== ''): ?><?= number_format($total) ?> résultat<?= $total > 1 ? 's' : '' ?>
    <?php else: ?><?= number_format($total) ?> questions disponibles<?php endif; ?>
  </div>
  <a href="/reussiteplus/examen.php" class="btn btn-primary btn-sm">
    <i data-lucide="pencil-line" style="width:13px;height:13px;vertical-align:-2px"></i> Passer un examen
  </a>
</div>

<?php if ($questions): ?>
<div style="display:flex;flex-direction:column;gap:14px">
<?php foreach ($questions as $q):
  $opts = dbAll("SELECT lettre, texte, est_correcte, explication FROM question_options WHERE question_id=? ORDER BY lettre ASC", [$q['id']]);
  $correcteLetter = '';
  $explication    = '';
  foreach ($opts as $o) {
    if ($o['est_correcte']) { $correcteLetter = $o['lettre']; if ($o['explication']) $explication = $o['explication']; }
  }
  $inSignet = isset($signetIds[$q['id']]);
  $qid = e($q['id']);
?>
<div class="q-card">
  <div class="q-card-head">
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <?php if ($q['matiere_nom']): ?>
      <span style="font-size:11px;background:<?= e($q['couleur'] ?? 'var(--primary)') ?>18;color:<?= e($q['couleur'] ?? 'var(--primary)') ?>;padding:3px 10px;border-radius:20px;font-weight:600">
        <?= matiere_icon($q['icone'] ?? 'book', 12) ?> <?= e($q['matiere_nom']) ?>
      </span>
      <?php endif; ?>
      <?= badge_difficulte($q['difficulte'] ?? 'INTERMEDIAIRE') ?>
      <?php if ($q['source']): ?><span style="font-size:10px;color:var(--gris-400)"><?= e($q['source']) ?></span><?php endif; ?>
    </div>
    <button class="btn btn-ghost btn-sm" onclick="toggleSignet(this,'<?= $qid ?>')"
      style="flex-shrink:0;color:<?= $inSignet ? 'var(--gold)' : 'var(--gris-400)' ?>"
      title="<?= $inSignet ? 'Retirer des signets' : 'Ajouter aux signets' ?>">
      <i data-lucide="<?= $inSignet ? 'bookmark-check' : 'bookmark' ?>" style="width:16px;height:16px"></i>
    </button>
  </div>
  <div class="q-enonce"><?= e($q['enonce']) ?></div>
  <?php if ($opts): ?>
  <div class="q-opts" id="opts-<?= $qid ?>">
    <?php foreach ($opts as $o): ?>
    <button type="button" class="q-opt" data-qid="<?= $qid ?>" data-letter="<?= e($o['lettre']) ?>" data-correct="<?= e($correcteLetter) ?>" onclick="selectOption(this)">
      <span class="opt-letter"><?= e($o['lettre']) ?></span>
      <span class="opt-text"><?= e($o['texte']) ?></span>
      <span class="opt-icon">
        <?php if ($o['est_correcte']): ?><i data-lucide="check-circle" style="width:16px;height:16px;stroke:#007A5E"></i>
        <?php else: ?><i data-lucide="x-circle" style="width:16px;height:16px;stroke:#DC2626"></i><?php endif; ?>
      </span>
    </button>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php if ($user['plan'] === 'GRATUIT'): ?>
  <div class="q-expl-lock" id="expl-<?= $qid ?>">
    <i data-lucide="lock" style="width:13px;height:13px;vertical-align:-2px;stroke:#D97706"></i>
    <a href="/reussiteplus/tarifs.php" style="color:#92640A;font-weight:600">Passez à Basique ou Premium</a> pour débloquer les explications détaillées.
  </div>
  <?php elseif ($explication): ?>
  <div class="q-expl" id="expl-<?= $qid ?>">
    <i data-lucide="lightbulb" style="width:14px;height:14px;vertical-align:-2px;stroke:var(--primary)"></i>
    <strong>Explication :</strong> <?= e($explication) ?>
  </div>
  <?php else: ?>
  <div class="q-expl" id="expl-<?= $qid ?>" style="background:var(--gris-50);border-left-color:var(--gris-300)">
    <i data-lucide="check-circle" style="width:13px;height:13px;vertical-align:-2px;stroke:#007A5E"></i>
    Bonne réponse ! Aucune explication supplémentaire pour cette question.
  </div>
  <?php endif; ?>
  <div class="q-footer">
    <div style="font-size:11px;color:var(--gris-400);display:flex;gap:10px">
      <?php if ($q['usage_count']): ?><span><i data-lucide="users" style="width:11px;height:11px;vertical-align:-1px"></i> <?= number_format($q['usage_count']) ?></span><?php endif; ?>
      <?php if ($q['success_rate'] !== null): ?><span><i data-lucide="trending-up" style="width:11px;height:11px;vertical-align:-1px"></i> <?= number_format($q['success_rate'], 0) ?>%</span><?php endif; ?>
    </div>
    <button type="button" class="btn btn-ghost btn-sm reset-btn" id="reset-<?= $qid ?>" onclick="resetQuestion('<?= $qid ?>')" style="display:none;font-size:11px">
      <i data-lucide="rotate-ccw" style="width:11px;height:11px;vertical-align:-1px"></i> Réessayer
    </button>
  </div>
</div>
<?php endforeach; ?>
</div>

<?php if ($pages > 1): ?>
<div class="pagination">
  <?php if ($page > 1): ?>
  <a href="?q=<?= urlencode($search) ?>&matiere=<?= urlencode($matiereF) ?>&diff=<?= urlencode($diffF) ?>&page=<?= $page-1 ?>" class="btn btn-ghost btn-sm"><i data-lucide="chevron-left" style="width:14px;height:14px;vertical-align:-2px"></i></a>
  <?php endif; ?>
  <?php $s=max(1,$page-3);$e=min($pages,$page+3);
  if ($s>1) echo '<span style="align-self:center;color:var(--gris-400)">…</span>';
  for ($i=$s;$i<=$e;$i++): ?>
  <a href="?q=<?= urlencode($search) ?>&matiere=<?= urlencode($matiereF) ?>&diff=<?= urlencode($diffF) ?>&page=<?= $i ?>" class="btn <?= $i===$page?'btn-primary':'btn-ghost' ?> btn-sm"><?= $i ?></a>
  <?php endfor;
  if ($e<$pages) echo '<span style="align-self:center;color:var(--gris-400)">…</span>'; ?>
  <?php if ($page < $pages): ?>
  <a href="?q=<?= urlencode($search) ?>&matiere=<?= urlencode($matiereF) ?>&diff=<?= urlencode($diffF) ?>&page=<?= $page+1 ?>" class="btn btn-ghost btn-sm"><i data-lucide="chevron-right" style="width:14px;height:14px;vertical-align:-2px"></i></a>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php else: ?>
<div class="card" style="text-align:center;padding:56px 24px">
  <i data-lucide="search-x" style="width:48px;height:48px;stroke:var(--gris-300);margin-bottom:16px"></i>
  <div style="font-size:16px;font-weight:700;margin-bottom:8px">Aucune question trouvée</div>
  <div style="color:var(--gris-500);margin-bottom:24px;font-size:14px">Essayez d'autres critères de recherche.</div>
  <a href="/reussiteplus/questions.php" class="btn btn-primary"><i data-lucide="refresh-cw" style="width:13px;height:13px;vertical-align:-2px"></i> Voir toutes les questions</a>
</div>
<?php endif; ?>

<script>
function selectOption(btn) {
  const qid = btn.dataset.qid, chosen = btn.dataset.letter, correct = btn.dataset.correct;
  const container = document.getElementById('opts-' + qid);
  if (!container) return;
  container.querySelectorAll('.q-opt').forEach(opt => {
    opt.setAttribute('disabled', 'disabled');
    const l = opt.dataset.letter;
    if (l === correct) opt.classList.add('correct');
    else if (l === chosen && chosen !== correct) opt.classList.add('wrong');
    const icon = opt.querySelector('.opt-icon');
    if (icon && (l === correct || (l === chosen && chosen !== correct))) icon.style.opacity = '1';
  });
  if (typeof lucide !== 'undefined') lucide.createIcons({nodes: Array.from(container.querySelectorAll('.opt-icon i'))});
  const expl = document.getElementById('expl-' + qid);
  if (expl) expl.style.display = 'block';
  const resetBtn = document.getElementById('reset-' + qid);
  if (resetBtn) resetBtn.style.display = '';
}
function resetQuestion(qid) {
  const container = document.getElementById('opts-' + qid);
  if (!container) return;
  container.querySelectorAll('.q-opt').forEach(opt => {
    opt.removeAttribute('disabled');
    opt.classList.remove('correct', 'wrong');
    const icon = opt.querySelector('.opt-icon');
    if (icon) icon.style.opacity = '0';
  });
  const expl = document.getElementById('expl-' + qid);
  if (expl) expl.style.display = 'none';
  const rb = document.getElementById('reset-' + qid);
  if (rb) rb.style.display = 'none';
}
async function toggleSignet(btn, questionId) {
  const fd = new FormData();
  fd.append('type', 'QUESTION'); fd.append('ref_id', questionId);
  fd.append('csrf_token', document.querySelector('[name=csrf_token]')?.value || '');
  try {
    const r = await fetch('/reussiteplus/api/signets.php', {method:'POST', body:fd});
    const d = await r.json();
    if (d.ok) {
      btn.innerHTML = d.added ? '<i data-lucide="bookmark-check" style="width:16px;height:16px"></i>' : '<i data-lucide="bookmark" style="width:16px;height:16px"></i>';
      btn.style.color = d.added ? 'var(--gold)' : 'var(--gris-400)';
      if (typeof lucide !== 'undefined') lucide.createIcons({nodes:[btn]});
    }
  } catch(e) { console.error(e); }
}
</script>
<?= csrf_field() ?>
<?php include __DIR__ . '/includes/footer_app.php'; ?>
