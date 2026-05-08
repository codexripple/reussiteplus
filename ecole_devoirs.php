<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Devoirs & Évaluations';
$pageActive = 'ecole_devoirs';
$user = require_login();
if ($user['plan'] !== 'ECOLE') redirect('/reussiteplus/tarifs.php');

$filtreClasse = $_GET['classe'] ?? '';
$filtreStatut = $_GET['statut'] ?? '';

// ── Actions ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { http_response_code(403); exit; }
    $action = $_POST['action'] ?? '';

    if ($action === 'creer_devoir') {
        $titre      = trim($_POST['titre']      ?? '');
        $desc       = trim($_POST['description'] ?? '');
        $classeId   = $_POST['classe_id']        ?? null;
        $matiere    = trim($_POST['matiere']     ?? '');
        $type       = $_POST['type']             ?? 'DEVOIR';
        $dateRemise = $_POST['date_limite']      ?? null;
        $points     = (int)($_POST['nb_questions'] ?? 20);
        if ($titre && $classeId) {
            $validTypes = ['ENTRAINEMENT','EXAMEN','CONTROLE','DEVOIR'];
            $type = in_array($type, $validTypes) ? $type : 'ENTRAINEMENT';
            dbRun("INSERT INTO devoirs_ecole (admin_id, classe_id, titre, type_examen, date_limite, nb_questions, actif)
                   VALUES (?,?,?,?,?,?,1)",
                [$user['id'], $classeId, $titre, $type, $dateRemise ?: null, $points]);
            redirect('/reussiteplus/ecole_devoirs.php', 'success', "Devoir « $titre » créé.");
        }
    }

    if ($action === 'supprimer_devoir') {
        $id = $_POST['devoir_id'] ?? '';
        dbRun("UPDATE devoirs_ecole SET actif=0 WHERE id=? AND admin_id=?", [$id, $user['id']]);
        redirect('/reussiteplus/ecole_devoirs.php', 'success', 'Devoir archivé.');
    }
    exit;
}

// ── Données ───────────────────────────────────────────────────
$classes = dbAll("SELECT id, nom FROM classes_ecole WHERE admin_id=? AND actif=1 ORDER BY nom", [$user['id']]) ?? [];

$whereExtra = ''; $params = [$user['id']];
if ($filtreClasse) { $whereExtra .= ' AND d.classe_id=?'; $params[] = $filtreClasse; }
if ($filtreStatut === 'actif')  { $whereExtra .= ' AND (d.date_limite IS NULL OR d.date_limite >= CURDATE())'; }
if ($filtreStatut === 'expire') { $whereExtra .= ' AND d.date_limite < CURDATE()'; }

$devoirs = dbAll(
    "SELECT d.*, c.nom as classe_nom,
            COUNT(DISTINCT cm.eleve_id) as nb_eleves,
            COUNT(DISTINCT sd.id) as nb_soumissions,
            COUNT(DISTINCT CASE WHEN sd.statut='CORRIGE' THEN sd.id END) as nb_corriges,
            COALESCE(ROUND(AVG(sd.note),1), 0) as note_moyenne
     FROM devoirs_ecole d
     JOIN classes_ecole c ON c.id=d.classe_id
     LEFT JOIN classe_membres cm ON cm.classe_id=d.classe_id AND cm.statut='ACTIF'
     LEFT JOIN soumissions_devoirs sd ON sd.devoir_id=d.id
     WHERE d.admin_id=? AND d.actif=1 $whereExtra
     GROUP BY d.id
     ORDER BY d.date_limite IS NULL, d.date_limite ASC, d.created_at DESC",
    $params
) ?? [];

$nbActifs  = (int)dbScalar("SELECT COUNT(*) FROM devoirs_ecole WHERE admin_id=? AND actif=1 AND (date_limite IS NULL OR date_limite >= CURDATE())", [$user['id']]);
$nbExpires = (int)dbScalar("SELECT COUNT(*) FROM devoirs_ecole WHERE admin_id=? AND actif=1 AND date_limite < CURDATE()", [$user['id']]);
$nbTotal   = (int)dbScalar("SELECT COUNT(*) FROM devoirs_ecole WHERE admin_id=? AND actif=1", [$user['id']]);

$typeConfig = [
    'DEVOIR'   => ['label'=>'Devoir',    'color'=>'#2563EB','bg'=>'#DBEAFE','icon'=>'file-text'],
    'CONTROLE' => ['label'=>'Contrôle',  'color'=>'#7C3AED','bg'=>'#EDE9FE','icon'=>'clipboard-check'],
    'EXAM'     => ['label'=>'Examen',    'color'=>'#DC2626','bg'=>'#FEE2E2','icon'=>'graduation-cap'],
    'PROJET'   => ['label'=>'Projet',    'color'=>'#059669','bg'=>'#D1FAE5','icon'=>'briefcase'],
    'EXPOSE'   => ['label'=>'Exposé',    'color'=>'#D97706','bg'=>'#FEF3C7','icon'=>'presentation'],
];

include __DIR__ . '/includes/header_app.php';
?>

<style>
.dv-hero { background:linear-gradient(135deg,#1a1035,#4f46e5 55%,#0f172a); border-radius:var(--radius-xl); padding:26px; margin-bottom:20px; }
.dv-stat { text-align:center; padding:10px 16px; border-radius:12px; border:1px solid rgba(255,255,255,.12); background:rgba(255,255,255,.08); }
.dv-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:14px; }
.dv-card { background:var(--blanc); border:1.5px solid var(--gris-200); border-radius:var(--radius-lg); overflow:hidden; transition:all .2s; }
.dv-card:hover { box-shadow:0 6px 24px rgba(0,0,0,.1); transform:translateY(-2px); }
.dv-card-bar { height:4px; }
.dv-card-body { padding:14px 16px; }
.dv-card-footer { padding:10px 16px; background:var(--gris-50); border-top:1px solid var(--gris-100); display:flex; align-items:center; justify-content:space-between; }
.deadline-badge { display:inline-flex; align-items:center; gap:4px; padding:3px 8px; border-radius:8px; font-size:11px; font-weight:700; }
.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.55); display:flex; align-items:center; justify-content:center; z-index:1000; padding:20px; backdrop-filter:blur(5px); }
.modal-box { background:var(--blanc); border-radius:20px; width:100%; max-width:500px; max-height:90vh; overflow-y:auto; }
.modal-head { padding:20px 24px 16px; border-bottom:1px solid var(--gris-100); display:flex; align-items:center; justify-content:space-between; }
.modal-body { padding:20px 24px; }
</style>

<!-- Hero -->
<div class="dv-hero">
  <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:16px">
    <div>
      <div style="font-size:11px;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px">
        <a href="/reussiteplus/ecole.php" style="color:rgba(255,255,255,.35);text-decoration:none">Mon École</a> / Devoirs
      </div>
      <div style="font-family:var(--font-display);font-size:22px;font-weight:900;color:#fff;margin-bottom:4px">Devoirs & Évaluations</div>
      <div style="font-size:12px;color:rgba(255,255,255,.45)">Créez et suivez les travaux assignés à vos classes</div>
    </div>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <div class="dv-stat"><div style="font-family:var(--font-display);font-size:20px;font-weight:900;color:#fff"><?= $nbTotal ?></div><div style="font-size:10px;color:rgba(255,255,255,.4);text-transform:uppercase">Total</div></div>
      <div class="dv-stat"><div style="font-family:var(--font-display);font-size:20px;font-weight:900;color:#86EFAC"><?= $nbActifs ?></div><div style="font-size:10px;color:rgba(255,255,255,.4);text-transform:uppercase">En cours</div></div>
      <div class="dv-stat"><div style="font-family:var(--font-display);font-size:20px;font-weight:900;color:#FCA5A5"><?= $nbExpires ?></div><div style="font-size:10px;color:rgba(255,255,255,.4);text-transform:uppercase">Expirés</div></div>
      <button onclick="document.getElementById('modal-creer').style.display='flex'"
              style="background:#4f46e5;border:1.5px solid #6366f1;color:#fff;padding:10px 18px;border-radius:var(--radius);font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:7px;transition:.15s"
              onmouseover="this.style.background='#4338ca'" onmouseout="this.style.background='#4f46e5'">
        <i data-lucide="plus" style="width:15px;height:15px;stroke:#fff"></i> Nouveau devoir
      </button>
    </div>
  </div>
</div>

<!-- Filtres -->
<div style="display:flex;gap:8px;margin-bottom:18px;flex-wrap:wrap;align-items:center">
  <?php foreach ($classes as $cl): ?>
  <a href="/reussiteplus/ecole_devoirs.php?classe=<?= urlencode($cl['id']) ?><?= $filtreStatut?'&statut='.urlencode($filtreStatut):'' ?>"
     style="padding:6px 13px;border-radius:20px;font-size:12px;font-weight:700;text-decoration:none;transition:.15s;<?= $filtreClasse===$cl['id']?'background:#4f46e5;color:#fff':'background:var(--blanc);color:var(--gris-600);border:1.5px solid var(--gris-200)' ?>">
    <?= e($cl['nom']) ?>
  </a>
  <?php endforeach; ?>
  <div style="margin-left:auto;display:flex;gap:6px">
    <?php foreach ([''=>'Tous','actif'=>'En cours','expire'=>'Expirés'] as $k=>$lbl): ?>
    <a href="/reussiteplus/ecole_devoirs.php<?= $filtreClasse?'?classe='.urlencode($filtreClasse).'&':'?' ?>statut=<?= $k ?>"
       style="padding:6px 12px;border-radius:20px;font-size:11px;font-weight:700;text-decoration:none;transition:.15s;<?= $filtreStatut===$k?'background:#0f172a;color:#fff':'background:var(--gris-100);color:var(--gris-600)' ?>">
      <?= $lbl ?>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<?php if ($devoirs): ?>
<div class="dv-grid">
  <?php foreach ($devoirs as $dv): ?>
  <?php
    $tc       = $typeConfig[$dv['type_examen']] ?? $typeConfig['DEVOIR'];
    $expired  = $dv['date_limite'] && $dv['date_limite'] < date('Y-m-d');
    $urgence  = $dv['date_limite'] && !$expired && (strtotime($dv['date_limite']) - time()) < 86400*3;
    $daysLeft = $dv['date_limite'] ? ceil((strtotime($dv['date_limite']) - time()) / 86400) : null;
  ?>
  <div class="dv-card">
    <div class="dv-card-bar" style="background:<?= $expired?'#D1D5DB':$tc['color'] ?>"></div>
    <div class="dv-card-body">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:10px">
        <div style="display:flex;align-items:center;gap:9px">
          <div style="width:38px;height:38px;border-radius:10px;background:<?= $tc['bg'] ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i data-lucide="<?= $tc['icon'] ?>" style="width:18px;height:18px;stroke:<?= $tc['color'] ?>"></i>
          </div>
          <div>
            <span style="background:<?= $tc['bg'] ?>;color:<?= $tc['color'] ?>;font-size:10px;font-weight:700;padding:2px 7px;border-radius:6px"><?= $tc['label'] ?></span>
            <?php if ($expired): ?>
            <span style="background:#F3F4F6;color:#6B7280;font-size:10px;font-weight:700;padding:2px 7px;border-radius:6px;margin-left:4px">Expiré</span>
            <?php elseif ($urgence): ?>
            <span style="background:#FEF3C7;color:#D97706;font-size:10px;font-weight:700;padding:2px 7px;border-radius:6px;margin-left:4px">⚡ Urgent</span>
            <?php endif; ?>
          </div>
        </div>
        <form method="POST" onsubmit="return confirm('Archiver ce devoir ?')" style="flex-shrink:0">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="supprimer_devoir">
          <input type="hidden" name="devoir_id" value="<?= e($dv['id']) ?>">
          <button type="submit" style="background:none;border:none;cursor:pointer;color:var(--gris-300);padding:3px;transition:.15s" onmouseover="this.style.color='#DC2626'" onmouseout="this.style.color='var(--gris-300)'">
            <i data-lucide="trash-2" style="width:14px;height:14px"></i>
          </button>
        </form>
      </div>
      <div style="font-family:var(--font-display);font-size:14px;font-weight:800;color:var(--gris-900);margin-bottom:4px;line-height:1.3"><?= e($dv['titre']) ?></div>
      <?php if ($dv['description']): ?>
      <div style="font-size:12px;color:var(--gris-500);margin-bottom:8px;line-height:1.5"><?= e(mb_substr($dv['description'],0,90)) ?><?= mb_strlen($dv['description'])>90?'…':'' ?></div>
      <?php endif; ?>
      <div style="display:flex;flex-wrap:wrap;gap:5px">
        <span style="background:#EDE9FE;color:#7C3AED;font-size:10px;font-weight:700;padding:2px 8px;border-radius:8px"><?= e($dv['classe_nom']) ?></span>
        <?php if ($dv['matiere']): ?>
        <span style="background:var(--primary-subtle);color:var(--primary);font-size:10px;font-weight:700;padding:2px 8px;border-radius:8px"><?= e($dv['matiere']) ?></span>
        <?php endif; ?>
        <span style="background:var(--gris-100);color:var(--gris-600);font-size:10px;font-weight:700;padding:2px 8px;border-radius:8px"><?= $dv['nb_questions'] ?> pts</span>
      </div>
    </div>
    <?php if ($dv['nb_eleves'] > 0): ?>
    <div style="padding:0 16px 12px">
      <?php $tauxSoumission = $dv['nb_eleves'] > 0 ? round($dv['nb_soumissions'] / $dv['nb_eleves'] * 100) : 0; ?>
      <div style="display:flex;justify-content:space-between;font-size:10.5px;color:var(--gris-500);margin-bottom:4px">
        <span><?= $dv['nb_soumissions'] ?>/<?= $dv['nb_eleves'] ?> soumissions</span>
        <span style="font-weight:700;color:<?= $tauxSoumission>=75?'#007A5E':($tauxSoumission>=40?'#C9972A':'#C9342A') ?>"><?= $tauxSoumission ?>%</span>
      </div>
      <div style="height:4px;background:var(--gris-100);border-radius:99px;overflow:hidden">
        <div style="height:100%;width:<?= $tauxSoumission ?>%;background:<?= $tauxSoumission>=75?'#007A5E':($tauxSoumission>=40?'#C9972A':'#C9342A') ?>;border-radius:99px;transition:width .4s"></div>
      </div>
      <?php if ($dv['nb_corriges'] > 0): ?>
      <div style="font-size:10px;color:var(--gris-400);margin-top:3px"><?= $dv['nb_corriges'] ?> corrigé<?= $dv['nb_corriges']>1?'s':'' ?><?= $dv['note_moyenne'] > 0 ? ' · moy. '.$dv['note_moyenne'].'/20' : '' ?></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="dv-card-footer">
      <div style="display:flex;align-items:center;gap:5px;font-size:11px;color:var(--gris-500)">
        <i data-lucide="users" style="width:11px;height:11px"></i>
        <?= $dv['nb_eleves'] ?> élève<?= $dv['nb_eleves']!=1?'s':'' ?>
      </div>
      <?php if ($dv['date_limite']): ?>
      <div class="deadline-badge" style="background:<?= $expired?'#F3F4F6':($urgence?'#FEF3C7':'#DCFCE7') ?>;color:<?= $expired?'#6B7280':($urgence?'#D97706':'#059669') ?>">
        <i data-lucide="calendar" style="width:10px;height:10px"></i>
        <?php if ($expired): ?>
          Rendu le <?= date('d/m', strtotime($dv['date_limite'])) ?>
        <?php elseif ($daysLeft <= 0): ?>
          Aujourd'hui
        <?php else: ?>
          J–<?= $daysLeft ?> · <?= date('d/m', strtotime($dv['date_limite'])) ?>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <span style="font-size:11px;color:var(--gris-400)">Pas de date limite</span>
      <?php endif; ?>
      <!-- Bouton Corriger -->
      <?php if ($dv['nb_soumissions'] > 0): ?>
      <a href="/reussiteplus/ecole_correction.php?devoir=<?= urlencode($dv['id']) ?>"
         style="background:var(--primary);color:#fff;border-radius:8px;padding:5px 11px;font-size:11px;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:4px;transition:.15s"
         onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
        Corriger (<?= $dv['nb_soumissions'] ?>)
      </a>
      <?php endif; ?>
      <!-- Bouton feedback IA -->
      <?php if ($dv['nb_soumissions'] > 0): ?>
      <button onclick="genFeedbackIA(<?= htmlspecialchars(json_encode([
          'titre'      => $dv['titre'],
          'matiere'    => $dv['matiere'] ?? '',
          'type'       => $dv['type_examen'] ?? 'DEVOIR',
          'nb_questions' => $dv['nb_questions'] ?? 20,
          'note'       => $dv['note_moyenne'] ?? 0,
      ]), ENT_QUOTES) ?>)"
        style="background:linear-gradient(135deg,#7C3AED,#4F46E5);color:#fff;border:none;border-radius:8px;padding:4px 10px;font-size:11px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:4px;transition:.15s;font-family:inherit"
        onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
        Feedback IA
      </button>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div class="card" style="text-align:center;padding:60px 30px">
  <div style="width:72px;height:72px;background:#EDE9FE;border-radius:20px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
    <i data-lucide="file-text" style="width:32px;height:32px;stroke:#4f46e5"></i>
  </div>
  <div style="font-family:var(--font-display);font-size:18px;font-weight:800;margin-bottom:8px">Aucun devoir pour l'instant</div>
  <p style="color:var(--gris-500);font-size:13px;max-width:360px;margin:0 auto 20px">Créez votre premier devoir, contrôle ou examen et assignez-le à une classe.</p>
  <button onclick="document.getElementById('modal-creer').style.display='flex'" class="btn btn-primary" style="background:#4f46e5;border-color:#4f46e5">
    <i data-lucide="plus" style="width:14px;height:14px;vertical-align:-2px"></i> Créer un devoir
  </button>
</div>
<?php endif; ?>

<!-- ══ MODAL Créer devoir ════════════════════════════════════ -->
<div id="modal-creer" class="modal-overlay" style="display:none" onclick="if(event.target===this)this.style.display='none'">
  <div class="modal-box">
    <div class="modal-head">
      <span style="font-family:var(--font-display);font-size:16px;font-weight:800;display:flex;align-items:center;gap:8px">
        <i data-lucide="file-plus" style="width:16px;height:16px;stroke:#4f46e5"></i> Nouveau devoir / évaluation
      </span>
      <button onclick="document.getElementById('modal-creer').style.display='none'" style="background:none;border:none;cursor:pointer"><i data-lucide="x" style="width:18px;height:18px;stroke:var(--gris-400)"></i></button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="creer_devoir">
        <div class="form-group">
          <label class="form-label">Titre *</label>
          <input type="text" name="titre" class="form-control" required placeholder="Ex : Contrôle de mathématiques — Algèbre">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group">
            <label class="form-label">Type</label>
            <select name="type" class="form-control">
              <?php foreach ($typeConfig as $k => $tc): ?>
              <option value="<?= $k ?>"><?= $tc['label'] ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Classe *</label>
            <select name="classe_id" class="form-control" required>
              <option value="">-- Sélectionner --</option>
              <?php foreach ($classes as $cl): ?>
              <option value="<?= e($cl['id']) ?>" <?= $filtreClasse===$cl['id']?'selected':'' ?>><?= e($cl['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group">
            <label class="form-label">Matière</label>
            <input type="text" name="matiere" class="form-control" placeholder="Ex : Mathématiques">
          </div>
          <div class="form-group">
            <label class="form-label">Date de remise</label>
            <input type="date" name="date_limite" class="form-control" min="<?= date('Y-m-d') ?>">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Points maximum</label>
          <input type="number" name="nb_questions" class="form-control" value="20" min="1" max="200">
        </div>
        <div class="form-group">
          <label class="form-label">Description / consignes</label>
          <textarea name="description" class="form-control" rows="3" placeholder="Instructions pour les élèves…"></textarea>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;background:#4f46e5;border-color:#4f46e5;padding:13px">
          <i data-lucide="check" style="width:14px;height:14px;vertical-align:-2px"></i> Créer le devoir
        </button>
      </form>
    </div>
  </div>
</div>

<!-- ══ MODALE Feedback IA ══════════════════════════════════════ -->
<div id="modal-ia-feedback" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);z-index:200;align-items:center;justify-content:center" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:linear-gradient(160deg,#0d1120,#111827);border:1px solid rgba(124,58,237,.25);border-radius:18px;padding:24px;width:100%;max-width:500px;margin:20px;box-shadow:0 24px 64px rgba(0,0,0,.5)">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px">
      <div style="display:flex;align-items:center;gap:8px">
        <div style="width:32px;height:32px;border-radius:9px;background:linear-gradient(135deg,#7C3AED,#4F46E5);display:flex;align-items:center;justify-content:center">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
        </div>
        <span style="font-size:14px;font-weight:800;color:#fff">Feedback IA — Gemini</span>
      </div>
      <button onclick="document.getElementById('modal-ia-feedback').style.display='none'" style="background:none;border:none;cursor:pointer;color:rgba(255,255,255,.4);font-size:20px">×</button>
    </div>
    <div id="ia-feedback-content" style="min-height:80px">
      <div id="ia-feedback-loading" style="text-align:center;padding:24px;color:rgba(255,255,255,.5)">
        <svg style="animation:iaSpin .7s linear infinite;display:inline-block;margin-right:8px" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#a78bfa" stroke-width="2.5"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
        Génération du feedback en cours…
      </div>
      <div id="ia-feedback-text" style="display:none;font-size:13.5px;line-height:1.75;color:rgba(255,255,255,.85);background:rgba(124,58,237,.12);border:1px solid rgba(124,58,237,.2);border-radius:10px;padding:14px 16px"></div>
      <div id="ia-feedback-error" style="display:none;font-size:13px;color:#fca5a5;text-align:center;padding:16px"></div>
    </div>
    <div id="ia-feedback-actions" style="display:none;margin-top:14px;display:flex;gap:8px;justify-content:flex-end">
      <button id="ia-copy-btn" onclick="copyFeedback()" style="background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);color:rgba(255,255,255,.8);border-radius:8px;padding:7px 14px;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit">
        Copier
      </button>
      <button onclick="document.getElementById('modal-ia-feedback').style.display='none'" style="background:linear-gradient(135deg,#7C3AED,#4F46E5);border:none;color:#fff;border-radius:8px;padding:7px 14px;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit">
        Fermer
      </button>
    </div>
  </div>
</div>

<style>@keyframes iaSpin { to { transform: rotate(360deg); } }</style>

<script>
let lastFeedbackText = '';

async function genFeedbackIA(dv) {
  const modal   = document.getElementById('modal-ia-feedback');
  const loading = document.getElementById('ia-feedback-loading');
  const textEl  = document.getElementById('ia-feedback-text');
  const errorEl = document.getElementById('ia-feedback-error');
  const actions = document.getElementById('ia-feedback-actions');

  modal.style.display = 'flex';
  loading.style.display = 'block';
  textEl.style.display  = 'none';
  errorEl.style.display = 'none';
  actions.style.display = 'none';

  try {
    const resp = await fetch('/reussiteplus/api/ia_devoir_feedback.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(dv),
    });
    const data = await resp.json();
    loading.style.display = 'none';
    if (data.ok && data.feedback) {
      lastFeedbackText = data.feedback;
      textEl.textContent = data.feedback;
      textEl.style.display = 'block';
      actions.style.display = 'flex';
    } else {
      errorEl.textContent = data.msg || 'Erreur lors de la génération.';
      errorEl.style.display = 'block';
    }
  } catch(e) {
    loading.style.display = 'none';
    errorEl.textContent = 'Erreur de connexion. Vérifiez votre connexion.';
    errorEl.style.display = 'block';
  }
}

function copyFeedback() {
  navigator.clipboard?.writeText(lastFeedbackText).then(() => {
    const btn = document.getElementById('ia-copy-btn');
    btn.textContent = 'Copié !';
    btn.style.color = '#4ade80';
    setTimeout(() => { btn.textContent = 'Copier'; btn.style.color = 'rgba(255,255,255,.8)'; }, 2000);
  });
}
</script>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
