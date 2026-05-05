<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Exercices & Questionnaires';
$pageActive = 'ecole_exercices';
$user = require_login();
if ($user['plan'] !== 'ECOLE') redirect('/reussiteplus/tarifs.php');

$classes = dbAll("SELECT id, nom FROM classes_ecole WHERE admin_id=? AND actif=1 ORDER BY nom", [$user['id']]) ?? [];

// ── Actions ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { http_response_code(403); exit; }
    $action = $_POST['action'] ?? '';

    if ($action === 'creer_exercice') {
        $titre  = trim($_POST['titre'] ?? '');
        $type   = in_array($_POST['type']??'', ['QCM','VRAI_FAUX','MIXTE']) ? $_POST['type'] : 'QCM';
        $classeId = $_POST['classe_id'] ?: null;
        $duree  = max(5, (int)($_POST['duree_minutes'] ?? 30));
        $desc   = trim($_POST['description'] ?? '');
        if ($titre) {
            $id = dbInsert('exercices_ecole', [
                'ecole_admin_id' => $user['id'],
                'classe_id'      => $classeId,
                'titre'          => $titre,
                'description'    => $desc ?: null,
                'type'           => $type,
                'duree_minutes'  => $duree,
                'note_max'       => (float)($_POST['note_max'] ?? 20),
            ]);
            redirect('/reussiteplus/ecole_exercices.php?edit=' . urlencode($id), 'success', 'Exercice créé ! Ajoutez maintenant vos questions.');
        }
    }

    if ($action === 'ajouter_question') {
        $exoId    = $_POST['exercice_id'] ?? '';
        $question = trim($_POST['question'] ?? '');
        $type_q   = in_array($_POST['type_q']??'', ['QCM','VRAI_FAUX','TEXTE_LIBRE']) ? $_POST['type_q'] : 'QCM';
        $points   = (float)($_POST['points'] ?? 1);
        $expl     = trim($_POST['explication'] ?? '');
        // Vérifier propriété
        $exo = dbRow("SELECT id FROM exercices_ecole WHERE id=? AND ecole_admin_id=?", [$exoId, $user['id']]);
        if ($exo && $question) {
            $ordre = (int)dbVal("SELECT COALESCE(MAX(ordre),0)+1 FROM questions_exercice WHERE exercice_id=?", [$exoId]);
            $qId = dbInsert('questions_exercice', [
                'exercice_id' => $exoId, 'question' => $question, 'type' => $type_q,
                'points' => $points, 'ordre' => $ordre, 'explication' => $expl ?: null,
            ]);
            // Options
            if ($type_q === 'VRAI_FAUX') {
                $correct = $_POST['vf_correct'] ?? 'VRAI';
                foreach (['VRAI','FAUX'] as $i => $opt) {
                    dbInsert('options_question', ['question_id'=>$qId,'texte'=>$opt,'est_correcte'=>($opt===$correct?1:0),'ordre'=>$i]);
                }
            } elseif ($type_q === 'QCM') {
                $opts    = $_POST['option_texte'] ?? [];
                $corrects = $_POST['option_correct'] ?? [];
                foreach ($opts as $i => $opt) {
                    if (trim($opt)) {
                        dbInsert('options_question', ['question_id'=>$qId,'texte'=>trim($opt),'est_correcte'=>(in_array((string)$i,$corrects)?1:0),'ordre'=>$i]);
                    }
                }
            }
            redirect('/reussiteplus/ecole_exercices.php?edit=' . urlencode($exoId), 'success', 'Question ajoutée !');
        }
    }

    if ($action === 'supprimer_question') {
        $qId  = $_POST['question_id'] ?? '';
        $exoId = $_POST['exercice_id'] ?? '';
        $exo = dbRow("SELECT id FROM exercices_ecole WHERE id=? AND ecole_admin_id=?", [$exoId, $user['id']]);
        if ($exo) {
            dbRun("DELETE FROM options_question WHERE question_id=?", [$qId]);
            dbRun("DELETE FROM questions_exercice WHERE id=? AND exercice_id=?", [$qId, $exoId]);
            redirect('/reussiteplus/ecole_exercices.php?edit=' . urlencode($exoId), 'success', 'Question supprimée.');
        }
    }

    if ($action === 'toggle_actif') {
        $exoId = $_POST['exercice_id'] ?? '';
        $exo = dbRow("SELECT id, actif FROM exercices_ecole WHERE id=? AND ecole_admin_id=?", [$exoId, $user['id']]);
        if ($exo) {
            dbRun("UPDATE exercices_ecole SET actif=? WHERE id=?", [$exo['actif'] ? 0 : 1, $exoId]);
        }
        redirect('/reussiteplus/ecole_exercices.php', 'success', 'Statut mis à jour.');
    }

    if ($action === 'supprimer_exercice') {
        $exoId = $_POST['exercice_id'] ?? '';
        $exo = dbRow("SELECT id FROM exercices_ecole WHERE id=? AND ecole_admin_id=?", [$exoId, $user['id']]);
        if ($exo) {
            $qIds = array_column(dbAll("SELECT id FROM questions_exercice WHERE exercice_id=?", [$exoId])??[], 'id');
            foreach ($qIds as $qid) dbRun("DELETE FROM options_question WHERE question_id=?", [$qid]);
            dbRun("DELETE FROM questions_exercice WHERE exercice_id=?", [$exoId]);
            dbRun("DELETE FROM reponses_exercice WHERE session_id IN (SELECT id FROM sessions_exercice WHERE exercice_id=?)", [$exoId]);
            dbRun("DELETE FROM sessions_exercice WHERE exercice_id=?", [$exoId]);
            dbRun("DELETE FROM exercices_ecole WHERE id=?", [$exoId]);
            redirect('/reussiteplus/ecole_exercices.php', 'success', 'Exercice supprimé.');
        }
    }
    exit;
}

// ── Mode édition d'un exercice ────────────────────────────
$editId = $_GET['edit'] ?? '';
$exoEdit = null;
$questions = [];
$resultats = [];

if ($editId) {
    $exoEdit = dbRow("SELECT e.*, c.nom as classe_nom FROM exercices_ecole e LEFT JOIN classes_ecole c ON c.id=e.classe_id WHERE e.id=? AND e.ecole_admin_id=?", [$editId, $user['id']]);
    if ($exoEdit) {
        $questions = dbAll(
            "SELECT q.*, (SELECT COUNT(*) FROM options_question WHERE question_id=q.id) as nb_options,
                    (SELECT GROUP_CONCAT(texte ORDER BY ordre SEPARATOR '|') FROM options_question WHERE question_id=q.id) as options_txt,
                    (SELECT GROUP_CONCAT(est_correcte ORDER BY ordre SEPARATOR '|') FROM options_question WHERE question_id=q.id) as options_correct
             FROM questions_exercice q WHERE q.exercice_id=? ORDER BY q.ordre",
            [$editId]
        ) ?? [];
        $resultats = dbAll(
            "SELECT s.*, u.prenom, u.nom FROM sessions_exercice s JOIN utilisateurs u ON u.id=s.eleve_id WHERE s.exercice_id=? AND s.statut='TERMINE' ORDER BY s.score DESC",
            [$editId]
        ) ?? [];
    }
}

// Liste de tous les exercices
$exercices = dbAll(
    "SELECT e.*, c.nom as classe_nom,
            (SELECT COUNT(*) FROM questions_exercice WHERE exercice_id=e.id) as nb_questions,
            (SELECT COUNT(*) FROM sessions_exercice WHERE exercice_id=e.id AND statut='TERMINE') as nb_sessions
     FROM exercices_ecole e LEFT JOIN classes_ecole c ON c.id=e.classe_id
     WHERE e.ecole_admin_id=? ORDER BY e.created_at DESC",
    [$user['id']]
) ?? [];

include __DIR__ . '/includes/header_app.php';
?>

<style>
.exo-card { background:var(--blanc); border:1.5px solid var(--gris-200); border-radius:var(--radius-lg); padding:16px 18px; transition:all .2s; position:relative; overflow:hidden; }
.exo-card:hover { box-shadow:0 6px 24px rgba(0,0,0,.09); transform:translateY(-1px); }
.exo-card .top-bar { position:absolute; top:0; left:0; right:0; height:3px; }
.q-card { background:var(--gris-50); border:1.5px solid var(--gris-200); border-radius:12px; padding:14px; margin-bottom:10px; }
.q-badge { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:20px; font-size:10px; font-weight:800; text-transform:uppercase; }
.opt-row { display:flex; align-items:center; gap:8px; padding:6px 10px; border-radius:8px; font-size:12px; margin-bottom:4px; }
.opt-correct { background:#D1FAE5; color:#065F46; border:1px solid #86EFAC; }
.opt-wrong { background:var(--gris-100); color:var(--gris-600); }
</style>

<?php if ($exoEdit): ?>
<!-- ══ MODE ÉDITION ══════════════════════════════════════ -->
<div style="display:flex;align-items:center;gap:12px;margin-bottom:18px;flex-wrap:wrap">
  <a href="/reussiteplus/ecole_exercices.php" class="btn btn-ghost btn-sm">
    <i data-lucide="arrow-left" style="width:13px;height:13px;vertical-align:-2px"></i> Retour
  </a>
  <div style="flex:1">
    <div style="font-family:var(--font-display);font-size:18px;font-weight:900"><?= e($exoEdit['titre']) ?></div>
    <div style="font-size:12px;color:var(--gris-500)">
      <?= e($exoEdit['type']) ?> · <?= $exoEdit['duree_minutes'] ?> min ·
      <?= $exoEdit['classe_nom'] ? e($exoEdit['classe_nom']) : 'Toutes les classes' ?>
      · <?= count($questions) ?> question<?= count($questions)>1?'s':'' ?>
    </div>
  </div>
  <?php if ($questions): ?>
  <a href="/reussiteplus/passer_exercice.php?id=<?= urlencode($editId) ?>" target="_blank"
     class="btn btn-ghost btn-sm">
    <i data-lucide="play" style="width:12px;height:12px"></i> Aperçu
  </a>
  <?php endif; ?>
</div>

<div style="display:grid;grid-template-columns:1fr 360px;gap:18px;align-items:start">

  <!-- Questions existantes -->
  <div>
    <div style="font-family:var(--font-display);font-size:15px;font-weight:800;margin-bottom:12px;display:flex;align-items:center;gap:8px">
      <i data-lucide="list" style="width:16px;height:16px"></i> Questions (<?= count($questions) ?>)
    </div>

    <?php foreach ($questions as $i => $q):
      $optsTxt = $q['options_txt'] ? explode('|', $q['options_txt']) : [];
      $optsCorrect = $q['options_correct'] ? explode('|', $q['options_correct']) : [];
      $typeColor = match($q['type']) { 'VRAI_FAUX'=>'#059669', 'TEXTE_LIBRE'=>'#B45309', default=>'#1E5FAD' };
    ?>
    <div class="q-card">
      <div style="display:flex;align-items:flex-start;gap:10px">
        <div style="width:26px;height:26px;border-radius:8px;background:<?= $typeColor ?>20;color:<?= $typeColor ?>;font-family:var(--font-display);font-weight:900;font-size:13px;display:flex;align-items:center;justify-content:center;flex-shrink:0"><?= $i+1 ?></div>
        <div style="flex:1">
          <div style="display:flex;align-items:center;gap:7px;margin-bottom:6px;flex-wrap:wrap">
            <span class="q-badge" style="background:<?= $typeColor ?>20;color:<?= $typeColor ?>"><?= $q['type'] ?></span>
            <span style="font-size:11px;color:var(--gris-500)"><?= $q['points'] ?> pt<?= $q['points']>1?'s':'' ?></span>
          </div>
          <div style="font-size:14px;font-weight:700;color:var(--gris-900);margin-bottom:8px"><?= e($q['question']) ?></div>
          <?php foreach ($optsTxt as $oi => $opt): ?>
          <div class="opt-row <?= ($optsCorrect[$oi]??'0')==='1' ? 'opt-correct' : 'opt-wrong' ?>">
            <?= ($optsCorrect[$oi]??'0')==='1' ? '✓' : '○' ?> <?= e($opt) ?>
          </div>
          <?php endforeach; ?>
          <?php if ($q['explication']): ?>
          <div style="margin-top:7px;font-size:11px;color:#B45309;background:#FEF3C7;padding:6px 10px;border-radius:8px;border-left:3px solid #F59E0B">💡 <?= e($q['explication']) ?></div>
          <?php endif; ?>
        </div>
        <form method="POST" onsubmit="return confirm('Supprimer cette question ?')">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="supprimer_question">
          <input type="hidden" name="question_id" value="<?= e($q['id']) ?>">
          <input type="hidden" name="exercice_id" value="<?= e($editId) ?>">
          <button type="submit" class="btn btn-ghost btn-sm" style="color:#DC2626;border-color:#FECACA">
            <i data-lucide="trash-2" style="width:12px;height:12px"></i>
          </button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>

    <?php if (!$questions): ?>
    <div style="text-align:center;padding:40px;background:var(--gris-50);border:2px dashed var(--gris-200);border-radius:12px">
      <div style="font-size:40px;margin-bottom:12px">❓</div>
      <div style="font-size:14px;font-weight:700;color:var(--gris-600)">Aucune question encore</div>
      <div style="font-size:12px;color:var(--gris-400);margin-top:4px">Utilisez le formulaire pour ajouter vos premières questions.</div>
    </div>
    <?php endif; ?>

    <!-- Résultats élèves -->
    <?php if ($resultats): ?>
    <div style="margin-top:20px">
      <div style="font-family:var(--font-display);font-size:15px;font-weight:800;margin-bottom:12px">📊 Résultats des élèves</div>
      <div style="background:var(--blanc);border:1.5px solid var(--gris-200);border-radius:12px;overflow:hidden">
        <table style="width:100%;border-collapse:collapse">
          <thead><tr style="background:var(--gris-50)">
            <th style="padding:10px 14px;text-align:left;font-size:11px;text-transform:uppercase;color:var(--gris-500)">Élève</th>
            <th style="padding:10px 14px;text-align:center;font-size:11px;text-transform:uppercase;color:var(--gris-500)">Score</th>
            <th style="padding:10px 14px;text-align:center;font-size:11px;text-transform:uppercase;color:var(--gris-500)">Correct</th>
            <th style="padding:10px 14px;text-align:center;font-size:11px;text-transform:uppercase;color:var(--gris-500)">Durée</th>
            <th style="padding:10px 14px;text-align:center;font-size:11px;text-transform:uppercase;color:var(--gris-500)">Date</th>
          </tr></thead>
          <tbody>
          <?php foreach ($resultats as $r): ?>
          <tr style="border-top:1px solid var(--gris-100)">
            <td style="padding:10px 14px;font-size:13px;font-weight:700"><?= e(($r['prenom']??'').' '.strtoupper($r['nom']??'')) ?></td>
            <td style="padding:10px 14px;text-align:center">
              <?php $pct = $exoEdit['note_max'] > 0 ? ($r['score']/$exoEdit['note_max']*100) : 0; ?>
              <span style="font-family:var(--font-display);font-weight:900;font-size:14px;color:<?= $pct>=70?'#059669':($pct>=50?'#B45309':'#DC2626') ?>"><?= number_format($r['score'],1) ?></span>
              <span style="font-size:11px;color:var(--gris-400)">/ <?= $exoEdit['note_max'] ?></span>
            </td>
            <td style="padding:10px 14px;text-align:center;font-size:12px"><?= $r['nb_correct'] ?>/<?= $r['nb_total'] ?></td>
            <td style="padding:10px 14px;text-align:center;font-size:12px;color:var(--gris-500)"><?= $r['duree_secondes'] ? floor($r['duree_secondes']/60).'min '.($r['duree_secondes']%60).'s' : '—' ?></td>
            <td style="padding:10px 14px;text-align:center;font-size:11px;color:var(--gris-400)"><?= $r['termine_le'] ? date('d/m/Y H:i', strtotime($r['termine_le'])) : '—' ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Sidebar : ajouter question -->
  <div style="position:sticky;top:80px">
    <div style="background:var(--blanc);border:1.5px solid var(--gris-200);border-radius:var(--radius-lg);overflow:hidden">
      <div style="padding:14px 16px;border-bottom:1px solid var(--gris-100);background:var(--gris-50);font-family:var(--font-display);font-size:14px;font-weight:800;display:flex;align-items:center;gap:8px">
        <i data-lucide="plus-circle" style="width:16px;height:16px;stroke:#1E5FAD"></i> Ajouter une question
      </div>
      <div style="padding:16px">
        <form method="POST" id="form-question">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="ajouter_question">
          <input type="hidden" name="exercice_id" value="<?= e($editId) ?>">

          <div class="form-group">
            <label class="form-label">Type de question</label>
            <select name="type_q" class="form-control" id="type-q-sel" onchange="updateQType(this.value)">
              <option value="QCM">QCM – Choix multiple</option>
              <option value="VRAI_FAUX">Vrai / Faux</option>
              <option value="TEXTE_LIBRE">Réponse libre</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Énoncé de la question *</label>
            <textarea name="question" class="form-control" rows="3" required placeholder="Ex : Quelle est la capitale de la RDC ?"></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">Points</label>
            <input type="number" name="points" class="form-control" value="1" min="0.5" max="10" step="0.5">
          </div>

          <!-- Options QCM -->
          <div id="opts-qcm">
            <label class="form-label">Options de réponse (cochez la/les bonne(s))</label>
            <?php for ($i=0;$i<4;$i++): ?>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
              <input type="checkbox" name="option_correct[]" value="<?= $i ?>" style="accent-color:#059669;width:16px;height:16px;flex-shrink:0">
              <input type="text" name="option_texte[]" class="form-control" placeholder="Option <?= chr(65+$i) ?>" style="margin-bottom:0">
            </div>
            <?php endfor; ?>
            <div style="font-size:11px;color:var(--gris-400);margin-top:4px">Cochez la ou les réponses correctes</div>
          </div>

          <!-- Options VRAI/FAUX -->
          <div id="opts-vf" style="display:none">
            <label class="form-label">Réponse correcte</label>
            <div style="display:flex;gap:10px">
              <label style="display:flex;align-items:center;gap:6px;cursor:pointer;padding:8px 14px;border:1.5px solid var(--gris-200);border-radius:8px;flex:1;justify-content:center">
                <input type="radio" name="vf_correct" value="VRAI" checked style="accent-color:#059669"> ✓ VRAI
              </label>
              <label style="display:flex;align-items:center;gap:6px;cursor:pointer;padding:8px 14px;border:1.5px solid var(--gris-200);border-radius:8px;flex:1;justify-content:center">
                <input type="radio" name="vf_correct" value="FAUX" style="accent-color:#DC2626"> ✗ FAUX
              </label>
            </div>
          </div>

          <div class="form-group" style="margin-top:12px;margin-bottom:14px">
            <label class="form-label">Explication (optionnelle)</label>
            <textarea name="explication" class="form-control" rows="2" placeholder="Pourquoi cette réponse est correcte…"></textarea>
          </div>
          <button type="submit" class="btn btn-primary" style="width:100%;background:#1E5FAD;border-color:#1E5FAD">
            <i data-lucide="plus" style="width:13px;height:13px;stroke:#fff;vertical-align:-2px"></i> Ajouter la question
          </button>
        </form>
      </div>
    </div>

    <!-- Aperçu total points -->
    <div style="margin-top:12px;background:var(--gris-50);border:1px solid var(--gris-200);border-radius:12px;padding:12px 14px;font-size:12px">
      <div style="display:flex;justify-content:space-between;margin-bottom:4px">
        <span style="color:var(--gris-600)">Total questions :</span><strong><?= count($questions) ?></strong>
      </div>
      <div style="display:flex;justify-content:space-between;margin-bottom:4px">
        <span style="color:var(--gris-600)">Total points :</span>
        <strong><?= number_format(array_sum(array_column($questions, 'points')),1) ?> / <?= $exoEdit['note_max'] ?></strong>
      </div>
      <div style="display:flex;justify-content:space-between">
        <span style="color:var(--gris-600)">Durée :</span><strong><?= $exoEdit['duree_minutes'] ?> min</strong>
      </div>
    </div>
  </div>
</div>

<?php else: ?>
<!-- ══ LISTE DES EXERCICES ═══════════════════════════════ -->

<!-- Hero -->
<div style="background:linear-gradient(135deg,#0f172a,#3b0764 50%,#0f172a);border-radius:var(--radius-xl);padding:26px;margin-bottom:20px">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px">
    <div>
      <div style="font-family:var(--font-display);font-size:20px;font-weight:900;color:#fff;display:flex;align-items:center;gap:10px">
        🧠 Exercices & Questionnaires
      </div>
      <div style="font-size:12px;color:rgba(255,255,255,.45);margin-top:4px">Créez des exercices interactifs pour évaluer vos élèves</div>
    </div>
    <div style="display:flex;gap:10px;align-items:center">
      <div style="text-align:center;padding:8px 14px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:10px">
        <div style="font-family:var(--font-display);font-size:20px;font-weight:900;color:#fff"><?= count($exercices) ?></div>
        <div style="font-size:9px;color:rgba(255,255,255,.4);text-transform:uppercase">Exercices</div>
      </div>
      <button onclick="document.getElementById('modal-creer').style.display='flex'"
              style="background:linear-gradient(135deg,#7C3AED,#6D28D9);border:none;color:#fff;padding:11px 20px;border-radius:var(--radius);font-size:13px;font-weight:800;cursor:pointer;display:flex;align-items:center;gap:8px;box-shadow:0 4px 14px rgba(124,58,237,.5);transition:.2s">
        🧠 Créer un exercice
      </button>
    </div>
  </div>
</div>

<?php if ($exercices): ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px">
<?php foreach ($exercices as $exo):
  $typeColor = match($exo['type']) { 'QCM'=>'#1E5FAD','VRAI_FAUX'=>'#059669','MIXTE'=>'#7C3AED' };
  $typeIcon  = match($exo['type']) { 'QCM'=>'📋','VRAI_FAUX'=>'⚖️','MIXTE'=>'🧩' };
?>
<div class="exo-card">
  <div class="top-bar" style="background:<?= $typeColor ?>"></div>
  <div style="padding-top:10px">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:10px">
      <div>
        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:5px">
          <span style="background:<?= $typeColor ?>20;color:<?= $typeColor ?>;font-size:10px;font-weight:800;padding:2px 8px;border-radius:6px"><?= $typeIcon ?> <?= $exo['type'] ?></span>
          <?php if ($exo['classe_nom']): ?>
          <span style="background:var(--gris-100);color:var(--gris-600);font-size:10px;font-weight:700;padding:2px 8px;border-radius:6px"><?= e($exo['classe_nom']) ?></span>
          <?php endif; ?>
          <?php if (!$exo['actif']): ?>
          <span style="background:#FEE2E2;color:#DC2626;font-size:10px;font-weight:700;padding:2px 8px;border-radius:6px">Désactivé</span>
          <?php else: ?>
          <span style="background:#D1FAE5;color:#059669;font-size:10px;font-weight:700;padding:2px 8px;border-radius:6px">Actif</span>
          <?php endif; ?>
        </div>
        <div style="font-family:var(--font-display);font-size:15px;font-weight:900;margin-bottom:4px"><?= e($exo['titre']) ?></div>
        <?php if ($exo['description']): ?>
        <div style="font-size:12px;color:var(--gris-500);margin-bottom:6px"><?= e(mb_strimwidth($exo['description'],0,80,'…')) ?></div>
        <?php endif; ?>
        <div style="display:flex;gap:12px;font-size:11px;color:var(--gris-400)">
          <span>❓ <?= $exo['nb_questions'] ?> questions</span>
          <span>⏱ <?= $exo['duree_minutes'] ?> min</span>
          <span>👥 <?= $exo['nb_sessions'] ?> passages</span>
        </div>
      </div>
    </div>
    <div style="display:flex;gap:6px;flex-wrap:wrap">
      <a href="/reussiteplus/ecole_exercices.php?edit=<?= urlencode($exo['id']) ?>" class="btn btn-sm" style="flex:1;justify-content:center;background:<?= $typeColor ?>;color:#fff;border:none;font-weight:700;text-decoration:none;display:flex;align-items:center;gap:5px">
        <i data-lucide="edit-3" style="width:12px;height:12px;stroke:#fff"></i> Éditer
      </a>
      <?php if ($exo['actif'] && $exo['nb_questions'] > 0): ?>
      <a href="/reussiteplus/passer_exercice.php?id=<?= urlencode($exo['id']) ?>" target="_blank" class="btn btn-ghost btn-sm" title="Aperçu">
        <i data-lucide="play" style="width:12px;height:12px"></i>
      </a>
      <?php endif; ?>
      <form method="POST" style="display:inline">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="toggle_actif">
        <input type="hidden" name="exercice_id" value="<?= e($exo['id']) ?>">
        <button type="submit" class="btn btn-ghost btn-sm" title="<?= $exo['actif']?'Désactiver':'Activer' ?>">
          <i data-lucide="<?= $exo['actif']?'eye-off':'eye' ?>" style="width:12px;height:12px"></i>
        </button>
      </form>
      <form method="POST" onsubmit="return confirm('Supprimer cet exercice et toutes ses données ?')" style="display:inline">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="supprimer_exercice">
        <input type="hidden" name="exercice_id" value="<?= e($exo['id']) ?>">
        <button type="submit" class="btn btn-ghost btn-sm" style="color:#DC2626;border-color:#FECACA">
          <i data-lucide="trash-2" style="width:12px;height:12px"></i>
        </button>
      </form>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>

<?php else: ?>
<div class="card" style="text-align:center;padding:60px 30px">
  <div style="font-size:56px;margin-bottom:16px">🧠</div>
  <div style="font-family:var(--font-display);font-size:20px;font-weight:800;margin-bottom:8px">Aucun exercice créé</div>
  <p style="color:var(--gris-500);font-size:13px;max-width:400px;margin:0 auto 24px;line-height:1.6">
    Créez des QCM, Vrai/Faux et exercices mixtes pour évaluer vos élèves de façon interactive.
  </p>
  <button onclick="document.getElementById('modal-creer').style.display='flex'" class="btn btn-primary" style="background:#7C3AED;border-color:#7C3AED">
    🧠 Créer mon premier exercice
  </button>
</div>
<?php endif; ?>

<?php endif; ?>

<!-- ══ MODAL Créer exercice ═══════════════════════════════ -->
<div id="modal-creer" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);align-items:center;justify-content:center;z-index:1000;padding:20px;backdrop-filter:blur(5px)" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--blanc);border-radius:22px;width:100%;max-width:500px;max-height:90vh;overflow-y:auto">
    <div style="padding:20px 24px 16px;border-bottom:1px solid var(--gris-100);position:sticky;top:0;background:var(--blanc);z-index:2;display:flex;align-items:center;justify-content:space-between">
      <span style="font-family:var(--font-display);font-size:17px;font-weight:900">🧠 Créer un exercice</span>
      <button onclick="document.getElementById('modal-creer').style.display='none'" style="background:none;border:none;cursor:pointer"><i data-lucide="x" style="width:20px;height:20px;stroke:var(--gris-400)"></i></button>
    </div>
    <div style="padding:20px 24px">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="creer_exercice">
        <div class="form-group">
          <label class="form-label">Titre de l'exercice *</label>
          <input type="text" name="titre" class="form-control" required placeholder="Ex : QCM Histoire du Congo – Chapitre 3">
        </div>
        <div class="form-group">
          <label class="form-label">Description (optionnel)</label>
          <textarea name="description" class="form-control" rows="2" placeholder="Instructions pour les élèves…"></textarea>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group">
            <label class="form-label">Type</label>
            <select name="type" class="form-control">
              <option value="QCM">📋 QCM</option>
              <option value="VRAI_FAUX">⚖️ Vrai / Faux</option>
              <option value="MIXTE">🧩 Mixte</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Classe</label>
            <select name="classe_id" class="form-control">
              <option value="">Toutes les classes</option>
              <?php foreach ($classes as $cl): ?>
              <option value="<?= e($cl['id']) ?>"><?= e($cl['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group">
            <label class="form-label">Durée (minutes)</label>
            <input type="number" name="duree_minutes" class="form-control" value="30" min="5" max="180">
          </div>
          <div class="form-group">
            <label class="form-label">Note maximale</label>
            <input type="number" name="note_max" class="form-control" value="20" min="5" max="100">
          </div>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;padding:14px;font-size:15px;background:#7C3AED;border-color:#7C3AED">
          Créer et ajouter les questions →
        </button>
      </form>
    </div>
  </div>
</div>

<script>
function updateQType(val) {
  document.getElementById('opts-qcm').style.display = val === 'QCM' ? '' : 'none';
  document.getElementById('opts-vf').style.display  = val === 'VRAI_FAUX' ? '' : 'none';
}
</script>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
