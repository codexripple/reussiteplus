<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Bulletins de notes';
$pageActive = 'ecole_bulletin';
$user = require_login();
if ($user['plan'] !== 'ECOLE') redirect('/reussiteplus/tarifs.php');

$filtreClasse = $_GET['classe']  ?? '';
$periode      = $_GET['periode'] ?? '1er Trimestre';
$eleveId      = $_GET['eleve']   ?? '';

// ── Actions ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { http_response_code(403); exit; }
    $action = $_POST['action'] ?? '';

    if ($action === 'ajouter_note') {
        $classeId   = $_POST['classe_id']   ?? '';
        $eleveId_p  = $_POST['eleve_id']    ?? '';
        $matiere    = trim($_POST['matiere']   ?? '');
        $typeEval   = $_POST['type_eval']      ?? 'DEVOIR';
        $note       = (float)($_POST['note']   ?? 0);
        $noteMax    = (float)($_POST['note_max'] ?? 20);
        $coef       = (float)($_POST['coefficient'] ?? 1);
        $periode_p  = trim($_POST['periode']   ?? '1er Trimestre');
        $comment    = trim($_POST['commentaire'] ?? '');
        $c = dbRow("SELECT id FROM classes_ecole WHERE id=? AND admin_id=?", [$classeId, $user['id']]);
        if ($c && $matiere && $eleveId_p) {
            $validEvals = ['DEVOIR','CONTROLE','EXAM','PROJET','PARTICIPATION'];
            $typeEval = in_array($typeEval, $validEvals) ? $typeEval : 'DEVOIR';
            dbRun("INSERT INTO evaluations_ecole (classe_id, eleve_id, matiere_nom, type_eval, note, note_max, coefficient, periode, commentaire)
                   VALUES (?,?,?,?,?,?,?,?,?)",
                [$classeId, $eleveId_p, $matiere, $typeEval, $note, $noteMax, $coef, $periode_p, $comment ?: null]);
            redirect('/reussiteplus/ecole_bulletin.php?classe='.urlencode($filtreClasse).'&periode='.urlencode($periode).'&eleve='.urlencode($eleveId), 'success', 'Note ajoutée.');
        }
    }

    if ($action === 'supprimer_note') {
        $id = $_POST['note_id'] ?? '';
        dbRun("DELETE e FROM evaluations_ecole e JOIN classes_ecole c ON c.id=e.classe_id WHERE e.id=? AND c.admin_id=?", [$id, $user['id']]);
        redirect('/reussiteplus/ecole_bulletin.php?classe='.urlencode($filtreClasse).'&periode='.urlencode($periode).'&eleve='.urlencode($eleveId), 'success', 'Note supprimée.');
    }
    exit;
}

// ── Données ───────────────────────────────────────────────────
$classes = dbAll("SELECT id, nom FROM classes_ecole WHERE admin_id=? AND actif=1 ORDER BY nom", [$user['id']]) ?? [];
if (!$filtreClasse && $classes) $filtreClasse = $classes[0]['id'];

$classeActive = null;
foreach ($classes as $cl) { if ($cl['id'] === $filtreClasse) { $classeActive = $cl; break; } }

$periodes = ['1er Trimestre', '2ème Trimestre', '3ème Trimestre', 'Annuel'];

$eleves = [];
if ($classeActive) {
    $eleves = dbAll(
        "SELECT u.id, u.nom, u.prenom FROM classe_membres cm JOIN users u ON u.id=cm.user_id WHERE cm.classe_id=? ORDER BY u.nom, u.prenom",
        [$filtreClasse]
    ) ?? [];
}

if (!$eleveId && $eleves) $eleveId = $eleves[0]['id'];

$eleveActif = null;
foreach ($eleves as $el) { if ($el['id'] === $eleveId) { $eleveActif = $el; break; } }

// Notes de l'élève pour la période
$notes = [];
$bulletinData = [];
if ($eleveActif && $classeActive) {
    $notes = dbAll(
        "SELECT * FROM evaluations_ecole WHERE classe_id=? AND eleve_id=? AND periode=? ORDER BY matiere_nom, created_at DESC",
        [$filtreClasse, $eleveId, $periode]
    ) ?? [];

    // Agréger par matière
    foreach ($notes as $n) {
        $mat = $n['matiere_nom'];
        if (!isset($bulletinData[$mat])) {
            $bulletinData[$mat] = ['notes'=>[], 'total_pondere'=>0, 'total_coef'=>0];
        }
        $bulletinData[$mat]['notes'][] = $n;
        $noteSur20 = $n['note_max'] > 0 ? ($n['note'] / $n['note_max'] * 20) : 0;
        $bulletinData[$mat]['total_pondere'] += $noteSur20 * $n['coefficient'];
        $bulletinData[$mat]['total_coef']    += $n['coefficient'];
    }
    foreach ($bulletinData as $mat => &$d) {
        $d['moyenne'] = $d['total_coef'] > 0 ? round($d['total_pondere'] / $d['total_coef'], 2) : 0;
    }
    unset($d);
    ksort($bulletinData);
}

// Moyenne générale
$totalPondere = 0; $totalCoef = 0;
foreach ($bulletinData as $d) {
    $totalPondere += $d['moyenne'] * $d['total_coef'];
    $totalCoef    += $d['total_coef'];
}
$moyenneGenerale = $totalCoef > 0 ? round($totalPondere / $totalCoef, 2) : null;
$mention = $moyenneGenerale !== null
    ? ($moyenneGenerale >= 16 ? 'Très Bien' : ($moyenneGenerale >= 14 ? 'Bien' : ($moyenneGenerale >= 12 ? 'Assez Bien' : ($moyenneGenerale >= 10 ? 'Passable' : 'Insuffisant'))))
    : '—';
$mentionColor = $moyenneGenerale === null ? '#6B7280'
    : ($moyenneGenerale >= 14 ? '#059669' : ($moyenneGenerale >= 10 ? '#D97706' : '#DC2626'));

// Rang dans la classe
$rang = 1; $nbEleves = count($eleves);
if ($eleveActif && $moyenneGenerale !== null) {
    foreach ($eleves as $el) {
        if ($el['id'] === $eleveId) continue;
        $notesOther = dbAll("SELECT * FROM evaluations_ecole WHERE classe_id=? AND eleve_id=? AND periode=?", [$filtreClasse, $el['id'], $periode]) ?? [];
        $tp = 0; $tc = 0;
        foreach ($notesOther as $no) {
            $s20 = $no['note_max'] > 0 ? ($no['note']/$no['note_max']*20) : 0;
            $tp += $s20 * $no['coefficient']; $tc += $no['coefficient'];
        }
        $moy = $tc > 0 ? ($tp/$tc) : 0;
        if ($moy > $moyenneGenerale) $rang++;
    }
}

include __DIR__ . '/includes/header_app.php';
?>

<style>
@media print {
  .sidebar, header, .btn, .bul-action-bar, #modal-add { display:none!important }
  .main-content { margin:0!important; padding:0!important }
  .bul-paper { box-shadow:none!important; border:1px solid #ddd!important }
}
.bul-layout { display:grid; grid-template-columns:240px 1fr; gap:20px; align-items:start; }
@media(max-width:768px){ .bul-layout{grid-template-columns:1fr} }
.bul-eleve-item { display:flex; align-items:center; gap:9px; padding:10px 12px; cursor:pointer; border-radius:8px; margin:1px 4px; transition:.15s; text-decoration:none; }
.bul-eleve-item:hover { background:var(--gris-100); }
.bul-eleve-item.active { background:var(--primary-subtle); }
.bul-paper { background:var(--blanc); border:1.5px solid var(--gris-200); border-radius:var(--radius-xl); padding:32px; }
.bul-header-school { text-align:center; border-bottom:3px double #1E5FAD; padding-bottom:16px; margin-bottom:20px; }
.bul-note-row td { padding:9px 12px; border-bottom:1px solid var(--gris-100); font-size:13px; }
.bul-note-row:last-child td { border-bottom:none; }
.bul-mat-header { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:var(--gris-500); padding:7px 12px; background:var(--gris-50); border-bottom:1px solid var(--gris-200); }
</style>

<!-- Hero compact -->
<div style="background:linear-gradient(135deg,#0c1a2e,#1E5FAD 55%,#0f172a);border-radius:var(--radius-xl);padding:24px;margin-bottom:20px">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
    <div>
      <div style="font-size:11px;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px">
        <a href="/reussiteplus/ecole.php" style="color:rgba(255,255,255,.35);text-decoration:none">Mon École</a> / Bulletins
      </div>
      <div style="font-family:var(--font-display);font-size:20px;font-weight:900;color:#fff">Bulletins de notes</div>
      <div style="font-size:12px;color:rgba(255,255,255,.4);margin-top:3px">Notes, moyennes et rapports imprimables par élève</div>
    </div>
    <div style="display:flex;gap:6px;flex-wrap:wrap">
      <?php foreach ($periodes as $p): ?>
      <a href="/reussiteplus/ecole_bulletin.php?classe=<?= urlencode($filtreClasse) ?>&periode=<?= urlencode($p) ?>&eleve=<?= urlencode($eleveId) ?>"
         style="padding:6px 12px;border-radius:20px;font-size:11px;font-weight:700;text-decoration:none;transition:.15s;<?= $periode===$p?'background:rgba(255,255,255,.9);color:#1E5FAD':'background:rgba(255,255,255,.12);color:rgba(255,255,255,.7);border:1px solid rgba(255,255,255,.2)' ?>">
        <?= e($p) ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Sélecteur classe -->
<div style="display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap">
  <?php foreach ($classes as $cl): ?>
  <a href="/reussiteplus/ecole_bulletin.php?classe=<?= urlencode($cl['id']) ?>&periode=<?= urlencode($periode) ?>"
     style="padding:6px 13px;border-radius:20px;font-size:12px;font-weight:700;text-decoration:none;transition:.15s;<?= $filtreClasse===$cl['id']?'background:#1E5FAD;color:#fff':'background:var(--blanc);color:var(--gris-600);border:1.5px solid var(--gris-200)' ?>">
    <?= e($cl['nom']) ?>
  </a>
  <?php endforeach; ?>
</div>

<div class="bul-layout">
  <!-- Sidebar élèves -->
  <div class="card" style="padding:8px">
    <div style="font-size:11px;font-weight:700;color:var(--gris-500);text-transform:uppercase;letter-spacing:.5px;padding:6px 8px;margin-bottom:4px">Élèves (<?= count($eleves) ?>)</div>
    <?php foreach ($eleves as $el): ?>
    <?php
      $myNotes = dbAll("SELECT note, note_max, coefficient FROM evaluations_ecole WHERE classe_id=? AND eleve_id=? AND periode=?", [$filtreClasse, $el['id'], $periode]) ?? [];
      $myTp = 0; $myTc = 0;
      foreach ($myNotes as $mn) { $s20=$mn['note_max']>0?$mn['note']/$mn['note_max']*20:0; $myTp+=$s20*$mn['coefficient']; $myTc+=$mn['coefficient']; }
      $myMoy = $myTc > 0 ? round($myTp/$myTc,1) : null;
      $mc = $myMoy===null?'#9CA3AF':($myMoy>=12?'#059669':($myMoy>=10?'#D97706':'#DC2626'));
    ?>
    <a href="/reussiteplus/ecole_bulletin.php?classe=<?= urlencode($filtreClasse) ?>&periode=<?= urlencode($periode) ?>&eleve=<?= urlencode($el['id']) ?>"
       class="bul-eleve-item <?= $eleveId===$el['id']?'active':'' ?>">
      <div style="width:30px;height:30px;border-radius:50%;background:var(--gris-100);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:900;color:var(--gris-500);flex-shrink:0">
        <?= mb_strtoupper(mb_substr($el['prenom']??'',0,1).mb_substr($el['nom']??'',0,1)) ?>
      </div>
      <div style="flex:1;min-width:0">
        <div style="font-size:12px;font-weight:700;color:var(--gris-900);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e(($el['prenom']??'').' '.($el['nom']??'')) ?></div>
      </div>
      <?php if ($myMoy !== null): ?>
      <span style="font-size:11px;font-weight:800;color:<?= $mc ?>"><?= $myMoy ?></span>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Bulletin imprimable -->
  <?php if ($eleveActif): ?>
  <div>
    <!-- Barre d'actions -->
    <div class="bul-action-bar" style="display:flex;gap:8px;margin-bottom:12px;justify-content:flex-end">
      <button onclick="document.getElementById('modal-add').style.display='flex'" class="btn btn-primary" style="background:#1E5FAD;border-color:#1E5FAD">
        <i data-lucide="plus" style="width:13px;height:13px;vertical-align:-2px"></i> Ajouter une note
      </button>
      <button onclick="window.print()" class="btn btn-ghost">
        <i data-lucide="printer" style="width:13px;height:13px;vertical-align:-2px"></i> Imprimer
      </button>
    </div>

    <!-- Bulletin papier -->
    <div class="bul-paper" id="bulletin-print">
      <!-- En-tête école -->
      <div class="bul-header-school">
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:2px;color:var(--gris-500);margin-bottom:4px">République Démocratique du Congo</div>
        <div style="font-family:var(--font-display);font-size:20px;font-weight:900;color:#1E5FAD;margin-bottom:2px">RÉUSSITE+</div>
        <div style="font-size:12px;color:var(--gris-600);margin-bottom:6px">Plateforme d'éducation numérique</div>
        <div style="font-family:var(--font-display);font-size:15px;font-weight:800;color:var(--gris-800)">BULLETIN DE NOTES — <?= strtoupper(e($periode)) ?></div>
      </div>

      <!-- Infos élève -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;padding:14px;background:var(--gris-50);border-radius:10px">
        <div>
          <div style="font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:var(--gris-500)">Élève</div>
          <div style="font-family:var(--font-display);font-size:16px;font-weight:900;color:var(--gris-900)"><?= e(strtoupper($eleveActif['nom']??'')).' '.e($eleveActif['prenom']??'') ?></div>
        </div>
        <div>
          <div style="font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:var(--gris-500)">Classe</div>
          <div style="font-size:14px;font-weight:700;color:var(--gris-800)"><?= e($classeActive['nom']) ?></div>
        </div>
        <div>
          <div style="font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:var(--gris-500)">Année scolaire</div>
          <div style="font-size:14px;font-weight:700;color:var(--gris-800)"><?= date('Y') ?> – <?= date('Y')+1 ?></div>
        </div>
        <div>
          <div style="font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:var(--gris-500)">Rang</div>
          <div style="font-size:14px;font-weight:700;color:var(--gris-800)"><?= $rang ?><sup>er</sup> / <?= $nbEleves ?></div>
        </div>
      </div>

      <!-- Tableau des notes -->
      <?php if ($bulletinData): ?>
      <table style="width:100%;border-collapse:collapse;margin-bottom:20px">
        <thead>
          <tr>
            <th style="text-align:left;padding:9px 12px;background:#1E5FAD;color:#fff;font-size:12px;font-weight:700;border-radius:8px 0 0 0">Matière</th>
            <th style="padding:9px 12px;background:#1E5FAD;color:#fff;font-size:12px;font-weight:700;text-align:center">Évaluations</th>
            <th style="padding:9px 12px;background:#1E5FAD;color:#fff;font-size:12px;font-weight:700;text-align:center">Coef.</th>
            <th style="padding:9px 12px;background:#1E5FAD;color:#fff;font-size:12px;font-weight:700;text-align:center;border-radius:0 8px 0 0">Moyenne /20</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($bulletinData as $mat => $d): ?>
          <?php $col = $d['moyenne']>=14?'#059669':($d['moyenne']>=10?'#D97706':'#DC2626'); ?>
          <tr style="border-bottom:1px solid var(--gris-100)">
            <td style="padding:10px 12px;font-weight:700;color:var(--gris-900)"><?= e($mat) ?></td>
            <td style="padding:10px 12px;text-align:center">
              <div style="display:flex;flex-wrap:wrap;gap:3px;justify-content:center">
                <?php foreach ($d['notes'] as $n): ?>
                <span style="font-size:10px;background:var(--gris-100);border-radius:5px;padding:2px 6px;color:var(--gris-700);cursor:default" title="<?= htmlspecialchars($n['type_eval']) ?>"><?= round($n['note'],1) ?>/<?= $n['note_max'] ?></span>
                <?php endforeach; ?>
              </div>
            </td>
            <td style="padding:10px 12px;text-align:center;color:var(--gris-500)"><?= $d['total_coef'] ?></td>
            <td style="padding:10px 12px;text-align:center">
              <span style="font-family:var(--font-display);font-size:15px;font-weight:900;color:<?= $col ?>"><?= $d['moyenne'] ?></span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
      <div style="text-align:center;padding:30px;color:var(--gris-400)">
        <i data-lucide="clipboard" style="width:32px;height:32px;stroke:var(--gris-300)"></i>
        <div style="margin-top:8px;font-size:13px">Aucune note pour ce trimestre.</div>
        <div style="font-size:11px;margin-top:4px">Ajoutez les premières notes via le bouton ci-dessus.</div>
      </div>
      <?php endif; ?>

      <!-- Résumé -->
      <?php if ($moyenneGenerale !== null): ?>
      <div style="background:linear-gradient(135deg,#1E5FAD,#0369a1);border-radius:12px;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
        <div>
          <div style="font-size:11px;color:rgba(255,255,255,.6);text-transform:uppercase;letter-spacing:.5px">Moyenne générale</div>
          <div style="font-family:var(--font-display);font-size:28px;font-weight:900;color:#fff"><?= $moyenneGenerale ?> <span style="font-size:14px;opacity:.6">/20</span></div>
        </div>
        <div style="text-align:center">
          <div style="font-family:var(--font-display);font-size:18px;font-weight:900;color:#fff"><?= $mention ?></div>
          <div style="font-size:11px;color:rgba(255,255,255,.6)">Mention</div>
        </div>
        <div style="text-align:center">
          <div style="font-family:var(--font-display);font-size:18px;font-weight:900;color:#fff"><?= $rang ?><sup style="font-size:12px">er</sup>/<?= $nbEleves ?></div>
          <div style="font-size:11px;color:rgba(255,255,255,.6)">Classement</div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Signature -->
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;margin-top:28px;padding-top:16px;border-top:1px solid var(--gris-200)">
        <?php foreach (['Directeur', 'Titulaire de classe', 'Parent / Tuteur'] as $sig): ?>
        <div style="text-align:center">
          <div style="height:50px;border-bottom:1px solid var(--gris-300);margin-bottom:6px"></div>
          <div style="font-size:11px;color:var(--gris-500)"><?= $sig ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Notes détaillées (hors impression) -->
    <?php if ($notes): ?>
    <div class="bul-action-bar card" style="margin-top:16px;padding:0;overflow:hidden">
      <div style="padding:12px 16px;border-bottom:1px solid var(--gris-100);font-family:var(--font-display);font-size:13px;font-weight:800">Toutes les notes — <?= e($periode) ?></div>
      <table style="width:100%;border-collapse:collapse">
        <thead>
          <tr>
            <th class="bul-mat-header">Matière</th>
            <th class="bul-mat-header" style="text-align:center">Type</th>
            <th class="bul-mat-header" style="text-align:center">Note</th>
            <th class="bul-mat-header" style="text-align:center">Coef.</th>
            <th class="bul-mat-header"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($notes as $n): ?>
          <tr class="bul-note-row">
            <td style="font-weight:700;color:var(--gris-900)"><?= e($n['matiere_nom']) ?></td>
            <td style="text-align:center"><span style="background:var(--gris-100);color:var(--gris-600);font-size:10px;font-weight:700;padding:2px 7px;border-radius:6px"><?= e($n['type_eval']) ?></span></td>
            <td style="text-align:center">
              <?php $note_color = ($n['note']/$n['note_max'])>=0.5?'#059669':'#DC2626'; ?>
              <span style="font-family:var(--font-display);font-size:14px;font-weight:900;color:<?= $note_color ?>"><?= $n['note'] ?></span>
              <span style="font-size:11px;color:var(--gris-400)">/<?= $n['note_max'] ?></span>
            </td>
            <td style="text-align:center;color:var(--gris-500)"><?= $n['coefficient'] ?></td>
            <td>
              <form method="POST" onsubmit="return confirm('Supprimer cette note ?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="supprimer_note">
                <input type="hidden" name="note_id" value="<?= e($n['id']) ?>">
                <button type="submit" style="background:none;border:none;cursor:pointer;color:var(--gris-300);transition:.15s;padding:4px" onmouseover="this.style.color='#DC2626'" onmouseout="this.style.color='var(--gris-300)'">
                  <i data-lucide="trash-2" style="width:13px;height:13px"></i>
                </button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<!-- ══ MODAL Ajouter note ════════════════════════════════════ -->
<div id="modal-add" class="modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);display:none;align-items:center;justify-content:center;z-index:1000;padding:20px;backdrop-filter:blur(5px)" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--blanc);border-radius:20px;width:100%;max-width:460px">
    <div style="padding:20px 24px 16px;border-bottom:1px solid var(--gris-100);display:flex;align-items:center;justify-content:space-between">
      <span style="font-family:var(--font-display);font-size:16px;font-weight:800;display:flex;align-items:center;gap:8px">
        <i data-lucide="plus-circle" style="width:16px;height:16px;stroke:#1E5FAD"></i> Ajouter une note
      </span>
      <button onclick="document.getElementById('modal-add').style.display='none'" style="background:none;border:none;cursor:pointer"><i data-lucide="x" style="width:18px;height:18px;stroke:var(--gris-400)"></i></button>
    </div>
    <div style="padding:20px 24px">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="ajouter_note">
        <input type="hidden" name="classe_id" value="<?= e($filtreClasse) ?>">
        <input type="hidden" name="periode" value="<?= e($periode) ?>">
        <div class="form-group">
          <label class="form-label">Élève *</label>
          <select name="eleve_id" class="form-control" required>
            <option value="">-- Sélectionner --</option>
            <?php foreach ($eleves as $el): ?>
            <option value="<?= e($el['id']) ?>" <?= $eleveId===$el['id']?'selected':'' ?>><?= e(($el['prenom']??'').' '.($el['nom']??'')) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group">
            <label class="form-label">Matière *</label>
            <input type="text" name="matiere" class="form-control" required placeholder="Ex : Mathématiques">
          </div>
          <div class="form-group">
            <label class="form-label">Type d'évaluation</label>
            <select name="type_eval" class="form-control">
              <option value="DEVOIR">Devoir</option>
              <option value="CONTROLE">Contrôle</option>
              <option value="EXAM">Examen</option>
              <option value="PROJET">Projet</option>
              <option value="PARTICIPATION">Participation</option>
            </select>
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
          <div class="form-group">
            <label class="form-label">Note obtenue</label>
            <input type="number" name="note" class="form-control" value="0" min="0" step="0.5" required>
          </div>
          <div class="form-group">
            <label class="form-label">Note max</label>
            <input type="number" name="note_max" class="form-control" value="20" min="1" step="0.5">
          </div>
          <div class="form-group">
            <label class="form-label">Coefficient</label>
            <input type="number" name="coefficient" class="form-control" value="1" min="0.5" max="5" step="0.5">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Commentaire (optionnel)</label>
          <input type="text" name="commentaire" class="form-control" placeholder="Bien travaillé, manque de rigueur…">
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;background:#1E5FAD;border-color:#1E5FAD;padding:13px">
          <i data-lucide="check" style="width:14px;height:14px;vertical-align:-2px"></i> Enregistrer la note
        </button>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
