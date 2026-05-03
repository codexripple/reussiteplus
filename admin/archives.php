<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$pageTitle  = 'Gérer les archives';
$pageActive = 'admin';
$user = require_admin();

$errors  = [];
$success = '';

// Créer / éditer une archive
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_archive') {
        $titre       = trim($_POST['titre'] ?? '');
        $annee       = (int)($_POST['annee'] ?? date('Y'));
        $examType    = $_POST['exam_type'] ?? '';
        $matiereId   = $_POST['matiere_id'] ?? null;
        $description = trim($_POST['description'] ?? '');
        $urlSujet    = trim($_POST['url_sujet'] ?? '');
        $urlCorrige  = trim($_POST['url_corrige'] ?? '');
        $premium     = isset($_POST['premium_only']) ? 1 : 0;
        $editId      = $_POST['edit_id'] ?? null;

        $validTypes = ['ENAFEP', 'TENASOSP', 'EXAMEN_ETAT', 'SERNAFOR', 'AUTRE'];
        if (!$titre)                              $errors[] = 'Titre requis.';
        if ($annee < 1990 || $annee > 2100)       $errors[] = 'Année invalide.';
        if (!in_array($examType, $validTypes))    $errors[] = 'Type d\'examen invalide.';

        if (!$errors) {
            $data = [
                'titre'        => $titre,
                'annee'        => $annee,
                'exam_type'    => $examType,
                'matiere_id'   => $matiereId ?: null,
                'description'  => $description,
                'url_sujet'    => $urlSujet,
                'url_corrige'  => $urlCorrige,
                'premium_only' => $premium,
            ];
            if ($editId) {
                dbUpdate('archives', $data, ['id' => $editId]);
                $success = 'Archive mise à jour.';
                dbInsert('admin_logs', ['admin_id'=>$user['id'],'action'=>'EDIT_ARCHIVE','details'=>"id=$editId"]);
            } else {
                dbInsert('archives', $data);
                $success = 'Archive créée avec succès.';
                dbInsert('admin_logs', ['admin_id'=>$user['id'],'action'=>'CREATE_ARCHIVE','details'=>"titre=$titre"]);
            }
        }
    } elseif ($action === 'delete_archive') {
        $delId = $_POST['delete_id'] ?? '';
        if ($delId) {
            dbQuery("DELETE FROM archives WHERE id=?", [$delId]);
            dbInsert('admin_logs', ['admin_id'=>$user['id'],'action'=>'DELETE_ARCHIVE','details'=>"id=$delId"]);
            $success = 'Archive supprimée.';
        }
    }
}

// Édition
$editArchive = null;
if (isset($_GET['edit'])) {
    $editArchive = dbRow("SELECT * FROM archives WHERE id=?", [$_GET['edit']]);
}

// Liste archives
$search  = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$limit   = 20;
$params  = [];
$where   = '1=1';
if ($search) {
    $where .= " AND (a.titre LIKE ? OR a.description LIKE ?)";
    $s = "%$search%"; array_push($params, $s, $s);
}
$total    = dbRow("SELECT COUNT(*) as n FROM archives a WHERE $where", $params)['n'];
$archives = dbAll(
    "SELECT a.*, m.nom as matiere_nom FROM archives a
     LEFT JOIN matieres m ON a.matiere_id=m.id
     WHERE $where ORDER BY a.annee DESC, a.created_at DESC
     LIMIT $limit OFFSET " . (($page-1)*$limit),
    $params
);

$matieres = dbAll("SELECT id, nom FROM matieres WHERE is_active=1 ORDER BY nom ASC");

include __DIR__ . '/../includes/header_app.php';
?>

<?php if ($errors): ?>
<div class="alert alert-error">⚠️ <?= e($errors[0]) ?></div>
<?php endif; ?>
<?php if ($success): ?>
<div class="alert alert-success">✅ <?= e($success) ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 380px;gap:24px;align-items:start">
  <!-- Liste -->
  <div>
    <form method="GET" style="display:flex;gap:8px;margin-bottom:16px">
      <input class="form-control" name="q" value="<?= e($search) ?>" placeholder="Rechercher...">
      <button type="submit" class="btn btn-ghost">🔍</button>
    </form>

    <div class="card">
      <div class="card-header">
        <div class="card-title">📂 Archives (<?= $total ?>)</div>
        <a href="/reussiteplus/admin/archives.php" class="btn btn-primary btn-sm">+ Nouvelle archive</a>
      </div>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Titre</th><th>Type</th><th>Année</th><th>Matière</th><th>Premium</th><th>DL</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($archives as $a): ?>
          <tr>
            <td style="font-size:13px;font-weight:500;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($a['titre']) ?></td>
            <td><span class="badge badge-gray" style="font-size:10px"><?= e($a['exam_type']) ?></span></td>
            <td style="font-size:12px"><?= $a['annee'] ?></td>
            <td style="font-size:11px;color:var(--gris-500)"><?= e($a['matiere_nom'] ?? '—') ?></td>
            <td><?= $a['premium_only'] ? '<span style="color:var(--gold);font-size:12px">⭐</span>' : '<span style="font-size:12px;color:var(--gris-400)">—</span>' ?></td>
            <td style="font-size:12px"><?= number_format((int)$a['nb_telechargements']) ?></td>
            <td style="white-space:nowrap">
              <a href="?edit=<?= e($a['id']) ?>" class="btn btn-ghost btn-sm">✏️</a>
              <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer cette archive ?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_archive">
                <input type="hidden" name="delete_id" value="<?= e($a['id']) ?>">
                <button type="submit" class="btn btn-danger btn-sm">🗑</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($total > $limit): ?>
      <div style="display:flex;justify-content:center;gap:6px;padding:16px">
        <?php for ($i = 1; $i <= ceil($total/$limit); $i++): ?>
        <a href="?q=<?= urlencode($search) ?>&page=<?= $i ?>" class="btn <?= $i==$page?'btn-primary':'btn-ghost' ?> btn-sm"><?= $i ?></a>
        <?php endfor; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Formulaire création/édition -->
  <div class="card" style="position:sticky;top:80px">
    <div class="card-title" style="margin-bottom:20px"><?= $editArchive ? '✏️ Modifier l\'archive' : '➕ Nouvelle archive' ?></div>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_archive">
      <?php if ($editArchive): ?>
      <input type="hidden" name="edit_id" value="<?= e($editArchive['id']) ?>">
      <?php endif; ?>

      <div class="form-group">
        <label class="form-label">Titre *</label>
        <input class="form-control" name="titre" required value="<?= e($editArchive['titre'] ?? $_POST['titre'] ?? '') ?>" placeholder="Ex: ENAFEP 2024 — Mathématiques">
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div class="form-group">
          <label class="form-label">Type *</label>
          <select class="form-control" name="exam_type" required>
            <?php foreach (['ENAFEP','TENASOSP','EXAMEN_ETAT','SERNAFOR','AUTRE'] as $t): ?>
            <option value="<?= $t ?>" <?= ($editArchive['exam_type']??'') === $t || ($_POST['exam_type']??'') === $t ? 'selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Année *</label>
          <input class="form-control" type="number" name="annee" min="1990" max="2100" value="<?= $editArchive['annee'] ?? date('Y') ?>" required>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Matière</label>
        <select class="form-control" name="matiere_id">
          <option value="">Toutes matières</option>
          <?php foreach ($matieres as $m): ?>
          <option value="<?= e($m['id']) ?>" <?= ($editArchive['matiere_id']??'') === $m['id'] ? 'selected' : '' ?>><?= e($m['nom']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">URL Sujet PDF</label>
        <input class="form-control" type="url" name="url_sujet" value="<?= e($editArchive['url_sujet'] ?? '') ?>" placeholder="https://...">
      </div>

      <div class="form-group">
        <label class="form-label">URL Corrigé PDF</label>
        <input class="form-control" type="url" name="url_corrige" value="<?= e($editArchive['url_corrige'] ?? '') ?>" placeholder="https://...">
      </div>

      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea class="form-control" name="description" rows="2"><?= e($editArchive['description'] ?? '') ?></textarea>
      </div>

      <div class="form-group">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px">
          <input type="checkbox" name="premium_only" value="1" <?= ($editArchive['premium_only'] ?? 0) ? 'checked' : '' ?>>
          ⭐ Réserver aux abonnés Premium
        </label>
      </div>

      <button type="submit" class="btn btn-primary btn-full"><?= $editArchive ? '💾 Mettre à jour' : '➕ Créer l\'archive' ?></button>
      <?php if ($editArchive): ?>
      <a href="/reussiteplus/admin/archives.php" class="btn btn-ghost btn-full" style="margin-top:8px">Annuler</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer_app.php'; ?>
