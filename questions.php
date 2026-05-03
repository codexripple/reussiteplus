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

$conditions = ['qb.is_active = 1'];
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
$questions = dbAll(
    "SELECT qb.*, m.nom as matiere_nom, m.couleur, m.icone
     FROM question_bank qb
     LEFT JOIN matieres m ON qb.matiere_id = m.id
     WHERE $where
     ORDER BY qb.created_at DESC
     LIMIT $limit OFFSET " . (($page - 1) * $limit),
    $params
);
$pagination = paginate($total, $limit, $page);

$matieres = dbAll("SELECT id, nom, icone FROM matieres WHERE is_active=1 ORDER BY nom");

// Signets de l'utilisateur
$signets = dbAll("SELECT question_id FROM signets WHERE user_id=? AND type='QUESTION'", [$user['id']]);
$signetIds = array_column($signets, 'question_id');

include __DIR__ . '/includes/header_app.php';
?>

<!-- Filtres -->
<form method="GET" style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;align-items:flex-end">
  <div style="flex:1;min-width:200px">
    <input class="form-control" name="q" value="<?= e($search) ?>" placeholder="🔍 Chercher une question...">
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
      <option value="FACILE" <?= $diffF==='FACILE'?'selected':'' ?>>🟢 Facile</option>
      <option value="MOYEN" <?= $diffF==='MOYEN'?'selected':'' ?>>🟡 Moyen</option>
      <option value="DIFFICILE" <?= $diffF==='DIFFICILE'?'selected':'' ?>>🔴 Difficile</option>
    </select>
  </div>
  <button type="submit" class="btn btn-primary">Filtrer</button>
  <?php if ($search || $matiereF || $diffF): ?>
  <a href="/reussiteplus/questions.php" class="btn btn-ghost">✕ Reset</a>
  <?php endif; ?>
</form>

<div class="section-header">
  <div class="section-title">🧠 <?= number_format($total) ?> questions disponibles</div>
  <a href="/reussiteplus/examen.php" class="btn btn-primary btn-sm">✏️ Passer un examen</a>
</div>

<?php if ($questions): ?>
<div style="display:flex;flex-direction:column;gap:12px">
  <?php foreach ($questions as $q):
    $opts = dbAll("SELECT * FROM question_options WHERE question_id=? ORDER BY lettre ASC", [$q['id']]);
    $inSignet = in_array($q['id'], $signetIds, true);
  ?>
  <div class="card" style="border-left:4px solid <?= e($q['couleur']??'var(--primary)') ?>">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:10px">
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <span style="font-size:11px;background:var(--gris-100);padding:2px 10px;border-radius:20px;color:var(--gris-600)"><?= e($q['icone']??'📚') ?> <?= e($q['matiere_nom']??'—') ?></span>
        <?= badge_difficulte($q['difficulte']??'MOYEN') ?>
        <?php if ($q['source']): ?><span style="font-size:10px;color:var(--gris-400)"><?= e($q['source']) ?></span><?php endif; ?>
      </div>
      <button class="btn btn-ghost btn-sm" onclick="toggleSignet(this,'<?= e($q['id']) ?>')" style="flex-shrink:0;color:<?= $inSignet ? 'var(--gold)' : 'var(--gris-400)' ?>" title="<?= $inSignet ? 'Retirer des signets' : 'Ajouter aux signets' ?>">
        <?= $inSignet ? '🔖' : '🏷' ?>
      </button>
    </div>

    <div style="font-size:14px;font-weight:500;line-height:1.6;margin-bottom:12px"><?= e($q['enonce']) ?></div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px">
      <?php foreach ($opts as $o): ?>
      <div style="padding:6px 10px;border-radius:8px;font-size:13px;border:1.5px solid <?= $o['est_correcte'] ? 'var(--primary)' : 'var(--gris-200)' ?>;background:<?= $o['est_correcte'] ? 'var(--primary-subtle)' : 'var(--gris-50)' ?>;color:<?= $o['est_correcte'] ? 'var(--primary)' : 'var(--gris-700)' ?>;font-weight:<?= $o['est_correcte'] ? '600' : '400' ?>">
        <span style="font-weight:700;margin-right:6px"><?= e($o['lettre']) ?>)</span><?= e($o['texte']) ?>
        <?php if ($o['est_correcte']): ?><span style="float:right">✓</span><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if ($q['explication']): ?>
    <?php if ($user['plan'] !== 'GRATUIT'): ?>
    <div style="margin-top:10px;padding:8px 12px;background:var(--gris-50);border-radius:8px;font-size:12px;color:var(--gris-600);border-left:3px solid var(--primary)">
      💡 <?= e($q['explication']) ?>
    </div>
    <?php else: ?>
    <div style="margin-top:10px;padding:8px 12px;background:var(--gold-light);border-radius:8px;font-size:12px;color:var(--gold-dark)">
      💡 <a href="/reussiteplus/tarifs.php" style="color:var(--gold-dark);font-weight:600">Passez à Premium</a> pour voir les explications détaillées.
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if ($pagination['total_pages'] > 1): ?>
<div style="display:flex;justify-content:center;gap:8px;padding:24px 0;flex-wrap:wrap">
  <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
  <a href="?q=<?= urlencode($search) ?>&matiere=<?= urlencode($matiereF) ?>&diff=<?= urlencode($diffF) ?>&page=<?= $i ?>" class="btn <?= $i == $page ? 'btn-primary' : 'btn-ghost' ?> btn-sm"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php else: ?>
<div class="card" style="text-align:center;padding:48px">
  <div style="font-size:48px;margin-bottom:12px">🧠</div>
  <div style="font-size:16px;font-weight:600;margin-bottom:8px">Aucune question trouvée</div>
  <div style="color:var(--gris-500);margin-bottom:20px">Modifiez vos critères ou revenez plus tard.</div>
  <a href="/reussiteplus/questions.php" class="btn btn-ghost">Voir toutes les questions</a>
</div>
<?php endif; ?>

<script>
async function toggleSignet(btn, questionId) {
  const fd = new FormData();
  fd.append('type', 'QUESTION');
  fd.append('ref_id', questionId);
  fd.append('csrf_token', document.querySelector('[name=csrf_token]')?.value || '');
  const r = await fetch('/reussiteplus/api/signets.php', {method:'POST', body:fd});
  const d = await r.json();
  if (d.ok) {
    btn.textContent = d.added ? '🔖' : '🏷';
    btn.style.color  = d.added ? 'var(--gold)' : 'var(--gris-400)';
  }
}
</script>
<?= csrf_field() ?>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
