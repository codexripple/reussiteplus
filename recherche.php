<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Recherche';
$pageActive = '';
$user = require_login();

$q    = trim($_GET['q'] ?? '');
$type = $_GET['type'] ?? 'all'; // all | archives | questions
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;

$archivesResults  = [];
$questionsResults = [];
$totalArchives    = 0;
$totalQuestions   = 0;

if (strlen($q) >= 2) {
    if ($type === 'all' || $type === 'archives') {
        $totalArchives = (int)(dbRow(
            "SELECT COUNT(*) as n FROM archives
             WHERE status = 'PUBLIE' AND MATCH(titre, description, mots_cles) AGAINST(? IN BOOLEAN MODE)",
            [$q . '*']
        )['n'] ?? 0);

        // Fallback to LIKE if FULLTEXT returns 0 (short words, etc.)
        if ($totalArchives === 0) {
            $totalArchives = (int)(dbRow(
                "SELECT COUNT(*) as n FROM archives
                 WHERE status = 'PUBLIE' AND (titre LIKE ? OR description LIKE ?)",
                ["%$q%", "%$q%"]
            )['n'] ?? 0);
        }

        $archivesOffset = ($page - 1) * $limit;
        $archivesResults = dbAll(
            "SELECT a.*, m.nom as matiere_nom, m.icone as matiere_icone
             FROM archives a
             LEFT JOIN matieres m ON a.matiere_id = m.id
             WHERE a.status = 'PUBLIE'
               AND (a.titre LIKE ? OR a.description LIKE ? OR a.mots_cles LIKE ?)
             ORDER BY a.vues DESC, a.created_at DESC
             LIMIT $limit OFFSET $archivesOffset",
            ["%$q%", "%$q%", "%$q%"]
        );
    }

    if ($type === 'all' || $type === 'questions') {
        $totalQuestions = (int)(dbRow(
            "SELECT COUNT(*) as n FROM question_bank
             WHERE status = 'PUBLIE' AND enonce LIKE ?",
            ["%$q%"]
        )['n'] ?? 0);

        $questionsOffset = ($page - 1) * $limit;
        $questionsResults = dbAll(
            "SELECT qb.*, m.nom as matiere_nom, m.couleur
             FROM question_bank qb
             LEFT JOIN matieres m ON qb.matiere_id = m.id
             WHERE qb.status = 'PUBLIE' AND qb.enonce LIKE ?
             ORDER BY qb.created_at DESC
             LIMIT $limit OFFSET $questionsOffset",
            ["%$q%"]
        );
    }
}

// Signets questions pour icône de marque-page
$signetIds = [];
if ($questionsResults) {
    $signets = dbAll("SELECT question_id FROM signets WHERE user_id=? AND question_id IS NOT NULL", [$user['id']]);
    $signetIds = array_column($signets, 'question_id');
}

$totalResults = $totalArchives + $totalQuestions;

include __DIR__ . '/includes/header_app.php';
?>

<div style="max-width:860px;margin:0 auto">

  <?php if (!$q): ?>
  <!-- État vide — barre de recherche prominente -->
  <div style="text-align:center;padding:60px 20px">
    <div style="font-size:56px;margin-bottom:16px;color:var(--gris-300)"><i class="bi bi-search"></i></div>
    <div style="font-family:var(--font-display);font-size:26px;font-weight:800;color:var(--gris-900);margin-bottom:8px">
      Recherche globale
    </div>
    <div style="color:var(--gris-500);font-size:15px;margin-bottom:32px">
      Cherchez dans les archives, les questions et les matières
    </div>
    <form method="GET" style="max-width:500px;margin:0 auto;display:flex;gap:10px">
      <input class="form-control" name="q" placeholder="Saisissez votre recherche..." autofocus
             style="flex:1;font-size:16px;padding:12px 16px">
      <button type="submit" class="btn btn-primary" style="padding:12px 20px">Chercher</button>
    </form>
  </div>

  <?php else: ?>

  <!-- Barre de filtre -->
  <form method="GET" style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;align-items:center">
    <input class="form-control" name="q" value="<?= e($q) ?>"
           style="flex:1;min-width:200px" placeholder="Recherche...">
    <select class="form-control" name="type" style="width:auto">
      <option value="all"       <?= $type === 'all'       ? 'selected' : '' ?>>Tout</option>
      <option value="archives"  <?= $type === 'archives'  ? 'selected' : '' ?>>Archives uniquement</option>
      <option value="questions" <?= $type === 'questions' ? 'selected' : '' ?>>Questions uniquement</option>
    </select>
    <button type="submit" class="btn btn-primary">🔍 Rechercher</button>
    <a href="/reussiteplus/recherche.php" class="btn btn-ghost">✕</a>
  </form>

  <!-- Résumé -->
  <div style="margin-bottom:20px;color:var(--gris-600);font-size:14px">
    <?php if ($totalResults > 0): ?>
      <strong><?= $totalResults ?></strong> résultat(s) pour «&nbsp;<em><?= e($q) ?></em>&nbsp;»
    <?php else: ?>
      Aucun résultat pour «&nbsp;<em><?= e($q) ?></em>&nbsp;»
    <?php endif; ?>
  </div>

  <?php if ($totalResults === 0): ?>
  <div class="card" style="text-align:center;padding:48px 20px">
    <div style="font-size:48px;margin-bottom:12px">😕</div>
    <div style="font-family:var(--font-display);font-size:18px;font-weight:700;margin-bottom:8px">Aucun résultat trouvé</div>
    <div style="color:var(--gris-500);font-size:14px">Essayez d'autres mots-clés ou vérifiez l'orthographe.</div>
  </div>
  <?php endif; ?>

  <!-- Résultats archives -->
  <?php if ($archivesResults): ?>
  <div style="margin-bottom:28px">
    <div style="font-family:var(--font-display);font-size:17px;font-weight:800;margin-bottom:14px;display:flex;align-items:center;gap:8px">
      📁 Archives
      <span style="background:var(--primary);color:#fff;border-radius:20px;padding:1px 10px;font-size:12px;font-weight:600"><?= $totalArchives ?></span>
    </div>
    <div style="display:flex;flex-direction:column;gap:10px">
      <?php foreach ($archivesResults as $a): ?>
      <a href="/reussiteplus/archives.php?id=<?= e($a['id']) ?>"
         style="text-decoration:none;display:block;background:#fff;border:1.5px solid var(--gris-200);border-radius:var(--radius-lg);padding:14px 16px;transition:.2s"
         onmouseover="this.style.borderColor='var(--primary)'"
         onmouseout="this.style.borderColor='var(--gris-200)'">
        <div style="display:flex;align-items:center;gap:10px">
          <span style="font-size:20px"><?= e($a['matiere_icone'] ?? '📚') ?></span>
          <div style="flex:1;min-width:0">
            <div style="font-size:14px;font-weight:600;color:var(--gris-900);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
              <?= e($a['titre']) ?>
            </div>
            <div style="font-size:12px;color:var(--gris-500);margin-top:3px">
              <?= e($a['exam_type']) ?> · <?= e($a['annee']) ?> · <?= e($a['matiere_nom'] ?? '') ?>
              <?php if ($a['premium_only'] && $user['plan'] === 'GRATUIT'): ?>
                <span style="margin-left:6px;color:var(--gold);font-weight:600">⭐ Premium</span>
              <?php endif; ?>
            </div>
          </div>
          <span style="font-size:12px;color:var(--gris-400);flex-shrink:0"><?= (int)$a['vues'] ?> vues</span>
        </div>
        <?php if ($a['description']): ?>
        <div style="font-size:12px;color:var(--gris-600);margin-top:8px;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical">
          <?= e($a['description']) ?>
        </div>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>

    <?php if ($totalArchives > $limit && $type === 'archives'):
         $pag = paginate($totalArchives, $page, $limit); ?>
    <div style="display:flex;justify-content:center;gap:8px;margin-top:16px;flex-wrap:wrap">
      <?php if ($pag['page'] > 1): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pag['page'] - 1])) ?>" class="btn btn-ghost btn-sm">&larr; Pr&eacute;c&eacute;dent</a>
      <?php endif; ?>
      <?php for ($pg = max(1, $pag['page'] - 2); $pg <= min($pag['pages'], $pag['page'] + 2); $pg++): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pg])) ?>"
         class="btn <?= $pg === $pag['page'] ? 'btn-primary' : 'btn-ghost' ?> btn-sm"><?= $pg ?></a>
      <?php endfor; ?>
      <?php if ($pag['page'] < $pag['pages']): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pag['page'] + 1])) ?>" class="btn btn-ghost btn-sm">Suivant &rarr;</a>
      <?php endif; ?>
    </div>
    <?php elseif ($totalArchives > count($archivesResults) && $type === 'all'): ?>
    <a href="?q=<?= urlencode($q) ?>&type=archives" style="font-size:13px;color:var(--primary);margin-top:10px;display:inline-block">
      Voir tous les <?= $totalArchives ?> résultats dans Archives →
    </a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Résultats questions -->
  <?php if ($questionsResults): ?>
  <div>
    <div style="font-family:var(--font-display);font-size:17px;font-weight:800;margin-bottom:14px;display:flex;align-items:center;gap:8px">
      🧠 Questions
      <span style="background:var(--bleu);color:#fff;border-radius:20px;padding:1px 10px;font-size:12px;font-weight:600"><?= $totalQuestions ?></span>
    </div>
    <div style="display:flex;flex-direction:column;gap:10px">
      <?php foreach ($questionsResults as $qr): ?>
      <?php $isSignet = in_array($qr['id'], $signetIds, true); ?>
      <div style="background:#fff;border:1.5px solid var(--gris-200);border-radius:var(--radius-lg);padding:14px 16px">
        <div style="display:flex;gap:10px;align-items:flex-start">
          <div style="flex:1;min-width:0">
            <div style="font-size:13px;font-weight:500;color:var(--gris-900);line-height:1.5">
              <?= e($qr['enonce']) ?>
            </div>
            <div style="margin-top:8px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
              <?= badge_difficulte($qr['difficulte']) ?>
              <?php if ($qr['matiere_nom']): ?>
              <span style="font-size:11px;color:var(--gris-500)"><?= e($qr['matiere_nom']) ?></span>
              <?php endif; ?>
              <?php if ($qr['premium_only'] && $user['plan'] === 'GRATUIT'): ?>
              <span style="font-size:11px;color:var(--gold);font-weight:600">⭐ Premium</span>
              <?php endif; ?>
            </div>
          </div>
          <button class="btn btn-ghost btn-sm signet-btn"
                  data-id="<?= e($qr['id']) ?>" data-type="QUESTION"
                  title="<?= $isSignet ? 'Retirer des signets' : 'Ajouter aux signets' ?>"
                  style="flex-shrink:0">
            <?= $isSignet ? '🔖' : '📌' ?>
          </button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if ($totalQuestions > $limit && $type === 'questions'):
         $pag = paginate($totalQuestions, $page, $limit); ?>
    <div style="display:flex;justify-content:center;gap:8px;margin-top:16px;flex-wrap:wrap">
      <?php if ($pag['page'] > 1): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pag['page'] - 1])) ?>" class="btn btn-ghost btn-sm">&larr; Pr&eacute;c&eacute;dent</a>
      <?php endif; ?>
      <?php for ($pg = max(1, $pag['page'] - 2); $pg <= min($pag['pages'], $pag['page'] + 2); $pg++): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pg])) ?>"
         class="btn <?= $pg === $pag['page'] ? 'btn-primary' : 'btn-ghost' ?> btn-sm"><?= $pg ?></a>
      <?php endfor; ?>
      <?php if ($pag['page'] < $pag['pages']): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pag['page'] + 1])) ?>" class="btn btn-ghost btn-sm">Suivant &rarr;</a>
      <?php endif; ?>
    </div>
    <?php elseif ($totalQuestions > count($questionsResults) && $type === 'all'): ?>
    <a href="?q=<?= urlencode($q) ?>&type=questions" style="font-size:13px;color:var(--bleu);margin-top:10px;display:inline-block">
      Voir toutes les <?= $totalQuestions ?> questions →
    </a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php endif; // fin if $q ?>
</div>

<script>
document.querySelectorAll('.signet-btn').forEach(btn => {
  btn.addEventListener('click', async () => {
    const id   = btn.dataset.id;
    const type = btn.dataset.type;
    const fd   = new FormData();
    fd.append('type', type);
    fd.append('ref_id', id);
    const res  = await fetch('/reussiteplus/api/signets.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.ok) {
      btn.textContent = data.added ? '🔖' : '📌';
      btn.title = data.added ? 'Retirer des signets' : 'Ajouter aux signets';
    }
  });
});
</script>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
