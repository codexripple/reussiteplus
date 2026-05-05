<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Ma Progression';
$pageActive = 'progression';
$user = require_login();

// Stats globales
$stats = get_user_stats($user['id']);

// Progression par matière (complète)
$progressMatieres = dbAll(
    "SELECT up.*, m.nom, m.couleur, m.icone, m.code
     FROM user_progression up
     JOIN matieres m ON up.matiere_id = m.id
     WHERE up.user_id = ?
     ORDER BY up.score_moyen DESC",
    [$user['id']]
);

// Historique des 20 dernières sessions
$historique = dbAll(
    "SELECT es.*, m.nom as matiere_nom, m.couleur
     FROM exam_sessions es
     LEFT JOIN matieres m ON es.matiere_id = m.id
     WHERE es.user_id = ? AND es.statut = 'TERMINE'
     ORDER BY es.finished_at DESC LIMIT 20",
    [$user['id']]
);

// Activité des 30 derniers jours
$activite30j = dbAll(
    "SELECT date_act, examens, questions
     FROM activite_journaliere
     WHERE user_id = ? AND date_act >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
     ORDER BY date_act ASC",
    [$user['id']]
);

// Construire la map activité pour les 30 jours
$actMap = [];
foreach ($activite30j as $a) $actMap[$a['date_act']] = $a;

// Classement provincial
$classement = null;
if ($user['province_id']) {
    $classement = dbRow(
        "SELECT COUNT(*) + 1 as rang FROM utilisateurs
         WHERE province_id = ? AND score_moyen > ? AND is_active = 1",
        [$user['province_id'], $user['score_moyen'] ?? 0]
    );
}

include __DIR__ . '/includes/header_app.php';
?>

<!-- Stats globales -->
<div class="stats-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:24px">
  <div class="stat-card green">
    <div class="stat-label"><i data-lucide="trending-up" style="width:13px;height:13px;vertical-align:middle;margin-right:4px"></i> Score moyen</div>
    <div class="stat-value" style="color:<?= score_couleur((float)($user['score_moyen']??0)) ?>"><?= number_format((float)($user['score_moyen']??0),1) ?>%</div>
    <div class="stat-sub"><?= score_label((float)($user['score_moyen']??0)) ?></div>
  </div>
  <div class="stat-card gold">
    <div class="stat-label"><i data-lucide="file-text" style="width:13px;height:13px;vertical-align:middle;margin-right:4px"></i> Examens</div>
    <div class="stat-value"><?= number_format((int)($user['total_examens']??0)) ?></div>
    <div class="stat-sub">Au total</div>
  </div>
  <div class="stat-card bleu">
    <div class="stat-label"><i data-lucide="help-circle" style="width:13px;height:13px;vertical-align:middle;margin-right:4px"></i> Questions</div>
    <div class="stat-value"><?= number_format((int)($user['total_questions']??0)) ?></div>
    <div class="stat-sub">Répondues</div>
  </div>
  <div class="stat-card rouge">
    <div class="stat-label"><i data-lucide="flame" style="width:13px;height:13px;vertical-align:middle;margin-right:4px"></i> Série</div>
    <div class="stat-value"><?= (int)($stats['streak_actuel']??0) ?></div>
    <div class="stat-sub">jours consécutifs</div>
  </div>
  <?php if ($classement): ?>
  <div class="stat-card" style="--accent-color:var(--gold)">
    <div class="stat-label"><i data-lucide="trophy" style="width:13px;height:13px;vertical-align:middle;margin-right:4px"></i> Classement</div>
    <div class="stat-value">#<?= (int)$classement['rang'] ?></div>
    <div class="stat-sub">Dans ma province</div>
  </div>
  <?php else: ?>
  <div class="stat-card">
    <div class="stat-label"><i data-lucide="calendar" style="width:13px;height:13px;vertical-align:middle;margin-right:4px"></i> Membre depuis</div>
    <div class="stat-value" style="font-size:18px"><?= date('m/Y', strtotime($user['created_at'])) ?></div>
    <div class="stat-sub"><?= floor((time()-strtotime($user['created_at']))/86400) ?> jours</div>
  </div>
  <?php endif; ?>
</div>

<!-- Calendrier activité 30j -->
<div class="card" style="margin-bottom:24px">
  <div class="card-header">
    <div class="card-title"><i data-lucide="calendar" style="width:15px;height:15px;vertical-align:middle;margin-right:6px"></i> Activité — 30 derniers jours</div>
    <div style="font-size:12px;color:var(--gris-500)"><?= count($activite30j) ?> jours actifs</div>
  </div>
  <div style="display:flex;gap:4px;align-items:flex-end;height:60px;overflow-x:auto;padding-bottom:4px">
    <?php
    $maxEx = max(1, max(array_map(fn($a) => $a['examens'], $activite30j ?: [['examens'=>0]])));
    for ($i = 29; $i >= 0; $i--):
        $d   = date('Y-m-d', strtotime("-{$i} days"));
        $act = $actMap[$d] ?? null;
        $ex  = $act ? $act['examens'] : 0;
        $h   = $ex > 0 ? max(12, (int)(($ex / $maxEx) * 56)) : 4;
        $color = $ex > 0 ? 'var(--primary)' : 'var(--gris-200)';
        $title = date('d/m', strtotime($d)) . ' — ' . $ex . ' examen(s)';
    ?>
    <div title="<?= e($title) ?>" style="flex:1;min-width:6px;height:<?= $h ?>px;background:<?= $color ?>;border-radius:3px;cursor:default;transition:opacity .2s" onmouseover="this.style.opacity='.7'" onmouseout="this.style.opacity='1'"></div>
    <?php endfor; ?>
  </div>
  <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--gris-400);margin-top:6px">
    <span><?= date('d/m', strtotime('-29 days')) ?></span>
    <span>Aujourd'hui</span>
  </div>
</div>

<!-- Progression par matière -->
<?php if ($progressMatieres): ?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px">
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i data-lucide="book-open" style="width:15px;height:15px;vertical-align:middle;margin-right:6px"></i> Progression par matière</div>
    </div>
    <?php foreach ($progressMatieres as $pm):
      $pct2 = (float)$pm['score_moyen'];
    ?>
    <div style="margin-bottom:14px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px">
        <span style="font-size:13px;color:var(--gris-800);font-weight:500">
          <i data-lucide="book" style="width:13px;height:13px;vertical-align:middle;margin-right:4px;opacity:.6"></i><?= e($pm['nom']) ?>
        </span>
        <div style="display:flex;gap:8px;align-items:center">
          <span style="font-size:11px;color:var(--gris-500)"><?= number_format($pm['questions_vues']) ?> q.</span>
          <span style="font-weight:700;font-size:13px;color:<?= score_couleur($pct2) ?>"><?= number_format($pct2, 1) ?>%</span>
        </div>
      </div>
      <div class="progress-bar">
        <div class="progress-bar-fill" style="width:<?= min(100,$pct2) ?>%;background:<?= e($pm['couleur']??'var(--primary)') ?>"></div>
      </div>
    </div>
    <?php endforeach; ?>
    <a href="/reussiteplus/questions.php" class="btn btn-primary btn-full" style="margin-top:8px"><i data-lucide="play-circle" style="width:14px;height:14px;vertical-align:middle;margin-right:6px"></i> S'entraîner maintenant</a>
  </div>

  <!-- Radar chart simulé par tableau -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i data-lucide="bar-chart-2" style="width:15px;height:15px;vertical-align:middle;margin-right:6px"></i> Analyse des forces</div>
    </div>
    <?php
    $top = array_slice($progressMatieres, 0, 3);
    $low = array_slice(array_reverse($progressMatieres), 0, 3);
    ?>
    <div style="margin-bottom:16px">
      <div style="font-size:12px;font-weight:600;color:var(--primary);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px"><i data-lucide="zap" style="width:12px;height:12px;vertical-align:middle;margin-right:4px"></i> Points forts</div>
      <?php foreach ($top as $t): ?>
      <div style="display:flex;justify-content:space-between;font-size:13px;padding:4px 0;border-bottom:1px solid var(--gris-100)">
        <span><i data-lucide="book" style="width:12px;height:12px;vertical-align:middle;margin-right:4px;opacity:.5"></i><?= e($t['nom']) ?></span>
        <span style="color:var(--primary);font-weight:600"><?= number_format((float)$t['score_moyen'],1) ?>%</span>
      </div>
      <?php endforeach; ?>
    </div>
    <div>
      <div style="font-size:12px;font-weight:600;color:var(--rouge);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px"><i data-lucide="alert-triangle" style="width:12px;height:12px;vertical-align:middle;margin-right:4px"></i> À renforcer</div>
      <?php foreach ($low as $l): ?>
      <div style="display:flex;justify-content:space-between;font-size:13px;padding:4px 0;border-bottom:1px solid var(--gris-100)">
        <span><i data-lucide="book" style="width:12px;height:12px;vertical-align:middle;margin-right:4px;opacity:.5"></i><?= e($l['nom']) ?></span>
        <span style="color:var(--rouge);font-weight:600"><?= number_format((float)$l['score_moyen'],1) ?>%</span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php if ($user['plan'] === 'GRATUIT'): ?>
    <div style="margin-top:12px;background:var(--gold-light);border-radius:8px;padding:10px;font-size:12px;color:var(--gold-dark);text-align:center">
      <i data-lucide="crown" style="width:12px;height:12px;vertical-align:middle;margin-right:4px"></i> <a href="/reussiteplus/tarifs.php" style="color:var(--gold-dark);font-weight:600">Premium</a> : Plan de révision IA personnalisé
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- Historique des examens -->
<div class="section-header">
  <div class="section-title"><i data-lucide="clock" style="width:16px;height:16px;vertical-align:middle;margin-right:6px"></i> Historique des examens</div>
  <div style="font-size:13px;color:var(--gris-500)"><?= count($historique) ?> examens passés</div>
</div>

<?php if ($historique): ?>
<div class="table-wrap">
  <table class="table">
    <thead>
      <tr><th>Matière</th><th>Type</th><th>Score</th><th>Questions</th><th>Temps</th><th>Date</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($historique as $s):
      $pct3 = (float)($s['pourcentage']??0);
      $m = floor(($s['temps_passe']??0)/60);
      $sec = ($s['temps_passe']??0)%60;
    ?>
    <tr>
      <td>
        <span style="display:flex;align-items:center;gap:6px">
          <span style="width:8px;height:8px;border-radius:50%;background:<?= e($s['couleur']??'var(--primary)') ?>;display:inline-block;flex-shrink:0"></span>
          <?= e($s['matiere_nom'] ?? $s['titre'] ?? 'Examen') ?>
        </span>
      </td>
      <td><span class="badge badge-gray" style="font-size:10px"><?= e($s['exam_type']??'—') ?></span></td>
      <td>
        <span style="font-weight:700;color:<?= score_couleur($pct3) ?>"><?= number_format($pct3,1) ?>%</span>
        <div style="font-size:10px;color:var(--gris-500)"><?= score_label($pct3) ?></div>
      </td>
      <td style="font-size:12px;color:var(--gris-600)"><?= (int)$s['nb_questions'] ?> q.</td>
      <td style="font-size:12px;color:var(--gris-600)"><?= $m ?>m <?= str_pad($sec,2,'0',STR_PAD_LEFT) ?>s</td>
      <td style="font-size:12px;color:var(--gris-500)"><?= date('d/m/Y', strtotime($s['finished_at']??$s['started_at'])) ?></td>
      <td><a href="/reussiteplus/resultat.php?session=<?= e($s['id']) ?>" class="btn btn-ghost btn-sm">Voir</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php else: ?>
<div class="card" style="text-align:center;padding:40px">
  <div style="font-size:48px;margin-bottom:12px">📊</div>
  <div style="font-size:15px;font-weight:600;margin-bottom:8px">Aucun examen passé</div>
  <div style="font-size:13px;color:var(--gris-500);margin-bottom:20px">Commencez par passer votre premier examen !</div>
  <a href="/reussiteplus/examen.php" class="btn btn-primary">✏️ Passer un examen maintenant</a>
</div>
<?php endif; ?>

<?php $hasIA = (bool)(PLANS[$user['plan']]['ia'] ?? false); ?>

<!-- ══════════════════════════════════════════════════════════
     SECTION IA — Tuteur + Plan de révision + Analyse erreurs
══════════════════════════════════════════════════════════ -->
<div style="margin-top:32px">

<?php if ($hasIA): ?>
<style>
.ia-hero {
  background:linear-gradient(135deg,#13111C 0%,#1E1B2E 60%,#1A0A2E 100%);
  border-radius:20px;padding:28px 28px 0;margin-bottom:24px;overflow:hidden;position:relative;
}
.ia-hero::before {
  content:'';position:absolute;top:-60px;right:-60px;
  width:250px;height:250px;border-radius:50%;
  background:radial-gradient(circle,rgba(124,58,237,.35) 0%,transparent 70%);
  pointer-events:none;
}
.ia-hero-inner { display:flex;align-items:flex-start;gap:20px;position:relative;z-index:1; }
.ia-avatar {
  width:54px;height:54px;border-radius:16px;flex-shrink:0;
  background:linear-gradient(135deg,#7C3AED,#4F46E5);
  display:flex;align-items:center;justify-content:center;
  box-shadow:0 4px 20px rgba(124,58,237,.5);
}
.ia-hero-text h2 { font-family:var(--font-display);font-size:20px;font-weight:800;color:#fff;margin-bottom:4px; }
.ia-hero-text p { font-size:13px;color:rgba(255,255,255,.55);line-height:1.6;max-width:440px; }
.ia-hero-actions { display:flex;gap:10px;flex-wrap:wrap;margin-top:20px;padding-bottom:24px; }
.ia-action-btn {
  display:inline-flex;align-items:center;gap:7px;padding:10px 18px;
  border-radius:10px;font-family:var(--font-display);font-size:13px;font-weight:700;
  cursor:pointer;transition:all .18s;border:none;
}
.ia-action-btn:hover { transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,.25); }
.ia-action-btn:active { transform:translateY(0); }
.ia-action-btn.ia-primary { background:linear-gradient(135deg,#7C3AED,#4F46E5);color:#fff; }
.ia-action-btn.ia-secondary { background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);color:rgba(255,255,255,.85); }
.ia-action-btn.ia-secondary:hover { background:rgba(255,255,255,.15); }
.ia-action-btn:disabled { opacity:.55;cursor:not-allowed;transform:none !important; }
@keyframes ia-spin { to { transform:rotate(360deg); } }
.ia-spin { animation:ia-spin .8s linear infinite;display:inline-block; }

.ia-result {
  background:#13111C;border:1px solid rgba(124,58,237,.25);border-radius:16px;
  padding:22px 24px;margin-bottom:24px;display:none;
}
.ia-result-header { display:flex;align-items:center;justify-content:space-between;margin-bottom:16px; }
.ia-result-title { font-size:13px;font-weight:700;color:#C4B5FD;display:flex;align-items:center;gap:7px; }
.ia-result-body {
  font-size:13px;line-height:1.8;color:rgba(255,255,255,.8);
  white-space:pre-wrap;max-height:500px;overflow-y:auto;
}
.ia-result-body::-webkit-scrollbar { width:4px; }
.ia-result-body::-webkit-scrollbar-thumb { background:rgba(124,58,237,.4);border-radius:4px; }

.ia-chat-wrap {
  background:#13111C;border:1px solid rgba(124,58,237,.2);border-radius:20px;overflow:hidden;margin-bottom:24px;
}
.ia-chat-header {
  padding:14px 20px;border-bottom:1px solid rgba(255,255,255,.07);
  display:flex;align-items:center;justify-content:space-between;
}
.ia-chat-title { display:flex;align-items:center;gap:9px;font-size:14px;font-weight:700;color:#fff; }
.ia-online-dot { width:8px;height:8px;border-radius:50%;background:#22C55E;animation:ia-pulse 2s infinite; }
@keyframes ia-pulse { 0%,100%{box-shadow:0 0 0 0 rgba(34,197,94,.4)}50%{box-shadow:0 0 0 5px rgba(34,197,94,0)} }
.ia-chat-messages {
  min-height:280px;max-height:420px;overflow-y:auto;padding:16px 20px;
  display:flex;flex-direction:column;gap:12px;
}
.ia-chat-messages::-webkit-scrollbar { width:4px; }
.ia-chat-messages::-webkit-scrollbar-thumb { background:rgba(124,58,237,.35);border-radius:4px; }
.ia-msg { display:flex;gap:10px;max-width:85%; }
.ia-msg.user { align-self:flex-end;flex-direction:row-reverse; }
.ia-msg-avatar {
  width:30px;height:30px;border-radius:9px;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;
}
.ia-msg.bot .ia-msg-avatar { background:linear-gradient(135deg,#7C3AED,#4F46E5); }
.ia-msg.user .ia-msg-avatar { background:rgba(255,255,255,.1);color:rgba(255,255,255,.8); }
.ia-msg-bubble {
  padding:10px 14px;border-radius:12px;font-size:13px;line-height:1.65;
  white-space:pre-wrap;word-break:break-word;
}
.ia-msg.bot .ia-msg-bubble { background:rgba(124,58,237,.18);color:rgba(255,255,255,.88);border-radius:4px 12px 12px 12px; }
.ia-msg.user .ia-msg-bubble { background:rgba(255,255,255,.1);color:rgba(255,255,255,.85);border-radius:12px 4px 12px 12px; }
.ia-typing { display:none;align-self:flex-start;gap:10px;max-width:85%; }
.ia-typing.visible { display:flex; }
.ia-dot-anim { display:flex;gap:4px;padding:12px 14px;background:rgba(124,58,237,.18);border-radius:4px 12px 12px 12px; }
.ia-dot-anim span { width:6px;height:6px;background:#C4B5FD;border-radius:50%;animation:ia-bounce .9s infinite; }
.ia-dot-anim span:nth-child(2){animation-delay:.15s}
.ia-dot-anim span:nth-child(3){animation-delay:.3s}
@keyframes ia-bounce{0%,60%,100%{transform:translateY(0)}30%{transform:translateY(-6px)}}
.ia-chat-input-row {
  padding:12px 16px;border-top:1px solid rgba(255,255,255,.07);
  display:flex;gap:8px;align-items:flex-end;
}
.ia-chat-input {
  flex:1;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);
  border-radius:12px;padding:10px 14px;font-size:13px;color:#fff;resize:none;
  font-family:var(--font-body);line-height:1.5;max-height:100px;
  transition:border-color .15s;
}
.ia-chat-input::placeholder { color:rgba(255,255,255,.3); }
.ia-chat-input:focus { outline:none;border-color:rgba(124,58,237,.6); }
.ia-send-btn {
  width:40px;height:40px;border-radius:11px;border:none;
  background:linear-gradient(135deg,#7C3AED,#4F46E5);cursor:pointer;
  display:flex;align-items:center;justify-content:center;transition:all .15s;flex-shrink:0;
}
.ia-send-btn:hover { transform:scale(1.05);box-shadow:0 4px 12px rgba(124,58,237,.4); }
.ia-send-btn:disabled { opacity:.45;cursor:not-allowed;transform:none; }
.ia-quick-chips { display:flex;gap:7px;flex-wrap:wrap;padding:0 16px 12px; }
.ia-chip {
  font-size:11px;font-weight:600;padding:5px 11px;border-radius:20px;
  background:rgba(124,58,237,.15);border:1px solid rgba(124,58,237,.3);
  color:#C4B5FD;cursor:pointer;transition:all .15s;white-space:nowrap;
}
.ia-chip:hover { background:rgba(124,58,237,.3);border-color:#7C3AED; }
</style>

<!-- IA Hero -->
<div class="ia-hero">
  <div class="ia-hero-inner">
    <div class="ia-avatar">
      <i data-lucide="sparkles" style="width:26px;height:26px;stroke:#fff"></i>
    </div>
    <div class="ia-hero-text">
      <h2>Assistant IA — RÉUSSITE+</h2>
      <p>Tuteur intelligent LLaMA 3, disponible 24h/24. Pose-lui toutes tes questions, génère un plan de révision hebdomadaire ou analyse tes erreurs récurrentes.</p>
    </div>
  </div>
  <div class="ia-hero-actions">
    <button class="ia-action-btn ia-primary" onclick="genererPlan()" id="btn-plan">
      <i data-lucide="calendar-check" style="width:15px;height:15px;stroke:#fff"></i>
      Générer mon plan de révision
    </button>
    <button class="ia-action-btn ia-secondary" onclick="analyserErreurs()" id="btn-erreurs">
      <i data-lucide="flame" style="width:15px;height:15px"></i>
      Analyser mes erreurs
    </button>
    <button class="ia-action-btn ia-secondary" onclick="ouvrirChat()" id="btn-chat">
      <i data-lucide="message-circle" style="width:15px;height:15px"></i>
      Ouvrir le chat IA
    </button>
  </div>
</div>

<!-- Résultat plan / erreurs -->
<div class="ia-result" id="ia-result">
  <div class="ia-result-header">
    <div class="ia-result-title">
      <i data-lucide="sparkles" style="width:14px;height:14px;stroke:#C4B5FD"></i>
      <span id="ia-result-title-text">Résultat</span>
    </div>
    <button onclick="document.getElementById('ia-result').style.display='none'" style="background:none;border:none;cursor:pointer;color:rgba(255,255,255,.4)" title="Fermer">
      <i data-lucide="x" style="width:16px;height:16px;stroke:rgba(255,255,255,.5)"></i>
    </button>
  </div>
  <div class="ia-result-body" id="ia-result-body"></div>
</div>

<!-- Chat IA -->
<div class="ia-chat-wrap" id="ia-chat" style="display:none">
  <div class="ia-chat-header">
    <div class="ia-chat-title">
      <div class="ia-online-dot"></div>
      <i data-lucide="sparkles" style="width:15px;height:15px;stroke:#C4B5FD"></i>
      RÉUSSITE+IA — Tuteur intelligent
    </div>
    <button onclick="fermerChat()" style="background:none;border:none;cursor:pointer" title="Fermer">
      <i data-lucide="x" style="width:16px;height:16px;stroke:rgba(255,255,255,.45)"></i>
    </button>
  </div>
  <div class="ia-quick-chips">
    <span class="ia-chip" onclick="sendChip(this)">Explique-moi les fractions</span>
    <span class="ia-chip" onclick="sendChip(this)">Quelle est la loi d'Ohm ?</span>
    <span class="ia-chip" onclick="sendChip(this)">Les temps de conjugaison</span>
    <span class="ia-chip" onclick="sendChip(this)">Aide-moi pour mon examen d'État</span>
    <span class="ia-chip" onclick="sendChip(this)">Révision rapide Maths</span>
  </div>
  <div class="ia-chat-messages" id="ia-messages">
    <div class="ia-msg bot">
      <div class="ia-msg-avatar" style="background:linear-gradient(135deg,#7C3AED,#4F46E5)">
        <i data-lucide="sparkles" style="width:14px;height:14px;stroke:#fff"></i>
      </div>
      <div class="ia-msg-bubble">Salut <?= e($user['prenom']) ?> ! Je suis ton tuteur IA. Je suis là pour t'aider à préparer tes examens (ENAFEP, TENASOSP, Examen d'État).

Tu peux me poser des questions sur :
— Mathématiques, Physique, Chimie
— Français, Sciences, Biologie
— Histoire-Géographie, Anglais

Que veux-tu réviser aujourd'hui ?</div>
    </div>
    <div class="ia-typing" id="ia-typing">
      <div class="ia-msg-avatar" style="background:linear-gradient(135deg,#7C3AED,#4F46E5);width:30px;height:30px;border-radius:9px;flex-shrink:0;display:flex;align-items:center;justify-content:center">
        <i data-lucide="sparkles" style="width:14px;height:14px;stroke:#fff"></i>
      </div>
      <div class="ia-dot-anim"><span></span><span></span><span></span></div>
    </div>
  </div>
  <div class="ia-chat-input-row">
    <textarea class="ia-chat-input" id="ia-input" placeholder="Pose ta question..." rows="1" onkeydown="iaKeydown(event)" oninput="autoResize(this)"></textarea>
    <button class="ia-send-btn" id="ia-send" onclick="sendChatMsg()" title="Envoyer">
      <i data-lucide="send" style="width:16px;height:16px;stroke:#fff"></i>
    </button>
  </div>
</div>

<?php else: ?>
<div style="background:linear-gradient(135deg,#13111C,#1E1B2E);border-radius:20px;padding:32px;display:flex;align-items:center;gap:24px;flex-wrap:wrap">
  <div style="width:56px;height:56px;border-radius:16px;background:linear-gradient(135deg,#7C3AED,#4F46E5);display:flex;align-items:center;justify-content:center;flex-shrink:0">
    <i data-lucide="sparkles" style="width:28px;height:28px;stroke:#fff"></i>
  </div>
  <div style="flex:1;min-width:200px">
    <div style="font-family:var(--font-display);font-size:18px;font-weight:800;color:#fff;margin-bottom:6px">Débloquez l'Assistant IA</div>
    <div style="font-size:13px;color:rgba(255,255,255,.55);line-height:1.6">Plan de révision personnalisé, analyse de vos erreurs, tuteur intelligent 24h/24. Disponible avec le plan <strong style="color:#C4B5FD">Premium</strong>.</div>
  </div>
  <a href="/reussiteplus/tarifs.php" style="display:inline-flex;align-items:center;gap:8px;background:linear-gradient(135deg,#7C3AED,#4F46E5);color:#fff;padding:12px 22px;border-radius:12px;font-family:var(--font-display);font-size:14px;font-weight:700;text-decoration:none;white-space:nowrap;transition:opacity .18s" onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'">
    <i data-lucide="crown" style="width:16px;height:16px;stroke:#fff"></i>
    Passer à Premium
  </a>
</div>
<?php endif; ?>
</div>

<?php if ($hasIA): ?>
<script>
const CSRF_TOKEN = '<?= e(csrf_token()) ?>';
let chatHistory = [];
let iaLoading   = false;

async function callIA(action, extra = {}) {
  const fd = new FormData();
  fd.append('action',     action);
  fd.append('csrf_token', CSRF_TOKEN);
  for (const [k, v] of Object.entries(extra)) fd.append(k, v);
  const r = await fetch('/reussiteplus/api/revision.php', { method:'POST', body:fd });
  return r.json();
}
function setBtnLoading(id, label) {
  const btn = document.getElementById(id);
  if (!btn) return;
  btn.disabled = true;
  btn.innerHTML = `<span class="ia-spin" style="display:inline-block"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/><line x1="2" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="22" y2="12"/><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"/><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"/></svg></span> ${label}`;
}
function resetBtn(id, svg, label) {
  const btn = document.getElementById(id);
  if (!btn) return;
  btn.disabled = false;
  btn.innerHTML = svg + ' ' + label;
}

const SVGS = {
  'calendar-check': '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><polyline points="9 16 11 18 15 14"/></svg>',
  'flame':          '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>',
  'message-circle': '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
  'x':              '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
};

function showResult(title, content) {
  const wrap = document.getElementById('ia-result');
  document.getElementById('ia-result-title-text').textContent = title;
  document.getElementById('ia-result-body').textContent = content;
  wrap.style.display = 'block';
  wrap.scrollIntoView({ behavior:'smooth', block:'start' });
}

async function genererPlan() {
  setBtnLoading('btn-plan', 'Génération en cours…');
  try {
    const d = await callIA('plan_revision');
    if (d.ok) showResult('Mon plan de révision — 7 jours', d.content);
    else alert(d.msg || d.error || 'Erreur lors de la génération.');
  } catch(e) { alert('Erreur réseau. Vérifiez votre connexion.'); }
  finally { resetBtn('btn-plan', SVGS['calendar-check'], 'Générer mon plan de révision'); }
}

async function analyserErreurs() {
  setBtnLoading('btn-erreurs', 'Analyse en cours…');
  try {
    const d = await callIA('analyse_erreurs');
    if (d.ok) showResult('Analyse de mes erreurs', d.content);
    else alert(d.msg || d.error || 'Erreur lors de l\'analyse.');
  } catch(e) { alert('Erreur réseau. Vérifiez votre connexion.'); }
  finally { resetBtn('btn-erreurs', SVGS['flame'], 'Analyser mes erreurs'); }
}

function ouvrirChat() {
  const chatEl = document.getElementById('ia-chat');
  const btn    = document.getElementById('btn-chat');
  chatEl.style.display = '';
  chatEl.scrollIntoView({ behavior:'smooth', block:'start' });
  document.getElementById('ia-input')?.focus();
  btn.innerHTML = SVGS['x'] + ' Fermer le chat';
}
function fermerChat() {
  document.getElementById('ia-chat').style.display = 'none';
  const btn = document.getElementById('btn-chat');
  btn.innerHTML = SVGS['message-circle'] + ' Ouvrir le chat IA';
}

function appendMsg(role, text) {
  const box    = document.getElementById('ia-messages');
  const typing = document.getElementById('ia-typing');
  const init   = '<?= e(strtoupper(substr($user['prenom'], 0, 1))) ?>';
  const div    = document.createElement('div');
  div.className = 'ia-msg ' + role;
  const esc = text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
  div.innerHTML = `
    <div class="ia-msg-avatar" ${role==='bot' ? 'style="background:linear-gradient(135deg,#7C3AED,#4F46E5)"' : ''}>
      ${role==='bot' ? '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/><path d="M19 3v4"/><path d="M21 5h-4"/></svg>' : init}
    </div>
    <div class="ia-msg-bubble">${esc}</div>`;
  box.insertBefore(div, typing);
  box.scrollTop = box.scrollHeight;
}

function showTyping(show) {
  const t = document.getElementById('ia-typing');
  if (t) t.classList.toggle('visible', show);
  const box = document.getElementById('ia-messages');
  if (box) box.scrollTop = box.scrollHeight;
}

async function sendChatMsg() {
  if (iaLoading) return;
  const input = document.getElementById('ia-input');
  const msg   = input?.value?.trim();
  if (!msg) return;
  input.value = '';
  input.style.height = 'auto';
  appendMsg('user', msg);
  chatHistory.push({ role:'user', content:msg });
  iaLoading = true;
  document.getElementById('ia-send').disabled = true;
  showTyping(true);
  try {
    const d = await callIA('chat', {
      message: msg,
      history: JSON.stringify(chatHistory.slice(0, -1))
    });
    showTyping(false);
    if (d.ok) {
      appendMsg('bot', d.content);
      chatHistory.push({ role:'assistant', content:d.content });
      if (chatHistory.length > 20) chatHistory = chatHistory.slice(-20);
    } else {
      appendMsg('bot', '[Erreur] ' + (d.msg || d.error || 'Erreur. Réessaie.'));
    }
  } catch(e) {
    showTyping(false);
    appendMsg('bot', '[Erreur réseau] Vérifie ta connexion.');
  } finally {
    iaLoading = false;
    document.getElementById('ia-send').disabled = false;
  }
}

function sendChip(el) {
  const input = document.getElementById('ia-input');
  if (input) input.value = el.textContent;
  if (document.getElementById('ia-chat').style.display === 'none' ||
      document.getElementById('ia-chat').style.display === '') {
    ouvrirChat();
  }
  setTimeout(sendChatMsg, 50);
}

function iaKeydown(e) {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendChatMsg(); }
}
function autoResize(el) {
  el.style.height = 'auto';
  el.style.height = Math.min(el.scrollHeight, 100) + 'px';
}
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
