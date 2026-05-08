<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Mes Devoirs';
$pageActive = 'mes_devoirs';
$user = require_login();

if ($user['plan'] === 'GRATUIT') {
    redirect('/reussiteplus/tarifs.php', 'warning', 'Les devoirs sont disponibles à partir du plan Basique.');
}

// ── Soumission d'un devoir ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { http_response_code(403); exit; }
    $devoirId = $_POST['devoir_id'] ?? '';

    // Vérifier que ce devoir appartient à une classe de l'élève
    $devoir = dbRow(
        "SELECT d.* FROM devoirs_ecole d
         JOIN classe_membres cm ON cm.classe_id = d.classe_id
         WHERE d.id=? AND cm.eleve_id=? AND cm.statut='ACTIF'",
        [$devoirId, $user['id']]
    );
    if (!$devoir) { redirect('/reussiteplus/mes_devoirs.php', 'error', 'Devoir introuvable.'); }

    $fichierUrl = null;
    $fichierNom = null;
    if (isset($_FILES['fichier']) && $_FILES['fichier']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['fichier'];
        $allowed_mime = ['application/pdf','application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/jpeg','image/png','application/zip'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        if (!in_array($mime, $allowed_mime)) {
            redirect('/reussiteplus/mes_devoirs.php', 'error', 'Format non autorisé (PDF, Word, JPG, PNG, ZIP).');
        }
        if ($file['size'] > 20 * 1024 * 1024) {
            redirect('/reussiteplus/mes_devoirs.php', 'error', 'Fichier trop volumineux (max 20 Mo).');
        }
        $ext_map = ['application/pdf'=>'pdf','application/msword'=>'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'docx',
            'image/jpeg'=>'jpg','image/png'=>'png','application/zip'=>'zip'];
        $ext     = $ext_map[$mime] ?? 'bin';
        $newName = 'devoir_' . bin2hex(random_bytes(10)) . '.' . $ext;
        $destDir = __DIR__ . '/uploads/devoirs/';
        if (!is_dir($destDir)) mkdir($destDir, 0755, true);
        if (move_uploaded_file($file['tmp_name'], $destDir . $newName)) {
            $fichierUrl = APP_URL . '/uploads/devoirs/' . $newName;
            $fichierNom = basename($file['name']);
        }
    }

    $commentaire  = trim($_POST['commentaire']   ?? '');
    $reponseTxt   = trim($_POST['reponse_texte'] ?? '');
    $statut = $devoir['date_remise'] && strtotime($devoir['date_remise']) < time() ? 'EN_RETARD' : 'SOUMIS';

    // Upsert
    $existing = dbRow("SELECT id FROM soumissions_devoirs WHERE devoir_id=? AND eleve_id=?", [$devoirId, $user['id']]);
    if ($existing) {
        dbQuery("UPDATE soumissions_devoirs SET fichier_url=COALESCE(?,fichier_url), fichier_nom=COALESCE(?,fichier_nom), commentaire=?, reponse_texte=?, statut=?, soumis_le=NOW() WHERE id=?",
            [$fichierUrl, $fichierNom, $commentaire ?: null, $reponseTxt ?: null, $statut, $existing['id']]);
    } else {
        dbRun("INSERT INTO soumissions_devoirs (devoir_id, eleve_id, fichier_url, fichier_nom, commentaire, reponse_texte, statut)
               VALUES (?,?,?,?,?,?,?)",
            [$devoirId, $user['id'], $fichierUrl, $fichierNom, $commentaire ?: null, $reponseTxt ?: null, $statut]);
    }
    redirect('/reussiteplus/mes_devoirs.php', 'success', 'Devoir soumis avec succès !');
}

// ── Données : mes classes + devoirs ──────────────────────
$mesClasses = dbAll(
    "SELECT c.id, c.nom FROM classe_membres cm JOIN classes_ecole c ON c.id=cm.classe_id
     WHERE cm.eleve_id=? AND cm.statut='ACTIF' ORDER BY c.nom",
    [$user['id']]
) ?? [];

$classeIds = array_column($mesClasses, 'id');
$devoirs = [];
if ($classeIds) {
    $in = implode(',', array_fill(0, count($classeIds), '?'));
    $devoirs = dbAll(
        "SELECT d.*, c.nom as classe_nom, s.id as soumission_id, s.statut as soumis_statut,
                s.note, s.feedback, s.soumis_le, s.fichier_nom, s.commentaire as mon_commentaire
         FROM devoirs_ecole d
         JOIN classes_ecole c ON c.id=d.classe_id
         LEFT JOIN soumissions_devoirs s ON s.devoir_id=d.id AND s.eleve_id=?
         WHERE d.classe_id IN ($in) AND d.actif=1
         ORDER BY d.date_remise ASC, d.created_at DESC",
        array_merge([$user['id']], $classeIds)
    ) ?? [];
}

$devoirsStats = [
    'total'    => count($devoirs),
    'soumis'   => count(array_filter($devoirs, fn($d) => $d['soumission_id'])),
    'en_attente' => count(array_filter($devoirs, fn($d) => !$d['soumission_id'])),
    'corriges' => count(array_filter($devoirs, fn($d) => $d['soumis_statut'] === 'CORRIGE')),
];

$typeConfig = [
    'DEVOIR'   => ['color'=>'#1E5FAD','bg'=>'#DBEAFE','icon'=>'📝'],
    'CONTROLE' => ['color'=>'#DC2626','bg'=>'#FEE2E2','icon'=>'⚡'],
    'EXAM'     => ['color'=>'#7C3AED','bg'=>'#EDE9FE','icon'=>'🎯'],
    'PROJET'   => ['color'=>'#059669','bg'=>'#D1FAE5','icon'=>'🔬'],
    'EXPOSE'   => ['color'=>'#B45309','bg'=>'#FEF3C7','icon'=>'🎤'],
];

include __DIR__ . '/includes/header_app.php';
?>

<style>
.devoir-card { background:var(--blanc); border:1.5px solid var(--gris-200); border-radius:var(--radius-lg); overflow:hidden; transition:all .2s; }
.devoir-card:hover { box-shadow:0 6px 24px rgba(0,0,0,.08); transform:translateY(-1px); }
.devoir-bar { height:4px; width:100%; }
.upload-zone { border:2px dashed var(--gris-300); border-radius:12px; padding:20px; text-align:center; cursor:pointer; transition:.2s; background:var(--gris-50); }
.upload-zone:hover, .upload-zone.dragover { border-color:#1E5FAD; background:#EFF6FF; }
.badge-statut { font-size:10px; font-weight:800; padding:3px 10px; border-radius:20px; text-transform:uppercase; letter-spacing:.5px; }
</style>

<!-- Hero -->
<div style="background:linear-gradient(135deg,#0f172a,#1e3a5f 50%,#0f172a);border-radius:var(--radius-xl);padding:26px;margin-bottom:20px">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px">
    <div>
      <div style="font-size:11px;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px">Mes Cours</div>
      <div style="font-family:var(--font-display);font-size:20px;font-weight:900;color:#fff;display:flex;align-items:center;gap:10px">Mes Devoirs</div>
      <div style="font-size:12px;color:rgba(255,255,255,.45);margin-top:3px">Consultez et soumettez vos devoirs en ligne</div>
      <?php if ($devoirs): ?>
      <button onclick="exportDevoirsPDF()" style="margin-top:10px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);color:rgba(255,255,255,.85);border-radius:9px;padding:7px 14px;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:5px;transition:.15s" onmouseover="this.style.background='rgba(255,255,255,.2)'" onmouseout="this.style.background='rgba(255,255,255,.12)'">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="13" x2="12" y2="19"/><polyline points="9 16 12 19 15 16"/></svg>
        Exporter relevé PDF
      </button>
      <?php endif; ?>
    </div>
    <div style="display:flex;gap:10px">
      <?php foreach ([['total','Total','#fff'],['soumis','Soumis','#34d399'],['en_attente','En attente','#f59e0b'],['corriges','Corrigés','#a78bfa']] as [$k,$l,$c]): ?>
      <div style="text-align:center;padding:10px 14px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:12px">
        <div style="font-family:var(--font-display);font-size:20px;font-weight:900;color:<?= $c ?>"><?= $devoirsStats[$k] ?></div>
        <div style="font-size:9px;color:rgba(255,255,255,.4);text-transform:uppercase"><?= $l ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php
// Barre de progression gamification
$tauxCompletion = ($devoirsStats['total'] > 0)
    ? round($devoirsStats['soumis'] / $devoirsStats['total'] * 100) : 0;
$niveauLabel = $tauxCompletion >= 100 ? '🏆 Tout soumis !' : ($tauxCompletion >= 75 ? '⭐ Excellent' : ($tauxCompletion >= 50 ? '📈 Bien' : '🎯 En cours'));
?>
<?php if ($devoirsStats['total'] > 0): ?>
<div style="background:var(--blanc);border:1px solid var(--gris-200);border-radius:12px;padding:14px 18px;margin-bottom:16px;display:flex;align-items:center;gap:16px;flex-wrap:wrap">
  <div style="flex:1;min-width:200px">
    <div style="display:flex;justify-content:space-between;font-size:12px;font-weight:600;color:var(--gris-700);margin-bottom:6px">
      <span>Avancement — <?= $devoirsStats['soumis'] ?>/<?= $devoirsStats['total'] ?> devoirs soumis</span>
      <span style="color:<?= $tauxCompletion>=75?'#007A5E':($tauxCompletion>=50?'#C9972A':'#1E5FAD') ?>"><?= $tauxCompletion ?>%</span>
    </div>
    <div style="height:6px;background:var(--gris-100);border-radius:99px;overflow:hidden">
      <div style="height:100%;width:<?= $tauxCompletion ?>%;background:<?= $tauxCompletion>=75?'#007A5E':($tauxCompletion>=50?'#C9972A':'#1E5FAD') ?>;border-radius:99px;transition:width .6s"></div>
    </div>
  </div>
  <div style="font-size:13px;font-weight:700;color:var(--gris-700);white-space:nowrap"><?= $niveauLabel ?></div>
  <?php if ($devoirsStats['corriges'] > 0): ?>
  <div style="font-size:12px;color:var(--gris-500)"><?= $devoirsStats['corriges'] ?> corrigé<?= $devoirsStats['corriges']>1?'s':'' ?></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if (!$mesClasses): ?>
<div class="card" style="text-align:center;padding:60px 30px">
  <div style="font-size:48px;margin-bottom:16px">🏫</div>
  <div style="font-family:var(--font-display);font-size:20px;font-weight:800;margin-bottom:8px">Vous n'êtes dans aucune classe</div>
  <p style="color:var(--gris-500);font-size:13px;max-width:400px;margin:0 auto 24px;line-height:1.6">
    Rejoignez une classe avec un code d'invitation pour accéder aux devoirs et exercices.
  </p>
  <a href="/reussiteplus/rejoindre.php" class="btn btn-primary" style="background:#1E5FAD;border-color:#1E5FAD">Rejoindre une classe</a>
</div>
<?php elseif (!$devoirs): ?>
<div class="card" style="text-align:center;padding:60px 30px">
  <div style="font-size:56px;margin-bottom:16px">😊</div>
  <div style="font-family:var(--font-display);font-size:20px;font-weight:800;margin-bottom:8px">Aucun devoir pour le moment</div>
  <p style="color:var(--gris-500);font-size:13px">Vos enseignants n'ont pas encore publié de devoirs.</p>
</div>
<?php else: ?>

<div style="display:flex;flex-direction:column;gap:14px">
<?php foreach ($devoirs as $d):
  $tc = $typeConfig[$d['type'] ?? 'DEVOIR'] ?? $typeConfig['DEVOIR'];
  $isLate = $d['date_remise'] && strtotime($d['date_remise']) < time() && !$d['soumission_id'];
  $daysLeft = $d['date_remise'] ? ceil((strtotime($d['date_remise']) - time()) / 86400) : null;
  $submitted = (bool)$d['soumission_id'];
?>
<div class="devoir-card">
  <div class="devoir-bar" style="background:<?= $tc['color'] ?>"></div>
  <div style="padding:18px 20px">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap">
      <div style="flex:1;min-width:200px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;flex-wrap:wrap">
          <span style="background:<?= $tc['bg'] ?>;color:<?= $tc['color'] ?>;font-size:11px;font-weight:800;padding:3px 10px;border-radius:8px"><?= $tc['icon'] ?> <?= e($d['type']??'DEVOIR') ?></span>
          <span style="background:var(--gris-100);color:var(--gris-600);font-size:11px;font-weight:700;padding:3px 10px;border-radius:8px"><?= e($d['classe_nom']??'') ?></span>
          <?php if ($submitted): ?>
            <?php if ($d['soumis_statut']==='CORRIGE'): ?>
            <span class="badge-statut" style="background:#D1FAE5;color:#065F46">✓ Corrigé</span>
            <?php elseif ($d['soumis_statut']==='EN_RETARD'): ?>
            <span class="badge-statut" style="background:#FEE2E2;color:#991B1B">⚠ En retard</span>
            <?php else: ?>
            <span class="badge-statut" style="background:#DBEAFE;color:#1e40af">↑ Soumis</span>
            <?php endif; ?>
          <?php elseif ($isLate): ?>
          <span class="badge-statut" style="background:#FEE2E2;color:#991B1B">⏰ Expiré</span>
          <?php elseif ($daysLeft !== null && $daysLeft <= 3): ?>
          <span class="badge-statut" style="background:#FEF3C7;color:#92400E">⚡ Urgent J–<?= max(0,$daysLeft) ?></span>
          <?php endif; ?>
        </div>
        <div style="font-family:var(--font-display);font-size:17px;font-weight:900;color:var(--gris-900);margin-bottom:6px"><?= e($d['titre']??'') ?></div>
        <?php if ($d['description']): ?>
        <div style="font-size:13px;color:var(--gris-600);line-height:1.5;margin-bottom:8px"><?= e($d['description']) ?></div>
        <?php endif; ?>
        <div style="font-size:11px;color:var(--gris-400);display:flex;align-items:center;gap:12px;flex-wrap:wrap">
          <?php if ($d['date_remise']): ?>
          <span style="display:flex;align-items:center;gap:4px"><i data-lucide="calendar" style="width:11px;height:11px"></i>
            Date limite : <strong><?= date('d/m/Y', strtotime($d['date_remise'])) ?></strong></span>
          <?php endif; ?>
          <?php if ($d['matiere']??null): ?>
          <span>📚 <?= e($d['matiere']) ?></span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Note si corrigé -->
      <?php if ($d['soumis_statut']==='CORRIGE' && $d['note']!==null): ?>
      <div style="text-align:center;background:linear-gradient(135deg,#D1FAE5,#A7F3D0);border-radius:14px;padding:14px 20px;border:1.5px solid #34d399">
        <div style="font-family:var(--font-display);font-size:28px;font-weight:900;color:#065F46"><?= number_format($d['note'],1) ?></div>
        <div style="font-size:10px;color:#059669;font-weight:700;text-transform:uppercase">/ 20 pts</div>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($d['soumis_statut']==='CORRIGE' && $d['feedback']): ?>
    <div style="margin-top:12px;background:#F0FDF4;border:1px solid #86EFAC;border-radius:10px;padding:12px;font-size:12px;color:#065F46">
      <strong>Feedback :</strong> <?= e($d['feedback']) ?>
    </div>
    <?php endif; ?>

    <?php if ($submitted && !($d['soumis_statut']==='CORRIGE')): ?>
    <!-- Soumission existante non corrigée -->
    <div style="margin-top:12px;background:var(--gris-50);border:1px solid var(--gris-200);border-radius:10px;padding:12px;font-size:12px;color:var(--gris-600)">
      <div style="display:flex;align-items:center;gap:7px">
        <i data-lucide="check-circle" style="width:13px;height:13px;stroke:#059669"></i>
        <strong>Soumis</strong> le <?= date('d/m/Y à H:i', strtotime($d['soumis_le'])) ?>
        <?php if ($d['fichier_nom']): ?> · <?= e($d['fichier_nom']) ?><?php endif; ?>
      </div>
      <?php if ($d['mon_commentaire']): ?>
      <div style="margin-top:5px;color:var(--gris-500)"><em>"<?= e($d['mon_commentaire']) ?>"</em></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!$submitted || $d['soumis_statut']==='CORRIGE'): // Permettre resoumission si corrigé — non, bloquer ?>
    <?php if (!$submitted): ?>
    <!-- Formulaire de soumission -->
    <details style="margin-top:14px">
      <summary style="cursor:pointer;font-size:13px;font-weight:700;color:#1E5FAD;display:flex;align-items:center;gap:6px;list-style:none;padding:10px 14px;background:#EFF6FF;border-radius:10px;border:1.5px solid #BFDBFE">
        <i data-lucide="upload" style="width:14px;height:14px"></i> Soumettre mon travail
      </summary>
      <div style="margin-top:12px;background:var(--gris-50);border-radius:12px;padding:16px">
        <form method="POST" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <input type="hidden" name="devoir_id" value="<?= e($d['id']) ?>">
          <div class="form-group">
            <label class="form-label">Fichier à soumettre (PDF, Word, Image, ZIP – max 20 Mo)</label>
            <label class="upload-zone" id="zone-<?= e($d['id']) ?>">
              <div style="font-size:28px;margin-bottom:8px">📎</div>
              <div style="font-size:13px;font-weight:700;color:var(--gris-700)" id="zone-txt-<?= e($d['id']) ?>">Cliquez ou déposez votre fichier ici</div>
              <div style="font-size:11px;color:var(--gris-400);margin-top:4px">PDF, DOC, DOCX, JPG, PNG, ZIP</div>
              <input type="file" name="fichier" style="display:none" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.zip"
                     onchange="document.getElementById('zone-txt-<?= e($d['id']) ?>').textContent=this.files[0]?.name||'Fichier sélectionné'">
            </label>
          </div>
          <!-- Réponse texte (optionnel mais analysable par IA) -->
          <div class="form-group" style="margin-bottom:14px">
            <label class="form-label" style="display:flex;align-items:center;gap:6px">
              Réponse texte
              <span style="background:linear-gradient(135deg,#7C3AED,#4F46E5);color:#fff;font-size:9px;font-weight:800;padding:1px 7px;border-radius:10px;letter-spacing:.3px">IA</span>
              <span style="font-size:11px;color:var(--gris-400);font-weight:400">(optionnel — permet la correction IA automatique)</span>
            </label>
            <textarea name="reponse_texte" class="form-control" rows="4" placeholder="Rédigez votre réponse ici pour une correction IA automatique par votre enseignant…" style="font-size:13px;line-height:1.6"></textarea>
          </div>
          <div class="form-group" style="margin-bottom:14px">
            <label class="form-label">Commentaire (optionnel)</label>
            <textarea name="commentaire" class="form-control" rows="2" placeholder="Remarques, difficultés rencontrées…"></textarea>
          </div>
          <button type="submit" class="btn btn-primary" style="background:<?= $tc['color'] ?>;border-color:<?= $tc['color'] ?>;width:100%">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" style="vertical-align:-2px;margin-right:4px"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            Envoyer le devoir
          </button>
        </form>
      </div>
    </details>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
</div>

<?php endif; ?>

<script>
fetch('/reussiteplus/api/devoirs_rappels.php').catch(()=>{});

function exportDevoirsPDF() {
  if (typeof DevoirsPdf === 'undefined') { alert('Générateur PDF non chargé.'); return; }
  DevoirsPdf.open({
    prenom:  <?= json_encode($user['prenom']) ?>,
    periode: <?= json_encode(date('F Y')) ?>,
    stats:   <?= json_encode($devoirsStats) ?>,
    devoirs: <?= json_encode(array_map(fn($d) => [
      'titre'         => $d['titre'],
      'type'          => $d['type_devoir'] ?? $d['type'] ?? 'DEVOIR',
      'matiere'       => $d['matiere'] ?? null,
      'classe_nom'    => $d['classe_nom'] ?? null,
      'date_remise'   => $d['date_remise'] ?? null,
      'soumis_le'     => $d['soumis_le'] ?? null,
      'soumission_id' => $d['soumission_id'] ?? null,
      'soumis_statut' => $d['soumis_statut'] ?? null,
      'note'          => $d['note'] ?? null,
      'points_max'    => $d['points_max'] ?? 20,
      'feedback'      => $d['feedback'] ?? null,
    ], $devoirs), JSON_UNESCAPED_UNICODE) ?>,
  });
}
</script>
<?php include __DIR__ . '/includes/footer_app.php'; ?>
