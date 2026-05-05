<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Messages & Annonces';
$pageActive = 'ecole_messages';
$user = require_login();
if ($user['plan'] !== 'ECOLE') redirect('/reussiteplus/tarifs.php');

$filtreCible = $_GET['cible'] ?? '';
$filtreType  = $_GET['type']  ?? '';

// ── Actions ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { http_response_code(403); exit; }
    $action = $_POST['action'] ?? '';

    if ($action === 'envoyer_annonce') {
        $sujet   = trim($_POST['sujet']   ?? '');
        $message = trim($_POST['message'] ?? '');
        $cible   = $_POST['cible']        ?? 'TOUS';
        $cibleId = $_POST['cible_id']     ?: null;
        $type    = $_POST['type']         ?? 'INFO';

        $validCibles = ['TOUS','CLASSE','ENSEIGNANTS','ELEVES'];
        $validTypes  = ['INFO','URGENT','DEVOIR','EVENEMENT'];
        $cible = in_array($cible, $validCibles) ? $cible : 'TOUS';
        $type  = in_array($type, $validTypes)   ? $type  : 'INFO';

        if ($sujet && $message) {
            dbRun(
                "INSERT INTO annonces_ecole (ecole_admin_id, expediteur_id, cible, cible_id, sujet, message, type)
                 VALUES (?,?,?,?,?,?,?)",
                [$user['id'], $user['id'], $cible, $cibleId, $sujet, $message, $type]
            );
            redirect('/reussiteplus/ecole_messages.php', 'success', 'Annonce envoy&eacute;e.');
        }
    }

    if ($action === 'supprimer_annonce') {
        $id = $_POST['annonce_id'] ?? '';
        dbRun("DELETE FROM annonces_ecole WHERE id=? AND ecole_admin_id=?", [$id, $user['id']]);
        redirect('/reussiteplus/ecole_messages.php', 'success', 'Annonce supprim&eacute;e.');
    }
    exit;
}

// ── Donn&eacute;es ───────────────────────────────────────────────
$classes = dbAll("SELECT id, nom FROM classes_ecole WHERE admin_id=? AND actif=1 ORDER BY nom", [$user['id']]) ?? [];

$whereExtra = ''; $params = [$user['id']];
if ($filtreCible) { $whereExtra .= ' AND a.cible=?'; $params[] = $filtreCible; }
if ($filtreType)  { $whereExtra .= ' AND a.type=?';  $params[] = $filtreType; }

$annonces = dbAll(
    "SELECT a.*, c.nom as classe_nom
     FROM annonces_ecole a
     LEFT JOIN classes_ecole c ON c.id=a.cible_id
     WHERE a.ecole_admin_id=? $whereExtra
     ORDER BY a.created_at DESC",
    $params
) ?? [];

// Stats
$statsType = [];
foreach ($annonces as $a) $statsType[$a['type']] = ($statsType[$a['type']] ?? 0) + 1;
$totalAnnonces = count(dbAll("SELECT id FROM annonces_ecole WHERE ecole_admin_id=?", [$user['id']]) ?? []);
$urgentes = count(dbAll("SELECT id FROM annonces_ecole WHERE ecole_admin_id=? AND type='URGENT'", [$user['id']]) ?? []);

include __DIR__ . '/includes/header_app.php';
?>

<style>
.annonce-card { background:var(--blanc); border:1.5px solid var(--gris-200); border-radius:var(--radius-lg); padding:16px 20px; transition:all .2s; }
.annonce-card:hover { box-shadow:0 4px 16px rgba(0,0,0,.07); }
</style>

<!-- Hero -->
<div style="background:linear-gradient(135deg,#0f172a,#064e3b 55%,#0f172a);border-radius:var(--radius-xl);padding:26px;margin-bottom:20px">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px">
    <div>
      <div style="font-size:11px;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Mon &Eacute;cole</div>
      <div style="font-family:var(--font-display);font-size:20px;font-weight:900;color:#fff;display:flex;align-items:center;gap:10px">
        <i data-lucide="message-square" style="width:22px;height:22px;stroke:#34d399"></i>
        Messages &amp; Annonces
      </div>
      <div style="font-size:12px;color:rgba(255,255,255,.45);margin-top:3px">Communiquez avec vos &eacute;l&egrave;ves et enseignants</div>
    </div>
    <div style="display:flex;gap:10px">
      <div style="text-align:center;padding:8px 14px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:12px">
        <div style="font-family:var(--font-display);font-size:20px;font-weight:900;color:#fff"><?= $totalAnnonces ?></div>
        <div style="font-size:9px;color:rgba(255,255,255,.4);text-transform:uppercase">Total</div>
      </div>
      <?php if ($urgentes > 0): ?>
      <div style="text-align:center;padding:8px 14px;background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);border-radius:12px">
        <div style="font-family:var(--font-display);font-size:20px;font-weight:900;color:#f87171"><?= $urgentes ?></div>
        <div style="font-size:9px;color:rgba(255,255,255,.4);text-transform:uppercase">Urgentes</div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 360px;gap:20px;align-items:start">

  <!-- Liste annonces -->
  <div>
    <!-- Filtres -->
    <div style="background:var(--blanc);border:1.5px solid var(--gris-200);border-radius:var(--radius-lg);padding:12px 16px;margin-bottom:16px">
      <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <select name="cible" class="form-control" style="width:150px;margin-bottom:0">
          <option value="">Toutes cibles</option>
          <?php foreach (['TOUS'=>'Tous','CLASSE'=>'Une classe','ENSEIGNANTS'=>'Enseignants','ELEVES'=>'&Eacute;l&egrave;ves'] as $k=>$l): ?>
          <option value="<?= $k ?>" <?= $filtreCible===$k?'selected':'' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
        <select name="type" class="form-control" style="width:150px;margin-bottom:0">
          <option value="">Tous types</option>
          <?php foreach (['INFO'=>'Information','URGENT'=>'Urgent','DEVOIR'=>'Devoir','EVENEMENT'=>'&Eacute;v&eacute;nement'] as $k=>$l): ?>
          <option value="<?= $k ?>" <?= $filtreType===$k?'selected':'' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-ghost btn-sm">Filtrer</button>
        <?php if ($filtreCible || $filtreType): ?>
        <a href="/reussiteplus/ecole_messages.php" class="btn btn-ghost btn-sm">Effacer</a>
        <?php endif; ?>
      </form>
    </div>

    <?php
    $typeConfig = [
      'INFO'      => ['#1E5FAD','#DBEAFE','info'],
      'URGENT'    => ['#DC2626','#FEE2E2','alert-triangle'],
      'DEVOIR'    => ['#7C3AED','#EDE9FE','file-text'],
      'EVENEMENT' => ['#059669','#D1FAE5','calendar'],
    ];
    $cibleLabels = ['TOUS'=>'Tous','CLASSE'=>'Classe','ENSEIGNANTS'=>'Enseignants','ELEVES'=>'&Eacute;l&egrave;ves'];
    ?>

    <?php if ($annonces): ?>
    <div style="display:flex;flex-direction:column;gap:10px">
    <?php foreach ($annonces as $a):
      [$tColor,$tBg,$tIcon] = $typeConfig[$a['type']] ?? $typeConfig['INFO'];
    ?>
    <div class="annonce-card" style="border-left:4px solid <?= $tColor ?>">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px">
        <div style="flex:1">
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px">
            <span style="background:<?= $tBg ?>;color:<?= $tColor ?>;font-size:10px;font-weight:800;padding:2px 10px;border-radius:6px;display:inline-flex;align-items:center;gap:4px">
              <i data-lucide="<?= $tIcon ?>" style="width:10px;height:10px"></i> <?= $a['type'] ?>
            </span>
            <span style="background:var(--gris-100);color:var(--gris-600);font-size:10px;font-weight:700;padding:2px 10px;border-radius:6px;display:inline-flex;align-items:center;gap:4px">
              <i data-lucide="users" style="width:10px;height:10px"></i>
              <?= $a['cible']==='CLASSE' && $a['classe_nom'] ? e($a['classe_nom']) : ($cibleLabels[$a['cible']] ?? $a['cible']) ?>
            </span>
            <span style="font-size:11px;color:var(--gris-400)"><?= time_ago($a['created_at']) ?></span>
          </div>
          <div style="font-family:var(--font-display);font-size:15px;font-weight:800;color:var(--gris-900);margin-bottom:5px"><?= e($a['sujet']) ?></div>
          <div style="font-size:13px;color:var(--gris-600);line-height:1.6"><?= nl2br(e(mb_strimwidth($a['message'],0,200,'&hellip;'))) ?></div>
        </div>
        <form method="POST" onsubmit="return confirm('Supprimer cette annonce ?')" style="flex-shrink:0">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="supprimer_annonce">
          <input type="hidden" name="annonce_id" value="<?= e($a['id']) ?>">
          <button type="submit" class="btn btn-ghost btn-sm" style="color:#DC2626;border-color:#FECACA" title="Supprimer">
            <i data-lucide="trash-2" style="width:13px;height:13px"></i>
          </button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:50px 20px;background:var(--gris-50);border:2px dashed var(--gris-200);border-radius:14px">
      <div style="display:flex;justify-content:center;margin-bottom:14px">
        <i data-lucide="message-circle" style="width:48px;height:48px;stroke:var(--gris-300);stroke-width:1.5"></i>
      </div>
      <div style="font-size:15px;font-weight:700;color:var(--gris-600)">Aucune annonce</div>
      <div style="font-size:12px;color:var(--gris-400);margin-top:4px">Envoyez votre premi&egrave;re annonce via le formulaire.</div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Formulaire cr&eacute;ation -->
  <div style="position:sticky;top:80px">
    <div class="card">
      <div style="padding:16px 20px 14px;border-bottom:1px solid var(--gris-100);display:flex;align-items:center;gap:8px">
        <i data-lucide="send" style="width:16px;height:16px;stroke:#059669"></i>
        <span style="font-family:var(--font-display);font-size:14px;font-weight:800">Nouvelle annonce</span>
      </div>
      <div style="padding:18px">
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="envoyer_annonce">

          <div class="form-group">
            <label class="form-label">Type *</label>
            <select name="type" class="form-control" id="sel-type" onchange="updateTypeStyle(this.value)">
              <option value="INFO">Information</option>
              <option value="URGENT">Urgent</option>
              <option value="DEVOIR">Devoir</option>
              <option value="EVENEMENT">&Eacute;v&eacute;nement</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Destinataires *</label>
            <select name="cible" class="form-control" id="sel-cible" onchange="toggleClasse(this.value)">
              <option value="TOUS">Tout le monde</option>
              <option value="ELEVES">&Eacute;l&egrave;ves uniquement</option>
              <option value="ENSEIGNANTS">Enseignants uniquement</option>
              <option value="CLASSE">Une classe sp&eacute;cifique</option>
            </select>
          </div>

          <div class="form-group" id="div-classe" style="display:none">
            <label class="form-label">Choisir la classe</label>
            <select name="cible_id" class="form-control">
              <option value="">S&eacute;lectionner&hellip;</option>
              <?php foreach ($classes as $cl): ?>
              <option value="<?= e($cl['id']) ?>"><?= e($cl['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Sujet *</label>
            <input type="text" name="sujet" class="form-control" required placeholder="Ex&nbsp;: Contr&ocirc;le de math vendredi">
          </div>

          <div class="form-group">
            <label class="form-label">Message *</label>
            <textarea name="message" class="form-control" rows="5" required placeholder="R&eacute;digez votre annonce ici&hellip;"></textarea>
          </div>

          <button type="submit" class="btn btn-primary btn-full" style="background:#059669;border-color:#059669">
            <i data-lucide="send" style="width:13px;height:13px;stroke:#fff;vertical-align:-2px"></i> Envoyer l&apos;annonce
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
function toggleClasse(v) {
  document.getElementById('div-classe').style.display = v === 'CLASSE' ? 'block' : 'none';
}
</script>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
