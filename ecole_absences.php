<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Gestion des absences';
$pageActive = 'ecole_absences';
$user = require_login();
if ($user['plan'] !== 'ECOLE') redirect('/reussiteplus/tarifs.php');

$filtreClasse = $_GET['classe'] ?? '';
$filtreDate   = $_GET['mois']   ?? date('Y-m');  // YYYY-MM

// ── Actions ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { http_response_code(403); exit; }
    $action = $_POST['action'] ?? '';

    if ($action === 'ajouter_absence') {
        $classeId    = $_POST['classe_id']   ?? '';
        $eleveId     = $_POST['eleve_id']    ?? '';
        $date        = $_POST['date_absence'] ?? date('Y-m-d');
        $seance      = trim($_POST['seance'] ?? 'Journée');
        $motif       = trim($_POST['motif']  ?? '');
        $justifiee   = (int)(bool)($_POST['justifiee'] ?? 0);
        // Vérifier propriété de la classe
        $c = dbRow("SELECT id FROM classes_ecole WHERE id=? AND admin_id=?", [$classeId, $user['id']]);
        if ($c && $eleveId && $date) {
            dbRun("INSERT IGNORE INTO absences_ecole (classe_id, eleve_id, date_absence, seance, motif, justifiee)
                   VALUES (?,?,?,?,?,?)", [$classeId, $eleveId, $date, $seance, $motif ?: null, $justifiee]);
            redirect('/reussiteplus/ecole_absences.php?classe='.urlencode($filtreClasse).'&mois='.urlencode($filtreDate), 'success', 'Absence enregistrée.');
        }
    }

    if ($action === 'justifier') {
        $id = $_POST['absence_id'] ?? '';
        // Join to verify ownership
        dbRun("UPDATE absences_ecole a JOIN classes_ecole c ON c.id=a.classe_id SET a.justifiee=1 WHERE a.id=? AND c.admin_id=?", [$id, $user['id']]);
        redirect('/reussiteplus/ecole_absences.php?classe='.urlencode($filtreClasse).'&mois='.urlencode($filtreDate), 'success', 'Absence justifiée.');
    }

    if ($action === 'supprimer_absence') {
        $id = $_POST['absence_id'] ?? '';
        dbRun("DELETE a FROM absences_ecole a JOIN classes_ecole c ON c.id=a.classe_id WHERE a.id=? AND c.admin_id=?", [$id, $user['id']]);
        redirect('/reussiteplus/ecole_absences.php?classe='.urlencode($filtreClasse).'&mois='.urlencode($filtreDate), 'success', 'Absence supprimée.');
    }
    exit;
}

// ── Données ───────────────────────────────────────────────────
$classes = dbAll("SELECT id, nom FROM classes_ecole WHERE admin_id=? AND actif=1 ORDER BY nom", [$user['id']]) ?? [];
if (!$filtreClasse && $classes) $filtreClasse = $classes[0]['id'];

$classeActive = null;
foreach ($classes as $cl) { if ($cl['id'] === $filtreClasse) { $classeActive = $cl; break; } }

$eleves = [];
if ($classeActive) {
    $eleves = dbAll(
        "SELECT u.id, u.nom, u.prenom FROM classe_membres cm JOIN utilisateurs u ON u.id=cm.eleve_id WHERE cm.classe_id=? ORDER BY u.nom, u.prenom",
        [$filtreClasse]
    ) ?? [];
}

// Absences du mois filtré
$dateDebut = $filtreDate . '-01';
$dateFin   = date('Y-m-t', strtotime($dateDebut));

$absences = [];
if ($classeActive) {
    $absences = dbAll(
        "SELECT a.*, u.nom, u.prenom FROM absences_ecole a
         JOIN users u ON u.id=a.eleve_id
         WHERE a.classe_id=? AND a.date_absence BETWEEN ? AND ?
         ORDER BY a.date_absence DESC, u.nom",
        [$filtreClasse, $dateDebut, $dateFin]
    ) ?? [];
}

// Stats par élève
$statsEleves = [];
if ($classeActive) {
    $rows = dbAll(
        "SELECT a.eleve_id, u.nom, u.prenom,
                COUNT(*) as total_abs,
                SUM(a.justifiee=1) as nb_justifiees,
                SUM(a.justifiee=0) as nb_injustifiees
         FROM absences_ecole a JOIN users u ON u.id=a.eleve_id
         WHERE a.classe_id=? AND a.date_absence BETWEEN ? AND ?
         GROUP BY a.eleve_id ORDER BY total_abs DESC",
        [$filtreClasse, $dateDebut, $dateFin]
    ) ?? [];
    foreach ($rows as $r) $statsEleves[$r['eleve_id']] = $r;
}

$totalAbs   = count($absences);
$injust     = array_sum(array_column($absences, 'justifiee') ? [] : array_map(fn($a)=>!$a['justifiee']?1:0, $absences));
$injust     = count(array_filter($absences, fn($a) => !$a['justifiee']));

// Générer navigation mois
$moisPrev = date('Y-m', strtotime($filtreDate.'-01 -1 month'));
$moisNext = date('Y-m', strtotime($filtreDate.'-01 +1 month'));
$moisLibelle = strftime('%B %Y', strtotime($filtreDate.'-01')) ?: date('F Y', strtotime($filtreDate.'-01'));

include __DIR__ . '/includes/header_app.php';
?>

<style>
.abs-hero { background:linear-gradient(135deg,#450a0a,#b91c1c 50%,#1e1b4b); border-radius:var(--radius-xl); padding:26px; margin-bottom:20px; }
.abs-layout { display:grid; grid-template-columns:1fr 320px; gap:20px; align-items:start; }
@media(max-width:900px){ .abs-layout{grid-template-columns:1fr} }
.abs-table th { font-size:11px; font-weight:700; color:var(--gris-500); text-transform:uppercase; letter-spacing:.5px; padding:8px 12px; background:var(--gris-50); border-bottom:1px solid var(--gris-200); }
.abs-table td { padding:10px 12px; border-bottom:1px solid var(--gris-100); font-size:13px; vertical-align:middle; }
.abs-table tr:last-child td { border-bottom:none; }
.abs-table tr:hover td { background:var(--gris-50); }
.badge-just { display:inline-flex; align-items:center; gap:4px; padding:3px 8px; border-radius:8px; font-size:10px; font-weight:700; }
</style>

<!-- Hero -->
<div class="abs-hero">
  <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:16px">
    <div>
      <div style="font-size:11px;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px">
        <a href="/reussiteplus/ecole.php" style="color:rgba(255,255,255,.35);text-decoration:none">Mon École</a> / Absences
      </div>
      <div style="font-family:var(--font-display);font-size:22px;font-weight:900;color:#fff;margin-bottom:4px">Suivi des absences</div>
      <div style="font-size:12px;color:rgba(255,255,255,.45)">Enregistrez, justifiez et analysez les absences de vos élèves</div>
    </div>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <div style="text-align:center;padding:10px 16px;border-radius:12px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.08)">
        <div style="font-family:var(--font-display);font-size:20px;font-weight:900;color:#fff"><?= $totalAbs ?></div>
        <div style="font-size:10px;color:rgba(255,255,255,.4);text-transform:uppercase">Ce mois</div>
      </div>
      <div style="text-align:center;padding:10px 16px;border-radius:12px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.08)">
        <div style="font-family:var(--font-display);font-size:20px;font-weight:900;color:#FCA5A5"><?= $injust ?></div>
        <div style="font-size:10px;color:rgba(255,255,255,.4);text-transform:uppercase">Injustifiées</div>
      </div>
      <?php if ($classeActive): ?>
      <button onclick="document.getElementById('modal-add').style.display='flex'"
              style="background:rgba(185,28,28,.6);border:1.5px solid rgba(239,68,68,.5);color:#fff;padding:10px 18px;border-radius:var(--radius);font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:7px;transition:.15s"
              onmouseover="this.style.background='rgba(185,28,28,.9)'" onmouseout="this.style.background='rgba(185,28,28,.6)'">
        <i data-lucide="user-x" style="width:15px;height:15px;stroke:#fff"></i> Enregistrer une absence
      </button>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Sélecteur de classe + navigation mois -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:18px">
  <div style="display:flex;gap:6px;flex-wrap:wrap">
    <?php foreach ($classes as $cl): ?>
    <a href="/reussiteplus/ecole_absences.php?classe=<?= urlencode($cl['id']) ?>&mois=<?= urlencode($filtreDate) ?>"
       style="padding:7px 14px;border-radius:20px;font-size:12px;font-weight:700;text-decoration:none;transition:.15s;<?= $filtreClasse===$cl['id']?'background:#b91c1c;color:#fff':'background:var(--blanc);color:var(--gris-600);border:1.5px solid var(--gris-200)' ?>">
      <?= e($cl['nom']) ?>
    </a>
    <?php endforeach; ?>
  </div>
  <div style="display:flex;align-items:center;gap:8px">
    <a href="/reussiteplus/ecole_absences.php?classe=<?= urlencode($filtreClasse) ?>&mois=<?= $moisPrev ?>"
       style="padding:6px 10px;background:var(--blanc);border:1.5px solid var(--gris-200);border-radius:8px;text-decoration:none;color:var(--gris-600);font-size:12px;transition:.15s"
       onmouseover="this.style.background='var(--gris-50)'" onmouseout="this.style.background='var(--blanc)'">
      <i data-lucide="chevron-left" style="width:14px;height:14px;vertical-align:-2px"></i>
    </a>
    <span style="font-family:var(--font-display);font-size:14px;font-weight:800;color:var(--gris-800);padding:0 4px;text-transform:capitalize"><?= ucfirst(date('F Y', strtotime($filtreDate.'-01'))) ?></span>
    <a href="/reussiteplus/ecole_absences.php?classe=<?= urlencode($filtreClasse) ?>&mois=<?= $moisNext ?>"
       style="padding:6px 10px;background:var(--blanc);border:1.5px solid var(--gris-200);border-radius:8px;text-decoration:none;color:var(--gris-600);font-size:12px;transition:.15s"
       onmouseover="this.style.background='var(--gris-50)'" onmouseout="this.style.background='var(--blanc)'">
      <i data-lucide="chevron-right" style="width:14px;height:14px;vertical-align:-2px"></i>
    </a>
  </div>
</div>

<div class="abs-layout">
  <!-- Tableau des absences -->
  <div class="card" style="padding:0;overflow:hidden">
    <div style="padding:14px 18px;border-bottom:1px solid var(--gris-100);display:flex;align-items:center;justify-content:space-between">
      <div style="font-family:var(--font-display);font-size:14px;font-weight:800">
        <?= e($classeActive['nom'] ?? 'Classe') ?> — <?= count($absences) ?> absence<?= count($absences)!=1?'s':'' ?>
      </div>
    </div>
    <?php if ($absences): ?>
    <div style="overflow-x:auto">
      <table class="abs-table" style="width:100%;border-collapse:collapse">
        <thead>
          <tr>
            <th>Élève</th>
            <th>Date</th>
            <th>Séance</th>
            <th>Motif</th>
            <th>Statut</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($absences as $abs): ?>
          <tr>
            <td>
              <div style="font-weight:700;color:var(--gris-900)"><?= e(($abs['prenom']??'').' '.($abs['nom']??'')) ?></div>
            </td>
            <td style="color:var(--gris-600)"><?= date('d/m/Y', strtotime($abs['date_absence'])) ?></td>
            <td style="color:var(--gris-500)"><?= e($abs['seance']) ?></td>
            <td style="color:var(--gris-500);max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= $abs['motif'] ? e($abs['motif']) : '—' ?></td>
            <td>
              <?php if ($abs['justifiee']): ?>
              <span class="badge-just" style="background:#D1FAE5;color:#059669"><i data-lucide="check-circle" style="width:10px;height:10px"></i> Justifiée</span>
              <?php else: ?>
              <span class="badge-just" style="background:#FEE2E2;color:#DC2626"><i data-lucide="x-circle" style="width:10px;height:10px"></i> Injustifiée</span>
              <?php endif; ?>
            </td>
            <td>
              <div style="display:flex;gap:4px">
                <?php if (!$abs['justifiee']): ?>
                <form method="POST" style="display:inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="justifier">
                  <input type="hidden" name="absence_id" value="<?= e($abs['id']) ?>">
                  <button type="submit" title="Justifier" style="background:none;border:none;cursor:pointer;color:var(--gris-400);transition:.15s;padding:3px" onmouseover="this.style.color='#059669'" onmouseout="this.style.color='var(--gris-400)'">
                    <i data-lucide="check" style="width:14px;height:14px"></i>
                  </button>
                </form>
                <?php endif; ?>
                <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer cette absence ?')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="supprimer_absence">
                  <input type="hidden" name="absence_id" value="<?= e($abs['id']) ?>">
                  <button type="submit" title="Supprimer" style="background:none;border:none;cursor:pointer;color:var(--gris-400);transition:.15s;padding:3px" onmouseover="this.style.color='#DC2626'" onmouseout="this.style.color='var(--gris-400)'">
                    <i data-lucide="trash-2" style="width:14px;height:14px"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:50px 20px">
      <i data-lucide="user-check" style="width:40px;height:40px;stroke:#D1D5DB"></i>
      <div style="font-family:var(--font-display);font-size:16px;font-weight:800;color:var(--gris-700);margin-top:12px">Aucune absence ce mois</div>
      <div style="font-size:12px;color:var(--gris-400);margin-top:4px">100% de présence !</div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Sidebar : top absentéistes -->
  <div>
    <div class="card">
      <div style="font-family:var(--font-display);font-size:13px;font-weight:800;margin-bottom:14px;display:flex;align-items:center;gap:7px">
        <i data-lucide="bar-chart-2" style="width:14px;height:14px;stroke:#b91c1c"></i> Classement absentéisme
      </div>
      <?php if ($statsEleves): ?>
      <?php foreach (array_slice($statsEleves, 0, 8) as $st): ?>
      <?php $maxAbs = max(array_column($statsEleves, 'total_abs')); $pct = $maxAbs ? round($st['total_abs']/$maxAbs*100) : 0; ?>
      <div style="margin-bottom:10px">
        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px">
          <span style="font-weight:700;color:var(--gris-800)"><?= e(($st['prenom']??'').' '.($st['nom']??'')) ?></span>
          <span style="color:<?= $st['nb_injustifiees']>0?'#DC2626':'#6B7280' ?>;font-weight:700"><?= $st['total_abs'] ?>×</span>
        </div>
        <div style="height:5px;background:var(--gris-200);border-radius:3px">
          <div style="width:<?= $pct ?>%;height:100%;background:<?= $st['nb_injustifiees']>2?'#DC2626':($st['nb_injustifiees']>0?'#D97706':'#059669') ?>;border-radius:3px;transition:.4s"></div>
        </div>
        <div style="font-size:10px;color:var(--gris-400);margin-top:2px"><?= $st['nb_justifiees'] ?> just. · <?= $st['nb_injustifiees'] ?> injust.</div>
      </div>
      <?php endforeach; ?>
      <?php else: ?>
      <div style="text-align:center;padding:20px;color:var(--gris-400);font-size:12px">Aucune donnée ce mois</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ══ MODAL Ajouter absence ════════════════════════════════ -->
<div id="modal-add" class="modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);display:none;align-items:center;justify-content:center;z-index:1000;padding:20px;backdrop-filter:blur(5px)" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--blanc);border-radius:20px;width:100%;max-width:440px">
    <div style="padding:20px 24px 16px;border-bottom:1px solid var(--gris-100);display:flex;align-items:center;justify-content:space-between">
      <span style="font-family:var(--font-display);font-size:16px;font-weight:800;display:flex;align-items:center;gap:8px">
        <i data-lucide="user-x" style="width:16px;height:16px;stroke:#b91c1c"></i> Enregistrer une absence
      </span>
      <button onclick="document.getElementById('modal-add').style.display='none'" style="background:none;border:none;cursor:pointer"><i data-lucide="x" style="width:18px;height:18px;stroke:var(--gris-400)"></i></button>
    </div>
    <div style="padding:20px 24px">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="ajouter_absence">
        <input type="hidden" name="classe_id" value="<?= e($filtreClasse) ?>">
        <div class="form-group">
          <label class="form-label">Élève *</label>
          <select name="eleve_id" class="form-control" required>
            <option value="">-- Choisir un élève --</option>
            <?php foreach ($eleves as $el): ?>
            <option value="<?= e($el['id']) ?>"><?= e(($el['prenom']??'').' '.($el['nom']??'')) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group">
            <label class="form-label">Date *</label>
            <input type="date" name="date_absence" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Séance</label>
            <select name="seance" class="form-control">
              <option>Journée</option>
              <option>Matin</option>
              <option>Après-midi</option>
              <option>1ère heure</option>
              <option>2ème heure</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Motif (optionnel)</label>
          <input type="text" name="motif" class="form-control" placeholder="Maladie, voyage, rendez-vous médical…">
        </div>
        <div class="form-group" style="display:flex;align-items:center;gap:10px">
          <input type="checkbox" name="justifiee" value="1" id="just-check" style="width:16px;height:16px;cursor:pointer">
          <label for="just-check" class="form-label" style="margin:0;cursor:pointer">Absence justifiée</label>
        </div>
        <button type="submit" class="btn" style="width:100%;background:#b91c1c;border-color:#b91c1c;color:#fff;padding:13px;font-weight:700;border-radius:var(--radius);cursor:pointer">
          <i data-lucide="check" style="width:14px;height:14px;vertical-align:-2px"></i> Enregistrer
        </button>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
