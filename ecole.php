<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Mon École';
$pageActive = 'ecole';
$user = require_login();

if ($user['plan'] !== 'ECOLE') {
    redirect('/reussiteplus/tarifs.php', 'warning', 'Le tableau de l\'école est réservé au plan École.');
}

// ── Profil école ──────────────────────────────────────────────
$ecole = dbRow("SELECT * FROM ecoles WHERE admin_id=?", [$user['id']]);
if (!$ecole) {
    dbRun("INSERT IGNORE INTO ecoles (admin_id, nom) VALUES (?,?)", [$user['id'], $user['prenom'].' '.$user['nom'].' — École']);
    $ecole = dbRow("SELECT * FROM ecoles WHERE admin_id=?", [$user['id']]);
}

// ── Stats globales ────────────────────────────────────────────
$nbClasses     = (int)(dbScalar("SELECT COUNT(*) FROM classes_ecole WHERE admin_id=? AND actif=1", [$user['id']]) ?? 0);
$nbEnseignants = (int)(dbScalar("SELECT COUNT(*) FROM enseignants_ecole WHERE ecole_admin_id=? AND statut='ACTIF'", [$user['id']]) ?? 0);
$nbEleves      = (int)(dbScalar("SELECT COUNT(DISTINCT cm.eleve_id) FROM classe_membres cm JOIN classes_ecole c ON c.id=cm.classe_id WHERE c.admin_id=? AND cm.statut='ACTIF'", [$user['id']]) ?? 0);
$nbDevoirs     = (int)(dbScalar("SELECT COUNT(*) FROM devoirs_ecole WHERE admin_id=? AND actif=1", [$user['id']]) ?? 0);
$nbRessources  = (int)(dbScalar("SELECT COUNT(*) FROM bibliotheque_ecole WHERE ecole_admin_id=?", [$user['id']]) ?? 0);
$scoreMoyen    = (float)(dbScalar("SELECT ROUND(AVG(u.score_moyen),1) FROM classe_membres cm JOIN classes_ecole c ON c.id=cm.classe_id JOIN utilisateurs u ON u.id=cm.eleve_id WHERE c.admin_id=? AND cm.statut='ACTIF'", [$user['id']]) ?? 0);
$examsSemaine  = (int)(dbScalar("SELECT COUNT(*) FROM exam_sessions es JOIN classe_membres cm ON cm.eleve_id=es.user_id JOIN classes_ecole c ON c.id=cm.classe_id WHERE c.admin_id=? AND es.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)", [$user['id']]) ?? 0);
$elevesActifs  = (int)(dbScalar("SELECT COUNT(DISTINCT cm.eleve_id) FROM classe_membres cm JOIN classes_ecole c ON c.id=cm.classe_id JOIN utilisateurs u ON u.id=cm.eleve_id WHERE c.admin_id=? AND cm.statut='ACTIF' AND u.derniere_activite >= DATE_SUB(NOW(), INTERVAL 7 DAY)", [$user['id']]) ?? 0);

// ── Classes ───────────────────────────────────────────────────
$classes = dbAll(
    "SELECT c.id, c.nom, c.niveau, c.annee_scolaire, c.code_invitation,
            COUNT(DISTINCT cm.eleve_id) as nb_eleves,
            COALESCE(ROUND(AVG(u.score_moyen),1),0) as score_moyen,
            COUNT(DISTINCT d.id) as nb_devoirs
     FROM classes_ecole c
     LEFT JOIN classe_membres cm ON cm.classe_id=c.id AND cm.statut='ACTIF'
     LEFT JOIN utilisateurs u ON u.id=cm.eleve_id
     LEFT JOIN devoirs_ecole d ON d.classe_id=c.id AND d.actif=1
     WHERE c.admin_id=? AND c.actif=1
     GROUP BY c.id ORDER BY c.created_at DESC",
    [$user['id']]
) ?? [];

// ── Activité récente ──────────────────────────────────────────
$activites = dbAll(
    "SELECT u.prenom, u.nom, m.nom as matiere, es.score, es.created_at
     FROM exam_sessions es
     JOIN utilisateurs u ON u.id=es.user_id
     LEFT JOIN matieres m ON m.id=es.matiere_id
     JOIN classe_membres cm ON cm.eleve_id=es.user_id
     JOIN classes_ecole c ON c.id=cm.classe_id
     WHERE c.admin_id=?
     ORDER BY es.created_at DESC LIMIT 8",
    [$user['id']]
) ?? [];

// ── Devoirs récents ───────────────────────────────────────────
$devoirsRecents = dbAll(
    "SELECT d.titre, d.date_limite, c.nom as classe_nom, m.nom as matiere_nom
     FROM devoirs_ecole d
     JOIN classes_ecole c ON c.id=d.classe_id
     LEFT JOIN matieres m ON m.id=d.matiere_id
     WHERE d.admin_id=? AND d.actif=1
     ORDER BY d.created_at DESC LIMIT 5",
    [$user['id']]
) ?? [];

// ── Top élèves ────────────────────────────────────────────────
$topEleves = dbAll(
    "SELECT u.prenom, u.nom, u.score_moyen, u.total_examens, c.nom as classe_nom
     FROM classe_membres cm
     JOIN classes_ecole c ON c.id=cm.classe_id
     JOIN utilisateurs u ON u.id=cm.eleve_id
     WHERE c.admin_id=? AND cm.statut='ACTIF' AND u.total_examens > 0
     ORDER BY u.score_moyen DESC LIMIT 5",
    [$user['id']]
) ?? [];

// ── Annonces récentes ─────────────────────────────────────────
$annonces = dbAll("SELECT * FROM annonces_ecole WHERE ecole_admin_id=? ORDER BY created_at DESC LIMIT 3", [$user['id']]) ?? [];

// ── Actions POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { http_response_code(403); exit('CSRF'); }
    $action = $_POST['action'] ?? '';

    if ($action === 'update_ecole') {
        $nom    = trim($_POST['nom']     ?? '');
        $devise = trim($_POST['devise']  ?? '');
        $adresse = trim($_POST['adresse'] ?? '');
        $tel    = trim($_POST['telephone'] ?? '');
        if ($nom) {
            dbRun("UPDATE ecoles SET nom=?, devise=?, adresse=?, telephone=? WHERE admin_id=?",
                  [$nom, $devise, $adresse, $tel, $user['id']]);
        }
        redirect('/reussiteplus/ecole.php', 'success', 'Profil école mis à jour.');
    }

    if ($action === 'envoyer_annonce') {
        $sujet   = trim($_POST['sujet']   ?? '');
        $message = trim($_POST['message'] ?? '');
        $type    = in_array($_POST['type'] ?? '', ['INFO','URGENT','DEVOIR','EVENEMENT']) ? $_POST['type'] : 'INFO';
        $cible   = in_array($_POST['cible'] ?? '', ['TOUS','CLASSE','ENSEIGNANTS','ELEVES']) ? $_POST['cible'] : 'TOUS';
        if ($sujet && $message) {
            dbRun("INSERT INTO annonces_ecole (ecole_admin_id, expediteur_id, cible, sujet, message, type) VALUES (?,?,?,?,?,?)",
                  [$user['id'], $user['id'], $cible, $sujet, $message, $type]);
            redirect('/reussiteplus/ecole.php', 'success', 'Annonce envoyée.');
        }
    }
    exit;
}

$planE = PLANS['ECOLE'];
include __DIR__ . '/includes/header_app.php';
?>

<style>
/* ────────────────────────────────────────────────
   ÉCOLE HUB — Design System
──────────────────────────────────────────────── */
.ecole-hero {
  background: linear-gradient(135deg,#0a1628 0%,#003D2E 60%,#0a1628 100%);
  border-radius: var(--radius-xl); padding: 32px 28px; margin-bottom: 24px;
  position: relative; overflow: hidden;
}
.ecole-hero::before {
  content:''; position:absolute; top:-40px; right:-40px;
  width:200px; height:200px;
  background:radial-gradient(circle,rgba(0,122,94,.4) 0%,transparent 70%);
  pointer-events:none;
}
.ecole-hero-inner { position:relative; display:flex; align-items:center; justify-content:space-between; gap:20px; flex-wrap:wrap; }
.ecole-badge { display:inline-flex; align-items:center; gap:6px; background:rgba(0,122,94,.3); border:1px solid rgba(0,122,94,.5); border-radius:20px; padding:4px 12px; font-size:11px; font-weight:700; color:#6EE7B7; text-transform:uppercase; letter-spacing:.5px; margin-bottom:10px; }
.ecole-nom { font-family:var(--font-display); font-size:clamp(18px,3vw,26px); font-weight:900; color:#fff; line-height:1.2; margin-bottom:6px; }
.ecole-sous { font-size:13px; color:rgba(255,255,255,.5); }
.hero-stats { display:flex; gap:24px; flex-wrap:wrap; }
.hero-stat-val { font-family:var(--font-display); font-size:26px; font-weight:900; color:#fff; line-height:1; }
.hero-stat-label { font-size:11px; color:rgba(255,255,255,.4); text-transform:uppercase; letter-spacing:.4px; margin-top:2px; }

/* ── Modules ──────────────────────────────────── */
.modules-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(165px,1fr)); gap:12px; margin-bottom:28px; }
.module-card {
  background:var(--blanc); border:1.5px solid var(--gris-200); border-radius:var(--radius-lg);
  padding:18px 14px; text-decoration:none; display:flex; flex-direction:column;
  align-items:center; gap:9px; text-align:center; transition:all .2s; position:relative; overflow:hidden;
}
.module-card::after { content:''; position:absolute; bottom:0; left:0; right:0; height:3px; background:var(--mc-color,var(--primary)); transform:scaleX(0); transition:transform .2s; transform-origin:left; }
.module-card:hover { border-color:var(--mc-color,var(--primary)); box-shadow:0 4px 16px rgba(0,0,0,.08); transform:translateY(-2px); }
.module-card:hover::after { transform:scaleX(1); }
.module-icon { width:46px; height:46px; border-radius:13px; display:flex; align-items:center; justify-content:center; flex-shrink:0; background:var(--mc-bg,var(--primary-subtle)); }
.module-name { font-family:var(--font-display); font-size:13px; font-weight:800; color:var(--gris-800); line-height:1.3; }
.module-count { font-size:11px; color:var(--gris-500); }
.module-new { position:absolute; top:7px; right:7px; background:#EF4444; color:#fff; font-size:9px; font-weight:800; padding:1px 6px; border-radius:8px; }

/* ── Stats row ────────────────────────────────── */
.stats-row { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:28px; }
@media(max-width:700px) { .stats-row { grid-template-columns:repeat(2,1fr); } }
.stat-card { background:var(--blanc); border:1px solid var(--gris-200); border-radius:var(--radius-lg); padding:18px 20px; display:flex; align-items:center; gap:14px; }
.stat-icon { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.stat-val { font-family:var(--font-display); font-size:24px; font-weight:900; line-height:1; }
.stat-label { font-size:12px; color:var(--gris-500); margin-top:2px; }

/* ── Two-col layout ───────────────────────────── */
.ecole-cols { display:grid; grid-template-columns:1fr 350px; gap:20px; align-items:start; }
@media(max-width:960px) { .ecole-cols { grid-template-columns:1fr; } }

/* ── Classe item ──────────────────────────────── */
.classe-row {
  display:flex; align-items:center; gap:12px; padding:12px 16px;
  border-radius:var(--radius); border:1.5px solid var(--gris-200);
  text-decoration:none; margin-bottom:9px; transition:all .18s;
}
.classe-row:hover { border-color:var(--primary); background:var(--primary-subtle); }
.classe-av { width:40px; height:40px; border-radius:10px; background:linear-gradient(135deg,var(--primary),#7C3AED); color:#fff; display:flex; align-items:center; justify-content:center; font-family:var(--font-display); font-size:13px; font-weight:900; flex-shrink:0; }

/* ── Feed ─────────────────────────────────────── */
.feed-item { display:flex; align-items:flex-start; gap:9px; padding:9px 0; border-bottom:1px solid var(--gris-100); }
.feed-item:last-child { border-bottom:none; }
.feed-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; margin-top:5px; }

/* ── Top élève ────────────────────────────────── */
.top-row { display:flex; align-items:center; gap:9px; padding:8px 0; border-bottom:1px solid var(--gris-100); }
.top-row:last-child { border-bottom:none; }
.top-av { width:30px; height:30px; border-radius:50%; background:linear-gradient(135deg,var(--primary),#1E5FAD); color:#fff; display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:800; flex-shrink:0; }

/* ── Annonce badge ────────────────────────────── */
.ann-badge { display:inline-flex; align-items:center; gap:3px; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.2px; padding:2px 7px; border-radius:9px; }
.ann-INFO     { background:#DBEAFE; color:#1E5FAD; }
.ann-URGENT   { background:#FEE2E2; color:#DC2626; }
.ann-DEVOIR   { background:#D1FAE5; color:#059669; }
.ann-EVENEMENT{ background:#FEF3C7; color:#D97706; }

/* ── Modals ───────────────────────────────────── */
.modal-bd { position:fixed; inset:0; background:rgba(0,0,0,.5); display:flex; align-items:center; justify-content:center; z-index:1000; padding:20px; backdrop-filter:blur(4px); }
.modal-card { background:var(--blanc); border-radius:20px; width:100%; max-width:480px; max-height:90vh; overflow-y:auto; }
.modal-head { padding:20px 24px 16px; border-bottom:1px solid var(--gris-100); display:flex; align-items:center; justify-content:space-between; }
.modal-title { font-family:var(--font-display); font-size:16px; font-weight:800; display:flex; align-items:center; gap:8px; }
.modal-body { padding:20px 24px; }
</style>

<!-- ══ HERO ══════════════════════════════════════════════════ -->
<div class="ecole-hero">
  <div class="ecole-hero-inner">
    <div style="flex:1;min-width:0">
      <div class="ecole-badge">
        <i data-lucide="school" style="width:11px;height:11px"></i>
        Plan École Actif
      </div>
      <div class="ecole-nom"><?= e($ecole['nom'] ?? 'Mon École') ?></div>
      <div class="ecole-sous">
        <?= e($ecole['devise'] ?? 'Plateforme éducative digitale') ?>
        <?php if (!empty($ecole['adresse'])): ?>
          &nbsp;·&nbsp;<i data-lucide="map-pin" style="width:11px;height:11px;vertical-align:-1px"></i> <?= e($ecole['adresse']) ?>
        <?php endif; ?>
      </div>
    </div>
    <div class="hero-stats">
      <?php foreach ([
          ['val' => $nbClasses,     'label' => 'Classes'],
          ['val' => $nbEnseignants, 'label' => 'Enseignants'],
          ['val' => $nbEleves,      'label' => 'Élèves'],
          ['val' => $examsSemaine,  'label' => 'Examens/7j'],
      ] as $s): ?>
      <div style="text-align:center">
        <div class="hero-stat-val"><?= $s['val'] ?></div>
        <div class="hero-stat-label"><?= $s['label'] ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="display:flex;gap:8px;flex-shrink:0;flex-wrap:wrap">
      <button onclick="document.getElementById('modal-annonce').style.display='flex'"
              style="background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.25);color:#fff;padding:9px 14px;border-radius:var(--radius);font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;transition:.15s"
              onmouseover="this.style.background='rgba(255,255,255,.22)'" onmouseout="this.style.background='rgba(255,255,255,.12)'">
        <i data-lucide="megaphone" style="width:14px;height:14px;stroke:#fff"></i> Annonce
      </button>
      <button onclick="document.getElementById('modal-settings').style.display='flex'"
              style="background:#007A5E;border:none;color:#fff;padding:9px 14px;border-radius:var(--radius);font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px;transition:opacity .15s"
              onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
        <i data-lucide="settings" style="width:14px;height:14px;stroke:#fff"></i> Configurer
      </button>
    </div>
  </div>
</div>

<!-- ══ MODULES ═══════════════════════════════════════════════ -->
<div class="modules-grid">
  <?php
  $modules = [
      ['href'=>'/reussiteplus/ecole_classes.php',      'icon'=>'layout-list',   'color'=>'#007A5E','bg'=>'#D1FAE5', 'name'=>'Classes',           'count'=>$nbClasses.'/'.$planE['classes_max'].' classes'],
      ['href'=>'/reussiteplus/ecole_enseignants.php',  'icon'=>'user-check',    'color'=>'#7C3AED','bg'=>'#EDE9FE', 'name'=>'Enseignants',        'count'=>$nbEnseignants.'/'.$planE['enseignants_max']],
      ['href'=>'/reussiteplus/ecole_eleves.php',       'icon'=>'users',         'color'=>'#1E5FAD','bg'=>'#DBEAFE', 'name'=>'Élèves',             'count'=>$nbEleves.'/'.$planE['eleves_max']],
      ['href'=>'/reussiteplus/ecole_devoirs.php',      'icon'=>'clipboard-list','color'=>'#D97706','bg'=>'#FEF3C7', 'name'=>'Devoirs',            'count'=>$nbDevoirs.' en cours'],
      ['href'=>'/reussiteplus/ecole_emploi_temps.php', 'icon'=>'calendar-days', 'color'=>'#0891B2','bg'=>'#CFFAFE', 'name'=>'Emploi du temps',    'count'=>'Calendrier interactif'],
      ['href'=>'/reussiteplus/ecole_bibliotheque.php', 'icon'=>'book-open',     'color'=>'#DC2626','bg'=>'#FEE2E2', 'name'=>'Bibliothèque',       'count'=>$nbRessources.' ressource'.($nbRessources!=1?'s':'')],
      ['href'=>'/reussiteplus/ecole_bulletin.php',     'icon'=>'file-badge',    'color'=>'#059669','bg'=>'#D1FAE5', 'name'=>'Bulletins',          'count'=>'PDF automatique'],
      ['href'=>'/reussiteplus/ecole_absences.php',     'icon'=>'user-x',        'color'=>'#F97316','bg'=>'#FFEDD5', 'name'=>'Absences',           'count'=>'Suivi assiduité'],
      ['href'=>'/reussiteplus/ecole_rapport.php',      'icon'=>'bar-chart-3',   'color'=>'#6B21A8','bg'=>'#F3E8FF', 'name'=>'Rapports',           'count'=>'Analytics avancées'],
      ['href'=>'/reussiteplus/ecole_ia.php',           'icon'=>'brain-circuit', 'color'=>'#C4B5FD','bg'=>'#2D1B69', 'name'=>'IA Pédagogique',     'count'=>'Recommandations IA', 'new'=>true, 'dark'=>true],
  ];
  foreach ($modules as $mod): ?>
  <a href="<?= $mod['href'] ?>" class="module-card"
     style="--mc-color:<?= $mod['color'] ?>;--mc-bg:<?= $mod['bg'] ?>;<?= !empty($mod['dark']) ? 'background:#12082a;border-color:#3b1f8c' : '' ?>">
    <?php if (!empty($mod['new'])): ?><span class="module-new">NOUVEAU</span><?php endif; ?>
    <div class="module-icon" style="<?= !empty($mod['dark']) ? 'background:rgba(124,58,237,.25)' : '' ?>">
      <i data-lucide="<?= $mod['icon'] ?>" style="width:22px;height:22px;stroke:<?= $mod['color'] ?>"></i>
    </div>
    <div>
      <div class="module-name" style="<?= !empty($mod['dark']) ? 'color:#C4B5FD' : '' ?>"><?= $mod['name'] ?></div>
      <div class="module-count" style="<?= !empty($mod['dark']) ? 'color:#9ca3af' : '' ?>"><?= $mod['count'] ?></div>
    </div>
  </a>
  <?php endforeach; ?>
</div>

<!-- ══ STATS ══════════════════════════════════════════════════ -->
<div class="stats-row">
  <?php
  $statsCards = [
      ['icon'=>'trending-up', 'bg'=>'#D1FAE5', 'stroke'=>'#059669', 'val'=>$scoreMoyen.'%', 'color'=>'#059669', 'label'=>'Score moyen global', 'sub'=>'Sur '.$nbEleves.' élève'.($nbEleves!=1?'s':'')],
      ['icon'=>'zap',         'bg'=>'#DBEAFE', 'stroke'=>'#1E5FAD', 'val'=>$elevesActifs,   'color'=>'#1E5FAD', 'label'=>'Élèves actifs (7j)',  'sub'=>($nbEleves > 0 ? round($elevesActifs/$nbEleves*100).'% d\'engagement' : '—')],
      ['icon'=>'book-check',  'bg'=>'#FEF3C7', 'stroke'=>'#D97706', 'val'=>$examsSemaine,   'color'=>'#D97706', 'label'=>'Examens / semaine',   'sub'=>'Tous élèves'],
      ['icon'=>'library',     'bg'=>'#EDE9FE', 'stroke'=>'#7C3AED', 'val'=>$nbRessources,   'color'=>'#7C3AED', 'label'=>'Ressources biblio',   'sub'=>'Docs & liens'],
  ];
  foreach ($statsCards as $sc): ?>
  <div class="stat-card">
    <div class="stat-icon" style="background:<?= $sc['bg'] ?>"><i data-lucide="<?= $sc['icon'] ?>" style="width:20px;height:20px;stroke:<?= $sc['stroke'] ?>"></i></div>
    <div>
      <div class="stat-val" style="color:<?= $sc['color'] ?>"><?= $sc['val'] ?></div>
      <div class="stat-label"><?= $sc['label'] ?></div>
      <div style="font-size:11px;color:var(--gris-400);margin-top:2px"><?= $sc['sub'] ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ══ CORPS ══════════════════════════════════════════════════ -->
<div class="ecole-cols">

  <!-- Colonne gauche -->
  <div style="display:flex;flex-direction:column;gap:20px">

    <!-- Classes -->
    <div class="card">
      <div class="card-header" style="margin-bottom:16px">
        <div style="font-family:var(--font-display);font-size:15px;font-weight:800;display:flex;align-items:center;gap:8px">
          <i data-lucide="layout-list" style="width:16px;height:16px;stroke:var(--primary)"></i> Mes classes
        </div>
        <a href="/reussiteplus/ecole_classes.php" class="btn btn-ghost btn-sm">
          <i data-lucide="arrow-right" style="width:13px;height:13px;vertical-align:-1px"></i> Gérer
        </a>
      </div>
      <?php if ($classes): ?>
        <?php foreach ($classes as $cl): ?>
        <a href="/reussiteplus/ecole_classes.php?classe=<?= e($cl['id']) ?>" class="classe-row">
          <div class="classe-av"><?= strtoupper(mb_substr($cl['nom'],0,2)) ?></div>
          <div style="flex:1;min-width:0">
            <div style="font-family:var(--font-display);font-size:14px;font-weight:700;color:var(--gris-900)"><?= e($cl['nom']) ?></div>
            <div style="font-size:12px;color:var(--gris-500)">
              <?= $cl['niveau'] ? e($cl['niveau']).' · ' : '' ?><?= $cl['nb_eleves'] ?> élève<?= $cl['nb_eleves']!=1?'s':'' ?> · <?= $cl['nb_devoirs'] ?> devoir<?= $cl['nb_devoirs']!=1?'s':'' ?>
            </div>
          </div>
          <div style="font-family:var(--font-display);font-size:16px;font-weight:900;color:<?= $cl['score_moyen']>=60?'var(--primary)':($cl['score_moyen']>0?'#EF4444':'var(--gris-400)') ?>">
            <?= $cl['score_moyen']>0 ? $cl['score_moyen'].'%' : '—' ?>
          </div>
          <i data-lucide="chevron-right" style="width:14px;height:14px;stroke:var(--gris-300)"></i>
        </a>
        <?php endforeach; ?>
      <?php else: ?>
        <div style="padding:30px;text-align:center">
          <i data-lucide="school" style="width:36px;height:36px;stroke:var(--gris-300);margin-bottom:10px"></i>
          <div style="font-size:14px;font-weight:600;color:var(--gris-600);margin-bottom:4px">Aucune classe</div>
          <div style="font-size:12px;color:var(--gris-400);margin-bottom:14px">Créez votre première classe pour commencer</div>
          <a href="/reussiteplus/ecole_classes.php" class="btn btn-primary btn-sm">Créer une classe</a>
        </div>
      <?php endif; ?>
    </div>

    <!-- Top élèves -->
    <?php if ($topEleves): ?>
    <div class="card">
      <div class="card-header" style="margin-bottom:12px">
        <div style="font-family:var(--font-display);font-size:15px;font-weight:800;display:flex;align-items:center;gap:8px">
          <i data-lucide="trophy" style="width:16px;height:16px;stroke:#D97706"></i> Top élèves
        </div>
        <a href="/reussiteplus/ecole_eleves.php" class="btn btn-ghost btn-sm">Voir tous</a>
      </div>
      <?php foreach ($topEleves as $i => $el): ?>
      <div class="top-row">
        <div style="width:24px;text-align:center;font-size:14px"><?= ['🥇','🥈','🥉'][$i] ?? ($i+1) ?></div>
        <div class="top-av"><?= strtoupper(mb_substr($el['prenom'],0,1).mb_substr($el['nom'],0,1)) ?></div>
        <div style="flex:1;min-width:0">
          <div style="font-weight:600;font-size:13px"><?= e($el['prenom']) ?> <?= e($el['nom']) ?></div>
          <div style="font-size:11px;color:var(--gris-500)"><?= e($el['classe_nom']) ?> · <?= $el['total_examens'] ?> examens</div>
        </div>
        <div style="font-family:var(--font-display);font-size:16px;font-weight:900;color:<?= $el['score_moyen']>=60?'var(--primary)':'#EF4444' ?>">
          <?= round($el['score_moyen']) ?>%
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Devoirs récents -->
    <?php if ($devoirsRecents): ?>
    <div class="card">
      <div class="card-header" style="margin-bottom:12px">
        <div style="font-family:var(--font-display);font-size:15px;font-weight:800;display:flex;align-items:center;gap:8px">
          <i data-lucide="clipboard-list" style="width:16px;height:16px;stroke:#D97706"></i> Devoirs en cours
        </div>
        <a href="/reussiteplus/ecole_devoirs.php" class="btn btn-ghost btn-sm">Gérer</a>
      </div>
      <?php foreach ($devoirsRecents as $dev): ?>
      <div style="display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--gris-100)">
        <div style="width:34px;height:34px;border-radius:9px;background:#FEF3C7;display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <i data-lucide="file-text" style="width:15px;height:15px;stroke:#D97706"></i>
        </div>
        <div style="flex:1;min-width:0">
          <div style="font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($dev['titre']) ?></div>
          <div style="font-size:11px;color:var(--gris-500)"><?= e($dev['classe_nom']) ?><?= $dev['matiere_nom']?' · '.e($dev['matiere_nom']):'' ?></div>
        </div>
        <?php if ($dev['date_limite']): ?>
        <div style="font-size:11px;color:#DC2626;font-weight:600;white-space:nowrap"><?= date('d/m',strtotime($dev['date_limite'])) ?></div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div><!-- /col gauche -->

  <!-- Colonne droite -->
  <div style="display:flex;flex-direction:column;gap:16px">

    <!-- Annonces -->
    <div class="card">
      <div class="card-header" style="margin-bottom:12px">
        <div style="font-family:var(--font-display);font-size:14px;font-weight:800;display:flex;align-items:center;gap:8px">
          <i data-lucide="megaphone" style="width:14px;height:14px;stroke:var(--primary)"></i> Annonces
        </div>
        <button onclick="document.getElementById('modal-annonce').style.display='flex'" class="btn btn-primary btn-sm">
          <i data-lucide="plus" style="width:11px;height:11px;vertical-align:-1px"></i> Nouvelle
        </button>
      </div>
      <?php if ($annonces): ?>
        <?php foreach ($annonces as $ann): ?>
        <div style="padding:10px 0;border-bottom:1px solid var(--gris-100)">
          <div style="display:flex;align-items:center;gap:7px;margin-bottom:4px">
            <span class="ann-badge ann-<?= e($ann['type']) ?>"><?= e($ann['type']) ?></span>
            <span style="font-size:11px;color:var(--gris-400)"><?= date('d/m/Y',strtotime($ann['created_at'])) ?></span>
          </div>
          <div style="font-weight:600;font-size:13px"><?= e($ann['sujet']) ?></div>
          <div style="font-size:12px;color:var(--gris-500);margin-top:2px"><?= e(mb_substr($ann['message'],0,90)) ?><?= mb_strlen($ann['message'])>90?'…':'' ?></div>
        </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div style="padding:20px;text-align:center;color:var(--gris-400);font-size:13px">Aucune annonce</div>
      <?php endif; ?>
    </div>

    <!-- Activité récente -->
    <div class="card">
      <div style="font-family:var(--font-display);font-size:14px;font-weight:800;display:flex;align-items:center;gap:8px;margin-bottom:12px">
        <i data-lucide="activity" style="width:14px;height:14px;stroke:var(--primary)"></i> Activité récente
      </div>
      <?php if ($activites): ?>
        <?php foreach ($activites as $act): ?>
        <div class="feed-item">
          <div class="feed-dot" style="background:<?= $act['score']>=60?'#059669':'#EF4444' ?>"></div>
          <div>
            <div style="font-size:13px;color:var(--gris-700);line-height:1.5">
              <strong><?= e($act['prenom']) ?> <?= e($act['nom']) ?></strong>
              — <?= $act['matiere']?e($act['matiere']):'Examen' ?>
              <strong style="color:<?= $act['score']>=60?'#059669':'#EF4444' ?>"> <?= round($act['score']) ?>%</strong>
            </div>
            <div style="font-size:11px;color:var(--gris-400)"><?= date('d/m à H:i',strtotime($act['created_at'])) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div style="padding:20px;text-align:center;color:var(--gris-400);font-size:13px">Aucune activité récente</div>
      <?php endif; ?>
    </div>

    <!-- IA Insights -->
    <div class="card" style="background:linear-gradient(135deg,#0D1117,#1a0a3d);border:none">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
        <div style="width:36px;height:36px;border-radius:9px;background:rgba(124,58,237,.3);display:flex;align-items:center;justify-content:center">
          <i data-lucide="brain-circuit" style="width:18px;height:18px;stroke:#C4B5FD"></i>
        </div>
        <div>
          <div style="font-family:var(--font-display);font-size:14px;font-weight:800;color:#fff">IA Pédagogique</div>
          <div style="font-size:11px;color:rgba(255,255,255,.4)">Analyse en temps réel</div>
        </div>
      </div>
      <?php
      $insights = [];
      if ($elevesActifs < $nbEleves * 0.5 && $nbEleves > 0)
          $insights[] = ['⚠️','Engagement faible', round($elevesActifs/$nbEleves*100).'% d\'élèves actifs. Envoyez une annonce.','#FBBF24'];
      if ($scoreMoyen < 50 && $scoreMoyen > 0)
          $insights[] = ['📉','Score moyen bas','Moyenne de '.$scoreMoyen.'%. Révisez les matières difficiles.','#F87171'];
      if ($nbEnseignants === 0)
          $insights[] = ['👨‍🏫','Invitez des enseignants','Déléguez et enrichissez votre école.','#6EE7B7'];
      if ($nbRessources < 3)
          $insights[] = ['📚','Bibliothèque vide','Ajoutez des ressources pour vos élèves.','#93C5FD'];
      if (!$insights)
          $insights[] = ['✅','École bien configurée','Continuez à enrichir votre contenu.','#6EE7B7'];
      foreach ($insights as $ins): ?>
      <div style="background:rgba(255,255,255,.06);border-radius:var(--radius);padding:9px 11px;border-left:3px solid <?= $ins[3] ?>;margin-bottom:7px">
        <div style="font-size:12px;font-weight:700;color:<?= $ins[3] ?>;margin-bottom:1px"><?= $ins[0] ?> <?= $ins[1] ?></div>
        <div style="font-size:11px;color:rgba(255,255,255,.5);line-height:1.5"><?= $ins[2] ?></div>
      </div>
      <?php endforeach; ?>
      <a href="/reussiteplus/ecole_ia.php"
         style="display:flex;align-items:center;justify-content:center;gap:6px;margin-top:6px;padding:9px;background:rgba(124,58,237,.3);border:1px solid rgba(124,58,237,.5);color:#C4B5FD;border-radius:var(--radius);font-size:12px;font-weight:700;text-decoration:none;transition:.15s"
         onmouseover="this.style.background='rgba(124,58,237,.5)'" onmouseout="this.style.background='rgba(124,58,237,.3)'">
        <i data-lucide="sparkles" style="width:12px;height:12px;stroke:#C4B5FD"></i> Analyse complète IA
      </a>
    </div>

    <!-- Support & Contact -->
    <div class="card">
      <div style="font-family:var(--font-display);font-size:13px;font-weight:800;margin-bottom:12px;display:flex;align-items:center;gap:7px">
        <i data-lucide="headphones" style="width:14px;height:14px;stroke:var(--primary)"></i> Support RÉUSSITE+
      </div>
      <div style="display:flex;flex-direction:column;gap:8px">
        <a href="https://wa.me/<?= CONTACT_ORANGE ?>" target="_blank" rel="noopener"
           style="display:flex;align-items:center;gap:10px;padding:9px 12px;background:#FFF7ED;border:1px solid #FED7AA;border-radius:var(--radius);text-decoration:none;transition:.15s"
           onmouseover="this.style.background='#FFEDD5'" onmouseout="this.style.background='#FFF7ED'">
          <div style="width:30px;height:30px;background:#FF6600;border-radius:7px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i data-lucide="phone" style="width:14px;height:14px;stroke:#fff"></i>
          </div>
          <div>
            <div style="font-size:12px;font-weight:700;color:#92400E">Orange Money</div>
            <div style="font-size:11px;color:#D97706">+243 84 020 4331</div>
          </div>
        </a>
        <a href="https://wa.me/<?= CONTACT_MPESA ?>" target="_blank" rel="noopener"
           style="display:flex;align-items:center;gap:10px;padding:9px 12px;background:#F0FDF4;border:1px solid #BBF7D0;border-radius:var(--radius);text-decoration:none;transition:.15s"
           onmouseover="this.style.background='#DCFCE7'" onmouseout="this.style.background='#F0FDF4'">
          <div style="width:30px;height:30px;background:#00A651;border-radius:7px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i data-lucide="message-circle" style="width:14px;height:14px;stroke:#fff"></i>
          </div>
          <div>
            <div style="font-size:12px;font-weight:700;color:#14532D">M-Pesa / WhatsApp</div>
            <div style="font-size:11px;color:#059669">+243 83 150 8853</div>
          </div>
        </a>
      </div>
    </div>

  </div><!-- /col droite -->
</div>

<!-- ══ MODAL Annonce ══════════════════════════════════════════ -->
<div id="modal-annonce" class="modal-bd" style="display:none" onclick="if(event.target===this)this.style.display='none'">
  <div class="modal-card">
    <div class="modal-head">
      <span class="modal-title"><i data-lucide="megaphone" style="width:16px;height:16px;stroke:var(--primary)"></i> Nouvelle annonce</span>
      <button onclick="document.getElementById('modal-annonce').style.display='none'" style="background:none;border:none;cursor:pointer;color:var(--gris-500)"><i data-lucide="x" style="width:18px;height:18px"></i></button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="envoyer_annonce">
        <div class="form-group">
          <label class="form-label">Type</label>
          <select name="type" class="form-control">
            <option value="INFO">📢 Information générale</option>
            <option value="URGENT">🚨 Urgent</option>
            <option value="DEVOIR">📝 Devoir</option>
            <option value="EVENEMENT">📅 Événement</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Destinataires</label>
          <select name="cible" class="form-control">
            <option value="TOUS">Toute l'école</option>
            <option value="ELEVES">Élèves seulement</option>
            <option value="ENSEIGNANTS">Enseignants seulement</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Sujet *</label>
          <input type="text" name="sujet" class="form-control" placeholder="Ex : Réunion parents vendredi 10h" required maxlength="255">
        </div>
        <div class="form-group">
          <label class="form-label">Message *</label>
          <textarea name="message" class="form-control" rows="4" required placeholder="Détails de l'annonce…"></textarea>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%">
          <i data-lucide="send" style="width:13px;height:13px;vertical-align:-2px"></i> Envoyer
        </button>
      </form>
    </div>
  </div>
</div>

<!-- ══ MODAL Paramètres École ═════════════════════════════════ -->
<div id="modal-settings" class="modal-bd" style="display:none" onclick="if(event.target===this)this.style.display='none'">
  <div class="modal-card">
    <div class="modal-head">
      <span class="modal-title"><i data-lucide="settings" style="width:16px;height:16px;stroke:var(--primary)"></i> Configurer l'école</span>
      <button onclick="document.getElementById('modal-settings').style.display='none'" style="background:none;border:none;cursor:pointer;color:var(--gris-500)"><i data-lucide="x" style="width:18px;height:18px"></i></button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_ecole">
        <div class="form-group">
          <label class="form-label">Nom de l'école *</label>
          <input type="text" name="nom" class="form-control" value="<?= e($ecole['nom']??'') ?>" required maxlength="200">
        </div>
        <div class="form-group">
          <label class="form-label">Devise / Slogan</label>
          <input type="text" name="devise" class="form-control" value="<?= e($ecole['devise']??'') ?>" maxlength="100" placeholder="L'excellence pour tous">
        </div>
        <div class="form-group">
          <label class="form-label">Adresse</label>
          <input type="text" name="adresse" class="form-control" value="<?= e($ecole['adresse']??'') ?>" maxlength="255" placeholder="Kinshasa, Gombe">
        </div>
        <div class="form-group">
          <label class="form-label">Téléphone de l'école</label>
          <input type="text" name="telephone" class="form-control" value="<?= e($ecole['telephone']??'') ?>" maxlength="30" placeholder="+243 8X XXX XXXX">
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%">
          <i data-lucide="save" style="width:13px;height:13px;vertical-align:-2px"></i> Enregistrer
        </button>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
