<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/ia_teachers.php';

$pageTitle  = 'Corps enseignant IA';
$pageActive = 'ecole';
$user = require_login();
if ($user['plan'] !== 'ECOLE') redirect('/reussiteplus/tarifs.php');

// ── Stats par matière depuis les sessions d'examen ────────────
$matStats = dbAll(
    "SELECT m.nom as matiere, m.couleur,
            COUNT(DISTINCT es.user_id) as nb_eleves,
            COUNT(DISTINCT es.id) as nb_sessions,
            COALESCE(ROUND(AVG(es.pourcentage), 1), 0) as score_moyen
     FROM exam_sessions es
     JOIN classe_membres cm ON cm.eleve_id = es.user_id
     JOIN classes_ecole c ON c.id = cm.classe_id
     JOIN matieres m ON m.id = es.matiere_id
     WHERE c.admin_id = ? AND es.statut = 'TERMINE'
     GROUP BY m.id",
    [$user['id']]
) ?? [];
$matStatsMap = array_column($matStats, null, 'matiere');

// ── Ratings des enseignants IA ────────────────────────────────
try {
    $ratings = dbAll(
        "SELECT teacher_code,
                COUNT(*) as nb_avis,
                ROUND(AVG(note), 1) as note_moyenne,
                ROUND(AVG(clarte), 1) as clarte_moyenne,
                ROUND(AVG(aide), 1) as aide_moyenne
         FROM ia_teacher_ratings
         WHERE school_admin_id = ?
         GROUP BY teacher_code",
        [$user['id']]
    ) ?? [];
} catch (Exception $e) { $ratings = []; }
$ratingsMap = array_column($ratings, null, 'teacher_code');

// ── Stats globales école ──────────────────────────────────────
$nbEleves   = (int)(dbScalar("SELECT COUNT(DISTINCT cm.eleve_id) FROM classe_membres cm JOIN classes_ecole c ON c.id=cm.classe_id WHERE c.admin_id=? AND cm.statut='ACTIF'", [$user['id']]) ?? 0);
$nbSessions = (int)(dbScalar("SELECT COUNT(*) FROM exam_sessions es JOIN classe_membres cm ON cm.eleve_id=es.user_id JOIN classes_ecole c ON c.id=cm.classe_id WHERE c.admin_id=? AND es.statut='TERMINE'", [$user['id']]) ?? 0);
$scoreMoyGlobal = (float)(dbScalar("SELECT ROUND(AVG(u.score_moyen),1) FROM classe_membres cm JOIN classes_ecole c ON c.id=cm.classe_id JOIN utilisateurs u ON u.id=cm.eleve_id WHERE c.admin_id=? AND cm.statut='ACTIF'", [$user['id']]) ?? 0);

// Masse salariale totale
$totalSalaire = 0;
foreach (IA_TEACHERS as $teacher) {
    $stats = $matStatsMap[$teacher['matiere']] ?? [];
    $sal = calculer_salaire_virtuel($teacher, $stats);
    $totalSalaire += $sal['total'];
}

include __DIR__ . '/includes/header_app.php';
?>

<style>
/* ── Corps enseignant IA ───────────────────────────────────── */
.teacher-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); gap:18px; margin-bottom:28px; }
.teacher-card {
  background:var(--blanc); border:1px solid var(--gris-200); border-radius:18px;
  overflow:hidden; transition:box-shadow .2s, transform .2s;
  position:relative;
}
.teacher-card:hover { box-shadow:0 8px 28px rgba(0,0,0,.1); transform:translateY(-2px); }
.teacher-card-top {
  padding:22px 20px 16px; display:flex; align-items:flex-start; gap:14px;
  border-bottom:1px solid var(--gris-100);
  background:linear-gradient(135deg, var(--gris-50), #fff);
}
.teacher-av {
  width:52px; height:52px; border-radius:14px;
  display:flex; align-items:center; justify-content:center;
  font-size:22px; flex-shrink:0;
  box-shadow:0 3px 10px rgba(0,0,0,.12);
}
.teacher-name { font-size:15px; font-weight:800; color:var(--gris-900); margin-bottom:2px; }
.teacher-titre { font-size:11.5px; color:var(--gris-500); margin-bottom:6px; }
.teacher-badge {
  display:inline-flex; align-items:center; gap:4px;
  font-size:9.5px; font-weight:700; padding:2px 9px; border-radius:20px;
  text-transform:uppercase; letter-spacing:.5px;
}
.teacher-stats { display:grid; grid-template-columns:1fr 1fr 1fr; gap:0; padding:12px 0; }
.teacher-stat { text-align:center; padding:8px 6px; border-right:1px solid var(--gris-100); }
.teacher-stat:last-child { border-right:none; }
.teacher-stat-val { font-size:18px; font-weight:900; line-height:1.1; }
.teacher-stat-lbl { font-size:9.5px; color:var(--gris-500); margin-top:2px; text-transform:uppercase; letter-spacing:.3px; }
.teacher-footer { padding:12px 18px; display:flex; align-items:center; justify-content:space-between; gap:8px; border-top:1px solid var(--gris-100); background:var(--gris-50); }
.salaire-badge {
  display:flex; align-items:center; gap:5px;
  background:rgba(0,122,94,.1); border:1px solid rgba(0,122,94,.2);
  border-radius:8px; padding:5px 10px;
  font-size:11.5px; font-weight:700; color:#007A5E;
}
.perf-bar-wrap { height:4px; background:var(--gris-200); border-radius:99px; overflow:hidden; margin-top:6px; }
.perf-bar-fill { height:100%; border-radius:99px; transition:width .6s; }
.stars { display:flex; gap:1px; }
.star { font-size:12px; }
.ia-online-badge {
  position:absolute; top:14px; right:14px;
  display:flex; align-items:center; gap:4px;
  background:rgba(34,197,94,.15); border:1px solid rgba(34,197,94,.3);
  border-radius:20px; padding:2px 9px; font-size:10px; font-weight:700; color:#15803d;
}
.ia-online-dot { width:5px; height:5px; border-radius:50%; background:#22c55e; animation:iaPulse 2s infinite; }
@keyframes iaPulse { 0%,100%{box-shadow:0 0 0 0 rgba(34,197,94,.4)} 50%{box-shadow:0 0 0 4px rgba(34,197,94,0)} }

/* Direction KPI */
.dir-kpi { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:24px; }
@media(max-width:768px){ .dir-kpi{grid-template-columns:repeat(2,1fr)} .teacher-grid{grid-template-columns:1fr} }
</style>

<!-- Hero direction -->
<div style="background:linear-gradient(135deg,#0a1628 0%,#003D2E 100%);border-radius:var(--radius-xl);padding:26px 28px;margin-bottom:22px;position:relative;overflow:hidden">
  <div style="position:absolute;top:-30px;right:-30px;width:180px;height:180px;border-radius:50%;background:radial-gradient(circle,rgba(0,122,94,.15) 0%,transparent 70%)"></div>
  <div style="position:relative;z-index:1;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px">
    <div>
      <div style="font-size:11px;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.6px;margin-bottom:6px">
        <a href="/reussiteplus/ecole.php" style="color:rgba(255,255,255,.4);text-decoration:none">Mon École</a> / Corps enseignant IA
      </div>
      <div style="font-size:22px;font-weight:900;color:#fff;margin-bottom:4px">Corps Enseignant Virtuel IA</div>
      <div style="font-size:13px;color:rgba(255,255,255,.5)"><?= count(IA_TEACHERS) ?> professeurs IA · Propulsé par Gemini · Disponibles 24h/24</div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <div style="text-align:center;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:10px 16px">
        <div style="font-size:20px;font-weight:900;color:#6EE7B7"><?= $nbEleves ?></div>
        <div style="font-size:9px;color:rgba(255,255,255,.4);text-transform:uppercase">Élèves suivis</div>
      </div>
      <div style="text-align:center;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:10px 16px">
        <div style="font-size:20px;font-weight:900;color:#FCD34D"><?= $nbSessions ?></div>
        <div style="font-size:9px;color:rgba(255,255,255,.4);text-transform:uppercase">Cours dispensés</div>
      </div>
      <div style="text-align:center;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:10px 16px">
        <div style="font-size:20px;font-weight:900;color:#A78BFA"><?= number_format($totalSalaire/1000) ?>k</div>
        <div style="font-size:9px;color:rgba(255,255,255,.4);text-transform:uppercase">Masse salariale CDF</div>
      </div>
    </div>
  </div>
</div>

<!-- KPI direction -->
<div class="dir-kpi">
  <div class="card" style="text-align:center;padding:16px">
    <div style="font-size:26px;font-weight:900;color:var(--primary)"><?= count(IA_TEACHERS) ?></div>
    <div style="font-size:10.5px;color:var(--gris-500);text-transform:uppercase;letter-spacing:.4px;margin-top:3px">Professeurs IA</div>
  </div>
  <div class="card" style="text-align:center;padding:16px">
    <div style="font-size:26px;font-weight:900;color:#1E5FAD"><?= $nbEleves ?></div>
    <div style="font-size:10.5px;color:var(--gris-500);text-transform:uppercase;letter-spacing:.4px;margin-top:3px">Élèves actifs</div>
  </div>
  <div class="card" style="text-align:center;padding:16px">
    <div style="font-size:26px;font-weight:900;color:<?= $scoreMoyGlobal >= 70 ? '#007A5E' : ($scoreMoyGlobal >= 50 ? '#C9972A' : '#C9342A') ?>"><?= $scoreMoyGlobal ?>%</div>
    <div style="font-size:10.5px;color:var(--gris-500);text-transform:uppercase;letter-spacing:.4px;margin-top:3px">Performance école</div>
  </div>
  <div class="card" style="text-align:center;padding:16px">
    <div style="font-size:26px;font-weight:900;color:#7C3AED"><?= number_format($totalSalaire) ?></div>
    <div style="font-size:10.5px;color:var(--gris-500);text-transform:uppercase;letter-spacing:.4px;margin-top:3px">Masse salariale (CDF)</div>
  </div>
</div>

<!-- Cartes enseignants -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
  <div class="section-title">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="vertical-align:-2px;margin-right:6px"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    Corps enseignant — <?= count(IA_TEACHERS) ?> professeurs
  </div>
  <span style="font-size:12px;color:var(--gris-400)">Tous disponibles 24h/24 · Propulsés par Gemini IA</span>
</div>

<div class="teacher-grid">
<?php foreach (IA_TEACHERS as $key => $teacher):
    $stats    = $matStatsMap[$teacher['matiere']] ?? ['nb_eleves'=>0,'nb_sessions'=>0,'score_moyen'=>0];
    $sal      = calculer_salaire_virtuel($teacher, $stats);
    $rating   = $ratingsMap[$teacher['id']] ?? ['note_moyenne'=>null,'nb_avis'=>0,'clarte_moyenne'=>null];
    $scorePct = min(100, (float)$stats['score_moyen']);
?>
<div class="teacher-card">
  <div class="ia-online-badge">
    <div class="ia-online-dot"></div>
    En ligne
  </div>

  <div class="teacher-card-top">
    <div class="teacher-av" style="background:<?= $teacher['avatar_bg'] ?>;color:#fff;font-size:18px;font-weight:900;font-family:var(--font-display)"><?= $teacher['avatar_initial'] ?? strtoupper(substr($teacher['prenom'],0,1)) ?></div>
    <div style="flex:1;min-width:0">
      <div class="teacher-name">Prof. <?= e($teacher['prenom'] . ' ' . $teacher['nom']) ?></div>
      <div class="teacher-titre"><?= e($teacher['titre']) ?></div>
      <span class="teacher-badge" style="background:<?= $teacher['avatar_bg'] ?>18;color:<?= $teacher['avatar_bg'] ?>">
        <?= e($teacher['matiere']) ?>
      </span>
    </div>
  </div>

  <!-- Stats -->
  <div class="teacher-stats">
    <div class="teacher-stat">
      <div class="teacher-stat-val" style="color:var(--primary)"><?= $stats['nb_eleves'] ?></div>
      <div class="teacher-stat-lbl">Élèves</div>
    </div>
    <div class="teacher-stat">
      <div class="teacher-stat-val" style="color:#1E5FAD"><?= $stats['nb_sessions'] ?></div>
      <div class="teacher-stat-lbl">Sessions</div>
    </div>
    <div class="teacher-stat">
      <div class="teacher-stat-val" style="color:<?= $sal['color_perf'] ?>"><?= $stats['score_moyen'] > 0 ? $stats['score_moyen'].'%' : '—' ?></div>
      <div class="teacher-stat-lbl">Résultats</div>
    </div>
  </div>

  <!-- Performance bar -->
  <?php if ($scorePct > 0): ?>
  <div style="padding:0 18px 10px">
    <div style="display:flex;justify-content:space-between;font-size:10.5px;color:var(--gris-500);margin-bottom:4px">
      <span>Performance pédagogique</span>
      <span style="font-weight:700;color:<?= $sal['color_perf'] ?>"><?= $sal['note_perf'] ?></span>
    </div>
    <div class="perf-bar-wrap">
      <div class="perf-bar-fill" style="width:<?= $scorePct ?>%;background:<?= $sal['color_perf'] ?>"></div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Rating élèves -->
  <?php if ($rating['note_moyenne'] !== null): ?>
  <div style="padding:6px 18px 10px;display:flex;align-items:center;gap:8px">
    <div class="stars">
      <?php $note = (float)$rating['note_moyenne'];
      for ($i=1; $i<=5; $i++): ?>
      <span class="star" style="color:<?= $i <= round($note) ? '#FBBF24' : '#E5E7EB' ?>">★</span>
      <?php endfor; ?>
    </div>
    <span style="font-size:11px;font-weight:700;color:var(--gris-700)"><?= $note ?>/5</span>
    <span style="font-size:10.5px;color:var(--gris-400)">(<?= $rating['nb_avis'] ?> avis)</span>
  </div>
  <?php endif; ?>

  <!-- Footer : salaire + action -->
  <div class="teacher-footer">
    <div class="salaire-badge">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
      <?= number_format($sal['total']) ?> CDF
    </div>
    <a href="/reussiteplus/ecole_ia.php?prof=<?= e($key) ?>" class="btn btn-primary btn-sm">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="vertical-align:-1px;margin-right:4px"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      Consulter
    </a>
  </div>
</div>
<?php endforeach; ?>
</div>

<!-- Info salaires -->
<div style="background:var(--gris-50);border:1px solid var(--gris-200);border-radius:12px;padding:16px 20px;font-size:12.5px;color:var(--gris-600);display:flex;align-items:flex-start;gap:10px">
  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--gris-400)" stroke-width="2" stroke-linecap="round" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
  <span>Les salaires sont <strong>virtuels et simulés</strong> — ils reflètent l'activité pédagogique de chaque enseignant IA (nombre d'élèves suivis, sessions, performance). Aucun vrai versement n'est effectué. Ce système crée une logique institutionnelle réaliste.</span>
</div>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
