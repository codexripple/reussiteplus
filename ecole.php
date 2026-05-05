<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Tableau de classe';
$pageActive = 'ecole';
$user = require_login();

// Seul le plan ECOLE peut accéder
if ($user['plan'] !== 'ECOLE') {
    redirect('/reussiteplus/tarifs.php', 'warning', 'Le tableau de classe est réservé au plan École.');
}

// ── Actions POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { http_response_code(403); exit('CSRF'); }
    $action = $_POST['action'] ?? '';

    // Créer une classe
    if ($action === 'creer_classe') {
        $nom    = trim($_POST['nom'] ?? '');
        $niveau = trim($_POST['niveau'] ?? '');
        $annee  = trim($_POST['annee'] ?? '');
        if ($nom) {
            $code = strtoupper(substr(md5(uniqid()), 0, 8));
            dbRun(
                "INSERT INTO classes_ecole (admin_id, nom, niveau, annee_scolaire, code_invitation) VALUES (?,?,?,?,?)",
                [$user['id'], $nom, $niveau ?: null, $annee ?: null, $code]
            );
            redirect('/reussiteplus/ecole.php', 'success', 'Classe créée avec succès.');
        }
    }

    // Supprimer une classe
    if ($action === 'supprimer_classe') {
        $classeId = $_POST['classe_id'] ?? '';
        dbRun("DELETE FROM classes_ecole WHERE id=? AND admin_id=?", [$classeId, $user['id']]);
        redirect('/reussiteplus/ecole.php', 'success', 'Classe supprimée.');
    }

    // Retirer un élève
    if ($action === 'retirer_eleve') {
        $classeId = $_POST['classe_id'] ?? '';
        $eleveId  = $_POST['eleve_id'] ?? '';
        dbRun(
            "DELETE FROM classe_membres WHERE classe_id=? AND eleve_id=? AND classe_id IN (SELECT id FROM classes_ecole WHERE admin_id=?)",
            [$classeId, $eleveId, $user['id']]
        );
        redirect('/reussiteplus/ecole.php?classe=' . $classeId, 'success', 'Élève retiré.');
    }

    // Créer un devoir
    if ($action === 'creer_devoir') {
        $classeId  = $_POST['classe_id'] ?? '';
        $titre     = trim($_POST['titre'] ?? '');
        $matiereId = $_POST['matiere_id'] ?: null;
        $nbQ       = (int)($_POST['nb_questions'] ?? 20);
        $duree     = (int)($_POST['duree_min'] ?? 60);
        $limite    = $_POST['date_limite'] ?: null;
        if ($titre && $classeId) {
            dbRun(
                "INSERT INTO devoirs_ecole (classe_id, admin_id, titre, matiere_id, nb_questions, duree_min, date_limite) VALUES (?,?,?,?,?,?,?)",
                [$classeId, $user['id'], $titre, $matiereId, $nbQ, $duree, $limite]
            );
            redirect('/reussiteplus/ecole.php?classe=' . $classeId, 'success', 'Devoir créé.');
        }
    }

    exit;
}

// ── Données ───────────────────────────────────────────────────
$classeActive = $_GET['classe'] ?? null;

$classes = dbAll(
    "SELECT c.*, COUNT(DISTINCT cm.eleve_id) as nb_eleves
     FROM classes_ecole c
     LEFT JOIN classe_membres cm ON cm.classe_id = c.id AND cm.statut='ACTIF'
     WHERE c.admin_id = ? AND c.actif = 1
     GROUP BY c.id
     ORDER BY c.created_at DESC",
    [$user['id']]
);

// Sélectionner la première classe si aucune spécifiée
if (!$classeActive && $classes) {
    $classeActive = $classes[0]['id'];
}

// Données de la classe sélectionnée
$classe = null;
$eleves = [];
$devoirs = [];
$statsClasse = [];

if ($classeActive) {
    $classe = dbRow(
        "SELECT * FROM classes_ecole WHERE id=? AND admin_id=?",
        [$classeActive, $user['id']]
    );
    if ($classe) {
        $eleves = dbAll(
            "SELECT u.id, u.nom, u.prenom, u.email, u.classe, u.total_examens, u.score_moyen, u.streak_jours, u.derniere_activite, cm.joined_at
             FROM classe_membres cm
             JOIN utilisateurs u ON u.id = cm.eleve_id
             WHERE cm.classe_id = ? AND cm.statut = 'ACTIF'
             ORDER BY u.nom, u.prenom",
            [$classeActive]
        );

        $devoirs = dbAll(
            "SELECT d.*, m.nom as matiere_nom
             FROM devoirs_ecole d
             LEFT JOIN matieres m ON m.id = d.matiere_id
             WHERE d.classe_id = ?
             ORDER BY d.created_at DESC",
            [$classeActive]
        );

        // Statistiques agrégées
        if ($eleves) {
            $scores = array_column($eleves, 'score_moyen');
            $statsClasse = [
                'nb_eleves'    => count($eleves),
                'score_moyen'  => count($scores) > 0 ? round(array_sum($scores) / count($scores), 1) : 0,
                'actifs_7j'    => count(array_filter($eleves, fn($e) => $e['derniere_activite'] && strtotime($e['derniere_activite']) > strtotime('-7 days'))),
                'total_examens'=> array_sum(array_column($eleves, 'total_examens')),
            ];
        }
    }
}

$matieres = dbAll("SELECT id, nom FROM matieres WHERE actif=1 ORDER BY nom");
$planActif = PLANS[$user['plan']];

include __DIR__ . '/includes/header_app.php';
?>

<style>
/* ── École dashboard ──────────────────────────── */
.ecole-layout { display: grid; grid-template-columns: 260px 1fr; gap: 20px; align-items: start; }
@media(max-width: 860px){ .ecole-layout { grid-template-columns: 1fr; } }

.ecole-sidebar { background: var(--blanc); border: 1px solid var(--gris-200); border-radius: var(--radius-lg); overflow: hidden; position: sticky; top: 80px; }
.ecole-sidebar-head { padding: 16px 18px; background: linear-gradient(135deg,#0D1117,#003D2E); }
.ecole-sidebar-title { font-family: var(--font-display); font-size: 14px; font-weight: 800; color: #fff; margin-bottom: 2px; }
.ecole-sidebar-sub { font-size: 11px; color: rgba(255,255,255,.5); }
.ecole-class-list { padding: 8px; }
.ecole-class-item { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: var(--radius); cursor: pointer; text-decoration: none; color: var(--gris-700); font-size: 13px; transition: background .15s; margin-bottom: 2px; }
.ecole-class-item:hover { background: var(--gris-50); }
.ecole-class-item.active { background: var(--primary-subtle); color: var(--primary); font-weight: 600; }
.ecole-class-icon { width: 32px; height: 32px; border-radius: 8px; background: var(--primary-subtle); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.ecole-class-badge { margin-left: auto; background: var(--gris-200); color: var(--gris-600); padding: 1px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
.ecole-class-item.active .ecole-class-badge { background: var(--primary); color: #fff; }

/* ── Header classe ────────────────────────────── */
.classe-hero { background: linear-gradient(135deg,#0D1117,#003D2E); border-radius: var(--radius-lg); padding: 24px 28px; margin-bottom: 20px; position: relative; overflow: hidden; }
.classe-hero::before { content:''; position:absolute; inset:0; background: radial-gradient(ellipse 60% 80% at 100% 0%, rgba(0,122,94,.4) 0%, transparent 70%); pointer-events:none; }
.classe-hero-inner { position: relative; display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap; }

/* ── Stats rapides ────────────────────────────── */
.ecole-stats { display: grid; grid-template-columns: repeat(4,1fr); gap: 12px; margin-bottom: 20px; }
@media(max-width:700px){ .ecole-stats { grid-template-columns: repeat(2,1fr); } }
.ecole-stat { background: var(--blanc); border: 1px solid var(--gris-200); border-radius: var(--radius-lg); padding: 16px; text-align: center; }
.ecole-stat-val { font-family: var(--font-display); font-size: 26px; font-weight: 900; line-height: 1; }
.ecole-stat-label { font-size: 11px; color: var(--gris-500); margin-top: 3px; text-transform: uppercase; letter-spacing: .4px; }

/* ── Tableau élèves ───────────────────────────── */
.eleve-row { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-bottom: 1px solid var(--gris-100); transition: background .1s; }
.eleve-row:hover { background: var(--gris-50); }
.eleve-row:last-child { border-bottom: none; }
.eleve-avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--primary-subtle); display: flex; align-items: center; justify-content: center; font-family: var(--font-display); font-size: 13px; font-weight: 700; color: var(--primary); flex-shrink: 0; }
.score-bar-wrap { flex: 1; min-width: 80px; }
.score-bar-track { height: 6px; background: var(--gris-200); border-radius: 3px; overflow: hidden; margin-top: 3px; }
.score-bar-fill { height: 100%; border-radius: 3px; transition: width .4s; }

/* ── Devoirs ──────────────────────────────────── */
.devoir-item { display: flex; align-items: center; gap: 14px; padding: 14px 16px; border-bottom: 1px solid var(--gris-100); }
.devoir-item:last-child { border-bottom: none; }
.devoir-icon { width: 40px; height: 40px; border-radius: 10px; background: #EEF2FF; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }

/* ── Code invitation ──────────────────────────── */
.invite-code { font-family: monospace; font-size: 22px; font-weight: 900; letter-spacing: 4px; background: var(--gris-100); padding: 10px 20px; border-radius: var(--radius); border: 2px dashed var(--gris-300); cursor: pointer; display: inline-block; color: var(--primary); }
.invite-code:hover { border-color: var(--primary); background: var(--primary-subtle); }

/* ── Modal ────────────────────────────────────── */
.modal-bd { position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 16px; backdrop-filter: blur(4px); }
.modal-card { background: var(--blanc); border-radius: 16px; width: 100%; max-width: 480px; box-shadow: 0 24px 60px rgba(0,0,0,.25); overflow: hidden; }
.modal-head { padding: 20px 24px 0; display: flex; align-items: center; justify-content: space-between; }
.modal-body { padding: 20px 24px 24px; }
.modal-title { font-family: var(--font-display); font-size: 18px; font-weight: 800; color: var(--gris-900); }
</style>

<div class="ecole-layout">

  <!-- ── Sidebar classes ───────────────────────────────────────── -->
  <aside class="ecole-sidebar">
    <div class="ecole-sidebar-head">
      <div class="ecole-sidebar-title">
        <i data-lucide="school" style="width:14px;height:14px;vertical-align:-2px;stroke:#fff"></i>
        Plan École
      </div>
      <div class="ecole-sidebar-sub"><?= e($user['prenom']) ?> <?= e($user['nom']) ?> · Admin</div>
    </div>
    <div class="ecole-class-list">
      <?php foreach ($classes as $cl): ?>
      <a href="/reussiteplus/ecole.php?classe=<?= e($cl['id']) ?>"
         class="ecole-class-item <?= $classeActive === $cl['id'] ? 'active' : '' ?>">
        <div class="ecole-class-icon">
          <i data-lucide="users" style="width:14px;height:14px;stroke:var(--primary)"></i>
        </div>
        <div>
          <div style="font-weight:600;font-size:13px"><?= e($cl['nom']) ?></div>
          <?php if ($cl['niveau']): ?>
          <div style="font-size:11px;color:var(--gris-500)"><?= e($cl['niveau']) ?> <?= $cl['annee_scolaire'] ? '· '.$cl['annee_scolaire'] : '' ?></div>
          <?php endif; ?>
        </div>
        <span class="ecole-class-badge"><?= (int)$cl['nb_eleves'] ?></span>
      </a>
      <?php endforeach; ?>

      <?php if (count($classes) < 5): // Max 5 classes par école ?>
      <button onclick="document.getElementById('modal-classe').style.display='flex'"
              style="width:100%;display:flex;align-items:center;gap:8px;padding:10px 12px;background:none;border:1.5px dashed var(--gris-300);border-radius:var(--radius);cursor:pointer;color:var(--gris-600);font-size:13px;margin-top:8px;transition:all .15s"
              onmouseover="this.style.borderColor='var(--primary)';this.style.color='var(--primary)'"
              onmouseout="this.style.borderColor='var(--gris-300)';this.style.color='var(--gris-600)'">
        <i data-lucide="plus-circle" style="width:15px;height:15px"></i>
        Nouvelle classe
      </button>
      <?php endif; ?>
    </div>

    <!-- Capacité utilisée -->
    <?php
    $totalEleves = array_sum(array_column($classes, 'nb_eleves'));
    $maxEleves   = PLANS['ECOLE']['eleves_max'];
    $pct = $maxEleves > 0 ? min(100, round($totalEleves / $maxEleves * 100)) : 0;
    ?>
    <div style="padding: 12px 16px; border-top: 1px solid var(--gris-100);">
      <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--gris-500);margin-bottom:5px">
        <span><i data-lucide="users" style="width:11px;height:11px;vertical-align:-1px"></i> Élèves inscrits</span>
        <strong style="color:var(--gris-800)"><?= $totalEleves ?>/<?= $maxEleves ?></strong>
      </div>
      <div style="height:6px;background:var(--gris-200);border-radius:3px;overflow:hidden">
        <div style="height:100%;width:<?= $pct ?>%;background:<?= $pct>85?'#EF4444':'var(--primary)' ?>;border-radius:3px;transition:width .4s"></div>
      </div>
    </div>
  </aside>

  <!-- ── Contenu principal ─────────────────────────────────────── -->
  <div>
    <?php if ($classe): ?>

    <!-- Hero classe -->
    <div class="classe-hero">
      <div class="classe-hero-inner">
        <div>
          <div style="font-size:11px;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.6px;margin-bottom:4px">Classe active</div>
          <div style="font-family:var(--font-display);font-size:24px;font-weight:900;color:#fff;margin-bottom:4px"><?= e($classe['nom']) ?></div>
          <div style="font-size:13px;color:rgba(255,255,255,.6);display:flex;align-items:center;gap:12px;flex-wrap:wrap">
            <?php if ($classe['niveau']): ?>
            <span><i data-lucide="graduation-cap" style="width:12px;height:12px;vertical-align:-1px;stroke:rgba(255,255,255,.5)"></i> <?= e($classe['niveau']) ?></span>
            <?php endif; ?>
            <?php if ($classe['annee_scolaire']): ?>
            <span><i data-lucide="calendar" style="width:12px;height:12px;vertical-align:-1px;stroke:rgba(255,255,255,.5)"></i> <?= e($classe['annee_scolaire']) ?></span>
            <?php endif; ?>
            <span><i data-lucide="hash" style="width:12px;height:12px;vertical-align:-1px;stroke:rgba(255,255,255,.5)"></i> <?= count($eleves) ?> élève<?= count($eleves)>1?'s':'' ?></span>
          </div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <button onclick="document.getElementById('modal-invite').style.display='flex'"
                  style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);color:#fff;padding:9px 16px;border-radius:var(--radius);font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:7px;transition:all .15s"
                  onmouseover="this.style.background='rgba(255,255,255,.25)'"
                  onmouseout="this.style.background='rgba(255,255,255,.15)'">
            <i data-lucide="user-plus" style="width:14px;height:14px;stroke:#fff"></i>
            Inviter des élèves
          </button>
          <button onclick="document.getElementById('modal-devoir').style.display='flex'"
                  style="background:#FBBF24;border:none;color:#1C2433;padding:9px 16px;border-radius:var(--radius);font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:7px;transition:opacity .15s"
                  onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'">
            <i data-lucide="send" style="width:14px;height:14px;stroke:#1C2433"></i>
            Assigner un devoir
          </button>
        </div>
      </div>
    </div>

    <!-- Stats -->
    <?php if ($statsClasse): ?>
    <div class="ecole-stats">
      <div class="ecole-stat">
        <div class="ecole-stat-val" style="color:var(--primary)"><?= $statsClasse['nb_eleves'] ?></div>
        <div class="ecole-stat-label">Élèves</div>
      </div>
      <div class="ecole-stat">
        <div class="ecole-stat-val" style="color:var(--gold)"><?= $statsClasse['score_moyen'] ?><span style="font-size:14px;color:var(--gris-400)">%</span></div>
        <div class="ecole-stat-label">Score moyen</div>
      </div>
      <div class="ecole-stat">
        <div class="ecole-stat-val" style="color:#059669"><?= $statsClasse['actifs_7j'] ?></div>
        <div class="ecole-stat-label">Actifs (7 jours)</div>
      </div>
      <div class="ecole-stat">
        <div class="ecole-stat-val" style="color:#1E5FAD"><?= $statsClasse['total_examens'] ?></div>
        <div class="ecole-stat-label">Examens passés</div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Grille : Élèves + Devoirs -->
    <div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start">

      <!-- Liste des élèves -->
      <div class="card">
        <div class="card-header" style="margin-bottom:8px">
          <div style="font-family:var(--font-display);font-size:15px;font-weight:700;display:flex;align-items:center;gap:8px">
            <i data-lucide="users" style="width:16px;height:16px;stroke:var(--primary)"></i>
            Élèves de la classe
          </div>
          <span style="font-size:12px;color:var(--gris-500)"><?= count($eleves) ?>/<?= PLANS['ECOLE']['eleves_max'] ?></span>
        </div>

        <?php if ($eleves): ?>
        <?php foreach ($eleves as $eleve): ?>
        <div class="eleve-row">
          <div class="eleve-avatar">
            <?= strtoupper(mb_substr($eleve['prenom'], 0, 1) . mb_substr($eleve['nom'], 0, 1)) ?>
          </div>
          <div style="flex:1;min-width:0">
            <div style="font-weight:600;font-size:13px;color:var(--gris-900);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
              <?= e($eleve['prenom']) ?> <?= e($eleve['nom']) ?>
            </div>
            <div style="font-size:11px;color:var(--gris-500)">
              <?= e($eleve['email']) ?>
              <?php if ($eleve['classe']): ?> · <?= e($eleve['classe']) ?><?php endif; ?>
            </div>
          </div>
          <div class="score-bar-wrap" style="max-width:100px">
            <div style="display:flex;justify-content:space-between;font-size:11px">
              <span style="color:var(--gris-500)"><?= $eleve['total_examens'] ?> exam.</span>
              <strong style="color:<?= $eleve['score_moyen'] >= 60 ? 'var(--primary)' : '#EF4444' ?>"><?= round($eleve['score_moyen']) ?>%</strong>
            </div>
            <div class="score-bar-track">
              <div class="score-bar-fill" style="width:<?= min(100,round($eleve['score_moyen'])) ?>%;background:<?= $eleve['score_moyen'] >= 60 ? 'var(--primary)' : '#EF4444' ?>"></div>
            </div>
          </div>
          <div style="font-size:11px;color:var(--gris-400);min-width:60px;text-align:right">
            <?= $eleve['derniere_activite'] ? date('d/m', strtotime($eleve['derniere_activite'])) : '—' ?>
          </div>
          <form method="POST" style="display:inline" onsubmit="return confirm('Retirer cet élève ?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="retirer_eleve">
            <input type="hidden" name="classe_id" value="<?= e($classeActive) ?>">
            <input type="hidden" name="eleve_id" value="<?= e($eleve['id']) ?>">
            <button type="submit" title="Retirer" style="background:none;border:none;cursor:pointer;padding:4px;color:var(--gris-400);line-height:1" onmouseover="this.style.color='#EF4444'" onmouseout="this.style.color='var(--gris-400)'">
              <i data-lucide="user-minus" style="width:14px;height:14px"></i>
            </button>
          </form>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <div style="padding:40px;text-align:center;color:var(--gris-400)">
          <i data-lucide="users" style="width:40px;height:40px;stroke:var(--gris-300);margin-bottom:12px"></i>
          <div style="font-size:15px;font-weight:600;color:var(--gris-600);margin-bottom:4px">Aucun élève pour l'instant</div>
          <div style="font-size:13px;margin-bottom:16px">Partagez le code d'invitation avec vos élèves.</div>
          <button onclick="document.getElementById('modal-invite').style.display='flex'" class="btn btn-primary btn-sm">
            <i data-lucide="user-plus" style="width:12px;height:12px;vertical-align:-2px"></i> Inviter des élèves
          </button>
        </div>
        <?php endif; ?>
      </div>

      <!-- Devoirs + Code invitation -->
      <div style="display:flex;flex-direction:column;gap:16px">

        <!-- Code invitation -->
        <div class="card">
          <div style="font-family:var(--font-display);font-size:14px;font-weight:700;margin-bottom:12px;display:flex;align-items:center;gap:7px">
            <i data-lucide="link" style="width:15px;height:15px;stroke:var(--primary)"></i>
            Code d'invitation
          </div>
          <div style="text-align:center;padding:8px 0">
            <div class="invite-code" id="invite-code-val"
                 onclick="navigator.clipboard.writeText('<?= e($classe['code_invitation']) ?>').then(()=>{this.style.background='#D1FAE5';this.style.borderColor='#059669';setTimeout(()=>{this.style.background='';this.style.borderColor=''},1500)})"
                 title="Cliquer pour copier">
              <?= e($classe['code_invitation']) ?>
            </div>
            <div style="font-size:12px;color:var(--gris-500);margin-top:8px">
              <i data-lucide="info" style="width:11px;height:11px;vertical-align:-1px"></i>
              Les élèves entrent ce code à l'inscription pour rejoindre la classe.
            </div>
          </div>
          <div style="margin-top:12px;padding:10px 12px;background:var(--gris-50);border-radius:var(--radius);font-size:12px;color:var(--gris-600)">
            <strong>Lien direct :</strong><br>
            <span style="font-size:11px;word-break:break-all">http://localhost/reussiteplus/rejoindre.php?code=<?= e($classe['code_invitation']) ?></span>
          </div>
        </div>

        <!-- Devoirs assignés -->
        <div class="card">
          <div class="card-header" style="margin-bottom:4px">
            <div style="font-family:var(--font-display);font-size:14px;font-weight:700;display:flex;align-items:center;gap:7px">
              <i data-lucide="clipboard-list" style="width:15px;height:15px;stroke:var(--primary)"></i>
              Devoirs assignés
            </div>
            <span style="font-size:12px;color:var(--gris-500)"><?= count($devoirs) ?></span>
          </div>
          <?php if ($devoirs): ?>
          <?php foreach ($devoirs as $dev): ?>
          <div class="devoir-item">
            <div class="devoir-icon">
              <i data-lucide="file-text" style="width:16px;height:16px;stroke:#4F46E5"></i>
            </div>
            <div style="flex:1;min-width:0">
              <div style="font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($dev['titre']) ?></div>
              <div style="font-size:11px;color:var(--gris-500)">
                <?= $dev['matiere_nom'] ? e($dev['matiere_nom']).' · ' : '' ?>
                <?= $dev['nb_questions'] ?>Q · <?= $dev['duree_min'] ?>min
                <?php if ($dev['date_limite']): ?>
                · <span style="color:#DC2626">Limite: <?= date('d/m', strtotime($dev['date_limite'])) ?></span>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php else: ?>
          <div style="padding:20px;text-align:center;color:var(--gris-400);font-size:13px">
            Aucun devoir assigné
          </div>
          <?php endif; ?>
          <div style="padding:12px 16px;border-top:1px solid var(--gris-100)">
            <button onclick="document.getElementById('modal-devoir').style.display='flex'"
                    style="width:100%;display:flex;align-items:center;justify-content:center;gap:6px;padding:8px;background:none;border:1.5px dashed var(--gris-300);border-radius:var(--radius);cursor:pointer;color:var(--gris-600);font-size:13px;font-weight:600;transition:all .15s"
                    onmouseover="this.style.borderColor='var(--primary)';this.style.color='var(--primary)'"
                    onmouseout="this.style.borderColor='var(--gris-300)';this.style.color='var(--gris-600)'">
              <i data-lucide="plus" style="width:13px;height:13px"></i> Nouveau devoir
            </button>
          </div>
        </div>

        <!-- Rapport mensuel -->
        <div class="card" style="background:linear-gradient(135deg,#0D1117,#1a0a3d);border:none">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
            <i data-lucide="bar-chart-2" style="width:18px;height:18px;stroke:#C4B5FD"></i>
            <span style="font-family:var(--font-display);font-size:14px;font-weight:800;color:#fff">Rapport mensuel</span>
          </div>
          <p style="font-size:12px;color:rgba(255,255,255,.55);margin-bottom:14px;line-height:1.6">
            Export des performances de la classe : scores, assiduité, progression par matière.
          </p>
          <a href="/reussiteplus/ecole_rapport.php?classe=<?= e($classeActive) ?>"
             style="display:flex;align-items:center;justify-content:center;gap:7px;padding:10px;background:#7C3AED;color:#fff;border-radius:var(--radius);font-size:13px;font-weight:700;text-decoration:none;transition:opacity .15s"
             onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'">
            <i data-lucide="download" style="width:14px;height:14px;stroke:#fff"></i>
            Générer le rapport
          </a>
        </div>

      </div><!-- /col -->
    </div><!-- /grid élèves+devoirs -->

    <?php else: ?>
    <!-- Aucune classe -->
    <div class="card" style="text-align:center;padding:60px 30px">
      <i data-lucide="school" style="width:56px;height:56px;stroke:var(--gris-300);margin-bottom:16px"></i>
      <div style="font-family:var(--font-display);font-size:22px;font-weight:800;color:var(--gris-800);margin-bottom:8px">Créez votre première classe</div>
      <p style="color:var(--gris-500);max-width:400px;margin:0 auto 24px">
        Le plan École vous permet de gérer jusqu'à <?= PLANS['ECOLE']['eleves_max'] ?> élèves, d'assigner des devoirs et de suivre leur progression en temps réel.
      </p>
      <button onclick="document.getElementById('modal-classe').style.display='flex'" class="btn btn-primary">
        <i data-lucide="plus-circle" style="width:14px;height:14px;vertical-align:-2px"></i> Créer une classe
      </button>
    </div>
    <?php endif; ?>
  </div><!-- /main -->
</div><!-- /layout -->


<!-- ══ MODAL : Créer une classe ══════════════════════════════ -->
<div id="modal-classe" class="modal-bd" style="display:none" onclick="if(event.target===this)this.style.display='none'">
  <div class="modal-card">
    <div class="modal-head">
      <span class="modal-title">
        <i data-lucide="plus-circle" style="width:18px;height:18px;vertical-align:-3px;stroke:var(--primary)"></i>
        Nouvelle classe
      </span>
      <button onclick="document.getElementById('modal-classe').style.display='none'" style="background:none;border:none;cursor:pointer;color:var(--gris-500);line-height:1;font-size:18px">
        <i data-lucide="x" style="width:18px;height:18px"></i>
      </button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="creer_classe">
        <div class="form-group" style="margin-bottom:14px">
          <label class="form-label">Nom de la classe *</label>
          <input type="text" name="nom" class="form-control" placeholder="Ex: 6ème Primaire A" required maxlength="120">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
          <div class="form-group">
            <label class="form-label">Niveau</label>
            <select name="niveau" class="form-control">
              <option value="">— Sélectionner —</option>
              <optgroup label="Primaire">
                <option>1ère Primaire</option><option>2ème Primaire</option>
                <option>3ème Primaire</option><option>4ème Primaire</option>
                <option>5ème Primaire</option><option>6ème Primaire</option>
              </optgroup>
              <optgroup label="Secondaire">
                <option>7ème (1ère Secondaire)</option><option>2ème Secondaire</option>
                <option>3ème Secondaire</option><option>4ème Secondaire</option>
                <option>5ème Secondaire</option><option>Terminale</option>
              </optgroup>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Année scolaire</label>
            <input type="text" name="annee" class="form-control" placeholder="2025-2026" value="2025-2026" maxlength="20">
          </div>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%">
          <i data-lucide="plus" style="width:14px;height:14px;vertical-align:-2px"></i>
          Créer la classe
        </button>
      </form>
    </div>
  </div>
</div>

<!-- ══ MODAL : Code invitation ═══════════════════════════════ -->
<div id="modal-invite" class="modal-bd" style="display:none" onclick="if(event.target===this)this.style.display='none'">
  <div class="modal-card">
    <div class="modal-head">
      <span class="modal-title">
        <i data-lucide="user-plus" style="width:18px;height:18px;vertical-align:-3px;stroke:var(--primary)"></i>
        Inviter des élèves
      </span>
      <button onclick="document.getElementById('modal-invite').style.display='none'" style="background:none;border:none;cursor:pointer;color:var(--gris-500);line-height:1">
        <i data-lucide="x" style="width:18px;height:18px"></i>
      </button>
    </div>
    <div class="modal-body">
      <?php if ($classe): ?>
      <p style="font-size:13px;color:var(--gris-600);margin-bottom:16px">
        Partagez ce code ou ce lien avec vos élèves. Ils l'utiliseront lors de leur inscription pour rejoindre automatiquement la classe <strong><?= e($classe['nom']) ?></strong>.
      </p>
      <div style="text-align:center;margin-bottom:20px">
        <div class="invite-code" style="font-size:28px;letter-spacing:6px;display:block;padding:16px"
             onclick="navigator.clipboard.writeText('<?= e($classe['code_invitation']) ?>').then(()=>{this.innerHTML='Copié !';setTimeout(()=>{this.innerHTML='<?= e($classe['code_invitation']) ?>'},1500)})"
             title="Cliquer pour copier">
          <?= e($classe['code_invitation']) ?>
        </div>
        <div style="font-size:11px;color:var(--gris-500);margin-top:6px">Cliquer pour copier</div>
      </div>
      <div style="background:var(--gris-50);border-radius:var(--radius);padding:12px 14px;font-size:12px">
        <div style="font-weight:600;margin-bottom:4px;color:var(--gris-700)">
          <i data-lucide="link" style="width:11px;height:11px;vertical-align:-1px"></i> Lien d'inscription direct
        </div>
        <div style="display:flex;align-items:center;gap:8px">
          <input type="text" value="http://localhost/reussiteplus/inscription.php?ref_classe=<?= e($classe['code_invitation']) ?>" readonly
                 style="flex:1;font-size:11px;background:var(--gris-100);border:1px solid var(--gris-200);border-radius:6px;padding:6px 10px;color:var(--gris-700)">
          <button onclick="navigator.clipboard.writeText(this.previousElementSibling.value);this.innerHTML='<i data-lucide=\'check\' style=\'width:14px;height:14px\'></i>';setTimeout(()=>{this.innerHTML='Copier'},1500)"
                  class="btn btn-ghost btn-sm">Copier</button>
        </div>
      </div>
      <div style="margin-top:16px;padding:12px 14px;background:#FFFBEB;border:1px solid #FDE68A;border-radius:var(--radius);font-size:12px;color:#92400E">
        <i data-lucide="info" style="width:12px;height:12px;vertical-align:-1px"></i>
        Les élèves doivent créer un compte gratuit. Le code les rattache automatiquement à votre classe.
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ══ MODAL : Assigner un devoir ═════════════════════════════ -->
<div id="modal-devoir" class="modal-bd" style="display:none" onclick="if(event.target===this)this.style.display='none'">
  <div class="modal-card">
    <div class="modal-head">
      <span class="modal-title">
        <i data-lucide="send" style="width:18px;height:18px;vertical-align:-3px;stroke:var(--primary)"></i>
        Assigner un devoir
      </span>
      <button onclick="document.getElementById('modal-devoir').style.display='none'" style="background:none;border:none;cursor:pointer;color:var(--gris-500);line-height:1">
        <i data-lucide="x" style="width:18px;height:18px"></i>
      </button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="creer_devoir">
        <input type="hidden" name="classe_id" value="<?= e($classeActive) ?>">
        <div class="form-group" style="margin-bottom:14px">
          <label class="form-label">Titre du devoir *</label>
          <input type="text" name="titre" class="form-control" placeholder="Ex: Contrôle Maths – Fractions" required maxlength="200">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
          <div class="form-group">
            <label class="form-label">Matière</label>
            <select name="matiere_id" class="form-control">
              <option value="">Toutes les matières</option>
              <?php foreach ($matieres as $m): ?>
              <option value="<?= e($m['id']) ?>"><?= e($m['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Questions</label>
            <select name="nb_questions" class="form-control">
              <option value="10">10 questions</option>
              <option value="20" selected>20 questions</option>
              <option value="30">30 questions</option>
              <option value="50">50 questions</option>
            </select>
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
          <div class="form-group">
            <label class="form-label">Durée</label>
            <select name="duree_min" class="form-control">
              <option value="30">30 minutes</option>
              <option value="60" selected>1 heure</option>
              <option value="90">1h30</option>
              <option value="120">2 heures</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Date limite</label>
            <input type="datetime-local" name="date_limite" class="form-control">
          </div>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%">
          <i data-lucide="send" style="width:14px;height:14px;vertical-align:-2px"></i>
          Assigner le devoir
        </button>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
