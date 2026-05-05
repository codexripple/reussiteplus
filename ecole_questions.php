<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Banque de questions';
$pageActive = 'ecole_questions';
$user = require_login();
if ($user['plan'] !== 'ECOLE') redirect('/reussiteplus/tarifs.php');

$filtreExo   = $_GET['exo']   ?? '';
$filtreType  = $_GET['type']  ?? '';
$q           = trim($_GET['q'] ?? '');

// ── Actions ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { http_response_code(403); exit; }
    $action = $_POST['action'] ?? '';

    if ($action === 'supprimer_question') {
        $qid = $_POST['question_id'] ?? '';
        // V&eacute;rifier que la question appartient &agrave; un exercice de cet admin
        $ok = dbRow(
            "SELECT q.id FROM questions_exercice q
             JOIN exercices_ecole e ON e.id=q.exercice_id
             WHERE q.id=? AND e.admin_id=?",
            [$qid, $user['id']]
        );
        if ($ok) {
            dbRun("DELETE FROM options_question WHERE question_id=?", [$qid]);
            dbRun("DELETE FROM questions_exercice WHERE id=?", [$qid]);
            redirect('/reussiteplus/ecole_questions.php', 'success', 'Question supprim&eacute;e.');
        }
    }
    exit;
}

// ── Donn&eacute;es ───────────────────────────────────────────────
// Exercices de cet admin
$exercices = dbAll(
    "SELECT id, titre, type FROM exercices_ecole WHERE admin_id=? ORDER BY created_at DESC",
    [$user['id']]
) ?? [];

// Questions de tous les exercices de cet admin
$whereExtra = ''; $params = [$user['id']];
if ($filtreExo)  { $whereExtra .= ' AND q.exercice_id=?'; $params[] = $filtreExo; }
if ($filtreType) { $whereExtra .= ' AND q.type=?';        $params[] = $filtreType; }
if ($q)          { $whereExtra .= ' AND q.question LIKE ?'; $params[] = "%$q%"; }

$questions = dbAll(
    "SELECT q.*, e.titre as exo_titre, e.type as exo_type,
            (SELECT COUNT(*) FROM options_question WHERE question_id=q.id) as nb_options,
            (SELECT COUNT(*) FROM options_question WHERE question_id=q.id AND est_correcte=1) as nb_correct
     FROM questions_exercice q
     JOIN exercices_ecole e ON e.id=q.exercice_id
     WHERE e.admin_id=? $whereExtra
     ORDER BY e.titre, q.ordre",
    $params
) ?? [];

// Stats globales
$totalQ   = (int)(dbRow("SELECT COUNT(*) as n FROM questions_exercice q JOIN exercices_ecole e ON e.id=q.exercice_id WHERE e.admin_id=?", [$user['id']]) ?? ['n'=>0])['n'];
$totalExo = count($exercices);
$byType   = dbAll("SELECT q.type, COUNT(*) as nb FROM questions_exercice q JOIN exercices_ecole e ON e.id=q.exercice_id WHERE e.admin_id=? GROUP BY q.type", [$user['id']]) ?? [];
$byTypeMap = [];
foreach ($byType as $bt) $byTypeMap[$bt['type']] = $bt['nb'];

include __DIR__ . '/includes/header_app.php';
?>

<style>
.q-row { background:var(--blanc); border:1.5px solid var(--gris-200); border-radius:var(--radius); padding:14px 16px; transition:all .15s; }
.q-row:hover { border-color:var(--primary); box-shadow:0 2px 10px rgba(0,122,94,.08); }
</style>

<!-- Hero -->
<div style="background:linear-gradient(135deg,#0f172a,#4c1d95 55%,#0f172a);border-radius:var(--radius-xl);padding:26px;margin-bottom:20px">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px">
    <div>
      <div style="font-size:11px;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Mon &Eacute;cole</div>
      <div style="font-family:var(--font-display);font-size:20px;font-weight:900;color:#fff;display:flex;align-items:center;gap:10px">
        <i data-lucide="help-circle" style="width:22px;height:22px;stroke:#c4b5fd"></i>
        Banque de questions
      </div>
      <div style="font-size:12px;color:rgba(255,255,255,.45);margin-top:3px">Toutes les questions de vos exercices</div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <?php foreach ([[$totalQ,'Questions','#c4b5fd'],[$totalExo,'Exercices','#fff']] as [$v,$l,$c]): ?>
      <div style="text-align:center;padding:8px 14px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:12px">
        <div style="font-family:var(--font-display);font-size:20px;font-weight:900;color:<?= $c ?>"><?= $v ?></div>
        <div style="font-size:9px;color:rgba(255,255,255,.4);text-transform:uppercase"><?= $l ?></div>
      </div>
      <?php endforeach; ?>
      <?php foreach ([['QCM','#60a5fa'],['VRAI_FAUX','#34d399'],['TEXTE_LIBRE','#fbbf24']] as [$t,$c]): ?>
      <?php if (isset($byTypeMap[$t])): ?>
      <div style="text-align:center;padding:8px 14px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:12px">
        <div style="font-family:var(--font-display);font-size:20px;font-weight:900;color:<?= $c ?>"><?= $byTypeMap[$t] ?></div>
        <div style="font-size:9px;color:rgba(255,255,255,.4);text-transform:uppercase"><?= $t ?></div>
      </div>
      <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php if (!$exercices): ?>
<div class="card" style="text-align:center;padding:60px 30px">
  <div style="display:flex;justify-content:center;margin-bottom:16px">
    <i data-lucide="inbox" style="width:48px;height:48px;stroke:var(--gris-300);stroke-width:1.5"></i>
  </div>
  <div style="font-family:var(--font-display);font-size:20px;font-weight:800;margin-bottom:8px">Aucun exercice cr&eacute;&eacute;</div>
  <p style="color:var(--gris-500);font-size:13px;max-width:380px;margin:0 auto 24px">Cr&eacute;ez d&apos;abord des exercices pour pouvoir g&eacute;rer vos questions.</p>
  <a href="/reussiteplus/ecole_exercices.php" class="btn btn-primary" style="background:#7C3AED;border-color:#7C3AED">
    <i data-lucide="plus" style="width:13px;height:13px;stroke:#fff;vertical-align:-2px"></i> Cr&eacute;er un exercice
  </a>
</div>
<?php else: ?>

<!-- Filtres -->
<div style="background:var(--blanc);border:1.5px solid var(--gris-200);border-radius:var(--radius-lg);padding:14px 18px;margin-bottom:18px">
  <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <div style="position:relative;flex:1;min-width:200px">
      <i data-lucide="search" style="width:14px;height:14px;position:absolute;left:12px;top:50%;transform:translateY(-50%);stroke:var(--gris-400)"></i>
      <input type="text" name="q" value="<?= e($q) ?>" placeholder="Rechercher une question&hellip;" class="form-control" style="padding-left:36px;margin-bottom:0">
    </div>
    <select name="exo" class="form-control" style="width:200px;margin-bottom:0">
      <option value="">Tous les exercices</option>
      <?php foreach ($exercices as $exo): ?>
      <option value="<?= e($exo['id']) ?>" <?= $filtreExo===$exo['id']?'selected':'' ?>><?= e(mb_strimwidth($exo['titre'],0,40,'&hellip;')) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="type" class="form-control" style="width:140px;margin-bottom:0">
      <option value="">Tous types</option>
      <option value="QCM" <?= $filtreType==='QCM'?'selected':'' ?>>QCM</option>
      <option value="VRAI_FAUX" <?= $filtreType==='VRAI_FAUX'?'selected':'' ?>>Vrai / Faux</option>
      <option value="TEXTE_LIBRE" <?= $filtreType==='TEXTE_LIBRE'?'selected':'' ?>>R&eacute;ponse libre</option>
    </select>
    <button type="submit" class="btn btn-primary" style="background:#7C3AED;border-color:#7C3AED">
      <i data-lucide="filter" style="width:13px;height:13px;stroke:#fff;vertical-align:-2px"></i> Filtrer
    </button>
    <?php if ($q || $filtreExo || $filtreType): ?>
    <a href="/reussiteplus/ecole_questions.php" class="btn btn-ghost">Effacer</a>
    <?php endif; ?>
    <a href="/reussiteplus/ecole_exercices.php" class="btn btn-ghost btn-sm" style="margin-left:auto">
      <i data-lucide="edit-3" style="width:12px;height:12px"></i> G&eacute;rer les exercices
    </a>
  </form>
</div>

<?php
$typeConfig = [
  'QCM'         => ['color'=>'#1E5FAD','bg'=>'#DBEAFE','icon'=>'list','label'=>'QCM'],
  'VRAI_FAUX'   => ['color'=>'#059669','bg'=>'#D1FAE5','icon'=>'check-square','label'=>'Vrai/Faux'],
  'TEXTE_LIBRE' => ['color'=>'#B45309','bg'=>'#FEF3C7','icon'=>'edit-2','label'=>'Libre'],
];

// Grouper par exercice
$byExo = [];
foreach ($questions as $quest) {
    $byExo[$quest['exo_titre']] = $byExo[$quest['exo_titre']] ?? [];
    $byExo[$quest['exo_titre']][] = $quest;
}
?>

<?php if ($questions): ?>
<?php foreach ($byExo as $exoTitre => $qs): ?>
<div style="margin-bottom:20px">
  <div style="font-family:var(--font-display);font-size:13px;font-weight:800;color:var(--gris-700);margin-bottom:10px;display:flex;align-items:center;gap:8px;padding-bottom:8px;border-bottom:2px solid var(--gris-200)">
    <i data-lucide="layers" style="width:14px;height:14px;stroke:#7C3AED"></i>
    <?= e($exoTitre) ?>
    <span style="font-size:11px;font-weight:600;color:var(--gris-400)">(<?= count($qs) ?> question<?= count($qs)>1?'s':'' ?>)</span>
  </div>
  <div style="display:flex;flex-direction:column;gap:8px">
  <?php foreach ($qs as $i => $quest):
    $tc = $typeConfig[$quest['type']] ?? $typeConfig['QCM'];
  ?>
  <div class="q-row">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px">
      <div style="flex:1">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;flex-wrap:wrap">
          <span style="background:var(--gris-100);color:var(--gris-600);font-size:11px;font-weight:800;padding:1px 8px;border-radius:6px">Q<?= $i+1 ?></span>
          <span style="background:<?= $tc['bg'] ?>;color:<?= $tc['color'] ?>;font-size:10px;font-weight:800;padding:2px 9px;border-radius:6px;display:inline-flex;align-items:center;gap:4px">
            <i data-lucide="<?= $tc['icon'] ?>" style="width:10px;height:10px"></i> <?= $tc['label'] ?>
          </span>
          <span style="font-size:11px;color:var(--gris-500)">
            <i data-lucide="star" style="width:10px;height:10px;vertical-align:-1px"></i> <?= number_format($quest['points'],1) ?> pt<?= $quest['points']>1?'s':'' ?>
          </span>
          <?php if ($quest['nb_options'] > 0): ?>
          <span style="font-size:11px;color:var(--gris-400)"><?= $quest['nb_options'] ?> options &bull; <?= $quest['nb_correct'] ?> correcte<?= $quest['nb_correct']>1?'s':'' ?></span>
          <?php endif; ?>
        </div>
        <div style="font-size:13px;color:var(--gris-900);line-height:1.5"><?= e(mb_strimwidth($quest['question'],0,150,'&hellip;')) ?></div>
        <?php if ($quest['explication']): ?>
        <div style="margin-top:6px;font-size:11px;color:#B45309;background:#FEF3C7;padding:5px 10px;border-radius:7px;border-left:3px solid #F59E0B;display:flex;gap:5px;align-items:flex-start">
          <i data-lucide="info" style="width:11px;height:11px;flex-shrink:0;margin-top:1px"></i>
          <?= e(mb_strimwidth($quest['explication'],0,100,'&hellip;')) ?>
        </div>
        <?php endif; ?>
      </div>
      <div style="display:flex;gap:6px;flex-shrink:0">
        <a href="/reussiteplus/ecole_exercices.php?edit=<?= urlencode($quest['exercice_id']) ?>" class="btn btn-ghost btn-sm" title="Modifier dans l'exercice">
          <i data-lucide="edit-3" style="width:12px;height:12px"></i>
        </a>
        <form method="POST" onsubmit="return confirm('Supprimer cette question ?')" style="display:inline">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="supprimer_question">
          <input type="hidden" name="question_id" value="<?= e($quest['id']) ?>">
          <button type="submit" class="btn btn-ghost btn-sm" style="color:#DC2626;border-color:#FECACA">
            <i data-lucide="trash-2" style="width:12px;height:12px"></i>
          </button>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>

<?php else: ?>
<div style="text-align:center;padding:50px 20px;background:var(--gris-50);border:2px dashed var(--gris-200);border-radius:14px">
  <div style="display:flex;justify-content:center;margin-bottom:14px">
    <i data-lucide="help-circle" style="width:48px;height:48px;stroke:var(--gris-300);stroke-width:1.5"></i>
  </div>
  <div style="font-size:15px;font-weight:700;color:var(--gris-600)">Aucune question trouv&eacute;e</div>
  <div style="font-size:12px;color:var(--gris-400);margin-top:4px">
    <?= ($q || $filtreExo || $filtreType) ? 'Aucune question ne correspond &agrave; vos filtres.' : 'Ajoutez des questions depuis vos exercices.' ?>
  </div>
  <?php if ($q || $filtreExo || $filtreType): ?>
  <a href="/reussiteplus/ecole_questions.php" class="btn btn-ghost" style="margin-top:14px">Effacer les filtres</a>
  <?php else: ?>
  <a href="/reussiteplus/ecole_exercices.php" class="btn btn-primary" style="background:#7C3AED;border-color:#7C3AED;margin-top:14px">Aller aux exercices</a>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
