<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Gestion des classes';
$pageActive = 'ecole_classes';
$user = require_login();
if ($user['plan'] !== 'ECOLE') redirect('/reussiteplus/tarifs.php');

// ── Actions ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { http_response_code(403); exit; }
    $action = $_POST['action'] ?? '';

    if ($action === 'creer_classe') {
        $nom    = trim($_POST['nom']    ?? '');
        $niveau = trim($_POST['niveau'] ?? '');
        $descr  = trim($_POST['description'] ?? '');
        $plan   = PLANS['ECOLE'];
        $count  = dbVal("SELECT COUNT(*) FROM classes_ecole WHERE admin_id=? AND actif=1", [$user['id']]);
        $max    = (int)($plan['classes_max'] ?? 5);
        if ($count >= $max) redirect('/reussiteplus/ecole_classes.php', 'error', "Limite de $max classes atteinte.");
        if ($nom) {
            $code = strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($nom)), 0, 4)) . rand(100, 999);
            dbRun("INSERT INTO classes_ecole (admin_id, nom, niveau, description, code_invitation, actif) VALUES (?,?,?,?,?,1)",
                [$user['id'], $nom, $niveau ?: null, $descr ?: null, $code]);
            redirect('/reussiteplus/ecole_classes.php', 'success', "Classe « $nom » créée.");
        }
    }

    if ($action === 'supprimer_classe') {
        $id = $_POST['classe_id'] ?? '';
        dbRun("UPDATE classes_ecole SET actif=0 WHERE id=? AND admin_id=?", [$id, $user['id']]);
        redirect('/reussiteplus/ecole_classes.php', 'success', 'Classe archivée.');
    }

    if ($action === 'regenerer_code') {
        $id   = $_POST['classe_id'] ?? '';
        $code = strtoupper(substr(md5(uniqid()), 0, 8));
        dbRun("UPDATE classes_ecole SET code_invitation=? WHERE id=? AND admin_id=?", [$code, $id, $user['id']]);
        redirect('/reussiteplus/ecole_classes.php', 'success', 'Code invitation régénéré.');
    }

    if ($action === 'retirer_eleve') {
        $classeId = $_POST['classe_id'] ?? '';
        $eleveId  = $_POST['eleve_id']  ?? '';
        // Vérifier que la classe appartient à cet admin
        $c = dbRow("SELECT id FROM classes_ecole WHERE id=? AND admin_id=?", [$classeId, $user['id']]);
        if ($c) dbRun("DELETE FROM classe_membres WHERE classe_id=? AND eleve_id=?", [$classeId, $eleveId]);
        redirect('/reussiteplus/ecole_classes.php?vue=' . urlencode($classeId), 'success', 'Élève retiré.');
    }
    exit;
}

// ── Données ───────────────────────────────────────────────────
$classeVue = $_GET['vue'] ?? '';
$classes = dbAll(
    "SELECT c.*, COUNT(DISTINCT cm.eleve_id) as nb_eleves
     FROM classes_ecole c
     LEFT JOIN classe_membres cm ON cm.classe_id=c.id
     WHERE c.admin_id=? AND c.actif=1
     GROUP BY c.id ORDER BY c.nom",
    [$user['id']]
) ?? [];

$classeActive = null;
$eleves = [];
if ($classeVue) {
    foreach ($classes as $cl) { if ($cl['id'] === $classeVue) { $classeActive = $cl; break; } }
}
if (!$classeActive && $classes) { $classeActive = $classes[0]; $classeVue = $classeActive['id']; }

if ($classeActive) {
    $eleves = dbAll(
        "SELECT u.id, u.nom, u.prenom, u.email, u.avatar_url, cm.created_at as rejoint_le,
                COUNT(DISTINCT es.id) as nb_examens,
                COALESCE(ROUND(AVG(er.score_pct),1), 0) as score_moyen
         FROM classe_membres cm
         JOIN utilisateurs u ON u.id=cm.eleve_id
         LEFT JOIN exam_sessions es ON es.user_id=u.id AND es.statut='COMPLETE'
         LEFT JOIN exam_results er ON er.session_id=es.id
         WHERE cm.classe_id=?
         GROUP BY u.id
         ORDER BY score_moyen DESC",
        [$classeVue]
    ) ?? [];
}

$planInfo = PLANS['ECOLE'];
$maxClasses = (int)($planInfo['classes_max'] ?? 5);

include __DIR__ . '/includes/header_app.php';
?>

<style>
.classes-layout { display:grid; grid-template-columns:260px 1fr; gap:20px; align-items:start; }
@media(max-width:768px){ .classes-layout{grid-template-columns:1fr} }
.classe-list-card { background:var(--blanc); border:1.5px solid var(--gris-200); border-radius:var(--radius-lg); overflow:hidden; }
.classe-list-item { display:flex; align-items:center; gap:10px; padding:12px 14px; cursor:pointer; border-bottom:1px solid var(--gris-100); transition:.15s; text-decoration:none; }
.classe-list-item:last-child { border-bottom:none; }
.classe-list-item:hover { background:var(--gris-50); }
.classe-list-item.active { background:var(--primary-subtle); border-left:3px solid var(--primary); }
.classe-avatar { width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-family:var(--font-display); font-size:13px; font-weight:900; flex-shrink:0; }
.eleve-row { display:flex; align-items:center; gap:12px; padding:12px 0; border-bottom:1px solid var(--gris-100); }
.eleve-row:last-child { border-bottom:none; }
.score-bar-w { flex:1; max-width:120px; height:6px; background:var(--gris-200); border-radius:3px; overflow:hidden; }
.score-bar-f { height:100%; border-radius:3px; transition:width .6s ease; }
</style>

<!-- Hero compact -->
<div style="background:linear-gradient(135deg,#1e3a5f,#2563EB 55%,#1e1b4b);border-radius:var(--radius-xl);padding:24px;margin-bottom:20px">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
    <div>
      <div style="font-size:11px;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px">
        <a href="/reussiteplus/ecole.php" style="color:rgba(255,255,255,.4);text-decoration:none">Mon École</a> / Classes
      </div>
      <div style="font-family:var(--font-display);font-size:20px;font-weight:900;color:#fff"><?= count($classes) ?> / <?= $maxClasses ?> classes actives</div>
      <div style="font-size:12px;color:rgba(255,255,255,.5);margin-top:3px">Gérez vos classes, codes d'invitation et élèves</div>
    </div>
    <?php if (count($classes) < $maxClasses): ?>
    <button onclick="document.getElementById('modal-creer').style.display='flex'"
            style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);color:#fff;padding:10px 18px;border-radius:var(--radius);font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:7px;transition:.15s"
            onmouseover="this.style.background='rgba(255,255,255,.25)'" onmouseout="this.style.background='rgba(255,255,255,.15)'">
      <i data-lucide="plus" style="width:15px;height:15px;stroke:#fff"></i> Nouvelle classe
    </button>
    <?php else: ?>
    <span style="background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);color:#FCA5A5;padding:8px 14px;border-radius:var(--radius);font-size:12px;font-weight:700">
      Limite atteinte (<?= $maxClasses ?>)
    </span>
    <?php endif; ?>
  </div>
</div>

<?php if (empty($classes)): ?>
<div class="card" style="text-align:center;padding:60px 30px">
  <div style="width:72px;height:72px;background:#DBEAFE;border-radius:20px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
    <i data-lucide="layout-list" style="width:32px;height:32px;stroke:#2563EB"></i>
  </div>
  <div style="font-family:var(--font-display);font-size:20px;font-weight:800;margin-bottom:8px">Aucune classe</div>
  <p style="color:var(--gris-500);max-width:380px;margin:0 auto 20px;font-size:14px">Créez votre première classe pour inviter des élèves et commencer à enseigner.</p>
  <button onclick="document.getElementById('modal-creer').style.display='flex'" class="btn btn-primary">
    <i data-lucide="plus" style="width:14px;height:14px;vertical-align:-2px"></i> Créer une classe
  </button>
</div>
<?php else: ?>

<div class="classes-layout">
  <!-- ── Sidebar classes ── -->
  <div>
    <div class="classe-list-card">
      <div style="padding:12px 14px;border-bottom:1px solid var(--gris-100);font-size:11px;font-weight:700;color:var(--gris-500);text-transform:uppercase;letter-spacing:.5px">Mes classes</div>
      <?php $colors = ['#2563EB','#7C3AED','#059669','#D97706','#DC2626']; $ci=0; ?>
      <?php foreach ($classes as $cl): ?>
      <?php $col = $colors[$ci++ % count($colors)]; ?>
      <a href="/reussiteplus/ecole_classes.php?vue=<?= urlencode($cl['id']) ?>" 
         class="classe-list-item <?= $classeActive && $classeActive['id']===$cl['id']?'active':'' ?>">
        <div class="classe-avatar" style="background:<?= $col ?>22;color:<?= $col ?>">
          <?= mb_substr(mb_strtoupper($cl['nom']), 0, 2) ?>
        </div>
        <div style="flex:1;min-width:0">
          <div style="font-size:13px;font-weight:700;color:var(--gris-900);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($cl['nom']) ?></div>
          <div style="font-size:11px;color:var(--gris-400)"><?= $cl['nb_eleves'] ?> élève<?= $cl['nb_eleves']!=1?'s':'' ?></div>
        </div>
        <i data-lucide="chevron-right" style="width:14px;height:14px;stroke:var(--gris-400);flex-shrink:0"></i>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ── Détail classe ── -->
  <?php if ($classeActive): ?>
  <div>
    <!-- Card info classe -->
    <div class="card" style="margin-bottom:16px">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">
        <div>
          <div style="font-family:var(--font-display);font-size:18px;font-weight:900;color:var(--gris-900)"><?= e($classeActive['nom']) ?></div>
          <?php if ($classeActive['niveau']): ?>
          <div style="font-size:12px;color:var(--gris-500);margin-top:2px"><?= e($classeActive['niveau']) ?></div>
          <?php endif; ?>
        </div>
        <div style="display:flex;gap:8px">
          <form method="POST" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="regenerer_code">
            <input type="hidden" name="classe_id" value="<?= e($classeActive['id']) ?>">
            <button type="submit" class="btn btn-ghost btn-sm" title="Régénérer le code d'invitation">
              <i data-lucide="refresh-cw" style="width:13px;height:13px;vertical-align:-2px"></i> Nouveau code
            </button>
          </form>
          <form method="POST" onsubmit="return confirm('Archiver la classe « <?= e($classeActive['nom']) ?> » ?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="supprimer_classe">
            <input type="hidden" name="classe_id" value="<?= e($classeActive['id']) ?>">
            <button type="submit" class="btn btn-ghost btn-sm" style="color:#DC2626;border-color:#FCA5A5">
              <i data-lucide="archive" style="width:13px;height:13px;vertical-align:-2px"></i> Archiver
            </button>
          </form>
        </div>
      </div>

      <!-- Code invitation -->
      <div style="background:linear-gradient(135deg,#EDE9FE,#DBEAFE);border:1.5px solid #C4B5FD;border-radius:12px;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
        <div>
          <div style="font-size:10px;font-weight:700;color:#5B21B6;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px">Code d'invitation élèves</div>
          <div style="font-family:var(--font-display);font-size:22px;font-weight:900;color:#3730A3;letter-spacing:3px" id="code-inv-<?= e($classeActive['id']) ?>"><?= e($classeActive['code_invitation']) ?></div>
        </div>
        <div style="display:flex;flex-direction:column;gap:6px">
          <button onclick="navigator.clipboard.writeText('<?= e($classeActive['code_invitation']) ?>').then(()=>{this.innerHTML='<span>✓ Copié</span>';setTimeout(()=>{this.innerHTML='Copier le code'},1500)})"
                  class="btn btn-sm" style="background:#7C3AED;color:#fff;border:none;font-weight:700;white-space:nowrap">
            Copier le code
          </button>
          <a href="/reussiteplus/ecole_rapport.php?classe=<?= urlencode($classeActive['id']) ?>"
             class="btn btn-ghost btn-sm" style="text-align:center;text-decoration:none">
            <i data-lucide="bar-chart-2" style="width:12px;height:12px;vertical-align:-2px"></i> Rapport
          </a>
        </div>
      </div>

      <!-- Stats de classe -->
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:16px">
        <div style="text-align:center;padding:12px;background:var(--gris-50);border-radius:10px">
          <div style="font-family:var(--font-display);font-size:22px;font-weight:900;color:var(--primary)"><?= count($eleves) ?></div>
          <div style="font-size:11px;color:var(--gris-500)">Élèves</div>
        </div>
        <div style="text-align:center;padding:12px;background:var(--gris-50);border-radius:10px">
          <?php $scoreMoyen = count($eleves) ? round(array_sum(array_column($eleves,'score_moyen'))/count($eleves),1) : 0; ?>
          <div style="font-family:var(--font-display);font-size:22px;font-weight:900;color:<?= $scoreMoyen>=70?'#059669':($scoreMoyen>=50?'#D97706':'#DC2626') ?>"><?= $scoreMoyen ?>%</div>
          <div style="font-size:11px;color:var(--gris-500)">Score moyen</div>
        </div>
        <div style="text-align:center;padding:12px;background:var(--gris-50);border-radius:10px">
          <?php $totalExamens = array_sum(array_column($eleves,'nb_examens')); ?>
          <div style="font-family:var(--font-display);font-size:22px;font-weight:900;color:#7C3AED"><?= $totalExamens ?></div>
          <div style="font-size:11px;color:var(--gris-500)">Examens passés</div>
        </div>
      </div>
    </div>

    <!-- Liste élèves -->
    <div class="card">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
        <div style="font-family:var(--font-display);font-size:14px;font-weight:800">Élèves inscrits (<?= count($eleves) ?>)</div>
        <a href="/reussiteplus/ecole_eleves.php?classe=<?= urlencode($classeActive['id']) ?>" class="btn btn-ghost btn-sm">
          <i data-lucide="users" style="width:12px;height:12px;vertical-align:-2px"></i> Vue complète
        </a>
      </div>
      <?php if ($eleves): ?>
      <?php foreach ($eleves as $i => $el): ?>
      <div class="eleve-row">
        <div style="font-size:12px;font-weight:700;color:var(--gris-400);width:20px;text-align:center"><?= $i+1 ?></div>
        <div style="width:34px;height:34px;border-radius:50%;background:var(--gris-100);display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:12px;font-weight:900;color:var(--gris-500);flex-shrink:0">
          <?= mb_strtoupper(mb_substr($el['prenom']??'?',0,1).mb_substr($el['nom']??'',0,1)) ?>
        </div>
        <div style="flex:1;min-width:0">
          <div style="font-size:13px;font-weight:700;color:var(--gris-900)"><?= e(($el['prenom']??'').' '.($el['nom']??'')) ?></div>
          <div style="font-size:11px;color:var(--gris-400)"><?= e($el['email']??'') ?></div>
        </div>
        <div style="display:flex;align-items:center;gap:8px">
          <div class="score-bar-w">
            <?php $sc = (float)$el['score_moyen']; $barcol = $sc>=70?'#059669':($sc>=50?'#D97706':'#DC2626'); ?>
            <div class="score-bar-f" style="width:<?= $sc ?>%;background:<?= $barcol ?>"></div>
          </div>
          <span style="font-size:12px;font-weight:700;color:<?= $barcol ?>;width:38px;text-align:right"><?= $sc ?>%</span>
          <form method="POST" onsubmit="return confirm('Retirer cet élève de la classe ?')" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="retirer_eleve">
            <input type="hidden" name="classe_id" value="<?= e($classeActive['id']) ?>">
            <input type="hidden" name="eleve_id" value="<?= e($el['id']) ?>">
            <button type="submit" style="background:none;border:none;cursor:pointer;padding:4px;color:var(--gris-400);transition:.15s" title="Retirer"
                    onmouseover="this.style.color='#DC2626'" onmouseout="this.style.color='var(--gris-400)'">
              <i data-lucide="user-minus" style="width:14px;height:14px"></i>
            </button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
      <?php else: ?>
      <div style="text-align:center;padding:30px">
        <i data-lucide="user-plus" style="width:32px;height:32px;stroke:var(--gris-300)"></i>
        <div style="font-size:13px;color:var(--gris-500);margin-top:8px">Aucun élève inscrit.</div>
        <div style="font-size:12px;color:var(--gris-400);margin-top:4px">Partagez le code d'invitation <strong><?= e($classeActive['code_invitation']) ?></strong></div>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php endif; ?>

<!-- ══ MODAL Créer classe ═════════════════════════════════ -->
<div id="modal-creer" class="modal-bd" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);display:none;align-items:center;justify-content:center;z-index:1000;padding:20px;backdrop-filter:blur(4px)" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--blanc);border-radius:20px;width:100%;max-width:440px">
    <div style="padding:20px 24px 16px;border-bottom:1px solid var(--gris-100);display:flex;align-items:center;justify-content:space-between">
      <span style="font-family:var(--font-display);font-size:16px;font-weight:800;display:flex;align-items:center;gap:8px">
        <i data-lucide="layout-list" style="width:16px;height:16px;stroke:#2563EB"></i> Nouvelle classe
      </span>
      <button onclick="document.getElementById('modal-creer').style.display='none'" style="background:none;border:none;cursor:pointer;color:var(--gris-500)">
        <i data-lucide="x" style="width:18px;height:18px"></i>
      </button>
    </div>
    <div style="padding:20px 24px">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="creer_classe">
        <div class="form-group">
          <label class="form-label">Nom de la classe *</label>
          <input type="text" name="nom" class="form-control" required placeholder="Ex : 6ème Année B">
        </div>
        <div class="form-group">
          <label class="form-label">Niveau scolaire</label>
          <input type="text" name="niveau" class="form-control" placeholder="Ex : Secondaire, Primaire, Terminal…">
        </div>
        <div class="form-group">
          <label class="form-label">Description (optionnel)</label>
          <textarea name="description" class="form-control" rows="2" placeholder="Section scientifique, langue principale…"></textarea>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%">
          <i data-lucide="check" style="width:14px;height:14px;vertical-align:-2px"></i> Créer la classe
        </button>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
