<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/ia_teachers.php';

$pageTitle  = 'Espace Enseignant';
$pageActive = 'enseignant';

// Authentification enseignant : soit rôle ENSEIGNANT, soit admin qui consulte un enseignant
$user = require_login('/reussiteplus/connexion.php');

$enseignantId = $_GET['ens'] ?? null;
$enseignant   = null;

if ($enseignantId) {
    // Admin consultant un espace enseignant
    $enseignant = dbRow(
        "SELECT e.*, u.email as user_email FROM enseignants_ecole e
         LEFT JOIN utilisateurs u ON u.id=e.user_id
         WHERE e.id=?",
        [$enseignantId]
    );
    if (!$enseignant || $enseignant['ecole_admin_id'] !== $user['id']) {
        redirect('/reussiteplus/ecole_enseignants.php', 'error', 'Accès non autorisé.');
    }
} elseif ($user['role'] === 'ENSEIGNANT') {
    // Enseignant connecté avec son propre compte
    $enseignant = dbRow(
        "SELECT * FROM enseignants_ecole WHERE user_id=? AND statut='ACTIF'",
        [$user['id']]
    );
    if (!$enseignant) {
        redirect('/reussiteplus/connexion.php', 'error', 'Compte enseignant non activé. Contactez votre directeur.');
    }
} else {
    redirect('/reussiteplus/ecole_enseignants.php', 'error', 'Accès réservé aux enseignants.');
}

// ── Données du tableau de bord enseignant ────────────────────
$adminId = $enseignant['ecole_admin_id'];

// Classes assignées
$classesAssignees = dbAll(
    "SELECT c.id, c.nom, c.niveau,
            COUNT(DISTINCT cm.eleve_id) as nb_eleves,
            GROUP_CONCAT(DISTINCT m.nom ORDER BY m.nom SEPARATOR ', ') as matieres
     FROM enseignant_classes ec
     JOIN classes_ecole c ON c.id = ec.classe_id
     LEFT JOIN classe_membres cm ON cm.classe_id = c.id AND cm.statut = 'ACTIF'
     LEFT JOIN matieres m ON m.id = ec.matiere_id
     WHERE ec.enseignant_id = ? AND c.actif = 1
     GROUP BY c.id",
    [$enseignant['id']]
) ?? [];

$classeIds = array_column($classesAssignees, 'id');

// Devoirs à corriger (soumissions non corrigées de ses classes)
$devoirsACorreger = [];
if ($classeIds) {
    $in = implode(',', array_fill(0, count($classeIds), '?'));
    $devoirsACorreger = dbAll(
        "SELECT d.id as devoir_id, d.titre, c.nom as classe_nom,
                COUNT(DISTINCT sd.id) as nb_soumissions,
                COUNT(DISTINCT CASE WHEN sd.statut='CORRIGE' THEN sd.id END) as nb_corriges
         FROM devoirs_ecole d
         JOIN classes_ecole c ON c.id=d.classe_id
         JOIN soumissions_devoirs sd ON sd.devoir_id=d.id
         WHERE d.classe_id IN ($in) AND d.admin_id=? AND d.actif=1
         GROUP BY d.id
         HAVING nb_soumissions > nb_corriges
         ORDER BY d.date_limite ASC LIMIT 8",
        array_merge($classeIds, [$adminId])
    ) ?? [];
}

// Performance globale des élèves par matière assignée
$performanceGlobale = [];
if ($classeIds) {
    $in = implode(',', array_fill(0, count($classeIds), '?'));
    $performanceGlobale = dbAll(
        "SELECT m.nom as matiere, m.couleur,
                COUNT(DISTINCT es.user_id) as nb_eleves,
                ROUND(AVG(es.pourcentage), 1) as score_moyen,
                COUNT(DISTINCT es.id) as nb_sessions
         FROM exam_sessions es
         JOIN matieres m ON m.id = es.matiere_id
         JOIN classe_membres cm ON cm.eleve_id = es.user_id
         WHERE cm.classe_id IN ($in) AND es.statut = 'TERMINE'
         GROUP BY m.id ORDER BY score_moyen ASC",
        $classeIds
    ) ?? [];
}

// Score moyen global enseignant
$scoreMoyenClasses = count($performanceGlobale)
    ? round(array_sum(array_column($performanceGlobale, 'score_moyen')) / count($performanceGlobale), 1)
    : 0;

// Élèves en difficulté
$elevesEnDifficulte = [];
if ($classeIds) {
    $in = implode(',', array_fill(0, count($classeIds), '?'));
    $elevesEnDifficulte = dbAll(
        "SELECT DISTINCT u.prenom, u.nom, c.nom as classe_nom,
                ROUND(AVG(es.pourcentage), 1) as score_moyen
         FROM classe_membres cm
         JOIN utilisateurs u ON u.id=cm.eleve_id
         JOIN classes_ecole c ON c.id=cm.classe_id
         LEFT JOIN exam_sessions es ON es.user_id=u.id AND es.statut='TERMINE'
         WHERE cm.classe_id IN ($in)
         GROUP BY u.id, c.id
         HAVING score_moyen < 50 AND score_moyen > 0
         ORDER BY score_moyen ASC LIMIT 6",
        $classeIds
    ) ?? [];
}

$matieresJson = is_string($enseignant['matieres_json'])
    ? json_decode($enseignant['matieres_json'], true) : ($enseignant['matieres_json'] ?? []);

// Calcul salaire virtuel pour cet enseignant (visible uniquement dans son espace)
$teacherPersona = null;
foreach (IA_TEACHERS as $key => $t) {
    if (!empty($matieresNoms) && in_array($t['matiere'], $matieresNoms)) {
        $teacherPersona = $t; break;
    }
}
$matiereStatEns = [];
if ($teacherPersona) {
    $matStatRow = dbRow(
        "SELECT COUNT(DISTINCT es.user_id) as nb_eleves, COUNT(DISTINCT es.id) as nb_sessions,
                ROUND(AVG(es.pourcentage),1) as score_moyen
         FROM exam_sessions es
         JOIN matieres m ON m.id=es.matiere_id
         JOIN classe_membres cm ON cm.eleve_id=es.user_id
         JOIN classes_ecole c ON c.id=cm.classe_id
         WHERE c.admin_id=? AND m.nom=? AND es.statut='TERMINE'",
        [$adminId, $teacherPersona['matiere']]
    ) ?? [];
    $salaire = calculer_salaire_virtuel($teacherPersona, $matStatRow);
}

$matieresNoms = [];
if (!empty($matieresJson)) {
    $in = implode(',', array_fill(0, count($matieresJson), '?'));
    $mats = dbAll("SELECT nom FROM matieres WHERE id IN ($in)", $matieresJson) ?? [];
    $matieresNoms = array_column($mats, 'nom');
}

include __DIR__ . '/../includes/header_app.php';
?>

<style>
.ens-header {
  background: linear-gradient(135deg, #1e3a5f 0%, #0a1628 100%);
  border-radius: 18px; padding: 24px 28px; margin-bottom: 22px;
  position: relative; overflow: hidden;
}
.ens-header::before {
  content: ''; position: absolute; top: -40px; right: -40px;
  width: 180px; height: 180px; border-radius: 50%;
  background: radial-gradient(circle, rgba(30,95,173,.2) 0%, transparent 70%);
}
.ens-kpi { display: grid; grid-template-columns: repeat(4,1fr); gap: 14px; margin-bottom: 22px; }
@media(max-width:768px){ .ens-kpi{ grid-template-columns:repeat(2,1fr); } }
.ens-kpi-card {
  background: var(--blanc); border: 1px solid var(--gris-200);
  border-radius: 13px; padding: 15px 17px; text-align: center;
}
.ens-kpi-val { font-size: 24px; font-weight: 900; line-height: 1.1; }
.ens-kpi-lbl { font-size: 10.5px; color: var(--gris-500); margin-top: 3px; text-transform: uppercase; letter-spacing: .4px; }
.ens-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
@media(max-width:900px){ .ens-grid{ grid-template-columns:1fr; } }
.perf-row { display: flex; align-items: center; gap: 10px; padding: 9px 0; border-bottom: 1px solid var(--gris-100); }
.perf-row:last-child { border: none; }
.perf-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.perf-name { flex: 1; font-size: 13px; color: var(--gris-800); }
.perf-score { font-size: 13px; font-weight: 700; }
.perf-bar-wrap { width: 70px; height: 5px; background: var(--gris-100); border-radius: 99px; overflow: hidden; }
.perf-bar-fill { height: 100%; border-radius: 99px; }
.alert-eleve {
  display: flex; align-items: center; gap: 10px; padding: 9px 12px;
  border-radius: 9px; background: rgba(201,52,42,.05); border: 1px solid rgba(201,52,42,.15);
  margin-bottom: 7px;
}
</style>

<!-- Header enseignant -->
<div class="ens-header">
  <div style="position:relative;z-index:1;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px">
    <div>
      <div style="font-size:11px;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.6px;margin-bottom:4px">
        <?php if ($enseignantId): ?>
        <a href="/reussiteplus/ecole_enseignants.php" style="color:rgba(255,255,255,.4);text-decoration:none">Enseignants</a> / Vue enseignant
        <?php else: ?>
        Espace Enseignant
        <?php endif; ?>
      </div>
      <div style="font-size:20px;font-weight:900;color:#fff;margin-bottom:2px">
        <?= e($enseignant['prenom'] . ' ' . $enseignant['nom']) ?>
      </div>
      <?php if (!empty($matieresNoms)): ?>
      <div style="font-size:12px;color:rgba(255,255,255,.55)"><?= e(implode(' · ', $matieresNoms)) ?></div>
      <?php endif; ?>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <div style="text-align:center;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:11px;padding:10px 16px">
        <div style="font-size:18px;font-weight:900;color:#6EE7B7"><?= count($classesAssignees) ?></div>
        <div style="font-size:9px;color:rgba(255,255,255,.4);text-transform:uppercase">Classes</div>
      </div>
      <div style="text-align:center;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:11px;padding:10px 16px">
        <div style="font-size:18px;font-weight:900;color:#FCD34D"><?= count($devoirsACorreger) ?></div>
        <div style="font-size:9px;color:rgba(255,255,255,.4);text-transform:uppercase">À corriger</div>
      </div>
      <div style="text-align:center;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:11px;padding:10px 16px">
        <div style="font-size:18px;font-weight:900;color:<?= $scoreMoyenClasses>=65?'#6EE7B7':($scoreMoyenClasses>=50?'#FCD34D':'#FCA5A5') ?>"><?= $scoreMoyenClasses ?>%</div>
        <div style="font-size:9px;color:rgba(255,255,255,.4);text-transform:uppercase">Moy. classes</div>
      </div>
    </div>
  </div>
</div>

<!-- KPI -->
<div class="ens-kpi">
  <div class="ens-kpi-card">
    <div class="ens-kpi-val" style="color:var(--primary)"><?= array_sum(array_column($classesAssignees,'nb_eleves')) ?></div>
    <div class="ens-kpi-lbl">Élèves suivis</div>
  </div>
  <div class="ens-kpi-card">
    <div class="ens-kpi-val" style="color:#1E5FAD"><?= count($matieresNoms) ?></div>
    <div class="ens-kpi-lbl">Matières</div>
  </div>
  <div class="ens-kpi-card">
    <div class="ens-kpi-val" style="color:#C9342A"><?= count($devoirsACorreger) ?></div>
    <div class="ens-kpi-lbl">Devoirs à corriger</div>
  </div>
  <div class="ens-kpi-card">
    <div class="ens-kpi-val" style="color:#C9342A"><?= count($elevesEnDifficulte) ?></div>
    <div class="ens-kpi-lbl">Élèves en difficulté</div>
  </div>
</div>

<?php if (!empty($salaire)): ?>
<!-- Salaire virtuel — visible uniquement dans l'espace enseignant -->
<div style="background:linear-gradient(135deg,#0d1120,#111827);border:1px solid rgba(201,151,42,.2);border-radius:14px;padding:18px 22px;margin-bottom:22px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
  <div>
    <div style="font-size:11px;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.6px;margin-bottom:4px">Rémunération mensuelle virtuelle</div>
    <div style="font-size:22px;font-weight:900;color:#F5D78E"><?= number_format($salaire['total']) ?> <span style="font-size:13px;color:rgba(255,255,255,.35)">CDF</span></div>
    <div style="font-size:11.5px;color:rgba(255,255,255,.35);margin-top:3px">Performance : <strong style="color:<?= $salaire['color_perf'] ?>"><?= $salaire['note_perf'] ?></strong></div>
  </div>
  <div style="display:flex;gap:12px;flex-wrap:wrap">
    <?php foreach ([
      ['Base',          number_format($salaire['base']),           'rgba(255,255,255,.4)'],
      ['Élèves',        '+'.number_format($salaire['bonus_eleves']), '#6EE7B7'],
      ['Activité',      '+'.number_format($salaire['bonus_activite']),'#93C5FD'],
      ['Performance',   '+'.number_format($salaire['bonus_perf']),   '#FCD34D'],
    ] as [$lbl, $val, $col]): ?>
    <div style="text-align:center;background:rgba(255,255,255,.05);border-radius:9px;padding:8px 12px">
      <div style="font-size:12.5px;font-weight:800;color:<?= $col ?>"><?= $val ?></div>
      <div style="font-size:9.5px;color:rgba(255,255,255,.3);text-transform:uppercase;margin-top:2px"><?= $lbl ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <div style="font-size:10px;color:rgba(255,255,255,.2);font-style:italic;width:100%">Salaire simulé à titre indicatif — calculé sur l'activité pédagogique réelle.</div>
</div>
<?php endif; ?>

<div class="ens-grid">

  <!-- Performance par matière -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="vertical-align:-2px;margin-right:6px"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/></svg>
        Performance par matière
      </div>
    </div>
    <?php if ($performanceGlobale): ?>
    <?php foreach ($performanceGlobale as $p):
        $pct = min(100, (float)$p['score_moyen']);
        $col = $pct >= 70 ? '#007A5E' : ($pct >= 50 ? '#C9972A' : '#C9342A');
    ?>
    <div class="perf-row">
      <div class="perf-dot" style="background:<?= $p['couleur'] ?? '#007A5E' ?>"></div>
      <div class="perf-name"><?= e($p['matiere']) ?> <span style="font-size:11px;color:var(--gris-400)">(<?= $p['nb_eleves'] ?> él.)</span></div>
      <div class="perf-bar-wrap"><div class="perf-bar-fill" style="width:<?= $pct ?>%;background:<?= $col ?>"></div></div>
      <div class="perf-score" style="color:<?= $col ?>"><?= $p['score_moyen'] ?>%</div>
    </div>
    <?php endforeach; ?>
    <?php else: ?>
    <div style="text-align:center;padding:24px;color:var(--gris-400);font-size:13px">Aucune donnée de performance disponible.</div>
    <?php endif; ?>
  </div>

  <!-- Devoirs à corriger -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="vertical-align:-2px;margin-right:6px"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        Devoirs à corriger
      </div>
    </div>
    <?php if ($devoirsACorreger): ?>
    <div style="display:flex;flex-direction:column;gap:8px">
    <?php foreach ($devoirsACorreger as $dv):
        $restant = $dv['nb_soumissions'] - $dv['nb_corriges'];
    ?>
    <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:var(--gris-50);border:1px solid var(--gris-200);border-radius:9px">
      <div style="flex:1;min-width:0">
        <div style="font-size:13px;font-weight:600;color:var(--gris-900);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($dv['titre']) ?></div>
        <div style="font-size:11px;color:var(--gris-500)"><?= e($dv['classe_nom']) ?> · <?= $restant ?> à corriger</div>
      </div>
      <a href="/reussiteplus/ecole_correction.php?devoir=<?= e($dv['devoir_id']) ?>" class="btn btn-primary btn-sm">Corriger</a>
    </div>
    <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:24px;color:var(--gris-400);font-size:13px">Aucun devoir en attente de correction.</div>
    <?php endif; ?>
  </div>

  <!-- Classes assignées -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="vertical-align:-2px;margin-right:6px"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Classes assignées
      </div>
    </div>
    <?php if ($classesAssignees): ?>
    <?php foreach ($classesAssignees as $cl): ?>
    <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--gris-100)">
      <div style="width:38px;height:38px;border-radius:10px;background:var(--primary-subtle);display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2.5" stroke-linecap="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
      </div>
      <div style="flex:1">
        <div style="font-size:13.5px;font-weight:700;color:var(--gris-900)"><?= e($cl['nom']) ?></div>
        <div style="font-size:11px;color:var(--gris-500)"><?= $cl['nb_eleves'] ?> élève<?= $cl['nb_eleves']!=1?'s':'' ?> · <?= e($cl['matieres'] ?? '—') ?></div>
      </div>
      <a href="/reussiteplus/ecole_eleves.php?classe=<?= e($cl['id']) ?>" class="btn btn-ghost btn-sm">Voir</a>
    </div>
    <?php endforeach; ?>
    <?php else: ?>
    <div style="text-align:center;padding:24px;color:var(--gris-400);font-size:13px">Aucune classe assignée.</div>
    <?php endif; ?>
  </div>

  <!-- Élèves en difficulté -->
  <?php if ($elevesEnDifficulte): ?>
  <div class="card">
    <div class="card-header">
      <div class="card-title" style="color:#C9342A">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="vertical-align:-2px;margin-right:6px"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        Élèves en difficulté
      </div>
    </div>
    <?php foreach ($elevesEnDifficulte as $el): ?>
    <div class="alert-eleve">
      <div style="width:30px;height:30px;border-radius:50%;background:rgba(201,52,42,.12);color:#C9342A;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px;flex-shrink:0"><?= strtoupper(substr($el['prenom'],0,1)) ?></div>
      <div style="flex:1">
        <div style="font-size:13px;font-weight:600;color:var(--gris-900)"><?= e($el['prenom'] . ' ' . $el['nom']) ?></div>
        <div style="font-size:11px;color:var(--gris-500)"><?= e($el['classe_nom']) ?></div>
      </div>
      <div style="font-size:13px;font-weight:800;color:#C9342A"><?= $el['score_moyen'] ?>%</div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div>

<?php include __DIR__ . '/../includes/footer_app.php'; ?>
