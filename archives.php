<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Archives officielles';
$pageActive = 'archives';
$user = require_login();

// Filtres
$filterType    = $_GET['type'] ?? 'all';
$filterAnnee   = (int)($_GET['annee'] ?? 0);
$filterMatiere = $_GET['matiere'] ?? '';
$filterSession = $_GET['session'] ?? '';
$search        = trim($_GET['q'] ?? '');
$page          = max(1, (int)($_GET['page'] ?? 1));
$perPage       = 12;

// Charger les données de filtres
$matieres  = dbAll("SELECT id, nom, code, couleur FROM matieres WHERE actif=1 ORDER BY ordre, nom");
$anneesRaw = dbAll("SELECT DISTINCT annee FROM archives WHERE status='PUBLIE' ORDER BY annee DESC");
$annees    = array_column($anneesRaw, 'annee');

// Construire la requête
$where  = ["a.status = 'PUBLIE'"];
$params = [];

// Restriction premium
if ($user['plan'] === 'GRATUIT') {
    $where[] = "a.premium_only = 0";
}
if ($filterType !== 'all') {
    $where[] = "a.exam_type = ?"; $params[] = $filterType;
}
if ($filterAnnee) {
    $where[] = "a.annee = ?"; $params[] = $filterAnnee;
}
if ($filterMatiere) {
    $where[] = "a.matiere_id = ?"; $params[] = $filterMatiere;
}
if ($filterSession) {
    $where[] = "a.session_type = ?"; $params[] = $filterSession;
}
if ($search) {
    $where[] = "MATCH(a.titre, a.description, a.mots_cles) AGAINST(? IN BOOLEAN MODE)";
    $params[] = '+' . implode(' +', explode(' ', $search));
}

$whereSql = implode(' AND ', $where);

// Compter le total
$countStmt = dbRow("SELECT COUNT(*) as c FROM archives a WHERE $whereSql", $params);
$total     = (int)($countStmt['c'] ?? 0);
$pag       = paginate($total, $page, $perPage);

// Récupérer les archives
$archives = dbAll(
    "SELECT a.*, m.nom as matiere_nom, m.couleur as matiere_couleur, m.icone as matiere_icone,
            p.nom as province_nom
     FROM archives a
     JOIN matieres m ON a.matiere_id = m.id
     LEFT JOIN provinces p ON a.province_id = p.id
     WHERE $whereSql
     ORDER BY a.annee DESC, a.created_at DESC
     LIMIT ? OFFSET ?",
    [...$params, $perPage, $pag['offset']]
);

// Vue detail d'une archive
$detailId = $_GET['id'] ?? null;
$detail   = null;
if ($detailId) {
    $detail = dbRow(
        "SELECT a.*, m.nom as matiere_nom, m.couleur, m.icone,
                p.nom as province_nom
         FROM archives a
         JOIN matieres m ON a.matiere_id = m.id
         LEFT JOIN provinces p ON a.province_id = p.id
         WHERE a.id = ? AND a.status = 'PUBLIE'",
        [$detailId]
    );
    if ($detail) {
        // Incrémenter vues
        dbQuery("UPDATE archives SET vues = vues + 1 WHERE id = ?", [$detailId]);
        // Enregistrer signet si demandé
        if (isset($_POST['toggle_signet']) && csrf_verify()) {
            $existing = dbRow("SELECT id FROM signets WHERE user_id=? AND archive_id=?", [$user['id'], $detailId]);
            if ($existing) {
                dbQuery("DELETE FROM signets WHERE id=?", [$existing['id']]);
                echo json_encode(['bookmarked' => false]); exit;
            } else {
                dbInsert('signets', ['user_id' => $user['id'], 'archive_id' => $detailId]);
                echo json_encode(['bookmarked' => true]); exit;
            }
        }
        $isBookmarked = (bool)dbRow("SELECT id FROM signets WHERE user_id=? AND archive_id=?", [$user['id'], $detailId]);
    }
}

include __DIR__ . '/includes/header_app.php';
?>

<?php if ($detail): ?>
<!-- ── Vue Détail Archive ── -->
<div style="margin-bottom:16px">
  <a href="/reussiteplus/archives.php" style="color:var(--primary);font-size:13px;font-weight:500">← Retour aux archives</a>
</div>
<div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;align-items:start">
  <div>
    <div class="card">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:16px">
        <div>
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
            <span class="badge badge-green"><?= e($detail['exam_type']) ?></span>
            <span class="badge badge-gray"><?= e($detail['annee']) ?></span>
            <span class="badge badge-gray"><?= e($detail['session_type']) ?></span>
          </div>
          <h1 style="font-family:var(--font-display);font-size:22px;font-weight:800;color:var(--gris-900);line-height:1.3"><?= e($detail['titre']) ?></h1>
        </div>
        <form method="POST" style="flex-shrink:0">
          <?= csrf_field() ?>
          <input type="hidden" name="toggle_signet" value="1">
          <button type="submit" class="btn btn-ghost btn-sm" title="<?= $isBookmarked ? 'Retirer des signets' : 'Ajouter aux signets' ?>">
            <i data-lucide="<?= $isBookmarked ? 'bookmark-check' : 'bookmark' ?>" style="width:14px;height:14px;vertical-align:-2px"></i> <?= $isBookmarked ? 'Sauvegardé' : 'Sauvegarder' ?>
          </button>
        </form>
      </div>

      <div style="display:flex;gap:20px;flex-wrap:wrap;font-size:13px;color:var(--gris-600);margin-bottom:16px">
        <span><?= matiere_icon($detail['icone'] ?? 'book', 14) ?> <?= e($detail['matiere_nom']) ?></span>
        <?php if ($detail['province_nom']): ?><span><i data-lucide="map-pin" style="width:13px;height:13px;vertical-align:-2px"></i> <?= e($detail['province_nom']) ?></span><?php endif; ?>
        <span><i data-lucide="eye" style="width:13px;height:13px;vertical-align:-2px"></i> <?= number_format($detail['vues']) ?> vues</span>
        <span><i data-lucide="download" style="width:13px;height:13px;vertical-align:-2px"></i> <?= number_format($detail['telechargements']) ?> téléchargements</span>
        <?php if ($detail['source']): ?><span><i data-lucide="file" style="width:13px;height:13px;vertical-align:-2px"></i> <?= e($detail['source']) ?></span><?php endif; ?>
      </div>

      <?php if ($detail['description']): ?>
      <p style="font-size:14px;color:var(--gris-600);line-height:1.7;margin-bottom:20px"><?= e($detail['description']) ?></p>
      <?php endif; ?>

      <!-- Actions sur les fichiers -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <?php if ($detail['sujet_url']): ?>
        <div class="card" style="border:2px solid var(--primary-subtle);padding:20px">
          <div style="margin-bottom:8px"><i data-lucide="file-text" style="width:28px;height:28px;stroke:var(--primary)"></i></div>
          <div style="font-weight:700;margin-bottom:4px">Sujet de l'examen</div>
          <div style="font-size:12px;color:var(--gris-500);margin-bottom:12px"><?= $detail['sujet_pages'] ? $detail['sujet_pages'] . ' pages' : 'PDF' ?></div>
          <a href="<?= e($detail['sujet_url']) ?>" target="_blank" class="btn btn-primary btn-sm btn-full"
             onclick="incrementDownload('<?= e($detail['id']) ?>')">
            <i data-lucide="download" style="width:13px;height:13px;vertical-align:-2px;margin-right:5px"></i> Télécharger le sujet
          </a>
        </div>
        <?php else: ?>
        <div class="card" style="background:var(--gris-50);padding:20px;text-align:center">
          <div style="margin-bottom:8px;opacity:.4"><i data-lucide="file-text" style="width:28px;height:28px;stroke:var(--gris-400)"></i></div>
          <div style="font-size:13px;color:var(--gris-400)">Sujet non disponible</div>
        </div>
        <?php endif; ?>

        <?php
        $hasCorrige = (bool)$detail['corrige_url'];
        $canCorrige = $user['plan'] !== 'GRATUIT' && plan_actif($user);
        ?>
        <?php if ($hasCorrige): ?>
        <div class="card <?= !$canCorrige ? 'premium-lock' : '' ?>" style="border:2px solid var(--gold-light);padding:20px">
          <?php if (!$canCorrige): ?>
          <div class="lock-overlay" style="border-radius:var(--radius-lg)">
            <div style="margin-bottom:8px"><i data-lucide="lock" style="width:32px;height:32px;stroke:var(--gold-dark)"></i></div>
            <div style="font-weight:700;margin-bottom:4px">Réservé Premium</div>
            <a href="/reussiteplus/tarifs.php" class="btn btn-gold btn-sm">Déverrouiller</a>
          </div>
          <?php endif; ?>
          <div style="margin-bottom:8px"><i data-lucide="check-circle" style="width:28px;height:28px;stroke:#C9972A"></i></div>
          <div style="font-weight:700;margin-bottom:4px">Corrigé officiel</div>
          <div style="font-size:12px;color:var(--gris-500);margin-bottom:12px"><?= $detail['corrige_pages'] ? $detail['corrige_pages'] . ' pages' : 'PDF' ?></div>
          <?php if ($canCorrige): ?>
          <a href="<?= e($detail['corrige_url']) ?>" target="_blank" class="btn btn-gold btn-sm btn-full"
             onclick="incrementDownload('<?= e($detail['id']) ?>')">
            <i data-lucide="download" style="width:13px;height:13px;vertical-align:-2px;margin-right:5px"></i> Télécharger le corrigé
          </a>
          <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="card" style="background:var(--gris-50);padding:20px;text-align:center">
          <div style="margin-bottom:8px;opacity:.4"><i data-lucide="check-circle" style="width:28px;height:28px;stroke:var(--gris-400)"></i></div>
          <div style="font-size:13px;color:var(--gris-400)">Corrigé non disponible</div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Sidebar détail -->
  <div>
    <div class="card" style="margin-bottom:16px">
      <div class="card-title" style="margin-bottom:16px"><i data-lucide="target" style="width:15px;height:15px;vertical-align:-2px;margin-right:6px;stroke:var(--primary)"></i> S'entraîner avec cet examen</div>
      <a href="/reussiteplus/examen.php?archive=<?= e($detail['id']) ?>" class="btn btn-primary btn-full">
        <i data-lucide="pencil-line" style="width:14px;height:14px;vertical-align:-2px;margin-right:5px"></i> Passer l'examen en ligne
      </a>
      <div style="font-size:11px;color:var(--gris-500);text-align:center;margin-top:8px">Questions interactives chronométrées</div>
    </div>

    <?php if ($user['plan'] === 'GRATUIT'): ?>
    <div class="card" style="background:linear-gradient(135deg,#F5E6C0,#FFF7E6);border:1px solid rgba(201,151,42,.3)">
      <div style="margin-bottom:8px"><i data-lucide="star" style="width:24px;height:24px;stroke:#C9972A"></i></div>
      <div style="font-weight:700;color:var(--gold-dark);margin-bottom:6px">Accédez aux corrigés</div>
      <p style="font-size:13px;color:var(--gris-600);margin-bottom:12px">Déverrouillez tous les corrigés officiels avec un plan Basique ou Premium.</p>
      <a href="/reussiteplus/tarifs.php" class="btn btn-gold btn-sm btn-full">Voir les offres →</a>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php else: ?>
<!-- ── Liste des Archives ── -->

<!-- Filtres -->
<div class="card" style="margin-bottom:24px;padding:20px">
  <form method="GET" action="" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
    <div style="flex:2;min-width:200px">
      <label class="form-label">Recherche</label>
      <input class="form-control" type="text" name="q" placeholder="Ex: mathématiques, géométrie..." value="<?= e($search) ?>">
    </div>
    <div style="min-width:140px">
      <label class="form-label">Type d'examen</label>
      <select class="form-control" name="type">
        <option value="all" <?= $filterType === 'all' ? 'selected' : '' ?>>Tous les types</option>
        <option value="ENAFEP" <?= $filterType === 'ENAFEP' ? 'selected' : '' ?>>ENAFEP</option>
        <option value="TENASOSP" <?= $filterType === 'TENASOSP' ? 'selected' : '' ?>>TENASOSP</option>
        <option value="EXAMEN_ETAT" <?= $filterType === 'EXAMEN_ETAT' ? 'selected' : '' ?>>Examen d'État</option>
        <option value="DIOCESAIN" <?= $filterType === 'DIOCESAIN' ? 'selected' : '' ?>>Diocésain</option>
      </select>
    </div>
    <div style="min-width:110px">
      <label class="form-label">Année</label>
      <select class="form-control" name="annee">
        <option value="">Toutes</option>
        <?php foreach ($annees as $a): ?>
        <option value="<?= $a ?>" <?= $filterAnnee === (int)$a ? 'selected' : '' ?>><?= $a ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="min-width:150px">
      <label class="form-label">Matière</label>
      <select class="form-control" name="matiere">
        <option value="">Toutes</option>
        <?php foreach ($matieres as $m): ?>
        <option value="<?= e($m['id']) ?>" <?= $filterMatiere === $m['id'] ? 'selected' : '' ?>><?= e($m['nom']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <button type="submit" class="btn btn-primary">Filtrer</button>
      <a href="/reussiteplus/archives.php" class="btn btn-ghost" style="margin-left:8px">Réinitialiser</a>
    </div>
  </form>
</div>

<!-- Résultats header -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
  <div style="font-size:14px;color:var(--gris-600)">
    <strong><?= number_format($total) ?></strong> archive<?= $total > 1 ? 's' : '' ?> trouvée<?= $total > 1 ? 's' : '' ?>
    <?php if ($search): ?> pour "<em><?= e($search) ?></em>"<?php endif; ?>
  </div>
  <?php if ($user['plan'] === 'GRATUIT'): ?>
  <div style="font-size:12px;background:var(--gold-light);color:var(--gold-dark);padding:4px 12px;border-radius:20px;display:inline-flex;align-items:center;gap:5px">
    <i data-lucide="lock" style="width:11px;height:11px"></i> Contenus premium masqués — <a href="/reussiteplus/tarifs.php" style="font-weight:600;color:var(--gold-dark)">Déverrouiller</a>
  </div>
  <?php endif; ?>
</div>

<?php if ($archives): ?>
<div class="exams-grid">
  <?php foreach ($archives as $arc): ?>
  <a href="/reussiteplus/archives.php?id=<?= e($arc['id']) ?>" style="text-decoration:none" class="exam-card">
    <div class="exam-card-header" style="background:linear-gradient(135deg,<?= e($arc['matiere_couleur'] ?? '#007A5E') ?>15,<?= e($arc['matiere_couleur'] ?? '#007A5E') ?>08)">
      <span class="badge" style="background:<?= e($arc['matiere_couleur'] ?? 'var(--primary)') ?>20;color:<?= e($arc['matiere_couleur'] ?? 'var(--primary)') ?>">
        <?= matiere_icon($arc['matiere_icone'] ?? 'book', 13) ?> <?= e($arc['exam_type']) ?>
      </span>
      <div style="text-align:right">
        <div style="font-size:12px;font-weight:700;color:var(--gris-700)"><?= e($arc['annee']) ?></div>
        <div style="font-size:10px;color:var(--gris-500)"><?= e($arc['session_type']) ?></div>
      </div>
    </div>
    <div class="exam-card-body">
      <div class="exam-card-title"><?= e($arc['titre']) ?></div>
      <div class="exam-meta">
        <span class="exam-meta-item"><i data-lucide="book" style="width:12px;height:12px;vertical-align:-2px;margin-right:3px"></i> <?= e($arc['matiere_nom']) ?></span>
        <?php if ($arc['province_nom']): ?>
        <span class="exam-meta-item"><i data-lucide="map-pin" style="width:11px;height:11px;vertical-align:-2px;margin-right:2px"></i><?= e($arc['province_nom']) ?></span>
        <?php endif; ?>
        <span class="exam-meta-item"><i data-lucide="eye" style="width:11px;height:11px;vertical-align:-2px;margin-right:2px"></i><?= number_format($arc['vues']) ?></span>
      </div>
    </div>
    <div class="exam-card-footer" style="justify-content:space-between">
      <div style="display:flex;gap:6px">
        <?php if ($arc['sujet_url']): ?><span class="badge badge-green"><i data-lucide="file-text" style="width:11px;height:11px;vertical-align:-2px;margin-right:3px"></i> Sujet</span><?php endif; ?>
        <?php if ($arc['corrige_url']): ?><span class="badge badge-gold"><i data-lucide="check-circle" style="width:11px;height:11px;vertical-align:-2px;margin-right:3px"></i> Corrigé</span><?php endif; ?>
      </div>
      <span style="font-size:11px;color:var(--primary);font-weight:600">Voir →</span>
    </div>
  </a>
  <?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if ($pag['pages'] > 1): ?>
<div style="display:flex;justify-content:center;gap:8px;margin-top:24px;flex-wrap:wrap">
  <?php if ($pag['page'] > 1): ?>
  <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pag['page'] - 1])) ?>" class="btn btn-ghost btn-sm">← Précédent</a>
  <?php endif; ?>

  <?php for ($p = max(1, $pag['page'] - 2); $p <= min($pag['pages'], $pag['page'] + 2); $p++): ?>
  <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"
     class="btn <?= $p === $pag['page'] ? 'btn-primary' : 'btn-ghost' ?> btn-sm"><?= $p ?></a>
  <?php endfor; ?>

  <?php if ($pag['page'] < $pag['pages']): ?>
  <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pag['page'] + 1])) ?>" class="btn btn-ghost btn-sm">Suivant →</a>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php else: ?>
<div class="card" style="text-align:center;padding:60px">
  <div style="font-size:48px;margin-bottom:16px">📂</div>
  <div style="font-size:17px;font-weight:700;margin-bottom:8px">Aucune archive trouvée</div>
  <div style="font-size:14px;color:var(--gris-500);margin-bottom:20px">
    <?php if ($search): ?>Aucun résultat pour "<?= e($search) ?>". Essayez d'autres termes.
    <?php else: ?>Aucune archive correspond à ces critères.<?php endif; ?>
  </div>
  <a href="/reussiteplus/archives.php" class="btn btn-primary">Voir toutes les archives</a>
</div>
<?php endif; ?>

<?php endif; ?>

<script>
function incrementDownload(archiveId) {
  fetch('/reussiteplus/api/archives.php?action=download&id=' + archiveId, {method:'POST'});
}
</script>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
