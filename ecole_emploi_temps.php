<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Emploi du temps';
$pageActive = 'ecole';
$user = require_login();
if ($user['plan'] !== 'ECOLE') redirect('/reussiteplus/tarifs.php');

$classes      = dbAll("SELECT id, nom, niveau FROM classes_ecole WHERE admin_id=? AND actif=1 ORDER BY nom", [$user['id']]) ?? [];
$matieres     = dbAll("SELECT id, nom FROM matieres ORDER BY nom") ?? [];
$enseignants  = dbAll("SELECT id, prenom, nom FROM enseignants_ecole WHERE ecole_admin_id=? AND statut='ACTIF' ORDER BY nom", [$user['id']]) ?? [];

$classeId = $_GET['classe'] ?? ($classes[0]['id'] ?? null);
$classeActive = null;
if ($classeId) {
    foreach ($classes as $cl) { if ($cl['id'] === $classeId) { $classeActive = $cl; break; } }
}

// ── Actions POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { http_response_code(403); exit; }
    $action = $_POST['action'] ?? '';

    if ($action === 'ajouter_creneau') {
        $cid       = $_POST['classe_id']      ?? '';
        $matNom    = trim($_POST['matiere_nom'] ?? '');
        $matId     = $_POST['matiere_id']      ?: null;
        $ensId     = $_POST['enseignant_id']   ?: null;
        $jour      = (int)($_POST['jour']      ?? 1);
        $debut     = $_POST['heure_debut']     ?? '08:00';
        $fin       = $_POST['heure_fin']       ?? '09:30';
        $salle     = trim($_POST['salle']      ?? '');
        $couleur   = $_POST['couleur']         ?? '#007A5E';

        if ($cid && $matNom && $jour >= 1 && $jour <= 6) {
            dbRun("INSERT INTO emploi_temps (ecole_admin_id, classe_id, enseignant_id, matiere_id, matiere_nom, jour, heure_debut, heure_fin, salle, couleur)
                   VALUES (?,?,?,?,?,?,?,?,?,?)",
                [$user['id'], $cid, $ensId, $matId, $matNom, $jour, $debut, $fin, $salle ?: null, $couleur]);
        }
        redirect('/reussiteplus/ecole_emploi_temps.php?classe='.$cid, 'success', 'Créneau ajouté.');
    }

    if ($action === 'supprimer_creneau') {
        $id  = $_POST['creneau_id'] ?? '';
        dbRun("DELETE FROM emploi_temps WHERE id=? AND ecole_admin_id=?", [$id, $user['id']]);
        redirect('/reussiteplus/ecole_emploi_temps.php?classe='.$classeId, 'success', 'Créneau supprimé.');
    }

    exit;
}

// ── Créneaux ──────────────────────────────────────────────────
$creneaux = [];
if ($classeId) {
    $rows = dbAll(
        "SELECT et.*, CONCAT(en.prenom,' ',en.nom) as enseignant_nom
         FROM emploi_temps et
         LEFT JOIN enseignants_ecole en ON en.id=et.enseignant_id
         WHERE et.ecole_admin_id=? AND et.classe_id=?
         ORDER BY et.jour, et.heure_debut",
        [$user['id'], $classeId]
    ) ?? [];
    foreach ($rows as $r) {
        $creneaux[$r['jour']][] = $r;
    }
}

$jours = [1=>'Lundi',2=>'Mardi',3=>'Mercredi',4=>'Jeudi',5=>'Vendredi',6=>'Samedi'];
$couleurs = ['#007A5E','#1E5FAD','#7C3AED','#D97706','#DC2626','#059669','#0891B2','#F97316','#9333EA','#0F766E'];

include __DIR__ . '/includes/header_app.php';
?>

<style>
@media print {
  .sidebar, .top-bar, .no-print { display:none!important; }
  .main-content { margin:0!important; padding:0!important; }
}
.et-hero { background:linear-gradient(135deg,#0a1628,#003D2E); border-radius:var(--radius-xl); padding:24px 28px; margin-bottom:24px; display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; }
.et-grid { display:grid; grid-template-columns:80px repeat(6,1fr); border:1px solid var(--gris-200); border-radius:var(--radius-lg); overflow:hidden; background:var(--blanc); }
.et-header { background:var(--gris-50); padding:10px; text-align:center; font-size:12px; font-weight:700; color:var(--gris-600); text-transform:uppercase; letter-spacing:.3px; border-bottom:2px solid var(--gris-200); border-right:1px solid var(--gris-200); }
.et-header:last-child { border-right:none; }
.et-time-col { background:var(--gris-50); border-right:1px solid var(--gris-200); border-bottom:1px solid var(--gris-100); }
.et-cell { border-right:1px solid var(--gris-100); border-bottom:1px solid var(--gris-100); min-height:80px; padding:4px; position:relative; vertical-align:top; }
.et-cell:last-child { border-right:none; }
.et-slot {
  border-radius:6px; padding:6px 8px; font-size:11px; font-weight:600;
  cursor:default; position:relative; transition:.15s;
  border-left:3px solid currentColor;
  margin-bottom:3px;
}
.et-slot:hover { filter:brightness(1.05); }
.et-slot-delete {
  position:absolute; top:3px; right:3px;
  background:none; border:none; cursor:pointer; opacity:0;
  transition:.15s; padding:1px; color:inherit;
}
.et-slot:hover .et-slot-delete { opacity:.7; }
.et-slot-delete:hover { opacity:1!important; }
.modal-bd { position:fixed; inset:0; background:rgba(0,0,0,.5); display:flex; align-items:center; justify-content:center; z-index:1000; padding:20px; backdrop-filter:blur(4px); }
.modal-card { background:var(--blanc); border-radius:20px; width:100%; max-width:480px; max-height:90vh; overflow-y:auto; }
.modal-head { padding:20px 24px 16px; border-bottom:1px solid var(--gris-100); display:flex; align-items:center; justify-content:space-between; }
.modal-title { font-family:var(--font-display); font-size:16px; font-weight:800; display:flex; align-items:center; gap:8px; }
.modal-body { padding:20px 24px; }
.color-picker { display:flex; gap:6px; flex-wrap:wrap; }
.color-opt { width:28px; height:28px; border-radius:50%; cursor:pointer; transition:.15s; border:3px solid transparent; }
.color-opt:hover, .color-opt.active { border-color:#fff; box-shadow:0 0 0 2px currentColor; }
</style>

<!-- Hero -->
<div class="et-hero no-print">
  <div>
    <div style="font-size:11px;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">
      <a href="/reussiteplus/ecole.php" style="color:rgba(255,255,255,.4);text-decoration:none">Mon École</a> / Emploi du temps
    </div>
    <div style="font-family:var(--font-display);font-size:22px;font-weight:900;color:#fff;margin-bottom:4px">Emploi du temps</div>
    <div style="font-size:13px;color:rgba(255,255,255,.5)">Gérez les horaires de vos classes</div>
  </div>
  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <!-- Sélecteur de classe -->
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <?php foreach ($classes as $cl): ?>
      <a href="/reussiteplus/ecole_emploi_temps.php?classe=<?= e($cl['id']) ?>"
         style="padding:8px 14px;border-radius:var(--radius);font-size:13px;font-weight:600;border:1.5px solid <?= $cl['id']===$classeId?'#fff':'rgba(255,255,255,.3)' ?>;color:<?= $cl['id']===$classeId?'#0a1628':'#fff' ?>;background:<?= $cl['id']===$classeId?'#fff':'transparent' ?>;text-decoration:none;transition:.15s">
        <?= e($cl['nom']) ?>
      </a>
      <?php endforeach; ?>
    </div>
    <?php if ($classeId): ?>
    <button onclick="document.getElementById('modal-add').style.display='flex'"
            style="background:#007A5E;border:none;color:#fff;padding:9px 16px;border-radius:var(--radius);font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:7px;transition:opacity .15s"
            onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
      <i data-lucide="plus" style="width:14px;height:14px;stroke:#fff"></i> Ajouter
    </button>
    <button onclick="window.print()"
            style="background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.3);color:#fff;padding:9px 14px;border-radius:var(--radius);font-size:13px;cursor:pointer;display:flex;align-items:center;gap:6px;transition:.15s"
            onmouseover="this.style.background='rgba(255,255,255,.2)'" onmouseout="this.style.background='rgba(255,255,255,.1)'">
      <i data-lucide="printer" style="width:14px;height:14px;stroke:#fff"></i> Imprimer
    </button>
    <?php endif; ?>
  </div>
</div>

<?php if (!$classes): ?>
<div class="card" style="text-align:center;padding:50px">
  <i data-lucide="calendar-x" style="width:48px;height:48px;stroke:var(--gris-300);margin-bottom:12px"></i>
  <div style="font-size:16px;font-weight:700;color:var(--gris-700);margin-bottom:8px">Aucune classe créée</div>
  <a href="/reussiteplus/ecole_classes.php" class="btn btn-primary">Créer une classe d'abord</a>
</div>
<?php elseif ($classeActive): ?>

<!-- Titre de la classe -->
<div style="margin-bottom:16px">
  <div style="font-family:var(--font-display);font-size:18px;font-weight:800;color:var(--gris-900)">
    <?= e($classeActive['nom']) ?>
    <?php if ($classeActive['niveau']): ?><span style="font-size:14px;color:var(--gris-500);font-weight:500"> · <?= e($classeActive['niveau']) ?></span><?php endif; ?>
  </div>
  <div style="font-size:13px;color:var(--gris-500);margin-top:3px"><?= array_sum(array_map('count',$creneaux)) ?> créneau<?= array_sum(array_map('count',$creneaux))!=1?'x':'' ?></div>
</div>

<!-- Grille emploi du temps -->
<div style="overflow-x:auto">
  <div style="min-width:700px">
    <div class="et-grid">
      <!-- En-têtes jours -->
      <div class="et-header" style="border-right:2px solid var(--gris-200)">Heure</div>
      <?php foreach ($jours as $j => $nom): ?>
      <div class="et-header <?= $j===6?'':'border-r' ?>"><?= $nom ?></div>
      <?php endforeach; ?>

      <!-- Ligne par créneau horaire -->
      <?php
      $plages = ['07:30','08:00','09:00','09:15','10:00','10:45','11:00','12:00','12:30','13:00','14:00','15:00','16:00','17:00'];
      // Afficher les créneaux de chaque jour dans une vue condensée
      ?>

      <!-- Vue par heure -->
      <?php
      // Collect all unique start times
      $allTimes = [];
      foreach ($creneaux as $joData) {
          foreach ($joData as $c) { $allTimes[] = $c['heure_debut']; }
      }
      $allTimes = array_unique($allTimes);
      sort($allTimes);
      if (!$allTimes) $allTimes = ['07:30','09:15','11:00'];
      ?>
      <?php foreach ($allTimes as $t): ?>
      <div class="et-time-col" style="padding:10px 6px;font-size:11px;font-weight:700;color:var(--gris-500);text-align:center;display:flex;align-items:center;justify-content:center">
        <?= substr($t,0,5) ?>
      </div>
      <?php foreach ($jours as $j => $jNom): ?>
      <div class="et-cell">
        <?php
        $jourCreneaux = $creneaux[$j] ?? [];
        foreach ($jourCreneaux as $c) {
            if ($c['heure_debut'] === $t): ?>
        <div class="et-slot" style="color:<?= e($c['couleur']) ?>;background:<?= e($c['couleur']) ?>18;border-left-color:<?= e($c['couleur']) ?>">
          <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ce créneau ?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="supprimer_creneau">
            <input type="hidden" name="creneau_id" value="<?= e($c['id']) ?>">
            <input type="hidden" name="classe_id" value="<?= e($classeId) ?>">
            <button type="submit" class="et-slot-delete" title="Supprimer">✕</button>
          </form>
          <div style="font-weight:700"><?= e($c['matiere_nom']) ?></div>
          <?php if ($c['enseignant_nom']): ?>
          <div style="font-weight:500;opacity:.75"><?= e($c['enseignant_nom']) ?></div>
          <?php endif; ?>
          <?php if ($c['salle']): ?>
          <div style="opacity:.6"><?= e($c['salle']) ?></div>
          <?php endif; ?>
          <div style="opacity:.5"><?= substr($c['heure_debut'],0,5) ?>–<?= substr($c['heure_fin'],0,5) ?></div>
        </div>
            <?php endif;
        } ?>
      </div>
      <?php endforeach; ?>
      <?php endforeach; ?>

    </div><!-- /et-grid -->
  </div>
</div>

<!-- Légende -->
<?php if ($creneaux): ?>
<div style="margin-top:16px;display:flex;flex-wrap:wrap;gap:8px;align-items:center">
  <span style="font-size:12px;color:var(--gris-500)">Matières :</span>
  <?php
  $seen = [];
  foreach ($creneaux as $joData) {
      foreach ($joData as $c) {
          if (!in_array($c['matiere_nom'], $seen)) {
              $seen[] = $c['matiere_nom'];
              echo '<span style="display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:600;color:'.e($c['couleur']).'"><span style="width:10px;height:10px;border-radius:50%;background:'.e($c['couleur']).';display:inline-block"></span>'.e($c['matiere_nom']).'</span>';
          }
      }
  }
  ?>
</div>
<?php endif; ?>

<?php endif; ?>

<!-- ══ MODAL Ajouter créneau ══════════════════════════════════ -->
<div id="modal-add" class="modal-bd no-print" style="display:none" onclick="if(event.target===this)this.style.display='none'">
  <div class="modal-card">
    <div class="modal-head">
      <span class="modal-title"><i data-lucide="calendar-plus" style="width:16px;height:16px;stroke:var(--primary)"></i> Nouveau créneau</span>
      <button onclick="document.getElementById('modal-add').style.display='none'" style="background:none;border:none;cursor:pointer;color:var(--gris-500)"><i data-lucide="x" style="width:18px;height:18px"></i></button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="ajouter_creneau">
        <input type="hidden" name="classe_id" value="<?= e($classeId ?? '') ?>">
        <div class="form-group">
          <label class="form-label">Matière *</label>
          <input type="text" name="matiere_nom" class="form-control" required placeholder="Ex : Mathématiques" list="mat-list">
          <datalist id="mat-list">
            <?php foreach ($matieres as $m): ?><option value="<?= e($m['nom']) ?>"><?php endforeach; ?>
          </datalist>
        </div>
        <div class="form-group">
          <label class="form-label">Enseignant</label>
          <select name="enseignant_id" class="form-control">
            <option value="">-- Aucun --</option>
            <?php foreach ($enseignants as $e): ?>
            <option value="<?= e($e['id']) ?>"><?= e($e['prenom']) ?> <?= e($e['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Jour *</label>
          <select name="jour" class="form-control" required>
            <?php foreach ($jours as $j => $jNom): ?>
            <option value="<?= $j ?>"><?= $jNom ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group">
            <label class="form-label">Début *</label>
            <input type="time" name="heure_debut" class="form-control" required value="07:30">
          </div>
          <div class="form-group">
            <label class="form-label">Fin *</label>
            <input type="time" name="heure_fin" class="form-control" required value="09:00">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Salle</label>
          <input type="text" name="salle" class="form-control" placeholder="Ex : Salle A, Terrain">
        </div>
        <div class="form-group">
          <label class="form-label" style="margin-bottom:10px">Couleur</label>
          <div class="color-picker">
            <?php foreach ($couleurs as $col): ?>
            <div class="color-opt" style="background:<?= $col ?>;color:<?= $col ?>" onclick="selectColor('<?= $col ?>',this)" title="<?= $col ?>"></div>
            <?php endforeach; ?>
          </div>
          <input type="hidden" name="couleur" id="sel-couleur" value="<?= $couleurs[0] ?>">
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%">
          <i data-lucide="calendar-plus" style="width:13px;height:13px;vertical-align:-2px"></i> Ajouter le créneau
        </button>
      </form>
    </div>
  </div>
</div>

<script>
function selectColor(col, el) {
  document.getElementById('sel-couleur').value = col;
  document.querySelectorAll('.color-opt').forEach(o => o.classList.remove('active'));
  el.classList.add('active');
}
// Marquer la première couleur
document.addEventListener('DOMContentLoaded', () => {
  const first = document.querySelector('.color-opt');
  if (first) first.classList.add('active');
});
</script>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
