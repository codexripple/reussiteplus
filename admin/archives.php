<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$pageTitle  = 'Gérer les archives';
$pageActive = 'admin_archives';
$user = require_admin();

$errors  = [];
$success = '';

// ── Helper upload PDF ──────────────────────────────────────
function upload_pdf(string $field, string $prefix): ?string {
    if (empty($_FILES[$field]['name'])) return null;
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) return null;

    // Vérification MIME réelle
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if ($mime !== 'application/pdf') return null;

    // Taille max 25 Mo
    if ($file['size'] > 25 * 1024 * 1024) return null;

    $dir = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/reussiteplus/uploads/archives/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $name = $prefix . '-' . bin2hex(random_bytes(8)) . '.pdf';
    if (move_uploaded_file($file['tmp_name'], $dir . $name)) {
        return APP_URL . '/uploads/archives/' . $name;
    }
    return null;
}

// Créer / éditer une archive
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_archive') {
        $titre       = trim($_POST['titre'] ?? '');
        $annee       = (int)($_POST['annee'] ?? date('Y'));
        $examType    = $_POST['exam_type'] ?? '';
        $sessionType = $_POST['session_type'] ?? 'ORDINAIRE';
        $matiereId   = $_POST['matiere_id'] ?? null;
        $provinceId  = trim($_POST['province_id'] ?? '') ?: null;
        $description = trim($_POST['description'] ?? '');
        $urlSujet    = trim($_POST['sujet_url'] ?? '');
        $urlCorrige  = trim($_POST['corrige_url'] ?? '');
        $sujetPages  = (int)($_POST['sujet_pages'] ?? 0) ?: null;
        $corrigePages= (int)($_POST['corrige_pages'] ?? 0) ?: null;
        $source      = trim($_POST['source'] ?? '');
        $premium     = isset($_POST['premium_only']) ? 1 : 0;
        $verifie     = isset($_POST['verifie']) ? 1 : 0;
        $status      = $_POST['status'] ?? 'PUBLIE';
        $editId      = $_POST['edit_id'] ?? null;

        // Upload PDF sujet
        $uploadedSujet   = upload_pdf('sujet_file', 'sujet');
        $uploadedCorrige = upload_pdf('corrige_file', 'corrige');
        if ($uploadedSujet)   $urlSujet   = $uploadedSujet;
        if ($uploadedCorrige) $urlCorrige = $uploadedCorrige;

        $validTypes    = ['ENAFEP', 'TENASOSP', 'EXAMEN_ETAT', 'DIOCESAIN', 'AUTRE'];
        $validSessions = ['ORDINAIRE', 'RATTRAPAGE', 'SPECIALE'];
        $validStatuts  = ['BROUILLON', 'REVISION', 'PUBLIE', 'ARCHIVE'];
        if (!$titre)                                    $errors[] = 'Titre requis.';
        if ($annee < 1990 || $annee > 2100)             $errors[] = 'Année invalide.';
        if (!in_array($examType, $validTypes))          $errors[] = 'Type d\'examen invalide.';
        if (!in_array($sessionType, $validSessions))    $errors[] = 'Session invalide.';
        if (!in_array($status, $validStatuts))          $errors[] = 'Statut invalide.';
        if (!$matiereId)                                $errors[] = 'Matière requise.';

        if (!$errors) {
            $data = [
                'titre'         => $titre,
                'annee'         => $annee,
                'exam_type'     => $examType,
                'session_type'  => $sessionType,
                'matiere_id'    => $matiereId,
                'province_id'   => $provinceId,
                'description'   => $description,
                'sujet_url'     => $urlSujet,
                'corrige_url'   => $urlCorrige,
                'sujet_pages'   => $sujetPages,
                'corrige_pages' => $corrigePages,
                'source'        => $source,
                'premium_only'  => $premium,
                'verifie'       => $verifie,
                'status'        => $status,
                'created_by'    => $user['id'],
            ];
            if ($editId) {
                unset($data['created_by']); // ne pas écraser à l'édition
                dbUpdate('archives', $data, 'id', $editId);
                $success = 'Archive mise à jour.';
                dbInsert('admin_logs', ['admin_id'=>$user['id'],'action'=>'EDIT_ARCHIVE','details'=>"id=$editId"]);
            } else {
                $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8','ASCII//TRANSLIT',$titre))) . '-' . time();
                $data['slug'] = $slug;
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
    } elseif ($action === 'toggle_status') {
        $arcId  = $_POST['arc_id'] ?? '';
        $newSt  = $_POST['new_status'] ?? '';
        if ($arcId && in_array($newSt, ['PUBLIE','ARCHIVE','BROUILLON'])) {
            dbQuery("UPDATE archives SET status=? WHERE id=?", [$newSt, $arcId]);
            $success = 'Statut mis à jour.';
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

$matieres  = dbAll("SELECT id, nom FROM matieres WHERE actif=1 ORDER BY nom ASC");
$provinces = dbAll("SELECT id, nom FROM provinces ORDER BY nom ASC");

include __DIR__ . '/../includes/header_app.php';
?>

<?php if ($errors): ?>
<div class="alert alert-error"><?= e($errors[0]) ?></div>
<?php endif; ?>
<?php if ($success): ?>
<div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 380px;gap:24px;align-items:start">
  <!-- Liste -->
  <div>
    <form method="GET" style="display:flex;gap:8px;margin-bottom:16px">
      <input class="form-control" name="q" value="<?= e($search) ?>" placeholder="Rechercher...">
      <button type="submit" class="btn btn-ghost">Rechercher</button>
    </form>

    <div class="card">
      <div class="card-header">
        <div class="card-title">Archives (<?= $total ?>)</div>
        <a href="/reussiteplus/admin/archives.php" class="btn btn-primary btn-sm">+ Nouvelle archive</a>
      </div>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Titre</th><th>Type</th><th>Année</th><th>Matière</th><th>Statut</th><th>DL</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($archives as $a): ?>
          <tr>
            <td style="font-size:13px;font-weight:500;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
              <?= e($a['titre']) ?>
              <?php if ($a['verifie']): ?><i data-lucide="check-circle" style="width:11px;height:11px;stroke:var(--primary);vertical-align:-1px;margin-left:3px"></i><?php endif; ?>
            </td>
            <td><span class="badge badge-gray" style="font-size:10px"><?= e($a['exam_type']) ?></span></td>
            <td style="font-size:12px"><?= $a['annee'] ?></td>
            <td style="font-size:11px;color:var(--gris-500)"><?= e($a['matiere_nom'] ?? '—') ?></td>
            <td>
              <?php
                $stColors = ['PUBLIE'=>'#007A5E','BROUILLON'=>'#6B7280','REVISION'=>'#C9972A','ARCHIVE'=>'#4A5568'];
                $sc = $stColors[$a['status']] ?? '#6B7280';
              ?>
              <span style="font-size:10px;background:<?= $sc ?>15;color:<?= $sc ?>;padding:2px 7px;border-radius:20px;font-weight:600"><?= e($a['status']) ?></span>
            </td>
            <td style="font-size:12px"><?= number_format((int)$a['telechargements']) ?></td>
            <td style="white-space:nowrap">
              <a href="?edit=<?= e($a['id']) ?>" class="btn btn-ghost btn-sm">Modifier</a>
              <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer cette archive ?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_archive">
                <input type="hidden" name="delete_id" value="<?= e($a['id']) ?>">
                <button type="submit" class="btn btn-danger btn-sm">Suppr.</button>
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
  <div class="card" style="position:sticky;top:80px;max-height:calc(100vh - 100px);overflow-y:auto">
    <div class="card-header" style="position:sticky;top:0;background:var(--blanc);z-index:2">
      <div class="card-title"><?= $editArchive ? 'Modifier l\'archive' : 'Nouvelle archive' ?></div>
      <?php if ($editArchive): ?><a href="/reussiteplus/admin/archives.php" class="btn btn-ghost btn-sm">Annuler</a><?php endif; ?>
    </div>
    <div style="padding:20px">
    <form method="POST" enctype="multipart/form-data">
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
            <?php foreach (['ENAFEP','TENASOSP','EXAMEN_ETAT','DIOCESAIN','AUTRE'] as $t): ?>
            <option value="<?= $t ?>" <?= ($editArchive['exam_type']??'') === $t ? 'selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Session</label>
          <select class="form-control" name="session_type">
            <?php foreach (['ORDINAIRE'=>'Ordinaire','RATTRAPAGE'=>'Rattrapage','SPECIALE'=>'Spéciale'] as $v=>$l): ?>
            <option value="<?= $v ?>" <?= ($editArchive['session_type']??'ORDINAIRE') === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div class="form-group">
          <label class="form-label">Année *</label>
          <input class="form-control" type="number" name="annee" min="1990" max="2100" value="<?= $editArchive['annee'] ?? date('Y') ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Statut</label>
          <select class="form-control" name="status">
            <?php foreach (['PUBLIE'=>'Publié','BROUILLON'=>'Brouillon','REVISION'=>'En révision','ARCHIVE'=>'Archivé'] as $v=>$l): ?>
            <option value="<?= $v ?>" <?= ($editArchive['status']??'PUBLIE') === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div class="form-group">
          <label class="form-label">Matière *</label>
          <select class="form-control" name="matiere_id" required>
            <option value="">-- choisir --</option>
            <?php foreach ($matieres as $m): ?>
            <option value="<?= e($m['id']) ?>" <?= ($editArchive['matiere_id']??'') === $m['id'] ? 'selected' : '' ?>><?= e($m['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Province</label>
          <select class="form-control" name="province_id">
            <option value="">Nationale</option>
            <?php foreach ($provinces as $p): ?>
            <option value="<?= e($p['id']) ?>" <?= ($editArchive['province_id']??'') === $p['id'] ? 'selected' : '' ?>><?= e($p['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Upload PDF Sujet -->
      <div class="form-group">
        <label class="form-label"><i data-lucide="file-text" style="width:13px;height:13px;vertical-align:-2px;margin-right:4px"></i> Sujet PDF</label>
        <?php if (!empty($editArchive['sujet_url'])): ?>
        <div style="font-size:11px;color:var(--primary);margin-bottom:5px;display:flex;align-items:center;gap:5px">
          <i data-lucide="check-circle" style="width:11px;height:11px"></i>
          <a href="<?= e($editArchive['sujet_url']) ?>" target="_blank" style="color:var(--primary)">Fichier actuel</a>
          — remplacer ci-dessous
        </div>
        <?php endif; ?>
        <input class="form-control" type="file" name="sujet_file" accept="application/pdf" style="padding:6px">
        <div style="font-size:11px;color:var(--gris-400);margin-top:3px">ou URL directe :</div>
        <input class="form-control" type="url" name="sujet_url" value="<?= e($editArchive['sujet_url'] ?? '') ?>" placeholder="https://..." style="margin-top:5px">
        <div style="display:flex;gap:8px;margin-top:5px">
          <div style="flex:1">
            <label class="form-label" style="font-size:11px">Nb pages</label>
            <input class="form-control" type="number" name="sujet_pages" min="1" value="<?= $editArchive['sujet_pages'] ?? '' ?>" placeholder="0">
          </div>
        </div>
      </div>

      <!-- Upload PDF Corrigé -->
      <div class="form-group">
        <label class="form-label"><i data-lucide="check-square" style="width:13px;height:13px;vertical-align:-2px;margin-right:4px"></i> Corrigé PDF</label>
        <?php if (!empty($editArchive['corrige_url'])): ?>
        <div style="font-size:11px;color:var(--gold);margin-bottom:5px;display:flex;align-items:center;gap:5px">
          <i data-lucide="check-circle" style="width:11px;height:11px;stroke:var(--gold)"></i>
          <a href="<?= e($editArchive['corrige_url']) ?>" target="_blank" style="color:var(--gold)">Fichier actuel</a>
          — remplacer ci-dessous
        </div>
        <?php endif; ?>
        <input class="form-control" type="file" name="corrige_file" accept="application/pdf" style="padding:6px">
        <div style="font-size:11px;color:var(--gris-400);margin-top:3px">ou URL directe :</div>
        <input class="form-control" type="url" name="corrige_url" value="<?= e($editArchive['corrige_url'] ?? '') ?>" placeholder="https://..." style="margin-top:5px">
        <div style="display:flex;gap:8px;margin-top:5px">
          <div style="flex:1">
            <label class="form-label" style="font-size:11px">Nb pages</label>
            <input class="form-control" type="number" name="corrige_pages" min="1" value="<?= $editArchive['corrige_pages'] ?? '' ?>" placeholder="0">
          </div>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea class="form-control" name="description" rows="2"><?= e($editArchive['description'] ?? '') ?></textarea>
      </div>

      <div class="form-group">
        <label class="form-label">Source / Référence</label>
        <input class="form-control" name="source" value="<?= e($editArchive['source'] ?? '') ?>" placeholder="Ex: MEPST, Ministère de l'Éducation…">
      </div>

      <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:16px">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px">
          <input type="checkbox" name="premium_only" value="1" <?= ($editArchive['premium_only'] ?? 0) ? 'checked' : '' ?>>
          Réserver aux abonnés Premium
        </label>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px">
          <input type="checkbox" name="verifie" value="1" <?= ($editArchive['verifie'] ?? 0) ? 'checked' : '' ?>>
          <i data-lucide="check-circle" style="width:13px;height:13px;stroke:var(--primary)"></i> Contenu vérifié
        </label>
      </div>

      <button type="submit" class="btn btn-primary btn-full"><?= $editArchive ? 'Mettre à jour' : 'Créer l\'archive' ?></button>
    </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer_app.php'; ?>
