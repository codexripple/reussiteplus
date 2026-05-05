<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Enseignants';
$pageActive = 'ecole';
$user = require_login();
if ($user['plan'] !== 'ECOLE') redirect('/reussiteplus/tarifs.php');

$planE    = PLANS['ECOLE'];
$matieres = dbAll("SELECT id, nom FROM matieres ORDER BY nom") ?? [];
$classes  = dbAll("SELECT id, nom FROM classes_ecole WHERE admin_id=? AND actif=1 ORDER BY nom", [$user['id']]) ?? [];

// ── Actions POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { http_response_code(403); exit('CSRF'); }
    $action = $_POST['action'] ?? '';

    if ($action === 'ajouter_enseignant') {
        $nom       = trim($_POST['nom']       ?? '');
        $prenom    = trim($_POST['prenom']    ?? '');
        $email     = trim($_POST['email']     ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $matIds    = $_POST['matieres']       ?? [];

        if ($nom && $prenom) {
            $count = (int)(dbScalar("SELECT COUNT(*) FROM enseignants_ecole WHERE ecole_admin_id=? AND statut='ACTIF'", [$user['id']]) ?? 0);
            if ($count >= $planE['enseignants_max']) {
                redirect('/reussiteplus/ecole_enseignants.php', 'error', 'Limite de '.$planE['enseignants_max'].' enseignants atteinte.');
            }
            $code = strtoupper(substr(md5(uniqid()), 0, 8));
            dbRun("INSERT INTO enseignants_ecole (ecole_admin_id, nom, prenom, email, telephone, matieres_json, statut, code_invitation) VALUES (?,?,?,?,?,?,?,?)",
                [$user['id'], $nom, $prenom, $email ?: null, $telephone ?: null, json_encode($matIds), 'ACTIF', $code]);
            redirect('/reussiteplus/ecole_enseignants.php', 'success', 'Enseignant ajouté avec succès.');
        }
    }

    if ($action === 'supprimer_enseignant') {
        $id = $_POST['enseignant_id'] ?? '';
        dbRun("UPDATE enseignants_ecole SET statut='INACTIF' WHERE id=? AND ecole_admin_id=?", [$id, $user['id']]);
        redirect('/reussiteplus/ecole_enseignants.php', 'success', 'Enseignant désactivé.');
    }

    if ($action === 'assigner_classe') {
        $ensId    = $_POST['enseignant_id'] ?? '';
        $classeId = $_POST['classe_id']     ?? '';
        $matId    = $_POST['matiere_id']    ?: null;
        if ($ensId && $classeId) {
            dbRun("INSERT IGNORE INTO enseignant_classes (enseignant_id, classe_id, matiere_id) VALUES (?,?,?)",
                  [$ensId, $classeId, $matId]);
            redirect('/reussiteplus/ecole_enseignants.php', 'success', 'Assignation enregistrée.');
        }
    }

    exit;
}

// ── Données ───────────────────────────────────────────────────
$enseignants = dbAll(
    "SELECT e.*,
            GROUP_CONCAT(DISTINCT c.nom ORDER BY c.nom SEPARATOR ', ') as classes_nom,
            GROUP_CONCAT(DISTINCT m.nom ORDER BY m.nom SEPARATOR ', ') as matieres_nom
     FROM enseignants_ecole e
     LEFT JOIN enseignant_classes ec ON ec.enseignant_id=e.id
     LEFT JOIN classes_ecole c ON c.id=ec.classe_id AND c.actif=1
     LEFT JOIN matieres m ON m.id=ec.matiere_id
     WHERE e.ecole_admin_id=? AND e.statut='ACTIF'
     GROUP BY e.id
     ORDER BY e.created_at DESC",
    [$user['id']]
) ?? [];

include __DIR__ . '/includes/header_app.php';
?>

<style>
.ens-hero { background:linear-gradient(135deg,#0a1628,#1a0a3d); border-radius:var(--radius-xl); padding:28px; margin-bottom:24px; display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; }
.ens-table { width:100%; border-collapse:collapse; }
.ens-table th { background:var(--gris-50); font-size:11px; text-transform:uppercase; letter-spacing:.4px; color:var(--gris-500); padding:10px 16px; text-align:left; font-weight:700; border-bottom:2px solid var(--gris-200); }
.ens-table td { padding:14px 16px; border-bottom:1px solid var(--gris-100); vertical-align:middle; }
.ens-table tr:last-child td { border-bottom:none; }
.ens-table tr:hover td { background:var(--gris-50); }
.ens-av { width:38px; height:38px; border-radius:50%; background:linear-gradient(135deg,#7C3AED,var(--primary)); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:13px; flex-shrink:0; }
.mat-tag { display:inline-block; background:var(--primary-subtle); color:var(--primary); font-size:11px; font-weight:600; padding:2px 8px; border-radius:8px; margin:2px 2px 0 0; }
.cls-tag { display:inline-block; background:#EDE9FE; color:#7C3AED; font-size:11px; font-weight:600; padding:2px 8px; border-radius:8px; margin:2px 2px 0 0; }
.modal-bd { position:fixed; inset:0; background:rgba(0,0,0,.5); display:flex; align-items:center; justify-content:center; z-index:1000; padding:20px; backdrop-filter:blur(4px); }
.modal-card { background:var(--blanc); border-radius:20px; width:100%; max-width:520px; max-height:90vh; overflow-y:auto; }
.modal-head { padding:20px 24px 16px; border-bottom:1px solid var(--gris-100); display:flex; align-items:center; justify-content:space-between; }
.modal-title { font-family:var(--font-display); font-size:16px; font-weight:800; display:flex; align-items:center; gap:8px; }
.modal-body { padding:20px 24px; }
.mat-check-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:8px; max-height:200px; overflow-y:auto; }
.mat-check-item { display:flex; align-items:center; gap:8px; padding:7px 10px; border:1.5px solid var(--gris-200); border-radius:var(--radius); cursor:pointer; transition:.15s; }
.mat-check-item:has(input:checked) { border-color:var(--primary); background:var(--primary-subtle); }
.mat-check-item label { font-size:13px; font-weight:500; cursor:pointer; }
</style>

<!-- Hero -->
<div class="ens-hero">
  <div>
    <div style="font-size:11px;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">
      <a href="/reussiteplus/ecole.php" style="color:rgba(255,255,255,.4);text-decoration:none">Mon École</a> / Enseignants
    </div>
    <div style="font-family:var(--font-display);font-size:22px;font-weight:900;color:#fff;margin-bottom:4px">Corps enseignant</div>
    <div style="font-size:13px;color:rgba(255,255,255,.5)">
      <?= count($enseignants) ?> / <?= $planE['enseignants_max'] ?> enseignants actifs
    </div>
  </div>
  <?php if (count($enseignants) < $planE['enseignants_max']): ?>
  <button onclick="document.getElementById('modal-add').style.display='flex'"
          style="background:#7C3AED;border:none;color:#fff;padding:11px 20px;border-radius:var(--radius);font-size:14px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:8px;transition:opacity .15s;flex-shrink:0"
          onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
    <i data-lucide="user-plus" style="width:16px;height:16px;stroke:#fff"></i>
    Ajouter un enseignant
  </button>
  <?php else: ?>
  <div style="background:rgba(239,68,68,.2);border:1px solid rgba(239,68,68,.4);border-radius:var(--radius);padding:10px 16px;color:#FCA5A5;font-size:13px">
    Limite de <?= $planE['enseignants_max'] ?> enseignants atteinte
  </div>
  <?php endif; ?>
</div>

<!-- Barre de progression -->
<?php $pctEns = $planE['enseignants_max'] > 0 ? min(100, round(count($enseignants)/$planE['enseignants_max']*100)) : 0; ?>
<div class="card" style="margin-bottom:20px;padding:16px 20px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
    <span style="font-size:13px;font-weight:600;color:var(--gris-700)">Capacité enseignants</span>
    <span style="font-size:13px;font-weight:700;color:<?= $pctEns>80?'#EF4444':'var(--primary)' ?>"><?= count($enseignants) ?>/<?= $planE['enseignants_max'] ?></span>
  </div>
  <div style="height:8px;background:var(--gris-100);border-radius:4px;overflow:hidden">
    <div style="height:100%;width:<?= $pctEns ?>%;background:<?= $pctEns>80?'#EF4444':'var(--primary)' ?>;border-radius:4px;transition:width .5s"></div>
  </div>
</div>

<!-- Tableau des enseignants -->
<div class="card" style="overflow:hidden;padding:0">
  <?php if ($enseignants): ?>
  <table class="ens-table">
    <thead>
      <tr>
        <th>Enseignant</th>
        <th>Matières</th>
        <th>Classes assignées</th>
        <th>Contact</th>
        <th>Code</th>
        <th style="text-align:right">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($enseignants as $ens): ?>
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:10px">
            <div class="ens-av"><?= strtoupper(mb_substr($ens['prenom'],0,1).mb_substr($ens['nom'],0,1)) ?></div>
            <div>
              <div style="font-weight:700;font-size:14px"><?= e($ens['prenom']) ?> <?= e($ens['nom']) ?></div>
              <?php if ($ens['email']): ?>
              <div style="font-size:11px;color:var(--gris-500)"><?= e($ens['email']) ?></div>
              <?php endif; ?>
            </div>
          </div>
        </td>
        <td>
          <?php
          $matiereIds = json_decode($ens['matieres_json'] ?? '[]', true) ?: [];
          foreach ($matiereIds as $mid) {
              $mat = array_filter($matieres, fn($m) => $m['id'] == $mid);
              if ($mat) echo '<span class="mat-tag">'.e(reset($mat)['nom']).'</span>';
          }
          if (!$matiereIds) echo '<span style="color:var(--gris-400);font-size:12px">—</span>';
          ?>
        </td>
        <td>
          <?php if ($ens['classes_nom']): ?>
            <?php foreach (explode(', ', $ens['classes_nom']) as $cn): ?>
            <span class="cls-tag"><?= e($cn) ?></span>
            <?php endforeach; ?>
          <?php else: ?>
            <button onclick="openAssign('<?= e($ens['id']) ?>','<?= e($ens['prenom'].' '.$ens['nom']) ?>')"
                    style="font-size:12px;color:var(--primary);background:none;border:1px dashed var(--primary);border-radius:7px;padding:3px 10px;cursor:pointer;transition:.15s"
                    onmouseover="this.style.background='var(--primary-subtle)'" onmouseout="this.style.background='none'">
              + Assigner
            </button>
          <?php endif; ?>
        </td>
        <td style="font-size:12px;color:var(--gris-600)">
          <?php if ($ens['telephone']): ?>
          <a href="https://wa.me/<?= preg_replace('/\D/','',$ens['telephone']) ?>" target="_blank" rel="noopener"
             style="color:#059669;text-decoration:none;display:flex;align-items:center;gap:4px">
            <i data-lucide="message-circle" style="width:12px;height:12px;stroke:#059669"></i>
            <?= e($ens['telephone']) ?>
          </a>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td>
          <span onclick="navigator.clipboard.writeText('<?= e($ens['code_invitation']) ?>').then(()=>{this.style.background='#D1FAE5';setTimeout(()=>this.style.background='',1200)})"
                style="font-family:monospace;font-size:12px;font-weight:700;background:var(--gris-100);border:1px solid var(--gris-200);padding:3px 8px;border-radius:6px;cursor:pointer;transition:.2s"
                title="Cliquer pour copier">
            <?= e($ens['code_invitation']) ?>
          </span>
        </td>
        <td style="text-align:right">
          <button onclick="openAssign('<?= e($ens['id']) ?>','<?= e($ens['prenom'].' '.$ens['nom']) ?>')"
                  style="background:none;border:1px solid var(--gris-200);border-radius:var(--radius);padding:5px 10px;font-size:12px;color:var(--gris-600);cursor:pointer;margin-right:4px;transition:.15s"
                  onmouseover="this.style.borderColor='#7C3AED';this.style.color='#7C3AED'" onmouseout="this.style.borderColor='var(--gris-200)';this.style.color='var(--gris-600)'">
            <i data-lucide="link" style="width:12px;height:12px;vertical-align:-1px"></i>
          </button>
          <form method="POST" style="display:inline" onsubmit="return confirm('Désactiver cet enseignant ?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="supprimer_enseignant">
            <input type="hidden" name="enseignant_id" value="<?= e($ens['id']) ?>">
            <button type="submit" style="background:none;border:1px solid #FECACA;border-radius:var(--radius);padding:5px 10px;font-size:12px;color:#DC2626;cursor:pointer;transition:.15s"
                    onmouseover="this.style.background='#FEE2E2'" onmouseout="this.style.background='none'">
              <i data-lucide="user-minus" style="width:12px;height:12px;vertical-align:-1px"></i>
            </button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php else: ?>
  <div style="padding:60px 30px;text-align:center">
    <div style="width:72px;height:72px;background:#EDE9FE;border-radius:20px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
      <i data-lucide="user-check" style="width:32px;height:32px;stroke:#7C3AED"></i>
    </div>
    <div style="font-family:var(--font-display);font-size:20px;font-weight:800;color:var(--gris-800);margin-bottom:8px">Aucun enseignant pour l'instant</div>
    <p style="color:var(--gris-500);max-width:380px;margin:0 auto 20px;font-size:14px">
      Ajoutez vos enseignants, assignez-leur des matières et des classes pour une gestion complète.
    </p>
    <button onclick="document.getElementById('modal-add').style.display='flex'" class="btn btn-primary">
      <i data-lucide="user-plus" style="width:14px;height:14px;vertical-align:-2px"></i> Ajouter le premier enseignant
    </button>
  </div>
  <?php endif; ?>
</div>

<!-- ══ MODAL Ajouter enseignant ═══════════════════════════════ -->
<div id="modal-add" class="modal-bd" style="display:none" onclick="if(event.target===this)this.style.display='none'">
  <div class="modal-card">
    <div class="modal-head">
      <span class="modal-title"><i data-lucide="user-plus" style="width:16px;height:16px;stroke:#7C3AED"></i> Ajouter un enseignant</span>
      <button onclick="document.getElementById('modal-add').style.display='none'" style="background:none;border:none;cursor:pointer;color:var(--gris-500)"><i data-lucide="x" style="width:18px;height:18px"></i></button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="ajouter_enseignant">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group">
            <label class="form-label">Prénom *</label>
            <input type="text" name="prenom" class="form-control" required placeholder="Jean-Pierre">
          </div>
          <div class="form-group">
            <label class="form-label">Nom *</label>
            <input type="text" name="nom" class="form-control" required placeholder="Kabongo">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" placeholder="enseignant@ecole.cd">
        </div>
        <div class="form-group">
          <label class="form-label">Téléphone (WhatsApp)</label>
          <input type="text" name="telephone" class="form-control" placeholder="+243 8X XXX XXXX">
        </div>
        <div class="form-group">
          <label class="form-label" style="margin-bottom:10px">Matières enseignées</label>
          <div class="mat-check-grid">
            <?php foreach ($matieres as $mat): ?>
            <label class="mat-check-item">
              <input type="checkbox" name="matieres[]" value="<?= $mat['id'] ?>" style="width:15px;height:15px;accent-color:var(--primary)">
              <span style="font-size:13px;font-weight:500"><?= e($mat['nom']) ?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;background:#7C3AED;border-color:#7C3AED">
          <i data-lucide="check" style="width:14px;height:14px;vertical-align:-2px"></i> Enregistrer
        </button>
      </form>
    </div>
  </div>
</div>

<!-- ══ MODAL Assigner classe ══════════════════════════════════ -->
<div id="modal-assign" class="modal-bd" style="display:none" onclick="if(event.target===this)this.style.display='none'">
  <div class="modal-card">
    <div class="modal-head">
      <span class="modal-title"><i data-lucide="link" style="width:16px;height:16px;stroke:#7C3AED"></i> Assigner à une classe</span>
      <button onclick="document.getElementById('modal-assign').style.display='none'" style="background:none;border:none;cursor:pointer;color:var(--gris-500)"><i data-lucide="x" style="width:18px;height:18px"></i></button>
    </div>
    <div class="modal-body">
      <div id="assign-name" style="font-size:14px;font-weight:700;color:var(--gris-800);margin-bottom:16px"></div>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="assigner_classe">
        <input type="hidden" name="enseignant_id" id="assign-ens-id">
        <div class="form-group">
          <label class="form-label">Classe *</label>
          <select name="classe_id" class="form-control" required>
            <option value="">-- Choisir une classe --</option>
            <?php foreach ($classes as $cl): ?>
            <option value="<?= e($cl['id']) ?>"><?= e($cl['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Matière principale</label>
          <select name="matiere_id" class="form-control">
            <option value="">-- Toutes matières --</option>
            <?php foreach ($matieres as $mat): ?>
            <option value="<?= $mat['id'] ?>"><?= e($mat['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%">
          <i data-lucide="check" style="width:14px;height:14px;vertical-align:-2px"></i> Assigner
        </button>
      </form>
    </div>
  </div>
</div>

<script>
function openAssign(id, name) {
  document.getElementById('assign-ens-id').value = id;
  document.getElementById('assign-name').textContent = 'Enseignant : ' + name;
  document.getElementById('modal-assign').style.display = 'flex';
}
</script>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
